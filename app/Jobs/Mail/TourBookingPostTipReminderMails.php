<?php

namespace App\Jobs\Mail;

use App\Mail\TourBooking\TourBookingPostTip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Google\Service\Css;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TourBookingPostTipReminderMails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

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
     * Create a new job instance.
     *
     * @param  mixed $tourBooking
     * @param  mixed $tourOperatorLogo
     * @param  mixed $packageLogo
     * @param  mixed $packageImage
     * @return void
     */
    public function __construct($tourBooking, $tourOperatorLogo)
    {
        $this->tourBooking = $tourBooking;
        $this->tourOperatorLogo = $tourOperatorLogo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // get from and throung date
            $tourBooking = (object) $this->tourBooking;
            $checkTipReminderSent = DB::table('sent_emails')
                ->where('tour_booking_id', $tourBooking->tbm_id)
                ->where('email_type', config('constants.email_types.post_tip'))
                ->first();
            if (!$checkTipReminderSent) {
                $arEmailData = [
                    'bookingId' => $tourBooking->tbm_id,
                    'tourOperatorId' => $tourBooking->tour_operator_id,
                    'tourPackageId' => $tourBooking->tour_package_id,
                    'tourOperator' => $tourBooking->tour_operator,
                    'tourOpPhone' => $tourBooking->tour_op_phone,
                    'tourOpEmail' => $tourBooking->tour_op_email,
                    'tourOpWebsite' => $tourBooking->tour_op_website,
                    'tourPackage' => $tourBooking->package_name,
                    'custName' => $tourBooking->customer_name,
                    'custEmail' => $tourBooking->customer_email,
                    'custPhone' => $tourBooking->customer_phone,
                    'packageEmail' => $tourBooking->packageEmail,
                    'packageFromName' => $tourBooking->packageFromName,
                    'packageFromEmail' => $tourBooking->packageFromEmail,
                    'emailType' => config('constants.email_types.post_tip'),
                    'operationBy' => adminUser()->id,
                    'operationByUserType' => 'Admin',
                    'tenantId' => $tourBooking->tenant_id,
                    'paymentLinkUrlInfo' => tenant_route(tenant()->domains->first()->domain, 'customer.give-tip', ['bookingId' => Crypt::encrypt($tourBooking->tbm_id)])//route('customer.give-tip', ['bookingId' => Crypt::encrypt($tourBooking->tbm_id)]),
                ];

                if (!empty($tourBooking->tour_op_logo)) {
                    $arEmailData['tourOpLogo'] = $this->tourOperatorLogo;
                }

                Mail::send(new TourBookingPostTip($arEmailData, 'post_tip'));
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
