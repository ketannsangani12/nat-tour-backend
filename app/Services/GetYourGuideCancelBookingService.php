<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TourBookingHistory;
use App\Models\Customer;
use App\Services\GetYourGuideBookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetYourGuideCancelBookingService
{
    /**
     * Execute the service to Cancel Booking From GetYourGuide.
     *
     * @return mixed
     */
    public static function run($bookingId, $tourBooking, $cancelNote)
    {
        $trans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->get();
        $customer = Customer::where('id',$tourBooking->customer_id)->first();
        $netAmount = 0;
        $refundKeepUs = 0;
        $refundVia = "GetYourGuide";

        foreach ($trans as $value) {
            $totalrefundedamountfortransaction = 0;
            
            $histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation','Payment')->first(['ticket_cost','platform_fees']);
            $refundedTransForTransaction = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Refund')->get(['id','amount']);

            foreach ($refundedTransForTransaction as $ky => $val) {
                $totalrefundedamountfortransaction += $val->amount;
            }

            $net_amount = $histPaymentDetails->ticket_cost - $totalrefundedamountfortransaction;
            $platformfees = $histPaymentDetails->platform_fees;

            // Update transactions table
            $transactionT = new Transaction;
            $transactionT->tour_booking_id = $bookingId;
            $transactionT->amount = $net_amount;
            $transactionT->transaction_number = '';
            $transactionT->via = $refundVia;
            $transactionT->response_init = '';
            $transactionT->response_end = '';
            $transactionT->response_end_datetime = Carbon::now();
            $transactionT->status = 'Refund';
            $transactionT->visitor = request()->ip();
            $transactionT->request_from = 'GETYOURGUIDE';
            $transactionT->save();
            
            $netAmount += $net_amount;
            $refundKeepUs += $platformfees;
        }

        $historyId = GetYourGuideBookingService::storeRefundDataInHistory($bookingId, $transactionT->id, $tourBooking->tourists, $netAmount, $refundKeepUs, $cancelNote, $customer->id, 'GetYourGuide', 'Full Refund');
        DB::commit();

        return [
            'historyId' => $historyId,
            'via' => $transactionT->via, 
            'transaction_id' => $transactionT->id,
        ];
    }

    public static function updateRefundHistory($historyId, $refundVia, $transactionId)
    {
        $tourBookingHistory = TourBookingHistory::find($historyId);
        if ($tourBookingHistory) {
            $history = json_decode($tourBookingHistory->details, true);

            $tourBookingHistory->ticket_cost = $history['amount'];
            $tourBookingHistory->transaction_id = $transactionId;
            $tourBookingHistory->payment_type = $refundVia;
            $tourBookingHistory->payment_method = $history['refund_type'];
            $tourBookingHistory->is_processed = 1;
            $tourBookingHistory->save();
        }
    }
}
