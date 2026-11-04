<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-tenant linkage for a Partner API cross-tenant booking.
 *
 * @property string     $booking_uuid    the octo_bookings uuid the local hold reuses
 * @property int|null   $tour_package_id the partner (lc) package id
 * @property array|null $origin_bookings per-origin booking references
 * @property string     $status          CONFIRMED | CANCELLED | FAILED
 */
class PartnerCrossTenantBooking extends Model
{
    protected $table = 'partner_cross_tenant_bookings';

    protected $fillable = [
        'booking_uuid',
        'tour_package_id',
        'origin_bookings',
        'status',
    ];

    protected $casts = [
        'origin_bookings' => 'array',
    ];
}
