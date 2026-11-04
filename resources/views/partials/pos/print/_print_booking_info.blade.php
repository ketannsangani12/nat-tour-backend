@php
		$bookingM = $comboBooking['bookingM'];
        $bookingT = $comboBooking['bookingT'];
        $customChargePrice = $comboBooking['customChargePrice'];
        $pricedAddons = $comboBooking['pricedAddons'];
        $payLaterAddons = $comboBooking['payLaterAddons'];

    @endphp
<div style="height:2px;background-color:black;margin:1px 0 1px"></div>

<p style="font-size:50px;line-height: 1;margin: 0;"><b>Booking #{{ bookingPrefix($bookingM->id) }}</b></p>

{{-- Print-friendly Dispute Marker for POS --}}
@if(isset($disputeInfo) && $disputeInfo)
<div style="border: 2px solid #000; padding: 4px; margin: 4px 0;">
    <p style="margin: 0; font-weight: bold; font-size: 40px; line-height: 1;">
        ⚠️ PAYMENT DISPUTE: {{ strtoupper($disputeInfo['status_label']) }}
    </p>
    <p style="margin: 2px 0 0 0; font-size: 30px; line-height: 1;">
        {{ $disputeInfo['reason_label'] }} | {{ number_format($disputeInfo['amount'] / 100, 2) }} {{ strtoupper($disputeInfo['currency']) }}
    </p>
</div>
@endif

<p style="font-size:50px;line-height: 1;margin: 0;">{{ $bookingM->custName }}</p>
<p style="font-size:50px;line-height: 1;margin: 0;">{!! $custPhoneEmail !!}</p>

<p class="mt-3" style="font-size:50px;line-height: 1;margin: 0;"><b>{{ $bookingM->tour_package }}</b></p>

<p style="font-size:50px;line-height: 1;margin: 0;">{{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}</p>

<div style="height:2px;background-color:black;margin:1px 0 1px;font-weight:bold;line-height: 1;"></div>

@include('partials.print._print_price_breakdown', [ 'transaction' => $bookingT,
												    'booking'=> $bookingM,
													'payLaterAddons' => $payLaterAddons,
													'pricedAddons' => $pricedAddons,
													'formLabels' => ["tax" => taxLabel()],
												    'style' => 'font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;' ])

 @include('partials.pos.print._print_price_total', [
                                                'bookingM'=> $bookingM,
												'payLaterAddons' => $payLaterAddons,
												'pricedAddons' => $pricedAddons,
                                                'customChargePrice' => $customChargePrice,
                                                'ageGroup' => $comboBooking['ageGroup'],
                                                'refundKeepUs' => $comboBooking['refundKeepUs'],
                                                'refundAmount' => $comboBooking['refundAmount'],
                                                'paymentTotal' => $comboBooking['paymentTotal'],
											    'style'=> 'font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;'])                                                   