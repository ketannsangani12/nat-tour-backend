<?php

namespace Tests\Unit\AutoPayout;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\AutoPayout;
use App\Models\AutoPayoutItem;
use App\Models\AutoPayoutRule;
use App\Models\PendingPayout;
use App\Models\PendingPayoutItem;
use App\Services\PayoutExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use PDO;

/**
 * Covers the adjustment flagging in the export payload.
 *
 * An executed payout has to resolve adjustments by foreign key as well as by
 * reference string. Adjustments folded in at approval time carry the parent
 * pending payout's reference, which does not match the executed row's reference
 * on any payout written while executeSinglePayout() still appended a random
 * suffix. Matching on the reference alone dropped the flag for those silently.
 */
class PayoutExportServiceTest extends TestCase
{
    private PayoutExportService $service;

    private static array $builders = [];

    private static array $insertedIds = [
        'payout_adjustments' => [],
        'pending_payout_items' => [],
        'pending_payouts' => [],
        'auto_payout_items' => [],
        'auto_payouts' => [],
        'auto_payout_rules' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayoutExportService::class);
    }

    private function createBooking(): int
    {
        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        return $builder->bookingId;
    }

    private function createRule(): AutoPayoutRule
    {
        $rule = AutoPayoutRule::create([
            'name' => 'Export Test Rule ' . Str::random(4),
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'execution_time' => '09:00:00',
            'minimum_amount' => 50.00,
            'enabled' => false,
            'requires_approval' => true,
            'next_execution_at' => now()->addWeek(),
        ]);
        static::$insertedIds['auto_payout_rules'][] = $rule->id;

        return $rule;
    }

    private function createAdjustment(array $attributes): int
    {
        $id = DB::table('payout_adjustments')->insertGetId(array_merge([
            'payout_id' => null,
            'type' => 'debit',
            'gross_amount' => 50.00,
            'platform_fee' => 1.50,
            'net_amount' => 48.50,
            'reason' => 'Export service test adjustment',
            'source_history_ids' => json_encode([]),
            'status' => 'pending',
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
        static::$insertedIds['payout_adjustments'][] = $id;

        return $id;
    }

    public function test_auto_payout_export_flags_adjustment_linked_only_by_foreign_key(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking();

        $pendingReference = 'PP-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        // A payout written before the suffix was removed: the executed reference
        // carries an extra segment the folded-in adjustment does not know about.
        $autoPayout = AutoPayout::create([
            'payout_reference' => $pendingReference . '-XFYK',
            'rule_id' => $rule->id,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'booking_count' => 1,
            'processed_date' => now(),
            'status' => 'completed',
        ]);
        static::$insertedIds['auto_payouts'][] = $autoPayout->id;

        $item = AutoPayoutItem::create([
            'payout_id' => $autoPayout->id,
            'booking_id' => $bookingId,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
        ]);
        static::$insertedIds['auto_payout_items'][] = $item->id;

        $this->createAdjustment([
            'payout_reference' => $pendingReference,
            'booking_id' => $bookingId,
            'status' => 'applied',
            'applied_in_payout_id' => $autoPayout->id,
            'applied_at' => now(),
        ]);

        $payload = $this->service->buildForAutoPayout($autoPayout);

        $this->assertCount(1, $payload['items']);
        $this->assertTrue(
            (bool) $payload['items']->first()->has_adjustment,
            'An adjustment linked by foreign key must still flag the exported line.'
        );
        $this->assertCount(
            1,
            $payload['applied_adjustments'],
            'The applied adjustment should appear in the export payload.'
        );
    }

    public function test_auto_payout_export_flags_adjustment_matched_by_reference(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking();

        $reference = 'AP-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $autoPayout = AutoPayout::create([
            'payout_reference' => $reference,
            'rule_id' => $rule->id,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'booking_count' => 1,
            'processed_date' => now(),
            'status' => 'completed',
        ]);
        static::$insertedIds['auto_payouts'][] = $autoPayout->id;

        $item = AutoPayoutItem::create([
            'payout_id' => $autoPayout->id,
            'booking_id' => $bookingId,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
        ]);
        static::$insertedIds['auto_payout_items'][] = $item->id;

        // Detected after the payout completed: carries the executed reference, no FK.
        $this->createAdjustment([
            'payout_reference' => $reference,
            'booking_id' => $bookingId,
        ]);

        $payload = $this->service->buildForAutoPayout($autoPayout);

        $this->assertTrue(
            (bool) $payload['items']->first()->has_adjustment,
            'A post-payout adjustment matched by reference must flag the exported line.'
        );
    }

    public function test_auto_payout_export_does_not_flag_unrelated_adjustment(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking();

        $autoPayout = AutoPayout::create([
            'payout_reference' => 'AP-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'rule_id' => $rule->id,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'booking_count' => 1,
            'processed_date' => now(),
            'status' => 'completed',
        ]);
        static::$insertedIds['auto_payouts'][] = $autoPayout->id;

        $item = AutoPayoutItem::create([
            'payout_id' => $autoPayout->id,
            'booking_id' => $bookingId,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
        ]);
        static::$insertedIds['auto_payout_items'][] = $item->id;

        // Belongs to a different payout entirely. The scope ORs two conditions, so
        // this guards that the booking filter constrains both arms.
        $this->createAdjustment([
            'payout_reference' => 'AP-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'booking_id' => $bookingId,
        ]);

        $payload = $this->service->buildForAutoPayout($autoPayout);

        $this->assertFalse(
            (bool) $payload['items']->first()->has_adjustment,
            'An adjustment belonging to another payout must not flag this line.'
        );
    }

    public function test_pending_payout_export_flags_adjustment_by_reference(): void
    {
        $rule = $this->createRule();
        $bookingId = $this->createBooking();

        $reference = 'PP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $stripeAccountId = 'acct_' . Str::random(16);

        $pending = PendingPayout::create([
            'payout_reference' => $reference,
            'rule_id' => $rule->id,
            'stripe_account_id' => $stripeAccountId,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
            'booking_count' => 1,
            'status' => 'pending_approval',
            'rule_snapshot' => $rule->toArray(),
            'calculation_data' => [
                'booking_ids' => [$bookingId],
                'booking_details' => [],
                'stripe_account_id' => $stripeAccountId,
            ],
        ]);
        static::$insertedIds['pending_payouts'][] = $pending->id;

        $item = PendingPayoutItem::create([
            'pending_payout_id' => $pending->id,
            'booking_id' => $bookingId,
            'total_amount' => 100.00,
            'platform_fee' => 3.20,
            'net_amount' => 96.80,
        ]);
        static::$insertedIds['pending_payout_items'][] = $item->id;

        $this->createAdjustment([
            'payout_reference' => $reference,
            'booking_id' => $bookingId,
        ]);

        $payload = $this->service->buildForPendingPayout($pending);

        $this->assertCount(1, $payload['items']);
        $this->assertTrue(
            (bool) $payload['items']->first()->has_adjustment,
            'A pending payout must still flag adjustments matched on its own reference.'
        );
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

            $pdo = new PDO("mysql:host={$host};dbname=tenant_dixies", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = [
                'payout_adjustments',
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
}
