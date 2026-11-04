<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class DiscountHistory extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'discount_history';

    protected $fillable = [
        'tour_booking_id',
        'old_tour_promotion_id',
        'new_tour_promotion_id',
        'old_discount2_value',
        'new_discount2_value',
        'old_discount2_percentage',
        'new_discount2_percentage',
        'old_discount_promo_code',
        'new_discount_promo_code',
        'service_commission',
        'rate',
        'old_total',
        'new_total',
        'discount_type',
        'payment_method',
        'created_at',
        'created_by',
        'created_by_user_type',
        'updated_at',
        'updated_by',
        'payment_type',
        'updated_by_user_type',
        'deleted_at',
        'deleted_by',
        'deleted_by_user_type',
    ];
}
