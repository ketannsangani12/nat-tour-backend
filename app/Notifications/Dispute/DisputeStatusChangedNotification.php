<?php

namespace App\Notifications\Dispute;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Dispute $dispute;
    protected string $oldStatus;
    protected string $newStatus;
    protected array $metadata;

    /**
     * Create a new notification instance.
     */
    public function __construct(Dispute $dispute, string $oldStatus, string $newStatus, array $metadata = [])
    {
        $this->dispute = $dispute;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->metadata = $metadata;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // return ['mail', 'database']; // Commented out to stop email notifications
        return ['database']; // Only database notifications, no emails
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        
        return (new MailMessage)
            ->subject($subject)
            ->view('mail.dispute.status-changed', [
                'dispute'     => $this->dispute,
                'oldStatus'   => $this->oldStatus,
                'newStatus'   => $this->newStatus,
                'metadata'    => $this->metadata,
                'dispute_url' => $this->resolveDisputeUrl($notifiable),
            ]);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_status_changed',
            'dispute_id' => $this->dispute->id,
            'stripe_dispute_id' => $this->dispute->stripe_dispute_id,
            'title' => $this->getNotificationTitle(),
            'message' => "Dispute {$this->dispute->stripe_dispute_id} status changed from {$this->getStatusLabel($this->oldStatus)} to {$this->getStatusLabel($this->newStatus)}",
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'amount' => $this->dispute->amount,
            'currency' => $this->dispute->currency,
            'booking_id' => $this->dispute->booking_id,
            'route' => route(prefixedRouteName('disputes.show'), $this->dispute->id),
            'urgency' => $this->getUrgencyLevel(),
            'metadata' => $this->metadata,
            'created_at' => now()->toISOString()
        ];
    }

    /**
     * Get email subject based on status change.
     */
    protected function getEmailSubject(): string
    {
        $statusIcon = $this->getStatusIcon($this->newStatus);
        
        return match($this->newStatus) {
            'won' => "✅ Dispute Won - {$this->dispute->stripe_dispute_id}",
            'lost' => "❌ Dispute Lost - {$this->dispute->stripe_dispute_id}",
            'charge_refunded' => "💰 Dispute Charge Refunded - {$this->dispute->stripe_dispute_id}",
            'under_review' => "🔍 Dispute Under Review - {$this->dispute->stripe_dispute_id}",
            default => "{$statusIcon} Dispute Status Updated - {$this->dispute->stripe_dispute_id}"
        };
    }

    /**
     * Get notification title for database storage.
     */
    protected function getNotificationTitle(): string
    {
        return match($this->newStatus) {
            'won' => 'Dispute Won',
            'lost' => 'Dispute Lost',
            'charge_refunded' => 'Dispute Charge Refunded',
            'under_review' => 'Dispute Under Review',
            default => 'Dispute Status Updated'
        };
    }

    /**
     * Get status-specific message for email content.
     */
    protected function getStatusSpecificMessage(): string
    {
        return match($this->newStatus) {
            'won' => '🎉 **Great news!** You have successfully won this dispute. The funds will remain in your account and no chargeback fee will be applied.',
            
            'lost' => '😞 **Unfortunately**, this dispute was not resolved in your favor. The disputed amount has been refunded to the customer, and a chargeback fee may apply. Please review the case details to understand the decision.',
            
            'charge_refunded' => '💰 **The charge has been refunded** as part of the dispute resolution. This may have been done proactively or as part of the dispute process.',
            
            'under_review' => '🔍 **Your evidence is being reviewed** by the card issuer. You will be notified once a decision has been made. No further action is required at this time.',
            
            'warning_needs_response' => '⚠️ **Action Required**: This dispute requires evidence submission. Please review and submit your response before the deadline.',
            
            default => 'The dispute status has been updated. Please review the current status and take any necessary actions.'
        };
    }

    /**
     * Get status icon for display.
     */
    protected function getStatusIcon(string $status): string
    {
        return match($status) {
            'won' => '✅',
            'lost' => '❌',
            'charge_refunded' => '💰',
            'under_review' => '🔍',
            'warning_needs_response' => '⚠️',
            default => '📋'
        };
    }

    /**
     * Get human-readable status label.
     */
    protected function getStatusLabel(string $status): string
    {
        return match($status) {
            'warning_needs_response' => 'Needs Response',
            'under_review' => 'Under Review',
            'charge_refunded' => 'Charge Refunded',
            'lost' => 'Lost',
            'won' => 'Won',
            default => ucfirst(str_replace('_', ' ', $status))
        };
    }

    /**
     * Get urgency level based on new status.
     */
    protected function getUrgencyLevel(): string
    {
        return match($this->newStatus) {
            'warning_needs_response' => 'high',
            'won', 'lost', 'charge_refunded' => 'medium',
            'under_review' => 'low',
            default => 'low'
        };
    }

    /**
     * Get the URL to view the dispute.
     */
    protected function resolveDisputeUrl(object $notifiable): string
    {
        $prefix = $notifiable instanceof \App\Models\Admin ? 'admin' : 'tour-operator';
        return route("{$prefix}.disputes.show", $this->dispute->id);
    }
}