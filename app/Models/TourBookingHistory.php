<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourBookingHistory extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'tour_booking_history';
    protected $fillable = [
    	'tour_booking_id',
		'transaction_id',
		'package_swipe_history_id',
    	'operation',
    	'details',
    	'operation_at',
    	'operation_by',
    	'operation_by_user_type',
    ];

	public function tourBooking()
	{
		return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id');
	}

	/**
	 * Whether a Refund history row is the ledger entry written when pax were split off a booking
	 * that had not been paid yet (ExtendBookingPayLaterService). Those rows carry the moved pax's
	 * subtotal in details->amount but no money ever moved: there was no charge to refund. A payment
	 * taken after the split is already sized for the pax left on the booking, so counting these rows
	 * as "already refunded" at cancel time subtracts the moved pax's cost a second time.
	 *
	 * Splits of a paid booking (Card/Cash/Invoiced) record their payment_type and must still count,
	 * since the moved pax's share stays on the parent's charge but now belongs to the child booking.
	 * Same rule ReportRepository already uses to zero these rows in its refund totals.
	 */
	public static function isUnpaidSplitReallocation($history): bool
	{
		$details = is_string($history->details) ? json_decode($history->details) : $history->details;
		if (empty($details)) {
			return false;
		}
		$details = (object) $details;

		return ($details->from ?? null) === 'Extend Booking'
			&& isset($details->payment_type)
			&& $details->payment_type === '';
	}

	/**
	 * Drop unpaid split reallocation rows from a set of Refund history rows, leaving only the rows
	 * that reflect money taken off a real payment. See isUnpaidSplitReallocation().
	 */
	public static function withoutUnpaidSplitReallocations($refundHistory)
	{
		return $refundHistory->reject(fn ($history) => self::isUnpaidSplitReallocation($history))->values();
	}

	/**
	 * Id of the tour-side Payment history row of a booking: the one for $transactionId when
	 * given, else the booking's first one. Null when there is none.
	 *
	 * A "Don't Refund" pax removal only forfeits money if the pax was already paid for. A row
	 * recorded before this Payment row removed pax while the booking was still unpaid, so that
	 * charge was already sized for the pax left on the booking and must not be reduced for them.
	 */
	public static function tourPaymentHistoryId(int $bookingId, ?int $transactionId = null): ?int
	{
		$query = self::where('tour_booking_id', $bookingId)->where('operation', 'Payment');
		$query = $transactionId !== null
			? $query->where('transaction_id', $transactionId)
			: $query->whereNull('charge_type');

		$id = $query->min('id');

		return $id !== null ? (int) $id : null;
	}

	/**
	 * Whether a history row was recorded before the given Payment history row. See
	 * tourPaymentHistoryId().
	 */
	public static function isRecordedBeforePayment($history, ?int $paymentHistoryId): bool
	{
		return $paymentHistoryId !== null && (int) $history->id < $paymentHistoryId;
	}
}
