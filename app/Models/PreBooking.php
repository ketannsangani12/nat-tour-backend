<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreBooking extends Model
{
    use HasFactory;

    protected $table = 'pre_bookings';

    protected $fillable = [
        'payment_intent_id',
        'stripe_customer_id',
        'data',
        'status',
        'attempts',
    ];

    protected $casts = [
        'data' => 'array', // Automatically decode/encode JSON
    ];
}
