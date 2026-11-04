<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WebhookAudit extends Model
{
    use HasFactory;

    protected $table = 'webhook_audit';

    protected $fillable = [
        'event_id',
        'event_type',
        'object_id',
        'payload',
        'signature',
        'processing_status',
        'processing_notes',
        'received_at',
        'processed_at',
        'tenant_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Create a new webhook audit record.
     * 
     * @param array $eventData The Stripe event data
     * @param string $signature The webhook signature
     * @param string $tenantId The tenant ID
     * @return self
     */
    public static function createFromWebhook(array $eventData, string $signature, string $tenantId): self
    {
        return self::create([
            'event_id' => $eventData['id'],
            'event_type' => $eventData['type'],
            'object_id' => $eventData['data']['object']['id'] ?? null,
            'payload' => $eventData,
            'signature' => $signature,
            'processing_status' => 'pending',
            'received_at' => now(),
            'tenant_id' => $tenantId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Mark the webhook as processed successfully.
     * 
     * @param string|null $notes Optional processing notes
     * @return bool
     */
    public function markAsProcessed(?string $notes = null): bool
    {
        return $this->update([
            'processing_status' => 'processed',
            'processing_notes' => $notes,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark the webhook as failed.
     * 
     * @param string $errorMessage The error message
     * @return bool
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'processing_status' => 'failed',
            'processing_notes' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark the webhook as skipped.
     * 
     * @param string $reason The reason for skipping
     * @return bool
     */
    public function markAsSkipped(string $reason): bool
    {
        return $this->update([
            'processing_status' => 'skipped',
            'processing_notes' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Check if this webhook has already been processed.
     * 
     * @param string $eventId The Stripe event ID
     * @return bool
     */
    public static function isAlreadyProcessed(string $eventId): bool
    {
        // Any existing row means we have seen this event before — whether it
        // succeeded, was skipped, failed, or is still pending. Checking only
        // 'processed'/'skipped' lets a second delivery through while the first
        // is still 'pending', causing a unique-constraint violation on insert.
        return self::where('event_id', $eventId)->exists();
    }

    /**
     * Get the processing duration in seconds.
     * 
     * @return int|null
     */
    public function getProcessingDurationAttribute(): ?int
    {
        if (!$this->processed_at || !$this->received_at) {
            return null;
        }

        return $this->received_at->diffInSeconds($this->processed_at);
    }

    /**
     * Get the status with human-readable format.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->processing_status) {
            'pending' => 'Pending',
            'processed' => 'Processed',
            'failed' => 'Failed',
            'skipped' => 'Skipped',
            default => ucfirst($this->processing_status)
        };
    }

    /**
     * Get the CSS class for status styling.
     */
    public function getStatusCssClassAttribute(): string
    {
        return match($this->processing_status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'processed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'skipped' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by processing status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('processing_status', $status);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('received_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter dispute-related webhooks.
     */
    public function scopeDisputeEvents($query)
    {
        return $query->where('event_type', 'like', 'dispute.%');
    }

    /**
     * Scope to filter failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Scope to filter pending webhooks.
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }
}