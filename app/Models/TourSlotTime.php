<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourSlotTime extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $table = 'tour_slot_times';

	protected $fillable = [
		'id',
		'tour_slot_id',
		'tour_package_id',
		'time_slot_seats',
		'date',
		'time_from',
		'time_to',
		'bookable_status',
		'hide_slot',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
	];

	/**
	 * Get the tour slot date data using relationship.
	 * 
	 * @return BelongsTo
	 */
	public function tourSlotDate(): BelongsTo
	{
		return $this->belongsTo(TourDateSlot::class, 'tour_slot_id', 'id', BelongsTo::class);
	}

	protected static function booted()
	{
		static::deleting(function ($tourSlotTime) {
			$tourSlotTime->deleted_by = Auth::user()->id;
			$tourSlotTime->deleted_by_user_type = userType();
			$tourSlotTime->save();
		});
	}
}
