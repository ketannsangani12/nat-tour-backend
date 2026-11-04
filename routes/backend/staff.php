<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\CheckUserStatus;
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
use App\Http\Controllers\Admin\ReportV2Controller;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\ReleaseController;
use App\Http\Controllers\Admin\Today_avialabilityController;
use App\Http\Controllers\Admin\Today_avialability_detailsController;
use App\Http\Controllers\Admin\TourOperatorController as AdminTourOperatorCtrl;
use App\Http\Controllers\Admin\TourPackageEmailTemplateController;
use App\Http\Controllers\Admin\NetworkController;
use App\Http\Controllers\Admin\SoftBlockController;
use App\Http\Controllers\Common\MassCancellationController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\DisputeResponseTemplateController;
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
use App\Http\Controllers\Common\PosController;
use App\Http\Controllers\Common\TimeSlotController;
use App\Http\Controllers\Common\SmsController;
use App\Http\Controllers\TourOperator\TourOperatorController;
use App\Http\Controllers\Common\BookingRequestController;
use App\Http\Controllers\Common\DepartmentController;
use App\Http\Controllers\Common\StripeController;
use App\Http\Controllers\Common\AddonFormController;
use App\Http\Controllers\Common\BoardController;
use App\Http\Controllers\Common\TipCenterController;
use App\Http\Controllers\Common\PaymentBreakdownController;
use App\Http\Controllers\Common\FilterPresetController;

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

