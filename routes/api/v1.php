<?php

use App\Http\Controllers\API\V1\User\AuthController;
use Illuminate\Support\Facades\Route;
use App\Constants\Constants;

Route::middleware('guest')->group(function () {
    // Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::get('/check-tenant-exist/{tenant_id}/{tenant_info?}', [ AuthController::class, 'checkTenantExist' ])->name('check-tenant-exist');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/password/forgot', [AuthController::class, 'forgot'])->name('password.forgot');
    Route::post('/password/reset', [AuthController::class, 'reset'])->name('password.reset');

    require __DIR__ . '/../trip-advisor/api.php';
    require __DIR__ . '/../get-your-guide/api.php';
    require __DIR__ . '/../bokun/api.php';
    require __DIR__ . '/../octo/api.php';

});

Route::middleware('partner-api')->group(function () {
    require __DIR__ . '/../partner-api/api.php';
});

require __DIR__ . '/../user/api.php';
require __DIR__ . '/../tour-booking/api.php';
require __DIR__ . '/../tour-operator/api.php';
require __DIR__ . '/../tour-slot/api.php';
require __DIR__ . '/../customer/api.php';
require __DIR__ . '/../tour-package/api.php';
require __DIR__ . '/../tour-report/api.php';
require __DIR__ . '/../tour-checkin/api.php';
require __DIR__ . '/../coordinator/api.php';
require __DIR__ . '/../tour-guide/api.php';
require __DIR__ . '/../image/api.php';


Route::any('{segment}', function () {
    return response()->json([
        'code' => Constants::HTTP_BAD_REQUEST,
        'message' => __('general.bad_request'),
    ], Constants::HTTP_BAD_REQUEST);
})->where('segment', '.*');
