<?php

namespace App\Services\Dispute;

use App\Models\Dispute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * DisputeTemplateService
 * 
 * Provides email template management and customization for different
 * dispute types, reasons, and urgency levels.
 * 
 * Requirements: 8.3, 11.1
 */
class DisputeTemplateService
{
    /**
     * Template configurations for different dispute reasons.
     */
    protected array $templateConfig = [
        'fraudulent' => [
            'subject_prefix' => '🚨 FRAUD ALERT',
            'urgency_multiplier' => 1.5,
            'recommended_evidence' => [
                'Customer communication records',
                'Service delivery confirmation',
                'Photo/video evidence of service',
                'Customer identification verification'
            ],
            'response_strategy' => 'Focus on proving legitimate service delivery and customer authorization'
        ],
        'subscription_canceled' => [
            'subject_prefix' => '📋 SUBSCRIPTION DISPUTE',
            'urgency_multiplier' => 1.0,
            'recommended_evidence' => [
                'Cancellation policy documentation',
                'Customer agreement to terms',
                'Cancellation request timeline',
                'Service usage after cancellation request'
            ],
            'response_strategy' => 'Demonstrate clear cancellation policy and proper procedure compliance'
        ],
        'product_unacceptable' => [
            'subject_prefix' => '⭐ SERVICE QUALITY DISPUTE',
            'urgency_multiplier' => 1.2,
            'recommended_evidence' => [
                'Service delivery documentation',
                'Customer feedback and reviews',
                'Quality assurance records',
                'Attempt to resolve complaint'
            ],
            'response_strategy' => 'Show service met advertised standards and address specific complaints'
        ],
        'credit_not_processed' => [
            'subject_prefix' => '💳 REFUND PROCESSING DISPUTE',
            'urgency_multiplier' => 1.1,
            'recommended_evidence' => [
                'Refund processing records',
                'Customer refund requests',
                'Payment system logs',
                'Bank processing confirmations'
            ],
            'response_strategy' => 'Provide clear refund processing timeline and documentation'
        ],
        'duplicate' => [
            'subject_prefix' => '🔄 DUPLICATE CHARGE DISPUTE',
            'urgency_multiplier' => 0.9,
            'recommended_evidence' => [
                'Transaction records showing single charge',
                'Customer booking confirmations',
                'Payment processing logs',
                'Service delivery for each charge'
            ],
            'response_strategy' => 'Prove each charge was for separate, legitimate services'
        ],
        'product_not_received' => [
            'subject_prefix' => '📦 SERVICE DELIVERY DISPUTE',
            'urgency_multiplier' => 1.3,
            'recommended_evidence' => [
                'Service delivery confirmation',
                'Customer check-in records',
                'Photo/video evidence',
                'Staff witness statements'
            ],
            'response_strategy' => 'Provide comprehensive proof of service delivery'
        ],
        'unrecognized' => [
            'subject_prefix' => '❓ UNRECOGNIZED CHARGE DISPUTE',
            'urgency_multiplier' => 1.1,
            'recommended_evidence' => [
                'Customer booking records',
                'Payment authorization',
                'Service delivery confirmation',
                'Customer communication'
            ],
            'response_strategy' => 'Help customer recognize the charge and prove authorization'
        ],
        'general' => [
            'subject_prefix' => '📋 PAYMENT DISPUTE',
            'urgency_multiplier' => 1.0,
            'recommended_evidence' => [
                'Complete booking documentation',
                'Customer communication records',
                'Service delivery proof',
                'Terms and conditions'
            ],
            'response_strategy' => 'Provide comprehensive documentation addressing all dispute points'
        ]
    ];

    /**
     * Get template configuration for a specific dispute reason.
     * 
     * @param string $reason Dispute reason
     * @return array Template configuration
     */
    public function getTemplateConfig(string $reason): array
    {
        return $this->templateConfig[$reason] ?? $this->templateConfig['general'];
    }

    /**
     * Get customized email subject for a dispute.
     * 
     * @param Dispute $dispute The dispute
     * @param string $notificationType Type of notification (created, deadline, status_change, escalation)
     * @param array $context Additional context
     * @return string Customized subject line
     */
    public function getCustomizedSubject(Dispute $dispute, string $notificationType, array $context = []): string
    {
        $config = $this->getTemplateConfig($dispute->reason);
        $prefix = $config['subject_prefix'];
        
        $baseSubject = match($notificationType) {
            'created' => "{$prefix}: New Dispute - {$dispute->stripe_dispute_id}",
            'deadline' => $this->getDeadlineSubject($dispute, $prefix, $context),
            'status_change' => $this->getStatusChangeSubject($dispute, $prefix, $context),
            'escalation' => "{$prefix}: URGENT ESCALATION - {$dispute->stripe_dispute_id}",
            default => "{$prefix}: {$dispute->stripe_dispute_id}"
        };

        // Add urgency indicators for high-priority disputes
        if ($this->isHighPriority($dispute)) {
            $baseSubject = "🚨 " . $baseSubject;
        }

        return $baseSubject;
    }

