<?php

namespace App\Services\Pos;

use App\Events\BookingNew;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\RateSlotMapping;
use App\Models\SurchargeDate;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\TimeSlot\TouristService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Utils\IpGeolocationUtils;
use App\Utils\StripeUtils;
use Illuminate\Support\Facades\Auth;

class PosService
{
	/**
	 * Get default customer info for POS 
	 *
	 * @param int $tourOperatorId
	 *
	 * @return mixed
	 */
	public static function getDefaultCustomerInfo($tourOperatorId): mixed
	{
		$posCustomer = tourOperator();
		$customerEmail = $posCustomer->email;
		$customerName = $posCustomer->name;

		$customer = Customer::where('email', $customerEmail)
			->where('tour_operator_id', $tourOperatorId)
			->first();

		if (!$customer) {
			$pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
			$pwd = '';

			for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
				$pwd .= $pwdChars[rand(0, $cl)];
			}

			$customer = Customer::create([
				'tour_operator_id' => $tourOperatorId,
				'name' => $customerName,
				'email' => $customerEmail,
				'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
			]);

			DB::commit();
		}

		return $customer;
	}

	/**
	 * Get slot info 
	 *
	 * @param int $tourPackageId
	 * @param int $slotId
	 *
	 * @return mixed
	 */
	public static function getSlotInfo($tourPackageId, $slotId = ''): mixed
	{
		$todayDate = Carbon::now()->format('Y-m-d');

		$slotQuery = $slotId !== ''
			? " WHERE `tst`.`id` = ? AND `tst`.`tour_package_id` = ?"
			: " WHERE `tst`.`date` = ? AND `tst`.`tour_package_id` = ?";

		$params = $slotId !== '' ? [$slotId, $tourPackageId] : [$todayDate, $tourPackageId];

		$query = "SELECT
            `tst`.`id` AS `slotId`,
            `tst`.`time_from`,
            `tst`.`time_to`,
            `tst`.`date`,
            `tst`.`time_slot_seats` AS `seats`,
            `tp`.`tour_operator_id`,
            `tst`.`tour_package_id`,
            `tp`.`name` AS `package_name`,
            `tp`.`important_notes`,
            `tp`.`things_to_bring`,
            `tp`.`is_group_rate_enabled`,
            `t_o`.`name` AS `tour_operator`,
            `t_o`.`email` AS `tour_op_email`,
            `t_o`.`phone_number` AS `tour_op_phone`,
            `t_o`.`website` AS `tour_op_website`,
            `t_o`.`logo` AS `tour_op_logo`,
            `t_o`.`cancellation_policy`,
            `tp`.`service_commission_percentage`,
            `tp`.`stripe_commission_percentage`,
            `t_o`.`tenant_id`,
            `t_o`.`stripe_destination_id`,
            `t_o`.`id` AS `tour_operator_id`,
            `t_o`.`statement_descriptor`,
            `t_o`.`card_reader_prefix`,
            `tp`.`email` AS `packageEmail`,
            `tp`.`from_name` AS `packageFromName`,
            `tp`.`from_email` AS `packageFromEmail`,
            `tp`.`api_key` AS `packageApiKey`
        FROM `tour_slot_times` AS `tst`
        INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tst`.`tour_package_id`
        INNER JOIN `tour_operators` AS `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
        $slotQuery";

		$info = collect(DB::select($query, $params))->first();

		return $info;
	}

	/**
	 * Calculate group total of individual cart package
	 * @param array $cartPackageData
	 *
	 * @return mixed
	 */
	public static function calculateCartPackageGroupTotals($cartPackageData): mixed
	{
		$rateGroups = $cartPackageData['rateGroups'];
		$natFeesPercentage = $cartPackageData['natFeesPercentage'];
		$stripeFeesPercentage = $cartPackageData['stripeFeesPercentage'];
		$discountType = $cartPackageData['discountType'];
		$discount = $cartPackageData['discount'];
		$promoCode = $cartPackageData['promoCode'];
		$isGroupRateEnabled = $cartPackageData['isGroupRateEnabled'];

		$total = $serviceCommissionTotal = 0;
		$tbTransact = $touristGroups = $addedPeoplesText = [];
		$userType = userType();
		$errorMessage = '';
		$totalTourists = 0;
		foreach ($rateGroups as $rateGroup) {
			$rateGroupInfo = DB::table('tour_package_rates as tpr')
				->where('tpr.id', $rateGroup['id'])
				->first([
					'tpr.id',
					'tpr.rate_for',
					'tpr.rate',
					'tpr.additional_tax_percentage',
					'tpr.permit_fee',
					'tpr.processing_charge_percentage',
					'tpr.additional_charge'
				]);

			if (!$rateGroupInfo) {
				$errorMessage = 'Rategroup not found.Please check packages in the cart.';
			}

			$groupTotal = 0;
			$touristGroups[] = $rateGroupInfo->rate_for[0] . ':' . $rateGroup['tourists'];

			if ($isGroupRateEnabled == 1) {
				$groupTotal = $rateGroupInfo->rate +
					$rateGroupInfo->additional_charge;
			} else {
				$groupTotal = $rateGroup['tourists'] * ($rateGroupInfo->rate +
					$rateGroupInfo->additional_charge +
					$rateGroupInfo->permit_fee
				);
			}


			if ($discountType == '' && $discount == 0) {

				if ($isGroupRateEnabled == 1) {
					$serviceCommissionPax = roundout(($rateGroupInfo->rate + $rateGroupInfo->additional_charge) * $natFeesPercentage / 100, 2);
					$serviceCommissionTotal = $serviceCommissionPax;
				} else {
					$serviceCommissionPax = roundout(($rateGroupInfo->rate + $rateGroupInfo->additional_charge + $rateGroupInfo->permit_fee) * $natFeesPercentage / 100, 2);
					$serviceCommissionPax = $rateGroup['tourists'] * $serviceCommissionPax;
					$serviceCommissionTotal += $serviceCommissionPax;
				}
			}

			$groupTotal += ($groupTotal * (float) $rateGroupInfo->processing_charge_percentage) / 100;

			$tbTransact[] = [
				'tour_package_rate_id' => $rateGroup['id'],
				'tourists' => $rateGroup['tourists'],
				'processing_charge_percentage' => $rateGroupInfo->processing_charge_percentage,
				'nat_fees_percentage' => $natFeesPercentage,
				'stripe_fees_percentage' => $stripeFeesPercentage,
				'additional_tax_percentage' =>  $rateGroupInfo->additional_tax_percentage,
				'additional_charge' =>  $rateGroupInfo->additional_charge,
				'permit_fee' => ($isGroupRateEnabled == 1) ? 0 : $rateGroupInfo->permit_fee,
				'rate' => $rateGroupInfo->rate,
				'total' => $groupTotal,
				'is_group_rate' => 0,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'created_at' => Carbon::now(),
			];

			$addedPeoplesText[] = ($isGroupRateEnabled == '1') ? 'Person(s) ' . $rateGroup['tourists'] : $rateGroupInfo->rate_for . " person " . $rateGroup['tourists'];

			$totalTourists += $rateGroup['tourists'];
			$total += $groupTotal;
		}

		$discountAmt = 0;
		if ($discountType != '' && $discount > 0) {
			$discountAmt = ($discountType == 'Percent') ? round(($total * $discount) / 100, 2) : round($discount, 2);

			$total -= $discountAmt;
			$serviceCommissionTotal = roundout(($total * $natFeesPercentage / 100), 2);
		}

		return [
			'total' => $total,
			'serviceCommissionTotal' => $serviceCommissionTotal,
			'discountAmt' => $discountAmt,
			'discountPercentage' => ($discountType == 'Percent') ? $discount : 0,
			'discountPromoCode' => $promoCode,
			'errorMessage' => $errorMessage,
			'tbTransact' => $tbTransact,
			'totalTourists' => $totalTourists,
			'touristGroups' => $touristGroups,
			'addedPeoplesText' => $addedPeoplesText
		];
	}

	/**
	 * Send notification of new booking
	 *
	 * @return mixed
	 */
	public static function notificationOfNewBooking($tourBooking, $info, $paymentMethod, $subtt, $tt, $touristGroups, $totalTouristsA, $isCashFees): mixed
	{
		$userType = userType();
		$slotInfo = DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tst.id', $info->slotId)
			->when(($userType == 'Tour Operator' || $userType == 'Affiliate'), function ($query) {
				$query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			})
			->select([
				DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
			])
			->first();

		$booking = [
			'id' => $tourBooking->id,
			'prefixedId' => bookingPrefix($tourBooking->id),
			'cust_name' => $tourBooking->name,
			'cust_id' => $tourBooking->customer_id,
			'pkg_id' => $info->tour_package_id,
			'pkg_name' => $info->package_name,
			'paid' => ($paymentMethod == "Pay Later") ? '' : $subtt,
			'total' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $tt : $subtt,
			'tour_slot_id' => $info->slotId,
			'tour_d_t' => $info->date . ' ' . $info->time_from,
			'booking_d_t' => $tourBooking->created_at,
			't_groups' => implode(' | ', $touristGroups),
			'tour_operator_id' => $info->tour_operator_id,
			'tourists' => $totalTouristsA,
			'is_affiliate' => 'No',
			'notif_for' => 'admin',
			'date' => $info->date,
			'time_from' => $info->time_from,
			'time_to' => $info->time_to,
			'seats' => $info->seats,
			'payment_method' => $paymentMethod,
			'booked_seats' => $slotInfo->booked_seats,
			'broadcaster_user_id' => Auth::id(),
			'env' => config('app.env'),
			'invoiceStatus' => 0,
			'BookingTotal' => ($paymentMethod == "Pay Later") ? 0 : $tourBooking->BookingTotal,
			'RefundAmount' => 0,
			'payment_total' => ($paymentMethod == "Pay Later") ? 0 : $tourBooking->BookingTotal,
			'paid_split' => !empty($splitValue) ? $splitValue : null,
			'domain' => url('/'),
			'background_colour_class' => 'normal-booking'
		];

		BookingNew::dispatch($booking);
		$booking['notif_for'] = 'Tour Operator';
		BookingNew::dispatch($booking);

		return $booking;
	}

	/**
	 * Create metadata and description for the stripe
	 * @param array $data
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
					'split_payment' => 0,
					'bookings' => json_encode($cartPackagesInfo),
					'tenant_id' => tenant()->id,
				]
			];
		}

		return $stripeMetadata;
	}

	/**
	 * Get age groups
	 * @param string $bookingStatus
	 * @param array $bookingT
	 * 
	 * 
	 * @return array
	 */
	public static function ageGroups($bookingStatus, $bookingT, $deletedAgeGroup = []): mixed
	{
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

		if ($bookingStatus == "Cancelled") {
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
				foreach ($bookingT as $bt) {
					$resultArray[] = $bt->rate_for . " " . $bt->tourists;
				}
			}
		}

		$ageGroups = (!empty($resultArray)) ? implode("<br>", $resultArray) : null;

		return $ageGroups;
	}

	/**
	 * Get info of tour operator
	 * @param object $bookingM
	 * 
	 * 
	 * @return mixed
	 */
	public static function tourOpInfo($bookingM): mixed
	{
		$tourOpPhoneEmail = $custPhoneEmail =  '';

		if ($bookingM->tour_op_phone != '' && $bookingM->tour_op_email != '')
			$tourOpPhoneEmail = e($bookingM->tour_op_phone) . '<br>' . e($bookingM->tour_op_email);
		elseif ($bookingM->tour_op_email != '')
			$tourOpPhoneEmail = e($bookingM->tour_op_email);
		elseif ($bookingM->tour_op_phone != '')
			$tourOpPhoneEmail = e($bookingM->tour_op_phone);


		return $tourOpPhoneEmail;
	}

	/**
	 * Get info of customer
	 * @param object $bookingM
	 * 
	 * 
	 * @return mixed
	 */
	public static function customerInfo($bookingM): mixed
	{
		$custPhoneEmail =  '';

		if ($bookingM->custPhone != '' && $bookingM->custEmail != '')
			$custPhoneEmail = e($bookingM->custPhone) . '<br>' . e($bookingM->custEmail);
		elseif ($bookingM->custEmail != '')
			$custPhoneEmail = e($bookingM->custEmail);
		elseif ($bookingM->custPhone != '')
			$custPhoneEmail = e($bookingM->custPhone);



		return  $custPhoneEmail;
	}

	/**
	 * Get details of POS order
	 * @param int $orderId
	 * @param string $customerEmail
	 * 
	 * @return mixed
	 */
	public static function posOrderDetails($orderId, $customerEmail = ''): mixed
	{
		$comboBookings = [];
		$comboSubtotal = 0;
		$comboBookingFeesTotal = 0;
		$comboBookingTotal = 0;
		$comboRefundTotal = 0;
		$comboNonRefundableFeesTotal = 0;
		$comboPaymentTotal = 0;
		$logo = '';
		$tenantId = '';

		$cartBookings = TourBooking::where('group_id', $orderId)->get(['id']);
		if ($cartBookings) {
			if ($customerEmail != '') {
				DB::table('tour_bookings_master')
					->where('group_id', $orderId)
					->update([
						'email' => $customerEmail,
						'updated_at' => mysqlDT(),
						'updated_by' => Auth::id(),
						'updated_by_user_type' => session('userType'),
					]);
			}
			foreach ($cartBookings as $cartBooking) {
				$bookingM = self::getBookingDetails($cartBooking->id);
				$bookingT = self::getBookingTransactions($cartBooking->id);

				$trans = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
				$historyPaymentTime = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation', 'Payment')->first();

				if (!empty($historyPaymentTime)) {
					$tourBookingHistory = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation_at', '>', $historyPaymentTime->operation_at)
						->where(function ($query) {
							return $query->where('operation', '=', 'Refund')
								->orWhere('operation', '=', 'Cancelled');
						})
						->get();
				}

				$paymentOnly = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation', 'Payment')->get();
				$paymentTrans = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->get(['id', 'tour_booking_id', 'amount', 'fee_amount', 'via', 'payment_type']);

				$paymentTransCount = count($paymentTrans);
				$paymentOnlyCheck = 0;
				foreach ($paymentOnly as $value) {
					$details = json_decode($value->details);
					$paymentOnlyCheck += $details->amount;
				}

				$transP = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->orderBy('id', 'ASC')->first();

				$paidF = !empty($trans) ? $paymentOnlyCheck : 0;

				$refundData = self::calculateRefunds(
					$tourBookingHistory ?? null,
					$paymentOnlyCheck,
					$historyPaymentTime
				);
				$amount = $refundData['amount'];
				$refundKeepUsDt = $refundData['refundKeepUs'];
				$deletedAgeGroup = $refundData['deletedAgeGroup'];
				$paidF = $refundData['paidF'];

				$ageGroup = PosService::ageGroups($bookingM->status, $bookingT, $deletedAgeGroup);

				$bookingTotal = 0;
				$refundKeepUs = 0;
				$refundAmount = 0;
				$paymentTotal = 0;
				if (!empty($trans)) {
					$isCardPayment = $transP->payment_type == 1;
					$isCashOrUndefined = $transP->payment_type == 0 || $transP->payment_type === '';

					if ($isCardPayment || ($isCashOrUndefined && $paymentTransCount > 1)) {
						$bookingTotal = $bookingM->booking_total;
					} elseif (!empty($checkAmount)) {
						$bookingTotal = $checkAmount->amount;
					} else {
						$bookingTotal = $bookingM->total;
					}

					$comboBookingTotal += $bookingTotal;
				}
				if ($refundKeepUsDt) {
					$refundKeepUs = $refundKeepUsDt;
					$comboNonRefundableFeesTotal += $refundKeepUsDt;
				}

				if ($amount) {
					$refundAmount = $amount;
					$comboRefundTotal += $amount;
				}
				$paymentTotal = ($paidF > 0) ? $paidF : 0.00;

				$comboPaymentTotal += $paidF;
				$comboSubtotal += ($bookingM->total - $bookingM->service_commission);
				$comboBookingFeesTotal += $bookingM->service_commission;

				// Add-on 
				$pricedAddons = BookingService::fetchAddonsWithPrice($bookingM->id);
				$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($bookingM->id, $bookingT[0]->nat_fees_percentage, $trans);

				// Custom charge service to calculate custom charge if applied
				$customChargePrice = CustomChargeBookingService::customChargePrice($bookingM->id, $paymentTrans);

				$comboBookings[] = [
					'bookingM' => $bookingM,
					'bookingT' => $bookingT,
					'ageGroup' => $ageGroup,
					'customChargePrice' => $customChargePrice,
					'pricedAddons' => $pricedAddons,
					'payLaterAddons' => $payLaterAddons,
					'bookingTotal' => number_format((float)$bookingTotal, 2, '.', ''),
					'refundKeepUs' => number_format((float)$refundKeepUs, 2, '.', ''),
					'refundAmount' => number_format((float)$refundAmount, 2, '.', ''),
					'paymentTotal' => number_format((float)$paymentTotal, 2, '.', '')
				];
			}
		}

		$bookingM = $comboBookings[0]['bookingM'];
		if ($bookingM->logo != '')
			$logo = tourOperatorLogo($bookingM->tenant_id, $bookingM->logo);

		$tenantId = $bookingM->tenant_id;
		$tourOpPhoneEmail = PosService::tourOpInfo($bookingM);
		$tourOpPhone = $bookingM->tour_op_phone;
		$tourOpEmail = $bookingM->tour_op_email;
		$tourOpWebsite = $bookingM->tour_op_website;
		$custPhoneEmail =  PosService::customerInfo($bookingM);
		$qrCode = posOrderGenerateQr($bookingM->tenant_id, $orderId);
		$bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
		$prefix = (isset($bookingPrefix) && isset($bookingPrefix->value)) ? $bookingPrefix->value : "";

		return [
			'page_title' => 'Print Receipt',
			'comboBookings' => $comboBookings,
			'logo' => $logo,
			'tourOpPhoneEmail' => $tourOpPhoneEmail,
			'tourOpPhone' => $tourOpPhone,
			'tourOpEmail' => $tourOpEmail,
			'tourOpWebsite' => $tourOpWebsite,
			'custPhoneEmail' => $custPhoneEmail,
			'tourOperator' => $bookingM->tour_operator,
			'comboSubtotal' => $comboSubtotal,
			'comboBookingFeesTotal' => $comboBookingFeesTotal,
			'comboBookingTotal' => $comboBookingTotal,
			'comboNonRefundableFeesTotal' => $comboNonRefundableFeesTotal,
			'comboRefundTotal' => $comboRefundTotal,
			'comboPaymentTotal' => $comboPaymentTotal,
			'qrCode' => $qrCode,
			'orderId' => $orderId,
			'prefix' => $prefix,
			'tenantId' => $tenantId,
			'emailType' => config('constants.email_types.pos_order_receipt'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
		];
	}
	/**
	 * Fetch booking details by ID.
	 *
	 * @param int $bookingId
	 * @return object|null
	 */
	private static function getBookingDetails(int $bookingId): ?object
	{
		return DB::table('tour_bookings_master AS tbm')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->leftJoin('tour_promotions AS promo', 'promo.id', '=', 'tbm.tour_promotion_id')
			->where('tbm.id', $bookingId)
			->select([
				'tbm.id',
				'tbm.tourists',
				'tbm.status',
				'tbm.total',
				'tbm.discount',
				'tbm.discount2_value',
				'tbm.discount2_percentage',
				'tbm.service_commission',
				'tbm.created_at',
				'c.is_affiliate',
				'c.is_price_show',
				'tp.name AS tour_package',
				'tp.package_has_slots',
				'tp.package_has_waiver',
				'tp.package_has_permit',
				't_o.name AS tour_operator',
				't_o.email AS tour_op_email',
				't_o.phone_number AS tour_op_phone',
				't_o.website AS tour_op_website',
				't_o.logo',
				't_o.tenant_id',
				'tsd.date',
				'tst.time_from',
				'tst.time_to',
				'tbm.name AS custName',
				'tbm.email AS custEmail',
				'tbm.phone_number AS custPhone',
				'promo.discount_value',
				'promo.discount_value_type',
				'tbm.booking_total',
				'tbm.is_group_booking',
				'tbm.is_partner_booking'
			])
			->first();
	}
	/**
	 * Get transactions for a specific booking.
	 *
	 * @param int $bookingId
	 * @return \Illuminate\Support\Collection
	 */
	private static function getBookingTransactions(int $bookingId): \Illuminate\Support\Collection
	{
		return DB::table('tour_bookings_transact AS tbt')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', $bookingId)
			->where('tbt.deleted_by', 0)
			->orderBy('tpr.orderby', 'asc')
			->select([
				'tbt.id',
				'tbt.tourists',
				'tbt.infants',
				'tbt.processing_charge_percentage',
				'tbt.additional_tax_percentage',
				'tbt.nat_fees_percentage',
				'tbt.permit_fee',
				'tbt.additional_charge',
				'tbt.state_tax',
				'tbt.rate',
				'tbt.total',
				'tpr.rate_for',
			])
			->get();
	}
	/**
	 * Process booking history and calculate refunds.
	 *
	 * @param object|null $tourBookingHistory
	 * @param float $paymentOnlyCheck
	 * @param object|null $historyPaymentTime
	 * @return array
	 */
	private static function calculateRefunds(?object $tourBookingHistory = null, float $paymentOnlyCheck, ?object $historyPaymentTime = null): array
	{
		$amount = 0;
		$refundKeepUs = 0;
		$deletedAgeGroup = [];
		$paidF = $paymentOnlyCheck;

		if ($tourBookingHistory != null) {
			foreach ($tourBookingHistory as $bookingHistory) {
				$details = json_decode($bookingHistory->details);

				// Handle valid refunds
				if (isset($details->refund_type) && !in_array(strtolower($details->refund_type), ["don't refund", "partial refund"])) {
					$amount += $details->amount;

					if (isset($details->deleted)) {
						$deletedAgeGroup[] = $details->deleted;
					}
				}

				// Default "keep us" value
				$details->refund_keep_us = $details->refund_keep_us ?? 0;

				// Calculate refund_keep_us for non-partial refunds
				if (isset($details->refund_type) && strtolower($details->refund_type) !== "partial refund") {
					$refundKeepUs += $details->refund_keep_us;
				}

				// Calculate remaining amount
				$paidF = $paymentOnlyCheck - $amount;

				if (!empty($historyPaymentTime)) {
					$checkAmount = json_decode($historyPaymentTime->details);

					if ($checkAmount->amount == $amount) {
						$paidF = 0;
					}
				}
			}
		}

		return [
			'amount' => $amount,
			'refundKeepUs' => $refundKeepUs,
			'deletedAgeGroup' => $deletedAgeGroup,
			'paidF' => $paidF,
		];
	}
	/**
	 * Get dynamic rates based on date and slots
	 * @param int $orderId
	 * @param string $customerEmail
	 * 
	 * @return mixed
	 */
	public static function getDynamiceRates($tourPackageId, $applyOnDate, $applyOnSlots, $date, $time = '', $minMaxPaxApply = false): mixed
	{
		$customRateDate = isCustomDateRateEnabled($date, $tourPackageId, 0);
		if ($applyOnSlots) {
			$formattedDate = date('Y-m-d', strtotime($date));
			/* START: Custom slot mapping */
			$customDate = SurchargeDate::where('tour_package_id', $tourPackageId)
				->whereDate('date_range_start', '<=', $formattedDate)
				->whereDate('date_range_end', '>=', $formattedDate)
				->whereNull('affiliate_id')
				->orderBy('priority', 'DESC')
				->first();

			$rateSlotMapping = 0;
			if ($customDate && $customDate->apply_on_slots) {
				$timeFrom = date('H:i:s', strtotime($time));
				$rateSlotMapping = RateSlotMapping::where('tour_package_id', $tourPackageId)
					->where('custom_date_id', $customDate->id)
					->where('time_from', $timeFrom)
					->whereNull('affiliate_id')
					->first();
				$customDateId =	$rateSlotMapping ? $customDate->id : 0;

				if ($customDateId) {
					$rateGroups = DB::table('tour_package_rates AS tpr')
						->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
						->where('scheduling_year', date('Y', strtotime($date)))
						->where('tour_package_id', $tourPackageId)
						->where('tpr.deleted_by', 0)
						->whereNull('tpr.affiliate_id')
						->where('tpr.custom_date_id', $customDateId)
						->when($minMaxPaxApply == true, function ($query) {
							$query->where(function ($queryMinMaxPax) {
								return $queryMinMaxPax->where('tpr.min_pax_allowed', '>', 0)
									->orWhere('tpr.max_pax_allowed', '>', 0);
							});
						})
						->orderBy('orderby')
						->get([
							'tpr.id',
							'tpr.rate_for',
							'tpr.is_group_rate',
							'tpr.is_infant_rate',
							'tpr.additional_charge',
							'tpr.min_group_size',
							'tpr.max_group_size',
							'tpr.rate',
							'tpr.permit_fee',
							'tpr.mapping_key',
							'tpr.min_pax_allowed',
							'tpr.max_pax_allowed'
						]);
				} else {
					$rateGroups = DB::table('tour_package_rates as tpr')
						->where([
							'tpr.tour_package_id' => $tourPackageId,
							'tpr.deleted_by' => 0
						])
						->whereNull('tpr.custom_date_id')
						->when($minMaxPaxApply == true, function ($query) {
							$query->where(function ($queryMinMaxPax) {
								return $queryMinMaxPax->where('tpr.min_pax_allowed', '>', 0)
									->orWhere('tpr.max_pax_allowed', '>', 0);
							});
						})
						->where('tpr.scheduling_year', date('Y', strtotime($date)))
						->where('tpr.deleted_by', 0)
						->whereNull('tpr.affiliate_id')
						->get([
							'tpr.id',
							'tpr.rate_for',
							'tpr.is_group_rate',
							'tpr.is_infant_rate',
							'tpr.additional_charge',
							'tpr.min_group_size',
							'tpr.max_group_size',
							'tpr.rate',
							'tpr.permit_fee',
							'tpr.mapping_key',
							'tpr.min_pax_allowed',
							'tpr.max_pax_allowed'
						]);
				}
			} else {
				$rateGroups = DB::table('tour_package_rates as tpr')
					->where([
						'tpr.tour_package_id' => $tourPackageId,
						'tpr.deleted_by' => 0
					])
					->when($customRateDate, function ($query) use ($customRateDate) {
						return $query->where('tpr.custom_date_id', $customRateDate);
					}, function ($query): mixed {
						return $query->whereNull('tpr.custom_date_id');
					})
					->when($minMaxPaxApply == true, function ($query) {
						$query->where(function ($queryMinMaxPax) {
							return $queryMinMaxPax->where('tpr.min_pax_allowed', '>', 0)
								->orWhere('tpr.max_pax_allowed', '>', 0);
						});
					})
					->where('tpr.scheduling_year', date('Y', strtotime($date)))
					->where('tpr.deleted_by', 0)
					->whereNull('tpr.affiliate_id')
					->get([
						'tpr.id',
						'tpr.rate_for',
						'tpr.is_group_rate',
						'tpr.is_infant_rate',
						'tpr.additional_charge',
						'tpr.min_group_size',
						'tpr.max_group_size',
						'tpr.rate',
						'tpr.permit_fee',
						'tpr.mapping_key',
						'tpr.min_pax_allowed',
						'tpr.max_pax_allowed'
					]);
			}
		} else {
			$rateGroups = DB::table('tour_package_rates as tpr')
				->where([
					'tpr.tour_package_id' => $tourPackageId,
					'tpr.deleted_by' => 0
				])
				->when($customRateDate, function ($query) use ($customRateDate) {
					return $query->where('tpr.custom_date_id', $customRateDate);
				}, function ($query): mixed {
					return $query->whereNull('tpr.custom_date_id');
				})
				->when($minMaxPaxApply == true, function ($query) {
					$query->where(function ($queryMinMaxPax) {
						return $queryMinMaxPax->where('tpr.min_pax_allowed', '>', 0)
							->orWhere('tpr.max_pax_allowed', '>', 0);
					});
				})
				->where('tpr.scheduling_year', date('Y', strtotime($date)))
				->where('tpr.deleted_by', 0)
				->whereNull('tpr.affiliate_id')
				->get([
					'tpr.id',
					'tpr.rate_for',
					'tpr.is_group_rate',
					'tpr.is_infant_rate',
					'tpr.additional_charge',
					'tpr.min_group_size',
					'tpr.max_group_size',
					'tpr.rate',
					'tpr.permit_fee',
					'tpr.mapping_key',
					'tpr.min_pax_allowed',
					'tpr.max_pax_allowed'
				]);
		}
		return $rateGroups;
	}
	/**
	 * Summary of requestPermissions
	 * @param mixed $id
	 * @return array
	 */
	public static function requestPermissions($id): array
	{
		// Fetch the default permissions from the config
		$requestPermissions = config('constants.request_permissions');

		// Map request types to their respective policies
		$policyMappings = config('constants.policy_mappings');

		// Fetch POS permission mappings
		$posPermissionsMappings = config('constants.pos_permissions_mappings');

		// Check permissions dynamically
		foreach ($requestPermissions as $type => $permission) {
			if (isset($policyMappings[$type]) && checkPermission($policyMappings[$type])) {
				$requestPermissions[$type] = 'Yes';
			}
		}

		// Fetch all approved requests for the given booking and staff
		$approvedRequests = BookingRequest::where('staff_id', Auth::user()->id)
			->where('order_id', $id)
			->where('status', 'Approved')
			->whereIn('request_type', array_keys($requestPermissions))
			->pluck('request_type');


		// Update based on approved requests
		foreach ($approvedRequests as $requestType) {
			if (isset($requestPermissions[$requestType])) {
				$requestPermissions[$requestType] = 'Yes';
				if (isset($posPermissionsMappings[$requestType])) {
					$requestPermissions[$posPermissionsMappings[$requestType]] = 'Yes';
				}
			}
		}

		// Pass the permissions to the repo file
		return $requestPermissions;
	}
	/**
	 * Get an existing customer or create a new one.
	 *
	 * @param int $tourOperatorId The ID of the tour operator.
	 * @param string $customerEmail The email of the customer.
	 * @param string $customerName Optional. The name of the customer. Defaults to an empty string.
	 * @param string $customerPhone Optional. The phone number of the customer. Defaults to an empty string.
	 * @return Customer The retrieved or newly created customer.
	 */
	public static function getOrCreateCustomer($tourOperatorId, $customerEmail, $customerName = '', $customerPhone = '')
	{
		if ($customerEmail !== '') {
			$customer = Customer::where('email', $customerEmail)
				->where('tour_operator_id', $tourOperatorId)
				->first();

			if (!$customer) {
				$password = self::generatePassword();

				if ($customerName === '') {
					$defaultCustomer = self::getDefaultCustomerInfo($tourOperatorId);
					$customerName = $defaultCustomer->name;
				}

				$customer = Customer::create([
					'tour_operator_id' => $tourOperatorId,
					'name' => $customerName,
					'email' => $customerEmail,
					'phone_number' => self::sanitizePhoneNumber($customerPhone),
					'password' => Hash::make(config('app.debug') ? '123456' : $password),
					'ip_address' => request()->ip(), // Capture IP address for POS customers
				]);

				// Get and store location data for the new POS customer
				if ($customer && $customer->id && request()->ip()) {
					IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, request()->ip());
				}
			}
		} else {
			$customer = PosService::getDefaultCustomerInfo($tourOperatorId);

			if ($customerName !== '') {
				$customer->name = $customerName;
			}

			if ($customerPhone !== '') {
				$customer->phone_number = self::sanitizePhoneNumber($customerPhone);
			}
		}

		return $customer;
	}

	/**
	 * Generate a random password with a given length.
	 *
	 * @param int $length The length of the password. Default is 10.
	 * @return string The generated password.
	 */
	private static function generatePassword($length = 10): string
	{
		$characters = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
		$password = '';

		for ($i = 0, $cl = strlen($characters) - 1; $i < $length; $i++) {
			$password .= $characters[rand(0, $cl)];
		}

		return $password;
	}

	/**
	 * Sanitize a phone number by removing special characters.
	 *
	 * @param string $phoneNumber The phone number to sanitize.
	 * @return string The sanitized phone number.
	 */
	private static function sanitizePhoneNumber($phoneNumber): string
	{
		return str_replace(['(', ')', '+', '-', '.', ' '], '', $phoneNumber);
	}

	/**
	 * Get slot times with booking and package family data.
	 *
	 * @param array $item Contains 'slotId' and 'tourPackageId' keys.
	 *     - slotId: The ID of the tour slot.
	 *     - tourPackageId: The ID of the tour package.
	 * 
	 * @return object|null Returns the slot data or null if not found.
	 */
	public static function getSlotTimes(array $item)
	{
		$year = Carbon::parse($item['slotDate'])->format('Y');
		$fields = [
			'tst.id',
			'tsd.tour_package_id',
			'tsd.date',
			'tst.time_from',
			'tst.time_to',
			'tst.time_slot_seats as seats',
			'tst.bookable_status',
			DB::raw("IFNULL((
                SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id = " . $item['tourPackageId'] . "
                GROUP BY tbm.tour_slot_id, tbm.tour_package_id
            ), 0) AS tourists"),
			DB::raw("IFNULL((
                SELECT DISTINCT tpf.is_parent
                FROM tour_package_families AS tpf
                WHERE tpf.tour_package_id = tsd.tour_package_id
                    AND tst.time_from = tpf.time_from
                    AND tpf.tour_package_id = " . $item['tourPackageId'] . "
					AND tpf.relation_year = $year
            ), 0) AS is_parent"),
			DB::raw("IFNULL((
                SELECT DISTINCT tpf.group_index
                FROM tour_package_families AS tpf
                WHERE tpf.tour_package_id = tsd.tour_package_id
                    AND tst.time_from = tpf.time_from
                    AND tpf.tour_package_id = " . $item['tourPackageId'] . "
					AND tpf.relation_year = $year
            ), 0) AS group_index")
		];

		return DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->where('tst.id', $item['slotId'])
			->where('tsd.tour_package_id', $item['tourPackageId'])
			->where('tsd.status', 'Active')
			->where('tst.bookable_status', 'Open')
			->where('tst.deleted_by', '0')
			->first($fields);
	}
	/**
	 * Get related tour packages based on parent package relations.
	 *
	 * @param int $tourPackageId The ID of the tour package.
	 * @return array The related tour package family data.
	 */
	public static function getRelatedTourPackageFamily(int $tourPackageId, $date): array
	{
		$year = Carbon::parse($date)->format('Y');
		return DB::table('tour_package_families')
			->where('relation_year', $year)
			->whereIn('parent_package_id', function ($query) use ($tourPackageId, $year) {
				$query->select('parent_package_id')
					->from('tour_package_families')
					->where('tour_package_id', $tourPackageId)
					->where('relation_year', $year);
			})
			->get()
			->toArray();
	}
	/**
	 * Calculate total tourists for related tour packages.
	 *
	 * @param array $tourPackageFamily The related tour package family data.
	 * @param object $slotTimes The slot times data.
	 * @return int The total number of tourists.
	 */
	public static function calculateTotalTourists(array $tourPackageFamily, object $slotTimes): int
	{
		$relationPackage = array_filter($tourPackageFamily, function ($obj) use ($slotTimes) {
			return $obj->tour_package_id == $slotTimes->tour_package_id &&
				$obj->group_index == $slotTimes->group_index;
		});

		return TouristService::countTotalTourists($tourPackageFamily, $slotTimes);
	}

	/**
	 * Retrieve active tour packages based on category, department, and search criteria.
	 *
	 * @param int $categoryId - The category ID to filter tour packages.
	 * @param $departmentPackage - List of department-specific package IDs.
	 * @param string|null $search - Search keyword to filter tour packages by name.
	 * @param string $todayDate - The current date for filtering available slots.
	 * @return \Illuminate\Support\Collection - Returns a collection of tour packages.
	 */
	public static function getAvailableTourPackages(int $categoryId,  $departmentPackage, ?string $search, string $todayDate)
	{
		// Build the base query for tour packages
		$tourPackagesQuery = DB::table('tour_packages as tp')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->where('tp.category_id', $categoryId)
			->where('tp.status', '=', "Active")
			->where('tp.show_in_pos', 1)
			->when($departmentPackage, function ($q) use ($departmentPackage) {
				return $q->whereIn('tp.id', $departmentPackage);
			});

		// Apply search filter if provided
		if (!empty($search)) {
			$tourPackagesQuery->where('tp.name', 'LIKE', '%' . $search . '%');
		}

		// Final query execution with slot count and ordering
		$tourPackageData = $tourPackagesQuery->having('slotCount', '>', 0)
			->orderByRaw('CASE WHEN "tp.pos_order" = 0 THEN 0 ELSE 1 END DESC')
			->orderBy('tp.pos_order')
			->get([
				'tp.name as tourPackageName',
				'tp.id as tourPackageId',
				'tp.package_has_slots as packageHasSlots',
				'tp.service_commission_percentage',
				'tp.is_group_rate_enabled as isGroupRateEnabled',
				't_o.tenant_id',
				// Fetching package image
				DB::raw("(SELECT path
                    FROM tour_package_photos AS tpp
                    WHERE tpp.tour_package_id = tp.id
                    AND `tpp`.`placement` = '6'
                    AND `tpp`.`status` = 'Active'
                    AND `tpp`.`deleted_by` = 0
                    LIMIT 1) AS tourPackageImage"),
				// Counting available slots for today's date
				DB::raw("(SELECT COUNT(tst.id)
                    FROM tour_slot_times AS tst
                    WHERE tst.tour_package_id = tp.id
                    AND `tst`.`date` = '" . $todayDate . "'
                    GROUP BY tst.tour_package_id) AS slotCount")
			]);

		return $tourPackageData;
	}
}
