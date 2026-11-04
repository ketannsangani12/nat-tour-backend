<?php

namespace Tests\Unit\AutoPayout;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\AutoPayout;
use App\Models\AutoPayoutItem;
use App\Models\AutoPayoutRule;
use App\Models\PendingPayout;
use App\Models\PendingPayoutItem;
use App\Models\PayoutApprovalLog;
use App\Models\TourBooking;
use App\Http\Controllers\StripeWebhookController;
use App\Services\PayoutApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use PDO;

class PayoutApprovalServiceTest extends TestCase
{
    private PayoutApprovalService $service;
    private ?Admin $admin = null;

    private static array $builders = [];

    private static array $insertedIds = [
        'payout_approval_logs' => [],
        'pending_payout_items' => [],
        'pending_payouts' => [],
        'auto_payout_items' => [],
        'auto_payouts' => [],
        'auto_payout_rules' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // PayoutApprovalService constructor calls Stripe::setApiKey which
        // requires the helper. We only test DB/logic, not Stripe calls.
        $this->service = app(PayoutApprovalService::class);

        $this->admin = Admin::first() ?? Admin::factory()->create();
    }

    private function createBooking(): int
    {
        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        // Ensure transaction has request_from set (fixture leaves it null)
        // and fee_amount populated so recalculation queries find it.
        if ($builder->transactionId) {
            DB::table('transactions')
                ->where('id', $builder->transactionId)
                ->update(['request_from' => 'BACKEND', 'fee_amount' => 3.20]);
        }

        return $builder->bookingId;
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up bookings created by CreateABooking fixture
        foreach (static::$builders as $builder) {
            try {
                $builder->cleanup();
            } catch (Exception $e) {
                fwrite(STDERR, "Booking cleanup failed: {$e->getMessage()}\n");
            }
        }
        static::$builders = [];

        // Clean up in reverse dependency order using raw PDO since
        // tenancy context is gone by this point.
        try {
            $host = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? 'mysql';
            $user = $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'nat_user';
            $pass = $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? 'nat_password';
            $db = 'tenant_dixies';

            $pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = [
                'payout_approval_logs',
                'pending_payout_items',
                'pending_payouts',
                'auto_payout_items',
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
            fwrite(STDERR, "Cleanup failed: {$e->getMessage()}\n");
        }

        parent::tearDownAfterClass();
    }

    private function createRule(array $overrides = []): AutoPayoutRule
    {
        $rule = AutoPayoutRule::create(array_merge([
            'name' => 'Test Rule ' . Str::random(4),
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'execution_time' => '09:00:00',
            'minimum_amount' => 50.00,
            'enabled' => false,
            'requires_approval' => true,
            'next_execution_at' => now()->addWeek(),
        ], $overrides));

        static::$insertedIds['auto_payout_rules'][] = $rule->id;

        return $rule;
    }

    private function createPendingPayoutRecord(AutoPayoutRule $rule, array $overrides = []): PendingPayout
    {
        $reference = 'PP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $stripeAccountId = 'acct_' . Str::random(16);

        $payout = PendingPayout::create(array_merge([
            'payout_reference' => $reference,
            'rule_id' => $rule->id,
            'stripe_account_id' => $stripeAccountId,
            'total_amount' => 500.00,
            'platform_fee' => 14.80,
            'net_amount' => 485.20,
            'booking_count' => 3,
            'status' => 'pending_approval',
            'rule_snapshot' => $rule->toArray(),
            'calculation_data' => [
                'booking_ids' => [9000001, 9000002, 9000003],
                'booking_details' => [],
                'calculation_date' => now()->toDateTimeString(),
                'stripe_account_id' => $stripeAccountId,
            ],
        ], $overrides));

        static::$insertedIds['pending_payouts'][] = $payout->id;

        return $payout;
    }

    private function trackLog(int $logId): void
    {
        static::$insertedIds['payout_approval_logs'][] = $logId;
    }

    private function trackPendingItem(int $itemId): void
    {
        static::$insertedIds['pending_payout_items'][] = $itemId;
    }

    // ---------------------------------------------------------------
    // Test 1: createPendingPayout stores correct amounts
    // ---------------------------------------------------------------
    public function test_create_pending_payout_stores_correct_amounts(): void
    {
        $rule = $this->createRule();
        $stripeAccountId = 'acct_test' . Str::random(12);

        $bookingId1 = $this->createBooking();
        $bookingId2 = $this->createBooking();

        $payoutData = [
            'total_amount' => 1000.00,
            'platform_fee' => 32.00,
            'net_amount' => 968.00,
            'booking_count' => 2,
            'booking_ids' => [$bookingId1, $bookingId2],
            'booking_details' => [
                $bookingId1 => ['amount' => 600.00, 'fee_amount' => 19.20, 'net_total' => 580.80],
                $bookingId2 => ['amount' => 400.00, 'fee_amount' => 12.80, 'net_total' => 387.20],
            ],
        ];

        $pendingPayout = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);

        static::$insertedIds['pending_payouts'][] = $pendingPayout->id;

        // Track items and logs created by the service
        foreach ($pendingPayout->items as $item) {
            $this->trackPendingItem($item->id);
        }
        foreach ($pendingPayout->logs as $log) {
            $this->trackLog($log->id);
        }

        $this->assertEquals('1000.00', $pendingPayout->total_amount);
        $this->assertEquals('32.00', $pendingPayout->platform_fee);
        $this->assertEquals('968.00', $pendingPayout->net_amount);
        $this->assertEquals(2, $pendingPayout->booking_count);
        $this->assertEquals('pending_approval', $pendingPayout->status);
        $this->assertEquals($stripeAccountId, $pendingPayout->stripe_account_id);
        $this->assertEquals($rule->id, $pendingPayout->rule_id);

        // Verify items
        $items = PendingPayoutItem::where('pending_payout_id', $pendingPayout->id)->get();
        $this->assertCount(2, $items);

        $item1 = $items->firstWhere('booking_id', $bookingId1);
        $this->assertNotNull($item1);
        $this->assertEquals('600.00', $item1->total_amount);
        $this->assertEquals('19.20', $item1->platform_fee);
        $this->assertEquals('580.80', $item1->net_amount);

        $item2 = $items->firstWhere('booking_id', $bookingId2);
        $this->assertNotNull($item2);
        $this->assertEquals('400.00', $item2->total_amount);
        $this->assertEquals('12.80', $item2->platform_fee);
        $this->assertEquals('387.20', $item2->net_amount);
    }

