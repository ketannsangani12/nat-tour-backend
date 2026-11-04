<?php

namespace App\Services\CancelBooking;

use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Constants\Constants;
use Illuminate\Support\Facades\DB;
use App\Services\CancelBooking\CancelHistoryUpdateService;
use App\Services\CancelBooking\CancelTransactionUpdateService;
use App\Services\Booking\CustomChargeBookingService;

class DontRefundCancelService
{
	/**
	 * Execute the service for Cancel Booking With Cash/Invoiced/Wire Transfer.
	 *
	 * @return mixed
	 */
	public static function cancel($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType)
	{
		$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'asc')->first();
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

			$refund_keep_us = 0;
			$net_amount = ($trans->payment_type == 1) ? ($net_amount_split - $ref) : $tourBooking->total;
			$refund_type = "fareharbor : don't refund";

			$Transaction = CancelTransactionUpdateService::run($id, 0, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
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

			// The admin explicitly overrode this booking to a manual Cash/Check-Wire settlement
			// (e.g. it's really a Card booking, but they're settling by hand) — add-ons should be
			// labeled with that same method in history instead of the original card, same as the
			// tour-side ticket refund above already does via $refundVia. Null (no override submitted)
			// means every add-on keeps its own real payment method, as before.
			$addonManualRefundVia = (isset($request->is_cash) && $request->is_cash != '') ? $refundVia : null;

			if ($request->fareharbor !== "fareharbor") {
				if ($isCashFees == '1' && $transaction->via == "Cash") {
					$refundVia = "Cash";
					$paymentTransCount = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->count();

					if (($trans->payment_type == 1 || $paymentTransCount > 1) && $request->refund_type == "dont_refund") {
						$net_amount_split = $net_amount_split - $ref;

						$Transaction = CancelTransactionUpdateService::run($id, 0, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
						$refund_keep_us = $transaction->amount;

						// Below line is for to update tour booking history table
						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount_split, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
						DB::commit();

						// To pass in email
						$refundedAmount = 0;

					} else {
						// Below line is for to update transactions table
						$Transaction = CancelTransactionUpdateService::run($id, 0, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
						$refund_keep_us = $transaction->amount;

						// Below line is for to update tour booking history table
						$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $tourBooking->total, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
						DB::commit();

						// To pass in email
						$refundedAmount = 0;

					}
				} else {
					$Transaction = CancelTransactionUpdateService::run($id, 0, null, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
					DB::commit();

					$net_amount = ($trans->payment_type == 1) ? ($net_amount_split - $ref) : $tourBooking->total;
					$refund_keep_us = 0;
					$refund_type = "don't refund";

					// To pass in email
					$refundedAmount = 0;

					// Below line is for to update history table as per cancel booking refund option
					$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, $refund_type, $userType);
					DB::commit();
				}

				// Addon charges were only ever being settled on the Card branch below;
				// Cash/Invoiced/Wire Transfer bookings need the same "don't refund" addon settlement
				// so their paid addon transactions get closed out in history too.
				(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_DONT_REFUND, null, null, $addonManualRefundVia);

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

				$RefundHistory = TourBookingHistory::withoutUnpaidSplitReallocations(TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Refund')->get());
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
				$Transaction = CancelTransactionUpdateService::run($id, 0, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
				$refund_keep_us = $transaction->amount;

				// Below line is for to update tour booking history table
				$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $net_amount_split, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
				DB::commit();

			} else {
				// Below line is for to update transactions table
				$Transaction = CancelTransactionUpdateService::run($id, 0, $transaction->transaction_number, 0, null, $refundVia, $transaction, $request, mode: $transaction->payment_mode);
				$refund_keep_us = $transaction->amount;

				// Below line is for to update tour booking history table
				$historyId = CancelHistoryUpdateService::run($id, $tourBooking, $tourBooking->total, $refund_keep_us, $refundVia, $created_by_with_name, $request, "Don't Refund", $userType);
				DB::commit();
			}

			(new TourBookingAddonRepository())->refundAddonsByBookingId($tourBooking->id, Constants::ADDONS_DONT_REFUND);
			// Refund Custom Charge
			CustomChargeBookingService::refundCustomChargeFromCancel($tourBooking->id, $request);

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
