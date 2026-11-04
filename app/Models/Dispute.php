<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Dispute extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $fillable = [
        'stripe_dispute_id',
        'stripe_reversal_id',
        'booking_id',
        'transaction_id',
        'amount',
        'currency',
        'reason',
        'status',
        'evidence_due_by',
        'reversed_amount',
        'lost_at',
        'stripe_dispute_fee',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'evidence_due_by'    => 'datetime',
        'lost_at'            => 'datetime',
        'reversed_amount'    => 'integer',
        'stripe_dispute_fee' => 'integer',
    ];

    /**
     * Get the booking associated with this dispute.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'booking_id');
    }

    /**
     * Get the transaction associated with this dispute.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Get all evidence files for this dispute.
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class);
    }

    /**
     * Get the timeline events for this dispute.
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(DisputeTimeline::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if the dispute is overdue for evidence submission.
     */
    public function isOverdue(): bool
    {
        return $this->evidence_due_by && 
               $this->evidence_due_by->isPast() && 
               $this->status === 'warning_needs_response';
    }

    /**
     * Get the number of days until the evidence deadline.
     */
    public function getDaysUntilDeadline(): ?int
    {
        if (!$this->evidence_due_by) {
            return null;
        }
        
        return (int) round(now()->diffInDays($this->evidence_due_by, false));
    }

    /**
     * Check if the dispute requires immediate attention.
     */
    public function requiresAttention(): bool
    {
        if ($this->status !== 'warning_needs_response') {
            return false;
        }

        $daysUntilDeadline = $this->getDaysUntilDeadline();
        
        return $daysUntilDeadline !== null && $daysUntilDeadline <= 3;
    }

    /**
     * Get the dispute status with human-readable format.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'warning_needs_response' => 'Needs Response',
            'under_review' => 'Under Review',
            'charge_refunded' => 'Charge Refunded',
            'lost' => 'Lost',
            'won' => 'Won',
            default => ucfirst(str_replace('_', ' ', $this->status))
        };
    }

    /**
     * Get the dispute reason with human-readable format.
     */
    public function getReasonLabelAttribute(): string
    {
        return match($this->reason) {
            'fraudulent' => 'Fraudulent',
            'subscription_canceled' => 'Subscription Canceled',
            'product_unacceptable' => 'Product Unacceptable',
            'credit_not_processed' => 'Credit Not Processed',
            'duplicate' => 'Duplicate',
            'product_not_received' => 'Product Not Received',
            'unrecognized' => 'Unrecognized',
            'general' => 'General',
            default => ucfirst(str_replace('_', ' ', $this->reason))
        };
    }

    /**
     * Get the CSS class for status styling.
     */
    public function getStatusCssClassAttribute(): string
    {
        return match($this->status) {
            'warning_needs_response' => 'bg-red-100 text-red-800',
            'under_review' => 'bg-yellow-100 text-yellow-800',
            'charge_refunded' => 'bg-blue-100 text-blue-800',
            'lost' => 'bg-red-100 text-red-800',
            'won' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Scope to filter disputes by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter disputes by reason.
     */
    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    /**
     * Scope to filter disputes requiring attention.
     */
    public function scopeRequiringAttention($query)
    {
        return $query->where('status', 'warning_needs_response')
                    ->where('evidence_due_by', '<=', now()->addDays(3));
    }

    /**
     * Scope to filter overdue disputes.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'warning_needs_response')
                    ->where('evidence_due_by', '<', now());
    }

    /**
     * Scope to filter active disputes.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['warning_needs_response', 'under_review']);
    }
}