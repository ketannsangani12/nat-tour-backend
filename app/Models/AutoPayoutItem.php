<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoPayoutItem extends Model
{
	use HasFactory;

	protected $table = 'auto_payout_items';

	protected $fillable = [
		'payout_id',
		'booking_id',
		'total_amount',
		'platform_fee',
		'net_amount'
	];

	/**
	 * Get the attributes that should be cast to native types.
	 *
	 * @return array
	 */
	protected $casts = [
		'total_amount' => 'decimal:2',
		'platform_fee' => 'decimal:2',
		'net_amount' => 'decimal:2',
	];

	/**
	 * Get the payout that owns this item.
	 */
	public function payout()
	{
		return $this->belongsTo(AutoPayout::class, 'payout_id');
	}

	/**
	 * Get the booking associated with this payout item.
	 */
	public function booking()
	{
		return $this->belongsTo(\App\Models\TourBooking::class, 'booking_id');
	}
}
