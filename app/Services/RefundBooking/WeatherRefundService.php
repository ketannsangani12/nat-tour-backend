<?php

namespace App\Services\RefundBooking;

use App\Jobs\DetectPayoutAdjustment;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\RefundBooking\RefundTransactionUpdateService;
use App\Services\RefundBooking\RefundHistoryUpdateService;
use Illuminate\Support\Facades\Log;

class WeatherRefundService
{
	/**
	 * Execute the service.
	 *
	 * @return mixed
	 */
	public static function refund($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType, $tourBookingTransaction)
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

		// 100% Refund for Cash, Invoiced and Wire Transfer Payment
		if ($transaction->via == "Cash" || $transaction->via == "Invoiced" || $transaction->via == "Wire Transfer") {

			$ref = 0;
			$net_amount_split = 0;
			$dontrefundamountFee = 0;
			$dontrefundamount = 0;
			$refTotal = 0;


			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				if ($value->payment_method == "Don't Refund") {
					$refTotal += $details->amount;
				}
				$ref += $details->amount;
				if ($isCashFees == '1' && $value->payment_method == 'Full Refund minus Fees') {
					$dontrefundamountFee += $details->refund_keep_us;
				}
				if ($isCashFees == '1' && $value->payment_method == "Don't Refund") {
					$dontrefundamount += $details->amount;
				}
			}
			$ref = $ref - $refTotal;

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
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->count();

