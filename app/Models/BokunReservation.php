<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BokunReservation extends Model
{
    use HasFactory;

    protected $table = 'bokun_reservations';

    protected $fillable = [
        'tour_package_id',
        'tour_slot_id',
        'product_code',
        'reservation_code',
        'date',
        'time',
        'seats',
        'infants',
        'expired_at'
    ];
}