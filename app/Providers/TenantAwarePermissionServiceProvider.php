<?php

namespace App\Providers;

use App\Services\Permissions\PermissionCacheService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Service provider for tenant-aware permission caching.
 * 
 * This provider registers the PermissionCacheService and validates
 * that the cache configuration is properly set up for multi-tenancy.
 */
class TenantAwarePermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register PermissionCacheService as a singleton
        $this->app->singleton(PermissionCacheService::class, function ($app) {
            return new PermissionCacheService(
                $app->make(PermissionRegistrar::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->validateCacheConfiguration();
    }

    /**
     * Validate that the permission cache configuration is tenant-aware.
     *
     * @return void
     */
    protected function validateCacheConfiguration(): void
    {
        try {
            // Validate permission cache key includes tenant context
            $cacheKey = config('permission.cache.key');
            
            if (!$this->cacheKeyIncludesTenantContext($cacheKey)) {
                Log::warning('Permission cache key does not include tenant context', [
                    'cache_key' => $cacheKey,
                    'expected_pattern' => 'spatie.permission.cache.tenant_*',
                    'recommendation' => 'Update config/permission.php cache.key to include tenant identifier',
                ]);
            }

            // Validate CacheTenancyBootstrapper is enabled
            $bootstrappers = config('tenancy.bootstrappers', []);
            
            if (!$this->hasCacheTenancyBootstrapper($bootstrappers)) {
                Log::warning('CacheTenancyBootstrapper is not enabled in tenancy config', [
                    'bootstrappers' => $bootstrappers,
                    'recommendation' => 'Add Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class to config/tenancy.php bootstrappers array',
                ]);
            }

            // Validate cache tagging is configured
            $cacheTagBase = config('tenancy.cache.tag_base');
            
            if (empty($cacheTagBase)) {
                Log::warning('Cache tag base is not configured in tenancy config', [
                    'tag_base' => $cacheTagBase,
                    'recommendation' => 'Set tenancy.cache.tag_base in config/tenancy.php (e.g., "tenant_")',
                ]);
            }

            // No success logging - validation runs on every request
            // Only log warnings/errors above when there are actual issues
        } catch (\Exception $e) {
            Log::error('Error validating permission cache configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if the cache key includes tenant context.
     *
     * Note: Since the cache key is now set dynamically by SetTenantPermissionCacheKey
     * listener after tenancy bootstraps, we accept the base key 'spatie.permission.cache'
     * as valid during early bootstrap.
     *
     * @param string|null $cacheKey
     * @return bool
     */
    protected function cacheKeyIncludesTenantContext(?string $cacheKey): bool
    {
        if (empty($cacheKey)) {
            return false;
        }

        // Accept the base key - it will be updated by SetTenantPermissionCacheKey listener
        // after tenancy is bootstrapped
        if ($cacheKey === 'spatie.permission.cache') {
            return true;
        }

        // Check if the cache key contains 'tenant' (for when it's already been set)
        return str_contains($cacheKey, 'tenant');
    }

    /**
     * Check if CacheTenancyBootstrapper is in the bootstrappers list.
     *
     * @param array $bootstrappers
     * @return bool
     */
    protected function hasCacheTenancyBootstrapper(array $bootstrappers): bool
    {
        foreach ($bootstrappers as $bootstrapper) {
            if (str_contains($bootstrapper, 'CacheTenancyBootstrapper')) {
                return true;
            }
        }

        return false;
    }
}
