@php
    $d                 = resolveRefundData($hist, $isCashFees ?? '0');
    $createdByName     = $d['createdByName'];
    $createdByRole     = $d['createdByRole'];
    $details           = $d['details'];
    $cardNumber        = $d['cardNumber'];
    $paymentType       = $d['paymentType'];
    $totalAmount       = $d['totalAmount'];
    $note              = $d['note'];
    $refundType        = $d['refundType'];
    $refundAmount      = $d['refundAmount'];
    $deletedGroups     = $d['deletedGroups'];
    $deletedCount      = $d['deletedCount'];
    $totalTicketCost   = $d['totalTicketCost'];
    $totalPlatformFees = $d['totalPlatformFees'];
    $perPersonTicket   = $d['perPersonTicket'];
    $perPersonPlatform = $d['perPersonPlatform'];
    $showPlatformFees  = $d['showPlatformFees'];
    $isCustomCharge    = $d['isCustomCharge'];
    $isAddons          = $d['isAddons'];
@endphp

<div class="log-entry" data-type="refund">
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

    <div class="log-badge refund">
        @if ($hist->charge_type == 'discount')
            <span style="color:#f59e0b;">Discount</span> Refund
        @elseif ($isCustomCharge)
            <span style="color:#7c3aed;">Custom Charge</span> Refund
        @elseif (!empty($deletedGroups))
            <span style="color:#c43030;">Remove Pax</span> Refund
        @else
            Refund
        @endif
    </div>

    <div class="log-content">
        <div class="txn-card refund">
            <div class="txn-amount">− {{ moneyFormat($refundAmount) }}</div>
            <div class="txn-method">
                {{ $refundType }}
                @if ($paymentType == 'Card' && $cardNumber)
                    <span class="card-chip">Card ···· {{ $cardNumber }}</span>
                @elseif($paymentType != '')
                    <span class="card-chip" style="{{ paymentTypeChipStyle($paymentType) }}">{{ $paymentType }}</span>
                @endif
            </div>
            @if($hist->charge_type != 'discount')
            <div class="txn-breakdown">
                @if ($isAddons)
                    <div><span class="fp-label">Add-on Cost:</span> <b>{{ moneyFormat($totalTicketCost) }}</b></div>
                    @if ($totalPlatformFees > 0)
                        <div><span class="fp-label">Platform Fees:</span> <b>{{ moneyFormat($totalPlatformFees) }}</b></div>
                    @endif
                @elseif ($isCustomCharge)
                    <div>Custom Charge: <b>{{ moneyFormat($totalTicketCost) }}</b></div>
                    @if ($totalPlatformFees > 0)
                        <div><span class="fp-label">Platform Fees:</span> <b>{{ moneyFormat($totalPlatformFees) }}</b></div>
                    @endif
                @else
                    <div>Ticket Cost: <b>{{ moneyFormat($totalTicketCost) }}</b></div>
                    @if ($showPlatformFees)
                        <div>Platform Fees: <b>{{ moneyFormat($totalPlatformFees) }}</b></div>
                    @endif
                @endif
                @if ($hist->tourists && $hist->charge_type != 'discount')
                    <div>New Pax Count: <b>{{ $hist->tourists }}</b></div>
                @endif
            </div>
            @endif
            @php
                $isPartialRefundCard = $refundType === 'Partial Refund'
                    && ($paymentType === 'Card' || $hist->payment_type === 'Card')
                    && $details
                    && !empty($details->cards)
                    && !empty($details->refund_amounts);
                $partialCards   = $isPartialRefundCard ? (array) $details->cards : [];
                $partialAmounts = $isPartialRefundCard ? (array) $details->refund_amounts : [];
            @endphp     
            @if ($isPartialRefundCard)
                <div class="refund-card-breakdown">
                    <div class="refund-card-breakdown-label">Card Breakdown</div>
                    @foreach ($partialCards as $k => $cardNo)
                        @if (isset($partialAmounts[$k]))
                            <div class="refund-card-breakdown-row">
                                <span class="refund-card-breakdown-amount">{{ moneyFormat($partialAmounts[$k]) }}</span>
                                <span class="card-chip">Card ···· {{ str_pad((string) $cardNo, 4, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
            @if($note)
                <div class="txn-note-box mt-2">
                    Note: {{ $note }}
                </div>
            @endif
            @if (!empty($deletedGroups))
                <div class="pax-changes-box pax-changes-box--removed">
                    <div class="pax-changes-label pax-changes-label--removed">🗑 Deleted</div>
                    @foreach ($deletedGroups as $rateName => $count)
                        <span class="pax-changes-item">{{ $rateName }} x {{ $count }}</span>
                        <span class="pax-changes-item-sub">
                            Ticket Cost: {{ moneyFormat(round($perPersonTicket * $count, 2)) }}
                            @if ($showPlatformFees)
                                · Platform Fees: {{ moneyFormat(round($perPersonPlatform * $count, 2)) }}
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
            @if ($isAddons && $details && (!empty($details->addons) || !empty($details->added_addons)))
                @php
                    $hasNetRefundBreakdown = !empty($details->added_addons);
                    $grossRemovedBase      = collect($details->addons ?? [])->sum('price');
                    $offsetAddedBase       = $hasNetRefundBreakdown ? collect($details->added_addons)->sum('price') : 0;
                @endphp
                @if ($hasNetRefundBreakdown)
                    {{-- Net refund breakdown: removed items offset by added items. Either side
                         can legitimately be empty (e.g. an all-'updated' net-zero edit where
                         only one field's price actually moved) — guard each section
                         independently so a real, non-empty side is never hidden just because
                         its counterpart happens to have nothing to show. --}}
                    <div class="addon-pills addon-pills--net">
                        @if (!empty($details->addons))
                        <div class="addon-pills-section-label addon-pills-section-label--removed">
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="5" fill="#dc2626"/></svg>
                            Removed
                        </div>
                        @foreach ($details->addons as $addon)
                            <div class="addon-pill addon-pill--removed">
                                <div class="addon-pill-name">
                                    {{ $addon->name }}
                                    @if (!empty($addon->quantity) && $addon->quantity > 1)
                                        <span class="addon-qty-badge">×{{ $addon->quantity }}</span>
                                    @endif
                                </div>
                                <div class="addon-pill-amounts">
                                    <span class="addon-pill-price addon-pill-price--credit">−{{ moneyFormat($addon->price ?? 0) }}</span>
                                </div>
                            </div>
                        @endforeach
                        @endif
                        @if (!empty($details->added_addons))
                        <div class="addon-pills-section-label addon-pills-section-label--added">
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="5" fill="#16a34a"/></svg>
                            Added (offset)
                        </div>
                        @foreach ($details->added_addons as $addon)
                            <div class="addon-pill">
                                <div class="addon-pill-name">
                                    {{ $addon->name }}
                                    @if (!empty($addon->quantity) && $addon->quantity > 1)
                                        <span class="addon-qty-badge">×{{ $addon->quantity }}</span>
                                    @endif
                                </div>
                                <div class="addon-pill-amounts">
                                    <span class="addon-pill-price">+{{ moneyFormat($addon->price ?? 0) }}</span>
                                    @if (!empty($addon->fee) && $addon->fee > 0)
                                        <span class="addon-pill-fee">+{{ moneyFormat($addon->fee) }} fee</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @endif
                        <div class="addon-pills-footer refund addon-pills-footer--net">
                            <span>
                                Net Refunded
                                <span class="addon-net-calc">({{ moneyFormat($grossRemovedBase) }} − {{ moneyFormat($offsetAddedBase) }})</span>
                            </span>
                            <span>{{ moneyFormat($hist->ticket_cost + $hist->platform_fees) }}</span>
                        </div>
                    </div>
                @else
                    <div class="addon-pills">
                        @foreach ($details->addons as $addon)
                            <div class="addon-pill">
                                <div class="addon-pill-name">
                                    {{ $addon->name }}
                                    @if (!empty($addon->quantity) && $addon->quantity > 1)
                                        <span class="addon-qty-badge">×{{ $addon->quantity }}</span>
                                    @endif
                                </div>
                                <div class="addon-pill-amounts">
                                    <span class="addon-pill-price">{{ moneyFormat($addon->price ?? 0) }}</span>
                                    @if (!empty($addon->fee) && $addon->fee > 0)
                                        <span class="addon-pill-fee">+{{ moneyFormat($addon->fee) }} fee</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if ($hist->platform_fees > 0 || count($details->addons) > 1)
                            <div class="addon-pills-footer refund">
                                <span>Total Refunded</span>
                                <span>{{ moneyFormat($hist->ticket_cost + $hist->platform_fees) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            @endif
            @if(isset($details->from) && $details->from == 'Edit Discount')
                    @php  
                        $oldDiscount = $details->old_discount2_value ?? '';
                        $promoCodeApplied = $details->new_discount_promo_code ?? '';
                        $percentageApplied = $details->new_discount2_percentage ?? '';
                    @endphp

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
                                {{ moneyFormat($details->discount) }}
                                @if($percentageApplied > 0)
                                    <span style="font-size:11px;font-weight:700;color:#94a3b8;margin-left:2px;">
                                        ({{ $percentageApplied }}%)
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Promo code badge --}}
                        @if($promoCodeApplied != '')
                            <div class="discount-divider"></div>
                            <div class="discount-stat discount-stat--fixed">
                                <div class="discount-stat-label">Promo Code</div>
                                <span class="card-chip"
                                      style="background:#fef9c3;color:#854d0e;border:1px solid #fde047;font-weight:700;font-size:11px;">
                                    {{ $promoCodeApplied }}
                                </span>
                            </div>
                        @endif
                    </div>
                    
            @endif
        </div>
    </div>
</div>