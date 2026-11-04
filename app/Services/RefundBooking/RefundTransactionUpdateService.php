<?php

namespace App\Services\RefundBooking;

use App\Constants\Constants;
use App\Models\Transaction;
use App\Services\Payments\PaymentModeResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RefundTransactionUpdateService
{
    /**
     * Execute the service for Card for history table update.
     *
     * @return mixed
     */
    public static function run($id, $net_amount = 0, $transaction_number = null, $fee_amount = 0, $application_fee_id = null, $refundVia, $transaction, $request)
    {
		$Transaction = new Transaction;
		$Transaction->tour_booking_id = $id;
		$Transaction->amount = $net_amount;
		$Transaction->fee_amount = $fee_amount;
		$Transaction->application_fee_id = $application_fee_id;
		$Transaction->transaction_number = $transaction_number;
		$Transaction->payment_mode = self::paymentModeFor($id, $transaction_number);
		$Transaction->via = $refundVia;
		$Transaction->response_init = '';
		$Transaction->response_end = '';
		$Transaction->response_end_datetime = Carbon::now();
		$Transaction->status = 'Refund';
		$Transaction->visitor = $request->getClientIp();
		$Transaction->request_from = $request->is('api/*') ? Constants::API : Constants::BACKEND;
		$Transaction->save();

		return $Transaction;
    }

	/**
	 * A refund is recorded in the same settlement mode as the payment it refunds. Card refunds always
	 * carry that payment's Stripe PaymentIntent, so the mode is read from the original payment row;
	 * cash, invoice and wire refunds have no PaymentIntent and keep the destination default. Without
	 * this, a refund of 2027 platform-held money was saved as 'destination'.
	 */
	private static function paymentModeFor($bookingId, ?string $transactionNumber): string
	{
		if (empty($transactionNumber)) {
			return PaymentModeResolver::DESTINATION;
		}

		// Narrowed by booking first (indexed): the refunded payment always belongs to this booking.
		$modes = DB::table('transactions')
			->where('tour_booking_id', $bookingId)
			->where('transaction_number', $transactionNumber)
			->where('status', 'Completed')
			->distinct()
			->pluck('payment_mode');

		return $modes->contains(PaymentModeResolver::PLATFORM_HELD)
			? PaymentModeResolver::PLATFORM_HELD
			: PaymentModeResolver::DESTINATION;
	}
}
