<?php

namespace App\Services\CancelBooking;

use App\Jobs\DetectPayoutAdjustment;
use App\Models\Transaction;
use App\Models\TourBookingHistory;
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

class TicketRefundCancelService
{
	/**
	 * Execute the service Cancel Booking With Cash/Invoiced/Wire Transfer.
	 *
	 * @return mixed
	 */
	public static function cancel($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType)
	{
		$tourBookingFormRepository = new TourBookingFormsRepository();
		$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'asc')->first();
		$PaymentHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->whereNull('charge_type')->get();
		// $RefundHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->whereNull('charge_type')->get();
		// Unpaid split reallocations are not refunds of any payment; see isUnpaidSplitReallocation().
		$RefundHistory = TourBookingHistory::withoutUnpaidSplitReallocations(TourBookingHistory::where('tour_booking_id', $id)
			->where('operation', 'Refund')
			->where(function ($query) {
				$query->whereNull('charge_type')
					->orWhere('charge_type', 'discount');
			})
			->get());

		$isCashFees = cashFees();
		$isPartnerBooking = $tourBooking->is_partner_booking;
		$netPartnerFees = ($isPartnerBooking == 1) ? ThreeWayPaymentService::getNetPartnerFees($id) : 0.00;
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

			$net_amount = $tourBooking->total;
			$refund_keep_us = 0;
			$refund_type = "fareharbor : Full Refund";

			$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
			DB::commit();

