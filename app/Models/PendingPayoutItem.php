<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingPayoutItem extends Model
{
    use HasFactory;

    protected $table = 'pending_payout_items';

    protected $fillable = [
        'pending_payout_id',
        'booking_id',
        'total_amount',
        'platform_fee',
        'net_amount'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2'
    ];

    /**
     * Get the pending payout that owns this item.
     */
    public function pendingPayout(): BelongsTo
    {
        return $this->belongsTo(PendingPayout::class);
    }

    /**
     * Get the booking associated with this payout item.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'booking_id');
    }

    /**
     * Get the formatted net amount for display.
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return number_format($this->net_amount, 2);
    }

    /**
     * Get the formatted total amount for display.
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format($this->total_amount, 2);
    }

    /**
     * Get the formatted platform fee for display.
     */
    public function getFormattedPlatformFeeAttribute(): string
    {
        return number_format($this->platform_fee, 2);
    }
}