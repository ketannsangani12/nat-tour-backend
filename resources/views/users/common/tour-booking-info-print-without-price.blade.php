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
		</tr>
	</thead>

	<tbody>
		@php
		$deletedAgeGroup = [];
		if (!empty($TourBookingHistory)) {
		foreach ($TourBookingHistory as $BookingHistory) {
		$details = json_decode($BookingHistory->details);

		if (isset($details->refund_type)) {
		if($details->refund_type != "Don't Refund") {
		
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

        @endphp
		@foreach($bookingT as $bt)
		

		@if($bt->tourists != 0 || $bt->infants != 0)
		<tr class="text-end">
			<td class="text-start">{{ ($bookingM->is_group_booking == '1') ? 'Person(s)' : $bt->rate_for }}</td>
			<td>{{ ($bt->infants > 0 ? $bt->infants : $bt->tourists) }}</td>
		</tr>
		@endif
		@endforeach
	</tbody>
	<tfoot>	
		@if ($ageGroup)
		<tr class="text-end">
			<th >Refunded Pax</th>
			<th>{!! $ageGroup !!}</th>
		</tr>
		@endif
		
	</tfoot>
</table>