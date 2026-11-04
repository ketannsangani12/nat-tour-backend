<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Auth;
use App\Notifications\LinkForResettingPassword;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;
use Spatie\Permission\Traits\HasRoles;
use App\Utils\IpGeolocationUtils;

class Customer extends Authenticatable implements CanResetPassword, Auditable
{
	use SoftDeletes, HasFactory, Notifiable, AuditTrait, HasRoles;

	protected $guard = 'customer';
	protected $table = 'customers';

	protected $fillable = [
		'tour_operator_id',
		'stripe_id',
		'name',
		'email',
		'password',
		'phone_number',
		'profile_photo_path',
		'is_price_show',
		'is_subscribed',
		'is_billable',
		'is_affiliate',
		'affiliate_processing_percentage',
		'stripe_affiliate_percentage',
		'next_year_affiliate_processing_percentage',
		'next_year_stripe_affiliate_percentage',
		'stripe_destination_id',
		'status',
		'ip_address',
		'country',
		'region',
		'city',
		'latitude',
		'longitude',
		'timezone',
		'isp',
		'duration',
		'day_hours',
		'manager_id',
		'department_id',
		'seats',
		'created_by',
		'created_by_user_type',
		'updated_at',
		'updated_by',
		'updated_by_user_type',
		'deleted_at',
		'deleted_by',
		'deleted_by_user_type',
		'expire_in_days',
		'get_your_guide',
		'bokun',
		'is_ota',
		'ota_id', // Unique OTA reseller slug — immutable after creation, used for OCTO API affiliate lookup
		'min_pax', // Minimum pax allowed in a booking for this affiliate; null = no restriction
		'max_pax', // Maximum pax allowed in a booking for this affiliate; null = no restriction
		'ota_affiliate_percentage',
		'next_year_ota_affiliate_percentage',
		'reseller_id',
		'default_booking_source',
	];

	protected $hidden = [
		'password',
		'remember_token',
	];

	public function sendPasswordResetNotification($token)
	{
		$this->notify(new LinkForResettingPassword($token));
	}

	public function scopeIsAffiliate($query)
	{
		return $query->where('is_affiliate', 'Yes');
	}

	/**
	 * Query to get department assigned packages only
	 */
	public function scopeDepartmentPackage($query)
	{
		$departmentPackage = departmentPackage();
		return $query->when($departmentPackage, function ($query) {
			$user = Auth::user();
			return $user ? $query->where('department_id', $user->department_id) : $query;
		});
	}

	public $import = [

        'fields' => [
        	'start' 		=> ['displayName' => 'Start',],
        	'booking_id' 	=> ['displayName' => 'Booking ID',],
            'name' 			=> ['displayName' => 'Contact',],
            'phone_number' 	=> ['displayName' => 'Phone',],
            'email' 		=> ['displayName' => 'email',],
        ],

    ];

    public function promocodes()
    {
        return $this->belongsToMany(TourPromotion::class);
    }

    /**
     * Get the customer's location from stored database fields
     */
    public function getLocationAttribute()
    {
        // Return stored location data from database fields
        return [
            'ip' => $this->ip_address,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'isp' => $this->isp
        ];
    }

    /**
     * Get formatted address string for the customer from stored data
     */
    public function getFormattedLocationAttribute()
    {
        $parts = [];
        
        if (!empty($this->city) && $this->city !== 'Unknown') {
            $parts[] = $this->city;
        }
        
        if (!empty($this->region) && $this->region !== 'Unknown') {
            $parts[] = $this->region;
        }
        
        if (!empty($this->country) && $this->country !== 'Unknown') {
            $parts[] = $this->country;
        }

        return !empty($parts) ? implode(', ', $parts) : 'Unknown Location';
    }

    /**
     * Refresh location data from IP address
     */
    public function refreshLocationData()
    {
        if ($this->ip_address) {
            return IpGeolocationUtils::updateCustomerLocationFromIp($this->id, $this->ip_address);
        }
        return null;
    }
}
