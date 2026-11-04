<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use App\Models\TourBooking;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\DisputeAuditLogger;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * Integration tests for evidence upload and submission workflows
 *
 * Tests Requirements:
 * - 4.1: Evidence upload and submission workflows
 * - 4.2: Evidence validation and file handling
 * - 4.3: Evidence submission to Stripe
 * - 4.4: Evidence audit trail and logging
 */
class EvidenceUploadWorkflowTest extends TestCase
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
     * Test complete evidence upload workflow with PDF file
     * Validates: Requirements 4.1
     */
    public function test_complete_evidence_upload_workflow_with_pdf()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_pdf',
            'status' => 'warning_needs_response',
            'amount' => 150.00
        ]);

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
        $pdfFile = UploadedFile::fake()->create('receipt.pdf', 2048, 'application/pdf');

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files'      => [$pdfFile],
                'description'         => 'Customer receipt for tour booking',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Evidence submitted successfully.',
            'data' => [
                'dispute_id'  => '1',
                'file_count'  => 1,
            ]
        ]);
    }

    /**
     * Test evidence upload with multiple file types in a single submission
     * Validates: Requirements 4.1, 4.2
     */
    public function test_evidence_upload_with_multiple_file_types()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_multi',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->with(1, [], Mockery::on(function ($files) {
                return isset($files['receipt'])
                    && isset($files['customer_signature'])
                    && isset($files['service_documentation'])
                    && isset($files['refund_policy']);
            }))
            ->once()
            ->andReturn(true);

        Storage::fake('evidence');

        $pdfFile = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
        $jpgFile = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(1024);
        $pngFile = UploadedFile::fake()->image('screenshot.png', 1024, 768)->size(1024);
        $docFile = UploadedFile::fake()->create('policy.doc', 1024, 'application/msword');

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt', 'customer_signature', 'service_documentation', 'refund_policy'],
                'evidence_files'      => [$pdfFile, $jpgFile, $pngFile, $docFile],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'    => true,
            'data'       => ['file_count' => 4],
        ]);
    }

    /**
     * Test file size validation (10MB limit)
     * Validates: Requirements 4.2
     */
    public function test_file_size_validation()
    {
        Storage::fake('evidence');

        $largeFile = UploadedFile::fake()->create('large_file.pdf', 11000); // 11MB

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files'      => [$largeFile],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_files.0']);
    }

    /**
     * Test invalid file type validation
     * Validates: Requirements 4.2
     */
    public function test_invalid_file_type_validation()
    {
        Storage::fake('evidence');

        $invalidFile = UploadedFile::fake()->create('document.txt', 1024, 'text/plain');

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files'      => [$invalidFile],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_files.0']);
    }

    /**
     * Test evidence submission with text only (no file)
     * Validates: Requirements 4.1
     */
    public function test_evidence_submission_with_text_only()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_text',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        // DisputeController packs any text-only evidence into the
        // `uncategorized_text` bucket regardless of which evidence_type the
        // admin picked (see DisputeController::submitEvidence around line
        // 367) — the Stripe Dispute Evidence API treats the `*_text` fields
        // as the catch-all for free-form text. See PR #5451.
        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->with(1, Mockery::on(function ($evidenceData) {
                return isset($evidenceData['uncategorized_text']) &&
                       $evidenceData['uncategorized_text'] === 'Customer email confirming satisfaction with tour service. No complaints received.';
            }), [])
            ->once()
            ->andReturn(true);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_text' => 'Customer email confirming satisfaction with tour service. No complaints received.',
                'description'   => 'Follow-up email from customer',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test evidence submission with both file and text
     * Validates: Requirements 4.1
     */
    public function test_evidence_submission_with_file_and_text()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_both',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        // Text goes into `uncategorized_text` (see PR #5451); the uploaded
        // file is still keyed by the form's evidence_type, so the $files
        // array key stays `customer_communication`.
        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->with(1,
                Mockery::on(function ($evidenceData) {
                    return isset($evidenceData['uncategorized_text']);
                }),
                Mockery::on(function ($files) {
                    return isset($files['customer_communication']);
                })
            )
            ->once()
            ->andReturn(true);

        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('email.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['customer_communication'],
                'evidence_files'      => [$file],
                'evidence_text'       => 'Email thread with customer showing positive feedback',
                'description'         => 'Complete email conversation',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test invalid evidence type value validation
     * Validates: Requirements 4.2
     */
    public function test_evidence_type_validation()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['invalid_type'],
                'evidence_files'      => [$file],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_file_types.0']);
    }

    /**
     * Test all valid evidence types accepted
     * Validates: Requirements 4.1, 4.2
     */
    public function test_all_valid_evidence_types()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_all_types',
            'status' => 'warning_needs_response'
        ]);

        $validTypes = [
            'receipt',
            'customer_communication',
            'service_documentation',
            'shipping_documentation',
            'duplicate_charge_documentation',
            'refund_policy',
            'cancellation_policy',
            'customer_signature',
            'uncategorized_file',
        ];

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->times(count($validTypes))
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->times(count($validTypes))
            ->andReturn(true);

        Storage::fake('evidence');

        foreach ($validTypes as $type) {
            $file = UploadedFile::fake()->create("evidence_{$type}.pdf", 1024);

            $response = $this->actingAs($this->admin, 'admin')
                ->postJson(route('admin.disputes.submit-evidence', 1), [
                    'evidence_file_types' => [$type],
                    'evidence_files'      => [$file],
                ]);

            $response->assertStatus(200);
        }
    }

    /**
     * Test evidence submission failure handling
     * Validates: Requirements 4.3
     */
    public function test_evidence_submission_failure_handling()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_failure',
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
            ->andReturn(false);

        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files'      => [$file],
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Failed to submit evidence to Stripe. Please try again.'
        ]);
    }

    /**
     * Test evidence submission for dispute in wrong status
     * Validates: Requirements 4.1
     */
    public function test_evidence_submission_for_wrong_status()
    {
        $closedDispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_closed',
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
                'evidence_files'      => [$file],
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Cannot submit evidence for disputes with status: Won'
        ]);
    }

    /**
     * Test evidence text length validation
     * Validates: Requirements 4.2
     */
    public function test_evidence_text_length_validation()
    {
        $longText = str_repeat('This is a very long evidence text. ', 200); // ~6600 characters

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_text' => $longText,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_text']);
    }

    /**
     * Test description length validation
     * Validates: Requirements 4.2
     */
    public function test_description_length_validation()
    {
        Storage::fake('evidence');
        $file = UploadedFile::fake()->create('receipt.pdf', 1024);

        $longDescription = str_repeat('This is a very long description. ', 40); // ~1320 characters

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files'      => [$file],
                'description'         => $longDescription,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    /**
     * Test submitting nothing (no file, no text) is rejected
     * Validates: Requirements 4.2
     */
    public function test_empty_submission_rejected()
    {
        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->zeroOrMoreTimes()
            ->andReturn(null);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), []);

        $response->assertStatus(422);
    }

    /**
     * Test concurrent evidence submissions
     * Validates: Requirements 4.1, 4.3
     */
    public function test_concurrent_evidence_submissions()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_concurrent',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->times(3)
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->times(3)
            ->andReturn(true);

        Storage::fake('evidence');

        $types = ['receipt', 'service_documentation', 'refund_policy'];
        $responses = [];

        foreach ($types as $i => $type) {
            $file = UploadedFile::fake()->create("evidence_{$i}.pdf", 1024);

            $responses[] = $this->actingAs($this->admin, 'admin')
                ->postJson(route('admin.disputes.submit-evidence', 1), [
                    'evidence_file_types' => [$type],
                    'evidence_files'      => [$file],
                    'description'         => "Evidence file {$i}",
                ]);
        }

        foreach ($responses as $response) {
            $response->assertStatus(200);
            $response->assertJson(['success' => true]);
        }
    }

    /**
     * Test evidence submission with special characters in filename
     * Validates: Requirements 4.1, 4.2
     */
    public function test_evidence_submission_with_special_characters_in_filename()
    {
        $dispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_special_chars',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('getDisputeById')
            ->with(1)
            ->once()
            ->andReturn($dispute);

        $this->disputeService
            ->shouldReceive('submitEvidence')
            ->once()
            ->andReturn(true);

        Storage::fake('evidence');

        $file = UploadedFile::fake()->create('receipt (copy) - 2024 #1.pdf', 1024);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.disputes.submit-evidence', 1), [
                'evidence_file_types' => ['receipt'],
                'evidence_files'      => [$file],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
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
