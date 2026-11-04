{{--
    "Update Booking" refund modal (#deleteBooking) — shown by both the direct "Edit Booking"
    delete flow and the Change People remove-flow. Four blocks: refund type, tour transaction
    picker (tour amount only), add-ons (own block, never merged with the tour amount), and a
    live payment breakdown. See public/js/user-common/delete-booking-refund.js for the JS that
    drives the live recalculation.

    Expects these variables from the including view's scope (implicit via @include, not passed
    explicitly): $trans, $transP, $paymentTransCount, $paymentTransactions.
--}}
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
    id="deleteBooking" tabindex="-1" role="dialog" aria-hidden="true">
    <div
        class="modal-dialog mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center"
        style="max-width:640px">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden max-h-[90vh] flex flex-col">
            <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between flex-shrink-0">
                <h4 class="text-lg font-semibold text-[#111827]" id="deleteBookingTitle">Delete Booking</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                        height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                            stroke-linejoin="round"></path>
                        <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                            stroke-linejoin="round"></path>
                    </svg></button>
            </div>

            <div class="modal-body p-4 overflow-y-auto flex-1">
                <div id="Partial-delete"
                    class="empty:hidden mb-4 px-4 py-3 bg-[#FEF2F2] border border-[#FECACA] rounded-lg text-sm text-[#991B1B] font-medium leading-relaxed"></div>

                {{-- Block 1: refund type — compact 2-up grid instead of a tall single column --}}
                <div class="mb-4">
                    <div class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide mb-2">Refund Type</div>
                    <div class="grid grid-cols-2 gap-2">
                        @if (!empty($trans) && $transP->via == "Card" && $trans->payment_type == 1)
                        @else
                            <label class="refund-type-option flex items-start gap-2 px-3 py-2.5 border-2 border-[#E5E7EB] rounded-lg cursor-pointer transition-all"
                                for="Radio3">
                                <input class="Refcheck w-4 h-4 flex-shrink-0 mt-0.5" type="radio" name="refund_type"
                                    id="Radio3" value="full_refund" checked>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-[#111827] leading-tight">Ticket Refund</div>
                                    <div class="text-[11px] text-[#6B7280] leading-tight mt-0.5">Tour + add-ons; fees kept</div>
                                </div>
                            </label>

                            <label class="refund-type-option flex items-start gap-2 px-3 py-2.5 border-2 border-[#E5E7EB] rounded-lg cursor-pointer transition-all"
                                for="Radio2">
                                <input class="Refcheck w-4 h-4 flex-shrink-0 mt-0.5" type="radio" name="refund_type"
                                    id="Radio2" value="partial_refund">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-[#111827] leading-tight">Partial Refund</div>
                                    <div class="text-[11px] text-[#6B7280] leading-tight mt-0.5">Manual tour amount</div>
                                </div>
                            </label>

                            <label class="refund-type-option flex items-start gap-2 px-3 py-2.5 border-2 border-[#E5E7EB] rounded-lg cursor-pointer transition-all"
                                for="Radio4">
                                <input class="Refcheck w-4 h-4 flex-shrink-0 mt-0.5" type="radio" name="refund_type"
                                    id="Radio4" value="100_refund">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-[#111827] leading-tight">Weather / 100%</div>
                                    <div class="text-[11px] text-[#6B7280] leading-tight mt-0.5">Full refund incl. fees</div>
                                </div>
                            </label>
                        @endif
                        <label class="refund-type-option flex items-start gap-2 px-3 py-2.5 border-2 border-[#E5E7EB] rounded-lg cursor-pointer transition-all"
                            for="Radio1">
                            <input class="Refcheck w-4 h-4 flex-shrink-0 mt-0.5" type="radio" name="refund_type"
                                id="Radio1" value="dont_refund">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-[#111827] leading-tight">Don't Refund</div>
                                <div class="text-[11px] text-[#6B7280] leading-tight mt-0.5">Refund neither amount</div>
                            </div>
                        </label>
                    </div>
                    <div id="partial_refund_value2" class="mt-2" hidden>
                        <div class="flex items-center text-sm font-normal text-[#020817]">
                            <label class="form-check-label mr-2" for="partial_refund_value_field2">Amount</label>
                            <input type="number" min="1" step="0.01" id="partial_refund_value_field2"
                                name="partial_refund_value" class="form-custom-input max-w-28">
                        </div>
                    </div>
                </div>

                {{-- Not gated on $paymentTransCount > 1 — a single-payment/single-PaymentIntent
                booking can still have priced add-ons that need their own refund selection here,
                even though Block 2's picker only ever shows the one available transaction.
                Cash/Invoiced are included alongside Card — those bookings' pax can carry priced
                add-ons too, and those still need their own refund selection in Block 3. Block 2
                (the Stripe-PaymentIntent picker) stays Card-only below, since Cash/Invoiced have
                no PaymentIntent to pick from.

                "Billable" (the badge shown at the top of the page) is NOT a distinct `via` value
                anywhere in the data — it's purely a display label for an Invoiced booking with no
                formal invoice record generated yet ($bookingM->via == 'Invoiced' && empty($invoiced)
                elsewhere in the including view). $trans->via is genuinely 'Invoiced' for these
                bookings, and their add-ons are committed as status='paid' immediately just like
                Card/Cash, so there is genuinely refundable add-on money to show/select here.
                $deleteBookingModalRendersAddons (computed server-side, same condition) is the
                source of truth; the inline fallback here only covers any other @include of this
                partial that doesn't pass it. --}}
                @if ($deleteBookingModalRendersAddons ?? (!empty($trans) && $trans->payment_type == 0 && in_array($trans->via, ['Card', 'Cash', 'Invoiced'])))
                    <input type="hidden" name="refund_booking" value="2">

                    {{-- Per-PaymentIntent preview data (outstanding ticket/fee split, kept apart from
                    the separately-listed add-on line items still refundable on that same
                    PaymentIntent) — read once by the JS below to drive live recalculation as the
                    admin changes refund type or picks a different transaction. Tour and add-on
                    figures are never merged in this payload, matching the two separate blocks below. --}}
                    @php
                        $deleteBookingTransactionData = [];
                        foreach ($paymentTransactions as $transAmountValue) {
                            $outstandingTicket = max(0, ($transAmountValue->amount - $transAmountValue->fee_amount) - ($transAmountValue->refund_amount - $transAmountValue->refund_fee_amount));
                            $outstandingFee = max(0, $transAmountValue->fee_amount - $transAmountValue->refund_fee_amount);
                            $addonItems = $transAmountValue->addon_items ?? collect();
                            // Outstanding price-per-pax add-on total tied to this SAME PaymentIntent
                            // (see DeleteBookingAddonBreakdownService::attach()) — folded into this
                            // card's own badge below so it reads as the true balance sitting on that
                            // card (e.g. a booking-time charge that billed ticket + add-ons in one
                            // Stripe PI shows their combined total, not just the ticket portion).
                            // Block 3's own per-item checkboxes and Block 4's live breakdown are
                            // untouched by this — they still independently decide what actually gets
                            // refunded, this is purely "how much is on this card" for the admin's info.
                            $addonAmountOnThisPI = round($transAmountValue->addon_amount ?? 0, 2);
                            // No longer unconditionally skipped for an empty transaction_number —
                            // Cash payments are stored with transaction_number = '' (no Stripe
                            // PaymentIntent), so that blanket skip silently discarded every Cash
                            // pax's addon_items before they ever reached Block 3. A row with
                            // nothing outstanding AND no add-ons is still skipped below, same as
                            // before.
                            if ($outstandingTicket <= 0 && $outstandingFee <= 0 && $addonItems->isEmpty()) {
                                continue;
                            }
                            $deleteBookingTransactionData[$transAmountValue->transaction_number] = [
                                'outstandingTicket' => round($outstandingTicket, 2),
                                'outstandingFee' => round($outstandingFee, 2),
                                'addonAmount' => $addonAmountOnThisPI,
                                'addonItems' => $addonItems->values(),
                            ];
                        }
                        // Add-ons charged/edited on their OWN PaymentIntent with no accompanying
                        // tour-side charge at all never appear in $paymentTransactions above, so
                        // without this they'd be invisible and unselectable in Block 3 — silently
                        // leaving that money unrefunded whenever the pax is later removed.
                        foreach (($addonOnlyTransactionEntries ?? []) as $transactionNumber => $items) {
                            if (!isset($deleteBookingTransactionData[$transactionNumber])) {
                                $deleteBookingTransactionData[$transactionNumber] = [
                                    // outstandingTicket/Fee stay 0 (genuinely no tour money on this
                                    // PI — must never leak into Block 4's Tour row math), but Block
                                    // 2's badge for this entry still needs a real number instead of
                                    // a permanent, misleading $0.00 — addonOnlyTotal is what's
                                    // actually sitting on this card, usable as a real Stripe refund
                                    // source (see editBooking()'s charge_type='addons' matching)
                                    // even though it's booked here as add-on money, not tour money.
                                    'outstandingTicket' => 0,
                                    'outstandingFee' => 0,
                                    'addonItems' => $items->values(),
                                    'addonOnlyTotal' => round($items->sum(fn ($item) => $item['price'] + $item['fee']), 2),
                                ];
                            }
                        }
                    @endphp
                    <script type="application/json" id="delete-booking-transaction-data">{!! json_encode($deleteBookingTransactionData) !!}</script>
                    {{-- Never-paid ("Pending") priced add-ons — see
                    DeleteBookingAddonBreakdownService::attachPending()'s docblock for why this
                    must feed the deletion scope (#delete_booking_addon_scope_ids), not just the
                    display, or a pax whose only priced add-on was pending gets left orphaned. --}}
                    <script type="application/json" id="delete-booking-pending-addon-data">{!! json_encode($pendingAddonBreakdownItems ?? []) !!}</script>

                    {{-- Block 2: tour transactions. Card-only — Cash/Invoiced (Billable included,
                    since it's just an Invoiced booking with no formal invoice yet — see the guard
                    just above) has no Stripe PaymentIntent to pick between, and its tour-side
                    refund is already driven entirely by the Refund Type radios in Block 1 —
                    editBooking() settles it without a PI, via its dedicated Cash branch or
                    (Invoiced, and everything else that isn't Card/Cash/fareharbor) its generic
                    non-Card refund branch.

                    Each card's own badge shows its TRUE outstanding balance — tour PLUS any
                    priced add-ons charged on that same PaymentIntent (e.g. a booking-time charge
                    that billed ticket + add-ons together in one Stripe PI) — so it never reads as
                    understated just because part of that PI's money happens to be add-on money.
                    This is purely an "amount sitting on this card" display: Block 3's per-item
                    checkboxes and Block 4's live breakdown still independently decide what
                    actually gets refunded and are never derived from this badge. --}}
                    @if (!empty($trans) && $trans->via == 'Card')
                        <div class="mb-4">
                            <div class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide mb-2">Tour Transaction</div>
                            <div class="grid grid-cols-2 gap-2" id="delete-booking-tour-transactions">
                                @foreach($paymentTransactions as $transAmountValue)
                                    @continue(!isset($deleteBookingTransactionData[$transAmountValue->transaction_number]))
                                    <label class="transaction-option flex items-center justify-between gap-2 px-3 py-2.5 border-2 border-[#E5E7EB] rounded-lg cursor-pointer transition-all"
                                        for="check_transactions_{{ $transAmountValue->id }}">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <input type="radio" id="check_transactions_{{ $transAmountValue->id }}"
                                                class="check_transactions w-4 h-4 flex-shrink-0"
                                                name="check_transactions_radio"
                                                value="{{ $transAmountValue->transaction_number }}">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-[#111827] leading-tight truncate">
                                                    Card &bull;&bull;&bull;&bull;
                                                    {{ str_pad($transAmountValue->bookingHistory->card_last_4_digit ?? '0', 4, '0', STR_PAD_LEFT) }}
                                                </div>
                                                <div class="text-[11px] text-[#6B7280] leading-tight mt-0.5">Outstanding</div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-bold text-[#111827] whitespace-nowrap tour-transaction-amount">
                                            {{ moneyFormat(max(0, $transAmountValue->amount - $transAmountValue->refund_amount) + ($transAmountValue->addon_amount ?? 0)) }}
                                        </div>
                                    </label>
                                @endforeach
                                {{-- Add-ons charged on their OWN PaymentIntent, with no accompanying tour-side
                                charge at all, are deliberately NOT listed here. They used to be shown as a
                                selectable "Add-ons only" card, letting the admin pick one as the Tour
                                Transaction — but deletePaxAddons() never actually refunds add-ons against
                                whatever's picked here (see its own docblock: an add-on always settles
                                against its own real origin PI, Block 3's checkboxes are the only control for
                                that), so offering that card here only invited the admin to believe picking
                                it would route an add-on refund through it, which it never does. This block
                                is tour amount only — see the header comment at the top of this file. --}}
                            </div>
                        </div>
                    @endif

                    {{-- Block 3: every add-on tied to a rate group the admin is removing people
                    from, labeled per person (Adult 1, Adult 2, ...) so it's clear which one's
                    add-ons are about to move — its own block, never folded into the tour amount --}}
                    <div class="mb-4" id="delete-booking-addons-block" hidden>
                        <div class="text-xs font-semibold text-[#92400E] uppercase tracking-wide mb-2">Add-ons</div>
                        <div class="grid grid-cols-2 gap-1.5" id="delete-booking-addons-list"></div>
                    </div>

                    {{-- Block 4: live payment breakdown. The "Tour" row previews only what Block 2's
                    selected PaymentIntent would refund, so it's hidden for Cash (see JS) — Cash has
                    no Block 2 to select from, and showing a permanent, un-selectable "$0.00" there
                    would wrongly read as "no tour refund happens," when the tour side is actually
                    refunded separately via the Refund Type radios in Block 1. --}}
                    <div class="mb-4 rounded-xl px-4 py-3 border-2" id="delete-booking-breakdown" style="background:#F9FAFB;border-color:#E5E7EB">
                        <div class="flex items-center justify-between text-sm text-[#374151] mb-1" id="delete-booking-breakdown-tour-row">
                            <span>Tour</span>
                            <span class="font-semibold" id="delete-booking-breakdown-tour">{{ currencyFormation(false, true) }}0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-[#374151] mb-2">
                            <span>Add-ons</span>
                            <span class="font-semibold" id="delete-booking-breakdown-addons">{{ currencyFormation(false, true) }}0.00</span>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-[#E5E7EB]">
                            <span class="text-sm font-semibold text-[#111827]">Total refund</span>
                            <span class="text-base font-bold text-[#111827]" id="delete-booking-breakdown-total">{{ currencyFormation(false, true) }}0.00</span>
                        </div>
                        <div class="text-xs text-[#92400E] mt-2" id="delete-booking-partial-note" hidden>
                            Partial refunds only apply to the tour amount — add-ons for removed people are kept (Don't Refund), not refunded automatically.
                        </div>
                    </div>
                @endif
                {{-- @if ($bookingT[0]->tour_operator_id == 1)
                <div class="d-flex mb-2">
                    <div class="form-check-wrap">
                        <div class="form-check form-check-inline flex items-center">
                            <input class="form-check-input Refcheck w-4 h-4 mr-2" type="checkbox" name="refund_type"
                                id="fareharbor" value="fareharbor">
                            <label class="form-check-label" for="fareharbor">Fareharbor</label>
                        </div>
                    </div>
                </div>
                @endif --}}
                @if (!empty($trans) && $transP->payment_type != 1)
                    <div class="flex flex-wrap -mx-3 mb-2">
                        <div class="form-check-wrap w-1/2 px-3">
                            <label
                                class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isCashDeleteBooking" value="Cash">
                                <span class="checkmark"></span>
                                Cash
                            </label>
                        </div>
                        <div class="form-check-wrap w-1/2 px-3">
                            <label
                                class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isWireDeleteBooking"
                                    value="Wire Transfer">
                                <span class="checkmark"></span>
                                Check/Wire
                            </label>
                        </div>
                    </div>
                @endif
                <div class="text-center"><button
                        class="btn btn-main btn-main-sm themed-btn short-btn inline-block mx-auto btn-primary-small"
                        id="button_id">Submit</button></div>
            </div>
        </div>
    </div>
</div>
