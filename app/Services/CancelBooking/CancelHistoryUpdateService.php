<?php

namespace App\Services\CancelBooking;

use App\Jobs\DetectPayoutAdjustment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CancelHistoryUpdateService
{
	/**
	 * Execute the service for Cash/Invoiced/Wire Transfer for history table update.
	 *
	 * @return int
	 */
	public static function run($id, $tourBooking, $netAmount, $refundKeepUs, $refundVia, $createdByWithName, $request, $refundType, $userType, $netPartnerFees = 0, $paidTourists = null)
	{
		$jsonData = [
			'amount' => $netAmount,
			'refund_keep_us' => $refundKeepUs,
			'partner_fees' => $netPartnerFees,
			'payment_type' => $refundVia,
			'created_by_with_name' => $createdByWithName,
			'note' => $request->comment,
			'refund_type' => $refundType,
		  	'refund_basis' => ($request->has('refund_basis')) ? $request->refund_basis : '',
		  	'refund_percent' => ($request->has('refund_percent')) ? $request->refund_percent : ''
		];

		$historyId = DB::table('tour_booking_history')
			->insertGetId([
				'tour_booking_id' => $id,
				'tourists' => $paidTourists ?? ($tourBooking->tourists + $tourBooking->infants),
				'operation' => 'Cancelled',
				'details' => json_encode($jsonData, JSON_NUMERIC_CHECK),
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => $userType,
				'updated_at' => Carbon::now(),
				'created_at' => Carbon::now(),
			]);

		DetectPayoutAdjustment::dispatch($id, $historyId, 'Cancelled');

		return $historyId;
	}
}
