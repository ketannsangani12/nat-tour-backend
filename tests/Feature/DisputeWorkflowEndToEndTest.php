<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\DisputeAuditLogger;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * End-to-end integration tests for complete dispute management workflows
 * 
 * Tests Requirements:
 * - 2.4: Complete dispute list filtering and search workflows
 * - 4.1: Complete evidence upload and submission workflows
 * - 13.1: Complete responsive design workflows across device types
 * - Integration of all dashboard components working together
 */
class DisputeWorkflowEndToEndTest extends TestCase
{
    use WithFaker;

    protected $admin;
    protected $disputeService;
    protected $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);
        
        // Create admin user with permissions
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['view-payment-dispute']);
        
        // Mock services
        $this->disputeService = Mockery::mock(DisputeService::class);
        $this->auditLogger = Mockery::mock(DisputeAuditLogger::class);
        $this->app->instance(DisputeService::class, $this->disputeService);
        $this->app->instance(DisputeAuditLogger::class, $this->auditLogger);

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\DisputeSecurityMiddleware::class,
        ]);

        \Illuminate\Support\Facades\RateLimiter::clear('dispute_view|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('dispute_modify|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('evidence_submit|127.0.0.1');
    }

    /**
     * Test complete dispute discovery and filtering workflow
     * Validates: Requirements 2.4
     */
    public function test_complete_dispute_discovery_and_filtering_workflow()
    {
        // Step 1: Load initial dispute dashboard
        $d1 = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_workflow_1', 'status' => 'warning_needs_response', 'reason' => 'fraudulent', 'amount' => 100.00, 'currency' => 'USD', 'evidence_due_by' => now()->addDays(5)]);
        $d1->setRelation('booking', (object) ['id' => 1, 'name' => 'John Doe']);
        $d2 = (new Dispute())->forceFill(['id' => 2, 'stripe_dispute_id' => 'dp_workflow_2', 'status' => 'under_review', 'reason' => 'product_unacceptable', 'amount' => 250.00, 'currency' => 'USD', 'evidence_due_by' => now()->addDays(3)]);
        $d2->setRelation('booking', (object) ['id' => 2, 'name' => 'Jane Smith']);
        $allDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$d1, $d2]),
            2,
            25
        );

        $statistics = [
            'total_disputes' => 2,
            'active_disputes' => 2,
            'overdue_disputes' => 0,
            'requiring_attention' => 1
        ];

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->withAnyArgs()
            ->once()
            ->andReturn($allDisputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $this->auditLogger
            ->shouldReceive('logListAccess')
            ->andReturn(null);

        // Load dashboard
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        $response->assertSee('dp_workflow_1');
        $response->assertSee('dp_workflow_2');
        $response->assertSee('John Doe');
        $response->assertSee('Jane Smith');

        // Step 2: Filter by status
        $f1 = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_workflow_1', 'status' => 'warning_needs_response', 'reason' => 'fraudulent', 'amount' => 100.00, 'currency' => 'USD']);
        $f1->setRelation('booking', (object) ['id' => 1, 'name' => 'John Doe']);
        $filteredByStatus = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$f1]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->withAnyArgs()
            ->once()
            ->andReturn($filteredByStatus);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $this->auditLogger
            ->shouldReceive('logListAccess')
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?status=warning_needs_response');

        $response->assertStatus(200);
        $response->assertSee('dp_workflow_1');
        $response->assertDontSee('dp_workflow_2');

        // Step 3: Add search filter
        $s1 = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_workflow_1', 'status' => 'warning_needs_response', 'reason' => 'fraudulent', 'amount' => 100.00, 'currency' => 'USD']);
        $s1->setRelation('booking', (object) ['id' => 1, 'name' => 'John Doe']);
        $searchResults = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$s1]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->withAnyArgs()
            ->once()
            ->andReturn($searchResults);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $this->auditLogger
            ->shouldReceive('logListAccess')
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?status=warning_needs_response&search=John');

        $response->assertStatus(200);
        $response->assertSee('dp_workflow_1');
        $response->assertSee('John Doe');
    }

    /**
     * Test complete evidence submission workflow from discovery to submission
     * Validates: Requirements 4.1
     */
    public function test_complete_evidence_submission_workflow()
    {
        // Step 1: Navigate to dispute detail
        $dispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_evidence_workflow',
            'status' => 'warning_needs_response',
            'amount' => 200.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(4),
            'created_at' => now()->subDays(1),
        ]);
        $dispute->setRelation('evidence', collect([]));
        $dispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($dispute);

        $this->auditLogger
            ->shouldReceive('logDisputeAccess')
            ->once();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        $response->assertSee('dp_evidence_workflow');
        $response->assertSee('Submit Evidence');
        $response->assertSee('Upload Evidence');

        // Step 2: Submit evidence with file
        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->with(1, [], Mockery::on(function ($files) {
                return isset($files['receipt']) && $files['receipt'] instanceof UploadedFile;
            }))
            ->once()
            ->andReturn(true);

        Storage::fake('evidence');
        $receiptFile = UploadedFile::fake()->create('customer_receipt.pdf', 2048);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$receiptFile],
                'description' => 'Original customer receipt showing payment',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Evidence submitted successfully.',
            'data' => [
                'dispute_id' => '1',
                'file_count' => 1,
            ]
        ]);

        // Step 3: Submit additional text evidence
        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        // DisputeController now packs text evidence into `uncategorized_text`
        // regardless of the selected evidence_type (see PR #5451).
        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->with(1, Mockery::on(function ($evidenceData) {
                return isset($evidenceData['uncategorized_text']) &&
                       strpos($evidenceData['uncategorized_text'], 'Customer confirmed') !== false;
            }), [])
            ->once()
            ->andReturn(true);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_text' => 'Customer confirmed receipt of tour service via email on ' . now()->format('Y-m-d') . '. No complaints received.',
                'description' => 'Follow-up email confirmation',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Step 4: Verify evidence appears in dispute detail
        $disputeWithEvidence = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_evidence_workflow',
            'status' => 'under_review',
            'amount' => 200.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(4),
            'created_at' => now()->subDays(1),
        ]);
        $disputeWithEvidence->setRelation('timeline', collect([]));
        $disputeWithEvidence->setRelation('evidence', collect([
            new \App\Models\DisputeEvidence([
                'evidence_type' => 'receipt',
                'file_name' => 'customer_receipt.pdf',
                'stripe_file_id' => 'file_test123',
                'submitted_at' => now(),
                'file_size' => 2097152,
            ]),
            new \App\Models\DisputeEvidence([
                'evidence_type' => 'customer_communication',
                'file_name' => null,
                'stripe_file_id' => null,
                'submitted_at' => null,
            ]),
        ]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($disputeWithEvidence);

        $this->auditLogger
            ->shouldReceive('logDisputeAccess')
            ->once();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        $response->assertSee('customer_receipt.pdf');
        $response->assertSee('Submitted to Stripe');
    }

    /**
     * Test complete responsive workflow across different viewport sizes
     * Validates: Requirements 13.1
     */
    public function test_complete_responsive_workflow()
    {
        $rd = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_responsive_test', 'status' => 'warning_needs_response', 'amount' => 150.00, 'currency' => 'USD', 'reason' => 'fraudulent', 'evidence_due_by' => now()->addDays(3)]);
        $rd->setRelation('booking', (object) ['id' => 1, 'name' => 'Mobile User']);
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$rd]),
            1,
            25
        );

        $statistics = [
            'total_disputes' => 1,
            'active_disputes' => 1,
            'overdue_disputes' => 0,
            'requiring_attention' => 1
        ];

        // Step 1: Test mobile viewport workflow
        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $this->auditLogger
            ->shouldReceive('logListAccess')
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);

        // Verify mobile elements are present
        $response->assertSee('sm:hidden'); // Mobile search
        $response->assertSee('lg:hidden'); // Mobile card layout
        $response->assertSee('toggleMobileFilters'); // Mobile filter toggle
        $response->assertSee('Advanced Filters'); // Mobile filter text

        // Step 2: Test mobile filtering workflow
        $fd = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_responsive_test', 'status' => 'warning_needs_response', 'amount' => 150.00, 'currency' => 'USD', 'reason' => 'fraudulent']);
        $fd->setRelation('booking', (object) ['id' => 1, 'name' => 'Mobile User']);
        $filteredDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$fd]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->withAnyArgs()
            ->once()
            ->andReturn($filteredDisputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $this->auditLogger
            ->shouldReceive('logListAccess')
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?status=warning_needs_response');

        $response->assertStatus(200);

        // Verify mobile filter results
        $response->assertSee('dp_responsive_test');
        $response->assertSee('Mobile User');

        // Step 3: Test desktop elements are also present
        $response->assertSee('hidden lg:block'); // Desktop table
        $response->assertSee('hidden sm:flex'); // Desktop filters
        $response->assertSee('table-auto'); // Desktop table styling
    }

    /**
     * Test complete dispute status update workflow
     * Validates: Requirements 2.4, 4.1
     */
    public function test_complete_dispute_status_update_workflow()
    {
        // Step 1: Load dispute in initial status
        $dispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_status_workflow',
            'status' => 'warning_needs_response',
            'amount' => 300.00,
            'currency' => 'USD',
            'reason' => 'general',
            'created_at' => now(),
        ]);
        $dispute->setRelation('evidence', collect([]));
        $dispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($dispute);

        $this->auditLogger
            ->shouldReceive('logDisputeAccess')
            ->once();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        $response->assertSee('Needs Response');

        // Step 2: Update dispute status
        $updatedDispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_status_workflow',
            'status' => 'under_review',
            'amount' => 300.00,
            'currency' => 'USD'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('updateDisputeStatus')
            ->with(1, 'under_review', Mockery::on(function ($data) {
                return isset($data['notes']) && $data['notes'] === 'Evidence submitted, awaiting Stripe review';
            }))
            ->once()
            ->andReturn($updatedDispute);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.disputes.update-status', 1), [
                'status' => 'under_review',
                'notes' => 'Evidence submitted, awaiting Stripe review'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'dispute_id' => '1',
                'old_status' => 'warning_needs_response',
                'new_status' => 'under_review'
            ]
        ]);

        // Step 3: Verify status appears in dispute list
        $disputesWithUpdatedStatus = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_status_workflow', 'status' => 'under_review', 'amount' => 300.00, 'currency' => 'USD', 'reason' => 'general']),
            ]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputesWithUpdatedStatus);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $this->auditLogger
            ->shouldReceive('logListAccess')
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        $response->assertSee('under_review');
    }

    /**
     * Test complete Stripe synchronization workflow
     * Validates: Requirements 2.4, 4.1
     */
    public function test_complete_stripe_synchronization_workflow()
    {
        // Step 1: Load dispute that needs sync
        $dispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_sync_workflow',
            'status' => 'warning_needs_response',
            'amount' => 400.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(5),
            'created_at' => now()->subDays(1),
        ]);
        $dispute->setRelation('evidence', collect([]));
        $dispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($dispute);

        $this->auditLogger
            ->shouldReceive('logDisputeAccess')
            ->once();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        $response->assertSee('Sync with Stripe');

        // Step 2: Perform sync operation
        $syncedDispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_sync_workflow',
            'status' => 'under_review', // Updated from Stripe
            'amount' => 400.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'evidence_due_by' => now()->addDays(5),
            'created_at' => now()->subDays(1),
        ]);
        $syncedDispute->setRelation('evidence', collect([]));
        $syncedDispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('syncWithStripe')
            ->with('1')
            ->once()
            ->andReturn($syncedDispute);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.sync-stripe', 1));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Dispute synchronized with Stripe successfully.'
        ]);

        // Step 3: Verify updated data appears in interface
        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($syncedDispute);

        $this->auditLogger
            ->shouldReceive('logDisputeAccess')
            ->once();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        $response->assertSee('Under Review');
    }

    /**
     * Test error handling throughout complete workflow
     * Validates: Requirements 2.4, 4.1, 13.1
     */
    public function test_error_handling_throughout_workflow()
    {
        // Step 1: Test dispute not found error
        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(99999, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 99999));

        $response->assertRedirect(route('admin.disputes.index'));
        $response->assertSessionHas('error', 'Dispute not found.');

        // Step 2: Test evidence submission error
        $dispute = (new Dispute())->forceFill([
            'id' => 1,
            'stripe_dispute_id' => 'dp_error_test',
            'status' => 'warning_needs_response',
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->once()
            ->andThrow(new \Exception('Stripe API error'));

        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$file],
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Failed to submit evidence. Please try again.'
        ]);

        // Step 3: Test AJAX error handling
        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andThrow(new \Exception('Database connection error'));

        $response = $this->actingAs($this->admin, 'admin')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.disputes.index'));

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Failed to load disputes. Please try again.'
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (isset($this->admin)) {
            DB::table('admins')->where('id', $this->admin->id)->delete();
        }
        parent::tearDown();
    }
}