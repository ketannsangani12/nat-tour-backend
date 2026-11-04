<style>
    /* ── Combo Hub Card ── */
    .hub-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
    .hub-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .hub-header-left { display: flex; align-items: center; gap: 12px; }
    .hub-icon { width: 38px; height: 38px; border-radius: 9px; background: #0d9488; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .hub-title { font-size: 15px; font-weight: 700; color: #0f172a; }
    .hub-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
    .hub-badges { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
    .badge-combo { padding: 3px 10px; background: #0d9488; color: #fff; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-confirmed { padding: 3px 10px; background: #d4f5de; color: #1e8c42; border: 1px solid #96d8aa; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-pending { padding: 3px 10px; background: #fef9c3; color: #854d0e; border: 1px solid #fde047; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-failed { padding: 3px 10px; background: #fde8e8; color: #c43030; border: 1px solid #fca5a5; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .exp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; padding: 20px 24px; }
    .exp-card { border-radius: 10px; padding: 14px; background: #fff; position: relative; }
    .exp-card.exp-green { border: 1.5px solid #059669; }
    .exp-card.exp-purple { border: 1.5px solid #7c3aed; }
    .exp-card.exp-blue { border: 1.5px solid #2563eb; }
    .exp-card.exp-orange { border: 1.5px solid #ea580c; }
    .exp-card.exp-pink { border: 1.5px solid #db2777; }
    .exp-card-badge { position: absolute; top: -1px; right: -1px; color: #fff; font-size: 9px; font-weight: 800; padding: 2px 8px; border-radius: 0 9px 0 6px; letter-spacing: .4px; text-transform: uppercase; }
    .exp-card-badge.exp-green { background: #059669; }
    .exp-card-badge.exp-purple { background: #7c3aed; }
    .exp-card-badge.exp-blue { background: #2563eb; }
    .exp-card-badge.exp-orange { background: #ea580c; }
    .exp-card-badge.exp-pink { background: #a21caf; }
    .exp-card-top { display: flex; align-items: center; gap: 8px; margin-bottom: 10px;margin-top: 20px; }
    .exp-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .exp-dot.exp-green { background: #059669; }
    .exp-dot.exp-purple { background: #7c3aed; }
    .exp-dot.exp-blue { background: #2563eb; }
    .exp-dot.exp-orange { background: #d97706; }
    .exp-dot.exp-pink { background: #a21caf; }
    .exp-name { font-size: 13px; font-weight: 700; color: #0f172a; flex: 1; min-width: 0; }
    .exp-ticket { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 800; white-space: nowrap; text-decoration: none; }
    .exp-ticket.exp-green { background: #f0fdf4; color: #059669; border: 1px solid #86efac; }
    .exp-ticket.exp-purple { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }
    .exp-ticket.exp-blue { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .exp-ticket.exp-orange { background: #fffbeb; color: #d97706; border: 1px solid #fcd34d; }
    .exp-ticket.exp-pink { background: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8; }
    .exp-detail { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #64748b; margin-bottom: 5px; }
    .exp-financial { display: flex; gap: 10px; margin-top: 10px; padding: 8px 10px; background: #f8fafc; border-radius: 6px; font-size: 11px; color: #64748b; flex-wrap: wrap; }
    .exp-financial b { color: #334155; }
    .exp-view-link { display: inline-flex; align-items: center; justify-content: center; gap: 4px; font-size: 11px; font-weight: 700; margin-top: 8px; padding: 6px 10px; border-radius: 6px; border: 1.5px solid; text-decoration: none; width: 100%; box-sizing: border-box; }
    .exp-view-link.exp-green { color: #059669; border-color: #059669; }
    .exp-view-link.exp-purple { color: #7c3aed; border-color: #7c3aed; }
    .exp-view-link.exp-blue { color: #2563eb; border-color: #2563eb; }
    .exp-view-link.exp-orange { color: #ea580c; border-color: #ea580c; }
    .exp-view-link.exp-pink { color: #a21caf; border-color: #a21caf; }
    /* ── Currently viewing highlight ── */
    .exp-grid { align-items: center; }
    .exp-card--current { border: 2px solid #f59e0b !important; background: #fffbeb; transform: scale(1.05); z-index: 1; box-shadow: 0 8px 28px rgba(245,158,11,0.22), 0 2px 8px rgba(0,0,0,0.07); }
    .exp-card--current .exp-dot { background: #f59e0b !important; }
    .exp-current-banner { margin: -14px -14px 12px; background: #f59e0b; color: #fff; font-size: 10px; font-weight: 800; padding: 6px 12px; display: flex; align-items: center; gap: 6px; letter-spacing: .5px; text-transform: uppercase; border-radius: 8px 8px 0 0; }
    .exp-card--current .exp-card-badge { top: 27px; border-radius: 6px 0 0 6px; }
    .exp-card--current .exp-financial b { color: #d97706; }
    .exp-view-btn--current { display: flex; align-items: center; justify-content: center; gap: 4px; font-size: 11px; font-weight: 700; margin-top: 8px; padding: 6px 10px; border: 1.5px solid #f59e0b; border-radius: 6px; color: #d97706; background: #fff; width: 100%; box-sizing: border-box; }
    .hub-total { padding: 12px 20px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .hub-total-label { font-size: 12px; font-weight: 600; color: #64748b; }
    .hub-total-amount { font-size: 15px; font-weight: 800; color: #0f172a; }
    .hub-current-share { margin-left: auto; font-size: 12px; color: #64748b; white-space: nowrap; }
    .hub-current-share b { color: #d97706; }
    /* Compact mode: non-parent, non-current cards show name in card color */
    .exp-card--compact.exp-purple .exp-name { color: #7c3aed; }
    .exp-card--compact.exp-blue .exp-name { color: #2563eb; }
    .exp-card--compact.exp-orange .exp-name { color: #d97706; }
    .exp-card--compact.exp-pink .exp-name { color: #a21caf; }
</style>

@php
    $comboBookingsData ??= [];
    $trans             ??= null;
    $transF            ??= null;

    if (empty($comboBookingsData)) {
        return;
    }

    $hubData = resolveComboHubCardData(
        $comboBookingsData,
        $bookingM,
        $trans,
        $transF,
        $routePrefix ?? null
    );

    $hubRoutePrefix      = $hubData['hubRoutePrefix'];
    $hubPaymentHistories = $hubData['hubPaymentHistories'];
    $hubFinancials       = $hubData['hubFinancials'];
    $hubParent           = $hubData['hubParent'];
    $hubChildren         = $hubData['hubChildren'];
    $hubCardNumber       = $hubData['hubCardNumber'];
    $hubPaymentType      = $hubData['hubPaymentType'];
    $hubTotalTicket      = $hubData['hubTotalTicket'];
    $hubTotalAddons      = $hubData['hubTotalAddons'];
    $hubTotalFees        = $hubData['hubTotalFees'];
    $hubTotalAmount      = $hubData['hubTotalAmount'];
    $hubTotalGuests      = $hubData['hubTotalGuests'];
    $hubParentLabel      = $hubData['hubParentLabel'];
    $hubChildrenLabel    = $hubData['hubChildrenLabel'];
    $hubBookedDate       = $hubData['hubBookedDate'];
    $hubStatus           = $hubData['hubStatus'];
    $hubStatusClass      = $hubData['hubStatusClass'];
    $hubColorKeys        = $hubData['hubColorKeys'];
    $hubColorHexMap      = $hubData['hubColorHexMap'];
    $hubOrdered          = $hubData['hubOrdered'];

    // Compact mode: shorten labels when 5+ bookings fill the grid
    $hubIsCompact            = count($comboBookingsData) >= 5;
    $hubCurrentBookingTotal  = null;
@endphp

<div class="hub-card">
    {{-- Header --}}
    <div class="hub-header">
        <div class="hub-header-left">
            <div class="hub-icon">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M7 13a4 4 0 1 0 0-6M13 7a4 4 0 1 0 0 6" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M8.5 10h3" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <div class="hub-title">
                    Multi-Booking Package{{ !empty($bookingM->order_id) ? ' #' . bookingPrefix($bookingM->order_id) : '' }}
                </div>
                <div class="hub-sub">
                    @if ($hubParentLabel) Parent {{ $hubParentLabel }} · @endif
                    @if ($hubChildrenLabel) {{ $hubChildren->count() === 1 ? 'Child' : 'Children' }} {{ $hubChildrenLabel }} · @endif
                    {{ $hubTotalGuests }} {{ Str::plural('guest', $hubTotalGuests) }} · {{ $hubBookedDate }}
                </div>
            </div>
        </div>
        <div class="hub-badges">
            <span class="badge-combo">Combo</span>
            <span class="badge-{{ $hubStatusClass }}">{{ $hubStatus }}</span>
        </div>
    </div>

    {{-- Experiences grid --}}
    <div class="exp-grid">
        @php $hubChildIdx = 0; @endphp
        @foreach ($hubOrdered as $hubEntry)
            @php
                if ($hubEntry['is_parent']) {
                    $hubColorKey = 'exp-green';
                } else {
                    $hubColorKey = $hubColorKeys[$hubChildIdx % count($hubColorKeys)];
                    $hubChildIdx++;
                }
                $hubHex       = $hubColorHexMap[$hubColorKey];
                $hubIsCurrent = $hubEntry['id'] == $bookingM->id || ($hubEntry['is_current'] ?? false);
                $hubPhist     = $hubPaymentHistories->get($hubEntry['id']);
                $hubPhDet     = $hubPhist ? (is_string($hubPhist->details) ? json_decode($hubPhist->details) : $hubPhist->details) : null;
                $hubFin       = $hubFinancials[$hubEntry['id']] ?? ['ticket' => 0, 'addons' => 0, 'fees' => 0, 'total' => 0, 'paid' => false];
                $hubTicket    = $hubFin['ticket'];
                $hubAddons    = $hubFin['addons'] ?? 0;
                $hubFees      = $hubFin['fees'];
                $hubTotal     = $hubFin['total'];
                $hubPhCard    = !empty($hubPhDet->card_number) ? str_pad($hubPhDet->card_number, 4, '0', STR_PAD_LEFT) : null;
                $hubPhType    = $hubPhDet->payment_type ?? null;
                $hubPhMethod  = $hubFin['paid'] ? ($hubPhDet->payment_method ?? 'Full Payment') : 'Pay Later';

                if ($hubIsCurrent) {
                    $hubCurrentBookingTotal = $hubTotal;
                }

                $hubCardClasses = "exp-card {$hubColorKey}"
                    . ($hubIsCurrent ? ' exp-card--current' : '')
                    . ($hubIsCompact && !$hubIsCurrent && !$hubEntry['is_parent'] ? ' exp-card--compact' : '');
            @endphp
            <div class="{{ $hubCardClasses }}">

                {{-- Currently viewing banner --}}
                @if ($hubIsCurrent)
                    <div class="exp-current-banner">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        {{ $hubIsCompact ? 'Currently Viewing' : 'Currently Viewing This Booking' }}
                    </div>
                @endif

                {{-- Badge: absolute top-right; pushed below banner on current card via CSS --}}
                @if ($hubEntry['is_parent'])
                    <div class="exp-card-badge exp-green">
                        {{ $hubIsCompact ? 'Parent' : 'Parent Booking' }} #{{ bookingPrefix($hubEntry['id']) }}
                    </div>
                @else
                    <div class="exp-card-badge {{ $hubColorKey }}">
                        {{ $hubIsCompact ? 'Child' : 'Child Booking' }} #{{ bookingPrefix($hubEntry['id']) }}
                    </div>
                @endif

                <div class="exp-card-top">
                    <span class="exp-dot {{ $hubIsCurrent ? '' : $hubColorKey }}"></span>
                    <span class="exp-name">{{ $hubEntry['packageName'] }}</span>
                    
                </div>

                @if (!empty($hubEntry['tour_date_time']))
                    <div class="exp-detail">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                        <span>{{ $hubEntry['tour_date_time'] }}</span>
                    </div>
                @endif

                <div class="exp-detail">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"/></svg>
                    <span>
                        {{ $hubEntry['tourists'] }} {{ Str::plural('guest', $hubEntry['tourists']) }}
                        · {{ $hubPhMethod }}
                        @if ($hubFin['paid'] && $hubPhType == 'Card' && $hubPhCard)
                            · {{ $hubPhType }} ···· {{ $hubPhCard }}
                        @elseif ($hubFin['paid'] && $hubPhType)
                            · {{ $hubPhType }}
                        @endif
                    </span>
                </div>

                <div class="exp-financial">
                    <span>Ticket <b>{{ moneyFormat($hubTicket) }}</b></span>
                    @if ($hubAddons > 0)
                        <span>·</span>
                        <span>Add-ons <b>{{ moneyFormat($hubAddons) }}</b></span>
                    @endif
                    <span>·</span>
                    <span>Fees <b>{{ moneyFormat($hubFees) }}</b></span>
                    <span>·</span>
                    <span>Total <b>{{ moneyFormat($hubTotal) }}</b></span>
                </div>

                @if ($hubIsCurrent)
                    <div class="exp-view-btn--current">
                        Currently Viewing{{ $hubIsCompact ? '' : ' #' . bookingPrefix($hubEntry['id']) }}
                    </div>
                @else
                    <a class="exp-view-link {{ $hubColorKey }}"
                       href="{{ route(prefixedRouteName("{$hubRoutePrefix}booking.show"), ['booking' => $hubEntry['id']]) }}"
                       target="_blank">
                        {{ $hubIsCompact ? 'View' : 'View Booking' }} #{{ bookingPrefix($hubEntry['id']) }}
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5h6M5.5 2.5L8 5l-2.5 2.5" stroke="{{ $hubHex }}" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Combined total --}}
    <div class="hub-total">
        <span class="hub-total-label">Combined Total ({{ count($comboBookingsData) }} {{ Str::plural('experience', count($comboBookingsData)) }}):</span>
        <span style="font-size:12px;color:#64748b;">{{ moneyFormat($hubTotalTicket) }} ticket + @if ($hubTotalAddons > 0){{ moneyFormat($hubTotalAddons) }} add-ons + @endif{{ moneyFormat($hubTotalFees) }} fees =</span>
        <span class="hub-total-amount">{{ moneyFormat($hubTotalAmount) }} total</span>
        @if ($hubPaymentType == 'Card' && $hubCardNumber)
            <span class="card-chip" style="margin-left:4px;">
                <svg width="14" height="10" viewBox="0 0 14 10" fill="none"><rect x=".5" y=".5" width="13" height="9" rx="1.5" stroke="#cbd5e1"/><path d="M0 3.5h14" stroke="#cbd5e1" stroke-width="1.2"/></svg>
                ···· {{ $hubCardNumber }}
            </span>
        @elseif ($hubPaymentType)
            <span class="card-chip" style="{{ paymentTypeChipStyle($hubPaymentType) }}">{{ $hubPaymentType }}</span>
        @endif
        @if ($hubCurrentBookingTotal !== null)
            <span class="hub-current-share">
                This booking: <b>{{ moneyFormat($hubCurrentBookingTotal) }}</b> of {{ moneyFormat($hubTotalAmount) }}
            </span>
        @endif
    </div>
</div>
