<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Services\Booking\BookingService;

class CancelPartialRefundService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($historyId, $refundVia, $transactionId)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);

        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);
			$cardNumber = '';
			if ($refundVia == "Card") {
				$convertedAmount = $history['amount'] / 102.9 * 100;
				$processingFee = $history['amount'] - $convertedAmount;
				$platformFee = 0;
				$transactionDetails = Transaction::where('id', $transactionId)->first();
				if ($transactionDetails && $transactionDetails->transaction_number && !isset($history['cards'])) {
					$cardDetails = BookingService::cardLastFourDigit($tourBookingHistory->tour_booking_id, $transactionDetails->transaction_number);
					$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
				}
			}
            $tourBookingHistory->ticket_cost = $history['amount'];
            $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
            $tourBookingHistory->transaction_id = $transactionId;
            $tourBookingHistory->payment_type = $refundVia;
			if ($refundVia == "Card") {
				$tourBookingHistory->card_last_4_digit = $cardNumber;
			}
            $tourBookingHistory->payment_method = $history['refund_type'];
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }
}
