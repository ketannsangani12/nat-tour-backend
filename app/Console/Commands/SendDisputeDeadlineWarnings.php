<?php

namespace App\Console\Commands;

use App\Services\Dispute\DisputeNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDisputeDeadlineWarnings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'disputes:send-deadline-warnings 
                            {--days= : Specific number of days until deadline to warn about}
                            {--batch : Send warnings for all configured deadline thresholds}';

    /**
     * The console command description.
     */
    protected $description = 'Send deadline warning notifications for disputes approaching evidence deadlines';

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
        $this->info('Starting dispute deadline warning notifications...');

        try {
            if ($this->option('batch')) {
                return $this->sendBatchWarnings();
            }

            $days = $this->option('days');
            if ($days !== null) {
                return $this->sendSpecificDayWarnings((int) $days);
            }

            // Default: send warnings for 3 days
            return $this->sendSpecificDayWarnings(3);

        } catch (\Exception $e) {
            $this->error("Failed to send deadline warnings: {$e->getMessage()}");
            Log::error('Dispute deadline warning command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Send warnings for all configured deadline thresholds.
     */
    protected function sendBatchWarnings(): int
    {
        $this->info('Sending batch deadline warnings for all configured thresholds...');

        $summary = $this->notificationService->sendBatchDeadlineWarnings();

        $this->info("Batch deadline warnings completed:");
        $this->info("Total notifications sent: {$summary['total_notifications']}");

        if (!empty($summary['warnings_by_days'])) {
            $this->info("Breakdown by days:");
            foreach ($summary['warnings_by_days'] as $days => $count) {
                $this->info("  {$days} days: {$count} notifications");
            }
        }

        if (!empty($summary['errors'])) {
            $this->warn("Errors encountered:");
            foreach ($summary['errors'] as $error) {
                $this->error("  {$error['days']} days: {$error['error']}");
            }
        }

        // Log::info('Batch dispute deadline warnings completed', $summary);

        return empty($summary['errors']) ? 0 : 1;
    }

    /**
     * Send warnings for a specific number of days.
     */
    protected function sendSpecificDayWarnings(int $days): int
    {
        $this->info("Sending deadline warnings for disputes due in {$days} day(s)...");

        $count = $this->notificationService->sendDeadlineWarnings($days);

        $this->info("Sent {$count} deadline warning notifications for {$days} day(s) until deadline.");

        Log::info("Dispute deadline warnings sent", [
            'days_until_deadline' => $days,
            'notifications_sent' => $count
        ]);

        return 0;
    }
}