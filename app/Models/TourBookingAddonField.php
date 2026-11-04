<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourBookingAddonField extends Model
{
    use SoftDeletes;
    protected $table = 'tour_booking_addon_fields';

    protected $fillable = [
        'tour_booking_id',
        'form_id',
        'field_id',
        'field_label',
        'option_id',
        'option_label',
        'rule_id',
        'parent_id',
        'value',
        'pax_id',
        'pax_index',
        'person_ordinal',
        'unit_type',
        'unit',
        'category_id',
        'category_name',
        'package_id',
        'package_name',
        'applied_rule_snapshot',
        'operation_at',
        'operation_by',
        'operation_by_user_type',
    ];

    // Cast JSON column to array for easy access
    protected $casts = [
        'applied_rule_snapshot' => 'array',
        'operation_at' => 'datetime',
    ];

    // relationships (optional but useful)
    public function field()
    {
        return $this->belongsTo(AddonField::class, 'field_id');
    }

    public function option()
    {
        return $this->belongsTo(AddonFieldOption::class, 'option_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AddonCategory::class, 'category_id');
    }

    public function linkedPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'package_id');
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(TourBookingAddonTransaction::class, 'tour_booking_addon_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TourBookingAddonField::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TourBookingAddonField::class, 'parent_id');
    }
}
