<?php

namespace Tests\Unit;

use App\Http\Controllers\StripeWebhookController;
use App\Services\Dispute\DisputeService;
use Tests\TestCase;
use Mockery;

class StripeWebhookSignatureTest extends TestCase
{
    protected $disputeService;
    protected $webhookController;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disputeService = Mockery::mock(DisputeService::class);
        $this->webhookController = new StripeWebhookController($this->disputeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_webhook_error_logging()
    {
        $eventType = 'charge.dispute.created';
        $eventData = (object) ['id' => 'dp_test_123'];
        $exception = new \Exception('Test error message', 500);

        // handleWebhookError logs via Log::* and must not throw.
        $this->invokeMethod(
            $this->webhookController,
            'handleWebhookError',
            [$eventType, $eventData, $exception]
        );

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
