<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalRateSchedule extends Model
{
	use SoftDeletes, HasFactory;

	protected $table = 'global_rate_schedules';

	protected $fillable = [
		'tour_package_id',
		'affiliate_id',
		'charge_amount',
		'charge_type',
		'charge_mode',
		'date_time',
		'scheduling_year',
		'status'
	];

	/**
	 * Get the tour package data using relationship.
	 *
	 * @return BelongsTo
	 */
	public function tourPackage(): BelongsTo
	{
		return $this->belongsTo(TourPackage::class, 'tour_package_id', 'id');
	}

	/**
	 * Get the affiliate data using relationship.
	 *
	 * @return BelongsTo
	 */
	public function affiliate(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'affiliate_id', 'id');
	}

	protected static function booted()
	{
		static::creating(function($schedule) {
			$schedule->created_at = Carbon::now();
			$schedule->created_by = auth()->id();
			$schedule->created_by_user_type = userType();
		});

		static::updating(function($schedule) {
			$schedule->updated_at = Carbon::now();
			// $schedule->updated_by = auth()->id();
			// $schedule->updated_by_user_type = userType();
		});

		static::deleting(function($schedule) {
			$schedule->deleted_at = Carbon::now();
			// $schedule->deleted_by = auth()->id();
			// $schedule->deleted_by_user_type = userType();
		});
	}
}
