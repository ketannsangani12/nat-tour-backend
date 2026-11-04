<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Country extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $table = 'countries';

	protected $fillable = [
		'name',
		'code',
		'currency',
		'currency_symbol',
		'phone_code',
		'locale',
		'status',
		'created_by',
		'updated_by',
		'deleted_by',
		'created_by_user_type',
		'updated_by_user_type',
	];

	public function scopeIsActive($query)
	{
		return $query->where('status', 'Active');
	}

	protected static function booted()
	{
		static::creating(function($country) {
			$country->created_by = Auth::id();
			$country->updated_at = now();
			$country->created_by_user_type = userType();
		});

		static::updating(function($country) {
			$country->updated_by = Auth::id();
			$country->updated_by_user_type = userType();
		});
	}
}
