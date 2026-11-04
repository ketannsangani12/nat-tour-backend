<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BokunAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isAuthenticated($request)) {
            return response()->json([
                "errorCode" => "AUTHORIZATION_FAILURE",
                "errorMessage" => "The provided authentication credentials are not valid."
            ], Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="HiBit"'
            ]);
        }

        return $next($request);
    }

    /**
     * Validate the Authorization Header.
     *
     * @param  Request  $request
     * @return bool
     */
    private function isAuthenticated(Request $request): bool
    {
        if (!$request->hasHeader('Authorization')) {
            return false;
        }

        $authHeader = $request->header('Authorization');
        if (!str_starts_with($authHeader, 'Basic ')) {
            return false;
        }

        $credentials = base64_decode(substr($authHeader, 6));
        if (!$credentials || !str_contains($credentials, ':')) {
            return false;
        }

        [$username, $password] = explode(':', $credentials, 2);

        return $username === config('auth.bokun_auth.username') &&
            $password === config('auth.bokun_auth.password');
    }
}
