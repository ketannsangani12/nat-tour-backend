<?php

namespace App\Services\Booking;

use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\AddonField;
use App\Models\AddonCategory;
use App\Models\AddonFieldOption;
use App\Models\BookingTransact;
use App\Models\TourPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ChangePeopleAddonService
{
    /**
     * Process all addon changes from the change people request
     * NOTE: This method runs within the existing transaction from changePeopleBooking()
     * Do NOT wrap in additional transaction as it will break atomicity
     *
     * The Stripe charge/refund itself stays a SINGLE combined operation (tour + add-on money in
     * one PaymentIntent/refund) — but the `transactions` ledger and `tour_booking_history` each get
     * TWO rows for it: one tour-only, one add-on-only (charge_type='addons'), same as the existing
     * one_time/price_per_unit convention at booking creation. Both rows reference the same Stripe
     * PaymentIntent/application fee; they just split the ledger amount cleanly in two so the two
     * pieces of money are never double-counted against each other.
     *
     * @param object $request The HTTP request containing addon data
     * @param int $bookingId The booking ID
     * @param array $paxIndexToTransactMap Map of pax_index to newly created transact IDs (optional)
     * @param array $financialContext Money-settlement context for priced add-ons, resolved by the
     *        caller from the SAME combined charge/refund decision made for the tour-amount delta:
     *        [
     *          'action'              => 'charge'|'refund'|'none',
     *          'payment_method'      => 'Card'|'Cash'|'Invoiced'|...,
     *          'status'              => 'paid'|'open' (optional, derived from payment_method if omitted),
     *          'ledger_transaction_id' => the tour-side `transactions`.id for the combined charge/
     *              refund — used to derive transaction_id/application_fee_id accurately (and avoid
     *              a redundant Stripe retrieve call) for the add-on's OWN separate ledger row.
     *          'transaction_id'      => Stripe PaymentIntent id, used when ledger_transaction_id
     *              is not available,
     *          'application_fee_id'  => Stripe application fee id (optional, same fallback as above),
     *          'refund_type'         => 'full_refund'|'ticket_refund'|'partial_refund'|"dont_refund" (optional),
     *          'execute_stripe_refund' => bool (default false) — set true ONLY when there is no
     *              existing combined Stripe operation to settle against (e.g. deletePeopleChangePeople(),
     *              which issues its own per-rate-group refund) — this method will then actually call
     *              Stripe for the add-on's own price(+fee) BEFORE booking the refund rows. Leave
     *              false for the normal changePeopleBooking() flow, where the combined charge/refund
     *              already happened and this method only needs to book the ledger split.
     *          'precomputed_delta'   => a calculatePricedAddonDelta() result the caller already
     *              computed (e.g. to merge into the tour-amount total before this method runs) —
     *              reuse it instead of recomputing, so the tour-only ledger math the caller derived
     *              from it and the amount actually settled here can never drift apart.
     *        ]
     * @return array Result with success status and details
     */
    public static function processAddons($request, int $bookingId, array $paxIndexToTransactMap = [], array $financialContext = []): array
    {
        $addedAddons = $request->addons_added ?? [];
        $updatedAddons = $request->addons_updated ?? [];
        $removedAddons = $request->addons_removed ?? [];

        if (empty($addedAddons) && empty($updatedAddons) && empty($removedAddons)) {
            return [
                'success' => true,
                'message' => 'No addon changes to process',
                'added' => 0,
                'updated' => 0,
                'removed' => 0,
            ];
        }

        try {
            $booking = TourBooking::findOrFail($bookingId);

            // If the caller already priced this exact delta (e.g. to merge it into the tour-amount
            // total BEFORE this method runs), reuse it instead of recomputing — two independent
            // calculatePricedAddonDelta() calls could resolve slightly different natFeesPercentage/
            // applyFees values depending on their data source, which would silently split the
            // "tour-only" ledger math from the amount actually settled here.
            if (isset($financialContext['precomputed_delta'])) {
                $delta = $financialContext['precomputed_delta'];
            } else {
                // Price the delta BEFORE any field rows are written/deleted, since removed/updated
                // items need to resolve their OLD price from the still-existing tour_booking_addon_fields row.
                $natFeesPercentage = BookingTransact::where('tour_booking_id', $bookingId)
                    ->where('deleted_by', 0)
                    ->value('nat_fees_percentage') ?? 0;
                // Payment method is known up-front (it's a form field) even when the caller hasn't
                // resolved full financial settlement context yet (transaction_id/action are only known
                // once the real Stripe call has run) — fall back to the request so the fee is priced
                // correctly into $delta regardless of when settlement actually happens.
                $methodLower = strtolower($financialContext['payment_method'] ?? $request->payment_method ?? '');
                $isCashFees = cashFees();
                $applyFees = $methodLower === 'card' || ($methodLower === 'cash' && $isCashFees === '1');

                $delta = self::calculatePricedAddonDelta($addedAddons, $updatedAddons, $removedAddons, (float) $natFeesPercentage, $applyFees);
            }

            $dbTransactMap = self::buildTransactMap($bookingId, $addedAddons);
            $paxIndexToTransactMap = array_merge($dbTransactMap, $paxIndexToTransactMap);

            $addedCount = 0;
            $updatedCount = 0;
            $removedCount = 0;
            $newFieldDbIdMap = [];

            if (!empty($removedAddons)) {
                $removedCount = self::removePaxAddons($removedAddons, $bookingId);
            }

            if (!empty($updatedAddons)) {
                $updatedCount = self::updatePaxAddons($updatedAddons, $booking);
            }

            // Partly-paid fields now exactly covered by what was paid: drop their pending link line.
            if (!empty($delta['pending_link_drop_db_ids'])) {
                PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields(
                    $bookingId,
                    $delta['pending_link_drop_db_ids'],
                    $delta['pending_link_drop_db_ids'],
                    true
                );
            }
            // Never-paid fields moved to a cheaper/free selection: reprice their pending line from
            // the selection just saved above (a line repriced to $0 is dropped).
            if (!empty($delta['pending_link_reprice_db_ids'])) {
                PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields(
                    $bookingId,
                    $delta['pending_link_reprice_db_ids'],
                    [],
                    true
                );
            }

            // The unpaid pending amount(s) taken off the link: a $0 "Don't Refund" history line,
            // same blank-payment-method convention as the Pay Later add-on removal entries.
            if (!empty($delta['unpaid_dont_refund_items'])) {
                try {
                    HistoryService::storeAddonsRefundInHistory(
                        $booking,
                        (object) ['payment_method' => ''],
                        ['price' => 0, 'fee' => 0, 'transaction_id' => null, 'application_fee_id' => null],
                        $delta['unpaid_dont_refund_items'],
                        'removed',
                        Constants::ADDONS_DONT_REFUND,
                        null,
                        (object) ['description' => null]
                    );
                } catch (Exception $ex) {
                    // History is informational -- never fail the edit over it.
                }
            }

            if (!empty($addedAddons)) {
                $addResult = self::addPaxAddons($addedAddons, $booking, $paxIndexToTransactMap);
                $addedCount = $addResult['count'];
                $newFieldDbIdMap = $addResult['map'];
            }

            if (($financialContext['action'] ?? 'none') !== 'none' && (!empty($delta['chargeItems']) || !empty($delta['refundItems']))) {
                self::settleAddonTransactions($delta, $financialContext, $booking, $newFieldDbIdMap);
            }

            return [
                'success' => true,
                'message' => 'Addon changes processed successfully',
                'added' => $addedCount,
                'updated' => $updatedCount,
                'removed' => $removedCount,
                'addon_delta' => $delta,
                'field_db_id_map' => $newFieldDbIdMap,
            ];
        } catch (Exception $e) {
            Log::error('ChangePeopleAddonService@processAddons failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Add new pax addons for newly added tourists
     * Uses two-pass approach: parents first, then children with proper inheritance
     *
     * @param array $addedAddons Array of addon data to add
     * @param TourBooking $booking The booking model
     * @param array $paxIndexToTransactMap Map of pax_index to transact IDs
     * @return array{count: int, map: array<string,int>} Count of addons added and a
     *         "field_id_pax_index" => new tour_booking_addon_fields.id map (needed by the
     *         financial settlement step to attach transactions to the freshly-inserted rows).
     *         Pricing/transaction creation is NOT done here — see settleAddonTransactions().
     */
    protected static function addPaxAddons(array $addedAddons, TourBooking $booking, array $paxIndexToTransactMap): array
    {
        $count = 0;
        $fieldIdToDbIdMap = [];
        $parentDataMap = [];

        $parents = [];
        $children = [];
        foreach ($addedAddons as $addon) {
            if (!empty($addon['parent_id'])) {
                $children[] = $addon;
            } else {
                $parents[] = $addon;
            }
        }

        foreach ($parents as $addon) {
            $formId = $addon['form_id'] ?? 0;
            $fieldId = $addon['field_id'] ?? null;
            $paxIndex = $addon['pax_index'] ?? null;

            if (!$fieldId) {
                continue;
            }

            $paxId = self::resolvePaxId($addon, $paxIndexToTransactMap);
            $personOrdinal = self::resolvePersonOrdinal($addon);
            $unitType = $addon['unit_type'] ?? 'price_per_pax';
            $resolvedCategory = self::getFieldCategory($fieldId);
            $categoryId = $addon['category_id'] ?? $resolvedCategory['category_id'];
            $categoryName = $addon['category_name'] ?? $resolvedCategory['category_name'];
            $resolvedPackage = self::getFieldPackage($fieldId);
            $packageId = $addon['package_id'] ?? $resolvedPackage['package_id'];
            $packageName = $addon['package_name'] ?? $resolvedPackage['package_name'];

            $record = TourBookingAddonField::create([
                'tour_booking_id' => $booking->id,
                'form_id' => $formId,
                'field_id' => $fieldId,
                'field_label' => $addon['field_label'] ?? self::getFieldLabel($fieldId),
                'option_id' => $addon['option_id'] ?? null,
                'option_label' => self::getOptionLabel($addon['option_id'] ?? null) ?? ($addon['option_label'] ?? null),
                'rule_id' => $addon['rule_id'] ?? null,
                'parent_id' => null,
                'value' => $addon['value'] ?? null,
                'pax_id' => $paxId,
                'pax_index' => $paxIndex,
                'person_ordinal' => $personOrdinal,
                'unit_type' => $unitType,
                'unit' => $addon['unit'] ?? 1,
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'package_id' => $packageId,
                'package_name' => $packageName,
                'applied_rule_snapshot' => is_array($addon['applied_rule_snapshot'] ?? null)
                    ? json_encode($addon['applied_rule_snapshot'])
                    : ($addon['applied_rule_snapshot'] ?? null),
                'operation_at' => now(),
                'operation_by' => $booking->created_by ?? Auth::id() ?? 0,
                'operation_by_user_type' => $booking->created_by_user_type ?? self::getUserType(),
            ]);

            $mapKey = $fieldId . '_' . $paxIndex;
            $fieldIdToDbIdMap[$mapKey] = $record->id;
            $parentDataMap[$mapKey] = [
                'db_id' => $record->id,
                'unit_type' => $unitType,
                'pax_id' => $paxId,
                'person_ordinal' => $personOrdinal,
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'package_id' => $packageId,
                'package_name' => $packageName,
            ];

            $count++;
        }

        $childrenByParent = [];
        foreach ($children as $addon) {
            $parentFieldId = $addon['parent_id'];
            $paxIndex = $addon['pax_index'] ?? null;
            $parentKey = $parentFieldId . '_' . $paxIndex;
            if (!isset($childrenByParent[$parentKey])) {
                $childrenByParent[$parentKey] = [];
            }
            $childrenByParent[$parentKey][] = $addon;
        }

        foreach ($childrenByParent as $parentKey => $childAddons) {
            $parentDbId = $fieldIdToDbIdMap[$parentKey] ?? null;
            $parentData = $parentDataMap[$parentKey] ?? null;

            if (!$parentDbId) {
                list($parentFieldId, $paxIndex) = explode('_', $parentKey, 2);
                $existingParent = TourBookingAddonField::where('tour_booking_id', $booking->id)
                    ->where('field_id', $parentFieldId)
                    ->where('pax_index', $paxIndex)
                    ->first();

                if ($existingParent) {
                    $parentDbId = $existingParent->id;
                    $parentData = [
                        'db_id' => $existingParent->id,
                        'unit_type' => $existingParent->unit_type,
                        'pax_id' => $existingParent->pax_id,
                        'person_ordinal' => $existingParent->person_ordinal,
                        'category_id' => $existingParent->category_id,
                        'category_name' => $existingParent->category_name,
                        'package_id' => $existingParent->package_id,
                        'package_name' => $existingParent->package_name,
                    ];
                }
            }

            $childRuleId = null;
            $childAppliedRuleSnapshot = null;
            foreach ($childAddons as $childAddon) {
                if (!empty($childAddon['rule_id'])) {
                    $childRuleId = $childAddon['rule_id'];
                    $childAppliedRuleSnapshot = $childAddon['applied_rule_snapshot'] ?? null;
                    break;
                }
            }

            if ($parentDbId && $childRuleId) {
                $snapshotValue = is_array($childAppliedRuleSnapshot)
                    ? json_encode($childAppliedRuleSnapshot)
                    : $childAppliedRuleSnapshot;

                TourBookingAddonField::where('id', $parentDbId)->update([
                    'rule_id' => $childRuleId,
                    'applied_rule_snapshot' => $snapshotValue,
                ]);
            }

            foreach ($childAddons as $addon) {
                $formId = $addon['form_id'] ?? 0;
                $fieldId = $addon['field_id'] ?? null;
                $paxIndex = $addon['pax_index'] ?? null;

                if (!$fieldId) {
                    continue;
                }

                $paxId = self::resolvePaxId($addon, $paxIndexToTransactMap);
                if (!$paxId && $parentData) {
                    $paxId = $parentData['pax_id'];
                }

                $personOrdinal = self::resolvePersonOrdinal($addon);
                if (!$personOrdinal && $parentData) {
                    $personOrdinal = $parentData['person_ordinal'];
                }

                $unitType = $addon['unit_type'] ?? null;
                if (!$unitType && $parentData) {
                    $unitType = $parentData['unit_type'];
                }
                if (!$unitType) {
                    $unitType = 'price_per_pax';
                }

                $resolvedCategory = self::getFieldCategory($fieldId);
                $categoryId = $addon['category_id'] ?? $resolvedCategory['category_id'] ?? ($parentData['category_id'] ?? null);
                $categoryName = $addon['category_name'] ?? $resolvedCategory['category_name'] ?? ($parentData['category_name'] ?? null);
                $resolvedPackage = self::getFieldPackage($fieldId);
                $packageId = $addon['package_id'] ?? $resolvedPackage['package_id'] ?? ($parentData['package_id'] ?? null);
                $packageName = $addon['package_name'] ?? $resolvedPackage['package_name'] ?? ($parentData['package_name'] ?? null);

                $record = TourBookingAddonField::create([
                    'tour_booking_id' => $booking->id,
                    'form_id' => $formId,
                    'field_id' => $fieldId,
                    'field_label' => $addon['field_label'] ?? self::getFieldLabel($fieldId),
                    'option_id' => $addon['option_id'] ?? null,
                    'option_label' => self::getOptionLabel($addon['option_id'] ?? null) ?? ($addon['option_label'] ?? null),
                    'rule_id' => null,
                    'parent_id' => $parentDbId,
                    'value' => $addon['value'] ?? null,
                    'pax_id' => $paxId,
                    'pax_index' => $paxIndex,
                    'person_ordinal' => $personOrdinal,
                    'unit_type' => $unitType,
                    'unit' => $addon['unit'] ?? 1,
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'package_id' => $packageId,
                    'package_name' => $packageName,
                    'applied_rule_snapshot' => null,
                    'operation_at' => now(),
                    'operation_by' => $booking->created_by ?? Auth::id() ?? 0,
                    'operation_by_user_type' => $booking->created_by_user_type ?? self::getUserType(),
                ]);

                $childMapKey = $fieldId . '_' . $paxIndex;
                $fieldIdToDbIdMap[$childMapKey] = $record->id;

                $count++;
            }
        }

        return ['count' => $count, 'map' => $fieldIdToDbIdMap];
    }

    /**
     * Resolve pax_id from addon data - pax_id should be the transact ID
     * Priority: existing pax_id > paxIndexToTransactMap lookup
     *
     * @param array $addon
     * @param array $paxIndexToTransactMap Map of pax_index to newly created transact IDs
     * @return int|null
     */
    protected static function resolvePaxId(array $addon, array $paxIndexToTransactMap): ?int
    {
        if (!empty($addon['pax_id'])) {
            return (int) $addon['pax_id'];
        }

        $personOrdinal = $addon['person_ordinal'] ?? null;
        $rateGroupId = $addon['rate_group_id'] ?? null;
        $paxIndex = $addon['pax_index'] ?? null;

        $mapKey = null;

        if (is_numeric($personOrdinal)) {
            $mapKey = $rateGroupId . '_' . $personOrdinal;
        } elseif ($paxIndex && preg_match('/-(\d+)$/', $paxIndex, $matches)) {
            $mapKey = $rateGroupId . '_' . $matches[1];
        }

        if ($mapKey && !empty($paxIndexToTransactMap) && isset($paxIndexToTransactMap[$mapKey])) {
            return $paxIndexToTransactMap[$mapKey];
        }

        if ($rateGroupId && !empty($paxIndexToTransactMap)) {
            $prefix = $rateGroupId . '_';
            foreach ($paxIndexToTransactMap as $key => $transactId) {
                if (strpos($key, $prefix) === 0) {
                    return $transactId;
                }
            }
        }

        return null;
    }

    /**
     * Resolve person_ordinal from addon data
     *
     * @param array $addon
     * @return string|null
     */
    protected static function resolvePersonOrdinal(array $addon): ?string
    {
        // Not !empty(): person_ordinal is documented as the traveler's 0-based
        // position, so the first traveler's legitimate value is 0, which
        // !empty() treats as falsy and would wrongly discard.
        if (isset($addon['person_ordinal']) && $addon['person_ordinal'] !== '') {
            return $addon['person_ordinal'];
        }

        if (!empty($addon['rate_for'])) {
            $paxIndex = $addon['pax_index'] ?? 0;
            $ordinalNum = is_numeric($paxIndex) ? ((int) $paxIndex + 1) : 1;
            return $addon['rate_for'] . ' ' . $ordinalNum;
        }

        return null;
    }

    /**
     * Build pax_index to transact_id map by querying existing transacts
     * Groups by rate_group_id and assigns pax_index based on order
     *
     * @param int $bookingId The booking ID
     * @param array $addedAddons Array of addon data to extract rate_group_ids from
     * @return array Map of "rateGroupId_paxIndex" => transact_id
     */
    protected static function buildTransactMap(int $bookingId, array $addedAddons): array
    {
        $rateGroupIds = [];
        foreach ($addedAddons as $addon) {
            if (!empty($addon['rate_group_id'])) {
                $rateGroupIds[$addon['rate_group_id']] = true;
            }
        }

        if (empty($rateGroupIds)) {
            return [];
        }

        $transacts = DB::table('tour_bookings_transact')
            ->where('tour_booking_id', $bookingId)
            ->whereIn('tour_package_rate_id', array_keys($rateGroupIds))
            ->where('deleted_by', 0)
            ->orderBy('id', 'asc')
            ->get(['id', 'tour_package_rate_id', 'tourists', 'is_infant_rate', 'infants']);

        $map = [];

        foreach ($transacts as $transact) {
            $rateId = $transact->tour_package_rate_id;
            $touristCount = $transact->is_infant_rate ? ($transact->infants ?? 0) : ($transact->tourists ?? 1);

            for ($i = 0; $i < $touristCount; $i++) {
                $mapKey = $rateId . '_' . $i;
                $map[$mapKey] = $transact->id;
            }
        }

        return $map;
    }

    /**
     * Update existing pax addons
     *
     * @param array $updatedAddons Array of addon data to update
     * @param TourBooking $booking The booking model
     * @return int Number of addons updated
     */
    protected static function updatePaxAddons(array $updatedAddons, TourBooking $booking): int
    {
        $count = 0;

        foreach ($updatedAddons as $addon) {
            $dbId = $addon['db_id'] ?? null;

            if (!$dbId) {
                continue;
            }

            $existing = TourBookingAddonField::where('id', $dbId)
                ->where('tour_booking_id', $booking->id)
                ->first();

            if (!$existing) {
                continue;
            }

            $newOptionId = $addon['option_id'] ?? $existing->option_id;
            $existing->update([
                'option_id' => $newOptionId,
                'option_label' => self::getOptionLabel($newOptionId) ?? $existing->option_label,
                'value' => $addon['value'] ?? $existing->value,
                'unit_type' => $addon['unit_type'] ?? $existing->unit_type,
                'unit' => $addon['unit'] ?? $existing->unit,
                'rule_id' => $addon['rule_id'] ?? $existing->rule_id,
                'applied_rule_snapshot' => is_array($addon['applied_rule_snapshot'] ?? null)
                    ? json_encode($addon['applied_rule_snapshot'])
                    : ($addon['applied_rule_snapshot'] ?? $existing->applied_rule_snapshot),
                'operation_at' => now(),
                'operation_by' => $booking->created_by ?? Auth::id() ?? 0,
                'operation_by_user_type' => $booking->created_by_user_type ?? self::getUserType(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Remove pax addons for deleted tourists
     * Only removes by db_id to ensure we don't accidentally delete unrelated addons
     *
     * @param array $removedAddons Array of addon data to remove (must contain db_id)
     * @param int $bookingId The booking ID
     * @return int Number of addons removed
     */
    protected static function removePaxAddons(array $removedAddons, int $bookingId): int
    {
        $dbIds = collect($removedAddons)
            ->pluck('db_id')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($dbIds)) {
            return 0;
        }

        $addonsToDelete = TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->whereIn('id', $dbIds)
            ->get();

        foreach ($addonsToDelete as $addon) {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $addon->id)->delete();
            $addon->children()->delete();
        }

        return TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->whereIn('id', $dbIds)
            ->delete();
    }

    /**
     * Update pax_id for addon records after new transact records are created
     * This is called after the main change people logic creates new BookingTransact records
     *
     * @param int $bookingId The booking ID
     * @param array $paxIndexToTransactMap Map of pax_index => transact_id
     * @return int Number of records updated
     */
    public static function updatePaxIds(int $bookingId, array $paxIndexToTransactMap): int
    {
        $count = 0;

        foreach ($paxIndexToTransactMap as $paxIndex => $transactId) {
            $updated = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('pax_index', $paxIndex)
                ->whereNull('pax_id')
                ->update(['pax_id' => $transactId]);

            $count += $updated;
        }

        return $count;
    }

    /**
     * Delete addons by pax_index - used when deleting tourists
     * Only call this when you specifically want to remove all addons for deleted pax
     *
     * @param int $bookingId The booking ID
     * @param array $paxIndices Array of pax indices being deleted
     * @return int Number of records deleted
     */
    public static function deleteAddonsByPaxIndices(int $bookingId, array $paxIndices): int
    {
        if (empty($paxIndices)) {
            return 0;
        }

        $addonsToDelete = TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->whereIn('pax_index', $paxIndices)
            ->get();

        foreach ($addonsToDelete as $addon) {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $addon->id)->delete();
        }

        return TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->whereIn('pax_index', $paxIndices)
            ->delete();
    }

    /**
     * Get field label from AddonField model
     *
     * @param int $fieldId
     * @return string|null
     */
    protected static function getFieldLabel($fieldId)
    {
        return AddonField::where('id', $fieldId)->value('label');
    }

    /**
     * Resolve a field's category, walking up parent_field_id when the field
     * itself has none set — category lives on the top-level field and
     * cascades down to its children (same inheritance as AddonFormRepository's
     * form tree builder).
     *
     * @param int $fieldId
     * @return array{category_id: int|null, category_name: string|null}
     */
    protected static function getFieldCategory($fieldId): array
    {
        $field = AddonField::find($fieldId);

        while ($field && !$field->category_id && $field->parent_field_id) {
            $field = AddonField::find($field->parent_field_id);
        }

        $categoryId = $field?->category_id;

        return [
            'category_id' => $categoryId,
            'category_name' => $categoryId ? AddonCategory::where('id', $categoryId)->value('name') : null,
        ];
    }

    /**
     * Resolve a field's linked package, walking up parent_field_id when the
     * field itself has none set — mirrors getFieldCategory()'s inheritance.
     *
     * @param int $fieldId
     * @return array{package_id: int|null, package_name: string|null}
     */
    protected static function getFieldPackage($fieldId): array
    {
        $field = AddonField::find($fieldId);

        while ($field && !$field->package_id && $field->parent_field_id) {
            $field = AddonField::find($field->parent_field_id);
        }

        $packageId = $field?->package_id;

        return [
            'package_id' => $packageId,
            'package_name' => $packageId ? TourPackage::where('id', $packageId)->value('name') : null,
        ];
    }

    /**
     * Resolve an option's clean, server-truth label ("Small", not "Small (+$20.00)").
     * The client-side dropdown decorates its visible option text with the price for shopping
     * purposes (e.g. "Small (+$20.00)"), but that decorated string must never be what gets stored
     * or shown in history/labels — same convention as the one_time/price_per_unit add-ons, which
     * store just the plain label.
     */
    protected static function getOptionLabel($optionId)
    {
        return $optionId ? AddonFieldOption::where('id', $optionId)->value('label') : null;
    }

    /**
     * Determine user type for operation_by_user_type field
     *
     * @return string
     */
    protected static function getUserType(): string
    {
        if (Auth::guard('admin')->check()) return 'Admin';
        if (Auth::guard('tour_operator_staff')->check()) return 'Tour Operator';
        if (Auth::guard('affiliate')->check()) return 'Affiliate';
        return 'Customer';
    }

    /**
     * Resolve the server-truth price for one addon selection (field + option), from
     * addon_fields/addon_field_options — NEVER trust price fields sent by the client.
     * Non-priced fields/options (additional_fee=0 or price<=0) contribute 0.
     * A price_per_pax row always represents exactly one person — no qty multiplier.
     *
     * @param int|null $fieldId
     * @param int|null $optionId
     * @param float $natFeesPercentage
     * @param bool $applyFees
     * @return array{price: float, field_price: float, option_price: float, field_fee: float, option_fee: float, fee: float, field_additional_fee: bool, option_additional_fee: bool}
     */
    /**
     * Pay Later booking (nothing collected yet): an edited field's never-collected 'open' charge
     * row must follow its NEW selection. AddonBookingService::createAddonTransactionsFromFields()
     * settles open rows at their STORED price once the booking is finally paid, so a Change People
     * dropdown edit on the same pax (Classic Turkey Wrap $16 → Gluten-Free Wrap $18) otherwise got
     * settled at the old $16. A field moved to an unpriced selection loses its open charge. Paid
     * rows are never touched. Call AFTER processAddons() has saved the new selection.
     *
     * @param array $updatedAddons the request's addons_updated entries (each with db_id)
     */
    public static function refreshOpenChargesForUpdatedFields(array $updatedAddons): void
    {
        foreach ($updatedAddons as $addon) {
            $dbId = (int) ($addon['db_id'] ?? 0);
            if (!$dbId) {
                continue;
            }
            $field = TourBookingAddonField::find($dbId);
            if (!$field) {
                continue;
            }
            $openRows = TourBookingAddonTransaction::where('tour_booking_addon_id', $dbId)
                ->where('operation', 'charge')
                ->where('status', 'open')
                ->orderBy('id')
                ->get();
            if ($openRows->isEmpty()) {
                continue;
            }

            $priced = self::resolveItemPrice($field->field_id, $field->option_id, 0, false);
            // Open rows store prices already qty-multiplied (see createAddonTransactionsFromFields()).
            $qty = ($field->unit_type === 'price_per_unit') ? max(1, (int) $field->unit) : 1;

            // Hard delete: raw readers of this table (settlement, reports) don't filter deleted_at.
            if ($priced['price'] <= 0) {
                TourBookingAddonTransaction::whereIn('id', $openRows->pluck('id'))->forceDelete();
                continue;
            }

            $openRows->first()->update([
                'field_price'           => round($priced['field_price'] * $qty, 2),
                'option_price'          => round($priced['option_price'] * $qty, 2),
                'field_fee'             => 0,
                'option_fee'            => 0,
                'field_additional_fee'  => $priced['field_additional_fee'],
                'option_additional_fee' => $priced['option_additional_fee'],
            ]);
            if ($openRows->count() > 1) {
                TourBookingAddonTransaction::whereIn('id', $openRows->slice(1)->pluck('id'))->forceDelete();
            }
        }
    }

    protected static function resolveItemPrice(?int $fieldId, ?int $optionId, float $natFeesPercentage, bool $applyFees): array
    {
        $fieldPrice = 0.0;
        $fieldAdditionalFee = false;
        $optionPrice = 0.0;
        $optionAdditionalFee = false;

        if ($fieldId) {
            $field = AddonField::find($fieldId);
            if ($field && $field->additional_fee && (float) $field->price > 0) {
                $fieldPrice = (float) $field->price;
                $fieldAdditionalFee = true;
            }
        }

        if ($optionId) {
            $option = AddonFieldOption::find($optionId);
            if ($option && $option->additional_fee && (float) $option->price > 0) {
                $optionPrice = (float) $option->price;
                $optionAdditionalFee = true;
            }
        }

        $fieldFee = $applyFees ? round($fieldPrice * $natFeesPercentage / 100, 2) : 0.0;
        $optionFee = $applyFees ? round($optionPrice * $natFeesPercentage / 100, 2) : 0.0;

        return [
            'price' => round($fieldPrice + $optionPrice, 2),
            'field_price' => $fieldPrice,
            'option_price' => $optionPrice,
            'fee' => round($fieldFee + $optionFee, 2),
            'field_fee' => $fieldFee,
            'option_fee' => $optionFee,
            'field_additional_fee' => $fieldAdditionalFee,
            'option_additional_fee' => $optionAdditionalFee,
        ];
    }

    /**
     * Build a human-readable label for an addon line item (matches the
     * "Field Label (Option Label)" convention used by the Booking Add-ons feature).
     */
    protected static function buildAddonLabel(?string $fieldLabel, ?string $optionLabel): string
    {
        $fieldLabel = $fieldLabel ?: 'Add-on';
        return $optionLabel ? "{$fieldLabel} ({$optionLabel})" : $fieldLabel;
    }

    /**
     * Human-readable display for a raw stored value on a non-option field (checkbox/text/etc.) —
     * mirrors tour-booking-addons.js's _displayValue() true/false → Yes/No convention.
     */
    protected static function displayValue($value)
    {
        if ($value === true || $value === 'true' || $value === 'checked') return 'Yes';
        if ($value === false || $value === 'false' || $value === 'not_checked') return 'No';
        return $value ?? '';
    }

    /**
     * "Sweater: X-Large → Large" for a same-field option/value change (an updated addon in
     * calculatePricedAddonDelta()) — shows what it actually changed FROM and TO, matching
     * tour-booking-addons.js's _buildAddonChangeLabel() convention used by the "Passenger
     * Add-ons" popup. Without this, a Change People downgrade/upgrade recorded only the NEW
     * value (e.g. "Sweater (Large)"), silently dropping the "what it used to be" half of the
     * history line that the same edit made through the Passenger Add-ons popup already shows.
     * Falls back to the plain buildAddonLabel() when there's nothing meaningful to show on the
     * "before" side, or when old/new resolve to the same label.
     */
    protected static function buildAddonChangeLabel(?string $fieldLabel, ?string $oldOptionLabel, $oldValue, ?string $newOptionLabel, $newValue): string
    {
        $fieldLabel = $fieldLabel ?: 'Add-on';
        $oldLabel = $oldOptionLabel ?: self::displayValue($oldValue);
        $newLabel = $newOptionLabel ?: self::displayValue($newValue);
        if ($oldLabel !== '' && $newLabel !== '' && $oldLabel !== $newLabel) {
            return "{$fieldLabel}: {$oldLabel} → {$newLabel}";
        }
        return self::buildAddonLabel($fieldLabel, $newOptionLabel);
    }

    /**
     * Compute the server-truth priced delta for a batch of pax add-on changes (add/remove/both
     * combined), the way the "Booking Add-ons" feature already prices one_time/price_per_unit
     * add-ons — but per-pax, with no qty multiplier (one row = one person).
     *
     * Non-priced add-ons (no additional_fee flag on field or option) are skipped entirely —
     * no price, no fee, no transaction row, per requirement.
     *
     * IMPORTANT: call this BEFORE addPaxAddons()/updatePaxAddons()/removePaxAddons() write to the
     * DB — removed/updated items need to resolve their OLD selection from the still-existing
     * tour_booking_addon_fields row.
     *
     * @param array $addedAddons
     * @param array $updatedAddons
     * @param array $removedAddons
     * @param float $natFeesPercentage
     * @param bool $applyFees Whether commission fee applies (Card, or Cash with fees enabled)
     * @return array{net_price: float, net_fee: float, net_total: float, chargeItems: array, refundItems: array}
     */
    public static function calculatePricedAddonDelta(array $addedAddons, array $updatedAddons, array $removedAddons, float $natFeesPercentage = 0, bool $applyFees = false): array
    {
        $netPrice = 0.0;
        $netFee = 0.0;
        $chargeItems = [];
        $refundItems = [];
        $pendingLinkDropDbIds = [];
        $pendingLinkRepriceDbIds = [];
        $pendingLinkRepriceSelections = [];
        // Unpaid pending amounts this edit takes off the link — recorded in history as "Don't
        // Refund" (nothing was collected, nothing goes back), e.g. "Gluten-Free Wrap → Classic
        // Turkey Wrap $2.00".
        $unpaidDontRefundItems = [];

        foreach ($addedAddons as $addon) {
            $fieldId = $addon['field_id'] ?? null;
            if (!$fieldId) {
                continue;
            }
            $priced = self::resolveItemPrice((int) $fieldId, isset($addon['option_id']) ? (int) $addon['option_id'] : null, $natFeesPercentage, $applyFees);
            if ($priced['price'] <= 0) {
                continue; // non-priced add-on — nothing to calculate
            }

            $optionIdForLabel = isset($addon['option_id']) ? (int) $addon['option_id'] : null;
            $chargeItems[] = array_merge($priced, [
                'field_id' => (int) $fieldId,
                'pax_index' => $addon['pax_index'] ?? null,
                'label' => self::buildAddonLabel(
                    $addon['field_label'] ?? self::getFieldLabel($fieldId),
                    self::getOptionLabel($optionIdForLabel) ?? ($addon['option_label'] ?? null)
                ),
            ]);

            $netPrice += $priced['price'];
            $netFee += $priced['fee'];
        }

        foreach ($removedAddons as $addon) {
            $dbId = $addon['db_id'] ?? null;
            if (!$dbId) {
                continue;
            }
            $existing = TourBookingAddonField::find($dbId);
            if (!$existing) {
                continue;
            }
            $priced = self::resolveItemPrice($existing->field_id, $existing->option_id, $natFeesPercentage, $applyFees);
            if ($priced['price'] <= 0) {
                continue;
            }

            // Partly paid (e.g. $16 paid by card, a $2 upgrade still pending on a link): only what
            // was actually collected can be refunded — the pending part is simply dropped from the
            // link (reduceChangePeopleAddonPaymentLink()). Crediting the full $18 would refund more
            // than the card was ever charged.
            if (self::fieldHasUnpaidLinkItem((int) $dbId, $existing->tour_booking_id)) {
                $paid = self::paidNetForField((int) $dbId);
                if ($paid['price'] > 0.005 && $paid['price'] < $priced['price']) {
                    $scale = $paid['price'] / $priced['price'];
                    $priced['field_price'] = round($priced['field_price'] * $scale, 2);
                    $priced['option_price'] = round($paid['price'] - $priced['field_price'], 2);
                    $priced['price'] = $paid['price'];
                    $priced['field_fee'] = round(min($paid['fee'], $priced['fee']) * ($priced['price'] > 0 ? $priced['field_price'] / $priced['price'] : 0), 2);
                    $priced['fee'] = round(min($paid['fee'], $priced['fee']), 2);
                    $priced['option_fee'] = round($priced['fee'] - $priced['field_fee'], 2);
                }
            }

            $refundItems[] = array_merge($priced, [
                'db_id' => (int) $dbId,
                'field_id' => $existing->field_id,
                'pax_id' => $existing->pax_id,
                'pax_index' => $existing->pax_index,
                'label' => self::buildAddonLabel($existing->field_label, $existing->option_label),
            ]);

            $netPrice -= $priced['price'];
            $netFee -= $priced['fee'];
        }

        foreach ($updatedAddons as $addon) {
            $dbId = $addon['db_id'] ?? null;
            if (!$dbId) {
                continue;
            }
            $existing = TourBookingAddonField::find($dbId);
            if (!$existing) {
                continue;
            }

            $oldPriced = self::resolveItemPrice($existing->field_id, $existing->option_id, $natFeesPercentage, $applyFees);
            $newFieldId = isset($addon['field_id']) ? (int) $addon['field_id'] : $existing->field_id;
            $newOptionId = isset($addon['option_id']) ? (int) $addon['option_id'] : $existing->option_id;
            $newPriced = self::resolveItemPrice($newFieldId, $newOptionId, $natFeesPercentage, $applyFees);

            // This field's CURRENT (pre-edit) selection is still sitting on a pending, never-paid
            // remaining_payment link item (added via Change People + "Pay with Link", then edited
            // again before anyone ever paid it). There is no real prior charge to net a delta
            // against, so charge the FULL new price instead of new-minus-old -- otherwise the
            // customer would be charged only the delta while the old (never-collected) price
            // silently vanished from every ledger. Flagged so the caller can drop the superseded
            // pending link line instead of separately finalizing it for the old, now-stale amount
            // (which would double-record the same add-on: once here at the delta/full price, once
            // again when the link settles). Deliberately checked via "is this field on an actual
            // pending link item" rather than "does no paid transaction exist for it" -- the latter
            // is also true for reasons that have nothing to do with a pending link (e.g. a field
            // whose money was recorded through a different, older convention), and would wrongly
            // treat an ordinary already-settled edit as a full-price charge.
            $supersedesPendingLink = $oldPriced['price'] > 0
                && self::fieldHasUnpaidLinkItem((int) $dbId, $existing->tour_booking_id);

            // PARTLY paid: the field's earlier selection was genuinely paid (e.g. Classic Turkey
            // Wrap $16 by card) and only a later upgrade difference is pending on the link (to
            // Gluten-Free Wrap, +$2). The baseline is what was actually collected, not $0 -- $0 made
            // a removal / downgrade (to "No lunch needed") look like a $0 change, so no refund was
            // recorded and the edit fell into the Card-charge branch with no PaymentIntent ("Card
            // payment could not be processed"). The pending line is dropped either way.
            $paidBaseline = false;
            // The Change People form sends an "updated" entry for EVERY pax with an existing
            // selection, edited or not. An untouched field with a pending link line must stay as
            // it is — not have its pending amount pulled into this edit's charge.
            if ($supersedesPendingLink
                && (int) $newOptionId === (int) $existing->option_id
                && (string) ($addon['value'] ?? $existing->value) === (string) $existing->value) {
                continue;
            }
            if ($supersedesPendingLink) {
                $paid = self::paidNetForField((int) $dbId);
                if ($paid['price'] > 0.005) {
                    $paidBaseline = true;
                    $priceDelta = round($newPriced['price'] - $paid['price'], 2);
                    // $newPriced['fee'] is priced under THIS edit's payment method, $paid['fee'] is
                    // what the earlier method collected -- they can differ (e.g. paid by Card, now
                    // edited as Cash with fees off). A charge may never carry a negative fee, and a
                    // refund may never return more fee than was collected or a fee never collected.
                    $feeDelta = round($newPriced['fee'] - $paid['fee'], 2);
                    $feeDelta = $priceDelta > 0 ? max(0.0, $feeDelta) : min(0.0, $feeDelta);
                    // Moving to a cheaper selection drops (part of) the still-unpaid pending amount
                    // — e.g. $16 paid + $2 pending, to Classic Turkey $16 or to "No lunch": the $2
                    // was never collected, so it's recorded as "Don't Refund" (any real refund of
                    // the $16 paid is recorded separately by the settlement).
                    $unpaidDropped = round($oldPriced['price'] - max($newPriced['price'], $paid['price']), 2);
                    if ($unpaidDropped > 0.005) {
                        $unpaidDontRefundItems[] = [
                            'name' => self::buildAddonChangeLabel(
                                $addon['field_label'] ?? $existing->field_label,
                                $existing->option_label,
                                $existing->value,
                                self::getOptionLabel($newOptionId) ?? ($addon['option_label'] ?? null),
                                $addon['value'] ?? null
                            ),
                            'price' => $unpaidDropped,
                            'fee' => 0,
                            'payment_method' => '',
                            'status' => "Don't Refund",
                        ];
                    }
                } elseif ($newPriced['price'] < $oldPriced['price'] - 0.005) {
                    // Entirely UNPAID (only ever sent on a payment link) and moved to a cheaper or
                    // free selection: no money moves in this edit — the pending link line is just
                    // repriced to the new selection (dropped if it's now free) and stays owed there.
                    // Charging the new price here instead sent a Card booking into the card-charge
                    // branch with no PaymentIntent.
                    $pendingLinkRepriceDbIds[] = (int) $dbId;
                    $pendingLinkRepriceSelections[(int) $dbId] = [
                        'option_id' => $newOptionId,
                        'unit' => $addon['unit'] ?? $existing->unit,
                    ];
                    $unpaidDontRefundItems[] = [
                        'name' => self::buildAddonChangeLabel(
                            $addon['field_label'] ?? $existing->field_label,
                            $existing->option_label,
                            $existing->value,
                            self::getOptionLabel($newOptionId) ?? ($addon['option_label'] ?? null),
                            $addon['value'] ?? null
                        ),
                        'price' => round($oldPriced['price'] - $newPriced['price'], 2),
                        'fee' => 0,
                        'payment_method' => '',
                        'status' => "Don't Refund",
                    ];
                    continue;
                } else {
                    $priceDelta = round($newPriced['price'], 2);
                    $feeDelta = round($newPriced['fee'], 2);
                }
            } else {
                $priceDelta = round($newPriced['price'] - $oldPriced['price'], 2);
                $feeDelta = round($newPriced['fee'] - $oldPriced['fee'], 2);
            }
            if (abs($priceDelta) < 0.005) {
                if ($paidBaseline) {
                    // Exactly covered by what was already paid (e.g. back to the $16 option): nothing
                    // to charge or refund, but its pending link line is no longer owed.
                    $pendingLinkDropDbIds[] = (int) $dbId;
                }
                continue; // no financial change (e.g. a non-priced value edit)
            }
            // The never-paid case can only ever be a charge (a full new price is never negative) --
            // if it somehow lands negative, treat it as an ordinary delta rather than mislabel a
            // refund as "supersedes a pending link" (nothing to drop when nothing was charged).
            // A partly-paid field's pending line is superseded in both directions.
            $supersedesPendingLink = $supersedesPendingLink && ($paidBaseline || $priceDelta > 0);

            // Show the "before → after" transition (e.g. "Sweater: X-Large → Large"), matching
            // the Passenger Add-ons popup's _buildAddonChangeLabel() convention — not just the
            // new value, which silently dropped what the option/value used to be.
            $label = self::buildAddonChangeLabel(
                $addon['field_label'] ?? $existing->field_label,
                $existing->option_label,
                $existing->value,
                self::getOptionLabel($newOptionId) ?? ($addon['option_label'] ?? null),
                $addon['value'] ?? null
            );

            if ($priceDelta > 0 && $paidBaseline) {
                // Only the part above what was already paid, scaled from the new selection.
                $scale = $newPriced['price'] > 0 ? $priceDelta / $newPriced['price'] : 0;
                $chargeFieldPrice = round($newPriced['field_price'] * $scale, 2);
                $chargeFieldFee = round($newPriced['field_fee'] * $scale, 2);
                $chargeItems[] = [
                    'db_id' => (int) $dbId,
                    'field_id' => $newFieldId,
                    'pax_index' => $addon['pax_index'] ?? $existing->pax_index,
                    'price' => $priceDelta,
                    'field_price' => $chargeFieldPrice,
                    'option_price' => round($priceDelta - $chargeFieldPrice, 2),
                    'fee' => $feeDelta,
                    'field_fee' => $chargeFieldFee,
                    'option_fee' => round($feeDelta - $chargeFieldFee, 2),
                    'field_additional_fee' => $newPriced['field_additional_fee'],
                    'option_additional_fee' => $newPriced['option_additional_fee'],
                    'label' => $label,
                    'is_price_delta' => true,
                    'supersedes_pending_link' => true,
                ];
            } elseif ($priceDelta < 0 && $paidBaseline) {
                // Real refund of money actually collected, split like the OLD selection's components.
                $refundPrice = abs($priceDelta);
                $refundFee = abs($feeDelta);
                $oldShare = $oldPriced['price'] > 0 ? $oldPriced['field_price'] / $oldPriced['price'] : 0;
                $refundFieldPrice = round($refundPrice * $oldShare, 2);
                $refundFieldFee = round($refundFee * $oldShare, 2);
                $refundItems[] = [
                    'db_id' => (int) $dbId,
                    'field_id' => $newFieldId,
                    'pax_index' => $addon['pax_index'] ?? $existing->pax_index,
                    'price' => $refundPrice,
                    'field_price' => $refundFieldPrice,
                    'option_price' => round($refundPrice - $refundFieldPrice, 2),
                    'fee' => $refundFee,
                    'field_fee' => $refundFieldFee,
                    'option_fee' => round($refundFee - $refundFieldFee, 2),
                    'field_additional_fee' => $oldPriced['field_additional_fee'],
                    'option_additional_fee' => $oldPriced['option_additional_fee'],
                    'label' => $label,
                    'is_price_delta' => true,
                    'supersedes_pending_link' => true,
                ];
            } elseif ($priceDelta > 0) {
                $chargeItems[] = [
                    'db_id' => (int) $dbId,
                    'field_id' => $newFieldId,
                    'pax_index' => $addon['pax_index'] ?? $existing->pax_index,
                    'price' => $priceDelta,
                    'field_price' => $supersedesPendingLink ? $newPriced['field_price'] : ($newPriced['field_price'] - $oldPriced['field_price']),
                    'option_price' => $supersedesPendingLink ? $newPriced['option_price'] : ($newPriced['option_price'] - $oldPriced['option_price']),
                    'fee' => $feeDelta,
                    'field_fee' => $supersedesPendingLink ? $newPriced['field_fee'] : ($newPriced['field_fee'] - $oldPriced['field_fee']),
                    'option_fee' => $supersedesPendingLink ? $newPriced['option_fee'] : ($newPriced['option_fee'] - $oldPriced['option_fee']),
                    'field_additional_fee' => $newPriced['field_additional_fee'],
                    'option_additional_fee' => $newPriced['option_additional_fee'],
                    'label' => $label,
                    'is_price_delta' => !$supersedesPendingLink,
                    'supersedes_pending_link' => $supersedesPendingLink,
                ];
            } else {
                $refundItems[] = [
                    'db_id' => (int) $dbId,
                    'field_id' => $newFieldId,
                    'pax_index' => $addon['pax_index'] ?? $existing->pax_index,
                    'price' => abs($priceDelta),
                    'field_price' => abs($newPriced['field_price'] - $oldPriced['field_price']),
                    'option_price' => abs($newPriced['option_price'] - $oldPriced['option_price']),
                    'fee' => abs($feeDelta),
                    'field_fee' => abs($newPriced['field_fee'] - $oldPriced['field_fee']),
                    'option_fee' => abs($newPriced['option_fee'] - $oldPriced['option_fee']),
                    'field_additional_fee' => $oldPriced['field_additional_fee'],
                    'option_additional_fee' => $oldPriced['option_additional_fee'],
                    'label' => $label,
                    'is_price_delta' => true,
                ];
            }

            $netPrice += $priceDelta;
            $netFee += $feeDelta;
        }

        return [
            'net_price' => round($netPrice, 2),
            'net_fee' => round($netFee, 2),
            'net_total' => round($netPrice + $netFee, 2),
            'chargeItems' => $chargeItems,
            'refundItems' => $refundItems,
            // Partly-paid fields whose new selection is exactly covered by what was already paid:
            // no money moves, but their pending link line must still be dropped (processAddons()).
            'pending_link_drop_db_ids' => array_values(array_unique($pendingLinkDropDbIds)),
            // Never-paid fields moved to a cheaper/free selection: reprice (or drop) their line.
            'pending_link_reprice_db_ids' => array_values(array_unique($pendingLinkRepriceDbIds)),
            // The same fields' new selections, so a Card PaymentIntent created BEFORE this edit is
            // saved can preview the repriced link (PaymentLinkService::pendingAddonLinkDropAmounts()).
            'pending_link_reprice_selections' => $pendingLinkRepriceSelections,
            'unpaid_dont_refund_items' => $unpaidDontRefundItems,
        ];
    }

    /**
     * What has actually been collected for a field: paid charges minus refunds, price and fee.
     *
     * @return array{price: float, fee: float}
     */
    private static function paidNetForField(int $dbId): array
    {
        $rows = TourBookingAddonTransaction::where('tour_booking_addon_id', $dbId)->get();
        $price = 0.0;
        $fee = 0.0;
        foreach ($rows as $row) {
            $rowPrice = (float) $row->field_price + (float) $row->option_price;
            $rowFee = (float) $row->field_fee + (float) $row->option_fee;
            if ($row->operation === 'charge' && $row->status === 'paid') {
                $price += $rowPrice;
                $fee += $rowFee;
            } elseif ($row->operation === 'refund') {
                $price -= $rowPrice;
                $fee -= $rowFee;
            }
        }
        return ['price' => round(max(0, $price), 2), 'fee' => round(max(0, $fee), 2)];
    }

    /**
     * Whether a `tour_booking_addon_fields.id` is currently referenced (via its `field_to_db` map)
     * by an Unpaid 'addon' item on this booking's pending `remaining_payment` link -- i.e. its
     * current selection was submitted via "Pay with Link" and never actually charged. Used by
     * calculatePricedAddonDelta() to tell "this old selection was genuinely already paid for"
     * (net a delta against it) apart from "nothing has been collected for it yet" (charge the full
     * new price and let the caller drop the superseded pending line).
     */
    private static function fieldHasUnpaidLinkItem(int $dbId, ?int $bookingId): bool
    {
        if (!$bookingId) {
            return false;
        }

        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return false;
        }

        $itemDetails = DB::table('tour_booking_payment_link_items as tbpli')
            ->join('tour_booking_payment_links as tbpl', 'tbpl.id', '=', 'tbpli.payment_link_id')
            ->where('tbpl.order_id', $groupId)
            ->where('tbpl.section', 'remaining_payment')
            ->where('tbpl.status', 'Unpaid')
            ->where('tbpli.type', 'addon')
            ->where('tbpli.status', 'Unpaid')
            ->pluck('tbpli.details');

        foreach ($itemDetails as $rawDetails) {
            $details = is_array($rawDetails) ? $rawDetails : (json_decode($rawDetails ?? '[]', true) ?? []);
            $fieldToDb = array_map('intval', $details['field_to_db'] ?? []);
            if (in_array($dbId, $fieldToDb, true)) {
                return true;
            }
            // Change People's own addon link items leave field_to_db EMPTY and carry the field
            // on each line's old_db_id instead — without this, a second edit of a field already
            // pending via Change People wasn't recognised as pending, so a second pending line
            // was stacked for the same field (booking #41960: two $17.57 lines for one $16 lunch).
            foreach (($details['financial']['to_charge'] ?? []) as $line) {
                if (PaymentLinkService::resolvePendingAddonLineFieldId($line, $details['field_to_db'] ?? []) === $dbId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Settle tour_booking_addon_transactions + tour_booking_history/transactions for the priced
     * delta computed by calculatePricedAddonDelta(), using the SAME Stripe charge/refund that was
     * already executed for the combined tour-amount + add-on total — this method never calls
     * Stripe itself, it only books the add-on-side records against that already-settled payment.
     *
     * Mirrors the conventions used by TourBookingAddonRepository::processAddonFinancialTransactions()
     * for the existing "Booking Add-ons" feature (operation_group_id grouping, status open/paid/refunded,
     * per-item transaction rows, storeAddonsPaymentInHistory/storeAddonsRefundInHistory so the add-on
     * change shows up in tour_booking_history exactly like a standalone Booking Add-ons edit does).
     *
     * @param array $delta Result of calculatePricedAddonDelta()
     * @param array $financialContext See processAddons() docblock
     * @param TourBooking $booking
     * @param array $newFieldDbIdMap "field_id_pax_index" => new tour_booking_addon_fields.id (for newly added pax)
     */
    /**
     * Refund (or write a "Don't Refund" audit entry for) the priced add-ons attached to the pax
     * being removed from ONE rate group, using the delta calculatePricedAddonDelta() already
     * computed for this "delete pax" edit. $rateGroupPaxId is that rate group's own
     * tour_bookings_transact.id — refundItems are matched to it via their own 'pax_id'.
     *
     * Ported from TourBookingRepository::deletePeopleChangePeople()'s own inline block (the
     * non-group "Change People" delete-pax flow) so the group-rate flow
     * (EditBookingService::deletePaxFromGroupBooking()) can refund add-ons the exact same way —
     * that flow never got this at all, so removing a pax from a Group-rate booking silently
     * hard-deleted their price_per_pax add-on fields (via processAddons() upstream) without ever
     * refunding the money, and a "Weather / 100%" refund only ever refunded the ticket portion.
     * The non-group call site's own inline copy is left as-is (not swapped to call this) to avoid
     * any risk of regressing that already-working, heavily-tested path in the same change.
     *
     * @param array $addonRefundDelta ['refundItems' => [...]] from calculatePricedAddonDelta()
     * @param int $rateGroupPaxId This rate group's tour_bookings_transact.id
     * @param object|null $transaction This rate group's own 'Completed', charge_type-null
     *                                 Transaction row — null/empty means a true Pay Later booking
     *                                 that was never actually charged (see the Pay Later bypass
     *                                 below).
     * @param string $refundVia Resolved payment method to refund through — already carries any
     *                          admin Cash/Check-Wire override, same variable the tour-side
     *                          storeRefundInTransaction() call uses.
     * @param array $data The change-people request payload (refund_Type, selected_addon_field_ids,
     *                     checked_transactions, etc.)
     * @param object $tourBooking
     * @param int $bookingId
     * @param mixed $rateGroupIdForLogging Only used to label a caught exception's log context.
     */
    public static function refundRemovedPaxAddonsForRateGroup(
        array $addonRefundDelta,
        int $rateGroupPaxId,
        ?object $transaction,
        string $refundVia,
        array $data,
        object $tourBooking,
        int $bookingId,
        $rateGroupIdForLogging = null
    ): void {
        if (empty($addonRefundDelta['refundItems'])) {
            return;
        }

        $addonItemsForGroup = array_values(array_filter(
            $addonRefundDelta['refundItems'],
            fn($item) => ($item['pax_id'] ?? null) == $rateGroupPaxId
        ));

        if (empty($addonItemsForGroup)) {
            return;
        }

        // dont_refund/partial_refund/fareharbor all settle as 'dont_refund': dont_refund means no
        // money moves for either side; partial_refund is a manually-typed dollar amount with no
        // reliable way to know how much of it the admin intended for add-ons (same "Partial
        // Refund never touches add-ons" convention the direct Edit Booking delete flow uses);
        // fareharbor is an external-platform refund handled outside Stripe.
        $addonRefundTypeForGroup = match ($data['refund_Type'] ?? 'dont_refund') {
            '100_refund' => 'full_refund',    // refund price + fee
            'full_refund' => 'ticket_refund',  // ("Full Refund minus Fees") price only, keep fee
            default => 'dont_refund',
        };

        // True Pay Later: nothing has ever actually been captured for this booking — the add-on
        // was only ever recorded as an open/unsettled field row. Calling settleFromDelta() here
        // regardless would create a refund row with no matching charge row to net against, plus a
        // real transactions ledger row/history entry for money that was never collected. Mirror
        // the add-side bypass instead: skip settlement entirely and log a plain $0, blank-
        // payment-method "Don't Refund" entry so the removal still has an audit trail.
        if (empty($transaction)) {
            try {
                $removedHistoryItems = array_map(function ($item) {
                    return [
                        'name' => $item['label'] ?? 'Add-on',
                        'price' => $item['price'] ?? 0,
                        'fee' => $item['fee'] ?? 0,
                        'payment_method' => '',
                        'status' => "Don't Refund",
                    ];
                }, $addonItemsForGroup);

                HistoryService::storeAddonsRefundInHistory(
                    $tourBooking,
                    (object) ['payment_method' => ''],
                    ['price' => 0, 'fee' => 0, 'transaction_id' => null, 'application_fee_id' => null],
                    $removedHistoryItems,
                    'removed',
                    Constants::ADDONS_DONT_REFUND,
                    null,
                    (object) ['description' => null]
                );
            } catch (Exception $ex) {
                Log::error('ChangePeopleAddonService error (Pay Later addon-removal history in refundRemovedPaxAddonsForRateGroup): ' . $ex->getMessage());
            }
            return;
        }

        // Partition into what actually gets refunded vs. what only gets a "Don't Refund" audit
        // entry. Only a real refund type (100% Refund / Full Refund minus Fees) ever lets the
        // admin choose specific items via selected_addon_field_ids — for 'dont_refund' every item
        // in scope settles as "Don't Refund" uniformly, regardless of whatever that field
        // happens to contain.
        $refundedItems = [];
        $notRefundedItems = $addonItemsForGroup;

        if ($addonRefundTypeForGroup !== 'dont_refund') {
            // A missing or empty-string selected_addon_field_ids means no selection was ever
            // made — fall back to refunding everything tied to this pax rather than silently
            // refunding nothing.
            if (!empty($data['selected_addon_field_ids'])) {
                $selectedAddonFieldIds = is_array($data['selected_addon_field_ids'])
                    ? $data['selected_addon_field_ids']
                    : (json_decode($data['selected_addon_field_ids'], true) ?? []);
                $selectedAddonFieldIds = array_map('intval', $selectedAddonFieldIds);
                $refundedItems = array_values(array_filter(
                    $addonItemsForGroup,
                    fn($item) => in_array((int) ($item['db_id'] ?? 0), $selectedAddonFieldIds, true)
                ));
                $notRefundedItems = array_values(array_filter(
                    $addonItemsForGroup,
                    fn($item) => !in_array((int) ($item['db_id'] ?? 0), $selectedAddonFieldIds, true)
                ));
            } else {
                $refundedItems = $addonItemsForGroup;
                $notRefundedItems = [];
            }
        }

        $addonStripeTransactionNumber = (isset($data['checked_transactions']) && $data['checked_transactions'] != '')
            ? $data['checked_transactions']
            : ($transaction->transaction_number ?? null);

        // One call per bucket (each with its own uniform refund_type) instead of one call for the
        // whole group — settleFromDelta()/settleAddonTransactions() only ever applies a single
        // refund_type per call.
        $settleGroupAddons = function (array $items, string $itemsRefundType) use ($bookingId, $rateGroupIdForLogging, $refundVia, $addonStripeTransactionNumber) {
            if (empty($items)) {
                return;
            }
            try {
                self::settleFromDelta(
                    ['chargeItems' => [], 'refundItems' => $items],
                    [
                        'action' => 'refund',
                        'payment_method' => $refundVia,
                        'transaction_id' => $addonStripeTransactionNumber,
                        'refund_type' => $itemsRefundType,
                        'execute_stripe_refund' => true,
                    ],
                    $bookingId
                );
            } catch (Exception $ex) {
                Log::error('ChangePeopleAddonService: addon refund settlement failed in refundRemovedPaxAddonsForRateGroup', [
                    'booking_id' => $bookingId,
                    'rate_group_id' => $rateGroupIdForLogging,
                    'error' => $ex->getMessage(),
                ]);
            }
        };

        $settleGroupAddons($refundedItems, $addonRefundTypeForGroup);
        $settleGroupAddons($notRefundedItems, 'dont_refund');
    }

    /**
     * Public entry point for settling a priced add-on delta AFTER the real combined Stripe
     * charge/refund has resolved (transaction_id known) — used when the caller needs to write the
     * tour_booking_addon_fields rows early (e.g. so contact/history logic downstream can see them)
     * but can only learn the transaction_id/application_fee_id once the Stripe call further down
     * in changePeopleBooking() completes. Pass the 'addon_delta' + 'field_db_id_map' returned by
     * an earlier processAddons() call (invoked with no financial context, i.e. field-writes only).
     *
     * @param array $delta Result of calculatePricedAddonDelta() (or processAddons()['addon_delta'])
     * @param array $financialContext See processAddons() docblock
     * @param int $bookingId
     * @param array $fieldDbIdMap processAddons()['field_db_id_map']
     */
    public static function settleFromDelta(array $delta, array $financialContext, int $bookingId, array $fieldDbIdMap = []): void
    {
        if (empty($delta['chargeItems']) && empty($delta['refundItems'])) {
            return;
        }

        $booking = TourBooking::find($bookingId);
        if (!$booking) {
            return;
        }

        self::settleAddonTransactions($delta, $financialContext, $booking, $fieldDbIdMap);
    }

    protected static function settleAddonTransactions(array $delta, array $financialContext, TourBooking $booking, array $newFieldDbIdMap): void
    {
        $action = $financialContext['action'] ?? 'none';
        if ($action === 'none') {
            return;
        }

        // Shared across every retrievePayment() call in this one settlement — the admin-selected
        // PI (context resolution below) and an origin PI inside the per-PI refund loop often
        // refer to the SAME PaymentIntent (e.g. a single-person removal), so caching avoids
        // hitting Stripe twice for identical data within one request.
        $paymentIntentCache = [];

        $context = self::resolveSettlementContext($financialContext, $paymentIntentCache);
        $paymentMethod = $context['paymentMethod'];
        $status = $context['status'];
        $transactionId = $context['transactionId'];
        $applicationFeeId = $context['applicationFeeId'];
        $operationGroupId = $context['operationGroupId'];
        $refundType = $context['refundType'];
        $refundTypeExplicit = $context['refundTypeExplicit'];
        $routePerOriginPi = $context['routePerOriginPi'];
        $operationBy = $booking->created_by ?? Auth::id() ?? 0;
        $operationByUserType = $booking->created_by_user_type ?? self::getUserType();

        $chargeHistoryItems = [];
        $totalChargePrice = 0.0;
        $totalChargeFee = 0.0;

        // refundItemsToProcess may get scaled DOWN below if a PaymentIntent has less available
        // than requested (e.g. an older charge that predates a fee-calculation fix, or ordinary
        // cent-level rounding drift) — using the SCALED items for the DB rows too keeps the ledger
        // from claiming more was refunded than Stripe actually returned.
        $refundItemsToProcess = $delta['refundItems'] ?? [];

        // Cache each item's own original charge row once — reused both to pick which
        // PaymentIntent to actually refund against below (when $routePerOriginPi), and to
        // attribute each refund row/ledger entry to it further down.
        $originalByDbId = self::mapRefundItemsToOriginalCharge($refundItemsToProcess);

        // Some callers (e.g. deletePeopleChangePeople(), which has no single combined Stripe
        // operation to piggyback on — it issues its OWN per-rate-group refund) need this method to
        // actually EXECUTE the Stripe refund for the add-on's money, not just book it against an
        // already-settled one. Do that ONCE up front, before writing any DB rows.
        if (($financialContext['execute_stripe_refund'] ?? false) && $refundType !== 'dont_refund' && !empty($refundItemsToProcess) && $paymentMethod === 'card') {
            $refundItemsToProcess = self::executeStripeRefundsPerOriginPaymentIntent(
                $refundItemsToProcess, $originalByDbId, $refundType, $transactionId, $paymentIntentCache
            );
        }

        // A net-CHARGE settlement that ALSO absorbs an offsetting removal in the SAME edit (e.g.
        // "Change People" swapping one pax's add-on for two new pax's add-ons) only ever confirms
        // a brand-new PaymentIntent for the NET amount still owed — the removed item's own
        // price+fee is never actually refunded via Stripe here ($routePerOriginPi is false for
        // this shape; see execute_stripe_refund above), it simply stays wherever it was already,
        // genuinely charged, completely untouched. Build that as spendable capacity so the loop
        // below can attribute new items' money to the PI that REALLY still holds it, instead of
        // tying every new item's FULL price to the brand-new PI regardless of what it was ever
        // actually confirmed for. Without this, a later 100% cancel refund asks Stripe to refund
        // more from the new PI than it ever charged there and gets rejected ("Refund amount ($X)
        // is greater than charge amount ($Y)").
        $offsetCapacity = [];
        if ($action === 'charge' && !$routePerOriginPi) {
            foreach ($refundItemsToProcess as $item) {
                $dbId = $item['db_id'] ?? null;
                $original = $dbId ? ($originalByDbId[$dbId] ?? null) : null;
                if (!$original || empty($original->transaction_id)) {
                    continue;
                }
                $offsetCapacity[] = [
                    'transaction_id' => $original->transaction_id,
                    'application_fee_id' => $original->application_fee_id,
                    'payment_method' => $original->payment_method,
                    'remaining' => round((float) ($item['price'] ?? 0) + (float) ($item['fee'] ?? 0), 2),
                ];
            }
        }

        // Charges settle against the ALREADY-executed combined payment (charge action) — this
        // never triggers a new Stripe call, it just books the addon-side transaction rows. A
        // single item is split across MULTIPLE rows below whenever $offsetCapacity only partly
        // covers it — the same "draw down the oldest capacity first, cascade the rest" shape the
        // refund side already uses when one refund spans several origin PaymentIntents.
        foreach ($delta['chargeItems'] ?? [] as $item) {
            $dbId = $item['db_id'] ?? ($newFieldDbIdMap[$item['field_id'] . '_' . $item['pax_index']] ?? null);
            if (!$dbId) {
                continue;
            }

            $itemFieldPrice  = (float) ($item['field_price']  ?? 0);
            $itemOptionPrice = (float) ($item['option_price'] ?? 0);
            $itemFieldFee    = (float) ($item['field_fee']    ?? 0);
            $itemOptionFee   = (float) ($item['option_fee']   ?? 0);
            $itemTotal       = round($itemFieldPrice + $itemOptionPrice + $itemFieldFee + $itemOptionFee, 2);

            $draws = [];
            $remaining = $itemTotal;
            while ($remaining > 0.001 && !empty($offsetCapacity)) {
                if ($offsetCapacity[0]['remaining'] <= 0.001) {
                    array_shift($offsetCapacity);
                    continue;
                }
                $draw = round(min($remaining, $offsetCapacity[0]['remaining']), 2);
                $draws[] = [
                    'transaction_id' => $offsetCapacity[0]['transaction_id'],
                    'application_fee_id' => $offsetCapacity[0]['application_fee_id'],
                    'payment_method' => $offsetCapacity[0]['payment_method'] ?: $paymentMethod,
                    'amount' => $draw,
                ];
                $offsetCapacity[0]['remaining'] = round($offsetCapacity[0]['remaining'] - $draw, 2);
                $remaining = round($remaining - $draw, 2);
            }
            if ($remaining > 0.001 || empty($draws)) {
                $draws[] = [
                    'transaction_id' => $transactionId,
                    'application_fee_id' => $applicationFeeId,
                    'payment_method' => $paymentMethod,
                    'amount' => $remaining,
                ];
            }

            // Proportion field_price/option_price/field_fee/option_fee across each draw by its
            // share of the item's total — the LAST draw absorbs whatever rounding leftover
            // remains so the rows always sum back EXACTLY to the item's real values.
            $allocFieldPrice = 0.0; $allocOptionPrice = 0.0; $allocFieldFee = 0.0; $allocOptionFee = 0.0;
            $lastIdx = count($draws) - 1;
            foreach ($draws as $idx => $draw) {
                if ($idx === $lastIdx) {
                    $rowFieldPrice  = round($itemFieldPrice  - $allocFieldPrice, 2);
                    $rowOptionPrice = round($itemOptionPrice - $allocOptionPrice, 2);
                    $rowFieldFee    = round($itemFieldFee    - $allocFieldFee, 2);
                    $rowOptionFee   = round($itemOptionFee   - $allocOptionFee, 2);
                } else {
                    $ratio = $itemTotal > 0 ? $draw['amount'] / $itemTotal : 0;
                    $rowFieldPrice  = round($itemFieldPrice  * $ratio, 2);
                    $rowOptionPrice = round($itemOptionPrice * $ratio, 2);
                    $rowFieldFee    = round($itemFieldFee    * $ratio, 2);
                    $rowOptionFee   = round($itemOptionFee   * $ratio, 2);
                    $allocFieldPrice  += $rowFieldPrice;
                    $allocOptionPrice += $rowOptionPrice;
                    $allocFieldFee    += $rowFieldFee;
                    $allocOptionFee   += $rowOptionFee;
                }

                TourBookingAddonTransaction::create([
                    'tour_booking_id' => $booking->id,
                    'tour_booking_addon_id' => $dbId,
                    'operation_group_id' => $operationGroupId,
                    'payment_method' => $draw['payment_method'],
                    'status' => $status,
                    'operation' => 'charge',
                    'transaction_id' => $draw['transaction_id'],
                    'application_fee_id' => $draw['application_fee_id'],
                    'field_additional_fee' => (bool) ($item['field_additional_fee'] ?? false),
                    'option_additional_fee' => (bool) ($item['option_additional_fee'] ?? false),
                    'field_price' => $rowFieldPrice,
                    'field_fee' => $rowFieldFee,
                    'option_price' => $rowOptionPrice,
                    'option_fee' => $rowOptionFee,
                    'operation_at' => now(),
                    'operation_by' => $operationBy,
                    'operation_by_user_type' => $operationByUserType,
                ]);
            }

            $chargeHistoryItems[] = [
                'name' => $item['label'] ?? 'Add-on',
                'price' => $item['price'],
                'fee' => $item['fee'],
                'payment_method' => $paymentMethod,
                'status' => $status,
            ];
            $totalChargePrice += $item['price'];
            $totalChargeFee += $item['fee'];
        }

        // Refunds settle against the ALREADY-executed combined refund — POSITIVE amounts
        // (status='refunded'/operation='refund' already say this is a refund; consumers net
        // charge vs. refund by summing each side separately via ABS() and subtracting, never by
        // summing both sides together in one signed SUM()), proportioned exactly as originally
        // charged (we know the real field/option split here, unlike a generic engine that only
        // has a lump refund amount to work with). Also grouped by whichever PaymentIntent each
        // row actually lands on, so the ledger writes ONE `transactions` row per PI actually
        // touched below, instead of a single combined row that would otherwise misattribute one
        // PI's money to another.
        $refundGroupsByPi = [];
        foreach ($refundItemsToProcess as $item) {
            $dbId = $item['db_id'] ?? null;
            if (!$dbId) {
                continue;
            }

            $original = $originalByDbId[$dbId] ?? null;

            // Ticket Refund (keep fee) vs 100% Refund (return fee) vs Don't Refund is the admin's
            // choice whenever they actually made one — every 'refund'-action caller passes
            // refund_type explicitly (the modal choice), and that choice applies uniformly to a
            // genuine full-item removal and an option/value downgrade (is_price_delta) alike.
            // But a net-CHARGE bulk edit (is_price_delta downgrade netted against a bigger new
            // charge) never shows that modal at all — refundTypeExplicit is false there, and
            // is_price_delta's field_fee/option_fee is already the correct delta computed by
            // calculatePricedAddonDelta(), so it must be stored in full rather than zeroed by a
            // refund_type that was only ever a fallback default, not a real choice.
            $refundFeeApplies = $refundType === 'full_refund'
                || (!$refundTypeExplicit && !empty($item['is_price_delta']));

            // "Don't Refund" means the customer receives $0 regardless of the item's original
            // price/fee — same "customer gets nothing" convention TourBookingAddonRepository's
            // own refund path already uses. This governs what's DISPLAYED/summed as money that
            // moved (below) — $0 for the customer either way.
            $refundPriceApplies = $refundType !== 'dont_refund';

            // Ledger bookkeeping (the row created below) is a SEPARATE concern from the display
            // above: DeleteBookingAddonBreakdownService's "still outstanding" netting sums
            // charge rows minus refund rows and only lists an item if that's still > 0.
            // 'ticket_refund' deliberately leaves the fee portion unmatched (refund row's fee
            // stays $0) so the fee remains "available" for a later upgrade to a 100% refund.
            // 'dont_refund' is a FINAL decision with nothing left to revisit — its refund row
            // must record the FULL fee (matching the charge row exactly, same as it already does
            // for price via $refundPriceApplies leaving price unzeroed below) so the item nets to
            // exactly $0 and stops appearing as refundable, instead of misleadingly leaving its
            // fee portion looking "still available" after the admin already decided not to
            // refund it at all.
            $ledgerFeeSettled = $refundFeeApplies || $refundType === 'dont_refund';

            // $routePerOriginPi: use each item's OWN original charge PI (falling back to
            // $transactionId only if that can't be resolved). Otherwise (every other caller,
            // which already executed ONE combined Stripe operation against $transactionId):
            // keep the original precedence exactly — $transactionId always wins, since that IS
            // the PaymentIntent this settlement actually refunded against.
            $rowTransactionId = $routePerOriginPi
                ? (($original->transaction_id ?? null) ?: $transactionId)
                : ($transactionId ?? ($original->transaction_id ?? null));
            $rowApplicationFeeId = $routePerOriginPi
                ? (($original->application_fee_id ?? null) ?: $applicationFeeId)
                : ($applicationFeeId ?? ($original->application_fee_id ?? null));

            // $original->payment_method normally wins (it's the exact method that item's own
            // charge used, more specific than the settlement-wide $paymentMethod). But when the
            // admin explicitly overrides HOW this settlement is being reimbursed to something
            // other than card (e.g. deletePeopleChangePeople()'s Cash/Check-Wire "is_cash" choice
            // in the refund modal, which skips the real Stripe refund entirely — see the
            // execute_stripe_refund gate above), that override must win instead: the money is
            // genuinely NOT going back to the original card, so the ledger/history must say so
            // rather than keep reporting the item's stale original charge method.
            $effectiveRefundPaymentMethod = $paymentMethod !== 'card' ? $paymentMethod : ($original->payment_method ?? $paymentMethod);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $booking->id,
                'tour_booking_addon_id' => $dbId,
                'operation_group_id' => $operationGroupId,
                'payment_method' => $effectiveRefundPaymentMethod,
                'status' => 'refunded',
                'operation' => 'refund',
                'transaction_id' => $rowTransactionId,
                'application_fee_id' => $rowApplicationFeeId,
                'field_additional_fee' => (bool) ($item['field_additional_fee'] ?? false),
                'option_additional_fee' => (bool) ($item['option_additional_fee'] ?? false),
                'field_price' => abs($item['field_price'] ?? 0),
                'field_fee' => $ledgerFeeSettled ? abs($item['field_fee'] ?? 0) : 0,
                'option_price' => abs($item['option_price'] ?? 0),
                'option_fee' => $ledgerFeeSettled ? abs($item['option_fee'] ?? 0) : 0,
                'notes' => $refundType === 'dont_refund' ? "Don't Refund" : null,
                'operation_at' => now(),
                'operation_by' => $operationBy,
                'operation_by_user_type' => $operationByUserType,
            ]);

            $historyItem = [
                'name' => $item['label'] ?? 'Add-on',
                'price' => $refundPriceApplies ? $item['price'] : 0,
                'fee' => $refundFeeApplies ? $item['fee'] : 0,
                'payment_method' => $effectiveRefundPaymentMethod,
                'status' => $refundType === 'dont_refund' ? "Don't Refund" : 'Refund',
            ];

            $groupKey = $rowTransactionId ?: '_none';
            if (!isset($refundGroupsByPi[$groupKey])) {
                $refundGroupsByPi[$groupKey] = [
                    'transaction_id' => $rowTransactionId,
                    'application_fee_id' => $rowApplicationFeeId,
                    'payment_method' => $effectiveRefundPaymentMethod,
                    // The item's TRUE original charge method, never overridden — used only to
                    // decide whether transaction_id above is a genuine Stripe PI worth a
                    // payment_mode lookup below. $effectiveRefundPaymentMethod is the wrong
                    // signal for that: it reports 'Cash' for a manually-overridden CARD original,
                    // which would wrongly skip a real lookup that should still happen.
                    'original_payment_method' => $original->payment_method ?? $effectiveRefundPaymentMethod,
                    'items' => [],
                    'total_price' => 0.0,
                    'total_fee' => 0.0,
                ];
            }
            $refundGroupsByPi[$groupKey]['items'][] = $historyItem;
            $refundGroupsByPi[$groupKey]['total_price'] += $refundPriceApplies ? $item['price'] : 0;
            $refundGroupsByPi[$groupKey]['total_fee'] += $refundFeeApplies ? $item['fee'] : 0;
        }

        if (!empty($chargeHistoryItems) && $status !== 'open') {
            $paymentIntent = null;
            if ($paymentMethod === 'card' && $transactionId) {
                try {
                    $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment(
                        $transactionId,
                        ['expand' => ['charges.data.transfer']]
                    );
                } catch (Exception $e) {
                    Log::error('ChangePeopleAddonService: unable to retrieve PaymentIntent for addon history', ['error' => $e->getMessage()]);
                }
            }

            // Add-ons ALWAYS get their own `transactions` row (charge_type='addons'), separate
            // from the tour-side row — same convention as one_time/price_per_unit add-ons at
            // booking creation. Both rows reference the same Stripe PaymentIntent/application fee
            // (one real charge), they just split the ledger amount cleanly in two.
            // $paymentIntent is null for a non-card charge (DESTINATION is the correct, inert
            // value there) and also, defensively, if the retrieve above failed for a genuine card
            // charge — a real anomaly with no better fallback available at this point.
            $chargeMode = $paymentIntent
                ? PaymentModeInference::fromStripeObject($paymentIntent)
                : PaymentModeResolver::DESTINATION;
            $txId = TransactionService::storeAddonsPaymentInTransaction(
                $booking, $paymentMethod, $totalChargePrice, $totalChargeFee, $paymentIntent, mode: $chargeMode
            );
            // Flat list for the "also refunded: ..." display alongside this charge, regardless of
            // which PaymentIntent(s) those refunds actually landed on — just the union of every
            // group's items built above.
            $refundHistoryItems = array_merge(...array_column($refundGroupsByPi, 'items'));
            HistoryService::storeAddonsPaymentInHistory(
                $booking, $chargeHistoryItems, 'added', $paymentIntent, $txId, $status,
                $totalChargePrice, $totalChargeFee, $refundHistoryItems
            );
        }

        // One `transactions` ledger row (and one history entry) PER PaymentIntent actually
        // touched above — when $routePerOriginPi grouped items across more than one PI, a single
        // combined row here would claim one PI's money moved through another. When there's only
        // ever one group (every other caller, and the common single-PI case), this behaves
        // exactly like the old single-row write.
        foreach ($refundGroupsByPi as $group) {
            if (empty($group['items'])) {
                continue;
            }

            // A manual Cash/Check-Wire (or Invoiced) settlement never actually touched Stripe —
            // the execute_stripe_refund gate above only calls the gateway when payment_method is
            // 'card'. Recording the item's real, original PaymentIntent/application fee id on
            // this `transactions` row anyway would misleadingly suggest it IS that Stripe
            // operation. Blank both here, matching the exact convention
            // TourBookingAddonRepository::deletePaxAddons() already uses for the identical
            // manual-reimbursement case (its own $manualRefundVia-gated transactionValue). The
            // internal tour_booking_addon_transactions ledger row above keeps the real PI
            // reference regardless — same as deletePaxAddons() — purely for audit/history-lookup
            // purposes, not as a claim that Stripe moved this money.
            $isManualRefundSettlement = $group['payment_method'] !== 'card';
            $transactionValue = [
                'price' => $group['total_price'],
                'fee' => $group['total_fee'],
                'transaction_id' => $isManualRefundSettlement ? '' : $group['transaction_id'],
                'application_fee_id' => $isManualRefundSettlement ? null : $group['application_fee_id'],
            ];
            $bookingAddonProxy = (object) ['payment_method' => $group['payment_method']];
            $refundRequestProxy = (object) ['description' => $financialContext['refund_note'] ?? null];

            // Same "own row" rule applies to refunds — a separate charge_type='addons' refund row,
            // referencing the same Stripe refund/PI as the tour-side refund row.
            $refundTxId = null;
            if ($refundType !== 'dont_refund') {
                // Use the group's real, unblanked original PI here — a manual reimbursement still
                // refunds money that was originally settled under that PI's real mode; only the
                // ledger-facing $transactionValue above blanks it for the manual-settlement case.
                // Gated on the item's TRUE original payment method (not $group['payment_method'],
                // which reports the possibly-overridden effective method — 'Cash' for a manually
                // reimbursed CARD original would wrongly skip a lookup that should still happen).
                //
                // The lookup can legitimately find no row yet: when !$routePerOriginPi,
                // $group['transaction_id'] is the SETTLEMENT'S OWN brand-new PI (a net-charge
                // Change People edit ties an embedded old-item refund to the new PI it's
                // charging, not to the old item's own already-settled one — see
                // $rowTransactionId above), and that new PI's own `transactions` row may not be
                // written yet at this point in the flow. DESTINATION is safe here specifically
                // because it's this row's only informational — the new PI's real transactions
                // row, written elsewhere by the properly mode-aware charge-creation call, is the
                // authoritative record of its actual settlement mode.
                $piRefundMode = ($group['original_payment_method'] === 'card' && !empty($group['transaction_id']))
                    ? (DB::table('transactions')->where('transaction_number', $group['transaction_id'])->value('payment_mode') ?? PaymentModeResolver::DESTINATION)
                    : PaymentModeResolver::DESTINATION;
                $refundTxId = TransactionService::storeAddonsRefundInTransaction($booking, $bookingAddonProxy, $transactionValue, mode: $piRefundMode);
            }
            HistoryService::storeAddonsRefundInHistory(
                $booking, $bookingAddonProxy, $transactionValue, $group['items'], 'removed',
                $refundType, $refundTxId, $refundRequestProxy, $chargeHistoryItems
            );
        }
    }

    /**
     * Resolves the payment-method/status/transaction/fee/refund-type context settleAddonTransactions()
     * needs, including the ledger-row derivation and application_fee_id resolution.
     *
     * @param array $paymentIntentCache Shared retrievePayment() cache, keyed by PaymentIntent id —
     *              passed by reference so the application_fee_id lookup here and the per-origin-PI
     *              capacity checks later in the same settlement reuse one Stripe call per PI.
     */
    private static function resolveSettlementContext(array $financialContext, array &$paymentIntentCache): array
    {
        $paymentMethod = strtolower($financialContext['payment_method'] ?? 'card');
        // Invoiced/Billable bookings settle their add-ons immediately (like cash/card), not
        // 'open' (true Pay Later, no money moved yet) — matching
        // TourBookingAddonRepository::processAddonFinancialTransactions()'s equivalent status
        // check. Without this, an add-on-only Change People edit on a Billable booking silently
        // charged status='open', so charge history/ledger writes below (gated on status!=='open')
        // never ran and the add-on's own "Add-ons" Payment entry never appeared.
        $status = $financialContext['status'] ?? (in_array($paymentMethod, ['cash', 'card', 'invoiced', 'billable']) ? 'paid' : 'open');
        $transactionId = $financialContext['transaction_id'] ?? null;
        $applicationFeeId = $financialContext['application_fee_id'] ?? null;
        $operationGroupId = $financialContext['operation_group_id'] ?? (string) Str::uuid();
        // Every 'refund' action caller explicitly sets refund_type (the admin's actual modal
        // choice); the pure 'charge' action callers (a net-charge bulk edit) never set it at all
        // — there is no refund popup to have made a choice in that path. Distinguishing "admin
        // chose ticket_refund" from "defaulted to ticket_refund because there was no choice to
        // make" is exactly what is_price_delta below needs to know.
        $refundTypeExplicit = array_key_exists('refund_type', $financialContext);
        $refundType = $financialContext['refund_type'] ?? 'ticket_refund';

        // The combined Stripe charge/refund covers tour + add-on money in ONE real operation, but
        // the `transactions` ledger still gets TWO rows for it — one tour-only, one add-on-only
        // (matching the existing one_time/price_per_unit convention, e.g. booking creation writes
        // a plain "Payment" row plus a separate charge_type='addons' row against the same PI).
        // When the caller supplies the tour-side ledger row, derive transaction_id/application_fee_id
        // from it directly rather than trusting separately-passed values (guaranteed accurate, and
        // saves a redundant Stripe retrieve call).
        $ledgerTransactionId = $financialContext['ledger_transaction_id'] ?? null;
        $ledgerRow = $ledgerTransactionId ? DB::table('transactions')->find($ledgerTransactionId) : null;
        if ($ledgerRow) {
            $transactionId = $ledgerRow->transaction_number ?: $transactionId;
            $applicationFeeId = $ledgerRow->application_fee_id ?: $applicationFeeId;
        }

        // Only deletePeopleChangePeople() sets this — it has no single already-executed combined
        // Stripe operation to book against (unlike callers that supply ledger_transaction_id
        // above), so it asks this method to execute its OWN Stripe refund for the add-on side.
        // That is exactly the case where refunding everything from one admin-picked
        // PaymentIntent is wrong: an add-on can have been originally charged on a DIFFERENT
        // PaymentIntent than the one selected for the tour-side refund (e.g. removing a child
        // whose add-on was charged on its own PI while the admin's selection only has headroom
        // from the adult's PI). Route each add-on's money — and its bookkeeping row — to ITS OWN
        // original charging PaymentIntent instead of the admin-selected one in that case only;
        // every other caller keeps refunding against $transactionId exactly as before.
        $routePerOriginPi = (bool) ($financialContext['execute_stripe_refund'] ?? false);

        // deletePeopleChangePeople() has no combined ledger row to borrow from — it only ever
        // passes `transaction_id` (the PaymentIntent the admin picked in the refund modal), never
        // `application_fee_id`. Without this, application_fee_id falls back to the addon's OWN
        // original charge row, which is wrong whenever the admin refunds from a DIFFERENT
        // PaymentIntent than the one that originally charged that addon (e.g. removing a child
        // whose addon was charged on its own PI, but the refund is drawn from the adult's PI that
        // still has headroom). Resolve it straight from Stripe so it always matches $transactionId,
        // the PI actually being refunded.
        if (!$applicationFeeId && $transactionId && $paymentMethod === 'card') {
            $paymentIntent = self::retrieveCachedPaymentIntent($paymentIntentCache, $transactionId);
            $charge = $paymentIntent->charges->data[0] ?? null;
            $feeRef = $charge->application_fee ?? null;
            $applicationFeeId = is_string($feeRef) ? $feeRef : ($feeRef->id ?? null);
        }

        return compact('paymentMethod', 'status', 'transactionId', 'applicationFeeId', 'operationGroupId', 'refundType', 'refundTypeExplicit', 'routePerOriginPi');
    }

    /**
     * retrievePayment() for a given PaymentIntent id, cached in the caller-supplied array so a
     * single settlement never fetches the same PI twice. Returns null (and logs once) if Stripe
     * can't be reached — callers read fields off the result via `?? null`-safe chains, so a null
     * here degrades to "skip this enrichment" exactly like the try/catch blocks it replaces did.
     */
    private static function retrieveCachedPaymentIntent(array &$cache, string $transactionId)
    {
        if (!array_key_exists($transactionId, $cache)) {
            try {
                $cache[$transactionId] = app(PaymentGatewayInterface::class)->retrievePayment($transactionId);
            } catch (Exception $e) {
                Log::error('ChangePeopleAddonService: unable to retrieve PaymentIntent for addon settlement', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
                $cache[$transactionId] = null;
            }
        }

        return $cache[$transactionId];
    }

    /**
     * Each refund item's own original 'paid' TourBookingAddonTransaction row, keyed by db_id —
     * used both to pick which PaymentIntent to actually refund against (when routing per origin
     * PI) and to attribute each refund row/ledger entry to it.
     */
    private static function mapRefundItemsToOriginalCharge(array $refundItemsToProcess): array
    {
        $originalByDbId = [];
        foreach ($refundItemsToProcess as $item) {
            $dbId = $item['db_id'] ?? null;
            if ($dbId && !array_key_exists($dbId, $originalByDbId)) {
                // withTrashed(): removePaxAddons() (called earlier in the SAME request, before
                // settlement) soft-deletes this exact row's TourBookingAddonTransaction as part
                // of removing the addon field — without withTrashed() this comes back empty.
                $originalByDbId[$dbId] = TourBookingAddonTransaction::withTrashed()
                    ->where('tour_booking_addon_id', $dbId)
                    ->where('status', 'paid')
                    ->orderBy('id', 'desc')
                    ->first();
            }
        }

        return $originalByDbId;
    }

    /**
     * Groups refund items by whichever PaymentIntent actually charged each one — NOT one lump
     * sum against the admin-selected PaymentIntent — so a child's add-on is refunded from the
     * PaymentIntent that actually collected that money, even when the admin picked a different
     * (e.g. the adult's) PaymentIntent for the tour-side refund. Issues one Stripe refund call
     * per distinct origin PaymentIntent, capping/scaling each PI's items independently against
     * only what's actually still available on THAT PI.
     *
     * @return array The (possibly amount-scaled) refund items to book — a PI with nothing left
     *               available has its items dropped entirely; every other PI's items pass through.
     */
    private static function executeStripeRefundsPerOriginPaymentIntent(
        array $refundItemsToProcess,
        array $originalByDbId,
        string $refundType,
        ?string $adminTransactionId,
        array &$paymentIntentCache
    ): array {
        $itemsByOriginPi = [];
        foreach ($refundItemsToProcess as $item) {
            $dbId = $item['db_id'] ?? null;
            $original = $dbId ? ($originalByDbId[$dbId] ?? null) : null;
            $originPi = ($original->transaction_id ?? null) ?: $adminTransactionId;
            $itemsByOriginPi[$originPi ?: ''][] = $item;
        }

        $processedItems = [];
        foreach ($itemsByOriginPi as $originPi => $items) {
            if ($originPi === '') {
                // No resolvable PaymentIntent at all for this subset — matches the original
                // behavior when the admin-selected transaction id was empty (no Stripe call
                // attempted, items still proceed to be booked below).
                $processedItems = array_merge($processedItems, $items);
                continue;
            }

            // Per item, not per group: Ticket Refund keeps the fee, 100% Refund returns it — the
            // same admin choice applies whether the item is a genuine full-item removal or an
            // option/value downgrade (is_price_delta); see settleAddonTransactions() above for why
            // is_price_delta must not bypass $refundType here either.
            $itemFeeApplies = fn (array $item) => $refundType === 'full_refund';

            $stripeRefundPrice = array_sum(array_column($items, 'price'));
            $stripeRefundFee = array_sum(array_map(
                fn (array $item) => $itemFeeApplies($item) ? ($item['fee'] ?? 0) : 0,
                $items
            ));
            $stripeRefundAmount = round($stripeRefundPrice + $stripeRefundFee, 2);
            if ($stripeRefundAmount <= 0) {
                continue;
            }

            $cappedRefundAmount = $stripeRefundAmount;
            $paymentIntent = self::retrieveCachedPaymentIntent($paymentIntentCache, $originPi);
            $charge = $paymentIntent->charges->data[0] ?? null;
            if ($charge) {
                $availableCents = (int) (($charge->amount_captured ?? 0) - ($charge->amount_refunded ?? 0));
                $requestedCents = (int) round($stripeRefundAmount * 100);
                if ($availableCents < $requestedCents) {
                    Log::warning('ChangePeopleAddonService: capping addon Stripe refund to what is actually available on the PaymentIntent (older charge likely predates a fee-calculation fix)', [
                        'transaction_id' => $originPi,
                        'requested_cents' => $requestedCents,
                        'available_cents' => $availableCents,
                    ]);
                    $cappedRefundAmount = round(max(0, $availableCents) / 100, 2);
                }
            }

            if ($cappedRefundAmount <= 0) {
                continue; // nothing left available on this specific PI — drop just its items
            }

            if ($cappedRefundAmount < $stripeRefundAmount) {
                $scale = $cappedRefundAmount / $stripeRefundAmount;
                $items = array_map(function ($item) use ($scale) {
                    foreach (['price', 'fee', 'field_price', 'field_fee', 'option_price', 'option_fee'] as $key) {
                        if (isset($item[$key])) {
                            $item[$key] = round($item[$key] * $scale, 2);
                        }
                    }
                    return $item;
                }, $items);
            }

            // No local Transaction row is threaded through this per-PaymentIntent path — infer
            // settlement mode directly from the already-retrieved PaymentIntent object itself
            // (ground truth for what that specific charge actually was), same signal
            // DisputeService::handleDisputeLoss() already relies on for its own Stripe-object case.
            $paymentMode = PaymentModeInference::fromStripeObject($paymentIntent);
            app(PaymentGatewayInterface::class)->refundPayment([
                'payment_intent' => $originPi,
                'amount' => (int) round($cappedRefundAmount * 100),
                'reverse_transfer' => true,
                'refund_application_fee' => $stripeRefundFee > 0,
            ], $paymentMode);
            $processedItems = array_merge($processedItems, $items);
        }

        return $processedItems;
    }
}
