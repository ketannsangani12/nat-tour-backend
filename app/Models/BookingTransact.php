<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class BookingTransact extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'tour_bookings_transact';

    protected $fillable = [
    	'tour_booking_id',
    	'tour_package_rate_id',
    	'tourists',
		'is_infant_rate',
		'is_cash_rate',
    	'processing_charge',
    	'additional_tax_percentage',
    	'processing_charge_percentage',
		'nat_fees_percentage',
		'stripe_fees_percentage',
		'partner_fees_type',
		'partner_fees_percentage',
		'partner_fees_amount',
		'permit_fee',
    	'additional_charge',
    	'state_tax',
    	'rate',
    	'total',
    	'check_in_status',
		'is_group_rate',
    	'created_by',
    	'created_by_user_type',
    	'updated_by',
    	'updated_by_user_type',
    	'deleted_by',
    	'deleted_by_user_type',
    ];

	/**
	 * Get the package rate from booking transaction
	 *
	 * @return BelongsTo
	 */
	public function packageRate(): BelongsTo
	{
		return $this->belongsTo(TourPackageRate::class, 'tour_package_rate_id', 'id', BelongsTo::class);
	}
}
