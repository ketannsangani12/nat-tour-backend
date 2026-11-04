<?php

namespace App\Models;

use App\Models\Concerns\HasPayoutPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoPayout extends Model
{
	use HasFactory;
	use HasPayoutPeriod;

	protected $table = 'auto_payouts';

	protected $fillable = [
		'payout_reference',
		'stripe_payout_id',
		'rule_id',
		'total_amount',
		'platform_fee',
		'net_amount',
		'booking_count',
		'period_start',
		'period_end',
		'processed_date',
		'status'
	];

	/**
	 * Get the attributes that should be cast to native types.
	 *
	 * @return array
	 */
	protected $casts = [
		'processed_date' => 'datetime',
		'period_start' => 'datetime',
		'period_end' => 'datetime',
		'total_amount' => 'decimal:2',
		'platform_fee' => 'decimal:2',
		'net_amount' => 'decimal:2',
	];

	/**
	 * Get the rule that owns this payout.
	 */
	public function rule()
	{
		return $this->belongsTo(AutoPayoutRule::class, 'rule_id');
	}

	/**
	 * Get the payout items for this payout.
	 */
	public function items()
	{
		return $this->hasMany(AutoPayoutItem::class, 'payout_id');
	}

	/**
	 * Get adjustments originating from this payout (bookings amended after this payout).
	 */
	public function adjustments()
	{
		return $this->hasMany(PayoutAdjustment::class, 'payout_id');
	}

	/**
	 * Get adjustments that were applied/settled in this payout.
	 */
	public function appliedAdjustments()
	{
		return $this->hasMany(PayoutAdjustment::class, 'applied_in_payout_id');
	}
}
