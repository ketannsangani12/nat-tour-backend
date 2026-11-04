<?php

namespace App\Console\Commands;

use App\Services\Dispute\DisputeNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CheckDisputeEscalations Command
 * 
 * Automated command to check for disputes requiring escalation and send
 * appropriate notifications to supervisors.
 * 
 * Escalation criteria:
 * - Evidence deadline within 24 hours
 * - High-value disputes (> $1000)
 * - Overdue evidence submissions
 * - Multiple failed response attempts
 * 
 * Requirements: 8.6
 */
class CheckDisputeEscalations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'disputes:check-escalations 
                           {--dry-run : Show what would be escalated without sending notifications}
                           {--reason= : Only check specific escalation reason}
                           {--force : Force escalation even if recently sent}';

    /**
     * The console command description.
     */
    protected $description = 'Check for disputes requiring escalation and send supervisor notifications';

    /**
     * The dispute notification service.
     */
    protected DisputeNotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(DisputeNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚨 Checking for dispute escalations...');
        
        $dryRun = $this->option('dry-run');
        $specificReason = $this->option('reason');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No notifications will be sent');
        }

        try {
            if ($dryRun) {
                $summary = $this->performDryRun($specificReason);
            } else {
                $summary = $this->notificationService->checkAndSendEscalations();
            }

            $this->displayResults($summary, $dryRun);

            if (!empty($summary['errors'])) {
                $this->error('Some escalations failed. Check logs for details.');
                return Command::FAILURE;
            }

            $this->info('✅ Escalation check completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to check escalations: {$e->getMessage()}");
            Log::error('Dispute escalation command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Perform a dry run to show what would be escalated.
     */
    protected function performDryRun(?string $specificReason): array
    {
        $summary = [
            'total_escalations' => 0,
            'escalations_by_reason' => [],
            'disputes_found' => [],
            'errors' => []
        ];

        // Check critical deadline disputes
        if (!$specificReason || $specificReason === 'deadline_critical') {
            $criticalDisputes = \App\Models\Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->where('evidence_due_by', '<=', now()->addHours(24))
                ->where('evidence_due_by', '>', now())
                ->with(['booking'])
                ->get();

            foreach ($criticalDisputes as $dispute) {
                $summary['disputes_found'][] = [
                    'dispute_id' => $dispute->stripe_dispute_id,
                    'reason' => 'deadline_critical',
                    'amount' => $dispute->amount,
                    'deadline' => $dispute->evidence_due_by->format('Y-m-d H:i:s'),
                    'hours_remaining' => now()->diffInHours($dispute->evidence_due_by, false)
                ];
            }

            $summary['escalations_by_reason']['deadline_critical'] = $criticalDisputes->count();
        }

        // Check overdue disputes
        if (!$specificReason || $specificReason === 'overdue_response') {
            $overdueDisputes = \App\Models\Dispute::where('status', 'warning_needs_response')
                ->whereNotNull('evidence_due_by')
                ->where('evidence_due_by', '<', now())
                ->with(['booking'])
                ->get();

            foreach ($overdueDisputes as $dispute) {
                $summary['disputes_found'][] = [
                    'dispute_id' => $dispute->stripe_dispute_id,
                    'reason' => 'overdue_response',
                    'amount' => $dispute->amount,
                    'deadline' => $dispute->evidence_due_by->format('Y-m-d H:i:s'),
                    'hours_overdue' => now()->diffInHours($dispute->evidence_due_by)
                ];
            }

            $summary['escalations_by_reason']['overdue_response'] = $overdueDisputes->count();
        }

        // Check high-value disputes
        if (!$specificReason || $specificReason === 'high_value_dispute') {
            $highValueDisputes = \App\Models\Dispute::where('status', 'warning_needs_response')
                ->where('amount', '>', 1000)
                ->with(['booking'])
                ->get();

            foreach ($highValueDisputes as $dispute) {
                $summary['disputes_found'][] = [
                    'dispute_id' => $dispute->stripe_dispute_id,
                    'reason' => 'high_value_dispute',
                    'amount' => $dispute->amount,
                    'currency' => $dispute->currency,
                    'created_at' => $dispute->created_at->format('Y-m-d H:i:s')
                ];
            }

            $summary['escalations_by_reason']['high_value_dispute'] = $highValueDisputes->count();
        }

        $summary['total_escalations'] = array_sum($summary['escalations_by_reason']);

        return $summary;
    }

    /**
     * Display the results of the escalation check.
     */
    protected function displayResults(array $summary, bool $dryRun): void
    {
        $this->newLine();
        
        if ($summary['total_escalations'] === 0) {
            $this->info('✅ No disputes require escalation at this time');
            return;
        }

        $this->info("📊 Escalation Summary:");
        $this->table(
            ['Reason', 'Count'],
            collect($summary['escalations_by_reason'])->map(function ($count, $reason) {
                return [ucfirst(str_replace('_', ' ', $reason)), $count];
            })->toArray()
        );

        if (isset($summary['disputes_found']) && !empty($summary['disputes_found'])) {
            $this->newLine();
            $this->info("📋 Disputes Found:");
            
            $headers = ['Dispute ID', 'Reason', 'Amount', 'Details'];
            $rows = collect($summary['disputes_found'])->map(function ($dispute) {
                $details = '';
                if (isset($dispute['hours_remaining'])) {
                    $details = "{$dispute['hours_remaining']} hours remaining";
                } elseif (isset($dispute['hours_overdue'])) {
                    $details = "{$dispute['hours_overdue']} hours overdue";
                } elseif (isset($dispute['currency'])) {
                    $details = "High value: {$dispute['currency']} {$dispute['amount']}";
                }

                return [
                    $dispute['dispute_id'],
                    ucfirst(str_replace('_', ' ', $dispute['reason'])),
                    "{$dispute['amount']}",
                    $details
                ];
            })->toArray();

            $this->table($headers, $rows);
        }

        if (!empty($summary['errors'])) {
            $this->newLine();
            $this->error("❌ Errors encountered:");
            foreach ($summary['errors'] as $error) {
                $this->line("  • {$error['error']}");
            }
        }

        $this->newLine();
        $action = $dryRun ? 'would be sent' : 'sent';
        $this->info("📧 Total escalation notifications {$action}: {$summary['total_escalations']}");
    }
}