<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockDirectIpAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // Check if the host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Allow specific IPs if needed (like load balancer health checks)
            $allowedIps = [
                '127.0.0.1',
                'localhost',
                // Add any other IPs that should be allowed
            ];

            if (!in_array($host, $allowedIps)) {
                return response('Access denied', 403);
            }
        }

        return $next($request);
    }
}