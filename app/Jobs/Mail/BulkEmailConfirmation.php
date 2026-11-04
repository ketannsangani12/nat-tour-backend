<?php

namespace App\Jobs\Mail;

use App\Mail\TourBooking\TourBookingAcknowledgement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BulkEmailConfirmation implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 0;

	/**
	 * The id int.
	 *
	 * @var $id
	 */
	public $id;

	/**
	 * The user ID int.
	 *
	 * @var $userId
	 */
	public $userId;

	/**
	 * The user type string.
	 *
	 * @var $userType
	 */
	public $userType;

	/**
	 * Create a new job instance.
	 *
	 * @param  int id
	 * @return void
	 */
	public function __construct($id, $userId, $userType)
	{
		$this->id = $id;
		$this->userId = $userId;
		$this->userType = $userType;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		try {
			$bookingData = DB::table('tour_bookings_master as bm')
                ->join('customers as cus', 'cus.id', '=', 'bm.customer_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'bm.tour_package_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->join('tour_slot_times as st', 'st.id', '=', 'bm.tour_slot_id')
                ->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
                ->where('bm.id', '=', $this->id)
                ->where('bm.status', 'Booked')
                ->select(
                    'bm.id as TourBookingID',
                    'bm.total as total',
                    'bm.name as Name',
                    'bm.email as Email',
                    'bm.phone_number as ContactNumber',
                    'bm.discount2_value',
                    'bm.tourists',
                    'bm.infants',
                    'bm.tour_promotion_id',
                    'bm.hotel_id',
                    'sd.date as Date',
                    'st.time_from as StartTime',
                    'st.time_to as EndTime',
                    'tp.name as TourPkgName',
                    't_o.logo AS tour_op_logo',
                    'tp.id AS tour_package_id',
                    'cus.id as customer_id',
                    'cus.name as customer_name',
                    'cus.email as customer_email',
                    'cus.phone_number as customer_phone_number',
                    'cus.is_affiliate',
                    't_o.name as tour_operator_name',
                    'st.id as tour_slot_time_id',
                    'tp.stripe_destination_id',
                    't_o.id As tour_operator_id',
                    'tp.name as packageName',
                    'tp.email as packageEmail',
                    'tp.from_name as packageFromName',
                    'tp.from_email as packageFromEmail',
                    'tp.api_key as packageApiKey',
                    't_o.phone_number As tourOpPhone',
                    't_o.email As tourOpEmail',
                    't_o.website As tourOpWebsite',
                    'tp.important_notes',
                    'tp.things_to_bring',
                    't_o.cancellation_policy As cancellationPolicy',
                    't_o.tenant_id'
                )
                ->first();

            if ($bookingData) {
                $tourListwithSeat = DB::table('tour_bookings_transact as tbm')
                    ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbm.tour_package_rate_id')
                    ->where('tbm.tour_booking_id', '=', $this->id)
                    ->select('tpr.rate_for', 'tbm.tourists', 'tbm.infants')
                    ->get();

                $bookingTime = "{$bookingData->Date} {$bookingData->StartTime}";
                $bookingData->tourDate = date('l, F j, Y', strtotime($bookingTime));
                $bookingData->tourDateTime = date('l, F j, Y @ g:i a', strtotime($bookingTime));

                $id = Crypt::encryptString($this->id);
                $bookingData->urlBookingInfo = route('customer.tour-booking-info', ['bookingId' => $id]);
                $bookingData->urlLiabilityWaiver = route('customer.liability-waiver-form', ['bookingId' => $id]);
                $bookingData->cancellationUrl = route('customer.confirm-cancel-booking', ['bookingId' => $id]);

                if (!empty($bookingData->tour_op_logo)) {
                    $bookingData->logo = tourOperatorLogo($bookingData->tenant_id, $bookingData->tour_op_logo);
                }

                $tourPkgPhoto = DB::table('tour_package_photos')
                    ->where('tour_package_id', $bookingData->tour_package_id)
                    ->where('placement', 2)
                    ->where('status', 'Active')
                    ->latest()
                    ->first([ 'path' ]);

                if (isset($tourPkgPhoto) && !empty($tourPkgPhoto)) {
                    $bookingData->tourPkgPhoto = tourPackageLogo($bookingData->tenant_id, $bookingData->tour_package_id, $tourPkgPhoto->path);;
                }

				$tourPackageImage = tourPackageImage($bookingData->tenant_id, $bookingData->tour_package_id);
				$bookingData->tourPkgImage = $tourPackageImage;

                $bookingData->thingsToBring = $bookingData->things_to_bring ? json_decode($bookingData->things_to_bring) : [];

                $arEmailData = [
                    'bookingId' => $bookingData->TourBookingID,
                    'tourOperatorId' => $bookingData->tour_operator_id,
                    'tourPackageId' => $bookingData->tour_package_id,
                    'tourOperator' => $bookingData->tour_operator_name,
                    'tourOpPhone' => $bookingData->tourOpPhone,
                    'tourOpEmail' => $bookingData->tourOpEmail,
                    'tourOpWebsite' => $bookingData->tourOpWebsite,
                    'tourPackage' => $bookingData->TourPkgName,
                    'tourDate' => $bookingData->tourDate,
                    'tourDateTime' => $bookingData->tourDateTime,
                    'urlBookingInfo' => $bookingData->urlBookingInfo,
                    'custName' => $bookingData->Name,
                    'custEmail' => $bookingData->Email,
                    'custPhone' => $bookingData->ContactNumber,
                    'urlLiabilityWaiver' => $bookingData->urlLiabilityWaiver,
                    'importantNotes' => $bookingData->important_notes,
                    'thingsToBring' => $bookingData->thingsToBring,
                    'cancellationPolicy' => $bookingData->cancellationPolicy,
                    'cancellationUrl' => $bookingData->cancellationUrl,
                    'numberoftourist' => $bookingData->tourists + $bookingData->infants,
                    'tourListwithSeat' => $tourListwithSeat,
                    'packageEmail' => $bookingData->packageEmail,
                    'packageFromName' => $bookingData->packageFromName,
                    'packageFromEmail' => $bookingData->packageFromEmail,
                    'packageApiKey' => $bookingData->packageApiKey,
                    'tourOpLogo' => $bookingData->logo ?? null,
                    'tourPkgPhoto' => $bookingData->tourPkgPhoto ?? null,
                    'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
                    'operationBy' => $this->userId,
                    'operationByUserType' => $this->userType,
					'tourPkgImage' => $bookingData->tourPkgImage ?? null,
                    'tenantId' => $bookingData->tenant_id
                ];

                Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));
            }
		} catch (Exception $exception) {
			Log::error($exception);
		}
	}
}
