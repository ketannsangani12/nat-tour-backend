<?php

namespace App\Services\Booking;

use App\Constants\Constants;
use App\Models\Transaction;
use App\Services\Payments\PaymentModeResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionService
{
	/**
	 * Who a transaction row is attributed to: the logged-in user, else the booking's customer
	 * (a customer cancelling from the public cancel link has no login), else 0.
	 * transactions.created_by is NOT NULL, so a bare Auth::id() crashed those inserts — after
	 * Stripe had already refunded, leaving the refund unrecorded.
	 */
	private static function createdByForBooking($tourBookingId): int
	{
		return (int) (Auth::id() ?? BookingService::customerIdByBookingEmail($tourBookingId) ?? 0);
	}

	/**
	 * Execute the transaction service to get the transaction data.
	 * @param mixed $id
	 * @return object
	 */
	public static function transaction($id)
	{
		$transaction = Transaction::findOrFail($id);
		return $transaction;
	}

	/**
	 * Execute the trasnaction service to save payment
	 *
	 * @param mixed $tourBookingId
	 *
	 * @return int
	 */
	public static function storePaymentInTransaction($tourBookingId, $amount = 0, $feesAmount = 0, $paymentVia = '', $paymentType = 0, $paymentIntentId = null, $applicationFeeId = null, $data = [], $isReallocated = false, string $mode = PaymentModeResolver::DESTINATION): int
	{
		$chargeType = isset($data['from_edit_discount']) ? "discount" : NULL;

		// This method is also reached from customer-facing payment-link completion (no
		// admin/staff session, so Auth::id() is null) — e.g. RemainingPaymentService's
		// SwitchPackageItemHandler finalizing a switch-package payment link. There is no Auth on
		// that path at all, so resolve the real Customer by the booking's own email and stamp
		// created_by_user_type as the static 'Customer' rather than leaving created_by NULL.
		$createdBy = (isset($data['booked_by_staff']) && !empty($data['booked_by_staff']))
			? $data['booked_by_staff']
			: self::createdByForBooking($tourBookingId);
		$createdByUserType = (isset($data['booked_by_staff_type']) && !empty($data['booked_by_staff_type']))
			? $data['booked_by_staff_type']
			: (Auth::id() ? userType() : 'Customer');

		// Update the Initialized reservation record if one exists, instead of inserting a new row
		$reservation = DB::table('transactions')
			->where('tour_booking_id', $tourBookingId)
			->where('status', 'Initialized')
			->whereNull('charge_type')
			->first(['id']);

		if ($reservation) {
			DB::table('transactions')
				->where('id', $reservation->id)
				->update([
					'amount' => $amount,
					'fee_amount' => $feesAmount,
					'transaction_number' => $paymentIntentId,
					'application_fee_id' => $applicationFeeId,
					'via' => $paymentVia,
					'response_end_datetime' => null,
					'payment_type' => $paymentType,
					'status' => 'Completed',
					'is_reallocated' => $isReallocated,
					'visitor' => request()->getClientIp(),
					'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
					'created_by' => $createdBy,
					'created_by_user_type' => $createdByUserType,
					'charge_type' => $chargeType,
					'payment_mode' => $mode,
				]);

			return $reservation->id;
		}

		$transactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => $amount,
				'fee_amount' => $feesAmount,
				'transaction_number' => $paymentIntentId,
				'application_fee_id' => $applicationFeeId,
				'via' => $paymentVia,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => null,
				'payment_type' => $paymentType,
				'status' => 'Completed',
				'is_reallocated' => $isReallocated,
				'visitor' => request()->getClientIp(),
				'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by' => $createdBy,
				'created_by_user_type' => $createdByUserType,
				'created_at' => Carbon::now(),
				'charge_type' => $chargeType,
				'payment_mode' => $mode,
			]);

		return $transactionId;
	}

	/**
	 * Execute the trasnaction service to save refund
	 *
	 * @param mixed $tourBookingId
	 *
	 * @return int
	 */
	public static function storeRefundInTransaction($tourBookingId, $amount = 0, $feesAmount = 0, $paymentVia = '', $paymentIntentId = null, $applicationFeeId = null, $chargeType = NULL, $isReallocated = false, string $mode = PaymentModeResolver::DESTINATION): int
	{
		// fee_amount is always a real number (0 when no fee was refunded in this transaction) —
		// never null. application_fee_id, by contrast, references a real Stripe application fee
		// object, so it stays null unless a genuine non-zero fee was actually refunded here (most
		// refund types — Ticket Refund, Don't Refund, Partial Refund — deliberately keep the
		// platform fee, so callers correctly pass/compute 0 for those).
		$hasRefundedFee = ((float) $feesAmount) > 0;
		$applicationFeeIdToStore = $hasRefundedFee ? $applicationFeeId : null;

		$transactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => $amount,
				'fee_amount' => $feesAmount,
				'transaction_number' => $paymentIntentId,
				'application_fee_id' => $applicationFeeIdToStore,
				'via' => $paymentVia,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => null,
				'status' => 'Refund',
				'is_reallocated' => $isReallocated,
				'visitor' => request()->getClientIp(),
				'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by' => self::createdByForBooking($tourBookingId),
				'created_by_user_type' => userType(),
				'created_at' => Carbon::now(),
				'charge_type' => $chargeType,
				'payment_mode' => $mode,
			]);
		return $transactionId;
	}

	/**
	 * Execute the trasnaction service to save payment
	 *
	 * @param mixed $booking
	 *
	 * @return int
	 */
	public static function storeAddonsPaymentInTransaction($booking, $via, $price, $fee = 0, $paymentIntent = null, string $mode = PaymentModeResolver::DESTINATION): int
	{
		$piId     = $paymentIntent->id ?? null;
		$appFeeId = $paymentIntent->charges->data[0]->application_fee ?? null;
		$amount   = $price + $fee;

		// Update the Initialized reservation record if one exists, instead of inserting a new row.
		$reservation = DB::table('transactions')
			->where('tour_booking_id', $booking->id)
			->where('status', 'Initialized')
			->whereNull('charge_type')
			->first(['id']);

		if ($reservation) {
			DB::table('transactions')
				->where('id', $reservation->id)
				->update([
					'amount'                => $amount,
					'fee_amount'            => $fee,
					'transaction_number'    => $piId,
					'application_fee_id'    => $appFeeId,
					'via'                   => ucwords($via),
					'payment_type'          => 0,
					'response_end_datetime' => Carbon::now(),
					'charge_type'           => 'addons',
					'status'                => 'Completed',
					'visitor'               => request()->getClientIp(),
					'request_from'          => requestFrom($booking->created_by_user_type),
					'created_by'            => $booking->created_by,
					'created_by_user_type'  => $booking->created_by_user_type,
					'payment_mode'          => $mode,
				]);

			return $reservation->id;
		}

		return DB::table('transactions')
			->insertGetId([
				'tour_booking_id'       => $booking->id,
				'amount'                => $amount,
				'fee_amount'            => $fee,
				'transaction_number'    => $piId,
				'application_fee_id'    => $appFeeId,
				'via'                   => ucwords($via),
				'payment_type'          => 0,
				'response_init'         => '',
				'response_end'          => '',
				'response_end_datetime' => Carbon::now(),
				'charge_type'           => 'addons',
				'status'                => 'Completed',
				'visitor'               => request()->getClientIp(),
				'request_from'          => requestFrom($booking->created_by_user_type),
				'created_by'            => $booking->created_by,
				'created_by_user_type'  => $booking->created_by_user_type,
				'created_at'            => Carbon::now(),
				'payment_mode'          => $mode,
			]);
	}

	/**
	 * Execute the trasnaction service to save refund
	 *
	 * @param mixed $booking
	 *
	 * @return int
	 */
	public static function storeAddonsRefundInTransaction($booking, $bookingAddon, $transactionValue, string $mode = PaymentModeResolver::DESTINATION): int
	{
		// Most add-on refund types (Ticket Refund, Don't Refund, Partial Refund) deliberately keep
		// the add-on's platform fee, so callers pass 0 for $transactionValue['fee'] in those cases.
		// fee_amount always stores that real numeric (0 when no fee was refunded) — never null.
		// application_fee_id references a real Stripe application fee object, so it stays null
		// unless a genuine non-zero fee was actually refunded here.
		$fee = (float) $transactionValue['fee'];
		$applicationFeeIdToStore = $fee > 0 ? $transactionValue['application_fee_id'] : null;

		$transactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $booking->id,
				'amount' => $transactionValue['price'] + $transactionValue['fee'],
				'fee_amount' => $transactionValue['fee'],
				'transaction_number' => $transactionValue['transaction_id'] ?? null,
				'application_fee_id' => $applicationFeeIdToStore,
				'via' => ucwords($bookingAddon->payment_method),
				'payment_type' => 0,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => Carbon::now(),
				'charge_type' => 'addons',
				'status' => 'Refund',
				'visitor' => request()->getClientIp(),
				'request_from' => requestFrom(userType()),
				'created_by' => self::createdByForBooking($booking->id),
				'created_by_user_type' => userType(),
				'created_at' => Carbon::now(),
				'payment_mode' => $mode,
			]);

		return $transactionId;
	}

	/**
	 * Store custom charge payment in transactions table
	 *
	 * @param mixed $bookingT
	 *
	 * @return object
	 */
	public static function storeCustomChargeInTransaction($tourBookingId, $customChargeAmount = 0, $feesAmount = 0, $paymentVia = '', $paymentIntent = null, $data = [], string $mode = PaymentModeResolver::DESTINATION): int
	{
		$piId     = $paymentIntent->id ?? null;
		$appFeeId = $paymentIntent->charges->data[0]->application_fee ?? null;
		$amount   = $customChargeAmount + $feesAmount;
		// Reached from customer-facing payment-link completion too (CustomChargeItemHandler),
		// where there is no Auth at all — resolve the real Customer by the booking's own email
		// and stamp created_by_user_type as the static 'Customer' on that path.
		$createdBy = (isset($data['booked_by_staff']) && !empty($data['booked_by_staff']))
			? $data['booked_by_staff']
			: self::createdByForBooking($tourBookingId);
		$createdByUserType = (isset($data['booked_by_staff_type']) && !empty($data['booked_by_staff_type']))
			? $data['booked_by_staff_type']
			: (Auth::id() ? userType() : 'Customer');

		// Update the Initialized reservation record if one exists, instead of inserting a new row.
		// When the admin pays via Make Payment, StripeService creates an Initialized placeholder row
		// first (amount=0, charge_type=NULL). Updating it keeps one clean Completed row per payment.
		$reservation = DB::table('transactions')
			->where('tour_booking_id', $tourBookingId)
			->where('status', 'Initialized')
			->whereNull('charge_type')
			->first(['id']);

		if ($reservation) {
			DB::table('transactions')
				->where('id', $reservation->id)
				->update([
					'amount'                => $amount,
					'fee_amount'            => $feesAmount,
					'transaction_number'    => $piId,
					'application_fee_id'    => $appFeeId,
					'via'                   => $paymentVia,
					'payment_type'          => 0,
					'response_end_datetime' => Carbon::now(),
					'charge_type'           => 'custom_charge',
					'status'                => 'Completed',
					'visitor'               => request()->getClientIp(),
					'request_from'          => request()->is('api/*') ? Constants::API : Constants::BACKEND,
					'created_by'            => $createdBy,
					'created_by_user_type'  => $createdByUserType,
					'payment_mode'          => $mode,
				]);

			return $reservation->id;
		}

		return DB::table('transactions')
			->insertGetId([
				'tour_booking_id'       => $tourBookingId,
				'amount'                => $amount,
				'fee_amount'            => $feesAmount,
				'transaction_number'    => $piId,
				'application_fee_id'    => $appFeeId,
				'via'                   => $paymentVia,
				'payment_type'          => 0,
				'response_init'         => '',
				'response_end'          => '',
				'response_end_datetime' => Carbon::now(),
				'charge_type'           => 'custom_charge',
				'status'                => 'Completed',
				'visitor'               => request()->getClientIp(),
				'request_from'          => request()->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by'            => $createdBy,
				'created_by_user_type'  => $createdByUserType,
				'created_at'            => Carbon::now(),
				'payment_mode'          => $mode,
			]);
	}

	/**
	 * Execute the trasnaction service to save refund for custom charge
	 *
	 * @param mixed $tourBookingId
	 *
	 * @return int
	 */
	public static function storeCustomChargeRefundInTransaction($tourBookingId, $customChargeAmount = 0, $customChargeFees = 0, $paymentVia = '', $paymentIntentId = null, $applicationFeeId = null, string $mode = PaymentModeResolver::DESTINATION): int
	{
		// A custom-charge refund that keeps the platform fee passes 0 for $customChargeFees.
		// fee_amount always stores that real numeric (0 when no fee was refunded) — never null.
		// application_fee_id references a real Stripe application fee object, so it stays null
		// unless a genuine non-zero fee was actually refunded here.
		$applicationFeeIdToStore = ((float) $customChargeFees) > 0 ? $applicationFeeId : null;

		$customChargeTransactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => $customChargeAmount + $customChargeFees,
				'fee_amount' => $customChargeFees,
				'transaction_number' => $paymentIntentId,
				'application_fee_id' => $applicationFeeIdToStore,
				'via' => $paymentVia,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => Carbon::now(),
				'charge_type' => 'custom_charge',
				'status' => 'Refund',
				'visitor' => request()->getClientIp(),
				'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by' => self::createdByForBooking($tourBookingId),
				'created_by_user_type' => userType(),
				'created_at' => Carbon::now(),
				'payment_mode' => $mode,
			]);

		return $customChargeTransactionId;
	}

	/**
	 * Execute the trasnaction service to save zero amount refund for payout
	 *
	 * Payout Simplification (2027+): not given a $mode parameter — verified zero
	 * callers exist anywhere in the codebase (grep for both method names, and for
	 * "ZeroAmount" generally, returns only this file), so there is no live call site
	 * whose payment_mode this could ever need to reflect.
	 *
	 * @param mixed $tourBookingId
	 *
	 * @return int
	 */
	public static function storeZeroAmountRefundForPayoutInTransaction($tourBookingId, $paymentVia = '', $paymentIntentId = null, $applicationFeeId = null, $createdByUserType): int
	{
		$transactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => 0,
				'fee_amount' => 0,
				'transaction_number' => $paymentIntentId,
				'application_fee_id' => $applicationFeeId,
				'via' => $paymentVia,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => null,
				'status' => 'Refund',
				'visitor' => request()->getClientIp(),
				'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by' => self::createdByForBooking($tourBookingId),
				'created_by_user_type' => $createdByUserType,
				'created_at' => Carbon::now(),
				'charge_type' => NULL,
			]);
		return $transactionId;
	}
	/**
	 * Execute the trasnaction service to save zero amount payment for payout in switch package feature
	 *
	 * Payout Simplification (2027+): not given a $mode parameter — same reasoning as
	 * storeZeroAmountRefundForPayoutInTransaction() above (dead code, zero callers).
	 *
	 * @param mixed $tourBookingId
	 *
	 * @return int
	 */
	public static function storeZeroAmountPaymentForPayoutInTransaction($tourBookingId, $paymentVia = '', $paymentIntentId = null, $applicationFeeId = null, $createdByUserType): int
	{
		$transactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => 0,
				'fee_amount' => 0,
				'transaction_number' => $paymentIntentId,
				'application_fee_id' => $applicationFeeId,
				'via' => $paymentVia,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => null,
				'status' => 'Completed',
				'visitor' => request()->getClientIp(),
				'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
				'created_by' => self::createdByForBooking($tourBookingId),
				'created_by_user_type' => $createdByUserType,
				'created_at' => Carbon::now(),
				'charge_type' => NULL,
			]);
		return $transactionId;
	}
	/**
	 * Store tip payment in transactions table
	 *
	 * Payout Simplification (2027+): tips are explicitly excluded from this round (deferred
	 * to a later, separate decision) and always stay on the destination-charge model, so this
	 * intentionally has no $mode parameter — the transactions.payment_mode column's own
	 * 'destination' default is correct here and needs no explicit value.
	 *
	 * @param int $tourBookingId
	 *
	 * @return object
	 */
	public static function storeTipInTransaction($tourBookingId, $tipAmount = 0, $feesAmount = 0, $paymentVia = '', $paymentIntent = null, $data = []): int
	{
		$userType = userType();

		$customChargeTransactionId = DB::table('transactions')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => $tipAmount + $feesAmount,
				'fee_amount' => $feesAmount,
				'transaction_number' => $paymentIntent->id ?? null,
				'application_fee_id' => $paymentIntent->charges->data[0]->application_fee ?? null,
				'via' => $paymentVia,
				'payment_type' => 0,
				'response_init' => '',
				'response_end' => '',
				'response_end_datetime' => Carbon::now(),
				'charge_type' => 'tip',
				'status' => 'Completed',
				'visitor' => request()->getClientIp(),
				'request_from' => requestFrom($userType),
				'created_by' => $data['customer_id'] ?? Auth::id(),
				'created_by_user_type' => $userType,
				'created_at' => Carbon::now(),
			]);

		return $customChargeTransactionId;
	}
}
