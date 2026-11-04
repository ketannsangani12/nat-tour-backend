<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Hotel extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $table = 'hotels';

	protected $fillable = [
		'tour_operator_id',
		'tour_package_id',
		'name',
		'email',
		'address',
		'phone_number',
		'location_url',
		'image',
		'status',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	protected static function booted()
	{
		static::creating(function($hotel) {
			$hotel->created_at = Carbon::now();
			$hotel->created_by = auth()->id();
			$hotel->created_by_user_type = userType();
		});

		static::updating(function($hotel) {
			$hotel->updated_at = Carbon::now();
			$hotel->updated_by = auth()->id();
			$hotel->updated_by_user_type = userType();
		});
	}
}
