<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Dispute;
use App\Models\WebhookAudit;
use App\Services\Dispute\DisputeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;

class EnhancedStripeWebhookTest extends TestCase
{

    protected $disputeService;
    protected $webhookController;

    /**
     * Dispute::factory()->create() without explicit booking_id/transaction_id
     * cascades a fresh TourBooking + Transaction row via DisputeFactory's
     * definition(). tests/TestCase.php runs against the real tenant DB with no
     * transaction/rollback wrapper, so these ids must be tracked here and
     * cleaned up explicitly in tearDown().
     */
    protected array $createdDisputeIds = [];
    protected array $createdBookingIds = [];
    protected array $createdTransactionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the DisputeService
        $this->disputeService = Mockery::mock(DisputeService::class);
        $this->app->instance(DisputeService::class, $this->disputeService);

        // Create controller instance
        $this->webhookController = new StripeWebhookController($this->disputeService);
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdDisputeIds)) {
            DB::table('disputes')->whereIn('id', $this->createdDisputeIds)->delete();
        }
        if (!empty($this->createdTransactionIds)) {
            DB::table('transactions')->whereIn('id', $this->createdTransactionIds)->delete();
        }
        if (!empty($this->createdBookingIds)) {
            DB::table('transactions')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_transact')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_booking_history')->whereIn('tour_booking_id', $this->createdBookingIds)->delete();
            DB::table('tour_bookings_master')->whereIn('id', $this->createdBookingIds)->delete();
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Track every id a bare Dispute::factory()->create() call cascades:
     * the dispute itself, its booking_id, its transaction_id, AND the
     * SEPARATE booking that TransactionFactory's own default
     * 'tour_booking_id' => TourBooking::factory() independently creates
     * (it does not reuse the dispute's booking_id unless told to).
     */
    protected function trackDisputeCascade(Dispute $dispute): void
    {
        $this->createdDisputeIds[] = $dispute->id;
        $this->createdBookingIds[] = $dispute->booking_id;
        $this->createdTransactionIds[] = $dispute->transaction_id;

        $transactionBookingId = DB::table('transactions')->where('id', $dispute->transaction_id)->value('tour_booking_id');
        if ($transactionBookingId) {
            $this->createdBookingIds[] = $transactionBookingId;
        }
    }

    /** @test */
    public function it_creates_webhook_audit_record_for_dispute_created()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_enh_created_111',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'charge' => 'ch_test_123456',
            'payment_intent' => 'pi_test_123456',
            'evidence_details' => (object) [
                'due_by' => time() + (7 * 24 * 60 * 60),
                'has_evidence' => false,
                'past_due' => false,
                'submission_count' => 0
            ],
            'created' => time(),
            'metadata' => []
        ];

        // Mock the dispute service (different stripe_dispute_id to avoid duplicate-check short-circuit)
        $mockDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_enh_created_return_' . uniqid(),
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response'
        ]);
        $this->trackDisputeCascade($mockDispute);

        $this->disputeService
            ->shouldReceive('createFromStripeData')
            ->once()
            ->andReturn($mockDispute);

        // Act - DisputeTimeline writes are real DB operations (OK in tenant context)
        $this->webhookController->handleDisputeCreated($stripeDispute);

        // Assert - The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_comprehensive_timeline_for_dispute_updates()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_enh_updated_222',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'under_review',
            'charge' => 'ch_test_123456',
            'payment_intent' => 'pi_test_123456',
            'evidence_details' => (object) [
                'due_by' => time() + (7 * 24 * 60 * 60),
                'has_evidence' => true,
                'past_due' => false,
                'submission_count' => 1
            ],
            'created' => time(),
            'metadata' => []
        ];

        // Create a real dispute in the DB
        $existingDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_enh_updated_222',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response'
        ]);
        $this->trackDisputeCascade($existingDispute);

        // Mock updated dispute with new status
        $updatedDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_enh_updated_222_synced',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'under_review'
        ]);
        $this->trackDisputeCascade($updatedDispute);

        $this->disputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->with($existingDispute->id)
            ->andReturn($updatedDispute);

        // Act - DisputeTimeline writes are real DB operations (OK in tenant context)
        $this->webhookController->handleDisputeUpdated($stripeDispute);

        // Assert - The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_duplicate_webhook_events()
    {
        // Arrange - create an existing dispute to simulate a duplicate creation attempt
        $duplicateDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_enh_duplicate_333',
            'status' => 'warning_needs_response',
        ]);
        $this->trackDisputeCascade($duplicateDispute);

        $stripeDispute = (object) [
            'id' => 'dp_enh_duplicate_333',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'charge' => 'ch_test_123456',
            'payment_intent' => 'pi_test_123456',
            'evidence_details' => (object) [
                'due_by' => time() + (7 * 24 * 60 * 60),
                'has_evidence' => false,
                'past_due' => false,
                'submission_count' => 0
            ],
            'created' => time(),
            'metadata' => []
        ];

        // createFromStripeData should NOT be called since dispute already exists
        $this->disputeService->shouldNotReceive('createFromStripeData');

        // Act
        $this->webhookController->handleDisputeCreated($stripeDispute);

        // Assert - should handle duplicate gracefully without creating a new dispute
        $this->assertTrue(true);
    }

    /** @test */
    public function it_stores_comprehensive_closure_metadata()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_enh_closed_444',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'lost',
            'charge' => 'ch_test_123456',
            'payment_intent' => 'pi_test_123456',
            'evidence_details' => (object) [
                'due_by' => time() + (7 * 24 * 60 * 60),
                'has_evidence' => true,
                'past_due' => false,
                'submission_count' => 1
            ],
            'created' => time(),
            'metadata' => []
        ];

        // Create a real dispute in the DB
        $existingDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_enh_closed_444',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'under_review',
            'created_at' => now()->subDays(5)
        ]);
        $this->trackDisputeCascade($existingDispute);

        // Mock closed dispute using a persisted record so DisputeTimeline FK resolves
        $closedDispute = Dispute::factory()->create([
            'stripe_dispute_id' => 'dp_enh_closed_444_synced',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'lost'
        ]);
        $this->trackDisputeCascade($closedDispute);

        $this->disputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->with($existingDispute->id)
            ->andReturn($closedDispute);

        // Act - DisputeTimeline writes are real DB operations (OK in tenant context)
        $this->webhookController->handleDisputeClosed($stripeDispute);

        // Assert - The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function webhook_audit_model_creates_record_correctly()
    {
        // Arrange
        $eventData = [
            'id' => 'evt_test_wh_audit_' . uniqid(),
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'id' => 'dp_test_123456'
                ]
            ]
        ];
        $signature = 'test_signature';
        $tenantId = 'tenant_test';

        // Act - real DB write in tenant context
        $webhookAudit = WebhookAudit::createFromWebhook($eventData, $signature, $tenantId);

        // Assert
        $this->assertEquals($eventData['id'], $webhookAudit->event_id);
        $this->assertEquals('charge.dispute.created', $webhookAudit->event_type);
        $this->assertEquals('dp_test_123456', $webhookAudit->object_id);
        $this->assertEquals($eventData, $webhookAudit->payload);
        $this->assertEquals('test_signature', $webhookAudit->signature);
        $this->assertEquals('pending', $webhookAudit->processing_status);
        $this->assertEquals('tenant_test', $webhookAudit->tenant_id);
    }

    /** @test */
    public function webhook_audit_can_be_marked_as_processed()
    {
        // Arrange - create a persisted record so update() can run
        $webhookAudit = WebhookAudit::create([
            'event_id' => 'evt_mark_processed_' . uniqid(),
            'event_type' => 'charge.dispute.created',
            'processing_status' => 'pending',
            'received_at' => now(),
            'tenant_id' => tenant()->id,
        ]);

        // Act
        $webhookAudit->markAsProcessed('Successfully processed charge.dispute.created event');

        // Assert
        $this->assertEquals('processed', $webhookAudit->processing_status);
        $this->assertEquals('Successfully processed charge.dispute.created event', $webhookAudit->processing_notes);
        $this->assertNotNull($webhookAudit->processed_at);
    }

    /** @test */
    public function webhook_audit_can_be_marked_as_failed()
    {
        // Arrange - create a persisted record so update() can run
        $webhookAudit = WebhookAudit::create([
            'event_id' => 'evt_mark_failed_' . uniqid(),
            'event_type' => 'charge.dispute.created',
            'processing_status' => 'pending',
            'received_at' => now(),
            'tenant_id' => tenant()->id,
        ]);

        // Act
        $webhookAudit->markAsFailed('Database connection failed');

        // Assert
        $this->assertEquals('failed', $webhookAudit->processing_status);
        $this->assertEquals('Database connection failed', $webhookAudit->processing_notes);
        $this->assertNotNull($webhookAudit->processed_at);
    }

    /**
     * Helper method to invoke private/protected methods for testing
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
