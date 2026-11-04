@php
	$logo = getLogo($bookingM);
	$tourData = printTourOptCustomer($bookingM);
@endphp
<style>
body { padding: 0px;color:#000;font-size:50px;size: A4 landscape;font-weight:bold;max-width: 96%; }
p { margin-bottom: 0.2rem; }
.tbl-price { font-size: 100%; }
.td-amount { width: 90px; text-align: right; }

@media  print {
body { padding: 0; }
.div-print-btn { display: none; }
}
@media print and (orientation: landscape) {
	.print-area {max-width: 90% !important;}
	.print-area * {font-size: 42px !important;}
}

</style>
<div class="clearfix"  style="font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;">
@if($logo)
	<div class="mb-2 text-center">
		<img src="{{ $logo }}" alt="" style="width:200;height:125px;" >
	</div>
@endif

	<p style="font-size:50px;line-height: 1;margin: 0;"><b>{{ $bookingM->tour_operator }}</b></p>

@if(count($tourData['tourOpt']))
	<p style="word-break: break-all;font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;">{!! implode('<br>', $tourData['tourOpt']) !!}</p>
@endif
</div>

<div style="height:2px;background-color:black;margin:1px 0 1px"></div>

<p style="font-size:50px;line-height: 1;margin: 0;"><b>Booking #{{ bookingPrefix($bookingM->id) }}</b></p>

@if($hasActiveDispute)
<p style="font-size:40px;line-height: 1;margin: 0;color: red;"><b>⚠ PAYMENT DISPUTE ACTIVE</b></p>
@elseif($isDisputed)
<p style="font-size:40px;line-height: 1;margin: 0;color: orange;"><b>⚠ DISPUTE HISTORY</b></p>
@endif

<p style="font-size:50px;line-height: 1;margin: 0;">{{ $bookingM->customer }}</p>
<p style="font-size:50px;line-height: 1;margin: 0;">{!! implode('<br>', $tourData['tourCust']) !!}</p>

<p class="mt-3" style="font-size:50px;line-height: 1;margin: 0;"><b>{{ $bookingM->tour_package }}</b></p>
@if ($bookingM->package_has_slots)
<p style="font-size:50px;line-height: 1;margin: 0;">{{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}</p>
@else
<p style="font-size:50px;line-height: 1;margin: 0;">{{ formatDate($bookingM->date) }}</p>
@endif

<div style="height:2px;background-color:black;margin:1px 0 1px;font-weight:bold;line-height: 1;"></div>

@include('partials.print._print_price_breakdown', [ 'transaction' => $bookingT,
												    'booking'=> $bookingM,
													'payLaterAddons' => $payLaterAddons,
													'pricedAddons' => $pricedAddons,
													'formLabels' => ["tax" => taxLabel()],
												    'style' => 'font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;' ])



@include('partials.print._print_price_total', [	'transaction' => $bookingT,
												'booking'=> $bookingM,
												'payLaterAddons' => $payLaterAddons,
												'pricedAddons' => $pricedAddons,
											   'style'=> 'font-size:50px;color:#000;font-weight:bold;line-height: 1;margin: 0;'])
