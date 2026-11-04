<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

Route::as('auth.')->middleware(['guest', 'prevent-back-history'])->group(function () {
	Route::get('/register', [AuthController::class, 'customerRegisterForm'])->name('customer.register-form');
	Route::post('/register', [AuthController::class, 'customerRegister'])->name('customer.register');
	Route::get('/login', [AuthController::class, 'customerLoginForm'])->name('customer.login-form');

	Route::post('/login', [AuthController::class, 'customerLogin'])->name('customer.login');
	Route::get('/forgot-password', [AuthController::class, 'customerForgotPasswordForm'])->name('customer.forgot-password-form');
	Route::post('/forgot-password', [AuthController::class, 'customerSendResetPasswordLink'])->name('customer.forgot-password-send-email');
	Route::get('/reset-password', [AuthController::class, 'customerResetPasswordForm'])->name('customer.reset-password-form');
	Route::post('/reset-password', [AuthController::class, 'customerResetPassword'])->name('customer.reset-password');

	Route::get('/admin', [AuthController::class, 'adminLoginForm'])->name('admin.login-form');
	Route::post('/admin', [AuthController::class, 'adminLogin'])->name('admin.login');
	Route::get('/admin/forgot-password', [AuthController::class, 'adminForgotPasswordForm'])->name('admin.forgot-password-form');
	Route::post('/admin/forgot-password', [AuthController::class, 'adminSendResetPasswordLink'])->name('admin.forgot-password-send-email');
	Route::get('/admin/reset-password', [AuthController::class, 'adminResetPasswordForm'])->name('admin.reset-password-form');
	Route::post('/admin/reset-password', [AuthController::class, 'adminResetPassword'])->name('admin.reset-password');

	Route::get('/register-tour-operator', [AuthController::class, 'tourOperatorRegisterForm'])->name('tour-operator.register-form');
	Route::post('/register-tour-operator', [AuthController::class, 'tourOperatorRegister'])->name('tour-operator.register');
	Route::get('/tour-operator', [AuthController::class, 'tourOpStaffLoginForm'])->name('tour-operator.login-form');
	Route::post('/tour-operator', [AuthController::class, 'tourOpStaffLogin'])->name('tour-operator.login');

	Route::get('/affiliate', [AuthController::class, 'affiliateLoginForm'])->name('affiliate.login-form');
	Route::post('/affiliate', [AuthController::class, 'affiliateLogin'])->name('affiliate.login');

	Route::get('/tour-operator/forgot-password', [AuthController::class, 'tourOpStaffForgotPasswordForm'])->name('tour-operator.forgot-password-form');
	Route::post('/tour-operator/forgot-password', [AuthController::class, 'tourOpStaffSendResetPasswordLink'])->name('tour-operator.forgot-password-send-email');
	Route::get('/tour-operator/reset-password', [AuthController::class, 'tourOpStaffResetPasswordForm'])->name('tour-operator.reset-password-form');
	Route::post('/tour-operator/reset-password', [AuthController::class, 'tourOpStaffResetPassword'])->name('tour-operator.reset-password');

	Route::get('/magic-login/custom/secure-access/c0mpl3x/access-portal/{token}/{type}', [AuthController::class, 'magicLogin'])->name('magic.login');
});
