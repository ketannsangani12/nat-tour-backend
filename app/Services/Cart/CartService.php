<?php

namespace App\Services\Cart;

use App\Constants\Constants;
use App\Events\BookingNew;
use App\Jobs\CartBookingsEmails;
use App\Models\BookingTransact;
use App\Models\TourBooking;
use App\Models\TourPackageRate;
use App\Models\Transaction;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use App\Services\CardBookingService;
use App\Utils\StripeUtils;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PlatformFeeMetadata;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;

class CartService
{
	/**
	 * Get cart tour package details
	 *
	 * @param mixed $id
	 *
	 * @return array
	 */
	public static function cartTourPackageDetails($id): array
	{
		$cart = session()->get('cart');
		$cartTourPackage = [];
		if (!empty($cart) && !empty($cart['tour_packages'])) {
			foreach ($cart['tour_packages'] as $item) {
				if ($item['tour_package_id'] == $id) {
					$cartTourPackage = $item;
				}
			}
		}
		return $cartTourPackage;
	}


	/**
	 * Get cart tour package details
	 *
	 * @return array|null
	 */
	public static function getCartData(): array|null
	{
		$cart = session()->get('cart');

		if (!empty($cart['tour_packages'])) {
			foreach ($cart['tour_packages'] as &$package) {
				$natFees = (float) ($package['nat_fees_percentage'] ?? 0);
				[$price, $fee, $total] = self::computeAddonAmounts($package, $natFees);
				$package['addon_price'] = $price;
				$package['addon_fee']   = $fee;
				$package['addon_total'] = $total;
			}
		}

		return $cart;
	}

	/**
	 * Compute addon price/fee/total from pax_addons_data and addons_data stored in a cart package.
	 * Returns [price, fee, total].
	 */
	private static function computeAddonAmounts(array $package, float $natFees): array
	{
		$price = 0.0;
		$fee   = 0.0;

		// --- price-per-pax addons ---
		if (!empty($package['pax_addons_data'])) {
			$pax = json_decode(trim($package['pax_addons_data']), true);
			foreach ($pax['fields'] ?? [] as $rateData) {
				foreach ($rateData['addons'] ?? [] as $paxRow) {
					foreach ($paxRow as $key => $field) {
						if ($key === 'uid' || !is_array($field)) continue;
						// Always compute — calculateFieldAmounts() safely returns zero when
						// neither the field nor its options are priced, AND recurses into
						// $field['children'] to pick up a priced CHILD even when the parent
						// itself carries no price. Gating on the parent's own price flags
						// before calling this skipped that recursion entirely.
						$amt = AddonBookingService::calculateFieldAmounts($field, 'Card', 0, $natFees);
						$price += $amt['price'];
						$fee   += $amt['fee'];
					}
				}
			}
		}

		// --- one-time / price-per-unit addons ---
		if (!empty($package['addons_data'])) {
			$other = json_decode(trim($package['addons_data']), true);
			foreach ($other['fields']['addons'] ?? [] as $addonRow) {
				foreach ($addonRow as $key => $field) {
					if ($key === 'uid' || !is_array($field)) continue;
					// See identical comment above — always compute so a priced CHILD under an
					// unpriced parent is never silently skipped.
					$amt = AddonBookingService::calculateFieldAmounts($field, 'Card', 0, $natFees);
					$price += $amt['price'];
					$fee   += $amt['fee'];
				}
			}
		}

		$fee   = round($fee, 2);
		$total = round($price + $fee, 2);

		return [$price, $fee, $total];
	}

	/**
	 * Get total amounts of cart
	 *
	 * @param int $cartView
	 *
	 * @return array
	 */
	public static function calculateCartTotals($cartView, $cart, $vars): array
	{
		$totalTicketCost = 0;
		$totalServiceCommission = 0;
		$total = 0;
		$totalCashTicketCost = 0;
		$totalCashServiceCommission = 0;
		$totalCash = 0;
		$totalDiscount = 0;
		$totalDiscountPercentage = 0;
		$totalDiscountCash = 0;
		$totalCustomCharge = 0;
		$totalCustomChargeFees = 0;

		$totalAddonsPrice = 0;
		$totalAddonsFee = 0;
		$totalAddonsTotal = 0;
		$totalTipAmount = 0;
		$totalTipFees = 0;
		$totalTipTotal = 0;
		if (!empty($cart) && !empty($cart['tour_packages']) && $cartView == 1) {
			foreach ($cart['tour_packages'] as $item) {
				$totalTicketCost += $item['subtt'];
				$totalCashTicketCost += $item['cashSubtt'];
				$totalServiceCommission += $item['fee'] + $item['addon_fee'] + $item['custom_charge_fees'] + $item['tip_fees'];
				$totalCashServiceCommission += $item['cashFee'] + $item['addon_fee'] + $item['custom_charge_fees'] + $item['tip_fees'];
				$total += $item['total'] + $item['addon_total'] + $item['custom_charge_total'] + $item['tip_total'];
				$totalCash += $item['cashTotal'] + $item['addon_total'] + $item['custom_charge_total'] + $item['tip_total'];
				$totalDiscount += $item['discount_amount'];
				$totalDiscountPercentage += $item['discount_percentage'];
				$totalDiscountCash += $item['discount_amount_cash'];
				$totalCustomCharge += $item['custom_charge'];
				$totalCustomChargeFees += $item['custom_charge_fees'];
				$totalAddonsPrice += $item['addon_price'];
				$totalAddonsFee += $item['addon_fee'];
				$totalAddonsTotal += $item['addon_price'] + $item['addon_fee'];
				$totalTipAmount += $item['tip_amount'];
				$totalTipFees += $item['tip_fees'];
				$totalTipTotal += $item['tip_total'];
			}
		}

		$cartTotals = $vars + [
			'cartTicketCost' => $totalTicketCost,
			'cartServiceCommission' => $totalServiceCommission,
			'cartTotal' => $total,
			'cartCashTicketCost' => $totalCashTicketCost,
			'cartCashServiceCommission' => $totalCashServiceCommission,
			'cartCashTotal' => $totalCash,
			'cartDiscount' => $totalDiscount,
			'cartDiscountPercentage' => $totalDiscountPercentage,
			'cartDiscountCash' => $totalDiscountCash,
			'cartCustomChargeAmount' => $totalCustomCharge,
			'cartCustomChargeFees' => $totalCustomChargeFees,
			'cartAddonsPrice' => $totalAddonsPrice,
			'cartAddonsFees' => $totalAddonsFee,
			'cartAddonsTotal' => $totalAddonsTotal,
			'cartTipAmount' => $totalTipAmount,
			'cartTipFees' => $totalTipFees,
			'cartTipTotal' => $totalTipTotal
		];

		return $cartTotals;
	}

