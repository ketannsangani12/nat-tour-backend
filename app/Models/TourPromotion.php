<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPromotion extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $table = 'tour_promotions';

	protected $fillable = [
		'tour_operator_id',
		'tour_package_id',
		'code',
		'start_date',
		'end_date',
		'max_allowed_usages',
		'min_allowed_tourists',
		'is_extra_charge',
		'discount_value',
		'discount_value_type',
		'status',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
	];

	public function tourOperator()
	{
		return $this->belongsTo(TourOperator::class);
	}

	protected static function booted()
	{
		static::creating(function($tourPromotion) {
			$tourPromotion->tour_operator_id = tourOperator()->id;
			if (request()->has('tour_package')) {
				$tourPromotion->tour_package_id = request('tour_package');
			}
			$tourPromotion->created_by = auth()->id();
			$tourPromotion->created_by_user_type = session('userType');
			$tourPromotion->updated_at = null;
		});

		static::updating(function($tourPromotion) {
			if (request()->has('tour_package')) {
				$tourPromotion->tour_package_id = request('tour_package');
			}
			$tourPromotion->updated_by = auth()->id();
			$tourPromotion->updated_by_user_type = session('userType');
		});
	}
}
