<?php

namespace App\Services\Webhook;

use App\Constants\Constants;
use App\Models\Admin;
use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Services\CardBookingService;
use App\Services\Cart\CartService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\BookingService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Frontend\TourBookingAddonService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PlatformFeeMetadata;
use Illuminate\Support\Facades\Auth;

class StripePaymentIntentSucceededForMakePayment
{
	/**
	 * Execute the service.
	 *
	 * @return void
	 */
	public static function run($paymentIntent)
	{
		if (isset($paymentIntent->metadata->app)) {
			if (isset($paymentIntent->metadata['bookings'])) {
				$bookingIds = json_decode($paymentIntent->metadata['bookings']);
				static::makePaymentForComboBooking($bookingIds, $paymentIntent->id);
			} else {
				$bookingId = trim($paymentIntent->metadata['booking_id']);
				static::makePaymentForBooking($bookingId, $paymentIntent->id);
			}
		}
	}

	/**
	 * Add stripe connected account metadata
	 *
	 * @param mixed $bookingId
	 * @param mixed $stripe
	 * @param mixed $paymentIntentRelational
	 * @param mixed $paymentMethod
	 *
	 * @return void
	 */
	public static function addStripeConnectedMetadata($bookingId, $paymentIntentRelational, $paymentMethod = ''): void
	{
		if (isset($paymentIntentRelational->charges->data[0]->transfer->destination_payment)) {

			if (is_array($bookingId)) {
				$bookingIds = $bookingId;
				$From = ($paymentMethod == 'Manual Card') ? 'iOS Manual Card' : 'iOS Card Reader';
				$customerDetails = [];
				$i = 0;
				foreach ($bookingIds as $booking) {

					$bookingId = $booking->BookingId;
					$tourBooking = TourBooking::with([
						'tourPackage.tourOperator',
						'tourSlotTime.tourSlotDate',
						'customer'
					])
						->find($bookingId);
					$tourPackage = $tourBooking->tourPackage;
					$description = '';
					$lineBreak = chr(0x0D) . chr(0x0A) . ' ' . chr(0x0D) . chr(0x0A);
					if ($i > 0) {
						$description .= $lineBreak;
					}

					$customer = $tourBooking->customer;
					$customerDetails = $customer;

					$description .= 'Booking Id: ' . $bookingId .
						"\nTour Package: " . $tourPackage->name .
						"\nTour Date: " . $tourBooking->tourSlotTime->tourSlotDate->date .
						"\nTour Time: " . $tourBooking->tourSlotTime->time_from .
						"\nTourists: " . $tourBooking->tourists .
						"\nEmail: " . $customer->email .
						"\nFrom: " . $From;

					$comboPackagesIds[] = [
						'BookingId' => $bookingId
					];
					$i++;
				}

				$stripeMetadata = [
					'description' => $description,
					'metadata' => [
						'bookings' => json_encode($comboPackagesIds),
						'customer_name' => $customerDetails->name,
						'customer_phone' => $customerDetails->phone_number,
						'email' => $customerDetails->email,
						'split' => $paymentIntentRelational->metadata->split,
						'is_pax_added' => $paymentIntentRelational->metadata->is_pax_added,
						'from' => $From,
						'section' => $paymentIntentRelational->metadata->section,
						'app' => Constants::STATUS_ONE,
						'tenant_id' => tenant()->id,
					],
				];

				$chargeParams = $stripeMetadata;
				app(PaymentGatewayInterface::class)->updateConnectedPayment(
					$paymentIntentRelational->charges->data[0]->transfer->destination_payment,
					$chargeParams,
					$paymentIntentRelational->charges->data[0]->transfer->destination
				);
			} else {
				$tourBooking = TourBooking::with([
					'tourPackage.tourOperator',
					'tourSlotTime.tourSlotDate',
					'customer'
				])
					->find($bookingId);

				$tourPackage = $tourBooking->tourPackage;

				$customer = $tourBooking->customer;
				if ($paymentMethod == 'Manual Card') {
					$From = 'iOS Manual Card';
				} else {
					$From = 'iOS Card Reader';
				}
				$stripeMetadata = [
					'description' => 'Booking Id: ' . $bookingId .
						"\nTour Package: " . $tourPackage->name .
						"\nTour Date: " . $tourBooking->tourSlotTime->tourSlotDate->date .
						"\nTour Time: " . $tourBooking->tourSlotTime->time_from .
						"\nTourists: " . $tourBooking->tourists + $tourBooking->infants .
						"\nEmail: " . $customer->email .
						"\nFrom: " . $From,
					'metadata' => [
						'booking_id' => $bookingId,
						'tour_package' => $tourPackage->name,
						'tour_date' => $tourBooking->tourSlotTime->tourSlotDate->date,
						'tour_time' => $tourBooking->tourSlotTime->time_from,
						'tourists' => $tourBooking->tourists + $tourBooking->infants,
						'customer_name' => $customer->name,
						'customer_phone' => $customer->phone_number,
						'email' => $customer->email,
						'amount' => $paymentIntentRelational->metadata->amount,
						'fee' => $paymentIntentRelational->metadata->fee,
						'split' => $paymentIntentRelational->metadata->split,
						'is_pax_added' => $paymentIntentRelational->metadata->is_pax_added,
						'from' => $From,
						'section' => $paymentIntentRelational->metadata->section,
						'app' => Constants::STATUS_ONE,
						'tenant_id' => tenant()->id,
					],
				];

				$chargeParams = $stripeMetadata;
				app(PaymentGatewayInterface::class)->updateConnectedPayment(
					$paymentIntentRelational->charges->data[0]->transfer->destination_payment,
					$chargeParams,
					$paymentIntentRelational->charges->data[0]->transfer->destination
				);
			}
		}
	}