					if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "100_refund") {

						$net_amount = $net_amount_split - $ref - $dontrefundamountFee;
						$refundedAmount =  (float)$net_amount - $refTotal;

						$Transaction = RefundTransactionUpdateService::run($id, $refundedAmount, '', round($net_amount * $tourBookingTransaction->stripe_fees_percentage / 100, 2), null, $refundVia, $transaction, $request);
						$refund_keep_us = 0;
						// To pass in email
						$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "100 % Refund", $userType);
						DB::commit();
					} else {
						$stripe_commission = roundout($tourBookingTransaction->stripe_fees_percentage, 2);
						$fee_amount = roundout((($tourBooking->total * $stripe_commission) / 100), 2);
						$refundData = (float)$tourBooking->total - $refTotal;
						$refundedAmount = (($ref == (float)$tourBooking->booking_total) ? 0.00 : $refundData);
						
						//Update total in booking master
						//TourBooking::where('id', $id)->update(['total' => number_format((float)$tourBooking->total - $refundedAmount, 2, '.', '')]);
						// To pass in email
						// Below line is for to update transactions table
						$Transaction = RefundTransactionUpdateService::run($id, $refundedAmount, '', $fee_amount, null, $refundVia, $transaction, $request);
						$refund_keep_us = 0;

						// Below line is for to update tour booking history tabll
						$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $tourBooking->total, $refund_keep_us, $refundVia, $created_by_with_name, $request, "100 % Refund", $userType);
						DB::commit();
					}
				} else {
					$net_amount = ($trans->payment_type == 1) ? ($net_amount_split - $ref) : (($ref == (float)$tourBooking->booking_total) ? 0 : $tourBooking->total);
					$refund_keep_us = 0;
					$refund_type = "100 % Refund";
					// To pass in email
					$refundData = (float)$net_amount - $refTotal;
					$refundedAmount = (($ref == (float)$tourBooking->booking_total) ? 0.00 : $refundData);

					$Transaction = RefundTransactionUpdateService::run($id, $refundedAmount, '', 0, null, $refundVia, $transaction, $request);
					DB::commit();

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

		// 100% Refund for Card Payment
		if ($transaction->via == "Card" && $request->fareharbor != "fareharbor") {
			$refundVia = "Card";
			$refTotal = 0;
			$ref = 0;
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				if ($value->payment_method == "Don't Refund") {
					$refTotal += $details->amount;
				}
				$ref += $details->amount;
			}
			$ref = $ref - $refTotal;

			$paymentTransCount = Transaction::where('tour_booking_id', $id)
				->where('status', 'Completed')
				->where(function ($query) {
					$query->whereNull('charge_type')
						->orWhere('charge_type', 'discount');
				})
				->count();

			$net_amount_split = 0;
			if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "100_refund") {
				$transGet = Transaction::where('tour_booking_id', $id)
					->where('status', 'Completed')
					->where(function ($query) {
						$query->whereNull('charge_type')
							->orWhere('charge_type', 'discount');
					})
					->get();

				$refund_keep_us = 0;
				$cardsArray = array();
				$partialAmountArray = array();
				$transactionIdArray = array();

				foreach ($transGet as $value) {
					// $refundtrans = Transaction::where('tour_booking_id', $id)->where('transaction_number', $value->transaction_number)->where('status', 'Refund')->whereNull('charge_type')->count();
					$refundtrans = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $value->transaction_number)
						->where('status', 'Refund')
						->where(function ($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->count();

					$histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
					if (!empty($histPaymentDetails)) {
						$cardsArray[] = $histPaymentDetails->card_last_4_digit;
					}
					if ($refundtrans == 0) {
						$net_amount = $value->amount;

						if ($value->transaction_number != '' && $net_amount > 0) {
							$refundParams = [
								'payment_intent' => $value->transaction_number,
								'amount' => $net_amount * 100,
								'reverse_transfer' => true,
								'refund_application_fee' => true,
							];
							// $stripeAmount = $net_amount + $addonTotal;
							app(PaymentGatewayInterface::class)->refundPayment($refundParams, $value->payment_mode);
						}

						// Below line is for to update transactions table
						$Transaction = RefundTransactionUpdateService::run($id, $net_amount, $value->transaction_number, $value->fee_amount, $value->application_fee_id, $refundVia, $transaction, $request);
						$partialAmountArray[] = $net_amount;
						$transactionIdArray[] = $Transaction->id;

						$net_amount_split += $value->amount;
					} else {
						// This is for transactions for added people to booking
						if ($paymentTransCount > 1 && $trans->payment_type != 1) {
							$refundedTransForTransaction = Transaction::where('tour_booking_id', $id)
								->where('transaction_number', $value->transaction_number)
								->where('status', 'Refund')
								->where(function ($query) {
									$query->whereNull('charge_type')
										->orWhere('charge_type', 'discount');
								})
								->get(['id', 'amount']);

							$refundedTransForTransactionZero = Transaction::where('tour_booking_id', $id)
								->where('transaction_number', $value->transaction_number)
								->where('status', 'Refund')
								->where('amount', 0)
								->where(function ($query) {
									$query->whereNull('charge_type')
										->orWhere('charge_type', 'discount');
								})
								->get(['id', 'amount']);

							$dontrefundamount = 0;
							if (count($refundedTransForTransactionZero) > 0) {
								foreach ($refundedTransForTransactionZero as $his) {
									$tBH = TourBookingHistory::where('transaction_id', $his->id)->first();
									$jsonData = json_decode($tBH->details, true);
									$dontrefundamount += $jsonData['amount'];
								}
							}

							$tourBookingHistoryFees = DB::table('tour_booking_history as tbh')
								->join('transactions as t', 't.id', '=', 'tbh.transaction_id')
								->where('tbh.tour_booking_id', $id)
								->where('tbh.payment_method', 'Full Refund minus Fees')
								->where('t.transaction_number', $value->transaction_number)
								->where('t.status', 'Refund')
								->whereNull('t.charge_type')
								->get();
							$dontrefundamountFee = 0;
							if (count($tourBookingHistoryFees) > 0) {
								foreach ($tourBookingHistoryFees as $hisFee) {
									$jsonDataFee = json_decode($hisFee->details, true);
									$dontrefundamountFee += $jsonDataFee['refund_keep_us'];
								}
							}

							$totalrefundedamountfortransaction = 0;
							foreach ($refundedTransForTransaction as $ky => $val) {
								$totalrefundedamountfortransaction += $val->amount;
							}
							$net_amount = $value->amount - $totalrefundedamountfortransaction - $dontrefundamount - $dontrefundamountFee;
							if ($net_amount >= 0) {
								$net_amount = number_format((float)$net_amount, 2, '.', '');
								if ($value->transaction_number != '' && $net_amount > 0) {
									$refundParams = [
										'payment_intent' => $value->transaction_number,
										'amount' => $net_amount * 100,
										'reverse_transfer' => true,
										'refund_application_fee' => true,
									];
									app(PaymentGatewayInterface::class)->refundPayment($refundParams, $value->payment_mode);
								}

								// Below line is for to update transactions table
								$Transaction = RefundTransactionUpdateService::run($id, $net_amount, $value->transaction_number, round($net_amount * $tourBookingTransaction->stripe_fees_percentage / 100, 2), $value->application_fee_id, $refundVia, $transaction, $request);
								$partialAmountArray[] = $net_amount;
								$transactionIdArray[] = $Transaction->id;

								$net_amount_split += $net_amount;
							}
						} else if ($paymentTransCount >= 1 && $trans->payment_type == 1) {
							$refundedTransForTransaction = Transaction::where('tour_booking_id', $id)
								->where('transaction_number', $value->transaction_number)
								->where('status', 'Refund')
								->where(function ($query) {
									$query->whereNull('charge_type')
										->orWhere('charge_type', 'discount');
								})
								->get(['id', 'amount']);

							$refundedTransForTransactionZero = Transaction::where('tour_booking_id', $id)
								->where('transaction_number', $value->transaction_number)
								->where('status', 'Refund')
								->where('amount', 0)
								->where(function ($query) {
									$query->whereNull('charge_type')
										->orWhere('charge_type', 'discount');
								})
								->get(['id', 'amount']);

							$dontrefundamount = 0;
							if (count($refundedTransForTransactionZero) > 0) {
								foreach ($refundedTransForTransactionZero as $his) {
									$tBH = TourBookingHistory::where('transaction_id', $his->id)->first();
									$jsonData = json_decode($tBH->details, true);
									$dontrefundamount += $jsonData['amount'];
								}
							}
							$tourBookingHistoryFees = DB::table('tour_booking_history as tbh')
								->join('transactions as t', 't.id', '=', 'tbh.transaction_id')
								->where('tbh.tour_booking_id', $id)
								->where('tbh.payment_method', 'Full Refund minus Fees')
								->where('t.transaction_number', $value->transaction_number)
								->where('t.status', 'Refund')
								->whereNull('t.charge_type')
								->get();
							$dontrefundamountFee = 0;
							if (count($tourBookingHistoryFees) > 0) {
								foreach ($tourBookingHistoryFees as $hisFee) {
									$jsonDataFee = json_decode($hisFee->details, true);
									$dontrefundamountFee += $jsonDataFee['refund_keep_us'];
								}
							}

							$totalrefundedamountfortransaction = 0;
							foreach ($refundedTransForTransaction as $ky => $val) {
								$totalrefundedamountfortransaction += $val->amount;
							}
							$net_amount = $value->amount - $totalrefundedamountfortransaction - $dontrefundamount - $dontrefundamountFee;
							if ($net_amount >= 0) {
								$net_amount = number_format((float)$net_amount, 2, '.', '');
								if ($value->transaction_number != '' && $net_amount > 0) {
									$refundParams = [
										'payment_intent' => $value->transaction_number,
										'amount' => $net_amount * 100,
										'reverse_transfer' => true,
										'refund_application_fee' => true,
									];
									app(PaymentGatewayInterface::class)->refundPayment($refundParams, $value->payment_mode);
								}

								// Below line is for to update transactions table
								$Transaction = RefundTransactionUpdateService::run($id, $net_amount, $value->transaction_number, round($net_amount * $tourBookingTransaction->stripe_fees_percentage / 100, 2), $value->application_fee_id, $refundVia, $transaction, $request);
								$partialAmountArray[] = $net_amount;
								$transactionIdArray[] = $Transaction->id;

								$net_amount_split += $net_amount;
							}
						}
					}
				}
				$historyId = DB::table('tour_booking_history')
					->insertGetId([
						'tour_booking_id' => $id,
						'tourists' => $tourBooking->tourists + $tourBooking->infants,
						'operation' => 'Refund',
						'details' => json_encode([
							'amount' => $net_amount_split,
							'refund_keep_us' => $refund_keep_us,
							'payment_type' => $refundVia,
							'created_by_with_name' => $created_by_with_name,
							'refund_type' => '100 % Refund',
							'cards' => $cardsArray,
							'refund_amounts' => $partialAmountArray,
							'note' => $request->comment,
							'transactionIdArray' => $transactionIdArray,
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
				if ((float)$tourBooking->booking_total != (float)$ref) {
					$refundParams = [
						'payment_intent' => $transaction->transaction_number,
						'amount' => $tourBooking->total * 100,
						'reverse_transfer' => true,
						'refund_application_fee' => true,
					];
					// $stripeAmount = $tourBooking->total + $addonTotal;
					$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
					$stripe_commission = roundout($tourBookingTransaction->stripe_fees_percentage, 2);
					$fee_amount = roundout((($tourBooking->total * $stripe_commission) / 100), 2);
					$serviceComission = $tourBooking->service_commission;
				} else {
					$serviceComission = 0.00;
				}
				$refundAmount = (float)$tourBooking->booking_total == (float)$ref ? 0.00 : $tourBooking->total;

				// Below line is for to update transactions table
				$Transaction = RefundTransactionUpdateService::run($id, $refundAmount, $transaction->transaction_number, $serviceComission, $transaction->application_fee_id, $refundVia, $transaction, $request);
				$refund_keep_us = 0;

				// Below line is for to update tour booking history table
				$historyId = RefundHistoryUpdateService::run($id, $tourBooking, $tourBooking->total, $refund_keep_us, $refundVia, $created_by_with_name, $request, "100 % Refund", $userType);
				DB::commit();

				// To pass in email
				$refundedAmount = $refundAmount;
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
