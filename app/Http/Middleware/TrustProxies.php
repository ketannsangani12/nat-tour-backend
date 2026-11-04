<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    protected $proxies;

    /**
     * Bootstrap the middleware.
     * Trust proxies based on environment or TRUST_PROXIES env variable.
     */
    public function __construct()
    {
        if (app()->environment('local', 'development', 'staging')) {
            $this->proxies = '*';
        } elseif (app()->environment('production')) {
            $this->proxies = '10.0.0.0/16';
        }
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_AWS_ELB;
}
