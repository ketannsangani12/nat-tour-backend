<?php

use App\Http\Controllers\API\V1\CoordinatorApi\CoordinatorAPIController;
use Illuminate\Support\Facades\Route;
Route::group(['middleware' => 'auth:sanctum'], function () {
	Route::post('add-booking', [CoordinatorAPIController::class, 'addBooking']);
	Route::post('booking-assigned', [CoordinatorAPIController::class, 'bookingAssigned']);
	Route::get('tour-guide-assinged-booking-list', [CoordinatorAPIController::class, 'tourGuideAssingedBookingList']);
	Route::post('move-booking', [CoordinatorAPIController::class, 'moveBooking']);
	Route::post('assigned-booking-delete', [CoordinatorAPIController::class, 'assignedBookingDelete']);
	Route::get('booking-invoice-for-tour-guide/{bookingId}', [CoordinatorAPIController::class, 'bookingInvoiceForTourGuide']);
	Route::get('get-tour-packages-for-coordinator/{tourOperatorId}', [CoordinatorAPIController::class, 'getTourPackagesForCoordinator']);
	Route::get('get-date-time/{tourOperatorId}/{tourPackageId}/{date}', [CoordinatorAPIController::class, 'getDateTime']);
	Route::get('get-tour-guide/{tourOperatorId}/{tourPackageId}/{timeSlotId}', [CoordinatorAPIController::class, 'getTourGuide']);
	Route::post('update-tour-guide', [CoordinatorAPIController::class, 'updateTourGuide']);
	Route::post('add-tour-guide', [CoordinatorAPIController::class, 'addTourGuide']);
	Route::post('tour-guide-status-update', [CoordinatorAPIController::class, 'tourGuideStatusUpdate']);
	Route::get('tour-guide-list', [CoordinatorAPIController::class, 'tourGuideList']);
});
