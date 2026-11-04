@php
$logo = $tourOpPhoneEmail = $custPhoneEmail = '';

if($bookingM->logo != '')
if(Storage::disk('s3')->exists('images/tour-operator/' . $bookingM->tenant_id . '/logo/' . $bookingM->logo))
$logo = Storage::disk('s3')->url('images/tour-operator/' . $bookingM->tenant_id . '/logo/' . $bookingM->logo);

if($bookingM->tour_op_phone != '' && $bookingM->tour_op_email != '')
$tourOpPhoneEmail = e($bookingM->tour_op_phone) . '<br>' . e($bookingM->tour_op_email);
elseif($bookingM->tour_op_email != '')
$tourOpPhoneEmail = e($bookingM->tour_op_email);
elseif($bookingM->tour_op_phone != '')
$tourOpPhoneEmail = e($bookingM->tour_op_phone);

if($bookingM->cust_phone != '' && $bookingM->cust_email != '')
$custPhoneEmail = e($bookingM->cust_phone) . '<br>' . e($bookingM->cust_email);
elseif($bookingM->cust_email != '')
$custPhoneEmail = e($bookingM->cust_email);
elseif($bookingM->cust_phone != '')
$custPhoneEmail = e($bookingM->cust_phone);
@endphp

<div class="clearfix">
	@if($logo)
	<div class="mb-2">
		<img src="{{ $logo }}" alt="" style="width:150px">
	</div>
	@endif

	<p><b>{{ $bookingM->tour_operator }}</b></p>

	@if($tourOpPhoneEmail)
	<p>{!! $tourOpPhoneEmail !!}</p>
	@endif
</div>

<div style="height:2px;background-color:black;margin:6px 0"></div>

<p><b>Booking #{{ bookingPrefix($bookingM->id) }}</b></p>

{{-- Print-friendly Dispute Marker --}}
@if(isset($disputeInfo) && $disputeInfo)
<div style="border: 2px solid #dc2626; background-color: #fef2f2; padding: 8px; margin: 8px 0; border-radius: 4px;">
    <p style="margin: 0; font-weight: bold; color: #dc2626; font-size: 12px;">
        ⚠️ PAYMENT DISPUTE: {{ strtoupper($disputeInfo['status_label']) }}
    </p>
    <p style="margin: 2px 0 0 0; font-size: 10px; color: #7f1d1d;">
        Reason: {{ $disputeInfo['reason_label'] }} | Amount: {{ number_format($disputeInfo['amount'] / 100, 2) }} {{ strtoupper($disputeInfo['currency']) }}
        @if($disputeInfo['evidence_due_by'])
            | Due: {{ $disputeInfo['evidence_due_by']->format('M j, Y') }}
        @endif
    </p>
</div>
@endif

<p>{{ $bookingM->customer }}</p>
<p>{!! $custPhoneEmail !!}</p>

