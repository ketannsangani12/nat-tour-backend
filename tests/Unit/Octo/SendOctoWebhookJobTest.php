<?php

namespace Tests\Unit\Octo;

use App\Jobs\SendOctoWebhookJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for SendOctoWebhookJob's HTTP delivery behavior.
 *
 * The job never touches the DB (see its class docblock), so these tests
 * instantiate it directly with primitives and fake the outbound HTTP call —
 * no tenant DB / OCTO auth setup is needed here.
 */
class SendOctoWebhookJobTest extends TestCase
{
    public function test_handle_posts_payload_as_json_to_webhook_url(): void
    {
        Http::fake(['https://example.com/hook' => Http::response(['received' => true], 200)]);

        $payload = ['type' => 'ping', 'message' => 'This is a test ping from the OCTO API.'];
        (new SendOctoWebhookJob(42, 'https://example.com/hook', [], $payload))->handle();

        Http::assertSent(function ($request) use ($payload) {
            return $request->url() === 'https://example.com/hook'
                && $request->method() === 'POST'
                && $request->data() === $payload;
        });
    }

    public function test_handle_sends_default_content_type_and_user_agent_headers(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new SendOctoWebhookJob(1, 'https://example.com/hook', [], ['type' => 'ping']))->handle();

        Http::assertSent(fn ($request) => $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('User-Agent', 'OCTO-NAT/1.0'));
    }

    public function test_handle_merges_subscriber_supplied_extra_headers(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new SendOctoWebhookJob(1, 'https://example.com/hook', ['X-Reseller-Secret' => 'abc123'], ['type' => 'ping']))->handle();

        Http::assertSent(fn ($request) => $request->hasHeader('X-Reseller-Secret', 'abc123')
            && $request->hasHeader('Content-Type', 'application/json'));
    }

    public function test_handle_lets_extra_headers_override_default_user_agent(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new SendOctoWebhookJob(1, 'https://example.com/hook', ['User-Agent' => 'Custom/2.0'], ['type' => 'ping']))->handle();

        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', 'Custom/2.0'));
    }

    public function test_handle_does_not_fail_job_on_2xx_response(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $job = (new SendOctoWebhookJob(9, 'https://example.com/hook', [], ['type' => 'ping']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotFailed();
    }

    public function test_handle_fails_job_and_logs_warning_on_server_error_response(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'OCTO webhook delivery failed'
                && $context['webhook_id'] === 7
                && $context['status'] === 500;
        });

        $job = (new SendOctoWebhookJob(7, 'https://example.com/hook', [], ['type' => 'ping']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertFailed();
    }

    public function test_handle_fails_job_on_4xx_client_error_response(): void
    {
        Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

        $job = (new SendOctoWebhookJob(3, 'https://example.com/hook', [], ['type' => 'ping']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertFailed();
    }
}
