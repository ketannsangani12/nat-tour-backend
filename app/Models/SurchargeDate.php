<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class SurchargeDate extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'surcharge_dates';

    protected $fillable = [
        'tour_package_id',
        'affiliate_id',
        'custom_date_name',
        'date_range_start',
        'date_range_end',
        'apply_on_slots',
        'priority'
    ];

	/**
     * Get the tour package associated with the custom date.
     */
    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

	/**
     * Get the tour package affiliate associated with the custom date.
     */
    public function affiliate()
    {
        return $this->belongsTo(Customer::class, 'affiliate_id');
    }

	/**
     * Get the tour package rates associated with the custom date.
     */
    public function tourPackageRates()
    {
        return $this->hasMany(TourPackageRate::class, 'custom_date_id');
    }

	/**
     * Get the rate slot mappings for the custom date.
     */
    public function rateSlotMappings()
    {
        return $this->hasMany(RateSlotMapping::class, 'custom_date_id');
    }

	/**
	 * Query to get department assigned packages only
	 */
	public function scopeDepartmentPackage($query)
	{
		$departmentPackage = departmentPackage();
		return $query->when($departmentPackage, function ($query) use ($departmentPackage) {
			return $query->whereIn('tour_package_id', $departmentPackage);
		});
	}
}
