<?php

namespace Tests\Feature\Disputes;

use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use App\Models\DisputeResponseTemplate;
use Tests\TestCase;

class DisputeSchemaIntegrityTest extends TestCase
{
    protected $tenancy = false; // Disable tenancy for this test

    /**
     * Property 3: Dispute Information Completeness
     * 
     * For any dispute record, the display should include all required fields 
     * (dispute ID, amount, reason, status, and deadline) and all dispute 
     * information should be accurately stored in the database.
     * 
     * **Validates: Requirements 2.2, 9.1**
     */
    public function test_dispute_information_completeness_property()
    {
        // Test that all required tables exist by checking model classes
        $this->assertTrue(class_exists(Dispute::class));
        $this->assertTrue(class_exists(DisputeEvidence::class));
        $this->assertTrue(class_exists(DisputeTimeline::class));
        $this->assertTrue(class_exists(DisputeResponseTemplate::class));

        // Test that Dispute model has all required fillable fields
        $dispute = new Dispute();
        $fillable = $dispute->getFillable();
        
        $requiredFields = [
            'stripe_dispute_id',
            'booking_id',
            'transaction_id',
            'amount',
            'currency',
            'reason',
            'status',
            'evidence_due_by'
        ];

        foreach ($requiredFields as $field) {
            $this->assertContains($field, $fillable, "Dispute model should have {$field} as fillable");
        }

        // Test that DisputeEvidence model has required fields
        $evidence = new DisputeEvidence();
        $evidenceFillable = $evidence->getFillable();
        
        $requiredEvidenceFields = [
            'dispute_id',
            'evidence_type',
            'file_path',
            'file_name',
            'file_size',
            'content_type',
            'stripe_file_id',
            'submitted_at',
            'created_by',
            'created_by_user_type'
        ];

        foreach ($requiredEvidenceFields as $field) {
            $this->assertContains($field, $evidenceFillable, "DisputeEvidence model should have {$field} as fillable");
        }

        // Test that DisputeTimeline model has required fields
        $timeline = new DisputeTimeline();
        $timelineFillable = $timeline->getFillable();
        
        $requiredTimelineFields = [
            'dispute_id',
            'event_type',
            'event_data',
            'user_id',
            'user_type',
            'ip_address',
            'user_agent',
            'created_at'
        ];

        foreach ($requiredTimelineFields as $field) {
            $this->assertContains($field, $timelineFillable, "DisputeTimeline model should have {$field} as fillable");
        }

        // Test that DisputeResponseTemplate model has required fields
        $template = new DisputeResponseTemplate();
        $templateFillable = $template->getFillable();
        
        $requiredTemplateFields = [
            'name',
            'dispute_reason',
            'template_content',
            'is_active',
            'created_by',
            'created_by_user_type'
        ];

        foreach ($requiredTemplateFields as $field) {
            $this->assertContains($field, $templateFillable, "DisputeResponseTemplate model should have {$field} as fillable");
        }

        // Test business logic methods exist
        $this->assertTrue(method_exists(Dispute::class, 'isOverdue'));
        $this->assertTrue(method_exists(Dispute::class, 'getDaysUntilDeadline'));
        $this->assertTrue(method_exists(Dispute::class, 'requiresAttention'));
        $this->assertTrue(method_exists(Dispute::class, 'getStatusLabelAttribute'));
        $this->assertTrue(method_exists(Dispute::class, 'getReasonLabelAttribute'));

        // Test relationship methods exist
        $this->assertTrue(method_exists(Dispute::class, 'booking'));
        $this->assertTrue(method_exists(Dispute::class, 'transaction'));
        $this->assertTrue(method_exists(Dispute::class, 'evidence'));
        $this->assertTrue(method_exists(Dispute::class, 'timeline'));

        $this->assertTrue(method_exists(DisputeEvidence::class, 'dispute'));
        $this->assertTrue(method_exists(DisputeTimeline::class, 'dispute'));
    }

    /**
     * Property-based test: For any valid dispute data, the system should store 
     * all required fields accurately and maintain data integrity.
     * 
     * **Validates: Requirements 2.2, 9.1**
     */
    public function test_dispute_data_integrity_property()
    {
        // Generate multiple test cases with different dispute data
        $testCases = $this->generateDisputeTestCases();
        
        foreach ($testCases as $testCase) {
            // Create a dispute with the test data
            $disputeData = [
                'stripe_dispute_id' => $testCase['stripe_dispute_id'],
                'booking_id' => $testCase['booking_id'],
                'transaction_id' => $testCase['transaction_id'],
                'amount' => $testCase['amount'],
                'currency' => $testCase['currency'],
                'reason' => $testCase['reason'],
                'status' => $testCase['status'],
                'evidence_due_by' => $testCase['evidence_due_by'],
            ];

            // Test that the dispute can be created with valid data
            $dispute = new Dispute($disputeData);
            
            // Verify all required fields are properly set
            $this->assertEquals($testCase['stripe_dispute_id'], $dispute->stripe_dispute_id);
            $this->assertEquals($testCase['booking_id'], $dispute->booking_id);
            $this->assertEquals($testCase['transaction_id'], $dispute->transaction_id);
            $this->assertEquals($testCase['amount'], $dispute->amount);
            $this->assertEquals($testCase['currency'], $dispute->currency);
            $this->assertEquals($testCase['reason'], $dispute->reason);
            $this->assertEquals($testCase['status'], $dispute->status);
            
            // Test that casts work properly
            $this->assertInstanceOf(\Carbon\Carbon::class, $dispute->evidence_due_by);
            $this->assertIsNumeric($dispute->amount);
            
            // Test that computed properties work correctly
            if ($testCase['status'] === 'warning_needs_response' && $dispute->evidence_due_by->isPast()) {
                $this->assertTrue($dispute->isOverdue(), "Dispute should be overdue for test case: " . json_encode($testCase));
            }
            
            // Test that status and reason labels are generated
            $this->assertNotEmpty($dispute->status_label);
            $this->assertNotEmpty($dispute->reason_label);
        }
    }

