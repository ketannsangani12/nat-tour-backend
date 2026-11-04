<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPackageRate extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;
	protected $table ="tour_package_rates";

	protected $fillable = [
		'affiliate_id',
		'tour_package_id',
		'custom_date_id',
		'rate_for',
		'age_from',
		'age_to',
		'rate',
		'primary_rate',
		'permit_fee',
		'partner_fee_percentage',
		'partner_fee_type',
		'partner_fee_amount',
		'description',
		'additional_tax_percentage',
		'processing_charge_percentage',
		'processing_charge',
		'additional_charge',
		'primary_additional_charge',
		'margin',
		'cash_rate',
		'cash_tax',
		'is_cash_rate',
		'scheduling_year',
		'status',
		'orderby',
		'mapping_key',
		'min_pax_allowed',
		'max_pax_allowed',
		'is_group_rate',
		'is_infant_rate',
		'min_group_size',
		'max_group_size',
		'global_rate',
		'approval',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
		'permit_fee',
	];

	/**
     * Get the tour operator associated with the tour package rate.
     */
	public function tourPackage()
	{
		return $this->belongsTo(TourPackage::class, 'tour_package_id');
	}

	public function affiliate()
	{
		return $this->belongsTo(Customer::class, 'affiliate_id');
	}

	/**
     * Get the rate slot mappings for the tour package rate.
     */
    public function rateSlotMappings()
    {
        return $this->hasMany(RateSlotMapping::class, 'tour_package_rate_id');
    }

	/**
     * Get the custom date associated with the tour package rate.
     */
    public function customDate()
    {
        return $this->belongsTo(SurchargeDate::class, 'custom_date_id');
    }

	/**
     * To prevent converting date-time in UTC when calling toArray().
     */
	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'rate' => 'float',
		'primary_rate' => 'float',
		'permit_fee' => 'float',
		'additional_tax_percentage' => 'float',
		'processing_charge_percentage' => 'float',
		'processing_charge' => 'float',
		'additional_charge' => 'float',
		'primary_additional_charge' => 'float',
		'partner_fee_percentage' => 'float',
		'partner_fee_amount' => 'float',
		'margin' => 'float',
	];
}
