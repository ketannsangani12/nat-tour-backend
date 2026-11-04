<?php

use Illuminate\Support\Facades\Route;


use App\Http\Controllers\AuthController;
use App\Http\Controllers\AmazonController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ImportExportController;

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SummarydetailsReportController;
use App\Http\Controllers\Admin\SummaryReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\ReleaseController;
use App\Http\Controllers\Admin\TourOperatorController as AdminTourOperatorCtrl;
use App\Http\Controllers\Admin\TourPackageEmailTemplateController;
use App\Http\Controllers\Admin\NetworkController;

use App\Http\Controllers\Common\StaffController;
use App\Http\Controllers\Common\TourPackageController;
use App\Http\Controllers\Common\TourPackageRateController;
use App\Http\Controllers\Common\TourPromotionController;
use App\Http\Controllers\Common\TourSlotController;
use App\Http\Controllers\Common\TourBookingController;
use App\Http\Controllers\Common\AffiliateController;
use App\Http\Controllers\Common\AgendaController;
use App\Http\Controllers\Common\ReportController;
use App\Http\Controllers\Common\CheckinController;
use App\Http\Controllers\Common\TimeSlotController;
use App\Http\Controllers\Common\SmsController;
use App\Http\Controllers\Common\BookingRequestController;
use App\Http\Controllers\Common\DepartmentController;
use App\Http\Controllers\Common\StripeController;
use App\Http\Controllers\Common\AddonFormController;

use App\Http\Controllers\TourOperator\TourOperatorController;

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

