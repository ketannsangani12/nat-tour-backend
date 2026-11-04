<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// NEVER MODIFY THIS FILE WITHOUT PERMISSION OF DEVAT
Route::prefix('v2')->name('api.')->middleware('guest')->group(function () {
    require __DIR__ . '/frontend/api.php';
});

// V1 Routes App Routes
Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api/v1.php'));

// V3 Routes
Route::prefix('v3')->name('api.v3.')->group(base_path('routes/api/v3.php'));

// V4 Routes
Route::prefix('v4')->name('api.v4.')->group(base_path('routes/api/v4.php'));

Route::get('unauthorized', fn() => response()->json([
    'code' => Response::HTTP_UNAUTHORIZED,
    'message' => __('general.unauthorized'),
], Response::HTTP_UNAUTHORIZED))->name('unauthorized');
