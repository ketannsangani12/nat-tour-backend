<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

/*
|--------------------------------------------------------------------------
| Central Global Routes
|--------------------------------------------------------------------------
*/
Route::get('/email/n', function () {
    return redirect(request()->l);
})->name('mailTracker_n');
Route::get('check-tenant-exist/{tenant_id}/{tenant_info?}', [CustomerController::class, 'checkTenantExist'])->name('check-tenant-exist');

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('api')->middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'maintenance-mode',
])->group(function () {
    require __DIR__ . '/api.php';
});

/*
|--------------------------------------------------------------------------
| Tenant Web Routes
|--------------------------------------------------------------------------
*/
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'maintenance-mode',
])->group(function () {
    require __DIR__ . '/web.php';
});