    // ---------------------------------------------------------------
    // Test 2: createPendingPayout logs creation
    // ---------------------------------------------------------------
    public function test_create_pending_payout_logs_creation(): void
    {
        $rule = $this->createRule();
        $stripeAccountId = 'acct_log' . Str::random(12);

        $realBookingId = $this->createBooking();

        $payoutData = [
            'total_amount' => 200.00,
            'platform_fee' => 6.10,
            'net_amount' => 193.90,
            'booking_count' => 1,
            'booking_ids' => [$realBookingId],
            'booking_details' => [
                $realBookingId => ['amount' => 200.00, 'fee_amount' => 6.10, 'net_total' => 193.90],
            ],
        ];

        $pendingPayout = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);

        static::$insertedIds['pending_payouts'][] = $pendingPayout->id;
        foreach ($pendingPayout->items as $item) {
            $this->trackPendingItem($item->id);
        }

        $log = PayoutApprovalLog::where('pending_payout_id', $pendingPayout->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log, 'Expected a creation log entry');
        $this->trackLog($log->id);

        $this->assertEquals('created', $log->action);
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('Pending payout created automatically', $log->notes);
    }

    // ---------------------------------------------------------------
    // Test 3: approve transitions status
    // ---------------------------------------------------------------
    public function test_approve_transitions_status(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayoutRecord($rule);

        $result = $this->service->approvePayout($payout, $this->admin->id, '127.0.0.1', 'Looks good');

        $this->assertTrue($result);

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertNotNull($payout->approved_at);
        $this->assertEquals($this->admin->id, $payout->approved_by);

        // Track the approval log
        $log = PayoutApprovalLog::where('pending_payout_id', $payout->id)
            ->where('action', 'approved')
            ->first();
        $this->assertNotNull($log);
        $this->trackLog($log->id);
        $this->assertEquals($this->admin->id, $log->user_id);
    }

    // ---------------------------------------------------------------
    // Test 4: reject stores reason
    // ---------------------------------------------------------------
    public function test_reject_stores_reason(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayoutRecord($rule);

        $reason = 'Amount seems too high, needs review';
        $result = $this->service->rejectPayout($payout, $this->admin->id, $reason, '127.0.0.1');

        $this->assertTrue($result);

        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);
        $this->assertNotNull($payout->rejected_at);
        $this->assertEquals($this->admin->id, $payout->rejected_by);
        $this->assertEquals($reason, $payout->rejection_reason);

        // Track the rejection log
        $log = PayoutApprovalLog::where('pending_payout_id', $payout->id)
            ->where('action', 'rejected')
            ->first();
        $this->assertNotNull($log);
        $this->trackLog($log->id);
    }

    // ---------------------------------------------------------------
    // Test 5: approve already-approved throws
    // ---------------------------------------------------------------
    public function test_approve_already_approved_throws(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayoutRecord($rule, ['status' => 'approved', 'approved_at' => now(), 'approved_by' => 1]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Payout is not in pending approval status');

        $this->service->approvePayout($payout, $this->admin->id, '127.0.0.1');
    }

    // ---------------------------------------------------------------
    // Test 6: approve already-rejected throws
    // ---------------------------------------------------------------
    public function test_approve_already_rejected_throws(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayoutRecord($rule, [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'Already rejected',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Payout is not in pending approval status');

        $this->service->approvePayout($payout, $this->admin->id, '127.0.0.1');
    }

    // ---------------------------------------------------------------
    // Test 7: reject already-rejected throws
    // ---------------------------------------------------------------
    public function test_reject_already_rejected_throws(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayoutRecord($rule, [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'Previously rejected',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Payout is not in pending approval status');

        $this->service->rejectPayout($payout, $this->admin->id, 'Double reject', '127.0.0.1');
    }

    // ---------------------------------------------------------------
    // Test 8: bulk approve all succeed
    // ---------------------------------------------------------------
    public function test_bulk_approve_all_succeed(): void
    {
        $rule = $this->createRule();
        $payout1 = $this->createPendingPayoutRecord($rule);
        $payout2 = $this->createPendingPayoutRecord($rule);

        $result = $this->service->bulkApprove(
            [$payout1->id, $payout2->id],
            $this->admin->id,
            '127.0.0.1',
            'Bulk approved'
        );

        $this->assertCount(2, $result['approved']);
        $this->assertCount(0, $result['failed']);

        $payout1->refresh();
        $payout2->refresh();
        $this->assertEquals('approved', $payout1->status);
        $this->assertEquals('approved', $payout2->status);

        // Track logs
        $logs = PayoutApprovalLog::whereIn('pending_payout_id', [$payout1->id, $payout2->id])
            ->where('action', 'approved')
            ->get();
        foreach ($logs as $log) {
            $this->trackLog($log->id);
        }
    }

    // ---------------------------------------------------------------
    // Test 9: bulk approve partial failure
    // ---------------------------------------------------------------
    public function test_bulk_approve_partial_failure(): void
    {
        $rule = $this->createRule();
        $validPayout = $this->createPendingPayoutRecord($rule);
        $approvedPayout = $this->createPendingPayoutRecord($rule, [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 1,
        ]);
        $nonExistentId = 9999999;

        $result = $this->service->bulkApprove(
            [$validPayout->id, $approvedPayout->id, $nonExistentId],
            $this->admin->id,
            '127.0.0.1'
        );

        $this->assertCount(1, $result['approved']);
        $this->assertCount(2, $result['failed']);

        $validPayout->refresh();
        $this->assertEquals('approved', $validPayout->status);

        // Track log for the one that succeeded
        $log = PayoutApprovalLog::where('pending_payout_id', $validPayout->id)
            ->where('action', 'approved')
            ->first();
        if ($log) {
            $this->trackLog($log->id);
        }
    }

    // ---------------------------------------------------------------
    // Test 10: payout reference uniqueness
    // ---------------------------------------------------------------
    public function test_payout_reference_uniqueness(): void
    {
        $rule = $this->createRule();
        $stripeAccount = 'acct_uniquetest123456';

        // Production references are PP-YYYYMMDD-ACCT6-RAND4, so a real same-account
        // same-day collision is improbable. This test hand-writes two identical
        // references to verify the unique constraint still catches one if it happens.

        $payout1 = $this->createPendingPayoutRecord($rule, [
            'payout_reference' => 'PP-' . date('Ymd') . '-123456',
            'stripe_account_id' => $stripeAccount,
        ]);

        $threw = false;
        try {
            $payout2 = PendingPayout::create([
                'payout_reference' => 'PP-' . date('Ymd') . '-123456',
                'rule_id' => $rule->id,
                'stripe_account_id' => $stripeAccount,
                'total_amount' => 100.00,
                'platform_fee' => 3.20,
                'net_amount' => 96.80,
                'booking_count' => 1,
                'status' => 'pending_approval',
                'rule_snapshot' => [],
                'calculation_data' => ['booking_ids' => [], 'booking_details' => []],
            ]);
            // If it was created somehow, track for cleanup
            static::$insertedIds['pending_payouts'][] = $payout2->id;
        } catch (\Illuminate\Database\QueryException $e) {
            $threw = true;
            // Duplicate entry error (MySQL error 1062)
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
        }

        $this->assertTrue($threw, 'Expected a duplicate key exception for identical payout_reference');
    }

    // ---------------------------------------------------------------
    // Test 11: pending payout scopes filter correctly
    // ---------------------------------------------------------------
    public function test_pending_payout_scopes(): void
    {
        $rule = $this->createRule();

        $pending = $this->createPendingPayoutRecord($rule, ['status' => 'pending_approval']);
        $approved = $this->createPendingPayoutRecord($rule, ['status' => 'approved', 'approved_at' => now(), 'approved_by' => 1]);
        $rejected = $this->createPendingPayoutRecord($rule, [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'Test',
        ]);

        // Use whereIn to scope to just our test records
        $testIds = [$pending->id, $approved->id, $rejected->id];

        $pendingResults = PendingPayout::pendingApproval()->whereIn('id', $testIds)->get();
        $approvedResults = PendingPayout::approved()->whereIn('id', $testIds)->get();
        $rejectedResults = PendingPayout::rejected()->whereIn('id', $testIds)->get();

        $this->assertCount(1, $pendingResults);
        $this->assertEquals($pending->id, $pendingResults->first()->id);

        $this->assertCount(1, $approvedResults);
        $this->assertEquals($approved->id, $approvedResults->first()->id);

        $this->assertCount(1, $rejectedResults);
        $this->assertEquals($rejected->id, $rejectedResults->first()->id);
    }

    // ---------------------------------------------------------------
    // Test 12: only approved payouts fetched for execution
    // ---------------------------------------------------------------
    public function test_only_approved_payouts_fetched_for_execution(): void
    {
        $rule = $this->createRule();

        $pending = $this->createPendingPayoutRecord($rule, ['status' => 'pending_approval']);
        $approved1 = $this->createPendingPayoutRecord($rule, ['status' => 'approved', 'approved_at' => now(), 'approved_by' => 1]);
        $approved2 = $this->createPendingPayoutRecord($rule, ['status' => 'approved', 'approved_at' => now(), 'approved_by' => 1]);
        $rejected = $this->createPendingPayoutRecord($rule, [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'No',
        ]);

        $testIds = [$pending->id, $approved1->id, $approved2->id, $rejected->id];

        // This is the exact query used in executeApprovedPayouts()
        $forExecution = PendingPayout::approved()->whereIn('id', $testIds)->get();

        $this->assertCount(2, $forExecution);

        $executionIds = $forExecution->pluck('id')->toArray();
        $this->assertContains($approved1->id, $executionIds);
        $this->assertContains($approved2->id, $executionIds);
        $this->assertNotContains($pending->id, $executionIds);
        $this->assertNotContains($rejected->id, $executionIds);
    }

    // ---------------------------------------------------------------
    // Test 13: recalculate removes already-paid-out bookings
    // ---------------------------------------------------------------
    public function test_recalculate_removes_already_paid_bookings(): void
    {
        $rule = $this->createRule();

        $bookingId1 = $this->createBooking();
        $bookingId2 = $this->createBooking();

        $stripeAccountId = 'acct_recalc' . Str::random(8);

        $payoutData = [
            'total_amount' => 800.00,
            'platform_fee' => 25.00,
            'net_amount' => 775.00,
            'booking_count' => 2,
            'booking_ids' => [$bookingId1, $bookingId2],
            'booking_details' => [
                $bookingId1 => ['amount' => 400.00, 'fee_amount' => 12.50, 'net_total' => 387.50],
                $bookingId2 => ['amount' => 400.00, 'fee_amount' => 12.50, 'net_total' => 387.50],
            ],
        ];

        $pendingPayout = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);
        static::$insertedIds['pending_payouts'][] = $pendingPayout->id;
        foreach ($pendingPayout->items as $item) {
            $this->trackPendingItem($item->id);
        }
        foreach ($pendingPayout->logs as $log) {
            $this->trackLog($log->id);
        }

        // Simulate booking 1 being paid out by another payout
        DB::table('tour_bookings_master')
            ->where('id', $bookingId1)
            ->update(['payout_made' => 1]);

        $result = $this->service->recalculatePendingPayout($pendingPayout);

        // Track new logs
        $newLogs = PayoutApprovalLog::where('pending_payout_id', $pendingPayout->id)
            ->whereNotIn('id', static::$insertedIds['payout_approval_logs'])
            ->get();
        foreach ($newLogs as $log) {
            $this->trackLog($log->id);
        }

        // Booking 1 should be removed (already paid), booking 2 should remain
        $removedIds = array_column($result['removed'], 'booking_id');
        $this->assertContains($bookingId1, $removedIds);
        $this->assertNotContains($bookingId2, $removedIds);

        // Verify the reason for booking 1
        $paidOutEntry = collect($result['removed'])->firstWhere('booking_id', $bookingId1);
        $this->assertEquals('Already paid out in another payout', $paidOutEntry['reason']);

        // Payout should still have booking 2
        $pendingPayout->refresh();
        $this->assertGreaterThanOrEqual(1, $pendingPayout->booking_count);

        $remainingIds = PendingPayoutItem::where('pending_payout_id', $pendingPayout->id)
            ->pluck('booking_id')->toArray();
        $this->assertContains($bookingId2, $remainingIds);
        $this->assertNotContains($bookingId1, $remainingIds);

        // Reset payout_made for cleanup
        DB::table('tour_bookings_master')
            ->where('id', $bookingId1)
            ->update(['payout_made' => 0]);
    }

    // ---------------------------------------------------------------
    // Test 14: recalculate updates partially refunded booking amounts
    // ---------------------------------------------------------------
    public function test_recalculate_updates_refunded_booking(): void
    {
        $rule = $this->createRule();

        $bookingId = $this->createBooking();
        $stripeAccountId = 'acct_refcalc' . Str::random(8);

        // Get the actual transaction amounts created by CreateABooking
        $originalTxn = DB::table('transactions')
            ->where('tour_booking_id', $bookingId)
            ->where('status', 'Completed')
            ->first();

        $originalAmount = (float) $originalTxn->amount;
        $originalFee = (float) $originalTxn->fee_amount;
        $originalNet = round($originalAmount - $originalFee, 2);

        $payoutData = [
            'total_amount' => $originalAmount,
            'platform_fee' => $originalFee,
            'net_amount' => $originalNet,
            'booking_count' => 1,
            'booking_ids' => [$bookingId],
            'booking_details' => [
                $bookingId => ['amount' => $originalAmount, 'fee_amount' => $originalFee, 'net_total' => $originalNet],
            ],
        ];

        $pendingPayout = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);
        static::$insertedIds['pending_payouts'][] = $pendingPayout->id;
        foreach ($pendingPayout->items as $item) {
            $this->trackPendingItem($item->id);
        }
        foreach ($pendingPayout->logs as $log) {
            $this->trackLog($log->id);
        }

        // Add a refund transaction after payout was created
        $refundTxnId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $bookingId,
            'amount' => 20.00,
            'fee_amount' => 0.64,
            'status' => 'Refund',
            'via' => 'Card',
            'request_from' => 'Admin',
            'created_at' => now(),
        ]);

        $result = $this->service->recalculatePendingPayout($pendingPayout);

        $newLogs = PayoutApprovalLog::where('pending_payout_id', $pendingPayout->id)
            ->whereNotIn('id', static::$insertedIds['payout_approval_logs'])
            ->get();
        foreach ($newLogs as $log) {
            $this->trackLog($log->id);
        }

        // Should have updated the booking amounts (net decreased by refund)
        $this->assertCount(1, $result['updated']);
        $this->assertEquals($bookingId, $result['updated'][0]['booking_id']);
        $this->assertLessThan($originalNet, $result['updated'][0]['new_net']);

        // Payout net should reflect the refund
        $pendingPayout->refresh();
        $this->assertLessThan($originalNet, (float) $pendingPayout->net_amount);

        // Cleanup refund transaction
        DB::table('transactions')->where('id', $refundTxnId)->delete();
    }

    // ---------------------------------------------------------------
    // Test 15: createPendingPayout rejects zero/negative net amount
    // ---------------------------------------------------------------
    public function test_create_pending_payout_rejects_zero_net(): void
    {
        $rule = $this->createRule();
        $stripeAccountId = 'acct_zero' . Str::random(8);

        $payoutData = [
            'total_amount' => 100.00,
            'platform_fee' => 100.00,
            'net_amount' => 0,
            'booking_count' => 1,
            'booking_ids' => [9000099],
            'booking_details' => [],
        ];

        $result = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);
        $this->assertNull($result);

        $payoutData['net_amount'] = -50.00;
        $result = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);
        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // Test 15b: Payout Simplification — platform_held transactions must never enter
    // the payout calculation (Foundation item 9's correctness fix). A platform_held
    // charge settled entirely on the platform's own Stripe balance, so including it
    // here would pay the operator for money they never received via Stripe.
    // ---------------------------------------------------------------
    public function test_calculate_account_payout_amount_excludes_platform_held_transactions(): void
    {
        $rule = $this->createRule([
            // Broad enough to safely contain both test bookings' tour date without needing
            // exact-second precision against real dixies data — the assertions below check for
            // THIS TEST'S OWN booking ids inside booking_ids, not aggregate sums, so other real
            // bookings anywhere in this window cannot make this test pass or fail incorrectly.
            'last_executed_at' => now()->subYear(),
        ]);

        // Yesterday, not "today" — the query's upper bound is "now" in the TENANT's timezone
        // (America/Phoenix for dixies), and CreateABooking's default tour time (09:00) can
        // land in the future relative to that if the real current Phoenix time is still early
        // morning, silently falling outside the [past, now] window this method sweeps.
        $tourDate = now(tourOperator()->timezone)->subDay()->toDateString();

        $destinationBooking = (new CreateABooking())->withTourists(1)->withRate(100.00)->withPaymentMethod('Card')->withDate($tourDate)->create();
        static::$builders[] = $destinationBooking;
        $destinationBookingId = $destinationBooking->bookingId;
        DB::table('transactions')->where('id', $destinationBooking->transactionId)->update(['request_from' => 'BACKEND', 'fee_amount' => 3.20]);

        $platformHeldBooking = (new CreateABooking())->withTourists(1)->withRate(100.00)->withPaymentMethod('Card')->withDate($tourDate)->create();
        static::$builders[] = $platformHeldBooking;
        $platformHeldBookingId = $platformHeldBooking->bookingId;
        DB::table('transactions')->where('id', $platformHeldBooking->transactionId)->update([
            'request_from' => 'BACKEND',
            'fee_amount' => 3.20,
            'payment_mode' => 'platform_held',
        ]);

        $result = $this->service->calculateAccountPayoutAmount($rule, 'acct_test_' . Str::random(8));

        $this->assertContains(
            $destinationBookingId,
            $result['booking_ids'],
            'A DESTINATION-mode transaction must still be swept into the payout as before.'
        );
        $this->assertNotContains(
            $platformHeldBookingId,
            $result['booking_ids'],
            'A platform_held transaction settled entirely on the platform balance — it must never be included in what Auto Payout sweeps to the connected account.'
        );
    }

    // ---------------------------------------------------------------
    // Test 16: calculateNextExecutionTime daily/weekly/monthly
    // ---------------------------------------------------------------
    private function freezeNow(string $datetime): string
    {
        $tz = tourOperator()->timezone;
        Carbon::setTestNow(Carbon::parse($datetime, $tz));
        return $tz;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_daily_fires_today_when_time_not_passed(): void
    {
        $tz = $this->freezeNow('2026-04-19 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('daily', null, null, '09:00');
        $this->assertEquals('2026-04-19 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_daily_fires_tomorrow_when_time_passed(): void
    {
        $tz = $this->freezeNow('2026-04-19 10:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('daily', null, null, '09:00');
        $this->assertEquals('2026-04-20 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_daily_fires_today_at_exact_time_inclusive(): void
    {
        $tz = $this->freezeNow('2026-04-19 09:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('daily', null, null, '09:00');
        $this->assertEquals('2026-04-19 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_weekly_fires_today_when_on_target_day(): void
    {
        // 2026-04-20 is a Monday (day_of_week = 1)
        $tz = $this->freezeNow('2026-04-20 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('weekly', 1, null, '09:00');
        $this->assertEquals('2026-04-20 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_weekly_fires_next_week_when_target_day_passed(): void
    {
        $tz = $this->freezeNow('2026-04-20 10:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('weekly', 1, null, '09:00');
        $this->assertEquals('2026-04-27 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_weekly_fires_next_target_day_when_off_day(): void
    {
        // 2026-04-18 is a Saturday
        $tz = $this->freezeNow('2026-04-18 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('weekly', 1, null, '09:00');
        $this->assertEquals('2026-04-20 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_monthly_fires_today_when_on_target_day(): void
    {
        $tz = $this->freezeNow('2026-04-15 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('monthly', null, 15, '14:30');
        $this->assertEquals('2026-04-15 14:30:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_monthly_fires_next_month_when_day_passed(): void
    {
        $tz = $this->freezeNow('2026-04-20 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('monthly', null, 15, '14:30');
        $this->assertEquals('2026-05-15 14:30:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_monthly_fires_this_month_when_target_day_still_ahead(): void
    {
        // On the 10th, requesting day_of_month=15 should fire THIS month on the 15th,
        // not skip to next month.
        $tz = $this->freezeNow('2026-04-10 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('monthly', null, 15, '14:30');
        $this->assertEquals('2026-04-15 14:30:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_monthly_clamps_to_last_day_of_month(): void
    {
        // February 2026 has 28 days; requesting day 31 should clamp to 28 (this month)
        $tz = $this->freezeNow('2026-02-01 08:00:00');
        $next = PayoutApprovalService::calculateNextExecutionTime('monthly', null, 31, '09:00');
        $this->assertEquals('2026-02-28 09:00:00', $next->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    // Simulates the post-run advance: cron fires at the scheduled slot, stamps
    // last_executed_at, then recalculates next_execution_at. Verifies the rule
    // steps cleanly to the next period for each frequency over three cycles.
    public function test_daily_advances_cleanly_across_three_cycles(): void
    {
        $tz = tourOperator()->timezone;
        $cycle = ['2026-04-19 09:00:05', '2026-04-20 09:00:05', '2026-04-21 09:00:05'];
        $expected = ['2026-04-20 09:00:00', '2026-04-21 09:00:00', '2026-04-22 09:00:00'];
        foreach ($cycle as $i => $fireAt) {
            Carbon::setTestNow(Carbon::parse($fireAt, $tz));
            $next = PayoutApprovalService::calculateNextExecutionTime('daily', null, null, '09:00');
            $this->assertEquals($expected[$i], $next->setTimezone($tz)->format('Y-m-d H:i:s'));
        }
    }

    public function test_weekly_advances_cleanly_across_three_cycles(): void
    {
        $tz = tourOperator()->timezone;
        // Mondays: 2026-04-20, 2026-04-27, 2026-05-04
        $cycle = ['2026-04-20 09:00:05', '2026-04-27 09:00:05', '2026-05-04 09:00:05'];
        $expected = ['2026-04-27 09:00:00', '2026-05-04 09:00:00', '2026-05-11 09:00:00'];
        foreach ($cycle as $i => $fireAt) {
            Carbon::setTestNow(Carbon::parse($fireAt, $tz));
            $next = PayoutApprovalService::calculateNextExecutionTime('weekly', 1, null, '09:00');
            $this->assertEquals($expected[$i], $next->setTimezone($tz)->format('Y-m-d H:i:s'));
        }
    }

    public function test_monthly_advances_cleanly_across_three_cycles(): void
    {
        $tz = tourOperator()->timezone;
        $cycle = ['2026-04-15 14:30:05', '2026-05-15 14:30:05', '2026-06-15 14:30:05'];
        $expected = ['2026-05-15 14:30:00', '2026-06-15 14:30:00', '2026-07-15 14:30:00'];
        foreach ($cycle as $i => $fireAt) {
            Carbon::setTestNow(Carbon::parse($fireAt, $tz));
            $next = PayoutApprovalService::calculateNextExecutionTime('monthly', null, 15, '14:30');
            $this->assertEquals($expected[$i], $next->setTimezone($tz)->format('Y-m-d H:i:s'));
        }
    }

    // ---------------------------------------------------------------
    // Test 17: multiple pending payouts don't double-pay on execution
    // (recalculate catches overlap)
    // ---------------------------------------------------------------
    public function test_recalculate_handles_overlapping_pending_payouts(): void
    {
        $rule = $this->createRule();

        $bookingId = $this->createBooking();
        $stripeAccountId = 'acct_overlap' . Str::random(8);

        $payoutData = [
            'total_amount' => 300.00,
            'platform_fee' => 10.00,
            'net_amount' => 290.00,
            'booking_count' => 1,
            'booking_ids' => [$bookingId],
            'booking_details' => [
                $bookingId => ['amount' => 300.00, 'fee_amount' => 10.00, 'net_total' => 290.00],
            ],
        ];

        // Create two pending payouts with the same booking (simulates daily runs)
        $payout1 = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);
        $payout2 = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);

        static::$insertedIds['pending_payouts'][] = $payout1->id;
        static::$insertedIds['pending_payouts'][] = $payout2->id;
        foreach ($payout1->items as $item) { $this->trackPendingItem($item->id); }
        foreach ($payout2->items as $item) { $this->trackPendingItem($item->id); }
        foreach ($payout1->logs as $log) { $this->trackLog($log->id); }
        foreach ($payout2->logs as $log) { $this->trackLog($log->id); }

        // Simulate payout1 being executed (booking marked as paid)
        DB::table('tour_bookings_master')
            ->where('id', $bookingId)
            ->update(['payout_made' => 1]);

        // Recalculate payout2 -- should remove the already-paid booking
        $result = $this->service->recalculatePendingPayout($payout2);

        $newLogs = PayoutApprovalLog::where('pending_payout_id', $payout2->id)
            ->whereNotIn('id', static::$insertedIds['payout_approval_logs'])
            ->get();
        foreach ($newLogs as $log) { $this->trackLog($log->id); }

        $this->assertCount(1, $result['removed']);
        $this->assertEquals('Already paid out in another payout', $result['removed'][0]['reason']);

        $payout2->refresh();
        $this->assertEquals(0, $payout2->booking_count);
        $this->assertEquals('0.00', $payout2->net_amount);

        // Cleanup
        DB::table('tour_bookings_master')
            ->where('id', $bookingId)
            ->update(['payout_made' => 0]);
    }

    // ---------------------------------------------------------------
    // Test 18: reject voids linked adjustments
    // ---------------------------------------------------------------
    public function test_reject_voids_linked_adjustments(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking();
        $payout = $this->createPendingPayoutRecord($rule);

        // Create a pending adjustment linked by payout_reference using a real booking
        $adjId = DB::table('payout_adjustments')->insertGetId([
            'payout_id' => null,
            'payout_reference' => $payout->payout_reference,
            'booking_id' => $bookingId,
            'type' => 'debit',
            'gross_amount' => 50.00,
            'platform_fee' => 1.50,
            'net_amount' => 48.50,
            'reason' => 'Test refund adjustment',
            'source_history_ids' => json_encode([]),
            'status' => 'pending',
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->rejectPayout($payout, $this->admin->id, 'Test rejection', '127.0.0.1');

        // Track logs
        $newLogs = PayoutApprovalLog::where('pending_payout_id', $payout->id)->get();
        foreach ($newLogs as $log) { $this->trackLog($log->id); }

        // Adjustment should be voided
        $adj = DB::table('payout_adjustments')->where('id', $adjId)->first();
        $this->assertEquals('voided', $adj->status);
        $this->assertStringContainsString('Test rejection', $adj->void_reason);

        // Cleanup
        DB::table('payout_adjustments')->where('id', $adjId)->delete();
    }

    // ---------------------------------------------------------------
    // Test 19: getConnectedAccounts uses tour operator
    // ---------------------------------------------------------------
    public function test_connected_accounts_from_tour_operator(): void
    {
        $accounts = $this->service->getConnectedAccounts();

        $this->assertCount(1, $accounts);
        $this->assertEquals(
            tourOperator()->stripe_destination_id,
            $accounts->first()->stripe_destination_id
        );
    }

    // ---------------------------------------------------------------
    // Test 20: reapprove resets failed payout for retry
    // ---------------------------------------------------------------
    public function test_reapprove_resets_failed_payout(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayoutRecord($rule, [
            'status' => 'failed',
            'retry_count' => 3,
            'last_failed_at' => now(),
            'last_failure_reason' => 'Insufficient balance',
        ]);

        $this->service->reapprovePayout($payout, $this->admin->id, '127.0.0.1', 'Try again');

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals(0, $payout->retry_count);
        $this->assertNull($payout->last_failed_at);
        $this->assertNull($payout->last_failure_reason);

        $newLogs = PayoutApprovalLog::where('pending_payout_id', $payout->id)->get();
        foreach ($newLogs as $log) { $this->trackLog($log->id); }
    }

    // ---------------------------------------------------------------
    // Fix 2: AutoPayout stores Stripe payout ID so the webhook can locate the row
    // ---------------------------------------------------------------
    public function test_auto_payout_row_persists_stripe_payout_id(): void
    {
        $rule = $this->createRule();

        $reference = 'AP-TEST-' . strtoupper(Str::random(6));
        $stripeId = 'po_' . Str::random(24);

        $payout = AutoPayout::create([
            'payout_reference' => $reference,
            'stripe_payout_id' => $stripeId,
            'rule_id' => $rule->id,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'booking_count' => 1,
            'processed_date' => now(),
            'status' => 'completed',
        ]);
        static::$insertedIds['auto_payouts'][] = $payout->id;

        // Round-trip: the webhook's lookup key must find this row.
        $located = AutoPayout::where('stripe_payout_id', $stripeId)->first();

        $this->assertNotNull($located);
        $this->assertEquals($payout->id, $located->id);
        $this->assertEquals($reference, $located->payout_reference);
    }

    // ---------------------------------------------------------------
    // Fix 3: payout.failed webhook releases the bookings so they requalify
    // ---------------------------------------------------------------
    public function test_webhook_payout_failed_resets_payout_made_flag(): void
    {
        $rule = $this->createRule();

        $bookingId1 = $this->createBooking();
        $bookingId2 = $this->createBooking();

        // Seed the bookings as already paid-out (what Stripe's "success" left behind)
        TourBooking::whereIn('id', [$bookingId1, $bookingId2])
            ->update(['payout_made' => 1]);

        $stripeId = 'po_' . Str::random(24);
        $autoPayout = AutoPayout::create([
            'payout_reference' => 'AP-FAIL-' . strtoupper(Str::random(6)),
            'stripe_payout_id' => $stripeId,
            'rule_id' => $rule->id,
            'total_amount' => 200.00,
            'platform_fee' => 6.40,
            'net_amount' => 193.60,
            'booking_count' => 2,
            'processed_date' => now(),
            'status' => 'completed',
        ]);
        static::$insertedIds['auto_payouts'][] = $autoPayout->id;

        foreach ([$bookingId1, $bookingId2] as $bid) {
            $item = AutoPayoutItem::create([
                'payout_id' => $autoPayout->id,
                'booking_id' => $bid,
                'total_amount' => 100.00,
                'platform_fee' => 3.20,
                'net_amount' => 96.80,
            ]);
            static::$insertedIds['auto_payout_items'][] = $item->id;
        }

        // Minimal Stripe event shape matching what the real SDK hands to the handler.
        $event = (object) [
            'type' => 'payout.failed',
            'data' => (object) [
                'object' => (object) [
                    'id' => $stripeId,
                    'arrival_date' => null,
                ],
            ],
        ];

        app(StripeWebhookController::class)->handleStripeAutoPayout($event);

        $autoPayout->refresh();
        $this->assertEquals('failed', $autoPayout->status);

        $b1 = TourBooking::find($bookingId1);
        $b2 = TourBooking::find($bookingId2);
        $this->assertEquals(0, (int) $b1->payout_made);
        $this->assertEquals(0, (int) $b2->payout_made);
    }

    // ---------------------------------------------------------------
    // Fix 1+2: recalculate keeps the snapshotted adjustments folded into net,
    // and drops them once they have been consumed (applied) by another payout.
    // Guards against the bug where recalc-before-execution stripped adjustments
    // from the Stripe amount while still marking them applied.
    // ---------------------------------------------------------------
    public function test_recalculate_folds_snapshot_adjustments_into_net(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking();
        $stripeAccountId = 'acct_adjcalc' . Str::random(8);

        $txn = DB::table('transactions')
            ->where('tour_booking_id', $bookingId)
            ->where('status', 'Completed')
            ->first();
        $amount = (float) $txn->amount;
        $fee = (float) $txn->fee_amount;
        $bookingNet = round($amount - $fee, 2);

        // A pending credit adjustment that gets snapshotted into the payout.
        $adjId = DB::table('payout_adjustments')->insertGetId([
            'payout_id' => null, 'payout_reference' => null, 'booking_id' => $bookingId,
            'type' => 'credit', 'gross_amount' => 50.00, 'platform_fee' => 0.00, 'net_amount' => 50.00,
            'reason' => 'Snapshot credit', 'source_history_ids' => json_encode([]),
            'status' => 'pending', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $payoutData = [
            'total_amount' => $amount,
            'platform_fee' => $fee,
            'net_amount' => round($bookingNet + 50.00, 2),
            'booking_count' => 1,
            'booking_ids' => [$bookingId],
            'booking_details' => [
                $bookingId => ['amount' => $amount, 'fee_amount' => $fee, 'net_total' => $bookingNet],
            ],
            'adjustment_ids' => [$adjId],
        ];

        $pendingPayout = $this->service->createPendingPayout($rule, $stripeAccountId, $payoutData);
        static::$insertedIds['pending_payouts'][] = $pendingPayout->id;
        foreach ($pendingPayout->items as $item) { $this->trackPendingItem($item->id); }
        foreach ($pendingPayout->logs as $log) { $this->trackLog($log->id); }

        // Snapshot persisted into calculation_data.
        $pendingPayout->refresh();
        $this->assertEquals([$adjId], array_map('intval', $pendingPayout->calculation_data['adjustment_ids']));

        // Recalc keeps the credit folded in; total tracks bookings only.
        $this->service->recalculatePendingPayout($pendingPayout);
        $newLogs = PayoutApprovalLog::where('pending_payout_id', $pendingPayout->id)
            ->whereNotIn('id', static::$insertedIds['payout_approval_logs'])->get();
        foreach ($newLogs as $log) { $this->trackLog($log->id); }

        $pendingPayout->refresh();
        $this->assertEquals(round($bookingNet + 50.00, 2), (float) $pendingPayout->net_amount);
        $this->assertEquals(round($amount, 2), (float) $pendingPayout->total_amount);

        // Once the adjustment is consumed elsewhere (status applied), recalc drops it.
        DB::table('payout_adjustments')->where('id', $adjId)->update(['status' => 'applied', 'applied_at' => now()]);
        $this->service->recalculatePendingPayout($pendingPayout);
        $pendingPayout->refresh();
        $this->assertEquals(round($bookingNet, 2), (float) $pendingPayout->net_amount);

        DB::table('payout_adjustments')->where('id', $adjId)->delete();
    }

    // ---------------------------------------------------------------
    // Fix 2: a pure-adjustment payout (no booking items) is re-validated
    // against its snapshot on recalc, and falls to zero once consumed.
    // ---------------------------------------------------------------
    public function test_recalculate_pure_adjustment_payout(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking(); // FK target for the adjustment row
        $stripeAccountId = 'acct_pureadj' . Str::random(8);

        $adjId = DB::table('payout_adjustments')->insertGetId([
            'payout_id' => null, 'payout_reference' => null, 'booking_id' => $bookingId,
            'type' => 'credit', 'gross_amount' => 80.00, 'platform_fee' => 0.00, 'net_amount' => 80.00,
            'reason' => 'Pure adjustment', 'source_history_ids' => json_encode([]),
            'status' => 'pending', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Pending payout with NO items but a snapshotted adjustment.
        $payout = $this->createPendingPayoutRecord($rule, [
            'total_amount' => 0, 'platform_fee' => 0, 'net_amount' => 80.00, 'booking_count' => 0,
            'calculation_data' => [
                'booking_ids' => [], 'booking_details' => [],
                'calculation_date' => now()->toDateTimeString(),
                'stripe_account_id' => $stripeAccountId,
                'adjustment_ids' => [$adjId],
            ],
        ]);

        $this->service->recalculatePendingPayout($payout);
        $payout->refresh();
        $this->assertEquals(80.00, (float) $payout->net_amount);
        $this->assertEquals(0, (int) $payout->booking_count);

        DB::table('payout_adjustments')->where('id', $adjId)->update(['status' => 'applied', 'applied_at' => now()]);
        $this->service->recalculatePendingPayout($payout);
        $payout->refresh();
        $this->assertEquals(0.00, (float) $payout->net_amount);

        DB::table('payout_adjustments')->where('id', $adjId)->delete();
    }

    /**
     * payout_approval_logs.created_at is `timestamp NOT NULL DEFAULT
     * CURRENT_TIMESTAMP`, and MySQL runs on UTC while the app runs on
     * America/Phoenix. PayoutApprovalLog sets $timestamps = false, so callers
     * pass created_at explicitly -- but if it is missing from $fillable,
     * mass-assignment drops it silently and MySQL writes UTC instead, which the
     * 'datetime' cast reads back as Phoenix: every row 7h in the future.
     *
     * Asserts the RAW stored string, not the cast attribute -- the cast would
     * hide the very mismatch under test.
     */
    public function test_approval_log_created_at_is_stored_in_app_timezone(): void
    {
        // Only meaningful while the DB clock and the app clock actually differ.
        $dbNow = DB::selectOne('SELECT NOW() AS n')->n;
        $offset = abs(strtotime($dbNow) - strtotime(now()->toDateTimeString()));
        if ($offset < 60) {
            $this->markTestSkipped('DB and app share a timezone here; this test cannot detect the bug.');
        }

        $rule = $this->createRule();
        $pendingPayout = $this->createPendingPayoutRecord($rule);

        $log = PayoutApprovalLog::create([
            'pending_payout_id' => $pendingPayout->id,
            'action' => 'approved',
            'user_id' => $this->admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'notes' => 'timezone regression check',
            'created_at' => now(),
        ]);
        static::$insertedIds['payout_approval_logs'][] = $log->id;

        $raw = DB::table('payout_approval_logs')->where('id', $log->id)->value('created_at');

        $driftFromApp = abs(strtotime($raw) - strtotime(now()->toDateTimeString()));
        $driftFromDb = abs(strtotime($raw) - strtotime($dbNow));

        $this->assertLessThan(
            60,
            $driftFromApp,
            "created_at should be app time (America/Phoenix). Stored '{$raw}', app now '" . now()->toDateTimeString() . "', DB now '{$dbNow}'."
        );
        $this->assertGreaterThan(
            60,
            $driftFromDb,
            "created_at matches the DB's UTC clock, so the explicit now() was dropped -- check 'created_at' is in PayoutApprovalLog::\$fillable."
        );
    }

    // ---------------------------------------------------------------
    // Test 25: an executed payout may share its pending payout's reference
    // ---------------------------------------------------------------
    public function test_auto_payout_may_share_reference_with_its_pending_payout(): void
    {
        $rule = $this->createRule();
        $pending = $this->createPendingPayoutRecord($rule);

        // executeSinglePayout() copies the reference verbatim rather than appending a
        // random suffix, so the same string now lives in both pending_payouts and
        // auto_payouts. Both columns are unique, but the indexes are per-table, so
        // this must not raise a duplicate key error.
        $autoPayout = AutoPayout::create([
            'payout_reference' => $pending->payout_reference,
            'rule_id' => $rule->id,
            'total_amount' => $pending->total_amount,
            'platform_fee' => $pending->platform_fee,
            'net_amount' => $pending->net_amount,
            'booking_count' => $pending->booking_count,
            'processed_date' => now(),
            'status' => 'pending',
        ]);
        static::$insertedIds['auto_payouts'][] = $autoPayout->id;

        $this->assertEquals(
            $pending->payout_reference,
            $autoPayout->fresh()->payout_reference,
            'The executed payout should carry its pending payout reference unchanged.'
        );
    }

    // ---------------------------------------------------------------
    // Test 26: forExecutedPayout resolves adjustments by FK and by reference
    // ---------------------------------------------------------------
    public function test_for_executed_payout_matches_by_foreign_key_and_reference(): void
    {
        $rule = $this->createRule();
        $pending = $this->createPendingPayoutRecord($rule);
        $bookingViaFk = $this->createBooking();
        $bookingViaReference = $this->createBooking();

        // Simulate a payout written BEFORE the suffix was removed: the auto_payouts
        // reference carries an extra segment that the pending payout does not.
        $legacyReference = $pending->payout_reference . '-XFYK';
        $autoPayout = AutoPayout::create([
            'payout_reference' => $legacyReference,
            'rule_id' => $rule->id,
            'total_amount' => 500.00,
            'platform_fee' => 14.80,
            'net_amount' => 485.20,
            'booking_count' => 2,
            'processed_date' => now(),
            'status' => 'completed',
        ]);
        static::$insertedIds['auto_payouts'][] = $autoPayout->id;

        // Folded in at approval time: holds the PENDING reference, linked only by FK.
        $fkAdjId = DB::table('payout_adjustments')->insertGetId([
            'payout_id' => null,
            'payout_reference' => $pending->payout_reference,
            'booking_id' => $bookingViaFk,
            'type' => 'debit',
            'gross_amount' => 50.00,
            'platform_fee' => 1.50,
            'net_amount' => 48.50,
            'reason' => 'Folded in at approval time',
            'source_history_ids' => json_encode([]),
            'status' => 'applied',
            'applied_in_payout_id' => $autoPayout->id,
            'applied_at' => now(),
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Detected after the payout completed: holds the EXECUTED reference, no FK.
        $refAdjId = DB::table('payout_adjustments')->insertGetId([
            'payout_id' => $autoPayout->id,
            'payout_reference' => $legacyReference,
            'booking_id' => $bookingViaReference,
            'type' => 'credit',
            'gross_amount' => 20.00,
            'platform_fee' => 0.80,
            'net_amount' => 19.20,
            'reason' => 'Detected after payout',
            'source_history_ids' => json_encode([]),
            'status' => 'pending',
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $matched = \App\Models\PayoutAdjustment::forExecutedPayout($autoPayout)
                ->pluck('booking_id')
                ->all();

            sort($matched);
            $expected = [$bookingViaFk, $bookingViaReference];
            sort($expected);

            $this->assertEquals(
                $expected,
                $matched,
                'forExecutedPayout should resolve adjustments linked by FK as well as by reference.'
            );

            // The reference-only scope is what the controller used to call. It misses
            // the folded-in adjustment, which is the defect this scope exists to fix.
            $referenceOnly = \App\Models\PayoutAdjustment::forPayoutReference($autoPayout->payout_reference)
                ->pluck('booking_id')
                ->all();
            $this->assertEquals(
                [$bookingViaReference],
                $referenceOnly,
                'Reference-only matching should miss the adjustment carrying the pending reference.'
            );

            // The two conditions must be grouped, so a caller's own booking filter
            // applies to the whole match instead of binding to the second arm.
            $filtered = \App\Models\PayoutAdjustment::forExecutedPayout($autoPayout)
                ->whereIn('booking_id', [$bookingViaFk])
                ->pluck('booking_id')
                ->all();
            $this->assertEquals(
                [$bookingViaFk],
                $filtered,
                'An outer booking_id filter must constrain both arms of the scope.'
            );
        } finally {
            DB::table('payout_adjustments')->whereIn('id', [$fkAdjId, $refAdjId])->delete();
        }
    }
}
