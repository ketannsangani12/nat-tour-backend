<?php

namespace App\Services\Booking;

use App\Models\BookingTransact;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\PaymentLinkService;
use App\Services\Booking\TransactionService;
use Illuminate\Support\Facades\Auth;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AddonBookingService
{
    /**
	 * Execute the service to get Price per pax addons calculation for create booking with cart disabled [Price|Fee|Total]
	 *
	 * @param array $fieldData
	 *
	 * @return array
	 */
	public static function calculateFieldAmounts(array $fieldData, string $paymentMethod, int $isCashFees, float $natFeesPercentage = 0): array
    {
        $qty = ($fieldData['unit_type'] ?? '') === 'price_per_unit'
            ? max(1, (int) ($fieldData['value'] ?? 1))
            : 1;

        // This field's OWN price only counts if its own price is actually enabled. A
        // field can carry a stale/leftover `field_price`/`option_price` value even with
        // pricing turned OFF — the JS always serializes the field's configured price
        // regardless of the additional_fee toggle (see components.js's
        // `wrapper.dataset.fee = node.price || 0`) — so this must be checked here,
        // inside the function itself, not left to the caller (a caller-side gate here
        // would have the exact same "parent gates the whole call" bug this function's
        // children-recursion below was already written to avoid — see calculateTotalForCreateBooking()/
        // createBooking()/computeAddonAmounts() for the real-money bug that produced).
        $fieldPriceEnabled  = (($fieldData['field_price_enabled']  ?? 0) == 1);
        $optionPriceEnabled = (($fieldData['option_price_enabled'] ?? 0) == 1);

        $fieldPrice  = $fieldPriceEnabled  ? (float) ($fieldData['field_price']  ?? 0) : 0.0;
        $optionPrice = $optionPriceEnabled ? (float) ($fieldData['option_price'] ?? 0) : 0.0;
        $addonPrice  = ($fieldPrice + $optionPrice) * $qty;
        $addonFee    = 0;

        // Fee is included only for Card or when Cash fees are enabled
        if ($paymentMethod === 'Card' || ($isCashFees === 1 && $paymentMethod === 'Cash')) {
            // Round PER UNIT, then multiply by qty — deliberate policy (2026-08-10, reversing
            // the prior "round on the combined total" convention): a price_per_unit fee is
            // rounded once per unit and multiplied, matching how a price_per_pax fee is
            // necessarily rounded once per traveler (each is its own row) rather than once on
            // a qty-multiplied total. See createAddonTransactionsFromFields() for the same
            // convention applied to what gets stored on the transaction row.
            $fieldFee  = round($fieldPrice  * $natFeesPercentage / 100, 2) * $qty;
            $optionFee = round($optionPrice * $natFeesPercentage / 100, 2) * $qty;
            $addonFee  = $fieldFee + $optionFee;
        }

        $addonTotal = $addonPrice + $addonFee;

        // Handle children recursively — UNCONDITIONALLY. Each child's own
        // field_price_enabled/option_price_enabled is checked inside ITS OWN recursive
        // call above, so a priced grandchild is never skipped just because its
        // immediate parent (this $fieldData, or one of its ancestors) has no price or
        // has pricing disabled.
        if (!empty($fieldData['children']) && is_array($fieldData['children'])) {
            foreach ($fieldData['children'] as $childGroup) {
                foreach ($childGroup as $childFieldData) {
                    if (!is_array($childFieldData)) continue;

                    $childAmounts = self::calculateFieldAmounts($childFieldData, $paymentMethod, $isCashFees, $natFeesPercentage);

                    $addonPrice += $childAmounts['price'];
                    $addonFee   += $childAmounts['fee'];
                    $addonTotal += $childAmounts['total'];
                }
            }
        }

        return [
            'price' => $addonPrice,
            'fee'   => $addonFee,
            'total' => $addonTotal,
        ];
    }

    /**
	 * Execute to integrate add-on fields
	 *
	 * @param array $addons
     * @param int|string $tourBookingId
	 *
	 * @return array
	 */
    private static function insertAddonFieldRecursive($fieldData, $tourBookingId, $formId, $booking, $parentId = null, $extra = [])
    {
        $insertData = [
            'tour_booking_id'       => $tourBookingId,
            'form_id'               => $formId,
            'field_id'              => $fieldData['field_id'] ?? null,
            'field_label'           => $fieldData['field_label'] ?? null,
            'option_id'             => $fieldData['option_id'] ?? null,
            'option_label'          => $fieldData['option_label'] ?? null,
            'rule_id'               => $fieldData['rule_id'] ?? null,
            'parent_id'             => $parentId,
            'value'                 => $fieldData['value'] ?? null,
            'unit_type'             => $fieldData['unit_type'] ?? null,
            'applied_rule_snapshot' => !empty($fieldData['applied_rule_snapshot'])
                                        ? json_encode($fieldData['applied_rule_snapshot'])
                                        : null,
            'operation_at'          => now(),
            'operation_by'          => $booking->created_by,
            'operation_by_user_type'=> $booking->created_by_user_type,
            'created_at'            => now(),
            'updated_at'            => now(),
        ];

        if (!empty($extra['tourBookingTransact'])) {
            // Price per pax: each person counts as 1 unit.
            $insertData['pax_id'] = $extra['tourBookingTransact']->id;
            $insertData['pax_index'] = $extra['paxIndex'] ?? null;
            $insertData['person_ordinal'] = $extra['personOrdinal'] ?? null;
            $insertData['unit'] = 1;
        } elseif (($fieldData['unit_type'] ?? null) === 'price_per_unit') {
            // Price per unit: mirror the user-entered quantity into the unit column.
            $insertData['unit'] = max(1, (int) ($fieldData['value'] ?? 1));
        }

        $insertId = DB::table('tour_booking_addon_fields')->insertGetId($insertData);

        // Recursively handle children
        if (!empty($fieldData['children']) && is_array($fieldData['children'])) {
            foreach ($fieldData['children'] as $childSet) {
                foreach ($childSet as $childData) {
                    self::insertAddonFieldRecursive(
                        $childData,
                        $tourBookingId,
                        $formId,
                        $booking,
                        $insertId,
                        $extra
                    );
                }
            }
        }
    }

    /**
     * A price_per_pax field flagged `display_once` is answered ONCE by the customer (shown in the
     * general add-ons section, not duplicated per traveler) but must still be charged/stored exactly
     * like a normal price_per_pax field — one row per traveler. This expands that single answer into
     * one entry per traveler on every affected rate group, directly on the raw submitted payloads,
     * BEFORE insertPricePerPaxBookingAddons()/insertBookingAddons() run — so neither of those (nor the
     * per-rate amount-calculation loops in TourBookingRepository) need to know this feature exists;
     * they just see ordinary price_per_pax data, one entry per traveler, as always.
     *
     * @param string|null $paxAddonsDataRaw Raw `pax_addons_data` JSON string as submitted (may be null/empty)
     * @param string|null $addonsDataRaw Raw `addons_data` JSON string as submitted (may be null/empty)
     * @param array<int|string,int> $touristsByRateId tour_package_rate_id => traveler count for this booking
     *
     * @return array{pax_addons_data: ?string, addons_data: ?string}
     */
    public static function fanOutDisplayOnceAddons(?string $paxAddonsDataRaw, ?string $addonsDataRaw, array $touristsByRateId): array
    {
        try {
            $addons = ($addonsDataRaw !== null && trim($addonsDataRaw) !== '')
                ? json_decode(trim($addonsDataRaw), true)
                : null;
            if (!is_array($addons)) {
                // Nothing submitted, or malformed JSON — nothing safe to expand; leave untouched.
                return ['pax_addons_data' => $paxAddonsDataRaw, 'addons_data' => $addonsDataRaw];
            }

            // A field lands in addons_data's general bucket with unit_type === 'price_per_pax' ONLY when
            // flagged display_once — AddonFormRepository::getPackageAddonForm() keeps ordinary price_per_pax
            // fields out of this bucket entirely — so that's a sufficient, DB-lookup-free signal.
            $displayOnceFields = [];
            if (!empty($addons['fields']['addons'][0]) && is_array($addons['fields']['addons'][0])) {
                foreach ($addons['fields']['addons'][0] as $fieldKey => $fieldData) {
                    if ($fieldKey === 'uid' || !is_array($fieldData)) continue;
                    if (($fieldData['unit_type'] ?? null) === 'price_per_pax') {
                        $displayOnceFields[$fieldKey] = $fieldData;
                        unset($addons['fields']['addons'][0][$fieldKey]);
                    }
                }
            }

            if (empty($displayOnceFields)) {
                // Nothing flagged — leave both payloads exactly as submitted.
                return ['pax_addons_data' => $paxAddonsDataRaw, 'addons_data' => $addonsDataRaw];
            }

            $paxAddons = ($paxAddonsDataRaw !== null && trim($paxAddonsDataRaw) !== '')
                ? json_decode(trim($paxAddonsDataRaw), true)
                : null;
            $paxAddons = is_array($paxAddons) ? $paxAddons : [];
            $paxAddons['form_id'] = $paxAddons['form_id'] ?? ($addons['form_id'] ?? null);
            $paxAddons['fields'] = $paxAddons['fields'] ?? [];

            foreach ($touristsByRateId as $rateId => $count) {
                if ($count < 1) continue;

                if (!isset($paxAddons['fields'][$rateId]['addons']) || !is_array($paxAddons['fields'][$rateId]['addons'])) {
                    $paxAddons['fields'][$rateId]['addons'] = [];
                }

                // The existing array is SPARSE — collectPaxAddonSelections() (form-reader.js) only
                // pushes an entry for a traveler who actually picked something in the real per-pax
                // modal, so a traveler with NO per-pax selection at all simply has no entry here yet.
                // Each existing entry's own uid still carries its TRUE ordinal in its trailing "-N"
                // segment — 1-based for a genuine per-pax-modal entry (see createPaxDetail()), already
                // 0-based for an earlier fan-out's own placeholder — the SAME dual convention
                // insertPricePerPaxBookingAddons() itself parses these uids with later on. Padding
                // must therefore fill in only the ordinals NOT already claimed by an existing entry,
                // not simply "however many more entries are needed to reach $count": a blind
                // append-by-count (the prior behavior) assumes the existing entries already occupy
                // slots 0..N-1, which silently collides once they don't — e.g. only travelers #3/#4
                // of 4 picked something: the 2 existing entries carry true ordinals 2 and 3, but a
                // count-based pad appended 2 MORE entries also numbered 2 and 3, giving travelers
                // #3/#4 a duplicate row each and leaving travelers #1/#2 with none at all.
                $claimedOrdinals = [];
                foreach ($paxAddons['fields'][$rateId]['addons'] as $existingEntry) {
                    $existingUid = is_array($existingEntry) ? ($existingEntry['uid'] ?? null) : null;
                    if ($existingUid && preg_match('/-(\d+)$/', $existingUid, $m)) {
                        $claimedOrdinals[] = str_starts_with($existingUid, 'display-once-')
                            ? (int) $m[1]
                            : ((int) $m[1]) - 1;
                    }
                }

                for ($i = 0; $i < $count; $i++) {
                    if (in_array($i, $claimedOrdinals, true)) {
                        continue;
                    }
                    $paxAddons['fields'][$rateId]['addons'][] = ['uid' => "display-once-{$rateId}-{$i}"];
                }

                // Every traveler on this rate gets the same answer the customer gave once.
                foreach ($paxAddons['fields'][$rateId]['addons'] as &$paxEntry) {
                    foreach ($displayOnceFields as $fieldKey => $fieldData) {
                        $paxEntry[$fieldKey] = $fieldData;
                    }
                }
                unset($paxEntry);
            }

            return [
                'pax_addons_data' => json_encode($paxAddons),
                'addons_data' => json_encode($addons),
            ];
        } catch (Exception $e) {
            // Never let a malformed/unexpected addon payload break booking creation — fall back to
            // the raw submitted values exactly as if this expansion step didn't run.
            Log::error($e);
            return ['pax_addons_data' => $paxAddonsDataRaw, 'addons_data' => $addonsDataRaw];
        }
    }

    /**
	 * Execute the service to store price per pax addon selection made for the booking [in tour_booking_addon_fields table]
	 *
	 * @param array $addons
     * @param int|string $tourBookingId
	 *
	 * @return array
	 */
	public static function insertPricePerPaxBookingAddons($addons, $tourBookingId): array
    {
        $error = null;
        DB::beginTransaction();

        try {
            $formId = $addons['form_id'] ?? null;
            if (!$formId) throw new Exception('Invalid addon form');

            $paxGroups = $addons['fields'] ?? [];
            $booking = BookingService::booking($tourBookingId);

            // Use tour_slot_id if available, otherwise use tour_package_id
            $prefixId = $booking->tour_slot_id ?? $booking->tour_package_id;

            foreach ($paxGroups as $rateId => $rateData) {
                if (empty($rateData['addons'])) continue;

                $tourBookingTransact = BookingTransact::where([
                    'tour_booking_id' => $tourBookingId,
                    'tour_package_rate_id' => $rateId
                ])->first();

                if (!$tourBookingTransact) {
                    throw new Exception("Booking transaction not found for rate ID: {$rateId}");
                }

                // $rateData['addons'] is SPARSE — the pax modal's collectPaxAddonSelections()
                // (form-reader.js) only pushes a pax entry when it actually has an addon
                // selected, so pax with none simply don't appear here at all. A plain per-entry
                // counter therefore silently compacts gaps: e.g. pax #1 and #3 picked an addon,
                // #2 didn't → this loop used to see 2 entries and number them ordinal 0 and 1,
                // storing pax #3's addon under pax #2's slot. Each entry's own uid carries its
                // TRUE display position as its trailing "-N" segment — prefer that over the
                // counter whenever it's parseable. Two different generators use two different
                // bases for that "-N", so both must be handled: createPaxDetail() (form-reader.js,
                // the real per-pax modal) is 1-based, while fanOutDisplayOnceAddons()'s own
                // "display-once-{rateId}-{i}" padding uid (used when a display_once field's
                // answer is fanned out with no real per-pax entry yet) is already 0-based.
                $personOrdinal = 0; // fallback only, for a legacy/malformed uid with no "-N" suffix
                foreach ($rateData['addons'] as $paxAddon) {
                    $uid = is_array($paxAddon) ? ($paxAddon['uid'] ?? null) : null;
                    $trueOrdinal = null;
                    if ($uid && preg_match('/-(\d+)$/', $uid, $m)) {
                        $trueOrdinal = str_starts_with($uid, 'display-once-')
                            ? (int) $m[1]          // already 0-based
                            : ((int) $m[1]) - 1;    // 1-based suffix from the real per-pax modal
                    }
                    $ordinalToUse = ($trueOrdinal !== null && $trueOrdinal >= 0) ? $trueOrdinal : $personOrdinal;

                    // Generate proper pax_index format: slotId/packageId-timestamp-uniqid-ordinal
                    $paxIndex = $prefixId . '-' . time() . '-' . uniqid() . '-' . $ordinalToUse;

                    foreach ($paxAddon as $fieldKey => $fieldData) {
                        if ($fieldKey === 'uid') continue;

                        self::insertAddonFieldRecursive(
                            $fieldData,
                            $tourBookingId,
                            $formId,
                            $booking,
                            null,
                            [
                                'tourBookingTransact' => $tourBookingTransact,
                                'paxIndex' => $paxIndex,
                                'personOrdinal' => $ordinalToUse
                            ]
                        );
                    }
                    $personOrdinal++;
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            $error = $e->getMessage();
        }

        return ["booking_id" => $tourBookingId, "form" => $formId, "addons" => $addons, "error" => $error];
    }

    /**
	 * Execute the service to store one time & price per unit addon selection made for the booking [in tour_booking_addon_fields table]
	 *
	 * @param array $addons
     * @param int|string $tourBookingId
	 *
	 * @return array
	 */
	public static function insertBookingAddons($addons, $tourBookingId): array
    {
        $error = null;
        DB::beginTransaction();

        try {
            $formId = $addons['form_id'] ?? null;
            if (!$formId) throw new Exception('Invalid addon form');

            $addonGroups = $addons['fields']['addons'] ?? [];
            $booking = BookingService::booking($tourBookingId);

            foreach ($addonGroups as $groupFields) {
                foreach ($groupFields as $fieldData) {
                    self::insertAddonFieldRecursive(
                        $fieldData,
                        $tourBookingId,
                        $formId,
                        $booking
                    );
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            $error = $e->getMessage();
        }

        return ["booking_id" => $tourBookingId, "form" => $formId, "addons" => $addons, "error" => $error];
    }

    /**
     * Resolve which tour_booking_addon_fields ids belong to the pax positions being split off,
     * from the same $checkarrays structure (rate_id => 0-based positions within that rate group)
     * every ExtendBooking*Service already builds from the "check[]" pax selection — no separate
     * "Passenger Add-ons" modal round trip needed to resolve scope.
     *
     * Uses the SAME headcount-aware position derivation as TourBookingAddonRepository::
     * getPaxAddonsIndexes() / DeleteBookingAddonBreakdownService::buildPaxIndexPositionFromSurvivingItems():
     * each pax_index's own person_ordinal (captured once at that add-on's creation/reassignment
     * time) IS its real 0-based slot within the rate group, validated against the group's CURRENT
     * headcount — not "the Nth pax_index that happens to already have an add-on row, counted from
     * 0". A prior version of this method did the latter (plain creation-order enumeration of only
     * the pax_index values with a surviving field row), which silently mismapped every position
     * whenever only SOME pax in the group had add-ons: e.g. add-ons only on the last 4 of 8 pax left
     * just 4 entries at array slots 0..3, so splitting off position 4 (the 5th physical pax — the
     * FIRST one that actually has an add-on) missed it entirely, while positions 0-2 (pax with no
     * add-ons at all) wrongly grabbed the first three addon-owning pax's fields instead.
     *
     * @param int $bookingId
     * @param array $checkarrays rate_id => [0-based position, ...]
     * @return array tour_booking_addon_fields.id list
     */
    public static function resolveSplitAddonFieldIds(int $bookingId, array $checkarrays): array
    {
        $fieldIds = [];

        foreach ($checkarrays as $rateId => $positions) {
            if (empty($positions)) {
                continue;
            }

            $bookingTransact = BookingTransact::where('tour_booking_id', $bookingId)
                ->where('tour_package_rate_id', $rateId)
                ->first();

            if (!$bookingTransact) {
                continue;
            }

            $paxId = $bookingTransact->id;
            $totalTourists = ((int) $bookingTransact->is_infant_rate === 1)
                ? (int) $bookingTransact->infants
                : (int) $bookingTransact->tourists;

            $rows = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('pax_id', $paxId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'pax_index', 'person_ordinal']);

            if ($rows->isEmpty()) {
                continue;
            }

            // One entry per distinct pax_index (several field rows can share one physical pax),
            // same dedup getPaxAddonsIndexes() does.
            $ordinalByPaxIndex = $rows->groupBy('pax_index')
                ->map(fn ($group) => $group->pluck('person_ordinal')->filter(fn ($v) => !is_null($v))->first());

            $paxIndexBySlot = [];
            if ($totalTourists > 0 && !$ordinalByPaxIndex->contains(fn ($v) => is_null($v))) {
                // Every pax_index in this group carries a real person_ordinal — reslot exactly
                // like getPaxAddonsIndexes(): an ordinal that still fits within the CURRENT
                // headcount keeps that EXACT slot; only ordinals that no longer fit (an
                // earlier-ordinal sibling was fully removed and the headcount shrank) are
                // "overflow", compacted, in ordinal order, into whichever slots are still
                // unclaimed.
                $sortedOrdinals = $ordinalByPaxIndex->map(fn ($v) => (int) $v)->sort();
                $overflowPaxIndexes = [];
                foreach ($sortedOrdinals as $paxIndex => $ordinal) {
                    if ($ordinal >= 0 && $ordinal < $totalTourists && !isset($paxIndexBySlot[$ordinal])) {
                        $paxIndexBySlot[$ordinal] = $paxIndex;
                    } else {
                        $overflowPaxIndexes[] = $paxIndex;
                    }
                }
                for ($slot = 0; $slot < $totalTourists && !empty($overflowPaxIndexes); $slot++) {
                    if (!isset($paxIndexBySlot[$slot])) {
                        $paxIndexBySlot[$slot] = array_shift($overflowPaxIndexes);
                    }
                }
            } else {
                // Legacy row(s) with no person_ordinal at all (pre-migration), or an unresolvable
                // headcount — fall back to the old creation-order approximation for this whole
                // rate group rather than mixing two different numbering schemes.
                $slot = 0;
                foreach ($ordinalByPaxIndex->keys() as $paxIndex) {
                    $paxIndexBySlot[$slot++] = $paxIndex;
                }
            }

            $targetPaxIndexes = [];
            foreach ($positions as $position) {
                if (isset($paxIndexBySlot[(int) $position])) {
                    $targetPaxIndexes[] = $paxIndexBySlot[(int) $position];
                }
            }

            if (empty($targetPaxIndexes)) {
                continue;
            }

            $fieldIds = array_merge($fieldIds, TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('pax_id', $paxId)
                ->whereIn('pax_index', $targetPaxIndexes)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all());
        }

        return $fieldIds;
    }

    /**
     * Move pax add-ons selected for split off to the new booking, and settle the money that goes
     * with them: an Add-ons Refund entry on the OLD booking and an Add-ons Payment entry on the
     * NEW booking — mirroring how the tour amount's own refund-on-old/payment-on-new bookkeeping
     * pair already works for a split. No real Stripe call either way: split never refunds the
     * customer, it only re-labels which booking record the already-paid money belongs to, same
     * as the tour amount's own storeRefundInTransaction()/storePaymentInTransaction() pair reusing
     * the SAME transaction_number on both sides.
     *
     * The original 'paid' charge row is left untouched on the OLD booking (paired with the new
     * 'refunded' row here, netting to zero there) rather than moved or deleted — a brand new
     * 'paid' row is written for the NEW booking instead. Both TourBookingAddonRepository::
     * deletePaxAddons()'s own paid-charge lookup and this method's are scoped by tour_booking_id
     * (not just tour_booking_addon_id) specifically so a field's pre-split charge history never
     * gets double-counted by whichever booking currently owns the field.
     *
     * $selectedTransaction carries the transaction/card the admin actually picked in the "Split
     * Booking" card-choice popup (when the booking has multiple card payments to choose from).
     * The NEW booking's Add-ons Payment entry is stamped with THIS transaction/card — same as the
     * tour amount's own new-booking payment entry — rather than each add-on field's own original
     * charge PI, so both entries agree on which payment this split is being attributed to. The OLD
     * booking's refund entry still nets against each field's own original PI (untouched below),
     * since that's the real charge still sitting on the old booking's ledger.
     *
     * @param int $oldBookingId
     * @param int $newBookingId
     * @param array $explicitFieldIds tour_booking_addon_fields.id list (NOT pax_index strings)
     * @param array{transaction_number?: ?string, application_fee_id?: ?string, card_last4?: ?string}|null $selectedTransaction
     * @return void
     * @throws Exception
     */
    public static function splitBookingPaxAddons(int $oldBookingId, int $newBookingId, array $explicitFieldIds, ?array $selectedTransaction = null): void
    {
        if (empty($explicitFieldIds)) {
            return;
        }

        DB::beginTransaction();
        try {
            $oldBooking = BookingService::booking($oldBookingId);
            $newBooking = BookingService::booking($newBookingId);

            $paidTransactions = TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $explicitFieldIds)
                ->where('tour_booking_id', $oldBookingId)
                ->where('status', 'paid')
                ->where('operation', 'charge')
                ->with('addonField')
                ->get();

            // Net out any prior edit-adjustment refunds per field (option downgrades, etc.) —
            // same shape as deletePaxAddons()'s own netting, ABS() handles both the positive
            // convention this method writes and the negative convention ChangePeopleAddonService
            // writes elsewhere. Grouped by PaymentIntent for Card (a split can involve fields
            // originally charged on different PIs, each needing its own settlement) or by payment
            // method otherwise — see the $piKey comment below.
            $piGroups = [];
            foreach ($paidTransactions as $txn) {
                $priorRefunds = TourBookingAddonTransaction::where('tour_booking_addon_id', $txn->tour_booking_addon_id)
                    ->where('tour_booking_id', $oldBookingId)
                    ->where('status', 'refunded')
                    ->where('operation', 'refund')
                    ->get();

                $priorFieldP = 0.0;
                $priorOptP = 0.0;
                $priorFieldF = 0.0;
                $priorOptF = 0.0;
                foreach ($priorRefunds as $r) {
                    $priorFieldP += abs((float) $r->field_price);
                    $priorOptP += abs((float) $r->option_price);
                    $priorFieldF += abs((float) $r->field_fee);
                    $priorOptF += abs((float) $r->option_fee);
                }

                $fieldPrice = max(0.0, (float) $txn->field_price - $priorFieldP);
                $fieldFee = max(0.0, (float) $txn->field_fee - $priorFieldF);
                $optionPrice = max(0.0, (float) $txn->option_price - $priorOptP);
                $optionFee = max(0.0, (float) $txn->option_fee - $priorOptF);

                if ($fieldPrice + $fieldFee + $optionPrice + $optionFee <= 0) {
                    continue;
                }

                // Card rows share one real Stripe PI, so grouping by transaction_id already
                // collapses them into a single history/transaction row. Cash/Invoiced/Wire rows
                // have no PI at all — falling back to the addon field id kept every field in its
                // own group, so splitting N non-card-paid add-ons together wrote N separate
                // refund/payment history entries instead of one combined entry each (the behavior
                // Card already has). Group everything without a real PI by payment method instead,
                // same "one settlement action, one entry" rule refundAddonsByBookingId() already
                // follows.
                $piKey = ($txn->payment_method === 'card' && $txn->transaction_id)
                    ? $txn->transaction_id
                    : ('method_' . ($txn->payment_method ?? 'unknown'));
                $piGroups[$piKey]['transaction_id'] = $txn->transaction_id;
                $piGroups[$piKey]['application_fee_id'] = $txn->application_fee_id;
                $piGroups[$piKey]['payment_method'] = $txn->payment_method;
                $piGroups[$piKey]['rows'][] = [
                    'tour_booking_addon_id' => $txn->tour_booking_addon_id,
                    'field_additional_fee' => $txn->field_additional_fee,
                    'option_additional_fee' => $txn->option_additional_fee,
                    'field_price' => $fieldPrice,
                    'field_fee' => $fieldFee,
                    'option_price' => $optionPrice,
                    'option_fee' => $optionFee,
                    'label' => self::buildAddonHistoryLabel($txn->addonField->field_label ?? null, $txn->addonField->option_label ?? null),
                ];
            }

            // Addons Refund on the OLD booking — written before the field move, so it pairs with
            // the still-in-place original charge row (both tagged to $oldBookingId, netting to 0).
            foreach ($piGroups as $group) {
                [$rows, $history, $price, $fee] = self::buildAddonTransactionRows($group, $oldBookingId, 'refunded', 'refund');

                DB::table('tour_booking_addon_transactions')->insert($rows);

                $bookingAddonProxy = (object) ['payment_method' => $group['payment_method']];
                $transactionValue = [
                    'price' => $price,
                    'fee' => $fee,
                    'transaction_id' => $group['transaction_id'],
                    'application_fee_id' => $group['application_fee_id'],
                ];

                // Gated on $piKey itself being a real PI (only 'card' + a real transaction_id
                // ever produces a non-'method_*' key, per the grouping above) — a Cash/Invoiced/
                // Wire $txn can still carry a non-empty transaction_id without it ever being a
                // genuine Stripe PI with a matching `transactions` row to look up.
                $piRefundMode = !str_starts_with((string) $piKey, 'method_')
                    ? DB::table('transactions')->where('transaction_number', $group['transaction_id'])->value('payment_mode')
                    : PaymentModeResolver::DESTINATION;
                $refundTxId = TransactionService::storeAddonsRefundInTransaction($oldBooking, $bookingAddonProxy, $transactionValue, mode: $piRefundMode);
                HistoryService::storeAddonsRefundInHistory(
                    $oldBooking,
                    $bookingAddonProxy,
                    $transactionValue,
                    $history,
                    'removed',
                    'Split Booking',
                    $refundTxId,
                    (object) ['description' => null]
                );
            }

            // Move field ownership to the new booking.
            $bookingPaxAddons = TourBookingAddonField::where('tour_booking_id', $oldBookingId)
                ->whereIn('id', $explicitFieldIds)
                ->get();
            $oldTransacts = BookingTransact::where('tour_booking_id', $oldBookingId)->get();

            $movedFieldIds = [];
            foreach ($bookingPaxAddons as $paxAddon) {
                foreach ($oldTransacts as $transact) {
                    if ((int) $transact->id === (int) $paxAddon->pax_id) {
                        $newTransact = BookingTransact::where('tour_booking_id', $newBookingId)
                            ->where('tour_package_rate_id', $transact->tour_package_rate_id)
                            ->first();

                        if ($newTransact) {
                            $paxAddon->update([
                                'tour_booking_id' => $newBookingId,
                                'pax_id' => $newTransact->id,
                                'updated_at' => mysqlDT(),
                            ]);
                            $movedFieldIds[] = $paxAddon->id;
                        }
                        break;
                    }
                }
            }

            // Re-point any still-open (unpaid Pay Later) placeholder rows for the fields that were
            // actually moved above onto the new booking too. Unlike the 'paid' rows handled earlier
            // — which stay on the old booking as settled charge history while a fresh 'paid' row is
            // written for the new booking instead — an 'open' row has no money behind it yet: it's
            // just a placeholder waiting for a future "Make Payment", so it simply moves with its
            // field rather than being refunded/recreated. Left unmoved, createAddonTransactionsFromFields()
            // can never find it on a later Make Payment (both its lookups are scoped to the booking's
            // OWN tour_booking_id), so the add-on keeps showing correctly in every amount display/
            // charge total but silently never gets marked paid.
            if (!empty($movedFieldIds)) {
                TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $movedFieldIds)
                    ->where('tour_booking_id', $oldBookingId)
                    ->where('status', 'open')
                    ->where('operation', 'charge')
                    ->update([
                        'tour_booking_id' => $newBookingId,
                        'updated_at' => mysqlDT(),
                    ]);
            }

            // Addons Payment on the NEW booking — stamped with the transaction/card the admin
            // actually picked for this split ($selectedTransaction) when one was given, falling
            // back to each row's own original transaction_id/application_fee_id otherwise (mirrors
            // storePaymentInTransaction() reusing the tour side's transaction_number on the new
            // booking, and ExtendBookingCardService's own multi-transaction "choose card" flow).
            foreach ($piGroups as $piKey => $group) {
                [$rows, $history, $price, $fee] = self::buildAddonTransactionRows($group, $newBookingId, 'paid', 'charge', $selectedTransaction);

                DB::table('tour_booking_addon_transactions')->insert($rows);

                $transactionNumber = $selectedTransaction['transaction_number'] ?? $group['transaction_id'];
                $applicationFeeId  = $selectedTransaction['application_fee_id'] ?? $group['application_fee_id'];
                $cardLast4         = $selectedTransaction['card_last4'] ?? null;

                // No explicit selection (e.g. Cash/Invoiced/Pay Later split callers, which have no
                // card to choose) — fall back to looking up the card actually charged on this
                // field's own PI, same lookup storeAddonsRefundInHistory() already relies on and
                // scoped to the OLD booking for the same reason: a PI's transaction_number gets
                // reused across ancestor/descendant bookings on every split, so an unscoped match
                // can land on a same-numbered row from an unrelated booking instead of this one.
                if (!$cardLast4 && !empty($transactionNumber)) {
                    $cardLast4 = DB::table('tour_booking_history as h')
                        ->join('transactions as t', 't.id', '=', 'h.transaction_id')
                        ->where('h.tour_booking_id', $oldBookingId)
                        ->where('t.transaction_number', $transactionNumber)
                        ->whereNotNull('h.card_last_4_digit')
                        ->where('h.card_last_4_digit', '!=', '')
                        ->orderBy('h.id', 'asc')
                        ->value('h.card_last_4_digit');
                }

                // storeAddonsPaymentInTransaction()/storeAddonsPaymentInHistory() read
                // application_fee_id (and card info) off a real Stripe PaymentIntent's nested
                // ->charges->data[0] shape — split never calls Stripe, so this proxy fakes just
                // enough of that shape to carry the resolved application_fee_id and card last4
                // through, instead of silently dropping them to null.
                $paymentIntentProxy = (object) [
                    'id' => $transactionNumber,
                    'charges' => (object) [
                        'data' => [(object) [
                            'application_fee' => $applicationFeeId,
                            'payment_method_details' => $cardLast4 ? ['card' => ['last4' => $cardLast4]] : null,
                        ]],
                    ],
                ];
                // $transactionNumber can be $selectedTransaction's chosen PI, not necessarily
                // $piKey's own original one — look up mode by the ACTUAL transaction_number this
                // row is being tied to, not the group's original key. Gated on payment_method
                // === 'card' (the item's true original method, never overridden by the
                // card-selection proxy above): non-card split callers never touch Stripe, so
                // DESTINATION (the column's own default) is the correct, inert value for them.
                $chargeMode = ($group['payment_method'] === 'card' && !empty($transactionNumber))
                    ? (DB::table('transactions')->where('transaction_number', $transactionNumber)->value('payment_mode') ?? PaymentModeResolver::DESTINATION)
                    : PaymentModeResolver::DESTINATION;
                $chargeTxId = TransactionService::storeAddonsPaymentInTransaction(
                    $newBooking,
                    $group['payment_method'],
                    $price,
                    $fee,
                    $paymentIntentProxy,
                    mode: $chargeMode
                );
                HistoryService::storeAddonsPaymentInHistory(
                    $newBooking,
                    $history,
                    'added',
                    $paymentIntentProxy,
                    $chargeTxId,
                    'paid',
                    $price,
                    $fee
                );
            }

            DB::commit();
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }

    /**
     * Build tour_booking_addon_transactions insert rows + the matching history line items for one
     * PaymentIntent group, tagged to $bookingId with the given $status/$operation. Shared by both
     * the old-booking refund phase and the new-booking charge phase of splitBookingPaxAddons() so
     * the row shape only needs to be defined once.
     *
     * $selectedTransaction (payment phase only) overrides the transaction_id/application_fee_id
     * stamped on each row with the admin's picked transaction, instead of the group's own original
     * charge PI — see splitBookingPaxAddons()'s own doc comment for why.
     *
     * @return array{0: array, 1: array, 2: float, 3: float} [$rows, $history, $totalPrice, $totalFee]
     */
    private static function buildAddonTransactionRows(array $group, int $bookingId, string $status, string $operation, ?array $selectedTransaction = null): array
    {
        $rows = [];
        $history = [];
        $totalPrice = 0.0;
        $totalFee = 0.0;

        $transactionNumber = $selectedTransaction['transaction_number'] ?? $group['transaction_id'];
        $applicationFeeId  = $selectedTransaction['application_fee_id'] ?? $group['application_fee_id'];

        foreach ($group['rows'] as $row) {
            $rows[] = [
                'tour_booking_id' => $bookingId,
                'tour_booking_addon_id' => $row['tour_booking_addon_id'],
                'payment_method' => $group['payment_method'],
                'status' => $status,
                'operation' => $operation,
                'transaction_id' => $transactionNumber,
                'application_fee_id' => $applicationFeeId,
                'field_additional_fee' => $row['field_additional_fee'],
                'option_additional_fee' => $row['option_additional_fee'],
                'field_price' => $row['field_price'],
                'field_fee' => $row['field_fee'],
                'option_price' => $row['option_price'],
                'option_fee' => $row['option_fee'],
                'notes' => $operation === 'refund' ? 'Split Booking' : null,
                'operation_at' => now(),
                'operation_by' => Auth::id(),
                'operation_by_user_type' => userType(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $rowPrice = $row['field_price'] + $row['option_price'];
            $rowFee = $row['field_fee'] + $row['option_fee'];

            $history[] = [
                'name' => $row['label'],
                'price' => $rowPrice,
                'fee' => $rowFee,
                'payment_method' => $group['payment_method'],
                'status' => $operation === 'refund' ? 'Split Booking' : 'paid',
            ];

            $totalPrice += $rowPrice;
            $totalFee += $rowFee;
        }

        return [$rows, $history, $totalPrice, $totalFee];
    }

    /**
     * Process pax addons (price_per_pax) and other addons (one_time/price_per_unit) in a single pass,
     * producing one consolidated transaction record and one history block.
     *
     * Use this instead of calling insertBookingAddonTransactions + insertBookingAddonTransactionsForOtherAddons
     * separately when the status is 'paid' and you want a single Add-ons history entry.
     */
    public static function insertBookingAddonTransactions(
        int    $tourBookingId,
        mixed  $paymentIntent,
        string $paymentMethod,
        ?string $paxAddonsData,
        ?string $addonsData,
        string $status = 'paid'
    ): void {
        if (empty($paxAddonsData) && empty($addonsData)) {
            return;
        }

        DB::beginTransaction();
        try {
            $resolvedMethod    = config('constants.payment_methods.' . $paymentMethod);
            $isCashFees        = cashFees();
            $applyFees         = $resolvedMethod === 'card' || ($resolvedMethod === 'cash' && $isCashFees === '1');
            $bookingData       = BookingService::booking($tourBookingId);
            $natFeesPercentage = BookingTransact::where('tour_booking_id', $tourBookingId)->value('nat_fees_percentage') ?? 0;
            $allTransactions   = [];
            $history           = [];
            $totalPrice        = 0.0;
            $totalFee          = 0.0;

            // --- Pax addons (price_per_pax) ---
            if (!empty($paxAddonsData) && trim($paxAddonsData) !== '') {
                $paxParsed  = json_decode(trim($paxAddonsData), true);
                $formId     = $paxParsed['form_id'] ?? null;
                $paxGroups  = $paxParsed['fields'] ?? [];

                foreach ($paxGroups as $rateId => $rateData) {
                    if (empty($rateData['addons'])) continue;

                    $tourBookingTransact = BookingTransact::where('tour_booking_id', $tourBookingId)
                        ->where('tour_package_rate_id', $rateId)
                        ->first();

                    if (!$tourBookingTransact) continue;

                    foreach ($rateData['addons'] as $personOrdinal => $paxAddon) {
                        foreach ($paxAddon as $fieldKey => $fieldData) {
                            if ($fieldKey === 'uid') continue;

                            self::buildAddonTransaction(
                                $fieldData, $tourBookingId, $formId, $tourBookingTransact->id,
                                $personOrdinal, $resolvedMethod, $status, $applyFees,
                                $natFeesPercentage, $paymentIntent, $bookingData,
                                $allTransactions, $history, $totalPrice, $totalFee
                            );
                        }
                    }
                }
            }

            // --- Other addons (one_time / price_per_unit) ---
            if (!empty($addonsData) && trim($addonsData) !== '') {
                $otherParsed = json_decode(trim($addonsData), true);
                $formId      = $otherParsed['form_id'] ?? null;
                $groups      = $otherParsed['fields']['addons'] ?? [];

                foreach ($groups as $groupFields) {
                    foreach ($groupFields as $fieldData) {
                        self::buildAddonTransaction(
                            $fieldData, $tourBookingId, $formId, null, null,
                            $resolvedMethod, $status, $applyFees,
                            $natFeesPercentage, $paymentIntent, $bookingData,
                            $allTransactions, $history, $totalPrice, $totalFee
                        );
                    }
                }
            }

            if (!empty($allTransactions)) {
                DB::table('tour_booking_addon_transactions')->insert($allTransactions);

                $transactionId = TransactionService::storeAddonsPaymentInTransaction(
                    $bookingData, $resolvedMethod, $totalPrice, $totalFee, $paymentIntent
                );
                HistoryService::storeAddonsPaymentInHistory(
                    $bookingData, $history, 'added', $paymentIntent, $transactionId, $status, $totalPrice, $totalFee
                );
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
        }
    }

    /**
     * Look up an already-saved tour_booking_addon_fields record and build a transaction row for it.
     * Appends to $userAddonFieldTransactions and accumulates price/fee/history totals.
     *
     * Handles children recursively so nested fields are also transacted.
     */
    private static function buildAddonTransaction(
        array  $fieldData,
        int    $tourBookingId,
        ?int   $formId,
        ?int   $paxId,
        ?int   $personOrdinal,
        string $paymentMethod,
        string $status,
        bool   $applyFees,
        float  $natFeesPercentage,
        mixed  $paymentIntent,
        object $bookingData,
        array  &$userAddonFieldTransactions,
        array  &$history,
        float  &$addonPriceTotal,
        float  &$addonFeeTotal
    ): void {
        $query = DB::table('tour_booking_addon_fields')
            ->where('tour_booking_id', $tourBookingId)
            ->where('form_id', $formId)
            ->where('field_id', $fieldData['field_id'] ?? null)
            ->whereNull('deleted_at');

        if ($paxId !== null) {
            $query->where('pax_id', $paxId)->where('person_ordinal', $personOrdinal);
        } else {
            $query->whereNull('pax_id');
        }

        $addonField = $query->first();

        if (!$addonField) return;

        if (($fieldData['field_price_enabled'] ?? 0) == 1 || ($fieldData['option_price_enabled'] ?? 0) == 1) {
            $qty         = ($fieldData['unit_type'] ?? '') === 'price_per_unit'
                ? max(1, (int) ($fieldData['value'] ?? 1))
                : 1;
            $fieldUnitPrice  = floatval($fieldData['field_price']  ?? 0);
            $optionUnitPrice = floatval($fieldData['option_price'] ?? 0);
            $fieldPrice      = $fieldUnitPrice  * $qty;
            $optionPrice     = $optionUnitPrice * $qty;
            $fieldFee        = $applyFees ? round($fieldUnitPrice  * $natFeesPercentage / 100, 2) * $qty : 0;
            $optionFee       = $applyFees ? round($optionUnitPrice * $natFeesPercentage / 100, 2) * $qty : 0;

            $userAddonFieldTransactions[] = [
                'tour_booking_addon_id'  => $addonField->id,
                'tour_booking_id'        => $tourBookingId,
                'payment_method'         => $paymentMethod,
                'status'                 => $status,
                'operation'              => 'charge',
                'transaction_id'         => $paymentIntent->id ?? null,
                'application_fee_id'     => $paymentIntent->charges->data[0]->application_fee ?? null,
                'field_additional_fee'   => (bool) ($fieldData['field_price_enabled']  ?? 0),
                'option_additional_fee'  => (bool) ($fieldData['option_price_enabled'] ?? 0),
                'field_price'            => $fieldPrice,
                'field_fee'              => $fieldFee,
                'option_price'           => $optionPrice,
                'option_fee'             => $optionFee,
                'operation_at'           => now(),
                'operation_by'           => $bookingData->created_by,
                'operation_by_user_type' => $bookingData->created_by_user_type,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];

            $addonPriceTotal += $fieldPrice + $optionPrice;
            $addonFeeTotal   += $fieldFee   + $optionFee;

            $history[] = [
                'name'           => self::buildAddonHistoryLabel($addonField->field_label, $addonField->option_label),
                'price'          => $fieldPrice + $optionPrice,
                'fee'            => $fieldFee   + $optionFee,
                'payment_method' => $paymentMethod,
                'status'         => $status,
            ];
        }

        // Recurse into children
        if (!empty($fieldData['children']) && is_array($fieldData['children'])) {
            foreach ($fieldData['children'] as $childSet) {
                foreach ($childSet as $childData) {
                    self::buildAddonTransaction(
                        $childData, $tourBookingId, $formId, $paxId, $personOrdinal,
                        $paymentMethod, $status, $applyFees, $natFeesPercentage,
                        $paymentIntent, $bookingData, $userAddonFieldTransactions, $history,
                        $addonPriceTotal, $addonFeeTotal
                    );
                }
            }
        }
    }

    /**
     * Build a human-readable label for an addon history line item — e.g. a "Sweater" field
     * with a "Medium" option selected must read "Sweater (Medium)", not a bare "Sweater" that
     * silently drops which option (size, color, quantity, etc.) was actually charged. Matches
     * the SAME "Field Label (Option Label)" convention ChangePeopleAddonService::buildAddonLabel()
     * already documents and applies for the Change People side of add-ons; this booking-creation
     * path (buildAddonTransaction() above) never adopted it and used $addonField->field_label
     * alone, so a size/option choice was stored correctly on tour_booking_addon_fields but never
     * showed up anywhere the resulting Payment history entry is displayed (History & Notes, etc).
     */
    private static function buildAddonHistoryLabel(?string $fieldLabel, ?string $optionLabel): string
    {
        $fieldLabel = $fieldLabel ?: 'Add-on';
        return $optionLabel ? "{$fieldLabel} ({$optionLabel})" : $fieldLabel;
    }

    /**
     * Settle every add-on charge still owed on a booking when a "Make a Payment" happens
     * (Pay Later -> Card/Cash/Invoiced). Two distinct sources of unpaid add-ons exist:
     *
     * 1. Fields with NO transaction row at all yet — inserts a fresh 'paid' row, price/fee
     *    derived from addon_fields/addon_field_options (legacy path, kept as-is).
     * 2. Fields with an existing 'open'/'pay later' placeholder row (created immediately
     *    when the add-on was added via TourBookingAddonRepository::chargeAddonFields()) —
     *    these were previously invisible to this method entirely, because its old
     *    whereNotExists() filter excluded ANY field with an existing transaction row,
     *    'open' or not. That silently left every add-on added before payment permanently
     *    unpaid and untraceable (confirmed via booking 65517: $80 of add-ons stayed
     *    status='open' after a $140 Cash "Make Payment" that only covered the tour price).
     *    These rows are UPDATED in place (not duplicated) — the price was already correct
     *    at add-time, only status/payment_method/transaction_id/fee need resolving now that
     *    the real payment method is known.
     */
    public static function createAddonTransactionsFromFields(
        int    $bookingId,
        mixed  $paymentIntent,
        string $paymentMethod,
        bool   $applyFees
    ): void {
        $natFeesPercentage = BookingTransact::where('tour_booking_id', $bookingId)
            ->value('nat_fees_percentage') ?? 0;

        $history    = [];
        $totalPrice = 0.0;
        $totalFee   = 0.0;

        // A field can have no transaction row yet for two very different reasons: it's a genuine
        // fresh selection covered by whatever payment just happened (the case this method exists
        // for), or it's sitting on an Unpaid remaining_payment link item (sent via "Payment Link")
        // that the customer hasn't actually paid yet — e.g. just moved here by a booking split's
        // AddonBookingService::splitBookingPaxAddons() + PaymentLinkService::
        // movePendingAddonPaymentLinkItems(), or never touched at all. Settling the latter as
        // 'paid' here would mark it paid before any money actually changed hands.
        $pendingLinkFieldIds = PaymentLinkService::pendingAddonFieldIdsForBooking($bookingId);

        // --- Source 1: fields with no transaction row at all yet ---
        $freshFieldRows = DB::table('tour_booking_addon_fields as tbaf')
            ->join('addon_fields as af', 'af.id', '=', 'tbaf.field_id')
            ->leftJoin('addon_field_options as afo', 'afo.id', '=', 'tbaf.option_id')
            ->where('tbaf.tour_booking_id', $bookingId)
            ->whereNull('tbaf.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tour_booking_addon_transactions')
                    ->whereColumn('tour_booking_addon_transactions.tour_booking_addon_id', 'tbaf.id')
                    ->whereNull('tour_booking_addon_transactions.deleted_at');
            })
            ->when(!empty($pendingLinkFieldIds), fn ($q) => $q->whereNotIn('tbaf.id', $pendingLinkFieldIds))
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('af.additional_fee', 1)->where('af.price', '>', 0);
                })->orWhere(function ($q3) {
                    $q3->where('afo.additional_fee', 1)->where('afo.price', '>', 0);
                });
            })
            ->select([
                'tbaf.id as addon_field_id',
                'tbaf.unit_type',
                'tbaf.value',
                'tbaf.field_label',
                'tbaf.option_label',
                'tbaf.operation_by',
                'tbaf.operation_by_user_type',
                'af.additional_fee as field_additional_fee',
                'af.price as field_price',
                'afo.additional_fee as option_additional_fee',
                'afo.price as option_price',
            ])
            ->get();

        $insertRows = [];
        foreach ($freshFieldRows as $row) {
            $fieldPrice  = ($row->field_additional_fee  && $row->field_price  > 0) ? (float) $row->field_price  : 0.0;
            $optionPrice = ($row->option_additional_fee && $row->option_price > 0) ? (float) $row->option_price : 0.0;

            if ($fieldPrice <= 0 && $optionPrice <= 0) continue;

            $qty        = ($row->unit_type === 'price_per_unit') ? max(1, (int) $row->value) : 1;
            // Round PER UNIT, then multiply by qty — deliberate policy (2026-08-10, reversing
            // the prior "round on the combined total" convention). See calculateFieldAmounts()
            // for the same convention applied to the Stripe PaymentIntent amount — both must
            // stay consistent with each other, or the stored fee disagrees with what was
            // actually charged.
            $fieldFee   = $applyFees ? round($fieldPrice  * $natFeesPercentage / 100, 2) * $qty : 0;
            $optionFee  = $applyFees ? round($optionPrice * $natFeesPercentage / 100, 2) * $qty : 0;
            $rowPrice   = ($fieldPrice + $optionPrice) * $qty;
            $rowFee     = $fieldFee + $optionFee;

            $insertRows[] = [
                'tour_booking_addon_id'  => $row->addon_field_id,
                'tour_booking_id'        => $bookingId,
                'payment_method'         => $paymentMethod,
                'status'                 => 'paid',
                'operation'              => 'charge',
                'transaction_id'         => $paymentIntent->id ?? null,
                'application_fee_id'     => $paymentIntent->charges->data[0]->application_fee ?? null,
                'field_additional_fee'   => $row->field_additional_fee ? 1 : 0,
                'option_additional_fee'  => $row->option_additional_fee ? 1 : 0,
                'field_price'            => $fieldPrice * $qty,
                'field_fee'              => $fieldFee,
                'option_price'           => $optionPrice * $qty,
                'option_fee'             => $optionFee,
                'operation_at'           => now(),
                'operation_by'           => $row->operation_by ?? 0,
                'operation_by_user_type' => $row->operation_by_user_type,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];

            $label     = $row->field_label ?? '';
            $optLabel  = $row->option_label ?? '';
            $history[] = [
                'name'           => $label . ($optLabel ? " ({$optLabel})" : ''),
                'price'          => $rowPrice,
                'fee'            => $rowFee,
                'quantity'       => $qty,
                'payment_method' => $paymentMethod,
                'status'         => 'paid',
            ];

            $totalPrice += $rowPrice;
            $totalFee   += $rowFee;
        }

        // --- Source 2: fields with an existing 'open' placeholder transaction row ---
        $openTransactionRows = DB::table('tour_booking_addon_transactions as tbat')
            ->join('tour_booking_addon_fields as tbaf', 'tbaf.id', '=', 'tbat.tour_booking_addon_id')
            ->where('tbat.tour_booking_id', $bookingId)
            ->where('tbat.status', 'open')
            ->where('tbat.operation', 'charge')
            ->whereNull('tbat.deleted_at')
            ->whereNull('tbaf.deleted_at')
            ->select([
                'tbat.id as transaction_id',
                'tbat.field_price',
                'tbat.option_price',
                'tbat.field_additional_fee',
                'tbat.option_additional_fee',
                'tbaf.unit_type',
                'tbaf.unit',
                'tbaf.field_label',
                'tbaf.option_label',
            ])
            ->get();

        $applicationFeeId = $paymentIntent->charges->data[0]->application_fee ?? null;
        foreach ($openTransactionRows as $row) {
            $fieldPrice  = (float) $row->field_price;
            $optionPrice = (float) $row->option_price;

            if ($fieldPrice <= 0 && $optionPrice <= 0) continue;

            // tbat.field_price/option_price were stored already qty-multiplied (unit price ×
            // qty) at 'open'-row creation time — back out the per-unit price so the fee can be
            // rounded per unit then multiplied, consistent with calculateFieldAmounts().
            $qty = ($row->unit_type === 'price_per_unit') ? max(1, (int) $row->unit) : 1;
            $fieldUnitPrice  = $qty > 0 ? $fieldPrice  / $qty : $fieldPrice;
            $optionUnitPrice = $qty > 0 ? $optionPrice / $qty : $optionPrice;

            $fieldFee  = $applyFees ? round($fieldUnitPrice  * $natFeesPercentage / 100, 2) * $qty : 0;
            $optionFee = $applyFees ? round($optionUnitPrice * $natFeesPercentage / 100, 2) * $qty : 0;
            $rowPrice  = $fieldPrice + $optionPrice;
            $rowFee    = $fieldFee + $optionFee;

            DB::table('tour_booking_addon_transactions')->where('id', $row->transaction_id)->update([
                'status'             => 'paid',
                'payment_method'     => $paymentMethod,
                'transaction_id'     => $paymentIntent->id ?? null,
                'application_fee_id' => $applicationFeeId,
                'field_fee'          => $fieldFee,
                'option_fee'         => $optionFee,
                'updated_at'         => now(),
            ]);

            $label     = $row->field_label ?? '';
            $optLabel  = $row->option_label ?? '';
            $history[] = [
                'name'           => $label . ($optLabel ? " ({$optLabel})" : ''),
                'price'          => $rowPrice,
                'fee'            => $rowFee,
                'quantity'       => 1,
                'payment_method' => $paymentMethod,
                'status'         => 'paid',
            ];

            $totalPrice += $rowPrice;
            $totalFee   += $rowFee;
        }

        if (empty($insertRows) && empty($history)) return;

        if (!empty($insertRows)) {
            DB::table('tour_booking_addon_transactions')->insert($insertRows);
        }

        $booking = TourBooking::find($bookingId);
        if (!$booking) return;

        // $paymentIntent is only ever a real Stripe object for a genuine card charge (see this
        // method's callers) — non-card methods never touch Stripe, so DESTINATION (the column's
        // own default) is the correct, inert value for them.
        $chargeMode = $paymentIntent
            ? PaymentModeInference::fromStripeObject($paymentIntent)
            : PaymentModeResolver::DESTINATION;
        $transId = TransactionService::storeAddonsPaymentInTransaction(
            $booking,
            $paymentMethod,
            $totalPrice,
            $totalFee,
            $paymentIntent,
            mode: $chargeMode
        );

        HistoryService::storeAddonsPaymentInHistory(
            $booking,
            $history,
            'added',
            $paymentIntent,
            $transId,
            'paid',
            $totalPrice,
            $totalFee
        );
    }
}