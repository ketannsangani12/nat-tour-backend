<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCheckoutTerm extends Model
{
    protected $table = 'booking_checkout_terms';

    protected $fillable = [
        'tour_booking_id',
        'term_key',
        'term_label',
        'is_required',
        'customer_response',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'customer_response' => 'boolean',
    ];

    /**
     * Get the booking that owns this checkout term.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id');
    }
}
