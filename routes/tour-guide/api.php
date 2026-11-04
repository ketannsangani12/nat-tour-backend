<?php

use App\Http\Controllers\API\V1\TourGuide\TourGuideController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
	Route::post('send-tip-payment-link', [TourGuideController::class, 'sendTipPaymentLink'])->name('send-tip-payment-link');
	Route::post('register-internet-reader', [TourGuideController::class, 'registerInternetReader'])->name('register-internet-reader');
	Route::post('after-tip-payment', [TourGuideController::class, 'afterTipPayment'])->name('after-tip-payment');
	Route::post('create-tip-payment-intent', [TourGuideController::class, 'stripeCreateTipPaymentIntent'])->name('create-tip-payment-intent');
	Route::post('send-tap-to-pay-payment-mail', [TourGuideController::class, 'sendTapToPayPaymentMail'])->name('send-tap-to-pay-payment-mail');
	Route::post('after-tip-payment', [TourGuideController::class, 'afterTipPayment'])->name('after-tip-payment');
	Route::post('update-guide-status', [TourGuideController::class, 'updateGuideStatus'])->name('update-guide-status');
});
