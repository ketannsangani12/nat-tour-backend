<?php

namespace App\Jobs;

use App\Mail\TourBooking\TourBookingAcknowledgement;
use App\Mail\TourBooking\TourBookingPaymentLink;
use App\Models\TourBooking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CartBookingsEmails implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 0;

	/**
	 * The data object.
	 *
	 * @var $tourBookings
	 */
	public $tourBookings;

	/**
	 * The user ID.
	 *
	 * @var $userId
	 */
	public $userId;

	/**
	 * The user type.
	 *
	 * @var $userType
	 */
	public $userType;
	/**
	 * The tour operator ID.
	 *
	 * @var $tourOperatorId
	 */
	public $tourOperatorId;
	/**
	 * The logged in user email.
	 *
	 * @var $authEmail
	 */
	public $authEmail;

	/**
	 * Create a new job instance.
	 *
	 * @param  mixed  $tourBookings
	 * @param  int  $userId
	 * @param  string  $userType
	 * @param  int  $tourOperatorId
	 * @param  string  $authEmail
	 * @return void
	 */
	public function __construct($tourBookings, $userId, $userType, $tourOperatorId, $authEmail)
	{
		$this->tourBookings = $tourBookings;
		$this->userId = $userId;
		$this->userType = $userType;
		$this->tourOperatorId = $tourOperatorId;
		$this->authEmail = $authEmail;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		$count = 0;
		$tourBookings = (array) $this->tourBookings;
		foreach ($tourBookings as $data) {
			$count++;

			$info = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('tst.id', $data['slot_time'])
				->select([
					'tst.time_from',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'tp.arrival_time_duration',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					't_o.tenant_id',
					'tp.stripe_destination_id',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					'tp.attach_waiver_pdf',
					'tp.waiver_pdf',
					DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();

			$tourBooking = TourBooking::find($data['booking_id']);

			$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
				->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', '=', $data['booking_id'])
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
			$tour_d_t = "{$info->date} {$info->time_from}";

			$arEmailData = [
				'bookingId' => $data['booking_id'],
				'bookingGroupId' => $data['group_id'],
				'tourOperatorId' => $info->tour_operator_id,
				'tourPackageId' => $info->tour_package_id,
				'tourOperator' => $info->tour_operator,
				'tourOpPhone' => $info->tour_op_phone,
				'tourOpEmail' => $info->tour_op_email,
				'tourOpWebsite' => $info->tour_op_website,
				'tourPackage' => $info->package_name,
				'tourDate' => date('l, F j, Y', strtotime($tour_d_t)),
				'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($tour_d_t)),
				'arrivalTime' => ($arrivalTime != '') ? date('g:i a', strtotime($arrivalTime)) : '',
				'urlBookingInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($data['booking_id'])]),
				'custName' => $tourBooking->name ?? '',
				'custEmail' => $tourBooking->email ?? '',
				'custPhone' => $tourBooking->phone_number ?? '',
				'urlLiabilityWaiver' => tenant_route(tenant()->domains->first()->domain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($data['booking_id'])]),
				'importantNotes' => $info->important_notes,
				'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
				'cancellationPolicy' => $info->cancellation_policy,
				'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($data['booking_id'])]),
				'numberoftourist' => $totalTourists + $totalInfants,
				'tourListwithSeat' => $tourListwithSeat,
				'packageEmail' => $info->packageEmail,
				'packageFromName' => $info->packageFromName,
				'packageFromEmail' => $info->packageFromEmail,
				'packageApiKey' => $info->packageApiKey,
				'waiverPdf' => $info->waiver_pdf ?? null,
				'attachWaiverPdf' => $info->attach_waiver_pdf ?? null,
				'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
				'operationBy' => $this->userId,
				'operationByUserType' => $this->userType,
				'tenantId' => $info->tenant_id
			];

			if (!empty($info->tour_op_logo)) {
				$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
				$arEmailData['tourOpLogo'] = $tourOperatorLogo;
			}

			// $tourBookingPaymentLink = \App\Models\TourBookingPaymentLink::where('tour_booking_id', $data['booking_id'])->first();
			/*if (!empty($tourBookingPaymentLink)) {
				$arEmailData['paymentLinkReasonOfPayment'] = $tourBookingPaymentLink->reason_of_payment;
				$arEmailData['paymentLinkUrlInfo'] = route('customer.payment', ['token' => $tourBookingPaymentLink->token]);
			} else {*/
			// $arEmailData['urlBookingInfo'] = tenant_route(tenant()->domains->first()->domain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($data['booking_id'])]);
			// }

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

			if (in_array($this->authEmail, config('constants.prevent_sending_emails')) == false) {
				/*if (!empty($tourBookingPaymentLink)) {
					Mail::send(new TourBookingPaymentLink($arEmailData, 'payment_link'));
				} else {*/
				Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));
				/*}*/
			}
		}
	}
}
