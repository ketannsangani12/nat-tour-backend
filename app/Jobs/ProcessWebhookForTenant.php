<?php

namespace App\Jobs;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Tenant;
use App\Models\WebhookAudit;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookForTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $tenantId,
        public readonly array $eventData,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (!$tenant) {
            // The controller now filters out foreign events before dispatch, so this
            // should only fire on a genuine race (tenant deleted between dispatch and
            // execution). Warn rather than error.
            Log::warning('ProcessWebhookForTenant: tenant not found', [
                'tenant_id'  => $this->tenantId,
                'event_id'   => $this->eventData['id'] ?? 'unknown',
                'event_type' => $this->eventData['type'] ?? 'unknown',
            ]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $event = \Stripe\Event::constructFrom($this->eventData);

            if (WebhookAudit::isAlreadyProcessed($event->id)) {
                Log::info('ProcessWebhookForTenant: duplicate event, skipping', [
                    'event_id'  => $event->id,
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            $webhookAudit = WebhookAudit::createFromWebhook(
                $this->eventData,
                '',
                $this->tenantId
            );

            try {
                app(StripeWebhookController::class)->processWebhookEvent($event, $webhookAudit);
                $webhookAudit->markAsProcessed("Processed via central webhook");
            } catch (Exception $e) {
                $webhookAudit->markAsFailed($e->getMessage());
                Log::error('ProcessWebhookForTenant: processing failed', [
                    'event_id'   => $event->id,
                    'event_type' => $event->type,
                    'tenant_id'  => $this->tenantId,
                    'error'      => $e->getMessage(),
                ]);
                throw $e;
            }
        } finally {
            tenancy()->end();
        }
    }
}
