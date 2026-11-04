<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Common\TourSlotController;
use App\Http\Controllers\Common\TourBookingController;
use App\Http\Controllers\Common\PosController;
use App\Http\Controllers\Common\StripeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('pos', [PosController::class, 'initPos'])->name('pos');
Route::prefix('pos')->as('pos.')->group(function () {
	Route::post('get-packages/{categoryId}', [PosController::class, 'getPackages'])->name('get-packages');
	Route::post('validate-cart', [PosController::class, 'validateCart'])->name('validate-cart');
	Route::post('book-tours-after-capture', [PosController::class, 'bookToursAfterCapture'])->name('book-tour-after-capture');
	Route::post('manual-pin-check', [PosController::class, 'manualPinCheck'])->name('manual-pin-check');
	Route::post('create-bookings', [PosController::class, 'createBookings'])->name('create-bookings');
	Route::get('order/{orderId}', [PosController::class, 'order'])->name('order');
	Route::get('print-receipt/{orderId}', [PosController::class, 'printReceipt'])->name('print-receipt');
	Route::post('send-receipt/{orderId}', [PosController::class, 'sendReceipt'])->name('send-receipt');
});

Route::post('get-tour-slot-times-by-date', [TourSlotController::class, 'getTourSlotTimesByDate'])->name('tour-operator.get-tour-slot-times-by-date');
Route::post('get-tour-rates-by-slot', [TourSlotController::class, 'getTourRatesBySlot'])->name('tour-operator.get-tour-rates-by-slot');
Route::prefix('stripe')->as('stripe.')->group(function () {
	Route::post('create-payment-intent-pos', [StripeController::class, 'createStripePaymentIntentForPos'])->name('create-payment-intent-pos');
});
Route::prefix('booking')->as('booking.')->group(function () {
	Route::post('check-overbooking', [TourBookingController::class, 'checkOverbooking'])->name('check-overbooking');
});
