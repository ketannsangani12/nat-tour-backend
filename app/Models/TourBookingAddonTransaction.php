<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class TourBookingAddonTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tour_booking_addon_transactions';

    protected $guarded = []; // We use bulk insert, so allow mass assignment

    // Or be strict (recommended in production – uncomment if you prefer):
    // protected $fillable = [
    //     'tour_booking_addon_id', 'payment_method', 'status', 'operation',
    //     'transaction_id', 'application_fee_id', 'field_additional_fee',
    //     'option_additional_fee', 'field_price', 'field_fee',
    //     'option_price', 'option_fee', 'notes', 'operation_at',
    //     'operation_by', 'operation_by_user_type',
    // ];

    public $timestamps = true; // created_at & updated_at are present

    protected $casts = [
        'operation_at'           => 'datetime',
        'field_price'            => 'decimal:2',
        'field_fee'              => 'decimal:2',
        'option_price'           => 'decimal:2',
        'option_fee'             => 'decimal:2',
        'field_additional_fee'   => 'boolean',
        'option_additional_fee'  => 'boolean',
        'status'                 => 'string', // paid | open | refunded
        'operation_by'           => 'integer',
        'operation_group_id'     => 'string',
    ];

    // ===================================================================
    // Relationships
    // ===================================================================

    /**
     * The actual field record (priced or not)
     */
    public function addonField(): BelongsTo
    {
        return $this->belongsTo(TourBookingAddonField::class, 'tour_booking_addon_id');
    }

    /**
     * Booking this transaction belongs to (via the field)
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'tour_booking_id', 'id')
            ->via('addonField');
    }

    /**
     * Who performed the operation (polymorphic if needed later)
     */
    public function operator()
    {
        // Optional: later turn into polymorphic if you have Admin/TO/Customer models
        return $this->belongsTo(User::class, 'operation_by');
    }

    // ===================================================================
    // Scopes
    // ===================================================================

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeCard($query)
    {
        return $query->where('payment_method', 'card');
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', 'cash');
    }

    // ===================================================================
    // Accessors / Mutators (optional helpers)
    // ===================================================================

    public function getTotalAmountAttribute(): float
    {
        return $this->field_price + $this->field_fee + $this->option_price + $this->option_fee;
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->status === 'refunded';
    }
}
