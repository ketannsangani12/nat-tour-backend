<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TourBookingHistory;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PartnerApiCancelBookingService
{
    /**
     * Execute the service to Cancel Booking From AffiliatePartner.
     *
     * @return mixed
     */
    public static function run($transaction, $bookingId, $tourBooking, $request)
    {
        $paymentTransCount = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->count();
        $trans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->get();
        $customer = Customer::where('id',$tourBooking->customer_id)->first();
        $net_amount_split = 0;
        $refund_keep_us = 0;
        $refundVia = "AffiliatePartner";

        foreach ($trans as $value) {
            $histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation','Payment')->first(['ticket_cost','platform_fees']);

            $refundedTransForTransaction = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Refund')->get(['id','amount']);
            $totalrefundedamountfortransaction = 0;
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
            $transactionT->request_from = 'PARTNERAPI';
            $transactionT->save();
            
            $net_amount_split += $net_amount;
            $refund_keep_us += $platformfees;
        }

        $historyId = DB::table('tour_booking_history')
            ->insertGetId([
                'tour_booking_id' => $bookingId,
                'transaction_id' => $transactionT->id,
                'tourists' => $tourBooking->tourists,
                'operation' => 'Cancelled',
                'details' => json_encode([
                    'amount' => $net_amount_split,
                    'refund_keep_us' => $refund_keep_us,
                    'payment_type' => 'AffiliatePartner',
                    'refund_type' => 'Full Refund',
                    'note' => $request['data']['Reason']
                ], JSON_NUMERIC_CHECK),
                'operation_at' => mysqlDT(),
                'operation_by' => $customer->id,
                'operation_by_user_type' => 'Affiliate',
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);
        DB::commit();

        return [
            'historyId' => $historyId,
            'via' => $transactionT->via, 
            'transaction_id' => $transactionT->id,
        ];
    }

    public static function historyUpdate($historyId, $refundVia, $transactionId)
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
