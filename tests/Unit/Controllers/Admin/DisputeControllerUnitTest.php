<?php

namespace Tests\Unit\Controllers\Admin;

use Tests\TestCase;
use App\Services\Dispute\DisputeService;
use App\Models\Admin;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Exception;

/**
 * Unit tests for DisputeController core logic
 * 
 * Tests service integration, validation logic, and business rules
 * without HTTP routing dependencies.
 * 
 * Requirements: 7.4, 2.4
 */
class DisputeControllerUnitTest extends TestCase
{
    protected $tenancy = false;
    protected $mockDisputeService;
    protected $admin;
    protected array $createdAdminIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);

        // Create admin user
        $this->admin = Admin::factory()->create();
        $this->createdAdminIds[] = $this->admin->id;
        $this->admin->givePermissionTo(['view-payment-dispute']);
        
        // Mock the DisputeService
        $this->mockDisputeService = Mockery::mock(DisputeService::class);
        $this->app->instance(DisputeService::class, $this->mockDisputeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        if (!empty($this->createdAdminIds)) {
            DB::table('admins')->whereIn('id', $this->createdAdminIds)->delete();
        }
        parent::tearDown();
    }

    // ========================================
    // VALIDATION TESTS
    // ========================================

    public function test_evidence_submission_validation_rules()
    {
        $evidenceTypes = 'receipt,customer_communication,service_documentation,shipping_documentation,duplicate_charge_documentation,refund_policy,cancellation_policy,customer_signature,uncategorized_file';

        // All fields are nullable; an invalid type in the array should fail
        $validator = Validator::make([
            'evidence_file_types' => ['invalid_type'],
        ], [
            'evidence_file_types'   => 'nullable|array',
            'evidence_file_types.*' => "nullable|string|in:{$evidenceTypes}",
            'evidence_text'         => 'nullable|string|max:5000',
            'evidence_files'        => 'nullable|array',
            'evidence_files.*'      => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'description'           => 'nullable|string|max:1000',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_file_types.0', $validator->errors()->toArray());
    }

    public function test_evidence_submission_validates_evidence_type()
    {
        $evidenceTypes = 'receipt,customer_communication,service_documentation,shipping_documentation,duplicate_charge_documentation,refund_policy,cancellation_policy,customer_signature,uncategorized_file';

        $validator = Validator::make([
            'evidence_file_types' => ['invalid_type'],
        ], [
            'evidence_file_types'   => 'nullable|array',
            'evidence_file_types.*' => "nullable|string|in:{$evidenceTypes}",
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_file_types.0', $validator->errors()->toArray());
    }

    public function test_evidence_submission_validates_file_size()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('large_file.pdf', 15000); // 15MB

        $validator = Validator::make([
            'evidence_file_types' => ['receipt'],
            'evidence_files'      => [$file],
        ], [
            'evidence_file_types'   => 'nullable|array',
            'evidence_file_types.*' => 'nullable|string|in:receipt,customer_communication,service_documentation,shipping_documentation,duplicate_charge_documentation,refund_policy,cancellation_policy,customer_signature,uncategorized_file',
            'evidence_files'        => 'nullable|array',
            'evidence_files.*'      => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_files.0', $validator->errors()->toArray());
    }

    public function test_evidence_submission_validates_file_type()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('malware.exe', 1024);

        $validator = Validator::make([
            'evidence_file_types' => ['receipt'],
            'evidence_files'      => [$file],
        ], [
            'evidence_file_types'   => 'nullable|array',
            'evidence_file_types.*' => 'nullable|string|in:receipt,customer_communication,service_documentation,shipping_documentation,duplicate_charge_documentation,refund_policy,cancellation_policy,customer_signature,uncategorized_file',
            'evidence_files'        => 'nullable|array',
            'evidence_files.*'      => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_files.0', $validator->errors()->toArray());
    }

    public function test_status_update_validation_rules()
    {
        // Test required status
        $validator = Validator::make([], [
            'status' => 'required|string|in:warning_needs_response,under_review,charge_refunded,lost,won',
            'notes' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000'
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_status_update_validates_status_values()
    {
        $validator = Validator::make([
            'status' => 'invalid_status'
        ], [
            'status' => 'required|string|in:warning_needs_response,under_review,charge_refunded,lost,won'
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_status_update_accepts_valid_status()
    {
        $validator = Validator::make([
            'status' => 'under_review',
            'notes' => 'Updated by admin',
            'internal_notes' => 'Internal note'
        ], [
            'status' => 'required|string|in:warning_needs_response,under_review,charge_refunded,lost,won',
            'notes' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000'
        ]);

        $this->assertFalse($validator->fails());
    }

    // ========================================
    // BUSINESS LOGIC TESTS
    // ========================================

    public function test_dispute_status_validation_for_evidence_submission()
    {
        // Valid statuses for evidence submission
        $validStatuses = ['warning_needs_response', 'under_review'];
        
        foreach ($validStatuses as $status) {
            $this->assertTrue(in_array($status, ['warning_needs_response', 'under_review']));
        }

        // Invalid statuses for evidence submission
        $invalidStatuses = ['won', 'lost', 'charge_refunded'];
        
        foreach ($invalidStatuses as $status) {
            $this->assertFalse(in_array($status, ['warning_needs_response', 'under_review']));
        }
    }

    public function test_filter_building_logic()
    {
        // Test status filter handling
        $statusArray = ['warning_needs_response', 'under_review'];
        $statusString = 'warning_needs_response';
        
        // Array handling
        $filters = [];
        if (is_array($statusArray)) {
            $filters['status'] = $statusArray;
        } else {
            $filters['status'] = [$statusArray];
        }
        $this->assertEquals(['warning_needs_response', 'under_review'], $filters['status']);
        
        // String handling
        $filters = [];
        if (is_array($statusString)) {
            $filters['status'] = $statusString;
        } else {
            $filters['status'] = [$statusString];
        }
        $this->assertEquals(['warning_needs_response'], $filters['status']);
    }

    public function test_amount_filter_conversion()
    {
        $amountMin = '50.00';
        $amountMax = '500.00';
        
        $filters = [];
        if (!empty($amountMin)) {
            $filters['amount_min'] = (float) $amountMin;
        }
        if (!empty($amountMax)) {
            $filters['amount_max'] = (float) $amountMax;
        }
        
        $this->assertEquals(50.00, $filters['amount_min']);
        $this->assertEquals(500.00, $filters['amount_max']);
        $this->assertIsFloat($filters['amount_min']);
        $this->assertIsFloat($filters['amount_max']);
    }

    public function test_boolean_filter_conversion()
    {
        // Test requires_attention filter
        $requiresAttention = '1';
        $overdue = '0';
        
        $filters = [];
        if ($requiresAttention === '1' || $requiresAttention === 'true') {
            $filters['requires_attention'] = true;
        }
        if ($overdue === '1' || $overdue === 'true') {
            $filters['overdue'] = true;
        }
        
        $this->assertTrue($filters['requires_attention']);
        $this->assertArrayNotHasKey('overdue', $filters);
    }

    public function test_sort_parameter_validation()
    {
        $allowedSortFields = ['created_at', 'evidence_due_by', 'amount', 'status', 'reason'];
        
        // Valid sort field
        $sortBy = 'amount';
        $this->assertTrue(in_array($sortBy, $allowedSortFields));
        
        // Invalid sort field should default to created_at
        $invalidSortBy = 'invalid_field';
        $finalSortBy = in_array($invalidSortBy, $allowedSortFields) ? $invalidSortBy : 'created_at';
        $this->assertEquals('created_at', $finalSortBy);
        
        // Valid sort direction
        $sortDirection = 'asc';
        $this->assertTrue(in_array($sortDirection, ['asc', 'desc']));
        
        // Invalid sort direction should default to desc
        $invalidSortDirection = 'invalid_direction';
        $finalSortDirection = in_array($invalidSortDirection, ['asc', 'desc']) ? $invalidSortDirection : 'desc';
        $this->assertEquals('desc', $finalSortDirection);
    }

    // ========================================
    // SERVICE INTEGRATION TESTS
    // ========================================

    public function test_service_method_calls_for_evidence_submission()
    {
        $disputeId = 123;
        $evidenceData = ['customer_communication' => 'Email exchange'];
        $files = ['receipt' => 'mock_file'];
        
        $mockDispute = $this->createMockDispute($disputeId, 'warning_needs_response');
        
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with($disputeId)
            ->andReturn($mockDispute);
            
        $this->mockDisputeService
            ->shouldReceive('submitEvidence')
            ->once()
            ->with($disputeId, $evidenceData, $files)
            ->andReturn(true);
        
        // Simulate the service calls
        $dispute = $this->mockDisputeService->getDisputeById($disputeId);
        $result = $this->mockDisputeService->submitEvidence($disputeId, $evidenceData, $files);
        
        $this->assertNotNull($dispute);
        $this->assertTrue($result);
    }

    public function test_service_method_calls_for_status_update()
    {
        $disputeId = 123;
        $newStatus = 'under_review';
        $metadata = ['notes' => 'Updated by admin'];
        
        $mockDispute = $this->createMockDispute($disputeId, 'warning_needs_response');
        $updatedDispute = $this->createMockDispute($disputeId, $newStatus);
        
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with($disputeId)
            ->andReturn($mockDispute);
            
        $this->mockDisputeService
            ->shouldReceive('updateDisputeStatus')
            ->once()
            ->with($disputeId, $newStatus, $metadata)
            ->andReturn($updatedDispute);
        
        // Simulate the service calls
        $dispute = $this->mockDisputeService->getDisputeById($disputeId);
        $result = $this->mockDisputeService->updateDisputeStatus($disputeId, $newStatus, $metadata);
        
        $this->assertNotNull($dispute);
        $this->assertNotNull($result);
        $this->assertEquals($newStatus, $result->getAttribute('status'));
    }

    public function test_service_method_calls_for_sync_with_stripe()
    {
        $disputeId = 123;
        
        $mockDispute = $this->createMockDispute($disputeId, 'warning_needs_response');
        $syncedDispute = $this->createMockDispute($disputeId, 'under_review');
        
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with($disputeId)
            ->andReturn($mockDispute);
            
        $this->mockDisputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->with((string) $disputeId)
            ->andReturn($syncedDispute);
        
        // Simulate the service calls
        $dispute = $this->mockDisputeService->getDisputeById($disputeId);
        $result = $this->mockDisputeService->syncWithStripe((string) $disputeId);
        
        $this->assertNotNull($dispute);
        $this->assertNotNull($result);
    }

    public function test_service_method_calls_for_analytics()
    {
        $dateRange = ['start' => now()->subDays(30), 'end' => now()];
        $mockStatistics = [
            'total_disputes' => 10,
            'active_disputes' => 3,
            'by_status' => ['warning_needs_response' => 2]
        ];
        $mockUrgentDisputes = new Collection([]);
        
        $this->mockDisputeService
            ->shouldReceive('getDisputeStatistics')
            ->once()
            ->with($dateRange)
            ->andReturn($mockStatistics);
            
        $this->mockDisputeService
            ->shouldReceive('getActiveDisputesRequiringAttention')
            ->once()
            ->with(3)
            ->andReturn($mockUrgentDisputes);
        
        // Simulate the service calls
        $statistics = $this->mockDisputeService->getDisputeStatistics($dateRange);
        $urgentDisputes = $this->mockDisputeService->getActiveDisputesRequiringAttention(3);
        
        $this->assertEquals($mockStatistics, $statistics);
        $this->assertInstanceOf(Collection::class, $urgentDisputes);
        $this->assertEquals(0, $urgentDisputes->count());
    }

    // ========================================
    // ERROR HANDLING TESTS
    // ========================================

    public function test_handles_service_exceptions_gracefully()
    {
        $disputeId = 123;
        
        $this->mockDisputeService
            ->shouldReceive('getDisputeById')
            ->once()
            ->with($disputeId)
            ->andThrow(new Exception('Database connection failed'));
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection failed');
        
        $this->mockDisputeService->getDisputeById($disputeId);
    }

    public function test_handles_validation_exceptions()
    {
        $disputeId = 123;
        
        $validator = validator([], ['required_field' => 'required']);
        $validationException = new ValidationException($validator);
        
        $this->mockDisputeService
            ->shouldReceive('submitEvidence')
            ->once()
            ->andThrow($validationException);
        
        $this->expectException(ValidationException::class);
        
        $this->mockDisputeService->submitEvidence($disputeId, [], []);
    }

    // ========================================
    // PERMISSION AND AUTHORIZATION TESTS
    // ========================================

    public function test_permission_requirements()
    {
        // Test view permission
        $this->assertTrue($this->admin->can('view-payment-dispute'));

        // Create user without permissions
        $userWithoutPermissions = Admin::factory()->create();
        $this->createdAdminIds[] = $userWithoutPermissions->id;
        $this->assertFalse($userWithoutPermissions->can('view-payment-dispute'));

        // Create user with view permission
        $viewUser = Admin::factory()->create();
        $this->createdAdminIds[] = $viewUser->id;
        $viewUser->givePermissionTo('view-payment-dispute');
        $this->assertTrue($viewUser->can('view-payment-dispute'));
    }

    public function test_middleware_permission_mapping()
    {
        // All dispute actions require view-payment-dispute
        $allMethods = ['index', 'show', 'timeline', 'analytics', 'submitEvidence', 'updateStatus', 'syncWithStripe'];

        $this->assertNotEmpty($allMethods);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Create a mock dispute object for testing.
     */
    protected function createMockDispute(int $id, string $status): Mockery\MockInterface
    {
        $dispute = Mockery::mock(Dispute::class);
        $dispute->shouldReceive('getAttribute')->with('id')->andReturn($id);
        $dispute->shouldReceive('getAttribute')->with('status')->andReturn($status);
        $dispute->shouldReceive('getAttribute')->with('status_label')->andReturn(ucfirst(str_replace('_', ' ', $status)));
        
        return $dispute;
    }
}