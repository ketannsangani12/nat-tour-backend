<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitFeeRefundQueue extends Model
{
    protected $table = 'permit_fee_refund_queue';

    protected $fillable = [
        'booking_id',
        'package_id',
        'year',
        'tour_date',
        'tourists',
        'total_amount',
        'rate_groups',
        'status',
        'error_message',
        'processed_at',
        'processed_by'
    ];

    protected $casts = [
        'rate_groups' => 'array',
        'tour_date' => 'date',
        'processed_at' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    public function booking()
    {
        return $this->belongsTo(\App\Models\TourBooking::class, 'booking_id');
    }

    public function package()
    {
        return $this->belongsTo(\App\Models\TourPackage::class, 'package_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(\App\Models\Admin::class, 'processed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
