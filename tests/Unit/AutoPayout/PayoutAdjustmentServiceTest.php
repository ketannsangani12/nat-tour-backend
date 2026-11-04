<?php

namespace Tests\Unit\AutoPayout;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\AutoPayout;
use App\Models\AutoPayoutItem;
use App\Models\AutoPayoutRule;
use App\Models\PayoutAdjustment;
use App\Services\PayoutAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use PDO;

class PayoutAdjustmentServiceTest extends TestCase
{
    private PayoutAdjustmentService $service;
    private ?Admin $admin = null;

    private static array $builders = [];

    private static array $insertedIds = [
        'payout_adjustments' => [],
        'auto_payout_items' => [],
        'auto_payouts' => [],
        'auto_payout_rules' => [],
        'tour_booking_history' => [],
    ];

    // History IDs we set adjustment_processed on -- need to reset in cleanup
    private static array $historyIdsToReset = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayoutAdjustmentService();
        $this->admin = Admin::first() ?? Admin::factory()->create();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            try {
                $builder->cleanup();
            } catch (Exception $e) {
                fwrite(STDERR, "Booking cleanup failed: {$e->getMessage()}\n");
            }
        }
        static::$builders = [];

        try {
            $host = $_SERVER['DB_HOST'] ?? $_ENV['DB_HOST'] ?? 'mysql';
            $user = $_SERVER['DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'nat_user';
            $pass = $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? 'nat_password';
            $db = 'tenant_dixies';

            $pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Delete in reverse dependency order
            $tables = [
                'payout_adjustments',
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

            // Reset adjustment_processed on history rows we touched
            if (!empty(static::$historyIdsToReset)) {
                $placeholders = implode(',', array_fill(0, count(static::$historyIdsToReset), '?'));
                $stmt = $pdo->prepare("UPDATE tour_booking_history SET adjustment_processed = 0 WHERE id IN ({$placeholders})");
                $stmt->execute(array_values(static::$historyIdsToReset));
            }

            // Delete test history rows we inserted
            $historyIds = static::$insertedIds['tour_booking_history'] ?? [];
            if (!empty($historyIds)) {
                $placeholders = implode(',', array_fill(0, count($historyIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM tour_booking_history WHERE id IN ({$placeholders})");
                $stmt->execute(array_values($historyIds));
            }

            // Reset payout_made on bookings
            foreach (static::$builders as $builder) {
                $pdo->exec("UPDATE tour_bookings_master SET payout_made = 0 WHERE id = {$builder->bookingId}");
            }
        } catch (Exception $e) {
            fwrite(STDERR, "Cleanup failed: {$e->getMessage()}\n");
        }

        parent::tearDownAfterClass();
    }

    private function createBooking(): int
    {
        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->create();
        static::$builders[] = $builder;
        return $builder->bookingId;
    }

    private function createRule(array $overrides = []): AutoPayoutRule
    {
        $rule = AutoPayoutRule::create(array_merge([
            'name' => 'Adj Test Rule ' . Str::random(4),
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

    private function createCompletedPayout(AutoPayoutRule $rule, array $bookingIds): AutoPayout
    {
        $reference = 'AP-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $payout = AutoPayout::create([
            'payout_reference' => $reference,
            'rule_id' => $rule->id,
            'total_amount' => count($bookingIds) * 100.00,
            'platform_fee' => count($bookingIds) * 3.20,
            'net_amount' => count($bookingIds) * 96.80,
            'booking_count' => count($bookingIds),
            'processed_date' => now()->subDay(),
            'status' => 'completed',
        ]);

        static::$insertedIds['auto_payouts'][] = $payout->id;

        foreach ($bookingIds as $bookingId) {
            $item = AutoPayoutItem::create([
                'payout_id' => $payout->id,
                'booking_id' => $bookingId,
                'total_amount' => 100.00,
                'platform_fee' => 3.20,
                'net_amount' => 96.80,
            ]);
            static::$insertedIds['auto_payout_items'][] = $item->id;
        }

        // Mark bookings as paid out
        DB::table('tour_bookings_master')
            ->whereIn('id', $bookingIds)
            ->update(['payout_made' => 1]);

        return $payout;
    }

    private function insertPostPayoutHistory(int $bookingId, string $operation, float $amount, float $fee = 0): int
    {
        $id = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId,
            'operation' => $operation,
            'ticket_cost' => $amount,
            'platform_fees' => $fee,
            'processing_fees' => $fee,
            'payment_type' => 'Card',
            'details' => json_encode(['amount' => $amount, 'payment_type' => 'Card']),
            'adjustment_processed' => 0,
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        static::$insertedIds['tour_booking_history'][] = $id;
        return $id;
    }

    private function trackAdjustment(int $id): void
    {
        static::$insertedIds['payout_adjustments'][] = $id;
    }

    // ---------------------------------------------------------------
    // Test 1: detect creates debit adjustment for post-payout refund
    // ---------------------------------------------------------------
    public function test_detect_creates_debit_for_post_payout_refund(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        // Mark any pre-existing history as already processed so it doesn't interfere
        DB::table('tour_booking_history')
            ->where('tour_booking_id', $bookingId)
            ->update(['adjustment_processed' => 1]);

        // Insert a refund AFTER the payout was processed
        $historyId = $this->insertPostPayoutHistory($bookingId, 'Refund', 50.00, 1.60);

        $adjustments = $this->service->detectAndCreateAdjustments();

        // Find the adjustment linked to our specific history ID
        $adj = $adjustments->first(fn($a) => in_array($historyId, $a->source_history_ids ?? []));
        $this->assertNotNull($adj, 'Expected an adjustment for the refunded booking');
        // Track all adjustments created for this booking for cleanup
        foreach ($adjustments as $a) {
            $this->trackAdjustment($a->id);
        }

        $this->assertEquals('debit', $adj->type);
        $this->assertEquals('50.00', $adj->gross_amount);
        $this->assertEquals('pending', $adj->status);
        $this->assertEquals($payout->id, $adj->payout_id);
        $this->assertEquals($payout->payout_reference, $adj->payout_reference);

        // Verify history row is marked as processed
        $historyRow = DB::table('tour_booking_history')->where('id', $historyId)->first();
        $this->assertEquals(1, $historyRow->adjustment_processed);
        static::$historyIdsToReset[] = $historyId;
    }

    // ---------------------------------------------------------------
    // Test 2: detect creates credit adjustment for post-payout payment
    // ---------------------------------------------------------------
    public function test_detect_creates_credit_for_post_payout_payment(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        // Mark pre-existing history as processed
        DB::table('tour_booking_history')
            ->where('tour_booking_id', $bookingId)
            ->update(['adjustment_processed' => 1]);

        $historyId = $this->insertPostPayoutHistory($bookingId, 'Payment', 75.00, 2.40);

        $adjustments = $this->service->detectAndCreateAdjustments();

        $adj = $adjustments->first(fn($a) => in_array($historyId, $a->source_history_ids ?? []));
        $this->assertNotNull($adj);
        foreach ($adjustments as $a) {
            $this->trackAdjustment($a->id);
        }

        $this->assertEquals('credit', $adj->type);
        $this->assertEquals('75.00', $adj->gross_amount);
        $this->assertEquals('pending', $adj->status);

        static::$historyIdsToReset[] = $historyId;
    }

    // ---------------------------------------------------------------
    // Test 3: detect is idempotent -- does not create duplicates
    // ---------------------------------------------------------------
    public function test_detect_does_not_create_duplicate_adjustments(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $historyId = $this->insertPostPayoutHistory($bookingId, 'Refund', 30.00, 0.96);

        // First detection
        $first = $this->service->detectAndCreateAdjustments();
        $adj = $first->first(fn($a) => $a->booking_id == $bookingId);
        $this->assertNotNull($adj);
        $this->trackAdjustment($adj->id);

        // Second detection -- should find nothing new
        $second = $this->service->detectAndCreateAdjustments();
        $dup = $second->first(fn($a) => $a->booking_id == $bookingId);
        $this->assertNull($dup, 'Should not create a duplicate adjustment');

        static::$historyIdsToReset[] = $historyId;
    }

    // ---------------------------------------------------------------
    // Test 4: voided adjustment stays voided -- not re-detected
    // ---------------------------------------------------------------
    public function test_voided_adjustment_is_not_redetected(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $historyId = $this->insertPostPayoutHistory($bookingId, 'Refund', 40.00, 1.28);

        // Detect
        $adjustments = $this->service->detectAndCreateAdjustments();
        $adj = $adjustments->first(fn($a) => $a->booking_id == $bookingId);
        $this->assertNotNull($adj);
        $this->trackAdjustment($adj->id);

        // Void it
        $this->service->voidAdjustment($adj, $this->admin->id, 'Voided for testing');

        $adj->refresh();
        $this->assertEquals('voided', $adj->status);

        // Re-detect -- should NOT re-create because adjustment_processed = 1
        $redetected = $this->service->detectAndCreateAdjustments();
        $rediscovered = $redetected->first(fn($a) => $a->booking_id == $bookingId);
        $this->assertNull($rediscovered, 'Voided adjustment should not be re-detected');

        static::$historyIdsToReset[] = $historyId;
    }

    // ---------------------------------------------------------------
    // Test 5: getPendingAdjustmentTotal includes detected adjustments
    // ---------------------------------------------------------------
    public function test_get_pending_adjustment_total_includes_adjustments(): void
    {
        $booking1 = $this->createBooking();
        $booking2 = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$booking1, $booking2]);

        // Mark pre-existing history as processed
        DB::table('tour_booking_history')
            ->whereIn('tour_booking_id', [$booking1, $booking2])
            ->update(['adjustment_processed' => 1]);

        // Snapshot totals before we add ours
        $before = $this->service->getPendingAdjustmentTotal();

        $h1 = $this->insertPostPayoutHistory($booking1, 'Refund', 60.00, 0);
        $h2 = $this->insertPostPayoutHistory($booking2, 'Payment', 25.00, 0);

        $this->service->detectAndCreateAdjustments();

        // Track all adjustments we just created
        $adjs = PayoutAdjustment::whereIn('booking_id', [$booking1, $booking2])->get();
        foreach ($adjs as $a) {
            $this->trackAdjustment($a->id);
        }

        $after = $this->service->getPendingAdjustmentTotal();

        // credit_total should have increased by 25
        $this->assertEquals(25.00, round($after['credit_total'] - $before['credit_total'], 2));
        // debit_total should have increased by 60
        $this->assertEquals(60.00, round($after['debit_total'] - $before['debit_total'], 2));
        // count should have increased by 2
        $this->assertEquals(2, $after['count'] - $before['count']);

        static::$historyIdsToReset[] = $h1;
        static::$historyIdsToReset[] = $h2;
    }

    // ---------------------------------------------------------------
    // Test 6: applyAdjustmentsToPayout marks the snapshotted adjustments as applied
    // ---------------------------------------------------------------
    public function test_apply_adjustments_marks_as_applied(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $historyId = $this->insertPostPayoutHistory($bookingId, 'Refund', 45.00, 1.44);

        $this->service->detectAndCreateAdjustments();
        $adj = PayoutAdjustment::where('booking_id', $bookingId)->pending()->first();
        $this->assertNotNull($adj);
        $this->trackAdjustment($adj->id);

        // Create a new payout to apply adjustments to
        $newPayout = $this->createCompletedPayout($rule, []);
        $newPayout->update(['booking_count' => 0, 'total_amount' => 0, 'net_amount' => 0]);

        // Apply EXACTLY the snapshotted id (the new contract) -- not a global sweep.
        $count = $this->service->applyAdjustmentsToPayout($newPayout, [$adj->id]);

        $this->assertEquals(1, $count);

        $adj->refresh();
        $this->assertEquals('applied', $adj->status);
        $this->assertEquals($newPayout->id, $adj->applied_in_payout_id);
        $this->assertNotNull($adj->applied_at);

        static::$historyIdsToReset[] = $historyId;
    }

    // ---------------------------------------------------------------
    // Test 6b: applyAdjustmentsToPayout applies ONLY the passed ids, and
    // a second call for an already-applied id is a no-op (the guard that
    // stops two stacked payouts from both claiming the same adjustment).
    // ---------------------------------------------------------------
    public function test_apply_adjustments_is_scoped_and_idempotent(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        // Two independent pending adjustments on the same payout.
        $adjA = $this->service->createManualAdjustment([
            'payout_id' => $payout->id, 'booking_id' => $bookingId,
            'type' => 'credit', 'net_amount' => 20.00, 'reason' => 'Scope test A',
        ], $this->admin->id);
        $adjB = $this->service->createManualAdjustment([
            'payout_id' => $payout->id, 'booking_id' => $bookingId,
            'type' => 'credit', 'net_amount' => 30.00, 'reason' => 'Scope test B',
        ], $this->admin->id);
        $this->trackAdjustment($adjA->id);
        $this->trackAdjustment($adjB->id);

        $payoutA = $this->createCompletedPayout($rule, []);
        $payoutB = $this->createCompletedPayout($rule, []);

        // Apply only A to payoutA -- B must stay pending.
        $countA = $this->service->applyAdjustmentsToPayout($payoutA, [$adjA->id]);
        $this->assertEquals(1, $countA);
        $adjA->refresh();
        $adjB->refresh();
        $this->assertEquals('applied', $adjA->status);
        $this->assertEquals($payoutA->id, $adjA->applied_in_payout_id);
        $this->assertEquals('pending', $adjB->status);

        // A second payout that snapshotted the same A id finds nothing to apply.
        $countAgain = $this->service->applyAdjustmentsToPayout($payoutB, [$adjA->id]);
        $this->assertEquals(0, $countAgain);
        $adjA->refresh();
        $this->assertEquals($payoutA->id, $adjA->applied_in_payout_id, 'A must stay attributed to the first payout');

        // Empty/null id list applies nothing (no global sweep).
        $this->assertEquals(0, $this->service->applyAdjustmentsToPayout($payoutB, []));
        $this->assertEquals(0, $this->service->applyAdjustmentsToPayout($payoutB, null));
        $adjB->refresh();
        $this->assertEquals('pending', $adjB->status, 'B must remain pending when not snapshotted');
    }

    // ---------------------------------------------------------------
    // Test 6c: getPendingAdjustmentTotal returns ids aligned with totals
    // ---------------------------------------------------------------
    public function test_get_pending_adjustment_total_returns_ids(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $adj = $this->service->createManualAdjustment([
            'payout_id' => $payout->id, 'booking_id' => $bookingId,
            'type' => 'credit', 'net_amount' => 12.34, 'reason' => 'Ids test',
        ], $this->admin->id);
        $this->trackAdjustment($adj->id);

        $totals = $this->service->getPendingAdjustmentTotal();

        $this->assertArrayHasKey('ids', $totals);
        $this->assertContains($adj->id, $totals['ids']);
        $this->assertCount($totals['count'], $totals['ids']);
    }

    // ---------------------------------------------------------------
    // Test 7: void only works on pending adjustments
    // ---------------------------------------------------------------
    public function test_void_rejects_non_pending_adjustment(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $historyId = $this->insertPostPayoutHistory($bookingId, 'Refund', 20.00, 0);
        $this->service->detectAndCreateAdjustments();

        $adj = PayoutAdjustment::where('booking_id', $bookingId)->pending()->first();
        $this->assertNotNull($adj);
        $this->trackAdjustment($adj->id);

        // Apply it first
        $adj->update(['status' => 'applied', 'applied_at' => now()]);

        // Now try to void -- should throw
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only pending adjustments can be voided');

        $this->service->voidAdjustment($adj, $this->admin->id, 'Should fail');

        static::$historyIdsToReset[] = $historyId;
    }

    // ---------------------------------------------------------------
    // Test 8: manual adjustment creation
    // ---------------------------------------------------------------
    public function test_create_manual_adjustment(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $adj = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'debit',
            'net_amount' => 35.50,
            'reason' => 'Manual correction for over-payment',
        ], $this->admin->id);

        $this->trackAdjustment($adj->id);

        $this->assertEquals('debit', $adj->type);
        $this->assertEquals('35.50', $adj->net_amount);
        $this->assertEquals('35.50', $adj->gross_amount);
        $this->assertEquals('0.00', $adj->platform_fee);
        $this->assertEquals('pending', $adj->status);
        $this->assertEquals($payout->id, $adj->payout_id);
        $this->assertEquals($payout->payout_reference, $adj->payout_reference);
        $this->assertEquals($bookingId, $adj->booking_id);
        $this->assertEmpty($adj->source_history_ids);
        $this->assertStringContainsString('Manual correction', $adj->reason);
    }

    // ---------------------------------------------------------------
    // Test 9: manual adjustment with gross_amount and platform_fee
    // ---------------------------------------------------------------
    public function test_create_manual_adjustment_with_fees(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $adj = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'credit',
            'gross_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'reason' => 'Additional payment received after payout',
        ], $this->admin->id);

        $this->trackAdjustment($adj->id);

        $this->assertEquals('credit', $adj->type);
        $this->assertEquals('100.00', $adj->gross_amount);
        $this->assertEquals('3.20', $adj->platform_fee);
        $this->assertEquals('96.80', $adj->net_amount);
    }

    // ---------------------------------------------------------------
    // Test 10: manual adjustment rejects booking not in payout
    // ---------------------------------------------------------------
    public function test_create_manual_adjustment_rejects_invalid_booking(): void
    {
        $bookingId = $this->createBooking();
        $otherBookingId = $this->createBooking();
        $rule = $this->createRule();
        // Payout only includes bookingId, not otherBookingId
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not part of payout');

        $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $otherBookingId,
            'type' => 'debit',
            'net_amount' => 10.00,
            'reason' => 'This should fail - wrong booking',
        ], $this->admin->id);
    }

    // ---------------------------------------------------------------
    // Test 11: signedNetAmount returns correct sign
    // ---------------------------------------------------------------
    public function test_signed_net_amount(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        $credit = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'credit',
            'net_amount' => 50.00,
            'reason' => 'Test credit signed amount',
        ], $this->admin->id);
        $this->trackAdjustment($credit->id);

        $debit = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'debit',
            'net_amount' => 30.00,
            'reason' => 'Test debit signed amount',
        ], $this->admin->id);
        $this->trackAdjustment($debit->id);

        $this->assertEquals(50.00, $credit->signedNetAmount());
        $this->assertEquals(-30.00, $debit->signedNetAmount());
    }

    // ---------------------------------------------------------------
    // Test 12: getSummary returns all three status buckets
    // ---------------------------------------------------------------
    public function test_get_summary_returns_all_statuses(): void
    {
        $bookingId = $this->createBooking();
        $rule = $this->createRule();
        $payout = $this->createCompletedPayout($rule, [$bookingId]);

        // Create a pending adjustment
        $pending = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'debit',
            'net_amount' => 20.00,
            'reason' => 'Summary test pending',
        ], $this->admin->id);
        $this->trackAdjustment($pending->id);

        // Create and apply one
        $applied = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'credit',
            'net_amount' => 15.00,
            'reason' => 'Summary test applied',
        ], $this->admin->id);
        $this->trackAdjustment($applied->id);
        $applied->update(['status' => 'applied', 'applied_at' => now()]);

        // Create and void one
        $voided = $this->service->createManualAdjustment([
            'payout_id' => $payout->id,
            'booking_id' => $bookingId,
            'type' => 'debit',
            'net_amount' => 10.00,
            'reason' => 'Summary test voided',
        ], $this->admin->id);
        $this->trackAdjustment($voided->id);
        $this->service->voidAdjustment($voided, $this->admin->id, 'Voiding for test');

        $summary = $this->service->getSummary();

        $this->assertArrayHasKey('pending', $summary);
        $this->assertArrayHasKey('applied', $summary);
        $this->assertArrayHasKey('voided_count', $summary);
        $this->assertGreaterThanOrEqual(1, $summary['pending']['count']);
        $this->assertGreaterThanOrEqual(1, $summary['applied']['count']);
        $this->assertGreaterThanOrEqual(1, $summary['voided_count']);
    }
}
