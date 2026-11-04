<?php

namespace App\Services;

use App\Models\TourBookingHistory;

class DeletePersonDontRefundService
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
            $history['amount'] = 0;
            $tourBookingHistory->details = json_encode($history);
            $tourBookingHistory->transaction_id = $transactionId;

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
