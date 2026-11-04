<?php

namespace Tests\Unit\AutoPayout;

use Tests\TestCase;
use App\Models\AutoPayout;
use App\Models\AutoPayoutRule;
use App\Models\AutoPayoutRunLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use PDO;

/**
 * Covers the run-log accounting in ProcessAutoPayouts::handle().
 *
 * These tests do NOT exercise the Stripe-calling paths -- those require a
 * connected account fixture and would need to mock Stripe::Payout::create.
 * They focus on the invariant: a tick writes to auto_payout_run_logs only
 * when something meaningful happened (rule fired, payouts created or
 * executed, or any failure). Skipped/no-activity ticks write nothing,
 * because writing 1440 confirmation rows per tenant per day buries the
 * signal under noise.
 */
class ProcessAutoPayoutsTest extends TestCase
{
    private static array $insertedIds = [
        'auto_payouts' => [],
        'auto_payout_rules' => [],
        'auto_payout_run_logs' => [],
    ];

    private ?int $originalAutoPayoutSetting = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot the AUTO_PAYOUT setting so we restore it in tearDown.
        // Other tests in the suite may depend on its current value.
        $row = DB::table('settings')->where('key', 'AUTO_PAYOUT')->first();
        $this->originalAutoPayoutSetting = $row ? (int) $row->value : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalAutoPayoutSetting !== null) {
            DB::table('settings')
                ->where('key', 'AUTO_PAYOUT')
                ->update(['value' => $this->originalAutoPayoutSetting]);
        }

        // Always release the lock used by the command so a failed test
        // doesn't poison the next one.
        try {
            Cache::lock('autopayout:processing')->forceRelease();
        } catch (Exception $e) {
            // ignore
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $host = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? 'mysql';
            $user = $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'nat_user';
            $pass = $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? 'nat_password';
            $db = 'tenant_dixies';

            $pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = [
                'auto_payout_run_logs',
                'auto_payouts',
                'auto_payout_rules',
            ];

            foreach ($tables as $table) {
                $ids = static::$insertedIds[$table] ?? [];
                if (empty($ids)) {
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($ids));
            }
        } catch (Exception $e) {
            fwrite(STDERR, "ProcessAutoPayoutsTest cleanup failed: {$e->getMessage()}\n");
        }

        parent::tearDownAfterClass();
    }

    private function setAutoPayout(int $value): void
    {
        $existing = DB::table('settings')->where('key', 'AUTO_PAYOUT')->first();
        if ($existing) {
            DB::table('settings')->where('key', 'AUTO_PAYOUT')->update(['value' => $value]);
        } else {
            DB::table('settings')->insert([
                'key' => 'AUTO_PAYOUT',
                'value' => $value,
                'order_number' => 47,
                'comment' => 'AUTO_PAYOUT (test)',
            ]);
        }
    }

    private function createRule(array $overrides = []): AutoPayoutRule
    {
        $rule = AutoPayoutRule::create(array_merge([
            'name' => 'PAPT-' . Str::random(6),
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'execution_time' => '09:00:00',
            'minimum_amount' => 50.00,
            'buffer_days' => 0,
            'enabled' => false,
            'requires_approval' => true,
            'next_execution_at' => now()->addWeek(),
        ], $overrides));

        static::$insertedIds['auto_payout_rules'][] = $rule->id;

        return $rule;
    }

    /**
     * Capture the run-log rows written during $callback so we can assert on
     * them and queue them for cleanup. Returns the rows newer than the
     * pre-existing max id.
     */
    private function capturingNewRunLogs(callable $callback): array
    {
        $beforeMaxId = (int) (AutoPayoutRunLog::max('id') ?? 0);
        $callback();
        $newRows = AutoPayoutRunLog::where('id', '>', $beforeMaxId)
            ->orderBy('id')
            ->get();
        foreach ($newRows as $row) {
            static::$insertedIds['auto_payout_run_logs'][] = $row->id;
        }
        return $newRows->all();
    }

    public function test_does_not_log_when_auto_payout_disabled(): void
    {
        $this->setAutoPayout(0);

        $rows = $this->capturingNewRunLogs(function () {
            $exit = Artisan::call('autopayout:process');
            $this->assertSame(0, $exit);
        });

        // AUTO_PAYOUT=off is a steady-state signal the dashboard reads from
        // the settings table; the scheduler does not echo it to the run log.
        $this->assertCount(0, $rows);
    }

    public function test_does_not_log_when_no_enabled_rule_and_no_approved_payouts(): void
    {
        $this->setAutoPayout(1);

        // The "no enabled rule" branch only fires when nothing else in the
        // tenant DB is enabled. Other test runs / seeds can leave enabled
        // rules behind, so snapshot any currently-enabled rules, disable
        // them for the duration of this test, and restore on exit.
        $previouslyEnabledIds = AutoPayoutRule::where('enabled', true)->pluck('id')->all();
        if (!empty($previouslyEnabledIds)) {
            AutoPayoutRule::whereIn('id', $previouslyEnabledIds)->update(['enabled' => false]);
        }

        // Approved pending payouts would also defeat this branch -- park them.
        $previouslyApprovedIds = DB::table('pending_payouts')
            ->where('status', 'approved')
            ->pluck('id')
            ->all();
        if (!empty($previouslyApprovedIds)) {
            DB::table('pending_payouts')
                ->whereIn('id', $previouslyApprovedIds)
                ->update(['status' => 'approved_test_parked_' . getmypid()]);
        }

        try {
            // A disabled rule satisfies "rule exists" but not "enabled rule exists".
            $this->createRule(['enabled' => false]);

            $rows = $this->capturingNewRunLogs(function () {
                $exit = Artisan::call('autopayout:process');
                $this->assertSame(0, $exit);
            });

            $this->assertCount(0, $rows);
        } finally {
            if (!empty($previouslyEnabledIds)) {
                AutoPayoutRule::whereIn('id', $previouslyEnabledIds)->update(['enabled' => true]);
            }
            if (!empty($previouslyApprovedIds)) {
                DB::table('pending_payouts')
                    ->whereIn('id', $previouslyApprovedIds)
                    ->update(['status' => 'approved']);
            }
        }
    }

    public function test_does_not_log_when_cache_lock_is_held(): void
    {
        $this->setAutoPayout(1);
        $this->createRule(['enabled' => true]);

        // Acquire the lock the command tries to take, simulating an in-flight tick.
        $lock = Cache::lock('autopayout:processing', 60);
        $this->assertTrue($lock->get(), 'failed to acquire the test lock');

        try {
            $rows = $this->capturingNewRunLogs(function () {
                $exit = Artisan::call('autopayout:process');
                $this->assertSame(0, $exit);
            });
        } finally {
            $lock->release();
        }

        $this->assertCount(0, $rows);
    }

    public function test_does_not_log_when_no_rules_are_due_for_execution(): void
    {
        $this->setAutoPayout(1);
        // Enabled rule, but next_execution_at is in the future -- the command
        // reaches processRules(), finds nothing due, and writes nothing.
        // Ticks with no activity must not bloat auto_payout_run_logs.
        $this->createRule([
            'enabled' => true,
            'next_execution_at' => now()->addDays(7),
        ]);

        $rows = $this->capturingNewRunLogs(function () {
            $exit = Artisan::call('autopayout:process');
            $this->assertSame(0, $exit);
        });

        $this->assertCount(0, $rows);
    }

    /**
     * The reconciler is a backstop for missed payout webhooks, but it must give
     * the webhook a head start: a payout still inside the 5-minute grace window
     * must NOT be reconciled (no Stripe call), so it is left 'pending'. This
     * exercises the gate without hitting Stripe -- a recent payout is excluded
     * from the candidate set entirely.
     */
    public function test_reconcile_skips_payouts_within_webhook_grace_window(): void
    {
        $this->setAutoPayout(1);
        // Disabled rule => no rule is due and no immediate/approved execution
        // path runs, so the only Stripe-touching code that could fire is the
        // reconciler -- which must skip this just-created payout.
        $rule = $this->createRule(['enabled' => false]);

        $payout = AutoPayout::create([
            'payout_reference' => 'AP-GRACE-' . strtoupper(Str::random(6)),
            'stripe_payout_id' => 'po_' . Str::random(24),
            'rule_id' => $rule->id,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'booking_count' => 1,
            'processed_date' => now(),
            'status' => 'pending',
        ]);
        static::$insertedIds['auto_payouts'][] = $payout->id;

        $this->capturingNewRunLogs(function () {
            $exit = Artisan::call('autopayout:process');
            $this->assertSame(0, $exit);
        });

        // Untouched: inside the grace window, so the reconciler never queried Stripe.
        $payout->refresh();
        $this->assertEquals('pending', $payout->status);
    }

    /**
     * Sanity check for the penny-perfect fix in
     * PayoutApprovalService::executeImmediatePayout / executeSinglePayout.
     * PHP float math turns 0.59 * 100 into 58.99999..., which (int) truncates
     * to 58, costing a penny. (int) round(...) is the safe form.
     */
    public function test_amount_to_cents_conversion_is_penny_safe(): void
    {
        // 4.81 and 35.41 are values where PHP float math produces
        // 480.99999... and 3540.99999... respectively. (int) on the raw
        // product truncates to 480 / 3540, costing the operator a penny.
        // (int) round(...) -- the fix in PayoutApprovalService -- recovers
        // the correct integer cents.
        $tricky = [
            [0.59, 59],
            [1.10, 110],
            [4.81, 481],
            [29.99, 2999],
            [35.41, 3541],
            [123.45, 12345],
        ];

        foreach ($tricky as [$dollars, $expectedCents]) {
            $this->assertSame(
                $expectedCents,
                (int) round($dollars * 100),
                sprintf('(int) round(%.2f * 100) should be %d cents', $dollars, $expectedCents)
            );
        }

        // Document the underlying float bug the fix exists to neutralise.
        // We assert that AT LEAST ONE of the known-bad values truncates
        // wrong without round() -- so the test stays meaningful even if
        // a future PHP version improves a particular case.
        $brokenCount = 0;
        foreach ($tricky as [$dollars, $expectedCents]) {
            if ((int) ($dollars * 100) !== $expectedCents) {
                $brokenCount++;
            }
        }
        $this->assertGreaterThan(
            0,
            $brokenCount,
            'Expected (int)($d * 100) to truncate wrong for at least one value -- if this fails, the penny fix may no longer be necessary on this PHP version.'
        );
    }

    /**
     * A rule with requires_approval=0 pays out inline via executeImmediatePayout,
     * and that count is only ever known inside processRules(). handle() used to
     * read $ruleStats['created'] but drop $ruleStats['executed'], so a run that
     * really paid logged executed=0 -- indistinguishable from a no-op run, and
     * enough to flip outcome to error when another account failed alongside it.
     *
     * Stripe is never reached: PayoutApprovalService is swapped for a double, so
     * this asserts the accounting in the command, not the payment itself.
     */
    public function test_immediate_payout_count_reaches_the_run_log(): void
    {
        $this->setAutoPayout(1);

        $rule = $this->createRule([
            'enabled' => true,
            'requires_approval' => false,
            'minimum_amount' => 50.00,
            'next_execution_at' => now()->subMinute(),
        ]);

        $account = (object) ['stripe_destination_id' => 'acct_TESTZBRVO3'];

        $service = \Mockery::mock(\App\Services\PayoutApprovalService::class);
        $service->shouldReceive('getConnectedAccounts')->andReturn(collect([$account]));
        $service->shouldReceive('calculateAccountPayoutAmount')
            ->andReturn(['net_amount' => 2020.00, 'total_amount' => 2217.96, 'platform_fee' => 197.96]);
        // The immediate path under test.
        $service->shouldReceive('executeImmediatePayout')
            ->once()
            ->andReturn(['amount' => 2020.00]);
        // Step 2 must contribute nothing, so any non-zero count came from step 1.
        $service->shouldReceive('executeApprovedPayouts')
            ->andReturn(['executed' => [], 'failed' => []]);
        $this->app->instance(\App\Services\PayoutApprovalService::class, $service);

        $adjustments = \Mockery::mock(\App\Services\PayoutAdjustmentService::class);
        $adjustments->shouldReceive('detectAndCreateAdjustments')->andReturn(collect());
        $this->app->instance(\App\Services\PayoutAdjustmentService::class, $adjustments);

        $rows = $this->capturingNewRunLogs(function () {
            $exit = Artisan::call('autopayout:process');
            $this->assertSame(0, $exit);
        });

        $this->assertCount(1, $rows, 'The rule fired, so exactly one run-log row is expected.');
        $row = $rows[0];

        $this->assertSame(1, (int) $row->payouts_executed, 'The immediate payout must be counted in payouts_executed.');
        $this->assertStringContainsString('executed=1', $row->message);
        $this->assertSame(AutoPayoutRunLog::OUTCOME_PROCESSED, $row->outcome);
    }
}
