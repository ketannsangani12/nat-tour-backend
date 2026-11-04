<?php

namespace App\Services;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBookingHistory;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\Transaction;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Constants\Constants;
use Carbon\Carbon;

class DeletePerson100RefundService
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($booking, $historyId, $request, $refundVia, $transactionId)
    {
    	// We seperated methods for old bookings and new bookings
		$oldBookingId = oldBookingId();
		if ($booking->id < $oldBookingId) {
            static::forOldBookings($booking, $historyId, $request, $refundVia, $transactionId);
		} else {
			static::forNewBookings($booking, $historyId, $request, $refundVia, $transactionId);
		}
    }

    private static function forOldBookings($booking, $historyId, $request, $refundVia, $transactionId)
	{
        $tourBookingHistory = TourBookingHistory::find($historyId);

        $customer = Customer::find($booking->customer_id);

        $tourPackage = TourPackage::find($booking->tour_package_id);

        $tourOperator = TourOperator::find($tourPackage->tour_operator_id);

		$isCashFees = cashFees();

        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);
			$cardNumber = '';
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
				if($transactionDetails && $transactionDetails->transaction_number!='') {
					$cardDetails = BookingService::cardLastFourDigit($booking->id, $transactionDetails->transaction_number);
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
        }
    }

    private static function forNewBookings($booking, $historyId, $request, $refundVia, $transactionId)
	{
        $tourBookingHistory = TourBookingHistory::find($historyId);
        $isCashFees = cashFees();

        $tourBookingTransaction = BookingTransact::where('tour_booking_id', $booking->id)->where('deleted_by', 0)->first();

        $transactionDetails = Transaction::where('id', $transactionId)->first();

        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);
            $cardNumber = '';
            if ($refundVia == "Card" || ($isCashFees=='1' && $refundVia=='Cash')) {
                if($transactionDetails && $transactionDetails->transaction_number) {
					$cardDetails = BookingService::cardLastFourDigit($booking->id, $transactionDetails->transaction_number);
					$cardNumber = !empty($cardDetails) ? $cardDetails['card_last_4_digit'] : '';
				}
            }
            
            if($transactionDetails){
                $tourBookingHistory->ticket_cost = $transactionDetails->amount - $transactionDetails->fee_amount;
                $tourBookingHistory->platform_fees = $transactionDetails->fee_amount;
                $tourBookingHistory->partner_fees = (isset($history['partner_fees']) && $history['partner_fees'] != null) ? $history['partner_fees'] : 0;
                $tourBookingHistory->transaction_id = $transactionId;
            }else{
                $tourBookingHistory->ticket_cost = $history['amount'];
                $tourBookingHistory->platform_fees = 0;
            }
            
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

             $trans = Transaction::where('tour_booking_id', $tourBookingHistory->tour_booking_id)->where('status', 'Completed')->first();
                if(!empty($trans) && $trans->via == "Invoiced"){
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
                            'created_by' => auth()->id(),
                            'created_by_user_type' => userType(),
                            'created_at' => Carbon::now(),
                            'charge_type' => null,
                        ]);
                }
        }
    }
}
