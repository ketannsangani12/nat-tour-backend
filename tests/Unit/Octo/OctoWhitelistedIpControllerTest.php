<?php

namespace Tests\Unit\Octo;

use App\Http\Controllers\Admin\OctoWhitelistedIpController;
use App\Http\Middleware\OctoIpWhitelist;
use App\Models\OctoWhitelistedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unit tests for OctoWhitelistedIpController — the self-service endpoint
 * that lets an admin whitelist a tester's IP without a code deploy.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Octo/OctoWhitelistedIpControllerTest.php
 */
class OctoWhitelistedIpControllerTest extends TestCase
{
    protected $tenancy = false;

    private OctoWhitelistedIpController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new OctoWhitelistedIpController();
        Cache::forget(OctoIpWhitelist::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        OctoWhitelistedIp::on('mysql')->where('label', 'like', 'octo-whitelist-controller-test%')->delete();
        Cache::forget(OctoIpWhitelist::CACHE_KEY);

        parent::tearDown();
    }

    public function test_store_requires_an_ip(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/admin/octo-whitelisted-ips', 'POST', [
            'label' => 'octo-whitelist-controller-test',
        ]);

        $this->controller->store($request);
    }

    public function test_store_accepts_an_explicit_cidr_range(): void
    {
        $request = Request::create('/admin/octo-whitelisted-ips', 'POST', [
            'ip'    => '203.0.113.0/24',
            'label' => 'octo-whitelist-controller-test',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('octo_whitelisted_ips', ['ip' => '203.0.113.0/24']);
    }

    public function test_store_rejects_a_malformed_ip(): void
    {
        // Rejected by the route-level format rule — Laravel's exception handler
        // turns this into a 422 JSON response for real HTTP requests.
        $this->expectException(ValidationException::class);

        $request = Request::create('/admin/octo-whitelisted-ips', 'POST', [
            'ip'    => 'not-an-ip',
            'label' => 'octo-whitelist-controller-test',
        ]);

        $this->controller->store($request);

        $this->assertDatabaseMissing('octo_whitelisted_ips', ['ip' => 'not-an-ip']);
    }

    public function test_store_rejects_an_out_of_range_octet(): void
    {
        // Passes the route-level format regex (digit groups) but fails the
        // controller's explicit IPv4 validation.
        $request = Request::create('/admin/octo-whitelisted-ips', 'POST', [
            'ip'    => '999.999.999.999',
            'label' => 'octo-whitelist-controller-test',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseMissing('octo_whitelisted_ips', ['ip' => '999.999.999.999']);
    }

    public function test_store_always_expires_48_hours_from_now(): void
    {
        $request = Request::create('/admin/octo-whitelisted-ips', 'POST', [
            'ip'    => '66.77.88.99',
            'label' => 'octo-whitelist-controller-test',
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(201, $response->getStatusCode());

        $entry = OctoWhitelistedIp::on('mysql')->where('ip', '66.77.88.99')->firstOrFail();

        $this->assertNotNull($entry->expires_at);
        $this->assertTrue($entry->expires_at->between(now()->addHours(47), now()->addHours(49)));
    }

    public function test_store_ignores_a_client_supplied_expires_at(): void
    {
        // There's no way to override the 48-hour expiry from the request —
        // any "expires_at" input field is simply not read by the controller.
        $request = Request::create('/admin/octo-whitelisted-ips', 'POST', [
            'ip'         => '66.77.88.100',
            'label'      => 'octo-whitelist-controller-test',
            'expires_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ]);

        $response = $this->controller->store($request);

        $this->assertSame(201, $response->getStatusCode());

        $entry = OctoWhitelistedIp::on('mysql')->where('ip', '66.77.88.100')->firstOrFail();

        $this->assertTrue($entry->expires_at->between(now()->addHours(47), now()->addHours(49)));
    }

    public function test_destroy_removes_the_entry_and_clears_the_cache(): void
    {
        $entry = OctoWhitelistedIp::on('mysql')->create([
            'ip'    => '44.55.66.77',
            'label' => 'octo-whitelist-controller-test',
        ]);
        Cache::put(OctoIpWhitelist::CACHE_KEY, ['44.55.66.77'], 60);

        $response = $this->controller->destroy($entry);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('octo_whitelisted_ips', ['id' => $entry->id]);
        $this->assertFalse(Cache::has(OctoIpWhitelist::CACHE_KEY));
    }
}
