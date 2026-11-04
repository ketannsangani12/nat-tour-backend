<?php

namespace App\Http\Controllers\Common;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Notify\SMSNotifications;
use App\Models\Setting;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Exception;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    protected $smsNotifications;
    protected $tourBookingRepository;

    /**
     * init sms repo
     */
    public function __construct(SMSNotifications $smsNotifications, TourBookingRepository $tourBookingRepository,
    )
    {
        parent::__construct();
        $this->smsNotifications = $smsNotifications;
        $this->tourBookingRepository = $tourBookingRepository;
    }

    /**
     * display a page to send a raw text or qr code sms message to a customer
     * 
     * @param $id booking id : required
     */
    function smsIndex(Request $request)
    {
        $props = null;
        if (isset($request->id)) {
            $props = (array) SmsController::getBookingInfo($request->id);
        }

        $settings = Setting::where('key', 'BOOKING_ID_PREFIX')->first();
        $prefixId = $settings->value;

        $vars = [
            'page_title' => 'SMS Message',
            'form_values' => $props,
            'prefix_id' => $prefixId
        ];

        return view('users.admin.sms', $vars);
    }

    /**
     * Post method to send plain text sms message
     * 
     * @param $message text message under 255 chars : required
     * @param $phone_number phone number with country code : required
     */
    public function smsSend(Request $request)
	{
		try {
			return $this->tourBookingRepository->smsSend($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

    /**
     * Post method to send sms qr code link
     * 
     * @param $bookingId required
     */
    public function smsSendLink(Request $request)
	{
		try {
            $bookingId = intval($request->bookingId);
            $props = (array) SmsController::getBookingInfo($bookingId);
			return $this->tourBookingRepository->smsSendLink($bookingId,$props);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

    /**
     * return customer info for sending sms meesages
     * 
     * @param string|int $bookingId
     */
    public static function getBookingInfo($bookingId)
    {
        return DB::table('tour_bookings_master AS tbm')
            ->join('customers AS cus', 'cus.id', '=', 'tbm.customer_id')
            ->join('tour_operators AS to', 'to.id', '=', 'cus.tour_operator_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('tbm.id', $bookingId)
            ->select([
                'tp.name as package_name',
                'to.tenant_id',
                'tbm.id AS booking_id',
                'tbm.phone_number AS cust_phone',
                'tbm.name AS cust_name'
            ])
            ->first();
    }
}
