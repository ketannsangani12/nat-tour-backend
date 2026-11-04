<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourDateSlot extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'tour_slot_dates';

	protected $fillable = [
		'tour_package_id',
		'date',
		'seats',
		'status',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
	];

	public function tourPackage()
	{
		return $this->belongsTo(TourPackage::class);
	}

	/**
	 * Get the tour slot date data using relationship.
	 * 
	 * @return HasMany
	 */
	public function tourSlotTime(): HasMany
	{
		return $this->hasMany(TourSlotTime::class, 'tour_slot_id', 'id');
	}

	protected static function booted()
	{
		static::creating(function($tourSlot) {
			$tourSlot->updated_at = null;
		});

		static::deleting(function($tourSlotDate) {
			$tourSlotDate->deleted_by = auth()->user()->id;
			$tourSlotDate->deleted_by_user_type = userType();
			$tourSlotDate->save();
			// Remove each relational records
			$tourSlotDate->tourSlotTime->each->delete();
	   	});
	}

	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}
}
