<?php

namespace App\Http\Middleware;

use App\Helpers\OctoErrorResponse;
use App\Models\OctoKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OctoAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authorization — Bearer token must be present
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return OctoErrorResponse::unauthorized('API key not sent in Authorization header.');
        }

        // 2. Resolve tenant + reseller from Bearer token
        $match = $this->resolveResellerTenant($token);

        if (!$match) {
            return OctoErrorResponse::forbidden('Invalid or revoked API key.');
        }

        // 3. Octo-Capabilities — header must be present (value may be empty)
        if (!$request->hasHeader('Octo-Capabilities')) {
            return OctoErrorResponse::badRequest('The Octo-Capabilities header is required.');
        }

        // 4. Content-Type — must be application/json for POST, PATCH, DELETE
        if (in_array($request->method(), ['POST', 'PATCH', 'DELETE'], true)) {
            $contentType = $request->header('Content-Type', '');
            if (!str_contains($contentType, 'application/json')) {
                return OctoErrorResponse::badRequest('Content-Type must be application/json for POST, PATCH, and DELETE requests.');
            }
        }

        [$tenantId, $resellerName] = $match;

        $this->switchTenantDatabase($tenantId);

        // Supported capabilities — all others are silently ignored per OCTO spec.
        // Per spec, each capability is prefixed with "octo/" (e.g. "octo/pricing").
        // We normalise both forms so clients sending either "pricing" or "octo/pricing" are handled.
        $supportedCapabilities = ['pricing', 'content', 'notifications'];
        $requestedCapabilities = array_filter(array_map(function (string $cap): string {
            $cap = trim($cap);
            // Strip the "octo/" namespace prefix if present, then compare bare names.
            return str_starts_with($cap, 'octo/') ? substr($cap, 5) : $cap;
        }, explode(',', $request->header('Octo-Capabilities', ''))));
        $activeCapabilities    = array_values(array_intersect($requestedCapabilities, $supportedCapabilities));

        // Make tenant, reseller, and capabilities available to downstream controllers/repositories
        $request->merge([
            'octo_tenant_id'    => $tenantId,
            'octo_reseller'     => $resellerName,
            'octo_capabilities' => $activeCapabilities,
        ]);

        return $next($request);
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }

    /**
     * Hash the incoming token and look it up in octo_keys (central DB).
     * Returns [tenant_id, reseller] or null if not found / inactive.
     */
    private function resolveResellerTenant(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        $key = OctoKey::on('mysql')
            ->where('api_key_hash', $tokenHash)
            ->where('is_active', true)
            ->first();

        if (!$key) {
            return null;
        }

        return [$key->tenant_id, $key->reseller];
    }

    /**
     * Switch the default DB connection to the identified tenant's database.
     */
    private function switchTenantDatabase(string $tenantId): void
    {
        $dbName = "tenant_{$tenantId}";

        Config::set('database.connections.tenant', [
            'driver'    => 'mysql',
            'host'      => config('database.connections.mysql.host'),
            'port'      => config('database.connections.mysql.port'),
            'database'  => $dbName,
            'username'  => config('database.connections.mysql.username'),
            'password'  => config('database.connections.mysql.password'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]);

        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        DB::reconnect('tenant');
    }
}
