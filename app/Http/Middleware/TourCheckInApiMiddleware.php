<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Constants\Constants;
use Carbon\Carbon;

class TourCheckInApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $acceptHeader = $request->header('x-api-key');
        if(!$acceptHeader){
            return response()->json([
                "code" => "INVALID_HEADER_VALUE",
                "message" => "Api key is missing or has invalid version information",
                "timestamp" => Carbon::now()
            ], 400);
        }
        if ($acceptHeader != Constants::TOUR_CHECK_IN_API_KEY) {
            return response()->json([
                "code" => "UNAUTHORIZED",
                "message" => "Access denied for API key: " .$acceptHeader. "",
                "timestamp" => Carbon::now()
            ], 400);
        }
        return $next($request);
    }
}
