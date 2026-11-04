@php
    $comboBookingsData ??= [];
    $d                = resolvePaymentData($hist, $comboBookingsData);
    $createdByName    = $d['createdByName'];
    $createdByRole    = $d['createdByRole'];
    $details          = $d['details'];
    $paymentMethod    = $d['paymentMethod'];
    $paymentType      = $d['paymentType'];
    $cardNumber       = $d['cardNumber'];
    $paymentAmount    = $d['paymentAmount'];
    $isComboPayment   = $d['isComboPayment'];
    $allComboHists    = $d['allComboHists'];
    $childColors      = $d['childColors'];
    $comboRows        = $d['comboRows'];
    $totalComboAmount = $d['totalComboAmount'];
    $totalTicketCost  = $d['totalTicketCost'];
    $totalFees        = $d['totalFees'];
    $totalPax         = $d['totalPax'];
    $experienceCount  = $d['experienceCount'];

    // Child bookings display as normal payments — no combo breakdown shown
    if ($isComboPayment) {
        $currentComboEntry = collect($comboRows)->firstWhere('is_current', true);
        if ($currentComboEntry && !$currentComboEntry['is_parent']) {
            $isComboPayment = false;
        }
    }
@endphp

<div class="log-entry" data-type="payment">
    <div class="log-time">{{ formatDateTime($hist->operation_at) }}
        <div class="log-user">
            <img class="log-avatar" src="{{ resolveHistAvatar($hist, $histPhotoMap) }}" alt="">
            <div class="log-user-info">
                <span class="log-user-name">{{ $createdByName }}</span>
                @if ($createdByRole)
                    <span class="log-cred">{{ $createdByRole }}</span>
                @endif
            </div>
        </div>
    </div>

    @php
        $isCustomCharge = $hist->charge_type == 'custom_charge' && !empty($details->custom_charge);
        $isAddons       = $hist->charge_type == 'addons';
        $badgeClass = match(true) {
            $isCustomCharge => 'custom-charge',
            $isAddons       => 'addons',
            default         => 'payment',
        };
        $cardClass = $badgeClass;

        // A priced add-on is only ever recorded here with a $0 amount when nothing has actually
        // been collected yet (a Pay Later addition — see processAddonFinancialTransactions()'s
        // 'open' branch) — a real charge always has price > 0 (unpriced add-ons never reach
        // history at all, see calculatePricedAddonDelta()). "+ $0.00" reads as if $0 was
        // genuinely charged; show how many add-ons were added instead.
        $isPendingAddonCharge = $isAddons
            && (float) ($hist->ticket_cost ?? 0) + (float) ($hist->platform_fees ?? 0) == 0.0
            && !empty($details->addons);
    @endphp
    <div class="log-badge {{ $badgeClass }}">
        @if ($hist->charge_type == 'discount')
            Discount Payment
        @elseif ($isCustomCharge)
            Custom Charge
        @elseif ($isAddons)
            Add-ons
        @else
            Payment
        @endif
    </div>

    <div class="log-content">
        @if ($isComboPayment && (session('userType') == 'Tour Operator' || session('userType') == 'Admin'))
            {{-- ── Combo payment: split breakdown across all bookings ── --}}
            <div class="txn-card payment">
                <div class="txn-amount">+ {{ moneyFormat($totalComboAmount) }}</div>
                <div class="txn-method">
                    {{ $paymentMethod }} — {{ $experienceCount }} {{ Str::plural('Experience', $experienceCount) }}
                    @if (!empty($bookingM->order_id))
                        · Order #{{ bookingPrefix($bookingM->order_id) }}
                    @endif
                    @if ($details->payment_type == 'Card' && $cardNumber)
                        <span class="card-chip">
                            <svg width="14" height="10" viewBox="0 0 14 10" fill="none"><rect x=".5" y=".5" width="13" height="9" rx="1.5" stroke="#cbd5e1"/><path d="M0 3.5h14" stroke="#cbd5e1" stroke-width="1.2"/></svg>
                            {{ $cardNumber }}
                        </span>
                    @else
                        <span class="card-chip" style="{{ paymentTypeChipStyle($paymentType) }}">{{ $paymentType }}</span>
                    @endif
                </div>
                <div class="split-breakdown">
                    @php
                        $childColorIdx = 0;
                        $pfx = $routePrefix ?? (session('userType') == 'Affiliate' ? '' : 'tour-operator.');
                    @endphp
                    @foreach ($comboRows as $comboEntry)
                        @php
                            $entryHist   = $allComboHists->get($comboEntry['id']);
                            $entryDet    = $entryHist ? (is_string($entryHist->details) ? json_decode($entryHist->details) : $entryHist->details) : null;
                            $entryTicket = $entryHist ? (float) $entryHist->ticket_cost   : 0;
                            $entryFees   = $entryHist ? (float) $entryHist->platform_fees : 0;
                            $entryAmt    = $entryDet  ? (float) ($entryDet->amount ?? 0)  : 0;
                            if ($comboEntry['is_parent']) {
                                $dotColor = '#059669';
                                $rowBg    = 'background:#f0fdf4;';
                            } else {
                                $dotColor = $childColors[$childColorIdx % count($childColors)];
                                $rowBg    = '';
                                $childColorIdx++;
                            }
                        @endphp
                        <div class="split-row" style="{{ $rowBg }}">
                            <span class="split-dot" style="background:{{ $dotColor }};"></span>
                            <span class="split-name">
                                <a href="{{ route(prefixedRouteName($pfx . 'booking.show'), ['booking' => $comboEntry['id']]) }}"
                                   style="color:{{ $dotColor }};text-decoration:none;font-weight:700;" target="_blank">#{{ bookingPrefix($comboEntry['id']) }}</a>
                                {{ $comboEntry['packageName'] }}
                                @if ($comboEntry['is_parent'])
                                    <span style="font-size:9px;font-weight:800;background:#059669;color:#fff;padding:1px 6px;border-radius:8px;margin-left:4px;">PARENT</span>
                                @else
                                    <span style="font-size:9px;color:#94a3b8;margin-left:4px;">child</span>
                                @endif
                                @if ($comboEntry['is_current'])
                                    <span style="font-size:9px;font-weight:700;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:4px;padding:1px 5px;margin-left:3px;">current</span>
                                @endif
                            </span>
                            @if ($entryHist)
                                <span class="split-sub">Ticket {{ moneyFormat($entryTicket) }} · Fees {{ moneyFormat($entryFees) }}</span>
                                <span class="split-amount">{{ moneyFormat($entryAmt) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="txn-breakdown" style="margin-top:8px;margin-bottom:0;">
                    <span><span class="fp-label">Ticket Cost:</span> <b>{{ moneyFormat($totalTicketCost) }}</b></span>
                    @if ($totalFees > 0)
                        <span><span class="fp-label">Platform Fees:</span> <b>{{ moneyFormat($totalFees) }}</b></span>
                    @endif
                    @if ($totalPax > 0)
                        <span><span class="fp-label">Pax:</span> <b>{{ $totalPax }}</b></span>
                    @endif
                </div>
            </div>
        @else
            @if ($isPendingAddonCharge)
                {{-- ── Pay Later add-on(s): nothing has actually been collected yet, so a
                     "+ $0.00" txn-card would read as if $0 was genuinely charged. Same
                     icon+title convention _update_pax.blade.php uses for "Edit Booking — N
                     tourist(s) added" instead — the itemized add-on breakdown below (shared
                     with the standard card) still shows the real per-item prices. ── --}}
                <div class="sys-card addons">
                    <div class="sys-card-icon">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                        </svg>
                    </div>
                    <div class="sys-card-body">
                        <div class="sys-card-title">
                            Add-ons Updated &mdash; {{ count($details->addons) }} add-on{{ count($details->addons) == 1 ? '' : 's' }} added
                        </div>
                        @include('users.common.partials.booking.history-entries._addon-pills')
                    </div>
                </div>
            @else
            {{-- ── Standard single payment card ── --}}
            <div class="txn-card {{ $cardClass }}">
                <div class="txn-amount">+ {{ moneyFormat($paymentAmount) }}</div>
                <div class="txn-method">
                    {{ $paymentMethod }}
                    @php
                        $displayPaymentType = $paymentType;
                        if ($paymentType == 'Invoiced') {
                            $displayPaymentType = !empty($invoiced) && is_object($invoiced)
                                ? ($invoiced->status == 'paid' ? $details->payment_type : 'Invoiced')
                                : 'Billable';
                        }
                    @endphp
                    @if(($details->payment_type == 'Card' || in_array($details->payment_type, $otaPlatforms)) && $cardNumber)
                        <span class="card-chip">{{ $details->payment_type }} ···· {{ $cardNumber }}</span>
                    @elseif($displayPaymentType != '')
                        <span class="card-chip"
                            style="{{ paymentTypeChipStyle($displayPaymentType) }}">{{ $displayPaymentType }}</span>
                    @endif
                </div>
                @if($hist->charge_type != 'discount')
                <div class="txn-breakdown">
                    @if ($hist->charge_type == 'addons')
                        <div><span class="fp-label">Add-on Cost:</span> <b>{{ moneyFormat($hist->ticket_cost) }}</b></div>
                        @if ($hist->platform_fees > 0)
                            <div><span class="fp-label">Platform Fees:</span> <b>{{ moneyFormat($hist->platform_fees) }}</b></div>
                        @endif
                    @elseif($hist->charge_type != 'discount')
                        @if ($hist->charge_type == "custom_charge" && !empty($details->custom_charge))
                            <div>Custom Charge: <b>{{ moneyFormat($hist->ticket_cost) }}</b></div>
                        @else
                            <div>Ticket Cost: <b>{{ moneyFormat($hist->ticket_cost) }}</b></div>
                        @endif

                        @if ($isCashFees == '1' && $details->payment_type == "Cash" || $details->payment_type == "Card" || in_array($details->payment_type, $otaPlatforms))
                            <div>Platform Fees: <b>{{ moneyFormat($hist->platform_fees) }}</b></div>
                        @endif

                        @if ($details->payment_type == "Cash" && isset($details->amount) && $hist->charge_type != "discount")
                            @if (isset($details->paid_amount) && $details->paid_amount > 0 && $paymentMethod != 'Split Payment')
                                <div>Paid: <b>{{ moneyFormat($details->paid_amount) }}</b></div>
                            @endif
                        @endif

                        @if ($details->payment_type == "Cash" && isset($hist->change_amount) && $hist->change_amount > 0 && isset($details->amount) && $hist->charge_type != "discount")
                            @php $paidAmountCash = $hist->change_amount + $details->amount; @endphp
                            @if (!isset($details->paid_amount))
                                <div>Paid: <b>{{ moneyFormat($paidAmountCash) }}</b></div>
                                <div>Change: <b>{{ moneyFormat($hist->change_amount) }}</b></div>
                            @endif
                        @endif

                        @if (isset($hist->discount) && $hist->discount > 0 && $paymentMethod != 'Split Payment' && $hist->charge_type != "discount")
                            @php $discountPromoCode = $details->new_discount_promo_code ?? ''; @endphp
                            <div>
                                Discount: <b>{{ moneyFormat($hist->discount) }}</b>
                                @if ($discountPromoCode != '')
                                    <span class="card-chip"
                                        style="background:#fef9c3;color:#854d0e;border:1px solid #fde047;font-weight:700;">{{ $discountPromoCode }}</span>
                                @endif
                            </div>
                        @endif
                    @endif
                    @if ($hist->tourists && $hist->charge_type != 'discount')
                        <div>New Pax Count: <b>{{ $hist->tourists }}</b></div>
                    @endif
                </div>
                @endif
                @include('users.common.partials.booking.history-entries._addon-pills')
            </div>
            @endif
                @if($hist->charge_type == "discount")
                    @if (isset($hist->discount) && $hist->discount >= 0 && $paymentMethod != 'Split Payment')
                        @php
                            $oldDiscount       = $details->old_discount2_value ?? '';
                            $discountPromoCode = $details->new_discount_promo_code ?? '';
                            $discountPct       = $details->new_discount2_percentage ?? 0;
                        @endphp
                        @if ((float)$oldDiscount !== (float)$hist->discount)
                        <div class="discount-breakdown" style="flex-wrap:wrap;align-items:center;gap:10px;">
                            {{-- Old discount --}}
                            <div class="discount-stat discount-stat--fixed">
                                <div class="discount-stat-label">Old Discount</div>
                                <div class="discount-stat-value discount-stat-value--muted"
                                     style="text-decoration:line-through;font-size:13px;">
                                    {{ moneyFormat($oldDiscount) ?: '—' }}
                                </div>
                            </div>

                            {{-- Arrow --}}
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;color:#c43030;">
                                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>

                            {{-- New discount --}}
                            <div class="discount-stat discount-stat--fixed">
                                <div class="discount-stat-label">New Discount</div>
                                <div class="discount-stat-value discount-stat-value--bold" style="font-size:15px;">
                                    {{ moneyFormat($hist->discount) }}
                                    @if($discountPct > 0)
                                        <span style="font-size:11px;font-weight:700;color:#94a3b8;margin-left:2px;">
                                            ({{ $discountPct }}%)
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Promo code badge --}}
                            @if ($discountPromoCode != '')
                                <div class="discount-divider"></div>
                                <div class="discount-stat discount-stat--fixed">
                                    <div class="discount-stat-label">Promo Code</div>
                                    <span class="card-chip"
                                          style="background:#fef9c3;color:#854d0e;border:1px solid #fde047;font-weight:700;font-size:11px;">
                                        {{ $discountPromoCode }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        @endif {{-- old !== new discount --}}
                    @endif
                @endif
                @if (
                    $hist->operation == 'Payment' &&
                    isset($hist->ticket_cost) && $hist->ticket_cost > 0 &&
                    $bookingM->statusm != 'Cancelled' &&
                    ($hist->payment_type == 'Cash' || $hist->payment_type == 'Invoiced') &&
                    (!empty($trans) && ($transP->payment_type == 0 || $transP->payment_type == '') && $paymentTransCount == 1) &&
                    $pricedAddons['addons_final_total'] == 0 &&
                    $customChargePrice['custom_charge_total'] == 0 &&
                    $tipAmounts['tip_total'] == 0 &&
                    $hist->charge_type == null &&
                    $Refund_Amount == 0
                )
                    <div class="txn-action-row">
                        <button type="button"
                            class="txn-change-pay-btn"
                            onclick="return changePaymentType('{{ $hist->id }}');"
                            @if (session('userType') == 'Affiliate') {{ ($affiliateFeature == 0) ? 'disabled' : '' }} @endif>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 16V4m0 0L3 8m4-4l4 4"/><path d="M17 8v12m0 0l4-4m-4 4l-4-4"/>
                            </svg>
                            Change to
                            @if ($setting)
                                {{ $hist->payment_type == 'Cash' ? 'Invoice / Card' : 'Cash / Card' }}
                            @else
                                {{ $hist->payment_type == 'Cash' ? 'Card' : 'Cash / Card' }}
                            @endif
                        </button>
                    </div>
                @endif
        @endif
    </div>
</div>