			// Below line is for to update history table as per cancel booking refund option
			$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, $refund_type, $userType);
			DB::commit();

			// To pass in email
			$refundedAmount = $Transaction->amount;

			// Below line is for to update tour booking history table
			// $this->handleCancelRefundHistoryUpdateJobs($tourBooking, $historyId, $request->all(), $refundVia, $Transaction->id);
		}

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

			// Scoped to ticket-side refunds only (NULL/'discount' charge_type) — same scoping
			// WeatherRefundCancelService's equivalent computation already uses, and this class's
			// OWN top-of-function $RefundHistory already used, just above. Without this, a
			// PRIOR add-on refund (charge_type='addons', e.g. from an earlier pax removal) got
			// summed into $ref right alongside genuine ticket refunds, so the ticket-side amount
			// still owed here came out short by however much unrelated add-on money had already
			// been refunded — under-refunding the ticket cost at cancel time.
			$RefundHistory = TourBookingHistory::withoutUnpaidSplitReallocations(TourBookingHistory::where('tour_booking_id', $id)
				->where('operation', 'Refund')
				->where(function ($query) {
					$query->whereNull('charge_type')
						->orWhere('charge_type', 'discount');
				})
				->get());

			$dontRefundPaxCount = 0;
			// Pax removed before the first payment were never part of the paid totals below.
			$firstPaymentHistoryId = TourBookingHistory::tourPaymentHistoryId($id);
			foreach ($RefundHistory as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				if ($value->payment_method == "Don't Refund") {
					$dontrefundamount += $details->amount;
				}
				if ($value->payment_method == "Don't Refund" && !TourBookingHistory::isRecordedBeforePayment($value, $firstPaymentHistoryId)) {
					// A "Don't Refund" pax removal keeps that pax's ticket cost as pure revenue --
					// no money moves, details->amount is zeroed, so it never shows up above. But the
					// pax IS gone from the booking, so their average per-pax share of the ORIGINAL,
					// never-decremented $ticketCostAmount/$net_amount_split totals (computed below)
					// must also be excluded from what's still refundable at cancellation, or a later
					// full cancel hands back money for a pax whose cost was already forfeited.
					$dontRefundPaxCount++;
				}
				if ($isCashFees == '1' && isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees') {
					if (isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
						$oldRefundKeepUs += $details->refund_keep_us;
					}
				}
			}
			$ref = $ref - $dontrefundamount;
			$paidTouristsTotal = 0;
			foreach ($PaymentHistory as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
				$fees += $value->platform_fees;
				$ticketCostAmount += $value->ticket_cost;
				$platformFeesAmount += $value->platform_fees;
				$paidTouristsTotal += (int) $value->tourists;
			}

			// Each Don't-Refund-removed pax's average per-pax share of the original totals --
			// ticket-cost-only ($ticketCostAmount) and fee-inclusive ($net_amount_split) bases are
			// tracked separately since the formulas below use each in different combinations.
			$dontRefundForfeitedTicketCost = 0;
			$dontRefundForfeitedGrossAmount = 0;
			if ($dontRefundPaxCount > 0 && $paidTouristsTotal > 0) {
				$dontRefundForfeitedTicketCost = ($ticketCostAmount / $paidTouristsTotal) * $dontRefundPaxCount;
				$dontRefundForfeitedGrossAmount = ($net_amount_split / $paidTouristsTotal) * $dontRefundPaxCount;
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

			// The admin explicitly overrode this booking to a manual Cash/Check-Wire settlement
			// (e.g. it's really a Card booking, but they're reimbursing by hand) — add-ons that were
			// originally charged on Stripe must follow the same override instead of hitting the card,
			// same as the tour-side ticket refund above already does via $refundVia. Null (no
			// override submitted) means every add-on refunds by its own real payment method, as before.
			$addonManualRefundVia = (isset($request->is_cash) && $request->is_cash != '') ? $refundVia : null;

			if ($request->fareharbor !== "fareharbor") {
				if ($isCashFees == '1' && $trans->via == "Cash") {
					$refundVia = "Cash";
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->count();
					if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "full_refund") {

						$net_amount = $ticketCostAmount - $ref - $dontrefundamount - $dontRefundForfeitedTicketCost;
						$refund_keep_us = $platformFeesAmount;

						$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);

						$remainingTicketCost = $net_amount - $netPartnerFees;

						//Below line is for inserting reverse transfer in payout table
						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
							ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
						}

						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Full Refund", $userType, $netPartnerFees);
						DB::commit();

						$refundedAmount = $net_amount;
					} else {
						$net_amount_data = $tourBooking->total + $oldRefundKeepUs - $tourBooking->service_commission;
						$net_amount = ((float)$ref == (float)$tourBooking->booking_total) ? 0.00 :  $net_amount_data;
						$remainingTicketCost = $net_amount - $netPartnerFees;

						$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);

						//Below line is for inserting reverse transfer in payout table
						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
							ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
						}
						$refund_keep_us = $tourBooking->service_commission - $oldRefundKeepUs;
						// Below line is for to update tour booking history table
						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Full Refund", $userType, $netPartnerFees);
						DB::commit();
						// To pass in email
						$refundedAmount = $net_amount;
					}
				} else {
					// $net_amount = $net_amount_split - $ref;
					// When the ORIGINAL transaction was really Card (whether refunding it directly
					// or the admin manually overrode it to Cash/Check-Wire), the platform/Stripe fee
					// already collected on that payment is kept back, not refunded — $net_amount
					// above already nets it out of the refunded amount. $refund_keep_us must still
					// reflect that SAME $fees value (matching the sibling "genuinely Cash + cashFees
					// policy" branch's $platformFeesAmount) for reporting, even though this
					// cancellation never touches Stripe. But transactions.fee_amount/application_fee_id
					// describe THIS transaction's own fee refund, and a Ticket Refund never refunds
					// the fee (it's a "keep the fee" refund type by design) -- so those two columns
					// stay null regardless of the original payment method, unlike $refund_keep_us.
					// A prior Don't-Refund pax removal's forfeited share must also come out: the
					// Card-override sub-case already excludes ALL pax's fees via $fees above, so only
					// that pax's ticket-cost-only share needs subtracting on top (subtracting the
					// fee-inclusive share too would remove its fee a second time); the genuine-Cash
					// sub-case has no separate fee exclusion, so it needs that pax's full,
					// fee-inclusive share instead.
					$net_amount = ($trans->via == "Card") ? ($net_amount_split - $ref - $fees - $dontRefundForfeitedTicketCost) : ($net_amount_split - $ref - $dontRefundForfeitedGrossAmount);
					$refund_keep_us = ($trans->via == "Card") ? $fees : 0;
					$refund_type = "Full Refund";
					$refundData = ((float)$ref == (float)$tourBooking->booking_total) ? 0.00 :  $net_amount;
					$Transaction = CancelTransactionUpdateService::run($id, $net_amount, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
					DB::commit();

					$remainingTicketCost = $net_amount - $netPartnerFees;

					if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
						ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
					}
					// To pass in email
					$refundedAmount = $refundData;
					// Below line is for to update history table as per cancel booking refund option
					$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, $refund_type, $userType, $netPartnerFees);
					DB::commit();
				}

				// Addon charges were only ever being refunded on the Card branch below;
				// Cash/Invoiced/Wire Transfer bookings need the same full addon refund.
				(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_TICKET_REFUND, null, null, $addonManualRefundVia);

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

		// Ticket Refund for Card Payment
		if ($transaction->via == "Card" && $request->fareharbor != "fareharbor") {
			$refundVia = "Card";
			// $addonTotal = static::calculateAddonTicketCost($id);
			$dontrefundamount = 0;
			$ref = 0;
			$paymentTransCount = Transaction::where('tour_booking_id', $id)
				->where('status', 'Completed')
				->where(function ($query) {
					$query->whereNull('charge_type')
						->orWhere('charge_type', 'discount');
				})
				->count();

			$net_amount_split = 0;
			if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "full_refund") {
				$trans = Transaction::where('tour_booking_id', $id)
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

				foreach ($trans as $value) {
					$histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees', 'tourists']);
					if (!empty($histPaymentDetails)) {
						$cardsArray[] = $histPaymentDetails->card_last_4_digit;
					}

					$refundedTransForTransaction = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $value->transaction_number)
						->where('status', 'Refund')
						->where(function ($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->get(['id', 'amount', 'charge_type', 'fee_amount']);

					$refundedTransForTransactionZero = Transaction::where('tour_booking_id', $id)
						->where('transaction_number', $value->transaction_number)
						->where('status', 'Refund')
						->where('amount', 0)
						->where(function ($query) {
							$query->whereNull('charge_type')
								->orWhere('charge_type', 'discount');
						})
						->get(['id', 'amount']);

					// The $0 Refund transaction rows above are each a single "Don't Refund" pax
					// removal against THIS transaction_number -- no money moved, and the linked
					// history row's details->amount is genuinely 0 (nothing to sum there). But that
					// removed pax's ticket cost is still baked into $histPaymentDetails->ticket_cost
					// (the ORIGINAL, never-decremented total for this transaction), so it must be
					// excluded here too, using each row's average per-pax share of that original
					// total -- not a details JSON ticket/fee split, which isn't reliably populated
					// for every booking type (e.g. group-rate removals).
					$dontrefundamount = 0;
					$dontRefundPaxCountForTransaction = $refundedTransForTransactionZero->count();
					if ($dontRefundPaxCountForTransaction > 0 && !empty($histPaymentDetails) && (int) $histPaymentDetails->tourists > 0) {
						$dontrefundamount = ((float) $histPaymentDetails->ticket_cost / (int) $histPaymentDetails->tourists) * $dontRefundPaxCountForTransaction;
					}

					$totalrefundedamountfortransaction = 0;
					$totalrefundedfeesfortransaction = 0;

					foreach ($refundedTransForTransaction as $ky => $val) {
						$totalrefundedamountfortransaction += $val->amount;
						$totalrefundedfeesfortransaction += $val->fee_amount;
					}

					// $totalrefundedamountfortransaction (transactions.amount) is fee-inclusive for a
					// prior '100 % Refund' (Weather) pax removal against this same transaction_number
					// -- it refunded ticket cost AND platform fee together as one Stripe refund. Netting
					// that straight against $histPaymentDetails->ticket_cost (a pure ticket-cost basis)
					// double-subtracts the fee, so only the ticket-cost portion (amount minus the fee
					// already tracked in fee_amount) belongs here.
					$net_amount = $histPaymentDetails->ticket_cost - ($totalrefundedamountfortransaction - $totalrefundedfeesfortransaction) - $dontrefundamount;
					$platformfees = $histPaymentDetails->platform_fees - $totalrefundedfeesfortransaction;

					if ($net_amount > 0) {
						//Calculate ticket cost without partner fees to reverse from partner account
						$netPayoutData = ($tourBooking->is_partner_booking == 1) ? ThreeWayPaymentService::getNetPartnerAmountsForTransaction($id, $value->transaction_number) : 0;
						$netPartnerFeesForTransaction = ($tourBooking->is_partner_booking == 1) ? $netPayoutData['netPartnerCommission'] : 0.00;
						$remainingTicketCostForTransaction = ($tourBooking->is_partner_booking == 1) ? $netPayoutData['netTicketCost'] : 0.00;

						if ($tourBooking->is_partner_booking == 1 && $remainingTicketCostForTransaction > 0) {
							ThreeWayPaymentService::reverseTransfer($id, $value->id, $remainingTicketCostForTransaction);
						}
						if ($value->transaction_number != '' && $net_amount > 0) {
							$refundParams = [
								'payment_intent' => $value->transaction_number,
								'amount' => $net_amount * 100,
								'reverse_transfer' => true,
							];
							app(PaymentGatewayInterface::class)->refundPayment($refundParams, $value->payment_mode);
						}

						// Below line is for to update transactions table
						// $Transaction = CancelTransactionUpdateService::run($id, $net_amount, $value->transaction_number, null, null, $refundVia, $transaction, $request);
						// $partialAmountArray[] = $net_amount;

						// $net_amount_split += $net_amount;
						// $refund_keep_us += $platformfees;
					} else {
						$net_amount = 0;
						$remainingTicketCostForTransaction = 0;
						$netPartnerFeesForTransaction = 0;
					}

					$Transaction = CancelTransactionUpdateService::run($id, $net_amount, $value->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $value->payment_mode);
					//Below line is for inserting reverse transfer in payout table
					if ($tourBooking->is_partner_booking == 1 &&  $remainingTicketCostForTransaction > 0) {
						ThreeWayPaymentService::storeReverseTransferInPayout($id, $value->id, $Transaction->id, $remainingTicketCostForTransaction, $netPartnerFeesForTransaction);
					}
					$partialAmountArray[] = $net_amount;
					$net_amount_split += $net_amount;
					$refund_keep_us += $platformfees;
					$netPartnerFees += $netPartnerFeesForTransaction;
				}

				$historyId = DB::table('tour_booking_history')
					->insertGetId([
						'tour_booking_id' => $id,
						'tourists' => $tourBooking->tourists + $tourBooking->infants,
						'operation' => 'Cancelled',
						'details' => json_encode([
							'amount' => $net_amount_split,
							'refund_keep_us' => $refund_keep_us,
							'partner_fees' => $netPartnerFees ?? 0.00,
							'payment_type' => 'Card',
							'created_by_with_name' => $created_by_with_name,
							'refund_type' => 'Full Refund',
							'cards' => $cardsArray,
							'refund_amounts' => $partialAmountArray,
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

				// To pass in email
				$refundedAmount = $net_amount_split;
			} else {
				$oldRefundKeepUs = 0;
				$dontRefundPaxCount = 0;
				// A pax removed while the booking was still unpaid was never in this charge (the
				// payment was sized for the pax left), so only rows recorded after it count.
				$transactionPaymentHistoryId = TourBookingHistory::tourPaymentHistoryId($id, $trans->id);

				foreach ($RefundHistory as $key => $value) {
					$details = json_decode($value->details);
					$isDontRefundBeforePayment = $value->payment_method == "Don't Refund"
						&& TourBookingHistory::isRecordedBeforePayment($value, $transactionPaymentHistoryId);
					if (!$isDontRefundBeforePayment && (!isset($details->from) || $details->from != 'Extend Booking')) {
						if (isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees' && isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
							$oldRefundKeepUs += $details->refund_keep_us;
						}

						if (isset($details->refund_type) && $value->charge_type == null) {
							if ($details->refund_type == '100 % Refund') {
								$oldRefundKeepUs += $value->platform_fees;
							}
							if ($details->refund_type == 'Don\'t Refund') {
								$oldRefundKeepUs += $details->fees;
							}
						}
					}

					//refund cancel case
					// A '100 % Refund' (Weather) prior refund's details->amount bundles the ticket
					// cost together with the platform fee it also refunded (platform_fees column
					// already records that fee correctly/separately). $transTicketCost below is a
					// pure ticket-cost basis, so only the ticket-cost portion of this prior refund
					// should count against it here — otherwise the fee gets subtracted twice: once
					// because it was actually refunded, and again here from a fee-less basis.
					$refundTicketAmount = (float) $details->amount;
					if (isset($details->refund_type) && $details->refund_type == '100 % Refund') {
						$refundTicketAmount -= (float) $value->platform_fees;
					}
					$ref += $refundTicketAmount;
					if ($value->payment_method == "Don't Refund") {
						$dontrefundamount += $details->amount;
						// A "Don't Refund" pax removal keeps that pax's ticket cost as pure revenue --
						// no money moves, details->amount is zeroed, so $ref above never counts it. But
						// the pax IS gone from the booking, so their slice of $transTicketCost (below --
						// the ORIGINAL, never-decremented per-transaction total) must also be excluded
						// here, or a later full cancel hands back money for a pax whose cost was already
						// forfeited. Counted per-row rather than read from a ticket/fee JSON split, since
						// that split isn't reliably populated for every booking type (e.g. group-rate
						// removals never populate details->fees at all).
						if (!$isDontRefundBeforePayment) {
							$dontRefundPaxCount++;
						}
					}
				}

				// Use the actual amounts from the payment history record for this transaction
				// rather than $tourBooking->total, which may have been updated when an unpaid
				// person was added via Edit Booking — causing the refund to exceed what was charged.
				// In tour_booking_history: ticket_cost = ticket only (no NAT fee), platform_fees = NAT fee.
				// The original formula was: total - service_commission = (ticket+fee) - fee = ticket.
				// So net_amount for a Ticket Refund (keeps fees) = ticket_cost = $transTicketCost.
				$singleTransHistory = TourBookingHistory::where('transaction_id', $trans->id)
					->where('operation', 'Payment')
					->first(['ticket_cost', 'platform_fees', 'tourists']);
				$transTicketCost = $singleTransHistory ? (float) $singleTransHistory->ticket_cost : ((float) $tourBooking->total - (float) $tourBooking->service_commission);
				$transServiceCommission = $singleTransHistory ? (float) $singleTransHistory->platform_fees : (float) $tourBooking->service_commission;

				// Exclude each Don't-Refund-removed pax's average share of the original ticket
				// cost from what's still refundable at cancellation (see comment above).
				$paidTouristsAtPayment = $singleTransHistory ? (int) $singleTransHistory->tourists : 0;
				if ($dontRefundPaxCount > 0 && $paidTouristsAtPayment > 0) {
					$ref += ($transTicketCost / $paidTouristsAtPayment) * $dontRefundPaxCount;
				}

				$ref = $ref - $dontrefundamount;
				// $transTicketCost is this transaction's ORIGINAL ticket cost (from its first Payment
				// history row) and doesn't reflect any ticket cost already sent back via an earlier
				// partial refund (e.g. Remove Pax) — $ref already tracks that already-refunded total,
				// so it must be netted out here or a full cancel re-refunds money the customer already
				// got back, exceeding what's left unrefunded on the Stripe charge.
				// $oldRefundKeepUs must NOT be added here: it's the platform fee an earlier partial
				// refund recorded as kept (never charged as its own transaction, never refunded), used
				// below purely for the $refund_keep_us reporting figure. Adding it to $net_amount would
				// hand the customer cash for a fee they were never separately charged for.
				$net_amount = $transTicketCost - $ref;
				$remainingTicketCost = 0;
				if ($tourBooking->is_partner_booking == 1) {
					$remainingTicketCost = $net_amount - $netPartnerFees;
				}
				if ((float)$tourBooking->booking_total != ((float)$ref)) {
					if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
						ThreeWayPaymentService::reverseTransfer($id, $transaction->id, $remainingTicketCost);
					}
					$refundParams = [
						'payment_intent' => $transaction->transaction_number,
						'amount' => $net_amount * 100,
						'reverse_transfer' => true,
					];
					// $stripeAmount = $net_amount + $addonTotal;
					$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
				}

				$refundAmount = (float)$tourBooking->booking_total == ((float)$ref) ? 0.00 : $net_amount;
				// Below line is for to update transactions table
				$Transaction = CancelTransactionUpdateService::run($id, $refundAmount, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
				$refund_keep_us = $transServiceCommission - $oldRefundKeepUs;

				//Below line is for inserting reverse transfer in payout table
				if ($tourBooking->is_partner_booking == 1 && $remainingTicketCost > 0) {
					ThreeWayPaymentService::storeReverseTransferInPayout($id, $transaction->id, $Transaction->id, $remainingTicketCost, $netPartnerFees);
				}

				// Below line is for to update tour booking history table
				$paidTourists = $singleTransHistory ? (int) $singleTransHistory->tourists : null;
				$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $refundAmount, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Full Refund", $userType, $netPartnerFees, $paidTourists);
				DB::commit();

				// To pass in email
				$refundedAmount = $refundAmount;
			}

			(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_TICKET_REFUND);
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
	 * Calculate the ticket cost of the addons added in the booking
	 * 
	 * @param int $id
	 * @return mixed
	 */
	public static function calculateAddonTicketCost($id): mixed
	{
		$addonPrice = 0;
		$tourBookingFormRepository = new TourBookingFormsRepository();
		$bookingForm = $tourBookingFormRepository->getBookingForm(null, $id);
		if (isset($bookingForm->form_submission) && !empty($bookingForm->form_submission)) {
			$fields = json_decode($bookingForm->form_submission, true);
			foreach ($fields as $key => $field) {
				if ($field['priceInfo']['enabled'] == 'true') {
					$addonPrice += $field['priceInfo']['price'];
				}
			}
		}

		return $addonPrice;
	}
}
