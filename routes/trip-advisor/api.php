<?php

use App\Http\Controllers\API\V1\TripAdvisorAPI\TripAdvisorAPIController;
use Illuminate\Support\Facades\Route;

/* TripAdvisor API Routes Start */

// Tour list API
Route::post('tours', [TripAdvisorAPIController::class, 'tourList']);

// Batch availability API
Route::post('batch-availability', [TripAdvisorAPIController::class, 'batchAvailability']);

// Real-time availability API
Route::post('real-time-availability', [TripAdvisorAPIController::class, 'realTimeAvailability']);

// Booking API
Route::post('generate-booking', [TripAdvisorAPIController::class, 'createBooking']);

// Booking cancellation API
Route::post('cancel-booking', [TripAdvisorAPIController::class, 'cancelBooking']);

// Booking amendment API
Route::post('amendment-booking', [TripAdvisorAPIController::class, 'amendmentBooking']);

/* TripAdvisor API Routes End */