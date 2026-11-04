<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\CanResetPassword;
use App\Notifications\LinkForResettingPassword;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable implements CanResetPassword, Auditable
{
	use SoftDeletes, HasFactory, Notifiable, AuditTrait, HasRoles;

	protected $guard = 'admin';
	protected $table = 'admins';

	protected $fillable = [
		'name',
		'email',
		'password',
		'department_id',
		'profile_photo_path',
		'default_booking_source',
		'status',
		'created_by',
		'updated_by',
		'deleted_by',
	];

	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * Summary of scopeIsActive
	 * @param mixed $query
	 * @return mixed
	 */
	public function scopeIsActive($query)
	{
		return $query->where('status', 'Active');
	}

	/**
	 * Query to get department assigned packages only
	 */
	public function scopeDepartmentPackage($query)
	{
		$departmentPackage = departmentPackage();
		return $query->when($departmentPackage, function ($query) {
			return $query->where('department_id', Auth::user()->department_id);
		});
	}

	/**
	* The "booted" method of the model.
	*
	* @return void
	*/
	protected static function booted()
	{
		static::creating(function($adminUser) {
			$adminUser->created_by = Auth::user()->id ?? 0;
			$adminUser->updated_at = now();
		});

		static::updating(function($adminUser) {
			$adminUser->updated_by = Auth::user()->id ?? 0;
		});
	}

	public function sendPasswordResetNotification($token)
	{
		$this->notify(new LinkForResettingPassword($token));
	}
}
