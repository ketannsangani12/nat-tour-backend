<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Dispute\EvidenceCompilerService;

/**
 * Test automated evidence compilation system for Task 12.2
 * Requirements: 11.2, 11.4, 11.7
 * 
 * This test focuses on the automation features without database dependencies
 */
class EvidenceCompilerAutomationTest extends TestCase
{
    protected $tenancy = false;

    protected EvidenceCompilerService $evidenceCompiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evidenceCompiler = new EvidenceCompilerService();
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

        // Verify completeness details structure
        $this->assertArrayHasKey('completeness_details', $validation);
        $this->assertArrayHasKey('required_evidence', $validation['completeness_details']);
        $this->assertArrayHasKey('recommended_evidence', $validation['completeness_details']);
        $this->assertArrayHasKey('optional_evidence', $validation['completeness_details']);

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
     * Test dispute reason tailoring functionality
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

        // Test subscription_canceled dispute tailoring
        $tailoredEvidence = $method->invoke($this->evidenceCompiler, $baseEvidence, 'subscription_canceled');
        $this->assertContains('service_delivery_proof', $tailoredEvidence['dispute_specific_evidence']['emphasis_areas']);
    }

    /**
     * Test enhanced completeness score calculation
     */
    public function test_calculates_enhanced_completeness_score()
    {
        $reflection = new \ReflectionClass($this->evidenceCompiler);
        $method = $reflection->getMethod('calculateEnhancedCompletenessScore');
        $method->setAccessible(true);

        // Define evidence categories for testing
        $requiredEvidence = [
            'receipt' => 'Payment receipt',
            'customer_communication' => 'Customer communication'
        ];
        $recommendedEvidence = [
            'service_documentation' => 'Service docs',
            'cancellation_policy' => 'Cancellation policy'
        ];
        $optionalEvidence = [
            'duplicate_charge_documentation' => 'Duplicate charge docs'
        ];

        // Test perfect evidence (all required + some recommended + some optional)
        $perfectEvidence = [
            'receipt' => 'content',
            'customer_communication' => 'content',
            'service_documentation' => 'content',
            'cancellation_policy' => 'content',
            'duplicate_charge_documentation' => 'content'
        ];

        $score = $method->invoke($this->evidenceCompiler, $perfectEvidence, $requiredEvidence, $recommendedEvidence, $optionalEvidence);
        $this->assertEquals(0.96, $score); // 80 (required) + 15 (recommended cap) + 1 (optional) = 96

        // Test only required evidence
        $requiredOnlyEvidence = [
            'receipt' => 'content',
            'customer_communication' => 'content'
        ];

        $score = $method->invoke($this->evidenceCompiler, $requiredOnlyEvidence, $requiredEvidence, $recommendedEvidence, $optionalEvidence);
        $this->assertEquals(0.8, $score); // 80 points out of 100

        // Test partial evidence (only receipt)
        $partialEvidence = [
            'receipt' => 'content'
        ];

        $score = $method->invoke($this->evidenceCompiler, $partialEvidence, $requiredEvidence, $recommendedEvidence, $optionalEvidence);
        $this->assertEquals(0.4, $score); // 40 points out of 100
    }

    /**
     * Test file validation functionality
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

    /**
     * Test standard documentation content generation
     */
    public function test_generates_standard_documentation_content()
    {
        $reflection = new \ReflectionClass($this->evidenceCompiler);

        // Test terms and conditions
        $termsMethod = $reflection->getMethod('getStandardTermsAndConditions');
        $termsMethod->setAccessible(true);
        $terms = $termsMethod->invoke($this->evidenceCompiler);

        $this->assertIsString($terms);
        $this->assertStringContainsString('TERMS AND CONDITIONS', $terms);
        $this->assertStringContainsString('Booking Confirmation', $terms);

        // Test privacy policy
        $privacyMethod = $reflection->getMethod('getStandardPrivacyPolicy');
        $privacyMethod->setAccessible(true);
        $privacy = $privacyMethod->invoke($this->evidenceCompiler);

        $this->assertIsString($privacy);
        $this->assertStringContainsString('PRIVACY POLICY', $privacy);
        $this->assertStringContainsString('Data Collection', $privacy);

        // Test dispute response policy
        $disputeMethod = $reflection->getMethod('getStandardDisputeResponsePolicy');
        $disputeMethod->setAccessible(true);
        $dispute = $disputeMethod->invoke($this->evidenceCompiler);

        $this->assertIsString($dispute);
        $this->assertStringContainsString('DISPUTE RESPONSE POLICY', $dispute);
        $this->assertStringContainsString('Response Time', $dispute);

        // Test service guarantee
        $guaranteeMethod = $reflection->getMethod('getStandardServiceGuarantee');
        $guaranteeMethod->setAccessible(true);
        $guarantee = $guaranteeMethod->invoke($this->evidenceCompiler);

        $this->assertIsString($guarantee);
        $this->assertStringContainsString('SERVICE QUALITY GUARANTEE', $guarantee);
        $this->assertStringContainsString('Service Commitment', $guarantee);
    }

    /**
     * Test requirement verification methods
     */
    public function test_verifies_requirements_implementation()
    {
        $reflection = new \ReflectionClass($this->evidenceCompiler);

        // Test requirement 11.7 verification
        $req117Method = $reflection->getMethod('verifyRequirement117');
        $req117Method->setAccessible(true);

        // Test with complete standard documentation
        $completePackage = [
            'standard_documentation' => [
                'standard_terms_and_conditions' => ['data' => 'content'],
                'standard_privacy_policy' => ['data' => 'content'],
                'standard_dispute_response_policy' => ['data' => 'content'],
                'standard_service_guarantee' => ['data' => 'content']
            ],
            'compilation_metadata' => [
                'standard_documentation_included' => true
            ]
        ];

        $this->assertTrue($req117Method->invoke($this->evidenceCompiler, $completePackage));

        // Test with incomplete standard documentation
        $incompletePackage = [
            'standard_documentation' => [
                'standard_terms_and_conditions' => ['data' => 'content']
                // Missing other required docs
            ]
        ];

        $this->assertFalse($req117Method->invoke($this->evidenceCompiler, $incompletePackage));
    }

    /**
     * Test that the enhanced validateEvidence method delegates to validateEvidencePackageCompleteness
     */
    public function test_enhanced_validate_evidence_delegates_correctly()
    {
        $evidence = [
            'receipt' => 'Payment receipt content',
            'customer_communication' => 'Customer email communication'
        ];

        $result1 = $this->evidenceCompiler->validateEvidence($evidence);
        $result2 = $this->evidenceCompiler->validateEvidencePackageCompleteness($evidence);

        // Both methods should return the same result since validateEvidence now delegates
        $this->assertEquals($result2['valid'], $result1['valid']);
        $this->assertEquals($result2['completeness_score'], $result1['completeness_score']);
        $this->assertEquals($result2['submission_readiness'], $result1['submission_readiness']);
    }
}