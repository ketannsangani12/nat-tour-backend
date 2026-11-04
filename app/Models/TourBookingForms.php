<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourBookingForms extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'tour_booking_forms';
	protected $fillable = [
		'tour_package_form_id',
		'tour_booking_id',
		'form_submission'
	];

	/**
	 * Get the tour booking data using relationship.
	 * @return BelongsTo
	 */
	public function tourPackage() : BelongsTo
	{
		return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id', BelongsTo::class);
	}

}
