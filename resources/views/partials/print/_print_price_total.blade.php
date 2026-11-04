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

	if (!empty($tourBookingHistory)) {
		foreach ($tourBookingHistory as $BookingHistory) {
			$details = json_decode($BookingHistory->details);

			// "Don't Refund" rows (add-ons: "dont_refund", custom charges: "Don't Refund") record a
			// removal with nothing given back — they must not count toward the refunded total.
			if (isset($details->refund_type) && !in_array(strtolower($details->refund_type), [\App\Constants\Constants::ADDONS_DONT_REFUND, "don't refund"], true)) {
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

			if (isset($details->refund_keep_us)) {
				$details->refund_keep_us = $details->refund_keep_us ?? 0;
			}

			if (isset($details->refund_type) && isset($details->refund_keep_us) && strtolower($details->refund_type) !== "partial refund") {
				$refund_keep_us += $details->refund_keep_us;
			}

			$checkAmount = json_decode($historyPaymentTime->details);
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
				$resultArray[] = $bt->rate_for . " " . $bt->tourists;
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

@if($booking->is_affiliate == 'No' ||  ($booking->is_affiliate == 'Yes' && $booking->is_price_show == 'Yes'))

@if (isset($bookingT) && !empty($bookingT))
@foreach($bookingT as $bkt)
	@if (isset($bkt->tourists) && $bkt->tourists > 0)
		@php
		    if($booking->is_group_booking == '1') {
            $rate = $bkt->rate + $bkt->additional_charge + $bkt->partner_fees_amount;
			$per = $rate * ($bkt->additional_tax_percentage + $bkt->processing_charge_percentage) / 100;
			$subtotal = $rate + $per;
			$totalSubtotal += $subtotal;
            } else {
            $rate = $bkt->rate + $bkt->additional_charge + $bkt->permit_fee + $bkt->partner_fees_amount;
			$per = $rate * ($bkt->additional_tax_percentage + $bkt->processing_charge_percentage) / 100;
			$subtotal = $rate + $per;
			$totalSubtotal += $subtotal * $bkt->tourists;
            }

		@endphp
	@endif
@endforeach
@endif

<table class="table table-sm table-bordered table-hover mt-3 mb-0 tbl--price" style="{{$style}}">
	<tbody>
		@if($booking->discount2_value != 0)
			<tr>
				<th class="text-end">Ticket Cost</th>
				<td class="td-amount">{{ moneyFormat(sprintf('%.2f', $totalSubtotal)) }}</td>
			</tr>
			<tr>
				<th class="text-end">Discount</th>
				<td class="td-amount">-
				@php
					if($booking->discount2_percentage != 0) {
						echo '(' . str_replace('.00', '', $booking->discount2_percentage) . '%) ';
					}
					echo moneyFormat($booking->discount2_value);
				@endphp
				</td>
			</tr>
		@endif

		<tr>
			<th class="text-end">Subtotal</th>
			<td class="td-amount">{{ moneyFormat($totalSubtotal - $booking->discount2_value) }}</td>
		</tr>
		@if ($customChargePrice['custom_charge_total'] > 0)
			<tr>
				<th class="text-end">Custom Charge</th>
				<td class="td-amount">{{ moneyFormat($customChargePrice['custom_charge_total']) }}</td>
			</tr>
		@endif

		@if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
			<tr>
				<th class="text-end">Add-ons</th>
				<td class="td-amount">{{ moneyFormat($payLaterAddons['addons_price']) }}</td>
			</tr>
		@elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0)
			<tr>
				<th class="text-end">Add-ons</th>
				<td class="td-amount">{{ moneyFormat($pricedAddons['addons_final_price']) }}</td>
			</tr>
		@endif
		@if ($tipAmounts['tip_amount'] > 0)
			<tr>
				<th class="text-end">Tip</th>
				<td class="td-amount">{{ moneyFormat($tipAmounts['tip_amount']) }}</td>
			</tr>
		@endif			
		@if((!empty($transP) && ($transP->via == 'Card' || ($transP->via=='Cash' && cashFees() == '1'))))
		<tr>
			<th class="text-end">Booking Fees</th>
			@if (isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0)
				<td class="td-amount">{{ moneyFormat($booking->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee'] + $tipAmounts['tip_fees']) }}</td>
			@else
				<td class="td-amount">{{ moneyFormat($booking->service_commission + $customChargePrice['custom_charge_fees'] + $tipAmounts['tip_fees']) }}</td>
			@endif
		</tr>
		@endif
		@if((!empty($transP) && $transP->payment_type == 1) || (!empty($transP) && ($transP->payment_type == 0 || $transP->payment_type == '') && $paymentTransCount > 1))
		<tr>
			<th class="text-end">Total Cost</th>
			@if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
				<td class="td-amount">{{ moneyFormat($booking->booking_total + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price'] + $tipAmounts['tip_total']) }}</td>
			@elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0)
				{{-- booking_total is the gross tour-side total ever charged (never decremented on
				refund by design — see tour-booking-info.blade.php's identical "Booking Total" row),
				so add-ons must use the matching gross figure here too — not addons_payment_total,
				which nets out any add-on refund and understated this line by exactly that amount. --}}
				<td class="td-amount">{{ moneyFormat($booking->booking_total + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total'] + $tipAmounts['tip_total']) }}</td>
			@else
				<td class="td-amount">{{ moneyFormat($booking->booking_total + $customChargePrice['overall_custom_charge'] + $tipAmounts['tip_total']) }}</td>
			@endif
		</tr>
		@elseif(!empty($checkAmount))
		<tr>
			<th class="text-end">Total Cost</th>
			@if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
			<td class="td-amount">{{ moneyFormat($checkAmount->amount + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price'] + $totalDiscountPayment + $tipAmounts['tip_total']) }}</td>
			@elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0)
			{{-- Same gross-with-gross pairing as above — $checkAmount->amount is also the
			never-decremented original payment figure. --}}
			<td class="td-amount">{{ moneyFormat($checkAmount->amount + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total'] + $totalDiscountPayment + $tipAmounts['tip_total']) }}</td>
			@else
			<td class="td-amount">{{ moneyFormat($checkAmount->amount + $customChargePrice['overall_custom_charge'] + $totalDiscountPayment + $tipAmounts['tip_total']) }}</td>
			@endif
		</tr>
		@else
		<tr>
			<th class="text-end">Total Cost</th>
			@if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
				<td class="td-amount">{{ moneyFormat($booking->total + $customChargePrice['custom_charge_final_total'] + $payLaterAddons['addons_price'] + $tipAmounts['tip_total']) }}</td>
			@elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0)
				{{-- Same gross-with-gross pairing as above — $booking->total is also the
				never-decremented original figure. --}}
				<td class="td-amount">{{ moneyFormat($booking->total + $customChargePrice['custom_charge_final_total'] + $pricedAddons['addons_gross_total'] + $tipAmounts['tip_total']) }}</td>
			@else
				<td class="td-amount">{{ moneyFormat($booking->total + $customChargePrice['custom_charge_final_total'] + $tipAmounts['tip_total']) }}</td>
			@endif
		</tr>
		@endif
		@if($amount || $pricedAddons['addons_refund_total'])
		<tr>
			<th class="text-end">Refund Amount</th>
			<td class="td-amount">{{ moneyFormat($amount) }}</td>
		</tr>
		@endif
		<tr>
			<th class="text-end">Payment Total</th>
			<td class="td-amount">{{ moneyFormat($paidF) }}</td>
		</tr>
		@if((!empty($trans) && (!empty($transP)) && $transP->payment_type == 1))
			@foreach($history as $hist)
				@if($hist->operation=='Payment')
					@php
						$paymentAmount = $hist->details;
					@endphp
					<tr>
						@if($hist->payment_type == 'Card')
							<td colspan="2">{{ formatDateTime($hist->operation_at) }} <b>{{ moneyFormat($paymentAmount->amount) }} </b> <b>Card({{$hist->card_last_4_digit}})</b> </td>
						@else
							<td colspan="2">{{ formatDateTime($hist->operation_at) }} <b>{{ moneyFormat($paymentAmount->amount) }} </b> <b>Cash</b> </td>
						@endif
					</tr>
     	  		@endif
	     	@endforeach
		@else
			@foreach($payments as $payment)
				<tr>
					@if($payment->via == 'Card')
						<td colspan="2">{{ formatDateTime($payment->created_at) }} <b>Card</b> </td>
					@else
						<td colspan="2">{{ formatDateTime($payment->created_at) }} <b>{{$payment->via}}</b> </td>
					@endif
				</tr>
			@endforeach
	    @endif
	</tfoot>
</table>
@endif
