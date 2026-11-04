<?php

use App\Http\Controllers\API\V1\Octo\OctoApiController;
use Illuminate\Support\Facades\Route;

/* OCTO API Routes */

Route::prefix('octo')->middleware(['octo-ip', 'octo-auth', 'throttle:60,1'])->group(function () {

    // GET /supplier - Returns supplier details
    Route::get('supplier', [OctoApiController::class, 'getSupplier']);

    // GET /products - Returns the list of products
    Route::get('products', [OctoApiController::class, 'getProductList']);

    // GET /products/{productId} - Returns a single product by ID
    Route::get('products/{productId}', [OctoApiController::class, 'getProductById']);

    // POST /availability/calendar - Returns date-level availability for a product
    Route::post('availability/calendar', [OctoApiController::class, 'getAvailabilityCalendar']);

    // POST /availability - Returns slot-level availability for a product
    Route::post('availability', [OctoApiController::class, 'getAvailabilityCheck']);

    // GET /bookings - List bookings by resellerReference or supplierReference
    Route::get('bookings', [OctoApiController::class, 'listBookings']);

    // POST /bookings - Create a booking on hold
    Route::post('bookings', [OctoApiController::class, 'createBooking']);

    // POST /bookings/{uuid}/confirm - Confirm a booking on hold
    Route::post('bookings/{uuid}/confirm', [OctoApiController::class, 'confirmBooking']);

    // GET /bookings/{uuid} - Get a single booking
    Route::get('bookings/{uuid}', [OctoApiController::class, 'getBooking']);

    // PATCH /bookings/{uuid} - Update a booking (pax, slot, contact, notes, extend hold)
    Route::patch('bookings/{uuid}', [OctoApiController::class, 'updateBooking']);

    // POST /bookings/{uuid}/extend - Extend the expiration of an ON_HOLD booking
    Route::post('bookings/{uuid}/extend', [OctoApiController::class, 'extendBooking']);

    // POST /bookings/{uuid}/cancel - Cancel a booking
    Route::post('bookings/{uuid}/cancel', [OctoApiController::class, 'cancelBooking']);

    // POST /notifications/subscriptions - OCTO spec alias for createWebhook
    Route::post('notifications/subscriptions', [OctoApiController::class, 'createWebhook']);

    // GET /notifications/subscriptions - List all notification subscriptions
    Route::get('notifications/subscriptions', [OctoApiController::class, 'listWebhooks']);

    // GET /notifications/subscriptions/{id} - Get a single notification subscription
    Route::get('notifications/subscriptions/{id}', [OctoApiController::class, 'getWebhook']);

    // DELETE /notifications/subscriptions/{id} - Delete a notification subscription
    Route::delete('notifications/subscriptions/{id}', [OctoApiController::class, 'deleteWebhook']);

    // PATCH /notifications/subscriptions/{id} - Update a notification subscription
    Route::patch('notifications/subscriptions/{id}', [OctoApiController::class, 'updateWebhook']);

    // POST /notifications/subscriptions/{id}/ping - Test webhook delivery (non-spec convenience)
    Route::post('notifications/subscriptions/{id}/ping', [OctoApiController::class, 'pingWebhook']);
});
/* OCTO API Routes End */
