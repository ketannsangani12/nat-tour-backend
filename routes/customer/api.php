<?php

use App\Http\Controllers\API\V1\Customer\CustomerController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/customer-update/{id}', [CustomerController::class, 'updateByAdminOrTourOp'])->name('customer-update');
    Route::post('/customers', [CustomerController::class, 'customers'])->name('customers');
    Route::get('/get-liability-waiver/{bookingId}/{touristNum}/{isAdult}', [CustomerController::class, 'getLiabilityWaiver'])->name('get-liability-waiver');
    Route::post('/update-liability-waiver/{bookingId}', [CustomerController::class, 'updateLiabilityWaiver'])->name('update-liability-waiver');
});
