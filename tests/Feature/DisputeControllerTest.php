<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Dispute;
use App\Models\TourBooking;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\DisputeAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Mockery;

class DisputeControllerTest extends TestCase
{


    protected $admin;
    protected $disputeService;
    protected array $createdAdminIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions if they don't exist
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);
        
        // Create admin user with permissions
        $this->admin = Admin::factory()->create();
        $this->createdAdminIds[] = $this->admin->id;
        $this->admin->givePermissionTo(['view-payment-dispute']);

        // Mock the DisputeService to avoid tenant context issues in tests
        $this->disputeService = Mockery::mock(DisputeService::class);
        $this->app->instance(DisputeService::class, $this->disputeService);

        $auditLogger = Mockery::mock(DisputeAuditLogger::class)->shouldIgnoreMissing();
        $this->app->instance(DisputeAuditLogger::class, $auditLogger);

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\DisputeSecurityMiddleware::class,
        ]);

        \Illuminate\Support\Facades\RateLimiter::clear('dispute_view|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('dispute_modify|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('evidence_submit|127.0.0.1');
    }

    public function test_index_displays_disputes_for_authorized_user()
    {
        // Mock the service methods
        $this->disputeService
            ->shouldReceive('getPaginatedDisputesForTenant')
            ->once()
            ->andReturn(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25));
            
        $this->disputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->andReturn([
                'total_disputes' => 0,
                'active_disputes' => 0,
                'overdue_disputes' => 0,
                'requiring_attention' => 0
            ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('users.admin.disputes.index');
    }

    public function test_index_requires_permission()
    {
        // Create admin without permissions
        $adminWithoutPermission = Admin::factory()->create();
        $this->createdAdminIds[] = $adminWithoutPermission->id;

        $response = $this->actingAs($adminWithoutPermission, 'admin')
            ->get(route('admin.disputes.index'));

        $response->assertStatus(403);
    }

    public function test_show_displays_dispute_details()
    {
        $dispute = new Dispute([
            'stripe_dispute_id' => 'dp_test123',
            'amount' => 100.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(7)
        ]);
        $dispute->id = 1;
        $dispute->created_at = now();
        $dispute->setRelation('evidence', collect([]));
        $dispute->setRelation('timeline', collect([]));

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1, ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline'])
            ->once()
            ->andReturn($dispute);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.disputes.show', 1));

        $response->assertStatus(200);
        $response->assertViewIs('users.admin.disputes.show');
        $response->assertViewHas('dispute', $dispute);
    }

    public function test_submit_evidence_with_valid_data()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test123',
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
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files' => [$file],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_update_status_with_valid_data()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test123',
            'status' => 'warning_needs_response'
        ]);

        $updatedDispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test123',
            'status' => 'under_review'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('updateDisputeStatus')
            ->with(1, 'under_review', Mockery::type('array'))
            ->once()
            ->andReturn($updatedDispute);

        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.disputes.update-status', 1), [
                'status' => 'under_review',
                'notes' => 'Status updated by admin'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_sync_with_stripe()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test123'
        ]);

        $syncedDispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test123',
            'status' => 'under_review'
        ]);

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
            ->post(route('admin.disputes.sync-stripe', 1));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_analytics_returns_statistics()
    {
        $this->disputeService
            ->shouldReceive('getComprehensiveAnalytics')
            ->once()
            ->andReturn([
                'total_disputes' => 5,
                'active_disputes' => 2,
                'by_status' => ['warning_needs_response' => 2],
                'by_reason' => ['fraudulent' => 3]
            ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.disputes.analytics'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (!empty($this->createdAdminIds)) {
            DB::table('admins')->whereIn('id', $this->createdAdminIds)->delete();
        }
        parent::tearDown();
    }
}