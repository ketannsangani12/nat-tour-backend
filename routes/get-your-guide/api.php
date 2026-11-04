<?php

use App\Http\Controllers\API\V1\TripAdvisorAPI\GetYourGuideAPIController;
use Illuminate\Support\Facades\Route;

/* Get Your Guide API Routes Start */
Route::middleware(['get-your-guide-auth'])->group(function () {
    Route::prefix('get-your-guide/1/')->group(function () {
        /* /1/ is a constant representing the version of GetYourGuide Supplier API and is mandatory as part of the URL */

        // Packages/Products List
        Route::get('suppliers/{supplierId}/products', [GetYourGuideAPIController::class, 'packageList']);

        // Product Details
        Route::get('products/{productId}', [GetYourGuideAPIController::class, 'packageDetails']);

        // Product Details
        Route::get('products/{productId}/pricing-categories', [GetYourGuideAPIController::class, 'packagePricing']);

        // Product Availability
        Route::get('get-availabilities', [GetYourGuideAPIController::class, 'packageAvailability']);

        // Reservation
        Route::post('reserve', [GetYourGuideAPIController::class, 'packageReservation']);

        // Cancel Reservation
        Route::post('cancel-reservation', [GetYourGuideAPIController::class, 'packageCancelReservation']);

        // Booking Creation
        Route::post('book', [GetYourGuideAPIController::class, 'createBooking']);

        // Booking Cancellation
        Route::post('cancel-booking', [GetYourGuideAPIController::class, 'cancelBooking']);

        // Notification
        Route::post('notify', [GetYourGuideAPIController::class, 'notification']);
    });
});
/* Get Your Guide API Routes End */