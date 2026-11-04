<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class PackageAffiliateAssignment extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'package_affiliate_assignments';

	protected $fillable = [
		'package_id',
		'affiliate_id',
		'show_all_package_bookings',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
	];

	/**
	 * Get the package data using relationship.
	 * 
	 * @return BelongsTo
	 */
	public function package(): BelongsTo
	{
		return $this->belongsTo(TourPackage::class, 'package_id');
	}

	/**
	 * Get the affiliate data using relationship.
	 * 
	 * @return BelongsTo
	 */
	public function affiliate(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'affiliate_id');
	}

    /**
     * Auto fill the values of user and date fields
     */
	protected static function booted()
	{
		static::deleting(function($assignment) {
			$assignment->deleted_by = auth()->user()->id;
			$assignment->deleted_by_user_type = userType();
			$assignment->save();
	   	});
	}
}
