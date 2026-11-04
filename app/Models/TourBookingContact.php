<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourBookingContact extends Model
{
    use HasFactory;

    protected $table = 'tour_booking_contacts';

	protected $fillable = [
		'tour_booking_id',
		'tour_booking_transact_id',
		'name',
		'age',
		'weight',
		'note'
	];

    /**
     * Summary of tourBooking
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
	public function tourBooking(): BelongsTo
	{
		return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id', BelongsTo::class);
	}

    /**
     * Summary of bookingTransact
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
	public function bookingTransact(): BelongsTo
	{
		return $this->belongsTo(BookingTransact::class, 'tour_booking_transact_id', 'id', BelongsTo::class);
	}
}
