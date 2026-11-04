<?php

use App\Http\Controllers\API\V1\TourOperator\TourOperatorController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/check-is-affiliate/{id}', [TourOperatorController::class, 'checkIsAffiliate'])->name('check-is-affiliate');
    Route::post('/bookedby-info', [TourOperatorController::class, 'bookedbyInfo'])->name('bookedby-info');
    Route::post('/update-staff-profile/{id}', [TourOperatorController::class, 'updateStaffProfile'])->name('update-staff-profile');
	Route::post('save-build-number', [ TourOperatorController::class, 'saveBuildNumber' ])->name('save-build-number');
    Route::get('get-stripe-keys', [ TourOperatorController::class, 'getStripeKeys' ])->name('get-stripe-keys');
});
