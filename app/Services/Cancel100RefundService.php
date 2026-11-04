<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Services\Booking\BookingService;

class Cancel100RefundService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $historyId, $refundVia, $transactionId)
    {
		$isCashFees = cashFees();

        $tourBookingHistory = TourBookingHistory::find($historyId);
        $transactionDetails = Transaction::where('id', $transactionId)->first();

        if ($tourBookingHistory) {
            $cardNumber = '';
            $history = json_decode($tourBookingHistory->details, true);

            if ($refundVia == "Card" || ($isCashFees=='1' && $refundVia=='Cash')) {
                if ($transactionDetails && $transactionDetails->transaction_number && !isset($history['cards'])) {
                    $cardDetails = BookingService::cardLastFourDigit($booking->id, $transactionDetails->transaction_number);
                    $cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
                }
            }

            if (isset($history['transactionIdArray'])) {
                $transactionIdArrayCount = count($history['transactionIdArray']);
                $totalAmount = 0;
                $feeAmount = 0;
                if ($transactionIdArrayCount > 1) {
                    foreach ($history['transactionIdArray'] as $value) {
                        $transactionDetail = Transaction::where('id', $value)->first();
                        $totalAmount += $transactionDetail->amount;
                        $feeAmount += $transactionDetail->fee_amount;
                    }
                    $transactionDetails->amount = $totalAmount;
                    $transactionDetails->fee_amount = $feeAmount;
                }
            }
            
            if (!empty($transactionDetails)) {
                $tourBookingHistory->ticket_cost = $transactionDetails->amount - $transactionDetails->fee_amount;
                $tourBookingHistory->platform_fees = $transactionDetails->fee_amount;
                $tourBookingHistory->transaction_id = $transactionId;
                $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
            } else {
                $tourBookingHistory->ticket_cost = $history['amount'];
                $tourBookingHistory->platform_fees = 0;
                $tourBookingHistory->transaction_id = $transactionId;
            }

            if ($refundVia == "Card") {
                $tourBookingHistory->card_last_4_digit = $cardNumber;
            }

            $tourBookingHistory->payment_type = $refundVia;
            $tourBookingHistory->payment_method = $history['refund_type'];
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }
}
