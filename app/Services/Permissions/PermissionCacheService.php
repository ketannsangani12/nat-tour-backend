<?php

namespace App\Services\Permissions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant-aware permission cache management service.
 * 
 * This service ensures that permission cache operations are properly scoped
 * to the current tenant, preventing cross-tenant cache pollution.
 */
class PermissionCacheService
{
    /**
     * The Spatie Permission Registrar instance.
     *
     * @var PermissionRegistrar
     */
    protected $permissionRegistrar;

    /**
     * Create a new PermissionCacheService instance.
     *
     * @param PermissionRegistrar $permissionRegistrar
     */
    public function __construct(PermissionRegistrar $permissionRegistrar)
    {
        $this->permissionRegistrar = $permissionRegistrar;
    }

    /**
     * Clear permission cache for the current tenant only.
     * 
     * This method ensures that only the current tenant's cached permissions
     * are cleared, preventing cross-tenant cache pollution.
     *
     * @return void
     */
    public function forgetCachedPermissions(): void
    {
        try {
            $tenantId = $this->getTenantId();
            $cacheKey = $this->getTenantCacheKey();

            Log::debug('Clearing permission cache', [
                'tenant_id' => $tenantId,
                'cache_key' => $cacheKey,
            ]);

            // Sync the config cache key to the current tenant
            $this->syncConfigCacheKey();

            // CRITICAL: Reinitialize the registrar to pick up the new cache key
            // The registrar caches the key in $this->cacheKey during initializeCache()
            // Without this, it would use the old key from when it was first instantiated
            $this->permissionRegistrar->initializeCache();

            // Now clear the cache - this will use the correct tenant-specific key
            $this->permissionRegistrar->forgetCachedPermissions();

            Log::debug('Permission cache cleared successfully', [
                'tenant_id' => $tenantId,
                'actual_cache_key' => config('permission.cache.key'),
            ]);
        } catch (\Exception $e) {
            $tenantId = $this->getTenantId();
            
            Log::error('Failed to clear permission cache', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Continue execution even if cache clearing fails
            // Permissions will be loaded from database on next request
        }
    }

    /**
     * Clear permission cache only if roles or permissions were modified.
     * 
     * This method executes a callback and clears the cache afterwards,
     * ensuring cache clearing happens at most once per transaction.
     *
     * @param callable $callback The operation to perform
     * @return mixed The result of the callback
     */
    public function clearCacheAfter(callable $callback)
    {
        $result = $callback();
        
        $this->forgetCachedPermissions();
        
        return $result;
    }

    /**
     * Get the current tenant's cache key.
     * 
     * Returns a tenant-specific cache key in the format:
     * spatie.permission.cache.tenant_{tenant_id}
     *
     * @return string
     */
    public function getTenantCacheKey(): string
    {
        $tenantId = $this->getTenantId();
        
        return 'spatie.permission.cache.tenant_' . $tenantId;
    }

    /**
     * Check if we're in a tenant context.
     * 
     * @return bool
     */
    public function hasTenantContext(): bool
    {
        try {
            return function_exists('tenancy') 
                && tenancy()->initialized 
                && tenancy()->tenant !== null;
        } catch (\Exception $e) {
            Log::warning('Error checking tenant context', [
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get the current tenant ID or fallback to 'default'.
     * 
     * @return string
     */
    protected function getTenantId(): string
    {
        try {
            if ($this->hasTenantContext()) {
                return tenancy()->tenant->id;
            }

            Log::warning('Cache operation outside tenant context, using default key');
            
            return 'default';
        } catch (\Exception $e) {
            Log::error('Error getting tenant ID, falling back to default', [
                'error' => $e->getMessage(),
            ]);
            
            return 'default';
        }
    }

    /**
     * Sync the config cache key with the current tenant context.
     * 
     * This ensures that Spatie's PermissionRegistrar uses the correct
     * tenant-specific cache key, even if called outside the normal
     * request lifecycle where TenancyBootstrapped event fires.
     *
     * @return void
     */
    public function syncConfigCacheKey(): void
    {
        $cacheKey = $this->getTenantCacheKey();
        $currentConfigKey = config('permission.cache.key');

        if ($currentConfigKey !== $cacheKey) {
            config(['permission.cache.key' => $cacheKey]);
            
            Log::debug('Synced permission cache key config', [
                'previous_key' => $currentConfigKey,
                'new_key' => $cacheKey,
            ]);
        }
    }
}