Route::prefix('affiliate')->as('affiliate.')->middleware(['auth:affiliate', 'prevent-back-history'])->group(function () {
	Route::get('switch-tenant', [NetworkController::class, 'switchTenant'])->name('networks.switch-tenant');
	Route::resource('master-email-template', EmailTemplateController::class)->except(['show']);
	Route::post('update-logo/{id}', [AdminTourOperatorCtrl::class, 'updateLogo'])->name('update-logo');
	Route::post('update-status/{id}', [AdminTourOperatorCtrl::class, 'updateStatus'])->name('update-status');
	Route::post('update-general/{id}', [AdminTourOperatorCtrl::class, 'updateGeneral'])->name('update-general');
	Route::post('update-seo/{id}', [AdminTourOperatorCtrl::class, 'updateSEO'])->name('update-seo');
	Route::post('update-social-media/{id}', [AdminTourOperatorCtrl::class, 'updateSocialMedia'])->name('update-social-media');
	Route::post('update-report-setup/{id}', [AdminTourOperatorCtrl::class, 'updateReportSetup'])->name('update-report-setup');
	Route::get('booking-request', [BookingRequestController::class, 'index'])->name('booking-request');
	Route::get('booking-request-info/{id}', [BookingRequestController::class, 'bookingRequestInfo'])->name('booking-request-info');
	Route::get('booking-approve', [BookingRequestController::class, 'bookingApprove'])->name('booking-approve');
	Route::get('booking-deny', [BookingRequestController::class, 'bookingDeny'])->name('booking-deny');
	Route::post('update-affiliate-setup/{id}', [AdminTourOperatorCtrl::class, 'updateAffiliateSetup'])->name('update-affiliate-setup');
	Route::post('update-checkin-setup/{id}', [AdminTourOperatorCtrl::class, 'updateCheckinSetup'])->name('update-checkin-setup');
	Route::post('set-active-country', [CountryController::class, 'setActive'])->name('set.active.country');
	Route::get('get-cities/{stateId}', [CityController::class, 'getCities'])->name('get-cities');
	Route::get('get-states/{countryId}', [StateController::class, 'getStates'])->name('get-states');
	Route::post('email-tracker-search', [AmazonController::class, 'postSearch'])->name('email-tracker-search');
	Route::get('email-tracker-clear', [AmazonController::class, 'clearSearch'])->name('email-tracker-clear');
	Route::get('email-tracker-show/{id}', [AmazonController::class, 'getShowEmail'])->name('email-tracker-show');
	Route::get('email-tracker-url/{id}', [AmazonController::class, 'getUrlDetail'])->name('email-tracker-url');
	Route::post('logout', [AuthController::class, 'logout'])->name('logout');
	Route::get('dashboard', [TourOperatorController::class, 'dashboard'])->name('dashboard');
	Route::get('profileedit/{id}', [AffiliateController::class, 'profileedit'])->name('profileedit');
	Route::post('profileupdate/{id}', [AffiliateController::class, 'profileupdate'])->name('profileupdate');
	Route::post('/affiliate/index-ahr/{tourOpId?}', [AffiliateController::class, 'indexAHR'])->name('index-ahr');
	Route::post('get-tour-slot-times-by-date', [TourSlotController::class, 'getTourSlotTimesByDate'])->name('get-tour-slot-times-by-date');
	Route::post('get-tour-rates-by-slot', [TourSlotController::class, 'getTourRatesBySlot'])->name('get-tour-rates-by-slot');
	Route::post('get-color-date', [TourSlotController::class, 'getColorDate'])->name('get-color-date');
	Route::get('get-tour-slot-time-bookings-info/{timeSlotId}', [TourBookingController::class, 'timeSlotBookingsInfo'])->name('get-tour-slot-time-bookings-info');
	Route::get('get-tour-slot-time-tourists-info/{timeSlotId}', [TourBookingController::class, 'timeSlotTouristsInfo'])->name('get-tour-slot-time-tourists-info');
	Route::get('switch-company/{tenantId}', [TenantController::class, 'switchCompany'])->name('switch-company');
	Route::get('get-affiliate-bookings/{timeSlotId}', [TourBookingController::class, 'affiliateBookings'])->name('get-affiliate-bookings');
	Route::get('search-booking', [TourBookingController::class, 'search'])->name('search-booking');
	Route::get('search-customer', [CustomerController::class, 'search'])->name('search-customer');
	Route::get('search-affiliate/{id}', [TourBookingController::class, 'searchAffiliate'])->name('search-affiliate');
	Route::post('customer-update/{id}', [CustomerController::class, 'updateByAdminOrTourOp'])->name('customer-update');
	Route::post('lower-antelope-canyon-closure-email', [TourSlotController::class, 'lowerAntelopeCanyonClosureEmail'])->name('lower-antelope-canyon-closure-email');
	Route::get('editoperatorprofile/{id}', [StaffController::class, 'editProfile'])->name('editoperatorprofile');
	Route::post('operatorprofile/{id}', [StaffController::class, 'updateProfile'])->name('operatorprofile');
	Route::get('affiliate/edit/{id}/{year}', [AffiliateController::class, 'editpackage'])->name('editpackageAffiliates');
	Route::get('package/edit/{id}/{year}', [TourPackageController::class, 'editpackage'])->name('editpackage');
	Route::post('affiliate/assign-package/{id}', [AffiliateController::class, 'assignPackage'])->name('assign-package');
	Route::get('/booking-invoice/{bookingId}', [TourBookingController::class, 'bookingInvoice'])->name('booking-invoice');
	Route::get('/split-booking-modal/{bookingId}', [TourBookingController::class, 'splitBookingModalContent'])->name('split-booking-modal-content');
	Route::get('agenda/{fromDate}/{toDate}/{tourPackageId?}/{tourCustomerId?}', [AgendaController::class, 'agenda'])->name('agenda');
	Route::get('download-affiliate-agenda/{fromDate}/{toDate}/{tourPackageId?}/{tourCustomerId?}', [AgendaController::class, 'downloadAffiliateAgenda'])->name('download-affiliate-agenda');
	Route::get('email-tracker', [AmazonController::class, 'getIndex'])->name('email-tracker');
	Route::get('admin-user-permission', [AdminUserController::class, 'adminUserPermission'])->name('admin-user-permission');
	Route::get('admin-user-permission-edit', [AdminUserController::class, 'adminUserPermissionEdit'])->name('admin-user-permission-edit');
	Route::get('admin-user-access-level', [AdminUserController::class, 'adminUserAccessLevel'])->name('admin-user-access-level');
	Route::get('user-permission', [StaffController::class, 'userPermission'])->name('user-permission');
	Route::get('user-permission-edit', [StaffController::class, 'userPermissionEdit'])->name('user-permission-edit');
	Route::get('user-access-level', [StaffController::class, 'userAccessLevel'])->name('user-access-level');
	Route::get('role-permission-add', [RoleController::class, 'rolePermissionAdd'])->name('role-permission-add');
	Route::get('role-permission-edit', [RoleController::class, 'rolePermissionEdit'])->name('role-permission-edit');
	Route::get('promotion-affiliate/{promotionId}', [TourPromotionController::class, 'promotionAffiliate'])->name('promotion-affiliate');
	Route::post('promotion-assign-to-affiliate', [TourPromotionController::class, 'promotionAssignToAffiliate'])->name('promotion-assign-to-affiliate');
	Route::delete('promotion-unassign-affiliate/{id}', [TourPromotionController::class, 'unassignAffiliate'])->name('promotion-unassign-affiliate');
	Route::post('package/update-settings/{id}', [TourPackageController::class, 'updateSettings'])->name('update-settings');
	Route::post('package/update-setting/{id}', [TourPackageController::class, 'updateSetting'])->name('update-setting');
	Route::get('affiliate-report-export', [ImportExportController::class, 'AffiliateReportExportCsvFile'])->name('affiliate_report_export');
	Route::get('affiliate_seats_report_export', [ImportExportController::class, 'AffiliateSeatsReportExportCsvFile'])->name('affiliate_seats_report_export');
	Route::get('affiliate-comprehensive-report-export', [ImportExportController::class, 'AffiliateComprehensiveReportExportCsvFile'])->name('affiliate_comprehensive_report_export');
	Route::get('get-tour-packages/{tourOperatorId}', [TourPackageController::class, 'getPackages'])->name('get-packages');
	Route::get('promotion-affiliate/{promotionId}', [TourPromotionController::class, 'promotionAffiliate'])->name('promotion-affiliate');
	Route::get('/get-release', [ReleaseController::class, 'getRelease'])->name('get-release');
	Route::get('release/view/{id}', [ReleaseController::class, 'view'])->name('release.view');
	Route::post('image-upload', [ReleaseController::class, 'storeImage'])->name('image.upload');
	Route::post('/release/{id}/send-notifications', [ReleaseController::class, 'sendNotifications'])->name('release.send-notifications');
	Route::get('/release/{id}/users', [ReleaseController::class, 'releaseUsers'])->name('release.users');

	Route::prefix('stripe')->as('stripe.')->group(function () {
		Route::post('create-payment-intent', [StripeController::class, 'createPaymentIntent'])->name('create-payment-intent');
		Route::post('delete-payment-method', [StripeController::class, 'deletePaymentMethod'])->name('delete-payment-method');
		Route::post('create-payment-intent-make-payment', [StripeController::class, 'createPaymentIntentForMakePayment'])->name('create-payment-intent-make-payment');
		Route::post('create-payment-intent-pos', [StripeController::class, 'createStripePaymentIntentForPos'])->name('create-payment-intent-pos');
		Route::post('save-card-list', [StripeController::class, 'saveCardList'])->name('save-card-list');
	});
	
	Route::prefix('staff')->as('staff.')->group(function () {
		Route::post('index-ahr', [StaffController::class, 'indexAHR'])->name('index-ahr');
		Route::post('update-general/{id}', [StaffController::class, 'updateGeneral'])->name('update-general');
		Route::post('update-role/{id}', [StaffController::class, 'updateRole'])->name('update-role');
	});

	Route::prefix('report')->as('report.')->group(function () {
		Route::get('/', [SummarydetailsReportController::class, 'report'])->name('reportop');
		Route::get('summary-details', [SummarydetailsReportController::class, 'summary']);
		Route::get('summary-details-data/{fromDate}/{toDate}/{tourOperatorId?}', [SummarydetailsReportController::class, 'indexAHR'])->name('filtered-data-for-summary-details');
		Route::get('summary-report', [SummaryReportController::class, 'summary_report']);
		Route::get('summary-reports-data/{fromDate}/{toDate}/{tourOperatorId?}', [SummaryReportController::class, 'indexAHR'])->name('filtered-data-for-summary-report');
		Route::get('booking-summary-report/{fromDate}/{toDate}/{tourOperatorId?}', [SummaryReportController::class, 'bookingSummaryList'])->name('booking-summary-list-report');
		Route::get('affiliate-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'affiliatereport'])->name('affiliate-report');
		Route::get('affiliate-seats-report', [SummaryReportController::class, 'affiliateSeatsReport'])->name('affiliate-seats-report');
		Route::get('affiliate-seats-report-detail', [ReportController::class, 'getAffiliateSeatsReportDetail'])->name('affiliate-seats-report-detail');
		Route::get('affiliate-comprehensive-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'affiliatecomprehensivereport'])->name('affiliate-comprehensive-report');
		Route::get('affiliate-seats-report', [SummaryReportController::class, 'affiliateSeatsReport'])->name('affiliate-seats-report');
		Route::get('affiliate-seats-sync', [SummaryReportController::class, 'affiliateSeatsSync'])->name('affiliate-seats-sync');
		Route::get('affiliate-seats-report-detail', [ReportController::class, 'getAffiliateSeatsReportDetail'])->name('affiliate-seats-report-detail');
	});

	// invoice
	Route::prefix('invoice')->as('invoice.')->group(function () {
		Route::post('index-ahr', [InvoiceController::class, 'indexAHR'])->name('index-ahr');
		Route::get('list/{customerId?}/{tourOpId?}', [InvoiceController::class, 'list'])->name('list');
		Route::get('show/{uuid}/{layout?}', [InvoiceController::class, 'show'])->name('show');
		Route::post('store-with/stripe', [InvoiceController::class, 'storeWithStripeInvoice'])->name('store-with-stripe');
		Route::get('save-pdf/{uuid}/{reload?}', [InvoiceController::class, 'savePdf'])->name('save-pdf');
		Route::get('pdf-status/{uuid}', [InvoiceController::class, 'pdfStatus'])->name('pdf-status');
		Route::get('print/{uuid}', [InvoiceController::class, 'printInfo'])->name('print-info');
		Route::post('send-email/{uuid}', [InvoiceController::class, 'sendEmail'])->name('send-email');
		Route::post('reference-invoice/{uuid}', [InvoiceController::class, 'referenceInvoice'])->name('reference-invoice');
		Route::get('affiliate-due-amount', [InvoiceController::class, 'affiliateDueAmount'])->name('affiliate-due-amount');
		Route::post('affiliate-due-amount-ahr', [InvoiceController::class, 'affiliateDueAmountAHR'])->name('affiliate-due-amount-ahr');
		Route::get('invoice-sum-by-status', [InvoiceController::class, 'invoiceSumByStatus'])->name('invoice-sum-by-status');
		Route::get('stripe-invoice-read', [InvoiceController::class, 'readStripeInvoice'])->name('stripe-invoice-read');
		Route::post('stripe-invoice-enable', [InvoiceController::class, 'enableStripeInvoiceForAffiliate'])->name('stripe-invoice-enable');
	});

	//CheckinController
	Route::prefix('checkin')->as('checkin.')->group(function () {
		Route::get('index/{packageId?}/{isBundlePackage?}', [CheckinController::class, 'index'])->name('index');
		Route::post('validate/{bookingId}', [CheckinController::class, 'validateBooking'])->name('validate');
		Route::post('waiver/{bookingId}/{touristNum}/{isAdult}', [CheckinController::class, 'waiverInfo'])->name('waiver');
		Route::post('sign/waiver/{bookingId}', [CheckinController::class, 'signWaiver'])->name('sign-waiver');
		Route::post('bulk/checkin', [CheckinController::class, 'bulkCheckIn'])->name('bulk-checkin');
		Route::get('sms-index', [SmsController::class, 'smsIndex'])->name('sms-index');
		Route::post('sms-send', [SmsController::class, 'smsSend'])->name('sms-send');
		Route::post('sms-send-link', [SmsController::class, 'smsSendLink'])->name('sms-send-link');
		Route::get('packages', [CheckinController::class, 'tourPackages'])->name('packages');
	});

	Route::prefix('package')->as('package.')->group(function () {
		Route::post('index-ahr/{tourOpId?}', [TourPackageController::class, 'indexAHR'])->name('index-ahr');
		Route::post('update-general/{id}', [TourPackageController::class, 'updateGeneral'])->name('update-general')->whereNumber('id');
		Route::post('update-seo/{id}', [TourPackageController::class, 'updateSEO'])->name('update-seo')->whereNumber('id');
		Route::post('update-waiver/{id}', [TourPackageController::class, 'updateWaiver'])->name('update-waiver')->whereNumber('id');
		Route::get('delete-waiver-pdf/{id}', [TourPackageController::class, 'deleteWaiverPdf'])->name('delete-waiver-pdf');
		Route::post('add-photos/{id}', [TourPackageController::class, 'addPhotos'])->name('add-photos');
		Route::post('update-photo-status/{id}', [TourPackageController::class, 'updatePhotoStatus'])->name('update-photo-status');
		Route::delete('delete-photo/{id}', [TourPackageController::class, 'deletePhoto'])->name('delete-photo');
		Route::post('add-update-rate/{tourPackageId}', [TourPackageController::class, 'addUpdateRate'])->name('add-update-rate');
		Route::delete('delete-rate/{id}', [TourPackageController::class, 'deleteRate'])->name('delete-rate');
		Route::post('add-relation/{tourPackageId}', [TourPackageController::class, 'addRelation'])->name('add-relation');
		Route::delete('delete-relation/{tourPackageFamilyId}', [TourPackageController::class, 'deleteRelation'])->name('delete-relation');
		Route::post('add-hotel/{tourPackageId}', [TourPackageController::class, 'addHotel'])->name('add-hotel');
		Route::post('update-hotel/{hotelId}', [TourPackageController::class, 'updateHotel'])->name('update-hotel');
		Route::delete('delete-hotel/{hotelId}', [TourPackageController::class, 'deleteHotel'])->name('delete-hotel');
		Route::delete('delete-category/{categoryId}', [TourPackageController::class, 'deleteCategory'])->name('delete-category');
		Route::post('upsert-package-form', [TourPackageController::class, 'upsertPackageForm'])->name('upsert-package-form');
		Route::get('get-package-form/{tourPackageId?}', [TourPackageController::class, 'getPackageForm'])->name('get-package-form');
		Route::delete('delete-package-form/{id?}/{tourPackageId?}', [TourPackageController::class, 'deletePackageFrom'])->name('delete-package-form');
		Route::get('get-package-form-submission/{bookingId?}', [TourPackageController::class, 'getSubmittedForm'])->name('get-package-form-submission');
		Route::post('put-package-form-submission', [TourPackageController::class, 'updateSubmittedForm'])->name('put-package-form-submission');

		// Addon Nested Form Builder Routes
		Route::post('save-addon-form', [AddonFormController::class, 'saveAddonForm'])->name('save-addon-form');
		Route::delete('delete-addon-form/{formId?}', [AddonFormController::class, 'deleteAddonForm'])->name('delete-addon-form');
		Route::get('get-addon-form/{tourPackageId?}/{builder?}', [AddonFormController::class, 'getPackageAddonForm'])->name('get-addon-form');
		Route::get('get-addon-categories', [AddonFormController::class, 'getAddonCategories'])->name('get-addon-categories');
		Route::post('save-addon-category', [AddonFormController::class, 'saveAddonCategory'])->name('save-addon-category');
		Route::get('get-addon-packages/{tourPackageId?}', [AddonFormController::class, 'getAddonPackages'])->name('get-addon-packages');

		Route::post('add-category', [TourPackageController::class, 'addCategory'])->name('add-category');
		Route::post('iframe-url', [TourPackageController::class, 'iFrameUrl'])->name('iframe-url');
		Route::post('update-category', [TourPackageController::class, 'updateCategory'])->name('update-category');
		Route::post('add-update-ratess', [AffiliateController::class, 'addUpdateRatebyaffiliate'])->name('affiliate-add-update-rate');
		Route::post('affiliate-pkg-list', [AffiliateController::class, 'affiliatePkgList'])->name('affiliatepackagelist');
		Route::post('affiliate-time-slot-list', [AffiliateController::class, 'affiliateTimeSlotList'])->name('affiliatetimeslotlist');
		Route::post('affiliate-time-slot-sync', [AffiliateController::class, 'affiliateTimeSlotSync'])->name('affiliatetimeslotsync');
		Route::get('affiliate-user-permission', [AffiliateController::class, 'affiliateUserPermission'])->name('affiliate-user-permission');
		Route::get('affiliate-user-permission-edit', [AffiliateController::class, 'affiliateUserPermissionEdit'])->name('affiliate-user-permission-edit');
		Route::get('affiliate-user-access-level', [AffiliateController::class, 'affiliateUserAccessLevel'])->name('affiliate-user-access-level');
		Route::post('assign-affiliate/{id}', [TourPackageController::class, 'assignAffiliate'])->name('assign-affiliate');
		Route::delete('unassign-affiliate', [TourPackageController::class, 'unassignAffiliate'])->name('unassign-affiliate');
		Route::post('update-assigned-affiliate', [TourPackageController::class, 'updateAssignedAffiliate'])->name('update-assigned-affiliate');
		Route::post('store-cancellation-policy', [TourPackageController::class, 'storeCancellationPolicy'])->name('store-cancellation-policy');
		Route::delete('delete-cancellation-policy/{id}', [TourPackageController::class, 'deleteCancellationPolicy'])->name('delete-cancellation-policy');
		Route::post('add-bundle-relation/{tourPackageId}', [TourPackageController::class, 'addBundleRelation'])->name('add-bundle-relation');
		Route::delete('delete-bundle-relation/{tourBundlePackageId}', [TourPackageController::class, 'deleteBundleRelation'])->name('delete-bundle-relation');
	});

	Route::prefix('package-rate')->as('package-rate.')->group(function () {
		Route::get('/', [TourPackageRateController::class, 'packageRate'])->name('index');
		Route::post('get-package-rates', [TourPackageRateController::class, 'getPackageRates'])->name('get-package-rates');
		Route::post('save-package-rates', [TourPackageRateController::class, 'savePackageRates'])->name('save-package-rates');
		Route::delete('delete-package-rates', [TourPackageRateController::class, 'deletePackageRates'])->name('delete-package-rates');
		Route::get('custom-date-rate', [TourPackageRateController::class, 'customDateRate'])->name('custom-date-rate');
		Route::post('get-time-slots', [TourPackageRateController::class, 'getTimeSlots'])->name('get-time-slots');
		Route::post('save-custom-date-rates', [TourPackageRateController::class, 'saveCustomDateRates'])->name('save-custom-date-rates');
		Route::post('unassign-slot-to-date-rate', [TourPackageRateController::class, 'unassignSlotToDateRate'])->name('unassign-slot-to-date-rate');
		Route::post('delete-custom-date-rate', [TourPackageRateController::class, 'deleteCustomDateRate'])->name('delete-custom-date-rate');
		Route::get('global-rate', [TourPackageRateController::class, 'globalRate'])->name('global-rate');
		Route::post('save-global-rates', [TourPackageRateController::class, 'saveGlobalRates'])->name('save-global-rates');
		Route::post('save-schedule-event', [TourPackageRateController::class, 'saveScheduleEvent'])->name('save-schedule-event');
		Route::post('fetch-schedule-events', [TourPackageRateController::class, 'fetchScheduleEvents'])->name('fetch-schedule-events');
		Route::post('delete-schedule-event', [TourPackageRateController::class, 'deleteScheduleEvent'])->name('delete-schedule-event');
	});

	Route::prefix('slot')->as('slot.')->group(function () {
		Route::get('searchdate/{fromdate?}/{todate?}/{tourOpId?}', [TourSlotController::class, 'searchdate'])->name('searchdate');
		Route::post('index-ahr/{tourOpId?}', [TourSlotController::class, 'indexAHR'])->name('index-ahr');
		Route::get('repeat/{dateSlotId}', [TourSlotController::class, 'repeatSlotForm'])->name('repeat-slot-form');
		Route::post('repeat', [TourSlotController::class, 'repeatSlot'])->name('repeat-slot');
		Route::post('add-update-time-slot', [TourSlotController::class, 'addUpdateTimeSlot'])->name('add-update-time-slot');
		Route::post('add-update-time-slots', [TourSlotController::class, 'addUpdateTimeSlots'])->name('add-update-time-slots');
		Route::delete('delete-time-slots', [TourSlotController::class, 'deleteallTimeSlot'])->name('delete-time-slot-all');
		Route::delete('delete-time-slot/{timeSlotId}', [TourSlotController::class, 'deleteTimeSlot'])->name('delete-time-slot');
		Route::get('bulk-edit-time-slot', [TourSlotController::class, 'bulkEditTimeSlot'])->name('bulk-edit-time-slot');
		Route::post('bulk-update-time-slot', [TourSlotController::class, 'bulkUpdateTimeSlot'])->name('bulk-update-time-slot');
	});

	Route::prefix('time-slot')->as('time-slot.')->group(function () {
		Route::post('index-ahr/{tourOpId?}', [TimeSlotController::class, 'indexAHR'])->name('index-ahr');
		Route::post('index-package-ahr/{tourPkId?}', [TimeSlotController::class, 'indexPackageAHR'])->name('index-package-ahr');
		Route::post('add-update-time-slot', [TimeSlotController::class, 'addUpdateTimeSlot'])->name('add-update-time-slot');
		Route::post('add-update-time-slots', [TimeSlotController::class, 'addUpdateTimeSlots'])->name('add-update-time-slots');
		Route::delete('delete-time-slot/{timeSlotId}', [TimeSlotController::class, 'deleteTimeSlot'])->name('delete-time-slot');
	});

	Route::prefix('booking')->as('booking.')->group(function () {
		Route::get('calendar', [TourBookingController::class, 'calendar'])->name('calendar');
		Route::post('checkRates', [TourOperatorController::class, 'checkRates'])->name('checkRates');
		Route::post('get-promo-detail', [TourBookingController::class, 'getPromoDetail'])->name('get-promo-detail');
		Route::get('help', [TourBookingController::class, 'help'])->name('help');
		Route::post('calendar-ahr', [TourBookingController::class, 'calendarAHR'])->name('calendar-ahr');
		Route::get('day-time-slots/{date}/{tourPackageId?}/{paymentStatus?}/{slotId?}', [TourBookingController::class, 'dayTimeSlots'])->name('day-time-slots');
		Route::post('agenda-checkin-overview', [TourBookingController::class, 'agendaCheckinOverview'])->name('agenda-checkin-overview');
		Route::get('day-time-slots-booking-id', [TourBookingController::class, 'dayTimeSlotsbooking'])->name('day-time-slots-booking-id');
		Route::get('download-agenda/{date}/{tourPackageId?}/{paymentStatus?}/{slotId?}', [TourBookingController::class, 'downloadAgenda'])->name('download-agenda');
		Route::post('cancel/{bookingId}', [TourBookingController::class, 'cancel'])->name('cancel');
		Route::post('request-to-manager', [TourBookingController::class, 'requestToManager'])->name('request-to-manager');
		Route::post('manual-id-check', [TourBookingController::class, 'manualIdCheck'])->name('manual-id-check');
		Route::post('manual-pin-check', [TourBookingController::class, 'manualPinCheck'])->name('manual-pin-check');
		Route::post('manual-pin-check-booking', [TourBookingController::class, 'manualPinCheckBooking'])->name('manual-pin-check-booking');
		Route::post('action-store', [TourBookingController::class, 'actionStore'])->name('action-store');
		Route::post('restore/{bookingId}', [TourBookingController::class, 'restore'])->name('restore');
		Route::post('change-payment-type/{historyId}', [TourBookingController::class, 'changePaymentType'])->name('change-payment-type');
		Route::post('refund/{bookingId}', [TourBookingController::class, 'refund'])->name('refund');
		Route::post('add-custom-charge/{bookingId}', [TourBookingController::class, 'addCustomCharge'])->name('add-custom-charge');
		Route::get('get-payment-link-items/{bookingId}', [TourBookingController::class, 'getPaymentLinkItems'])->name('get-payment-link-items');
		Route::post('save-note/{bookingId}', [TourBookingController::class, 'saveNote'])->name('save-note');
		Route::post('save-priority-note/{bookingId}', [TourBookingController::class, 'savePriorityNote'])->name('save-priority-note');
		Route::post('save-comments/{bookingId}', [TourBookingController::class, 'saveComments'])->name('save-comments');
		Route::post('save-reference-booking/{bookingId}', [TourBookingController::class, 'saveReferenceBooking'])->name('save-reference-booking');
		Route::post('save-tour-guide/{bookingId}', [TourBookingController::class, 'saveTourGuide'])->name('save-tour-guide');
		Route::post('update-check-in-status', [TourBookingController::class, 'updateCheckInStatus'])->name('update-check-in-status');
		Route::get('print/{bookingId}', [TourBookingController::class, 'printInfo'])->name('print-info');
		Route::get('receipt-print/{bookingId}', [TourBookingController::class, 'receiptPrint'])->name('receipt-print');
		Route::get('receipt-print-data/{bookingId}', [TourBookingController::class, 'receiptPrintData'])->name('receipt-print-data');
		Route::post('save-affiliate-booking', [TourBookingController::class, 'saveAffiliateBooking'])->name('save-affiliate-booking');
		Route::post('tip-payment', [TourBookingController::class, 'tipPayment'])->name('tip-payment');
		Route::post('payment-new-booking', [TourBookingController::class, 'paymentnewbooking'])->name('payment-new-booking');
		Route::post('edit-discount', [TourBookingController::class, 'editDiscount'])->name('edit-discount');
		Route::post('edit-discount-payment', [TourBookingController::class, 'editDiscountPayment'])->name('edit-discount-payment');
		Route::post('edit-discount-refund',        [TourBookingController::class, 'editDiscountRefund'])->name('edit-discount-refund');
		Route::post('edit-discount-payment-link', [TourBookingController::class, 'editDiscountPaymentLink'])->name('edit-discount-payment-link');
		Route::post('payment-cart-bookings', [TourBookingController::class, 'paymentCartBookings'])->name('payment-cart-bookings');
		Route::get('send-email/{bookingId}', [TourBookingController::class, 'sendEmailForm'])->name('send-email-form');
		Route::post('send-email/{bookingId}', [TourBookingController::class, 'sendEmail'])->name('send-email');
		Route::post('reschedule/{bookingId}', [TourBookingController::class, 'reschedule'])->name('reschedule');
		Route::get('liability-waiver/{bookingId}/{touristNum}/{isAdult}', [TourBookingController::class, 'liabilityWaiverForm'])->name('liability-waiver-form');
		Route::post('liability-waiver/{bookingId}', [TourBookingController::class, 'liabilityWaiverSubmission'])->name('liability-waiver-submission');
		Route::post('refund_and_booking_edit', [TourBookingController::class, 'refund_and_booking_edit'])->name('refund_and_booking_edit');
		Route::post('change-people-booking', [TourBookingController::class, 'changePeopleBooking'])->name('change-people-booking');
		Route::post('split-people-booking', [TourBookingController::class, 'splitPeopleBooking'])->name('split-people-booking');
		Route::post('switch-package-booking', [TourBookingController::class, 'switchPackageBooking'])->name('switch-package-booking');
		Route::any('resend_email/{bookingId}', [TourBookingController::class, 'ResendEmail'])->name('ResendEmail');
		Route::post('check-overbooking', [TourBookingController::class, 'checkOverbooking'])->name('check-overbooking');
		Route::get('unblock-affiliate-soft-booking/{bookingId}', [TourBookingController::class, 'unblockAffiliateSoftBooking'])->name('unblock-affiliate-soft-booking');
		Route::post('add-or-update-hotel/{bookingId}', [TourBookingController::class, 'addOrUpdateHotel'])->name('add-or-update-hotel');
		Route::post('add-to-cart', [TourBookingController::class, 'addToCart'])->name('add-to-cart');
		Route::post('remove-from-cart', [TourBookingController::class, 'removeFromCart'])->name('remove-from-cart');
		Route::post('edit-cart', [TourBookingController::class, 'editCart'])->name('edit-cart');
		Route::post('set-default-booking-source', [TourBookingController::class, 'setDefaultBookingSource'])->name('set-default-booking-source');
		Route::post('create-combo-booking', [TourBookingController::class, 'createComboBooking'])->name('create-combo-booking');
		Route::get('combo-booking-invoice/{cartId}', [TourBookingController::class, 'comboBookingInvoice'])->name('combo-booking-invoice');
		Route::get('/bookings/{bookingId}/invoice/no-price', [TourBookingController::class, 'invoiceWithoutPrice'])->name('invoice.no-price');
		Route::post('refund-custom-charge/{bookingId}', [TourBookingController::class, 'refundCustomCharge'])->name('refund-custom-charge');
		Route::post('upsert-addons', [TourBookingController::class, 'upseartAddons'])->name('upsert-addons');
		Route::post('update-package-redeem-status', [TourBookingController::class, 'updatePackageRedeemTicketStatus'])->name('update-package-redeem-status');
		Route::get('get-contact-details/{contactId}', [TourBookingController::class, 'getContactDetails'])->name('get-contact-details');
		Route::post('edit-contact', [TourBookingController::class, 'editContact'])->name('edit-contact');
		Route::get('roster-report-csv/{date}/{tourPackageId?}/{PaymentStatus?}/{slotId?}', [TourBookingController::class, 'rosterReportCSV'])->name('roster-report-csv');
		Route::get('roster-report-pdf/{date}/{tourPackageId?}/{PaymentStatus?}/{slotId?}', [TourBookingController::class, 'rosterReportPDF'])->name('roster-report-pdf');
		Route::get('contacts/{bookingId}', [TourBookingController::class, 'bookingContacts'])->name('contacts');
		Route::get('rate-group-contacts/{bookingId}', [TourBookingController::class, 'rateGroupWiseBookingContacts'])->name('rate-group-contacts');
		Route::post('update-slot-bookable-status', [TourBookingController::class, 'updateSlotBookableStatus'])->name('update-slot-bookable-status');
		Route::post('bulk-update-slot-bookable-status', [TourBookingController::class, 'bulkUpdateSlotBookableStatus'])->name('bulk-update-slot-bookable-status');
		Route::post('update-slot-hide-status', [TourBookingController::class, 'updateSlotHideStatus'])->name('update-slot-hide-status');
		Route::post('bulk-update-slot-hide-status', [TourBookingController::class, 'bulkUpdateSlotHideStatus'])->name('bulk-update-slot-hide-status');
		Route::post('add-time-slot', [TourBookingController::class, 'addTimeSlot'])->name('add-time-slot');
		Route::post('create-payment-link', [TourBookingController::class, 'createPaymentLink'])->name('create-payment-link');
		Route::post('payment-link-reason', [TourBookingController::class, 'getPaymentLinkReason'])->name('payment-link-reason');
		Route::post('expiry-date-override', [TourBookingController::class, 'expiryDateOverride'])->name('expiry-date-override');
		Route::get('active-payment-link', [TourBookingController::class, 'activePaymentLink'])->name('active-payment-link');
		Route::get('inactive-payment-link', [TourBookingController::class, 'inActivePaymentLink'])->name('inactive-payment-link');
		Route::post('payment-link-status-update', [TourBookingController::class, 'paymentLinkStatusUpdate'])->name('payment-link-status-update');
		Route::post('payment-link-status-check', [TourBookingController::class, 'paymentLinkStatusCheck'])->name('payment-link-status-check');
		Route::get('get-assigned-guides/{bookingId}', [TourBookingController::class, 'getAssignedGuides'])->name('get-assigned-guides');
		
		// Booking nested Add-ons updation
		Route::get('get-booking-addons-submission/{tourBookingId}', [TourBookingController::class, 'getTourBookingAddonsSubmission'])->name('get-booking-addons-submission');
		Route::post('update-booking-addon-submission', [TourBookingController::class, 'updateBookingAddons'])->name('update-booking-addon-submission');
		Route::post('addon-payment-link', [TourBookingController::class, 'addonPaymentLink'])->name('addon-payment-link');
		Route::post('get-booking-pax-addons', [TourBookingController::class, 'getBookingPaxAddons'])->name('get-booking-pax-addons');
		Route::post('save-pax-addons', [TourBookingController::class, 'savePaxAddons'])->name('save-pax-addons');
		Route::post('update-pax-addons', [TourBookingController::class, 'updatePaxAddons'])->name('update-pax-addons');
		Route::post('delete-pax-addons', [TourBookingController::class, 'deletePaxAddons'])->name('delete-pax-addons');
	});

	Route::prefix('setting')->as('setting.')->group(function () {
		Route::get('edit', [SettingController::class, 'edit'])->name('edit');
		Route::post('update', [SettingController::class, 'update'])->name('update');
		Route::post('add-surcharge-date', [SettingController::class, 'addSurchargeDate'])->name('add-surcharge-date');
		Route::delete('detele-surcharge-date/{id}', [SettingController::class, 'deleteSurchargeDate'])->name('detele-surcharge-date');
		Route::get('card-readers', [SettingController::class, 'cardReadersSettings'])->name('card-readers');
		Route::post('card-readers-actions', [SettingController::class, 'cardReaderActions'])->name('card-readers-actions');
	});

	Route::resource('country', CountryController::class)->except(['show']);
	Route::resource('state', StateController::class)->except(['show']);
	Route::resource('city', CityController::class)->except(['show']);
	Route::resource('category', CategoryController::class)->except(['show']);
	Route::resource('tax', TaxController::class)->except(['show']);
	Route::resource('role', RoleController::class)->except(['show']);
	Route::resource('permission', PermissionController::class)->except(['show']);
	Route::resource('department', DepartmentController::class)->except(['show']);
	Route::resource('tour-operator', AdminTourOperatorCtrl::class)->except(['show', 'update']);
	Route::resource('release', ReleaseController::class);
	Route::resource('invoice', InvoiceController::class)->except(['show', 'list']);
	Route::resource('tenant', TenantController::class)->except(['show', 'edit', 'update', 'destroy']);
	Route::resource('admin-user', AdminUserController::class)->except(['show']);
	Route::resource('staff', StaffController::class)->except(['show', 'update']);
	Route::resource('package', TourPackageController::class)->except(['show', 'update']);
	Route::resource('promotion', TourPromotionController::class)->except(['show']);
	Route::resource('slot', TourSlotController::class)->except(['show', 'indexAHR']);
	Route::resource('time-slot', TimeSlotController::class)->except(['show', 'indexAHR']);
	Route::resource('booking', TourBookingController::class)->only(['create', 'store', 'show', 'destroy']);
	Route::resource('affiliate', AffiliateController::class)->except(['show']);
	Route::resource('master-email-template', EmailTemplateController::class)->except(['show']);
	Route::resource('{tour_package}/email-template', TourPackageEmailTemplateController::class)->except(['show']);
});
