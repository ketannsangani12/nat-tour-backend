<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V2\TourPackageController;
use App\Http\Controllers\API\V2\TourSlotMasterController;
use App\Http\Controllers\API\V2\TourBookingController;
use App\Http\Controllers\API\V2\PaymentController;
use App\Http\Controllers\API\V2\PreBookingController;
use App\Http\Controllers\API\V2\ReceiptController;
use App\Http\Controllers\API\V2\SettingController;

/* API TourOperator Logo */

Route::get('/tour-operator-logo/{tour_operator_id}', [TourPackageController::class, 'tourOperatorLogo'])->name('pkgs.tour_operator_logo');

/* API TourPackage */
Route::get('/tour-package/{date}/{tour_operator_id}/{package_id?}/{affiliate_id?}/{combo_package_ids?}/{with_rate_groups?}/{slot_time_id?}', [TourPackageController::class, 'getPackages'])->name('pkgs.get_index');
Route::get('/package/custom/form/{id}', [TourPackageController::class, 'customForm'])->name('package-custom-form');
Route::get('/package/get-addon-form/{id}/{builder?}', [TourPackageController::class, 'getAddonForm'])->name('get-addon-form');

/* API TourSlotMaster */
Route::get('get-color-date/{month}/{year}/{tourPackageId}/{affiliateId?}', [TourSlotMasterController::class, 'getColorDate'])->name('get-color-date');
Route::get('get-color-date-old/{month}/{year}/{tourPackageId}/{affiliateId?}', [TourSlotMasterController::class, 'getColorDateOld'])->name('get-color-date-old');
Route::get('/tour-slot/{date}/{package_id}/{affiliate_id?}', [TourSlotMasterController::class, 'getSlotTimes'])->name('slot-times');

/* API TourBooking */
Route::post('/package-booking-tour', [TourBookingController::class, 'packageBookingTour'])->name('package-booking-tour');
Route::post('/package-booking-3ds-payment', [TourBookingController::class, 'packageBooking3dsPayment'])->name('package-booking-3ds-payment');
Route::post('/available-seats', [TourBookingController::class, 'availableSeats'])->name('available-seats');
Route::post('/bulk-check-available-seats', [TourBookingController::class, 'bulkCheckAvailableSeats'])->name('bulk-check-available-seats');
Route::post('/package-booking-confirmed', [TourBookingController::class, 'packageBookingConfirmed'])->name('package-booking-confirmed');
Route::get('/apply-coupon/{packageId}/{timeSlotId}/{code}', [TourBookingController::class, 'applyCoupon'])->name('apply-coupon');

/* API for new stripe integration */
Route::post('/create-payment-intent', [TourBookingController::class, 'createPaymentIntent'])->name('create-payment-intent');
Route::post('/prepare-to-save-card-on-payment-intent', [PaymentController::class, 'prepareToSaveCardOnPaymentIntent'])->name('prepare-to-save-card-on-payment-intent');
Route::post('/verify-payment', [TourBookingController::class, 'verifyPayment'])->name('verify-payment');

/* API to store pre booking data */
Route::post('create-pre-booking', [PreBookingController::class, 'createPreBooking'])->name('create-pre-booking');
Route::post('delete-pre-booking', [PreBookingController::class, 'deletePreBooking'])->name('delete-pre-booking');

/* API Receipt Print */
Route::middleware('auth:sanctum')->get('receipt/{booking_id}', [ReceiptController::class, 'getReceiptData'])->name('receipt');

/* API for get Settings */
Route::get('/settings', [SettingController::class, 'getSettings'])->name('settings-get');