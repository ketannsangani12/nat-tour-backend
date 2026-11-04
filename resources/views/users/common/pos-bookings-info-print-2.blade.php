<div class="clearfix" style="font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;">
    @if($logo)
    <div class="mb-2 text-center">
        <img src="{{ $logo }}" alt="" style="width:200;height:125px;">
    </div>
    @endif
    @if($qrCode)
    <div class="mb-2" style="margin-left: 20px;">
        <img src="{{ $qrCode }}" alt="" style="width:200;height:125px;">
    </div>
    @endif

    <p style="font-size:50px;line-height: 1;margin: 0;"><b>{{ $tourOperator }}</b></p>

    @if($tourOpPhoneEmail)
    <p style="word-break: break-all;font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;">{!! $tourOpPhoneEmail !!}</p>
    @endif
</div>
@if($comboBookings)
    @foreach($comboBookings as $comboBooking)

    @include('partials.pos.print._print_booking_info', [ 'comboBooking' => $comboBooking])

    @endforeach
@endif