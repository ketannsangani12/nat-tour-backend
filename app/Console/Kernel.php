<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Tenant;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SendReminderEmail::class,
        Commands\TruncateTables::class,
        Commands\MarkAbsentCheckins::class,
        Commands\UpdateAffiliateSeat::class,
        Commands\ExecuteGlobalRateScheduleEvents::class,
        Commands\UpdatePromotions::class,
        Commands\ManagerRequestExpired::class,
        Commands\UpdatePaymentLinkStatus::class,
        Commands\VerifyPreBooking::class,
        Commands\ExecuteTipsPayout::class,
        Commands\SendPostTipReminder::class,
        Commands\RemoveBokunReservations::class,
        Commands\CleanupAbandonedPaymentReservations::class,
        Commands\ProcessAutoPayouts::class,
        Commands\SendDisputeDeadlineWarnings::class,
        Commands\CheckDisputeEscalations::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $tenants = Tenant::all();

        foreach ($tenants as $index => $tenant) {
            $timezone = $tenant->timezone ?? config('app.timezone');

            // Spread tenants across a 30-minute window so all ProcessReminderEmails
            // jobs don't hit the DB simultaneously.
            $reminderTime = sprintf('03:%02d', $index % 30);
            $schedule->command("tenants:run send:reminder --tenants={$tenant->id}")
                ->daily()
                ->at($reminderTime)
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run truncate:tables --tenants={$tenant->id}")
                ->everyTenMinutes()
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run checkins:absent --tenants={$tenant->id}")
                ->everyTenMinutes()
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run affiliate:seat-update --tenants={$tenant->id}")
                ->daily()
                ->at('23:55')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            // Expire any Bulk Manager operations still queued at end of day (before midnight).
            $schedule->command("tenants:run bulk-operations:expire-queued --tenants={$tenant->id}")
                ->daily()
                ->at('23:50')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run rates:execute-scheduled-events --tenants={$tenant->id}")
                ->everyMinute()
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run promotion:inactive --tenants={$tenant->id}")
                ->daily()
                ->at('00:00')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run manager:request-expired --tenants={$tenant->id}")
                ->daily()
                ->at('00:30')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run payment-link-status:update --tenants={$tenant->id}")
                ->daily()
                ->at('00:30')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run pre-booking:verify --tenants={$tenant->id}")
                ->everyMinute()
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run cleanup:bokun-reservations --tenants={$tenant->id}")
                ->daily()
                ->at('00:30')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command("tenants:run sanctum:prune-expired --option=hours=60 --tenants={$tenant->id}")
                ->daily()
                ->at('01:00')
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            // Spread tenants across the 15-minute window so cleanups don't all lock rows at once.
            $cleanupOffset = $index % 15;
            $cleanupCron = implode(',', [$cleanupOffset, $cleanupOffset + 15, $cleanupOffset + 30, $cleanupOffset + 45]);
            $schedule->command("tenants:run transactions:cleanup-abandoned-reservations --tenants={$tenant->id}")
                ->cron("{$cleanupCron} * * * *")
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            // Auto Payout Processing - Check every minute for due payouts
            $schedule->command("tenants:run autopayout:process --tenants={$tenant->id}")
                ->everyMinute()
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            // Dispute Deadline Warnings - Send batch warnings twice daily
            $schedule->command("tenants:run disputes:send-deadline-warnings --option=batch=true --tenants={$tenant->id}")
                ->twiceDaily(9, 15) // 9 AM and 3 PM
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            // Dispute Escalation Checks - Check for urgent disputes every 2 hours during business hours
            $schedule->command("tenants:run disputes:check-escalations --tenants={$tenant->id}")
                ->hourlyAt([0, 30]) // Every 30 minutes
                ->between('08:00', '18:00') // Business hours only
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();

            // Critical Dispute Escalation - Check every 15 minutes for critical deadlines
            $schedule->command("tenants:run disputes:check-escalations --option=reason=deadline_critical --tenants={$tenant->id}")
                ->everyFifteenMinutes()
                ->timezone($timezone)
                ->withoutOverlapping()
                ->onOneServer();
        }
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