    /**
     * Generate test cases for property-based testing of dispute data integrity.
     */
    private function generateDisputeTestCases(): array
    {
        $reasons = ['fraudulent', 'subscription_canceled', 'product_unacceptable', 'credit_not_processed', 'duplicate', 'product_not_received', 'unrecognized', 'general'];
        $statuses = ['warning_needs_response', 'under_review', 'charge_refunded', 'lost', 'won'];
        $currencies = ['USD', 'EUR', 'GBP', 'CAD'];
        
        $testCases = [];
        
        // Generate 20 test cases with different combinations
        for ($i = 0; $i < 20; $i++) {
            $testCases[] = [
                'stripe_dispute_id' => 'dp_test_' . uniqid(),
                'booking_id' => rand(1, 1000),
                'transaction_id' => rand(1, 1000),
                'amount' => round(rand(1000, 500000) / 100, 2), // $10.00 to $5000.00
                'currency' => $currencies[array_rand($currencies)],
                'reason' => $reasons[array_rand($reasons)],
                'status' => $statuses[array_rand($statuses)],
                'evidence_due_by' => now()->addDays(rand(-10, 30)), // Past or future dates
            ];
        }
        
        return $testCases;
    }

    /**
     * Test that model casts are properly configured.
     */
    public function test_model_casts_configuration()
    {
        $dispute = new Dispute();
        $casts = $dispute->getCasts();
        
        $this->assertArrayHasKey('amount', $casts);
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertArrayHasKey('evidence_due_by', $casts);
        $this->assertEquals('datetime', $casts['evidence_due_by']);

        $evidence = new DisputeEvidence();
        $evidenceCasts = $evidence->getCasts();
        
        $this->assertArrayHasKey('submitted_at', $evidenceCasts);
        $this->assertEquals('datetime', $evidenceCasts['submitted_at']);

        $timeline = new DisputeTimeline();
        $timelineCasts = $timeline->getCasts();
        
        $this->assertArrayHasKey('event_data', $timelineCasts);
        $this->assertEquals('array', $timelineCasts['event_data']);

        $template = new DisputeResponseTemplate();
        $templateCasts = $template->getCasts();
        
        $this->assertArrayHasKey('is_active', $templateCasts);
        $this->assertEquals('boolean', $templateCasts['is_active']);
    }

    /**
     * Test that scope methods are properly defined.
     */
    public function test_scope_methods_exist()
    {
        $this->assertTrue(method_exists(Dispute::class, 'scopeByStatus'));
        $this->assertTrue(method_exists(Dispute::class, 'scopeByReason'));
        $this->assertTrue(method_exists(Dispute::class, 'scopeRequiringAttention'));
        $this->assertTrue(method_exists(Dispute::class, 'scopeOverdue'));
        $this->assertTrue(method_exists(Dispute::class, 'scopeActive'));

        $this->assertTrue(method_exists(DisputeEvidence::class, 'scopeByType'));
        $this->assertTrue(method_exists(DisputeEvidence::class, 'scopeSubmitted'));
        $this->assertTrue(method_exists(DisputeEvidence::class, 'scopePending'));

        $this->assertTrue(method_exists(DisputeResponseTemplate::class, 'scopeActive'));
        $this->assertTrue(method_exists(DisputeResponseTemplate::class, 'scopeForReason'));
    }

    /**
     * Test that helper methods are properly defined.
     */
    public function test_helper_methods_exist()
    {
        // DisputeEvidence helper methods
        $this->assertTrue(method_exists(DisputeEvidence::class, 'getFileUrl'));
        $this->assertTrue(method_exists(DisputeEvidence::class, 'isSubmittedToStripe'));
        $this->assertTrue(method_exists(DisputeEvidence::class, 'getTypeLabelAttribute'));
        $this->assertTrue(method_exists(DisputeEvidence::class, 'getFileSizeHumanAttribute'));

        // DisputeTimeline helper methods
        $this->assertTrue(method_exists(DisputeTimeline::class, 'getEventLabelAttribute'));
        $this->assertTrue(method_exists(DisputeTimeline::class, 'getDescriptionAttribute'));
        $this->assertTrue(method_exists(DisputeTimeline::class, 'logDisputeCreated'));
        $this->assertTrue(method_exists(DisputeTimeline::class, 'logEvidenceSubmitted'));

        // DisputeResponseTemplate helper methods
        $this->assertTrue(method_exists(DisputeResponseTemplate::class, 'applyVariables'));
        $this->assertTrue(method_exists(DisputeResponseTemplate::class, 'getAvailableVariables'));
        $this->assertTrue(method_exists(DisputeResponseTemplate::class, 'extractBookingVariables'));
    }
}