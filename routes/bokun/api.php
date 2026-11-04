<?php

use App\Http\Controllers\API\V1\Bokun\BokunApiController;
use Illuminate\Support\Facades\Route;

/* Bokun Channel Manager API Routes Start */
Route::prefix('bokun')->group(function () {
    Route::get('plugin/definition', [BokunApiController::class, 'pluginDefinition']);

    Route::middleware(['bokun', 'bokun_identify_tenant'])->group(function () {
        Route::post('product/search', [BokunApiController::class, 'searchProduct']);

        Route::post('product/getById', [BokunApiController::class, 'productDetailsById']);

        // A set of product ids provided, return their availability over given date range
        Route::post('product/getAvailable', [BokunApiController::class, 'productGetAvailable']);

        // Get availability of a particular product over a date range
        Route::post('product/getAvailability', [BokunApiController::class, 'getProductAvailability']);

        Route::post('booking/reserve', [BokunApiController::class, 'bookingReservation']);

        Route::post('booking/cancelReserve', [BokunApiController::class, 'bookingCancelReservation']);

        Route::post('booking/confirm', [BokunApiController::class, 'bookingConfirmation']);

        Route::post('booking/cancel', [BokunApiController::class, 'bookingCancellation']);

        Route::post('booking/amend', [BokunApiController::class, 'bookingAmendment']);
    });
});
/* Bokun Channel Manager API Routes End */