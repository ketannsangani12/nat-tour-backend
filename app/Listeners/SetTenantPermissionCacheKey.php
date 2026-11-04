<?php

namespace App\Listeners;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyBootstrapped;

/**
 * Sets the Spatie Permission cache key after tenancy is bootstrapped.
 * 
 * This listener solves the problem where config/permission.php is loaded
 * at bootstrap time before tenancy is initialized, causing the cache key
 * to always resolve to 'default' regardless of the actual tenant.
 */
class SetTenantPermissionCacheKey
{
    /**
     * Handle the event.
     *
     * @param TenancyBootstrapped $event
     * @return void
     */
    public function handle(TenancyBootstrapped $event): void
    {
        $tenantId = $event->tenancy->tenant->id ?? 'default';
        $cacheKey = 'spatie.permission.cache.tenant_' . $tenantId;

        // Update the config at runtime so Spatie uses the correct tenant-specific key
        config(['permission.cache.key' => $cacheKey]);

        // CRITICAL: Reinitialize the PermissionRegistrar to pick up the new cache key
        // The registrar caches $this->cacheKey during construction/initializeCache()
        // Without this, it continues using the old key, causing all tenants to share
        // the same cache entry in Redis
        app(PermissionRegistrar::class)->initializeCache();
    }
}
