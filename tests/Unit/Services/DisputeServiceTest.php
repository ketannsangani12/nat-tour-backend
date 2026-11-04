<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\EvidenceCompilerService;
use App\Services\Dispute\StripeErrorHandler;
use App\Services\Dispute\DisputeAuditLogger;
use App\Services\Dispute\DisputeNotificationService;
use App\Repositories\Dispute\DisputeRepository;
use App\Models\Dispute;
use App\Models\TourBooking;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Exception;

class DisputeServiceTest extends TestCase
{
    protected $disputeService;
    protected $mockRepository;
    protected $mockEvidenceCompiler;
    protected $mockErrorHandler;
    protected $mockAuditLogger;
    protected $mockNotificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(DisputeRepository::class);
        $this->mockEvidenceCompiler = Mockery::mock(EvidenceCompilerService::class);
        $this->mockErrorHandler = Mockery::mock(StripeErrorHandler::class);
        $this->mockAuditLogger = Mockery::mock(DisputeAuditLogger::class);
        $this->mockNotificationService = Mockery::mock(DisputeNotificationService::class);

        $this->disputeService = new DisputeService(
            $this->mockRepository,
            $this->mockEvidenceCompiler,
            $this->mockErrorHandler,
            $this->mockAuditLogger,
            $this->mockNotificationService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validates_evidence_file_size()
    {
        // Create a mock file that exceeds size limit
        $file = UploadedFile::fake()->create('large_file.pdf', 15000); // 15MB
        
        $result = $this->disputeService->validateEvidenceFile($file);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('File size exceeds maximum allowed size', $result['errors'][0]);
    }

    public function test_validates_evidence_file_type()
    {
        // Create a mock file with invalid type
        $file = UploadedFile::fake()->create('document.exe', 1000);
        
        $result = $this->disputeService->validateEvidenceFile($file);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('File type \'exe\' is not supported', $result['errors'][0]);
    }

    public function test_validates_valid_evidence_file()
    {
        // Create a valid mock file
        $file = UploadedFile::fake()->create('document.pdf', 1000);
        
        $result = $this->disputeService->validateEvidenceFile($file);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_extracts_dispute_data_from_stripe()
    {
        $stripeData = [
            'id' => 'dp_1234567890',
            'amount' => 5000, // $50.00 in cents
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_details' => [
                'due_by' => 1640995200 // Unix timestamp
            ]
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->disputeService);
        $method = $reflection->getMethod('extractDisputeDataFromStripe');
        $method->setAccessible(true);

        $result = $method->invoke($this->disputeService, $stripeData);

        $this->assertEquals('dp_1234567890', $result['stripe_dispute_id']);
        $this->assertEquals(50.00, $result['amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('fraudulent', $result['reason']);
        $this->assertEquals('warning_needs_response', $result['status']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $result['evidence_due_by']);
    }

    public function test_gets_disputes_for_tenant()
    {
        $filters = ['status' => 'warning_needs_response'];
        $with = ['booking', 'evidence'];
        
        $this->mockRepository
            ->shouldReceive('findAll')
            ->once()
            ->with($filters, $with)
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $result = $this->disputeService->getDisputesForTenant($filters, $with);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_gets_dispute_by_id()
    {
        $disputeId = 123;
        $with = ['booking'];
        
        $mockDispute = Mockery::mock(Dispute::class);
        
        $this->mockRepository
            ->shouldReceive('findById')
            ->once()
            ->with($disputeId, $with)
            ->andReturn($mockDispute);

        $result = $this->disputeService->getDisputeById($disputeId, $with);

        $this->assertSame($mockDispute, $result);
    }

    public function test_gets_disputes_by_booking_id()
    {
        $bookingId = 456;
        $with = ['evidence'];
        
        $this->mockRepository
            ->shouldReceive('findByBookingId')
            ->once()
            ->with($bookingId, $with)
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $result = $this->disputeService->getDisputesByBookingId($bookingId, $with);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_gets_active_disputes_requiring_attention()
    {
        $days = 5;
        
        $this->mockRepository
            ->shouldReceive('getActiveDisputesRequiringAttention')
            ->once()
            ->with($days)
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $result = $this->disputeService->getActiveDisputesRequiringAttention($days);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_gets_dispute_statistics()
    {
        $dateRange = ['start' => now()->subDays(30), 'end' => now()];
        $expectedStats = [
            'total_disputes' => 10,
            'active_disputes' => 3,
            'overdue_disputes' => 1
        ];
        
        $this->mockRepository
            ->shouldReceive('getStatistics')
            ->once()
            ->with($dateRange)
            ->andReturn($expectedStats);

        $result = $this->disputeService->getDisputeStatistics($dateRange);

        $this->assertEquals($expectedStats, $result);
    }

    public function test_prepares_stripe_evidence_data()
    {
        $evidenceData = [
            'customer_communication' => 'Email exchange with customer',
            'service_documentation' => 'Tour completion certificate'
        ];
        
        $stripeFileIds = [
            'receipt' => 'file_1234567890',
            'customer_signature' => 'file_0987654321'
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->disputeService);
        $method = $reflection->getMethod('prepareStripeEvidenceData');
        $method->setAccessible(true);

        $result = $method->invoke($this->disputeService, $evidenceData, $stripeFileIds);

        $this->assertEquals('file_1234567890', $result['receipt']);
        $this->assertEquals('file_0987654321', $result['customer_signature']);
        $this->assertEquals('Email exchange with customer', $result['customer_communication']);
        $this->assertEquals('Tour completion certificate', $result['service_documentation']);
    }

    public function test_execute_with_retry_succeeds_on_first_attempt()
    {
        $expectedResult = 'success';
        
        $operation = function() use ($expectedResult) {
            return $expectedResult;
        };

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->disputeService);
        $method = $reflection->getMethod('executeWithRetry');
        $method->setAccessible(true);

        $result = $method->invoke($this->disputeService, $operation);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_execute_with_retry_throws_exception_after_max_retries()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Max retries exceeded for Stripe operation');
        
        $stripeException = Mockery::mock(\Stripe\Exception\ApiErrorException::class);
        $stripeException->shouldReceive('getStripeCode')->andReturn('authentication_error');
        $operation = function () use ($stripeException) {
            throw $stripeException;
        };

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->disputeService);
        $method = $reflection->getMethod('executeWithRetry');
        $method->setAccessible(true);

        $method->invoke($this->disputeService, $operation);
    }
}