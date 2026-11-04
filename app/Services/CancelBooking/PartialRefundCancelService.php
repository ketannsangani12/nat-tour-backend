<?php

namespace App\Services\CancelBooking;

use App\Jobs\DetectPayoutAdjustment;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\TourBookingHistory;
use App\Services\CancelBooking\CancelTransactionUpdateService;
use App\Services\CancelBooking\CancelHistoryUpdateService;
use Carbon\Carbon;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use Illuminate\Support\Facades\Auth;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Constants\Constants;

class PartialRefundCancelService
{
	/**
	 * Execute the service.
	 *
	 * @return mixed
	 */
	public static function cancel($transaction, $id, $tourBooking, $request, $createdByWithName, $userType)
	{
		$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'asc')->first();
		$PaymentHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->whereNull('charge_type')->get();
		// Unpaid split reallocations are not refunds of any payment; see isUnpaidSplitReallocation().
		$RefundHistory = TourBookingHistory::withoutUnpaidSplitReallocations(TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->whereNull('charge_type')->get());
		$isCashFees = cashFees();

		// If booking is fareharbor
		if ($request->fareharbor == "fareharbor") {
			if (isset($request->is_cash) && $request->is_cash != '') {
				if ($request->is_cash == 'Cash') {
					$refundVia = 'Cash';
				} elseif ($request->is_cash == 'Wire Transfer') {
					$refundVia = 'Check/Wire';
				}
			} else {
				$refundVia = 'Cash';
			}

			$ref = 0;
			$net_amount_split = 0;

			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
			}

			$net_amount = $request->partial_refund_value;
			$refund_keep_us = $tourBooking->total - $request->partial_refund_value;
			$refund_type = "fareharbor : Partial Refund";

			$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
			DB::commit();

