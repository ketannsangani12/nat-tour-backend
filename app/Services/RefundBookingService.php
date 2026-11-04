<?php

namespace App\Services;

use App\Models\TourBookingHistory;

class RefundBookingService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($transactionId, $historyId, $refundVia)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);

        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);

            $tourBookingHistory->ticket_cost = $history['amount'];
            $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
            $tourBookingHistory->transaction_id = $transactionId;
            $tourBookingHistory->payment_type = $refundVia;
            $tourBookingHistory->payment_method = $history['refund_type'];
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }
}
