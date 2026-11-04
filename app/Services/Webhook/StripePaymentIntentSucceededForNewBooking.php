<?php

namespace App\Services\Webhook;

use App\Constants\Constants;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Services\CardBookingService;
use App\Services\Cart\CartService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PlatformFeeMetadata;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentIntentSucceededForNewBooking
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
				static::makePaymentForComboBooking($bookingIds,$paymentIntent->id);
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
						'from' => $From,
						'is_pax_added' => $paymentIntentRelational->metadata->is_pax_added,
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
				$From = ($paymentMethod == 'Manual Card') ? 'iOS Manual Card' : 'iOS Card Reader';

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
					$crd = $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4 ?? '';
					$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand : '';
					$paymentMethod = 'Card Reader';
				} else if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) {
					$crd = $paymentIntentRelational->charges->data[0]->payment_method_details->card->last4 ?? '';
					$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->brand : '';
					$paymentMethod = 'Manual Card';
				}

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
						// Ground truth: this PaymentIntent already exists on Stripe (retrieved above
						// with charges.data.transfer expanded), so its real settlement mode can be
						// read directly off the object rather than re-resolving from the booking's
						// tour date here.
						'payment_mode' => PaymentModeInference::fromStripeObject($paymentIntentRelational),
					]);
				$created_by_with_name = '';
				if ($tourBooking->created_by_user_type == "Admin") {
					$user = Admin::find($tourBooking->created_by);
					if (!empty($user)) {
						$created_by_with_name = "{$user->name}: (Admin)";
					}
				} elseif ($tourBooking->created_by_user_type == "Tour Operator") {
					$user = Staff::find($tourBooking->created_by);
					if (!empty($user)) {
						$created_by_with_name = "{$user->name}: (Tour Operator)";
					}
				} else {
					$user = Customer::find($tourBooking->created_by);
					if (!empty($user)) {
						$created_by_with_name = "{$user->name}: (Affiliate)";
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
			$crd = $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4 ?? '';
			$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand : '';
			$paymentMethod = 'Card Reader';
		} else if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) {
			$crd = $paymentIntentRelational->charges->data[0]->payment_method_details->card->last4 ?? '';
			$cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->brand : '';
			$paymentMethod = 'Manual Card';
		}

		$splitPayment = $paymentIntentRelational->metadata->split ?? 0;
		$paymentSuccess = false;

		foreach ($bookingIds as $booking) {
			try {
				$bookingId = $booking->BookingId;
				$transactionCount = Transaction::where('transaction_number', $paymentIntentId)
					->where('tour_booking_id', $bookingId)
					->where('status', 'Completed')
					->count();

				$tourBooking = TourBooking::with([
					'tourPackage',
					'tourSlotTime'
				])
					->where('id', $bookingId)
					->first();

				if ($transactionCount == 0 && !empty($tourBooking)) {

					if ($splitPayment == 1) {
						$totalAmount = isset($paymentIntentRelational->charges->data[0]->amount) ? $paymentIntentRelational->charges->data[0]->amount / 100 : 0;
						$feesAmount = PlatformFeeMetadata::feeFor($paymentIntentRelational, isset($paymentIntentRelational->charges->data[0]->application_fee_amount) ? $paymentIntentRelational->charges->data[0]->application_fee_amount / 100 : 0);
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
							// one shared PaymentIntent covers every booking in the combo, so each
							// gets the same real, already-retrieved settlement mode.
							'payment_mode' => PaymentModeInference::fromStripeObject($paymentIntentRelational),
						]);
					$created_by_with_name = '';
					if ($tourBooking->created_by_user_type == "Admin") {
						$user = Admin::find($tourBooking->created_by);
						if (!empty($user)) {
							$created_by_with_name = "{$user->name}: (Admin)";
						}
					} elseif ($tourBooking->created_by_user_type == "Tour Operator") {
						$user = Staff::find($tourBooking->created_by);
						if (!empty($user)) {
							$created_by_with_name = "{$user->name}: (Tour Operator)";
						}
					} else {
						$user = Customer::find($tourBooking->created_by);
						if (!empty($user)) {
							$created_by_with_name = "{$user->name}: (Affiliate)";
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
								'payment_method' => ($splitPayment == 1 ? 'Split Payment' : 'Full Payment'),
								'from_method' => 'Webhook Update',
								'created_by_with_name' => $created_by_with_name
							], JSON_NUMERIC_CHECK),
							'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
							'operation_by' => $tourBooking->created_by,
							'operation_by_user_type' => $tourBooking->created_by_user_type,
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);

					$isSplitOrFull = $splitPayment == 1 ? 'Split Payment' : 'Full Payment';
					$bookingDetails = (array)$tourBooking;
					$bookingDetails['booking_id'] = $bookingId;
					$bookingDetails['tour_operator_id'] = $tourBooking->tourPackage->tour_operator_id;
					$bookingDetails['customer_id'] = $tourBooking->customer_id;
					$bookingDetails['is_pax_added'] = $paymentIntentRelational->metadata->is_pax_added == 1 ? 1 : 0;

					CardBookingService::run($bookingDetails, $transactionId, $historyId, $isSplitOrFull, array());
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
