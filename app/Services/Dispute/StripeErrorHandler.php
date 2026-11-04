<?php

namespace App\Services\Dispute;

use App\Models\Dispute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\CardException;
use Exception;
use Carbon\Carbon;

/**
 * StripeErrorHandler
 * 
 * Comprehensive error handling service for Stripe API operations.
 * Implements rate limiting, exponential backoff, authentication error handling,
 * and fallback data display mechanisms.
 * 
 * Requirements: 3.6, 3.7
 */
class StripeErrorHandler
{
    /**
     * Maximum retry attempts for API calls.
     */
    protected int $maxRetries = 5;

    /**
     * Base delay for exponential backoff (seconds).
     */
    protected int $baseDelay = 1;

    /**
     * Maximum delay for exponential backoff (seconds).
     */
    protected int $maxDelay = 300; // 5 minutes

    /**
     * Rate limit cache key prefix.
     */
    protected string $rateLimitCachePrefix = 'stripe_rate_limit_';

    /**
     * Execute a Stripe API operation with comprehensive error handling.
     * 
     * @param callable $operation The Stripe API operation to execute
     * @param array $context Additional context for logging
     * @param bool $enableFallback Whether to enable fallback data on failure
     * @return mixed
     * @throws Exception When operation fails after all retries
     */
    public function executeWithErrorHandling(callable $operation, array $context = [], bool $enableFallback = false)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                // Check if we're currently rate limited
                if ($this->isRateLimited()) {
                    $this->waitForRateLimit();
                }

                // Execute the operation
                $result = $operation();

                // Clear any rate limit flags on success
                $this->clearRateLimitFlag();

                // Log successful operation if it required retries
                if ($attempt > 0) {
                    // Log::info('Stripe operation succeeded after retries', [
                    //     'attempt' => $attempt + 1,
                    //     'context' => $context,
                    //     'operation_type' => $this->getOperationType($context)
                    // ]);
                }

