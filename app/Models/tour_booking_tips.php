<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class tour_booking_tips extends Model implements Auditable
{
    use HasFactory, AuditTrait;
    protected $fillable = [
    	'tour_booking_id',
    	'tour_operator_staff_id',
        'amount',
        'transaction_number',
        'notes',
        'status',
        'date',
    	'created_by',
    	'created_by_user_type',
    	'deleted_by',
    	'deleted_by_user_type',
    ];
}
