<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutAdjustment extends Model
{
	protected $table = 'payout_adjustments';

	protected $fillable = [
		'payout_id',
		'payout_reference',
		'booking_id',
		'type',
		'gross_amount',
		'platform_fee',
		'net_amount',
		'reason',
		'source_history_ids',
		'status',
		'applied_in_payout_id',
		'applied_at',
		'detected_at',
		'voided_by',
		'voided_at',
		'void_reason',
	];

	protected $casts = [
		'gross_amount' => 'decimal:2',
		'platform_fee' => 'decimal:2',
		'net_amount' => 'decimal:2',
		'source_history_ids' => 'array',
		'applied_at' => 'datetime',
		'detected_at' => 'datetime',
		'voided_at' => 'datetime',
	];

	public function originalPayout()
	{
		return $this->belongsTo(AutoPayout::class, 'payout_id')->withDefault();
	}

	public function isOrphaned(): bool
	{
		return $this->payout_id === null;
	}

	/**
	 * Find adjustments by payout_reference. Works for both auto and pending payouts.
	 */
	public function scopeForPayoutReference($query, string $reference)
	{
		return $query->where('payout_reference', $reference);
	}

	/**
	 * Find adjustments belonging to an executed payout, by foreign key or by reference.
	 *
	 * Two different kinds of adjustment attach to one executed payout, and they carry
	 * different reference strings:
	 *
	 *  - Adjustments folded into the payout at approval time were created against the
	 *    parent PendingPayout, so they hold that row's reference and are linked here
	 *    only by applied_in_payout_id.
	 *  - Adjustments detected after the payout completed copy ap.payout_reference and
	 *    have no applied_in_payout_id until a later payout absorbs them.
	 *
	 * Matching on the reference alone therefore missed the first kind on any payout
	 * written while executeSinglePayout() still appended a random suffix to the
	 * reference. Rows written then keep the suffixed value, so the foreign-key arm is
	 * what makes those historical payouts resolve correctly.
	 *
	 * The two conditions are grouped so a caller's own constraints (booking id filters
	 * in particular) apply to the whole match rather than binding to the second arm.
	 */
	public function scopeForExecutedPayout($query, AutoPayout $payout)
	{
		return $query->where(function ($q) use ($payout) {
			$q->where('applied_in_payout_id', $payout->id)
				->orWhere('payout_reference', $payout->payout_reference);
		});
	}

	public function appliedInPayout()
	{
		return $this->belongsTo(AutoPayout::class, 'applied_in_payout_id');
	}

	public function booking()
	{
		return $this->belongsTo(TourBooking::class, 'booking_id');
	}

	public function voidedByAdmin()
	{
		return $this->belongsTo(Admin::class, 'voided_by');
	}

	public function scopePending($query)
	{
		return $query->where('status', 'pending');
	}

	public function scopeApplied($query)
	{
		return $query->where('status', 'applied');
	}

	public function scopeVoided($query)
	{
		return $query->where('status', 'voided');
	}

	public function isDebit(): bool
	{
		return $this->type === 'debit';
	}

	public function isCredit(): bool
	{
		return $this->type === 'credit';
	}

	/**
	 * Returns the net_amount with sign: negative for debits, positive for credits.
	 */
	public function signedNetAmount(): float
	{
		return $this->isDebit() ? -1 * (float) $this->net_amount : (float) $this->net_amount;
	}
}
