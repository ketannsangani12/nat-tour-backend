<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\CheckUserStatus;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;

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

Route::as('customer.')->middleware(['auth:customer', 'prevent-back-history', 'check-user-status', 'check-customer'])->group(function () {
	Route::post('logout', [AuthController::class, 'logout'])->name('logout')->withoutMiddleware([CheckUserStatus::class]);

	Route::get('tour-bookings', [CustomerController::class, 'tourBookings'])->name('tour-bookings');
	Route::post('tour-bookings', [CustomerController::class, 'tourBookingsAHR'])->name('tour-bookings-ahr');
	Route::post('tour-booking-cancel/{id}', [CustomerController::class, 'tourBookingCancel'])->name('tour-booking-cancel');
});
