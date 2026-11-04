<?php

namespace Tests\Unit\Octo;

use App\Http\Middleware\OctoIpWhitelist;
use App\Models\OctoWhitelistedIp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Unit tests for OctoIpWhitelist middleware.
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Octo/OctoIpWhitelistTest.php
 */
class OctoIpWhitelistTest extends TestCase
{
    protected $tenancy = false;

    private OctoIpWhitelist $middleware;

    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware  = new OctoIpWhitelist();
        $this->originalEnv = $this->app['env'];

        Cache::forget(OctoIpWhitelist::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnv;
        Cache::forget(OctoIpWhitelist::CACHE_KEY);
        OctoWhitelistedIp::on('mysql')->where('label', 'octo-ip-whitelist-test')->delete();

        parent::tearDown();
    }

    private function requestFromIp(string $ip): Request
    {
        return Request::create('/api/v1/octo/supplier', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    private function passThrough(Request $request)
    {
        return $this->middleware->handle($request, fn ($req) => new Response('OK', 200));
    }

    public function test_local_environment_bypasses_whitelist_entirely(): void
    {
        $this->app['env'] = 'local';
        Config::set('octo.allowed_ips', ['1.2.3.4']);

        $response = $this->passThrough($this->requestFromIp('9.9.9.9'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_development_environment_bypasses_whitelist_entirely(): void
    {
        $this->app['env'] = 'development';
        Config::set('octo.allowed_ips', ['1.2.3.4']);

        $response = $this->passThrough($this->requestFromIp('9.9.9.9'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_empty_allowed_ips_passes_through_on_production(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', []);
        // Force an empty effective DB-whitelist regardless of real rows that may
        // exist in this shared dev DB (e.g. entries added via the admin page).
        Cache::put(OctoIpWhitelist::CACHE_KEY, [], 60);

        $response = $this->passThrough($this->requestFromIp('9.9.9.9'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_exact_ip_match_passes_on_production(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['34.224.248.1']);

        $response = $this->passThrough($this->requestFromIp('34.224.248.1'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_non_matching_ip_returns_403_on_production(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['34.224.248.1']);

        $response = $this->passThrough($this->requestFromIp('9.9.9.9'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', json_decode($response->getContent(), true)['error']);
    }

    public function test_cidr_range_match_passes(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['10.0.0.0/24']);

        $response = $this->passThrough($this->requestFromIp('10.0.0.55'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_cidr_range_non_match_returns_403(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['10.0.0.0/24']);

        $response = $this->passThrough($this->requestFromIp('10.0.1.5'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_cidr_zero_bits_allows_any_ip(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['0.0.0.0/0']);

        $response = $this->passThrough($this->requestFromIp('123.45.67.89'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_malformed_subnet_entry_does_not_match_or_crash(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['not-an-ip/24']);

        $response = $this->passThrough($this->requestFromIp('10.0.0.1'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_multiple_entries_matches_any_one(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['1.1.1.1', '10.0.0.0/24', '2.2.2.2']);

        $response = $this->passThrough($this->requestFromIp('10.0.0.200'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_db_whitelisted_ip_passes_on_production(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['1.1.1.1']);

        OctoWhitelistedIp::on('mysql')->create([
            'ip'    => '55.66.77.88',
            'label' => 'octo-ip-whitelist-test',
        ]);

        $response = $this->passThrough($this->requestFromIp('55.66.77.88'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_expired_db_whitelisted_ip_returns_403(): void
    {
        $this->app['env'] = 'production';
        Config::set('octo.allowed_ips', ['1.1.1.1']);

        OctoWhitelistedIp::on('mysql')->create([
            'ip'         => '55.66.77.99',
            'label'      => 'octo-ip-whitelist-test',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->passThrough($this->requestFromIp('55.66.77.99'));

        $this->assertSame(403, $response->getStatusCode());
    }
}
