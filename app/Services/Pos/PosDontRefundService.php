<?php

namespace App\Services\Pos;

use App\Models\TourBookingHistory;

class PosDontRefundService
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
            $history['amount'] = 0;
            $tourBookingHistory->details = json_encode($history);
            $tourBookingHistory->transaction_id = $transactionId;
            $tourBookingHistory->payment_type = $refundVia;
            $tourBookingHistory->payment_method = $history['refund_type'];
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }
}
