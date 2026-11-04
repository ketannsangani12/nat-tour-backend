<?php

namespace App\Notifications\Dispute;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Dispute $dispute;
    protected string $escalationReason;
    protected ?string $timeRemaining;
    protected array $metadata;

    public function __construct(
        Dispute $dispute,
        string $escalationReason,
        ?string $timeRemaining = null,
        array $metadata = []
    ) {
        $this->dispute = $dispute;
        $this->escalationReason = $escalationReason;
        $this->timeRemaining = $timeRemaining;
        $this->metadata = $metadata;
    }

    public function via(object $notifiable): array
    {
        // return ['mail', 'database']; // Commented out to stop email notifications
        return ['database']; // Only database notifications, no emails
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getEmailSubject())
            ->view('mail.dispute.escalation', [
                'dispute'          => $this->dispute,
                'escalationReason' => $this->escalationReason,
                'timeRemaining'    => $this->timeRemaining,
                'metadata'         => $this->metadata,
                'dispute_url'      => $this->resolveDisputeUrl($notifiable),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_escalation',
            'dispute_id' => $this->dispute->id,
            'stripe_dispute_id' => $this->dispute->stripe_dispute_id,
            'title' => 'URGENT: Dispute Escalation',
            'message' => "Critical dispute {$this->dispute->stripe_dispute_id} escalated: {$this->escalationReason}",
            'escalation_reason' => $this->escalationReason,
            'time_remaining' => $this->timeRemaining,
            'amount' => $this->dispute->amount,
            'currency' => $this->dispute->currency,
            'evidence_due_by' => $this->dispute->evidence_due_by?->toISOString(),
            'booking_id' => $this->dispute->booking_id,
            'route' => route(prefixedRouteName('disputes.show'), $this->dispute->id),
            'urgency' => 'critical',
            'priority' => 'high',
            'requires_acknowledgment' => true,
            'metadata' => $this->metadata,
            'escalated_at' => now()->toISOString(),
            'created_at' => now()->toISOString(),
        ];
    }

    protected function resolveDisputeUrl(object $notifiable): string
    {
        $prefix = $notifiable instanceof \App\Models\Admin ? 'admin' : 'tour-operator';
        return route("{$prefix}.disputes.show", $this->dispute->id);
    }

    protected function getEmailSubject(): string
    {
        $baseSubject = "URGENT ESCALATION: {$this->dispute->stripe_dispute_id}";

        return match($this->escalationReason) {
            'deadline_critical'  => "{$baseSubject} - Evidence Due Within Hours",
            'high_value_dispute' => "{$baseSubject} - High Value Dispute ({$this->dispute->amount})",
            'overdue_response'   => "{$baseSubject} - Overdue Evidence Submission",
            'multiple_failures'  => "{$baseSubject} - Multiple Response Failures",
            'supervisor_review'  => "{$baseSubject} - Supervisor Review Required",
            'customer_complaint' => "{$baseSubject} - Customer Escalation",
            default              => "{$baseSubject} - {$this->escalationReason}",
        };
    }
}
