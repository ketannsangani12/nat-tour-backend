<?php

namespace App\Services\CancelBooking;

use App\Jobs\DetectPayoutAdjustment;
use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Repositories\TourBooking\TourBookingFormsRepository;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Constants\Constants;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\CancelBooking\CancelTransactionUpdateService;
use App\Services\CancelBooking\CancelHistoryUpdateService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use Illuminate\Support\Facades\Auth;

class WeatherRefundCancelService
{
	/**
	 * Execute the service.
	 *
	 * @return mixed
	 */
	public static function cancel($transaction, $id, $tourBooking, $request, $createdByWithName, $userType, $tourBookingTransaction)
	{
		$tourBookingFormRepository = new TourBookingFormsRepository();
		$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'asc')->first();
		$PaymentHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->whereNull('charge_type')->get();
		// Unpaid split reallocations are not refunds of any payment; see isUnpaidSplitReallocation().
		$RefundHistory = TourBookingHistory::withoutUnpaidSplitReallocations(TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->whereNull('charge_type')->get());

		$isCashFees = cashFees();
		$isPartnerBooking = $tourBooking->is_partner_booking;
		$netPartnerFees = ($isPartnerBooking == 1) ? ThreeWayPaymentService::getNetPartnerFees($id) : 0.00;

		// Capture the TRUE original payment method before the is_cash override below mutates
		// $transaction->via — needed to tell "genuinely Cash" (no card/platform fee ever existed)
		// apart from "really Card, admin is just manually settling as Cash/Check-Wire" (a real
		// Stripe fee WAS collected and must still be recorded, even though this cancellation never
		// touches Stripe).
		$originalTransactionVia = $transaction->via;

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

			$net_amount = ($trans->payment_type == 1) ? ($net_amount_split - $ref) : $tourBooking->total;
			$refund_keep_us = 0;
			$refund_type = "fareharbor : 100 % Refund";

			$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
			DB::commit();

