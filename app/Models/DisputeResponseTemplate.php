<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class DisputeResponseTemplate extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $fillable = [
        'name',
        'dispute_reason',
        'template_content',
        'is_active',
        'created_by',
        'created_by_user_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this template (polymorphic relationship).
     */
    public function creator()
    {
        return match($this->created_by_user_type) {
            'Admin' => $this->belongsTo(Admin::class, 'created_by'),
            'Tour Operator' => $this->belongsTo(Staff::class, 'created_by'),
            default => $this->belongsTo(Admin::class, 'created_by')
        };
    }

    /**
     * Get the dispute reason with human-readable format.
     */
    public function getReasonLabelAttribute(): string
    {
        return match($this->dispute_reason) {
            'fraudulent' => 'Fraudulent',
            'subscription_canceled' => 'Subscription Canceled',
            'product_unacceptable' => 'Product Unacceptable',
            'credit_not_processed' => 'Credit Not Processed',
            'duplicate' => 'Duplicate',
            'product_not_received' => 'Product Not Received',
            'unrecognized' => 'Unrecognized',
            'general' => 'General',
            default => ucfirst(str_replace('_', ' ', $this->dispute_reason))
        };
    }

    /**
     * Apply template variables to the content.
     */
    public function applyVariables(array $variables): string
    {
        $content = $this->template_content;
        
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }

    /**
     * Get available template variables.
     */
    public static function getAvailableVariables(): array
    {
        return [
            'booking_reference' => 'Booking Reference Number',
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'tour_name' => 'Tour Package Name',
            'tour_date' => 'Tour Date',
            'tour_time' => 'Tour Time',
            'booking_amount' => 'Booking Amount',
            'payment_date' => 'Payment Date',
            'transaction_id' => 'Transaction ID',
            'dispute_amount' => 'Dispute Amount',
            'dispute_reason' => 'Dispute Reason',
            'company_name' => 'Company Name',
            'company_email' => 'Company Email',
            'company_phone' => 'Company Phone',
            'current_date' => 'Current Date',
        ];
    }

    /**
     * Extract variables from a booking for template application.
     */
    public static function extractBookingVariables(TourBooking $booking, ?Dispute $dispute = null): array
    {
        $variables = [
            'booking_reference' => $booking->reference_booking ?? $booking->id,
            'customer_name' => $booking->name,
            'customer_email' => $booking->email,
            'tour_name' => $booking->tourPackage?->name ?? 'Unknown Tour',
            'tour_date' => $booking->tourSlotTime?->date ?? 'Unknown Date',
            'tour_time' => $booking->tourSlotTime?->time ?? 'Unknown Time',
            'booking_amount' => '$' . number_format($booking->total, 2),
            'payment_date' => $booking->created_at->format('M j, Y'),
            'transaction_id' => $booking->transactions->first()?->transaction_number ?? 'N/A',
            'current_date' => now()->format('M j, Y'),
        ];

        if ($dispute) {
            $variables['dispute_amount'] = '$' . number_format($dispute->amount, 2);
            $variables['dispute_reason'] = $dispute->reason_label;
        }

        // Add company information if available
        // This would typically come from tenant settings or tour operator data
        $variables['company_name'] = 'Tour Company'; // TODO: Get from tenant settings
        $variables['company_email'] = 'support@tourcompany.com'; // TODO: Get from tenant settings
        $variables['company_phone'] = '(555) 123-4567'; // TODO: Get from tenant settings

        return $variables;
    }

    /**
     * Scope to filter active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter templates by dispute reason.
     */
    public function scopeForReason($query, string $reason)
    {
        return $query->where('dispute_reason', $reason);
    }

    /**
     * Scope to filter templates by creator.
     */
    public function scopeByCreator($query, int $creatorId, string $creatorType)
    {
        return $query->where('created_by', $creatorId)
                    ->where('created_by_user_type', $creatorType);
    }
}