<?php

namespace App\Services\RefundBooking;

use App\Jobs\DetectPayoutAdjustment;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Models\TourBookingHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\RefundBooking\RefundTransactionUpdateService;
use App\Services\RefundBooking\RefundHistoryUpdateService;
class TicketRefundService
{
	/**
	 * Execute the service Refund Booking With Cash/Invoiced/Wire Transfer.
	 *
	 * @return mixed
	 */
	public static function refund($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType)
	{
		$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'asc')->first();
		$PaymentHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->whereNull('charge_type')->get();
		$RefundHistory = TourBookingHistory::where('tour_booking_id', $id)
			->where('operation', 'Refund')
			->where(function($query) {
				$query->whereNull('charge_type')
					->orWhere('charge_type', 'discount');
			})
			->get();

		$isCashFees = cashFees();

		if (isset($request->is_cash) && $request->is_cash != '') {
			if ($request->is_cash == 'Cash') {
				$transaction->via = "Cash";
			} else if ($request->is_cash == 'Wire Transfer') {
				$transaction->via = "Wire Transfer";
			}
		}

		// Ticket Refund for Cash, Invoiced and Wire Transfer Payment
		if ($transaction->via == "Cash" || $transaction->via == "Invoiced" || $transaction->via == "Wire Transfer") {
			$ref = 0;
			$net_amount_split = 0;
			$fees = 0;
			$dontrefundamount = 0;
			$oldRefundKeepUs = 0;
			$ticketCostAmount = 0;
			$platformFeesAmount = 0;

			$RefundHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->get();

			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				if ($value->payment_method == "Don't Refund") {
					$dontrefundamount += $details->amount;
				}
				if ($isCashFees == '1' && isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees') {
					if (isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
						$oldRefundKeepUs += $details->refund_keep_us;
					}
				}
			}
			$ref = $ref - $dontrefundamount;
			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
				$fees += $value->platform_fees;
				$ticketCostAmount += $value->ticket_cost;
				$platformFeesAmount += $value->platform_fees;
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
				if ($isCashFees == '1' && $trans->via == "Cash") {
					$refundVia = "Cash";
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->count();
					if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "full_refund") {

						$net_amount = $ticketCostAmount - $ref - $dontrefundamount;
						$refund_keep_us = $platformFeesAmount;
						TourBooking::where('id', $id)->update(['total' => number_format((float)$tourBooking->total - $net_amount, 2, '.', '')]);
						$Transaction = RefundTransactionUpdateService::run($id, $net_amount, '', 0, null, $refundVia, $transaction, $request);

						$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Full Refund minus Fees", $userType);
						DB::commit();

						$refundedAmount = $net_amount;
					} else {
						$net_amount_data= $tourBooking->total + $oldRefundKeepUs - $tourBooking->service_commission;
						$net_amount=((float)$ref== (float)$tourBooking->booking_total) ? 0.00 :  $net_amount_data;
						TourBooking::where('id', $id)->update(['total' => number_format((float)$tourBooking->total - $net_amount, 2, '.', '')]);
						DB::commit();
						
						$Transaction = RefundTransactionUpdateService::run($id, $net_amount, '', 0, null, $refundVia, $transaction, $request);
						$refund_keep_us = $tourBooking->service_commission - $oldRefundKeepUs;
						// Below line is for to update tour booking history table
						$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Full Refund minus Fees", $userType);
						DB::commit();
						// To pass in email
						$refundedAmount = $net_amount;
					}
				} else {
					// $net_amount = $net_amount_split - $ref;
					$net_amount = ($trans->via == "Card") ? ($net_amount_split - $ref - $fees) : ($net_amount_split - $ref);
					$refund_keep_us = 0;
					$refund_type = "Full Refund minus Fees";
					$refundData=((float)$ref==(float)$tourBooking->booking_total) ? 0.00 :  $net_amount;
					TourBooking::where('id', $id)->update(['total' => number_format((float)$tourBooking->total - $net_amount, 2, '.', '')]);
						
					$Transaction = RefundTransactionUpdateService::run($id, $net_amount, '', 0, null, $refundVia, $transaction, $request);
					DB::commit();

					// To pass in email
					$refundedAmount = $refundData;
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

		// Ticket Refund for Card Payment
		if ($transaction->via == "Card" && $request->fareharbor != "fareharbor") {
			$refundVia = "Card";
			$dontrefundamount =0;
			$ref=0;
			$paymentTransCount = Transaction::where('tour_booking_id', $id)
				->where('status', 'Completed')
				->where(function($query) {
					$query->whereNull('charge_type')
						->orWhere('charge_type', 'discount');
				})
				->count();

			$net_amount_split = 0;
			if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "full_refund") {
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
					$histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
					if (!empty($histPaymentDetails)) {
						$cardsArray[] = $histPaymentDetails->card_last_4_digit;
					}

					$refundedTransForTransaction = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $value->transaction_number)
						->where('status', 'Refund')
						->where(function($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->get(['id', 'amount', 'charge_type', 'fee_amount']);

					$refundedTransForTransactionZero = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $value->transaction_number)
						->where('status', 'Refund')
						->where('amount', 0)
						->where(function($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->get(['id', 'amount']);
						
					$dontrefundamount = 0;

					foreach ($refundedTransForTransactionZero as $his) {
						$tBH = TourBookingHistory::where('transaction_id', $his->id)->first();
						$jsonData = json_decode($tBH->details, true);
						$dontrefundamount += $jsonData['amount'];
					}

					$totalrefundedamountfortransaction = 0;
					$totalrefundedfeesfortransaction = 0;

					foreach ($refundedTransForTransaction as $ky => $val) {
						$totalrefundedamountfortransaction += $val->amount;
						$totalrefundedfeesfortransaction += $val->fee_amount;
					}

					$net_amount = $histPaymentDetails->ticket_cost - $totalrefundedamountfortransaction - $dontrefundamount;
					$platformfees = $histPaymentDetails->platform_fees - $totalrefundedfeesfortransaction;

					if($net_amount > 0) {
						if ($value->transaction_number != '' && $net_amount > 0) {
							$refundParams = [
								'payment_intent' => $value->transaction_number,
								'amount' => $net_amount * 100,
								'reverse_transfer' => true,
							];
							app(PaymentGatewayInterface::class)->refundPayment($refundParams, $value->payment_mode);
						}

					} else {
						$net_amount = 0;
					}

					$Transaction = RefundTransactionUpdateService::run($id, $net_amount, $value->transaction_number, 0, null, $refundVia, $transaction, $request);
					$partialAmountArray[] = $net_amount;
					$net_amount_split += $net_amount;
					$refund_keep_us += $platformfees;
				}
				TourBooking::where('id', $id)->update(['total' => number_format((float)$tourBooking->total - $net_amount_split, 2, '.', '')]);
						

				$historyId = DB::table('tour_booking_history')
					->insertGetId([
						'tour_booking_id' => $id,
						'tourists' => $tourBooking->tourists + $tourBooking->infants,
						'operation' => 'Refund',
						'details' => json_encode([
							'amount' => $net_amount_split,
							'refund_keep_us' => $refund_keep_us,
							'payment_type' => 'Card',
							'created_by_with_name' => $created_by_with_name,
							'refund_type' => 'Full Refund minus Fees',
							'cards' => $cardsArray,
							'refund_amounts' => $partialAmountArray,
							'note' => $request->comment,
							'from' => 'POS'
						], JSON_NUMERIC_CHECK),
						'operation_at' => mysqlDT(),
						'operation_by' => auth()->id(),
						'operation_by_user_type' => $userType,
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);
				DB::commit();

				DetectPayoutAdjustment::dispatch($id, $historyId, 'Refund');

				// To pass in email
				$refundedAmount = $net_amount_split;
			} else {
				$oldRefundKeepUs = 0;

				foreach ($RefundHistory as $key => $value) {
					$details = json_decode($value->details);
					if (isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees' && isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
						$oldRefundKeepUs += $details->refund_keep_us;
					}

					if (isset($details->refund_type) && $value->charge_type == null) {
						if($details->refund_type == '100 % Refund') {
							$oldRefundKeepUs += $value->platform_fees;
						}
						if($details->refund_type == 'Don\'t Refund') {
							$oldRefundKeepUs += $details->fees;
						}
					}
					//refund cancel case
					$ref += $details->amount;
					if ($value->payment_method == "Don't Refund") {
						$dontrefundamount += $details->amount;
					}

				}

				$net_amount = $tourBooking->total + $oldRefundKeepUs - $tourBooking->service_commission;
				$ref = $ref - $dontrefundamount;
				if((float)$tourBooking->booking_total!=((float)$ref)){
					$refundParams = [
						'payment_intent' => $transaction->transaction_number,
						'amount' => $net_amount * 100,
						'reverse_transfer' => true,
					];
					// $stripeAmount = $net_amount + $addonTotal;
					$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
				}

				$refundAmount=(float)$tourBooking->booking_total==((float)$ref)?0.00:$net_amount;
				TourBooking::where('id', $id)->update(['total' => number_format((float)$tourBooking->total - $refundAmount, 2, '.', '')]);
				// Below line is for to update transactions table
				$Transaction = RefundTransactionUpdateService::run($id, $refundAmount, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request);
				$refund_keep_us = $tourBooking->service_commission - $oldRefundKeepUs;

				// Below line is for to update tour booking history table
				$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $refundAmount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Full Refund minus Fees", $userType);
				DB::commit();

				// To pass in email
				$refundedAmount =$refundAmount;
			}


			return [
				'historyId' => $historyId,
				'via' => $Transaction->via,
				'transaction_id' => $Transaction->id,
				'refundedAmount' => $refundedAmount
			];
		}
	}
}