	/**
	 * Calculate Service Commission
	 *
	 * @param int $bookingId
	 * @param mixed $paymentIntentRelational
	 * @param mixed $trans
	 * @return float
	 */
	public static function calculatePlatformFees($bookingId, $paymentIntentRelational, $trans)
	{
		$oldBookingId = oldBookingId();

		$info = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
			->where('tbm.id', $bookingId)
			->select([
				'tbm.id',
				'tbm.customer_id',
				'tbm.discount2_value',
				'tbm.discount2_percentage',
				'tbm.is_group_booking',
				'tp.tour_operator_id',
				'tp.service_commission_percentage',
				'tp.stripe_commission_percentage',
				'tst.tour_package_id',
				'tst.date',
				'cus.affiliate_processing_percentage',
				'cus.stripe_affiliate_percentage'
			])
			->first();

		$customer = Customer::where('id', $info->customer_id)
			->where('tour_operator_id', $info->tour_operator_id)
			->withTrashed()
			->first();
		$isBlackoutDate = isBlackoutDateEnabled($info->date);		
		$isGroupBooking = $info->is_group_booking;
		$natFeesPercentage = $info->service_commission_percentage;
		if ($customer->is_affiliate == 'Yes' && !$isBlackoutDate) {
			$natFeesPercentage = $info->affiliate_processing_percentage;
			if (getNextYearCommissionPercentage($info->date)) {
				$affiliateCommission = getAffiliateCommissionPercentage($customer->id);
				$natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
			}
		} elseif (getNextYearCommissionPercentage($info->date)) {
			$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
			$natFeesPercentage = $serviceCommission['service_commission_percentage'];
		}

		$bookingT = BookingTransact::with('packageRate')
			->where('tour_booking_id', $bookingId)
			->get();

		$rate = 0;
		$permitFee = 0;
		$procFee = 0;
		$additionalCharge = 0;
		$tourists = 0;
		$subTotal = 0;
		$total = 0;
		$serviceCommission = 0;
		$discountAmt = 0;
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
				if (((!isset($paymentIntentRelational->metadata->discount_fixed) || $paymentIntentRelational->metadata->discount_fixed == '') || (!isset($paymentIntentRelational->metadata->discount_percentage) || $paymentIntentRelational->metadata->discount_percentage == '')) && $info->discount2_value) {
					if ($isGroupBooking == '1') {
						$serviceCommissionPax = roundout((($rate) * $natFeesPercentage / 100), 2);
						$serviceCommissionTotal = $serviceCommissionPax;
					} else {
						$serviceCommissionPax = roundout((($rate + $additionalCharge + $permitFee) * $natFeesPercentage / 100), 2);
						$serviceCommissionPax = $tourists * $serviceCommissionPax;
						$serviceCommissionTotal += $serviceCommissionPax;
					}
				}
			}

