<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class DisputeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/dispute.php', 'dispute'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register permissions
        $this->registerPermissions();

        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/dispute.php' => config_path('dispute.php'),
            ], 'dispute-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/tenant' => database_path('migrations/tenant'),
            ], 'dispute-migrations');
        }
    }

    /**
     * Register dispute management permissions.
     */
    protected function registerPermissions(): void
    {
        $permissions = config('dispute.permissions', []);

        foreach ($permissions as $permission => $description) {
            Gate::define($permission, function ($user) use ($permission) {
                // Check if user has the permission using Spatie Permission package
                if (method_exists($user, 'hasPermissionTo')) {
                    return $user->hasPermissionTo($permission);
                }

                // Fallback for users without Spatie Permission
                return false;
            });
        }
    }
}