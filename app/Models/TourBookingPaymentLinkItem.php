<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourBookingPaymentLinkItem extends Model
{
    use HasFactory;

    protected $table = 'tour_booking_payment_link_items';

    protected $fillable = [
        'payment_link_id',
        'type',
        'reference_id',
        'amount',
        'status',
        'details',
        'created_by',
        'created_by_user_type',
        'updated_by',
        'updated_by_user_type',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(TourBookingPaymentLink::class, 'payment_link_id', 'id');
    }
}
