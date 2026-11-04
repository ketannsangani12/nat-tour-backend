<?php

namespace App\Services;

use App\Models\TourBookingHistory;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Constants\Constants;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DeletePersonFullRefundService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($historyId, $request, $refundVia, $transactionId)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);
		$oldBookingId = oldBookingId();
		$isCashFees = cashFees();
        $trans = Transaction::where('tour_booking_id', $tourBookingHistory->tour_booking_id)->whereNull('charge_type')->where('status', 'Completed')->first();
        if ($tourBookingHistory) {
            if ($tourBookingHistory->tour_booking_id < $oldBookingId) {
                $history = json_decode($tourBookingHistory->details, true);
				$cardNumber = '';
                if ($refundVia == "Card" || ($isCashFees=='1' && $refundVia=='Cash')) {
                    $convertedAmount = $history['amount'] / 102.9 * 100;
                    $processingFee = $history['amount'] - $convertedAmount;
                    $platformFee = 0;
					$transactionDetails = Transaction::where('id', $transactionId)->first();
					if($transactionDetails && $transactionDetails->transaction_number) {
						$cardDetails = BookingService::cardLastFourDigit($tourBookingHistory->tour_booking_id, $transactionDetails->transaction_number);
						$cardNumber = (!empty($cardDetails))?$cardDetails['card_last_4_digit']:'';
					}
                } else {
                    $convertedAmount = $history['amount'];
                    $processingFee = 0;
                    $platformFee = 0;
                }

                $tourBookingHistory->ticket_cost = $convertedAmount;
                $tourBookingHistory->processing_fees = $processingFee;
                $tourBookingHistory->platform_fees = $platformFee;
                $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
                $tourBookingHistory->transaction_id = $transactionId;
				if ($refundVia == "Card") {
					$tourBookingHistory->card_last_4_digit = $cardNumber;
				}
                if ($request['is_cash'] == "Wire Transfer") {
                    $tourBookingHistory->payment_type = "Check/Wire";
                } elseif ($request['is_cash'] == "Cash") {
                    $tourBookingHistory->payment_type = "Cash";
                } else {
                    $tourBookingHistory->payment_type = $refundVia;
                }

                $tourBookingHistory->payment_method = $history['refund_type'];
                $tourBookingHistory->is_processed = 1;
                $tourBookingHistory->save();
            } else {
                $history = json_decode($tourBookingHistory->details, true);
				$cardNumber = '';
				if ($refundVia == "Card") {
					$transactionDetails = Transaction::where('id', $transactionId)->first();
					if($transactionDetails && $transactionDetails->transaction_number!='') {
						$cardDetails = BookingService::cardLastFourDigit($tourBookingHistory->tour_booking_id, $transactionDetails->transaction_number);
						$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
					}
				}
                $tourBookingHistory->ticket_cost = $history['amount'];
                $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
                $tourBookingHistory->transaction_id = $transactionId;
				if ($refundVia == "Card") {
					$tourBookingHistory->card_last_4_digit = $cardNumber;
				}
                if ($request['is_cash'] == "Wire Transfer") {
                    $tourBookingHistory->payment_type = "Check/Wire";
                } elseif ($request['is_cash'] == "Cash") {
                    $tourBookingHistory->payment_type = "Cash";
                } else {
                    $tourBookingHistory->payment_type = $refundVia;
                }

                $tourBookingHistory->payment_method = $history['refund_type'];
                $tourBookingHistory->is_processed = 1;
                $tourBookingHistory->save();

                
                if (isset($trans->via) && $trans->via == "Invoiced" && $transactionId == null) {
                    $transactionId = DB::table('transactions')
                        ->insertGetId([
                            'tour_booking_id' => $tourBookingHistory->tour_booking_id,
                            'amount' => $history['amount'],
                            'fee_amount' => 0,
                            'transaction_number' => null,
                            'application_fee_id' => null,
                            'via' => $refundVia,
                            'response_init' => '',
                            'response_end' => '',
                            'response_end_datetime' => null,
                            'status' => 'Refund',
                            'visitor' => request()->getClientIp(),
                            'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
                            'created_by' => Auth::id(),
                            'created_by_user_type' => userType(),
                            'created_at' => Carbon::now(),
                            'charge_type' => null,
                        ]);
                }
            }
        }
    }
}
