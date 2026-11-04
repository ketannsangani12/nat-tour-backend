<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\Payments\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourOperator extends Model implements Auditable
{
	use SoftDeletes, HasFactory, AuditTrait;

	protected $fillable = [
		'tenant_id',
		'shortdesc',
		'city_id',
		'name',
		'email',
		'phone_number',
		'website',
		'description',
		'cancellation_policy',
		'address',
		'postal_code',
		'timezone',
		'logo',
		'logo_navigation',
		'logo_print',
		'social_media_urls',
		'seo_title',
		'seo_description',
		'seo_keywords',
		'reports',
		'is_affiliate',
		'statement_descriptor',
		'card_reader_prefix',
		'cancellation_within_days',
		'checkins',
		'status',
		'stripe_destination_id',
		'stripe_terminal_location_id',
		'tripadvisor_supplier_id',
		'tripadvisor_reseller_id',
		'get_your_guide_supplier_id',
		'stripe_invoice_enabled',
		'supplier_id',
		'custom_checkout_terms',
		'created_by',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
	];

	protected $casts = [
		'custom_checkout_terms' => 'array',
	];

	public function city()
	{
		return $this->belongsTo(City::class);
	}

	public function packages()
	{
		return $this->hasMany(TourPackage::class, 'tour_operator_id');
	}

	protected static function booted()
	{
		static::creating(function ($tourOperator) {
			$tourOperator->created_by = Auth::id();
			$tourOperator->updated_at = null;
		});

		static::updating(function ($tourOperator) {
			$tourOperator->updated_by = Auth::id();
			$tourOperator->updated_by_user_type = session('userType');
		});

		static::saved(function ($tourOperator) {
			if (empty($tourOperator->stripe_destination_id)) {
				return;
			}

			$descriptor = $tourOperator->statement_descriptor
				?: strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $tourOperator->name));

			// Stripe requires: 5-22 chars, at least one letter, no < > \ ' " *
			$descriptor = substr(preg_replace('/[<>\\\'"*]/', '', $descriptor), 0, 22);
			$descriptor = str_pad($descriptor, 5, '.');

			if (!preg_match('/[A-Za-z]/', $descriptor)) {
				return;
			}

			try {
				$accountParams = [
					'settings' => [
						'payments' => [
							'statement_descriptor' => $descriptor,
						],
						'card_payments' => [
							'statement_descriptor_prefix' => substr($descriptor, 0, 10),
						],
					],
				];
				app(PaymentGatewayInterface::class)->updateAccount($tourOperator->stripe_destination_id, $accountParams);
			} catch (Exception $e) {
				Log::error("Failed to sync statement_descriptor to {$tourOperator->stripe_destination_id}: " . $e->getMessage());
			}
		});
	}

	/**
	 * Check if the tour operator is affiliate or not
	 */
	public function scopeIsAffiliate($query)
	{
		return $query->whereNotNull('is_affiliate');
	}
}
