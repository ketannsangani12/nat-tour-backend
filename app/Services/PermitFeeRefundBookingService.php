<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\TourBookingHistory;

class PermitFeeRefundBookingService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $transactionId, $historyId, $request, $refundVia)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);
		$oldBookingId = oldBookingId();
        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);
			if($booking->id < $oldBookingId) {
				$customer = Customer::find($booking->customer_id);
				if ($refundVia == "Card") {
					if ($customer->is_affiliate === 'No') {
						$convertedAmount = $history['amount'] / 102.9 * 100;
						$processingFee = $history['amount'] - $convertedAmount;
					} else {
						$convertedAmount = $history['amount'];
						$processingFee = 0;
					}
				} else {
					$convertedAmount = $history['amount'];
					$processingFee = 0;
				}

				$tourBookingHistory->ticket_cost = $convertedAmount;
				$tourBookingHistory->processing_fees = $processingFee;
				$tourBookingHistory->transaction_id = $transactionId;
				$tourBookingHistory->payment_type = $refundVia;
				$tourBookingHistory->payment_method = $history['refund_type'];
				$tourBookingHistory->is_processed = 1;
				$tourBookingHistory->save();
			} else {
				$tourBookingHistory->ticket_cost = $history['amount'];
				$tourBookingHistory->transaction_id = $transactionId;
				$tourBookingHistory->payment_type = $refundVia;
				$tourBookingHistory->payment_method = $history['refund_type'];
				$tourBookingHistory->is_processed = 1;
				$tourBookingHistory->save();
			}
        }
    }
}
