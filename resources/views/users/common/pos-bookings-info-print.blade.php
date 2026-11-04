<div class="clearfix">
	<div class="mb-2">
	<div style="width: 45%;display: inline-block;">
		<img src="{{ $qrCode }}" alt="" style="width:150px;">
	</div>
		<div style="width: 54%;display: inline-block;text-align: right;margin-top: 30px;">
			@if($logo)
			<img src="{{ $logo }}" alt="" style="width:150px;margin-bottom: 10px;">
			@endif

			<p><b>{{ $tourOperator }}</b></p>

			@if($tourOpPhoneEmail)
			<p>{!! $tourOpPhoneEmail !!}</p>
			@endif
		</div>
	</div>

</div>

<div style="height:2px;background-color:black;margin:6px 0"></div>
@php
$customChargeTotal = 0;
$addOnTotal = 0;
$feeTotal = 0;
$bookingTotal = 0;
@endphp
@foreach($comboBookings as $comboBooking)
@php
$bookingM = $comboBooking['bookingM'];
$bookingT = $comboBooking['bookingT'];
$customChargePrice = $comboBooking['customChargePrice'];
$pricedAddons = $comboBooking['pricedAddons'];
$payLaterAddons = $comboBooking['payLaterAddons'];

if ($customChargePrice['custom_charge_total'] > 0){
$customChargeTotal += $customChargePrice['custom_charge_total'];
}

if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0){
$addOnTotal += $payLaterAddons['addons_price'];
} elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0){
$addOnTotal += $pricedAddons['addons_final_price'];
}

if (isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0){
$feeTotal += $bookingM->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee'];
} else {
$feeTotal += $bookingM->service_commission + $customChargePrice['custom_charge_fees'];
}

if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0){
$bookingTotal += $comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price'];
} elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0){
// comboBooking['bookingTotal'] is the gross, never-decremented-by-refund figure, so add-ons
// must use the matching gross figure here too — not addons_payment_total, which nets out any
// add-on refund and would understate this line by exactly that amount.
$bookingTotal += $comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total'];
} else {
$bookingTotal += $comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'];
}


@endphp
<p><b>Booking #{{ bookingPrefix($bookingM->id) }}</b></p>
<p>{{ $bookingM->custName }}</p>
<p>{!! $custPhoneEmail !!}</p>

<p class="mt-3"><b>{{ $bookingM->tour_package }}</b></p>

<p>{{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}</p>

<div class="separator"></div>

<table class="table table-bordered table-hover mt-3 mb-0 tbl-price">
	<thead>
		<tr class="text-end">
			<th class="text-start">Age Group</th>
			<th>Persons ({{ $bookingM->tourists }})</th>
			@if($bookingM->is_group_booking != '1')
			<th>Rate</th>
			@if($bookingM->package_has_permit)
			<th>Permit Fee</th>
			@endif
			<th>{{ taxLabel() }}</th>
			@endif
			<th>Total</th>
		</tr>
	</thead>

	<tbody>

		@foreach($bookingT as $bt)
		<tr class="text-end">
			<td class="text-start">{{ ($bookingM->is_group_booking == '1') ? 'Person(s)' : $bt->rate_for }}</td>
			<td>{{ $bt->tourists }}</td>
			@if($bookingM->is_group_booking != '1')
			<td>{{ moneyFormat($bt->rate) }}</td>
			@if($bookingM->package_has_permit)
			<td>{{ moneyFormat($bt->permit_fee) }}</td>
			@endif
			<td>{{ moneyFormat($bt->additional_charge) }}</td>
			@endif
			<th>{{ moneyFormat($bt->total) }}</th>
		</tr>
		@endforeach
	</tbody>
	<tfoot>


		@if($bookingM->discount2_value != 0)
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Discount</th>
			<th>-
				@if($bookingM->discount2_percentage != 0)
				({{ str_replace('.00', '', $bookingM->discount2_percentage) }} %)
				@endif

				${{ $bookingM->discount2_value }}
			</th>
		</tr>
		@endif

		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Subtotal</th>
			<th>{{ moneyFormat(sprintf('%.2f', $bookingM->total - $bookingM->service_commission)) }}</th>
		</tr>
		@if($customChargePrice['custom_charge_total'] > 0)
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Custom Charge</th>
			<th>{{ moneyFormat($customChargePrice['custom_charge_total']) }}</th>
		</tr>
		@endif
		@if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Add-ons</th>
			<th class="td-amount">{{ moneyFormat($payLaterAddons['addons_price']) }}</th>
		</tr>
		@elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0)
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Add-ons</th>
			<th>{{ moneyFormat($pricedAddons['addons_final_price']) }}</th>
		</tr>
		@endif
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Booking Fees</th>
			@if (isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0)
			<th>{{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee']) }}</th>
			@else
			<th>{{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees']) }}</th>
			@endif
		</tr>
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Booking Total</th>
			@if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
			<th>{{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price']) }}</th>
			@elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0)
			<th>{{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total']) }}</th>
			@else
			<th>{{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge']) }}</th>
			@endif
		</tr>
		@if ($comboBooking['ageGroup'])
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Refunded Pax</th>
			<th>{!! $comboBooking['ageGroup'] !!}</th>
		</tr>
		@endif
		@if ($comboBooking['refundKeepUs'])
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Non-Refundable Fees</th>
			<th>{{ moneyFormat($comboBooking['refundKeepUs']) }}</th>
		</tr>
		@endif
		@if ($comboBooking['refundAmount'])
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Refund Amount</th>
			<th>{{ moneyFormat($comboBooking['refundAmount']) }}</th>
		</tr>
		@endif
		<tr class="text-end">
			<th colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}">Payment Total</th>
			<th>{{ moneyFormat($comboBooking['paymentTotal'])  }}</th>
		</tr>
	</tfoot>
</table>
@if(end($comboBookings) != $comboBooking)
<div class="separator"></div>
@endif
@endforeach
@if(count($comboBookings) > 1)
<div class="separator"></div>
<table class="table table-bordered table-hover mt-3 mb-0 tbl-price">
	<thead>
		<tr class="text-end">
			<th>Combo Subtotal</th>
			<th>Combo Custom Charge</th>
			<th>Combo Add-ons</th>
			<th>Combo Booking Fees Total</th>
			<th>Combo Booking Total</th>
			<th>Combo Non-Refundable Fees Total</th>
			<th>Combo Refund Total</th>
			<th>Combo Payment Total</th>
		</tr>
	</thead>
	<tbody>
		<tr class="text-end">
			<th>{{ moneyFormat($comboSubtotal) }}</th>
			<th>{{ moneyFormat($customChargeTotal) }}</th>
			<th>{{ moneyFormat($addOnTotal) }}</th>
			<th>{{ moneyFormat($feeTotal) }}</th>
			<th>{{ moneyFormat($bookingTotal) }}</th>
			<th>{{ moneyFormat($comboNonRefundableFeesTotal) }}</th>
			<th>{{ moneyFormat($comboRefundTotal) }}</th>
			<th>{{ moneyFormat($comboPaymentTotal) }}</th>
		</tr>
	</tbody>
</table>
@endif