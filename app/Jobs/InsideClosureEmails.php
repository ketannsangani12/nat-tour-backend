<?php

namespace App\Jobs;

use App\Mail\TourBooking\LowerAntelopeCanyonClosure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InsideClosureEmails implements ShouldQueue
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
     * @var $tourBooking
     */
    public $tourBooking;

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
     * Create a new job instance.
     *
     * @param  object  $tourBooking
	 * @param  int  $userId
     * @param  string  $userType
     * @return void
     */
    public function __construct($tourBooking, $userId, $userType)
    {
        $this->tourBooking = $tourBooking;
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
        $tourBooking = (object) $this->tourBooking;

        $arEmailData = [
            'bookingId' => $tourBooking->tbm_id,
            'tourPackageId' => $tourBooking->tour_package_id,
            'tourPackage' => $tourBooking->tour_package_name,
            'tourOperatorId' => $tourBooking->tour_operator_id,
            'tourOperator' => $tourBooking->tour_operator,
            'tourOpPhone' => $tourBooking->tour_op_phone,
            'tourOpEmail' => $tourBooking->tour_op_email,
            'tourOpWebsite' => $tourBooking->tour_op_website,
            'tourDate' => date('F jS, Y', strtotime($tourBooking->date)),
            'tourDateTime' => date('F jS, Y', strtotime($tourBooking->date)),
            'custName' => $tourBooking->customer_name,
            'custEmail' => $tourBooking->customer_email,
            'custPhone' => $tourBooking->customer_phone,
            'packageEmail' => $tourBooking->packageEmail,
            'packageFromName' => $tourBooking->packageFromName,
            'packageFromEmail' => $tourBooking->packageFromEmail,
            'packageApiKey' => $tourBooking->packageApiKey,
            'cancellationUrl' => tenant_route(tenant()->domains->first()->domain, 'customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($tourBooking->tbm_id)]),
            'operationBy' => $this->userId,
            'operationByUserType' => $this->userType,
            'emailType'=> config('constants.email_types.lower_antelope_canyon_closure'),
            'tenantId' => $tourBooking->tenant_id
        ];

        if (!empty($tourBooking->tour_op_logo)) {
            $tourOperatorLogo = tourOperatorLogo($tourBooking->tenant_id, $tourBooking->tour_op_logo);
            $arEmailData['tourOpLogo'] = $tourOperatorLogo;
        }

        $tourPkgPhoto = DB::table('tour_package_photos')
            ->where('tour_package_id', $tourBooking->tour_package_id)
            ->where('placement', 2)
            ->where('status', 'Active')
            ->latest()
            ->first(['path']);

        if ($tourPkgPhoto) {
            $tourPackageLogo = tourPackageLogo($tourBooking->tenant_id, $tourBooking->tour_package_id, $tourPkgPhoto->path);
            $arEmailData['tourPkgPhoto'] = $tourPackageLogo;
        }

        Mail::send(new LowerAntelopeCanyonClosure($arEmailData, 'lower_antelope_canyon_closure'));
    }
}
