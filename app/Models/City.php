<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class City extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'cities';

	protected $fillable = [
		'state_id',
		'name',
		'timezone',
		'status',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	public function state()
	{
		return $this->belongsTo(State::class);
	}

	protected static function booted()
	{
		static::creating(function($city) {
			$city->state_id = request('state');
			$city->created_by = Auth::id();
			$city->updated_at = now();
		});
	}
}
