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
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * Integration tests for dispute dashboard functionality
 * 
 * Tests Requirements:
 * - 2.4: Dispute list filtering and search
 * - 4.1: Evidence upload and submission workflows
 * - 13.1: Responsive design across device types
 */
class DisputeDashboardIntegrationTest extends TestCase
{
    use WithFaker;


    protected $admin;
    protected $disputeService;

    /**
     * tests/TestCase.php runs against the real tenant DB with no
     * transaction/rollback wrapper. test_permission_based_access_control()
     * creates an extra ad-hoc admin beyond the one in setUp(); track its id
     * here so tearDown() can remove it even if an assertion fails first.
     */
    protected array $extraAdminIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions if they don't exist
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);
        
        // Create admin user with permissions
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['view-payment-dispute']);
        
        // Mock the DisputeService to avoid tenant context issues in tests
        $this->disputeService = Mockery::mock(DisputeService::class);
        $this->app->instance(DisputeService::class, $this->disputeService);

        $auditLogger = Mockery::mock(\App\Services\Dispute\DisputeAuditLogger::class);
        $auditLogger->shouldReceive('logListAccess')->andReturn(null);
        $auditLogger->shouldReceive('logDisputeAccess')->andReturn(null);
        $this->app->instance(\App\Services\Dispute\DisputeAuditLogger::class, $auditLogger);

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\DisputeSecurityMiddleware::class,
        ]);

        \Illuminate\Support\Facades\RateLimiter::clear('dispute_view|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('dispute_modify|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('evidence_submit|127.0.0.1');
    }

    /**
     * Test dispute list filtering by status
     * Validates: Requirements 2.4
     */
    public function test_dispute_list_filtering_by_status()
    {
        // Mock filtered disputes
        $dispute1 = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_test1', 'status' => 'warning_needs_response', 'amount' => 100.00, 'currency' => 'USD', 'reason' => 'fraudulent']);
        $filteredDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$dispute1]),
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
            ->andReturn([
                'total_disputes' => 5,
                'active_disputes' => 3,
                'overdue_disputes' => 1,
                'requiring_attention' => 2
            ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?status=warning_needs_response');

        $response->assertStatus(200);
        $response->assertViewIs('users.admin.disputes.index');
        $response->assertViewHas('disputes', $filteredDisputes);
        $response->assertSee('dp_test1');
    }

    /**
     * Test dispute list filtering by reason
     * Validates: Requirements 2.4
     */
    public function test_dispute_list_filtering_by_reason()
    {
        $disputeF = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_fraudulent', 'status' => 'warning_needs_response', 'reason' => 'fraudulent', 'amount' => 250.00, 'currency' => 'USD']);
        $filteredDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$disputeF]),
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
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?reason=fraudulent');

        $response->assertStatus(200);
        $response->assertSee('dp_fraudulent');
    }

    /**
     * Test dispute search functionality
     * Validates: Requirements 2.4
     */
    public function test_dispute_search_functionality()
    {
        $searchDispute = (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_search_test', 'status' => 'warning_needs_response', 'amount' => 100.00, 'currency' => 'USD', 'reason' => 'fraudulent']);
        $searchDispute->setRelation('booking', (object) ['name' => 'John Doe', 'id' => 123]);
        $searchResults = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$searchDispute]),
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
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?search=John+Doe');

        $response->assertStatus(200);
        $response->assertSee('dp_search_test');
        $response->assertSee('John Doe');
    }

    /**
     * Test dispute list sorting functionality
     * Validates: Requirements 2.4
     */
    public function test_dispute_list_sorting()
    {
        $sortedDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_sorted1', 'status' => 'warning_needs_response', 'amount' => 500.00, 'currency' => 'USD', 'reason' => 'fraudulent']),
                (new Dispute())->forceFill(['id' => 2, 'stripe_dispute_id' => 'dp_sorted2', 'status' => 'under_review', 'amount' => 100.00, 'currency' => 'USD', 'reason' => 'fraudulent']),
            ]),
            2,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->with(Mockery::on(function ($filters) {
                return isset($filters['sort_by']) && $filters['sort_by'] === 'amount'
                    && isset($filters['sort_direction']) && $filters['sort_direction'] === 'desc';
            }), Mockery::any(), Mockery::any())
            ->once()
            ->andReturn($sortedDisputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?sort_by=amount&sort_direction=desc');

        $response->assertStatus(200);
        $response->assertSee('dp_sorted1');
        $response->assertSee('dp_sorted2');
    }

    /**
     * Test combined filtering and search
     * Validates: Requirements 2.4
     */
    public function test_combined_filtering_and_search()
    {
        $combinedResults = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_combined', 'status' => 'under_review', 'reason' => 'fraudulent', 'amount' => 100.00, 'currency' => 'USD']),
            ]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->withAnyArgs()
            ->once()
            ->andReturn($combinedResults);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?status=under_review&reason=fraudulent&search=dp_combined');

        $response->assertStatus(200);
        $response->assertSee('dp_combined');
    }

    /**
     * Test evidence upload workflow - file validation
     * Validates: Requirements 4.1
     */
    public function test_evidence_upload_file_validation()
    {
        // Validation runs before getDisputeById, so no service mock needed
        Storage::fake('evidence');

        // Test invalid file type
        $invalidFile = UploadedFile::fake()->create('document.txt', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$invalidFile],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_files.0']);
    }

    /**
     * Test evidence upload workflow - successful submission
     * Validates: Requirements 4.1
     */
    public function test_evidence_upload_successful_submission()
    {
        $dispute = new \App\Models\Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->with(1, [], Mockery::type('array'))
            ->once()
            ->andReturn(true);

        Storage::fake('evidence');
        $validFile = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$validFile],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'dispute_id',
                'file_count'
            ]
        ]);
    }

    /**
     * Test evidence upload with text evidence
     * Validates: Requirements 4.1
     */
    public function test_evidence_upload_with_text_evidence()
    {
        $dispute = new \App\Models\Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test',
            'status' => 'warning_needs_response'
        ]);

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
                       $evidenceData['uncategorized_text'] === 'Customer confirmed receipt of service';
            }), [])
            ->once()
            ->andReturn(true);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_text' => 'Customer confirmed receipt of service',
                'description' => 'Email confirmation from customer',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test evidence upload for closed disputes (should fail)
     * Validates: Requirements 4.1
     */
    public function test_evidence_upload_for_closed_disputes_fails()
    {
        $closedDispute = new \App\Models\Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_closed',
            'status' => 'won'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($closedDispute);

        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$file],
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['message' => 'Cannot submit evidence for disputes with status: Won']);
    }

    /**
     * Test responsive design - mobile view elements
     * Validates: Requirements 13.1
     */
    public function test_responsive_design_mobile_elements()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_mobile_test', 'status' => 'warning_needs_response', 'amount' => 100.00, 'currency' => 'USD', 'reason' => 'fraudulent']),
            ]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);

        // Check for mobile-specific elements
        $response->assertSee('sm:hidden'); // Mobile search input
        $response->assertSee('lg:hidden'); // Mobile card layout
        $response->assertSee('toggleMobileFilters'); // Mobile filter toggle
        $response->assertSee('mobileFilters'); // Mobile filter section
    }

    /**
     * Test responsive design - desktop table elements
     * Validates: Requirements 13.1
     */
    public function test_responsive_design_desktop_elements()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_desktop_test', 'status' => 'under_review', 'amount' => 250.00, 'currency' => 'USD', 'reason' => 'fraudulent']),
            ]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);

        // Check for desktop-specific elements
        $response->assertSee('hidden lg:block'); // Desktop table
        $response->assertSee('hidden sm:flex'); // Desktop filter layout
    }

    /**
     * Test AJAX filter submission
     * Validates: Requirements 2.4, 13.1
     */
    public function test_ajax_filter_submission()
    {
        $filteredDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([
                (new Dispute())->forceFill(['id' => 1, 'stripe_dispute_id' => 'dp_ajax_test', 'status' => 'lost', 'amount' => 100.00, 'currency' => 'USD', 'reason' => 'fraudulent']),
            ]),
            1,
            25
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($filteredDisputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.disputes.index') . '?status=lost');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'disputes',
                'statistics',
                'filters'
            ]
        ]);
    }

    /**
     * Test pagination with filters preserved
     * Validates: Requirements 2.4
     */
    public function test_pagination_preserves_filters()
    {
        $paginatedDisputes = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]),
            50, // Total items
            25, // Per page
            2   // Current page
        );

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->withAnyArgs()
            ->once()
            ->andReturn($paginatedDisputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index') . '?status=warning_needs_response&page=2');

        $response->assertStatus(200);
        $response->assertViewHas('disputes', $paginatedDisputes);
    }

    /**
     * Test statistics display on dashboard
     * Validates: Requirements 2.4
     */
    public function test_statistics_display_on_dashboard()
    {
        $disputes = new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25);
        
        $statistics = [
            'total_disputes' => 15,
            'active_disputes' => 8,
            'overdue_disputes' => 3,
            'requiring_attention' => 5
        ];

        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn($disputes);

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn($statistics);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        $response->assertSee('15'); // Total disputes
        $response->assertSee('8');  // Active disputes
        $response->assertSee('3');  // Overdue disputes
        $response->assertSee('5');  // Requiring attention
    }

    /**
     * Test error handling for invalid dispute ID
     * Validates: Requirements 4.1
     */
    public function test_error_handling_for_invalid_dispute_id()
    {
        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(99999)
            ->once()
            ->andReturn(null);

        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 99999), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$file],
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Dispute not found.'
        ]);
    }

    /**
     * Test permission-based access control
     * Validates: Requirements 2.4, 4.1
     */
    public function test_permission_based_access_control()
    {
        // Create admin with only view permission
        $viewOnlyAdmin = Admin::factory()->create();
        $viewOnlyAdmin->givePermissionTo('view-payment-dispute');
        $this->extraAdminIds[] = $viewOnlyAdmin->id;

        // Should be able to view disputes
        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn(new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, 25));

        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->actingAs($viewOnlyAdmin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        if (!empty($this->extraAdminIds)) {
            DB::table('admins')->whereIn('id', $this->extraAdminIds)->delete();
            $this->extraAdminIds = [];
        }

        if (isset($this->admin)) {
            DB::table('admins')->where('id', $this->admin->id)->delete();
        }

        parent::tearDown();
    }
}