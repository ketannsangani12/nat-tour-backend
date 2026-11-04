<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\CanResetPassword;
use App\Notifications\LinkForResettingPassword;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;
use Spatie\Permission\Traits\HasRoles;

class Staff extends Authenticatable implements CanResetPassword, Auditable
{
	use SoftDeletes, HasApiTokens, HasFactory, Notifiable, AuditTrait, HasRoles;

	protected $guard = 'tour_operator_staff';
	protected $table = 'tour_operator_staff';
	protected $appends = [ 'name'];

	protected $fillable = [
		'tour_operator_id',
		'first_name',
		'last_name',
		'email',
		'password',
		'phone_number',
		'manager_id',
		'manager_pin',
		'profile_photo_path',
		'department_id',
		'role_type_id',
		'tip_distribution_percentage',
		'status',
		'capacity',
		'build_no',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
		'default_booking_source',
	];

	protected $hidden = [
		'password',
		'remember_token',
	];

	public function tourOperator()
	{
		return $this->belongsTo(TourOperator::class);
	}

	public function getNameAttribute()
	{
		return $this->first_name . ' ' . $this->last_name;
	}

	/**
	 * Query to get department assigned packages only
	 */
	public function scopeDepartmentPackage($query)
	{
		$departmentPackage = departmentPackage();
		return $query->when($departmentPackage, function ($query) {
			return $query->where('department_id', auth()->user()->department_id);
		});
	}

	protected static function booted()
	{
		static::creating(function($staff) {

			$staff->created_by = auth()->id();
			$staff->created_by_user_type = userType();
			$staff->password = Hash::make(request('password'));
			$staff->updated_at = null;
		});

		static::updating(function($staff) {
			if(!empty(request('password')))
				$staff->password = Hash::make(request('password'));

			$staff->updated_by = auth()->id();
			$staff->updated_by_user_type = userType();
		});
	}

	public function sendPasswordResetNotification($token)
	{
		$this->notify(new LinkForResettingPassword($token));
	}
}
