{{-- Itemized add-on breakdown — shared by _payment.blade.php's standard txn-card (sibling
     placement, below the card) and its Pay Later sys-card (nested inside sys-card-body, since
     that card has no separate breakdown box of its own to sit below). --}}
@if ($hist->charge_type == 'addons' && (!empty($details->addons) || !empty($details->removed_addons)))
    @php
        $hasNetBreakdown = !empty($details->removed_addons);
        $grossAddedBase  = collect($details->addons ?? [])->sum('price');
        $offsetBase      = $hasNetBreakdown ? collect($details->removed_addons)->sum('price') : 0;
    @endphp
    @if ($hasNetBreakdown)
        {{-- Net charge breakdown: added items offset by removed items. Either
             side can legitimately be empty (e.g. an all-'updated' net-charge edit
             where only one field's price actually moved) — guard each section
             independently so a real, non-empty side is never hidden just because
             its counterpart happens to have nothing to show (mirrors
             _refund.blade.php's equivalent net-refund breakdown). --}}
        <div class="addon-pills addon-pills--net">
            @if (!empty($details->addons))
            <div class="addon-pills-section-label addon-pills-section-label--added">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="5" fill="#16a34a"/></svg>
                Added
            </div>
            @foreach ($details->addons as $addon)
                <div class="addon-pill">
                    <div class="addon-pill-name">
                        {{ $addon->name }}
                        @if (!empty($addon->quantity) && $addon->quantity > 1)
                            <span class="addon-qty-badge">×{{ $addon->quantity }}</span>
                        @endif
                    </div>
                    <div class="addon-pill-amounts">
                        <span class="addon-pill-price">{{ moneyFormat($addon->price) }}</span>
                        @if (!empty($addon->fee) && $addon->fee > 0)
                            <span class="addon-pill-fee">+{{ moneyFormat($addon->fee) }} fee</span>
                        @endif
                    </div>
                </div>
            @endforeach
            @endif
            {{-- No !empty($details->removed_addons) guard needed here —
                 $hasNetBreakdown IS !empty($details->removed_addons), so it's
                 already guaranteed non-empty by virtue of being inside this @if. --}}
            <div class="addon-pills-section-label addon-pills-section-label--removed">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="5" fill="#dc2626"/></svg>
                Removed (offset)
            </div>
            @foreach ($details->removed_addons as $addon)
                <div class="addon-pill addon-pill--removed">
                    <div class="addon-pill-name">
                        {{ $addon->name }}
                        @if (!empty($addon->quantity) && $addon->quantity > 1)
                            <span class="addon-qty-badge">×{{ $addon->quantity }}</span>
                        @endif
                    </div>
                    <div class="addon-pill-amounts">
                        <span class="addon-pill-price addon-pill-price--credit">−{{ moneyFormat($addon->price) }}</span>
                    </div>
                </div>
            @endforeach
            <div class="addon-pills-footer addon-pills-footer--net">
                <span>
                    Net Charged
                    <span class="addon-net-calc">({{ moneyFormat($grossAddedBase) }} − {{ moneyFormat($offsetBase) }})</span>
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
                        <span class="addon-pill-price">{{ moneyFormat($addon->price) }}</span>
                        @if (!empty($addon->fee) && $addon->fee > 0)
                            <span class="addon-pill-fee">+{{ moneyFormat($addon->fee) }} fee</span>
                        @endif
                    </div>
                </div>
            @endforeach
            {{-- Pay Later: nothing has actually been collected, so a "Total $0.00" footer would
                 read as if $0 was genuinely charged for these add-ons — same reasoning as the
                 sys-card replacing "+ $0.00" up top. Skip it; each pill above still shows its
                 real price. --}}
            @if (!($isPendingAddonCharge ?? false) && ($hist->platform_fees > 0 || count($details?->addons ?? []) > 1))
                <div class="addon-pills-footer">
                    <span>Total</span>
                    <span>{{ moneyFormat($hist->ticket_cost + $hist->platform_fees) }}</span>
                </div>
            @endif
        </div>
    @endif
@endif
