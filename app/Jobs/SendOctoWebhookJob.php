<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers a single OCTO webhook notification to the reseller's URL.
 *
 * All data is passed as primitives — no Eloquent model is serialized.
 * This means the job carries everything it needs and does NOT touch the DB
 * during execution, which is critical in a multi-tenant setup where the
 * queue worker runs without any tenant DB context.
 */
class SendOctoWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly int    $webhookDbId,
        private readonly string $url,
        private readonly array  $extraHeaders,
        private readonly array  $payload,
    ) {}

    public function handle(): void
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json', 'User-Agent' => 'OCTO-NAT/1.0'],
            $this->extraHeaders,
        );

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post($this->url, $this->payload);

        if (! $response->successful()) {
            Log::warning('OCTO webhook delivery failed', [
                'webhook_id' => $this->webhookDbId,
                'url'        => $this->url,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);

            $this->fail("Webhook returned HTTP {$response->status()}");
        }
    }
}
