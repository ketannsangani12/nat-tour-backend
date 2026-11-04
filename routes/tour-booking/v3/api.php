<?php

use App\Http\Controllers\API\V3\TourBooking\TourBookingController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('agenda-day-time-slots', [TourBookingController::class, 'dayTimeSlots'])->name('agenda-day-time-slots');
});
