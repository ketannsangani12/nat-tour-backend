<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutApprovalLog extends Model
{
    use HasFactory;

    protected $table = 'payout_approval_logs';

    // 'created_at' must stay fillable: $timestamps is false, so the callers pass
    // it explicitly (now(), in the app's America/Phoenix zone). Dropping it from
    // this list does not fall back to Laravel -- it lets mass-assignment silently
    // discard the value and MySQL's DEFAULT CURRENT_TIMESTAMP write UTC instead,
    // which the 'datetime' cast below then reads back as Phoenix, 7h in the future.
    protected $fillable = [
        'pending_payout_id',
        'action',
        'user_id',
        'ip_address',
        'user_agent',
        'notes',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the pending payout that owns this log.
     */
    public function pendingPayout(): BelongsTo
    {
        return $this->belongsTo(PendingPayout::class);
    }

    /**
     * Get the user who performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    /**
     * Get the formatted action for display.
     */
    public function getFormattedActionAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get the user name or system for display.
     */
    public function getActorNameAttribute(): string
    {
        return $this->user ? $this->user->name : 'System';
    }

    /**
     * Scope a query to only include logs for a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs by a specific user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}