			// Below line is for to update history table as per cancel booking refund option
			$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $createdByWithName, $request, $refund_type, $userType, $netPartnerFees);
			DB::commit();

			// To pass in email
			$refundedAmount = $Transaction->amount;
		}

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
			$totalTicketCostAmount = 0;
			$refTicketCostAmount = 0;
			$refTotal = 0;
			// A "Don't Refund" pax removal keeps that pax's money: its details->amount is $0 and the
			// kept amount is in refund_keep_us (already scaled to paid pax only). The removed pax is
			// still inside the Payment history amounts ($net_amount_split), so the formulas built on
			// those must exclude it, or a later 100% Refund hands back money that was forfeited.
			// tour_bookings_master.total is already reduced by the removal, so the total-based
			// formulas need nothing. Rows that carry the kept amount in details->amount instead are
			// already netted via $refTotal, so they are skipped here.
			$dontRefundKeptAmount = 0;
			// Pax removed before the first payment were never part of $net_amount_split.
			$firstPaymentHistoryId = TourBookingHistory::tourPaymentHistoryId($id);

			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				if ($value->payment_method == "Don't Refund") {
					$refTotal += $details->amount;
					if ((float) $details->amount == 0 && !TourBookingHistory::isRecordedBeforePayment($value, $firstPaymentHistoryId)) {
						$dontRefundKeptAmount += (float) ($details->refund_keep_us ?? 0);
					}
				}
				$ref += $details->amount;
				if ($isCashFees == '1' && $value->payment_method == 'Full Refund minus Fees') {
					$dontrefundamountFee += $details->refund_keep_us;
				}
				if ($isCashFees == '1' && $value->payment_method == "Don't Refund") {
					$dontrefundamount += $details->amount;
				}
				$refTicketCostAmount += $value->ticket_cost - $value->partner_fees;
			}
			$ref = $ref - $refTotal;

			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
				$totalTicketCostAmount += $value->ticket_cost - $value->partner_fees;
			}

			//This is if booking it partner booking and calculations of ticket cost without partner fees
			$remainingTicketCost = $totalTicketCostAmount - $refTicketCostAmount;
			
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

			// Real platform-fee accounting also applies when the admin manually overrode an
			// originally Card-paid booking to a Cash/Check-Wire settlement — that booking DID
			// collect a real Stripe fee, so cancelling it should still compute and record it (in
			// transactions.fee_amount and history's "Platform Fees") even though no Stripe call
			// happens in this branch. Without this, $isCashFees being off (fees legitimately don't
			// apply to genuinely-cash bookings) also silently zeroed the fee for the override case.
			$isManualOverrideOfCard = $originalTransactionVia === 'Card'
				&& isset($request->is_cash) && $request->is_cash != '';

			if ($request->fareharbor !== "fareharbor") {
				if (($isCashFees == '1' && $transaction->via == "Cash") || $isManualOverrideOfCard) {
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->count();

					if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "100_refund") {

						$net_amount = $net_amount_split - $ref - $dontrefundamountFee - $dontRefundKeptAmount;
						$net_amount = $net_amount < 0 ? 0 : round($net_amount, 2);
						$refundedAmount =  (float)$net_amount - $refTotal;

						$stripe_commission = $tourBookingTransaction->stripe_fees_percentage;
						$fee = roundout((($tourBooking->booking_total * $stripe_commission) / 100), 2);
						$fee_amount = (roundout($refundedAmount, 2) <= $fee) ? 0.00 : roundout($net_amount * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
						$Transaction = CancelTransactionUpdateService::run($id, $refundedAmount, null, $fee_amount, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);

						//This is for calculating ticket cost without partner fees to reverse the transfer
						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
							ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
						}
						
						$refund_keep_us = 0;
						// To pass in email
						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $createdByWithName, $request, "100 % Refund", $userType, $netPartnerFees);
						DB::commit();
					} else {
						$stripe_commission = $tourBookingTransaction->stripe_fees_percentage;
						$fee = roundout((($tourBooking->booking_total * $stripe_commission) / 100), 2);
						$fee_amount = (roundout($ref, 2) == roundout((float)$tourBooking->booking_total, 2)) ? 0.00 : roundout((($tourBooking->total * $stripe_commission) / 100), 2);
						$fee_amount = ((float)$tourBooking->total <= $fee) ? 0.00 : $fee_amount;
						$refundData = (float)$tourBooking->total - $refTotal;
						$refundedAmount = ((roundout($ref, 2) == roundout((float)$tourBooking->booking_total, 2)) ? 0.00 : $refundData);
						// To pass in email
						// Below line is for to update transactions table
						$Transaction = CancelTransactionUpdateService::run($id, $refundedAmount, null, $fee_amount, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
						$refund_keep_us = 0;

						//This is for calculating ticket cost without partner fees to reverse the transfer
						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
							ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
						}
						// Below line is for to update tour booking history tabll
						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $refundedAmount, $refund_keep_us, $refundVia, $createdByWithName, $request, "100 % Refund", $userType, $netPartnerFees);
						DB::commit();
					}
				} else {
					$net_amount = ($trans->payment_type == 1) ? max(0, round($net_amount_split - $ref - $dontRefundKeptAmount, 2)) : (($ref == (float)$tourBooking->booking_total) ? 0 : $tourBooking->total);
					$refund_keep_us = 0;
					$refund_type = "100 % Refund";
					// To pass in email
					$refundData = (float)$net_amount - $refTotal;
					$refundedAmount = (($ref == (float)$tourBooking->booking_total) ? 0.00 : $refundData);

					$Transaction = CancelTransactionUpdateService::run($id, $refundedAmount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
					DB::commit();

					//This is for calculating ticket cost without partner fees to reverse the transfer
					$remainingTicketCost = $refundedAmount - $netPartnerFees;

					if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
						ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
					}
					// Below line is for to update history table as per cancel booking refund option
					$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $createdByWithName, $request, $refund_type, $userType, $netPartnerFees);
					DB::commit();
				}

				// Addon charges were only ever being refunded on the Card branch below;
				// Cash/Invoiced/Wire Transfer bookings need the same 100% addon refund.
				(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_FULL_REFUND, null, null, $addonManualRefundVia);
				// Refund Custom Charge
				CustomChargeBookingService::refundCustomChargeFromCancel($tourBooking->id, $request);
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
			// A prior "Full Refund minus Fees" (Ticket Cost) removal refunds ticket cost only and
			// keeps its proportional platform fee as revenue -- that fee was never charged as its
			// own transaction and never refunded, so $ref (built from $details->amount) never
			// includes it. A later 100% Refund must not hand it back a second time on top of the
			// remaining pax's own fee, so track it separately here and net it out below.
			$alreadyKeptFee = 0;
			// A "Don't Refund" pax removal keeps that pax's ticket cost AND platform fee as revenue --
			// no money moves, and its details->amount is netted out of $ref below, so nothing here
			// otherwise accounts for it. But the pax IS gone from the booking, so their slice of the
			// ORIGINAL (never-decremented) per-transaction charge must be excluded too, or a later
			// 100% Refund hands back money that was already forfeited. Counted from the row's own
			// "deleted" label ("Adult person 2", same parsing the history card uses), falling back to
			// one pax per row, same as TicketRefundCancelService.
			$dontRefundPaxCount = 0;
			// Partner fee getNetPartnerFees() already excluded for those rows (refund_partner_fees_keep_us).
			$dontRefundPartnerFeesAlreadyNetted = 0;
			// A pax removed while the booking was still unpaid was never in this charge (the
			// payment was sized for the pax left), so only rows recorded after it count.
			$transactionPaymentHistoryId = TourBookingHistory::tourPaymentHistoryId($id, $transaction->id);
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				if ($value->payment_method == "Don't Refund") {
					$refTotal += $details->amount;
				}
				if ($value->payment_method == "Don't Refund" && !TourBookingHistory::isRecordedBeforePayment($value, $transactionPaymentHistoryId)) {
					$rowPaxCount = 0;
					$deletedRaw = $details->deleted ?? null;
					$deletedStr = is_array($deletedRaw) ? implode(', ', $deletedRaw) : (is_string($deletedRaw) ? $deletedRaw : '');
					foreach (preg_split('/\s*,\s*/', trim($deletedStr)) ?: [] as $segment) {
						if (trim($segment) === '') continue;
						$rowPaxCount += preg_match('/(?:person|Person\(s\))\s+(\d+)$/i', trim($segment), $m) ? max(1, (int) $m[1]) : 1;
					}
					$dontRefundPaxCount += max(1, $rowPaxCount);
					$dontRefundPartnerFeesAlreadyNetted += (float) ($details->refund_partner_fees_keep_us ?? 0);
				}
				$ref += $details->amount;
				if ($value->payment_method == 'Full Refund minus Fees' && isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
					$alreadyKeptFee += $details->refund_keep_us;
				}
			}
			$ref -= $refTotal;

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
				$netPartnerFees = 0;
				$cardsArray = array();
				$partialAmountArray = array();
				$transactionIdArray = array();

				foreach ($transGet as $value) {
					$refundtrans = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $value->transaction_number)
						->where('status', 'Refund')
						->where(function ($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->count();

					$histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees', 'partner_fees']);
					if (!empty($histPaymentDetails)) {
						$cardsArray[] = $histPaymentDetails->card_last_4_digit;
					}

					if ($refundtrans == 0) {
						$net_amount = $value->amount;

						//Calculate ticket cost without partner fees to reverse from partner account
						$remainingTicketCost = $histPaymentDetails->ticket_cost - $histPaymentDetails->partner_fees;
						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
							ThreeWayPaymentService::reverseTransfer($id, $value->id, $remainingTicketCost);
						}

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
						$Transaction = CancelTransactionUpdateService::run($id, $net_amount, $value->transaction_number, $value->fee_amount, $value->application_fee_id, $refundVia, $transaction, $request, mode: $value->payment_mode);
						//Below line is for inserting reverse transfer in payout table
						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
							$netPartnerFees += $histPaymentDetails->partner_fees;
							ThreeWayPaymentService::storeReverseTransferInPayout($id, $value->id, $Transaction->id, $remainingTicketCost, $histPaymentDetails->partner_fees);
						}
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
								
								//Calculate ticket cost without partner fees to reverse from partner account
								$netPayoutData = ($tourBooking->is_partner_booking == 1) ? ThreeWayPaymentService::getNetPartnerAmountsForTransaction($id, $value->transaction_number) : 0;
								$netPartnerFeesForTransaction = ($tourBooking->is_partner_booking == 1) ? $netPayoutData['netPartnerCommission'] : 0.00;
								$remainingTicketCostForTransaction = ($tourBooking->is_partner_booking == 1) ? $netPayoutData['netTicketCost'] : 0.00;
								if ($tourBooking->is_partner_booking == 1 && $remainingTicketCostForTransaction > 0) {
									ThreeWayPaymentService::reverseTransfer($id, $value->id, $remainingTicketCostForTransaction);
								}
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
								$Transaction = CancelTransactionUpdateService::run($id, $net_amount, $value->transaction_number, round($net_amount * $tourBookingTransaction->stripe_fees_percentage / 100, 2), $value->application_fee_id, $refundVia, $transaction, $request, mode: $value->payment_mode);
								//Below line is for inserting reverse transfer in payout table
								if ($tourBooking->is_partner_booking == 1 && $remainingTicketCostForTransaction > 0) {
									ThreeWayPaymentService::storeReverseTransferInPayout($id, $value->id, $Transaction->id, $remainingTicketCostForTransaction, $netPartnerFeesForTransaction);
								}
								$partialAmountArray[] = $net_amount;
								$transactionIdArray[] = $Transaction->id;

								$net_amount_split += $net_amount;
								$netPartnerFees += $netPartnerFeesForTransaction;
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
									// $stripeAmount = $net_amount + $addonTotal;
									app(PaymentGatewayInterface::class)->refundPayment($refundParams, $value->payment_mode);
								}

								$stripe_commission = $tourBookingTransaction->stripe_fees_percentage;
								$fee = roundout(($tourBooking->booking_total * $stripe_commission) / 100, 2);
								$fee_amount = ((float)$net_amount <= $fee) ? 0.00 : roundout($net_amount * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
								// Below line is for to update transactions table
								$Transaction = CancelTransactionUpdateService::run($id, $net_amount, $value->transaction_number, $fee_amount, $value->application_fee_id, $refundVia, $transaction, $request, mode: $value->payment_mode);

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
						'operation' => 'Cancelled',
						'details' => json_encode([
							'amount' => $net_amount_split,
							'refund_keep_us' => $refund_keep_us,
							'partner_fees' => $netPartnerFees,
							'payment_type' => $refundVia,
							'created_by_with_name' => $createdByWithName,
							'refund_type' => '100 % Refund',
							'cards' => $cardsArray,
							'refund_amounts' => $partialAmountArray,
							'note' => $request->comment,
							'transactionIdArray' => $transactionIdArray,
						], JSON_NUMERIC_CHECK),
						'operation_at' => mysqlDT(),
						'operation_by' => Auth::id(),
						'operation_by_user_type' => $userType,
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);
				DB::commit();

				DetectPayoutAdjustment::dispatch($id, $historyId, 'Cancelled');

				// To pass in email
				$refundedAmount = $net_amount_split;
			} else {
				// Use the actual ticket_cost and platform_fees from the payment history for this
				// transaction. $tourBooking->total may have been updated when an unpaid person was
				// added via Edit Booking, which would cause the refund to exceed what was charged.
				$singleTransHistory = TourBookingHistory::where('transaction_id', $transaction->id)
					->where('operation', 'Payment')
					->first(['ticket_cost', 'platform_fees', 'tourists']);
				$transTicketCost = $singleTransHistory ? (float) $singleTransHistory->ticket_cost : (float) $tourBooking->total;
				$transServiceCommission = $singleTransHistory ? (float) $singleTransHistory->platform_fees : (float) $tourBooking->service_commission;
				// 100% refund returns the full amount charged: ticket cost + platform fees, minus
				// whatever's already been refunded by an earlier partial refund (e.g. Remove Pax) --
				// $ref tracks that already-refunded total. Without netting it out here, a 100%
				// refund after a prior partial refund re-refunds money the customer already got
				// back, exceeding what's left unrefunded on the Stripe charge. $alreadyKeptFee is
				// also subtracted so a fee already kept as revenue by an earlier "Ticket Cost"
				// removal isn't handed back a second time.
				$transFullAmount = $transTicketCost + $transServiceCommission - $ref - $alreadyKeptFee;

				// Exclude each Don't-Refund-removed pax's average share of this transaction's ticket
				// cost + platform fee (see $dontRefundPaxCount above). The fee share is tracked
				// separately so the ticket/fee split recorded below stays consistent with the amount.
				$dontRefundKeptFee = 0;
				$paidTouristsAtPayment = $singleTransHistory ? (int) $singleTransHistory->tourists : 0;
				if ($dontRefundPaxCount > 0 && $paidTouristsAtPayment > 0) {
					$excludedPax = min($dontRefundPaxCount, $paidTouristsAtPayment);
					$dontRefundKeptFee = roundout(($transServiceCommission / $paidTouristsAtPayment) * $excludedPax, 2);
					$dontRefundKeptTicket = roundout(($transTicketCost / $paidTouristsAtPayment) * $excludedPax, 2);
					$transFullAmount = max(0, round($transFullAmount - $dontRefundKeptTicket - $dontRefundKeptFee, 2));
				}

				$remainingTicketCost = 0;
				if ($tourBooking->is_partner_booking == 1) {
					//Calculate ticket cost without partner fees to reverse from partner account
					$stripeCommission = $tourBookingTransaction->stripe_fees_percentage;
					$feeAmount = roundout(($transTicketCost * $stripeCommission) / 100, 2);
					//below line is for to get net remaining ticket cost of transaction which transferred to partner
					$remainingPartnerAmounts = ThreeWayPaymentService::getNetPartnerAmountsForTransaction($tourBooking->id,$transaction->transaction_number);//$tourBooking->total - $feeAmount - $netPartnerFees;
					$remainingTicketCost = $remainingPartnerAmounts['netTicketCost'];

					// A "Don't Refund" pax removal never reverses the partner transfer (no Reversed payout
					// row is written for it) -- the partner keeps that pax's payout and NAT keeps its
					// partner fee. So the netTicketCost/netPartnerFees above still include the removed
					// pax; exclude their per-pax share of this transaction's original payout here, or
					// the cancellation reverses (and records in payouts) money that isn't being refunded.
					if ($dontRefundPaxCount > 0 && $paidTouristsAtPayment > 0) {
						$originalPayout = DB::table('payouts')
							->where('tour_booking_id', $id)
							->where('transaction_id', $transaction->id)
							->where('payout_status', 'Completed')
							->first(['payout_amount', 'commission_amount']);
						if ($originalPayout) {
							$excludedPax = min($dontRefundPaxCount, $paidTouristsAtPayment);
							$keptPayout = roundout(((float) $originalPayout->payout_amount / $paidTouristsAtPayment) * $excludedPax, 2);
							$keptCommission = roundout(((float) $originalPayout->commission_amount / $paidTouristsAtPayment) * $excludedPax, 2);
							$remainingTicketCost = max(0, round($remainingTicketCost - $keptPayout, 2));
							$netPartnerFees = max(0, round($netPartnerFees - max(0, $keptCommission - $dontRefundPartnerFeesAlreadyNetted), 2));
						}
					}
				}
				if ((float)$tourBooking->booking_total != (float)$ref) {
					if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
						ThreeWayPaymentService::reverseTransfer($id, $transaction->id, $remainingTicketCost);
					}
					$refundParams = [
						'payment_intent' => $transaction->transaction_number,
						'amount' => $transFullAmount * 100,
						'reverse_transfer' => true,
						'refund_application_fee' => true,
					];
					if ($transFullAmount > 0) {
						app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
					}
					// Pass the platform fee as fee_amount so Cancel100RefundService stores
					// ticket_cost = $transTicketCost and platform_fees = $transServiceCommission
					// (ticket_cost = amount - fee_amount in that service). Net out $alreadyKeptFee
					// here too so that split stays consistent with $transFullAmount above --
					// otherwise the recorded ticket_cost would be short by exactly the fee amount
					// this record isn't actually refunding.
					$serviceComission = max(0, $transServiceCommission - $alreadyKeptFee - $dontRefundKeptFee);

				} else {
					$serviceComission = 0.00;
				}

				$refundAmount = (float)$tourBooking->booking_total == (float)$ref ? 0.00 : $transFullAmount;

				// Below line is for to update transactions table
				$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $transaction->transaction_number, $serviceComission, $transaction->application_fee_id, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
				$refund_keep_us = 0;

				//Below line is for inserting reverse transfer in payout table
				if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
					ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
				}
				// Below line is for to update tour booking history table
				$paidTourists = $singleTransHistory ? (int) $singleTransHistory->tourists : null;
				$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $refundAmount, $refund_keep_us, $refundVia, $createdByWithName, $request, "100 % Refund", $userType, $netPartnerFees, $paidTourists);
				DB::commit();

				// To pass in email
				$refundedAmount = $refundAmount;
			}

			(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_FULL_REFUND);
			// Refund Custom Charge
			CustomChargeBookingService::refundCustomChargeFromCancel($tourBooking->id, $request);

			return [
				'historyId' => $historyId,
				'via' => $Transaction->via,
				'transaction_id' => $Transaction->id,
				'refundedAmount' => $refundedAmount
			];
		}
	}

	/**
	 * Calculate the total cost of the addons added in the booking
	 * 
	 * @param int $id
	 * @return mixed
	 */
	public static function calculateAddonTotalCost($id): mixed
	{
		$addonTotal = 0;
		$tourBookingFormRepository = new TourBookingFormsRepository();
		$bookingForm = $tourBookingFormRepository->getBookingForm(null, $id);
		if (isset($bookingForm->form_submission) && !empty($bookingForm->form_submission)) {
			$fields = json_decode($bookingForm->form_submission, true);
			foreach ($fields as $key => $field) {
				if ($field['priceInfo']['enabled'] == 'true') {
					$addonTotal += $field['priceInfo']['price'] + $field['priceInfo']['fee'];
				}
			}
		}

		return $addonTotal;
	}
}
