<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    private const CACHE_KEY = 'central_maintenance_mode';
    private const CACHE_TTL = 60; // seconds

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isMaintenanceModeEnabled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'We are currently under maintenance. Please try again later.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            return response()->view('errors.503', [], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }

    private function isMaintenanceModeEnabled(): bool
    {
        return Cache::store('file')->remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $cdb = config('constants.database.' . app()->environment());
            $value = DB::connection(env('DB_CONNECTION', 'mysql'))
                ->table("{$cdb}.settings")
                ->where('key', 'MAINTENANCE_MODE')
                ->value('value');

            return $value === '1';
        });
    }
}