<p class="mt-3"><b>{{ $bookingM->tour_package }}</b></p>
@if ($bookingM->package_has_slots)
<p>{{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}</p>
@else
<p>{{ formatDate($bookingM->date) }}</p>
@endif
<div class="separator"></div>

<table class="table table-bordered table-hover mt-3 mb-0 tbl-price">
	<thead>
		<tr class="text-end">
			<th class="text-start">Age Group</th>
			<th>Persons ({{ $bookingM->tourists + $bookingM->infants }})</th>
			@if($bookingM->is_group_booking != '1')
			<th>Rate</th>
			@if($bookingM->package_has_permit)
			<th>Permit Fee</th>
			@endif
			@if($bookingM->is_partner_booking)
			<th>Partner Fee</th>
			@endif
			<th>{{ taxLabel() }}</th>
			@endif
			<th>Total</th>
		</tr>
	</thead>

	<tbody>
		@php
		$promoDiscount = 0;

		if ($bookingM->discount2_percentage > 0) {
		$promoDiscount = str_replace('.00', '', $bookingM->discount2_value) . '%';
		} elseif ($bookingM->discount2_value > 0) {
		$promoDiscount = currencyFormation(false, true) . $bookingM->discount2_value;
		}

		$totalBeforeServiceCommission = $bookingM->total - $bookingM->service_commission;
		$serviceCommissionPercentage = 0;
		if ($totalBeforeServiceCommission != 0) {
		$serviceCommissionPercentage = round($bookingM->service_commission * 100 / $totalBeforeServiceCommission, 2);
		}

		$totalSubtotal = 0;
		$amount = 0;
		$refund_keep_us = 0;
		$deletedAgeGroup = [];

		if (!empty($trans)) {
		$paidF = $paymentOnlyCheck;
		} else {
		$paidF = 0;
		}

		if (!empty($TourBookingHistory)) {
		foreach ($TourBookingHistory as $BookingHistory) {
		$details = json_decode($BookingHistory->details);

		if (isset($details->refund_type)) {
		// A "Don't Refund" row records something removed with nothing given back — it must not
		// count toward the refunded total. Add-ons store the internal key "dont_refund", but custom
		// charge removals store the display label "Don't Refund", so both must be excluded.
		if (!in_array(strtolower($details->refund_type), [\App\Constants\Constants::ADDONS_DONT_REFUND, "don't refund"], true)) {
		$amount += $details->amount;

		if (isset($details->deleted)) {
		if (is_array($details->deleted)) {
		foreach ($details->deleted as $look) {
		$deletedAgeGroup[] = $look;
		}
		} else {
		$deletedAgeGroup[] = $details->deleted;
		}
		}
		}
		}

		$details->refund_keep_us = $details->refund_keep_us ?? 0;
		if (isset($details->refund_type) && strtolower($details->refund_type) !== "partial refund") {
		$refund_keep_us += $details->refund_keep_us;
		}

		$checkAmount = json_decode($HistoryPaymentTime->details);
		$paidF = $paymentOnlyCheck - $amount;

		if ($checkAmount->amount == $amount) {
		$paidF = 0;
		}
		}
		}

		$resultArray = [];
		if (count($deletedAgeGroup)) {
		$personCount = [];
		foreach ($deletedAgeGroup as $entry) {
		// Get the prefix text "Adults (Ages 13 & Up) person"
		$prefix = substr($entry, 0, strpos($entry, "person"));

		// Extract the person number from the entry and convert to integer
		$personNumber = intval(substr($entry, strrpos($entry, " ") + 1));

		// Check if the entry with the same prefix exists in the person count array
		if (!isset($personCount[$prefix])) {
		// Create a new entry in the person count array
		$personCount[$prefix] = 0;
		}

		// Increment the sum of person numbers for the existing entry
		$personCount[$prefix] += $personNumber;
		}

		// Convert the person count array to the desired format
		foreach ($personCount as $prefix => $count) {
		$resultArray[] = $prefix . " " . $count;
		}
		}

		if ($bookingM->status == "Cancelled") {
		if (count($resultArray) > 0) {
		foreach ($resultArray as $key => $ar) {
		$rate = substr($ar, 0, strripos($ar, " "));
		$rateNumber = intval(substr($ar, strrpos($ar, " ") + 1));
		$ratespacetrim = rtrim($rate);

		foreach ($bookingT as $bt) {
		if ($ratespacetrim == $bt->rate_for) {
		$totaltourists = $rateNumber + $bt->tourists;
		unset($resultArray[$key]);
		}

		if (!empty($totaltourists)) {
		$resultArray[] = $bt->rate_for . " " . $totaltourists;
		} else {
		$resultArray[] = $bt->rate_for . " " . $bt->tourists;
		}
		}
		}
		} else {
		foreach ($bookingT as $bt){
		$resultArray[] = $bt->rate_for . " " . ($bt->infants > 0 ? $bt->infants : $bt->tourists);
		}
		}
		}

		$ageGroup = (!empty($resultArray)) ? implode("<br>", $resultArray) : null;

		$partialAmount = 0;
		if ($paidF >= 1 && isset($refundType) && $refundType === 'partial_refund') {
		if ($bookingM->service_commission > 0) {
		$stripeFeesPercentage = $paidF * $bookingT[0]->stripe_fees_percentage / 100;
		$partialAmount = round(($paidF - $stripeFeesPercentage) / 2, 2);
		} else {
		$partialAmount = $paidF / 2;
		}
		}
		@endphp

		@foreach($bookingT as $bt)
		@php

		if($bookingM->is_group_booking == '1') {
		$subtotal = $bt->rate + $bt->additional_charge + $bt->partner_fees_amount;
		$totalSubtotal += $subtotal;
		} else {
		$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee + $bt->partner_fees_amount;
		$per = $rate * ($bt->additional_tax_percentage + $bt->processing_charge_percentage) / 100;
		$subtotal = $rate + $per;
		$totalSubtotal += $subtotal * $bt->tourists;
		}
		
		@endphp

		@if($bt->tourists != 0 || $bt->infants != 0)
		<tr class="text-end">
			<td class="text-start">{{ ($bookingM->is_group_booking == '1') ? 'Person(s)' : $bt->rate_for }}</td>
			<td>{{ ($bt->infants > 0 ? $bt->infants : $bt->tourists) }}</td>
			@if($bookingM->is_group_booking != '1')
			<td>{{ moneyFormat($bt->rate) }}</td>
			@if($bookingM->package_has_permit)
			<td>{{ moneyFormat($bt->permit_fee) }}</td>
			@endif
			@if($bookingM->is_partner_booking)
			<td>{{ moneyFormat($bt->partner_fees_amount) }}</td>
			@endif
			<td>{{ moneyFormat($bt->additional_charge) }}</td>
			@endif
			<td>{{ moneyFormat($bt->total) }}</td>
		</tr>
		@endif
		@endforeach
	</tbody>
	<tfoot>
	@php
			if($bookingM->is_group_booking)
			{
			$colSpan = 2;
			} else if($bookingM->package_has_permit == 1 && $bookingM->is_partner_booking == 1) 
			{
			$colSpan = 6;
			}
			else if($bookingM->package_has_permit == 1 && $bookingM->is_partner_booking != 1) 
			{
			$colSpan = 5;
			} else {
			$colSpan = 4;
			}
			
			@endphp

		@if($bookingM->discount2_value != 0)
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Discount</th>
			<th>-
				@php
				if($bookingM->discount2_percentage != 0) {
				echo '(' . str_replace('.00', '', $bookingM->discount2_percentage) . '%)';
				}

				echo currencyFormation(false, true) . $bookingM->discount2_value;
				@endphp
			</th>
		</tr>
		@endif
		<tr class="text-end">
			<th colspan="{{ $colSpan}}">Subtotal</th>
			<th>
				@if (($totalSubtotal - $bookingM->discount2_value) >= 0)
				{{ moneyFormat($totalSubtotal - $bookingM->discount2_value) }}
				@else
				{{ moneyFormat(sprintf('%.2f', $bookingM->total)) }}
				@endif
			</th>
		</tr>
		
		{{-- addons_gross_price (not addons_final_price) to match tour-booking-info.blade.php's
		already-correct "Add-ons:" row — final_price nets a field's charge against ANY later
		'refunded' removal row for that same add-on slot, even one that was never actually paid
		(status='open' at creation, then removed via a "Don't Refund" path before ever being
		charged), which can drive it negative and hide a genuinely-paid add-on entirely. --}}
		@if ((isset($pricedAddons['addons_gross_price']) && $pricedAddons['addons_gross_price'] > 0) || (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0))
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Add-ons</th>
			<th>{{ (isset($pricedAddons['addons_gross_price']) && $pricedAddons['addons_gross_price'] > 0) ? moneyFormat($pricedAddons['addons_gross_price']) : moneyFormat($payLaterAddons['addons_price']) }}</th>
		</tr>
		@endif
		@if(isset($customChargePrice['custom_charge_total']) && $customChargePrice['custom_charge_total'] > 0)
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Custom Charge</th>
			<th>{{ moneyFormat($customChargePrice['custom_charge_total']) }}</th>
		</tr>
		@endif
		@if(isset($tipAmounts['tip_amount']) && $tipAmounts['tip_amount'] > 0)
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Tip</th>
			<th>{{ moneyFormat($tipAmounts['tip_amount']) }}</th>
		</tr>
		@endif
		<tr class="text-end">
			
			<th colspan="{{ $colSpan }}">Booking Fees</th>
			@if ((isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0) || (isset($customChargePrice['custom_charge_fees']) && $customChargePrice['custom_charge_fees'] > 0) || (isset($tipAmounts['tip_fees']) && $tipAmounts['tip_fees'] > 0))
			<th>{{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee'] + $tipAmounts['tip_fees']) }}</th>
			@else
			<th>{{ moneyFormat($bookingM->service_commission) }}</th>
			@endif
		</tr>
		@php
			if(isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0) {
				$addOnAmount = $payLaterAddons['addons_price'];
			} elseif (isset($pricedAddons['addons_payment_total']) && $pricedAddons['addons_payment_total'] > 0) {
				$addOnAmount = $pricedAddons['addons_payment_total'];
			} else {
				$addOnAmount = 0;
			}

			if(empty($trans)) {
				$overallCustomCharge = $customChargePrice['custom_charge_total'] ?? 0;
			} else {
				$overallCustomCharge = $customChargePrice['overall_custom_charge'] ?? 0;
			}

			$tipTotal = $tipAmounts['tip_total'] ?? 0;	
		@endphp
		@if((!empty($trans) && $transP->payment_type == 1) || (!empty($trans) && ($transP->payment_type == 0 || $transP->payment_type=='') && $paymentTransCount>1))
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Booking Total</th>
			{{-- booking_total is the gross tour-side total ever charged (never decremented on
			refund by design), so add-ons must use the matching gross figure here too — not
			$addOnAmount (addons_payment_total), which nets out any add-on refunds and would
			understate this line by exactly the refunded amount. Matches
			tour-booking-info.blade.php's equivalent "Booking Total" row. --}}
			<th>{{ moneyFormat($bookingM->booking_total + $overallCustomCharge + $pricedAddons['addons_gross_total'] + $tipTotal) }}</th>
		</tr>
		@elseif(!empty($checkAmount))
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Booking Total</th>
			<th>{{ moneyFormat($checkAmount->amount + $overallCustomCharge + $pricedAddons['addons_gross_total'] + $totalDiscountPayment + $tipTotal) }}</th>
		</tr>
		@else
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Booking Total</th>
			<th>{{ moneyFormat($bookingM->total + $overallCustomCharge + $addOnAmount + $tipTotal) }}</th>
		</tr>
		@endif
		@if ($ageGroup)
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Refunded Pax</th>
			<th>{!! $ageGroup !!}</th>
		</tr>
		@endif
		@if ($refund_keep_us)
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Non-Refundable Fees</th>
			<th>{{ moneyFormat($refund_keep_us) }}</th>
		</tr>
		@endif
		@if ($amount)
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Refund Amount</th>
			<th>{{ moneyFormat($amount) }}</th>
		</tr>
		@endif
		<tr class="text-end">
			<th colspan="{{ $colSpan }}">Payment Total</th>
			<th>{{ moneyFormat($paidF) }}</th>
			<input type="hidden" id="partial-refund-value" value="{{ $partialAmount }}">
		</tr>
	</tfoot>
</table>

{{-- Payment & Refund History: a plain chronological log so a customer can always see
	 EVERY charge/refund on this booking, not just the net totals above — e.g. a permit
	 fee refund from months ago is still listed here even if this invoice was generated
	 later for an unrelated change. $paymentHistoryLog is built (and labeled/amount'd)
	 by HistoryService::customerFacingPaymentLog() from the same $history rows and the
	 same resolve*Data() helpers the admin History tab already trusts — nothing is
	 computed here, this just renders it. Not every caller of this shared partial passes
	 it (e.g. the Viator/TripAdvisor invoice builds its own $vars separately), hence the
	 isset() guard. --}}
@if(isset($paymentHistoryLog) && count($paymentHistoryLog))
<p class="mt-3"><b>Payment &amp; Refund History</b></p>
<table class="table table-bordered table-hover mt-3 mb-0 tbl-price">
	<thead>
		<tr class="text-end">
			<th class="text-start">Date</th>
			<th class="text-start">Description</th>
			<th>Amount</th>
		</tr>
	</thead>
	<tbody>
		@foreach($paymentHistoryLog as $entry)
		<tr class="text-end">
			<td class="text-start">{{ formatDateTime($entry['date']) }}</td>
			<td class="text-start">{{ $entry['label'] }}</td>
			<td style="color:{{ $entry['direction'] === 'out' ? '#B91C1C' : '#15803D' }}">
				{{ $entry['direction'] === 'out' ? '−' : '+' }}{{ moneyFormat($entry['amount']) }}
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
@endif