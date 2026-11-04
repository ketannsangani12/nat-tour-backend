<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class TourSlotBulkOperation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tour_slot_bulk_operations';

    /**
     * This table only has a created_at column (no updated_at), so disable updated_at handling.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'tour_package_id',
        'action',
        'from_date',
        'to_date',
        'slots',
        'payload',
        'total_slots',
        'status',
        'applied_at',
        'error',
        'created_by',
        'created_by_user_type',
        'updated_by',
        'updated_by_user_type',
        'deleted_by',
    ];

    protected $casts = [
        'slots'       => 'array',
        'payload'     => 'array',
        'total_slots' => 'integer',
        'from_date'   => 'date:Y-m-d',
        'to_date'     => 'date:Y-m-d',
        'applied_at'  => 'datetime',
        'created_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }

    protected static function booted()
    {
        // Record who soft-deleted the operation (matches the platform's deleted_by convention).
        static::deleting(function ($operation) {
            $operation->deleted_by = Auth::id() ?? 0;
            $operation->save();
        });
    }
}
