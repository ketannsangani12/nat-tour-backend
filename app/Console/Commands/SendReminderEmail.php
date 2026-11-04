<?php

namespace App\Console\Commands;

use App\Jobs\Mail\ProcessReminderEmails;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendReminderEmail extends Command
{
    protected $name = 'send_reminder';

    protected $signature = 'send:reminder {--dry-run : Show what would be processed without actually sending emails}';

    protected $description = 'Send Reminder email before N days';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            if (!isset(tenant()->id) || empty(tenant()->id) || tenant()->id === 'live') {
                return;
            }

            $setting = DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->first();
            if (!$setting || trim($setting->value) === '0') {
                if ($this->option('dry-run')) {
                    $this->info('REMINDER_EMAIL_DAYS is not configured or set to 0 — reminder emails are disabled for this tenant.');
                }
                return;
            }

            if ($this->option('dry-run')) {
                $this->runDryRun($setting);
                return;
            }

            // Dispatch a single job and return immediately — all SQS dispatching
            // happens inside the job on a queue worker, not in the scheduler process.
            ProcessReminderEmails::dispatch();

        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    private function runDryRun(object $setting): void
    {
        $days = explode(',', $setting->value);

        $today = date('Y-m-d');
        $dates = [];
        foreach ($days as $day) {
            $dates[] = date('Y-m-d', strtotime("+{$day} day", strtotime($today)));
        }

        $bookings = DB::table('tour_bookings_master AS tbm')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->whereIn('tsd.date', $dates)
            ->where('tbm.status', 'Booked')
            ->where('c.is_affiliate', 'No')
            ->whereNotNull('tbm.email')
            ->where('tbm.email', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tour_booking_history as tbh')
                    ->whereRaw('tbh.tour_booking_id = tbm.id')
                    ->where('tbh.operation', 'Email')
                    ->where('tbh.details', 'LIKE', 'Reminder % days prior to booking');
            })
            ->select(['tbm.id as tbm_id', 'tbm.name as customer_name', 'tbm.email as customer_email'])
            ->limit(6)
            ->get();

        $this->info('DRY RUN MODE - No emails will be sent');

        foreach ($bookings->take(5) as $booking) {
            $this->line("Would process booking ID: {$booking->tbm_id} for {$booking->customer_name} ({$booking->customer_email})");
        }

        if ($bookings->count() === 6) {
            $this->line('... and more bookings (limit reached in dry-run preview)');
        }
    }
}
