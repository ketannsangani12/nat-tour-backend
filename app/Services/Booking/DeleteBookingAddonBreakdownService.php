<?php

namespace App\Services\Booking;

use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use Illuminate\Support\Facades\DB;

/**
 * Attaches per-PaymentIntent priced add-on data to a booking's payment transactions, for the
 * "Update Booking" (remove people) refund modal — never merged into the tour amount, per its own
 * separate block in that modal.
 */
class DeleteBookingAddonBreakdownService
{
    /**
     * Mutates each $paymentTransactions row in place, attaching:
     *  - addon_amount: outstanding (paid minus already-refunded) price-per-pax total on that PI
     *  - addon_items: the individual line items behind that total, for the modal's checkbox list
     *
     * Skipped entirely (rows left untouched, no queries run) unless the "Update Booking" modal's
     * blocks 2-4 would actually render for this booking — same gate _update-booking-modal.blade.php's
     * `@if` uses (Card or Cash, payment_type == 0), so split-payment bookings don't pay for these
     * queries at all. Deliberately NOT gated on transaction count — a single-PaymentIntent booking
     * can still have priced add-ons needing their own refund selection in Block 3, even though
     * Block 2's transaction picker only ever shows the one available option (and Cash has no
     * Block 2 / PaymentIntent picker at all — see the blade).
     *
     * @return array<string, \Illuminate\Support\Collection> Add-on items keyed by PaymentIntent,
     *         for any origin PI that has NO row at all in $paymentTransactions (e.g. a priced
     *         add-on charged/edited on its own PI without any accompanying tour-side charge —
     *         adding/removing a person never touched that specific PI, so it's absent from the
     *         tour-side transaction list entirely). The caller must fold these into whatever it
     *         builds for the modal, or these add-ons are invisible and unselectable there.
     */
    public static function attach($paymentTransactions, int $bookingId, bool $modalRendersAddonBlocks): array
    {
        if (!$modalRendersAddonBlocks) {
            return [];
        }

        // Attach the outstanding (paid minus already-refunded) PRICE-PER-PAX add-on amount tied
        // to each Stripe PaymentIntent, so the pax-delete refund modal can show the TRUE amount
        // a pax removal would also refund.
        //
        // IMPORTANT: this deliberately sums `tour_booking_addon_transactions` (joined to
        // tour_booking_addon_fields, filtered to pax_id IS NOT NULL) rather than the flat
        // `transactions` ledger's charge_type='addons' rows — the ledger row lumps together
        // EVERY add-on charged via that PaymentIntent, including one_time/price_per_unit add-ons
        // (photography package, water bottles, etc.) that are booking-level and NOT tied to any
        // specific pax. Those will never be refunded by removing a person, so including them
        // here would show a misleadingly large "will also refund $X" figure. Resolves to 0 for
        // any booking/tenant with no priced price_per_pax add-ons — no behavior change for those.
        // NOTE: deliberately NOT filtering out soft-deleted rows here — removePaxAddons() soft-
        // deletes BOTH the original 'paid' transaction row and the addon field row the moment a
        // pax is removed, but that original charge still needs to be summed against its 'refunded'
        // counterpart to net out to 0; excluding it would leave only the refund row and undercount
        // what's actually still outstanding on other, not-yet-removed pax.
        //
        // Charge and refund rows are summed SEPARATELY (each side via ABS(), then refund
        // subtracted from charge) rather than in one raw SUM(), because refund rows are NOT
        // written with a consistent sign across the codebase — ChangePeopleAddonService writes
        // them negative, but TourBookingAddonRepository's direct addon-edit refund path writes
        // them positive. A single SUM() across both operations would, for a positive-convention
        // refund, add it back onto the charge instead of netting it out.
        $addonTransactionsByPaymentIntent = DB::table('tour_booking_addon_transactions as tbat')
            ->join('tour_booking_addon_fields as tbaf', 'tbaf.id', '=', 'tbat.tour_booking_addon_id')
            ->where('tbat.tour_booking_id', $bookingId)
            ->whereNotNull('tbaf.pax_id')
            ->whereIn('tbat.status', ['paid', 'refunded'])
            ->selectRaw("
                tbat.transaction_id,
                SUM(CASE WHEN tbat.operation = 'charge' THEN ABS(tbat.field_price) + ABS(tbat.field_fee) + ABS(tbat.option_price) + ABS(tbat.option_fee) ELSE 0 END) as charge_total,
                SUM(CASE WHEN tbat.operation = 'refund' THEN ABS(tbat.field_price) + ABS(tbat.field_fee) + ABS(tbat.option_price) + ABS(tbat.option_fee) ELSE 0 END) as refund_total
            ")
            ->groupBy('tbat.transaction_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->transaction_id => round(((float) $row->charge_total) - ((float) $row->refund_total), 2)]);

        foreach ($paymentTransactions as $payment) {
            $payment->addon_amount = $addonTransactionsByPaymentIntent[$payment->transaction_number] ?? 0;
        }

        $addonLineItemsByTransactionNumber = self::buildAddonLineItemsByTransactionNumber($bookingId);

        $coveredTransactionNumbers = [];
        foreach ($paymentTransactions as $payment) {
            $payment->addon_items = $addonLineItemsByTransactionNumber[$payment->transaction_number] ?? collect();
            $coveredTransactionNumbers[$payment->transaction_number] = true;
        }

        // Some priced add-ons get charged (or later edited) on their OWN PaymentIntent with no
        // accompanying tour-side charge at all, so that PI never appears in $paymentTransactions
        // above — surface those separately so the caller can still show/refund them.
        $addonOnlyEntries = [];
        foreach ($addonLineItemsByTransactionNumber as $transactionNumber => $items) {
            if (!isset($coveredTransactionNumbers[$transactionNumber]) && $items->isNotEmpty()) {
                $addonOnlyEntries[$transactionNumber] = $items;
            }
        }

        return $addonOnlyEntries;
    }

    /**
     * Never-paid ("Pending" — sent via a payment link but not yet paid) priced add-on fields for
     * this booking. Shaped like attach()'s paid items (same id/pax_id/pax_index/label/rate_order
     * keys, so the modal's JS can merge both lists with no special-casing) plus 'pending' => true.
     *
     * This isn't just a display nicety: a removed pax's field ids flow into this modal's deletion
     * scope (#delete_booking_addon_scope_ids -> $explicitFieldIds -> deletePaxAddons()), and
     * deletePaxAddons() no-ops entirely on an empty/non-matching explicit field id list (see
     * TourBookingAddonRepository::deletePaxAddons()). A pax whose ONLY priced add-on was pending
     * never appeared in attach()'s paid-only listing, so that field id never reached the scope —
     * the field (and its pending payment-link item) was silently left behind, orphaned, even
     * though the pax itself was removed. Including it here is what gets it cleaned up at all.
     *
     * Only fields actually covered by an active Unpaid addon-type payment-link item are returned
     * — a field with no transaction AND no pending link at all is some other in-progress state
     * (e.g. mid-edit), not something this modal should present as "pending, will be cancelled."
     */
    public static function attachPending(int $bookingId): array
    {
        $paidFieldIds = DB::table('tour_booking_addon_transactions')
            ->where('tour_booking_id', $bookingId)
            ->whereIn('status', ['paid', 'refunded'])
            ->pluck('tour_booking_addon_id')
            ->all();

        $fields = DB::table('tour_booking_addon_fields as tbaf')
            ->leftJoin('tour_bookings_transact as pax_tbt', 'pax_tbt.id', '=', 'tbaf.pax_id')
            ->leftJoin('tour_package_rates as pax_tpr', 'pax_tpr.id', '=', 'pax_tbt.tour_package_rate_id')
            ->where('tbaf.tour_booking_id', $bookingId)
            ->whereNotNull('tbaf.pax_id')
            ->whereNull('tbaf.deleted_at')
            ->when(!empty($paidFieldIds), fn ($q) => $q->whereNotIn('tbaf.id', $paidFieldIds))
            ->select([
                'tbaf.id', 'tbaf.pax_id', 'tbaf.pax_index', 'tbaf.person_ordinal',
                'tbaf.field_label', 'tbaf.option_label',
                'pax_tpr.rate_for', 'pax_tpr.orderby as rate_order',
            ])
            ->get();

        if ($fields->isEmpty()) {
            return [];
        }

        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        $pendingFieldIds = [];
        if ($groupId) {
            $link = TourBookingPaymentLink::where('order_id', $groupId)
                ->where('status', 'Unpaid')
                ->where('section', 'remaining_payment')
                ->first();
            if ($link) {
                $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
                    ->where('type', 'addon')
                    ->where('status', 'Unpaid')
                    ->get();
                foreach ($addonItems as $item) {
                    $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
                    $financial = $details['financial'] ?? [];
                    $fieldToDb = $details['field_to_db'] ?? [];
                    foreach (array_merge($financial['to_charge'] ?? [], $financial['to_refund'] ?? []) as $lineItem) {
                        if (!empty($lineItem['old_db_id'])) {
                            $pendingFieldIds[] = (int) $lineItem['old_db_id'];
                            continue;
                        }
                        $lookupKey = ($lineItem['field_id'] ?? '') . '_' . ($lineItem['pax_index'] ?? '');
                        if (isset($fieldToDb[$lookupKey])) {
                            $pendingFieldIds[] = (int) $fieldToDb[$lookupKey];
                        }
                    }
                }
            }
        }
        $pendingFieldIds = array_unique($pendingFieldIds);
        if (empty($pendingFieldIds)) {
            return [];
        }

        $fields = $fields->filter(fn ($f) => in_array((int) $f->id, $pendingFieldIds))->values();
        if ($fields->isEmpty()) {
            return [];
        }

        // Reuses the SAME headcount-aware reslotting rule buildPaxIndexPositionFromSurvivingItems()
        // already applies to paid items — $fields carries the identical pax_id/pax_index/
        // person_ordinal shape it expects, so both lists agree on which checkbox/position label
        // ("Adult 2", ...) a given field belongs to.
        $paxPositionByPaxId = self::buildPaxIndexPositionFromSurvivingItems($fields);

        return $fields->map(function ($f) use ($paxPositionByPaxId) {
            $label = trim(implode(' — ', array_filter([$f->field_label, $f->option_label])));
            $paxPosition = $paxPositionByPaxId[$f->pax_id][$f->pax_index] ?? 0;
            $personLabel = trim(($f->rate_for ?: 'Person') . ' ' . ($paxPosition + 1));
            return [
                'id' => (int) $f->id,
                'pax_id' => (int) $f->pax_id,
                'pax_index' => (int) $paxPosition,
                'rate_order' => (int) ($f->rate_order ?? 0),
                'person_label' => $personLabel,
                'label' => $label !== '' ? $label : 'Add-on',
                // Never actually paid — nothing to preview/refund, but still a real dollar key
                // (not just absent) so the JS's price/fee math never has to special-case it.
                'price' => 0.0,
                'fee' => 0.0,
                'pending' => true,
            ];
        })->values()->all();
    }

    /**
     * Individual add-on line items per PaymentIntent (name, price, fee kept separate) for the
     * "Update Booking" modal's add-ons block — shown alongside, never merged into, the tour
     * amount. Same pax_id scope as the addon_amount total above (booking-level add-ons excluded
     * — never touched by removing a person, own refund flow).
     *
     * Grouped by tour_booking_addon_id (the specific field/option selection), NOT by
     * transaction_id: a refund row can carry a DIFFERENT transaction_id than the charge it's
     * refunding (a historical data-correctness issue on some bookings — the refund lands
     * wherever the admin's combined refund actually drew from, not necessarily back on the
     * PaymentIntent that originally charged this specific selection). Netting charge+refund per
     * selection first, then keeping only the ones still genuinely outstanding (HAVING > 0), and
     * attaching each survivor to whichever PaymentIntent its *charge* row belongs to (not
     * wherever a stray refund row landed), keeps this list honest even on bookings with that
     * history.
     */
    protected static function buildAddonLineItemsByTransactionNumber(int $bookingId)
    {
        $rawItems = DB::table('tour_booking_addon_transactions as tbat')
            ->join('tour_booking_addon_fields as tbaf', 'tbaf.id', '=', 'tbat.tour_booking_addon_id')
            // Only for a person-facing label ("Adult 1", "Child 2", ...) so the admin can tell
            // WHICH specific person within a rate group each line item belongs to — a rate
            // group can hold several people, each with their own add-on selection.
            ->leftJoin('tour_bookings_transact as pax_tbt', 'pax_tbt.id', '=', 'tbaf.pax_id')
            ->leftJoin('tour_package_rates as pax_tpr', 'pax_tpr.id', '=', 'pax_tbt.tour_package_rate_id')
            ->where('tbat.tour_booking_id', $bookingId)
            ->whereNotNull('tbaf.pax_id')
            // A soft-deleted field row means that specific person's add-on selection was already
            // removed (removePaxAddons() soft-deletes the field row the moment the pax it belongs
            // to is removed) — it must never resurface as a selectable item in a LATER, unrelated
            // pax removal's modal. Without this, a "Ticket Refund" (fees kept) settlement — which
            // deliberately leaves the fee portion unrefunded so it stays "available" for that SAME
            // still-open item to later be upgraded to a 100% refund — nets to (price=0, fee>0)
            // instead of (0, 0), still clears the HAVING filter below on the fee alone, and keeps
            // occupying a pax_position slot. That phantom, fee-only remnant then shifts every
            // still-active person's position down by one, so the modal shows the WRONG person's
            // add-on (or the fee-only remnant itself, at only the fee amount) under whichever
            // label the shifted position lands on — this is the "only fee amount, no base price"
            // bug. A still-ACTIVE person's own genuine "fee kept available" row is untouched here
            // (their field row was never soft-deleted), so that legitimate case still works.
            ->whereNull('tbaf.deleted_at')
            ->whereIn('tbat.status', ['paid', 'refunded'])
            // Charge and refund rows are summed SEPARATELY (each side via ABS(), then refund
            // subtracted from charge) rather than summed together in one raw SUM(), because
            // refund rows are NOT written with a consistent sign across the codebase —
            // ChangePeopleAddonService writes them negative, but TourBookingAddonRepository's
            // direct addon-edit refund path (ADDONS_FULL_REFUND / ADDONS_TICKET_REFUND) writes
            // them positive. A single SUM(field_price+...) across both operations would, for a
            // positive-convention refund, ADD it back onto the charge instead of netting it out —
            // making an already-fully-refunded item (e.g. one removed then re-added under a new
            // pax_index) reappear here at DOUBLE its original amount instead of disappearing.
            ->selectRaw("
                tbat.tour_booking_addon_id,
                MAX(CASE WHEN tbat.operation = 'charge' THEN tbat.transaction_id END) as origin_transaction_id,
                tbaf.pax_id,
                tbaf.pax_index,
                tbaf.person_ordinal,
                pax_tpr.rate_for,
                pax_tpr.orderby as rate_order,
                tbaf.field_label,
                tbaf.option_label,
                SUM(CASE WHEN tbat.operation = 'charge' THEN ABS(tbat.field_price) + ABS(tbat.option_price) ELSE 0 END) as charge_price,
                SUM(CASE WHEN tbat.operation = 'charge' THEN ABS(tbat.field_fee) + ABS(tbat.option_fee) ELSE 0 END) as charge_fee,
                SUM(CASE WHEN tbat.operation = 'refund' THEN ABS(tbat.field_price) + ABS(tbat.option_price) ELSE 0 END) as refund_price,
                SUM(CASE WHEN tbat.operation = 'refund' THEN ABS(tbat.field_fee) + ABS(tbat.option_fee) ELSE 0 END) as refund_fee
            ")
            ->groupBy('tbat.tour_booking_addon_id', 'tbaf.pax_id', 'tbaf.pax_index', 'tbaf.person_ordinal', 'pax_tpr.rate_for', 'pax_tpr.orderby', 'tbaf.field_label', 'tbaf.option_label')
            ->havingRaw('(charge_price + charge_fee - refund_price - refund_fee) > 0.001')
            ->orderBy('tbat.tour_booking_addon_id')
            ->get()
            ->map(function ($item) {
                $item->net_price = round(((float) $item->charge_price) - ((float) $item->refund_price), 2);
                $item->net_fee = round(((float) $item->charge_fee) - ((float) $item->refund_fee), 2);
                return $item;
            });

        $paxIndexPositionByPaxId = self::buildPaxIndexPositionFromSurvivingItems($rawItems);

        // Card last-4 per PaymentIntent, so the modal can show WHICH card each add-on item will
        // actually be refunded from — an add-on charged/edited on its own PI (no accompanying
        // tour-side charge) has no row in the "Tour Transaction" picker at all (that block is
        // deliberately tour-money-only), so without this its card is otherwise invisible anywhere
        // in the modal even though the refund is genuinely drawn from it.
        $cardLast4ByTransactionNumber = self::buildCardLast4ByTransactionNumber(
            $rawItems->pluck('origin_transaction_id')->filter()->unique()->values()->all()
        );

        return $rawItems
            ->map(function ($item) use ($paxIndexPositionByPaxId) {
                // 0-based position of this item's real pax_index within its own rate group —
                // see buildPaxIndexPositionFromSurvivingItems() below. Falls back to 0 only for
                // the (essentially never-happening) case of a price_per_pax addon whose
                // pax_index isn't found among that pax_id's own distinct values.
                $item->pax_position = $paxIndexPositionByPaxId[$item->pax_id][$item->pax_index] ?? 0;
                return $item;
            })
            // Ordered by the rate group's own display order (same "orderby" the rate group
            // list elsewhere on this page sorts by — Adult before Child, etc.), then by
            // position within that group, so the modal's combined add-ons list (merged across
            // every PaymentIntent) reads in the same person order the rest of the page uses,
            // not whatever order the DB happens to return rows in.
            ->sortBy([['rate_order', 'asc'], ['pax_position', 'asc']])
            ->groupBy('origin_transaction_id')
            ->map(function ($items) use ($cardLast4ByTransactionNumber) {
                return $items->map(function ($item) use ($cardLast4ByTransactionNumber) {
                    $label = trim(implode(' — ', array_filter([$item->field_label, $item->option_label])));
                    $personLabel = trim(($item->rate_for ?: 'Person') . ' ' . ($item->pax_position + 1));
                    return [
                        // tour_booking_addon_fields.id — matches calculatePricedAddonDelta()'s
                        // `db_id` and TourBookingAddonRepository::deletePaxAddons()'s field ids,
                        // so the admin's checkbox selection in the modal can be threaded straight
                        // through to both backend refund-settlement paths without translation.
                        'id' => (int) $item->tour_booking_addon_id,
                        // tour_bookings_transact.id — the rate group (Adult/Child/...) this
                        // selection belongs to, so the modal can show only add-ons for the
                        // rate groups the admin is actually removing people from.
                        'pax_id' => (int) $item->pax_id,
                        // 0-based POSITION within that rate group (NOT the raw opaque
                        // pax_index string) — combined with pax_id this identifies the exact
                        // person, matching the direct "Edit Booking" delete checkboxes' own
                        // per-person ordering ($a in that loop).
                        'pax_index' => (int) $item->pax_position,
                        // Rate group's own display order (tour_package_rates.orderby) — the
                        // modal's JS re-sorts by this after merging items from every
                        // PaymentIntent, since that merge would otherwise interleave rate
                        // groups in whatever order their transactions happen to be in.
                        'rate_order' => (int) $item->rate_order,
                        // e.g. "Adult 2" — lets the modal show/select add-ons per specific
                        // person instead of lumping every person in the rate group together.
                        'person_label' => $personLabel,
                        'label' => $label !== '' ? $label : 'Add-on',
                        'price' => round((float) $item->net_price, 2),
                        'fee' => round((float) $item->net_fee, 2),
                        // Which card this item will actually be refunded from — surfaced because
                        // the "Tour Transaction" picker is deliberately tour-money-only and won't
                        // show a card whose only charge on this booking is add-ons.
                        'card_last_4' => $cardLast4ByTransactionNumber[$item->origin_transaction_id] ?? null,
                    ];
                })->values();
            });
    }

    /**
     * Card last-4 digit per Stripe PaymentIntent (transaction_number), resolved via whichever
     * `tour_booking_history` row recorded the original charge on that PI — same join pattern
     * HistoryService::storeAddonsRefundInHistory() uses to find the card for a specific PI,
     * rather than "whichever card was used first on this booking."
     */
    private static function buildCardLast4ByTransactionNumber(array $transactionNumbers): array
    {
        if (empty($transactionNumbers)) {
            return [];
        }

        return DB::table('tour_booking_history as h')
            ->join('transactions as t', 't.id', '=', 'h.transaction_id')
            ->whereIn('t.transaction_number', $transactionNumbers)
            ->whereNotNull('h.card_last_4_digit')
            ->where('h.card_last_4_digit', '!=', '')
            ->orderBy('h.id', 'asc')
            ->select(['t.transaction_number', 'h.card_last_4_digit'])
            ->get()
            ->groupBy('transaction_number')
            ->map(fn ($rows) => $rows->first()->card_last_4_digit)
            ->toArray();
    }

    /**
     * tour_booking_addon_fields.pax_index is NOT a plain 0/1/2 — it's an opaque, per-add
     * synthetic string (e.g. "2642-1781780332-6a33cf6cbc719-0"), so it can't be compared
     * directly to the plain 0-based position ($a) the "Edit Booking" delete checkboxes and the
     * individual-pax-addons preview feature use. Re-derive that SAME position numbering here,
     * from tbaf.person_ordinal — the SAME 0-based "which physical person" value
     * getPaxAddonsIndexes() (the pax-addons-preview badge next to each checkbox) already keys
     * off — with the identical headcount-aware reslotting rule that function uses, so both
     * features agree on which checkbox an add-on belongs to.
     *
     * A prior version of this method instead enumerated each distinct pax_index in the order
     * its (surviving) row was CREATED — i.e. "1st add-on charged this booking = position 0, 2nd
     * = position 1", with no regard for which physical person it actually belongs to. That drifts
     * from reality the moment add-ons are added/removed for DIFFERENT people over the booking's
     * life: e.g. pax #1 gets an add-on first (creation order 0) and later has it removed, then
     * pax #2 and #3 each get one — the 2nd-created survivor (pax #2's, real slot 1) still landed
     * on position 0, so the "Edit Booking" delete modal wrongly showed pax #2's add-on under pax
     * #1's checkbox, and pax #3's add-on (real slot 2, 3rd-created) never matched pax #3's own
     * checkbox at all. person_ordinal is captured once, at that add-on's own creation/reassignment
     * time, and reflects the person's REAL slot, not creation order — using it directly (with the
     * same overflow-compaction as getPaxAddonsIndexes(), for when an earlier-ordinal sibling has
     * since been fully removed and the headcount shrank) fixes both symptoms at once.
     *
     * Falls back to the old creation-order approximation only when a pax_id group has ANY row
     * missing person_ordinal (bookings/add-ons created before that column existed — see its
     * migration) — those keep rendering exactly as before rather than mixing two different
     * numbering schemes in the same rate group.
     *
     * Deliberately derived from $rawItems — the SAME net-charge-filtered (HAVING > 0.001) rows
     * that end up displayed — rather than every historical tour_booking_addon_fields row
     * (including trashed ones). A field whose charge+refund has already netted to $0 (e.g. an
     * add-on that was removed then re-added for the SAME still-present person, which creates a
     * brand new pax_index rather than reusing the old one) must NOT occupy a position slot, or
     * it pushes the still-active person's item past the plain 0/1 position the "Edit Booking"
     * delete checkboxes use for the CURRENT people list — exactly the mismatch that silently
     * excluded a re-added add-on from ever matching its owner's checkbox. A genuinely-removed
     * pax whose add-on hasn't been refunded YET still keeps its own slot here, since its net
     * amount is still positive — which is what keeps ITS label/position stable across reloads.
     */
    protected static function buildPaxIndexPositionFromSurvivingItems($rawItems)
    {
        // Current headcount per pax_id (tour_bookings_transact row) — needed to replicate
        // getPaxAddonsIndexes()'s "still fits within the current headcount keeps its exact
        // slot, only overflow gets compacted" rule. Batched once for every pax_id in scope
        // rather than per-group, to avoid an N+1 query per rate group.
        $paxIds = $rawItems->pluck('pax_id')->filter()->unique()->values();
        $headcountByPaxId = $paxIds->isEmpty() ? collect() : DB::table('tour_bookings_transact')
            ->whereIn('id', $paxIds)
            ->get(['id', 'tourists', 'infants', 'is_infant_rate'])
            ->mapWithKeys(fn ($row) => [
                (int) $row->id => (int) (((int) $row->is_infant_rate === 1) ? $row->infants : $row->tourists),
            ]);

        return $rawItems
            ->groupBy('pax_id')
            ->map(function ($rows, $paxId) use ($headcountByPaxId) {
                // One entry per distinct pax_index (a person can hold several add-on
                // selections/rows) — same dedup getPaxAddonsIndexes() does, and for the same
                // reason: several rows can share one physical person.
                $ordinalByPaxIndex = $rows
                    ->groupBy('pax_index')
                    ->map(fn ($group) => $group->pluck('person_ordinal')->filter(fn ($v) => !is_null($v))->first());

                if ($ordinalByPaxIndex->contains(fn ($v) => is_null($v))) {
                    // Legacy row(s) with no person_ordinal at all — keep the old
                    // creation-order approximation for this whole rate group rather than
                    // guessing a real slot for the rows that have no ordinal to give.
                    $positions = [];
                    $next = 0;
                    foreach ($ordinalByPaxIndex->keys() as $paxIndex) {
                        $positions[$paxIndex] = $next++;
                    }
                    return $positions;
                }

                $sortedOrdinals = $ordinalByPaxIndex->map(fn ($v) => (int) $v)->sort();
                $totalTourists = $headcountByPaxId[(int) $paxId] ?? null;

                if ($totalTourists === null) {
                    // Current headcount for this pax_id couldn't be resolved (e.g. its
                    // tour_bookings_transact row is gone) — fall back to plain ordinal
                    // order without headcount validation, same as getPaxAddonsIndexes()
                    // does when ITS $totalTourists is unknown.
                    $positions = [];
                    $next = 0;
                    foreach ($sortedOrdinals->keys() as $paxIndex) {
                        $positions[$paxIndex] = $next++;
                    }
                    return $positions;
                }

                // Exact same reslotting rule as getPaxAddonsIndexes(): an ordinal that still
                // fits within the CURRENT headcount keeps that EXACT slot. Only ordinals that
                // no longer fit (an earlier-ordinal sibling was fully removed and the
                // headcount shrank) are "overflow" — compacted, in ordinal order, into
                // whichever slots are still unclaimed.
                $claimedSlots = []; // slot => pax_index
                $overflowPaxIndexes = [];
                foreach ($sortedOrdinals as $paxIndex => $ordinal) {
                    if ($ordinal >= 0 && $ordinal < $totalTourists && !isset($claimedSlots[$ordinal])) {
                        $claimedSlots[$ordinal] = $paxIndex;
                    } else {
                        $overflowPaxIndexes[] = $paxIndex;
                    }
                }
                for ($slot = 0; $slot < $totalTourists && !empty($overflowPaxIndexes); $slot++) {
                    if (!isset($claimedSlots[$slot])) {
                        $claimedSlots[$slot] = array_shift($overflowPaxIndexes);
                    }
                }

                $positions = [];
                foreach ($claimedSlots as $slot => $paxIndex) {
                    $positions[$paxIndex] = $slot;
                }
                return $positions;
            });
    }
}
