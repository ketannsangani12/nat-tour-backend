{{--
    Cost + Fees = Total chip row (e.g. "Cost $36.00 + Fees $3.53 = Total $39.53").

    Visually matches _renderAddonsSummaryHeader() in
    public/js/user-common/addons/tour-booking-addons.js exactly (same pill colors/markup) —
    that one is built in JS because the "Booking Add-ons" section it lives in is populated
    over AJAX. This component is for sections that are plain server-rendered Blade (no
    AJAX re-render), so the same look doesn't need a JS reimplementation — e.g. the
    "Custom Charges" section in tour-booking-info.blade.php, computed from
    CustomChargeBookingService::customChargePrice()'s already-correct numbers.

    If tour-booking-addons.js's chip styling/behavior ever changes, update both this file
    and _renderAddonsSummaryHeader() together — there's no way to share markup between a
    Blade component and JS string-building.
--}}
@props([
    'cost' => 0,
    'fees' => 0,
    'total' => 0,
])

@php
    $chipStyle = 'display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:9999px;font-size:0.78rem;vertical-align:middle;';
@endphp

@if($total > 0)
    &nbsp;
    @if($fees > 0)
        <span style="{{ $chipStyle }}border:1px solid #E2E8F0;background:#F8FAFC;">
            <span style="color:#64748B;font-weight:500;">Cost</span>
            <span style="color:#1e293b;font-weight:700;">{{ moneyFormat($cost) }}</span>
        </span>
        <span style="color:#94A3B8;font-size:0.8rem;vertical-align:middle;margin:0 3px;">+</span>
        <span style="{{ $chipStyle }}border:1px solid #E2E8F0;background:#F8FAFC;">
            <span style="color:#64748B;font-weight:500;">Fees</span>
            <span style="color:#1e293b;font-weight:700;">{{ moneyFormat($fees) }}</span>
        </span>
        <span style="color:#94A3B8;font-size:0.8rem;vertical-align:middle;margin:0 3px;">=</span>
    @endif
    <span style="{{ $chipStyle }}border:1px solid #BFDBFE;background:#EFF6FF;">
        <span style="color:#1D4ED8;font-weight:500;">Total</span>
        <span style="color:#1e40af;font-weight:700;">{{ moneyFormat($total) }}</span>
    </span>
@endif
