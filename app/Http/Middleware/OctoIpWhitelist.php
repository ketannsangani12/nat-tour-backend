<?php

namespace App\Http\Middleware;

use App\Helpers\OctoErrorResponse;
use App\Models\OctoWhitelistedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class OctoIpWhitelist
{
    public const CACHE_KEY = 'octo:whitelisted-ips:active';

    public function handle(Request $request, Closure $next): Response
    {
        // IP whitelist only enforced on staging and production
        if (app()->environment('local', 'development')) {
            return $next($request);
        }

        $allowedIps = array_merge(config('octo.allowed_ips', []), $this->dbWhitelistedIps());

        // No restriction configured — pass through
        if (empty($allowedIps)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        foreach ($allowedIps as $entry) {
            if ($this->matches($clientIp, $entry)) {
                return $next($request);
            }
        }

        return OctoErrorResponse::forbidden("Requests from IP {$clientIp} are not permitted.");
    }

    /**
     * IPs added via the admin self-service endpoint, cached briefly so a busy
     * endpoint isn't hitting the DB on every OCTO request.
     */
    private function dbWhitelistedIps(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            return OctoWhitelistedIp::on('mysql')->active()->pluck('ip')->all();
        });
    }

    /**
     * Match an IP against an exact address or a CIDR range (IPv4 only).
     */
    private function matches(string $ip, string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            return $ip === $entry;
        }

        [$subnet, $bits] = explode('/', $entry, 2);
        $bits = (int) $bits;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
