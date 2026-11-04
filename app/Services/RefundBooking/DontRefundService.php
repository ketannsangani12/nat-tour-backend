<?php

namespace App\Services\RefundBooking;

use App\Models\TourBookingHistory;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use App\Services\RefundBooking\RefundTransactionUpdateService;
use App\Services\RefundBooking\RefundHistoryUpdateService;
class DontRefundService
{
	/**
	 * Execute the service for refund Booking With Cash/Invoiced/Wire Transfer.
	 *
	 * @return mixed
	 */
	public static function refund($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType)
	{
		$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'asc')->first();
		$PaymentHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->whereNull('charge_type')->get();
		$RefundHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->whereNull('charge_type')->get();
		$isCashFees = cashFees();

	
		if (isset($request->is_cash) && $request->is_cash != '') {
			if ($request->is_cash == 'Cash') {
				$transaction->via = "Cash";
			} else if ($request->is_cash == 'Wire Transfer') {
				$transaction->via = "Wire Transfer";
			}
		}

		// Don't Refund for Cash, Invoiced and Wire Transfer Payment
		if ($transaction->via == "Cash" || $transaction->via == "Invoiced" || $transaction->via == "Wire Transfer") {
			$ref = 0;
			$net_amount_split = 0;
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
			}

			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
			}

			if (isset($request->is_cash) && $request->is_cash != '') {
				if ($request->is_cash == 'Cash') {
					$refundVia = 'Cash';
				} else if ($request->is_cash == 'Wire Transfer') {
					$refundVia = 'Check/Wire';
				}
			} else {
				$refundVia = $transaction->via;
			}

			if ($request->fareharbor !== "fareharbor") {
				if ($isCashFees == '1' && $transaction->via == "Cash") {
					$refundVia = "Cash";
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->count();

					if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "dont_refund") {
						$net_amount_split = $net_amount_split - $ref;

						$Transaction = RefundTransactionUpdateService::run($id, 0, '', 0, null, $refundVia, $transaction, $request);
						$refund_keep_us = $transaction->amount;

						// Below line is for to update tour booking history table
						$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $net_amount_split, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
						DB::commit();

						// To pass in email
						$refundedAmount = 0;

					} else {
						// Below line is for to update transactions table
						$Transaction = RefundTransactionUpdateService::run($id, 0, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request);
						$refund_keep_us = $transaction->amount;

						// Below line is for to update tour booking history table
						$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $tourBooking->total, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
						DB::commit();

						// To pass in email
						$refundedAmount = 0;

					}
				} else {
					$Transaction = RefundTransactionUpdateService::run($id, 0, '', 0, null, $refundVia, $transaction, $request);
					DB::commit();

					$net_amount = ($trans->payment_type == 1) ? ($net_amount_split - $ref) : $tourBooking->total;
					$refund_keep_us = 0;
					$refund_type = "don't refund";

					// To pass in email
					$refundedAmount = 0;

					// Below line is for to update history table as per cancel booking refund option
					$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, $refund_type, $userType);
					DB::commit();
				}


				return [
					'historyId' => $historyId,
					'via' => $Transaction->via,
					'transaction_id' => $Transaction->id,
					'refundedAmount' => $refundedAmount
				];
			}
		}

		// Don't Refund for Card Payment
		if ($transaction->via == "Card" && $request->fareharbor != "fareharbor") {
			$refundVia = "Card";
			$paymentTransCount = Transaction::where('tour_booking_id', $id)
				->where('status', 'Completed')
				->where(function($query) {
					$query->whereNull('charge_type')
						->orWhere('charge_type', 'discount');
				})
				->count();

			$net_amount_split = 0;
			if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "dont_refund") {
				$trans = Transaction::where('tour_booking_id', $id)
					->where('status', 'Completed')
					->where(function($query) {
						$query->whereNull('charge_type')
							->orWhere('charge_type', 'discount');
					})
					->get();
					
				$refund_keep_us = 0;
				$cardsArray = array();
				$partialAmountArray = array();

				foreach ($trans as $value) {
					$refundtrans = Transaction::where('tour_booking_id', $id)->where('transaction_number', $value->transaction_number)->where('status', 'Refund')->whereNull('charge_type')->count();
					$histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
					if (!empty($histPaymentDetails)) {
						$cardsArray[] = $histPaymentDetails->card_last_4_digit;
					}
					$net_amount_split += $value->amount;
				}

				$RefundHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->get();
				$ref = 0;
				$dontRefundAmount = 0;
				foreach ($RefundHistory as $key => $value) {
					$details = json_decode($value->details);
					$ref += $details->amount;

					if($value->payment_method == "Don't Refund") {
						$dontRefundAmount += $details->amount;
					}
				}
				$net_amount_split = $net_amount_split - $ref - $dontRefundAmount;
				$net_amount_split = abs($net_amount_split);

				// Below line is for to update transactions table
				$Transaction = RefundTransactionUpdateService::run($id, 0, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request);
				$refund_keep_us = $transaction->amount;

				// Below line is for to update tour booking history table
				$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $net_amount_split, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
				DB::commit();

			} else {
				// Below line is for to update transactions table
				$Transaction = RefundTransactionUpdateService::run($id, 0, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request);
				$refund_keep_us = $transaction->amount;

				// Below line is for to update tour booking history table
				$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $tourBooking->total, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
				DB::commit();

			}

			// To pass in email
			$refundedAmount = 0;
			return [
				'historyId' => $historyId,
				'via' => $Transaction->via,
				'transaction_id' => $Transaction->id,
				'refundedAmount' => $refundedAmount
			];
		}
	}
}
