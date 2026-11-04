<?php

namespace App\Services\Dispute;

use App\Models\Admin;
use App\Models\Dispute;
use App\Models\Staff;
use App\Notifications\Dispute\DisputeDeadlineWarningNotification;
use App\Notifications\Dispute\DisputeCreatedNotification;
use App\Notifications\Dispute\DisputeStatusChangedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

/**
 * DisputeNotificationService
 * 
 * Handles automated notifications for dispute management system.
 * Sends alerts for deadline warnings, new disputes, and status changes.
 * 
 * Requirements: 8.1, 8.2, 8.4
 */
class DisputeNotificationService
{
    /**
     * Default notification channels.
     */
    // protected array $defaultChannels = ['mail', 'database']; // Commented out to stop email notifications
    protected array $defaultChannels = ['database']; // Only database notifications, no emails

    /**
     * Deadline warning thresholds in days.
     */
    protected array $deadlineWarningDays = [7, 3, 1];

    /**
     * Send deadline warning notifications for approaching evidence deadlines.
     * 
     * @param int $daysUntilDeadline Days until deadline to warn about
     * @return int Number of notifications sent
     */
    public function sendDeadlineWarnings(int $daysUntilDeadline = 3): int
    {
        try {
            // Find disputes approaching deadline
            $disputes = Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->whereDate('evidence_due_by', '=', now()->addDays($daysUntilDeadline)->toDateString())
                ->with(['booking', 'transaction'])
                ->get();

            $notificationCount = 0;

            foreach ($disputes as $dispute) {
                $recipients = $this->getNotificationRecipients();
                
                if ($recipients->isEmpty()) {
                    Log::warning('No recipients found for dispute deadline warning', [
                        'dispute_id' => $dispute->id,
                        'days_until_deadline' => $daysUntilDeadline
                    ]);
                    continue;
                }

                // Send notification to all recipients
                // Notification::send(
                //     $recipients,
                //     new DisputeDeadlineWarningNotification($dispute, $daysUntilDeadline)
                // ); // Commented out to stop email notifications

                $notificationCount += $recipients->count();

                // Log::info('Deadline warning notification sent', [
                //     'dispute_id' => $dispute->id,
                //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
                //     'days_until_deadline' => $daysUntilDeadline,
                //     'recipient_count' => $recipients->count(),
                //     'evidence_due_by' => $dispute->evidence_due_by->toISOString()
                // ]);
            }

            return $notificationCount;

        } catch (\Exception $e) {
            Log::error('Failed to send deadline warning notifications', [
                'days_until_deadline' => $daysUntilDeadline,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Send notification for new dispute creation.
     * 
     * @param Dispute $dispute The newly created dispute
     * @return int Number of notifications sent
     */
    public function sendNewDisputeNotification(Dispute $dispute): int
    {
        try {
            $recipients = $this->getNotificationRecipients();
            
            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for new dispute notification', [
                    'dispute_id' => $dispute->id
                ]);
                return 0;
            }

            // Send notification to all recipients
            // Notification::send(
            //     $recipients,
            //     new DisputeCreatedNotification($dispute)
            // ); // Commented out to stop email notifications

            // Log::info('New dispute notification sent', [
            //     'dispute_id' => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'recipient_count' => $recipients->count(),
            //     'amount' => $dispute->amount,
            //     'reason' => $dispute->reason,
            //     'booking_id' => $dispute->booking_id
            // ]);

            return $recipients->count();

        } catch (\Exception $e) {
            Log::error('Failed to send new dispute notification', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Send notification for dispute status changes.
     * 
     * @param Dispute $dispute The dispute with status change
     * @param string $oldStatus The previous status
     * @param string $newStatus The new status
     * @param array $metadata Optional metadata about the change
     * @return int Number of notifications sent
     */
    public function sendStatusChangeNotification(
        Dispute $dispute, 
        string $oldStatus, 
        string $newStatus, 
        array $metadata = []
    ): int {
        try {
            // Only send notifications for significant status changes
            if (!$this->isSignificantStatusChange($oldStatus, $newStatus)) {
                Log::debug('Skipping notification for non-significant status change', [
                    'dispute_id' => $dispute->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                return 0;
            }

            $recipients = $this->getNotificationRecipients();
            
            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for status change notification', [
                    'dispute_id' => $dispute->id,
                    'status_change' => "{$oldStatus} -> {$newStatus}"
                ]);
                return 0;
            }

            // Send notification to all recipients
            // Notification::send(
            //     $recipients,
            //     new DisputeStatusChangedNotification($dispute, $oldStatus, $newStatus, $metadata)
            // ); // Commented out to stop email notifications

            // Log::info('Status change notification sent', [
            //     'dispute_id' => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'status_change' => "{$oldStatus} -> {$newStatus}",
            //     'recipient_count' => $recipients->count(),
            //     'metadata' => $metadata
            // ]);

            return $recipients->count();

        } catch (\Exception $e) {
            Log::error('Failed to send status change notification', [
                'dispute_id' => $dispute->id,
                'status_change' => "{$oldStatus} -> {$newStatus}",
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Send batch deadline warnings for all disputes approaching deadlines.
     * 
     * @return array Summary of notifications sent
     */
    public function sendBatchDeadlineWarnings(): array
    {
        $summary = [
            'total_notifications' => 0,
            'warnings_by_days' => [],
            'errors' => []
        ];

        foreach ($this->deadlineWarningDays as $days) {
            try {
                $count = $this->sendDeadlineWarnings($days);
                $summary['warnings_by_days'][$days] = $count;
                $summary['total_notifications'] += $count;

                // Log::info("Sent {$count} deadline warnings for {$days} days until deadline");

            } catch (\Exception $e) {
                $summary['errors'][] = [
                    'days' => $days,
                    'error' => $e->getMessage()
                ];

                Log::error("Failed to send deadline warnings for {$days} days", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $summary;
    }

    /**
     * Get users who should receive dispute notifications.
     * 
     * @return Collection Collection of users with dispute permissions
     */
    protected function getNotificationRecipients(): Collection
    {
        // Super admins bypass permission checks via Gate::before, so the permission()
        // scope won't include them — fetch them explicitly and merge.
        $superAdmins = Admin::role('super-admin')
            ->where('status', 'Active')
            ->whereNotNull('email')
            ->get();

        $admins = Admin::permission('view-payment-dispute')
            ->where('status', 'Active')
            ->whereNotNull('email')
            ->get();

        $staff = Staff::permission('view-payment-dispute')
            ->where('status', 'Active')
            ->whereNotNull('email')
            ->get();

        return $superAdmins->merge($admins)->merge($staff)->unique('id');
    }

    /**
     * Get supervisor users who should receive escalation notifications.
     * 
     * @return Collection Collection of supervisor users
     */
    protected function getSupervisorRecipients(): Collection
    {
        // Super admins are always included as supervisors — they bypass the permission()
        // scope via Gate::before so must be fetched by role directly.
        $superAdmins = Admin::role('super-admin')
            ->where('status', 'Active')
            ->whereNotNull('email')
            ->get();

        $adminSupervisors = Admin::permission('view-payment-dispute')
            ->where('status', 'Active')
            ->whereNotNull('email')
            ->get();

        $staffSupervisors = Staff::permission('view-payment-dispute')
            ->where('status', 'Active')
            ->whereNotNull('email')
            ->get();

        return $superAdmins->merge($adminSupervisors)->merge($staffSupervisors)->unique('id');
    }

    /**
     * Determine if a status change is significant enough to warrant notification.
     * 
     * @param string $oldStatus The previous status
     * @param string $newStatus The new status
     * @return bool True if the change is significant
     */
    protected function isSignificantStatusChange(string $oldStatus, string $newStatus): bool
    {
        // No change is not significant
        if ($oldStatus === $newStatus) {
            return false;
        }

        // Define significant status changes that warrant notifications
        $significantChanges = [
            // Dispute resolution outcomes
            'under_review' => ['won', 'lost', 'charge_refunded'],
            'warning_needs_response' => ['under_review', 'lost', 'charge_refunded'],
            
            // Any change to final states is significant
            '*' => ['won', 'lost', 'charge_refunded']
        ];

        // Check if this specific transition is significant
        if (isset($significantChanges[$oldStatus]) && 
            in_array($newStatus, $significantChanges[$oldStatus])) {
            return true;
        }

        // Check if new status is in the global significant list
        if (in_array($newStatus, $significantChanges['*'])) {
            return true;
        }

        return false;
    }

    /**
     * Get notification channels for dispute notifications.
     * 
     * @return array Array of notification channels
     */
    public function getNotificationChannels(): array
    {
        return config('dispute.notifications.channels', $this->defaultChannels);
    }

    /**
     * Get deadline warning thresholds.
     * 
     * @return array Array of days for deadline warnings
     */
    public function getDeadlineWarningDays(): array
    {
        return config('dispute.notifications.deadline_warning_days', $this->deadlineWarningDays);
    }

    /**
     * Check if notifications are enabled for a specific type.
     * 
     * @param string $type Notification type (deadline, created, status_change)
     * @return bool True if notifications are enabled
     */
    public function isNotificationEnabled(string $type): bool
    {
        return config("dispute.notifications.enabled.{$type}", true);
    }

    /**
     * Send escalation notification for urgent disputes.
     * 
     * @param Dispute $dispute The dispute to escalate
     * @param string $escalationReason Reason for escalation
     * @param array $metadata Additional escalation metadata
     * @return int Number of notifications sent
     */
    public function sendEscalationNotification(
        Dispute $dispute, 
        string $escalationReason, 
        array $metadata = []
    ): int {
        try {
            // Get supervisor recipients
            $supervisors = $this->getSupervisorRecipients();
            
            if ($supervisors->isEmpty()) {
                Log::warning('No supervisors found for dispute escalation', [
                    'dispute_id' => $dispute->id,
                    'escalation_reason' => $escalationReason
                ]);
                return 0;
            }

            // Calculate time remaining if evidence deadline exists
            $timeRemaining = null;
            if ($dispute->evidence_due_by) {
                $hoursRemaining = now()->diffInHours($dispute->evidence_due_by, false);
                $timeRemaining = $hoursRemaining > 0 
                    ? "{$hoursRemaining} hours remaining"
                    : "OVERDUE by " . abs($hoursRemaining) . " hours";
            }

            // Send escalation notification
            // $notification = new \App\Notifications\Dispute\DisputeEscalationNotification(
            //     $dispute, 
            //     $escalationReason, 
            //     $timeRemaining, 
            //     $metadata
            // );

            // Notification::send($supervisors, $notification); // Commented out to stop email notifications

            // Log escalation
            Log::critical('Dispute escalation notification sent', [
                'dispute_id' => $dispute->id,
                'stripe_dispute_id' => $dispute->stripe_dispute_id,
                'escalation_reason' => $escalationReason,
                'time_remaining' => $timeRemaining,
                'supervisor_count' => $supervisors->count(),
                'amount' => $dispute->amount,
                'evidence_due_by' => $dispute->evidence_due_by?->toISOString(),
                'metadata' => $metadata
            ]);

            return $supervisors->count();

        } catch (\Exception $e) {
            Log::error('Failed to send escalation notification', [
                'dispute_id' => $dispute->id,
                'escalation_reason' => $escalationReason,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check and send escalation notifications for disputes requiring urgent attention.
     * 
     * @return array Summary of escalations sent
     */
    public function checkAndSendEscalations(): array
    {
        $summary = [
            'total_escalations' => 0,
            'escalations_by_reason' => [],
            'errors' => []
        ];

        try {
            // Check for critical deadline disputes (< 24 hours)
            $criticalDeadlineDisputes = Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->where('evidence_due_by', '<=', now()->addHours(24))
                ->where('evidence_due_by', '>', now())
                ->with(['booking', 'transaction'])
                ->get();

            foreach ($criticalDeadlineDisputes as $dispute) {
                try {
                    $count = $this->sendEscalationNotification(
                        $dispute, 
                        'deadline_critical',
                        ['hours_remaining' => now()->diffInHours($dispute->evidence_due_by, false)]
                    );
                    
                    $summary['escalations_by_reason']['deadline_critical'] = 
                        ($summary['escalations_by_reason']['deadline_critical'] ?? 0) + $count;
                    $summary['total_escalations'] += $count;

                } catch (\Exception $e) {
                    $summary['errors'][] = [
                        'dispute_id' => $dispute->id,
                        'reason' => 'deadline_critical',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Check for overdue disputes
            $overdueDisputes = Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->where('evidence_due_by', '<', now())
                ->with(['booking', 'transaction'])
                ->get();

            foreach ($overdueDisputes as $dispute) {
                try {
                    $count = $this->sendEscalationNotification(
                        $dispute, 
                        'overdue_response',
                        ['hours_overdue' => now()->diffInHours($dispute->evidence_due_by)]
                    );
                    
                    $summary['escalations_by_reason']['overdue_response'] = 
                        ($summary['escalations_by_reason']['overdue_response'] ?? 0) + $count;
                    $summary['total_escalations'] += $count;

                } catch (\Exception $e) {
                    $summary['errors'][] = [
                        'dispute_id' => $dispute->id,
                        'reason' => 'overdue_response',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Check for high-value disputes (> $1000)
            $highValueDisputes = Dispute::where('status', 'warning_needs_response')
                ->where('amount', '>', 1000)
                ->whereDoesntHave('timeline', function($query) {
                    $query->where('event_type', 'escalation_sent')
                          ->where('created_at', '>', now()->subHours(24));
                })
                ->with(['booking', 'transaction'])
                ->get();

            foreach ($highValueDisputes as $dispute) {
                try {
                    $count = $this->sendEscalationNotification(
                        $dispute, 
                        'high_value_dispute',
                        ['amount' => $dispute->amount, 'currency' => $dispute->currency]
                    );
                    
                    $summary['escalations_by_reason']['high_value_dispute'] = 
                        ($summary['escalations_by_reason']['high_value_dispute'] ?? 0) + $count;
                    $summary['total_escalations'] += $count;

                } catch (\Exception $e) {
                    $summary['errors'][] = [
                        'dispute_id' => $dispute->id,
                        'reason' => 'high_value_dispute',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log::info('Dispute escalation check completed', [
            //     'total_escalations' => $summary['total_escalations'],
            //     'escalations_by_reason' => $summary['escalations_by_reason'],
            //     'error_count' => count($summary['errors'])
            // ]);

        } catch (\Exception $e) {
            Log::error('Failed to check and send escalations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $summary['errors'][] = [
                'general_error' => $e->getMessage()
            ];
        }

        return $summary;
    }
    /**
     * Get dispute summary for dashboard widgets.
     * 
     * @return array Dispute summary data
     */
    public function getDisputeSummary(): array
    {
        try {
            $activeDisputes = Dispute::whereIn('status', ['warning_needs_response', 'under_review'])->count();
            
            $urgentDisputes = Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->where('evidence_due_by', '<=', now()->addDays(3))
                ->count();

            $overdueDisputes = Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->where('evidence_due_by', '<', now())
                ->count();

            // Get status breakdown
            $statusBreakdown = Dispute::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Calculate metrics
            $totalDisputes = Dispute::count();
            $wonDisputes = Dispute::where('status', 'won')->count();
            $lostDisputes = Dispute::where('status', 'lost')->count();
            $resolvedDisputes = $wonDisputes + $lostDisputes;

            $winRate = $resolvedDisputes > 0 ? round(($wonDisputes / $resolvedDisputes) * 100, 1) . '%' : 'N/A';
            $responseRate = $totalDisputes > 0 ? round((($totalDisputes - $overdueDisputes) / $totalDisputes) * 100, 1) . '%' : 'N/A';

            // Calculate average response time
            $avgResponseTime = $this->calculateAverageResponseTime();

            return [
                'active_disputes' => $activeDisputes,
                'urgent_disputes' => $urgentDisputes,
                'overdue_disputes' => $overdueDisputes,
                'total_disputes' => $totalDisputes,
                'status_breakdown' => $statusBreakdown,
                'metrics' => [
                    'win_rate' => $winRate,
                    'response_rate' => $responseRate,
                    'avg_response_time' => $avgResponseTime
                ],
                'last_updated' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get dispute summary', [
                'error' => $e->getMessage()
            ]);

            return [
                'active_disputes' => 0,
                'urgent_disputes' => 0,
                'overdue_disputes' => 0,
                'total_disputes' => 0,
                'status_breakdown' => [],
                'metrics' => [
                    'win_rate' => 'N/A',
                    'response_rate' => 'N/A',
                    'avg_response_time' => 'N/A'
                ],
                'last_updated' => now()->toISOString(),
                'error' => 'Failed to load dispute data'
            ];
        }
    }

    /**
     * Calculate average response time for disputes.
     * 
     * @return string Formatted average response time
     */
    protected function calculateAverageResponseTime(): string
    {
        try {
            // Get disputes that have evidence submission timeline entries
            $responseTimes = Dispute::whereHas('timeline', function($query) {
                $query->where('event_type', 'evidence_submitted');
            })
            ->with(['timeline' => function($query) {
                $query->whereIn('event_type', ['dispute_created', 'evidence_submitted'])
                      ->orderBy('created_at');
            }])
            ->get()
            ->map(function($dispute) {
                $created = $dispute->timeline->where('event_type', 'dispute_created')->first();
                $submitted = $dispute->timeline->where('event_type', 'evidence_submitted')->first();
                
                if ($created && $submitted) {
                    return $created->created_at->diffInHours($submitted->created_at);
                }
                return null;
            })
            ->filter()
            ->values();

            if ($responseTimes->isEmpty()) {
                return 'N/A';
            }

            $avgHours = $responseTimes->average();
            
            if ($avgHours < 24) {
                return round($avgHours, 1) . ' hours';
            } else {
                return round($avgHours / 24, 1) . ' days';
            }

        } catch (\Exception $e) {
            Log::error('Failed to calculate average response time', [
                'error' => $e->getMessage()
            ]);
            return 'N/A';
        }
    }

    /**
     * Get escalation criteria for disputes.
     * 
     * @return array Escalation criteria configuration
     */
    public function getEscalationCriteria(): array
    {
        return [
            'deadline_critical' => [
                'threshold' => 24, // hours
                'description' => 'Evidence deadline within 24 hours'
            ],
            'high_value_dispute' => [
                'threshold' => 1000, // amount
                'description' => 'Dispute amount exceeds $1,000'
            ],
            'overdue_response' => [
                'threshold' => 0, // hours past deadline
                'description' => 'Evidence submission is overdue'
            ],
            'multiple_failures' => [
                'threshold' => 2, // failed attempts
                'description' => 'Multiple evidence submission failures'
            ]
        ];
    }
}