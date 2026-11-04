<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Dispute\EvidenceCompilerService;
use App\Models\TourBooking;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * Test automated evidence compilation system for Task 12.2
 * Requirements: 11.2, 11.4, 11.7
 */
class AutomatedEvidenceCompilationTest extends TestCase
{
    protected EvidenceCompilerService $evidenceCompiler;

    /**
     * tests/TestCase.php runs against the real tenant DB with no
     * transaction/rollback wrapper, so every TourBooking::factory()/
     * Transaction::factory() row created here is a real, permanent write
     * unless torn down explicitly.
     */
    protected array $createdBookingIds = [];
    protected array $createdTransactionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->evidenceCompiler = new EvidenceCompilerService();
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdTransactionIds)) {
            DB::table('transactions')->whereIn('id', $this->createdTransactionIds)->delete();
        }
        if (!empty($this->createdBookingIds)) {
            DB::table('transactions')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_transact')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_booking_history')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_master')->whereIn('id', $this->createdBookingIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * Test automated evidence gathering (Requirement 11.2)
     */
    public function test_automatically_gathers_booking_and_payment_documentation()
    {
        $booking = TourBooking::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'total' => 150.00,
            'status' => 'Completed',
        ]);
        $this->createdBookingIds[] = $booking->id;
        $transaction = Transaction::factory()->create([
            'tour_booking_id' => $booking->id,
            'amount' => 150.00,
            'status' => 'Completed',
        ]);
        $this->createdTransactionIds[] = $transaction->id;

        $gatheredEvidence = $this->evidenceCompiler->automaticallyGatherEvidenceDocumentation(
            $booking->id,
            'fraudulent'
        );

        // Verify evidence was automatically gathered
        $this->assertArrayHasKey('booking_documentation', $gatheredEvidence);
        $this->assertArrayHasKey('payment_records', $gatheredEvidence);
        $this->assertArrayHasKey('communication_records', $gatheredEvidence);
        $this->assertArrayHasKey('service_documentation', $gatheredEvidence);
        $this->assertArrayHasKey('policy_documentation', $gatheredEvidence);
        $this->assertArrayHasKey('automatic_gathering_metadata', $gatheredEvidence);

        // Verify metadata indicates automated gathering
        $metadata = $gatheredEvidence['automatic_gathering_metadata'];
        $this->assertEquals($booking->id, $metadata['booking_id']);
        $this->assertEquals('fraudulent', $metadata['dispute_reason']);
        $this->assertEquals('automated', $metadata['gathering_method']);
        $this->assertContains('booking_system', $metadata['evidence_sources']);
        $this->assertContains('payment_system', $metadata['evidence_sources']);

        // Verify dispute-specific tailoring
        $this->assertArrayHasKey('dispute_specific_evidence', $gatheredEvidence);
        $this->assertEquals('fraudulent', $gatheredEvidence['dispute_specific_evidence']['dispute_reason']);
        $this->assertTrue($gatheredEvidence['dispute_specific_evidence']['tailored_for_reason']);
    }

    /**
     * Test evidence package validation and completeness checking (Requirement 11.4)
     */
    public function test_validates_evidence_package_completeness()
    {
        // Test with complete evidence
        $completeEvidence = [
            'receipt' => 'Payment receipt content',
            'customer_communication' => 'Customer email communication',
            'service_documentation' => 'Service delivery proof',
            'cancellation_policy' => 'Cancellation policy text',
            'shipping_documentation' => 'Booking confirmation'
        ];

        $validation = $this->evidenceCompiler->validateEvidencePackageCompleteness($completeEvidence);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        $this->assertGreaterThan(0.8, $validation['completeness_score']);
        $this->assertEquals('ready', $validation['submission_readiness']);

        // Verify completeness details
        $this->assertArrayHasKey('completeness_details', $validation);
        $this->assertArrayHasKey('required_evidence', $validation['completeness_details']);
        $this->assertArrayHasKey('recommended_evidence', $validation['completeness_details']);

        // Test with incomplete evidence (only receipt, missing customer_communication)
        $incompleteEvidence = [
            'receipt' => 'Payment receipt content'
            // Missing customer_communication
        ];

        $validation = $this->evidenceCompiler->validateEvidencePackageCompleteness($incompleteEvidence);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
        $this->assertContains('customer_communication', $validation['completeness_details']['missing_critical']);
        // With only receipt (40 points out of 100), score should be 0.4, which maps to 'needs_improvement'
        $this->assertEquals('needs_improvement', $validation['submission_readiness']);
    }

    /**
     * Test standard documentation inclusion (Requirement 11.7)
     */
    public function test_includes_standard_documentation_automatically()
    {
        $baseEvidencePackage = [
            'booking_documentation' => ['test' => 'data'],
            'payment_records' => ['test' => 'data']
        ];

        $bookingId = 123;

        $enhancedPackage = $this->evidenceCompiler->includeStandardDocumentation(
            $baseEvidencePackage, 
            $bookingId
        );

        // Verify standard documentation was added
        $this->assertArrayHasKey('standard_documentation', $enhancedPackage);
        
        $standardDocs = $enhancedPackage['standard_documentation'];
        $this->assertArrayHasKey('standard_terms_and_conditions', $standardDocs);
        $this->assertArrayHasKey('standard_privacy_policy', $standardDocs);
        $this->assertArrayHasKey('standard_dispute_response_policy', $standardDocs);
        $this->assertArrayHasKey('standard_service_guarantee', $standardDocs);

        // Verify metadata indicates standard documentation inclusion
        $this->assertArrayHasKey('metadata', $enhancedPackage);
        $this->assertTrue($enhancedPackage['metadata']['standard_documentation_included']);
        $this->assertEquals(4, $enhancedPackage['metadata']['standard_docs_count']);
        $this->assertTrue($enhancedPackage['metadata']['requirement_117_met']);

        // Verify each standard document has proper structure
        foreach ($standardDocs as $docKey => $doc) {
            $this->assertArrayHasKey('type', $doc);
            $this->assertArrayHasKey('category', $doc);
            $this->assertArrayHasKey('data', $doc);
            $this->assertEquals('standard_documentation', $doc['type']);
            $this->assertEquals($bookingId, $doc['data']['booking_id']);
        }
    }

    /**
     * Test enhanced evidence compilation integration
     */
    public function test_enhanced_evidence_compilation_integration()
    {
        $booking = TourBooking::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'total' => 200.00,
            'status' => 'Completed',
        ]);
        $this->createdBookingIds[] = $booking->id;
        $transaction = Transaction::factory()->create([
            'tour_booking_id' => $booking->id,
            'amount' => 200.00,
            'status' => 'Completed',
        ]);
        $this->createdTransactionIds[] = $transaction->id;

        $evidencePackage = $this->evidenceCompiler->compileEvidencePackage(
            $booking->id,
            'subscription_canceled'
        );

        // Verify all automation features are present
        $this->assertArrayHasKey('automatic_gathering_metadata', $evidencePackage);
        $this->assertArrayHasKey('standard_documentation', $evidencePackage);
        $this->assertArrayHasKey('dispute_specific_evidence', $evidencePackage);
        $this->assertArrayHasKey('compilation_metadata', $evidencePackage);
        $this->assertArrayHasKey('validation_result', $evidencePackage);

        // Verify compilation metadata
        $metadata = $evidencePackage['compilation_metadata'];
        $this->assertEquals('subscription_canceled', $metadata['dispute_reason']);
        $this->assertEquals('automated_enhanced', $metadata['compilation_method']);
        $this->assertEquals('full', $metadata['automation_level']);
        $this->assertContains('11.2', $metadata['requirements_implemented']);
        $this->assertContains('11.4', $metadata['requirements_implemented']);
        $this->assertContains('11.7', $metadata['requirements_implemented']);
    }

    /**
     * Test comprehensive evidence package creation with automation
     */
    public function test_creates_comprehensive_evidence_package_with_automation()
    {
        $booking = TourBooking::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'total' => 100.00,
            'status' => 'Completed',
        ]);
        $this->createdBookingIds[] = $booking->id;
        $transaction = Transaction::factory()->create([
            'tour_booking_id' => $booking->id,
            'amount' => 100.00,
            'status' => 'Completed',
        ]);
        $this->createdTransactionIds[] = $transaction->id;

        $additionalEvidence = [
            'custom_file' => 'Additional evidence content'
        ];

        $comprehensivePackage = $this->evidenceCompiler->createComprehensiveEvidencePackage(
            $booking->id,
            $additionalEvidence,
            'duplicate'
        );

        // Verify package structure
        $this->assertArrayHasKey('evidence_package', $comprehensivePackage);
        $this->assertArrayHasKey('stripe_evidence', $comprehensivePackage);
        $this->assertArrayHasKey('validation_result', $comprehensivePackage);
        $this->assertArrayHasKey('package_metadata', $comprehensivePackage);

        // Verify automation features
        $metadata = $comprehensivePackage['package_metadata'];
        $this->assertTrue($metadata['automation_features']['automated_gathering']);
        $this->assertTrue($metadata['automation_features']['standard_docs_included']);
        $this->assertTrue($metadata['automation_features']['enhanced_validation']);
        $this->assertTrue($metadata['automation_features']['dispute_reason_tailoring']);

        // Verify requirements are met
        $this->assertTrue($metadata['requirements_met']['11.2']); // Automated gathering
        $this->assertTrue($metadata['requirements_met']['11.4']); // Standard docs
        $this->assertTrue($metadata['requirements_met']['11.7']); // Standard documentation inclusion
    }

    /**
     * Test dispute reason tailoring
     */
    public function test_tailors_evidence_for_dispute_reason()
    {
        $baseEvidence = [
            'booking_documentation' => ['test' => 'data']
        ];

        // Test fraudulent dispute tailoring
        $reflection = new \ReflectionClass($this->evidenceCompiler);
        $method = $reflection->getMethod('tailorEvidenceForDisputeReason');
        $method->setAccessible(true);

        $tailoredEvidence = $method->invoke($this->evidenceCompiler, $baseEvidence, 'fraudulent');

        $this->assertArrayHasKey('dispute_specific_evidence', $tailoredEvidence);
        $this->assertEquals('fraudulent', $tailoredEvidence['dispute_specific_evidence']['dispute_reason']);
        $this->assertTrue($tailoredEvidence['dispute_specific_evidence']['tailored_for_reason']);
        $this->assertContains('customer_verification', $tailoredEvidence['dispute_specific_evidence']['emphasis_areas']);

        // Test duplicate dispute tailoring
        $tailoredEvidence = $method->invoke($this->evidenceCompiler, $baseEvidence, 'duplicate');
        $this->assertContains('transaction_uniqueness', $tailoredEvidence['dispute_specific_evidence']['emphasis_areas']);
    }

    /**
     * Test file validation
     */
    public function test_validates_evidence_files()
    {
        $validFiles = [
            [
                'name' => 'receipt.pdf',
                'stripe_file_id' => 'file_123456',
                'size' => 1024000, // 1MB
                'type' => 'pdf'
            ],
            [
                'name' => 'communication.jpg',
                'stripe_file_id' => 'file_789012',
                'size' => 512000, // 512KB
                'type' => 'jpg'
            ]
        ];

        $reflection = new \ReflectionClass($this->evidenceCompiler);
        $method = $reflection->getMethod('validateEvidenceFiles');
        $method->setAccessible(true);

        $validation = $method->invoke($this->evidenceCompiler, $validFiles);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        $this->assertEquals(2, $validation['file_count']);
        $this->assertEquals(1536000, $validation['total_size']); // 1MB + 512KB

        // Test invalid files
        $invalidFiles = [
            [
                'name' => 'large_file.pdf',
                // Missing stripe_file_id
                'size' => 15 * 1024 * 1024, // 15MB - too large
                'type' => 'pdf'
            ]
        ];

        $validation = $method->invoke($this->evidenceCompiler, $invalidFiles);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
        $this->assertStringContainsString('Missing Stripe file ID', $validation['errors'][0]);
        $this->assertStringContainsString('Size exceeds 10MB limit', $validation['errors'][1]);
    }
}