<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class DisputeEvidence extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'dispute_evidence';

    protected $fillable = [
        'dispute_id',
        'evidence_type',
        'file_path',
        'file_name',
        'file_size',
        'content_type',
        'stripe_file_id',
        'submitted_at',
        'created_by',
        'created_by_user_type',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'file_size' => 'integer',
    ];

    /**
     * Get the dispute this evidence belongs to.
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    /**
     * Get the user who created this evidence (polymorphic relationship).
     */
    public function creator()
    {
        return match($this->created_by_user_type) {
            'Admin' => $this->belongsTo(Admin::class, 'created_by'),
            'Tour Operator' => $this->belongsTo(Staff::class, 'created_by'),
            'Customer' => $this->belongsTo(Customer::class, 'created_by'),
            'Affiliate' => $this->belongsTo(Customer::class, 'created_by'),
            default => null
        };
    }

    /**
     * Get the file URL for this evidence.
     */
    public function getFileUrl(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk('s3')->url($this->file_path);
    }

    /**
     * Get the file download URL for this evidence.
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return route('admin.disputes.evidence.download', [
            'disputeId' => $this->dispute_id,
            'evidenceId' => $this->id
        ]);
    }

    /**
     * Check if this evidence has been submitted to Stripe.
     */
    public function isSubmittedToStripe(): bool
    {
        return !is_null($this->stripe_file_id) && !is_null($this->submitted_at);
    }

    /**
     * Get the evidence type with human-readable format.
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->evidence_type) {
            'receipt' => 'Receipt',
            'shipping_documentation' => 'Shipping Documentation',
            'duplicate_charge_documentation' => 'Duplicate Charge Documentation',
            'product_or_service_description' => 'Product/Service Description',
            'customer_communication' => 'Customer Communication',
            'refund_policy' => 'Refund Policy',
            'cancellation_policy' => 'Cancellation Policy',
            'customer_signature' => 'Customer Signature',
            'uncategorized_file' => 'Other Documentation',
            default => ucfirst(str_replace('_', ' ', $this->evidence_type))
        };
    }

    /**
     * Get the file size in human-readable format.
     */
    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the file exists in storage.
     */
    public function fileExists(): bool
    {
        if (!$this->file_path) {
            return false;
        }

        return Storage::disk('s3')->exists($this->file_path);
    }

    /**
     * Get the file icon based on content type.
     */
    public function getFileIconAttribute(): string
    {
        if (!$this->content_type) {
            return 'document';
        }

        return match(true) {
            str_contains($this->content_type, 'pdf') => 'document-pdf',
            str_contains($this->content_type, 'image') => 'photograph',
            str_contains($this->content_type, 'word') => 'document-text',
            str_contains($this->content_type, 'excel') => 'table',
            default => 'document'
        };
    }

    /**
     * Scope to filter evidence by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('evidence_type', $type);
    }

    /**
     * Scope to filter submitted evidence.
     */
    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('stripe_file_id')
                    ->whereNotNull('submitted_at');
    }

    /**
     * Scope to filter pending evidence.
     */
    public function scopePending($query)
    {
        return $query->whereNull('stripe_file_id')
                    ->orWhereNull('submitted_at');
    }
}