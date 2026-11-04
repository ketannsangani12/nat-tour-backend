<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class AffilaiteSeatsUpdate extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = "affilaite_seats_updates";

    protected $fillable = [
    	'tour_booking_id',
    	'tour_package_id',
    	'tour_slot_id',
    	'customer_id',
    	'tourists',
    	'check_in_not',
    	'check_in_1st',
		'check_in_2nd',
		'absent',
    	'status',
    ];
}