Route::prefix('tour-operator')->as('tour-operator.')->middleware(['auth:tour_operator_staff', 'prevent-back-history', 'check-user-status'])->group(function () {
	Route::prefix('board')->name('board.')->group(function () {
		Route::get('/', [BoardController::class, 'index'])->name('index');
		Route::post('/stations/store', [BoardController::class, 'storeStation'])->name('store-station');
		Route::post('/stations/update/{id}', [BoardController::class, 'updateStation'])->name('update-station');
		Route::delete('/stations/delete/{id}', [BoardController::class, 'deleteStation'])->name('delete-station');

		Route::get('guide-management', [BoardController::class, 'guideManagement'])->name('guide-management');
		Route::post('assign-guide-and-booking', [BoardController::class, 'assignGuideAndBooking'])->name('assign-guide-and-booking');
		Route::post('unassign-booking', [BoardController::class, 'unAssignBooking'])->name('unassign-booking');
		Route::post('unassign-guide', [BoardController::class, 'unAssignGuide'])->name('unassign-guide');
		Route::post('station-update-order', [BoardController::class, 'updateStationOrder'])->name('update-station-order');
		Route::post('auto-assign', [BoardController::class, 'autoAssign'])->name('auto-assign');
		Route::post('bulk-assign', [BoardController::class, 'bulkAssign'])->name('bulk-assign');
		Route::post('split-booking-details', [BoardController::class, 'splitBookingDetails'])->name('split-booking-details');
		Route::post('bulk-checkin', [BoardController::class, 'bulkCheckIn'])->name('bulk-checkin');
		Route::post('swap-bookings', [BoardController::class, 'swapBookings'])->name('swap-bookings');
		Route::get('guides', [BoardController::class, 'guides'])->name('guides');
		Route::get('search-booking', [BoardController::class, 'searchBooking'])->name('search-booking');
		Route::post('transfer-booking', [BoardController::class, 'transferBooking'])->name('transfer-booking');
		Route::get('get-manual-update/{bookingId}', [BoardController::class, 'getManualUpdate'])->name('get-manual-update');
		Route::get('guide', [BoardController::class, 'guideBoard'])->name('guide');
		Route::get('update-guide-status', [BoardController::class, 'updateGuideStatus'])->name('update-guide-status');
		Route::get('guide-board-pdf', [BoardController::class, 'downloadGuideBoardPdf'])->name('guide-board-pdf');
		Route::post('manual-update/{bookingId}', [BoardController::class, 'manualUpdate'])->name('manual-update');
		Route::get('time-slot-transfer', [BoardController::class, 'timeSlotTransfer'])->name('time-slot-transfer');
		Route::post('station-packages/store', [BoardController::class, 'storeStationPackage'])->name('station-packages.store');
		Route::get('station-packages/edit/{id}', [BoardController::class, 'showEditStationPackage'])->name('station-packages.edit');
		Route::post('station-packages/update/{id}', [BoardController::class, 'updateStationPackage'])->name('station-packages.update');
		Route::delete('station-packages/{id}', [BoardController::class, 'destroyStationPackage'])->name('station-packages.destroy');
		Route::post('specific-stations-update-name', [BoardController::class, 'specificStationsUpdateName'])->name('specific-stations-update-name');
		Route::get('specific-stations-packages', [BoardController::class, 'getStationSpecificPackages'])->name('specific-stations-packages');
		Route::post('specific-stations-update-packages', [BoardController::class, 'updateStationSpecificPackages'])->name('specific-stations-update-packages');
		Route::get('admin-board-pdf', [BoardController::class, 'downloadAdminBoardPdf'])->name('admin-board-pdf');
		Route::post('guide-status-active-all', [BoardController::class, 'guideStatusActiveAll'])->name('guide-status-active-all');
		Route::post('auto-assign-guide', [BoardController::class, 'autoAssignGuide'])->name('auto-assign-guide');
		Route::post('ready-status-update', [BoardController::class, 'readyStatusUpdate'])->name('ready-status-update');
		Route::post('update-guide-capacity', [BoardController::class, 'updateGuideCapacity'])->name('update-guide-capacity');
		Route::post('update-bulk-capacity', [BoardController::class, 'updateBulkCapacity'])->name('update-bulk-capacity');
		Route::post('update-booking-checked', [BoardController::class, 'updateBookingChecked'])->name('update-booking-checked');
		Route::post('mark-guide-available', [BoardController::class, 'markGuideAvailable'])->name('mark-guide-available');
		Route::post('unlock-station', [BoardController::class, 'unlockStation'])->name('unlock-station');
	});
	Route::get('tip-center', [TipCenterController::class, 'dashboard'])->name('tip-center');

	Route::prefix('tip-center')->as('tip-center.')->group(function () {
		Route::get('profile', [TipCenterController::class, 'profile'])->name('profile');
		Route::post('update-profile', [TipCenterController::class, 'updateProfile'])->name('update-profile');
		Route::get('create-tip', [TipCenterController::class, 'createTip'])->name('create-tip');
		Route::post('make-tip-payment', [TipCenterController::class, 'makeTipPayment'])->name('make-tip-payment');
		Route::post('send-tip-payment-link', [TipCenterController::class, 'sendTipPaymentLink'])->name('send-tip-payment-link');
		Route::get('settings', [TipCenterController::class, 'settings'])->name('settings');
		Route::post('settings-list', [TipCenterController::class, 'settingsList'])->name('settings-list');
		Route::get('settings/edit/{id}', [TipCenterController::class, 'editGratuitySetting'])->name('edit-gratuity-setting');
		Route::post('save-gratuity-setting', [TipCenterController::class, 'saveGratuitySetting'])->name('save-gratuity-setting');

		Route::get('guides', [TipCenterController::class, 'guides'])->name('guides');
		Route::post('search-guide', [TipCenterController::class, 'searchGuide'])->name('search-guide');
		Route::get('bookings', [TipCenterController::class, 'bookings'])->name('bookings');
		Route::post('guide-bookings', [TipCenterController::class, 'guideBookings'])->name('guide-bookings');
		Route::get('guides/edit/{id}', [TipCenterController::class, 'editGuide'])->name('edit-guide');
		Route::post('update-guide-general-info', [TipCenterController::class, 'updatGuideGeneralInfo'])->name('update-guide-general-info');
		Route::post('update-guide-stripe-account-info', [TipCenterController::class, 'updatGuideStripeAccountInfo'])->name('update-guide-stripe-account-info');
		Route::post('update-guide-bank-account-info', [TipCenterController::class, 'updatGuideBankAccountInfo'])->name('update-guide-bank-account-info');

		Route::get('distributions', [TipCenterController::class, 'distributions'])->name('distributions');
		Route::post('distributions-list', [TipCenterController::class, 'distributionsList'])->name('distributions-list');
		Route::get('timeline', [TipCenterController::class, 'timeline'])->name('timeline');
		Route::get('distributions-export/{fromDate?}/{toDate?}/{payrollScheduleId?}', [TipCenterController::class, 'distributionsExport'])->name('distributions-export');
		Route::get('distribution-info/{id}', [TipCenterController::class, 'distributionInfo'])->name('distribution-info');
		Route::post('save-distribution-amounts', [TipCenterController::class, 'saveDistributionAmounts'])->name('save-distribution-amounts');
		Route::post('process-accept-tips', [TipCenterController::class, 'processAcceptTips'])->name('process-accept-tips');
		Route::post('process-bulk-accept-tips', [TipCenterController::class, 'processBulkAcceptTips'])->name('process-bulk-accept-tips');
		Route::post('process-bulk-complete-tips', [TipCenterController::class, 'processBulkCompleteTips'])->name('process-bulk-complete-tips');
	});
	Route::get('switch-tenant', [NetworkController::class, 'switchTenant'])->name('networks.switch-tenant');
	Route::post('update-status/{id}', [AdminTourOperatorCtrl::class, 'updateStatus'])->name('update-status');
	Route::post('update-general/{id}', [AdminTourOperatorCtrl::class, 'updateGeneral'])->name('update-general');
	Route::post('update-seo/{id}', [AdminTourOperatorCtrl::class, 'updateSEO'])->name('update-seo');
	Route::post('update-logo/{id}', [AdminTourOperatorCtrl::class, 'updateLogo'])->name('update-logo');
	Route::post('update-social-media/{id}', [AdminTourOperatorCtrl::class, 'updateSocialMedia'])->name('update-social-media');
	Route::post('update-report-setup/{id}', [AdminTourOperatorCtrl::class, 'updateReportSetup'])->name('update-report-setup');
	Route::post('update-affiliate-setup/{id}', [AdminTourOperatorCtrl::class, 'updateAffiliateSetup'])->name('update-affiliate-setup');
	Route::post('update-checkin-setup/{id}', [AdminTourOperatorCtrl::class, 'updateCheckinSetup'])->name('update-checkin-setup');
	Route::get('blackout-date', [AdminTourOperatorCtrl::class, 'blackoutDate'])->name('blackout-date');
	Route::post('add-blackout-date', [AdminTourOperatorCtrl::class, 'addBlackoutDate'])->name('add-blackout-date');
	Route::post('update-blackout-date', [AdminTourOperatorCtrl::class, 'updateBlackoutDate'])->name('update-blackout-date');
	Route::delete('delete-blackout-date/{id}', [AdminTourOperatorCtrl::class, 'deleteBlackoutDate'])->name('delete-blackout-date');
	Route::get('booking-request', [BookingRequestController::class, 'index'])->name('booking-request');
	Route::get('booking-request-info/{id}', [BookingRequestController::class, 'bookingRequestInfo'])->name('booking-request-info');
	Route::get('booking-approve', [BookingRequestController::class, 'bookingApprove'])->name('booking-approve');
	Route::get('booking-deny', [BookingRequestController::class, 'bookingDeny'])->name('booking-deny');
	Route::post('set-active-country', [CountryController::class, 'setActive'])->name('set.active.country');
	Route::get('get-cities/{stateId}', [CityController::class, 'getCities'])->name('get-cities');
	Route::get('get-states/{countryId}', [StateController::class, 'getStates'])->name('get-states');
	Route::post('email-tracker-search', [AmazonController::class, 'postSearch'])->name('email-tracker-search');
	Route::get('email-tracker-clear', [AmazonController::class, 'clearSearch'])->name('email-tracker-clear');
	Route::get('email-tracker-show/{id}', [AmazonController::class, 'getShowEmail'])->name('email-tracker-show');
	Route::get('email-tracker-url/{id}', [AmazonController::class, 'getUrlDetail'])->name('email-tracker-url');
	Route::post('logout', [AuthController::class, 'logout'])->name('logout')->withoutMiddleware([CheckUserStatus::class]);
	Route::get('dashboard', [TourOperatorController::class, 'dashboard'])->name('dashboard');
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
	Route::get('editoperatorprofile/{id}', [StaffController::class, 'editProfile'])->name('editoperatorprofile');
	Route::post('operatorprofile/{id}', [StaffController::class, 'updateProfile'])->name('operatorprofile');
	Route::post('lower-antelope-canyon-closure-email', [TourSlotController::class, 'lowerAntelopeCanyonClosureEmail'])->name('lower-antelope-canyon-closure-email');
	Route::get('get-tour-operator-affiliates/{id?}', [TourBookingController::class, 'getTourOperatorAffiliates'])->name('get-tour-operator-affiliates');
	Route::get('get-tour-packages/{tourOperatorId}', [TourPackageController::class, 'getPackages'])->name('get-packages');
	Route::get('package/edit/{id}/{year}', [TourPackageController::class, 'editpackage'])->name('editpackage');
	Route::post('package/update-settings/{id}', [TourPackageController::class, 'updateSettings'])->name('update-settings');
	Route::post('package/update-setting/{id}', [TourPackageController::class, 'updateSetting'])->name('update-setting');
	Route::get('affiliate/edit/{id}/{year}', [AffiliateController::class, 'editpackage'])->name('editpackageAffiliates');
	Route::post('affiliate/update-settings/{id}', [AffiliateController::class, 'updateSettings'])->name('update-settings-affiliate');
	Route::post('affiliate/assign-package/{id}', [AffiliateController::class, 'assignPackage'])->name('assign-package');
	Route::post('master-email-template/index-ahr', [EmailTemplateController::class, 'indexAHR'])->name('master-email-template');
	Route::post('{tour_package}/email-template/index-ahr', [TourPackageEmailTemplateController::class, 'indexAHR'])->name('package-email-template');
	Route::get('/booking-invoice/{bookingId}', [TourBookingController::class, 'bookingInvoice'])->name('booking-invoice');
	Route::get('/split-booking-modal/{bookingId}', [TourBookingController::class, 'splitBookingModalContent'])->name('split-booking-modal-content');
	Route::get('/bookings/{bookingId}/invoice/no-price', [TourBookingController::class, 'invoiceWithoutPrice'])->name('booking.invoice.no-price');
	Route::get('/get-release', [ReleaseController::class, 'getRelease'])->name('get-release');
	Route::get('release/view/{id}', [ReleaseController::class, 'view'])->name('release.view');
	Route::post('/release/{id}/send-notifications', [ReleaseController::class, 'sendNotifications'])->name('release.send-notifications');
	Route::get('/release/{id}/users', [ReleaseController::class, 'releaseUsers'])->name('release.users');
	Route::get('promotion-affiliate/{promotionId}', [TourPromotionController::class, 'promotionAffiliate'])->name('promotion-affiliate');
	Route::post('promotion-assign-to-affiliate', [TourPromotionController::class, 'promotionAssignToAffiliate'])->name('promotion-assign-to-affiliate');
	Route::delete('promotion-unassign-affiliate/{id}', [TourPromotionController::class, 'unassignAffiliate'])->name('promotion-unassign-affiliate');
	Route::post('update-user-type', [AffiliateController::class, 'userTypeUpdate'])->name('user-type-update');
	Route::get('agenda/{fromDate}/{toDate}/{tourPackageId?}/{tourCustomerId?}', [AgendaController::class, 'agenda'])->name('agenda');
	Route::get('download-affiliate-agenda/{fromDate}/{toDate}/{tourPackageId?}/{tourCustomerId?}', [AgendaController::class, 'downloadAffiliateAgenda'])->name('download-affiliate-agenda');

	Route::get('email-tracker', [AmazonController::class, 'getIndex'])->name('email-tracker');
	Route::resource('tenant', TenantController::class)->except(['show', 'edit', 'update', 'destroy']);
	Route::resource('admin-user', AdminUserController::class)->except(['show']);
	Route::get('admin-user-permission', [AdminUserController::class, 'adminUserPermission'])->name('admin-user-permission');
	Route::get('admin-user-permission-edit', [AdminUserController::class, 'adminUserPermissionEdit'])->name('admin-user-permission-edit');
	Route::get('admin-user-access-level', [AdminUserController::class, 'adminUserAccessLevel'])->name('admin-user-access-level');
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
	Route::get('role-permission-add', [RoleController::class, 'rolePermissionAdd'])->name('role-permission-add');
	Route::get('role-permission-edit', [RoleController::class, 'rolePermissionEdit'])->name('role-permission-edit');

	Route::prefix('stripe')->as('stripe.')->group(function () {
		Route::post('create-payment-intent', [StripeController::class, 'createPaymentIntent'])->name('create-payment-intent');
		Route::post('delete-payment-method', [StripeController::class, 'deletePaymentMethod'])->name('delete-payment-method');
		Route::post('create-payment-intent-make-payment', [StripeController::class, 'createPaymentIntentForMakePayment'])->name('create-payment-intent-make-payment');
		Route::post('create-payment-intent-pos', [StripeController::class, 'createStripePaymentIntentForPos'])->name('create-payment-intent-pos');
		Route::post('save-card-list', [StripeController::class, 'saveCardList'])->name('save-card-list');
	});

	Route::prefix('setting')->as('setting.')->group(function () {
		Route::get('edit', [SettingController::class, 'edit'])->name('edit');
		Route::post('update', [SettingController::class, 'update'])->name('update');
		Route::post('add-surcharge-date', [SettingController::class, 'addSurchargeDate'])->name('add-surcharge-date');
		Route::delete('detele-surcharge-date/{id}', [SettingController::class, 'deleteSurchargeDate'])->name('detele-surcharge-date');
		Route::put('update-stripe-setting', [SettingController::class, 'updateStripeSetting'])->name('update-stripe-setting');
		Route::get('get-stripe-settings', [SettingController::class, 'getStripeSettings'])->name('get-stripe-settings');
		Route::get('card-readers', [SettingController::class, 'cardReadersSettings'])->name('card-readers');
		Route::post('card-readers-actions', [SettingController::class, 'cardReaderActions'])->name('card-readers-actions');
		Route::get('printers', [SettingController::class, 'printerSettings'])->name('printers');
	});

	Route::prefix('staff')->as('staff.')->group(function () {
		Route::post('index-ahr', [StaffController::class, 'indexAHR'])->name('index-ahr');
		Route::post('update-general/{id}', [StaffController::class, 'updateGeneral'])->name('update-general');
		Route::post('update-role/{id}', [StaffController::class, 'updateRole'])->name('update-role');
	});
	Route::get('user-permission', [StaffController::class, 'userPermission'])->name('user-permission');
	Route::get('user-permission-edit', [StaffController::class, 'userPermissionEdit'])->name('user-permission-edit');
	Route::get('user-access-level', [StaffController::class, 'userAccessLevel'])->name('user-access-level');

	Route::get('pos', [PosController::class, 'initPos'])->name('pos');
	Route::prefix('pos')->as('pos.')->group(function () {
		Route::post('get-packages/{categoryId}', [PosController::class, 'getPackages'])->name('get-packages');
		Route::post('validate-cart', [PosController::class, 'validateCart'])->name('validate-cart');
		Route::post('manual-pin-check', [PosController::class, 'manualPinCheck'])->name('manual-pin-check');
		Route::post('apply-promo-code', [PosController::class, 'applyPromoCode'])->name('apply-promo-code');
		Route::post('create-bookings', [PosController::class, 'createBookings'])->name('create-bookings');
		Route::get('orders', [PosController::class, 'orders'])->name('orders');
		Route::get('order/{orderId}', [PosController::class, 'order'])->name('order');
		Route::get('print-receipt/{orderId}', [PosController::class, 'printReceipt'])->name('print-receipt');
		Route::post('send-receipt/{orderId}', [PosController::class, 'sendReceipt'])->name('send-receipt');
		Route::get('settings', [PosController::class, 'settings'])->name('settings');
		Route::post('update-settings', [PosController::class, 'updateSettings'])->name('update-settings');
		Route::post('cancel-order/{orderId}', [PosController::class, 'cancelOrder'])->name('cancel-order');
		Route::post('refund-order/{orderId}', [PosController::class, 'refundOrder'])->name('refund-order');
		Route::post('check-request/{orderId}', [PosController::class, 'checkRequest'])->name('check-request');
		Route::post('request-to-manager', [PosController::class, 'requestToManager'])->name('request-to-manager');
	});

	Route::prefix('report')->as('report.')->group(function () {
		Route::get('/', [SummarydetailsReportController::class, 'report'])->name('reportop');
		Route::get('/v2', [ReportV2Controller::class, 'index'])->name('reportop-v2'); // New report structure
		Route::post('/set-selected-report', [ReportV2Controller::class, 'setSelectedReport'])->name('set-selected-report'); // Store selected report in session
		Route::get('summary-details', [SummarydetailsReportController::class, 'summary']);
		Route::get('summary-details-data/{fromDate}/{toDate}/{tourOperatorId?}', [SummarydetailsReportController::class, 'indexAHR'])->name('filtered-data-for-summary-details');
		Route::get('summary-report', [SummaryReportController::class, 'summary_report']);
		Route::get('summary-reports-data/{fromDate}/{toDate}/{tourOperatorId?}', [SummaryReportController::class, 'indexAHR'])->name('filtered-data-for-summary-report');
		Route::get('booking-summary-report/{fromDate}/{toDate}/{tourOperatorId?}', [SummaryReportController::class, 'bookingSummaryList'])->name('booking-summary-list-report');
		Route::get('affiliate-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'affiliatereport'])->name('affiliate-report');
		Route::get('affiliate-seats-report', [SummaryReportController::class, 'affiliateSeatsReport'])->name('affiliate-seats-report');
		Route::get('affiliate-all-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'affiliateallreport'])->name('affiliate-all-report');
		Route::get('affiliate-comprehensive-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'affiliatecomprehensivereport'])->name('affiliate-comprehensive-report');
		Route::get('affiliate-seats-report', [SummaryReportController::class, 'affiliateSeatsReport'])->name('affiliate-seats-report');
		Route::get('affiliate-seats-sync', [SummaryReportController::class, 'affiliateSeatsSync'])->name('affiliate-seats-sync');
		Route::get('affiliate-seats-report-detail', [ReportController::class, 'getAffiliateSeatsReportDetail'])->name('affiliate-seats-report-detail');
		Route::get('cancel-booking-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'cancelbookingreport'])->name('cancel-booking-report');
		Route::get('email-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'emailreport'])->name('email-report');
		Route::get('tip-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'tipreport'])->name('tip-report');
		Route::get('daily-activity-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{viewBy?}/{export?}', [SummaryReportController::class, 'dailyactivityreport'])->name('daily-activity-report');
		Route::get('invalid-bookings-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'invalidbookingsreport'])->name('invalid-bookings-report');
		Route::get('tip-summary-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'tipsummaryreport'])->name('tip-summary-report');
		Route::get('daily-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'dailyreport'])->name('daily-report');
		Route::get('daily-report-details/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'dailyreportdetails'])->name('daily-report-details');
		Route::get('invoice-insights-report/{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}/{status?}', [SummaryReportController::class, 'invoiceInsightsReport'])->name('invoice-insights-report');
		Route::get('refundpayment-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}', [SummaryReportController::class, 'refundpayment'])->name('refund-payment-report');
		Route::get('fareharaborbooking-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummaryReportController::class, 'fareharaborbookingreport'])->name('fareharabor-booking-report');
		Route::get('revenue-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{viewBy?}', [SummaryReportController::class, 'revenueReport'])->name('revenue-report');
		Route::get('revenue-summation-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{viewBy?}', [SummaryReportController::class, 'revenuneSummationReport'])->name('revenue-summation-report');
		Route::get('stripe-refund-report/{bookingId?}', [SummaryReportController::class, 'stripeRefundReport'])->name('stripe-refund-report');
		Route::get('promotion-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{promotionId?}/{export?}', [SummaryReportController::class, 'promotionReport'])->name('promotion-report');
		Route::get('pendingpayment-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [SummarydetailsReportController::class, 'pendingReport'])->name('pending-payment-report');
		Route::get('today_avialability', [Today_avialabilityController::class, 'summary']);
		Route::get('today_avialability-data/{fromDate}/{tourOperatorId?}', [Today_avialabilityController::class, 'indexAHR'])->name('filtered-data-for-today_avialability');
		Route::get('today_avialability_report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [Today_avialabilityController::class, 'todayavialabilityList'])->name('today_avialability_report');
		Route::get('today_avialability_details', [Today_avialability_detailsController::class, 'summary']);
		Route::get('today_avialability_details-data/{fromDate}/{tourOperatorId?}', [Today_avialability_detailsController::class, 'indexAHR'])->name('filtered-data-for-today_avialability-details');
		Route::get('today_avialability_details_report/{fromDate}/{tourOperatorId?}', [Today_avialability_detailsController::class, 'todayavialabilityList'])->name('today_avialability_report-details');
		Route::post('sofrtware-fee', [ReportController::class, 'softwarefee'])->name('sofrtware-fee');
		Route::post('tax-collection', [ReportController::class, 'taxCollection'])->name('tax-collection');
		Route::post('payments', [ReportController::class, 'payments'])->name('payments');
		Route::get('affiliate-seats-report-detail', [ReportController::class, 'getAffiliateSeatsReportDetail'])->name('affiliate-seats-report-detail');
		Route::get('payments-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{selectedReport?}/{viewBy?}/{export?}/{type?}', [ReportController::class, 'paymentsExport'])->name('payments-export');
		Route::post('cardcollectionreport', [ReportController::class, 'cardcollection'])->name('cardcollection');
		Route::post('cashcollectionreport', [ReportController::class, 'cashcollection'])->name('cashcollection');
		Route::get('cash-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{selectedReport?}/{viewBy?}/{export?}', [ReportController::class, 'cashExport'])->name('cash-export');
		Route::get('transactionreport', [ReportController::class, 'transactionReport'])->name('transactionreport');
		Route::get('transaction-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}/{checkCard?}/{checkCash?}/{checkInvoiced?}/{checkCancel?}/{checkWire?}/{excludeExternal?}/{includeExternal?}/{onlyShowExternal?}/{includeExternalWithFee?}/{viewDate?}', [ReportController::class, 'transactionExport'])->name('transaction-export');
		Route::get('transactionsummaryreport', [ReportController::class, 'transactionSummaryReport'])->name('transactionsummaryreport');
		Route::get('transaction-summary-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}/{checkCard?}/{checkCash?}/{checkInvoiced?}/{checkCancel?}/{checkWire?}/{excludeExternal?}/{includeExternal?}/{onlyShowExternal?}/{includeExternalWithFee?}/{viewDate?}/{checkUnpaid?}', [ReportController::class, 'transactionSummaryExport'])->name('transaction-summary-export');
		Route::get('addonspackagereport', [ReportController::class, 'addonsPackageReport'])->name('addonspackagereport');
		Route::get('addons-package-report-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}/{addOnName?}', [ReportController::class, 'addonsPackageExport'])->name('addons-package-export');
		Route::get('addonspackagedetailsreport', [ReportController::class, 'addonsPackageDetailsReport'])->name('addonspackagedetailsreport');
		Route::get('addons-package-details-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{export?}/{addOnName?}', [ReportController::class, 'addonsPackageDetailsExport'])->name('addons-package-details-export');
		Route::get('addonspackagevaluereport', [ReportController::class, 'addonsPackageValueReport'])->name('addonspackagevaluereport');
		Route::post('wirecollectionreport', [ReportController::class, 'wirecollection'])->name('wirecollection');
		Route::get('wire-export/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{viewBy?}/{export?}', [ReportController::class, 'wireExport'])->name('wire-export');
		Route::post('financial-report', [ReportController::class, 'FinancialReport'])->name('financial-report');
		Route::get('closure-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}', [ReportController::class, 'lowerAntelopeCanyonClosureReport'])->name('closure-report');
		Route::get('get-customers/{tourOperatorId?}/{tourPackageId?}', [ReportController::class, 'getCustomers'])->name('get-customers');
		Route::get('staff-booking-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{viewBy?}', [SummaryReportController::class, 'staffBookingReport'])->name('staff-booking-report');
		Route::get('activity-logs-report', [SummaryReportController::class, 'activityLogsReport'])->name('activity-logs-report');
		Route::get('ota-report/{fromDate}/{toDate}/{tourOperatorId?}/{tourPackageId?}/{otaPlatform?}/{export?}', [ReportController::class, 'otaBookingReport'])->name('ota-report');
		
		// Payment Breakdown Report Routes
		Route::get('payment-breakdown-report', [PaymentBreakdownController::class, 'index'])->name('payment-breakdown-report');
		Route::get('payment-breakdown-report/data', [PaymentBreakdownController::class, 'getReportData'])->name('payment-breakdown-report-data');
		Route::get('payment-breakdown-report/export', [PaymentBreakdownController::class, 'export'])->name('payment-breakdown-report-export');
		Route::get('payment-breakdown-report/filters', [PaymentBreakdownController::class, 'getFilters'])->name('payment-breakdown-report-filters');
		Route::get('payment-breakdown-report/saved-filters', [PaymentBreakdownController::class, 'getSavedFilters'])->name('payment-breakdown-report-saved-filters');
		Route::delete('payment-breakdown-report/filters', [PaymentBreakdownController::class, 'clearSavedFilters'])->name('payment-breakdown-report-clear-filters');
		Route::post('payment-breakdown-report/real-time-calculations', [PaymentBreakdownController::class, 'getRealTimeCalculations'])->name('payment-breakdown-report-real-time');
		Route::post('payment-breakdown-report/validate-filters', [PaymentBreakdownController::class, 'validateFilters'])->name('payment-breakdown-report-validate-filters');
		Route::post('payment-breakdown-report/reset-filters', [PaymentBreakdownController::class, 'resetFilters'])->name('payment-breakdown-report-reset-filters');

		// Filter Preset Routes (for all reports)
		Route::prefix('filter-presets')->as('filter-presets.')->group(function () {
			Route::get('/', [FilterPresetController::class, 'index'])->name('index');
			Route::post('/', [FilterPresetController::class, 'store'])->name('store');
			Route::get('/{id}', [FilterPresetController::class, 'show'])->name('show');
			Route::put('/{id}', [FilterPresetController::class, 'update'])->name('update');
			Route::delete('/{id}', [FilterPresetController::class, 'destroy'])->name('destroy');
		});
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
		Route::post('add-update-affiliate-time-slots', [AffiliateController::class, 'addUpdateAffiliateTimeslots'])->name('affiliate-add-update-affiliate-time-slots');
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
	Route::prefix('soft-block')->as('soft-block.')->group(function () {
		Route::get('/', [SoftBlockController::class, 'index'])->name('index');
		Route::post('preview-soft-blocks', [SoftBlockController::class, 'previewSoftBlocks'])->name('preview-soft-blocks');
		Route::post('create-soft-blocks', [SoftBlockController::class, 'createSoftBlocks'])->name('create-soft-blocks');
		Route::post('fetch-soft-blocks', [SoftBlockController::class, 'fetchSoftBlocks'])->name('fetch-soft-blocks');
		Route::post('fetch-time-slots', [SoftBlockController::class, 'fetchTimeSlots'])->name('fetch-time-slots');
	});
	Route::prefix('mass-cancellation')->as('mass-cancellation.')->group(function () {
		Route::get('/', [MassCancellationController::class, 'index'])->name('index');
		Route::get('time-slots', [MassCancellationController::class, 'timeSlots'])->name('time-slots');
		Route::get('bookings', [MassCancellationController::class, 'bookings'])->name('bookings');
		Route::post('cancel', [MassCancellationController::class, 'cancel'])->name('cancel');
		Route::get('status/{massCancellation}', [MassCancellationController::class, 'status'])->name('status');
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
		Route::get('bulk-manager', [TourSlotController::class, 'bulkManager'])->name('bulk-manager');
		Route::get('distinct-slot-times', [TourSlotController::class, 'distinctSlotTimes'])->name('distinct-slot-times');
		Route::get('slots-in-range', [TourSlotController::class, 'slotsInRange'])->name('slots-in-range');
		Route::post('bulk-operation', [TourSlotController::class, 'storeBulkOperation'])->name('bulk-operation.store');
		Route::get('bulk-operations', [TourSlotController::class, 'bulkOperations'])->name('bulk-operations');
		Route::get('bulk-operations/applied', [TourSlotController::class, 'appliedBulkOperations'])->name('bulk-operations.applied');
		Route::post('bulk-operations/clear-history', [TourSlotController::class, 'clearBulkOperationsHistory'])->name('bulk-operations.clear-history');
		Route::post('bulk-operations/apply', [TourSlotController::class, 'applyBulkOperations'])->name('bulk-operations.apply');
		Route::put('bulk-operation/{operation}', [TourSlotController::class, 'updateBulkOperation'])->name('bulk-operation.update');
		Route::delete('bulk-operation/{operation}', [TourSlotController::class, 'destroyBulkOperation'])->name('bulk-operation.destroy');
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
		Route::get('history-and-transactions/{bookingId}', [TourBookingController::class, 'getBookingHistoryAndTransactions'])->name('history-and-transactions');
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
		Route::post('add-people-booking', [TourBookingController::class, 'addPeopleBooking'])->name('add-people-booking');
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

	Route::prefix('invoice')->as('invoice.')->group(function () {
		Route::get('list/{customerId?}/{tourOpId?}', [InvoiceController::class, 'list'])->name('list');
		Route::post('index-ahr', [InvoiceController::class, 'indexAHR'])->name('index-ahr');
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
		Route::post('check-exists', [InvoiceController::class, 'checkInvoiceExists'])->name('check-exists');
	});

	Route::prefix('time-slot')->as('time-slot.')->group(function () {
		Route::post('index-ahr/{tourOpId?}', [TimeSlotController::class, 'indexAHR'])->name('index-ahr');
		Route::post('index-package-ahr/{tourPkId?}', [TimeSlotController::class, 'indexPackageAHR'])->name('index-package-ahr');
		Route::post('add-update-time-slot', [TimeSlotController::class, 'addUpdateTimeSlot'])->name('add-update-time-slot');
		Route::post('add-update-time-slots', [TimeSlotController::class, 'addUpdateTimeSlots'])->name('add-update-time-slots');
		Route::delete('delete-time-slot/{timeSlotId}', [TimeSlotController::class, 'deleteTimeSlot'])->name('delete-time-slot');
	});

	// daily report export
	Route::get('daily-report-export', [ImportExportController::class, 'DailyReportExportCsvFile'])->name('daily_report_export');
	// Financial report export
	Route::get('financial-report-export', [ImportExportController::class, 'FinancialReportExportCsvFile'])->name('financial_report_export');
	// cancel booking report export
	Route::get('cancel-booking-report-export', [ImportExportController::class, 'CancelBookingReportExportCsvFile'])->name('cancel_booking_report_export');
	// daily report details export
	Route::get('daily-report-details-export', [ImportExportController::class, 'DailyReportDetailsExportCsvFile'])->name('daily_report_details_export');
	// booking details report export
	Route::get('booking-details-report-export', [ImportExportController::class, 'BookingDetailsReportExportCsvFile'])->name('booking_details_report_export');
	// tip report export
	Route::get('tip-report-export', [ImportExportController::class, 'TipReportExportCsvFile'])->name('tip_report_export');
	// tip summary report export
	Route::get('tip-summary-report-export', [ImportExportController::class, 'TipSummaryReportExportCsvFile'])->name('tip_summary_report_export');
	// tax collections report export
	Route::get('tax-collection-report-export', [ImportExportController::class, 'TaxCollectionReportExportCsvFile'])->name('tax_collection_report_export');
	// software fee report export
	Route::get('software-fee-report-export', [ImportExportController::class, 'SoftwareFeeReportExportCsvFile'])->name('software_fee_report_export');
	// card collection report export
	Route::get('cash-card-collection-report-export', [ImportExportController::class, 'CashCardCollectionReportExportCsvFile'])->name('cash-card_collection_report_export');
	// cash collection report export
	Route::get('cash-collection-report-export', [ImportExportController::class, 'CashCollectionReportExportCsvFile'])->name('cash_collection_report_export');
	// wire collection report export
	Route::get('wire-collection-report-export', [ImportExportController::class, 'WireCollectionReportExportCsvFile'])->name('wire_collection_report_export');
	// Failed payment report export
	Route::get('failed-payment-report-export', [ImportExportController::class, 'FailedPaymentReportExportCsvFile'])->name('failed_payment_report_export');
	// refund payment report export
	Route::get('refund-payment-report-export', [ImportExportController::class, 'RefundPaymentReportExportCsvFile'])->name('refund_payment_report_export');
	// Fareharbor booking report export
	Route::get('fareharborbooking-booking-report-export', [ImportExportController::class, 'FareharborBookingReportExportCsvFile'])->name('fareharborbooking_booking_report_export');
	// affiliate report export
	Route::get('affiliate-report-export', [ImportExportController::class, 'AffiliateReportExportCsvFile'])->name('affiliate_report_export');
	// affiliate Seats report export
	Route::get('affiliate_seats_report_export', [ImportExportController::class, 'AffiliateSeatsReportExportCsvFile'])->name('affiliate_seats_report_export');
	// affiliate All report export
	Route::get('affiliate-all-report-export', [ImportExportController::class, 'AffiliateAllReportExportCsvFile'])->name('affiliate_all_report_export');

	// affiliate Seats report export
	Route::get('affiliate_seats_report_export', [ImportExportController::class, 'AffiliateSeatsReportExportCsvFile'])->name('affiliate_seats_report_export');

	// affiliate Comprehensive report export
	Route::get('affiliate-comprehensive-report-export', [ImportExportController::class, 'AffiliateComprehensiveReportExportCsvFile'])->name('affiliate_comprehensive_report_export');
	// email report export
	Route::get('email-report-export', [ImportExportController::class, 'EmailReportExportCsvFile'])->name('email_report_export');
	// availability report export
	Route::get('today-avialability-report-export', [ImportExportController::class, 'TodayAvialabilityReportExportCsvFile'])->name('today_avialability_report_export');
	// invalid payment report export
	Route::get('invalid-payment-report-export', [ImportExportController::class, 'InvalidPaymentReportExportCsvFile'])->name('invalid_payment_report_export');
	// closure report export
	Route::get('closure-report-export', [ImportExportController::class, 'LowerAntelopeCanyonExportCsvFile'])->name('closure_report_export');
	//revenue report export
	Route::get('revenue-report-export', [ImportExportController::class, 'RevenuneReportExportCsvFile'])->name('revenue_report_export');
	// staff booking report export
	Route::get('staff-booking-report-export', [ImportExportController::class, 'staffBookingReportExportCsvFile'])->name('staff_booking_report_export');
	//promotion report export
	Route::get('promotion-report-export', [ImportExportController::class, 'PromotionReportExportCsvFile'])->name('promotion_report_export');
	// invoice insight report export
	Route::get('invoice-insights-export', [ImportExportController::class, 'InvoiceInsightsExportCsvFile'])->name('invoice_insights_export');
	// activity log report export
	Route::get('activity-logs-report-export', [ImportExportController::class, 'activityLogsReportExportCsvFile'])->name('activity_logs_report_export');
	// OTAs [TripAdvisor | GetYourGuide] report export
	Route::get('ota-report-export', [ImportExportController::class, 'otaReportExportCsvFile'])->name('ota_report_export');

	Route::resource('invoice', InvoiceController::class)->except(['show', 'list']);
	Route::resource('staff', StaffController::class)->except(['show', 'update']);
	Route::resource('package', TourPackageController::class)->except(['show', 'update']);
	Route::resource('promotion', TourPromotionController::class)->except(['show']);
	Route::resource('slot', TourSlotController::class)->except(['show', 'indexAHR']);
	Route::resource('time-slot', TimeSlotController::class)->except(['show', 'indexAHR']);
	Route::resource('booking', TourBookingController::class)->only(['create', 'store', 'show', 'destroy']);
	Route::resource('affiliate', AffiliateController::class)->except(['show']);
	Route::resource('time-slot', TimeSlotController::class)->except(['show', 'indexAHR']);
	Route::resource('master-email-template', EmailTemplateController::class)->except(['show']);
	Route::resource('{tour_package}/email-template', TourPackageEmailTemplateController::class)->except(['show']);
	Route::resource('release', ReleaseController::class);

	Route::prefix('disputes')->as('disputes.')->middleware(['dispute-security'])->group(function () {
		Route::get('/', [DisputeController::class, 'index'])->name('index');
		Route::post('/filters', [DisputeController::class, 'storeFilters'])->name('filters');
		Route::post('/filters/clear', [DisputeController::class, 'clearFilters'])->name('filters.clear');
		Route::get('/{disputeId}', [DisputeController::class, 'show'])->name('show')->where('disputeId', '[0-9]+');
		Route::post('/{disputeId}/evidence', [DisputeController::class, 'submitEvidence'])->name('submit-evidence')->where('disputeId', '[0-9]+');
		Route::put('/{disputeId}/status', [DisputeController::class, 'updateStatus'])->name('update-status')->where('disputeId', '[0-9]+');
		Route::get('/dashboard-data', [DisputeController::class, 'dashboardData'])->name('dashboard-data');
		Route::get('/{disputeId}/timeline', [DisputeController::class, 'timeline'])->name('timeline')->where('disputeId', '[0-9]+');
		Route::get('/analytics', [DisputeController::class, 'analytics'])->name('analytics');
		Route::post('/analytics/filters', [DisputeController::class, 'storeAnalyticsFilters'])->name('analytics.filters');
		Route::post('/analytics/filters/clear', [DisputeController::class, 'clearAnalyticsFilters'])->name('analytics.filters.clear');
		Route::get('/analytics/export', [DisputeController::class, 'exportAnalytics'])->name('analytics.export');
		Route::post('/{disputeId}/sync', [DisputeController::class, 'syncWithStripe'])->name('sync-stripe')->where('disputeId', '[0-9]+');
		Route::get('/{disputeId}/templates', [DisputeController::class, 'getResponseTemplates'])->name('templates')->where('disputeId', '[0-9]+');
		Route::post('/{disputeId}/apply-template', [DisputeController::class, 'applyResponseTemplate'])->name('apply-template')->where('disputeId', '[0-9]+');
	});

	Route::prefix('dispute-templates')->as('dispute-templates.')->middleware(['dispute-security'])->group(function () {
		Route::get('/', [DisputeResponseTemplateController::class, 'index'])->name('index');
		Route::post('/filters', [DisputeResponseTemplateController::class, 'storeFilters'])->name('filters');
		Route::post('/filters/clear', [DisputeResponseTemplateController::class, 'clearFilters'])->name('filters.clear');
		Route::get('/create', [DisputeResponseTemplateController::class, 'create'])->name('create');
		Route::get('/{template}', [DisputeResponseTemplateController::class, 'show'])->name('show');
		Route::get('/{template}/edit', [DisputeResponseTemplateController::class, 'edit'])->name('edit');
		Route::post('/', [DisputeResponseTemplateController::class, 'store'])->name('store');
		Route::put('/{template}', [DisputeResponseTemplateController::class, 'update'])->name('update');
		Route::delete('/{template}', [DisputeResponseTemplateController::class, 'destroy'])->name('destroy');
		Route::post('/{template}/activate', [DisputeResponseTemplateController::class, 'activate'])->name('activate');
		Route::post('/{template}/deactivate', [DisputeResponseTemplateController::class, 'deactivate'])->name('deactivate');
		Route::get('/{template}/preview', [DisputeResponseTemplateController::class, 'preview'])->name('preview');
		Route::get('/reason/{dispute_reason}', [DisputeResponseTemplateController::class, 'getTemplatesForReason'])->name('for-reason');
	});
});