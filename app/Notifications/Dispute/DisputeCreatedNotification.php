<?php

namespace App\Notifications\Dispute;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Dispute $dispute;

    /**
     * Create a new notification instance.
     */
    public function __construct(Dispute $dispute)
    {
        $this->dispute = $dispute;
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
        $templateService = app(\App\Services\Dispute\DisputeTemplateService::class);
        $templateVars = $templateService->getTemplateVariables(
            $this->dispute, 
            'created', 
            ['urgency' => $this->getUrgencyLevel()]
        );

        $templateVars['dispute_url'] = $this->resolveDisputeUrl($notifiable);

        return (new MailMessage)
            ->subject($templateVars['customized_subject'])
            ->view('mail.dispute.created', $templateVars);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_created',
            'dispute_id' => $this->dispute->id,
            'stripe_dispute_id' => $this->dispute->stripe_dispute_id,
            'title' => 'New Payment Dispute',
            'message' => "New dispute created: {$this->dispute->stripe_dispute_id} for {$this->dispute->currency} " . number_format($this->dispute->amount, 2),
            'amount' => $this->dispute->amount,
            'currency' => $this->dispute->currency,
            'reason' => $this->dispute->reason,
            'status' => $this->dispute->status,
            'evidence_due_by' => $this->dispute->evidence_due_by?->toISOString(),
            'booking_id' => $this->dispute->booking_id,
            'transaction_id' => $this->dispute->transaction_id,
            'route' => route(prefixedRouteName('disputes.show'), $this->dispute->id),
            'urgency' => $this->getUrgencyLevel(),
            'created_at' => now()->toISOString()
        ];
    }

    /**
     * Get action message based on dispute status and deadline.
     */
    protected function getActionMessage(): string
    {
        if (!$this->dispute->evidence_due_by) {
            return '📋 This dispute requires immediate attention and evidence preparation.';
        }

        $daysUntilDeadline = now()->diffInDays($this->dispute->evidence_due_by, false);

        return match(true) {
            $daysUntilDeadline <= 1 => '🚨 **URGENT**: Evidence submission deadline is within 24 hours!',
            $daysUntilDeadline <= 3 => '⚠️ **HIGH PRIORITY**: Evidence submission deadline is approaching quickly.',
            $daysUntilDeadline <= 7 => '📋 Please begin preparing evidence submission - deadline is within a week.',
            default => '📋 Please review this dispute and begin evidence preparation.'
        };
    }

    /**
     * Get urgency level based on evidence deadline.
     */
    protected function getUrgencyLevel(): string
    {
        if (!$this->dispute->evidence_due_by) {
            return 'high';
        }

        $daysUntilDeadline = now()->diffInDays($this->dispute->evidence_due_by, false);

        return match(true) {
            $daysUntilDeadline <= 1 => 'critical',
            $daysUntilDeadline <= 3 => 'high',
            $daysUntilDeadline <= 7 => 'medium',
            default => 'low'
        };
    }

    protected function resolveDisputeUrl(object $notifiable): string
    {
        $prefix = $notifiable instanceof \App\Models\Admin ? 'admin' : 'tour-operator';
        return route("{$prefix}.disputes.show", $this->dispute->id);
    }
}