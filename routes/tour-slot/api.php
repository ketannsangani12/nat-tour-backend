<?php

use App\Http\Controllers\API\V1\TourSlot\TourSlotController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/get-tour-slot-times-by-date', [TourSlotController::class, 'getTourSlotTimesByDate'])->name('get-tour-slot-times-by-date');
    Route::get('/get-tour-slot-time-bookings-info/{timeSlotId}', [TourSlotController::class, 'timeSlotBookingsInfo'])->name('get-tour-slot-time-bookings-info');
    Route::post('/lower-antelope-canyon-closure-email', [TourSlotController::class, 'lowerAntelopeCanyonClosureEmail'])->name('lower-antelope-canyon-closure-email');
    Route::post('get-tour-rates-by-slot', [TourSlotController::class, 'getTourRatesBySlot'])->name('get-tour-rates-by-slot');
});
