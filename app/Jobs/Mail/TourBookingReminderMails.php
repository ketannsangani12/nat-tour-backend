<?php

namespace App\Jobs\Mail;

use App\Mail\TourBooking\TourBookingReminder;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TourBookingReminderMails implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 120;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * The number of seconds to wait before retrying the job.
	 *
	 * @var int
	 */
	public $backoff = 30;

	/**
	 * The tourBooking object.
	 *
	 * @var $tourBooking
	 */
	public $tourBooking;

	/**
	 * The tourOperatorLogo data.
	 *
	 * @var $tourOperatorLogo
	 */
	public $tourOperatorLogo;

	/**
	 * The packageLogo data.
	 *
	 * @var $packageLogo
	 */
	public $packageLogo;

	/**
	 * The packageImage data.
	 *
	 * @var $packageImage
	 */
	public $packageImage;

	/**
	 * Pre-fetched rate/seat breakdown for this booking (avoids a per-job DB query).
	 *
	 * @var \Illuminate\Support\Collection
	 */
	public $tourListwithSeat;

	public function __construct($tourBooking, $tourOperatorLogo, $packageLogo, $packageImage, $tourListwithSeat = null)
	{
		$this->tourBooking      = $tourBooking;
		$this->tourOperatorLogo = $tourOperatorLogo;
		$this->packageLogo      = $packageLogo;
		$this->packageImage     = $packageImage;
		$this->tourListwithSeat = $tourListwithSeat ?? collect();
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		try {
			$tourBooking = (object) $this->tourBooking;
			$fromDate = Carbon::parse(date('Y-m-d'));
			$throughDate = Carbon::parse(date('Y-m-d', strtotime($tourBooking->date)));
			$interval = $fromDate->diff($throughDate);
			$days = $interval->d;

			// Use a more efficient duplicate check with database locking
			$reminderDetails = "Reminder {$days} days prior to booking";

			// Use insertOrIgnore to prevent duplicates at database level
			$inserted = DB::table('tour_booking_history')->insertOrIgnore([
				'tour_booking_id' => $tourBooking->tbm_id,
				'operation' => 'Email',
				'details' => $reminderDetails,
				'operation_at' => Carbon::now(),
				'operation_by' => 1,
				'operation_by_user_type' => 'Admin',
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			]);

			// Only send email if we successfully inserted the history record
			if ($inserted) {
				// Resolve the tenant domain once — tenant()->domains is a lazy-loaded
				// relationship that may hit the DB; calling it three times in the same
				// job wastes repeated lookups.
				$tenantDomain = tenant()->domains->first()->domain;

				$packageLogos  = is_array($this->packageLogo)  ? $this->packageLogo  : [];
				$packageImages = is_array($this->packageImage) ? $this->packageImage : [];

				$arEmailData = [
					'bookingId' => $tourBooking->tbm_id,
					'tourOperatorId' => $tourBooking->tour_operator_id,
					'tourPackageId' => $tourBooking->tour_package_id,
					'tourOperator' => $tourBooking->tour_operator,
					'tourOpPhone' => $tourBooking->tour_op_phone,
					'tourOpEmail' => $tourBooking->tour_op_email,
					'tourOpWebsite' => $tourBooking->tour_op_website,
					'tourPackage' => $tourBooking->package_name,
					'tourDate' => date('l, F j, Y', strtotime($tourBooking->date)),
					'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($tourBooking->date . ' ' . $tourBooking->time_from)),
					'urlBookingInfo' => tenant_route($tenantDomain, 'customer.tour-booking-info', ['bookingId' => Crypt::encryptString($tourBooking->tbm_id)]),
					'custName' => $tourBooking->customer_name,
					'custEmail' => $tourBooking->customer_email,
					'custPhone' => $tourBooking->customer_phone,
					'urlLiabilityWaiver' => tenant_route($tenantDomain, 'customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($tourBooking->tbm_id)]),
					'importantNotes' => $tourBooking->important_notes,
					'thingsToBring' => $tourBooking->things_to_bring ? json_decode($tourBooking->things_to_bring) : [],
					'cancellationPolicy' => $tourBooking->cancellation_policy,
					'cancellationUrl' => tenant_route($tenantDomain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($tourBooking->tbm_id)]),
					'numberoftourist' => $tourBooking->total_tourists + $tourBooking->total_infants,
					'tourListwithSeat' => $this->tourListwithSeat,
					'packageEmail' => $tourBooking->packageEmail,
					'packageFromName' => $tourBooking->packageFromName,
					'packageFromEmail' => $tourBooking->packageFromEmail,
					'packageApiKey' => $tourBooking->packageApiKey,
					'emailType' => config('constants.email_types.reminder_before_seven_days'),
					'operationBy' => 1,
					'operationByUserType' => 'Admin',
					'tenantId' => $tourBooking->tenant_id
				];

				if (!empty($tourBooking->tour_op_logo)) {
					$arEmailData['tourOpLogo'] = $this->tourOperatorLogo;
				}

				if (isset($packageLogos[$tourBooking->tour_package_id])) {
					$arEmailData['tourPkgPhoto'] = $packageLogos[$tourBooking->tour_package_id];
				}

				if (isset($packageImages[$tourBooking->tour_package_id])) {
					$arEmailData['tourPkgImage'] = $packageImages[$tourBooking->tour_package_id];
				}

				Mail::send(new TourBookingReminder($arEmailData, 'reminder_before_seven_days'));
			}
		} catch (Exception $exception) {
			Log::error('TourBookingReminderMails job failed', [
				'booking_id' => $this->tourBooking->tbm_id ?? 'unknown',
				'error' => $exception->getMessage(),
				'trace' => $exception->getTraceAsString()
			]);
			throw $exception; // Re-throw to mark job as failed
		}
	}
}
