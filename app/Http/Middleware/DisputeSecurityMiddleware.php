<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Dispute\DisputeAuditLogger;
use Symfony\Component\HttpFoundation\Response;

/**
 * DisputeSecurityMiddleware
 * 
 * Comprehensive security middleware for dispute management operations.
 * Implements rate limiting, security monitoring, and audit logging.
 * 
 * Requirements: 3.6, 3.7, 7.7
 */
class DisputeSecurityMiddleware
{
    /**
     * The audit logger instance.
     */
    protected DisputeAuditLogger $auditLogger;

    /**
     * Rate limiting configuration.
     */
    protected array $rateLimits = [
        'dispute_view' => [
            'max_attempts' => 100,
            'decay_minutes' => 60
        ],
        'dispute_modify' => [
            'max_attempts' => 50,
            'decay_minutes' => 60
        ],
        'evidence_submit' => [
            'max_attempts' => 20,
            'decay_minutes' => 60
        ]
    ];

    /**
     * Security risk patterns.
     */
    protected array $riskPatterns = [
        'sql_injection' => [
            '/(\bunion\b.*\bselect\b)|(\bselect\b.*\bunion\b)/i',
            '/\b(select|insert|update|delete|drop|create|alter)\b.*\b(from|into|table|database)\b/i'
        ],
        'xss_attempt' => [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i'
        ],
        'path_traversal' => [
            '/\.\.[\/\\\\]/',
            '/\.(exe|bat|cmd|sh|php|pl|cgi)$/i'
        ]
    ];

    /**
     * Create a new middleware instance.
     */
    public function __construct(DisputeAuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->performSecurityChecks($request);
            $this->applyRateLimiting($request);
        } catch (\Exception $e) {
            $this->logSecurityIncident($request, $e);
            return $this->handleSecurityError($request, $e);
        }

