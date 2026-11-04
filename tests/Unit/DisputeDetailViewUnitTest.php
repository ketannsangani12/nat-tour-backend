<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;

class DisputeDetailViewUnitTest extends TestCase
{

    protected $tenancy = false; // Disable tenancy for this test

    /** @test */
    public function dispute_model_has_correct_attributes()
    {
        $dispute = new Dispute([
            'stripe_dispute_id' => 'dp_test123',
            'amount' => 100.50,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(7)
        ]);

        $this->assertEquals('dp_test123', $dispute->stripe_dispute_id);
        $this->assertEquals(100.50, $dispute->amount);
        $this->assertEquals('USD', $dispute->currency);
        $this->assertEquals('fraudulent', $dispute->reason);
        $this->assertEquals('warning_needs_response', $dispute->status);
    }

    /** @test */
    public function dispute_calculates_days_until_deadline_correctly()
    {
        $dispute = new Dispute([
            'evidence_due_by' => now()->addDays(5)->startOfDay()
        ]);

        // Allow for small time differences
        $days = $dispute->getDaysUntilDeadline();
        $this->assertTrue($days >= 4 && $days <= 5, "Expected 4-5 days, got {$days}");
    }

    /** @test */
    public function dispute_identifies_overdue_status()
    {
        $dispute = new Dispute([
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->subDay()
        ]);

        $this->assertTrue($dispute->isOverdue());
    }

    /** @test */
    public function dispute_identifies_requiring_attention()
    {
        $dispute = new Dispute([
            'status' => 'warning_needs_response',
            'evidence_due_by' => now()->addDays(2)
        ]);

        $this->assertTrue($dispute->requiresAttention());
    }

    /** @test */
    public function dispute_status_label_returns_human_readable_format()
    {
        $dispute = new Dispute(['status' => 'warning_needs_response']);
        $this->assertEquals('Needs Response', $dispute->getStatusLabelAttribute());

        $dispute = new Dispute(['status' => 'under_review']);
        $this->assertEquals('Under Review', $dispute->getStatusLabelAttribute());

        $dispute = new Dispute(['status' => 'won']);
        $this->assertEquals('Won', $dispute->getStatusLabelAttribute());
    }

    /** @test */
    public function dispute_reason_label_returns_human_readable_format()
    {
        $dispute = new Dispute(['reason' => 'fraudulent']);
        $this->assertEquals('Fraudulent', $dispute->getReasonLabelAttribute());

        $dispute = new Dispute(['reason' => 'product_not_received']);
        $this->assertEquals('Product Not Received', $dispute->getReasonLabelAttribute());
    }

    /** @test */
    public function dispute_evidence_model_has_correct_attributes()
    {
        $evidence = new DisputeEvidence([
            'evidence_type' => 'receipt',
            'file_name' => 'receipt.pdf',
            'file_size' => 1024,
            'content_type' => 'application/pdf'
        ]);

        $this->assertEquals('receipt', $evidence->evidence_type);
        $this->assertEquals('receipt.pdf', $evidence->file_name);
        $this->assertEquals(1024, $evidence->file_size);
        $this->assertEquals('application/pdf', $evidence->content_type);
    }

    /** @test */
    public function dispute_evidence_type_label_returns_human_readable_format()
    {
        $evidence = new DisputeEvidence(['evidence_type' => 'receipt']);
        $this->assertEquals('Receipt', $evidence->getTypeLabelAttribute());

        $evidence = new DisputeEvidence(['evidence_type' => 'customer_communication']);
        $this->assertEquals('Customer Communication', $evidence->getTypeLabelAttribute());
    }

    /** @test */
    public function dispute_evidence_file_size_human_readable()
    {
        $evidence = new DisputeEvidence(['file_size' => 1024]); // 1KB
        $this->assertEquals('1 KB', $evidence->getFileSizeHumanAttribute());

        $evidence = new DisputeEvidence(['file_size' => 1048576]); // 1MB
        $this->assertEquals('1 MB', $evidence->getFileSizeHumanAttribute());
        
        $evidence = new DisputeEvidence(['file_size' => 512]); // 512 bytes
        $this->assertEquals('512 B', $evidence->getFileSizeHumanAttribute());
    }

    /** @test */
    public function dispute_timeline_event_label_returns_human_readable_format()
    {
        $timeline = new DisputeTimeline(['event_type' => 'dispute_created']);
        $this->assertEquals('Dispute Created', $timeline->getEventLabelAttribute());

        $timeline = new DisputeTimeline(['event_type' => 'evidence_submitted']);
        $this->assertEquals('Evidence Submitted', $timeline->getEventLabelAttribute());
    }

    /** @test */
    public function dispute_timeline_generates_correct_descriptions()
    {
        $timeline = new DisputeTimeline([
            'event_type' => 'dispute_created'
        ]);
        $this->assertEquals('Dispute was created from Stripe webhook', $timeline->getDescriptionAttribute());

        $timeline = new DisputeTimeline([
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => 'warning_needs_response',
                'new_status' => 'under_review'
            ]
        ]);
        $this->assertEquals('Status changed from warning_needs_response to under_review', $timeline->getDescriptionAttribute());
    }

    /** @test */
    public function view_file_exists()
    {
        $viewPath = resource_path('views/users/admin/disputes/show.blade.php');
        $this->assertFileExists($viewPath);
    }

    /** @test */
    public function view_contains_required_sections()
    {
        $viewContent = file_get_contents(resource_path('views/users/admin/disputes/show.blade.php'));
        
        // Check for main sections
        $this->assertStringContainsString('Dispute Information', $viewContent);
        $this->assertStringContainsString('Evidence Files', $viewContent);
        $this->assertStringContainsString('Activity Timeline', $viewContent);
        
        // Check for modals
        $this->assertStringContainsString('evidenceModal', $viewContent);
        $this->assertStringContainsString('statusModal', $viewContent);
        
        // Check for JavaScript functionality
        $this->assertStringContainsString('submitEvidenceButton', $viewContent);
        $this->assertStringContainsString('syncWithStripe', $viewContent);
    }

    /** @test */
    public function controller_method_exists()
    {
        $this->assertTrue(method_exists(\App\Http\Controllers\Admin\DisputeController::class, 'show'));
        $this->assertTrue(method_exists(\App\Http\Controllers\Admin\DisputeController::class, 'downloadEvidence'));
    }
}