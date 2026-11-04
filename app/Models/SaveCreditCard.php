<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaveCreditCard extends Model
{
    protected $table = 'save_credit_card';

    protected $fillable = [
        'tour_booking_id',
        'customer_id',
        'payment_method_id',
        'email',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'request_from',
        'created_by',
        'created_by_user_type',
    ];
}
