<?php

namespace App\Repositories\TourBooking;

use App\Repositories\Repository;
use App\Models\AddonField;
use App\Models\AddonCategory;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\Transaction;
use App\Models\AddonFieldOption;
use App\Models\BookingTransact;
use App\Models\TourPackage;
use App\Models\Customer;
use App\Services\Booking\BookingService;
use App\Services\Booking\TransactionService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\PaymentLinkService;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\RemainingPayment\RemainingPaymentService;
use App\Services\TourPackage\TourPackageService;
use App\Utils\BookingUtils;
use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use Stripe\Exception\CardException;
use App\Exceptions\Core\GeneralCoreException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TourBookingAddonRepository extends Repository
{
    /**
         * Update nested add-ons for the booking
         *
         * @param object $request
         * @return array
         * @throws Exception
         */
    public function updateBookingAddons(object $request): array
    {
        // A cancelled booking is done — nothing here should be able to add a new charge (or
        // even a $0 field change) against it. Without this, the add-ons editor happily walked
        // through processAddonFinancialTransactions() below and actually charged Stripe for a
        // brand-new add-on on an already-cancelled booking.
        if (DB::table('tour_bookings_master')->where('id', (int) $request->booking_id)->value('status') === 'Cancelled') {
            return [
                'success' => false,
                'message' => 'This booking has been cancelled — add-ons can no longer be added or updated.',
            ];
        }

        DB::beginTransaction();
        try {
            $bookingId = (int) $request->booking_id;
            $payLinkSnapshot = $this->snapshotPayLaterAddonsForPaymentLink($bookingId);

            $added   = is_string($request->added)   ? json_decode($request->added, true)   : ($request->added ?? []);
            $updated = is_string($request->updated) ? json_decode($request->updated, true) : ($request->updated ?? []);
            $deleted = is_string($request->deleted) ? json_decode($request->deleted, true) : ($request->deleted ?? []);

            $fieldToDb = $this->upsertBookingAddonFields(
                $request->booking_id,
                $request->form_id,
                null,
                null,
                $added,
                $updated,
                $deleted
            );

            $financial = $request->financial;
            if (is_string($financial)) {
                $financial = json_decode($financial, true);
            }

            $this->syncPendingAddonLinkForEditedFields($bookingId, $updated, $financial);

            if (is_array($financial) && in_array($financial['action'] ?? '', ['charge', 'refund'])) {
                $resolvedPI = $this->processAddonFinancialTransactions(
                    (int) $request->booking_id,
                    $financial,
                    $fieldToDb
                );

                // If the PI covered a pending remaining_payment link (backend always includes it
                // when present), finalize those items now so they are marked Paid against this PI.
                // Placed here (not inside processAddonFinancialTransactions) to avoid infinite
                // recursion: AddonItemHandler::finalize → processAddonPaymentFromLink →
                // processAddonFinancialTransactions → finalizeItems → AddonItemHandler::finalize…
                if (($financial['action'] ?? '') === 'charge' && strtolower($financial['payment_method'] ?? '') === 'card' && $resolvedPI) {
                    $groupId = DB::table('tour_bookings_master')->where('id', (int) $request->booking_id)->value('group_id');
                    if ($groupId) {
                        $pendingLink = TourBookingPaymentLink::where('order_id', $groupId)
                            ->where('status', 'Unpaid')
                            ->where('section', 'remaining_payment')
                            ->first();
                        if ($pendingLink) {
                            try {
                                RemainingPaymentService::finalizeItems($pendingLink, $resolvedPI, (int) $request->booking_id);
                            } catch (\Exception $e) {
                                Log::error('RemainingPaymentService finalizeItems failed after addon card charge: ' . $e->getMessage());
                            }
                        } else {
                            $this->settleLumpSumLinkForCardCharge((int) $request->booking_id, $groupId, $resolvedPI);
                        }
                    }
                }
            } elseif (is_array($financial) && (!empty($financial['to_charge']) || !empty($financial['to_refund']))) {
                // Net-zero edit (e.g. removing one priced field while adding a different one of
                // equal value) — no Stripe charge/refund is needed, but the fields DID change,
                // so the audit trail must still record it. Without this, a net-$0 edit here
                // wrote NO history entry at all — see the analogous (partial, now-fixed) gap in
                // savePaxAddons() for the bulk-pax equivalent of this same scenario.
                $this->recordZeroNetAddonHistory((int) $request->booking_id, $financial);
            }

            $this->syncPayLaterAddonsPaymentLink($bookingId, $payLinkSnapshot);

            DB::commit();
            return ['success' => true];

        } catch (Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Addon update failed',
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Snapshot a booking's Pay-Later add-ons total BEFORE an add-on edit, so
     * syncPayLaterAddonsPaymentLink() can later apply the delta to that booking's active
     * whole-booking "Payment Link" (section=null, sent via the toolbar's Payment Link button) once
     * the edit's fields are saved — mirrors addCustomCharge()'s own "Payment link query" for custom
     * charges, which add-ons never got until now. A booking with any Completed transaction isn't
     * genuinely Pay-Later (it's been paid, at least in part), so this is skipped entirely for it.
     *
     * @param int $bookingId
     * @return array{transaction: ?object, natFeePct: float, oldAddonsTotal: float}
     */
    private function snapshotPayLaterAddonsForPaymentLink(int $bookingId): array
    {
        $transaction = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
        $natFeePct   = 0.0;
        $oldTotal    = 0.0;

        if ($transaction === null) {
            $natFeePct = (float) (BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);
            $oldTotal  = (float) (BookingService::fetchPayLaterAddonsWithPrice($bookingId, $natFeePct)['addons_total'] ?? 0);
        }

        return ['transaction' => $transaction, 'natFeePct' => $natFeePct, 'oldAddonsTotal' => $oldTotal];
    }

    /**
     * Companion to snapshotPayLaterAddonsForPaymentLink() — recomputes the booking's Pay-Later
     * add-ons total AFTER the edit and, if it changed and an active whole-booking Payment Link
     * exists, regenerates and resends it via PaymentLinkService::paymentLinkUpdate('Add-ons').
     *
     * @param int   $bookingId
     * @param array $snapshot Result of snapshotPayLaterAddonsForPaymentLink(), taken before the edit.
     */
    private function syncPayLaterAddonsPaymentLink(int $bookingId, array $snapshot): void
    {
        if ($snapshot['transaction'] !== null) {
            return;
        }

        $newTotal = (float) (BookingService::fetchPayLaterAddonsWithPrice($bookingId, $snapshot['natFeePct'])['addons_total'] ?? 0);
        if (abs($newTotal - $snapshot['oldAddonsTotal']) <= 0.001) {
            return;
        }

        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        $tourBookingPaymentLink = $groupId
            ? TourBookingPaymentLink::where('order_id', $groupId)->where('status', 'Unpaid')->first()
            : null;
        if (!$tourBookingPaymentLink) {
            return;
        }

        $tourBooking = TourBooking::find($bookingId);
        $info        = BookingUtils::getBookingDetails($bookingId);
        PaymentLinkService::paymentLinkUpdate(
            ['oldAddonsTotal' => $snapshot['oldAddonsTotal'], 'newAddonsTotal' => $newTotal],
            $info,
            $tourBooking,
            $tourBookingPaymentLink,
            'Add-ons'
        );
    }

    /**
     * Settles a coexisting LUMP-SUM payment link (section=NULL — the original booking-creation
     * link, or a paymentLinkUpdate() replacement of it) once StripeService::
     * stripeCreatePaymentIntentForAddonEditCharge() has folded its amount into the SAME
     * PaymentIntent as a Card-charged add-on edit. Shared by updateBookingAddons() and
     * updatePaxAddons() — both call this from their own "no itemized link" branch, right where
     * RemainingPaymentService::finalizeItems() settles the itemized case. Records a real
     * Completed `transactions` row for the lump sum's own amount (separate from the addon's own
     * TourBookingAddonTransaction row) so the booking's own $trans-driven Payment Total/badge
     * picks it up, a matching "Payment" History & Notes entry, and retires the link — the same
     * three things paying through the link's own hosted checkout would have done.
     *
     * @param int    $bookingId
     * @param string $groupId    The booking's order_id (tour_bookings_master.group_id).
     * @param object $resolvedPI The already-retrieved, confirmed Stripe PaymentIntent this charge went through on.
     */
    private function settleLumpSumLinkForCardCharge(int $bookingId, string $groupId, object $resolvedPI): void
    {
        $pendingLumpSumLink = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->where(function ($q) {
                $q->whereNull('section')->orWhere('section', '!=', 'remaining_payment');
            })
            ->first();
        if (!$pendingLumpSumLink) {
            return;
        }

        $amount = (float) $pendingLumpSumLink->amount;
        $feePct = (float) (BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);
        // The link's amount is a single fee-INCLUSIVE total with no stored per-component
        // breakdown (unlike an itemized link's items, which each carry their own details.
        // platform_fees) -- back the fee portion out algebraically assuming it was marked up by
        // this same flat percentage, matching how storeTourBookingPaymentLink()'s callers build
        // it in the first place (ticket subtotal × (1 + pct/100)). Exact for a link that has
        // never been through a paymentLinkUpdate() replacement computed at a different rate;
        // a reasonable, grounded approximation otherwise -- never a hardcoded 0.
        $feeAmount = $feePct > 0 ? round($amount - ($amount / (1 + $feePct / 100)), 2) : 0.0;

        $applicationFeeId = $resolvedPI->charges->data[0]->application_fee ?? null;
        $transactionId = TransactionService::storePaymentInTransaction(
            $bookingId,
            $amount,
            $feeAmount,
            'Card',
            0,
            $resolvedPI->id,
            $applicationFeeId,
            [],
            mode: PaymentModeInference::fromStripeObject($resolvedPI)
        );

        $master   = TourBooking::find($bookingId);
        $cardInfo = $resolvedPI->charges->data[0]->payment_method_details ?? [];
        $crd      = $cardInfo['card_present']['last4'] ?? $cardInfo['card']['last4'] ?? '';
        $cardType = $cardInfo['card_present']['brand'] ?? $cardInfo['card']['brand'] ?? '';

        $historyId = HistoryService::storeCardPaymentInHistory(
            $bookingId,
            ($master->tourists ?? 0) + ($master->infants ?? 0),
            ['total' => $amount, 'fees' => $feeAmount],
            $crd,
            $cardType,
            BookingService::createdUserInfo()
        );

        // storeCardPaymentInHistory() only writes the `details` JSON blob -- ticket_cost/
        // platform_fees/transaction_id are separate row columns the "Payment" history partial
        // reads directly (see _payment.blade.php: $hist->ticket_cost / $hist->platform_fees),
        // normally populated by CardBookingService::run() after a fresh-booking Card payment.
        // That method does a lot more than this (discount/addon/custom-charge/tip processing
        // for a brand-new booking) that doesn't apply here, so only mirror its exact field
        // assignments for these two columns rather than calling the whole thing.
        DB::table('tour_booking_history')->where('id', $historyId)->update([
            'ticket_cost'    => $amount - $feeAmount,
            'platform_fees'  => $feeAmount,
            'transaction_id' => $transactionId,
            'payment_type'   => 'Card',
            'is_processed'   => 1,
        ]);

        // Mirrors PaymentLinkService::handleStripePayment()'s own update() when it settles a
        // booking's amount for the first time via its hosted checkout: total/booking_total take
        // the fee-INCLUSIVE tour amount (what "Booking Total" is meant to reflect once actually
        // collected, not the fee-exclusive value left over from booking creation), and
        // service_commission carries ONLY the tour's own fee -- never the add-ons'/custom
        // charges' fees, which the "Booking Fees:" summary line (tour-booking-info.blade.php)
        // already adds in separately from their own tracking (addons_gross_fee,
        // custom_charge_fees). Added to any existing service_commission rather than overwritten,
        // in case this booking already carries one from an unrelated source.
        $master->update([
            'total'              => $amount,
            'booking_total'      => $amount,
            'service_commission' => (float) $master->service_commission + $feeAmount,
        ]);

        $pendingLumpSumLink->update(['status' => 'Paid', 'updated_at' => now()]);
    }

    /**
     * An edited add-on field can still be sitting on a pending (Unpaid) addon payment-link item —
     * e.g. sent via "Pay with Link" as X-Large, then downgraded to Large before the customer paid.
     * Reprices (or drops) that item via PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields()
     * so the link is regenerated at the new amount instead of staying at the old selection's price.
     *
     * A line is dropped rather than repriced when this same request's to_charge already charges
     * that field's full new price itself (an upgrade — never-paid fields have old_paid = 0).
     *
     * For a Card charge only those dropped (superseded) lines are applied, never a reprice, and
     * without regenerating/emailing the link: RemainingPaymentService::finalizeItems() settles the
     * link's remaining items with the same PaymentIntent right after this, and that PaymentIntent
     * was already built without the superseded lines
     * (StripeService::stripeCreatePaymentIntentForAddonEditCharge() → PaymentLinkService::
     * pendingAddonLinkDropAmounts()). Leaving them on the link would settle the old selection
     * too — the field recorded as paid for both Medium and Large. Anything else would desync the
     * link from what Stripe actually charged, so it's left alone as before.
     *
     * @param int        $bookingId
     * @param array      $updatedItems 'updated' diff entries (each carrying db_id)
     * @param array|null $financial
     * @param bool       $recalculate  false when the caller re-sums/emails the link itself
     */
    private function syncPendingAddonLinkForEditedFields(int $bookingId, array $updatedItems, ?array $financial, bool $recalculate = true, ?float $feePct = null): void
    {
        $updatedIds = collect($updatedItems)->pluck('db_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (empty($updatedIds)) {
            return;
        }

        $financial = $financial ?? [];

        $supersededIds = collect($financial['to_charge'] ?? [])
            ->pluck('old_db_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->intersect($updatedIds)
            ->values()
            ->all();

        if (($financial['action'] ?? '') === 'charge' && strtolower($financial['payment_method'] ?? '') === 'card') {
            if (!empty($supersededIds)) {
                PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($bookingId, $supersededIds, $supersededIds, false, $feePct);

                // If that was the link's only remaining item, cancel the now-empty link rather than
                // letting finalizeItems() mark it Paid. Cancelled directly — NOT via
                // recalculateRemainingPaymentLink(), which would also attach any unlinked Open
                // custom charges to it (and email it) that this card payment never covered.
                $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
                $link    = $groupId ? TourBookingPaymentLink::where('order_id', $groupId)
                    ->where('section', 'remaining_payment')
                    ->where('status', 'Unpaid')
                    ->first() : null;
                if ($link && !TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->where('status', 'Unpaid')->exists()) {
                    $link->status     = 'Cancelled';
                    $link->updated_at = now();
                    $link->save();
                }
            }
            return;
        }

        PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($bookingId, $updatedIds, $supersededIds, $recalculate, $feePct);
    }

    /**
     * Write an audit-trail entry for a single-passenger addon edit that nets to exactly $0
     * (e.g. removing one priced field while adding a different one of equal value) — no Stripe
     * charge/refund happens, so processAddonFinancialTransactions() never runs, but the fields
     * DID change and that must still be visible in "History & Notes". Reuses the already-correct
     * to_charge/to_refund entries buildAddonPayload() computed on the frontend (real prices,
     * proper labels) rather than re-deriving anything server-side. Mirrors the equivalent fix in
     * savePaxAddons() (the bulk-pax flow), which had a partial version of this same gap.
     *
     * @param int $bookingId
     * @param array $financial
     * @return void
     */
    private function recordZeroNetAddonHistory(int $bookingId, array $financial): void
    {
        // Booking's own real payment method (blank for a genuinely never-paid Pay Later
        // booking) — NOT a hardcoded 'card' default, which showed "Card" for every net-$0
        // add-on edit on a Pay Later booking regardless of its actual (or non-existent) payment
        // method.
        $bookingPaymentMethod = $this->resolveBookingPaymentMethodLabel($bookingId);

        $removedHistory = [];
        $addedHistory   = [];

        foreach (($financial['to_refund'] ?? []) as $item) {
            $removedHistory[] = [
                'name'           => $item['label'] ?? 'Add-on',
                'price'          => (float) ($item['credit_amount'] ?? $item['refund_amount'] ?? 0),
                'fee'            => 0,
                'payment_method' => $bookingPaymentMethod,
                'status'         => "Don't Refund",
            ];
        }

        foreach (($financial['to_charge'] ?? []) as $item) {
            $addedHistory[] = [
                'name'           => $item['label'] ?? 'Add-on',
                'price'          => (float) ($item['charge_amount'] ?? 0),
                'fee'            => 0,
                'payment_method' => $bookingPaymentMethod,
                'status'         => 'Added',
            ];
        }

        if (empty($removedHistory) && empty($addedHistory)) {
            return;
        }

        $booking              = BookingService::booking($bookingId);
        $bookingAddonProxy    = (object) ['payment_method' => $removedHistory[0]['payment_method'] ?? $bookingPaymentMethod];
        $requestProxy         = (object) ['description' => null];
        $zeroTransactionValue = [
            'price'              => 0,
            'fee'                => 0,
            'transaction_id'     => null,
            'application_fee_id' => null,
        ];

        HistoryService::storeAddonsRefundInHistory(
            $booking, $bookingAddonProxy, $zeroTransactionValue,
            $removedHistory, 'removed', Constants::ADDONS_DONT_REFUND, null, $requestProxy,
            $addedHistory
        );
    }

    protected function processAddonFinancialTransactions(int $bookingId, array $financial, array $fieldToDb): ?object
    {
        $booking        = BookingService::booking($bookingId);
        $operationGroup = (string) Str::uuid(); // groups all records from this single edit operation
        $action         = $financial['action']          ?? 'none';
        $paymentMethod = $financial['payment_method']  ?? 'Pay Later';
        $intentId      = $financial['payment_intent_id'] ?? null;
        $refundType    = $financial['refund_type']     ?? 'ticket_refund';
        $refundNote    = $financial['refund_note']     ?? null;
        $methodLower   = strtolower($paymentMethod);

        // Invoiced/Billable bookings settle add-ons through their invoice, so the charge is
        // recorded immediately — status 'paid' plus the transactions-ledger row and Payment
        // history entry (matching InvoicedBookingService at booking creation) — instead of
        // being left 'open' like a true Pay Later charge, which records no payment at all.
        // Fees still apply to card/cash only ($applyFees below), so invoiced charges stay fee-free.
        $status     = in_array($methodLower, ['cash', 'card', 'invoiced', 'billable']) ? 'paid' : 'open';
        $isCashFees = cashFees();

        // For refund scenarios: pre-fetch the original transaction BEFORE computing feePct.
        // The refund financial payload does not always carry payment_method (or may carry the
        // booking-level method which could differ from what was used for the specific addon charge).
        // Using the original transaction's method guarantees the correct applyFees determination.
        $refOriginalTxn = null;
        if ($action === 'refund') {
            foreach ($financial['to_refund'] ?? [] as $refItem) {
                $refDbId = $refItem['db_id'] ?? null;
                if (!$refDbId) {
                    continue;
                }
                $txn = TourBookingAddonTransaction::where('tour_booking_addon_id', $refDbId)
                    ->where('status', 'paid')
                    ->orderBy('id', 'desc')
                    ->first();
                if ($txn) {
                    $refOriginalTxn = $txn;
                    break;
                }
            }
        }

        // For refunds use the original transaction's payment method; for charges use the new PI method.
        $effectiveMethod = $action === 'refund'
            ? strtolower($refOriginalTxn?->payment_method ?? $methodLower)
            : $methodLower;
        $applyFees = $effectiveMethod === 'card' || ($effectiveMethod === 'cash' && $isCashFees === '1');

        // nat_fees_percentage is the forward rate: fee = base × rate
        $bookingTxn = BookingTransact::where('tour_booking_id', $bookingId)
            ->where('deleted_by', 0)
            ->first();
        $feePct = $applyFees ? (float) ($bookingTxn->nat_fees_percentage ?? 0) : 0;

        // For pure card charges: retrieve the PaymentIntent (needed for fee and history records)
        $paymentIntent = null;
        $piId          = null;
        $appFeeId      = null;
        if ($action === 'charge' && $methodLower === 'card' && $intentId) {
            $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment(
                $intentId,
                ['expand' => ['charges.data.transfer']]
            );
            $piId     = $paymentIntent->id ?? $intentId;
            $appFeeId = $paymentIntent->charges->data[0]->application_fee ?? null;
        }

        // ----------------------------------------------------------------
        // Charges
        // When action='charge': fee charged on the net amount (PI was created for net_amount,
        //   not the gross sum of to_charge items). Per-item fees are 0 when offset items exist.
        // When action='refund': charge items are settled via the original PI (we only refunded
        //   the net); stored as paid with no additional fee.
        // ----------------------------------------------------------------

        // True when action='charge' but to_refund items offset part of the gross charge.
        // The PI was created for net_amount, not the sum of to_charge prices.
        $hasChargeOffset    = $action === 'charge' && !empty($financial['to_refund']);
        // True when action='refund' but to_charge items offset part of the gross refund.
        // The refund was reduced by the new charge amount — to_charge items need to appear in refund history.
        $hasRefundOffset    = $action === 'refund' && !empty($financial['to_charge']);

        $totalChargePrice   = 0;
        $totalChargeFee     = 0;
        $chargeHistoryItems = [];
        $refundHistoryItems = [];

        // ----------------------------------------------------------------
        // Refunds — computed BEFORE the charge loop below (moved up from its natural reading
        // position further down this method) specifically so $offsetCapacityForCharge exists in
        // time for the charge loop to draw from it. Per-item TourBookingAddonTransaction records
        // are created for addon state tracking. Stripe calls are grouped per original
        // PaymentIntent so we never refund more from a PI than was originally charged to it.
        //
        // Charge offset (to_charge items in a net-refund scenario) is subtracted from
        // the most-recently-added PI group first, then cascades to older ones if needed.
        // ----------------------------------------------------------------
        $refundGroups    = []; // [pi_id => ['total' => float, 'method' => string, 'app_fee_id' => string|null, 'txn' => object]]
        $refundRecords   = [];
        $refundHistoryItemsByPi = []; // [pi_id => [history item, ...]] — one transactions/history record per PI
        $lastOriginalTxn = null;
        // Declared here (rather than down by $leftoverBase/$leftoverFee below) so the two
        // fallback branches just below can fold genuinely-unattributable removals straight in —
        // see their comments for why "no ledger row to draw from" must not mean "drop from
        // history too". These contribute $0 to $leftoverBase/$leftoverFee (nothing was ever
        // actually charged for them), only to the display list.
        $leftoverHistoryItems = [];
        $hasUnpaidPartHistory = false; // a partly-paid downgrade's unpaid part (see below)

        foreach ($financial['to_refund'] ?? [] as $item) {
            $dbId         = $item['db_id'] ?? null;
            $displayPrice = (float) ($item['credit_amount'] ?? $item['refund_amount'] ?? 0);

            if (!$dbId) {
                // No DB row at all to attribute this removal to (e.g. a stale/missing db_id from
                // the frontend diff) — there's nothing to refund via Stripe, but the field still
                // changed. Surface it via the same leftover/no-Stripe-call bucket used below for
                // "no real Stripe refund happened", and also into the flat $refundHistoryItems
                // list so a net-CHARGE operation's "removed (offset)" side sees it too.
                $key = 'nodb_' . ($item['field_id'] ?? uniqid());
                $leftoverHistoryItems[$key] = [
                    'name'           => $item['label'] ?? 'Add-on',
                    'price'          => $displayPrice,
                    'fee'            => 0,
                    'payment_method' => $this->resolveBookingPaymentMethodLabel($bookingId),
                    'status'         => $refundType === 'dont_refund' ? "Don't Refund" : 'Refund',
                ];
                $refundHistoryItems[] = $leftoverHistoryItems[$key];
                continue;
            }

            // An addon field can have been charged across MULTIPLE PaymentIntents over its
            // lifetime (e.g. original addition + a later upgrade, each its own Stripe charge).
            // Walk every 'paid' transaction for this field — most recent first — and draw the
            // refund from each one's own PI in turn, instead of attributing the whole amount to
            // just the latest transaction, which can only ever return what THAT one PI covers
            // and silently truncates the refund when older money lives on a different PI.
            $paidTxns = TourBookingAddonTransaction::where('tour_booking_addon_id', $dbId)
                ->where('status', 'paid')
                ->orderBy('id', 'desc')
                ->get();

            if ($paidTxns->isEmpty()) {
                // Field exists but was never actually charged — a Pay Later add-on never
                // invoiced, or the $0 "no additional charge" placeholder row savePaxAddons()
                // writes for a reassignment target. Nothing to refund via Stripe, but the
                // removal still happened and must remain visible (same reasoning as the !$dbId
                // branch just above).
                $key = 'unpaid_' . $dbId;
                $leftoverHistoryItems[$key] = [
                    'name'           => $item['label'] ?? 'Add-on',
                    'price'          => $displayPrice,
                    'fee'            => 0,
                    'payment_method' => $this->resolveBookingPaymentMethodLabel($bookingId),
                    'status'         => $refundType === 'dont_refund' ? "Don't Refund" : 'Refund',
                ];
                $refundHistoryItems[] = $leftoverHistoryItems[$key];
                continue;
            }

            $lastOriginalTxn = $paidTxns->first();
            $remainingRefund = round((float) $item['refund_amount'], 2);
            $itemRefundTotal = $remainingRefund;

            // Partly paid downgrade: the credit is bigger than what was actually paid back (e.g.
            // Gluten-Free $18 = $16 paid + $2 still pending on a payment link → Vegetarian $16:
            // refund_amount $0, credit_amount $2). That unpaid $2 was never collected, so nothing
            // goes back and no ledger row is written — but it's recorded in history as its own
            // "Don't Refund" line, same as an unpaid add-on change in Change People.
            $unpaidCredit = round((float) ($item['credit_amount'] ?? 0) - $remainingRefund, 2);
            if (($item['reason'] ?? '') === 'downgraded' && $unpaidCredit > 0.001) {
                $leftoverHistoryItems['unpaid_part_' . $dbId] = [
                    'name'           => $item['label'] ?? 'Add-on',
                    'price'          => $unpaidCredit,
                    'fee'            => 0,
                    'payment_method' => $lastOriginalTxn->payment_method ?? $this->resolveBookingPaymentMethodLabel($bookingId),
                    'status'         => "Don't Refund",
                ];
                $hasUnpaidPartHistory = true;
                if ($remainingRefund <= 0.001) {
                    // Nothing actually paid to refund for this item. Surface the unpaid part on the
                    // flat list too, like the other no-ledger-row branches above, so a net-CHARGE
                    // operation's "removed (offset)" side still sees it.
                    $refundHistoryItems[] = $leftoverHistoryItems['unpaid_part_' . $dbId];
                    continue;
                }
            }

            foreach ($paidTxns as $originalTxn) {
                if ($remainingRefund <= 0.001) {
                    break;
                }

                // Split the refund amount across field_price / option_price in the same ratio
                // as this specific transaction. This ensures option-priced addons (e.g. dropdown
                // selections) record refunds in option_price, not field_price.
                $origFieldPAbs  = abs(floatval($originalTxn->field_price  ?? 0));
                $origOptionPAbs = abs(floatval($originalTxn->option_price ?? 0));
                $origPTotal     = $origFieldPAbs + $origOptionPAbs;

                if ($origPTotal <= 0) {
                    continue; // nothing priced on this transaction to draw a refund from
                }

                $originalMethod   = $originalTxn->payment_method ?? 'Cash';
                $originalIntentId = $originalTxn->transaction_id ?? 'no_pi';
                $origAppFeeId     = $originalTxn->application_fee_id ?? null;

                // Only draw as much price as THIS transaction actually covers — the remainder
                // (if any) cascades to the next older transaction on the next loop iteration.
                $refundBaseAmount = round(min($remainingRefund, $origPTotal), 2);
                $remainingRefund  = round($remainingRefund - $refundBaseAmount, 2);

                // Accumulate per PI so we make one Stripe call per intent
                if (!isset($refundGroups[$originalIntentId])) {
                    $refundGroups[$originalIntentId] = [
                        'total'      => 0.0,
                        'method'     => $originalMethod,
                        'app_fee_id' => $origAppFeeId,
                        'txn'        => $originalTxn,
                    ];
                }
                $refundGroups[$originalIntentId]['total'] += $refundBaseAmount;

                // Original fee totals for this transaction — needed both for the proportional
                // fee split below AND (the 'dont_refund' branch just below) to work out how much
                // fee to settle in the ledger without any of it actually reaching the customer.
                $origFieldFAbs  = abs(floatval($originalTxn->field_fee  ?? 0));
                $origOptionFAbs = abs(floatval($originalTxn->option_fee ?? 0));
                $origFTotal     = $origFieldFAbs + $origOptionFAbs;

                // Per-transaction DB record — individual amount for addon state tracking.
                // field_fee convention in refund records (POSITIVE — status='refunded'/
                // operation='refund' already say this is a refund, the magnitude doesn't need to
                // repeat that with a sign):
                //   full_refund         → the fee amount that was actually returned to the
                //                         customer (real money — also drives the Stripe amount
                //                         via $groupFee below).
                //   ticket_refund /
                //   dont_refund         → NOT the amount returned to the customer (that stays $0
                //                         for both — $groupFee below is unaffected by this and
                //                         still computes $0 fee for either type, so nothing extra
                //                         is ever sent to Stripe or shown as refunded). This is
                //                         instead how much of the fee to mark as PERMANENTLY
                //                         SETTLED in the ledger, proportional to how much of this
                //                         transaction's price is being removed here — full removal
                //                         (refund_amount == the whole remaining price) settles
                //                         100% of the fee; a partial downgrade (refund_amount is
                //                         only part of it) settles only that same proportion,
                //                         leaving the rest still open and tracking the item's own
                //                         now-lower remaining price. Without this, a downgrade
                //                         left the ENTIRE original fee sitting unmatched forever
                //                         against a price that no longer exists — e.g. a $25→$20
                //                         Ticket-Refund downgrade (fee unaffected, correctly kept
                //                         in full at $2.46 at the time) still showed the full
                //                         $2.46 as "outstanding" against the now-$20 item, so a
                //                         LATER cancel-booking 100% refund paid out the whole
                //                         stale $2.46 instead of the ~$1.97 that actually still
                //                         applies to the $20 (the other $0.49 was already
                //                         permanently forfeited by the $5 Ticket-Refund downgrade,
                //                         same as the price side). "Don't Refund" is the same
                //                         principle taken to its full-removal extreme — see its
                //                         own fix above; unlike Ticket Refund it's never partial,
                //                         so $refundBaseAmount always equals $origPTotal for it.
                //                         Matches the ledger-settlement (not customer-facing)
                //                         convention ChangePeopleAddonService::
                //                         settleAddonTransactions() already uses for 'dont_refund'.
                // Consumers net charge vs. refund by summing each side separately (via ABS(), so
                // this still works if REAL historical rows carry the old negative convention) and
                // subtracting — never by summing both sides together in one signed SUM().
                if ($refundType === 'full_refund' && $applyFees) {
                    $refundedFeeForRecord = round($refundBaseAmount * $feePct / 100, 2);
                } elseif (in_array($refundType, ['dont_refund', 'ticket_refund'], true) && $origPTotal > 0) {
                    $refundedFeeForRecord = round($origFTotal * ($refundBaseAmount / $origPTotal), 2);
                } else {
                    $refundedFeeForRecord = 0;
                }

                if ($origOptionPAbs > 0) {
                    $fieldRatio    = $origFieldPAbs / $origPTotal;
                    $refFieldP     = round($refundBaseAmount * $fieldRatio, 2);
                    $refOptionP    = round($refundBaseAmount - $refFieldP, 2);
                } else {
                    $refFieldP  = $refundBaseAmount;
                    $refOptionP = 0.0;
                }

                // Apply the same proportional split to the fee record.
                $refFieldFee    = $refundedFeeForRecord;
                $refOptionFee   = 0.0;

                if ($refundedFeeForRecord > 0 && $origFTotal > 0 && $origOptionFAbs > 0) {
                    $feeFieldRatio = $origFieldFAbs / $origFTotal;
                    $refFieldFee   = round(abs($refundedFeeForRecord) * $feeFieldRatio, 2);
                    $refOptionFee  = round(abs($refundedFeeForRecord) - abs($refFieldFee), 2);
                }

                $refundRecords[] = [
                    'tour_booking_id'        => $bookingId,
                    'tour_booking_addon_id'  => $dbId,
                    'operation_group_id'     => $operationGroup,
                    'payment_method'         => strtolower($originalMethod),
                    'status'                 => 'refunded',
                    'operation'              => 'refund',
                    'transaction_id'         => $originalTxn->transaction_id,
                    'application_fee_id'     => $origAppFeeId,
                    'field_price'            => abs($refFieldP),
                    'field_fee'              => $refFieldFee,
                    'option_price'           => abs($refOptionP),
                    'option_fee'             => $refOptionFee,
                    'notes'                  => $refundNote,
                    'operation_at'           => now(),
                    'operation_by'           => $booking->created_by,
                    'operation_by_user_type' => $booking->created_by_user_type,
                ];

                // Same entry, scoped to the specific PI this portion was drawn from — used
                // below to write one transactions/history record per PaymentIntent actually
                // refunded, instead of one blended record referencing only the latest PI.
                // Keyed by addon field (not appended per-transaction) so a field whose paid
                // history happens to land in the SAME record (e.g. two Cash transactions,
                // which never split across PIs) shows as one combined line — "Sweater $15.00"
                // — instead of one line per underlying transaction ("Sweater $5" / "$10").
                if (!isset($refundHistoryItemsByPi[$originalIntentId][$dbId])) {
                    $refundHistoryItemsByPi[$originalIntentId][$dbId] = [
                        'name'           => $item['label'] ?? 'Add-on',
                        'price'          => 0.0,
                        'fee'            => 0,
                        'payment_method' => $originalMethod,
                        'status'         => $refundType === 'dont_refund' ? "Don't Refund" : 'Refund',
                    ];
                }
                $refundHistoryItemsByPi[$originalIntentId][$dbId]['price'] += $refundBaseAmount;
            }

            $refundHistoryItems[] = [
                'name'           => $item['label'] ?? 'Add-on',
                'price'          => $itemRefundTotal,
                'fee'            => 0,
                'payment_method' => $lastOriginalTxn->payment_method ?? 'Cash',
                'status'         => $refundType === 'dont_refund' ? "Don't Refund" : 'Refund',
            ];
        }

        // Subtract the charge offset from PI groups, starting from the most recent
        // (last added) and cascading to older ones if the offset exceeds one group's total.
        // Track exactly which PI(s) it actually reduced in $offsetReducedPiKeys — the
        // offsetting "added" items must only be displayed on THOSE groups' own history
        // row(s) (see $offsetItemsForHistory() below), not on whichever row merely happens
        // to be written first, which can be a completely different (and untouched) PI group
        // whenever a refund spans more than one PaymentIntent.
        $offsetReducedPiKeys = [];
        $chargeOffset = 0.0;
        foreach ($financial['to_charge'] ?? [] as $chargeItem) {
            $chargeOffset += (float) ($chargeItem['charge_amount'] ?? 0);
        }
        // Capacity freed up per PI by the reduction below, in the SAME order the cascade drains
        // it (most-recently-added PI first) — the removed item(s) on that PI genuinely keep this
        // many dollars UN-refunded (the brand-new PaymentIntent below is only ever confirmed for
        // the net amount — see $netChargeBase), so it's real, spendable capacity the to_charge
        // loop below draws from BEFORE the brand-new PI, instead of tying every new item's FULL
        // price to a PI that never actually held it. Without this, a later 100% cancel refund
        // asks Stripe to refund more from that brand-new PI than it was ever charged and gets
        // rejected ("Refund amount ($X) is greater than charge amount ($Y)").
        //
        // Fee is only included when $refundType is 'full_refund' — for 'ticket_refund'/
        // 'dont_refund' the fee was never going to be refunded regardless of any offset (it's
        // permanently kept against the REMOVED item itself, tracked by its own $refundRecords row
        // above — see that row's own comment), so redirecting it here would double-count the
        // SAME dollar against two different fields.
        $offsetCapacityForCharge = [];
        foreach (array_reverse(array_keys($refundGroups)) as $piKey) {
            if ($chargeOffset <= 0) {
                break;
            }
            $reduction = min($chargeOffset, $refundGroups[$piKey]['total']);
            if ($reduction > 0) {
                $offsetReducedPiKeys[$piKey] = true;
                $offsetCapacityForCharge[] = [
                    'transaction_id'     => $piKey,
                    'application_fee_id' => $refundGroups[$piKey]['app_fee_id'],
                    'payment_method'     => $refundGroups[$piKey]['method'],
                    'remaining_price'    => $reduction,
                ];
            }
            $refundGroups[$piKey]['total'] -= $reduction;
            $chargeOffset                  -= $reduction;
        }

        foreach ($financial['to_charge'] ?? [] as $item) {
            // Prefer composite key (field_id_paxIndex) set by upsertBookingAddonFields,
            // fall back to plain field_id for non-pax or legacy payloads.
            $paxIndexKey  = isset($item['pax_index']) ? ($item['field_id'] . '_' . $item['pax_index']) : null;
            $dbId = $item['old_db_id']
                ?? ($paxIndexKey ? ($fieldToDb[$paxIndexKey] ?? null) : null)
                ?? ($fieldToDb[$item['field_id']] ?? null);

            // Pay Later upgrade of a still-unpaid selection (e.g. Classic Turkey Wrap $16 →
            // Gluten-Free Wrap $18): the new 'open' charge row below carries the field's FULL new
            // price (nothing was ever collected for the old one, so charge_amount = new price), so
            // the old 'open' charge row is superseded — leaving it made the field count as both
            // $16 AND $18. Only never-collected 'open' rows are removed; paid rows are untouched.
            if ($action === 'charge' && $status === 'open' && !empty($item['old_db_id']) && $dbId
                && isset($item['new_price']) && abs((float) $item['charge_amount'] - (float) $item['new_price']) < 0.01) {
                TourBookingAddonTransaction::where('tour_booking_addon_id', $dbId)
                    ->where('operation', 'charge')
                    ->where('status', 'open')
                    // Hard delete: raw DB::table() readers of this table (settlement in
                    // AddonBookingService, reports) don't filter deleted_at, so a soft-deleted
                    // open row could still be picked up and settled as paid.
                    ->forceDelete();
            }

            $chargeAmount = (float) $item['charge_amount'];

            // Use the JS-supplied field/option price breakdown when available.
            // For 'upgraded' items charge_amount is a delta, so scale each component
            // proportionally to preserve the correct split.
            $rawFieldPrice  = isset($item['field_price'])  ? (float) $item['field_price']  : $chargeAmount;
            $rawOptionPrice = isset($item['option_price']) ? (float) $item['option_price'] : 0.0;
            $rawTotal       = $rawFieldPrice + $rawOptionPrice;
            if ($rawTotal > 0 && abs($rawTotal - $chargeAmount) > 0.001) {
                // delta charge — scale the breakdown proportionally
                $scale       = $chargeAmount / $rawTotal;
                $fieldPrice  = round($rawFieldPrice  * $scale, 2);
                $optionPrice = round(($chargeAmount - $fieldPrice), 2); // absorb rounding
            } else {
                $fieldPrice  = $rawFieldPrice;
                $optionPrice = $rawOptionPrice;
            }

            // Per-item fee only for a pure standalone charge/refund with no offset at all. Both
            // offset shapes ($hasChargeOffset and $hasRefundOffset) instead compute fee per-DRAW
            // below (see $offsetCapacityForCharge), because it must follow WHICH PaymentIntent
            // each portion of this item's price is actually funded from — a single item can be
            // split across the untouched old PI(s) plus (for $hasChargeOffset) the brand-new one,
            // and each portion's fee is genuinely different depending on where it lands.
            $applyItemFee = $applyFees && $action === 'charge' && !$hasChargeOffset && !$hasRefundOffset;
            $fieldFee  = $applyItemFee ? round($fieldPrice  * $feePct / 100, 2) : 0;
            $optionFee = $applyItemFee ? round($optionPrice * $feePct / 100, 2) : 0;
            $chargeFee = $fieldFee + $optionFee;
            // Overwritten below (to the real, per-draw total) whenever an offset actually splits
            // this item — stays equal to $chargeFee for the plain, no-offset case above.
            $displayFee = $chargeFee;

            // $dbId can fail to resolve (e.g. a pax-scoped 'upgraded' item whose old_db_id went
            // stale, or a field that only entered $fieldToDb under a differently-shaped key) —
            // when that happens we can't write a per-field ledger row, but the item still really
            // changed and was still charged as part of this operation's PaymentIntent (see
            // $totalChargePrice/$netChargeBase below, which are NOT gated on $dbId either), so it
            // must not silently vanish from the audit trail too. Log it and keep going instead of
            // the old `continue`, which dropped both the ledger row AND the history line together.
            if ($dbId) {
                // A net-CHARGE settlement offsetting a removal ($hasChargeOffset) only ever
                // confirms a brand-new PaymentIntent for the NET amount still owed (see
                // $netChargeBase above); a net-REFUND settlement offsetting an addition
                // ($hasRefundOffset) only ever refunds the removed item(s)' NET amount (see
                // $groupFee below). Either way, the removed item's own price+fee is never
                // actually refunded via Stripe for whatever portion the offset absorbed (see
                // $offsetCapacityForCharge above) — it simply stays wherever it was already,
                // genuinely charged, untouched. Draw this item's price from that capacity FIRST —
                // splitting it across multiple ledger rows/PIs when needed, the same "draw down
                // the oldest capacity first, cascade the rest" shape the refund side already uses
                // — falling back to the brand-new PI ($hasChargeOffset) or the original settled
                // transaction ($hasRefundOffset) only for whatever isn't covered. Without this,
                // every new item's FULL price was tied to a single PI regardless of what it was
                // ever actually confirmed for, and — for $hasRefundOffset specifically — the fee
                // was fabricated flat off this item's own price instead of following whatever the
                // offset genuinely freed up (nothing at all for 'ticket_refund'/'dont_refund',
                // since their fee was never going to be refunded either way).
                $draws = [];
                $remaining = $chargeAmount;
                if ($hasChargeOffset || $hasRefundOffset) {
                    while ($remaining > 0.001 && !empty($offsetCapacityForCharge)) {
                        if ($offsetCapacityForCharge[0]['remaining_price'] <= 0.001) {
                            array_shift($offsetCapacityForCharge);
                            continue;
                        }
                        $draw = round(min($remaining, $offsetCapacityForCharge[0]['remaining_price']), 2);
                        $draws[] = [
                            'transaction_id'     => $offsetCapacityForCharge[0]['transaction_id'],
                            'application_fee_id' => $offsetCapacityForCharge[0]['application_fee_id'],
                            'payment_method'     => $offsetCapacityForCharge[0]['payment_method'] ?: $paymentMethod,
                            'price'              => $draw,
                            // The fee this drawn price would have carried had the removed item
                            // been refunded in full — see $offsetCapacityForCharge's own comment
                            // above for why this is $0 for 'ticket_refund'/'dont_refund'.
                            'fee'                => $refundType === 'full_refund' ? round($draw * $feePct / 100, 2) : 0,
                        ];
                        $offsetCapacityForCharge[0]['remaining_price'] = round($offsetCapacityForCharge[0]['remaining_price'] - $draw, 2);
                        $remaining = round($remaining - $draw, 2);
                    }
                }
                if (empty($draws)) {
                    // No offset (the common case) or nothing left in the pool — single row,
                    // exactly the original computation ($fieldFee/$optionFee already correct).
                    TourBookingAddonTransaction::create([
                        'tour_booking_id'        => $bookingId,
                        'tour_booking_addon_id'  => $dbId,
                        'operation_group_id'     => $operationGroup,
                        'payment_method'         => strtolower($action === 'refund'
                            ? ($refOriginalTxn?->payment_method ?? $paymentMethod)
                            : $paymentMethod),
                        'status'                 => $action === 'refund' ? 'paid' : $status,
                        'operation'              => 'charge',
                        'transaction_id'         => $action === 'charge'
                            ? ($piId ?? $intentId)
                            : $refOriginalTxn?->transaction_id,
                        'application_fee_id'     => $action === 'charge'
                            ? $appFeeId
                            : $refOriginalTxn?->application_fee_id,
                        'field_additional_fee'   => $fieldFee > 0 || (!empty($item['field_additional_fee']) && $fieldPrice > 0),
                        'option_additional_fee'  => $optionFee > 0 || (!empty($item['option_additional_fee']) && $optionPrice > 0),
                        'field_price'            => $fieldPrice,
                        'field_fee'              => $fieldFee,
                        'option_price'           => $optionPrice,
                        'option_fee'             => $optionFee,
                        'operation_at'           => now(),
                        'operation_by'           => $booking->created_by,
                        'operation_by_user_type' => $booking->created_by_user_type,
                    ]);
                } else {
                    // A real split happened — $fieldFee/$optionFee are always 0 whenever it can
                    // (see $applyItemFee above), so the only fee to record on each row is what
                    // THAT specific draw carries, split across field/option by the same ratio as
                    // its price.
                    if ($remaining > 0.001) {
                        // Nothing left in the pool to cover the rest of this item — should be
                        // rare/never for $hasRefundOffset (a well-formed net-refund payload's
                        // removed item(s) always have at least as much price capacity as the
                        // offsetting charge total), but handled safely either way: fall back to
                        // the brand-new PI for $hasChargeOffset (matching the original single-row
                        // convention), or to the same original settled transaction the pre-split
                        // convention already used for $hasRefundOffset — still correctly priced
                        // with its own feePct-based fee rather than silently dropped to 0.
                        $draws[] = $hasChargeOffset ? [
                            'transaction_id'     => $piId ?? $intentId,
                            'application_fee_id' => $appFeeId,
                            'payment_method'     => strtolower($paymentMethod),
                            'price'              => $remaining,
                            'fee'                => 0,
                        ] : [
                            'transaction_id'     => $refOriginalTxn?->transaction_id,
                            'application_fee_id' => $refOriginalTxn?->application_fee_id,
                            'payment_method'     => strtolower($refOriginalTxn?->payment_method ?? $paymentMethod),
                            'price'              => $remaining,
                            'fee'                => $refundType === 'full_refund' ? round($remaining * $feePct / 100, 2) : 0,
                        ];
                    }
                    $displayFee = round(array_sum(array_column($draws, 'fee')), 2);
                    $allocFieldPrice = 0.0;
                    $allocOptionPrice = 0.0;
                    $lastIdx = count($draws) - 1;
                    foreach ($draws as $idx => $draw) {
                        if ($idx === $lastIdx) {
                            $rowFieldPrice  = round($fieldPrice  - $allocFieldPrice, 2);
                            $rowOptionPrice = round($optionPrice - $allocOptionPrice, 2);
                        } else {
                            $ratio = $chargeAmount > 0 ? $draw['price'] / $chargeAmount : 0;
                            $rowFieldPrice  = round($fieldPrice  * $ratio, 2);
                            $rowOptionPrice = round($optionPrice * $ratio, 2);
                            $allocFieldPrice  += $rowFieldPrice;
                            $allocOptionPrice += $rowOptionPrice;
                        }
                        $rowTotal = $rowFieldPrice + $rowOptionPrice;
                        if ($rowTotal > 0 && $optionPrice > 0) {
                            $rowFieldFee  = round($draw['fee'] * ($rowFieldPrice / $rowTotal), 2);
                            $rowOptionFee = round($draw['fee'] - $rowFieldFee, 2);
                        } else {
                            $rowFieldFee  = $draw['fee'];
                            $rowOptionFee = 0.0;
                        }

                        TourBookingAddonTransaction::create([
                            'tour_booking_id'        => $bookingId,
                            'tour_booking_addon_id'  => $dbId,
                            'operation_group_id'     => $operationGroup,
                            'payment_method'         => $draw['payment_method'],
                            'status'                 => $action === 'refund' ? 'paid' : $status,
                            'operation'              => 'charge',
                            'transaction_id'         => $draw['transaction_id'],
                            'application_fee_id'     => $draw['application_fee_id'],
                            'field_additional_fee'   => $rowFieldFee > 0 || (!empty($item['field_additional_fee']) && $rowFieldPrice > 0),
                            'option_additional_fee'  => $rowOptionFee > 0 || (!empty($item['option_additional_fee']) && $rowOptionPrice > 0),
                            'field_price'            => $rowFieldPrice,
                            'field_fee'              => $rowFieldFee,
                            'option_price'           => $rowOptionPrice,
                            'option_fee'             => $rowOptionFee,
                            'operation_at'           => now(),
                            'operation_by'           => $booking->created_by,
                            'operation_by_user_type' => $booking->created_by_user_type,
                        ]);
                    }
                }
            } else {
                Log::warning('processAddonFinancialTransactions: unresolved tour_booking_addon_id for to_charge item — ledger row skipped, history preserved', [
                    'booking_id' => $bookingId,
                    'field_id'   => $item['field_id'] ?? null,
                    'pax_index'  => $item['pax_index'] ?? null,
                    'reason'     => $item['reason'] ?? null,
                ]);
            }

            if ($action === 'charge') {
                $totalChargePrice += $chargeAmount;
                $totalChargeFee   += $displayFee;
            }
            $chargeHistoryItems[] = [
                'name'           => $item['label'] ?? 'Add-on',
                'price'          => $chargeAmount,
                'fee'            => $displayFee,
                // Pay Later/open: nothing was actually collected via this method yet, so don't
                // record it as if it was — blank it, same convention used for the equivalent
                // Pay Later add-on removal ($0/"Don't Refund" leftover history entries above).
                'payment_method' => $status === 'open' ? '' : strtolower($paymentMethod),
                'status'         => $status,
            ];
        }

        // When offset items exist, the PI was created for net_amount — use that for the
        // transaction record so it matches the actual Stripe charge.
        $netChargeBase = $hasChargeOffset
            ? (float) ($financial['net_amount'] ?? $totalChargePrice)
            : $totalChargePrice;
        $netChargeFee = $hasChargeOffset && $applyFees
            ? round($netChargeBase * $feePct / 100, 2)
            : $totalChargeFee;

        // $refundGroups/$refundRecords/$refundHistoryItemsByPi/$lastOriginalTxn/
        // $leftoverHistoryItems/$refundHistoryItems and $offsetReducedPiKeys were already fully
        // computed ABOVE, before the charge loop — moved there so $offsetCapacityForCharge exists
        // in time for that loop to draw from it. See the "Refunds" block above.

        // Write charge transaction + history AFTER the refund loop so $refundHistoryItems
        // is fully populated for the hasChargeOffset net-breakdown case.
        if ($action === 'charge' && !empty($chargeHistoryItems)) {
            if ($status !== 'open') {
                // $paymentIntent is only ever populated for a genuine card charge (see the
                // retrieval above, gated on methodLower === 'card') — Cash/Invoiced/Billable never
                // touch Stripe, so DESTINATION (the column's own default) is the correct, inert
                // value for them; a real card charge's actual settlement mode is read directly
                // off the already-retrieved object.
                $chargeMode = $paymentIntent
                    ? PaymentModeInference::fromStripeObject($paymentIntent)
                    : PaymentModeResolver::DESTINATION;
                $txId = TransactionService::storeAddonsPaymentInTransaction(
                    $booking, $methodLower, $netChargeBase, $netChargeFee, $paymentIntent, mode: $chargeMode
                );
                HistoryService::storeAddonsPaymentInHistory(
                    $booking, $chargeHistoryItems, 'added', $paymentIntent,
                    $txId, $status, $netChargeBase, $netChargeFee,
                    $hasChargeOffset ? $refundHistoryItems : []
                );
            } else {
                // True Pay Later: nothing was actually charged, so don't fabricate a 'Completed'
                // transactions-ledger row or a non-zero Payment Total for it —
                // AddonBookingService::createAddonTransactionsFromFields() settles this add-on
                // for real (its own 'paid' history entry) once an actual "Make Payment" happens
                // later. Still record the addition NOW — $0 collected — so it isn't silently
                // invisible in History & Notes in the meantime, same "leave it open but visible"
                // convention already used for the equivalent Pay Later add-on removal case.
                HistoryService::storeAddonsPaymentInHistory(
                    $booking, $chargeHistoryItems, 'added', null,
                    null, $status, 0, 0,
                    $hasChargeOffset ? $refundHistoryItems : []
                );
            }

        }

        // $offsetReducedPiKeys and $refundGroups[*]['total'] were already reduced by the offset
        // cascade ABOVE, before the charge loop.

        // Execute one Stripe refund per PI group (card only, captured PIs), writing a
        // dedicated transactions + tour_booking_history record for each PaymentIntent
        // actually refunded. An addon field can have been charged across several PIs
        // (e.g. original addition + a later upgrade) — each is a separate real-world
        // payment and must show up as its own record, not blended into one summary.
        $leftoverBase = 0.0; // groups with no real Stripe refund: no_pi, non-card, open, dont_refund
        $leftoverFee  = 0.0;
        // $leftoverHistoryItems is declared earlier (above the to_refund loop) so the !$dbId/
        // no-paid-txn fallback branches there can fold straight into it.
        // Every PI group actually folded into $leftoverHistoryItems below (populated as the
        // main loop runs) — lets the leftover summary row below be checked against
        // $offsetReducedPiKeys just like any per-PI row.
        $leftoverPiKeys = [];

        // A refund operation can write MULTIPLE history rows below (one per PI group, plus a
        // leftover summary) — the offsetting $chargeHistoryItems (added items) must only
        // explain the row(s) for the PI group(s) $offsetReducedPiKeys says the offset actually
        // reduced, so callers pass the piKey(s) that specific row covers. $isFinal is a
        // fallback for the one case with no per-PI concept to check at all (every removed item
        // was un-attributable to any PaymentIntent — see the !$dbId/no-paid-txn branches above
        // — so $refundGroups, and therefore $offsetReducedPiKeys, stays entirely empty even
        // though a real offset exists) — the leftover call is the only row written at all in
        // that case, so it still gets the offset explained on it, matching prior behavior.
        $offsetAttached = false;
        $offsetItemsForHistory = function (array $piKeys, bool $isFinal = false) use (&$offsetAttached, $hasRefundOffset, $chargeHistoryItems, $offsetReducedPiKeys) {
            if (!$hasRefundOffset || $offsetAttached || empty($chargeHistoryItems)) {
                return [];
            }
            $touchedByOffset = false;
            foreach ($piKeys as $piKey) {
                if (!empty($offsetReducedPiKeys[$piKey])) {
                    $touchedByOffset = true;
                    break;
                }
            }
            if (!$touchedByOffset && !$isFinal) {
                return [];
            }
            $offsetAttached = true;
            return $chargeHistoryItems;
        };

        foreach ($refundGroups as $piKey => $group) {
            $groupTotal = $group['total']; // pre-fee, pre-cap intended refund principal for this PI
            if ($groupTotal <= 0) {
                // Fully absorbed by the charge offset above (nothing left to actually refund
                // through Stripe for this PI) — but the removed item(s) still really changed, so
                // fold them into the leftover bucket at their ORIGINAL (pre-offset) amount from
                // $refundHistoryItemsByPi, same shape as the "no real Stripe refund" case below,
                // rather than silently dropping them. The net math still comes out right: this
                // group contributes $0 to $leftoverBase (nothing was actually refunded), while
                // the displayed "Removed" pill keeps its true gross amount and the offsetting
                // "Added" pill (via $offsetItemsForHistory() below, now that $piKey is recorded
                // in $leftoverPiKeys) accounts for the difference.
                $leftoverPiKeys[$piKey] = true;
                foreach ($refundHistoryItemsByPi[$piKey] ?? [] as $dbId => $histItem) {
                    $key = 'offset_' . $dbId;
                    if (!isset($leftoverHistoryItems[$key])) {
                        $leftoverHistoryItems[$key] = $histItem;
                    } else {
                        $leftoverHistoryItems[$key]['price'] += $histItem['price'];
                    }
                }
                continue;
            }

            $groupFee = $refundType === 'full_refund' ? round($groupTotal * $feePct / 100, 2) : 0;

            $isRefundablePi = $refundType !== 'dont_refund'
                && $piKey !== 'no_pi'
                && strtolower($group['method'] ?? '') === 'card'
                && ($group['txn']?->status ?? '') !== 'open';

            if ($isRefundablePi) {
                // Fee must be added BEFORE capping against what's available on the PI —
                // otherwise capping the pre-fee amount and then adding the fee on top can
                // push the fee-inclusive total requested from Stripe back over the very
                // ceiling it was just capped against (e.g. cap to $5.49, then +$0.54 fee
                // = $6.03 requested against a PI that was only ever charged $5.49).
                $stripeAmount = $groupTotal + $groupFee;

                // Cap the refund at what was actually charged on this PI minus what has
                // already been refunded. This guards against cases where a net-charge edit
                // stored a gross item amount against the PI (e.g. Guided Language $5 charged
                // as part of a $2.20 net PI) — attempting to refund the full $5 later would
                // be rejected by Stripe.
                $piCharged   = (float) DB::table('transactions')
                    ->where('transaction_number', $piKey)
                    ->where('status', 'Completed')
                    ->sum('amount');
                $piRefunded  = (float) DB::table('transactions')
                    ->where('transaction_number', $piKey)
                    ->where('status', 'Refund')
                    ->sum('amount');
                $piAvailable = round(max(0.0, $piCharged - $piRefunded), 2);

                if ($piAvailable > 0) {
                    $stripeAmount = min($stripeAmount, $piAvailable);

                    $refundParams = [
                        'payment_intent'   => $piKey,
                        'amount'           => intval(round($stripeAmount * 100)),
                        'reverse_transfer' => true,
                    ];
                    if ($refundType === 'full_refund') {
                        $refundParams['refund_application_fee'] = true;
                    }
                    // $group['txn'] is a TourBookingAddonTransaction row (its own 'transaction_id'
                    // column stores the Stripe PI/transaction_number as a string, not an FK into the
                    // main transactions table) — payment_mode lives only on the real Transaction row,
                    // looked up the same way $piCharged/$piRefunded above already key off $piKey.
                    $piPaymentMode = DB::table('transactions')->where('transaction_number', $piKey)->value('payment_mode');
                    app(PaymentGatewayInterface::class)->refundPayment($refundParams, $piPaymentMode);

                    $piTransactionValue = [
                        'price'              => $groupTotal,
                        'fee'                => $groupFee,
                        'transaction_id'     => $piKey,
                        'application_fee_id' => $group['app_fee_id'],
                    ];
                    $piBookingAddonProxy  = (object) ['payment_method' => $group['method']];
                    $piRefundRequestProxy = (object) ['description' => $refundNote];

                    $piTxId = TransactionService::storeAddonsRefundInTransaction(
                        $booking, $piBookingAddonProxy, $piTransactionValue, mode: $piPaymentMode
                    );
                    HistoryService::storeAddonsRefundInHistory(
                        $booking, $piBookingAddonProxy, $piTransactionValue,
                        array_values($refundHistoryItemsByPi[$piKey] ?? []), 'removed', $refundType, $piTxId,
                        $piRefundRequestProxy,
                        $offsetItemsForHistory([$piKey])
                    );

                    continue;
                }
            }

            // No real Stripe refund happened for this group (dont_refund, no_pi, non-card,
            // open status, or nothing left available on the PI) — fold it into one combined
            // summary record below, same as the old single-record behavior. Merge by addon
            // field (not appended per-group) so a field split across e.g. two Cash
            // transactions still shows as one combined line in that single record.
            $leftoverPiKeys[$piKey] = true;
            $leftoverBase += $groupTotal;
            $leftoverFee  += $groupFee;
            foreach ($refundHistoryItemsByPi[$piKey] ?? [] as $dbId => $histItem) {
                if (!isset($leftoverHistoryItems[$dbId])) {
                    $leftoverHistoryItems[$dbId] = $histItem;
                    $leftoverHistoryItems[$dbId]['price'] = 0.0;
                }
                $leftoverHistoryItems[$dbId]['price'] += $histItem['price'];
            }
        }

        // Create per-item refund records AFTER Stripe confirms
        foreach ($refundRecords as $record) {
            TourBookingAddonTransaction::create($record);
        }

        // Any groups that didn't get a dedicated per-PI record above (dont_refund, no_pi,
        // non-card, open, or nothing available on the PI) still get ONE combined summary
        // record so the removal remains visible in history even though no Stripe refund
        // actually moved money for that portion. Also covers three cases with NO original
        // transaction at all ($lastOriginalTxn stays null): a removed item with a missing
        // db_id, one that was never actually charged (Pay Later / $0 placeholder row), or a
        // group fully absorbed by the charge offset — all three still populate
        // $leftoverHistoryItems (see the fallback branches above) and must not be silently
        // dropped just because there's nothing left to compute $leftoverBase from.
        $shouldWriteLeftoverHistory = !empty($leftoverHistoryItems)
            && ($leftoverBase > 0 || $refundType === 'dont_refund' || $lastOriginalTxn === null || $hasRefundOffset || $hasUnpaidPartHistory);

        if ($shouldWriteLeftoverHistory) {
            $transactionValue = [
                'price'              => $refundType === 'dont_refund' ? 0.0 : $leftoverBase,
                'fee'                => $refundType === 'dont_refund' ? 0.0 : $leftoverFee,
                'transaction_id'     => $lastOriginalTxn->transaction_id ?? null,
                'application_fee_id' => $lastOriginalTxn->application_fee_id ?? null,
            ];
            $bookingAddonProxy  = (object) ['payment_method' => $lastOriginalTxn->payment_method ?? $this->resolveBookingPaymentMethodLabel($bookingId)];
            $refundRequestProxy = (object) ['description'    => $refundNote];

            // Only touch the real transactions ledger when there's an actual original
            // transaction to reference AND a nonzero amount to record — a $0 leftover (no
            // $lastOriginalTxn, or a group fully absorbed by the offset) has no real money
            // movement to log there, only a history entry documenting what changed.
            $txId = null;
            if ($refundType !== 'dont_refund' && $lastOriginalTxn && $leftoverBase > 0) {
                // This leftover summary covers non-card refunds too (Cash has no Stripe PI at
                // all), so unlike the per-PI lookups above, a missing transaction_id here is a
                // real, expected case, not a data anomaly — DESTINATION (the column's own
                // default) is correct since payment_mode isn't a meaningful concept without a
                // Stripe charge. When a PI *is* present, look up its real persisted mode same as
                // everywhere else; a lookup miss there is a genuine anomaly and is left to fail
                // loudly via the typed $mode parameter rather than silently defaulting.
                $leftoverMode = !empty($lastOriginalTxn->transaction_id)
                    ? DB::table('transactions')->where('transaction_number', $lastOriginalTxn->transaction_id)->value('payment_mode')
                    : PaymentModeResolver::DESTINATION;
                $txId = TransactionService::storeAddonsRefundInTransaction(
                    $booking, $bookingAddonProxy, $transactionValue, mode: $leftoverMode
                );
            }
            HistoryService::storeAddonsRefundInHistory(
                $booking, $bookingAddonProxy, $transactionValue,
                array_values($leftoverHistoryItems), 'removed', $refundType, $txId, $refundRequestProxy,
                // isFinal=true: this is always the last history row this method can write, so
                // if the offset was never attached to a per-PI row above (either because it
                // wasn't touched by one, or $refundGroups was empty entirely), attach it here
                // rather than silently dropping it.
                $offsetItemsForHistory(array_keys($leftoverPiKeys), true)
            );
        }

        return $paymentIntent ?? null;
    }

    /**
     * Recursively insert pax addon fields
     *
     * @param int $bookingId
     * @param int $formId
     * @param int $paxId
     * @param string $paxIndex
     * @param array $added
     * @param array $updated
     * @param array $deleted
     * @return array
     */
    protected function upsertBookingAddonFields(int $bookingId, int $formId, ?int $paxId = null, ?string $paxIndex = null, array $added, array $updated, array $deleted, ?int $suppliedPersonOrdinal = null): array
    {
        $booking = BookingService::booking($bookingId);

        $isPaxAddon = $paxId !== null;

        /* -------------------------------------------------------------
        | Determine person ordinal (ONLY for pax addons)
        |
        | Priority:
        |  1. JS-supplied ordinal — the caller (bulk pax-addons JS) always resolves this from
        |     the actual visual slot the admin is editing, so it's authoritative whenever
        |     present. MUST outrank the soft-deleted lookup below: pax_id is the whole RATE
        |     GROUP (shared by every person in it, e.g. "Adult x3"), not one specific person,
        |     so within a single request that both removes one person's field and adds a
        |     DIFFERENT person's (e.g. reassigning an add-on from the 2nd pax to the 3rd), that
        |     lookup can't tell "this pax_id's own field was re-added" apart from "a completely
        |     different person who happens to share this pax_id had a field removed a moment
        |     ago" — it previously grabbed the 2nd pax's just-soft-deleted ordinal for the 3rd
        |     pax's brand new field, silently mislabeling who actually holds the add-on.
        |  2. Soft-deleted rows for this pax_id — only consulted when the caller has no
        |     supplied ordinal at all, so a re-add can still recover the pax's original ordinal.
        |  3. max + 1 — last resort when no ordinal is available at all.
        ------------------------------------------------------------- */
        $personOrdinal = null;

        if ($isPaxAddon && !empty($added)) {
            if ($suppliedPersonOrdinal !== null) {
                $personOrdinal = $suppliedPersonOrdinal;
            } else {
                // 0. Same-request remove-then-add: the rows being deleted in this call have not
                //    been soft-deleted yet, so withTrashed()->whereNotNull('deleted_at') won't
                //    find them. Extract their ordinal directly while they're still active.
                $previousOrdinal = null;
                if (!empty($deleted)) {
                    $deleteIds = collect($deleted)->pluck('db_id')->filter()->toArray();
                    if (!empty($deleteIds)) {
                        $previousOrdinal = TourBookingAddonField::whereIn('id', $deleteIds)
                            ->whereNotNull('person_ordinal')
                            ->value('person_ordinal');
                    }
                }

                // 1. Check previously soft-deleted rows for this pax_id — finds the most
                //    recently deleted row regardless of ordinal (avoids false-negative when
                //    display position diverges from stored ordinal due to ordinal gaps).
                if ($previousOrdinal === null) {
                    $previousOrdinal = TourBookingAddonField::withTrashed()
                        ->where('tour_booking_id', $bookingId)
                        ->where('form_id', $formId)
                        ->where('pax_id', $paxId)
                        ->whereNotNull('deleted_at')
                        ->whereNotNull('person_ordinal')
                        ->orderByDesc('deleted_at')
                        ->value('person_ordinal');
                }

                if ($previousOrdinal !== null) {
                    // Re-add: reuse the ordinal the pax had before their fields were removed
                    $personOrdinal = (int) $previousOrdinal;
                } else {
                    // Truly new pax, no prior record — assign next available ordinal
                    $nextOrdinal = TourBookingAddonField::where('tour_booking_id', $bookingId)
                        ->where('form_id', $formId)
                        ->where('pax_id', $paxId)
                        ->max('person_ordinal');

                    $personOrdinal = $nextOrdinal ? $nextOrdinal + 1 : 1;
                }
            }
        }

        /* -------------------------------------------------------------
        | 1. Load existing addon rows in one query
        ------------------------------------------------------------- */
        $existingRows = TourBookingAddonField::where('form_id', $formId)
            ->where('tour_booking_id', $bookingId)
            ->get()
            ->keyBy('id');

        /* -------------------------------------------------------------
        | 2. Insert new rows (store mapping for parent_id resolution)
        ------------------------------------------------------------- */
        $fieldToDb = [];

        foreach ($added ?? [] as $item) {
            $category = $this->getFieldCategory($item['field_id']);
            $package = $this->getFieldPackage($item['field_id']);

            $row = TourBookingAddonField::create([
                'tour_booking_id'     => $bookingId,
                'form_id'             => $formId,
                'field_id'            => $item['field_id'],
                'field_label'         => $this->getFieldLabel($item['field_id']),
                'option_id'           => $item['option_id'],
                'option_label'        => $item['option_label'],
                'value'               => $item['value'] ?? null,
                'category_id'         => $category['category_id'],
                'category_name'       => $category['category_name'],
                'package_id'          => $package['package_id'],
                'package_name'        => $package['package_name'],

                // pax-specific (nullable)
                'pax_id'              => $isPaxAddon ? $paxId : null,
                'pax_index'           => $isPaxAddon ? $paxIndex : null,
                'person_ordinal'      => $isPaxAddon ? $personOrdinal : null,
                // 'pax_id'              => $paxId,
                // 'pax_index'           => $paxIndex,
                // 'person_ordinal'      => $personOrdinal,
                'unit_type'           => $item['unit_type'],
                'unit'                => $item['unit'] ?? 0,
                'rule_id'             => $item['rule_id'],
                'applied_rule_snapshot' => $item['applied_rule_snapshot'],
                'operation_at'        => now(),
                'operation_by'        => $booking->created_by,
                'operation_by_user_type' => $booking->created_by_user_type,
            ]);

            // Use composite key (field_id_paxIndex) so multiple pax adding the same
            // field_id don't overwrite each other's DB row mapping.
            $mapKey = ($isPaxAddon && $paxIndex !== null)
                ? $item['field_id'] . '_' . $paxIndex
                : $item['field_id'];
            $fieldToDb[$mapKey] = $row->id;
        }

        /* -------------------------------------------------------------
        | 3. Update existing rows
        ------------------------------------------------------------- */
        foreach ($updated ?? [] as $item) {

            if (!isset($item['db_id']) || !$existingRows->has($item['db_id'])) {
                continue;
            }

            $row = $existingRows[$item['db_id']];

            $row->update([
                'option_id'           => $item['option_id'],
                'option_label'        => $item['option_label'],
                'value'               => $item['value'],
                'unit_type'           => $item['unit_type'] ?? $row->unit_type,
                'unit'                => $item['unit'] ?? $row->unit,
                'rule_id'             => $item['rule_id'],
                'applied_rule_snapshot' => $item['applied_rule_snapshot'] ?? $row->applied_rule_snapshot,
                'operation_at'        => now(),
                'operation_by'        => $booking->created_by,
                'operation_by_user_type' => $booking->created_by_user_type,
            ]);

            // Same composite-key convention used for $added above — without this, $fieldToDb
            // only ever reflects newly-added fields, never edits to existing ones. A request
            // that both adds one pax's field and edits a DIFFERENT pax's existing field then
            // returns a field_to_db map with no entry at all for the edited field, silently
            // mismapping "which field this edit's money belongs to" wherever field_to_db is
            // consulted afterwards (e.g. resolving which payment-link item a field's charge is
            // actually part of).
            $mapKey = ($isPaxAddon && $paxIndex !== null)
                ? $item['field_id'] . '_' . $paxIndex
                : $item['field_id'];
            $fieldToDb[$mapKey] = $row->id;
        }

        /* -------------------------------------------------------------
        | 4. Delete removed rows (batch delete)
        ------------------------------------------------------------- */
        $deleteIds = collect($deleted ?? [])->pluck('db_id')->filter()->toArray();
        if (!empty($deleteIds)) {
            // A deleted field can still have its own pending (Unpaid) addon payment-link item —
            // e.g. the field was sent via "Pay Later" link, then removed here before ever being
            // paid. Nothing elsewhere cancels that item, so it kept sitting there Unpaid forever
            // (still billing the customer for a field that no longer exists on the booking, and
            // still showing "Pending" everywhere the link's total is surfaced). Mirrors the same
            // old_db_id/field_to_db matching TourBookingRepository::movePendingAddonPaymentLinkItems()
            // already uses for the split-booking case.
            $this->cancelPendingAddonLinkItemsForDeletedFields($bookingId, $deleteIds);

            TourBookingAddonField::whereIn('id', $deleteIds)->delete();
        }

        /* -------------------------------------------------------------
        | 5. Fix parent-child references for newly added items
        ------------------------------------------------------------- */
        foreach ($added ?? [] as $item) {

            if (!isset($item['parent_id']) || !$item['parent_id']) {
                continue;
            }

            // Same composite-key convention used when $fieldToDb was populated above
            // (field_id_paxIndex for pax add-ons) — using the plain field_id here always
            // missed for pax add-ons, silently leaving every nested child's parent_id unset.
            $childMapKey  = ($isPaxAddon && $paxIndex !== null) ? $item['field_id']   . '_' . $paxIndex : $item['field_id'];
            $parentMapKey = ($isPaxAddon && $paxIndex !== null) ? $item['parent_id']  . '_' . $paxIndex : $item['parent_id'];
            $childDbId = $fieldToDb[$childMapKey] ?? null;
            $parentDbId = $fieldToDb[$parentMapKey] ?? null;

            // If parent was not newly added → lookup existing DB row by field_id
            if (!$parentDbId) {
                $parentQuery = TourBookingAddonField::where('form_id', $formId)
                    ->where('tour_booking_id', $bookingId)
                    ->where('field_id', $item['parent_id']);

                if (isset($paxId)) {
                    $parentQuery->where('pax_id', $paxId)
                        ->where('person_ordinal', $personOrdinal);
                }

                $parentDbId = $parentQuery->value('id');
            }

            if ($childDbId && $parentDbId) {
                TourBookingAddonField::where('id', $childDbId)->update([
                    'parent_id' => $parentDbId,
                ]);
            }
        }

        return $fieldToDb;
    }

    /**
     * Cancel any pending (Unpaid) addon-type payment-link item whose charge/refund actually
     * covers one of the addon fields being deleted here — otherwise a field removed before its
     * "Pay Later" link was ever paid keeps billing the customer for something that no longer
     * exists on the booking, and the booking keeps showing that item's amount as still Pending.
     *
     * Matching mirrors TourBookingRepository::movePendingAddonPaymentLinkItems() (the split-
     * booking equivalent): scoped to financial.to_charge/to_refund's own old_db_id (falling back
     * to a field_to_db lookup), not the item's broader field_to_db map, which can list fields
     * from a combined multi-pax request that never actually contributed to this item's amount.
     *
     * @param int $bookingId
     * @param array $deletedFieldIds
     * @return void
     */
    private function cancelPendingAddonLinkItemsForDeletedFields(int $bookingId, array $deletedFieldIds): void
    {
        if (empty($deletedFieldIds)) {
            return;
        }

        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            return;
        }

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        if (!$link) {
            return;
        }

        $addonItems = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'addon')
            ->where('status', 'Unpaid')
            ->get();

        $userType = function_exists('userType') ? userType() : 'Admin';

        foreach ($addonItems as $item) {
            $details = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);

            $financial = $details['financial'] ?? [];
            $fieldToDb = $details['field_to_db'] ?? [];
            $resolveFieldId = fn (array $lineItem): ?int => PaymentLinkService::resolvePendingAddonLineFieldId($lineItem, $fieldToDb);

            $toCharge = $financial['to_charge'] ?? [];
            $toRefund = $financial['to_refund'] ?? [];

            $chargedFieldIds = [];
            foreach (array_merge($toCharge, $toRefund) as $lineItem) {
                $fid = $resolveFieldId($lineItem);
                if ($fid !== null) {
                    $chargedFieldIds[] = $fid;
                }
            }
            if (empty(array_intersect($chargedFieldIds, $deletedFieldIds))) {
                continue;
            }

            // One 'Pay with Link' send can bundle several priced add-ons onto a single item —
            // deleting one of them must only drop its own share, not the whole item, or a
            // sibling field's still-legitimate pending charge gets silently written off. Keep
            // only the lines whose field isn't one of the ones being deleted.
            $keptCharge = array_values(array_filter($toCharge, fn ($li) => !in_array($resolveFieldId($li), $deletedFieldIds, true)));
            $keptRefund = array_values(array_filter($toRefund, fn ($li) => !in_array($resolveFieldId($li), $deletedFieldIds, true)));

            if (empty($keptCharge) && empty($keptRefund)) {
                // Every field on this item is being deleted -- the whole item goes, same as before.
                $item->status               = 'Cancelled';
                $item->updated_by           = Auth::id();
                $item->updated_by_user_type = $userType;
                $item->save();
                PaymentLinkService::recalculateRemainingPaymentLink($link->id);
                continue;
            }

            // Partial: some (not all) referenced fields are being deleted -- keep only the
            // surviving entries and recompute this item's totals from just those. Mirrors
            // PaymentLinkService::cancelPendingAddonLinkItemsForBooking()'s equivalent partial
            // case (that one keys off "field no longer exists"; this one keys off "field is in
            // the deleted set" -- same recomputation once the kept/removed split is made).
            $chargeGross = array_sum(array_map(
                fn ($li) => (float) ($li['charge_amount'] ?? 0) + (float) ($li['field_fee'] ?? 0) + (float) ($li['option_fee'] ?? 0),
                $keptCharge
            ));
            $chargeNet = array_sum(array_map(fn ($li) => (float) ($li['charge_amount'] ?? 0), $keptCharge));
            $chargeFees = array_sum(array_map(
                fn ($li) => (float) ($li['field_fee'] ?? 0) + (float) ($li['option_fee'] ?? 0),
                $keptCharge
            ));
            $refundGross = array_sum(array_map(fn ($li) => (float) ($li['refund_amount'] ?? 0), $keptRefund));

            $newAmount = round($chargeGross - $refundGross, 2);
            if ($newAmount <= 0.001) {
                $item->status               = 'Cancelled';
                $item->updated_by           = Auth::id();
                $item->updated_by_user_type = $userType;
                $item->save();
                PaymentLinkService::recalculateRemainingPaymentLink($link->id);
                continue;
            }

            $keptFieldToDbKeys = [];
            foreach (array_merge($keptCharge, $keptRefund) as $li) {
                $lookupKey = isset($li['pax_index']) && $li['pax_index'] !== ''
                    ? ($li['field_id'] ?? '') . '_' . $li['pax_index']
                    : ($li['field_id'] ?? '');
                if (isset($fieldToDb[$lookupKey])) {
                    $keptFieldToDbKeys[$lookupKey] = $fieldToDb[$lookupKey];
                }
            }

            $details['financial']['to_charge']  = $keptCharge;
            $details['financial']['to_refund']  = $keptRefund;
            $details['financial']['net_amount'] = round($chargeNet - $refundGross, 2);
            $details['field_to_db']             = $keptFieldToDbKeys;
            $details['platform_fees']           = round($chargeFees, 2);

            $item->amount                  = $newAmount;
            $item->details                 = $details;
            $item->updated_by              = Auth::id();
            $item->updated_by_user_type    = $userType;
            $item->save();
            PaymentLinkService::recalculateRemainingPaymentLink($link->id);
        }
    }

    /**
     * Get the label for a specific field.
     *
     * @param int $fieldId
     * @return string|null
     */
    private function getFieldLabel(int $fieldId): ?string
    {
        return AddonField::where('id', $fieldId)->value('label');
    }

    /**
     * Resolve a field's category, walking up parent_field_id when the field
     * itself has none set — mirrors the inheritance used when building the
     * form tree in AddonFormRepository (category lives on the top-level field
     * and cascades down to its children).
     *
     * @param int $fieldId
     * @return array{category_id: int|null, category_name: string|null}
     */
    private function getFieldCategory(int $fieldId): array
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
     * field itself has none set — mirrors getFieldCategory()'s inheritance
     * (the linked package lives on the top-level field and cascades down).
     *
     * @param int $fieldId
     * @return array{package_id: int|null, package_name: string|null}
     */
    private function getFieldPackage(int $fieldId): array
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
     * Identity key for a field selection — same field_id + same chosen option (falling back to
     * `value` for field types with no option_id, e.g. checkbox/text) reads as the same selection
     * regardless of which pax/row currently holds it. Used by savePaxAddons()'s swap/rotation
     * ledger-repoint logic to detect "this option moved from one row to another" unambiguously.
     */
    private function addonSelectionKey(int $fieldId, $optionId, $value): string
    {
        return $fieldId . '|' . (($optionId !== null && $optionId !== '') ? 'opt:' . $optionId : 'val:' . ($value ?? ''));
    }

    /**
     * Finds and removes (by reference) the pool entry that best represents this added item
     * being a reassignment of a removed one, for savePaxAddons()'s zero-net "no financial
     * action" path.
     *
     * Tries an EXACT match first (same field_id + option_id — the money moved but the
     * selection itself didn't change at all). Falls back to "same field, matching price" —
     * moving a T-Shirt from Adult to Child can legitimately land on a DIFFERENT size/option
     * that just happens to cost the same, and that must still be recognized as a real
     * transfer (see booking 65175: Adult's XL removed, Child's Large added, same $22 price,
     * different option_id — an exact-only match left the $22 stranded on the old field).
     *
     * @param  array $pool  List of {dbId, field, lastCharge}, mutated to remove the match
     * @return array|null
     */
    private function findReassignmentMatch(array &$pool, int $fieldId, $optionId): ?array
    {
        foreach ($pool as $i => $entry) {
            // lastCharge must be non-null here too (the fallback price-match loop below already
            // requires it) — a removed field that was NEVER actually charged (e.g. a Pay Later
            // add-on selected at booking creation; AddonBookingService::
            // insertPricePerPaxBookingAddons() only ever writes the field row, no transaction
            // row at all) has nothing to transfer, so it isn't a real reassignment. Without this
            // check, an exact field_id+option_id match still spliced it out of $pool here, the
            // caller's `if ($match && $lastCharge)` then correctly refused to log it as
            // "Reassigned" (lastCharge is null) — but by then it was already gone from $pool and
            // never reached the trailing "plain removal" loop either, so the deletion vanished
            // from the history audit trail entirely instead of showing as its own "Don't Refund"
            // entry with the field's real configured price.
            if ((int) $entry['field']->field_id === $fieldId
                && (string) $entry['field']->option_id === (string) $optionId
                && $entry['lastCharge']) {
                return array_splice($pool, $i, 1)[0];
            }
        }

        $addedPrice = $this->resolveAddonListPrice($fieldId, $optionId);
        if ($addedPrice === null) {
            return null;
        }

        foreach ($pool as $i => $entry) {
            if ((int) $entry['field']->field_id !== $fieldId || !$entry['lastCharge']) {
                continue;
            }
            $removedPrice = (float) $entry['lastCharge']->field_price + (float) $entry['lastCharge']->option_price;
            if (abs($removedPrice - $addedPrice) < 0.01) {
                return array_splice($pool, $i, 1)[0];
            }
        }

        return null;
    }

    /**
     * The configured list price for an add-on option (or the field itself, for field-only
     * pricing with no option) — used only to MATCH a newly-added selection against a removed
     * one by price when their option_id differs; never charged directly.
     */
    private function resolveAddonListPrice(int $fieldId, $optionId): ?float
    {
        if ($optionId) {
            $price = AddonFieldOption::where('id', $optionId)->value('price');
            if ($price !== null) {
                return (float) $price;
            }
        }

        $price = AddonField::where('id', $fieldId)->value('price');
        return $price !== null ? (float) $price : null;
    }

    /**
     * Retrieve or update the Stripe PaymentIntent for a card payment.
     * For web (card reader): updates the intent metadata before confirming.
     * For API calls: retrieves with expanded charges.
     *
     * @param object $request
     * @param object $booking
     * @return mixed
     */
    private function resolveCardPaymentIntent(object $request, object $booking)
    {
        $paymentIntentId = $request->payment_intent_id ?? '';

        if (!$paymentIntentId) {
            return null;
        }

        if ($request->is('api/*')) {
            return app(PaymentGatewayInterface::class)->retrievePayment(
                $paymentIntentId,
                ['expand' => ['charges.data.transfer']]
            );
        }

        // Card reader (web): update intent with booking metadata
        $package  = TourPackage::findOrFail($booking->tour_package_id);
        $customer = Customer::findOrFail($booking->customer_id);

        $metadata = [
            'booking_id'       => $booking->id,
            'tour_package'     => $package->name,
            'date'             => $booking->tourSlotTime->tourSlotDate->date,
            'time_from'        => $booking->tourSlotTime->time_from,
            'tourists'         => $booking->tourists,
            'customer_id'      => $customer->id,
            'customer_name'    => $customer->name,
            'customer_phone'   => $customer->phone_number,
            'customer_email'   => $customer->email,
            'split_value'      => (!empty($request->split_value)) ? 1 : 0,
        ];

        return BookingService::updateCardReaderIntent($paymentIntentId, $metadata);
    }

    /**
     * Check if booking has any Price Per Pax Add-ons exists
     *
     * @param int $bookingId
     * @param int|null $paxId
     * @return mixed
     * @throws Exception
     */
    public function isBookingPaxAddonsExists(int $bookingId, ?int $paxId = null): bool
    {
        return TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->where('unit_type', 'price_per_pax')
            ->when($paxId, function ($query) use ($paxId) {
                return $query->where('pax_id', $paxId);
            })
            ->exists();
    }

    /**
     * Get array of pax position indexes ($a in the tourist-list loop) that have addons for a
     * specific pax_id (rate group). Each returned index must be the exact slot that specific
     * pax currently occupies — never just "however many pax have add-ons, starting from slot
     * 0" (see below for why that broke).
     *
     * @param int $bookingId
     * @param int $paxId
     * @param int|null $totalTourists Current live headcount for this rate group ($tourists in
     *        BookingService::booking()'s loop). Pass this whenever known — without it we can
     *        only fall back to the old count-based approximation (see below).
     * @return array
     */
    public function getPaxAddonsIndexes(int $bookingId, int $paxId, ?int $totalTourists = null)
    {
        // One entry per real pax who has at least one priced add-on, keyed by their actual
        // person_ordinal (not array position) — this is what lets us tell "the 2nd pax has an
        // add-on, the 1st doesn't" apart from "the 1st pax has one, the 2nd doesn't", which a
        // bare count can never distinguish.
        $ordinals = TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->where('pax_id', $paxId)
            ->where('unit_type', 'price_per_pax')
            ->select('pax_index', 'person_ordinal')
            ->get()
            ->groupBy('pax_index')
            ->map(fn ($rows) => $rows->pluck('person_ordinal')->filter(fn ($v) => !is_null($v))->first())
            ->filter(fn ($v) => !is_null($v))
            ->map(fn ($v) => (int) $v)
            ->sort()
            ->values();

        if ($ordinals->isEmpty()) {
            return [];
        }

        if ($totalTourists === null) {
            // Caller doesn't know the current headcount — fall back to the old count-based
            // approximation (range 0..n-1) rather than guessing further.
            return range(0, $ordinals->count() - 1);
        }

        // A pax whose own person_ordinal still fits within the CURRENT headcount keeps that
        // EXACT slot — this is the normal case (e.g. only the 2nd of 2 pax has an add-on; slot
        // 0 must read "no add-on", not borrow slot 1's). Only ordinals that no longer fit at
        // all (an earlier-ordinal sibling was fully removed and the headcount shrank) are
        // "overflow" — those get compacted, in ordinal order, into whichever slots are still
        // unclaimed, which is the removal-after-deletion case this function's old count-based
        // approach was originally trying to protect.
        $claimedSlots = [];
        $overflowOrdinals = [];
        foreach ($ordinals as $ordinal) {
            if ($ordinal >= 0 && $ordinal < $totalTourists && !isset($claimedSlots[$ordinal])) {
                $claimedSlots[$ordinal] = true;
            } else {
                $overflowOrdinals[] = $ordinal;
            }
        }
        for ($slot = 0; $slot < $totalTourists && !empty($overflowOrdinals); $slot++) {
            if (!isset($claimedSlots[$slot])) {
                array_shift($overflowOrdinals);
                $claimedSlots[$slot] = true;
            }
        }

        return array_keys($claimedSlots);
    }

    /**
     * Check if booking has any add-ons exists [Price Per Unit | One Time | Price Per Pax]
     *
     * @param int $bookingId
     * @return bool
     * @throws Exception
     */
    public function isBookingHasAddons(int $bookingId): bool
    {
        return TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->whereIn('unit_type', ['price_per_pax', 'one_time', 'price_per_unit'])
            ->exists();
    }

    /**
     * Get Price Per Pax Add-ons list For The Booking
     *
     * @param object $request
     * @return mixed
     * @throws Exception
     */
    public function getBookingPaxAddons(object $request)
    {
        $bookingId  = $request->booking_id;

        // Check if this is an individual pax request
        if ($request->has('pax_id') && $request->has('pax_index')) {
            return $this->getIndividualPaxAddons($request);
        }

        if (!$request->filled('rate_groups')) {
            // variable NOT present
            $rateGroups = DB::table('tour_bookings_transact AS tbt')
                ->where('tbt.tour_booking_id', $bookingId)
                ->where('tbt.deleted_by', 0)
                ->distinct()
                ->pluck('tbt.tour_package_rate_id')
                ->toArray();
        } else {
            // Original rate group logic
            $rateGroups = array_unique((array) $request->rate_groups);
        }

        // Detect any pending addon payment-link item for this booking.
        // Used by the frontend to show a "pending link will be cancelled" warning on delete.
        $pendingAddonLinkItemId = null;
        $pendingPaxIndexes      = [];
        $bookingGroupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if ($bookingGroupId) {
            $pendingLinkIds = DB::table('tour_booking_payment_links')
                ->where('order_id', $bookingGroupId)
                ->where('status', 'Unpaid')
                ->where('section', 'remaining_payment')
                ->pluck('id');

            $pendingLinkItem = \App\Models\TourBookingPaymentLinkItem::whereIn('payment_link_id', $pendingLinkIds)
                ->where('type', 'addon')
                ->get()
                ->first(function ($item) use ($bookingId) {
                    $d = is_array($item->details) ? $item->details : (json_decode($item->details ?? '[]', true) ?? []);
                    return (int) ($d['booking_id'] ?? 0) === (int) $bookingId;
                });

            if ($pendingLinkItem) {
                $pendingAddonLinkItemId = $pendingLinkItem->id;
                $d = is_array($pendingLinkItem->details)
                    ? $pendingLinkItem->details
                    : (json_decode($pendingLinkItem->details ?? '[]', true) ?? []);
                foreach ($d['pax'] ?? [] as $pg) {
                    if (isset($pg['pax_index'])) $pendingPaxIndexes[] = $pg['pax_index'];
                }
                foreach ($d['financial']['to_charge'] ?? [] as $ci) {
                    if (isset($ci['pax_index'])) $pendingPaxIndexes[] = $ci['pax_index'];
                }
                $pendingPaxIndexes = array_unique($pendingPaxIndexes);
            }
        }

        $response = [];

        // Booking-level fee rate/applicability, used below to backfill a field's DISPLAY fee
        // when its ledger rows carry a genuine $0 (see $fieldTxnTotals' map() callback for why).
        $bookingTxnForFees   = BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->first();
        $addonFeePct         = (float) ($bookingTxnForFees->nat_fees_percentage ?? 0);
        $bookingMethodForFee = strtolower($this->resolveBookingPaymentMethodLabel($bookingId));
        $addonApplyFees      = $addonFeePct > 0 && ($bookingMethodForFee === 'card' || ($bookingMethodForFee === 'cash' && cashFees() === '1'));

        foreach ($rateGroups as $rateGroupId) {

            // 1️⃣ Fetch transact rows for THIS rate group (full details)
            $bookingTransact = DB::table('tour_bookings_transact AS tbt')
                ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->where('tbt.tour_booking_id', $bookingId)
                ->where('tbt.tour_package_rate_id', $rateGroupId)
                ->where('tbt.deleted_by', 0)
                ->get([
                    'tpr.rate_for',
                    'tbt.tour_package_rate_id AS rate_id',
                    'tbt.id AS transact_id',
                    'tbt.tourists',
                    'tbt.is_infant_rate',
                    'tbt.infants'
                ]);

            if ($bookingTransact->isEmpty()) {
                $response[] = [
                    'rate_group_id' => $rateGroupId,
                    'rate_for'      => null,
                    'transact_id'   => null,
                    'total_tourists'=> 0,
                    'pax'           => []
                ];
                continue;
            }

            // Extract transact IDs
            $transactIds = $bookingTransact->pluck('transact_id')->toArray();

            // Get rate_for & total tourists (sum for this rate group)
            $rateFor        = $bookingTransact->pluck('rate_for')->unique()->first();
            $rateGroupTransactId  = $bookingTransact->first()->transact_id; // pax_id in tour_booking_addon_fields table
            $totalTourists = $bookingTransact->where('is_infant_rate', 0)->sum('tourists') + $bookingTransact->where('is_infant_rate', 1)->sum('infants');

            // 2️⃣ fetch addons for these pax_ids
            // $addonRows = TourBookingAddonField::where('tour_booking_id', $bookingId)
            //     ->whereIn('pax_id', $transactIds)
            //     ->orderBy('id')
            //     ->get();
            // LEFT JOIN, not JOIN — a booking's addon field row must still surface here even if
            // its underlying addon_fields definition was later deleted/edited; an INNER JOIN
            // silently drops that historical row (and af.sort_order) entirely.
            $addonRows = DB::table('tour_booking_addon_fields as tbaf')
                ->leftJoin('addon_fields as af', 'af.id', '=', 'tbaf.field_id')
                ->where('tbaf.tour_booking_id', $bookingId)
                ->whereIn('tbaf.pax_id', $transactIds)
                ->whereNull('tbaf.deleted_at')
                ->orderBy('af.sort_order')
                ->get([
                    'tbaf.*',
                    'af.sort_order'
                ]);

            if ($addonRows->isEmpty()) {
                $response[] = [
                    'rate_group_id' => $rateGroupId,
                    'rate_for'      => $rateFor,
                    'transact_id'   => $rateGroupTransactId,
                    'total_tourists'=> $totalTourists,
                    'pax'           => []
                ];
                continue;
            }

            // 3️⃣ filter parents (only price_per_pax)
            $parentGroups = $addonRows
                ->where('unit_type', 'price_per_pax')
                ->groupBy('pax_index');

            $paxList = [];

            // Batch-fetch net paid totals per field.
            // Includes original charge records (status=paid, operation=charge) PLUS
            // edit-adjustment refunds from processAddonFinancialTransactions/
            // ChangePeopleAddonService (status=refunded, operation=refund, operation_group_id
            // IS NOT NULL) — i.e. an in-place price adjustment (e.g. an option downgrade), not a
            // full pax removal (deletePaxAddons refunds have operation_group_id IS NULL and are
            // excluded here for that reason). Charge and refund sides are summed SEPARATELY (via
            // ABS(), then subtracted) rather than in one signed SUM(), because refund rows are
            // not guaranteed to carry one consistent sign — historical rows may still be negative
            // even though new rows are written positive; a signed SUM() would double-count either way.
            $allFieldIds    = $addonRows->pluck('id')->toArray();
            $fieldTxnTotals = collect();
            if (!empty($allFieldIds)) {
                // Scoped to $bookingId — Split Booking can move a field to a different booking,
                // leaving its pre-split charge/refund rows still tagged to the OLD booking. Without
                // this, a moved field's historical rows from before the split would be summed in
                // here too, doubling the "Passenger Add-ons" modal's displayed price/fee.
                $fieldTxnTotals = DB::table('tour_booking_addon_transactions')
                    ->whereIn('tour_booking_addon_id', $allFieldIds)
                    ->where('tour_booking_id', $bookingId)
                    ->where(function ($q) {
                        $q->where(function ($inner) {
                            $inner->where('status', 'paid')
                                  ->where('operation', 'charge');
                        })->orWhere(function ($inner) {
                            $inner->where('status', 'refunded')
                                  ->where('operation', 'refund')
                                  ->whereNotNull('operation_group_id');
                        });
                    })
                    ->whereNull('deleted_at')
                    ->selectRaw("
                        tour_booking_addon_id,
                        SUM(CASE WHEN operation = 'charge' THEN ABS(field_price) + ABS(option_price) ELSE 0 END) as charge_price,
                        SUM(CASE WHEN operation = 'charge' THEN ABS(field_fee) + ABS(option_fee) ELSE 0 END) as charge_fee,
                        SUM(CASE WHEN operation = 'refund' THEN ABS(field_price) + ABS(option_price) ELSE 0 END) as refund_price,
                        SUM(CASE WHEN operation = 'refund' THEN ABS(field_fee) + ABS(option_fee) ELSE 0 END) as refund_fee
                    ")
                    ->groupBy('tour_booking_addon_id')
                    ->get()
                    ->map(function ($row) use ($addonApplyFees, $addonFeePct) {
                        $row->charged_price = round(((float) $row->charge_price) - ((float) $row->refund_price), 2);
                        $row->charged_fee   = round(((float) $row->charge_fee)   - ((float) $row->refund_fee), 2);
                        // A field's OWN ledger rows can carry a genuine $0 fee even though it was
                        // really charged a nonzero price: processAddonFinancialTransactions()
                        // deliberately zeroes the PER-ITEM fee whenever an add/remove edit nets to
                        // a charge or refund with an "offset" (see $hasChargeOffset/
                        // $hasRefundOffset there) — the real fee for that edit is booked once in
                        // aggregate against the booking's own Payment/History rows, never split
                        // back onto one specific field. Left alone, this "Passenger Add-ons" popup
                        // showed those specific items with no fee and an artificially low "Charged
                        // Total" even though the exact same add-on genuinely carries a fee
                        // everywhere else it's charged. Backfill it here for DISPLAY using the
                        // booking's own current fee rate — safe because a field that's genuinely
                        // fee-exempt (Pay Later never reaches 'paid' status here, Cash without
                        // cashFees, or Invoiced) already has $addonApplyFees false, so this stays
                        // $0 for those; a real partial fee (e.g. a ticket-refund downgrade) is
                        // already nonzero and never reaches this branch.
                        if ($row->charged_price > 0 && $row->charged_fee <= 0 && $addonApplyFees) {
                            $row->charged_fee = round($row->charged_price * $addonFeePct / 100, 2);
                        }
                        return $row;
                    })
                    ->keyBy('tour_booking_addon_id');
            }
            $fieldIdsWithPaidTxn = $fieldTxnTotals->keys()->toArray();

            // 4️⃣ Build pax addon structure
            $ordinalCounter = 0;
            foreach ($parentGroups as $paxIndex => $parentItems) {

                $allForPax = $addonRows->where('pax_index', $paxIndex)->values();
                $personOrdinal = $allForPax->pluck('person_ordinal')->filter(fn ($v) => !is_null($v))->first();

                $numericOrdinal = null;
                if (is_numeric($personOrdinal)) {
                    $numericOrdinal = (int) $personOrdinal;
                } elseif ($paxIndex && preg_match('/-(\d+)$/', $paxIndex, $matches)) {
                    $numericOrdinal = (int) $matches[1];
                } else {
                    $numericOrdinal = $ordinalCounter;
                }

                $paxFieldIds  = $allForPax->pluck('id')->toArray();
                $hasPaidTxn   = !empty(array_intersect($paxFieldIds, $fieldIdsWithPaidTxn));
                $chargedPrice = 0.0;
                $chargedFee   = 0.0;
                foreach ($paxFieldIds as $fid) {
                    if ($fieldTxnTotals->has($fid)) {
                        $row = $fieldTxnTotals->get($fid);
                        $chargedPrice += (float) $row->charged_price;
                        $chargedFee   += (float) $row->charged_fee;
                    }
                }

                $paxList[] = [
                    'pax_id'               => $allForPax->pluck('pax_id')->unique()->first(),
                    'pax_index'            => $paxIndex,
                    'person_ordinal'       => $numericOrdinal,
                    'has_paid_transaction' => $hasPaidTxn,
                    'pending_link_item_id' => $pendingAddonLinkItemId ?: null,
                    'charged_price'        => round($chargedPrice, 2),
                    'charged_fee'          => round($chargedFee, 2),
                    'tree'                 => $this->buildNestedTree($allForPax, $addonRows, $fieldTxnTotals->toArray())
                ];
                $ordinalCounter++;
            }

            // $parentGroups was built by groupBy('pax_index') over $addonRows, which is ORDERED
            // BY af.sort_order (the ADD-ON FIELD's own display order) — NOT by traveler. groupBy()
            // preserves first-appearance order, so whichever traveler's row happens to carry the
            // LOWEST sort_order field lands FIRST in $paxList, regardless of which physical
            // traveler they are. E.g. only travelers #3/#4 (of 4) have "Photography Package"
            // (a lower sort_order than "Sweater", which every traveler has): the sorted addon rows
            // list both Photography rows (travelers #3, #4) before any Sweater row, so groupBy()
            // creates their pax groups FIRST — $paxList then reads
            // [traveler#3, traveler#4, traveler#1, traveler#2], which the "Passenger Add-ons"
            // modal renders as 4 sequential blocks with no traveler number of its own (see
            // renderPaxAddonsModal()) — i.e. it visually looks exactly like Photography Package
            // was moved onto travelers #1/#2. $numericOrdinal (person_ordinal, already computed
            // above) is each pax's TRUE 0-based traveler position — sort by it so the modal's
            // block order always matches real traveler order regardless of which fields any one
            // traveler happens to have.
            usort($paxList, fn ($a, $b) => $a['person_ordinal'] <=> $b['person_ordinal']);

            // 5️⃣ Final structured output
            $response[] = [
                'rate_group_id'  => $rateGroupId,
                'rate_for'       => $rateFor,
                'transact_id'    => $rateGroupTransactId,
                'total_tourists' => $totalTourists,
                'pax'            => $paxList
            ];
        }

        return response()->json($response);
    }

    /**
     * Get individual pax addons for a specific pax
     *
     * @param object $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function getIndividualPaxAddons(object $request)
    {
        $bookingId  = $request->booking_id;
        $paxId      = $request->pax_id;
        $paxIndex   = $request->pax_index;
        $rateGroupId = $request->rate_group_id;

        // Fetch the specific transact row
        $bookingTransact = DB::table('tour_bookings_transact AS tbt')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->where('tbt.tour_booking_id', $bookingId)
            ->where('tbt.id', $paxId)
            ->where('tbt.deleted_by', 0)
            ->first([
                'tpr.rate_for',
                'tbt.tour_package_rate_id AS rate_id',
                'tbt.id AS transact_id',
                'tbt.tourists'
            ]);

        if (!$bookingTransact) {
            return response()->json([]);
        }

        // Fetch ALL addons for this specific pax_id
        $addonRows = TourBookingAddonField::where('tour_booking_id', $bookingId)
            ->where('pax_id', $paxId)
            ->orderBy('id')
            ->get();

        if ($addonRows->isEmpty()) {
            return response()->json([]);
        }

        // Group by pax_index to handle multiple tourists under same pax_id
        $paxGroups = $addonRows->where('unit_type', 'price_per_pax')->groupBy('pax_index');
        
        if ($paxGroups->isEmpty()) {
            return response()->json([]);
        }

        // Get the pax_index by position (0, 1, 2, etc.)
        $allPaxIndexes = $paxGroups->keys()->values();
        
        if (!isset($allPaxIndexes[$paxIndex])) {
            return response()->json([]);
        }

        $actualPaxIndex = $allPaxIndexes[$paxIndex];
        
        // Get all addons (including children) for this specific pax_index
        $filteredAddons = $addonRows->where('pax_index', $actualPaxIndex);

        $paxList = [[
            'pax_id'         => $paxId,
            'pax_index'      => $actualPaxIndex,
            'person_ordinal' => $paxIndex,
            'tree'           => $this->buildNestedTree($filteredAddons, $addonRows)
        ]];

        $response = [[
            'rate_group_id'  => $rateGroupId,
            'rate_for'       => $bookingTransact->rate_for,
            'total_tourists' => 1,
            'pax'            => $paxList
        ]];

        return response()->json($response);
    }

    /**
     * Build a nested tree structure from flat items
     *
     * @param object $flatItems
     * @param object $addonRows
     * @param array $fieldTxnTotals
     * @return array
     */
    protected function buildNestedTree(object $flatItems, object $addonRows, array $fieldTxnTotals = [])
    {
        $indexed = $flatItems->keyBy('id');

        $tree = [];

        $fieldIds = $addonRows->pluck('field_id')->filter()->unique()->values()->all();
        $optionIds = $addonRows->pluck('option_id')->filter()->unique()->values()->all();

        $fields = [];
        if (!empty($fieldIds)) {
            $fields = AddonField::whereIn('id', $fieldIds)
                ->get()
                ->keyBy('id')
                ->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'label' => $f->label,
                        'type' => $f->type,
                        'required' => (bool) $f->required,
                        'unit_type' => $f->unit_type,
                        'additional_fee' => (bool) $f->additional_fee,
                        'price' => $f->price,
                    ];
                })
                ->toArray();
        }

        $options = [];
        if (!empty($optionIds)) {
            $options = AddonFieldOption::whereIn('id', $optionIds)
                ->get()
                ->keyBy('id')
                ->map(function ($o) {
                    return [
                        'id' => $o->id,
                        'label' => $o->label,
                        'value' => $o->value,
                        'price' => $o->price,
                        'additional_fee' => (bool) $o->additional_fee,
                    ];
                })
                ->toArray();
        }

        foreach ($indexed as $item) {
            if ($item->parent_id === null) {
                $tree[] = $this->buildNode($item, $indexed, $fields, $options, $fieldTxnTotals);
            }
        }

        return $tree;
    }

    /**
     * Build a tree structure from flat items
     *
     * @param object $node
     * @param object $all
     * @param array $fields
     * @param array $options
     * @param array $fieldTxnTotals
     * @return array
     */
    protected function buildNode(object $node, object $all, array $fields, array $options, array $fieldTxnTotals = [])
    {
        $children = $all->where('parent_id', $node->id);
        $txn = $fieldTxnTotals[$node->id] ?? null;

        return [
            'id' => $node->id,
            'tour_booking_id' => $node->tour_booking_id,
            'form_id' => $node->form_id,
            'field_id' => $node->field_id,
            'field_label' => $node->field_label ?? ($fields[$node->field_id]['label'] ?? null),
            'option_id' => $node->option_id,
            'option_label' => $node->option_label ?? ($options[$node->option_id]['label'] ?? null),
            'rule_id' => $node->rule_id,
            'parent_id' => $node->parent_id,
            'value' => $node->value,
            'pax_id' => $node->pax_id,
            'pax_index' => $node->pax_index,
            'unit_type' => $node->unit_type,
            'unit' => (int) $node->unit,
            'applied_rule_snapshot' => $node->applied_rule_snapshot,
            'field_meta' => $fields[$node->field_id] ?? null,
            'option_meta' => $options[$node->option_id] ?? null,
            'paid_price' => $txn ? round((float) $txn->charged_price, 2) : 0.0,
            'paid_fee'   => $txn ? round((float) $txn->charged_fee,   2) : 0.0,
            'children' => $children->map(fn($c) => $this->buildNode($c, $all, $fields, $options, $fieldTxnTotals))->values()
        ];
    }

    /**
     * Resolve which tour_booking_addon_fields belong to the Nth person(s) (0-based, by creation
     * order) within one rate group's pax_id — for callers that only know a POSITION (e.g. the
     * classic "Edit Booking" delete flow's checkbox index, `data-pax-index="{{ $a }}"` in
     * tour-booking-info.blade.php) and not the field rows' own opaque `pax_index` value.
     *
     * `tour_booking_addon_fields.pax_index` is a free-form string set by whatever created the
     * booking (see 2025_09_29_171159_create_tour_booking_addon_fields_table.php and
     * DeletePaxAddonsExplicitFieldIdsTest's 'explicit-test-a'/'explicit-test-b' fixtures) — it is
     * NOT guaranteed to be a simple sequential "0", "1" matching the admin edit page's own
     * position numbering. The only reliable way to turn "the 2nd person" into an actual scope is
     * the same position -> real-pax_index mapping getIndividualPaxAddons() already uses: order
     * this pax_id's OWN price_per_pax rows by id (creation order = person order) and take the
     * Nth distinct pax_index value.
     *
     * @param  int   $paxId     tour_bookings_transact.id for the rate group
     * @param  int[] $positions 0-based person positions within that rate group
     * @return int[] tour_booking_addon_fields.id values for every field (parent + children) tied
     *               to those people — empty when the rate group carries no pax add-ons at all.
     */
    public function resolveFieldIdsByPaxPositions(int $paxId, array $positions): array
    {
        if (empty($positions)) {
            return [];
        }

        $orderedPaxIndexes = TourBookingAddonField::where('pax_id', $paxId)
            ->where('unit_type', 'price_per_pax')
            ->orderBy('id')
            ->pluck('pax_index')
            ->unique()
            ->values();

        $resolvedPaxIndexes = collect($positions)
            ->map(fn ($position) => $orderedPaxIndexes->get((int) $position))
            ->filter(fn ($value) => $value !== null)
            ->values();

        if ($resolvedPaxIndexes->isEmpty()) {
            return [];
        }

        return TourBookingAddonField::where('pax_id', $paxId)
            ->whereIn('pax_index', $resolvedPaxIndexes->all())
            ->pluck('id')
            ->all();
    }

    /**
     * Delete pax add-ons selected from edit booking delete feature
     *
     * @param int $bookingId
     * @param string $paxAddonIds JSON array of opaque tour_booking_addon_fields.pax_index
     *        strings — ignored when $explicitFieldIds is provided.
     * @param string $refundType
     * @param string|null $description
     * @param array|null $selectedFieldIds Which in-scope fields to also refund (null = all).
     * @param array|null $explicitFieldIds tour_booking_addon_fields.id values, used directly as
     *        scope instead of resolving $paxAddonIds via pax_index — see deletePaxAddons()'s body.
     * @param string|null $manualRefundVia When the admin picked "Cash" or "Check/Wire" in the
     *        "Update Booking" modal to manually reimburse a CARD-paid booking instead of an actual
     *        Stripe refund (editBooking()'s $request->is_cash), pass 'Cash' / 'Check/Wire' here —
     *        this skips the Stripe refundPayment() call entirely and records the refund ledger/
     *        history rows under that method instead of the field's original card charge, matching
     *        how the tour-side refund already honors is_cash. Null (default) keeps prior behavior:
     *        refund via Stripe against the field's own original PaymentIntent.
     * @return mixed
     * @throws Exception
     */
    public function deletePaxAddons(
        int $bookingId,
        string $paxAddonIds,
        string $refundType = Constants::ADDONS_DONT_REFUND,
        ?string $description = null,
        ?array $selectedFieldIds = null,
        ?array $explicitFieldIds = null,
        ?string $targetTransactionNumber = null,
        ?string $manualRefundVia = null,
        ?int $pendingLinkItemId = null
    ) {
        DB::beginTransaction();
        try {
            $payLinkSnapshot = $this->snapshotPayLaterAddonsForPaymentLink($bookingId);

            $ids = json_decode($paxAddonIds, TRUE);

            // $explicitFieldIds (tour_booking_addon_fields.id, NOT the opaque pax_index string)
            // lets the direct "Edit Booking" delete flow identify scope by real field id instead
            // of pax_index — it already knows the exact ids from the "Update Booking" modal's own
            // add-ons block (same position-derivation the modal displays with), so it no longer
            // needs the separate "Passenger Add-ons" selection modal to resolve pax_index strings.
            // The split-people flow and the standalone "delete only add-ons" endpoint still pass
            // pax_index strings via $paxAddonIds and are unaffected.
            if ($explicitFieldIds !== null) {
                if (empty($explicitFieldIds)) {
                    DB::commit();
                    return true;
                }

                $bookingPaxAddons = TourBookingAddonField::where('tour_booking_id', $bookingId)
                    ->whereIn('id', $explicitFieldIds)
                    ->get();
            } else {
                if (empty($ids)) {
                    DB::commit();
                    return true;
                }

                $bookingPaxAddons = TourBookingAddonField::where('tour_booking_id', $bookingId)
                    ->whereIn('pax_index', $ids)
                    ->get();
            }

            if ($bookingPaxAddons->isEmpty()) {
                DB::commit();
                return true;
            }

            // Tracks which of $bookingPaxAddons actually got a refund transaction below, so the
            // "not refunded" fields (whole batch is dont_refund, or individually deselected via
            // $selectedFieldIds) can still get a history entry after — see the block right before
            // the hard-delete step. Without it, those fields vanish with zero audit trail.
            $refundedFieldIds = [];

            // Process refund for any paid charge transactions on these fields
            if ($refundType !== Constants::ADDONS_DONT_REFUND) {
                $fieldIds = $bookingPaxAddons->pluck('id')->toArray();

                // The admin can deselect specific add-on line items in the "Update Booking" modal
                // so only the CHOSEN ones get refunded. Deselected fields are still hard-deleted
                // below (which pax slots are in scope is decided independently, upstream) — they
                // just never get a refund transaction/Stripe call here, which is this method's
                // existing convention for "kept, not refunded" (same as passing
                // Constants::ADDONS_DONT_REFUND for the whole batch). Null means no explicit
                // selection was submitted — keep refunding every field in scope, as before.
                if ($selectedFieldIds !== null) {
                    $fieldIds = array_values(array_intersect($fieldIds, $selectedFieldIds));
                }

                // Scoped to $bookingId — a field now carries charge history from a PRIOR booking
                // too once Split Booking can move it between bookings (see AddonBookingService::
                // splitBookingPaxAddons()), so only the currently-owning booking's own charge is
                // ours to refund here; without this a moved field's old, already-settled charge
                // row would double up with its new one in the sum below.
                $paidTransactions = TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)
                    ->where('tour_booking_id', $bookingId)
                    ->where('status', 'paid')
                    ->where('operation', 'charge')
                    ->with('addonField')
                    ->get();

                // $targetTransactionNumber (the admin's "Tour Transaction" pick in the "Update
                // Booking" modal's Block 2) is intentionally NOT applied here. It used to
                // force-overwrite every removed add-on's transaction_id/application_fee_id to
                // that one picked PaymentIntent, redirecting the WHOLE refund (tour side AND
                // every add-on) onto it, regardless of each add-on's own real origin PI.
                //
                // Confirmed wrong via booking KEN-348645: add-ons were charged on their own
                // separate PaymentIntent (pi_...2gi4LVYQ, $98.82), but a Ticket Refund pax removal
                // with the TICKET PaymentIntent (pi_...1JmhezY4) selected in Block 2 redirected the
                // $45.00 add-on refund onto THAT PI too — leaving the add-on's own PI showing zero
                // refund activity despite genuinely holding that money, and making the modal's
                // "Add-ons only" balance badge (computed from which add-on fields still exist, not
                // from real per-PI refund records) silently drift out of sync with reality on the
                // next open, even though nothing was ever actually refunded from that PI.
                //
                // Per the modal's own design (see _update-booking-modal.blade.php's header
                // comment: "tour transaction picker (tour amount only), add-ons (own block, never
                // merged with the tour amount)") and matching refundAddonsByBookingId() — the
                // whole-booking cancel flow's sibling method, which has never had an override
                // mechanism at all — Block 2 must only ever steer the TICKET-side refund (handled
                // separately, above this method's own call site in editBooking()/
                // EditBookingService). Every add-on always refunds against its own real origin PI
                // ($txn->transaction_id, set at the original charge), full stop; Block 3's
                // checkboxes are the only control needed for "which add-ons to refund." The
                // $targetTransactionNumber parameter is kept (and still populated by callers) only
                // because it's a shared positional signature — see the git history for the removed
                // override block if this needs to be revisited.
                if ($paidTransactions->isNotEmpty()) {
                    $refundsByPi     = [];
                    $newTransactions = [];
                    $lastBooking     = null;

                    foreach ($paidTransactions as $txn) {
                        $booking     = BookingService::booking($bookingId);
                        $lastBooking = $booking;

                        // Subtract any prior edit-adjustment refunds (option downgrades, etc.)
                        // so we refund the net outstanding, not the original charge amount.
                        // Edit refunds from processAddonFinancialTransactions always have an
                        // operation_group_id set and store negative amounts; ABS() handles both
                        // positive and negative conventions safely.
                        $priorEditRefunds = TourBookingAddonTransaction::where('tour_booking_addon_id', $txn->tour_booking_addon_id)
                            ->where('tour_booking_id', $bookingId)
                            ->where('status', 'refunded')
                            ->where('operation', 'refund')
                            ->whereNotNull('operation_group_id')
                            ->get();

                        $priorRefFieldP = 0.0; $priorRefOptP = 0.0;
                        $priorRefFieldF = 0.0; $priorRefOptF = 0.0;
                        foreach ($priorEditRefunds as $r) {
                            $priorRefFieldP += abs(floatval($r->field_price));
                            $priorRefOptP   += abs(floatval($r->option_price));
                            $priorRefFieldF += abs(floatval($r->field_fee));
                            $priorRefOptF   += abs(floatval($r->option_fee));
                        }

                        $origFieldPrice  = max(0.0, floatval($txn->field_price)  - $priorRefFieldP);
                        $origFieldFee    = max(0.0, floatval($txn->field_fee)    - $priorRefFieldF);
                        $origOptionPrice = max(0.0, floatval($txn->option_price) - $priorRefOptP);
                        $origOptionFee   = max(0.0, floatval($txn->option_fee)   - $priorRefOptF);

                        switch ($refundType) {
                            case Constants::ADDONS_TICKET_REFUND:
                                $refFieldPrice  = $origFieldPrice;
                                $refOptionPrice = $origOptionPrice;
                                $refFieldFee    = 0;
                                $refOptionFee   = 0;
                                $refundLabel    = 'Full Refund';
                                break;

                            case Constants::ADDONS_FULL_REFUND:
                                $refFieldPrice  = $origFieldPrice;
                                $refOptionPrice = $origOptionPrice;
                                $refFieldFee    = $origFieldFee;
                                $refOptionFee   = $origOptionFee;
                                $refundLabel    = '100 % Refund';
                                break;

                            default:
                                continue 2;
                        }

                        $refTotal = $refFieldPrice + $refOptionPrice + $refFieldFee + $refOptionFee;

                        if ($txn->status !== 'open' && $txn->payment_method === 'card' && $txn->transaction_id) {
                            $pi = $txn->transaction_id;
                            if (!isset($refundsByPi[$pi])) {
                                $refundsByPi[$pi] = [
                                    'total'              => 0,
                                    'transaction_id'     => $txn->transaction_id,
                                    'application_fee_id' => $txn->application_fee_id,
                                    'is_full_refund'     => false,
                                ];
                            }
                            $refundsByPi[$pi]['total'] += $refTotal;
                            if ($refundType === Constants::ADDONS_FULL_REFUND) {
                                $refundsByPi[$pi]['is_full_refund'] = true;
                            }
                        }

                        $refundedFieldIds[] = $txn->tour_booking_addon_id;

                        $newTransactions[] = [
                            'txn'          => $txn,
                            'field_price'  => $refFieldPrice,
                            'field_fee'    => $refFieldFee,
                            'option_price' => $refOptionPrice,
                            'option_fee'   => $refOptionFee,
                            'label'        => $refundLabel,
                        ];
                    }

                    // Execute Stripe refunds — one API call per PaymentIntent.
                    // Cap the amount at what the PI actually has available. Sourced from the
                    // `transactions` table — the PI's real, complete local ledger (every charge
                    // and refund ever recorded against this exact transaction_number, regardless
                    // of which feature wrote it) — the same source the ticket-side refund above
                    // already validates against (see the "Unable to refund booking..." checks
                    // further up in V3/TourBookingRepository.php's editBooking()).
                    //
                    // This must NOT be computed from tour_booking_addon_transactions (as it
                    // previously was): when $targetTransactionNumber overrides $piId to the
                    // admin's explicitly-picked "Tour Transaction" (see above), that PI is very
                    // often NOT the add-on's own original charge PI — a split-payment booking's
                    // add-ons are commonly purchased via a separate, later PaymentIntent — so it
                    // has little or no addon-tagged history of its own. Querying
                    // tour_booking_addon_transactions for it wrongly computed ~$0 available and
                    // silently skipped the real Stripe call below, even though the chosen PI had
                    // plenty of real remaining balance — while the ledger/history write further
                    // down (unconditional on this amount) still recorded the full refund as if
                    // Stripe had processed it, leaving Stripe and the local system disagreeing.
                    foreach ($refundsByPi as $piId => $data) {
                        if ($data['total'] > 0) {
                            $piCharged  = (float) DB::table('transactions')
                                ->where('transaction_number', $piId)
                                ->where('status', 'Completed')
                                ->sum('amount');
                            $piRefunded = (float) DB::table('transactions')
                                ->where('transaction_number', $piId)
                                ->where('status', 'Refund')
                                ->sum('amount');
                            $piAvailable    = round(max(0.0, $piCharged - $piRefunded), 2);
                            $amountToRefund = round(min($data['total'], $piAvailable), 2);

                            // $manualRefundVia set means the admin is reimbursing this Card-paid
                            // add-on by hand (Cash/Check-Wire) instead of an actual Stripe refund —
                            // same "is_cash" override the tour-side refund already respects. Still
                            // record the ledger/history rows below (with that method instead of
                            // 'card'); just skip the real gateway call.
                            if ($amountToRefund > 0 && $manualRefundVia === null) {
                                $refundArray = [
                                    'payment_intent'   => $piId,
                                    'amount'           => intval(round($amountToRefund * 100)),
                                    'reverse_transfer' => true,
                                ];
                                if ($data['is_full_refund']) {
                                    $refundArray['refund_application_fee'] = true;
                                }
                                $piPaymentMode = DB::table('transactions')->where('transaction_number', $piId)->value('payment_mode');
                                app(PaymentGatewayInterface::class)->refundPayment($refundArray, $piPaymentMode);
                            }
                        }
                    }

                    // Create refund transaction records and booking history — per-PaymentIntent,
                    // NOT one combined total applied to every PI. A single call can refund fields
                    // that were originally charged on DIFFERENT PaymentIntents (e.g. two different
                    // people's add-ons), and each PI must get its OWN amount/fee/transaction_id in
                    // its own `transactions` row and history entry — reusing one grand total (and
                    // the last-seen transaction's id) for every PI silently duplicated the combined
                    // amount onto each entry and tagged them with the wrong PI/application fee id.
                    $historyByPi = [];
                    $piMeta      = [];

                    foreach ($newTransactions as $data) {
                        $txn = $data['txn'];
                        // How THIS refund actually moved, for the ledger/history — the field's
                        // ORIGINAL charge stays 'card' on its own charge row untouched; only this
                        // refund event is tagged as the admin's manual Cash/Check-Wire reimbursement
                        // when applicable.
                        $effectivePaymentMethod = $manualRefundVia ?? $txn->payment_method;

                        TourBookingAddonTransaction::create([
                            'tour_booking_id'        => $bookingId,
                            'tour_booking_addon_id'  => $txn->tour_booking_addon_id,
                            'payment_method'         => $effectivePaymentMethod,
                            'status'                 => 'refunded',
                            'operation'              => 'refund',
                            'transaction_id'         => $txn->transaction_id,
                            'application_fee_id'     => $txn->application_fee_id,
                            'field_additional_fee'   => $txn->field_additional_fee,
                            'option_additional_fee'  => $txn->option_additional_fee,
                            'field_price'            => $data['field_price'],
                            'field_fee'              => $data['field_fee'],
                            'option_price'           => $data['option_price'],
                            'option_fee'             => $data['option_fee'],
                            'notes'                  => $description,
                            'operation_at'           => now(),
                            'operation_by'           => Auth::id(),
                            'operation_by_user_type' => userType(),
                        ]);

                        $rowPrice = $data['field_price'] + $data['option_price'];
                        $rowFee   = $data['field_fee']   + $data['option_fee'];

                        // Card rows share one real Stripe PI, so grouping by transaction_id
                        // already collapses them into a single history/transaction row.
                        // Cash/Check-Wire rows have no PI at all — falling back to the addon
                        // field id kept every field in its own group, so removing a pax with N
                        // cash-paid add-ons wrote N separate refund history entries instead of
                        // one combined entry (the behavior card already has). Group everything
                        // without a real PI by payment method instead, same "one settlement
                        // action, one entry" rule refundAddonsByBookingId() already follows.
                        // Grouped by the ORIGINAL payment_method/PI (not $effectivePaymentMethod)
                        // even under a manual override — that's still what identifies "one
                        // settlement batch"; only how it's tagged/settled changes.
                        $piKey = ($txn->payment_method === 'card' && $txn->transaction_id)
                            ? $txn->transaction_id
                            : ('method_' . ($txn->payment_method ?? 'unknown'));
                        $historyByPi[$piKey][] = [
                            'name'           => trim(implode(' — ', array_filter([$txn->addonField->field_label ?? null, $txn->addonField->option_label ?? null]))),
                            'price'          => $rowPrice,
                            'fee'            => $rowFee,
                            'payment_method' => $effectivePaymentMethod,
                            'status'         => $data['label'],
                        ];

                        if (!isset($piMeta[$piKey])) {
                            $piMeta[$piKey] = [
                                'price'              => 0,
                                'fee'                => 0,
                                'transaction_id'     => $txn->transaction_id,
                                'application_fee_id' => $txn->application_fee_id,
                                'payment_method'     => $effectivePaymentMethod,
                            ];
                        }
                        $piMeta[$piKey]['price'] += $rowPrice;
                        $piMeta[$piKey]['fee']   += $rowFee;
                    }

                    if ($lastBooking && !empty($historyByPi)) {
                        $requestProxy = (object) ['description' => $description];

                        foreach ($historyByPi as $piKey => $histItems) {
                            $meta = $piMeta[$piKey];
                            $transactionValue = [
                                'price'              => $meta['price'],
                                'fee'                => $meta['fee'],
                                // A manual Cash/Check-Wire refund ($manualRefundVia set) never
                                // touched Stripe — recording the field's original PaymentIntent/
                                // application fee id on this ledger row would misleadingly suggest
                                // it IS that real Stripe operation. Blank both, matching the exact
                                // convention a genuinely Cash-paid booking's own refund already uses
                                // (TransactionService::storeRefundInTransaction(..., '', '')).
                                'transaction_id'     => $manualRefundVia === null ? $meta['transaction_id'] : '',
                                'application_fee_id' => $manualRefundVia === null ? $meta['application_fee_id'] : null,
                            ];
                            $bookingAddonProxy = (object) ['payment_method' => $meta['payment_method']];

                            // Use the field's real original PI here (unblanked $meta, not the
                            // ledger-facing $transactionValue above) — a manual reimbursement still
                            // refunds money that was originally settled under that PI's real mode;
                            // only the ledger's displayed transaction_id is blanked for that case.
                            // Gated on $piKey itself being a real PI, not on $meta['payment_method']
                            // (which reports the possibly manually-overridden EFFECTIVE method —
                            // 'Cash' for a manually reimbursed CARD original would wrongly skip a
                            // lookup that should still happen). $piKey was built from the item's
                            // TRUE original payment_method (see the grouping above: only 'card' +
                            // a real transaction_id ever produces a non-'method_*' key), so it's
                            // the correct discriminant here.
                            $piRefundMode = !str_starts_with((string) $piKey, 'method_')
                                ? DB::table('transactions')->where('transaction_number', $meta['transaction_id'])->value('payment_mode')
                                : PaymentModeResolver::DESTINATION;
                            $txId = TransactionService::storeAddonsRefundInTransaction(
                                $lastBooking, $bookingAddonProxy, $transactionValue, mode: $piRefundMode
                            );
                            HistoryService::storeAddonsRefundInHistory(
                                $lastBooking, $bookingAddonProxy, $transactionValue,
                                $histItems, 'removed', end($newTransactions)['label'], $txId, $requestProxy
                            );
                        }
                    }
                }
            }

            // Log a history entry for any field being hard-deleted below WITHOUT a refund —
            // whether the whole call is Constants::ADDONS_DONT_REFUND, or specific fields were
            // deselected via $selectedFieldIds while others in the same call were refunded.
            // Previously these fields vanished with zero audit trail: no transaction row, no
            // history entry, nothing to show the removal ever happened. transactionValue's
            // price/fee stay at 0 here (no money actually moved) so this never inflates the
            // "Refund Amount" summary elsewhere, which sums real refunded amounts only — the
            // field's real price/fee still appear in the per-item $history array below purely
            // for informational display in the history timeline.
            $notRefundedFields = $bookingPaxAddons->whereNotIn('id', $refundedFieldIds);
            if ($notRefundedFields->isNotEmpty()) {
                $notRefundedBooking = BookingService::booking($bookingId);
                $notRefundedHistory = [];
                // Falls back to the booking's OWN payment method (e.g. "Pay Later") rather than
                // a hardcoded 'card' default — matters for the very first field in the loop if
                // it has no lastCharge at all (see below).
                $lastPaymentMethod = $this->resolveBookingPaymentMethodLabel($bookingId);
                foreach ($notRefundedFields as $field) {
                    // 'paid' covers Card/Cash/Invoiced; Pay Later add-ons are charged with
                    // status 'open' (see chargeAddonFields()) and never become 'paid' since
                    // nothing is collected until the booking itself is invoiced. Filtering to
                    // 'paid' only made a Pay Later field's removal here show $0.00 price/fee and
                    // fall through to the hardcoded 'card' default below instead of its real
                    // price and the booking's actual Pay Later payment method — the exact same
                    // bug already fixed for savePaxAddons()'s equivalent lookup.
                    $lastCharge = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                        ->whereIn('status', ['paid', 'open'])
                        ->where('operation', 'charge')
                        ->orderByDesc('id')
                        ->first();

                    if ($lastCharge) {
                        $lastPaymentMethod = $this->paymentMethodLabel($lastCharge->payment_method);
                        $fieldPrice = (float) $lastCharge->field_price + (float) $lastCharge->option_price;
                        $fieldFee   = (float) $lastCharge->field_fee   + (float) $lastCharge->option_fee;

                        // "Don't Refund" is a FINAL decision on this field's money, same as the
                        // Full/Ticket Refund branch above — nothing is ever owing on it again. But
                        // ADDONS_DONT_REFUND never reaches that branch (that's the whole reason this
                        // field is here, in $notRefundedFields, instead of $refundedFieldIds), so its
                        // ORIGINAL 'paid' charge row was never matched by a 'refunded' row of any
                        // kind. Left that way, refundAddonsByBookingId() — run later at cancel-booking
                        // time — has no record this charge was ever settled, and refunds it a SECOND
                        // time: paying out money the admin explicitly chose to keep when the pax was
                        // removed. Record a same-amount 'refunded' row (customer still keeps the
                        // money — nothing DISPLAYED as refunded changes; $notRefundedHistory and
                        // $zeroTransactionValue below are untouched by this) so the charge nets to
                        // exactly $0 and can never be swept up again, matching the exact convention
                        // ChangePeopleAddonService::settleAddonTransactions() already uses for its own
                        // 'dont_refund' branch. Only when 'paid' — a Pay Later field's charge row
                        // ('open', never actually collected) has nothing to net out and isn't matched
                        // by refundAddonsByBookingId()'s own 'paid'-only query anyway.
                        if ($lastCharge->status === 'paid') {
                            TourBookingAddonTransaction::create([
                                'tour_booking_id'        => $bookingId,
                                'tour_booking_addon_id'  => $field->id,
                                'payment_method'         => $manualRefundVia ?? $lastCharge->payment_method,
                                'status'                 => 'refunded',
                                'operation'              => 'refund',
                                'transaction_id'         => $lastCharge->transaction_id,
                                'application_fee_id'     => $lastCharge->application_fee_id,
                                'field_additional_fee'   => $lastCharge->field_additional_fee,
                                'option_additional_fee'  => $lastCharge->option_additional_fee,
                                'field_price'            => $lastCharge->field_price,
                                'field_fee'              => $lastCharge->field_fee,
                                'option_price'           => $lastCharge->option_price,
                                'option_fee'             => $lastCharge->option_fee,
                                'notes'                  => "Don't Refund",
                                'operation_at'           => now(),
                                'operation_by'           => Auth::id(),
                                'operation_by_user_type' => userType(),
                            ]);
                        }
                    } else {
                        // No transaction row AT ALL — not even 'open'. A genuinely new Pay Later
                        // booking's add-ons are deliberately left exactly this way at creation
                        // time (see resolveConfiguredAddonPrice()'s docblock) until a real "Make
                        // Payment" happens later. Fall back to the field/option's own configured
                        // list price so this still shows the real amount instead of $0.00.
                        $configured = $this->resolveConfiguredAddonPrice($field);
                        $fieldPrice = $configured['price'];
                        $fieldFee   = $configured['fee'];
                    }

                    // A manual Cash/Check-Wire refund override applies to the WHOLE delete
                    // operation this field is caught up in, even when this particular field
                    // itself keeps $0/"Don't Refund" (deselected, or the whole call is
                    // dont_refund) — its history entry must say so instead of always echoing the
                    // original card charge, matching the actually-refunded fields below.
                    $fieldPaymentMethod = $manualRefundVia ?? $lastPaymentMethod;
                    // Only fields that actually had a price/fee attached belong in the visible
                    // audit trail here — a non-priced field (a plain Yes/No or text question,
                    // possibly with non-priced nested children) never moved any money in the
                    // first place, so recording it as "$0.00 / Don't Refund" is just noise, not
                    // an audit trail. Matches recordZeroNetAddonHistory()'s equivalent convention
                    // for the one_time/price_per_unit flow, which only ever builds its history
                    // from priced to_charge/to_refund items.
                    if ($fieldPrice > 0 || $fieldFee > 0) {
                        $notRefundedHistory[] = [
                            'name'           => trim(implode(' — ', array_filter([$field->field_label, $field->option_label]))),
                            'price'          => $fieldPrice,
                            'fee'            => $fieldFee,
                            'payment_method' => $fieldPaymentMethod,
                            // Matches the exact wording ChangePeopleAddonService and
                            // processAddonFinancialTransactions() (above, in this same file) already
                            // use for a kept-not-refunded line item.
                            'status'         => "Don't Refund",
                        ];
                    }
                }

                // Skip the history entry entirely when every not-refunded field in this batch
                // was non-priced — nothing to show in the audit trail (see the price/fee guard
                // above). The fields are still hard-deleted below either way; this only silences
                // the otherwise-empty "$0.00 / Don't Refund" entry.
                if (!empty($notRefundedHistory)) {
                    $notRefundedProxy   = (object) ['payment_method' => $manualRefundVia ?? $lastPaymentMethod];
                    $notRefundedRequest = (object) ['description' => $description];
                    $zeroTransactionValue = [
                        'price'              => 0,
                        'fee'                => 0,
                        'transaction_id'     => null,
                        'application_fee_id' => null,
                    ];

                    // Pass the raw Constants::ADDONS_DONT_REFUND slug (not a human-readable string)
                    // as the history-level refund type — resolveRefundData() in app/helpers.php
                    // translates 'dont_refund' to "Don't Refund" for display, the same pipeline every
                    // other refund-type history entry goes through.
                    HistoryService::storeAddonsRefundInHistory(
                        $notRefundedBooking, $notRefundedProxy, $zeroTransactionValue,
                        $notRefundedHistory, 'removed', Constants::ADDONS_DONT_REFUND, null, $notRefundedRequest
                    );
                }
            }

            // Hard-delete the addon fields
            TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->when(
                    $explicitFieldIds !== null,
                    fn ($q) => $q->whereIn('id', $explicitFieldIds),
                    fn ($q) => $q->whereIn('pax_index', $ids)
                )
                ->delete();

            DB::commit();

            // Cancel the pending addon payment-link item outside the transaction so the
            // delete is already committed before recalculateRemainingPaymentLink fires.
            if ($pendingLinkItemId) {
                \App\Services\Booking\PaymentLinkService::cancelPendingAddonLinkItem($pendingLinkItemId);
            }

            // Same "keep the whole-booking Payment Link in sync" treatment as adding an add-on
            // (see updateBookingAddons()/updatePaxAddons()) — a genuinely never-paid booking's
            // link must shrink (and resend) when a priced add-on is removed too, not just grow
            // when one is added. No-ops entirely for a paid booking (snapshot's transaction check).
            $this->syncPayLaterAddonsPaymentLink($bookingId, $payLinkSnapshot);

            return true;
        } catch (CardException $ex) {
            DB::rollBack();
            Log::error($ex);
            throw new GeneralCoreException($ex);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }

    /**
     * Summary of deleteAddonsByBookingId
     * @param mixed $bookingId
     * @return bool
     */
    public function deleteAddonsByBookingId($bookingId)
    {
        DB::beginTransaction();
        try {
            TourBookingAddonField::where('tour_booking_id', $bookingId)->delete();

            DB::commit();
            return true;
        } catch (Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    /**
     * A Pay Later booking's add-on field can genuinely have NO tour_booking_addon_transactions
     * row at all yet — not even 'open' — because booking creation deliberately leaves true Pay
     * Later add-ons unsettled until a real "Make Payment" happens later (see the "no transaction
     * row yet" comment in TourBookingRepository::changePeopleBooking() and the mirror-image
     * "Source 1" logic in AddonBookingService::createAddonTransactionsFromFields(), which
     * back-fills exactly this state once the booking is actually paid). Reuses that exact same
     * technique here: read the field/option's own configured list price directly from
     * addon_fields/addon_field_options, so a removal history entry for such a field shows its
     * real price instead of $0.00 just because no transaction row exists to read from.
     *
     * Fee is deliberately 0 — matches the convention chargeAddonFields() itself already uses for
     * every genuine Pay Later 'open' row (fees are never applied before the payment method that
     * will actually collect them is known), so this isn't a shortcut, it's the same rule.
     *
     * @param object $field A tour_booking_addon_fields row (Eloquent model or plain object) with
     *        field_id, option_id, unit_type, unit.
     * @return array{price: float, fee: float}
     */
    private function resolveConfiguredAddonPrice(object $field): array
    {
        $addonField  = AddonField::find($field->field_id);
        $addonOption = $field->option_id ? AddonFieldOption::find($field->option_id) : null;

        $fieldPrice  = ($addonField && $addonField->additional_fee && $addonField->price > 0) ? (float) $addonField->price : 0.0;
        $optionPrice = ($addonOption && $addonOption->additional_fee && $addonOption->price > 0) ? (float) $addonOption->price : 0.0;

        $qty = ($field->unit_type === 'price_per_unit') ? max(1, (int) $field->unit) : 1;

        return [
            'price' => round(($fieldPrice + $optionPrice) * $qty, 2),
            'fee'   => 0.0,
        ];
    }

    /**
     * A booking's payment method ("Card"/"Cash"/"Invoiced") lives on its `transactions` row, NOT
     * on tour_bookings_master — BookingService::booking()'s plain TourBooking model has no `via`
     * column/attribute at all, so reading $booking->via always silently returns null. Used as the
     * LAST-resort fallback when an add-on field has no transaction row of its own to read a
     * payment method from (see resolveConfiguredAddonPrice()'s docblock for why that genuinely
     * happens for Pay Later).
     *
     * A GENUINELY never-paid Pay Later booking has ZERO rows in `transactions` at all — nothing
     * has ever been charged, so there's no "via" to read no matter how the query is written.
     * "Pay Later" isn't itself a real payment method (nothing has actually been paid BY any
     * method yet), so this deliberately returns '' rather than either a misleading 'Card' or a
     * "Pay Later" label masquerading as a payment method — the history entry's payment-method
     * chip (see resolveRefundData()/`_refund.blade.php`'s `$paymentType != ''` guard) simply
     * doesn't render at all when there's genuinely no payment method to show.
     *
     * @param int $bookingId
     * @return string Empty string when the booking has no primary transaction row at all.
     */
    private function resolveBookingPaymentMethodLabel(int $bookingId): string
    {
        // 'Completed' only — matches the tour-booking-info blade's own `$trans` lookup (which
        // filters to status='Completed' before deciding the booking is Pay Later vs paid).
        // Without this, a stray Failed/Refund transaction row (e.g. a Card attempt that failed
        // before the customer switched to Pay Later) would leak through as this booking's
        // "real" payment method even though the booking is genuinely unpaid/Pay Later.
        $via = Transaction::where('tour_booking_id', $bookingId)
            ->where('status', 'Completed')
            ->whereNull('charge_type')
            ->orderBy('id')
            ->value('via');

        // A booking can genuinely have a 'Completed' row with via='Pay Later' itself (the
        // arrangement, not a real charge — no money moved). The docblock above already promises
        // this method never returns "Pay Later" as if it were a real payment method; the lookup
        // just didn't actually exclude it. Same normalization as paymentMethodLabel()'s
        // 'paylater' special case just below, so both fallback paths keep agreeing.
        if ($via && str_replace(' ', '', strtolower($via)) === 'paylater') {
            return '';
        }

        return $via ?: '';
    }

    /**
     * Nicely-cased payment method label for history display, reversing
     * config('constants.payment_methods') (e.g. 'invoiced' -> 'Invoiced'; a plain ucwords() can't
     * fix 'paylater' on its own since there's no space to capitalize on, unlike 'card'/'cash'
     * which already read fine either way — moot now that 'paylater' returns '' below anyway).
     *
     * 'paylater' is deliberately blank, not "Pay Later" — a status='open' Pay Later charge means
     * nothing has actually been paid BY any method yet, so "Pay Later" would read as a real
     * payment method when it isn't one. Matches resolveBookingPaymentMethodLabel()'s same '' for
     * the "no transaction at all" case, so both fallback paths agree.
     *
     * @param string|null $raw Stored tour_booking_addon_transactions.payment_method value.
     * @return string
     */
    private function paymentMethodLabel(?string $raw): string
    {
        if (!$raw) {
            return '';
        }

        // Normalized the same way resolveBookingPaymentMethodLabel() does — a ledger row's
        // payment_method can be stored as either 'paylater' (chargeAddonFields()'s legacy
        // convention) or 'pay later' (processAddonFinancialTransactions() stores
        // strtolower($financial['payment_method']), and the frontend sends the booking's real
        // "Pay Later" string, space included). Comparing the raw, un-normalized string here
        // only ever matched the no-space form, so a ledger row written by the other convention
        // fell through to ucwords('pay later') below and displayed "Pay Later" as if it were a
        // real payment method.
        if (str_replace(' ', '', strtolower($raw)) === 'paylater') {
            return '';
        }

        $labels = array_flip(config('constants.payment_methods'));

        return $labels[strtolower($raw)] ?? ucwords($raw);
    }

    /**
     * Save pax addons for the booking [Bulk]
     *
     * @param object $request
     * @return array
     * @throws Exception
     */
    public function savePaxAddons(object $request)
    {
        // Same cancelled-booking guard as updateBookingAddons() above — this net-$0 path never
        // touches Stripe, but it still writes new/changed tour_booking_addon_fields rows against
        // a booking that's supposed to be frozen once cancelled.
        if (DB::table('tour_bookings_master')->where('id', (int) $request->booking_id)->value('status') === 'Cancelled') {
            return [
                'success' => false,
                'message' => 'This booking has been cancelled — add-ons can no longer be added or updated.',
            ];
        }

        DB::beginTransaction();
        try {
            $bookingId = $request->booking_id;
            $formId = $request->form_id;
            $paxGroups = $request->pax ?? [];

            // Look up each about-to-be-deleted field's label/last charge BEFORE
            // upsertBookingAddonFields() soft-deletes it below — this is the JS "no financial
            // action" path (net $0 delta across the whole request), most commonly a pure
            // reassignment: an add-on selection moving from one pax to another. Pool every
            // deleted item here (flat list, not keyed) so it can be matched against the added
            // items once the upserts have run — by exact (field_id, option_id) first, falling
            // back to "same field, same price" (see the matching loop below) since a
            // reassignment doesn't always keep the identical option: e.g. moving a T-Shirt from
            // Adult to Child can land on a DIFFERENT size that happens to cost the same, and
            // that must still be recognized as a real transfer, not a fresh $0 addition.
            $deletedPool = [];
            foreach ($paxGroups as $pax) {
                foreach (($pax['deleted'] ?? []) as $item) {
                    $dbId = $item['db_id'] ?? null;
                    if (!$dbId) {
                        continue;
                    }
                    $field = TourBookingAddonField::find($dbId);
                    if (!$field) {
                        continue;
                    }
                    // 'paid' covers Card/Cash/Invoiced items; Pay Later add-ons are charged
                    // with status 'open' (see chargeAddonFields()) and NEVER become 'paid' —
                    // that charge is real (it's what the customer actually owes), just not yet
                    // collected. Filtering to 'paid' only made a Pay Later booking's removed
                    // add-on invisible here (no lastCharge found), which showed $0.00/wrong
                    // payment method in the "Don't Refund" history entry below and also
                    // prevented findReassignmentMatch() from ever recognizing a Pay Later
                    // reassignment (it requires a lastCharge to confirm the match).
                    $lastCharge = TourBookingAddonTransaction::where('tour_booking_addon_id', $dbId)
                        ->whereIn('status', ['paid', 'open'])
                        ->where('operation', 'charge')
                        ->orderByDesc('id')
                        ->first();
                    $deletedPool[] = ['dbId' => $dbId, 'field' => $field, 'lastCharge' => $lastCharge];
                }
            }

            // Snapshot every 'updated' item's CURRENT (pre-mutation) selection identity, pooled
            // by field_id + selection — used below (after the upserts run) to detect a genuine
            // swap/rotation among N pax updating the SAME field within this one net-$0 request,
            // so the underlying paid ledger can follow the option to wherever it actually ended
            // up. Matching on the SELECTION ITSELF (not price) is unambiguous and generalizes to
            // any N: this method only ever runs when the whole batch already nets to $0 (see
            // saveBulkPaxAddons()/updateAddonForm()'s action==='none' branch), so if a pax's NEW
            // selection is exactly what a DIFFERENT pax's row held a moment ago, that option
            // really did move between them — a price-only match would miss a 3+-way rotation
            // whose individual deltas don't happen to pair up 1:1.
            $oldSelectionOwners = [];
            foreach ($paxGroups as $pax) {
                foreach (($pax['updated'] ?? []) as $item) {
                    $dbId = $item['db_id'] ?? null;
                    if (!$dbId) {
                        continue;
                    }
                    $existing = TourBookingAddonField::find($dbId);
                    if (!$existing) {
                        continue;
                    }
                    $key = $this->addonSelectionKey((int) $item['field_id'], $existing->option_id, $existing->value);
                    $oldSelectionOwners[$key][] = $dbId;
                }
            }

            $allFieldToDb = [];
            foreach ($paxGroups as $pax) {
                $fieldToDb = $this->upsertBookingAddonFields(
                    $bookingId,
                    $formId,
                    $pax['pax_id'],
                    $pax['pax_index'],
                    $pax['added'] ?? [],
                    $pax['updated'] ?? [],
                    $pax['deleted'] ?? [],
                    isset($pax['person_ordinal']) ? (int) $pax['person_ordinal'] : null
                );
                // array_merge() renumbers purely-integer keys (the plain field_id fallback key
                // upsertBookingAddonFields() uses when pax_index is null) instead of preserving
                // them — silently destroying/mis-pointing that entry. `+` preserves every key,
                // string or int, as-is; composite field_id_paxIndex keys already make cross-pax
                // collisions impossible, so there's no ordering ambiguity to worry about either.
                $allFieldToDb = $allFieldToDb + $fieldToDb;
            }

            $this->syncPendingAddonLinkForEditedFields(
                (int) $bookingId,
                collect($paxGroups)->flatMap(fn ($pax) => $pax['updated'] ?? [])->all(),
                null
            );

            $removedHistory = [];
            $addedHistory   = [];

            // Booking's own real payment method (blank for a genuinely never-paid Pay Later
            // booking) — used below wherever there's no specific lastCharge to read a real
            // payment method from, instead of a hardcoded 'card' default that showed "Card" for
            // a Pay Later booking's add-ons regardless of its actual payment status.
            $bookingPaymentMethod = $this->resolveBookingPaymentMethodLabel($bookingId);

            // The financial payload (already computed correctly on the frontend by
            // buildBulkAddonPayload()/buildAddonPayload(), and always sent even for a
            // net-$0 'none' action) has the real price for every 'added' item — reuse it
            // below instead of $item['paid_price'], which is always 0 for a fresh
            // selection (nothing has been charged for it yet) and made every no-reassignment
            // addition show as a free $0.00 history entry regardless of its real price.
            $financial   = is_array($request->financial ?? null) ? $request->financial : [];
            $chargeByKey = [];
            foreach (($financial['to_charge'] ?? []) as $c) {
                if (($c['reason'] ?? null) === 'added') {
                    $chargeByKey[$c['field_id'] . '_' . ($c['pax_index'] ?? '')] = (float) ($c['charge_amount'] ?? 0);
                }
            }

            foreach ($paxGroups as $pax) {
                foreach (($pax['added'] ?? []) as $item) {
                    $mapKey = $item['field_id'] . '_' . $pax['pax_index'];
                    $newDbId = $allFieldToDb[$mapKey] ?? null;
                    if (!$newDbId) {
                        continue;
                    }

                    $itemLabel = trim(implode(' — ', array_filter([
                        $this->getFieldLabel((int) $item['field_id']),
                        $item['option_label'] ?? null,
                    ])));

                    $match = $this->findReassignmentMatch($deletedPool, (int) $item['field_id'], $item['option_id'] ?? null);
                    $lastCharge = $match['lastCharge'] ?? null;

                    if ($match && $lastCharge) {
                        // Real reassignment: this exact selection was actually charged
                        // elsewhere. Repoint EVERY tour_booking_addon_transactions row for the
                        // old field onto the new field id — not just the latest charge, but any
                        // earlier adjustment/refund rows too, so the field's entire transaction
                        // history moves as one unit and nothing is left stranded on the old
                        // (now soft-deleted) field. The transaction_id (Stripe PaymentIntent)
                        // on those rows is untouched, so the money still correctly traces back
                        // to whichever real PI funded it if this pax is later genuinely
                        // refunded. No Stripe call happens here — nothing is owed right now.
                        $amountPrice = (float) $lastCharge->field_price + (float) $lastCharge->option_price;
                        $amountFee   = (float) $lastCharge->field_fee + (float) $lastCharge->option_fee;

                        TourBookingAddonTransaction::where('tour_booking_addon_id', $match['dbId'])
                            ->update(['tour_booking_addon_id' => $newDbId]);

                        $removedHistory[] = [
                            'name'           => trim(implode(' — ', array_filter([$match['field']->field_label, $match['field']->option_label]))),
                            'price'          => $amountPrice,
                            'fee'            => $amountFee,
                            'payment_method' => $this->paymentMethodLabel($lastCharge->payment_method),
                            'status'         => 'Reassigned',
                        ];
                        $addedHistory[] = [
                            'name'           => $itemLabel,
                            'price'          => $amountPrice,
                            'fee'            => $amountFee,
                            'payment_method' => $this->paymentMethodLabel($lastCharge->payment_method),
                            'status'         => 'Reassigned',
                        ];
                        continue;
                    }

                    // No matching removed item (or the matched field was never actually
                    // charged) — nothing to transfer, so mark this field "paid" with a $0
                    // transaction instead. Leaving it with NO transaction row at all makes it
                    // look unpaid/pending everywhere that checks "does this field have a paid
                    // charge transaction" (e.g. the "Passenger Add-ons" preview modal's badge).
                    TourBookingAddonTransaction::create([
                        'tour_booking_id'        => $bookingId,
                        'tour_booking_addon_id'  => $newDbId,
                        // Stored lowercase, 'paylater' rather than blank, to match
                        // paymentMethodLabel()'s canonical "no real payment method" marker — so
                        // if THIS field is looked up again later (e.g. reassigned/removed), it
                        // correctly resolves back to blank instead of falling through
                        // paymentMethodLabel()'s own `!$raw` branch to a hardcoded 'Card'.
                        'payment_method'         => $bookingPaymentMethod !== '' ? strtolower($bookingPaymentMethod) : 'paylater',
                        'status'                 => 'paid',
                        'operation'              => 'charge',
                        'transaction_id'         => null,
                        'field_price'            => 0,
                        'field_fee'              => 0,
                        'option_price'           => 0,
                        'option_fee'             => 0,
                        'notes'                  => 'No additional charge',
                        'operation_at'           => now(),
                        'operation_by'           => Auth::id(),
                        'operation_by_user_type' => userType(),
                    ]);
                    $addedHistory[] = [
                        'name'           => $itemLabel,
                        'price'          => $chargeByKey[$mapKey] ?? (float) ($item['paid_price'] ?? 0),
                        'fee'            => (float) ($item['paid_fee'] ?? 0),
                        'payment_method' => $bookingPaymentMethod,
                        'status'         => 'Added',
                    ];
                }
            }

            // Any deleted items never matched to an added item (a plain removal, not part of a
            // reassignment) — the money simply stays with the booking, kept but not refunded;
            // no transaction change, just an audit trail entry.
            if (!empty($deletedPool)) {
                $booking = BookingService::booking($bookingId);
            }
            foreach ($deletedPool as $entry) {
                $lastCharge = $entry['lastCharge'];
                if ($lastCharge) {
                    $entryPrice = (float) $lastCharge->field_price + (float) $lastCharge->option_price;
                    $entryFee   = (float) $lastCharge->field_fee   + (float) $lastCharge->option_fee;
                    $entryPaymentMethod = $this->paymentMethodLabel($lastCharge->payment_method);
                } else {
                    // No transaction row AT ALL — see resolveConfiguredAddonPrice()'s docblock:
                    // a genuinely new Pay Later booking's add-ons are deliberately left this way
                    // until a real "Make Payment" happens. Fall back to the field/option's own
                    // configured list price, and the booking's own payment method, instead of
                    // $0.00/hardcoded 'card'.
                    $configured = $this->resolveConfiguredAddonPrice($entry['field']);
                    $entryPrice = $configured['price'];
                    $entryFee   = $configured['fee'];
                    $entryPaymentMethod = $this->resolveBookingPaymentMethodLabel($bookingId);
                }
                $removedHistory[] = [
                    'name'           => trim(implode(' — ', array_filter([$entry['field']->field_label, $entry['field']->option_label]))),
                    'price'          => $entryPrice,
                    'fee'            => $entryFee,
                    'payment_method' => $entryPaymentMethod,
                    'status'         => "Don't Refund",
                ];
            }

            // pax['updated'] items (an existing field's same slot changed to a different
            // option/value — e.g. downgrading a priced option to a non-priced one, or vice
            // versa) never appear in $paxGroups[*]['added']/['deleted'] above, so without this
            // they were completely invisible in the audit trail whenever they happened to net
            // to $0 against an unrelated change elsewhere (the only way this method runs at
            // all — see saveBulkPaxAddons()/updateAddonForm()'s action==='none' branch).
            // Reuse the already-correct to_refund/to_charge entries the frontend computed for
            // these — tagged 'downgraded'/'upgraded' respectively, distinct from the
            // 'removed'/'added' entries already handled above, so nothing double-counts.
            foreach (($financial['to_refund'] ?? []) as $item) {
                if (($item['reason'] ?? null) !== 'downgraded') {
                    continue;
                }
                $removedHistory[] = [
                    'name'           => $item['label'] ?? 'Add-on',
                    'price'          => (float) ($item['credit_amount'] ?? $item['refund_amount'] ?? 0),
                    'fee'            => 0,
                    'payment_method' => $this->resolveBookingPaymentMethodLabel($bookingId),
                    'status'         => "Don't Refund",
                ];
            }
            foreach (($financial['to_charge'] ?? []) as $item) {
                if (($item['reason'] ?? null) !== 'upgraded') {
                    continue;
                }
                $addedHistory[] = [
                    'name'           => $item['label'] ?? 'Add-on',
                    'price'          => (float) ($item['charge_amount'] ?? 0),
                    'fee'            => 0,
                    'payment_method' => $bookingPaymentMethod,
                    'status'         => 'Added',
                ];
            }

            // Repoint the underlying paid-transaction ledger between pax rows wherever a pax's
            // NEW selection is exactly what a DIFFERENT pax's row (also 'updated' in this same
            // request) held a moment ago ($oldSelectionOwners, snapshotted above before the
            // upserts ran) — i.e. that option genuinely moved from one row to another. "Don't
            // Refund" on a net-$0 batch only means "don't move CASH for this operation" — without
            // repointing, each row's `paid_price` stays pinned to whichever option was ORIGINALLY
            // charged to that specific DB row, permanently decoupled from what the row now
            // displays. A LATER, perfectly genuine downgrade on a row that inherited a pricier
            // option this way then computes its refund against that stale paid amount and wrongly
            // finds "nothing to refund" — even though the booking's aggregate nominal total has
            // genuinely dropped and real money IS owed back.
            //
            // Handles any N: a plain 2-pax swap, several independent swaps in one batch, or a
            // full rotation among 3+ pax (A inherits what C had, B inherits what A had, C
            // inherits what B had) — every move is executed via a per-source sentinel FIRST, so a
            // row that is simultaneously a source for one move and a destination for another
            // never has its transactions overwritten before they're read.
            $moves = []; // destDbId => sourceDbId
            foreach ($paxGroups as $pax) {
                foreach (($pax['updated'] ?? []) as $item) {
                    $dbId = $item['db_id'] ?? null;
                    if (!$dbId) {
                        continue;
                    }
                    $newKey = $this->addonSelectionKey((int) $item['field_id'], $item['option_id'] ?? null, $item['value'] ?? null);
                    if (empty($oldSelectionOwners[$newKey])) {
                        continue;
                    }
                    foreach ($oldSelectionOwners[$newKey] as $idx => $candidateDbId) {
                        if ($candidateDbId === $dbId) {
                            continue; // already holding this exact selection — nothing moved
                        }
                        $moves[$dbId] = $candidateDbId;
                        unset($oldSelectionOwners[$newKey][$idx]);
                        break;
                    }
                }
            }

            if (!empty($moves)) {
                // `tour_booking_addon_id` is `bigint unsigned`, so a negative sentinel silently
                // clamps to 0 instead of storing as given (cost a failed test to learn) — use a
                // value far outside any real auto-increment id instead, still safely within PHP's
                // own signed-int range so it reads back as the same int, not a string.
                $sentinelFor = [];
                foreach (array_unique(array_values($moves)) as $sourceDbId) {
                    $sentinel = PHP_INT_MAX - $sourceDbId;
                    $sentinelFor[$sourceDbId] = $sentinel;
                    DB::table('tour_booking_addon_transactions')
                        ->where('tour_booking_addon_id', $sourceDbId)
                        ->update(['tour_booking_addon_id' => $sentinel]);
                }
                foreach ($moves as $destDbId => $sourceDbId) {
                    DB::table('tour_booking_addon_transactions')
                        ->where('tour_booking_addon_id', $sentinelFor[$sourceDbId])
                        ->update(['tour_booking_addon_id' => $destDbId]);
                }
            }

            if (!empty($removedHistory) || !empty($addedHistory)) {
                $booking = $booking ?? BookingService::booking($bookingId);
                $bookingAddonProxy = (object) ['payment_method' => $removedHistory[0]['payment_method'] ?? $bookingPaymentMethod];
                $requestProxy = (object) ['description' => $request->description ?? null];
                $zeroTransactionValue = [
                    'price'              => 0,
                    'fee'                => 0,
                    'transaction_id'     => null,
                    'application_fee_id' => null,
                ];

                HistoryService::storeAddonsRefundInHistory(
                    $booking, $bookingAddonProxy, $zeroTransactionValue,
                    $removedHistory, 'removed', Constants::ADDONS_DONT_REFUND, null, $requestProxy,
                    $addedHistory
                );
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pax addons saved successfully'
            ];
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('Error saving pax addons: ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * Save bulk pax addon changes WITH financial processing (charge or refund).
     * Upserts each pax's added/updated/deleted fields, then processes the net
     * financial transaction in one DB commit.
     */
    public function updatePaxAddons(object $request): array
    {
        // Same cancelled-booking guard as updateBookingAddons()/savePaxAddons() above.
        if (DB::table('tour_bookings_master')->where('id', (int) $request->booking_id)->value('status') === 'Cancelled') {
            return [
                'success' => false,
                'message' => 'This booking has been cancelled — add-ons can no longer be added or updated.',
            ];
        }

        DB::beginTransaction();
        try {
            $bookingId = (int) $request->booking_id;
            $formId    = $request->form_id;
            $paxGroups = $request->pax ?? [];
            $financial = $request->financial;
            if (is_string($financial)) {
                $financial = json_decode($financial, true);
            }
            $payLinkSnapshot = $this->snapshotPayLaterAddonsForPaymentLink($bookingId);

            $allFieldToDb = [];

            foreach ($paxGroups as $pax) {
                $fieldToDb = $this->upsertBookingAddonFields(
                    $bookingId,
                    $formId,
                    $pax['pax_id'],
                    $pax['pax_index'],
                    $pax['added']   ?? [],
                    $pax['updated'] ?? [],
                    $pax['deleted'] ?? [],
                    isset($pax['person_ordinal']) ? (int) $pax['person_ordinal'] : null
                );
                // Accumulate field_id → new db_id mappings across all pax. array_merge() would
                // renumber purely-integer keys (the plain field_id fallback key used when
                // pax_index is null) instead of preserving them, silently destroying/mis-pointing
                // that entry — `+` preserves every key, string or int, as-is. Composite
                // field_id_paxIndex keys already make cross-pax collisions impossible, so there's
                // no ordering ambiguity to worry about either.
                $allFieldToDb = $allFieldToDb + $fieldToDb;
            }

            $this->syncPendingAddonLinkForEditedFields(
                $bookingId,
                collect($paxGroups)->flatMap(fn ($pax) => $pax['updated'] ?? [])->all(),
                $financial
            );

            if (is_array($financial) && in_array($financial['action'] ?? '', ['charge', 'refund'])) {
                $resolvedPI = $this->processAddonFinancialTransactions($bookingId, $financial, $allFieldToDb);

                // Same combined-PI finalisation as updateBookingAddons() — placed here (not inside
                // processAddonFinancialTransactions) to prevent infinite recursion via AddonItemHandler.
                if (($financial['action'] ?? '') === 'charge' && strtolower($financial['payment_method'] ?? '') === 'card' && $resolvedPI) {
                    $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
                    if ($groupId) {
                        $pendingLink = TourBookingPaymentLink::where('order_id', $groupId)
                            ->where('status', 'Unpaid')
                            ->where('section', 'remaining_payment')
                            ->first();
                        if ($pendingLink) {
                            try {
                                RemainingPaymentService::finalizeItems($pendingLink, $resolvedPI, $bookingId);
                            } catch (\Exception $e) {
                                Log::error('RemainingPaymentService finalizeItems failed after bulk pax addon card charge: ' . $e->getMessage());
                            }
                        } else {
                            $this->settleLumpSumLinkForCardCharge($bookingId, $groupId, $resolvedPI);
                        }
                    }
                }
            }

            $this->syncPayLaterAddonsPaymentLink($bookingId, $payLinkSnapshot);

            DB::commit();
            return ['success' => true];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating pax addons: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Pax addon update failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Recursively insert pax addon fields
     *
     * @param int $bookingId
     * @param int $formId
     * @param int $paxId
     * @param string $paxIndex
     * @param int $personOrdinal
     * @param array $addons
     * @param int|null $parentDbId
     * @return void
     */
    protected function insertPaxAddonFields($bookingId, $formId, $paxId, $paxIndex, $personOrdinal, $addons, $parentDbId = null)
    {
        foreach ($addons as $addon) {
            $addonField = new TourBookingAddonField();
            $addonField->tour_booking_id = $bookingId;
            $addonField->form_id = $formId;
            $addonField->field_id = $addon['field_id'];
            $addonField->field_label = $this->getFieldLabel($addon['field_id']);
            $addonField->option_id = $addon['option_id'] ?? null;
            $addonField->option_label = $addon['option_label'] ?? null;
            $addonField->rule_id = $addon['rule_id'] ?? null;
            $addonField->parent_id = $parentDbId;
            $addonField->value = $addon['value'] ?? null;
            $addonField->pax_id = $paxId;
            $addonField->pax_index = $paxIndex;
            $addonField->person_ordinal = $personOrdinal;
            $addonField->unit_type = $addon['unit_type'] ?? 'price_per_pax';
            $addonField->unit = 1;
            $addonField->applied_rule_snapshot = $addon['applied_rule_snapshot'] ?? null;
            $addonField->operation_at = now();
            $addonField->operation_by = Auth::id();
            $addonField->operation_by_user_type = session('userType', 'Admin');
            $addonField->save();

            // Recursively insert children
            if (!empty($addon['children'])) {
                $this->insertPaxAddonFields($bookingId, $formId, $paxId, $paxIndex, $personOrdinal, $addon['children'], $addonField->id);
            }
        }
    }

    /**
     * Charge addon field transactions for a booking (new system).
     *
     * Handles the payment (items) and refund (refund_items) sides in one request,
     * matching the pattern of the legacy upseartBookingAddons.
     *
     * Payment methods: card / cash / paylater
     * Unit types:
     *   one_time       → multiplier = 1
     *   price_per_unit → multiplier = field->unit (user-entered quantity)
     *   price_per_pax  → multiplier = 1 (each pax already has its own row)
     *
     * Expected request fields for charge:
     *   booking_id, payment_method, payment_intent_id (card only),
     *   items[]{tour_booking_addon_field_id, field_price, field_fee,
     *           field_additional_fee, option_price, option_fee, option_additional_fee}
     *
     * Expected request fields for refund:
     *   refund_type, description,
     *   refund_items[]{tour_booking_addon_transaction_id, partial_refund_value}
     *
     * @param object $request
     * @return array
     * @throws GeneralCoreException
     */
    public function chargeAddonFields(object $request): array
    {
        DB::beginTransaction();
        try {
            // -------------------------------------------------------
            // PAYMENT
            // -------------------------------------------------------
            if (!empty($request->items)) {
                $bookingId     = $request->booking_id;
                $booking       = BookingService::booking($bookingId);
                $paymentMethod = config('constants.payment_methods.' . $request->payment_method);
                $isCashFees    = cashFees();
                $status        = $paymentMethod === 'paylater' ? 'open' : 'paid';
                $paymentIntent = null;
                $piId          = null;
                $appFeeId      = null;

                if ($paymentMethod === 'card') {
                    $paymentIntent = $this->resolveCardPaymentIntent($request, $booking);
                    $piId          = $paymentIntent->id ?? null;
                    $appFeeId      = $paymentIntent->charges->data[0]->application_fee ?? null;
                }

                $applyFees   = $paymentMethod === 'card' || ($paymentMethod === 'cash' && $isCashFees === '1');
                $totalPrice  = 0;
                $totalFee    = 0;
                $historyItems = [];

                foreach ($request->items as $item) {
                    $addonField = TourBookingAddonField::findOrFail($item['tour_booking_addon_field_id']);

                    $multiplier = $addonField->unit_type === 'price_per_unit'
                        ? max(1, (int) ($addonField->unit ?? 1))
                        : 1;

                    $fieldPrice  = round(floatval($item['field_price']  ?? 0) * $multiplier, 2);
                    $optionPrice = round(floatval($item['option_price'] ?? 0) * $multiplier, 2);
                    $fieldFee    = $applyFees ? round(floatval($item['field_fee']  ?? 0) * $multiplier, 2) : 0;
                    $optionFee   = $applyFees ? round(floatval($item['option_fee'] ?? 0) * $multiplier, 2) : 0;

                    TourBookingAddonTransaction::create([
                        'tour_booking_id'        => $bookingId,
                        'tour_booking_addon_id'  => $addonField->id,
                        'payment_method'         => $paymentMethod,
                        'status'                 => $status,
                        'operation'              => 'charge',
                        'transaction_id'         => $piId,
                        'application_fee_id'     => $appFeeId,
                        'field_additional_fee'   => (bool) ($item['field_additional_fee']  ?? false),
                        'option_additional_fee'  => (bool) ($item['option_additional_fee'] ?? false),
                        'field_price'            => $fieldPrice,
                        'field_fee'              => $fieldFee,
                        'option_price'           => $optionPrice,
                        'option_fee'             => $optionFee,
                        'operation_at'           => now(),
                        'operation_by'           => Auth::id(),
                        'operation_by_user_type' => userType(),
                    ]);

                    $rowPrice = $fieldPrice + $optionPrice;
                    $rowFee   = $fieldFee   + $optionFee;
                    $totalPrice += $rowPrice;
                    $totalFee   += $rowFee;

                    $historyItems[] = [
                        'name'           => trim(implode(' — ', array_filter([$addonField->field_label, $addonField->option_label]))),
                        'price'          => $rowPrice,
                        'fee'            => $rowFee,
                        'payment_method' => $paymentMethod,
                        'status'         => $status,
                    ];
                }

                if (!empty($historyItems) && $status !== 'open') {
                    // $paymentIntent is only ever populated for a genuine card charge (see
                    // resolveCardPaymentIntent() above, gated on paymentMethod === 'card') —
                    // non-card methods never touch Stripe, so DESTINATION (the column's own
                    // default) is the correct, inert value for them.
                    $chargeMode = $paymentIntent
                        ? PaymentModeInference::fromStripeObject($paymentIntent)
                        : PaymentModeResolver::DESTINATION;
                    $txId = TransactionService::storeAddonsPaymentInTransaction(
                        $booking, $paymentMethod, $totalPrice, $totalFee, $paymentIntent, mode: $chargeMode
                    );
                    HistoryService::storeAddonsPaymentInHistory(
                        $booking, $historyItems, 'added', $paymentIntent, $txId, $status, $totalPrice, $totalFee
                    );
                }
            }

            // -------------------------------------------------------
            // REFUND
            // -------------------------------------------------------
            if (!empty($request->refund_items)) {
                $refundType     = $request->refund_type;
                $refundsByPi    = [];
                $newTransactions = [];
                $lastBooking    = null;

                foreach ($request->refund_items as $item) {
                    $txn     = TourBookingAddonTransaction::findOrFail($item['tour_booking_addon_transaction_id']);
                    // addonField is a SoftDeletes-scoped belongsTo, so it comes back null for a
                    // transaction whose addon field was later soft-deleted (e.g. by a pax removal) —
                    // ->tour_booking_id on that null crashed this refund with "Attempt to read
                    // property on null". Fall back to the transaction row's own tour_booking_id
                    // column, same as the refund-record creation below already does.
                    $booking = BookingService::booking($txn->addonField->tour_booking_id ?? $txn->tour_booking_id);
                    $lastBooking = $booking;

                    $origFieldPrice  = floatval($txn->field_price);
                    $origFieldFee    = floatval($txn->field_fee);
                    $origOptionPrice = floatval($txn->option_price);
                    $origOptionFee   = floatval($txn->option_fee);
                    $origTotalPrice  = $origFieldPrice + $origOptionPrice;
                    $origTotalFee    = $origFieldFee   + $origOptionFee;

                    switch ($refundType) {
                        case Constants::ADDONS_TICKET_REFUND:
                            // Refund price only, waive fees
                            $refFieldPrice  = $origFieldPrice;
                            $refOptionPrice = $origOptionPrice;
                            $refFieldFee    = 0;
                            $refOptionFee   = 0;
                            $refundLabel    = 'Full Refund';
                            break;

                        case Constants::ADDONS_PARTIAL_REFUND:
                            // Apply partial amount to price first; overflow spills into fee
                            $partial        = floatval($item['partial_refund_value'] ?? 0);
                            $refFieldPrice  = min($partial, $origTotalPrice);
                            $overflow       = max(0, $partial - $origTotalPrice);
                            $refFieldFee    = min($overflow, $origTotalFee);
                            $refOptionPrice = 0;
                            $refOptionFee   = 0;
                            $refundLabel    = 'Partial Refund';
                            break;

                        case Constants::ADDONS_FULL_REFUND:
                            // Refund everything: price + fees
                            $refFieldPrice  = $origFieldPrice;
                            $refOptionPrice = $origOptionPrice;
                            $refFieldFee    = $origFieldFee;
                            $refOptionFee   = $origOptionFee;
                            $refundLabel    = '100 % Refund';
                            break;

                        case Constants::ADDONS_DONT_REFUND:
                            $refFieldPrice  = 0;
                            $refOptionPrice = 0;
                            $refFieldFee    = 0;
                            $refOptionFee   = 0;
                            $refundLabel    = "Don't Refund";
                            break;

                        default:
                            continue 2;
                    }

                    $refTotal = $refFieldPrice + $refOptionPrice + $refFieldFee + $refOptionFee;

                    // Group by Stripe PI so we make one Stripe API call per intent
                    if ($txn->status !== 'open' && $txn->payment_method === 'card' && $txn->transaction_id) {
                        $pi = $txn->transaction_id;
                        if (!isset($refundsByPi[$pi])) {
                            $refundsByPi[$pi] = [
                                'total'              => 0,
                                'transaction_id'     => $txn->transaction_id,
                                'application_fee_id' => $txn->application_fee_id,
                                'is_full_refund'     => false,
                            ];
                        }
                        $refundsByPi[$pi]['total'] += $refTotal;
                        if ($refundType === Constants::ADDONS_FULL_REFUND) {
                            $refundsByPi[$pi]['is_full_refund'] = true;
                        }
                    }

                    $newTransactions[] = [
                        'txn'          => $txn,
                        'field_price'  => $refFieldPrice,
                        'field_fee'    => $refFieldFee,
                        'option_price' => $refOptionPrice,
                        'option_fee'   => $refOptionFee,
                        'label'        => $refundLabel,
                    ];
                }

                // Execute Stripe refunds (one API call per PI)
                foreach ($refundsByPi as $piId => $data) {
                    if ($data['total'] > 0) {
                        $refundArray = [
                            'payment_intent'   => $piId,
                            'amount'           => intval(round($data['total'] * 100)),
                            'reverse_transfer' => true,
                        ];
                        if ($data['is_full_refund']) {
                            $refundArray['refund_application_fee'] = true;
                        }
                        $piPaymentMode = DB::table('transactions')->where('transaction_number', $piId)->value('payment_mode');
                        app(PaymentGatewayInterface::class)->refundPayment($refundArray, $piPaymentMode);
                    }
                }

                // Create refund transaction records and history — per-PaymentIntent, NOT one
                // combined total applied to every PI (see deletePaxAddons()'s equivalent comment).
                $historyByPi = [];
                $piMeta      = [];

                foreach ($newTransactions as $data) {
                    $txn = $data['txn'];

                    TourBookingAddonTransaction::create([
                        'tour_booking_id'        => $txn->addonField->tour_booking_id ?? $txn->tour_booking_id,
                        'tour_booking_addon_id'  => $txn->tour_booking_addon_id,
                        'payment_method'         => $txn->payment_method,
                        'status'                 => 'refunded',
                        'operation'              => 'refund',
                        'transaction_id'         => $txn->transaction_id,
                        'application_fee_id'     => $txn->application_fee_id,
                        'field_additional_fee'   => $txn->field_additional_fee,
                        'option_additional_fee'  => $txn->option_additional_fee,
                        'field_price'            => $data['field_price'],
                        'field_fee'              => $data['field_fee'],
                        'option_price'           => $data['option_price'],
                        'option_fee'             => $data['option_fee'],
                        'notes'                  => $request->description ?? null,
                        'operation_at'           => now(),
                        'operation_by'           => Auth::id(),
                        'operation_by_user_type' => userType(),
                    ]);

                    $rowPrice = $data['field_price'] + $data['option_price'];
                    $rowFee   = $data['field_fee']   + $data['option_fee'];

                    // Card rows share one real Stripe PI, so grouping by transaction_id already
                    // collapses them into a single history/transaction row. Cash/Check-Wire rows
                    // have no PI at all — falling back to the addon field id kept every field in
                    // its own group, writing N separate refund history entries instead of one
                    // combined entry (the behavior card already has). Group everything without a
                    // real PI by payment method instead, same "one settlement action, one entry"
                    // rule refundAddonsByBookingId() already follows.
                    $piKey = ($txn->payment_method === 'card' && $txn->transaction_id)
                        ? $txn->transaction_id
                        : ('method_' . ($txn->payment_method ?? 'unknown'));
                    $historyByPi[$piKey][] = [
                        'name'           => trim(implode(' — ', array_filter([$txn->addonField->field_label ?? null, $txn->addonField->option_label ?? null]))),
                        'price'          => $rowPrice,
                        'fee'            => $rowFee,
                        'payment_method' => $txn->payment_method,
                        'status'         => $data['label'],
                    ];

                    if (!isset($piMeta[$piKey])) {
                        $piMeta[$piKey] = [
                            'price'              => 0,
                            'fee'                => 0,
                            'transaction_id'     => $txn->transaction_id,
                            'application_fee_id' => $txn->application_fee_id,
                            'payment_method'     => $txn->payment_method,
                        ];
                    }
                    $piMeta[$piKey]['price'] += $rowPrice;
                    $piMeta[$piKey]['fee']   += $rowFee;
                }

                if ($lastBooking && !empty($historyByPi)) {
                    foreach ($historyByPi as $piKey => $histItems) {
                        $meta = $piMeta[$piKey];
                        $transactionValue = [
                            'price'              => $meta['price'],
                            'fee'                => $meta['fee'],
                            'transaction_id'     => $meta['transaction_id'],
                            'application_fee_id' => $meta['application_fee_id'],
                        ];
                        $bookingAddonProxy = (object) ['payment_method' => $meta['payment_method']];

                        // Gated on $piKey itself being a real PI (only 'card' + a real
                        // transaction_id ever produces a non-'method_*' key, per the grouping
                        // above), not on $meta['payment_method'] alone — kept consistent with the
                        // other refund sites in this file that also thread a manual-override
                        // effective method through $meta['payment_method'].
                        $piRefundMode = !str_starts_with((string) $piKey, 'method_')
                            ? DB::table('transactions')->where('transaction_number', $meta['transaction_id'])->value('payment_mode')
                            : PaymentModeResolver::DESTINATION;
                        $txId = TransactionService::storeAddonsRefundInTransaction(
                            $lastBooking, $bookingAddonProxy, $transactionValue, mode: $piRefundMode
                        );
                        HistoryService::storeAddonsRefundInHistory(
                            $lastBooking, $bookingAddonProxy, $transactionValue,
                            $histItems, 'removed', end($newTransactions)['label'], $txId, $request
                        );
                    }
                }
            }

            DB::commit();
            return ['success' => true];

        } catch (CardException $e) {
            DB::rollBack();
            Log::error($e);
            throw new GeneralCoreException($e);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw new GeneralCoreException($e);
        }
    }

    /**
     * Refund all paid add-on transactions for a booking (called from cancel-booking services).
     *
     * Mirrors the refund branch of chargeAddonFields() but operates on all paid charge
     * transactions for a booking rather than on a caller-supplied item list.
     *
     * @param int         $tourBookingId
     * @param string      $refundType   One of Constants::ADDONS_TICKET_REFUND, ADDONS_FULL_REFUND, ADDONS_DONT_REFUND
     * @param string|null $description
     * @param float|null  $maxAmount
     * @param string|null $manualRefundVia  Set when the admin is manually reimbursing (Cash/Check-Wire)
     *                                      instead of an actual Stripe refund — same "is_cash" override
     *                                      the tour-side cancel refund already respects. Skips the real
     *                                      gateway call for Card-paid add-ons and tags the ledger/history
     *                                      with this method instead of 'card'. Mirrors deletePaxAddons().
     */
    public function refundAddonsByBookingId(int $tourBookingId, string $refundType, ?string $description = null, ?float $maxAmount = null, ?string $manualRefundVia = null): void
    {
        // Scoped directly by the transaction's OWN tour_booking_id, not the field's current one
        // (whereHas('addonField', ...) would match on that instead) — Split Booking can move a
        // field to a different booking, leaving its pre-split 'paid' charge row still tagged to
        // the OLD booking. Without this, cancelling the NEW booking would refund that old charge
        // a second time (it's already been settled via the split's own Refund/Payment pair).
        $paidTransactions = TourBookingAddonTransaction::where('tour_booking_id', $tourBookingId)
            ->where('status', 'paid')
            ->where('operation', 'charge')
            // withTrashed() — addonField is a SoftDeletes-scoped belongsTo, so it comes back null
            // for a transaction whose field was already soft-deleted (e.g. by an earlier pax
            // removal that left this exact charge unrefunded at the time). Without this, cancelling
            // the booking later still correctly refunds that charge (the amount is read straight off
            // the transaction row, not the field), but the history/ledger line item for it renders
            // with a blank name — no way to tell which add-on the refund was even for.
            ->with(['addonField' => fn ($q) => $q->withTrashed()])
            ->get();

        if ($paidTransactions->isEmpty()) {
            return;
        }

        // Net out any refund already issued against these SAME fields (e.g. a pax-removal
        // refund earlier in this booking's life, which only ever inserts a NEW 'refund' row —
        // it never updates the original 'charge' row's status away from 'paid'). Without this,
        // an already-refunded field's ORIGINAL charge row still matched the query above at its
        // full face value, so cancelling a booking that had earlier had one pax's paid add-on
        // refunded via pax removal tried to refund THAT money a second time on top of what the
        // remaining pax's add-ons genuinely still owe — inflating the requested Stripe refund
        // past what the PaymentIntent actually has left. Stripe then rejects the whole
        // cancellation ("refund amount is greater than the unrefunded amount on charge"), and
        // since that happens AFTER the tour-side refund has already been committed elsewhere in
        // the cancel flow, the booking was left half-cancelled: pax/ticket refunded, add-ons not.
        //
        // Also carries 'zero_refund_max_id' per field (see computeNetAddonTransactions()) — the id
        // of the highest-id "Don't Refund" settlement row (all four amount columns at 0) against
        // that field, if any. That signature is written both by historical data (pre-dating the
        // fix that made other settlement paths record the real amount instead) and by
        // chargeAddonFields()'s still-live Don't-Refund branch, and — unlike the trashed()-gated
        // check below — isn't limited to an already-removed pax. Compared by id below (not just
        // "does one exist for this field"), so it only closes out charges that existed AT OR
        // BEFORE that settlement — a genuinely new charge added to the same field afterwards
        // (e.g. a later upgrade) is a separate, still-owed event and isn't swallowed by it.
        $fieldIdsInScope = $paidTransactions->pluck('tour_booking_addon_id')->unique()->all();
        $netByFieldId = $this->computeNetAddonTransactions($fieldIdsInScope, $tourBookingId);

        DB::beginTransaction();
        try {
            $refundsByPi     = [];
            $newTransactions = [];
            $lastBooking     = null;

            // For partial refund with a budget cap, track remaining allowance across iterations.
            $maxBudget = ($refundType === Constants::ADDONS_PARTIAL_REFUND && $maxAmount !== null)
                ? round($maxAmount, 2)
                : PHP_FLOAT_MAX;

            foreach ($paidTransactions as $txn) {
                // Read tour_booking_id off the transaction row itself, not addonField->tour_booking_id
                // — addonField is a SoftDeletes-scoped belongsTo, so it comes back null for a
                // transaction whose addon field was later soft-deleted (e.g. by a pax removal), and
                // ->tour_booking_id on that null crashed the whole cancellation with "Attempt to read
                // property on null" even though the refund itself has nothing to do with the field
                // still existing. tour_booking_addon_transactions.tour_booking_id was added
                // specifically to make orphaned rows like this queryable/attributable by booking (see
                // its migration), and every row here already matched it via the query above, so it's
                // always the same value and never null.
                $booking     = BookingService::booking($txn->tour_booking_id);
                $lastBooking = $booking;

                $net = $netByFieldId[$txn->tour_booking_addon_id] ?? null;
                $origFieldPrice  = $net ? max(0.0, (float) $net['field_price'])  : floatval($txn->field_price);
                $origFieldFee    = $net ? max(0.0, (float) $net['field_fee'])    : floatval($txn->field_fee);
                $origOptionPrice = $net ? max(0.0, (float) $net['option_price']) : floatval($txn->option_price);
                $origOptionFee   = $net ? max(0.0, (float) $net['option_fee'])   : floatval($txn->option_fee);

                // A field whose pax has ALREADY been removed (addonField soft-deleted) AND already
                // had SOME settlement decision recorded against it at removal time (Ticket Refund /
                // 100% Refund / Don't Refund — any of them leaves at least one 'refunded' row here)
                // is closed for good, no matter what the net computation above still shows as
                // technically outstanding. Ticket Refund in particular deliberately leaves its FEE
                // portion unmatched (kept, but not marked "refunded" in the ledger) so a direct
                // edit to that SAME item can still offer a later upgrade to a 100% refund — but
                // cancelling the WHOLE booking for an unrelated reason is not that edit, and must
                // never re-open and pay out a fee an already-removed pax's admin already decided to
                // keep. Without this, a pax removed via "Ticket Refund" (whose TICKET-side fee
                // stays kept forever, since that pax's ticket record is simply gone) had their
                // ADD-ON's fee swept up and refunded anyway at cancel time — inconsistent with the
                // final, unrecoverable decision already made on the ticket side of that same pax.
                if ($txn->addonField && $txn->addonField->trashed() && $net && !empty($net['has_refund'])) {
                    continue;
                }

                // "Don't Refund" settlement (see 'zero_refund_max_id' comment above) — closed for
                // good regardless of whether this field's pax was ever removed. Gated by id so it
                // only swallows charges that predate the settlement; a later charge on this same
                // field id (e.g. a subsequent upgrade) is a separate, still-owed event.
                if ($net && !empty($net['zero_refund_max_id']) && $txn->id <= $net['zero_refund_max_id']) {
                    continue;
                }

                // Already fully settled by a prior refund (e.g. this exact field was refunded
                // when its pax was removed earlier) — nothing left owing on it, so don't refund
                // it again or write a redundant zero-amount ledger row for it.
                if (round($origFieldPrice + $origFieldFee + $origOptionPrice + $origOptionFee, 2) <= 0.0) {
                    continue;
                }

                switch ($refundType) {
                    case Constants::ADDONS_TICKET_REFUND:
                        $refFieldPrice  = $origFieldPrice;
                        $refOptionPrice = $origOptionPrice;
                        $refFieldFee    = 0;
                        $refOptionFee   = 0;
                        $refundLabel    = 'Full Refund';
                        break;

                    case Constants::ADDONS_FULL_REFUND:
                        $refFieldPrice  = $origFieldPrice;
                        $refOptionPrice = $origOptionPrice;
                        $refFieldFee    = $origFieldFee;
                        $refOptionFee   = $origOptionFee;
                        $refundLabel    = '100 % Refund';
                        break;

                    case Constants::ADDONS_DONT_REFUND:
                        $refFieldPrice  = 0;
                        $refOptionPrice = 0;
                        $refFieldFee    = 0;
                        $refOptionFee   = 0;
                        $refundLabel    = "Don't Refund";
                        break;

                    case Constants::ADDONS_PARTIAL_REFUND:
                        $txnPrincipal = $origFieldPrice + $origOptionPrice;
                        if ($txnPrincipal <= 0 || $maxBudget <= 0.001) {
                            continue 2;
                        }
                        $toRefund       = round(min($txnPrincipal, $maxBudget), 2);
                        $proportion     = $toRefund / $txnPrincipal;
                        $refFieldPrice  = round($origFieldPrice * $proportion, 2);
                        $refOptionPrice = round($toRefund - $refFieldPrice, 2);
                        $refFieldFee    = 0;
                        $refOptionFee   = 0;
                        $refundLabel    = 'Partial Refund';
                        $maxBudget      = round($maxBudget - $toRefund, 2);
                        break;

                    default:
                        continue 2;
                }

                $refTotal = $refFieldPrice + $refOptionPrice + $refFieldFee + $refOptionFee;

                if ($txn->status !== 'open' && $txn->payment_method === 'card' && $txn->transaction_id) {
                    $pi = $txn->transaction_id;
                    if (!isset($refundsByPi[$pi])) {
                        $refundsByPi[$pi] = [
                            'total'              => 0,
                            'transaction_id'     => $txn->transaction_id,
                            'application_fee_id' => $txn->application_fee_id,
                            'is_full_refund'     => false,
                        ];
                    }
                    $refundsByPi[$pi]['total'] += $refTotal;
                    if ($refundType === Constants::ADDONS_FULL_REFUND) {
                        $refundsByPi[$pi]['is_full_refund'] = true;
                    }
                }

                $newTransactions[] = [
                    'txn'          => $txn,
                    'field_price'  => $refFieldPrice,
                    'field_fee'    => $refFieldFee,
                    'option_price' => $refOptionPrice,
                    'option_fee'   => $refOptionFee,
                    'label'        => $refundLabel,
                ];
            }

            // Execute Stripe refunds (one API call per PI) — skipped entirely when the admin chose
            // to manually reimburse by Cash/Check-Wire instead ($manualRefundVia set); the ledger
            // still records the refund below, just tagged with that method instead of a real charge.
            foreach ($refundsByPi as $piId => $data) {
                if ($data['total'] > 0 && $manualRefundVia === null) {
                    $refundArray = [
                        'payment_intent'   => $piId,
                        'amount'           => intval(round($data['total'] * 100)),
                        'reverse_transfer' => true,
                    ];
                    if ($data['is_full_refund']) {
                        $refundArray['refund_application_fee'] = true;
                    }
                    $piPaymentMode = DB::table('transactions')->where('transaction_number', $piId)->value('payment_mode');
                    app(PaymentGatewayInterface::class)->refundPayment($refundArray, $piPaymentMode);
                }
            }

            // Create refund transaction records and history — per-PaymentIntent, NOT one combined
            // total applied to every PI (see deletePaxAddons()'s equivalent comment).
            $historyByPi = [];
            $piMeta      = [];

            foreach ($newTransactions as $data) {
                $txn = $data['txn'];

                // How THIS refund actually moved, for the ledger/history — the field's ORIGINAL
                // charge stays untouched on its own charge row; only this refund event is tagged as
                // the admin's manual Cash/Check-Wire reimbursement when applicable.
                $effectivePaymentMethod = $manualRefundVia ?? $txn->payment_method;

                TourBookingAddonTransaction::create([
                    'tour_booking_id'        => $tourBookingId,
                    'tour_booking_addon_id'  => $txn->tour_booking_addon_id,
                    'payment_method'         => $effectivePaymentMethod,
                    'status'                 => 'refunded',
                    'operation'              => 'refund',
                    'transaction_id'         => $txn->transaction_id,
                    'application_fee_id'     => $txn->application_fee_id,
                    'field_additional_fee'   => $txn->field_additional_fee,
                    'option_additional_fee'  => $txn->option_additional_fee,
                    'field_price'            => $data['field_price'],
                    'field_fee'              => $data['field_fee'],
                    'option_price'           => $data['option_price'],
                    'option_fee'             => $data['option_fee'],
                    'notes'                  => $description,
                    'operation_at'           => now(),
                    'operation_by'           => Auth::id(),
                    'operation_by_user_type' => userType(),
                ]);

                $rowPrice = $data['field_price'] + $data['option_price'];
                $rowFee   = $data['field_fee']   + $data['option_fee'];

                // Card rows share one real Stripe PI, so grouping by transaction_id already
                // collapses them into a single history/transaction row. Cash/Check-Wire rows have
                // no PI at all — falling back to the addon field id kept every field in its own
                // group, so cancelling N cash-paid add-ons together wrote N separate refund history
                // entries instead of one combined entry (the behavior card and the original charge
                // both already have). Group everything without a real PI by payment method instead,
                // same "one settlement action, one entry" rule the Card branch already follows.
                // Grouped by the ORIGINAL payment_method/PI (not $effectivePaymentMethod) even under
                // a manual override — that's still what identifies "one settlement batch"; only how
                // it's tagged/settled changes.
                $piKey = ($txn->payment_method === 'card' && $txn->transaction_id)
                    ? $txn->transaction_id
                    : ('method_' . ($txn->payment_method ?? 'unknown'));
                $historyByPi[$piKey][] = [
                    'name'           => trim(implode(' — ', array_filter([$txn->addonField->field_label ?? null, $txn->addonField->option_label ?? null]))),
                    'price'          => $rowPrice,
                    'fee'            => $rowFee,
                    'payment_method' => $effectivePaymentMethod,
                    'status'         => $data['label'],
                ];

                if (!isset($piMeta[$piKey])) {
                    $piMeta[$piKey] = [
                        'price'              => 0,
                        'fee'                => 0,
                        'transaction_id'     => $txn->transaction_id,
                        'application_fee_id' => $txn->application_fee_id,
                        'payment_method'     => $effectivePaymentMethod,
                    ];
                }
                $piMeta[$piKey]['price'] += $rowPrice;
                $piMeta[$piKey]['fee']   += $rowFee;
            }

            if ($lastBooking && !empty($historyByPi)) {
                $requestProxy = (object) ['description' => $description];

                foreach ($historyByPi as $piKey => $histItems) {
                    $meta = $piMeta[$piKey];
                    $transactionValue = [
                        'price'              => $meta['price'],
                        'fee'                => $meta['fee'],
                        // A manual Cash/Check-Wire refund ($manualRefundVia set) never touched
                        // Stripe — recording the field's original PaymentIntent/application fee id
                        // on this ledger row would misleadingly suggest it IS that real Stripe
                        // operation. Blank both, matching deletePaxAddons()'s same convention.
                        'transaction_id'     => $manualRefundVia === null ? $meta['transaction_id'] : '',
                        'application_fee_id' => $manualRefundVia === null ? $meta['application_fee_id'] : null,
                    ];
                    $bookingAddonProxy = (object) ['payment_method' => $meta['payment_method']];

                    // Gated on $piKey itself being a real PI, not on $meta['payment_method']
                    // (which reports the possibly manually-overridden EFFECTIVE method — 'Cash'
                    // for a manually reimbursed CARD original would wrongly skip a lookup that
                    // should still happen). $piKey was built from the item's TRUE original
                    // payment_method (see the grouping comment above), so it's the correct
                    // discriminant here.
                    $piRefundMode = !str_starts_with((string) $piKey, 'method_')
                        ? DB::table('transactions')->where('transaction_number', $meta['transaction_id'])->value('payment_mode')
                        : PaymentModeResolver::DESTINATION;
                    $txId = TransactionService::storeAddonsRefundInTransaction(
                        $lastBooking, $bookingAddonProxy, $transactionValue, mode: $piRefundMode
                    );
                    HistoryService::storeAddonsRefundInHistory(
                        $lastBooking, $bookingAddonProxy, $transactionValue,
                        $histItems, 'removed', end($newTransactions)['label'], $txId, $requestProxy
                    );
                }
            }

            DB::commit();

        } catch (CardException $e) {
            DB::rollBack();
            Log::error($e);
            throw new GeneralCoreException($e);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw new GeneralCoreException($e);
        }
    }

    /**
     * Reverse a booking's add-on refunds when its cancellation is undone (Restore Booking).
     *
     * restoreBooking() already restores the TICKET side (deletes the cancel's refund
     * transaction/history row, sets status back to 'Booked') and the custom-charge side
     * (CustomChargeBookingService::restoreCustomCharges()) but never touched add-ons — a
     * cancelled booking with paid add-ons stayed permanently refunded even after restore.
     *
     * Restorable strictly by payment method, not by "which action caused it": a Cash/Invoiced/
     * Check-Wire (or manually-settled, via CancelBooking's is_cash override) add-on refund never
     * touched a real payment gateway — it's pure bookkeeping, always safe to reverse. A 'card'
     * refund moved REAL money back to the customer through Stripe; reactivating that DB row
     * without also re-charging the card would falsely show the add-on as paid again while the
     * customer keeps the refund. A persisted 'card' refund row is itself proof the Stripe call
     * succeeded — refundAddonsByBookingId() only ever creates the row AFTER the gateway call
     * returns; a failed call throws and rolls back before any row is written — so under this
     * architecture "restore card only when its refund failed" always evaluates to "never restore
     * card," which is exactly what this skips.
     *
     * Deletes rather than reverses with a new "re-charge" transaction, mirroring
     * restoreCustomCharges()'s same "delete the refund's own records" convention — the field's
     * underlying 'paid'/'charge' row (never touched by the refund) is simply left as the most
     * recent transaction again once its 'refund' row is gone. A refundAddonsByBookingId() ledger
     * row is always entirely one payment method (it groups by Stripe PI for card, by payment
     * method for everything else — never mixed), so a card-vs-not filter on the ledger rows
     * themselves is exact, not an approximation.
     *
     * $cancelledAt scopes this to refunds created BY THIS CANCELLATION only — payment_method
     * alone can't tell "refunded because of this cancel" apart from "refunded earlier for an
     * unrelated, permanent reason" (e.g. a pax removed, or an add-on downgraded, before the
     * booking was ever cancelled). Without it, restoring a booking that was cancelled AFTER an
     * earlier pax/add-on removal resurrected that earlier refund too — money the customer kept
     * for a real, unrelated, already-settled reason came back as "paid" again. Both the
     * "Cancelled" ticket history row and this cancellation's own addon "Refund" history/
     * transaction rows are written via `mysqlDT()`/`now()` within the same request, so comparing
     * `operation_at >= $cancelledAt` (the Cancelled row's own timestamp, already in hand at the
     * caller) reliably keeps this cancellation's rows in and any earlier refund out — no schema
     * change needed. Left nullable (falls back to the old unscoped behavior) only so a caller
     * that genuinely can't find the Cancelled history row isn't forced to restore nothing.
     *
     * @param int $tourBookingId
     * @param string|null $cancelledAt The Cancelled history row's own operation_at/created_at.
     */
    public function restoreAddonsRefundedByCancelBooking(int $tourBookingId, ?string $cancelledAt = null): void
    {
        $refundQuery = TourBookingAddonTransaction::where('tour_booking_id', $tourBookingId)
            ->where('operation', 'refund')
            ->where('status', 'refunded')
            ->where('payment_method', '!=', 'card');
        if ($cancelledAt !== null) {
            $refundQuery->where('operation_at', '>=', $cancelledAt);
        }
        $restorableFieldIds = $refundQuery->pluck('id');

        if ($restorableFieldIds->isEmpty()) {
            return;
        }

        DB::beginTransaction();
        try {
            $historyQuery = DB::table('tour_booking_history')
                ->where('tour_booking_id', $tourBookingId)
                ->where('operation', 'Refund')
                ->where('charge_type', 'addons')
                ->whereRaw('LOWER(payment_type) != ?', ['card']);
            if ($cancelledAt !== null) {
                $historyQuery->where('operation_at', '>=', $cancelledAt);
            }
            $historyRows = $historyQuery->get(['id', 'transaction_id']);

            if ($historyRows->isNotEmpty()) {
                DB::table('tour_booking_history')->whereIn('id', $historyRows->pluck('id'))->delete();

                $transactionIds = $historyRows->pluck('transaction_id')->filter();
                if ($transactionIds->isNotEmpty()) {
                    DB::table('transactions')->whereIn('id', $transactionIds)->delete();
                }
            }

            // forceDelete(), not delete() — TourBookingAddonTransaction uses SoftDeletes, and
            // BookingService::fetchAddonsWithPrice() (plus DeleteBookingAddonBreakdownService's
            // identical query) deliberately sums tour_booking_addon_transactions WITHOUT
            // filtering deleted_at, so a merely soft-deleted refund row stayed counted there
            // forever — netting the restored charge back down to $0 and hiding the booking-info
            // "Add-ons:" line even though restoreAddonsRefundedByCancelBooking() had already made
            // the addon "restored" everywhere else (e.g. computeNetAddonTransactions(), which is
            // soft-delete-scoped). These rows are being reversed, exactly like the sibling
            // transactions/tour_booking_history deletes just above, so they must actually go away.
            TourBookingAddonTransaction::whereIn('id', $restorableFieldIds)->forceDelete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw new GeneralCoreException($e);
        }
    }

    /**
     * Return user saved add-on selections.
     *
     * Route example:
     * GET get-booking-addons-submission/{tourBookingId}
     */
    public function getTourBookingAddonsSubmission(int $tourBookingId): \Illuminate\Http\JsonResponse
    {
        try {
            $data = $this->buildAddonsSubmission($tourBookingId);

            if ($data === null) {
                return response()->json(['success' => true, 'addons' => null]);
            }

            return response()->json(['success' => true] + $data);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Unable to fetch add-ons', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Build the new form-builder add-ons submission for a booking as a plain array
     * (no HTTP response wrapping), so other callers — e.g. the v1
     * get-package-form-submission endpoint — can nest it inside their own response.
     *
     * Returns null when the booking has no new-system add-ons submitted at all.
     *
     * @return array{addons: \Illuminate\Support\Collection, pax_addons: \Illuminate\Support\Collection, retained_fees: float}|null
     */
    public function buildAddonsSubmission(int $tourBookingId): ?array
    {
        $rows = TourBookingAddonField::query()
            ->leftJoin('tour_bookings_transact as tbt', 'tbt.id', '=', 'tour_booking_addon_fields.pax_id')
            ->leftJoin('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->select('tour_booking_addon_fields.*', 'tpr.rate_for')
            ->where('tour_booking_addon_fields.tour_booking_id', $tourBookingId)
            ->orderBy('tour_booking_addon_fields.id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $addonFieldIds   = $rows->pluck('id')->all();
        $meta            = $this->loadAddonMeta(
            $rows->pluck('field_id')->filter()->unique()->values()->all(),
            $rows->pluck('option_id')->filter()->unique()->values()->all()
        );
        $transactions    = $this->computeNetAddonTransactions($addonFieldIds, $tourBookingId);
        $retainedFee     = $this->computeOrphanedRetainedFee($tourBookingId, $addonFieldIds);
        $displayFeePct   = (float) (BookingTransact::where('tour_booking_id', $tourBookingId)
            ->where('deleted_by', 0)->value('nat_fees_percentage') ?? 0);
        $cashFeesEnabled = (string) cashFees();
        $grouped         = $rows->groupBy('parent_id');

        // General add-ons = everything except pax-scoped rows: priced one_time /
        // price_per_unit fields AND non-priced fields, whose rows carry unit_type
        // NULL — an in_array allowlist here silently dropped those from the
        // booking-info list and from edit-form prefill. The pax_index guard keeps
        // pax rows out regardless of unit_type (they render under pax_addons).
        $addons = $this->buildSubmissionTree($grouped, $meta['fields'], $meta['options'], $transactions, $displayFeePct, $cashFeesEnabled)
            ->filter(fn($node) => $node['unit_type'] !== 'price_per_pax' && $node['pax_index'] === null)
            ->values();

        $paxAddons = $this->buildPaxAddonGroups($rows, $grouped, $meta['fields'], $meta['options'], $transactions, $displayFeePct, $cashFeesEnabled);

        return [
            'addons'        => $addons,
            'pax_addons'    => $paxAddons,
            'retained_fees' => $retainedFee,
        ];
    }

    /**
     * Summary of loadAddonMeta
     * Load metadata for add-on fields and options.
     * @param array $fieldIds
     * @param array $optionIds
     * @return array{fields: array, options: array}
     */
    private function loadAddonMeta(array $fieldIds, array $optionIds): array
    {
        $fields = [];
        if (!empty($fieldIds)) {
            $fields = AddonField::whereIn('id', $fieldIds)
                ->get()
                ->keyBy('id')
                ->map(fn($f) => [
                    'id'             => $f->id,
                    'label'          => $f->label,
                    'type'           => $f->type,
                    'required'       => (bool) $f->required,
                    'unit_type'      => $f->unit_type,
                    'additional_fee' => (bool) $f->additional_fee,
                    'price'          => $f->price,
                ])
                ->toArray();
        }

        $options = [];
        if (!empty($optionIds)) {
            $options = AddonFieldOption::whereIn('id', $optionIds)
                ->get()
                ->keyBy('id')
                ->map(fn($o) => [
                    'id'             => $o->id,
                    'label'          => $o->label,
                    'value'          => $o->value,
                    'price'          => $o->price,
                    'additional_fee' => (bool) $o->additional_fee,
                ])
                ->toArray();
        }

        return ['fields' => $fields, 'options' => $options];
    }

    /**
     * Load paid/refunded transactions for the given addon field IDs and return
     * the net price/fee per field as a plain array keyed by addon field id.
     *
     * Refund rows may store negative amounts (new style) or positive amounts with
     * status='refunded' (old style) — both are normalised to a negative contribution.
     *
     * @param  int[] $addonFieldIds
     * @return array<int, array{payment_method: string, status: string, field_price: float, field_fee: float, option_price: float, option_fee: float, has_refund: bool, zero_refund_max_id: ?int}>
     */
    private function computeNetAddonTransactions(array $addonFieldIds, int $tourBookingId): array
    {
        // Scoped to $tourBookingId — Split Booking can move a field to a different booking
        // (AddonBookingService::splitBookingPaxAddons()), leaving its pre-split charge/refund
        // rows behind still tagged to the OLD booking. Without this, a moved field's historical
        // rows from its previous booking would be summed in here too, doubling its net total.
        return TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $addonFieldIds)
            ->where('tour_booking_id', $tourBookingId)
            ->whereIn('status', ['paid', 'refunded'])
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('tour_booking_addon_id')
            ->map(function ($group): array {
                // Highest-id 'refunded' row against this field whose amounts are ALL zero — the
                // signature a "Don't Refund" settlement writes (deliberately net-$0: nothing is
                // actually paid out, but the charge must still close out for good). $group is
                // already ordered id-desc (see the query above), so first() here is the MAX id
                // among any matches.
                $zeroRefund = $group->first(fn ($t) => $t->status === 'refunded'
                    && (float) $t->field_price === 0.0 && (float) $t->field_fee === 0.0
                    && (float) $t->option_price === 0.0 && (float) $t->option_fee === 0.0);

                // The net $ amounts below are deliberately computed over rows AFTER that
                // settlement only (or the whole group when there is none) — not the whole group.
                // A field id can carry more than one independent 'paid' charge over its lifetime
                // without ever being soft-deleted/recreated (e.g. an option upgrade — see
                // processAddonFinancialTransactions()'s "multiple PaymentIntents over its
                // lifetime" comment), so a blended net across ALL rows would leak the
                // already-settled charge's dollar amount back into a later, independent charge's
                // refund total (wrongly re-including it, and against the WRONG PaymentIntent to
                // boot). Excluding rows at-or-before the settlement's id keeps the settled charge
                // out of every net calculation, not just the direct per-row skip
                // refundAddonsByBookingId() does via 'zero_refund_max_id' below.
                $netGroup = $zeroRefund ? $group->filter(fn ($t) => $t->id > $zeroRefund->id) : $group;

                $netField = (float) $netGroup->sum(function ($t): float {
                    $v = (float) $t->field_price;
                    return $t->status === 'refunded' ? ($v <= 0 ? $v : -$v) : $v;
                });
                $netOption = (float) $netGroup->sum(function ($t): float {
                    $v = (float) $t->option_price;
                    return $t->status === 'refunded' ? ($v <= 0 ? $v : -$v) : $v;
                });

                // Same sign-convention handling as price above: some refund paths store fee
                // negative, others (ADDONS_FULL_REFUND / ADDONS_TICKET_REFUND) store it positive.
                $netFee = round((float) $netGroup->sum(function ($t): float {
                    $v = (float) $t->field_fee;
                    return $t->status === 'refunded' ? ($v <= 0 ? $v : -$v) : $v;
                }), 2);
                $netOptFee = round((float) $netGroup->sum(function ($t): float {
                    $v = (float) $t->option_fee;
                    return $t->status === 'refunded' ? ($v <= 0 ? $v : -$v) : $v;
                }), 2);

                $latest = $group->where('status', 'paid')->first() ?? $group->first();

                return [
                    'payment_method' => $latest->payment_method,
                    'status'         => $latest->status,
                    'field_price'    => $netField,
                    'field_fee'      => $netFee,
                    'option_price'   => $netOption,
                    'option_fee'     => $netOptFee,
                    // Whether ANY settlement decision has already been recorded against this field
                    // at all, independent of the net $ amounts above — refundAddonsByBookingId()
                    // uses this (together with the field itself being soft-deleted) to recognize a
                    // pax-removal's decision as final, even when it deliberately left some portion
                    // (e.g. Ticket Refund's fee) numerically unmatched on purpose.
                    'has_refund'     => $group->contains(fn ($t) => $t->status === 'refunded'),
                    'zero_refund_max_id' => $zeroRefund->id ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Sum retained fees from transactions whose addon fields were soft-deleted.
     *
     * Orphaned = rows in tour_booking_addon_transactions whose addon field no
     * longer exists (soft-deleted). Charge and refund rows are summed SEPARATELY
     * (each via ABS()) rather than as one signed SUM(), because refund rows are
     * not written with a consistent sign across the codebase — some paths store
     * them negative, but the direct addon-edit refund path (ADDONS_FULL_REFUND /
     * ADDONS_TICKET_REFUND) stores them positive. A signed SUM() would silently
     * add a positive-convention full-refund's fee back on top of the charge
     * instead of cancelling it out.
     *
     * @param  int[] $activeAddonFieldIds  IDs of addon fields still alive (not soft-deleted)
     */
    private function computeOrphanedRetainedFee(int $tourBookingId, array $activeAddonFieldIds): float
    {
        $chargedFee = TourBookingAddonTransaction::where('tour_booking_id', $tourBookingId)
            ->when(!empty($activeAddonFieldIds), fn($q) => $q->whereNotIn('tour_booking_addon_id', $activeAddonFieldIds))
            ->where('operation', 'charge')
            ->where('status', 'paid')
            ->selectRaw('SUM(ABS(field_fee)) + SUM(ABS(option_fee)) AS net_fee')
            ->value('net_fee');

        $refundedFee = TourBookingAddonTransaction::where('tour_booking_id', $tourBookingId)
            ->when(!empty($activeAddonFieldIds), fn($q) => $q->whereNotIn('tour_booking_addon_id', $activeAddonFieldIds))
            ->where('operation', 'refund')
            ->where('status', 'refunded')
            ->selectRaw('SUM(ABS(field_fee)) + SUM(ABS(option_fee)) AS net_fee')
            ->value('net_fee');

        return round(max(0.0, (float) $chargedFee - (float) $refundedFee), 2);
    }

    /**
     * Group price_per_pax rows by pax_index and build each pax's addon tree.
     *
     * @param  \Illuminate\Support\Collection $rows    All addon field rows for the booking
     * @param  \Illuminate\Support\Collection $grouped Rows keyed by parent_id (for tree building)
     * @param  array                          $fields       AddonField metadata keyed by id
     * @param  array                          $options      AddonFieldOption metadata keyed by id
     * @param  array                          $transactions Net transaction data keyed by addon field id
     * @param  float                          $displayFeePct
     * @param  string                         $cashFeesEnabled
     * @return \Illuminate\Support\Collection
     */
    private function buildPaxAddonGroups(
        \Illuminate\Support\Collection $rows,
        \Illuminate\Support\Collection $grouped,
        array $fields,
        array $options,
        array $transactions,
        float $displayFeePct,
        string $cashFeesEnabled
    ): \Illuminate\Support\Collection {
        return collect($rows)
            ->where('unit_type', 'price_per_pax')
            ->groupBy('pax_index')
            ->map(function ($items, $paxIndex) use ($grouped, $fields, $options, $transactions, $displayFeePct, $cashFeesEnabled): array {
                $label = $items->first()?->rate_for ?: "Pax {$paxIndex}";

                return [
                    'rate_group'     => $label,
                    'pax_id'         => $items->first()?->pax_id,
                    'pax_index'      => $paxIndex,
                    'person_ordinal' => $items->first()?->person_ordinal,
                    'addons'         => $this->buildSubmissionTree(
                        $grouped, $fields, $options, $transactions,
                        $displayFeePct, $cashFeesEnabled, null, $paxIndex
                    ),
                ];
            })
            ->values();
    }

    /**
     * Recursively build the addon submission tree for getTourBookingAddonsSubmission.
     *
     * @param \Illuminate\Support\Collection $grouped   Rows keyed by parent_id
     * @param array                          $fields    AddonField metadata keyed by id
     * @param array                          $options   AddonFieldOption metadata keyed by id
     * @param array                          $transactions  Net transaction data keyed by addon field id
     * @param float                          $displayFeePct  Fee percentage for backfill
     * @param string                         $cashFeesEnabled  '1' when cash fees apply
     * @param int|null                       $parentId  Parent addon field id (null = root)
     * @param string|int|null                $paxIndex  Pax index filter (null = no filter)
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildSubmissionTree(
        \Illuminate\Support\Collection $grouped,
        array $fields,
        array $options,
        array $transactions,
        float $displayFeePct,
        string $cashFeesEnabled,
        mixed $parentId = null,
        mixed $paxIndex = null
    ): \Illuminate\Support\Collection {
        return collect($grouped[$parentId] ?? [])
            ->filter(fn($row) => $paxIndex === null || $row->pax_index == $paxIndex)
            ->map(function ($row) use ($grouped, $fields, $options, $transactions, $displayFeePct, $cashFeesEnabled) {
                $txn = $transactions[$row->id] ?? null;

                // Backfill field_fee/option_fee when stored as 0 but fee applies
                // (old bookings or Pay-Later bookings where fee wasn't computed at creation).
                if ($txn !== null && $displayFeePct > 0) {
                    $txnMethod = strtolower($txn['payment_method'] ?? '');
                    $applyFee  = $txnMethod === 'card'
                        || ($txnMethod === 'cash' && $cashFeesEnabled === '1');

                    if ($applyFee) {
                        $fieldMeta = $fields[$row->field_id] ?? null;
                        if ($txn['field_fee'] == 0.0 && ($fieldMeta['additional_fee'] ?? false) && $txn['field_price'] != 0.0) {
                            // Per-unit-first rounding avoids half-cent drift on the multiplied total.
                            $bfQty = ($row->unit_type === 'price_per_unit' && (int) $row->unit > 1) ? (int) $row->unit : 1;
                            $bfUnit = abs($txn['field_price']) / $bfQty;
                            $txn['field_fee'] = round($bfUnit * $displayFeePct / 100, 2) * $bfQty;
                        }

                        $optionMeta = isset($row->option_id) ? ($options[$row->option_id] ?? null) : null;
                        if ($txn['option_fee'] == 0.0 && ($optionMeta['additional_fee'] ?? false) && $txn['option_price'] != 0.0) {
                            $txn['option_fee'] = round(abs($txn['option_price']) * $displayFeePct / 100, 2);
                        }
                    }
                }

                return [
                    'id'                    => $row->id,
                    'tour_booking_id'       => $row->tour_booking_id,
                    'form_id'               => $row->form_id,
                    'field_id'              => $row->field_id,
                    'field_label'           => $row->field_label ?? ($fields[$row->field_id]['label'] ?? null),
                    'option_id'             => $row->option_id,
                    'option_label'          => $row->option_label ?? ($options[$row->option_id]['label'] ?? null),
                    'rule_id'               => $row->rule_id,
                    'parent_id'             => $row->parent_id,
                    'value'                 => $row->value,
                    'pax_id'                => $row->pax_id,
                    'pax_index'             => $row->pax_index,
                    'person_ordinal'        => $row->person_ordinal,
                    'unit_type'             => $row->unit_type,
                    'unit'                  => (int) $row->unit,
                    'applied_rule_snapshot' => $row->applied_rule_snapshot,
                    'field_meta'            => $fields[$row->field_id] ?? null,
                    'option_meta'           => $options[$row->option_id] ?? null,
                    'transaction'           => $txn,
                    'children'              => $this->buildSubmissionTree(
                        $grouped, $fields, $options, $transactions,
                        $displayFeePct, $cashFeesEnabled, $row->id, $row->pax_index
                    ),
                ];
            })
            ->values();
    }

    public function processAddonPaymentFromLink(int $bookingId, array $financial, array $fieldToDb): void
    {
        $this->processAddonFinancialTransactions($bookingId, $financial, $fieldToDb);
    }

    public function createAddonPaymentLink(object $request): array
    {
        // Same cancelled-booking guard as updateBookingAddons()/savePaxAddons() above.
        if (DB::table('tour_bookings_master')->where('id', (int) $request->booking_id)->value('status') === 'Cancelled') {
            return [
                'success' => false,
                'message' => 'This booking has been cancelled — add-ons can no longer be added or updated.',
            ];
        }

        DB::beginTransaction();
        try {
            $added     = is_string($request->added)    ? json_decode($request->added, true)    : ($request->added ?? []);
            $updated   = is_string($request->updated)  ? json_decode($request->updated, true)  : ($request->updated ?? []);
            $deleted   = is_string($request->deleted)  ? json_decode($request->deleted, true)  : ($request->deleted ?? []);
            $paxGroups = is_string($request->pax)      ? json_decode($request->pax, true)      : ($request->pax ?? []);
            $financial = is_string($request->financial) ? json_decode($request->financial, true) : ($request->financial ?? []);

            $bookingId = (int) $request->booking_id;
            $booking   = BookingService::booking($bookingId);

            // Top-level (non-pax) addon fields
            $fieldToDb = $this->upsertBookingAddonFields(
                $bookingId, (int) $request->form_id, null, null, $added, $updated, $deleted
            );

            // Pax-structured addon fields (bulk pax addon payment link flow)
            foreach ($paxGroups as $pax) {
                $paxFieldToDb = $this->upsertBookingAddonFields(
                    $bookingId,
                    (int) $request->form_id,
                    $pax['pax_id'] ?? null,
                    $pax['pax_index'] ?? null,
                    $pax['added'] ?? [],
                    $pax['updated'] ?? [],
                    $pax['deleted'] ?? [],
                    isset($pax['person_ordinal']) ? (int) $pax['person_ordinal'] : null
                );
                // array_merge() renumbers purely-integer keys (the plain field_id fallback key
                // upsertBookingAddonFields() uses when pax_index is null) instead of preserving
                // them — silently destroying/mis-pointing that entry. `+` preserves every key,
                // string or int, as-is; composite field_id_paxIndex keys already make cross-pax
                // collisions impossible, so there's no ordering ambiguity to worry about either.
                // Same fix as updatePaxAddons()/savePaxAddons() above for the identical pattern.
                $fieldToDb = $fieldToDb + $paxFieldToDb;
            }

            // Fetched once here (rather than letting syncPendingAddonLinkForEditedFields() below
            // query it again internally) since this same nat_fees_percentage is also needed for
            // this method's own platform-fee computation just below.
            $bookingTxn = BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->first();
            $feePct     = (float) ($bookingTxn->nat_fees_percentage ?? 0);

            // upsertRemainingPaymentLink() below re-sums the link and emails it, so don't
            // recalculate/email here too.
            $this->syncPendingAddonLinkForEditedFields(
                $bookingId,
                array_merge($updated, collect($paxGroups)->flatMap(fn ($pax) => $pax['updated'] ?? [])->all()),
                $financial,
                false,
                $feePct
            );

            $netAmount    = (float) ($financial['net_amount'] ?? 0);

            if (!empty($financial['to_refund'])) {
                $platformFees = round($netAmount * $feePct / 100, 2);
            } else {
                $platformFees = 0.0;
                foreach ($financial['to_charge'] ?? [] as $item) {
                    $chargeAmount   = (float) $item['charge_amount'];
                    $rawFieldPrice  = isset($item['field_price'])  ? (float) $item['field_price']  : $chargeAmount;
                    $rawOptionPrice = isset($item['option_price']) ? (float) $item['option_price'] : 0.0;
                    $rawTotal       = $rawFieldPrice + $rawOptionPrice;
                    if ($rawTotal > 0 && abs($rawTotal - $chargeAmount) > 0.001) {
                        $scale       = $chargeAmount / $rawTotal;
                        $fieldPrice  = round($rawFieldPrice * $scale, 2);
                        $optionPrice = round($chargeAmount - $fieldPrice, 2);
                    } else {
                        $fieldPrice  = $rawFieldPrice;
                        $optionPrice = $rawOptionPrice;
                    }
                    $platformFees += round($fieldPrice * $feePct / 100, 2) + round($optionPrice * $feePct / 100, 2);
                }
                $platformFees = round($platformFees, 2);
            }

            $totalAmount = round($netAmount + $platformFees, 2);

            if ($totalAmount <= 0) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Payment link amount must be greater than zero.'];
            }

            $details = [
                'platform_fees'        => $platformFees,
                'form_id'              => (int) $request->form_id,
                'booking_id'           => $bookingId,
                'field_to_db'          => $fieldToDb,
                'financial'            => $financial,
                'added'                => $added,
                'updated'              => $updated,
                'pax'                  => $paxGroups,
                'created_by_with_name' => BookingService::createdUserInfo(),
            ];

            $slotInfo     = TourPackageService::getTourSlotInfo($booking->tour_slot_id, userType());
            $slotDateTime = Carbon::parse($slotInfo->date . ' ' . $slotInfo->time_from);
            $info         = BookingUtils::getBookingDetails($bookingId);

            PaymentLinkService::upsertRemainingPaymentLink(
                $booking->group_id,
                [
                    'type'         => 'addon',
                    'reference_id' => null,
                    'amount'       => $totalAmount,
                    'reason'       => 'Payment for add-ons on your booking',
                    'details'      => $details,
                ],
                $booking,
                $slotDateTime,
                $info
            );

            DB::commit();
            return ['success' => true, 'message' => 'Payment link sent successfully.'];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}