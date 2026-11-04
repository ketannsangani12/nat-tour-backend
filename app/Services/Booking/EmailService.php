<?php

namespace App\Services\Booking;

use App\Mail\TourBooking\BookingRescheduled;
use App\Mail\TourBooking\PeopleRemovedFromBooking;
use App\Mail\TourBooking\TapToPayPaymentStatus;
use App\Mail\TourBooking\TourBookingAcknowledgement;
use App\Mail\TourBooking\TourBookingCancelled;
use App\Mail\TourBooking\TourBookingRefund;
use App\Mail\TourBooking\TourBookingPaymentLink;
use App\Services\TourPackage\TourPackageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailService
{
	/**
	 * Execute the email service to send booking acknowledgement email.
	 *
	 * @param mixed $info
	 * @param mixed $emailData
	 *
	 * @return void
	 */
	public static function sendAcknowledgementEmail($info, $emailData): void
	{
		$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', '=', $emailData['bookingId'])
			->where('tbt.deleted_by', 0)
			->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
			->get();

		$totalTourists = 0;
		$totalInfants = 0;
		foreach ($tourListwithSeat as $value) {
			$totalTourists += $value->tourists;
			$totalInfants += $value->infants;
		}
		$arrivalDuration = $info->arrival_time_duration;
		$arrivalTime = '';
		if ($arrivalDuration > 0) {
			$arrivalTime = Carbon::createFromFormat('H:i:s', $info->time_from)
				->subMinutes($arrivalDuration)
				->format('H:i:s');
		}

		$arEmailData = [
			'bookingId' => $emailData['bookingId'],
			'bookingGroupId' => $emailData['bookingGroupId'],
			'externalBookingId' => $info->external_booking_id ?? null,
			'tourOperatorId' => $info->tour_operator_id,
			'tourPackageId' => $info->tour_package_id,
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'tourPackage' => $info->package_name,
			'tourDate' => date('l, F j, Y', strtotime($emailData['tour_d_t'])),
			'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($emailData['tour_d_t'])),
			'arrivalTime' => ($arrivalTime != '') ? date('g:i a', strtotime($arrivalTime)) : '',
			'urlBookingInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'custName' => $emailData['custName'] ?? '',
			'custEmail' => $emailData['custEmail'] ?? '',
			'custPhone' => $emailData['custPhone'] ?? '',
			'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'importantNotes' => $info->important_notes,
			'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
			'cancellationPolicy' => $info->cancellation_policy,
			'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'numberoftourist' => $totalTourists + $totalInfants,
			'tourListwithSeat' => $tourListwithSeat,
			'packageEmail' => $info->packageEmail,
			'packageFromName' => $info->packageFromName,
			'packageFromEmail' => $info->packageFromEmail,
			'packageApiKey' => $info->packageApiKey,
			'waiverPdf' => $info->waiver_pdf ?? null,
			'attachWaiverPdf' => $info->attach_waiver_pdf ?? null,
			'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
			'tenantId' => $info->tenant_id
		];

		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}

		$tourPkgPhoto = DB::table('tour_package_photos')
			->where('tour_package_id', $info->tour_package_id)
			->where('placement', 2)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		if ($tourPkgPhoto) {
			$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
			$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
		}

		$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
		$arEmailData['tourPkgImage'] = $tourPackageImage;

		if (isset($emailData['pwd']) && $emailData['pwd'] != '') {
			$arEmailData['password'] = $emailData['pwd'];
		}

		Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));
	}

	/**
	 * Execute the email service to send booking cancelled email.
	 *
	 * @param mixed $info
	 * @param mixed $emailData
	 *
	 * @return void
	 */
	public static function sendBookingCancelledEmail($info, $emailData): void
	{
		$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', '=', $emailData['bookingId'])
			->where('tbt.deleted_by', 0)
			->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
			->get();

		$totalTourists = 0;
		$totalInfants = 0;
		foreach ($tourListwithSeat as $value) {
			$totalTourists += $value->tourists;
			$totalInfants += $value->infants;
		}

		$arEmailData = [
			'bookingId' => $emailData['bookingId'],
			'bookingGroupId' => $emailData['bookingGroupId'],
			'tourOperatorId' => $info->tour_operator_id,
			'tourPackageId' => $info->tour_package_id,
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'tourPackage' => $info->package_name,
			'tourDate' => date('l, F j, Y', strtotime($emailData['tour_d_t'])),
			'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($emailData['tour_d_t'])),
			'urlBookingInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'custName' => $emailData['custName'] ?? '',
			'custEmail' => $emailData['custEmail'] ?? '',
			'custPhone' => $emailData['custPhone'] ?? '',
			'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'importantNotes' => $info->important_notes,
			'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
			'cancellationPolicy' => $info->cancellation_policy,
			'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'numberoftourist' => $totalTourists + $totalInfants,
			'tourListwithSeat' => $tourListwithSeat,
			'cardNumber' => $emailData['cardNumber'] ?? '',
			'refundedAmount' => $emailData['cancelRefundedAmount'] ?? '',
			'packageEmail' => $info->packageEmail,
			'packageFromName' => $info->packageFromName,
			'packageFromEmail' => $info->packageFromEmail,
			'packageApiKey' => $info->packageApiKey,
			'emailType' => config('constants.email_types.tour_booking_cancelled'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
			'tenantId' => $info->tenant_id
		];

		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}

		$tourPkgPhoto = DB::table('tour_package_photos')
			->where('tour_package_id', $info->tour_package_id)
			->where('placement', 2)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		if ($tourPkgPhoto) {
			$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
			$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
		}

		$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
		$arEmailData['tourPkgImage'] = $tourPackageImage;

		Mail::send(new TourBookingCancelled($arEmailData, 'tour_booking_cancelled'));
	}

	/**
	 * Execute the email service to send booking cancelled email.
	 *
	 * @param mixed $info
	 * @param mixed $emailData
	 *
	 * @return void
	 */
	public static function sendBookingReschuduleEmail($info, $emailData): void
	{
		$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', '=', $emailData['bookingId'])
			->where('tbt.deleted_by', 0)
			->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
			->get();

		$totalTourists = 0;
		$totalInfants = 0;
		foreach ($tourListwithSeat as $value) {
			$totalTourists += $value->tourists;
			$totalInfants += $value->infants;
		}

		$arEmailData = [
			'bookingId' => $emailData['bookingId'],
			'tourOperatorId' => $info->tour_operator_id,
			'tourPackageId' => $info->tour_package_id,
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'tourPackage' => $info->package_name,
			'tourDate' => date('l, F j, Y', strtotime($emailData['tour_d_t'])),
			'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($emailData['tour_d_t'])),
			'urlBookingInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'custName' => $emailData['custName'] ?? '',
			'custEmail' => $emailData['custEmail'] ?? '',
			'custPhone' => $emailData['custPhone'] ?? '',
			'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'importantNotes' => $info->important_notes,
			'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
			'cancellationPolicy' => $info->cancellation_policy,
			'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'numberoftourist' => $totalTourists + $totalInfants,
			'tourListwithSeat' => $tourListwithSeat,
			'rescheduledDateTime' => $emailData['rescheduledDateTime'],
			'packageEmail' => $info->packageEmail,
			'packageFromName' => $info->packageFromName,
			'packageFromEmail' => $info->packageFromEmail,
			'packageApiKey' => $info->packageApiKey,
			'emailType' => config('constants.email_types.booking_rescheduled'),
			// Attribution can be supplied by callers without an Auth context (e.g. queued bulk jobs);
			// falls back to the logged-in user for the normal web reschedule flow.
			'operationBy' => $emailData['operationBy'] ?? Auth::id(),
			'operationByUserType' => $emailData['operationByUserType'] ?? userType(),
			'tenantId' => $info->tenant_id
		];

		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}

		$tourPkgPhoto = DB::table('tour_package_photos')
			->where('tour_package_id', $info->tour_package_id)
			->where('placement', 2)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		if ($tourPkgPhoto) {
			$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
			$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
		}

		$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
		$arEmailData['tourPkgImage'] = $tourPackageImage;

		Mail::send(new BookingRescheduled($arEmailData, 'booking_rescheduled'));
	}

	/**
	 * Execute the email service to send booking cancelled email.
	 *
	 * @param mixed $info
	 * @param mixed $emailData
	 *
	 * @return void
	 */
	public static function sendBookingRefundEmail($info, $emailData): void
	{
		$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', '=', $emailData['bookingId'])
			->where('tbt.deleted_by', 0)
			->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
			->get();

		$totalTourists = 0;
		$totalInfants = 0;
		foreach ($tourListwithSeat as $value) {
			$totalTourists += $value->tourists;
			$totalInfants += $value->infants;
		}

		$arEmailData = [
			'bookingId' => $emailData['bookingId'],
			'tourOperatorId' => $info->tour_operator_id,
			'tourPackageId' => $info->tour_package_id,
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'tourPackage' => $info->package_name,
			'tourDate' => date('l, F j, Y', strtotime($emailData['tour_d_t'])),
			'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($emailData['tour_d_t'])),
			'urlBookingInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'custName' => $emailData['custName'] ?? '',
			'custEmail' => $emailData['custEmail'] ?? '',
			'custPhone' => $emailData['custPhone'] ?? '',
			'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'importantNotes' => $info->important_notes,
			'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
			'cancellationPolicy' => $info->cancellation_policy,
			'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'numberoftourist' => $totalTourists + $totalInfants,
			'tourListwithSeat' => $tourListwithSeat,
			'refundedAmount' => $emailData['refundedAmount'],
			'packageEmail' => $info->packageEmail,
			'packageFromName' => $info->packageFromName,
			'packageFromEmail' => $info->packageFromEmail,
			'packageApiKey' => $info->packageApiKey,
			'emailType' => config('constants.email_types.tour_booking_refund'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
			'tenantId' => $info->tenant_id
		];

		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}

		$tourPkgPhoto = DB::table('tour_package_photos')
			->where('tour_package_id', $info->tour_package_id)
			->where('placement', 2)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		if ($tourPkgPhoto) {
			$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
			$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
		}

		$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
		$arEmailData['tourPkgImage'] = $tourPackageImage;

		Mail::send(new TourBookingRefund($arEmailData, 'tour_booking_refund'));
	}

	/**
	 * Execute the email service to send booking cancelled email.
	 *
	 * @param mixed $info
	 * @param mixed $emailData
	 *
	 * @return void
	 */
	public static function sendPeopleRemovedEmail($info, $emailData): void
	{
		$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', '=', $emailData['bookingId'])
			->where('tbt.deleted_by', 0)
			->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
			->get();

		$totalTourists = 0;
		$totalInfants = 0;
		foreach ($tourListwithSeat as $value) {
			$totalTourists += $value->tourists;
			$totalInfants += $value->infants;
		}

		$arEmailData = [
			'bookingId' => $emailData['bookingId'],
			'tourOperatorId' => $info->tour_operator_id,
			'tourPackageId' => $info->tour_package_id,
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'tourPackage' => $info->package_name,
			'tourDate' => date('l, F j, Y', strtotime($emailData['tour_d_t'])),
			'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($emailData['tour_d_t'])),
			'urlBookingInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'custName' => $emailData['custName'] ?? '',
			'custEmail' => $emailData['custEmail'] ?? '',
			'custPhone' => $emailData['custPhone'] ?? '',
			'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'importantNotes' => $info->important_notes,
			'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
			'cancellationPolicy' => $info->cancellation_policy,
			'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($emailData['bookingId'])]),
			'numberoftourist' => $totalTourists + $totalInfants,
			'tourListwithSeat' => $tourListwithSeat,
			'deletedPeople' => $emailData['deletedPeople'],
			'packageEmail' => $info->packageEmail,
			'packageFromName' => $info->packageFromName,
			'packageFromEmail' => $info->packageFromEmail,
			'packageApiKey' => $info->packageApiKey,
			'emailType' => config('constants.email_types.people_remove_from_booking'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
			'tenantId' => $info->tenant_id
		];

		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}

		$tourPkgPhoto = DB::table('tour_package_photos')
			->where('tour_package_id', $info->tour_package_id)
			->where('placement', 2)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		if ($tourPkgPhoto) {
			$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
			$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
		}

		$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
		$arEmailData['tourPkgImage'] = $tourPackageImage;

		Mail::send(new PeopleRemovedFromBooking($arEmailData, 'people_remove_from_booking'));
	}

	/**
	 * Execute the email service to send payment link email
	 *
	 * @param mixed $info
	 * @param mixed $tourBooking
	 * @param mixed $paymentLink
	 *
	 * @return void
	 */
	public static function sendPaymentLinkEmail($info, $tourBooking = null, $paymentLink): void
	{
		$userType = userType();
		$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', '=', $tourBooking->id)
			->where('tbt.deleted_by', 0)
			->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
			->get();

		$slotTime = TourPackageService::getTourSlotInfo($tourBooking->tour_slot_id, $userType);
		$tour_d_t = "{$slotTime->date} {$slotTime->time_from}";

		$totalTourists = 0;
		$totalInfants = 0;
		foreach ($tourListwithSeat as $value) {
			$totalTourists += $value->tourists;
			$totalInfants += $value->infants;
		}

		$arEmailData = [
			'bookingId' => $tourBooking->id,
			'bookingGroupId' => $tourBooking->group_id,
			'externalBookingId' => $info->external_booking_id ?? null,
			'tourOperatorId' => $info->tour_operator_id,
			'tourPackageId' => $info->tour_package_id,
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'tourPackage' => $info->package_name,
			'tourDate' => date('l, F j, Y', strtotime($tour_d_t)),
			'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($tour_d_t)),
			'paymentLinkReasonOfPayment' => $paymentLink->reason_of_payment,
			'paymentLinkUrlInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.payment', ['token' => $paymentLink->token]),
			'custName' => $tourBooking->name ?? '',
			'custEmail' => $tourBooking->email ?? '',
			'custPhone' => $tourBooking->phone_number ?? '',
			'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($tourBooking->id)]),
			'importantNotes' => $info->important_notes,
			'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
			'cancellationPolicy' => $info->cancellation_policy,
			'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($tourBooking->id)]),
			'numberoftourist' => $totalTourists + $totalInfants,
			'tourListwithSeat' => $tourListwithSeat,
			'packageEmail' => $info->packageEmail,
			'packageFromName' => $info->packageFromName,
			'packageFromEmail' => $info->packageFromEmail,
			'packageApiKey' => $info->packageApiKey,
			'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
			'tenantId' => $info->tenant_id,
			'expiredDateTime' => date('l, F j, Y', strtotime($paymentLink->expired_at)),
			'custPayAmount' => $paymentLink->amount,
		];

		if ($tourBooking->cart_id) {
			$arEmailData['urlBookingInfo'] = tenant_route(tenant()->domains->first()->domain, 'customer.payment-link-tour-booking-info', ['bookingId' => Crypt::encryptString($tourBooking->cart_id)]);
		} else {
			$arEmailData['urlBookingInfo'] = tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($tourBooking->id)]);
		}

		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}

		$tourPkgPhoto = DB::table('tour_package_photos')
			->where('tour_package_id', $info->tour_package_id)
			->where('placement', 2)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->latest()
			->first(['path']);

		if ($tourPkgPhoto) {
			$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
			$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
		}

		$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
		$arEmailData['tourPkgImage'] = $tourPackageImage;

		// if (isset($emailData['pwd']) && $emailData['pwd'] != '') {
		// 	$arEmailData['password'] = $emailData['pwd'];
		// }

		Mail::send(new TourBookingPaymentLink($arEmailData, 'new_payment_link'));
	}

	/**
	 * Execute the email service to send booking acknowledgement email.
	 *
	 * @param mixed $info
	 * @param mixed $emailData
	 *
	 * @return void
	 */
	public static function sendTapToPayPaymentMail($info, $emailData): void
	{

		$arEmailData = [
			'tourOperator' => $info->tour_operator,
			'tourOpPhone' => $info->tour_op_phone,
			'tourOpEmail' => $info->tour_op_email,
			'tourOpWebsite' => $info->tour_op_website,
			'guideName' => $emailData['guideName'],
			'custEmail' => $emailData['custEmail'],
			'paymentStatus' => $emailData['paymentStatus'],
			'custPayAmount' => $emailData['custPayAmount'],
			'emailType' => config('constants.email_types.tap_to_pay_payment'),
			'operationBy' => Auth::id(),
			'operationByUserType' => userType(),
			'tenantId' => $info->tenant_id
		];
		if (!empty($info->tour_op_logo)) {
			$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
			$arEmailData['tourOpLogo'] = $tourOperatorLogo;
		}


		Mail::send(new TapToPayPaymentStatus($arEmailData, 'tap_to_pay_payment'));
	}
}
