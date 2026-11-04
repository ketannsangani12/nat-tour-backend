<?php

namespace Tests\Unit\TourBooking;

use App\Jobs\Mail\ProcessReminderEmails;
use App\Jobs\Mail\TourBookingReminderMails;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks in that the reminder pipeline (send:reminder command, its --dry-run
 * path, and the queued job) is disabled both when REMINDER_EMAIL_DAYS = '0'
 * and when the setting row is missing entirely, while any other value still
 * runs normally.
 *
 * Mutates (and, for the "missing row" cases, deletes) the real "settings" row
 * for this tenant, so the original row is captured and restored in
 * tearDown() regardless of test outcome.
 */
class ReminderEmailSettingTest extends TestCase
{
    private ?array $originalRow;

    protected function setUp(): void
    {
        parent::setUp();
        $row = DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->first();
        $this->originalRow = $row ? (array) $row : null;
    }

    protected function tearDown(): void
    {
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->delete();
        if ($this->originalRow) {
            DB::table('settings')->insert($this->originalRow);
        }
        parent::tearDown();
    }

    public function test_command_does_not_dispatch_job_when_setting_is_zero(): void
    {
        Bus::fake();
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->update(['value' => '0']);

        Artisan::call('send:reminder');

        Bus::assertNotDispatched(ProcessReminderEmails::class);
    }

    public function test_command_dispatches_job_when_setting_is_nonzero(): void
    {
        Bus::fake();
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->update(['value' => '7']);

        Artisan::call('send:reminder');

        Bus::assertDispatched(ProcessReminderEmails::class);
    }

    public function test_dry_run_reports_disabled_when_setting_is_zero(): void
    {
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->update(['value' => '0']);

        Artisan::call('send:reminder', ['--dry-run' => true]);

        $this->assertStringContainsString('disabled', Artisan::output());
    }

    public function test_job_does_not_dispatch_reminder_mail_when_setting_is_zero(): void
    {
        Bus::fake();
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->update(['value' => '0']);

        $job = new ProcessReminderEmails();
        $job->handle();

        Bus::assertNotDispatched(TourBookingReminderMails::class);
    }

    public function test_command_does_not_dispatch_job_when_setting_row_missing(): void
    {
        Bus::fake();
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->delete();

        Artisan::call('send:reminder');

        Bus::assertNotDispatched(ProcessReminderEmails::class);
    }

    public function test_dry_run_reports_disabled_when_setting_row_missing(): void
    {
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->delete();

        Artisan::call('send:reminder', ['--dry-run' => true]);

        $this->assertStringContainsString('disabled', Artisan::output());
    }

    public function test_job_does_not_dispatch_reminder_mail_when_setting_row_missing(): void
    {
        Bus::fake();
        DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->delete();

        $job = new ProcessReminderEmails();
        $job->handle();

        Bus::assertNotDispatched(TourBookingReminderMails::class);
    }
}
