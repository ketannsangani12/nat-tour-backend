<?php

namespace App\Services\Webhook;

use App\Constants\Constants;
use App\Models\TourBooking;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TourBookingPaymentLink;
use App\Services\Booking\BookingService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Booking\TransactionService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\RemainingPayment\RemainingPaymentService;
use App\Services\Payments\PaymentModeInference;
use Illuminate\Support\Facades\Auth;

class StripePaymentIntentSucceededForDiscountPayment
{
	/**
	 * Execute the service.
	 *
	 * @return void
	 */
	public static function run($paymentIntent)
	{
		if (isset($paymentIntent->metadata->app)) {
			static::makePaymentForDiscount($paymentIntent);
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
	public static function addStripeConnectedMetadata($bookingId, $stripe, $paymentIntentRelational, $paymentMethod = ''): void
	{
		// Log::info("Stripe connected metadata called");

		if (isset($paymentIntentRelational->charges->data[0]->transfer->destination_payment)) {

			$From = ($paymentMethod == 'Manual Card') ? 'iOS Manual Card' : 'iOS Card Reader';

			$stripeMetadata = [
				'description' => "Booking Id: $bookingId",
				'metadata' => [
					'booking_id' => $bookingId,
					'amount' => $paymentIntentRelational->metadata->new_total,
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

			// Log::info("Stripe connected metadata completed");

		}

		// Log::info("Stripe connected metadata end");
	}

	/**
	 * Process the discount data and update records
	 *
	 * @param array $discountHistoryArray
	 * @return void
	 */
	public static function makePaymentForDiscount($paymentIntent): void
	{
		if (isset($paymentIntent->metadata->app)) {
			$bookingId = trim($paymentIntent->metadata['booking_id']); 
			try {
				$tourBooking = TourBooking::find($bookingId);
				if (!empty($tourBooking)) {

					$retrieveOptions = ['expand' => ['charges.data.transfer']];
					$paymentIntentRelational = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntent->id, $retrieveOptions);

					$crd = '';
					$paymentMethod = '';
					if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) {
						$crd = $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4 ?? '';
						$paymentMethod = 'Card Reader';
					} else if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) {
						$crd = $paymentIntentRelational->charges->data[0]->payment_method_details->card->last4 ?? '';
						$paymentMethod = 'Manual Card';
					}

					// Update tour bookings master table data
					$discountHistoryArray = $paymentIntent->metadata;
					$bookingDiscountHistoryArray = $discountHistoryArray;

					//set for web test
					$discountHistoryArray['transaction_number'] = $paymentIntentRelational->id;
					$discountHistoryArray['application_fee_id'] = !empty($paymentIntentRelational->charges->data[0]->application_fee) ? $paymentIntentRelational->charges->data[0]->application_fee : null;
					$discountHistoryArray['payment_type'] ="payment";
					$discountHistoryArray['payment_method'] ="Card";
					$discountHistoryArray['cardLast4'] =$crd;
					$discountHistoryArray['created_by'] = $tourBooking->created_by; 
					$discountHistoryArray['created_by_user_type'] = $tourBooking->created_by_user_type;

					$bookingDiscountHistoryArray['tour_booking_id'] = $bookingId;
					BookingService::updateBooking($bookingDiscountHistoryArray, $bookingDiscountHistoryArray['payment_type'] !== 'refund');

					// Add transaction table data
					$amount = $discountHistoryArray['new_total'] + $discountHistoryArray['service_commission'];
					$feesAmount = $discountHistoryArray['service_commission'];

					$transactionNumber = $discountHistoryArray['transaction_number'] ?? "";
					$appFeeId = $discountHistoryArray['application_fee_id'] ?? "";
					// Ground truth: this PaymentIntent already exists on Stripe (retrieved above with
					// charges.data.transfer expanded), so its real settlement mode can be read directly
					// off the object rather than waiting on the creating call site to resolve/pass one.
					$mode = PaymentModeInference::fromStripeObject($paymentIntentRelational);
					if ($discountHistoryArray['payment_type'] === "refund") {
						$chargeType = 'discount';
						$transactionId = TransactionService::storeRefundInTransaction($bookingId, $amount, $feesAmount, $paymentMethod, $transactionNumber, $appFeeId, $chargeType, mode: $mode);
						$discountHistoryArray['transaction_id'] = $transactionId;
					} else {
						$paymentType = ($discountHistoryArray['split_pending_amount'] > 0) ? 1 : 0;
						if (!(isset($discountHistoryArray['transaction_id']) && $discountHistoryArray['transaction_id'])) {
							$discountHistoryArray['from_edit_discount'] = 1;
							$transactionId = TransactionService::storePaymentInTransaction($bookingId, $amount, $feesAmount, $paymentMethod, $paymentType, $transactionNumber, $appFeeId, $discountHistoryArray, mode: $mode);
							$discountHistoryArray['transaction_id'] = $transactionId;
						}
					}

					$discountHistoryArray['from'] = "Update Webhook";
					$discountHistoryArray = $discountHistoryArray->toArray();

					// Add tour booking history table data
					HistoryService::updateBookingDiscountInHistory($bookingId, $discountHistoryArray);

					BookingService::addEditDiscount($discountHistoryArray);

					// If the PI covered a combined amount (discount + existing pending link items), finalize them.
					$discountOnly = (float) $discountHistoryArray['new_total'] + (float) $discountHistoryArray['service_commission'];
					$piAmount     = ($paymentIntentRelational->amount ?? 0) / 100;
					if ($piAmount > $discountOnly) {
						$groupId     = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
						$pendingLink = TourBookingPaymentLink::where('order_id', $groupId)
							->where('status', 'Unpaid')
							->where('section', 'remaining_payment')
							->first();
						if ($pendingLink) {
							RemainingPaymentService::finalizeItems($pendingLink, $paymentIntentRelational, $bookingId, $paymentMethod);
						}
					}

					// Add connected account metadata values
					static::addStripeConnectedMetadata($bookingId, $stripe, $paymentIntentRelational, $paymentMethod);

					DB::commit();
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
	}
}
