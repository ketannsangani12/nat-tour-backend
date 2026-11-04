<?php

use App\Http\Controllers\API\V1\PartnerApi\PartnerAPIController;
use App\Http\Middleware\PartnerApiValidateKey;
use Illuminate\Support\Facades\Route;

/**
 * These routes allow Affiliates Partners to connect and create bookings on a given tentant
 *
*/

Route::prefix('partner-api')->as('partner-api.')->group(function () {
	// Deployment version check (no API key required, so it can be curled easily)
	Route::get('version', [PartnerAPIController::class, 'version'])
		->name('version')
		->withoutMiddleware(PartnerApiValidateKey::class);

	// API Index this will display all tours for the tenant
	Route::post('api-info', [PartnerAPIController::class, 'apiInfo'])->name('apiInfo');
	
	// Product List API
	Route::post('tour-operators', [PartnerAPIController::class, 'tourOperators'])->name('tourOperators');

	// Tour list API
	Route::post('tours', [PartnerAPIController::class, 'tourList'])->name('tourList');

	// Rate groups API
	Route::post('rate-groups', [PartnerAPIController::class, 'rateGroups'])->name('rateGroups');
	
	// Time slots API
	Route::post('time-slots', [PartnerAPIController::class, 'timeSlots'])->name('timeSlots');

	// Bookings List API
	Route::post('booking-list', [PartnerAPIController::class, 'bookingList'])->name('bookingList');

	// Batch availability API
	Route::post('batch-availability', [PartnerAPIController::class, 'batchAvailability'])->name('batchAvailability');

	// Real-time availability API
	Route::post('real-time-availability', [PartnerAPIController::class, 'realTimeAvailability'])->name('realTimeAvailability');

	// Booking API
	Route::post('create-booking', [PartnerAPIController::class, 'createBooking'])->name('createBooking');

	// Booking cancellation API
	Route::post('cancel-booking', [PartnerAPIController::class, 'cancelBooking'])->name('cancelBooking');

	// ── OCTO-shaped cross-tenant endpoints (identical names/inputs/responses to OCTO) ──
	// Supplier
	Route::get('supplier', [PartnerAPIController::class, 'getSupplier'])->name('supplier');

	// Products
	Route::get('products', [PartnerAPIController::class, 'getProductList'])->name('products');
	Route::get('products/{productId}', [PartnerAPIController::class, 'getProductById'])->name('products.show');

	// Availability
	Route::post('availability/calendar', [PartnerAPIController::class, 'availabilityCalendar'])->name('availability.calendar');
	Route::post('availability', [PartnerAPIController::class, 'availabilityCheck'])->name('availability');

	// Bookings — lookup, reservation (hold), confirmation, cancellation
	Route::get('bookings', [PartnerAPIController::class, 'getBookingList'])->name('bookings.index');
	Route::get('bookings/{uuid}', [PartnerAPIController::class, 'getBookingByUuid'])->name('bookings.show');
	Route::post('bookings', [PartnerAPIController::class, 'bookingReservation'])->name('bookings.create');
	Route::post('bookings/{uuid}/confirm', [PartnerAPIController::class, 'bookingConfirmation'])->name('bookings.confirm');
	Route::post('bookings/{uuid}/cancel', [PartnerAPIController::class, 'bookingCancellation'])->name('bookings.cancel');

});









