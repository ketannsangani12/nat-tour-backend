<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Dispute;
use App\Models\DisputeTimeline;
use App\Models\TourBooking;
use App\Models\TourOperator;
use App\Models\Transaction;
use App\Services\Dispute\DisputeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class StripeWebhookControllerTest extends TestCase
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
    public function it_can_handle_dispute_created_webhook()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_created_111',
            'amount' => 5000, // $50.00 in cents
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'charge' => 'ch_test_123456',
            'payment_intent' => 'pi_test_123456',
            'evidence_details' => (object) [
                'due_by' => time() + (7 * 24 * 60 * 60), // 7 days from now
                'has_evidence' => false,
                'past_due' => false,
                'submission_count' => 0
            ],
            'created' => time(),
            'metadata' => []
        ];

        // Mock the dispute service to return a created dispute
        $mockDispute = new Dispute([
            'id' => 1,
            'stripe_dispute_id' => 'dp_test_created_111',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response'
        ]);

        $this->disputeService
            ->shouldReceive('createFromStripeData')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($mockDispute);

        // Act
        $this->webhookController->handleDisputeCreated($stripeDispute);

        // Assert - The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_handle_dispute_updated_webhook()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_updated_456',
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
            'stripe_dispute_id' => 'dp_test_updated_456',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response'
        ]);
        $this->trackDisputeCascade($existingDispute);

        // Mock the dispute service sync method
        $updatedDispute = new Dispute([
            'id' => $existingDispute->id,
            'stripe_dispute_id' => 'dp_test_updated_456',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'under_review'
        ]);

        $this->disputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->with($existingDispute->id)
            ->andReturn($updatedDispute);

        // Act
        $this->webhookController->handleDisputeUpdated($stripeDispute);

        // Assert - The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_handle_dispute_closed_webhook()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_closed_789',
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
            'stripe_dispute_id' => 'dp_test_closed_789',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'under_review'
        ]);
        $this->trackDisputeCascade($existingDispute);

        // Mock the dispute service sync method
        $closedDispute = new Dispute([
            'id' => $existingDispute->id,
            'stripe_dispute_id' => 'dp_test_closed_789',
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'fraudulent',
            'status' => 'lost'
        ]);

        $this->disputeService
            ->shouldReceive('syncWithStripe')
            ->once()
            ->with($existingDispute->id)
            ->andReturn($closedDispute);

        // Act
        $this->webhookController->handleDisputeClosed($stripeDispute);

        // Assert - The method should complete without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_converts_stripe_dispute_to_array_correctly()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_123456',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'charge' => 'ch_test_123456',
            'payment_intent' => 'pi_test_123456',
            'evidence_details' => (object) [
                'due_by' => 1234567890,
                'has_evidence' => false,
                'past_due' => false,
                'submission_count' => 0
            ],
            'created' => 1234567890,
            'metadata' => ['key' => 'value']
        ];

        // Act
        $result = $this->invokeMethod($this->webhookController, 'convertStripeDisputeToArray', [$stripeDispute]);

        // Assert
        $this->assertEquals('dp_test_123456', $result['id']);
        $this->assertEquals(5000, $result['amount']);
        $this->assertEquals('usd', $result['currency']);
        $this->assertEquals('fraudulent', $result['reason']);
        $this->assertEquals('warning_needs_response', $result['status']);
        $this->assertEquals('ch_test_123456', $result['charge']);
        $this->assertEquals('pi_test_123456', $result['payment_intent']);
        $this->assertIsArray($result['evidence_details']);
        $this->assertEquals(1234567890, $result['evidence_details']['due_by']);
        $this->assertEquals(1234567890, $result['created']);
        $this->assertEquals(['key' => 'value'], $result['metadata']);
    }

    /** @test */
    public function it_handles_webhook_errors_gracefully()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_graceful_999',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response'
        ];

        // Mock the dispute service to throw an exception
        $this->disputeService
            ->shouldReceive('createFromStripeData')
            ->once()
            ->andThrow(new \Exception('Test error'));

        // Mock Log facade to capture error logging
        Log::shouldReceive('error')->once();

        // Act & Assert - Should not throw exception
        $this->webhookController->handleDisputeCreated($stripeDispute);
        $this->assertTrue(true);
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