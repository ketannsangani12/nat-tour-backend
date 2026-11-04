<?php

namespace Tests\Unit;

use App\Http\Controllers\StripeWebhookController;
use App\Services\Dispute\DisputeService;
use PHPUnit\Framework\TestCase;
use Mockery;

class StripeWebhookControllerUnitTest extends TestCase
{
    protected $disputeService;
    protected $webhookController;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the DisputeService
        $this->disputeService = Mockery::mock(DisputeService::class);
        
        // Create controller instance
        $this->webhookController = new StripeWebhookController($this->disputeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
    public function it_handles_stripe_dispute_without_optional_fields()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_123456',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response'
            // Missing optional fields: charge, payment_intent, evidence_details, created, metadata
        ];

        // Act
        $result = $this->invokeMethod($this->webhookController, 'convertStripeDisputeToArray', [$stripeDispute]);

        // Assert
        $this->assertEquals('dp_test_123456', $result['id']);
        $this->assertEquals(5000, $result['amount']);
        $this->assertEquals('usd', $result['currency']);
        $this->assertEquals('fraudulent', $result['reason']);
        $this->assertEquals('warning_needs_response', $result['status']);
        $this->assertNull($result['charge']);
        $this->assertNull($result['payment_intent']);
        $this->assertNull($result['evidence_details']);
        $this->assertNull($result['created']);
        $this->assertEquals([], $result['metadata']);
    }

    /** @test */
    public function it_handles_stripe_dispute_with_partial_evidence_details()
    {
        // Arrange
        $stripeDispute = (object) [
            'id' => 'dp_test_123456',
            'amount' => 5000,
            'currency' => 'usd',
            'reason' => 'fraudulent',
            'status' => 'warning_needs_response',
            'evidence_details' => (object) [
                'due_by' => 1234567890
                // Missing: has_evidence, past_due, submission_count
            ]
        ];

        // Act
        $result = $this->invokeMethod($this->webhookController, 'convertStripeDisputeToArray', [$stripeDispute]);

        // Assert
        $this->assertIsArray($result['evidence_details']);
        $this->assertEquals(1234567890, $result['evidence_details']['due_by']);
        $this->assertFalse($result['evidence_details']['has_evidence']);
        $this->assertFalse($result['evidence_details']['past_due']);
        $this->assertEquals(0, $result['evidence_details']['submission_count']);
    }

    /** @test */
    public function it_validates_required_dispute_fields()
    {
        // Test that the conversion method handles all required Stripe dispute fields
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
                'has_evidence' => true,
                'past_due' => false,
                'submission_count' => 1
            ],
            'created' => 1234567890,
            'metadata' => (object) ['booking_id' => '12345']
        ];

        $result = $this->invokeMethod($this->webhookController, 'convertStripeDisputeToArray', [$stripeDispute]);

        // Verify all expected keys are present
        $expectedKeys = [
            'id', 'amount', 'currency', 'reason', 'status', 
            'charge', 'payment_intent', 'evidence_details', 'created', 'metadata'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }

        // Verify evidence_details structure
        $expectedEvidenceKeys = ['due_by', 'has_evidence', 'past_due', 'submission_count'];
        foreach ($expectedEvidenceKeys as $key) {
            $this->assertArrayHasKey($key, $result['evidence_details'], "Missing evidence key: {$key}");
        }

        // Verify metadata is converted to array
        $this->assertIsArray($result['metadata']);
        $this->assertEquals(['booking_id' => '12345'], $result['metadata']);
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