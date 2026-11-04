<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class State extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $table = 'states';

	protected $fillable = [
		'country_id',
		'name',
		'permit_fee',
		'status',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	public function country()
	{
		return $this->belongsTo(Country::class);
	}

	protected static function booted()
	{
		static::creating(function($state) {
			$state->country_id = request('country');
			$state->created_by = Auth::id();
			$state->updated_at = null;
		});

		static::updating(function($state) {
			$state->country_id = request('country');
			$state->updated_by = Auth::id();
		});
	}
}