                return $result;

            } catch (RateLimitException $e) {
                $lastException = $e;
                $attempt++;

                $this->handleRateLimitError($e, $attempt, $context);

                if ($attempt < $this->maxRetries) {
                    $delay = $this->calculateExponentialBackoff($attempt);
                    $this->setRateLimitFlag($delay);
                    
                    Log::warning('Stripe rate limit exceeded, implementing backoff', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'max_retries' => $this->maxRetries,
                        'context' => $context,
                        'rate_limit_type' => $e->getStripeCode()
                    ]);

                    sleep($delay);
                    continue;
                }

            } catch (AuthenticationException $e) {
                $this->handleAuthenticationError($e, $context);
                throw new Exception("Stripe authentication failed: {$e->getMessage()}", 401, $e);

            } catch (InvalidRequestException $e) {
                $this->handleInvalidRequestError($e, $context);
                
                // Don't retry invalid requests
                if ($enableFallback) {
                    return $this->getFallbackData($context);
                }
                
                throw new Exception("Invalid Stripe request: {$e->getMessage()}", 400, $e);

            } catch (CardException $e) {
                $this->handleCardError($e, $context);
                
                // Don't retry card errors
                throw new Exception("Card error: {$e->getMessage()}", 402, $e);

            } catch (ApiErrorException $e) {
                $lastException = $e;
                $attempt++;

                $this->handleGenericApiError($e, $attempt, $context);

                // Only retry on specific error types
                if ($this->shouldRetryApiError($e) && $attempt < $this->maxRetries) {
                    $delay = $this->calculateExponentialBackoff($attempt);
                    
                    Log::warning('Stripe API error, retrying', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'error_type' => $e->getStripeCode(),
                        'error_message' => $e->getMessage(),
                        'context' => $context
                    ]);

                    sleep($delay);
                    continue;
                }

                // If we can't retry or max retries reached
                if ($enableFallback) {
                    return $this->getFallbackData($context);
                }

                throw new Exception("Stripe API error: {$e->getMessage()}", 500, $e);

            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                $this->handleGenericError($e, $attempt, $context);

                // Only retry on network-related errors
                if ($this->shouldRetryGenericError($e) && $attempt < $this->maxRetries) {
                    $delay = $this->calculateExponentialBackoff($attempt);
                    
                    Log::warning('Generic error during Stripe operation, retrying', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'error_type' => get_class($e),
                        'error_message' => $e->getMessage(),
                        'context' => $context
                    ]);

                    sleep($delay);
                    continue;
                }

                // If we can't retry or max retries reached
                if ($enableFallback) {
                    return $this->getFallbackData($context);
                }

                throw $e;
            }
        }

        // If we've exhausted all retries
        Log::error('Stripe operation failed after all retries', [
            'max_retries' => $this->maxRetries,
            'final_error' => $lastException?->getMessage(),
            'context' => $context,
            'operation_type' => $this->getOperationType($context)
        ]);

        if ($enableFallback) {
            return $this->getFallbackData($context);
        }

        throw new Exception(
            "Stripe operation failed after {$this->maxRetries} attempts: " . 
            ($lastException?->getMessage() ?? 'Unknown error'), 
            500, 
            $lastException
        );
    }

    /**
     * Handle rate limit errors with detailed logging.
     * 
     * @param RateLimitException $e The rate limit exception
     * @param int $attempt Current attempt number
     * @param array $context Operation context
     */
    protected function handleRateLimitError(RateLimitException $e, int $attempt, array $context): void
    {
        Log::warning('Stripe rate limit exceeded', [
            'attempt' => $attempt,
            'error_code' => $e->getStripeCode(),
            'error_message' => $e->getMessage(),
            'context' => $context,
            'rate_limit_type' => $this->determineRateLimitType($e),
            'suggested_retry_after' => $this->extractRetryAfter($e),
            'timestamp' => now()->toISOString()
        ]);

        // Track rate limit incidents for monitoring
        $this->trackRateLimitIncident($e, $context);
    }

    /**
     * Handle authentication errors with security logging.
     * 
     * @param AuthenticationException $e The authentication exception
     * @param array $context Operation context
     */
    protected function handleAuthenticationError(AuthenticationException $e, array $context): void
    {
        Log::error('Stripe authentication failed - SECURITY ALERT', [
            'error_code' => $e->getStripeCode(),
            'error_message' => $e->getMessage(),
            'context' => $context,
            'tenant_id' => $this->getCurrentTenantId(),
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
            'severity' => 'CRITICAL'
        ]);

        // Track authentication failures for security monitoring
        $this->trackAuthenticationFailure($e, $context);
    }

    /**
     * Handle invalid request errors with detailed analysis.
     * 
     * @param InvalidRequestException $e The invalid request exception
     * @param array $context Operation context
     */
    protected function handleInvalidRequestError(InvalidRequestException $e, array $context): void
    {
        Log::error('Stripe invalid request error', [
            'error_code' => $e->getStripeCode(),
            'error_message' => $e->getMessage(),
            'error_param' => $e->getStripeParam(),
            'context' => $context,
            'request_data' => $this->sanitizeRequestData($context),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Handle card-related errors.
     * 
     * @param CardException $e The card exception
     * @param array $context Operation context
     */
    protected function handleCardError(CardException $e, array $context): void
    {
        Log::warning('Stripe card error', [
            'error_code' => $e->getStripeCode(),
            'error_message' => $e->getMessage(),
            'decline_code' => $e->getDeclineCode(),
            'context' => $context,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Handle generic API errors.
     * 
     * @param ApiErrorException $e The API exception
     * @param int $attempt Current attempt number
     * @param array $context Operation context
     */
    protected function handleGenericApiError(ApiErrorException $e, int $attempt, array $context): void
    {
        Log::error('Stripe API error', [
            'attempt' => $attempt,
            'error_code' => $e->getStripeCode(),
            'error_message' => $e->getMessage(),
            // 'error_type' => $e->getStripeParam(),
            'http_status' => $e->getHttpStatus(),
            'context' => $context,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Handle generic errors.
     * 
     * @param Exception $e The exception
     * @param int $attempt Current attempt number
     * @param array $context Operation context
     */
    protected function handleGenericError(Exception $e, int $attempt, array $context): void
    {
        Log::error('Generic error during Stripe operation', [
            'attempt' => $attempt,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'context' => $context,
            'stack_trace' => $e->getTraceAsString(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Calculate exponential backoff delay.
     * 
     * @param int $attempt Current attempt number
     * @return int Delay in seconds
     */
    protected function calculateExponentialBackoff(int $attempt): int
    {
        // Exponential backoff with jitter
        $delay = min(
            $this->baseDelay * pow(2, $attempt - 1) + rand(0, 1000) / 1000,
            $this->maxDelay
        );

        return (int) $delay;
    }

    /**
     * Check if we're currently rate limited.
     * 
     * @return bool
     */
    protected function isRateLimited(): bool
    {
        $cacheKey = $this->rateLimitCachePrefix . $this->getCurrentTenantId();
        return Cache::has($cacheKey);
    }

    /**
     * Set rate limit flag in cache.
     * 
     * @param int $delay Delay in seconds
     */
    protected function setRateLimitFlag(int $delay): void
    {
        $cacheKey = $this->rateLimitCachePrefix . $this->getCurrentTenantId();
        Cache::put($cacheKey, true, $delay);
    }

    /**
     * Clear rate limit flag from cache.
     */
    protected function clearRateLimitFlag(): void
    {
        $cacheKey = $this->rateLimitCachePrefix . $this->getCurrentTenantId();
        Cache::forget($cacheKey);
    }

    /**
     * Wait for rate limit to clear.
     */
    protected function waitForRateLimit(): void
    {
        $cacheKey = $this->rateLimitCachePrefix . $this->getCurrentTenantId();

        try {
            $ttl = Cache::getStore()->getRedis()->ttl($cacheKey);
        } catch (\Exception $e) {
            // Non-Redis cache store; skip TTL-based waiting
            return;
        }

        if ($ttl > 0) {
            // Log::info('Waiting for rate limit to clear', [
            //     'wait_seconds' => $ttl,
            //     'tenant_id' => $this->getCurrentTenantId()
            // ]);

            sleep(min($ttl, 60)); // Don't wait more than 60 seconds
        }
    }

    /**
     * Determine if an API error should be retried.
     * 
     * @param ApiErrorException $e The API exception
     * @return bool
     */
    protected function shouldRetryApiError(ApiErrorException $e): bool
    {
        $retryableCodes = [
            'api_connection_error',
            'api_error',
            'rate_limit',
            'temporary_unavailable'
        ];

        return in_array($e->getStripeCode(), $retryableCodes);
    }

    /**
     * Determine if a generic error should be retried.
     * 
     * @param Exception $e The exception
     * @return bool
     */
    protected function shouldRetryGenericError(Exception $e): bool
    {
        $retryableMessages = [
            'connection timeout',
            'connection refused',
            'network error',
            'temporary failure',
            'service unavailable'
        ];

        $message = strtolower($e->getMessage());
        
        foreach ($retryableMessages as $retryableMessage) {
            if (strpos($message, $retryableMessage) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get fallback data when API operations fail.
     * 
     * @param array $context Operation context
     * @return array|null
     */
    protected function getFallbackData(array $context): ?array
    {
        $operationType = $this->getOperationType($context);

        switch ($operationType) {
            case 'dispute_retrieve':
                return $this->getCachedDisputeData($context);
            
            case 'dispute_list':
                return $this->getCachedDisputeList($context);
            
            case 'evidence_submit':
                return ['status' => 'pending', 'message' => 'Evidence queued for submission'];
            
            default:
                Log::warning('No fallback data available for operation type', [
                    'operation_type' => $operationType,
                    'context' => $context
                ]);
                
                return [
                    'status' => 'unavailable',
                    'message' => 'Service temporarily unavailable. Please try again later.',
                    'fallback' => true,
                    'timestamp' => now()->toISOString()
                ];
        }
    }

    /**
     * Get cached dispute data as fallback.
     * 
     * @param array $context Operation context
     * @return array|null
     */
    protected function getCachedDisputeData(array $context): ?array
    {
        $disputeId = $context['dispute_id'] ?? null;
        
        if (!$disputeId) {
            return null;
        }

        // Try to get from local database
        try {
            $dispute = Dispute::where('stripe_dispute_id', $disputeId)->first();

            if ($dispute) {
                // Log::info('Using cached dispute data as fallback', [
                //     'dispute_id' => $disputeId,
                //     'local_id' => $dispute->id,
                //     'last_updated' => $dispute->updated_at
                // ]);

                return [
                    'id' => $dispute->stripe_dispute_id,
                    'amount' => $dispute->amount * 100, // Convert to cents
                    'currency' => $dispute->currency,
                    'reason' => $dispute->reason,
                    'status' => $dispute->status,
                    'evidence_due_by' => $dispute->evidence_due_by?->timestamp,
                    'fallback' => true,
                    'last_sync' => $dispute->updated_at->toISOString()
                ];
            }
        } catch (Exception $e) {
            Log::error('Failed to retrieve cached dispute data', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get cached dispute list as fallback.
     * 
     * @param array $context Operation context
     * @return array
     */
    protected function getCachedDisputeList(array $context): array
    {
        try {
            $disputes = Dispute::latest()->limit(100)->get();

            // Log::info('Using cached dispute list as fallback', [
            //     'count' => $disputes->count(),
            //     'tenant_id' => $this->getCurrentTenantId()
            // ]);

            return [
                'object' => 'list',
                'data' => $disputes->map(function ($dispute) {
                    return [
                        'id' => $dispute->stripe_dispute_id,
                        'amount' => $dispute->amount * 100,
                        'currency' => $dispute->currency,
                        'reason' => $dispute->reason,
                        'status' => $dispute->status,
                        'evidence_due_by' => $dispute->evidence_due_by?->timestamp,
                        'fallback' => true
                    ];
                })->toArray(),
                'has_more' => false,
                'fallback' => true,
                'timestamp' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Failed to retrieve cached dispute list', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->getCurrentTenantId()
            ]);

            return [
                'object' => 'list',
                'data' => [],
                'has_more' => false,
                'fallback' => true,
                'error' => 'Unable to retrieve cached data'
            ];
        }
    }

    /**
     * Get operation type from context.
     * 
     * @param array $context Operation context
     * @return string
     */
    protected function getOperationType(array $context): string
    {
        return $context['operation_type'] ?? 'unknown';
    }

    /**
     * Get current tenant ID.
     * 
     * @return string
     */
    protected function getCurrentTenantId(): string
    {
        return session('tenant_id', 'default');
    }

    /**
     * Determine rate limit type from exception.
     * 
     * @param RateLimitException $e The rate limit exception
     * @return string
     */
    protected function determineRateLimitType(RateLimitException $e): string
    {
        $message = strtolower($e->getMessage());
        
        if (strpos($message, 'read') !== false) {
            return 'read_rate_limit';
        } elseif (strpos($message, 'write') !== false) {
            return 'write_rate_limit';
        } else {
            return 'general_rate_limit';
        }
    }

    /**
     * Extract retry-after header from exception.
     * 
     * @param RateLimitException $e The rate limit exception
     * @return int|null
     */
    protected function extractRetryAfter(RateLimitException $e): ?int
    {
        // Try to extract retry-after from headers if available
        $headers = $e->getHttpHeaders() ?? [];
        
        if (isset($headers['Retry-After'])) {
            return (int) $headers['Retry-After'];
        }

        return null;
    }

    /**
     * Track rate limit incidents for monitoring.
     * 
     * @param RateLimitException $e The rate limit exception
     * @param array $context Operation context
     */
    protected function trackRateLimitIncident(RateLimitException $e, array $context): void
    {
        $cacheKey = 'stripe_rate_limit_incidents_' . now()->format('Y-m-d-H');
        $incidents = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $incidents + 1, 3600); // Store for 1 hour

        // Alert if too many incidents
        if ($incidents > 10) {
            Log::alert('High rate limit incident count detected', [
                'incidents_this_hour' => $incidents,
                'tenant_id' => $this->getCurrentTenantId(),
                'context' => $context
            ]);
        }
    }

    /**
     * Track authentication failures for security monitoring.
     * 
     * @param AuthenticationException $e The authentication exception
     * @param array $context Operation context
     */
    protected function trackAuthenticationFailure(AuthenticationException $e, array $context): void
    {
        $cacheKey = 'stripe_auth_failures_' . $this->getCurrentTenantId() . '_' . now()->format('Y-m-d');
        $failures = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $failures + 1, 86400); // Store for 24 hours

        // Alert on multiple failures
        if ($failures > 3) {
            Log::alert('Multiple Stripe authentication failures detected - SECURITY ALERT', [
                'failures_today' => $failures,
                'tenant_id' => $this->getCurrentTenantId(),
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'context' => $context
            ]);
        }
    }

    /**
     * Sanitize request data for logging.
     * 
     * @param array $context Operation context
     * @return array
     */
    protected function sanitizeRequestData(array $context): array
    {
        $sensitiveKeys = ['api_key', 'secret', 'token', 'password', 'card_number', 'cvv'];
        
        $sanitized = $context;
        
        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }

        return $sanitized;
    }
}