    /**
     * Get deadline-specific subject line.
     */
    protected function getDeadlineSubject(Dispute $dispute, string $prefix, array $context): string
    {
        $daysUntilDeadline = $context['days_until_deadline'] ?? 0;
        
        if ($daysUntilDeadline <= 1) {
            return "{$prefix}: Evidence Due Tomorrow - {$dispute->stripe_dispute_id}";
        } elseif ($daysUntilDeadline <= 3) {
            return "{$prefix}: Evidence Due in {$daysUntilDeadline} Days - {$dispute->stripe_dispute_id}";
        } else {
            return "{$prefix}: Evidence Deadline Reminder - {$dispute->stripe_dispute_id}";
        }
    }

    /**
     * Get status change specific subject line.
     */
    protected function getStatusChangeSubject(Dispute $dispute, string $prefix, array $context): string
    {
        $newStatus = $context['new_status'] ?? $dispute->status;
        
        return match($newStatus) {
            'won' => "{$prefix}: ✅ Dispute Won - {$dispute->stripe_dispute_id}",
            'lost' => "{$prefix}: ❌ Dispute Lost - {$dispute->stripe_dispute_id}",
            'charge_refunded' => "{$prefix}: 💰 Charge Refunded - {$dispute->stripe_dispute_id}",
            'under_review' => "{$prefix}: 🔍 Under Review - {$dispute->stripe_dispute_id}",
            default => "{$prefix}: Status Updated - {$dispute->stripe_dispute_id}"
        };
    }

    /**
     * Get recommended evidence for a dispute.
     * 
     * @param Dispute $dispute The dispute
     * @return array List of recommended evidence items
     */
    public function getRecommendedEvidence(Dispute $dispute): array
    {
        $config = $this->getTemplateConfig($dispute->reason);
        return $config['recommended_evidence'];
    }

    /**
     * Get response strategy for a dispute.
     * 
     * @param Dispute $dispute The dispute
     * @return string Response strategy guidance
     */
    public function getResponseStrategy(Dispute $dispute): string
    {
        $config = $this->getTemplateConfig($dispute->reason);
        return $config['response_strategy'];
    }

    /**
     * Determine if a dispute is high priority.
     * 
     * @param Dispute $dispute The dispute
     * @return bool True if high priority
     */
    public function isHighPriority(Dispute $dispute): bool
    {
        // High value disputes
        if ($dispute->amount > 1000) {
            return true;
        }

        // Critical deadline
        if ($dispute->evidence_due_by && now()->diffInHours($dispute->evidence_due_by, false) <= 24) {
            return true;
        }

        // High-risk dispute reasons
        $highRiskReasons = ['fraudulent', 'product_not_received', 'product_unacceptable'];
        if (in_array($dispute->reason, $highRiskReasons)) {
            return true;
        }

        return false;
    }

    /**
     * Get urgency level for a dispute.
     * 
     * @param Dispute $dispute The dispute
     * @return string Urgency level (low, medium, high, critical)
     */
    public function getUrgencyLevel(Dispute $dispute): string
    {
        if (!$dispute->evidence_due_by) {
            return $this->isHighPriority($dispute) ? 'high' : 'medium';
        }

        $hoursUntilDeadline = now()->diffInHours($dispute->evidence_due_by, false);
        $config = $this->getTemplateConfig($dispute->reason);
        $multiplier = $config['urgency_multiplier'];

        // Apply reason-specific urgency multiplier
        $adjustedHours = $hoursUntilDeadline / $multiplier;

        return match(true) {
            $adjustedHours <= 12 => 'critical',
            $adjustedHours <= 48 => 'high',
            $adjustedHours <= 120 => 'medium',
            default => 'low'
        };
    }

    /**
     * Get template variables for email rendering.
     * 
     * @param Dispute $dispute The dispute
     * @param string $notificationType Type of notification
     * @param array $context Additional context
     * @return array Template variables
     */
    public function getTemplateVariables(Dispute $dispute, string $notificationType, array $context = []): array
    {
        $config = $this->getTemplateConfig($dispute->reason);
        
        return [
            'dispute' => $dispute,
            'urgency' => $this->getUrgencyLevel($dispute),
            'is_high_priority' => $this->isHighPriority($dispute),
            'recommended_evidence' => $config['recommended_evidence'],
            'response_strategy' => $config['response_strategy'],
            'template_config' => $config,
            'notification_type' => $notificationType,
            'context' => $context,
            'customized_subject' => $this->getCustomizedSubject($dispute, $notificationType, $context)
        ];
    }

    /**
     * Get all available template configurations.
     * 
     * @return array All template configurations
     */
    public function getAllTemplateConfigs(): array
    {
        return $this->templateConfig;
    }

    /**
     * Get dispute reason statistics for template optimization.
     * 
     * @return array Reason statistics
     */
    public function getReasonStatistics(): array
    {
        try {
            $stats = Dispute::selectRaw('reason, COUNT(*) as count, AVG(amount) as avg_amount')
                ->groupBy('reason')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->reason => [
                        'count' => $item->count,
                        'avg_amount' => round($item->avg_amount, 2),
                        'config' => $this->getTemplateConfig($item->reason)
                    ]];
                })
                ->toArray();

            return $stats;

        } catch (\Exception $e) {
            Log::error('Failed to get dispute reason statistics', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}