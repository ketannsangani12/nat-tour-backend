<div class="redeem-ticket-section" style="border-radius: 7px;">
    @if(count($dayBookings[$i]->package_list) > 0)
        @foreach($dayBookings[$i]->package_list as $key => $package)
            <button style="cursor: {{ (($package->redeemed == $dayBookings[$i]->tourists) ? 'unset' : 'pointer') }};" class="{{ $package->class }} redeem_ticket_{{ $package->id }}" @if($package->redeemed != $dayBookings[$i]->tourists + $dayBookings[$i]->infants) onclick="updateRedeemTicketStatus('{{ $dayBookings[$i]->booking_id }}','{{ $dayBookings[$i]->tour_package_id }}','{{ $package->id }}','{{ $package->redeemed }}','{{ $package->not_redeemed }}')" @endif id="all_redeem_ticket_{{ $dayBookings[$i]->booking_id }}_{{ $package->id }}" title="{{ $package->name }}">
                <span id="redeem_ticket_{{ $dayBookings[$i]->booking_id }}_{{ $package->id }}">{{ $package->redeemed }}</span>/{{ $dayBookings[$i]->tourists + $dayBookings[$i]->infants }} {{ $package->name }}
            </button>
        @endforeach
    @endif
</div>