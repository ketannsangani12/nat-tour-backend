<?php

namespace App\Services\Pos;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBookingHistory;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\Log;

class PosFullRefundService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $historyId, $refundVia, $transactionId)
    {
		$oldBookingId = oldBookingId();
		$isCashFees = cashFees();

        if ($booking->id < $oldBookingId) {

            $tourBookingHistory = TourBookingHistory::find($historyId);

            $customer = Customer::find($booking->customer_id);
            if ($tourBookingHistory) {
				$cardNumber = '';
                $history = json_decode($tourBookingHistory->details, true);

                if ($refundVia == "Card" || ($isCashFees=='1' && $refundVia=='Cash')) {
                    if ($customer->is_affiliate === 'No') {
                        $convertedPlaformAmount = $history['amount'] / 106 * 100;
                        $platformFee = $history['amount'] - $convertedPlaformAmount;
                        $convertedAmount = $convertedPlaformAmount / 102.9 * 100;
                        $processingFee = $convertedPlaformAmount - $convertedAmount;
                    } else {
                        $convertedAmount = $history['amount'] / 102.9 * 100;
                        $processingFee = $history['amount'] - $convertedAmount;
                        $platformFee = 0;
                    }
					$transactionDetails = Transaction::where('id', $transactionId)->first();
					if($transactionDetails && $transactionDetails->transaction_number && !isset($history['cards'])) {
						$cardDetails = BookingService::cardLastFourDigit($booking->id, $transactionDetails->transaction_number);
						$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
					}
                } else {
                    $convertedAmount = $history['amount'];
                    $processingFee = 0;
                    $platformFee = 0;
                }

                $tourBookingHistory->ticket_cost = $convertedAmount;
                $tourBookingHistory->processing_fees = $processingFee;
                $tourBookingHistory->platform_fees = $platformFee;
                $tourBookingHistory->transaction_id = $transactionId;
				if ($refundVia == "Card") {
					$tourBookingHistory->card_last_4_digit = $cardNumber;
				}
                $tourBookingHistory->payment_type = $refundVia;
                $tourBookingHistory->payment_method = $history['refund_type'];
                $tourBookingHistory->is_processed = 1;
                $tourBookingHistory->save();
            }
        } else {
            $tourBookingHistory = TourBookingHistory::find($historyId);
            $transactionDetails = Transaction::where('id', $transactionId)->first();

            $tourBookingTransaction = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->get();

            $tourBooking = TourBooking::find($booking->id);

            if ($tourBookingHistory) {
				$cardNumber = '';
                $history = json_decode($tourBookingHistory->details, true);

                if ($refundVia == "Card" || ($isCashFees=='1' && $refundVia=='Cash')) {
                   
					if($transactionDetails && $transactionDetails->transaction_number && !isset($history['cards'])) {
						$cardDetails = BookingService::cardLastFourDigit($booking->id, $transactionDetails->transaction_number);
						$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
					}
                    $paymentTransCount = Transaction::where('tour_booking_id', $booking->id)->where('status', 'Completed')->count();
                    
                }
                if(isset($history['transactionIdArray'])){
                    $transactionIdArrayCount = count($history['transactionIdArray']);
                    $totalAmount = 0;
                    $feeAmount = 0;
                   if($transactionIdArrayCount > 1){
                        foreach ($history['transactionIdArray'] as $value) {
                            $transactionDetail = Transaction::where('id', $value)->first();
                            $totalAmount += $transactionDetail->amount;
                            $feeAmount += $transactionDetail->fee_amount;
                        }
                        $transactionDetails->amount = $totalAmount;
                        $transactionDetails->fee_amount = $feeAmount;
                   }

                }
               
                if(!empty($transactionDetails)){
                    $tourBookingHistory->ticket_cost = $transactionDetails->amount - $transactionDetails->fee_amount;
                    $tourBookingHistory->platform_fees = $transactionDetails->fee_amount;
                    $tourBookingHistory->transaction_id = $transactionId;
                }else{
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
}
