<?php

use App\Http\Controllers\Admin\SummarydetailsReportController;
use App\Http\Controllers\API\V1\Report\ReportController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('/report', [ SummarydetailsReportController::class, 'report' ])->name('reportop');
    Route::post('cash-report', [ ReportController::class, 'cashCollection' ])->name('cashcollection');
    Route::post('card-report', [ ReportController::class, 'cardCollection' ])->name('payments');
    Route::post('daily-activity-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}', [ ReportController::class, 'dailyactivityreport'])->name('daily-activity-report');
    Route::post('wire-collection-report', [ ReportController::class, 'wireCollection' ])->name('wirecollection');
    Route::post('affiliate-booked-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'affiliateReport'])->name('affiliate-report');
    Route::get('affiliate-invoice-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'affiliateInvoiceReport'])->name('affiliate-all-report');
    Route::get('affiliate-comprehensive-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'affiliateComprehensiveReport'])->name('affiliate-comprehensive-report');
    Route::post('tax-collection-report', [ ReportController::class, 'taxCollection' ])->name('tax-collection');
    Route::get('refund-payment-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}', [ ReportController::class, 'refundPayment'])->name('refund-payment-report');
    Route::post('cancel-booking-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'cancelBookingReport'])->name('cancel-booking-report');
    Route::get('failed-payment-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'pendingReport'])->name('pending-payment-report');
    Route::post('email-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'emailreport'])->name('email-report');
    Route::post('permit-fee-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}', [ ReportController::class, 'dailyreport'])->name('daily-report');
    Route::post('permit-fee-details-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}', [ ReportController::class, 'dailyreportdetails'])->name('daily-report-details');
    Route::post('fareharaborbooking-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'fareharaborbookingreport'])->name('fareharabor-booking-report');
    Route::post('tip-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'tipreport'])->name('tip-report');
    Route::post('tip-summary-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'tipSummaryReport'])->name('tip-summary-report');
    Route::post('availability-report/{fromDate}/{toDate}/{tourPackageId?}', [ ReportController::class, 'todayAvailabilityList' ])->name('today_avialability_report');
    Route::post('invalid-bookings-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'invalidBookingsReport'])->name('invalid-bookings-report');
    Route::get('closure-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'lowerAntelopeCanyonClosureReport'])->name('closure-report');
    Route::get('get-customers/{tourOperatorId?}/{tourPackageId?}', [ ReportController::class, 'getCustomers'])->name('get-customers');
    Route::get('invoice-insights-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}/{status?}', [ ReportController::class, 'invoiceInsightsReport']);
    Route::get('invoice-list/{customerId?}/{tourOpId?}',[ ReportController::class, 'invoicelist']);
    Route::get('invoice-sum-by-status',[ ReportController::class, 'invoiceSumByStatus']);
});
