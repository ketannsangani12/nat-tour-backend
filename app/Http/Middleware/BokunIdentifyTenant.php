<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BokunIdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $parameters = $request['parameters'] ?? [];
        $partnerId = collect($parameters)->firstWhere('name', 'partner_id')['value'] ?? null;

        $tenant = Tenant::find($partnerId);

        if (!$tenant) {
            return response()->json(['error' => 'Invalid partner_id'], 403);
        }

        // Use the tenancy package's initialization so that QueueTenancyBootstrapper
        // serializes the tenant into any jobs dispatched during this request (e.g.
        // DetectPayoutAdjustment). Manual DB::setDefaultConnection() bypasses this
        // and causes queued jobs to run against the central database instead.
        tenancy()->initialize($tenant);

        $response = $next($request);

        tenancy()->end();

        return $response;
    }
}
