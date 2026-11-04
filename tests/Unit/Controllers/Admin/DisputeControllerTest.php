<?php

namespace Tests\Unit\Controllers\Admin;

use Tests\TestCase;
use App\Http\Controllers\Admin\DisputeController;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\DisputeAuditLogger;
use App\Models\Admin;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Exception;

/**
 * Unit tests for DisputeController
 * 
 * Tests authorization, permission validation, dispute filtering, search functionality,
 * evidence submission workflows, and error handling scenarios.
 * 
 * Requirements: 7.4, 2.4
 */
class DisputeControllerTest extends TestCase
{
    protected $tenancy = false; // Disable tenancy for unit tests
    protected $controller;
    protected $mockDisputeService;
    protected $mockAuditLogger;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions if they don't exist
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);

        // Create admin user with permissions
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['view-payment-dispute']);
        
        // Mock the dependencies
        $this->mockDisputeService = Mockery::mock(DisputeService::class);
        $this->mockAuditLogger = Mockery::mock(DisputeAuditLogger::class)->shouldIgnoreMissing();

        // Create controller instance with mocked dependencies
        $this->controller = new DisputeController($this->mockDisputeService, $this->mockAuditLogger);
        
        // Set authenticated user
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (isset($this->admin)) {
            DB::table('admins')->where('id', $this->admin->id)->delete();
        }
        parent::tearDown();
    }

    // ========================================
    // EVIDENCE SUBMISSION TESTS
    // ========================================

    public function test_submit_evidence_with_valid_file()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);
        
        $mockDispute = $this->createMockDispute(1, 'warning_needs_response');

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(1)
            ->andReturn($mockDispute);

        $this->mockDisputeService
            ->shouldReceive('submitEvidence')
            ->once()
            ->with(1, [], Mockery::type('array'))
            ->andReturn(true);

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_file_types' => ['receipt'],
            'description' => 'Customer receipt'
        ]);
        $request->files->set('evidence_files', [$file]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Evidence submitted successfully.', $responseData['message']);
    }

    public function test_submit_evidence_validates_required_fields()
    {
        $request = Request::create('/admin/disputes/1/evidence', 'POST', []);
        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Please provide at least one file', $responseData['message']);
    }

    public function test_submit_evidence_validates_file_type()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('malware.exe', 1024);

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_file_types' => ['receipt'],
        ]);
        $request->files->set('evidence_files', [$file]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function test_submit_evidence_validates_file_size()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('large_file.pdf', 15000); // 15MB

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_file_types' => ['receipt'],
        ]);
        $request->files->set('evidence_files', [$file]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function test_submit_evidence_returns_404_for_nonexistent_dispute()
    {
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $request = Request::create('/admin/disputes/999/evidence', 'POST', [
            'evidence_text' => 'Customer confirmed service was delivered.',
        ]);

        $response = $this->controller->submitEvidence($request, '999');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Dispute not found.', $responseData['message']);
    }

    public function test_submit_evidence_rejects_invalid_dispute_status()
    {
        $mockDispute = $this->createMockDispute(1, 'won'); // Closed dispute
        $mockDispute->shouldReceive('getAttribute')->with('status_label')->andReturn('Won');

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->andReturn($mockDispute);

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_text' => 'Customer confirmed service was delivered.',
        ]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Cannot submit evidence', $responseData['message']);
    }

    public function test_submit_evidence_handles_service_failure()
    {
        $mockDispute = $this->createMockDispute(1, 'warning_needs_response');

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->andReturn($mockDispute);

        $this->mockDisputeService
            ->shouldReceive('submitEvidence')
            ->once()
            ->andReturn(false);

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_text' => 'Customer confirmed service was delivered.',
        ]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Failed to submit evidence to Stripe. Please try again.', $responseData['message']);
    }

    // ========================================
    // STATUS UPDATE TESTS
    // ========================================

    public function test_update_status_with_valid_data()
    {
        $mockDispute = $this->createMockDispute(1, 'warning_needs_response');
        $updatedDispute = $this->createMockDispute(1, 'under_review');
        $updatedDispute->shouldReceive('getAttribute')->with('status_label')->andReturn('Under Review');

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(1)
            ->andReturn($mockDispute);

        $this->mockDisputeService
            ->shouldReceive('updateDisputeStatus')
            ->once()
            ->with(1, 'under_review', Mockery::type('array'))
            ->andReturn($updatedDispute);

        $request = Request::create('/admin/disputes/1/status', 'PUT', [
            'status' => 'under_review',
            'notes' => 'Status updated by admin'
        ]);
        
        $response = $this->controller->updateStatus($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Dispute status updated successfully.', $responseData['message']);
    }

    public function test_update_status_validates_required_fields()
    {
        $request = Request::create('/admin/disputes/1/status', 'PUT', []);
        $response = $this->controller->updateStatus($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function test_update_status_validates_status_values()
    {
        $request = Request::create('/admin/disputes/1/status', 'PUT', [
            'status' => 'invalid_status'
        ]);
        
        $response = $this->controller->updateStatus($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function test_update_status_returns_404_for_nonexistent_dispute()
    {
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $request = Request::create('/admin/disputes/999/status', 'PUT', [
            'status' => 'under_review'
        ]);
        
        $response = $this->controller->updateStatus($request, '999');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Dispute not found.', $responseData['message']);
    }

    // ========================================
    // SYNC WITH STRIPE TESTS
    // ========================================

    public function test_sync_with_stripe_success()
    {
        $mockDispute = $this->createMockDispute(1, 'warning_needs_response');
        $syncedDispute = $this->createMockDispute(1, 'under_review');

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(1)
            ->andReturn($mockDispute);

        $this->mockDisputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->with('1')
            ->andReturn($syncedDispute);

        $request = Request::create('/admin/disputes/1/sync', 'POST');
        $response = $this->controller->syncWithStripe($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Dispute synchronized with Stripe successfully.', $responseData['message']);
    }

    public function test_sync_with_stripe_returns_404_for_nonexistent_dispute()
    {
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $request = Request::create('/admin/disputes/999/sync', 'POST');
        $response = $this->controller->syncWithStripe($request, '999');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Dispute not found.', $responseData['message']);
    }

    public function test_sync_with_stripe_handles_service_exception()
    {
        $mockDispute = $this->createMockDispute(1, 'warning_needs_response');

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->andReturn($mockDispute);

        $this->mockDisputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->andThrow(new Exception('Stripe API error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to sync dispute with Stripe', Mockery::type('array'));

        $request = Request::create('/admin/disputes/1/sync', 'POST');
        $response = $this->controller->syncWithStripe($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Failed to sync dispute with Stripe', $responseData['message']);
    }

    // ========================================
    // TIMELINE TESTS
    // ========================================

    public function test_timeline_returns_dispute_timeline()
    {
        $mockDispute = $this->createMockDispute(1, 'warning_needs_response');
        $mockTimeline = collect([
            ['event_type' => 'dispute_created', 'created_at' => now()],
            ['event_type' => 'evidence_submitted', 'created_at' => now()->subHour()]
        ]);

        $mockDispute->shouldReceive('timeline->orderBy->get')->andReturn($mockTimeline);

        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(1)
            ->andReturn($mockDispute);

        $request = Request::create('/admin/disputes/1/timeline', 'GET');
        $response = $this->controller->timeline($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(2, $responseData['data']['count']);
    }

    public function test_timeline_returns_404_for_nonexistent_dispute()
    {
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $request = Request::create('/admin/disputes/999/timeline', 'GET');
        $response = $this->controller->timeline($request, '999');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Dispute not found.', $responseData['message']);
    }

    // ========================================
    // ANALYTICS TESTS
    // ========================================

    public function test_analytics_returns_statistics()
    {
        $mockStatistics = [
            'total_disputes' => 10,
            'active_disputes' => 3,
            'by_status' => ['warning_needs_response' => 2],
            'by_reason' => ['fraudulent' => 5],
            'dispute_list' => [],
        ];

        $this->mockDisputeService
            ->shouldReceive('getComprehensiveAnalytics')
            ->once()
            ->with(Mockery::type('array'), 'month', Mockery::any())
            ->andReturn($mockStatistics);

        $request = Request::create('/admin/disputes/analytics', 'GET', [], [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest'
        ]);
        $response = $this->controller->analytics($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($mockStatistics, $responseData['data']['analytics']);
    }

    public function test_analytics_applies_date_range_filter()
    {
        $this->mockDisputeService
            ->shouldReceive('getComprehensiveAnalytics')
            ->once()
            ->with(Mockery::on(function ($dateRange) {
                return isset($dateRange['start']) && isset($dateRange['end']);
            }), 'month', Mockery::any())
            ->andReturn(['dispute_list' => []]);

        $request = Request::create('/admin/disputes/analytics', 'GET', [], [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest'
        ]);

        $response = $this->controller->analytics($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================
    // FILTER BUILDING TESTS
    // ========================================

    public function test_build_filters_handles_all_filter_types()
    {
        $request = Request::create('/admin/disputes', 'GET', [
            'status' => ['warning_needs_response', 'under_review'],
            'reason' => 'fraudulent',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'amount_min' => '50.00',
            'amount_max' => '500.00',
            'booking_id' => '123',
            'stripe_dispute_id' => 'dp_test123',
            'search' => 'customer name',
            'requires_attention' => '1',
            'overdue' => '1'
        ]);

        // Use reflection to test the protected buildFilters method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildFilters');
        $method->setAccessible(true);

        $filters = $method->invoke($this->controller, $request);

        $this->assertEquals(['warning_needs_response', 'under_review'], $filters['status']);
        $this->assertEquals(['fraudulent'], $filters['reason']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $filters['date_from']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $filters['date_to']);
        $this->assertEquals(50.00, $filters['amount_min']);
        $this->assertEquals(500.00, $filters['amount_max']);
        $this->assertEquals(123, $filters['booking_id']);
        $this->assertEquals('dp_test123', $filters['stripe_dispute_id']);
        $this->assertEquals('customer name', $filters['search']);
        $this->assertTrue($filters['requires_attention']);
        $this->assertTrue($filters['overdue']);
    }

    public function test_build_filters_handles_single_status_value()
    {
        $request = Request::create('/admin/disputes', 'GET', [
            'status' => 'warning_needs_response'
        ]);

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildFilters');
        $method->setAccessible(true);

        $filters = $method->invoke($this->controller, $request);

        $this->assertEquals(['warning_needs_response'], $filters['status']);
    }

    public function test_build_filters_handles_empty_request()
    {
        $request = Request::create('/admin/disputes', 'GET');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildFilters');
        $method->setAccessible(true);

        $filters = $method->invoke($this->controller, $request);

        $this->assertIsArray($filters);
        $this->assertEmpty($filters);
    }

    // ========================================
    // ERROR HANDLING TESTS
    // ========================================

    public function test_handles_validation_exceptions()
    {
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->andThrow(new ValidationException(validator([], [])));

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_text' => 'Customer confirmed service was delivered.',
        ]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_submit_evidence_handles_general_exception()
    {
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->andThrow(new Exception('Database error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to submit evidence', Mockery::type('array'));

        $request = Request::create('/admin/disputes/1/evidence', 'POST', [
            'evidence_text' => 'Customer confirmed service was delivered.',
        ]);

        $response = $this->controller->submitEvidence($request, '1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Failed to submit evidence', $responseData['message']);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Create a mock dispute object for testing.
     */
    protected function createMockDispute(int $id, string $status): Mockery\MockInterface
    {
        $data = [
            'id' => $id,
            'status' => $status,
            'stripe_dispute_id' => "dp_test{$id}",
            'amount' => 100.00,
            'currency' => 'USD',
            'reason' => 'fraudulent'
        ];

        $dispute = Mockery::mock(Dispute::class);
        $dispute->shouldReceive('getAttribute')->with('id')->andReturn($id);
        $dispute->shouldReceive('getAttribute')->with('status')->andReturn($status);
        $dispute->shouldReceive('getAttribute')->with('status_label')->andReturn(ucfirst(str_replace('_', ' ', $status)));
        $dispute->shouldReceive('toArray')->andReturn($data);
        $dispute->shouldReceive('jsonSerialize')->andReturn($data);

        return $dispute;
    }
}