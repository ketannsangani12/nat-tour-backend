<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AmazonController;
use App\Http\Controllers\ScriptController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BulkEmailController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TruncateTablesController;

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\NetworkController;

use App\Http\Controllers\Common\TourBookingController;
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

Route::get('/',  [AuthController::class, 'tourOpStaffLoginForm'])->name('tour-operator.login-form');

Route::get('storage/{filename}', fn($fileName) => loadPdf($fileName))->name('storage');

Route::get('send-bulk-email/{start_date}/{end_date}', [BulkEmailController::class, 'run'])
	->name('send-bulk-email')
	->middleware(['auth:admin', 'prevent-back-history', 'check-user-status']);

Route::get('truncate-tables/{table}/{tourOperatorId}', [TruncateTablesController::class, 'run'])
	->name('truncate-tables')
	->middleware(['auth:admin', 'prevent-back-history', 'check-user-status']);

Route::get('time-slot-seats-update/{tourPackageId}', [TruncateTablesController::class, 'timeSlotSeatsUpdate'])
	->name('time-slot-seats-update')
	->middleware(['auth:admin', 'prevent-back-history', 'check-user-status']);

Route::get('update-migrations-tables', [TruncateTablesController::class, 'runMigrations'])
	->name('update-migrations-tables')
	->middleware(['auth:admin', 'prevent-back-history', 'check-user-status']);

Route::get('swap-booking-seats', [ScriptController::class, 'antelopeCanyonAZTimeSlotChange'])
	->name('swap-booking-seats')
	->middleware(['auth:admin', 'prevent-back-history', 'check-user-status']);

Route::post('stripe-webhook', [StripeWebhookController::class, 'stripeWebhook'])->name('stripe-webhook');

Route::post('handle-aws-email-subscription', [AmazonController::class, 'emailNotifications'])->name('handle-aws-email-subscription');

Route::get('/tour-booking-info/{bookingId}', [CustomerController::class, 'tourBookingInfo'])->name('customer.tour-booking-info');

Route::get('/payment-link-tour-booking-info/{bookingId}', [CustomerController::class, 'paymentLinkBourBookingInfo'])->name('customer.payment-link-tour-booking-info');

// Route for TripAdvisor booking [PDF]
Route::get('/viator-booking/{bookingId}', [CustomerController::class, 'tripAdvisorBookingPdf'])->name('customer.viator-booking');

Route::get('/booking-invoice/{bookingId}', [TourBookingController::class, 'bookingInvoice'])
	->name('booking-invoice')
	->middleware(['auth:admin,tour_operator_staff,affiliate', 'prevent-back-history', 'check-user-status']);


Route::get('/confirm-cancel-booking/{bookingId}', [CustomerController::class, 'tourBookingInfo'])->name('customer.confirm-cancel-booking');
Route::post('/cancel-booking/{bookingId}', [CustomerController::class, 'tourBookingCancel'])->name('customer.cancel-booking');

Route::get('/liability-waiver/{bookingId}', [CustomerController::class, 'liabilityWaiverForm'])->name('customer.liability-waiver-form');
Route::post('/liability-waiver/{bookingId}', [CustomerController::class, 'liabilityWaiverSubmission'])->name('customer.liability-waiver-submission');

Route::get('/school-waiver/{bookingId}', [CustomerController::class, 'schoolWaiverForm'])->name('customer.school-waiver-form');
Route::post('/school-waiver/{bookingId}', [CustomerController::class, 'schoolWaiverSubmission'])->name('customer.school-waiver-submission');
Route::get('/school-waiver-success/{bookingId}', [CustomerController::class, 'schoolWaiverSuccess'])->name('customer.school-waiver-success');

Route::get('send-email-to/{date}', [TourBookingController::class, 'sendEmailFormByTourDate'])->name('send-email-form-');
Route::post('send-email-to/{date}/{time}', [TourBookingController::class, 'sendEmailByTourDate'])->name('send-emailbyDatetime');

#Route::get('check-tenant-exist/{tenant_id}/{tenant_info?}', [CustomerController::class, 'checkTenantExist'])->name('check-tenant-exist');
Route::get('accept-company/{userId}/{userType}', [TenantController::class, 'acceptCompany'])->name('accept-company');
Route::get('accept-tenant/{userId}/{userType}/{tenantId}/{networkId}', [NetworkController::class, 'acceptTenant'])->name('accept-tenant');

// Payment Link Route
Route::get('payment/{token}', [CustomerController::class, 'generatePaymentLink'])->name('customer.payment');
Route::get('success-payment', [CustomerController::class, 'paymentLinkSuccess'])->name('customer.success-payment');


//Tip payment link route
Route::get('pay-tip/{guideId}', [CustomerController::class, 'payTip'])->name('customer.pay-tip');
Route::get('give-tip/{bookingId}', [CustomerController::class, 'giveTip'])->name('customer.give-tip');
Route::post('create-tip-payment-link', [CustomerController::class, 'createTipPaymentLink'])->name('customer.create-tip-payment-link');
Route::get('success-tip-payment', [CustomerController::class, 'tipPaymentSuccess'])->name('customer.success-tip-payment');
