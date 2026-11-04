<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One origin package (living in another tenant) that a local Partner-API package
 * is assembled from. One row = single-package passthrough; two or more rows for
 * the same package_id = a combo.
 *
 * time_from / time_to mirror the origin's tour_slot_times columns; the engine
 * matches the origin's bookable slot on the booking date at time_from. All
 * origins book on the same date as the local booking. buffer_time_minutes is a
 * guest-facing reference only and is not used in booking logic.
 *
 * @property int         $package_id          local (lc) package id
 * @property int         $origin_package_id
 * @property string      $origin_tenant
 * @property int|null    $origin_customer_id  standard pricing / attribution
 * @property string      $time_from
 * @property string|null $time_to
 * @property int         $buffer_time_minutes
 */
class PartnerCrossTenantOrigin extends Model
{
    protected $table = 'partner_cross_tenant_origins';

    protected $fillable = [
        'package_id',
        'origin_package_id',
        'origin_tenant',
        'origin_customer_id',
        'local_time_from',
        'time_from',
        'time_to',
        'buffer_time_minutes',
    ];

    protected $casts = [
        'package_id'          => 'integer',
        'origin_package_id'   => 'integer',
        'origin_customer_id'  => 'integer',
        'buffer_time_minutes' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(TourPackage::class, 'package_id', 'id');
    }
}
