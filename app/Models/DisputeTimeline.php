<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class DisputeTimeline extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'dispute_timeline';

    public $timestamps = false;

    protected $fillable = [
        'dispute_id',
        'event_type',
        'event_data',
        'user_id',
        'user_type',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the dispute this timeline entry belongs to.
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    /**
     * Get the user who performed this action (polymorphic relationship).
     */
    public function user()
    {
        return match($this->user_type) {
            'Admin' => $this->belongsTo(Admin::class, 'user_id'),
            'Tour Operator' => $this->belongsTo(Staff::class, 'user_id'),
            'Customer' => $this->belongsTo(Customer::class, 'user_id'),
            'Affiliate' => $this->belongsTo(Customer::class, 'user_id'),
            default => null
        };
    }

    /**
     * Get the event type with human-readable format.
     */
    public function getEventLabelAttribute(): string
    {
        return match($this->event_type) {
            'dispute_created' => 'Dispute Created',
            'dispute_updated' => 'Dispute Updated',
            'dispute_closed' => 'Dispute Closed',
            'evidence_submitted' => 'Evidence Submitted',
            'evidence_uploaded' => 'Evidence Uploaded',
            'status_changed' => 'Status Changed',
            'webhook_received' => 'Webhook Received',
            'notification_sent' => 'Notification Sent',
            'user_action' => 'User Action',
            'system_action' => 'System Action',
            default => ucfirst(str_replace('_', ' ', $this->event_type))
        };
    }

    /**
     * Get the event description based on event type and data.
     */
    public function getDescriptionAttribute(): string
    {
        $data = $this->event_data ?? [];
        
        return match($this->event_type) {
            'dispute_created' => 'Dispute was created from Stripe webhook',
            'dispute_updated' => 'Dispute information was updated' . 
                (isset($data['changes']) ? ' (' . implode(', ', array_keys($data['changes'])) . ')' : ''),
            'dispute_closed' => 'Dispute was closed with status: ' . ($data['final_status'] ?? 'unknown'),
            'evidence_submitted' => 'Evidence was submitted to Stripe' . 
                (isset($data['evidence_count']) ? ' (' . $data['evidence_count'] . ' files)' : ''),
            'evidence_uploaded' => 'Evidence file was uploaded: ' . ($data['file_name'] ?? 'unknown file'),
            'status_changed' => 'Status changed from ' . ($data['old_status'] ?? 'unknown') . 
                ' to ' . ($data['new_status'] ?? 'unknown'),
            'webhook_received' => 'Received webhook: ' . ($data['webhook_type'] ?? 'unknown type'),
            'notification_sent' => 'Notification sent: ' . ($data['notification_type'] ?? 'unknown type'),
            'user_action' => $data['action_description'] ?? 'User performed an action',
            'system_action' => $data['action_description'] ?? 'System performed an action',
            default => 'Event occurred'
        };
    }

    /**
     * Get the CSS class for event type styling.
     */
    public function getEventCssClassAttribute(): string
    {
        return match($this->event_type) {
            'dispute_created' => 'bg-blue-100 text-blue-800',
            'dispute_updated' => 'bg-yellow-100 text-yellow-800',
            'dispute_closed' => 'bg-gray-100 text-gray-800',
            'evidence_submitted' => 'bg-green-100 text-green-800',
            'evidence_uploaded' => 'bg-indigo-100 text-indigo-800',
            'status_changed' => 'bg-purple-100 text-purple-800',
            'webhook_received' => 'bg-blue-100 text-blue-800',
            'notification_sent' => 'bg-orange-100 text-orange-800',
            'user_action' => 'bg-green-100 text-green-800',
            'system_action' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Get the icon for the event type.
     */
    public function getEventIconAttribute(): string
    {
        return match($this->event_type) {
            'dispute_created' => 'exclamation-triangle',
            'dispute_updated' => 'pencil',
            'dispute_closed' => 'check-circle',
            'evidence_submitted' => 'upload',
            'evidence_uploaded' => 'document-add',
            'status_changed' => 'refresh',
            'webhook_received' => 'lightning-bolt',
            'notification_sent' => 'mail',
            'user_action' => 'user',
            'system_action' => 'cog',
            default => 'information-circle'
        };
    }

    /**
     * Scope to filter timeline by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter timeline by user type.
     */
    public function scopeByUserType($query, string $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope to filter timeline by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Create a timeline entry for dispute creation.
     */
    public static function logDisputeCreated(Dispute $dispute, array $additionalData = []): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'dispute_created',
            'event_data' => array_merge([
                'stripe_dispute_id' => $dispute->stripe_dispute_id,
                'amount' => $dispute->amount,
                'reason' => $dispute->reason,
                'status' => $dispute->status,
            ], $additionalData),
            'user_type' => 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for evidence submission.
     */
    public static function logEvidenceSubmitted(Dispute $dispute, int $evidenceCount, ?int $userId = null, ?string $userType = null): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'evidence_submitted',
            'event_data' => [
                'evidence_count' => $evidenceCount,
            ],
            'user_id' => $userId ?? auth()->id(),
            'user_type' => $userType ?? (session('userType') ?? 'Tour Operator'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for status change.
     */
    public static function logStatusChange(Dispute $dispute, string $oldStatus, string $newStatus, array $metadata = []): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'status_changed',
            'event_data' => array_merge([
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ], $metadata),
            'user_id' => auth()->id(),
            'user_type' => session('userType') ?? 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for evidence submission.
     */
    public static function logEvidenceSubmission(Dispute $dispute, array $evidenceData): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'evidence_submitted',
            'event_data' => $evidenceData,
            'user_id' => auth()->id(),
            'user_type' => session('userType') ?? 'Tour Operator',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for dispute synchronization.
     */
    public static function logSynchronization(Dispute $dispute, array $syncData): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'dispute_updated',
            'event_data' => array_merge([
                'sync_type' => 'stripe_api',
            ], $syncData),
            'user_type' => 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for dispute closure.
     */
    public static function logDisputeClosure(Dispute $dispute, array $closureData): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'dispute_closed',
            'event_data' => array_merge([
                'final_status' => $dispute->status,
                'closure_source' => 'stripe_webhook',
            ], $closureData),
            'user_type' => 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for webhook received events.
     */
    public static function logWebhookReceived(Dispute $dispute, array $webhookData): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'webhook_received',
            'event_data' => $webhookData,
            'user_type' => 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a timeline entry for system actions.
     */
    public static function logSystemAction(Dispute $dispute, array $actionData): self
    {
        return self::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'system_action',
            'event_data' => $actionData,
            'user_type' => 'System',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}