			//Below line is for to store reverse transfer for partner booking
			if ($tourBooking->is_partner_booking == 1) {
				ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $net_amount);
			}

			// Below line is for to update history table as per cancel booking refund option
			$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $createdByWithName, $request, $refund_type, $userType);
			DB::commit();

			// To pass in email
			$refundedAmount = $Transaction->amount;
		}

		if (isset($request->is_cash) && $request->is_cash != '') {
			if ($request->is_cash == 'Cash') {
				$transaction->via = "Cash";
			} elseif ($request->is_cash == 'Wire Transfer') {
				$transaction->via = "Wire Transfer";
			}
		}

		// Partial Refund for Cash, Invoiced and Wire Transfer Payment
		if ($transaction->via == "Cash" || $transaction->via == "Invoiced" || $transaction->via == "Wire Transfer") {
			$ref = 0;
			$net_amount_split = 0;
			$refTotal=0;
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				if($value->payment_method=="Don't Refund"){
					$refTotal+=$details->amount;
				}
			}
			$ref= $ref-$refTotal;

			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
			}

			// How much the TOUR/ticket side can actually still give up — $net_amount_split holds
			// total tour payments and $ref is the already-refunded tour amount. Computed here (not
			// just further below, where it already existed for the addon-spillover calc) so the
			// ticket-side refund amount below can be capped by it too — see the clamp comment there
			// for why that matters.
			$tourNetAvailable = round($net_amount_split - $ref, 2);

			if (isset($request->is_cash) && $request->is_cash != '') {
				if ($request->is_cash == 'Cash') {
					$refundVia = 'Cash';
				} else if ($request->is_cash == 'Wire Transfer') {
					$refundVia = 'Check/Wire';
				}
			} else {
				$refundVia = $transaction->via;
			}

			// The admin explicitly overrode this booking to a manual Cash/Check-Wire settlement
			// (e.g. it's really a Card booking, but they're reimbursing by hand) — add-ons that were
			// originally charged on Stripe must follow the same override instead of hitting the card,
			// same as the tour-side ticket refund above already does via $refundVia. Null (no
			// override submitted) means every add-on refunds by its own real payment method, as before.
			$addonManualRefundVia = (isset($request->is_cash) && $request->is_cash != '') ? $refundVia : null;

			if ($request->fareharbor !== "fareharbor") {
				if ($isCashFees == '1' && $transaction->via == "Cash") {
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->count();

					if (($trans->payment_type == 0 || $paymentTransCount == 1) && $request->refund_type == "partial_refund") {

						// Cap the TICKET-side refund at what the tour side actually still has
						// ($tourNetAvailable) — Partial Refund is one combined dollar figure the
						// admin types covering tour + add-ons together (the block below already
						// spills anything beyond $tourNetAvailable over to add-ons), so writing the
						// FULL raw $request->partial_refund_value here as well double-recorded the
						// spilled-over portion: once (correctly) as an add-on refund, and again
						// (wrongly) as ticket-side money that was never actually available on the
						// ticket. That inflated the booking's total recorded refund past what was
						// ever actually collected.
						$refundData=((float)$ref== (float)$tourBooking->booking_total) ? 0.00 : min((float) $request->partial_refund_value, $tourNetAvailable);

						$Transaction = CancelTransactionUpdateService::run($id, $refundData, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
						$refund_keep_us = $transaction->amount - $refundData;

						//Below line is for to store reverse transfer for partner booking
						if ($tourBooking->is_partner_booking == 1) {
							ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $refundData);
						}

						// Below line is for to update tour booking history table
						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $refundData, $refund_keep_us, $refundVia, $createdByWithName, $request, "Partial Refund", $userType);
						DB::commit();

						// To pass in email
						$refundedAmount = $refundData;
					}
				} else {
					// Same cap as the sibling branch above — see its comment.
					$net_amount_data = min((float) $request->partial_refund_value, $tourNetAvailable);

					$refund_keep_us = $tourBooking->total - $net_amount_data;
					$refund_type = "Partial Refund";
					$net_amount=((float)$ref== (float)$tourBooking->booking_total) ? 0.00 :  $net_amount_data;
					// To pass in email
					$refundedAmount = $net_amount;
					$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
					DB::commit();

					//Below line is for to store reverse transfer for partner booking
					if ($tourBooking->is_partner_booking == 1) {
						ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $net_amount);
					}

					// Below line is for to update history table as per cancel booking refund option
					$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $createdByWithName, $request, $refund_type, $userType);
					DB::commit();
				}
			// Refund any addon transactions for the portion of the partial refund that
			// exceeds the tour net amount.
			$addonRemainder    = max(0.0, (float) $request->partial_refund_value - $tourNetAvailable);
			if ($addonRemainder > 0.001) {
				(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_PARTIAL_REFUND, null, $addonRemainder, $addonManualRefundVia);
			}

			return [
					'historyId' => $historyId,
					'via' => $Transaction->via,
					'transaction_id' => $Transaction->id,
					'refundedAmount' => $refundedAmount
				];
			}
		}

		// Partial Refund for Card Payment
		if ($transaction->via == "Card" && $request->fareharbor != "fareharbor") {
			$refundVia = "Card";
			$ref = 0;
			$refTotal=0;
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				if($value->payment_method=="Don't Refund"){
					$refTotal+=$details->amount;
				}
			}
			$ref= $ref-$refTotal;
			$paymentTransCount = Transaction::where('tour_booking_id', $id)
				->where('status', 'Completed')
				->whereNull('charge_type')
				->count();

			$net_amount_total = 0;

			if (($trans->payment_type == 0 || $paymentTransCount == 1) && $request->refund_type == "partial_refund") {

				$transactions = Transaction::where('tour_booking_id', $id)
					->where('status', 'Completed')
					->where(function($query) {
						$query->whereNull('charge_type')
							->orWhere('charge_type', 'discount');
					})
					->get();
				
				$refund_keep_us = 0;
				$cardsArray = array();
				$partialAmountArray = array();
				$discountAmount = 0;
				$refundedPartnerFees = 0;
				foreach ($transactions as $key => $trans) {
					$histPaymentDetails = TourBookingHistory::where('transaction_id', $trans->id)
						->where('operation', 'Payment')
						->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);

					$refundedTransForTransaction = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $trans->transaction_number)
						->where('status', 'Refund')
						->where(function($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->get(['id', 'amount']);

					$refundedTransForTransactionZero = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $trans->transaction_number)
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

					foreach ($refundedTransForTransaction as $ky => $val) {
						$totalrefundedamountfortransaction += $val->amount;
					}

					$net_amount = ($histPaymentDetails->ticket_cost + $histPaymentDetails->platform_fees) - $totalrefundedamountfortransaction - $dontrefundamount;
					$transactions[$key]->net_amount = $net_amount;
				}
				
				foreach ($transactions as $key => $trans) {
					if($trans->charge_type == null) {
						$refundAmountFromConnectedAccountForTrans = 0;
						$refundPartnerFeesForTrans = 0;
						$netPartnerAmountsForTrans = ($tourBooking->is_partner_booking == 1) ? ThreeWayPaymentService::getNetPartnerAmountsForTransaction($id,$trans->transaction_number) : 0;  //Get net ticket cost without partner fees
						$netTicketCostForTrans = $netPartnerAmountsForTrans['netTicketCost'] ?? 0;
						$netPartnerFeesForTrans = $netPartnerAmountsForTrans['netPartnerCommission'] ?? 0;
						//Below line is for to check if the partial refund value is greater than net ticket cost and refund from partner fees amount for selected transaction
						$refundAmountFromConnectedAccountForTrans = 0;
						if ($tourBooking->is_partner_booking == 1 && $netTicketCostForTrans > 0) {
							if ($netTicketCostForTrans < $request->partial_refund_value) {
								$remainingAmount = $request->partial_refund_value - $netTicketCostForTrans;
								if ($netPartnerFeesForTrans >= $remainingAmount) {
									$refundPartnerFeesForTrans = $remainingAmount;
								} else if ($netPartnerFeesForTrans > 0 && $netPartnerFeesForTrans < $remainingAmount) {
									$refundPartnerFeesForTrans = $netPartnerFeesForTrans;
								}
								$refundAmountFromConnectedAccountForTrans = $netTicketCostForTrans;
							} else {
								$refundAmountFromConnectedAccountForTrans = $request->partial_refund_value;
							}
						}
						if($request->partial_refund_value > $trans->net_amount) {
							$discountAmount = $request->partial_refund_value - $trans->net_amount;
		
							$histPaymentDetails = TourBookingHistory::where('transaction_id', $trans->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
							
							if (!empty($histPaymentDetails)) {
								array_push($cardsArray, $histPaymentDetails->card_last_4_digit);
							}

							if((float)$tourBooking->booking_total!=(float)$ref){
								if ($tourBooking->is_partner_booking == 1 && $refundAmountFromConnectedAccountForTrans > 0) {
									ThreeWayPaymentService::reverseTransfer($id, $trans->id, $refundAmountFromConnectedAccountForTrans);
								}
								$refundParams = [
									'payment_intent' => $trans->transaction_number,
									'amount' => $trans->net_amount * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $trans->payment_mode);
							}
		
							$refundAmount = $trans->net_amount;

							// Below line is for to update transactions table
							$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $trans->payment_mode);
			
							if ($tourBooking->is_partner_booking == 1) {
								if ($refundAmountFromConnectedAccountForTrans > 0) {
									ThreeWayPaymentService::storeReverseTransferInPayout($id, $trans->id, $Transaction->id, $refundAmountFromConnectedAccountForTrans,$refundPartnerFeesForTrans);
								}

								if ($refundAmountFromConnectedAccountForTrans <= 0 && $refundPartnerFeesForTrans > 0) {
									//If the net ticket cost is less than the partial refund value then we will refund from partner fees
									ThreeWayPaymentService::storeReverseTransferOfPartnerFeesOnly(
										$id, 
										$trans->id, 
										$Transaction->id ?? null, 
										$refundPartnerFeesForTrans,
										'Card'
									);
								}
							}
							// To pass in email
							$refundedAmount = $refundAmount;
		
							$refundedPartnerFees += $refundPartnerFeesForTrans;
							array_push($partialAmountArray, $refundAmount);
						} else {
							if((float)$tourBooking->booking_total!=(float)$ref){
								
								if ($tourBooking->is_partner_booking == 1 && $refundAmountFromConnectedAccountForTrans > 0) {
									ThreeWayPaymentService::reverseTransfer($id, $transaction->id, $refundAmountFromConnectedAccountForTrans);
								}
								$refundParams = [
									'payment_intent' => $transaction->transaction_number,
									'amount' => $request->partial_refund_value * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
							}
						
							$refundAmount=(float)$tourBooking->booking_total==(float)$ref?0.00:$request->partial_refund_value;
			
							// // Below line is for to update transactions table
							$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
							$refund_keep_us = $net_amount - $request->partial_refund_value;
			
							//Below line is for to store reverse transfer in payout table
							if ($tourBooking->is_partner_booking == 1) {
								if ($refundAmountFromConnectedAccountForTrans > 0) {
									ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $refundAmountFromConnectedAccountForTrans,$refundPartnerFeesForTrans);
								}

								if ($refundAmountFromConnectedAccountForTrans <= 0 && $refundPartnerFeesForTrans > 0) {
									//If the net ticket cost is less than the partial refund value then we will refund from partner fees
									ThreeWayPaymentService::storeReverseTransferOfPartnerFeesOnly(
										$id, 
										$trans->id, 
										$refundTransId ?? null, 
										$refundPartnerFeesForTrans,
										'Card'
									);
								}
								
							}

							// // Below line is for to update tour booking history table
							$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $refundAmount, $refund_keep_us, $refundVia, $createdByWithName, $request, "Partial Refund", $userType,$refundPartnerFeesForTrans);
							DB::commit();

							// // To pass in email
							$refundedAmount = $refundAmount;

							// partial_refund_value fits entirely within tour net — no addon refund needed.

							return [
								'historyId' => $historyId,
								'via' => $Transaction->via,
								'transaction_id' => $Transaction->id,
								'refundedAmount' => $refundedAmount
							];
						}
					}
				}

				$finalAmount = 0;

				if($discountAmount > 0) {
					$discountTransactions = Transaction::where('tour_booking_id', $id)
						->where('status', 'Completed')
						->where('charge_type', 'discount')
						->get();

					foreach ($discountTransactions as $key => $dTransaction) {
						if($discountAmount <= $dTransaction->amount) {
							if((float)$tourBooking->booking_total!=(float)$ref){
								if ($tourBooking->is_partner_booking == 1) {
									ThreeWayPaymentService::reverseTransfer($id, $dTransaction->id, $discountAmount );
								}
								$refundParams = [
									'payment_intent' => $dTransaction->transaction_number,
									'amount' => $discountAmount * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true
								];
								$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $dTransaction->payment_mode);
							}
						
							$refundAmount = $discountAmount;

							$histPaymentDetails = TourBookingHistory::where('transaction_id', $dTransaction->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
					
							if (!empty($histPaymentDetails)) {
								array_push($cardsArray, $histPaymentDetails->card_last_4_digit);
							}
			
							// Below line is for to update transactions table
							$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $dTransaction->transaction_number, 0, null, $refundVia, $dTransaction, $request, mode: $dTransaction->payment_mode);

							if ($tourBooking->is_partner_booking == 1) {
								ThreeWayPaymentService::storeReverseTransferInPayout($id, $dTransaction->id, $Transaction->id, $refundAmount);
							}
							$refundedAmount = $discountAmount;
							$refund_keep_us = ($transaction->amount + $discountAmount) - $request->partial_refund_value;
							array_push($partialAmountArray, $discountAmount);

							break;
						} else {
							if(($discountAmount > $dTransaction->amount) && $finalAmount == 0) {
								if((float)$tourBooking->booking_total!=(float)$ref){
									if ($tourBooking->is_partner_booking == 1) {
										ThreeWayPaymentService::reverseTransfer($id, $dTransaction->id, $dTransaction->amount);
									}
									$refundParams = [
										'payment_intent' => $dTransaction->transaction_number,
										'amount' => $dTransaction->amount * 100,
										'reverse_transfer' => true,
										'refund_application_fee' => true
									];
									$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $dTransaction->payment_mode);
								}
							
								$refundAmount = $dTransaction->amount;
	
								$histPaymentDetails = TourBookingHistory::where('transaction_id', $dTransaction->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
						
								if (!empty($histPaymentDetails)) {
									array_push($cardsArray, $histPaymentDetails->card_last_4_digit);
								}
				
								// Below line is for to update transactions table
								$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $dTransaction->transaction_number, 0, null, $refundVia, $dTransaction, $request, mode: $dTransaction->payment_mode);

								if ($tourBooking->is_partner_booking == 1) {
									ThreeWayPaymentService::storeReverseTransferInPayout($id, $dTransaction->id, $Transaction->id, $refundAmount);
								}
								$refundedAmount = $dTransaction->amount;
								$refund_keep_us = $discountAmount - $dTransaction->amount;
								array_push($partialAmountArray, $dTransaction->amount);

								$finalAmount = $discountAmount - $dTransaction->amount;
							} else {
								if((float)$tourBooking->booking_total!=(float)$ref){
									if ($tourBooking->is_partner_booking == 1) {
										ThreeWayPaymentService::reverseTransfer($id, $dTransaction->id, $finalAmount);
									}
									$refundParams = [
										'payment_intent' => $dTransaction->transaction_number,
										'amount' => $finalAmount * 100,
										'reverse_transfer' => true,
										'refund_application_fee' => true
									];
									$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $dTransaction->payment_mode);
								}
							
								$refundAmount = $finalAmount;
	
								$histPaymentDetails = TourBookingHistory::where('transaction_id', $dTransaction->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
						
								if (!empty($histPaymentDetails)) {
									array_push($cardsArray, $histPaymentDetails->card_last_4_digit);
								}
				
								// Below line is for to update transactions table
								$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $dTransaction->transaction_number, 0, null, $refundVia, $dTransaction, $request, mode: $dTransaction->payment_mode);

								if ($tourBooking->is_partner_booking == 1) {
									ThreeWayPaymentService::storeReverseTransferInPayout($id, $dTransaction->id, $Transaction->id, $refundAmount);
								}
								$refundedAmount = $finalAmount;
								$refund_keep_us = $dTransaction->amount - $finalAmount;
								array_push($partialAmountArray, $finalAmount);
							}
						}
					}

					// Record the actual tour/discount amount refunded, not the full entered value.
					$actualTourRefunded = array_sum($partialAmountArray);

					$historyId = DB::table('tour_booking_history')
						->insertGetId([
							'tour_booking_id' => $id,
							'tourists' => $tourBooking->tourists + $tourBooking->infants,
							'operation' => 'Cancelled',
							'details' => json_encode([
								'amount' => $actualTourRefunded,
								'refund_keep_us' => $refund_keep_us,
								'payment_type' => 'Card',
								'created_by_with_name' => $createdByWithName,
								'refund_type' => 'Partial Refund',
								'cards' => $cardsArray,
								'refund_amounts' => $partialAmountArray,
								'partner_fees' => $refundedPartnerFees,
								'note' => $request->comment
							], JSON_NUMERIC_CHECK),
							'operation_at' => mysqlDT(),
							'operation_by' => Auth::id(),
							'operation_by_user_type' => $userType,
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);

					DB::commit();

					DetectPayoutAdjustment::dispatch($id, $historyId, 'Cancelled');

					// Refund addon transactions for the portion that could not be covered
					// by tour/discount transactions.
					$addonRemainder = max(0.0, (float) $request->partial_refund_value - $actualTourRefunded);
					if ($addonRemainder > 0.001) {
						(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_PARTIAL_REFUND, null, $addonRemainder);
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
	}

	public static function refund($transaction, $tourBooking, $request, $netAmount)
	{
		$refundFlag = 1;
		if (empty($transaction)) {
			$netAmount = $tourBooking->total;
		}
		if ($request->partial_refund_value > $netAmount) {
			$refundFlag = 0;
		}
		if (!empty($transaction) && ($transaction->via == "Cash" || $transaction->via == "Invoiced" || $transaction->via == 'Wire Transfer')) {
			if ($request->partial_refund_value > $netAmount) {
				$refundFlag = 0;
			}
		}

		return $refundFlag;
	}
}
