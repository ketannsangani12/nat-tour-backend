<?php

namespace Tests\Feature\AutoPayout;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\AutoPayoutRule;
use App\Models\PendingPayout;
use App\Models\PayoutApprovalLog;
use Illuminate\Support\Str;
use Exception;
use PDO;

class AutoPayoutApprovalTest extends TestCase
{
    private ?Admin $admin = null;

    private static array $insertedIds = [
        'payout_approval_logs' => [],
        'pending_payout_items' => [],
        'pending_payouts' => [],
        'auto_payout_rules' => [],
        'admins' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::first() ?? Admin::factory()->create();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
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
                'payout_approval_logs',
                'pending_payout_items',
                'pending_payouts',
                'auto_payout_rules',
                'admins',
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

    private function createRule(): AutoPayoutRule
    {
        $rule = AutoPayoutRule::create([
            'name' => 'Test Rule ' . Str::random(4),
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

    private function createPendingPayout(AutoPayoutRule $rule, array $overrides = []): PendingPayout
    {
        $payout = PendingPayout::create(array_merge([
            'payout_reference' => 'PP-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'rule_id' => $rule->id,
            'stripe_account_id' => 'acct_' . Str::random(16),
            'total_amount' => 500.00,
            'platform_fee' => 14.80,
            'net_amount' => 485.20,
            'booking_count' => 3,
            'status' => 'pending_approval',
            'rule_snapshot' => $rule->toArray(),
            'calculation_data' => [
                'booking_ids' => [],
                'booking_details' => [],
                'calculation_date' => now()->toDateTimeString(),
            ],
        ], $overrides));

        static::$insertedIds['pending_payouts'][] = $payout->id;

        return $payout;
    }

    private function trackLogsForPayout(int $payoutId): void
    {
        $logs = PayoutApprovalLog::where('pending_payout_id', $payoutId)->get();
        foreach ($logs as $log) {
            static::$insertedIds['payout_approval_logs'][] = $log->id;
        }
    }

    // ---------------------------------------------------------------
    // GET /admin/auto-payout/approval/pending
    // ---------------------------------------------------------------
    public function test_admin_can_list_pending_payouts(): void
    {
        $rule = $this->createRule();
        $this->createPendingPayout($rule);
        $this->createPendingPayout($rule);
        $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.auto-payout.approval.pending'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination' => ['current_page', 'total'],
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThanOrEqual(3, $response->json('pagination.total'));
    }

    // ---------------------------------------------------------------
    // POST /admin/auto-payout/approval/{id}/approve
    // ---------------------------------------------------------------
    public function test_admin_can_approve_pending_payout(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.approve', $payout), [
                'notes' => 'Looks good',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals($this->admin->id, $payout->approved_by);
        $this->assertNotNull($payout->approved_at);

        $this->trackLogsForPayout($payout->id);
    }

    // ---------------------------------------------------------------
    // POST /admin/auto-payout/approval/{id}/reject
    // ---------------------------------------------------------------
    public function test_admin_can_reject_pending_payout(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.reject', $payout), [
                'reason' => 'Amount needs review',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);
        $this->assertEquals($this->admin->id, $payout->rejected_by);
        $this->assertEquals('Amount needs review', $payout->rejection_reason);
        $this->assertNotNull($payout->rejected_at);

        $this->trackLogsForPayout($payout->id);
    }

    // ---------------------------------------------------------------
    // POST /admin/auto-payout/approval/bulk-approve
    // ---------------------------------------------------------------
    public function test_admin_can_bulk_approve_payouts(): void
    {
        $rule = $this->createRule();
        $p1 = $this->createPendingPayout($rule);
        $p2 = $this->createPendingPayout($rule);
        $p3 = $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.bulk-approve'), [
                'payout_ids' => [$p1->id, $p2->id, $p3->id],
                'notes' => 'Bulk approved',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(3, $response->json('data.approved_count'));

        foreach ([$p1, $p2, $p3] as $p) {
            $p->refresh();
            $this->assertEquals('approved', $p->status);
            $this->trackLogsForPayout($p->id);
        }
    }

    // ---------------------------------------------------------------
    // POST /admin/auto-payout/approval/bulk-reject
    // ---------------------------------------------------------------
    public function test_admin_can_bulk_reject_payouts(): void
    {
        $rule = $this->createRule();
        $p1 = $this->createPendingPayout($rule);
        $p2 = $this->createPendingPayout($rule);
        $p3 = $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.bulk-reject'), [
                'payout_ids' => [$p1->id, $p2->id, $p3->id],
                'reason' => 'Bulk rejection test',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(3, $response->json('data.rejected_count'));

        foreach ([$p1, $p2, $p3] as $p) {
            $p->refresh();
            $this->assertEquals('rejected', $p->status);
            $this->trackLogsForPayout($p->id);
        }
    }

    // ---------------------------------------------------------------
    // GET /admin/auto-payout/approval/{id}/details
    // ---------------------------------------------------------------
    public function test_admin_can_view_payout_details(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.auto-payout.approval.details', $payout));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'payout',
                'items',
                'logs',
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // GET /admin/auto-payout/approval/dashboard-data
    // ---------------------------------------------------------------
    public function test_admin_can_view_approval_dashboard(): void
    {
        $rule = $this->createRule();
        $this->createPendingPayout($rule);
        $this->createPendingPayout($rule);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.auto-payout.approval.dashboard-data'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'pending_count',
                'approved_today',
                'rejected_today',
                'total_pending_amount',
            ],
        ]);
        $this->assertGreaterThanOrEqual(2, $response->json('data.pending_count'));
    }

    // ---------------------------------------------------------------
    // Cannot approve already-approved -> 400
    // ---------------------------------------------------------------
    public function test_approve_already_approved_returns_400(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule, [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.approve', $payout), [
                'notes' => 'Try again',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Cannot reject already-rejected -> 400
    // ---------------------------------------------------------------
    public function test_reject_already_rejected_returns_400(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule, [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $this->admin->id,
            'rejection_reason' => 'Already done',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.reject', $payout), [
                'reason' => 'Try again',
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------
    // Unauthenticated -> redirect
    // ---------------------------------------------------------------
    public function test_unauthenticated_user_is_redirected(): void
    {
        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule);

        $this->getJson(route('admin.auto-payout.approval.pending'))
            ->assertUnauthorized();

        $this->postJson(route('admin.auto-payout.approval.approve', $payout))
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Authenticated admin without 'auto-payout' permission -> 403
    // ---------------------------------------------------------------
    public function test_admin_without_permission_is_denied(): void
    {
        $admin = Admin::factory()->create();
        // Factory creates a bare admin with no roles/permissions attached.
        static::$insertedIds['admins'][] = $admin->id;

        $rule = $this->createRule();
        $payout = $this->createPendingPayout($rule);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.auto-payout.approval.pending'))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.auto-payout.approval.approve', $payout))
            ->assertForbidden();

        // Cleanup the extra admin we just created.
        $admin->forceDelete();
    }
}
