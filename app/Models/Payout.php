<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
	use HasFactory;

	
	protected $table = 'payouts';

	protected $fillable = [
		'tour_booking_id',
		'transaction_id',
		'package_id',
		'stripe_account_id',
		'transfer_id',
		'total_amount',
		'commission_percentage',
		'commission_amount',
		'payout_amount',
		'payment_method',
        'charge_type',
        'payout_status',
        'created_by',
		'created_by_user_type'
	];

	public function tourBooking()
	{
		return $this->belongsTo(TourBooking::class);
	}
}