        return $next($request);
    }

    /**
     * Perform comprehensive security checks on the request.
     * 
     * @param Request $request
     * @throws \Exception
     */
    protected function performSecurityChecks(Request $request): void
    {
        // Check for malicious patterns in request data
        $this->checkForMaliciousPatterns($request);

        // Validate request headers
        $this->validateRequestHeaders($request);

        // Check for suspicious user agent patterns
        $this->checkUserAgent($request);

        // Validate IP address
        $this->validateIpAddress($request);

        // Check for session hijacking indicators
        $this->checkSessionSecurity($request);
    }

    /**
     * Apply rate limiting based on request type and user.
     * 
     * @param Request $request
     * @throws \Exception
     */
    protected function applyRateLimiting(Request $request): void
    {
        $rateLimitKey = $this->getRateLimitKey($request);
        $rateLimitConfig = $this->getRateLimitConfig($request);

        $key = $rateLimitKey . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $rateLimitConfig['max_attempts'])) {
            $this->logRateLimitExceeded($request, $key);
            
            throw new \Exception(
                'Rate limit exceeded. Too many requests.',
                429
            );
        }

        RateLimiter::hit($key, $rateLimitConfig['decay_minutes'] * 60);
    }

    /**
     * Check for malicious patterns in request data.
     * Scoped to body and query parameters only — headers contain legitimate
     * values (Authorization, Content-Type) that would trigger false positives.
     *
     * @param Request $request
     * @throws \Exception
     */
    protected function checkForMaliciousPatterns(Request $request): void
    {
        $requestData = array_merge(
            $request->query(),
            $request->post(),
            [$request->getPathInfo()]
        );

        foreach ($this->riskPatterns as $patternType => $patterns) {
            foreach ($patterns as $pattern) {
                foreach ($requestData as $key => $value) {
                    if (is_string($value) && preg_match($pattern, $value)) {
                        $this->logSecurityThreat($request, $patternType, [
                            'pattern' => $pattern,
                            'matched_value' => $this->sanitizeLogValue($value),
                            'field' => $key
                        ]);

                        throw new \Exception(
                            "Security threat detected: {$patternType}",
                            403
                        );
                    }
                }
            }
        }
    }

    /**
     * Validate request headers for security.
     * 
     * @param Request $request
     * @throws \Exception
     */
    protected function validateRequestHeaders(Request $request): void
    {
        // Check for required security headers in AJAX requests
        if ($request->ajax()) {
            if (!$request->hasHeader('X-Requested-With')) {
                throw new \Exception('Missing required security header', 400);
            }
        }

        // Validate Content-Type for POST requests
        if ($request->isMethod('POST')) {
            $contentType = $request->header('Content-Type');
            $allowedTypes = [
                'application/json',
                'application/x-www-form-urlencoded',
                'multipart/form-data'
            ];

            $isValidContentType = false;
            foreach ($allowedTypes as $type) {
                if (strpos($contentType, $type) !== false) {
                    $isValidContentType = true;
                    break;
                }
            }

            if (!$isValidContentType) {
                throw new \Exception('Invalid content type', 400);
            }
        }

        // Check for suspicious headers
        $suspiciousHeaders = ['X-Forwarded-Host', 'X-Original-URL', 'X-Rewrite-URL'];
        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header)) {
                $this->logSecurityThreat($request, 'suspicious_header', [
                    'header' => $header,
                    'value' => $request->header($header)
                ]);
            }
        }
    }

    /**
     * Check user agent for suspicious patterns.
     * 
     * @param Request $request
     * @throws \Exception
     */
    protected function checkUserAgent(Request $request): void
    {
        $userAgent = $request->userAgent();

        if (empty($userAgent)) {
            $this->logSecurityThreat($request, 'missing_user_agent', []);
            return; // Don't block, but log
        }

        // Check for bot patterns
        $botPatterns = [
            '/bot|crawler|spider|scraper/i',
            '/curl|wget|python|java/i'
        ];

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $this->logSecurityThreat($request, 'bot_detected', [
                    'user_agent' => $userAgent,
                    'pattern' => $pattern
                ]);
                
                // Don't block legitimate bots, but monitor
                break;
            }
        }
    }

    /**
     * Validate IP address for security risks.
     * 
     * @param Request $request
     * @throws \Exception
     */
    protected function validateIpAddress(Request $request): void
    {
        $ip = $request->ip();

        // Check if IP is in blocked list (this would be configurable)
        $blockedIps = config('dispute.security.blocked_ips', []);
        
        if (in_array($ip, $blockedIps)) {
            throw new \Exception('Access denied from this IP address', 403);
        }

        // Check for private/internal IP addresses in production
        if (app()->environment('production')) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $this->logSecurityThreat($request, 'private_ip_access', [
                    'ip_address' => $ip,
                    'environment' => app()->environment()
                ]);
            }
        }
    }

    /**
     * Check session security indicators.
     * 
     * @param Request $request
     * @throws \Exception
     */
    protected function checkSessionSecurity(Request $request): void
    {
        // Skip session checks for stateless/API requests — they have no _token
        if (!$request->hasSession() || $request->expectsJson()) {
            return;
        }

        $session = $request->session();

        // Check for session fixation
        if (!$session->has('_token')) {
            throw new \Exception('Invalid session token', 401);
        }

        // Check session age
        $sessionStart = $session->get('_session_start', time());
        $maxSessionAge = config('dispute.security.max_session_age', 28800); // 8 hours

        if ((time() - $sessionStart) > $maxSessionAge) {
            $session->invalidate();
            throw new \Exception('Session expired', 401);
        }

        // Update session activity
        $session->put('_last_activity', time());
    }

    /**
     * Get rate limit key for the request.
     * 
     * @param Request $request
     * @return string
     */
    protected function getRateLimitKey(Request $request): string
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : 'unknown';

        // Determine rate limit type based on route
        if (strpos($routeName, 'evidence') !== false) {
            return 'evidence_submit';
        } elseif ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE')) {
            return 'dispute_modify';
        } else {
            return 'dispute_view';
        }
    }

    /**
     * Get rate limit configuration for the request.
     * 
     * @param Request $request
     * @return array
     */
    protected function getRateLimitConfig(Request $request): array
    {
        $rateLimitType = $this->getRateLimitKey($request);
        return $this->rateLimits[$rateLimitType] ?? $this->rateLimits['dispute_view'];
    }

    /**
     * Log rate limit exceeded incident.
     * 
     * @param Request $request
     * @param string $key
     */
    protected function logRateLimitExceeded(Request $request, string $key): void
    {
        Log::warning('Rate limit exceeded for dispute management', [
            'rate_limit_key' => $key,
            'ip_address' => $request->ip(),
            'user_id' => Auth::id(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString(),
            'severity' => 'medium'
        ]);

        // Log authentication failure if user is involved
        if (Auth::check()) {
            $this->auditLogger->logAuthenticationFailure('rate_limit_exceeded', [
                'rate_limit_key' => $key,
                'route' => $request->route()?->getName(),
                'method' => $request->method()
            ]);
        }
    }

    /**
     * Log security threat detection.
     * 
     * @param Request $request
     * @param string $threatType
     * @param array $details
     */
    protected function logSecurityThreat(Request $request, string $threatType, array $details): void
    {
        Log::alert('Security threat detected in dispute management', [
            'threat_type' => $threatType,
            'details' => $details,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString(),
            'severity' => 'high',
            'requires_investigation' => true
        ]);
    }

    /**
     * Log security incident.
     * 
     * @param Request $request
     * @param \Exception $exception
     */
    protected function logSecurityIncident(Request $request, \Exception $exception): void
    {
        Log::error('Security incident in dispute management', [
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'request_data' => $this->sanitizeRequestData($request->all()),
            'timestamp' => now()->toISOString(),
            'severity' => 'critical'
        ]);
    }

    /**
     * Handle security errors and return appropriate response.
     * 
     * @param Request $request
     * @param \Exception $exception
     * @return Response
     */
    protected function handleSecurityError(Request $request, \Exception $exception): Response
    {
        $validHttpCodes = [400, 401, 403, 404, 422, 429, 500];
        $statusCode = in_array($exception->getCode(), $validHttpCodes) ? $exception->getCode() : 500;
        $message = $exception->getMessage() ?: 'Security error occurred';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'SECURITY_ERROR',
                'timestamp' => now()->toISOString()
            ], $statusCode);
        }

        // For web requests, redirect with error
        return redirect()->back()->with('error', $message);
    }

    /**
     * Sanitize log value to prevent log injection.
     * 
     * @param string $value
     * @return string
     */
    protected function sanitizeLogValue(string $value): string
    {
        // Remove newlines and limit length
        $sanitized = str_replace(["\n", "\r", "\t"], ' ', $value);
        return substr($sanitized, 0, 200) . (strlen($sanitized) > 200 ? '...' : '');
    }

    /**
     * Sanitize request data for logging.
     * 
     * @param array $data
     * @return array
     */
    protected function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'api_key', 'secret', 'cvv', 'card_number'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }
}