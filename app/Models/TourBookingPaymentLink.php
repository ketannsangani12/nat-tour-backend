<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourBookingPaymentLink extends Model
{
    use HasFactory;

    protected $table = 'tour_booking_payment_links';

	protected $fillable = [
		'order_id',
		'section',
		'amount',
		'reason_of_payment',
		'email',
		'phone_number',
        'token',
        'status',
        'override_status',
        'expired_at',
        'created_by',
        'created_by_user_type'
	];

    /**
     * Summary of tourBooking
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
	public function tourBooking(): BelongsTo
	{
		return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id', BelongsTo::class);
	}
}