	/**
	 * Get total amounts of cart
	 *
	 * @param array $cartPackages
	 *
	 * @return array
	 */
	public static function validateCartPackages($cartPackages): array
	{
		$validatedCart = true;
		$userType = userType();
		$tourOperatorId = '';
		if (!empty($cartPackages)) {
			foreach ($cartPackages as $cartPackage) {

				$info = "SELECT
						`tst`.`time_from`, `tst`.`time_to`, `tst`.`date`, `tp`.`tour_operator_id`,
						`tp`.`bundle_package`, `tp`.`package_has_slots`, `tp`.`package_has_waiver`, `tp`.`package_has_permit`
					FROM
						`tour_slot_times` as `tst`
						INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tst`.`tour_package_id`
					WHERE
						`tst`.`id` = " . $cartPackage['slot_time'] . "
					";

				if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
					$info .= ' AND tp.tour_operator_id = ' . Auth::user()->tour_operator_id . '';
				}

				$info = collect(DB::select($info))->first();

				if (!$info) {
					$validatedCart = false;
				}

				if ($info->package_has_slots && ("{$info->date} {$info->time_from}" <= mysqlDT())) {
					$validatedCart = false;
				}

				$tourOperatorId = $info->tour_operator_id;
			}
		}

		return [
			'validatedCart' => $validatedCart,
			'tourOperatorId' => $tourOperatorId
		];
	}
	/**
	 * Validate and process tourist bookings.
	 *
	 * @param array $cartPackages List of cart packages.
	 * @param array $cart Cart details.
	 * @param \Illuminate\Http\Request $request Request object.
	 * @throws Exception If booking constraints are not met.
	 */
	public static function validateTouristsAndBookings(array $cartPackages, array $cart, $request)
	{
		$totalTourists = 0;

		foreach ($cartPackages as $cartPackage) {
			$totalTourists += array_sum($cartPackage['tourists']);

			// if ($request->is('api/*')) {
			// 	self::checkAffiliateReservation($cart, $cartPackage);
			// }
		}

		if ($totalTourists === 0) {
			throw new Exception('Please select at least one tourist.');
		}

		if ($request->is('api/*')) {
			self::validateMinPaxRequirement($cartPackages);
		}
	}

	/**
	 * Check if the affiliate customer already has a reserved booking.
	 *
	 * @param array $cart Cart details.
	 * @param array $cartPackage Single package details.
	 * @throws Exception If a reserved booking already exists.
	 */
	private static function checkAffiliateReservation(array $cart, array $cartPackage)
	{
		if (
			!empty($cart['customer_details']['affiliatecust']) &&
			!empty($cartPackage['is_reserved_for_affiliate']) &&
			$cartPackage['is_reserved_for_affiliate'] == 1
		) {
			$reserveBookingCount = TourBooking::whereDoesntHave('transactions')
				->where([
					['tour_package_id', $cartPackage['tour_package_id']],
					['is_reserved_for_affiliate', '1'],
					['customer_id', $cart['customer_details']['affiliatecust']],
					['tour_slot_id', $cartPackage['slot_time']],
					['status', 'Booked'],
				])
				->count();

			if ($reserveBookingCount > 0) {
				throw new Exception('Unable to book the tour package. You have already reserved a booking for the slot.');
			}
		}
	}

	/**
	 * Validate minimum pax (passenger) requirement for each package.
	 *
	 * @param array $cartPackages List of cart packages.
	 * @throws Exception If minimum pax requirement is not met.
	 */
	private static function validateMinPaxRequirement(array $cartPackages)
	{
		if (!in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO])) {
			return;
		}

		foreach ($cartPackages as $cartPackage) {
			foreach ($cartPackage['tourists'] as $index => $touristCount) {
				$rateGroupId = $cartPackage['rate_group'][$index] ?? null;
				$rateDetails = TourPackageRate::find($rateGroupId);

				if ($rateDetails && $rateDetails->min_pax_allowed > $touristCount) {
					throw new Exception(
						"Please select a minimum of {$rateDetails->min_pax_allowed} {$rateDetails->rate_for} people to process your booking."
					);
				}
			}
		}
	}
	/**
	 * Calculate group total of individual cart package
	 * @param array $cartPackageData
	 *
	 *
	 * @return array
	 */
	public static function calculateCartPackageGroupTotals($cartPackageData): array
	{
		$cartPackage = $cartPackageData['cartPackage'];
		$tourists = $cartPackageData['tourists'];
		$rateGroups = $cartPackageData['rateGroups'];
		$selectedRateGroups = $cartPackageData['selectedRateGroups'];
		$permitfeeAr = $cartPackageData['permitfeeAr'];
		$natFeesPercentage = $cartPackageData['natFeesPercentage'];
		$stripeFeesPercentage = $cartPackageData['stripeFeesPercentage'];
		$paymentMethod = $cartPackageData['paymentMethod'];
		$discountAmt = $cartPackageData['discountAmt'];
		$isGroupRate = $cartPackageData['cartPackage']['is_group_rate'] ?? '';
		$isPartnerPackage = $cartPackageData['cartPackage']['is_partner_package'] ?? '';

		$total = $serviceCommissionTotal = $partnerFeesTotal = 0;
		$tbTransact = $touristGroups = [];
		$userType = userType();
		$isCashFees = cashFees();
		$isCashPrice = $cartPackageData['cashPriceEnabled'] ?? 0;
		$errorMessage = '';
		$totalTourists = 0;
		$totalInfants = 0;
		for ($a = 0, $ac = count($tourists); $a < $ac; $a++) {

			if (!$tourists[$a])
				continue;

			$touristsA = $tourists[$a];
			$groupTotal = 0;

			for ($b = 0, $bc = count($rateGroups); $b < $bc; $b++) {
				if ($selectedRateGroups[$a] == $rateGroups[$b]->id) {
					$touristGroups[] = $rateGroups[$b]->rate_for[0] . ':' . $touristsA;
					break;
				}
			}

			if ($b >= $bc) { // rate group not found
				$errorMessage = 'Unable to book the tour package.';
			}

			$isInfantRate = $rateGroups[$b]->is_infant_rate;
			$isCashRate = $rateGroups[$b]->is_cash_rate;
			$infants = ($isInfantRate == 1) ? $tourists[$a] : 0;
			$partnerFeeType = ($isPartnerPackage == 1  && $rateGroups[$b]->partner_fee_type != '') ? $rateGroups[$b]->partner_fee_type : '';
			$partnerFeesValue = ($isPartnerPackage == 1  && $rateGroups[$b]->partner_fee_percentage > 0) ? $rateGroups[$b]->partner_fee_percentage : 0;
			$partnerFees = ($isPartnerPackage == 1) ? $rateGroups[$b]->partner_fee_amount : 0;

			// Calculate total based on group rate or individual rate
			$isCashPriceApplied = ($isCashPrice == 1 && $isCashRate == 1 && $paymentMethod == 'Cash');
			$rate = ($isCashPriceApplied) ? $rateGroups[$b]->cash_rate : $rateGroups[$b]->rate;
			// $permitfeeAr already encodes the policy: rate value for affiliates, posted value for staff.
			$permitFee =  $permitfeeAr[$b];
			$additionalCharge = ($isCashPriceApplied) ? $rateGroups[$b]->cash_tax : $rateGroups[$b]->additional_charge;

			// rate may be zero for one or more age/rate group and
			// if rate is non-zero then only add other amounts
			if ($isGroupRate == '1') {
				$groupTotal = $rate + $additionalCharge + $partnerFees;
			} else {
				$groupTotal = ($touristsA * ($rate +
					$additionalCharge +
					$permitFee + $partnerFees
				));
			}


			if ($paymentMethod === 'Card' || ($isCashFees == '1' && $paymentMethod == 'Cash')) {
				if ($isGroupRate == '1') {
					$serviceCommissionTotal = roundout((($rate + $additionalCharge + $partnerFees) * $natFeesPercentage / 100), 2);
				} else {
					$serviceCommissionPax =  roundout((($rate + $additionalCharge + $permitFee + $partnerFees) * $natFeesPercentage / 100), 2);
					$serviceCommissionPax = $touristsA * $serviceCommissionPax;
					$serviceCommissionTotal += $serviceCommissionPax;
				}

				$groupTotal += ($groupTotal * (float)$rateGroups[$b]->processing_charge_percentage) / 100;
			}

			$partnerFeesTotal += ($partnerFees * $touristsA);

			if ($cartPackage['subtt'] == null) {
				$cartPackage['subtt'] = 0.00;
			}

			if ($cartPackage['fee'] == null) {
				$cartPackage['fee'] = 0.00;
			}

			$tt = $cartPackage['subtt'] + $cartPackage['fee'];

			$tbTransact[] = [
				'tour_package_rate_id' => $rateGroups[$b]->id,
				'tourists' => ($isInfantRate == 0) ? $touristsA : 0,
				'infants' => $infants,
				'processing_charge_percentage' => (($paymentMethod !== 'Card') ? 0 : $rateGroups[$b]->processing_charge_percentage),
				'nat_fees_percentage' => $natFeesPercentage,
				'stripe_fees_percentage' => $stripeFeesPercentage,
				'partner_fees_type' => ($partnerFeesValue > 0) ? $partnerFeeType : '',
				'partner_fees_percentage' => $partnerFeesValue,
				'partner_fees_amount' => $partnerFees,
				'additional_tax_percentage' => ($isGroupRate == '1') ? 0 : ($rateGroups[$b]->additional_tax_percentage),
				'additional_charge' =>  $rateGroups[$b]->additional_charge,
				'permit_fee' => ($isGroupRate == '1') ? 0 : $permitfeeAr[$b],
				'rate' => $rateGroups[$b]->rate,
				'total' => $groupTotal,
				'is_group_rate' => ($isGroupRate == '1') ? 1 : 0,
				'is_infant_rate' => $isInfantRate,
				'is_cash_rate' => ($isCashPriceApplied) ? 1 : 0,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'created_at' => Carbon::now(),
			];

			/* START - Added this to place the history of person added */
			$tbTransaction[] = [
				'tourists' => $touristsA,
				'rate_for' => $rateGroups[$b]->rate_for
			];
			/* END - Added this to place the history of person added */
			$totalTourists += ($isInfantRate == 0) ? $touristsA : 0;
			$totalInfants += $infants;
			$total += $groupTotal;
		}

		$total -= $discountAmt;
		return [
			'total' => $total,
			'serviceCommissionTotal' => $serviceCommissionTotal,
			'partnerFeesTotal' => $partnerFeesTotal,
			'errorMessage' => $errorMessage,
			'tbTransact' => $tbTransact,
			'tbTransaction' => $tbTransaction,
			'totalTourists' => $totalTourists,
			'totalInfants' => $totalInfants,
			'touristGroups' => $touristGroups
		];
	}

	/**
	 * Create metadata and description for the stripe
	 * @param array $data
	 *
	 *
	 * @return array
	 */
	public static function stripeMetadata($data): array
	{
		$description = '';
		$cardBookingsData = $data['data'];
		$i = 0;
		$cartPackagesInfo = [];
		foreach ($cardBookingsData as $key => $value) {
			$lineBreak = chr(0x0D) . chr(0x0A) . ' ' . chr(0x0D) . chr(0x0A);
			if ($i > 0) {
				$description .= $lineBreak;
			}
			$description .= 'Booking Id: ' . $value['booking_id'] .
				"\nTour Package: " . $value['tour_package'] .
				"\nTour Date: " . $value['date'] .
				"\nTour Time: " . $value['time_from'] .
				"\nTourists: " . $value['tourists'] .
				"\nEmail: " . $data['customer_email'];

			$cartPackagesInfo[] = [
				'BookingId' => $value['booking_id']
			];
			$i++;
		}
		$description = StripeUtils::limitDescription($description);

		if (isset($data['App'])) {
			$stripeMetadata = [
				'description' => $description,
				'metadata' => [
					'customer_name' => $data['customer_name'],
					'customer_phone' => $data['customer_phone'],
					'email' => $data['customer_email'],
					'split_payment' => ($data['split_value'] != '') ? 1 : 0,
					'bookings' => json_encode($cartPackagesInfo),
					'app' => $data['App'],
					'section' => $data['Section'],
					'from' => $data['From'] ?? 'iOS Manual Card',
					'tenant_id' => tenant()->id,
				]
			];
		} else {
			$stripeMetadata = [
				'description' => $description,
				'metadata' => [
					'customer_name' => $data['customer_name'],
					'customer_phone' => $data['customer_phone'],
					'email' => $data['customer_email'],
					'split_payment' => ($data['split_value'] != '') ? 1 : 0,
					'bookings' => json_encode($cartPackagesInfo),
					'from' => $data['From'] ?? 'iOS Manual Card',
					'tenant_id' => tenant()->id,
				]
			];
			if (isset($data['isPartnerBooking']) && $data['isPartnerBooking'] == 1) {
				$stripeMetadata['metadata']['amount'] = $data['total'] ?? 0.00;
				$stripeMetadata['metadata']['platform_fee'] = $data['serviceCommission'] ?? 0.00;
				$stripeMetadata['metadata']['partner_fee'] = $data['partnerFees'] ?? 0.00;
				$stripeMetadata['metadata']['payout_amount'] = $data['payoutAmount'] ?? 0.00;
			}
		}


		return $stripeMetadata;
	}


	/**
	 * Complete 3d secure payment for the cart
	 * @param array $data
	 * @param bool $isMakePayment
	 *
	 * @return string
	 */
	public static function completeSecureCardPaymentForCart($data, $isMakePayment = false): string
	{
		$bookings = TourBooking::where('stripe_paymentintent_id', $data['payment_intent_client_secret'])->where('status', 'Booked')->get();
		$paymentIntentId = isset($data['payment_intent']) ? $data['payment_intent'] : '';
		$userType = userType();
		$paymentIntentDetails = PaymentIntent::retrieve(['id' => $paymentIntentId]);
		$stripeMetaDataBookings = (isset($paymentIntentDetails->charges->data[0]->metadata->bookings) && !empty($paymentIntentDetails->charges->data[0]->metadata->bookings)) ? json_decode($paymentIntentDetails->charges->data[0]->metadata->bookings) : [];

		if (empty($stripeMetaDataBookings)) {
			return static::completeSecureCardPayment($data);
		} else {
			$paymentAcknowledgement = '';
			if ($bookings) {
				$cardBookingsData = [];
				foreach ($stripeMetaDataBookings as $booking) {
					$transactionsExist = Transaction::where('tour_booking_id', $booking->BookingId)->where('status', 'Completed')->first();
					if (!$transactionsExist) {
						try {

							if (!empty($paymentIntentDetails)) {
								if ($paymentIntentDetails->status == 'succeeded') {
									$bookingDetails = TourBooking::where('id', $booking->BookingId)->first();

									$splitPayment = (isset($paymentIntentDetails->charges->data[0]->metadata->split_payment)) ? $paymentIntentDetails->charges->data[0]->metadata->split_payment : 0;

									if ($splitPayment == 1) {
										$splitAmount = (isset($paymentIntentDetails->charges->data[0]->amount)) ? $paymentIntentDetails->charges->data[0]->amount / 100 : 0;
										$feesAmount = PlatformFeeMetadata::feeFor($paymentIntentDetails, (isset($paymentIntentDetails->charges->data[0]->application_fee_amount)) ? $paymentIntentDetails->charges->data[0]->application_fee_amount / 100 : 0);
									} else {
										$amountsDetails = static::calculateCartBookingTotal($booking->BookingId);
										$splitAmount = $amountsDetails['total'];
										$feesAmount = $amountsDetails['serviceCommission'];
									}

									$transactionId = TransactionService::storePaymentInTransaction(
										$bookingDetails->id,
										$splitAmount,
										$feesAmount,
										'Card',
										($splitPayment == 1) ? 1 : 0,
										!empty($paymentIntentId) ? $paymentIntentId : 0,
										!empty($paymentIntentDetails->charges->data[0]->application_fee) ? $paymentIntentDetails->charges->data[0]->application_fee : 0,
										mode: PaymentModeInference::fromStripeObject($paymentIntentDetails)
									);


									$crd = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->last4) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
									$cardType = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

									$createdByWithName = BookingService::createdUserInfo();

									$histData = [
										'total' => $splitAmount,
										'payment_type' => ($splitPayment == 1) ? 'Split Payment' : 'Full Payment',
										'discount_promo_code' => ($bookingDetails->discount_promo_code != '') ? $bookingDetails->discount_promo_code : ''
									];
									$historyId = HistoryService::storeCardPaymentInHistory($bookingDetails->id, $bookingDetails->tourists + $bookingDetails->infants, $histData, $crd, $cardType, $createdByWithName, [], '');

									// Get the information details
									$info = static::bookingDetails($bookingDetails->tour_slot_id);

									// Below line is for to update tour booking history table
									$isSplitOrFull = ($splitPayment == 1) ? 'Split Payment' : 'Full Payment';
									$bookingDetails->booking_id = $bookingDetails->id;
									$bookingDetails->tour_operator_id = $info->tour_operator_id;

									CardBookingService::run($bookingDetails->toArray(), $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntentDetails);

									$bookingDetails = TourBooking::where('id', $bookingDetails->id)->first();
									$bookingDetails->stripe_paymentintent_id = '';
									$bookingDetails->save();

									$cardBookingsData[] = [
										'tourists' => $bookingDetails->tourists,
										'booking_id' => $bookingDetails->id,
										'tour_package' => $info->package_name,
										'date' => $info->date,
										'time_from' => $info->time_from,
										'slot_time' => $bookingDetails->tour_slot_id,
										'tour_operator_id' => $info->tour_operator_id,
									];

									$paymentAcknowledgement = 'success';
								} else {
									$bookingDetails = TourBooking::where('id', $booking->BookingId)->first();
									$bookingDetails->status = 'Cancelled';
									$bookingDetails->stripe_paymentintent_id = '';
									$bookingDetails->updated_by = Auth::id();
									$bookingDetails->updated_by = $userType;
									$bookingDetails->save();
									$paymentAcknowledgement = 'failed';
								}
							} else {
								$paymentAcknowledgement = 'wrongdetails';
							}
						} catch (CardException $ex) {
							Log::error($ex);
							HistoryService::storeCardPaymentErrorInHistory($booking->BookingId, $ex);
							$paymentAcknowledgement = 'failed';
						}
					}
				}

				if ($paymentAcknowledgement == 'success' && !$isMakePayment) {
					$userId = Auth::id();
					$tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? Auth::user()->tour_operator_id : '';
					$userType = userType();
					$loggedInUserEmail = Auth::user()->email;
					dispatch(new CartBookingsEmails($cardBookingsData, $userId, $userType, $tourOperatorId, $loggedInUserEmail));
				}
			}

			return $paymentAcknowledgement;
		}
	}

	/**
	 * Complete 3d secure payment for single booking
	 * @param array $data
	 *
	 * @return string
	 */
	public static function completeSecureCardPayment($data): string
	{
		$bookingDetails = TourBooking::where('stripe_paymentintent_id', $data['payment_intent_client_secret'])->where('status', 'Booked')->first();

		$paymentIntentId = isset($data['payment_intent']) ? $data['payment_intent'] : '';
		$userType = userType();
		$paymentIntentDetails = PaymentIntent::retrieve(['id' => $paymentIntentId]);

		$paymentAcknowledgement = '';
		if ($bookingDetails && $paymentIntentId != '') {
			$transactionsexist = Transaction::where('tour_booking_id', $bookingDetails->id)->where('transaction_number', $paymentIntentId)->where('status', 'Completed')->first();
			if (!$transactionsexist) {
				try {
					if (!empty($paymentIntentDetails)) {
						if ($paymentIntentDetails->status == 'succeeded') {
							$bookingDetails = TourBooking::where('id', $bookingDetails->id)->first();
							$splitpayment = (isset($paymentIntentDetails->charges->data[0]->metadata->split_payment)) ? $paymentIntentDetails->charges->data[0]->metadata->split_payment : 0;
							$splitamount = (isset($paymentIntentDetails->charges->data[0]->amount)) ? $paymentIntentDetails->charges->data[0]->amount / 100 : 0;
							$feesamount = PlatformFeeMetadata::feeFor($paymentIntentDetails, (isset($paymentIntentDetails->charges->data[0]->application_fee_amount)) ? $paymentIntentDetails->charges->data[0]->application_fee_amount / 100 : 0);

							$transactionId = TransactionService::storePaymentInTransaction(
								$bookingDetails->id,
								$splitamount,
								$feesamount,
								'Card',
								($splitpayment == 1) ? 1 : 0,
								!empty($paymentIntentId) ? $paymentIntentId : null,
								!empty($paymentIntentDetails->charges->data[0]->application_fee) ? $paymentIntentDetails->charges->data[0]->application_fee : null,
								mode: PaymentModeInference::fromStripeObject($paymentIntentDetails)
							);
							$crd = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->last4) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
							$cardType = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

							$createdByWithName = BookingService::createdUserInfo();

							$histData = [
								'total' => $splitamount,
								'discount_promo_code' => ($bookingDetails->discount_promo_code != '') ? $bookingDetails->discount_promo_code : ''
							];
							$historyId = HistoryService::storeCardPaymentInHistory($bookingDetails->id, $bookingDetails->tourists + $bookingDetails->infants, $histData, $crd, $cardType, $createdByWithName, [], '');

							// Get the information details
							$info = static::bookingDetails($bookingDetails->tour_slot_id);


							if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
								$info = $info->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
							}

							$info = $info->first();

							// Below line is for to update tour booking history table
							$isSplitOrFull = ($splitpayment == 1) ? 'Split Payment' : 'Full Payment';
							$bookingDetails->booking_id = $bookingDetails->id;
							$bookingDetails->tour_operator_id = $info->tour_operator_id;
							CardBookingService::run($bookingDetails->toArray(), $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntentDetails);
							$bookingDetails = TourBooking::where('id', $bookingDetails->id)->first();
							$bookingDetails->stripe_paymentintent_id = '';
							$bookingDetails->save();
							$cardBookingsData[] = [
								'tourists' => $bookingDetails->tourists,
								'booking_id' => $bookingDetails->id,
								'tour_package' => $info->package_name,
								'date' => $info->date,
								'time_from' => $info->time_from,
								'slot_time' => $bookingDetails->tour_slot_id,
								'tour_operator_id' => $info->tour_operator_id,

							];
							$paymentAcknowledgement = 'success';
						} else {
							$bookingDetails = TourBooking::where('id', $bookingDetails->id)->first();
							$bookingDetails->status = 'Cancelled';
							$bookingDetails->stripe_paymentintent_id = '';
							$bookingDetails->updated_by = Auth::id();
							$bookingDetails->updated_by = $userType;
							$bookingDetails->save();
							$paymentAcknowledgement = 'failed';
						}
					} else {
						$paymentAcknowledgement = 'wrongdetails';
					}
				} catch (CardException $ex) {
					Log::error($ex);
					HistoryService::storeCardPaymentErrorInHistory($bookingDetails->id, $ex);
					$paymentAcknowledgement = 'failed';
				}
			}

			if ($paymentAcknowledgement == 'success') {
				$userId = Auth::id();
				$tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? Auth::user()->tour_operator_id : '';
				$userType = userType();
				$loggedInUserEmail = Auth::user()->email;
				dispatch(new CartBookingsEmails($cardBookingsData, $userId, $userType, $tourOperatorId, $loggedInUserEmail));
			}
		}
		return $paymentAcknowledgement;
	}

	/**
	 * Summary of bookingDetails
	 * @param mixed $tourSlotId
	 * @return object|null
	 */
	public static function bookingDetails($tourSlotId)
	{
		$info = DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
			->join('states AS st', 'st.id', '=', 'ct.state_id')
			->where('tst.id', $tourSlotId)
			->select([
				'tst.time_from',
				'tsd.date',
				'tst.time_slot_seats as seats',
				'tp.tour_operator_id',
				'tsd.tour_package_id',
				'tp.name AS package_name',
				'tp.important_notes',
				'tp.things_to_bring',
				'ct.state_id',
				'st.permit_fee',
				't_o.name AS tour_operator',
				't_o.email AS tour_op_email',
				't_o.phone_number AS tour_op_phone',
				't_o.website AS tour_op_website',
				't_o.logo AS tour_op_logo',
				't_o.cancellation_policy',
				'tp.service_commission_percentage',
				't_o.affiliate_processing_percentage',
				't_o.stripe_affiliate_percentage',
				'tp.stripe_commission_percentage',
				't_o.tenant_id',
				'tp.stripe_destination_id',
				'tp.email as packageEmail',
				'tp.from_name as packageFromName',
				'tp.from_email as packageFromEmail',
				'tp.api_key as packageApiKey',
				DB::raw("(SELECT SUM(tbm.tourists)
                            FROM tour_bookings_master AS tbm
                            WHERE tbm.tour_slot_id = tst.id
                                AND tbm.status != 'Cancelled'
                            GROUP BY tbm.tour_slot_id) AS booked_seats")
			])
			->first();

		return $info;
	}

	/**
	 * Calculate Total and Fees of cart bookings
	 * @param int $bookingId
	 *
	 *
	 * @return mixed
	 */
	public static function calculateCartBookingTotal($bookingId): mixed
	{
		$oldBookingId = oldBookingId();

		$tourBooking = DB::select("
				SELECT
					`tbm`.`id`,
					`tbm`.`id` as `booking_id`,
					`tbm`.`customer_id`,
				    `tbm`.`discount2_value`,
				    `tbm`.`discount2_percentage`,
					`t_o`.`id` as `tour_operator_id`,
					`tbm`.`service_commission`,
				    `c`.`is_affiliate`,
					`tp`.`service_commission_percentage`,
					`c`.`affiliate_processing_percentage`,
					`tbm`.`tour_package_id`,
					`tst`.`date`
				FROM
					`tour_bookings_master` as `tbm`
					INNER JOIN `tour_slot_times` as `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
					INNER JOIN `customers` as `c` ON `c`.`id` = `tbm`.`customer_id`
					INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
					INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
				WHERE
				`tbm`.`id` = " . $bookingId . "
			");

		$isBlackoutDate = isBlackoutDateEnabled($tourBooking[0]->date);
		$natFeesPercentage = $tourBooking[0]->service_commission_percentage ?? 0;
		if ($tourBooking[0]->is_affiliate == 'Yes') {
			$natFeesPercentage = ($isBlackoutDate) ? $tourBooking[0]->service_commission_percentage : $tourBooking[0]->affiliate_processing_percentage;
			if (getNextYearCommissionPercentage($tourBooking[0]->date)) {
				if ($isBlackoutDate) {
					$serviceCommission = getNormalCommissionPercentage($tourBooking[0]->tour_package_id);
					$natFeesPercentage = $serviceCommission['service_commission_percentage'];
				} else {
					$affiliateCommission = getAffiliateCommissionPercentage($tourBooking[0]->customer_id);
					$natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
				}
			}
		} elseif (getNextYearCommissionPercentage($tourBooking[0]->date)) {
			$serviceCommission = getNormalCommissionPercentage($tourBooking[0]->tour_package_id);
			$natFeesPercentage = $serviceCommission['service_commission_percentage'];
		}

		$bookingT = BookingTransact::with('packageRate')
			->where('tour_booking_id', $bookingId)
			->get();

		$subTotal = 0;
		$total = 0;
		$serviceCommission = 0;
		$oldDiscountAmt = 0;
		$serviceCommissionTotal = 0;

		// Calculate the booking amount
		foreach ($bookingT as $tbTransaction) {
			$rate = $tbTransaction->rate;
			$permitFee = $tbTransaction->permit_fee;
			$additionalCharge = $tbTransaction->additional_charge;
			$tourists = $tbTransaction->tourists;

			if ($bookingId < $oldBookingId) {
				$procFee = 2.9;
			} else {
				$procFee = $tbTransaction->packageRate->processing_charge_percentage;
				if ($tourBooking[0]->discount2_value == '0') {
					$serviceCommissionPax = roundout((($rate + $additionalCharge + $permitFee) * $natFeesPercentage / 100), 2);
					$serviceCommissionPax = $tourists * $serviceCommissionPax;
					$serviceCommissionTotal += $serviceCommissionPax;
				}
			}

			$subTotal = ($rate + $permitFee + $additionalCharge) * $tourists
				- BookingService::calculatePendingPermitFeeAdjustment($tbTransaction->refund_permitfee_status, $permitFee);

			$total += $subTotal + ($subTotal * ($procFee / 100));
		}

		if ($tourBooking[0]->discount2_value > 0 && $tourBooking[0]->discount2_percentage == 0) {
			$oldDiscountAmt = $tourBooking[0]->discount2_value;
			$total -= $tourBooking[0]->discount2_value;
		} else if ($tourBooking[0]->discount2_percentage > 0) {
			$oldDiscountAmt = number_format(($total * $tourBooking[0]->discount2_percentage) / 100, 2, '.', '');
			$total -= $oldDiscountAmt;
		}

		// Add the service commission in the total amount
		if ($tourBooking[0]->is_affiliate == 'No') {
			if ($bookingId >= $oldBookingId) {
				if ($oldDiscountAmt > 0) {
					$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
				} else {
					$serviceCommission = $serviceCommissionTotal;
				}
			} else {
				$serviceCommission = number_format((float)$total * 6 / 100, 2, '.', '');
			}

			$total += $serviceCommission;
		} elseif ($tourBooking[0]->is_affiliate == 'Yes') {
			if ($bookingId >= $oldBookingId) { // Set booking id 95440
				if ($oldDiscountAmt > 0) {
					$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
				} else {
					$serviceCommission = $serviceCommissionTotal;
				}
			}

			$total += $serviceCommission;
		}

		return [
			'total' => $total,
			'serviceCommission' => $serviceCommission
		];
	}
	/**
	 * Get tour slot information by slot time ID.
	 *
	 * @param int $slotTime
	 * @return object|null
	 */
	public static function getTourSlotInfo(int $slotTime): ?object
	{
		$userType = userType();
		$slotQuery = DB::table('tour_slot_times as tst')
			->select([
				'tst.time_from',
				'tst.time_to',
				'tst.date',
				'tst.time_slot_seats as seats',
				'tp.tour_operator_id',
				'tst.tour_package_id',
				'tp.name as package_name',
				'tp.important_notes',
				'tp.things_to_bring',
				't_o.name as tour_operator',
				't_o.email as tour_op_email',
				't_o.phone_number as tour_op_phone',
				't_o.website as tour_op_website',
				't_o.logo as tour_op_logo',
				't_o.cancellation_policy',
				'tp.service_commission_percentage',
				'tp.stripe_commission_percentage',
				'tp.package_has_slots',
				't_o.tenant_id',
				't_o.stripe_destination_id',
				'tp.stripe_destination_id as partnerDestinationId',
				't_o.id as tour_operator_id',
				't_o.statement_descriptor',
				't_o.card_reader_prefix',
				'tp.email as packageEmail',
				'tp.from_name as packageFromName',
				'tp.from_email as packageFromEmail',
				'tp.api_key as packageApiKey',
				'tp.bundle_package',
			])
			->join('tour_packages as tp', 'tp.id', '=', 'tst.tour_package_id')
			->join('tour_operators as t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->where('tst.id', $slotTime);
		if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
			$slotQuery->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		}
		return $slotQuery->first();
	}
	/**
	 * Retrieve rate group details for the given tourists and rate groups for cart.
	 *
	 * @param array $tourists
	 * @param array $rateGroup
	 * @param array $permitFee
	 * @return array
	 */
	public static function getRateGroupsForCart(array $tourists, array $rateGroup, array $permitFee)
	{
		$rateGrpIds = [];
		$postedPermitByRate = [];

		foreach ($tourists as $index => $tourist) {
			if ($tourist) {
				$rateGrpIds[] = $rateGroup[$index];
				$postedPermitByRate[$rateGroup[$index]] = $permitFee[$index] ?? 0;
			}
		}

		if (empty($rateGrpIds)) {
			return []; // Return empty collection if no valid rate groups exist
		}

		$rateGroups = DB::table('tour_package_rates as tpr')
			->select([
				'tpr.id',
				'tpr.rate_for',
				'tpr.rate',
				'tpr.permit_fee',
				'tpr.additional_tax_percentage',
				'tpr.processing_charge_percentage',
				'tpr.additional_charge',
				'tpr.cash_rate',
				'tpr.cash_tax',
				'tpr.is_cash_rate',
				'tpr.partner_fee_type',
				'tpr.partner_fee_percentage',
				'tpr.partner_fee_amount',
				'tpr.is_infant_rate'
			])
			->join('tour_packages as tp', 'tp.id', '=', 'tpr.tour_package_id')
			->where('tpr.deleted_by', 0)
			->whereIn('tpr.id', $rateGrpIds)
			->orderBy('tpr.orderby', 'asc')
			->get();

		// Affiliates cannot set the permit fee: force the rate's value (server source of truth).
		// Tour Operators/Admins may legitimately waive the fee, so keep their posted value.
		// Built aligned to $rateGroups order so it matches the consumer's indexing.
		$isAffiliate = (userType() === 'Affiliate');
		$permitFeeArray = [];
		foreach ($rateGroups as $rg) {
			$permitFeeArray[] = $isAffiliate ? (float) $rg->permit_fee : (float) ($postedPermitByRate[$rg->id] ?? 0);
		}

		return [
			'rateGroups' => $rateGroups,
			'permitFeeArray' => $permitFeeArray
		];
	}
	/**
	 * Prepare cart booking data.
	 *
	 * @param object $info
	 * @param array $bookingDetails
	 * @return array
	 */
	public static function prepareCartBookingData(object $info, array $bookingDetails): array
	{
		return [
			'total'                 => $bookingDetails['total'],
			'discount2_value'       => $bookingDetails['discount_amount'],
			'tourists'              => $bookingDetails['total_tourists'],
			'tour_package'          => $info->package_name,
			'date'                  => $info->date,
			'time_from'             => $info->time_from,
			'subtotal'              => $bookingDetails['subtotal'],
			'serviceCommission'     => $bookingDetails['fee'],
			'partnerFeesTotal'    	=> $bookingDetails['partner_fee'],
			'destination'           => $info->stripe_destination_id,
			'slot_time'             => $bookingDetails['slot_time'],
			'tour_operator_id'      => $info->tour_operator_id,
			'statement_descriptor'  => $info->statement_descriptor,
			'card_reader_prefix'    => $info->card_reader_prefix,
			'note'                  => $bookingDetails['tour_operator_note'],
			'tour_package_id'       => $info->tour_package_id,
			'addon_price'           => $bookingDetails['addon_price'],
			'addon_fee'             => $bookingDetails['addon_fee'],
			'addon_total'           => $bookingDetails['addon_total'],
			'custom_charge_amount'  => $bookingDetails['custom_charge_amount'],
			'custom_charge_fees'    => $bookingDetails['custom_charge_fees'],
			'tip_amount'           	=> $bookingDetails['tip_amount'] ?? 0,
			'tip_fees'             	=> $bookingDetails['tip_fees'] ?? 0
		];
	}
	/**
	 * Prepare booking data for processing.
	 *
	 * @param object $tourBooking Tour booking details.
	 * @param object $info Tour package information.
	 * @param float $tt Total amount.
	 * @param float $discountAmt Discount applied.
	 * @param int $totalTouristsA Total number of tourists.
	 * @param float $subtt Subtotal before additional fees.
	 * @param float $fee Service commission fee.
	 * @param string $slotTime Selected slot time.
	 * @param string|null $tourOperatorNote Additional notes from the tour operator.
	 * @return array Booking data array.
	 */
	public static function prepareBookingData(
		$tourBooking,
		$info,
		$tt,
		$discountAmt,
		$totalTouristsA,
		$subtt,
		$fee,
		$slotTime,
		$tourOperatorNote = null
	): array {
		return [
			'total'                 => $tt,
			'discount2_value'       => $discountAmt,
			'tourists'              => $totalTouristsA,
			'booking_id'            => $tourBooking->id,
			'customer_id'           => $tourBooking->customer_id,
			'customer_name'         => $tourBooking->name,
			'customer_email'        => $tourBooking->email,
			'customer_phone'        => $tourBooking->phone_number,
			'tour_package'          => $info->package_name,
			'date'                  => $info->date,
			'time_from'             => $info->time_from,
			'subtotal'              => $subtt,
			'serviceCommission'     => $fee,
			'destination'           => $info->stripe_destination_id,
			'slot_time'             => $slotTime,
			'tour_operator_id'      => $info->tour_operator_id,
			'statement_descriptor'  => $info->statement_descriptor,
			'card_reader_prefix'    => $info->card_reader_prefix,
			'note'                  => $tourOperatorNote,
			'tour_package_id'       => $info->tour_package_id,
		];
	}
	/**
	 * Prepare booking details for dispatching events.
	 *
	 * @param object $tourBooking Tour booking details.
	 * @param object $info Tour package information.
	 * @param float $tt Total amount.
	 * @param float $subtt Subtotal before additional fees.
	 * @param string $paymentMethod Payment method used.
	 * @param bool $isCashFees Whether cash fees apply.
	 * @param array $touristGroups Groups of tourists.
	 * @param int $totalTouristsA Total number of tourists.
	 * @param object $slotInfo Slot information.
	 * @param string $slotTime Selected slot time.
	 * @param int $affiliateCustomer Affiliate customer ID (if applicable).
	 * @param $splitValue Payment split value.
	 */
	public static function prepareBookingDetails(
		$tourBooking,
		$info,
		$tt,
		$subtt,
		$paymentMethod,
		$isCashFees,
		$touristGroups,
		$totalTouristsA,
		$slotInfo,
		string $slotTime,
		$affiliateCustomer = 0,
		$splitValue = null,
		$totalInfants = 0
	): array {
		return [
			'id'                      => $tourBooking->id,
			'prefixedId'              => bookingPrefix($tourBooking->id),
			'cust_name'               => $tourBooking->name,
			'cust_id'                 => $tourBooking->customer_id,
			'pkg_id'                  => $info->tour_package_id,
			'pkg_name'                => $info->package_name,
			'paid'                    => ($paymentMethod === "Pay Later") ? '' : $subtt,
			'total'                   => ($paymentMethod === "Card" || ($isCashFees && $paymentMethod === 'Cash')) ? $tt : $subtt,
			'tour_slot_id'            => $slotTime,
			'tour_d_t'                => "{$info->date} {$info->time_from}",
			'booking_d_t'             => $tourBooking->created_at,
			't_groups'                => implode(' | ', $touristGroups),
			'tour_operator_id'        => $info->tour_operator_id,
			'tourists'                => $totalTouristsA,
			'infants'                 => $totalInfants,
			'is_affiliate'            => 'No',
			'notif_for'               => 'admin',
			'date'                    => $info->date,
			'time_from'               => $info->time_from,
			'time_to'                 => $info->time_to,
			'package_has_slots'       => $info->package_has_slots,
			'seats'                   => $info->seats,
			'payment_method'          => $paymentMethod,
			'booked_seats'            => $slotInfo->booked_seats,
			'broadcaster_user_id'     => Auth::id(),
			'env'                     => config('app.env'),
			'invoiceStatus'           => 0,
			'BookingTotal'            => ($paymentMethod === "Pay Later") ? 0 : $tourBooking->BookingTotal,
			'RefundAmount'            => 0,
			'PaymentTotal'            => ($paymentMethod === "Pay Later") ? 0 : $tourBooking->BookingTotal,
			'paid_split'              => $splitValue ?? null,
			'domain'                  => url('/'),
			'background_colour_class' => self::determineBackgroundColorClass($tourBooking, $affiliateCustomer),
		];
	}

	/**
	 * Determine background color class for booking notification.
	 * 
	 * @param object $tourBooking The booking record
	 * @param int $affiliateCustomer The affiliate customer ID (0 for walk-in)
	 * @return string The background color class
	 */
	private static function determineBackgroundColorClass($tourBooking, $affiliateCustomer): string
	{
		// Check if it's a soft booking (reserved for affiliate)
		if ($tourBooking->is_reserved_for_affiliate) {
			return 'affiliate-soft bg-[#E4FFE6] hover:bg-[#E4FFF6]';
		}

		// Check if an affiliate customer was selected (affiliateCustomer != 0)
		if ($affiliateCustomer != 0) {
			return 'affiliate-catch bg-[#ff03] hover:bg-[#ff05]';
		}

		// Default to normal booking
		return 'normal-booking bg-[#f4feff] hover:bg-[#e4eeff]';
	}

	/**
	 * Prepare card booking data for processing.
	 *
	 * @param object $tourBooking Tour booking details.
	 * @param object $info Tour package information.
	 * @param float $tt Total amount.
	 * @param float $subtt Subtotal before additional fees.
	 * @param float $fee Service commission fee.
	 * @param string $slotTime Selected slot time.
	 * @param string $tourOperatorNote Note from the tour operator.
	 * @param array $booking Notification information.
	 * @param int $totalTouristsA Total number of tourists.
	 * @param float $discountAmt Discount amount applied.
	 * @param float $addonPrice Price of add-ons.
	 * @param float $addonFee Additional fees for add-ons.
	 * @param float $addonTotal Total price for add-ons.
	 * @param array $customChargeDetails Custom charge details.
	 * @param float $customChargeAmount Total custom charge amount.
	 * @param float $customChargeFees Fees for custom charges.
	 * @return array Returns an array of formatted booking data.
	 */
	public static function prepareCardBookingData(
		$tourBooking,
		$info,
		$tt,
		$subtt,
		$fee,
		$slotTime,
		$tourOperatorNote,
		$booking,
		$totalTouristsA,
		$discountAmt,
		$addonPrice,
		$addonFee,
		$addonTotal,
		$customChargeDetails,
		$customChargeAmount,
		$customChargeFees,
		$tipAmount,
		$tipFees,
		$partnerFees,
		$isPartnerPackage
	): array {
		return [
			'total'                  => $tt,
			'discount2_value'        => $discountAmt,
			'tourists'               => $totalTouristsA,
			'booking_id'             => $tourBooking->id,
			'group_id'               => $tourBooking->group_id,
			'tour_package'           => $info->package_name,
			'date'                   => $info->date,
			'time_from'              => $info->time_from,
			'isPartnerPackage'     	 => $isPartnerPackage,
			'subtotal'               => $subtt,
			'serviceCommission'      => $fee,
			'partnerFees'          	 => $partnerFees,
			'destination'            => $info->stripe_destination_id,
			'partner_destination_id'     => $info->partnerDestinationId,
			'slot_time'              => $slotTime,
			'tour_operator_id'       => $info->tour_operator_id,
			'statement_descriptor'   => $info->statement_descriptor,
			'card_reader_prefix'     => $info->card_reader_prefix,
			'note'                   => $tourOperatorNote,
			'tour_package_id'        => $info->tour_package_id,
			'bookingNotifInfo'       => $booking,
			'addon_price'            => $addonPrice,
			'addon_fee'              => $addonFee,
			'addon_total'            => $addonTotal,
			'custom_charge'          => $customChargeDetails,
			'custom_charge_amount'   => $customChargeAmount,
			'tip_amount'             => $tipAmount,
			'tip_fees'               => $tipFees,
			'custom_charge_fees'     => $customChargeFees,
		];
	}
	/**
	 * Prepare booking data for processing.
	 *
	 * @param object $master Master booking details.
	 * @param array $cardBookingsData Processed card bookings data.
	 * @param float $totalCartAmount Total amount for the cart.
	 * @param float $totalCartFees Total fees associated with the cart.
	 * @param \Illuminate\Http\Request $request HTTP request data.
	 * @param string $splitValue Value for split payments.
	 * @param object $info Tour package and operator information.
	 * @param float $totalCustomCharge Total custom charge applied.
	 * @param float $totalCustomChargeFees Total custom charge fees.
	 * @return array Returns formatted booking data.
	 */
	public static function prepareBookingDataForComboCardBookings(
		$master,
		array $cardBookingsData,
		float $totalCartAmount,
		float $totalCartFees,
		$request,
		string $splitValue,
		$info,
		$totalCustomCharge,
		$totalCustomChargeFees,
		$totalTipAmount,
		$totalTipFees
	): array {
		return [
			'customer_id'           => $master->customer_id,
			'customer_name'         => $master->name,
			'customer_email'        => $master->email,
			'customer_phone'        => $master->phone_number,
			'group_id'              => $master->group_id,
			'data'                  => $cardBookingsData,
			'total'                 => $totalCartAmount,
			'fees'                  => $totalCartFees,
			'booked_by_staff'       => $request->booked_by_staff,
			'split_value'           => $splitValue,
			'api'                   => $request->api,
			'splitCount'            => $request->dividedBy,
			'destination'           => $info->stripe_destination_id,
			'totalCustomCharge'     => $totalCustomCharge,
			'totalCustomChargeFees' => $totalCustomChargeFees,
			'totalTipAmount'        => $totalTipAmount,
			'totalTipFees'          => $totalTipFees,
			'payment_intent_id'     => $request->payment_intent_id ?? '',
		];
	}

	/**
	 * Prepare the tour package data for cart
	 *
	 * @param Request $request
	 * @param int $tourPackageId
	 * @param object $info
	 * @param array $additionalData
	 * @return array
	 */
	public static function preparePackageData(Request $request, int $tourPackageId, object $info, array $additionalData): array
	{
		return [
			'slot_time'                 => $request->slot_time,
			'tour_package_id'           => $tourPackageId,
			'is_group_rate'             => $additionalData['is_group_rate'],
			'is_partner_package'        => $additionalData['is_partner_package'],
			'tour_package_name'         => $info->package_name,
			'date'                      => $info->date,
			'time_from'                 => $info->time_from,
			'booked_by_staff'           => $request->booked_by_staff,
			'tour_operator_note'        => $request->tour_operator_note,
			'is_reserved_for_affiliate' => $request->is_reserved_for_affiliate,
			'hotel_id'                  => $request->hotel_id ?? '',
			'permit_fee'                => $additionalData['permit_fee'],
			'rate_group'                => $additionalData['rate_group'],
			'tourists'                  => $additionalData['tourists'],
			'infants'                   => $additionalData['infants'],
			'tourist_groups'            => $additionalData['tourist_groups'],
			'customer_comment'          => $request->customer_comment,
			'subtt'                     => $additionalData['subtt'],
			'fee'                       => $additionalData['service_commission'],
			'total'                     => $additionalData['total'],
			'cashSubtt' 				=> $additionalData['cashSubtt'],
			'cashTotal' 				=> $additionalData['cashTotal'],
			'cashFee' 					=> $additionalData['cashServiceCommission'],
			'discount_type'             => $request->has('discount_type') ? $request->discount_type : '',
			'discount_amount'           => $additionalData['discount_amount'],
			'discount_percentage'       => $additionalData['discount_percentage'],
			'discount_amount_cash'		=> $additionalData['discount_amount_cash'],
			'custom_charge_data'        => $request->custom_charge_data,
			'custom_charge'             => $additionalData['custom_charge'],
			'custom_charge_fees'        => $additionalData['custom_charge_fees'],
			'custom_charge_total'       => $additionalData['custom_charge_total'],
			'discount_promo_code'       => $additionalData['discount_promo_code'],
			'tour_promotion_id'         => $additionalData['tour_promotion_id'],
			'package_has_slots'         => $info->package_has_slots,
			'contact_data'              => $additionalData['contact_data'] ?? '',
			'tip_data'        			=> $request->tip_data,
			'tip_amount' 				=> $additionalData['tip_amount'],
			'tip_fees' 					=> $additionalData['tip_fees'],
			'tip_total' 				=> $additionalData['tip_total'],
			'pax_addons_data'           => $request->pax_addons_data,
			'addons_data'               => $request->addons_data,
			'nat_fees_percentage'       => $additionalData['nat_fees_percentage'] ?? 0,
		];
	}
	/**
	 * Calculate add-on prices for tour packages.
	 *
	 * @param array $cart
	 * @return array
	 */
	public static function calculateAddonPrices(array $cart): array
	{
		foreach ($cart['tour_packages'] as &$package) {
			$natFees = (float) ($package['nat_fees_percentage'] ?? 0);
			[$price, $fee, $total] = self::computeAddonAmounts($package, $natFees);
			$package['addon_price'] = $price;
			$package['addon_fee']   = $fee;
			$package['addon_total'] = $total;
		}

		return $cart;
	}
	/**
	 * Calculate destination amount and service commission.
	 *
	 * @param array $data
	 * @param array $cardBookingsData
	 * @param object $customer
	 * @param int $oldBookingID
	 * @return float
	 */
	public static function calculateServiceCommissionForCard(array $data, array $cardBookingsData, object $customer, int $oldBookingID): float
	{
		$destinationAmount = 0;

		foreach ($cardBookingsData as $booking) {
			$destinationAmountPackage = 0;

			if ($booking['booking_id'] < $oldBookingID) {
				$feePercentage = ($customer->is_affiliate === 'Yes') ? 2.9 : 8.9;
				$destinationAmountPackage = $data['total'] - ($data['total'] * $feePercentage / 100);
			} else {
				$transaction = BookingTransact::where('tour_booking_id', $booking['booking_id'])
					->where('deleted_by', 0)
					->first();

				if ($transaction) {
					$stripeFee = $transaction->stripe_fees_percentage / 100;
					$amountToCalculate = !empty($data['split_value']) ? $data['total'] : $booking['total'];
					$destinationAmountPackage = $amountToCalculate - ($amountToCalculate * $stripeFee);
				}
			}

			$destinationAmount += $destinationAmountPackage;
		}

		$serviceCommission = roundout($data['total'] - $destinationAmount, 2);
		return $serviceCommission;
	}
	/**
	 * Calculate custom charge fee and total custom charge.
	 *
	 * @param string $customChargeData JSON-encoded custom charge data.
	 * @param float $natFeesPercentage National fee percentage.
	 * @return array Associative array with 'customChargeFee' and 'totalCustomCharge'.
	 */
	public static function calculateCustomCharge(string $customChargeData, float $natFeesPercentage): array
	{
		$customChargeFee = $customChargeAmount = $totalCustomCharge = 0;

		if (!empty($customChargeData)) {
			$customData = json_decode($customChargeData);

			if (isset($customData->custom_data)) {
				$customChargeAmount = (float) $customData->custom_data->custom_charge_amount;

				// Check if the charge is taxable and calculate the fee
				if (!empty($customData->custom_data->taxable)) {
					$customChargeFee = roundout(($customChargeAmount * $natFeesPercentage / 100), 2);
				}

				$totalCustomCharge = $customChargeAmount + $customChargeFee;
			}
		}

		return [
			'customChargeFee' => $customChargeFee,
			'totalCustomCharge' => $totalCustomCharge,
			'customChargeAmount' => $customChargeAmount
		];
	}
	/**
	 * Retrieve cart bookings with related details.
	 *
	 * @param string $cartId Cart ID.
	 * @return array List of cart bookings.
	 */
	public static function getCartBookings(string $cartId): array
	{
		$cartBookings = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tbm.cart_id', $cartId)
			->select([
				'tbm.id as booking_id',
				'tbm.tourists',
				'tst.time_from',
				'tsd.date',
				'tp.name AS tour_package',
			])
			->get()
			->toArray();

		return array_map(function ($cartBooking) {
			return [
				'booking_id' => $cartBooking->booking_id,
				'tourists' => $cartBooking->tourists,
				'time_from' => $cartBooking->time_from,
				'date' => $cartBooking->date,
				'tour_package' => $cartBooking->tour_package,
			];
		}, $cartBookings);
	}

	/**
	 * Calculate the total booking amount, including processing fees and service commissions.
	 * 
	 * @param object $bookingTransactions
	 * @param string $paymentMethod
	 * @param bool $isCashFees
	 * @param Request $request
	 * @param object $info
	 * @param float $natFeesPercentage
	 * @param float $stripeFeesPercentage
	 * @return array
	 */
	public static function calculateTotalAmount($bookingTransactions, $paymentMethod, $isCashFees, $request, $info, $natFeesPercentage, $stripeFeesPercentage, $saveBookingT = false)
	{
		$cashPriceEnabled = cashPriceEnabled();
		$total = 0;
		$serviceCommissionTotal = 0;
		$partnerFeesTotal = 0;

		// Calculate the booking amount
		foreach ($bookingTransactions as $tbTransaction) {
			$isCashRate = $tbTransaction->packageRate->is_cash_rate;
			$isCashPriceApplied = ($cashPriceEnabled == 1 && $isCashRate == 1 && $paymentMethod == 'Cash');
			$rate = ($isCashPriceApplied && $tbTransaction->packageRate->cash_rate > 0) ? $tbTransaction->packageRate->cash_rate : $tbTransaction->rate;
			$permitFee = $tbTransaction->permit_fee;
			$additionalCharge = ($isCashPriceApplied && $tbTransaction->packageRate->cash_tax > 0) ? $tbTransaction->packageRate->cash_tax : $tbTransaction->additional_charge;
			$tourists = $tbTransaction->tourists;
			$partnerFees = $tbTransaction->partner_fees_amount;

			if ($paymentMethod == 'Card' || ($isCashFees == '1' && $paymentMethod == 'Cash')) {
				$procFee = $tbTransaction->packageRate->processing_charge_percentage;
				if ($info->discount2_value) {
					if ($info->is_group_booking == 1) {
						$serviceCommissionPax = roundout((($rate + $additionalCharge + $partnerFees) * $natFeesPercentage / 100), 2);
					} else {
						$serviceCommissionPax = roundout((($rate + $additionalCharge + $permitFee + $partnerFees) * $natFeesPercentage / 100), 2);
						$serviceCommissionPax = $tourists * $serviceCommissionPax;
					}
					$serviceCommissionTotal += $serviceCommissionPax;
				}
			} else {
				$procFee = 0;
			}

			$partnerFeesTotal += $partnerFees * $tourists;
			// Calculate subtotal based on group or individual booking
			$subTotal = $info->is_group_booking
				? $rate + $additionalCharge + $partnerFees
				: ($rate + $permitFee + $additionalCharge + $partnerFees) * $tourists
					- BookingService::calculatePendingPermitFeeAdjustment($tbTransaction->refund_permitfee_status, $permitFee);

			// Calculate total including processing fee
			$total += $procFee
				? $subTotal + $subTotal * ($procFee / 100)
				: $subTotal;

			if ($saveBookingT) {
				// Update transaction details
				$packageRate = $tbTransaction->packageRate;
				if ($isCashPriceApplied && $packageRate->cash_rate > 0) {
					$tbTransaction->rate = $packageRate->cash_rate;
					$tbTransaction->additional_charge = $packageRate->cash_tax;
					$tbTransaction->is_cash_rate = 1;
				}
				$tbTransaction->processing_charge_percentage = $procFee;
				$tbTransaction->nat_fees_percentage = $natFeesPercentage;
				$tbTransaction->stripe_fees_percentage = $stripeFeesPercentage;
				$tbTransaction->total = $procFee
					? $subTotal + $subTotal * ($procFee / 100)
					: $subTotal;

				$tbTransaction->save();
			}
		}

		return compact('total', 'serviceCommissionTotal', 'partnerFeesTotal');
	}

	public static function prepareBookingDataForMakeCartPayment(array $tourBooking, int $bookingId, string $paymentType, float $total): array
	{
		$tGroupsChanged = BookingService::tGroupsString($bookingId);
		$invoiceStatus = self::getInvoiceStatus($bookingId);

		return [
			'id'                 => $bookingId,
			'prefixedId'         => bookingPrefix($bookingId),
			'cust_name'          => $tourBooking[0]->Name,
			'cust_id'            => $tourBooking[0]->customer_id,
			'pkg_name'           => $tourBooking[0]->package_name,
			'paid'               => $total,
			'total'              => $total,
			'tour_slot_id'       => '',
			'tour_d_t'           => $tourBooking[0]->Date . ' ' . $tourBooking[0]->StartTime,
			'booking_d_t'        => $tourBooking[0]->created_at,
			't_groups'           => $tGroupsChanged ?? '',
			'tour_operator_id'   => $tourBooking[0]->tour_operator_id,
			'is_affiliate'       => 'No',
			'notif_for'          => 'admin',
			'date'               => $tourBooking[0]->Date,
			'time_from'          => $tourBooking[0]->StartTime,
			'time_to'            => $tourBooking[0]->EndTime,
			'package_has_slots'  => $tourBooking[0]->package_has_slots ?? 1,
			'background_colour_class' => BookingService::backgroundColourClass($tourBooking[0]),
			'payment_method'     => $paymentType,
			'broadcaster_user_id' => Auth::id(),
			'env'                => config('app.env'),
			'invoiceStatus'      => $invoiceStatus,
			'BookingTotal'       => $total,
			'RefundAmount'       => 0,
			'PaymentTotal'       => $total,
			'paid_split'         => $total,
			'domain'             => url('/'),
		];
	}
	public static function getInvoiceStatus(int $bookingId): string
	{
		$invoiced = DB::table('invoices as i')
			->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
			->where('ib.tour_booking_id', $bookingId)
			->first(['status']);

		return !empty($invoiced) ? $invoiced->status : '0';
	}
	public static function dispatchBooking(array $booking): void
	{
		BookingNew::dispatch($booking);
		$booking['notif_for'] = 'Tour Operator';
		BookingNew::dispatch($booking);
	}
}
