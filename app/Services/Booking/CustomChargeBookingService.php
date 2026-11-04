<?php

namespace App\Services\Booking;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Booking\TransactionService;
use App\Services\Booking\HistoryService;
use App\Models\Transaction;
use App\Models\TourBookingHistory;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;

class CustomChargeBookingService
{
    /**
	 * Store custom charge in tour_booking_custom_charge table
	 *
	 * @param integer|string $tourBookingId
	 *
	 * @return void
	 */
	public static function tourBookingCustomCharge($tourBookingId, $customChargeDetails = [], $paymentIntentId = null, $paymentVia = '', $status = ''): void
	{
		DB::table('tour_booking_custom_charges')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'amount' => $customChargeDetails['amount'],
				'fees' => $customChargeDetails['fees'],
				'taxable' => $customChargeDetails['taxable'],
				'transaction_id' => $paymentIntentId,
				'description' => $customChargeDetails['comment'],
				'payment_method' => $paymentVia,
				'status' => $status,
				'operation_by' => auth()->id(),
				'operation_by_user_type' => userType(),
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now()
			]);
	}

	/**
	 * Get records for charge_type of custom_charge in transactions table
	 *
	 * @param int|string $bookingId
	 *
	 * @return object
	 */
	public static function customChargeTransactions($bookingId): object
	{
		$transactions = Transaction::with('bookingHistory')
			->where('tour_booking_id', $bookingId)
			->whereIn('status', ['Completed', 'Failed', 'Refund'])
			->where('charge_type', 'custom_charge')
			->orderBy('id', 'DESC')
			->get();

		return $transactions;
	}

	/**
	 * Execute the service to get the custom charge total for the booking
	 *
	 * @param mixed $bookingId
	 *
	 * @return array
	 */
	public static function tourBookingCustomCharges($bookingId, $transaction): array
	{
		// Return ALL non-cancelled parent charges (paid and pending) so totals and
		// the table always reflect the full picture, regardless of payment status.
		// Charges cancelled via "Don't Refund" on a pending Payment Link are excluded.
		$customChargeSql = '
			SELECT
				`tbm`.`id` as `tour_booking_id`,
				`tbcc`.`id` as `custom_charge_id`,
				`tbcc`.`parent_id`,
				`tbcc`.`amount`,
				`tbcc`.`fees`,
				`tbcc`.`taxable`,
				`tbcc`.`transaction_id`,
				`tbcc`.`description`,
				`tbcc`.`payment_method`,
				`tbcc`.`status`
			FROM `tour_booking_custom_charges` AS `tbcc`
			INNER JOIN `tour_bookings_master` AS `tbm` ON `tbm`.`id` = `tbcc`.`tour_booking_id`
			WHERE `tbm`.`id` = ' . $bookingId . '
			AND `tbcc`.`parent_id` IS NULL
			AND `tbcc`.`status` != \'Cancelled\'
			ORDER BY `tbcc`.`created_at` DESC';

		return DB::select($customChargeSql);
	}

	/**
	 * Execute the service to get overall and remaining custom charge and fees
	 *
	 * @param mixed $bookingId
	 *
	 * @return array
	 */
	public static function customChargePrice($bookingId, $transactions = []): array
	{
		$totalCustomCharge = 0;
		$customCharge = 0;
		$customChargeFees = 0;
		$customChargeTotal = 0;
		$payLaterCustomChargeTotal = 0;
		$payLaterCustomChargeFees = 0;

		$transactions = static::customChargeTransactions($bookingId);
		$customCharges = static::tourBookingCustomCharges($bookingId, $transactions);
		$customChargeAmountAndFees = static::customChargeCalculation($bookingId, $transactions);
		
		if(!empty($customCharges)) {
			$amount = array_sum(array_column($customCharges, 'amount'));
			$fees = array_sum(array_column($customCharges, 'fees'));
			$totalCustomCharge += $amount + $fees;
		}

		$customCharge = $customChargeAmountAndFees['customChargeAmount'];
		$customChargeFees = $customChargeAmountAndFees['customChargeFees'];
		$customChargeTotal = $customChargeAmountAndFees['customChargeAmount'] + $customChargeAmountAndFees['customChargeFees'];
		$payLaterCustomChargeTotal = $customChargeAmountAndFees['customChargeAmount'] + $customChargeAmountAndFees['payLaterCustomChargeFees'];
		$payLaterCustomChargeFees = $customChargeAmountAndFees['payLaterCustomChargeFees'];

		return [
			'overall_custom_charge' => $totalCustomCharge,
			'custom_charge_total' => $customCharge,
			'custom_charge_fees' => $customChargeFees,
			'custom_charge_final_total' => $customChargeTotal,
			'pay_later_custom_charge_total' => $payLaterCustomChargeTotal,
			'pay_later_custom_charge_fees' => $payLaterCustomChargeFees
		];
	}

	/**
	 * Service method to calculate custom charge with refunds
	 *
	 * @param integer $bookingId
	 * @param mixed $transaction
	 *
	 * @return array
	 */
	public static function customChargeCalculation($bookingId, $transaction = []): array
	{
		$ccAmount = 0;
		$ccFeeAmount = 0;
		$ccPayLaterFees = 0;
		$customChargeParentList = static::tourBookingCustomCharges($bookingId, $transaction);

		if (!empty($customChargeParentList)) {
			if (count($transaction) > 0) {
				// For Cash | Card | Cash Fees | Invoiced Payment Types
				$totalCustomCharge = array_sum(array_column($customChargeParentList, 'amount'));
				$totalCustomChargeFees = array_sum(array_column($customChargeParentList, 'fees'));
				$customChargeRefunds = static::customChargeRefunds($bookingId);
				
				if (!empty($customChargeRefunds)) {
					foreach ($customChargeRefunds as $key => $customCharge) {
						if ($customCharge->status == "100 % Refund") {
							$ccAmount += $customCharge->amount;
							$ccFeeAmount += $customCharge->fees;
						}
						if ($customCharge->status == "Full Refund minus Fees") {
							$ccAmount += $customCharge->amount;
						}
					}
					$ccAmount = $totalCustomCharge - $ccAmount;
					$ccFeeAmount = $totalCustomChargeFees - $ccFeeAmount;
				} else {
					$ccAmount = $totalCustomCharge;
					$ccFeeAmount = $totalCustomChargeFees;
				}
			} else {
				// For Pay Later
				$customChargeList = static::customChargeList($bookingId, $transaction);

				if (!empty($customChargeList)) {
					$ccAmount += array_sum(array_column($customChargeList, 'amount'));
					$ccPayLaterFees += array_sum(array_column($customChargeList, 'fees'));
				}
			}
		}

		return [
			'customChargeAmount' => $ccAmount,
			'customChargeFees' => $ccFeeAmount,
			'payLaterCustomChargeFees' => $ccPayLaterFees
		];
	}

	/**
	 * For an OTA booking (paid via the OTA, custom charges collected on-site as Cash or
	 * Card), resolve which of the two the booking is locked to. A booking has exactly one
	 * payment method for its custom charges for life, same as every other booking type
	 * already assumes everywhere else in this codebase — once the first custom charge is
	 * added, every later one reuses that same method, regardless of whether earlier ones
	 * were later refunded. Returns null only when the booking has no custom charges yet, in
	 * which case the next one is free to pick Cash or Card.
	 *
	 * @param integer|string $bookingId
	 *
	 * @return string|null
	 */
	public static function activeOtaCustomChargeMethod($bookingId): ?string
	{
		$parentCharge = DB::table('tour_booking_custom_charges')
			->where('tour_booking_id', $bookingId)
			->whereNull('parent_id')
			->orderBy('id', 'DESC')
			->first(['payment_method']);

		return $parentCharge->payment_method ?? null;
	}

	/**
	 * Service method to get custom charge refunds for the booking
	 *
	 * @param integer|string $bookingId
	 *
	 * @return array
	 */
	public static function customChargeRefunds($bookingId): array
	{
		$transactions = static::customChargeTransactions($bookingId);

		$refundSql = '
			SELECT 
				`tbm`.`id` as `tour_booking_id`,
				`tbcc`.`id` as `custom_charge_id`,
				`tbcc`.`parent_id`,
				`tbcc`.`amount`,
				`tbcc`.`fees`,
				`tbcc`.`taxable`,
				`tbcc`.`transaction_id`,
				`tbcc`.`description`,
				`tbcc`.`payment_method`,
				`tbcc`.`status`,
				`tbcc`.`operation`
			FROM tour_booking_custom_charges AS tbcc
			INNER JOIN `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbcc`.`tour_booking_id`
			AND `tbm`.`id` = ' .$bookingId. '
			AND `tbcc`.`parent_id` IS NOT NULL
			AND `tbcc`.`status` LIKE "%Refund%"
		';

		// Pay Later
		if(count($transactions) == 0) {
			$refundSql .= ' WHERE `tbcc`.`payment_method` = "Pay Later"';
		} else {
			// Not "= $trans->via" — locking this to only the latest custom-charge transaction's
			// method misses refund records filed under any other method (confirmed live on
			// tenant_dixies booking 95405: newest charge is Cash, but its earlier Card charge's
			// refund is tagged payment_method="Card", so "= Cash" never finds it, leaving that
			// refunded charge looking un-refunded in both the Cost/Fees/Total summary and the
			// selectable-refund list). Every booking's refund records already all share the one
			// non-Pay-Later method they were charged under, so this matches the exact same rows
			// for the normal case; verified with a full-tenant scan that no existing booking's
			// result changes except where it corrects this exact bug.
			$refundSql .= ' WHERE `tbcc`.`payment_method` != "Pay Later"';
		}

		$customChargeRefunds = DB::select($refundSql);

		return $customChargeRefunds;
	}

	/**
	 * Service method to get custom charge list of open/paid
	 *
	 * @param integer $bookingId
	 * @param mixed $transaction
	 *
	 * @return array
	 */
	public static function customChargeList($bookingId, $transaction = []): array
	{
		$customChargeList = $customChargeRefunds = [];

		$transaction = static::customChargeTransactions($bookingId);
		$customChargeParentList = static::tourBookingCustomCharges($bookingId, $transaction);

		if(empty($customChargeParentList)) {
			$customChargeList = $customChargeParentList;
		} else {
			$customChargeRefunds = static::customChargeRefunds($bookingId);

			if(!empty($customChargeRefunds)) {
				$refundedParentIds = array_column($customChargeRefunds, 'parent_id');
				$customChargeList = array_values(
					array_filter($customChargeParentList, fn($charge) => !in_array($charge->custom_charge_id, $refundedParentIds))
				);
			} else {
				$customChargeList = $customChargeParentList;
			}
		}
		return $customChargeList;
	}

	/**
	 * Store refunded custom charges in tour_booking_custom_charge table
	 *
	 * @param integer|string $tourBookingId
	 *
	 * @return void
	 */
	public static function refundTourBookingCustomCharge($tourBookingId, $customChargeId, $customChargeDetails = [], $paymentIntentId = null, $paymentVia = '', $refundType = '', $operation = null): void
	{
		DB::table('tour_booking_custom_charges')
			->insertGetId([
				'tour_booking_id' => $tourBookingId,
				'parent_id' => $customChargeId,
				'amount' => $customChargeDetails['amount'],
				'fees' => $customChargeDetails['fees'],
				'taxable' => $customChargeDetails['taxable'],
				'transaction_id' => $paymentIntentId,
				'description' => isset($customChargeDetails['description']) ? $customChargeDetails['description'] : null,
				'operation' => $operation,
				'payment_method' => $paymentVia,
				'status' => $refundType,
				'operation_by' => auth()->id(),
				'operation_by_user_type' => userType(),
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now()
			]);
	}

	/**
	 * Custom charge refund from cancel booking
	 *
	 * @param mixed $bookingId
	 *
	 * @return void
	 */
	public static function refundCustomChargeFromCancel($bookingId, $request): void
	{
		$isCashFees = cashFees();
		$transactions = static::customChargeTransactions($bookingId);

		$trans = $transactions->where('status', 'Completed')->first();

		if($trans != null) {
			$paymentType = $trans->via;

			$customCharges = static::customChargeList($bookingId, $transactions);

			if(!empty($customCharges)) {
				foreach ($customCharges as $customCharge) {
					$amount = $fees = $taxable = $cardRefund = 0;
					$paymentIntentId = null;
					$applicationFeeId = $customChargeTransactionId = $cardLast4 = null;

					switch ($request->refund_type) {
						case "full_refund":
							$amount = $customCharge->amount;
							$paymentIntentId = ($paymentType == "Card") ? $customCharge->transaction_id : null;
							$refundType = "Full Refund minus Fees";
							$cardRefund = $amount;
							break;

						case "100_refund":
							$amount = $customCharge->amount;
							$fees = $customCharge->fees;
							$taxable = $customCharge->taxable;
							$paymentIntentId = (($isCashFees == 1 && $paymentType == "Cash") || $paymentType == "Card") ? $customCharge->transaction_id : null;
							$refundType = "100 % Refund";
							$cardRefund = $amount + $fees;
							break;

						case "dont_refund":
							$amount = $customCharge->amount;
							$fees = $customCharge->fees;
							$taxable = $customCharge->taxable;
							$paymentIntentId = (($isCashFees == 1 && $paymentType == "Cash") || $paymentType == "Card") ? $customCharge->transaction_id : null;
							$refundType = "Don't Refund";
							break;

						case "partial_refund":
							$amount = $customCharge->amount;
							$refundType = "Partial Refund";
							break;
					}

					if ($paymentType == "Card") {
						$paymentTrans = Transaction::where('tour_booking_id', $bookingId)->where('transaction_number', $paymentIntentId)->where('charge_type', 'custom_charge')->where('status', 'Completed')->first(['id', 'amount', 'transaction_number', 'application_fee_id', 'payment_mode']);

						if($request->refund_type != "dont_refund") {
							$refundArray = [
								'payment_intent' => $paymentIntentId,
								'amount' => $cardRefund * 100,
								'reverse_transfer' => true,
							];

							if ($request->refund_type == "100_refund") {
								$refundArray['refund_application_fee'] = true;
							}

							app(PaymentGatewayInterface::class)->refundPayment($refundArray, $paymentTrans->payment_mode);
							DB::commit();

							if ($request->refund_type == "100_refund") {
								$applicationFeeId = $paymentTrans->application_fee_id;
							}

							$histDetails = TourBookingHistory::where('transaction_id', $paymentTrans->id)->where('tour_booking_id', $bookingId)->where('operation', 'Payment')->first(['card_last_4_digit']);
							$cardLast4 = $histDetails->card_last_4_digit ?? 0;
						}
					}

					$customChargeDetails = $history = [
						'amount' => $amount,
						'taxable' => $taxable,
						'fees' => $fees,
						'status' => $refundType
					];

					$customChargeDetails['description'] = $request->comment;

					// Store refunds in transactions only for Cash, Card and Invoiced bookings
					if ($paymentType != "Pay Later") {
						if ($request->refund_type == "dont_refund") {
							$ccAmount = $ccFeeAmount = 0;
						} else {
							$ccAmount = $amount;
							$ccFeeAmount = $fees;
						}

						// Cash/Invoiced custom charges never carry a Stripe settlement, so payment_mode
					// is not a meaningful concept for them — DESTINATION (the column's own default)
					// is the correct, inert value there; Card uses the transaction's real persisted mode.
					$customChargeMode = ($paymentType == "Card") ? $paymentTrans->payment_mode : PaymentModeResolver::DESTINATION;
					$customChargeTransactionId = TransactionService::storeCustomChargeRefundInTransaction($bookingId, $ccAmount, $ccFeeAmount, $paymentType, $paymentIntentId, $applicationFeeId, mode: $customChargeMode);
						DB::commit();
					}

					CustomChargeBookingService::refundTourBookingCustomCharge($bookingId, $customCharge->custom_charge_id, $customChargeDetails, $paymentIntentId, $paymentType, $refundType, 'Cancelled');
					
					HistoryService::storeCustomChargeRefundInHistory($bookingId, $paymentType, $amount, $fees, $history, $refundType, $cardLast4, $customChargeTransactionId, 'cancelled');
					DB::commit();
				}
			}
		}
	}

	/**
	 * Store custom charge in transactions and history table
	 *
	 * @param mixed $booking
	 *
	 * @return void
	 */
	public static function createBookingStoreCustomCharge($booking, $paymentMethod, $paymentIntent = null): void
	{
		$customChargeFees = 0;
		$customChargeIsTaxed = 0;
		$customChargeDetails = [];
		$isCashFees = cashFees();

		if($paymentMethod == 'Card' || ($paymentMethod == 'Cash' && $isCashFees == 1)) {
			$bookingId = $booking["booking_id"];
		} else {
			$bookingId = $booking->id;
		}

		$payLaterCustomCharges = static::customChargeList($bookingId);

		if(count($payLaterCustomCharges) > 0) {
			foreach ($payLaterCustomCharges as $key => $customCharge) {
				$customChargeAmount = $customCharge->amount;
				$customChargeDetails['amount'] = $customChargeAmount;

				if($paymentMethod == 'Card' || ($paymentMethod == 'Cash' && $isCashFees == 1)) {
					$customChargeFees = $customCharge->fees;
					$customChargeIsTaxed = $customCharge->taxable;	
				}

				$customChargeDetails['fees'] = $customChargeFees;
				$customChargeDetails['taxable'] = $customChargeIsTaxed;
				$customChargeDetails['status'] = 'Paid';

				// Update tour_booking_custom_charges table
				$getCustomCharge = DB::table('tour_booking_custom_charges')
					->where('id', $customCharge->custom_charge_id)
					->update([
						'fees' => $customChargeFees,
						'taxable' => $customChargeIsTaxed,
						'transaction_id' => $paymentIntent?->id,
						'operation' => 'Payment',
						'payment_method' => $paymentMethod,
						'status' => $customChargeDetails['status']
					]);

				if($getCustomCharge) {
					// $paymentIntent is only ever a real Stripe object for a genuine card charge —
					// non-card methods never touch Stripe, so DESTINATION (the column's own
					// default) is the correct, inert value for them.
					$chargeMode = $paymentIntent
						? PaymentModeInference::fromStripeObject($paymentIntent)
						: PaymentModeResolver::DESTINATION;
					$customChargeTransactionId = TransactionService::storeCustomChargeInTransaction($bookingId, $customChargeAmount, $customChargeFees, $paymentMethod, $paymentIntent, mode: $chargeMode);
					DB::commit();

					// Save custom charges in history
					HistoryService::storeCustomChargePaymentInHistory($bookingId, $paymentMethod, $customChargeAmount, $customChargeFees, $customChargeDetails, $paymentIntent, $customChargeTransactionId);
					DB::commit();
				}
			}
		}
	}

	/**
	 * Re-store custom charges of the booking
	 *
	 * @param string|int $bookingId
	 *
	 * @return void
	 */
	public static function restoreCustomCharges($bookingId): void
	{
		$transactions = static::customChargeTransactions($bookingId);

		$trans = $transactions->where('status', 'Completed')->first();

		if($trans != null) {
			$refundedCustomCharge = DB::table('tour_booking_custom_charges')
				->where('tour_booking_id', $bookingId)
				->whereNotNull('parent_id')
				->where('operation', 'Cancelled')
				->get()->toArray();
			
			if(count($refundedCustomCharge) > 0) {
				$customChargeIds = array_column($refundedCustomCharge, 'id');

				DB::table('tour_booking_custom_charges')->whereIn('id', $customChargeIds)->delete();
			}

			$customChargeHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
				->where('operation', 'Refund')
				->where('charge_type', 'custom_charge')
				->whereJsonContains('details', ['operation' => 'cancelled'])
				->get()->toArray();

			if(count($customChargeHistory) > 0) {
				$customChargeHistoryIds = array_column($customChargeHistory, 'id');

				TourBookingHistory::whereIn('id', $customChargeHistoryIds)->delete();
			}
		}
	}

	/**
	 * Update custom charge in tour_booking_custom_charge table
	 *
	 * @return void
	 */
	public static function tourBookingUpdateCustomCharge($tourBookingId, $data, $paymentIntent): void
	{
		// TransactionService to store custom charges in the transactions table for Card
		$customChargeTransactionId = TransactionService::storeCustomChargeInTransaction(
			$tourBookingId,
			$data['custom_charge_amount'],
			$data['custom_charge_fees'],
			'Card',
			$paymentIntent,
			mode: PaymentModeInference::fromStripeObject($paymentIntent)
		);
		DB::commit();

		DB::table('tour_booking_custom_charges')
			->where('tour_booking_id', $tourBookingId)
			->update([
				'transaction_id' => $paymentIntent->id,
				'operation' => 'Payment',
				'payment_method' => 'Card',
				'status' => 'Paid',
				'operation_by' => auth()->id(),
				'operation_by_user_type' => userType(),
				'updated_at' => Carbon::now()
			]);
		DB::commit();

		if (!empty($data['custom_data'])) {
			$data['custom_data']['status'] = 'Paid';
		}

		// Save custom charges in history
		HistoryService::storeCustomChargePaymentInHistory($tourBookingId, 'Card', $data['custom_charge_amount'], $data['custom_charge_fees'], $data['custom_data'], $paymentIntent, $customChargeTransactionId);
		DB::commit();
	}

		/**
	 * add custom charge in transaction and tour booking history table
	 *
	 * @return array
	 */
	public static function cardPaymentCustomChargeFromApp($id,$paymentIntentId,$customChargeDetails): array
	{
		$retrieveOptions = ['expand' => ['charges.data.transfer']];
		$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

		// TransactionService to store custom charges in the transactions table for Card
		$customChargeTransactionId = TransactionService::storeCustomChargeInTransaction(
			$id,
			$customChargeDetails['amount'],
			$customChargeDetails['fees'],
			'Card',
			$paymentIntent,
			mode: PaymentModeInference::fromStripeObject($paymentIntent)
		);
		DB::commit();

		// CustomChargeBookingService to store custom charges with details in the tour_booking_custom_charge table
		CustomChargeBookingService::tourBookingCustomCharge(
			$id,
			$customChargeDetails,
			!empty($paymentIntent->id) ? $paymentIntent->id : null,
			'Card',
			'Paid'
		);
		DB::commit();

		if (!empty($customChargeDetails)) {
			$customChargeDetails['status'] = 'Paid';
		}

		// Save custom charges in history
		HistoryService::storeCustomChargePaymentInHistory($id, 'Card', $customChargeDetails['amount'], $customChargeDetails['fees'], $customChargeDetails, $paymentIntent, $customChargeTransactionId);
		DB::commit();

		return [
			'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
			'transactionId' => $customChargeTransactionId,
			'status' => !empty($paymentIntent->status) ? $paymentIntent->status : ''
		];
	}
}