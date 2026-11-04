<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Role extends Model implements Auditable
{
	use HasFactory, AuditTrait;

	protected $table = 'roles';

	protected $fillable = [
		'name',
		'guard_name',
		'display_name',
		'description',
		'created_from',
		'created_by',
		'created_at',
		'updated_at',
	];

	protected static function booted()
	{
		static::creating(function($role) {
			$role->created_by = Auth::id();
			$role->updated_at = now();
		});

		static::updating(function($role) {
			// $role->updated_at = now();
		});
	}
}
