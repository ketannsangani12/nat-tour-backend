<?php

use App\Http\Controllers\API\V4\Network\NetworkController;
use Illuminate\Support\Facades\Route;

Route::name('network.')->group(function () {
    Route::get('tenants', [NetworkController::class, 'getTenants'])->name('tenants');
    Route::get('packages', [NetworkController::class, 'getPackages'])->name('packages');
    Route::get('package/{tenant_id}/{package_id}', [NetworkController::class, 'getPackageDetails'])->name('package.details');
    Route::post('time-slots/{tenant_id}/{package_id}', [NetworkController::class, 'getTimeSlots'])->name('time-slots');
    Route::post('rate-groups/{tenant_id}/{package_id}', [NetworkController::class, 'getRateGroups'])->name('rate-groups');
    Route::get('custom-form/{tenant_id}/{package_id}', [NetworkController::class, 'getCustomForm'])->name('custom-form');
    Route::post('set-coupon/{tenant_id}/{package_id}', [NetworkController::class, 'setCoupon'])->name('set-coupon');
    Route::post('create-payment-intent', [NetworkController::class, 'createPaymentIntent'])->name('create-payment-intent');
    Route::post('check-availability', [NetworkController::class, 'checkAvailability'])->name('check-availability');
    Route::post('create-bookings', [NetworkController::class, 'createBookings'])->name('create-bookings');
});
