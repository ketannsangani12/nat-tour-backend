<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class RateSlotMapping extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'rate_slot_mappings';

    protected $fillable = [
        'tour_package_id',
        'affiliate_id',
        'custom_date_id',
        'time_from',
        'time_to',
    ];

    /**
     * Get the tour package that owns the mapping.
     */
    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    /**
     * Get the tour package that owns the mapping.
     */
    public function affiliate()
    {
        return $this->belongsTo(Customer::class, 'affiliate_id');
    }

    /**
     * Get the custom date that owns the mapping.
     */
    public function surchargeDate()
    {
        return $this->belongsTo(SurchargeDate::class, 'custom_date_id');
    }
}
