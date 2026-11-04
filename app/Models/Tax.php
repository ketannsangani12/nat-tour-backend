<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Tax extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $table = 'taxes';

	protected $fillable = [
		'state_id',
		'name',
		'value',
		'value_type',
		'status',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	public function state()
	{
		return $this->belongsTo(State::class);
	}

	public function scopeActive($query)
	{
		return $query->where('status', 'Active');
	}

	protected static function booted()
	{
		static::creating(function($tax) {
			$tax->state_id = request('state');
			$tax->created_by = auth()->id();
			$tax->updated_at = null;
		});

		static::updating(function($tax) {
			$tax->state_id = request('state');
			$tax->updated_by = auth()->id();
		});
	}
}
