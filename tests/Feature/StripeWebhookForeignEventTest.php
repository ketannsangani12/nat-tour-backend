<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookForTenant;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * nat shares a Stripe account with a separate product (the Laguna Creek POS), so
 * Stripe fans that product's events out to nat's webhook endpoint too. These tests
 * cover nat's handling of such "foreign" events: it must record them (for
 * visibility) but must NOT dispatch a processing job that could only fail, while
 * still processing its own events normally.
 *
 * Runs un-tenanted ($tenancy = false) so the default connection is the central DB
 * that holds the webhook_events and tenants tables. The suite has no
 * RefreshDatabase, so created rows are cleaned up explicitly.
 */
class StripeWebhookForeignEventTest extends TestCase
{
    protected $tenancy = false;

    private string $secret = 'whsec_test_foreign_event_secret';

    /** Event IDs created during a test, removed in tearDown. */
    private array $createdEventIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Force the controller to fall back to this known secret (the central
        // settings table has no STRIPE_WEBHOOK_SECRET row in the test DB).
        config(['services.stripe.webhook_secret' => $this->secret]);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdEventIds)) {
            WebhookEvent::whereIn('stripe_event_id', $this->createdEventIds)->delete();
        }
        parent::tearDown();
    }

    /** @test */
    public function foreign_event_is_recorded_as_unroutable_and_not_dispatched(): void
    {
        $eventId = 'evt_test_foreign_' . uniqid();
        $this->createdEventIds[] = $eventId;

        $response = $this->postStripeEvent($this->paymentIntentEvent(
            $eventId,
            'production/pos/tenant/qy87bRxmph617AVk1VR635TsxuLsPReg6jMe7FX7' // no matching nat tenant
        ));

        $response->assertOk();

        $record = WebhookEvent::where('stripe_event_id', $eventId)->first();
        $this->assertNotNull($record, 'foreign event should still be recorded');
        $this->assertSame('unroutable', $record->status);
        // Metadata is retained, not dropped.
        $this->assertSame(
            'production/pos/tenant/qy87bRxmph617AVk1VR635TsxuLsPReg6jMe7FX7',
            $record->payload['data']['object']['metadata']['tenant_id'] ?? null
        );

        Queue::assertNotPushed(ProcessWebhookForTenant::class);
    }

    /** @test */
    public function own_event_is_dispatched_for_processing(): void
    {
        $tenant = Tenant::query()->first();
        $this->assertNotNull($tenant, 'a real tenant is required for this test');

        $eventId = 'evt_test_own_' . uniqid();
        $this->createdEventIds[] = $eventId;

        $response = $this->postStripeEvent($this->paymentIntentEvent($eventId, $tenant->id));

        $response->assertOk();

        $record = WebhookEvent::where('stripe_event_id', $eventId)->first();
        $this->assertNotNull($record);
        $this->assertSame('dispatched', $record->status);

        Queue::assertPushed(
            ProcessWebhookForTenant::class,
            fn ($job) => $job->tenantId === $tenant->id
        );
    }

    /** @test */
    public function is_foreign_event_distinguishes_our_tenants_from_others(): void
    {
        $controller = $this->app->make(\App\Http\Controllers\StripeWebhookController::class);
        $tenant = Tenant::query()->first();
        $this->assertNotNull($tenant);

        // null tenant_id is not "foreign" — it has its own unroutable handling.
        $this->assertFalse($this->invokeIsForeign($controller, null));
        // A known nat tenant is ours.
        $this->assertFalse($this->invokeIsForeign($controller, $tenant->id));
        // A non-null id matching no tenant is foreign.
        $this->assertTrue($this->invokeIsForeign($controller, 'production/pos/tenant/does-not-exist'));
    }

    private function invokeIsForeign($controller, ?string $tenantId): bool
    {
        $method = new \ReflectionMethod($controller, 'isForeignEvent');
        $method->setAccessible(true);

        return $method->invoke($controller, $tenantId);
    }

    /**
     * POST a Stripe event to the webhook endpoint with a valid signature, sending
     * the exact signed bytes as the raw request body.
     */
    private function postStripeEvent(array $event)
    {
        $payload   = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);

        return $this->call(
            'POST',
            '/stripe-webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE'          => 'application/json',
            ],
            $payload
        );
    }

    private function paymentIntentEvent(string $eventId, string $tenantId): array
    {
        return [
            'id'      => $eventId,
            'object'  => 'event',
            'type'    => 'payment_intent.succeeded',
            'created' => time(),
            'data'    => [
                'object' => [
                    'id'       => 'pi_test_' . uniqid(),
                    'object'   => 'payment_intent',
                    'metadata' => [
                        'tenant_id' => $tenantId,
                    ],
                ],
            ],
        ];
    }
}
