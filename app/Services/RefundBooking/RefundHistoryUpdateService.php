<?php

namespace App\Services\RefundBooking;

use App\Jobs\DetectPayoutAdjustment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
class RefundHistoryUpdateService
{
    /**
     * Execute the service for Cash/Invoiced/Wire Transfer for history table update.
     *
     * @return int
     */
    public static function run($id, $tourBooking, $net_amount, $refund_keep_us, $refundVia, $created_by_with_name, $request, $refund_type, $userType)
    {
		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $id,
				'tourists' => $tourBooking->tourists + $tourBooking->infants,
				'operation' => 'Refund',
				'details' => json_encode(['amount' => $net_amount, 'refund_keep_us' => $refund_keep_us, 'payment_type' => $refundVia, 'created_by_with_name' => $created_by_with_name, 'note' => $request->comment, 'refund_type' => $refund_type ,'refund_basis' => ($request->has('refund_basis')) ? $request->refund_basis : '' ,'refund_percent' => ($request->has('refund_percent')) ? $request->refund_percent : '','from' => 'POS'  ], JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => $userType,
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($id, $historyId, 'Refund');

		return $historyId;
    }
}