			if ($isGroupBooking == '1') {
				$subTotal = $rate;
				$total += $subTotal;
			} else {
				$subTotal = ($rate + $permitFee + $additionalCharge) * $tourists
					- BookingService::calculatePendingPermitFeeAdjustment($tbTransaction->refund_permitfee_status, $permitFee);
				$total += ($procFee) ? $subTotal + ($subTotal * ($procFee / 100)) : $subTotal;
			}
		}

		$totalForDiscountWithSplit = 0;

		if (empty($trans) && $paymentIntentRelational->metadata->split == 1 && $info->discount2_value > 0 && $paymentIntentRelational->metadata->discount_fixed == '' && $paymentIntentRelational->metadata->discount_percentage == '') {
			$totalForDiscountWithSplit = $total - $info->discount2_value;
		}

		//Discount applied in make payment with split payment
		if (empty($trans) && $paymentIntentRelational->metadata->split == 1 && ($paymentIntentRelational->metadata->discount_percentage != '' || $paymentIntentRelational->metadata->discount_fixed != '')) {
			if ($paymentIntentRelational->metadata->discount_percentage != '')
				$totalForDiscountWithSplit = $total - round(($total * $paymentIntentRelational->metadata->discount_percentage) / 100, 2);
			elseif ($paymentIntentRelational->metadata->discount_fixed != '')
				$totalForDiscountWithSplit = $total - round($paymentIntentRelational->metadata->discount_fixed, 2);
		}

		if ($paymentIntentRelational->metadata->discount_percentage != '') {
			$discountAmt = round(($total * $paymentIntentRelational->metadata->discount_percentage) / 100, 2); //round($request->pr_amount, 2);
		} elseif ($paymentIntentRelational->metadata->discount_fixed != '') {
			$discountAmt = round($paymentIntentRelational->metadata->discount_fixed, 2);
		}

		if ($paymentIntentRelational->metadata->split != 1) {
			$total -= $discountAmt;
		}

		if (isset($info->discount2_value) && $info->discount2_value > 0 && $info->discount2_percentage == 0) {
			$oldDiscountAmt = $info->discount2_value;
			if ($paymentIntentRelational->metadata->split != 1) {
				$total = $total - $info->discount2_value;
			}
		} else if ($info->discount2_percentage > 0) {
			$oldDiscountAmt = number_format(($total * $info->discount2_percentage) / 100, 2, '.', '');
			if ($paymentIntentRelational->metadata->split != 1) {
				$total = $total - $oldDiscountAmt;
			}
		}

		if ($customer->is_affiliate == 'No') {
			if ($bookingId >= $oldBookingId) { // Set booking id 95440
				if ($discountAmt > 0 || $oldDiscountAmt > 0 || $paymentIntentRelational->metadata->split == 1) {
					if ($totalForDiscountWithSplit) {
						$serviceCommission = roundout(($totalForDiscountWithSplit * $natFeesPercentage / 100), 2);
					} else {
						$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
					}
				} else {
					$serviceCommission = $serviceCommissionTotal;
				}
			} else {
				$serviceCommission = number_format((float)$total * 6 / 100, 2, '.', '');
			}
			$total = $total + $serviceCommission;
		} elseif ($customer->is_affiliate == 'Yes') {
			if ($bookingId >= $oldBookingId) { // Set booking id 95440
				if ($discountAmt > 0 || $oldDiscountAmt > 0 || $paymentIntentRelational->metadata->split == 1) {
					if ($totalForDiscountWithSplit) {
						$serviceCommission = roundout(($totalForDiscountWithSplit * $natFeesPercentage / 100), 2);
					} else {
						$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
					}
				} else {
					$serviceCommission = $serviceCommissionTotal;
				}
				$total = $total + $serviceCommission;
			}
		};

		$tourBooking = TourBooking::where('id', $bookingId)->first();
		$tourBooking->service_commission = $serviceCommission;
		$tourBooking->total = $total;
		$tourBooking->booking_total = $total;
		$tourBooking->save();

		if (empty($trans)) {
			if ($paymentIntentRelational->metadata->split == 1 && $discountAmt > 0) {
				$total -= $discountAmt;
			}

			if ($paymentIntentRelational->metadata->split == 1 && $oldDiscountAmt > 0) {
				$total -= $oldDiscountAmt;
			}

			$amountupdate = TourBooking::findOrFail($bookingId);

			$amountupdate->total = $total;
			$amountupdate->booking_total = $total;
			if ($paymentIntentRelational->metadata->discount_percentage != '') {
				$amountupdate->discount2_percentage = $paymentIntentRelational->metadata->discount_percentage;
				$amountupdate->discount2_value = ($amountupdate->discount2_value > 0) ? $amountupdate->discount2_value + $discountAmt : $discountAmt;
			} elseif ($paymentIntentRelational->metadata->discount_fixed != '') {
				$amountupdate->discount2_value = ($amountupdate->discount2_value > 0) ? $amountupdate->discount2_value + $discountAmt : $discountAmt;
			}

			$amountupdate->save();
		} else if (!empty($trans)) {
			if (($paymentIntentRelational->metadata->discount_percentage != '' || $paymentIntentRelational->metadata->discount_fixed != '') && $discountAmt > 0) {
				$amountupdate = TourBooking::findOrFail($bookingId);

				if ($amountupdate) {
					$amountupdate->total = $amountupdate->total - $discountAmt;
					$amountupdate->booking_total = $amountupdate->booking_total - $discountAmt;

					if ($paymentIntentRelational->metadata->discount_percentage != '') {
						$amountupdate->discount2_percentage = $paymentIntentRelational->metadata->discount_percentage;
						$amountupdate->discount2_value = ($amountupdate->discount2_value > 0) ? $amountupdate->discount2_value + $discountAmt : $discountAmt;
					} elseif ($paymentIntentRelational->metadata->discount_fixed != '') {
						$amountupdate->discount2_value = ($amountupdate->discount2_value > 0) ? $amountupdate->discount2_value + $discountAmt : $discountAmt;
					}

					$amountupdate->save();
				}
			}
		}

		return true;
	}

	/**
	 * Update booking payments data for single booking
	 *
	 * @param int $bookingId
	 * @param mixed $paymentIntentId
	 *
	 * @return void
	 */
	public static function makePaymentForBooking($bookingId, $paymentIntentId): void
	{
		try {
			$transactionCount = Transaction::where('transaction_number', $paymentIntentId)
				->where('tour_booking_id', $bookingId)
				->where('status', 'Completed')
				->count();

			$trans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->first(['payment_type']);

			$tourBooking = TourBooking::with([
				'tourPackage',
				'tourSlotTime'
			])
				->where('id', $bookingId)
				->first();

			if ($transactionCount == 0 && !empty($tourBooking)) {
				$retrieveOptions = ['expand' => ['charges.data.transfer']];
				$paymentIntentRelational = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

				$crd = $cardtype = '';
				$paymentMethod = '';
				if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) {
					$crd = (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4 : '';
					$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand : '';
					$paymentMethod = 'Card Reader';
				} else if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) {
					$crd = (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->last4 : '';
					$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->brand : '';
					$paymentMethod = 'Manual Card';
				}

				// code for custom charge make payment in pay later case - card
				$customChargeList = DB::table('tour_booking_custom_charges')
					->where('tour_booking_id', $bookingId)
					->get();

				if ($customChargeList->count() > 0) {
					foreach ($customChargeList as $customCharge) {
						$customData['custom_data'] = [
							"amount" => $customCharge->amount,
							"fees" => $customCharge->fees,
							"taxable" => $customCharge->taxable,
							"status" => "Paid"
						];
						$customData['custom_charge_fees'] = $customCharge->fees;
						$customData['custom_charge_amount'] = $customCharge->amount;
						CustomChargeBookingService::tourBookingUpdateCustomCharge($bookingId, $customData, $paymentIntentRelational);
					}
				}
				// End - code for custom charge make payment in pay later case - card

				// Ground truth: this PaymentIntent already exists on Stripe (retrieved above with
				// charges.data.transfer expanded), so its real settlement mode can be read
				// directly off the object rather than re-resolving from the booking's tour date.
				$mode = PaymentModeInference::fromStripeObject($paymentIntentRelational);

				// Update the Initialized reservation record to Completed instead of inserting a new one
				$reservation = DB::table('transactions')
					->where('tour_booking_id', $bookingId)
					->where('status', 'Initialized')
					->whereNull('charge_type')
					->first(['id']);

				if ($reservation) {
					DB::table('transactions')
						->where('id', $reservation->id)
						->update([
							'amount' => $paymentIntentRelational->metadata->amount,
							'fee_amount' => $paymentIntentRelational->metadata->fee,
							'transaction_number' => $paymentIntentRelational->id,
							'application_fee_id' => !empty($paymentIntentRelational->charges->data[0]->application_fee) ? $paymentIntentRelational->charges->data[0]->application_fee : null,
							'response_end_datetime' => null,
							'status' => 'Completed',
							'payment_type' => $paymentIntentRelational->metadata->split,
							'visitor' => request()->getClientIp(),
							'request_from' => Constants::API,
							'created_by' => $tourBooking->created_by,
							'created_by_user_type' => $tourBooking->created_by_user_type,
							'payment_mode' => $mode,
						]);
					$transactionId = $reservation->id;
				} else {
					$transactionId = DB::table('transactions')
						->insertGetId([
							'tour_booking_id' => $bookingId,
							'amount' => $paymentIntentRelational->metadata->amount,
							'fee_amount' => $paymentIntentRelational->metadata->fee,
							'transaction_number' => $paymentIntentRelational->id,
							'application_fee_id' => !empty($paymentIntentRelational->charges->data[0]->application_fee) ? $paymentIntentRelational->charges->data[0]->application_fee : null,
							'via' => 'Card',
							'response_init' => '',
							'response_end' => '',
							'response_end_datetime' => null,
							'status' => 'Completed',
							'payment_type' => $paymentIntentRelational->metadata->split,
							'visitor' => request()->getClientIp(),
							'request_from' => Constants::API,
							'created_by' => $tourBooking->created_by,
							'created_by_user_type' => $tourBooking->created_by_user_type,
							'created_at' => Carbon::now(),
							'payment_mode' => $mode,
						]);
				}

				$created_by_with_name = '';
				if ($tourBooking->created_by_user_type == "Admin") {
					$user = Admin::find($tourBooking->created_by);
					if (!empty($user)) {
						$created_by_with_name = $user->name . ': (' . "Admin" . ')';
					}
				} elseif ($tourBooking->created_by_user_type == "Tour Operator") {
					$user = Staff::find($tourBooking->created_by);
					if (!empty($user)) {
						$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
					}
				} else {
					$user = Customer::find($tourBooking->created_by);
					if (!empty($user)) {
						$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
					}
				}

				$historyId = DB::table('tour_booking_history')
					->insertGetId([
						'tour_booking_id' => $bookingId,
						'tourists' => $tourBooking->tourists + $tourBooking->infants,
						'operation' => 'Payment',
						'details' => json_encode([
							'amount' => $paymentIntentRelational->metadata->amount,
							'payment_type' => 'Card',
							'card_number' => $crd,
							'card_type' => $cardtype,
							'payment_method' => ($paymentIntentRelational->metadata->split ? 'Split Payment' : 'Full Payment'),
							'from_method' => 'Webhook Update',
							'created_by_with_name' => $created_by_with_name
						], JSON_NUMERIC_CHECK),
						'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
						'operation_by' => $tourBooking->created_by,
						'operation_by_user_type' => $tourBooking->created_by_user_type,
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);

				$isSplitOrFull = $paymentIntentRelational->metadata->split == 1 ? 'Split Payment' : 'Full Payment';
				$bookingDetails = (array)$tourBooking;
				$bookingDetails['booking_id'] = $bookingId;
				$bookingDetails['tour_operator_id'] = $tourBooking->tourPackage->tour_operator_id;
				$bookingDetails['customer_id'] = $tourBooking->customer_id;
				$bookingDetails['is_pax_added'] = $paymentIntentRelational->metadata->is_pax_added == 1 ? 1 : 0;

				CardBookingService::run($bookingDetails, $transactionId, $historyId, $isSplitOrFull, array());
				TourBookingAddonService::markAddonsAsPaid($bookingId, $paymentIntentRelational, 'card');
				AddonBookingService::createAddonTransactionsFromFields((int) $bookingId, $paymentIntentRelational, 'Card', true);

				// Finally change the status from cancelled to booked
				// $tourBooking->service_commission = $tourBooking->service_commission + $paymentIntentRelational->metadata->fee;
				$tourBooking = TourBooking::find($bookingId);
				if ($tourBooking->status != 'Booked') {
					$tourBooking->status = "Booked";
					$tourBooking->save();
				}

				//Here updating booking master table service commission data
				if ($tourBooking->service_commission == 0) {
					static::calculatePlatformFees($bookingId, $paymentIntentRelational, $trans);
				}

				// Add connected account metadata values
				static::addStripeConnectedMetadata($bookingId, $paymentIntentRelational, $paymentMethod);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			if ($bookingId != '') {
				$tourBooking = TourBooking::find($bookingId);
				DB::table('tour_booking_history')
					->insert([
						'tour_booking_id' => $bookingId,
						'operation' => 'Error',
						'details' => $exception->getMessage(),
						'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
						'operation_by' => Auth::id() ?? 1,
						'operation_by_user_type' => $tourBooking->created_by_user_type ?? 'Admin',
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);
			}
		}
	}

	/**
	 * Update booking payments data for combo bookings
	 *
	 * @param array $bookingIds
	 * @param mixed $paymentIntentId
	 *
	 * @return void
	 */
	public static function makePaymentForComboBooking($bookingIds, $paymentIntentId): void
	{
		$retrieveOptions = ['expand' => ['charges.data.transfer']];
		$paymentIntentRelational = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

		$crd = $cardtype = '';
		$paymentMethod = '';
		if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) {
			$crd = (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4 : '';
			$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand : '';
			$paymentMethod = 'Card Reader';
		} else if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) {
			$crd = (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->last4 : '';
			$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->brand : '';
			$paymentMethod = 'Manual Card';
		}

		$splitPayment = (isset($paymentIntentRelational->metadata->split)) ? $paymentIntentRelational->metadata->split : 0;
		$paymentSuccess = false;
		foreach ($bookingIds as $booking) {
			try {
				$bookingId = $booking->BookingId;
				$transactionCount = Transaction::where('transaction_number', $paymentIntentId)
					->where('tour_booking_id', $bookingId)
					->where('status', 'Completed')
					->count();
				$trans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->first(['payment_type']);

				$tourBooking = TourBooking::with([
					'tourPackage',
					'tourSlotTime'
				])
					->where('id', $bookingId)
					->first();

				if ($transactionCount == 0 && !empty($tourBooking)) {
					if ($splitPayment == 1) {
						$totalAmount = (isset($paymentIntentRelational->charges->data[0]->amount)) ? $paymentIntentRelational->charges->data[0]->amount / 100 : 0;
						$feesAmount = PlatformFeeMetadata::feeFor($paymentIntentRelational, (isset($paymentIntentRelational->charges->data[0]->application_fee_amount)) ? $paymentIntentRelational->charges->data[0]->application_fee_amount / 100 : 0);
					} else {
						$amountsDetails = CartService::calculateCartBookingTotal($bookingId);
						$totalAmount = $amountsDetails['total'];
						$feesAmount = $amountsDetails['serviceCommission'];
					}

					$transactionId = DB::table('transactions')
						->insertGetId([
							'tour_booking_id' => $bookingId,
							'amount' => $totalAmount,
							'fee_amount' => $feesAmount,
							'transaction_number' => $paymentIntentRelational->id,
							'application_fee_id' => !empty($paymentIntentRelational->charges->data[0]->application_fee) ? $paymentIntentRelational->charges->data[0]->application_fee : null,
							'via' => 'Card',
							'response_init' => '',
							'response_end' => '',
							'response_end_datetime' => null,
							'status' => 'Completed',
							'payment_type' => $splitPayment,
							'visitor' => request()->getClientIp(),
							'request_from' => Constants::API,
							'created_by' => $tourBooking->created_by,
							'created_by_user_type' => $tourBooking->created_by_user_type,
							'created_at' => Carbon::now(),
							// Ground truth: same reasoning as makePaymentForBooking() above — this
							// one shared PaymentIntent covers every booking in the combo.
							'payment_mode' => PaymentModeInference::fromStripeObject($paymentIntentRelational),
						]);

					$created_by_with_name = '';
					if ($tourBooking->created_by_user_type == "Admin") {
						$user = Admin::find($tourBooking->created_by);
						if (!empty($user)) {
							$created_by_with_name = $user->name . ': (' . "Admin" . ')';
						}
					} elseif ($tourBooking->created_by_user_type == "Tour Operator") {
						$user = Staff::find($tourBooking->created_by);
						if (!empty($user)) {
							$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
						}
					} else {
						$user = Customer::find($tourBooking->created_by);
						if (!empty($user)) {
							$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
						}
					}

					$historyId = DB::table('tour_booking_history')
						->insertGetId([
							'tour_booking_id' => $bookingId,
							'tourists' => $tourBooking->tourists + $tourBooking->infants,
							'operation' => 'Payment',
							'details' => json_encode([
								'amount' => $totalAmount,
								'payment_type' => 'Card',
								'card_number' => $crd,
								'card_type' => $cardtype,
								'payment_method' => (($splitPayment == '1') ? 'Split Payment' : 'Full Payment'),
								'from_method' => 'Webhook Update',
								'created_by_with_name' => $created_by_with_name
							], JSON_NUMERIC_CHECK),
							'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
							'operation_by' => $tourBooking->created_by,
							'operation_by_user_type' => $tourBooking->created_by_user_type,
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);

					$isSplitOrFull = $splitPayment == '1' ? 'Split Payment' : 'Full Payment';
					$bookingDetails = (array)$tourBooking;
					$bookingDetails['booking_id'] = $bookingId;
					$bookingDetails['tour_operator_id'] = $tourBooking->tourPackage->tour_operator_id;
					$bookingDetails['customer_id'] = $tourBooking->customer_id;
					$bookingDetails['is_pax_added'] = $paymentIntentRelational->metadata->is_pax_added == 1 ? 1 : 0;

					CardBookingService::run($bookingDetails, $transactionId, $historyId, $isSplitOrFull, array());

					// Finally change the status from cancelled to booked
					// $tourBooking->service_commission = $tourBooking->service_commission + $paymentIntentRelational->metadata->fee;
					$tourBooking = TourBooking::find($bookingId);
					if ($tourBooking->status != 'Booked') {
						$tourBooking->status = "Booked";
						$tourBooking->save();
					}

					//Here updating booking master table service commission data
					if ($tourBooking->service_commission == 0) {
						static::calculatePlatformFees($bookingId, $paymentIntentRelational, $trans);
					}

					$paymentSuccess = true;
				}
			} catch (Exception $exception) {
				$paymentSuccess = false;
				Log::error($exception);
				if ($bookingId != '') {
					$tourBooking = TourBooking::find($bookingId);
					DB::table('tour_booking_history')
						->insert([
							'tour_booking_id' => $bookingId,
							'operation' => 'Error',
							'details' => $exception->getMessage(),
							'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
							'operation_by' => Auth::id() ?? 1,
							'operation_by_user_type' => $tourBooking->created_by_user_type ?? 'Admin',
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);
				}
			}
		}

		if ($paymentSuccess) {
			// Add connected account metadata values
			static::addStripeConnectedMetadata($bookingIds, $paymentIntentRelational, $paymentMethod);
		}
	}
}
