<?php

namespace App\Services\ThreeWayPayment;

use App\Constants\Constants;
use App\Models\BookingTransact;
use App\Models\Payout;
use App\Services\Booking\TransactionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutService
{

    /**
     * Execute the payout service to save payment in payout
     *
     * @param mixed $tourBookingId
     *
     * @return int
     */
    public static function storePaymentInPayout($tourBookingId, $tourPackageId, $paymenTransactionId = NULL, $destinationId, $payoutAmount, $totalAmount, $partnerFees, $paymentMethod, $transferId = '',  $createdByUserType): void
    {
        DB::table('payouts')->insert([
            'tour_booking_id' => $tourBookingId,
            'package_id' => $tourPackageId,
            'transaction_id' => $paymenTransactionId,
            'stripe_account_id' => $destinationId,
            'transfer_id' => $transferId,
            'payout_amount' => $payoutAmount,
            'total_amount' => $totalAmount,
            'commission_amount' => $partnerFees,
            'payment_method' => $paymentMethod,
            'payout_status' => 'Completed',
            'created_by' => Auth::id(),
            'created_by_user_type' => $createdByUserType,
            'created_at' => Carbon::now(),
        ]);
    }
    /**
     * Execute the payout service to save reverse transfer in payout
     *
     * @param mixed $tourBookingId
     *
     * @return int
     */
    public static function storeRefundInPayout($tourBookingId, $tourPackageId, $paymenTransactionId = NULL, $destinationId, $payoutAmount, $totalAmount, $partnerFees, $paymentMethod, $transferId = '',  $createdByUserType): int
    {
        $payoutId = DB::table('payouts')
            ->insertGetId([
                'tour_booking_id' => $tourBookingId,
                'package_id' => $tourPackageId,
                'transaction_id' => $paymenTransactionId,
                'stripe_account_id' => $destinationId,
                'transfer_id' => $transferId,
                'payout_amount' => $payoutAmount,
                'total_amount' => $totalAmount,
                'commission_amount' => $partnerFees,
                'payment_method' => $paymentMethod,
                'payout_status' => 'Reversed',
                'created_by' => Auth::id(),
                'created_by_user_type' => $createdByUserType,
                'created_at' => Carbon::now(),
            ]);
        return $payoutId;
    }
}
