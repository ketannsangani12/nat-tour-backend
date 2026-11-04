<?php

namespace App\Notifications\Dispute;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeDeadlineWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Dispute $dispute;
    protected int $daysUntilDeadline;

    /**
     * Create a new notification instance.
     */
    public function __construct(Dispute $dispute, int $daysUntilDeadline)
    {
        $this->dispute = $dispute;
        $this->daysUntilDeadline = $daysUntilDeadline;
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
            'deadline', 
            [
                'days_until_deadline' => $this->daysUntilDeadline,
                'urgency' => $this->getUrgencyLevel()
            ]
        );
        
        $templateVars['dispute_url'] = $this->resolveDisputeUrl($notifiable);

        return (new MailMessage)
            ->subject($templateVars['customized_subject'])
            ->view('mail.dispute.deadline-warning', $templateVars);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_deadline_warning',
            'dispute_id' => $this->dispute->id,
            'stripe_dispute_id' => $this->dispute->stripe_dispute_id,
            'days_until_deadline' => $this->daysUntilDeadline,
            'title' => $this->getNotificationTitle(),
            'message' => "Dispute {$this->dispute->stripe_dispute_id} evidence deadline in {$this->daysUntilDeadline} day(s)",
            'urgency' => $this->getUrgencyLevel(),
            'amount' => $this->dispute->amount,
            'currency' => $this->dispute->currency,
            'evidence_due_by' => $this->dispute->evidence_due_by->toISOString(),
            'booking_id' => $this->dispute->booking_id,
            'route' => route(prefixedRouteName('disputes.show'), $this->dispute->id),
            'created_at' => now()->toISOString()
        ];
    }

    /**
     * Get the email subject based on urgency.
     */
    protected function getEmailSubject(): string
    {
        $urgencyLevel = $this->getUrgencyLevel();
        
        return match($urgencyLevel) {
            'critical' => "🚨 URGENT: Dispute Evidence Due Tomorrow - {$this->dispute->stripe_dispute_id}",
            'high' => "⚠️ HIGH PRIORITY: Dispute Evidence Due in {$this->daysUntilDeadline} Days - {$this->dispute->stripe_dispute_id}",
            'medium' => "📋 Dispute Evidence Deadline Reminder - {$this->dispute->stripe_dispute_id}",
            default => "Dispute Evidence Deadline Warning - {$this->dispute->stripe_dispute_id}"
        };
    }

    /**
     * Get the notification title for database storage.
     */
    protected function getNotificationTitle(): string
    {
        return match($this->daysUntilDeadline) {
            1 => 'Dispute Evidence Due Tomorrow',
            default => "Dispute Evidence Due in {$this->daysUntilDeadline} Days"
        };
    }

    /**
     * Get urgency level based on days until deadline.
     */
    protected function getUrgencyLevel(): string
    {
        return match($this->daysUntilDeadline) {
            1 => 'critical',
            2, 3 => 'high',
            default => 'medium'
        };
    }

    /**
     * Get urgency-specific message.
     */
    protected function getUrgencyMessage(): string
    {
        return match($this->daysUntilDeadline) {
            1 => '🚨 **CRITICAL**: Evidence must be submitted by tomorrow to avoid losing this dispute!',
            2, 3 => '⚠️ **HIGH PRIORITY**: Please prioritize evidence submission for this dispute.',
            default => '📋 Please review and prepare evidence submission for this dispute.'
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