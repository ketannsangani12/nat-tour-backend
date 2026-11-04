<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourBookingPackageSwipeHistory extends Model implements Auditable
{
	use HasFactory, AuditTrait;

	protected $table = 'tour_booking_package_swipe_history';
	protected $fillable = [
		'tour_booking_id',
		'old_tour_package_id',
		'new_tour_package_id',
		'tourists',
		'payment_method'
	];

	public function tourBooking()
	{
		return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id');
	}

	public function oldTourPackage()
	{
		return $this->belongsTo(TourPackage::class, 'old_tour_package_id', 'id');
	}

	public function newTourPackage()
	{
		return $this->belongsTo(TourPackage::class, 'new_tour_package_id', 'id');
	}
}
