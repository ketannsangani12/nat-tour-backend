<?php

namespace Tests\Unit\Octo;

use App\Models\OctoKey;
use Tests\TestCase;

/**
 * Integration tests for OctoAuthentication middleware.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Octo/OctoAuthMiddlewareTest.php
 */
class OctoAuthMiddlewareTest extends TestCase
{
    private const TEST_KEY      = 'octo-test-key-middleware';
    // Must NOT be a real OTA/reseller name (e.g. 'headout') — octo_keys has a real
    // UNIQUE(tenant_id, reseller) constraint, and a fixed value here previously
    // collided with an actual production key for ('dixies', 'headout'), causing
    // setUp() to throw on every run. Cleanup below is scoped by api_key_hash
    // specifically so it can never match/delete a real key even by accident.
    private const RESELLER_NAME = 'octo-test-reseller-middleware';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        OctoKey::on('mysql')->updateOrCreate(
            ['tenant_id' => 'dixies', 'reseller' => self::RESELLER_NAME],
            [
                'api_key_hash' => hash('sha256', self::TEST_KEY),
                'key_prefix'   => substr(self::TEST_KEY, 0, 12),
                'is_active'    => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        OctoKey::on('mysql')
            ->where('api_key_hash', hash('sha256', self::TEST_KEY))
            ->delete();

        parent::tearDown();
    }

    private function octoHeaders(string $token = self::TEST_KEY, string $capabilities = ''): array
    {
        return [
            'Authorization'      => 'Bearer ' . $token,
            'Octo-Capabilities'  => $capabilities,
            'Content-Type'       => 'application/json',
        ];
    }

    // ── 401 — missing / malformed Authorization header ────────────────────────

    public function test_missing_authorization_header_returns_401(): void
    {
        $response = $this->withHeaders([
            'Octo-Capabilities' => '',
        ])->getJson('/api/v1/octo/supplier');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'UNAUTHORIZED']);
    }

    public function test_non_bearer_authorization_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization'     => 'Basic ' . base64_encode('user:pass'),
            'Octo-Capabilities' => '',
        ])->getJson('/api/v1/octo/supplier');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'UNAUTHORIZED']);
    }

    public function test_bearer_with_empty_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization'     => 'Bearer ',
            'Octo-Capabilities' => '',
        ])->getJson('/api/v1/octo/supplier');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'UNAUTHORIZED']);
    }

    // ── 403 — valid format but wrong token ────────────────────────────────────

    public function test_unknown_api_key_returns_403(): void
    {
        $response = $this->withHeaders([
            'Authorization'     => 'Bearer wrong-key-xyz',
            'Octo-Capabilities' => '',
        ])->getJson('/api/v1/octo/supplier');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'FORBIDDEN']);
    }

    // ── 400 — missing or invalid Octo-Capabilities header ────────────────────

    public function test_missing_capabilities_header_returns_400(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . self::TEST_KEY,
        ])->getJson('/api/v1/octo/supplier');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
    }

    public function test_unknown_capability_is_silently_ignored(): void
    {
        // Per OCTO spec, suppliers must never reject requests for unsupported capabilities —
        // they must silently ignore them. Unknown capabilities pass through without a 400.
        $response = $this->withHeaders([
            'Authorization'     => 'Bearer ' . self::TEST_KEY,
            'Octo-Capabilities' => 'pricing,unknown_capability',
        ])->getJson('/api/v1/octo/supplier');

        $this->assertNotContains($response->status(), [400, 401, 403]);
    }

    // ── 400 — Content-Type on POST ────────────────────────────────────────────

    public function test_post_without_json_content_type_returns_400(): void
    {
        $response = $this->call('POST', '/api/v1/octo/availability', [], [], [], [
            'HTTP_AUTHORIZATION'     => 'Bearer ' . self::TEST_KEY,
            'HTTP_OCTO_CAPABILITIES' => '',
            'CONTENT_TYPE'           => 'application/x-www-form-urlencoded',
        ], json_encode(['productId' => '1', 'optionId' => 'default', 'localDateStart' => '2025-06-01', 'localDateEnd' => '2025-06-30']));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'BAD_REQUEST']);
    }

    // ── valid auth passes through ─────────────────────────────────────────────

    public function test_valid_token_with_empty_capabilities_passes_auth(): void
    {
        $response = $this->withHeaders($this->octoHeaders())->getJson('/api/v1/octo/supplier');

        // Should not be a 401 or 403 — either 200 (supplier found) or 422 (no operator) is fine
        $this->assertNotContains($response->status(), [401, 403]);
    }

    public function test_valid_token_with_pricing_capability_passes_auth(): void
    {
        $response = $this->withHeaders($this->octoHeaders(self::TEST_KEY, 'pricing'))->getJson('/api/v1/octo/supplier');

        $this->assertNotContains($response->status(), [401, 403]);
    }

    public function test_valid_token_with_all_capabilities_passes_auth(): void
    {
        $response = $this->withHeaders($this->octoHeaders(self::TEST_KEY, 'pricing,content,availability'))->getJson('/api/v1/octo/supplier');

        $this->assertNotContains($response->status(), [401, 403, 400]);
    }

    public function test_octo_prefixed_capability_is_accepted_and_normalised(): void
    {
        // Per OCTO spec the canonical form is "octo/pricing", "octo/content", etc.
        // The middleware must strip the "octo/" prefix and treat it identically to the bare name.
        $response = $this->withHeaders($this->octoHeaders(self::TEST_KEY, 'octo/pricing'))->getJson('/api/v1/octo/supplier');

        $this->assertNotContains($response->status(), [400, 401, 403]);
    }

    public function test_mixed_prefixed_and_bare_capabilities_are_all_normalised(): void
    {
        $response = $this->withHeaders($this->octoHeaders(self::TEST_KEY, 'octo/pricing,octo/content,availability'))->getJson('/api/v1/octo/supplier');

        $this->assertNotContains($response->status(), [400, 401, 403]);
    }
}
