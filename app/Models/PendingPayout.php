<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PendingPayout extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HasPayoutPeriod;

    protected $table = 'pending_payouts';

    protected $fillable = [
        'payout_reference',
        'rule_id',
        'stripe_account_id',
        'total_amount',
        'platform_fee',
        'net_amount',
        'booking_count',
        'period_start',
        'period_end',
        'status',
        'rule_snapshot',
        'calculation_data',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'retry_count',
        'last_failed_at',
        'last_failure_reason'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'rule_snapshot' => 'array',
        'calculation_data' => 'array',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'last_failed_at' => 'datetime'
    ];

    /**
     * Get the rule that owns this pending payout.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutoPayoutRule::class, 'rule_id');
    }

    /**
     * Get the payout items for this pending payout.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PendingPayoutItem::class);
    }

    /**
     * Get the user who approved this payout.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Get the user who rejected this payout.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'rejected_by');
    }

    /**
     * Get the approval logs for this payout.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PayoutApprovalLog::class);
    }

    /**
     * Scope a query to only include pending approval payouts.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    /**
     * Scope a query to only include approved payouts.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include rejected payouts.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope a query to only include executed payouts.
     */
    public function scopeExecuted($query)
    {
        return $query->where('status', 'executed');
    }

    /**
     * Scope a query to only include failed payouts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Check if the payout is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    /**
     * Check if the payout is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the payout is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the payout has been executed.
     */
    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    /**
     * Check if the payout has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the formatted payout reference for display.
     */
    public function getFormattedReferenceAttribute(): string
    {
        return strtoupper($this->payout_reference);
    }

    /**
     * Get the initials for the payout reference.
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(implode('', array_map(
            fn($word) => substr($word, 0, 1), 
            explode('-', $this->payout_reference)
        )));
    }
}