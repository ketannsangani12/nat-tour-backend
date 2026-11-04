<?php

namespace App\Services\CancelBooking;

use App\Constants\Constants;
use App\Models\Transaction;
use Carbon\Carbon;

class CancelTransactionUpdateService
{
	/**
	 * Execute the service for Card for history table update.
	 *
	 * $mode is required, with no default: this writes the ledger row for a cancellation refund,
	 * and every caller already has the real underlying Transaction (or the specific one actually
	 * refunded via Stripe in this call, for multi-transaction loops) in scope with its own
	 * persisted payment_mode — silently defaulting here is exactly the class of bug that lets a
	 * platform_held booking's cancellation get recorded as 'destination'.
	 *
	 * @return mixed
	 */
	public static function run($id, $netAmount = 0, $transactionNumber = null, $feeAmount = 0, $applicationFeeId = null, $refundVia, $transaction, $request, string $mode)
	{
		// fee_amount is always a real number (0 when no fee was refunded in this transaction) --
		// most cancellation refund types (Ticket Refund, Don't Refund, Partial Refund) deliberately
		// keep the platform fee, so callers pass null or 0 for those, normalized to 0 below.
		// application_fee_id references a real Stripe application fee object, so it stays null
		// unless a genuine non-zero fee was actually refunded here.
		$feeAmountToStore = (float) ($feeAmount ?? 0);
		$applicationFeeIdToStore = ($feeAmountToStore > 0 && $applicationFeeId !== '') ? $applicationFeeId : null;

		$Transaction = new Transaction;
		$Transaction->tour_booking_id = $id;
		$Transaction->amount = $netAmount;
		$Transaction->fee_amount = $feeAmountToStore;
		$Transaction->application_fee_id = $applicationFeeIdToStore;
		$Transaction->transaction_number = $transactionNumber;
		$Transaction->via = $refundVia;
		$Transaction->payment_mode = $mode;
		$Transaction->response_init = '';
		$Transaction->response_end = '';
		$Transaction->response_end_datetime = Carbon::now();
		$Transaction->status = 'Refund';
		$Transaction->visitor = $request->getClientIp();
		$Transaction->request_from = $request->is('api/*') ? Constants::API : Constants::BACKEND;
		$Transaction->save();

		return $Transaction;
	}
}
