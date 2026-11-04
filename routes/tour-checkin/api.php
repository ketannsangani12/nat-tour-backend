<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\TourCheckIn\TourCheckInController;

Route::middleware('tour-checkin-api')->group(function () {

    Route::prefix('self-check-in')->group(function () {
        Route::get('/packages/{id}', [TourCheckInController::class, 'packages']);
        Route::get('/booking/{bookingId}', [TourCheckInController::class, 'booking']);
        Route::get('/waiver', [TourCheckInController::class, 'waiver']);
        Route::post('/sign-waiver', [TourCheckInController::class, 'signWaiver']);
        Route::post('/checkin', [TourCheckInController::class, 'checkin']);
       
    });


// Tour list API


// // Batch availability API
// Route::post('batch-availability', [TripAdvisorAPIController::class, 'batchAvailability']);

// // Real-time availability API
// Route::post('real-time-availability', [TripAdvisorAPIController::class, 'realTimeAvailability']);

// // Booking API
// Route::post('generate-booking', [TripAdvisorAPIController::class, 'createBooking']);

// // Booking cancellation API
// Route::post('cancel-booking', [TripAdvisorAPIController::class, 'cancelBooking']);
});

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::prefix('check-in')->group(function () {
        Route::get('/packages', [TourCheckInController::class, 'packages']);
        Route::get('/booking/{bookingGroupId}/{tourPackageId}', [TourCheckInController::class, 'getBooking']);
        Route::post('/redeem-ticket', [TourCheckInController::class, 'redeemTickets']);
    });
});