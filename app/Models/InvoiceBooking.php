<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class InvoiceBooking extends Model implements Auditable
{
	use HasFactory, AuditTrait;

	protected $table = 'invoice_bookings';
	protected $fillable = [
		'tour_booking_id',
		'invoice_id',
		'created_by'
	];
}
