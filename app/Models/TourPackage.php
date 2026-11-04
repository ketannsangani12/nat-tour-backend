<?php

namespace App\Models;

use App\Constants\Constants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;
use Illuminate\Support\Facades\Auth;

class TourPackage extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $fillable = [
		'tour_operator_id',
		'name',
		'from_name',
		'from_email',
		'from_email_hotmail',
		'overbook_seats',
		'short_description',
		'long_description',
		'stripe_destination_id',
		'service_commission_percentage',
		'stripe_commission_percentage',
		'next_year_service_commission_percentage',
		'next_year_stripe_commission_percentage',
		'partner_commission_type',
		'partner_commission_subtype',
		'partner_commission_value',
		'hours',
		'minutes',
		'duration',
		'ota_duration',
		'day_hours',
		'block_ctb_duration',
		'ctb_description',
		'arrival_time_duration',
		'things_to_bring',
		'important_notes',
		'waiver_content',
		'school_waiver_content',
		'attach_waiver_pdf',
		'waiver_pdf',
		'cancellation',
		'seo_title',
		'seo_description',
		'seo_keywords',
		'status',
		'viator_tags',
		'productCode',
		'get_your_guide_product_code',
		'bokun_product_code',
		'Order_by',
		'pos_order',
		'frontend_enabled',
		'category_id',
		'type',
		'is_cross_tenant_package',
		'is_group_rate_enabled',
		'include_in_operation_board',
		'include_in_addons',
		'package_has_slots',
		'package_has_waiver',
		'package_has_permit',
		'is_partner_package',
		'disable_slot_overlap_blocking',
		'tax_applicable',
		'slot_close_on_booking',
		'show_seat_availability',
		'show_in_agenda',
		'bundle_package',
		'show_in_pos',
		'checkin',
		'checkin_type',
		'latitude',
		'longitude',
		'travel_duration',
		'package_colour',
		'has_contacts',
		'expire_in_days',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type'
	];

	public static function fillableFields()
	{
		return (new self)->fillable;
	}

	/**
	 * Origin packages this partner package is assembled from (Partner API),
	 * one PartnerCrossTenantOrigin row per origin. A single row is a cross-tenant
	 * passthrough resell; two or more rows is a combo. Each row carries the
	 * origin tenant/package, the origin's slot time_from/time_to, and an optional
	 * origin_customer_id for standard pricing / attribution.
	 */
	public function crossTenantOrigins()
	{
		return $this->hasMany(PartnerCrossTenantOrigin::class, 'package_id');
	}

	/**
	 * True when this package is a Partner-API cross-tenant package (a single
	 * origin, or a combo of two or more) that must fan bookings out to its
	 * origin tenants.
	 */
	public function isCrossTenantPartnerPackage(): bool
	{
		return $this->crossTenantOrigins()->exists();
	}

	public function tourOperator()
	{
		return $this->belongsTo(TourOperator::class, 'tour_operator_id', 'id');
	}

	public function families()
	{
		return $this->hasMany(TourPackageFamily::class, 'tour_package_id', 'id');
	}

	public function packageAffiliate()
	{
		return $this->hasMany(PackageAffiliateAssignment::class, 'package_id', 'id');
	}

	public function departments()
	{
		return $this->belongsToMany(Department::class, 'department_package', 'package_id', 'department_id');
	}

	protected static function booted()
	{
		static::creating(function ($tourPackage) {
			$tourOperator = tourOperator();
			$tourPackage->tour_operator_id = $tourOperator->id;
			$tourPackage->created_by = Auth::id() ?? 0;
			$tourPackage->created_by_user_type = session('userType');
			$tourPackage->updated_at = null;
		});
	}

	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}

	/**
	 * Query to get active packages only
	 */
	public function scopeActive($query)
	{
		return $query->where('status', Constants::STATUS_ACTIVE);
	}

	/**
	 * Query to get active packages only
	 */
	public function scopeIsComboPackage($query)
	{
		return $query->where('is_cross_tenant_package', Constants::STATUS_ONE);
	}

	/**
	 * Query to get department assigned packages only
	 */
	public function scopeDepartmentPackage($query)
	{
		$departmentPackage = departmentPackage();
		return $query->when($departmentPackage, function ($query) use ($departmentPackage) {
			return $query->whereIn('id', $departmentPackage);
		});
	}

	public function bundleChildren()
	{
		return $this->hasMany(TourPackageBundleRelation::class, 'parent_id', 'id');
	}
}
