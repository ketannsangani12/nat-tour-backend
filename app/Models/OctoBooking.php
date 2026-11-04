<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TourPackage;

/**
 * @property int         $id
 * @property string      $uuid
 * @property int         $affiliate_id
 * @property int         $tour_slot_id
 * @property int|null    $tour_package_id
 * @property int|null    $tour_booking_id
 * @property string      $status
 * @property int         $tourists
 * @property int         $infants
 * @property array       $unit_items
 * @property array|null  $contact
 * @property string|null $notes
 * @property Carbon      $expire_at
 * @property Carbon|null $confirmed_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class OctoBooking extends Model
{
    protected $table = 'octo_bookings';

    protected $fillable = [
        'uuid',
        'affiliate_id',
        'tour_slot_id',
        'tour_package_id',
        'tour_booking_id',
        'status',
        'tourists',
        'infants',
        'unit_items',
        'contact',
        'notes',
        'expire_at',
        'confirmed_at',
    ];

    protected $casts = [
        'unit_items'   => 'array',
        'contact'      => 'array',
        'expire_at'    => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function tourBooking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function isExpired(): bool
    {
        return $this->status === 'ON_HOLD' && ($this->expire_at?->isPast() ?? false);
    }
}
