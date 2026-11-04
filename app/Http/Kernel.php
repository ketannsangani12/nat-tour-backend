<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \App\Http\Middleware\BlockDirectIpAccess::class,
        //\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
        'partner-api' => [
            \App\Http\Middleware\PartnerApiValidateKey::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'prevent-back-history' => \App\Http\Middleware\PreventBackHistory::class,
        'check-user-status' => \App\Http\Middleware\CheckUserStatus::class,
        'check-admin' => \App\Http\Middleware\CheckAdmin::class,
        'check-customer' => \App\Http\Middleware\CheckCustomer::class,
        'check-tour-operator-staff' => \App\Http\Middleware\CheckTourOperatorStaff::class,
        'validate-broadcast-listening-requests' => \App\Http\Middleware\ValidateBroadcastListeningRequests::class,
        'xml' => \Mtownsend\RequestXml\Middleware\XmlRequest::class,
        'tour-checkin-api' => \App\Http\Middleware\TourCheckInApiMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'get-your-guide-auth' => \App\Http\Middleware\GetYourGuideBasicAuthentication::class,
        'bokun' => \App\Http\Middleware\BokunAuthentication::class,
        'octo-auth' => \App\Http\Middleware\OctoAuthentication::class,
        'octo-ip'   => \App\Http\Middleware\OctoIpWhitelist::class,
        'bokun_identify_tenant' => \App\Http\Middleware\BokunIdentifyTenant::class,
        'swagger.auth' => \App\Http\Middleware\SwaggerAuth::class,
        'maintenance-mode' => \App\Http\Middleware\CheckMaintenanceMode::class,
        'dispute-security' => \App\Http\Middleware\DisputeSecurityMiddleware::class,
    ];
}
