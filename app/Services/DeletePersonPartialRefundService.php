<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Services\Booking\BookingService;

class DeletePersonPartialRefundService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($historyId, $request, $paymentType, $transactionId)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);

        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);
			$cardNumber = '';

			$transactionDetails = Transaction::where('id', $transactionId)->first();
			if($transactionDetails && $transactionDetails->transaction_number!='') {
					$cardDetails = BookingService::cardLastFourDigit($tourBookingHistory->tour_booking_id, $transactionDetails->transaction_number);
					$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
			}

            $tourBookingHistory->ticket_cost = $history['amount'];
            $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
            $tourBookingHistory->transaction_id = $transactionId;
			$tourBookingHistory->card_last_4_digit = $cardNumber;
            if ($request['is_cash'] == "Wire Transfer") {
                $tourBookingHistory->payment_type = "Check/Wire";
            } elseif ($request['is_cash'] == "Cash") {
                $tourBookingHistory->payment_type = "Cash";
            } else {
                $tourBookingHistory->payment_type = $paymentType;
            }

            $tourBookingHistory->payment_method = $history['refund_type'];
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }
}
