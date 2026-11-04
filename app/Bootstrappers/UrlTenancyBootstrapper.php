<?php

namespace App\Bootstrappers;

use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;

class UrlTenancyBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant)
    {
        // In a queue/console context there is no HTTP request, so route() falls
        // back to config('app.url'). Force it to the tenant's primary domain so
        // that MailTracker tracking links use the correct tenant subdomain.
        if (app()->runningInConsole()) {
            $domain = $tenant->domains()->first()?->domain;

            if ($domain) {
                URL::forceRootUrl('https://' . $domain);
            }
        }
    }

    public function revert()
    {
        if (app()->runningInConsole()) {
            URL::forceRootUrl(null);
        }
    }
}
