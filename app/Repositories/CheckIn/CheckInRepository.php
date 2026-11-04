<?php

namespace App\Repositories\CheckIn;

use Exception;
use App\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\TourPackage;
use App\Models\TourBooking;
use App\Models\TourOperator;
use App\Models\TourPackageBundleRelation;
use App\Models\TourPackageRate;
use App\Utils\BookingUtils;
use App\Utils\StrUtils;
use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\BundlePackageBookingService;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CheckInRepository extends Repository
{
    /**
     * @var CustomerRepository
     * used to interact with the waver
     */
    protected $customerRepository;

    /**
     * @var TourBookingRepository
     * use to set checkin status
     */
    protected $tourBookingRepository;

    /**
     * @var string
     */
    public $tourPackage = TourPackage::class;

    public $tourPackageBundleRelation = TourPackageBundleRelation::class;

    public $tourBooking = TourBooking::class;

    public $tourPackageRate = TourPackageRate::class;

    public function __construct(
        CustomerRepository $customerRepository,
        TourBookingRepository $tourBookingRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->tourBookingRepository = $tourBookingRepository;
    }

    /**
     * getBooking
     * find the booking from the qr code id or by the users input.
     * @param int|string $bookingId
     * @return array
     */
    public function getBooking($bookingId)
    {
        try {
            //get booking by id
            $query = DB::table('tour_bookings_master AS tbm')
                ->select([
                    'tbm.*',
                    DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
                    'tpkg.name AS tour_package_name',
                    'top.logo AS tour_logo',
                    'tst.time_from as tour_time',
                    'tpkg.things_to_bring',
                    'tsd.date as tour_date',
                    'image.url as tour_image',
                    'image.category as tour_image_category',
                    'c.is_affiliate',
                    DB::raw(
                        "IFNULL((SELECT SUM(amount)
                        FROM transactions AS t
                        WHERE t.tour_booking_id = tbm.id
                        AND t.status = 'Completed'
                        GROUP BY t.tour_booking_id), 0) AS paids"
                    )
                ])
                ->join('tour_packages AS tpkg', 'tpkg.id', '=', 'tbm.tour_package_id')
                ->join('tour_operators AS top', 'top.id', '=', 'tpkg.tour_operator_id')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
                ->join('tour_package_images AS image', 'image.tour_package_id', '=', 'tbm.tour_package_id')
                ->where(function ($query) use ($bookingId) {
                    $query->where('tbm.id', '=', StrUtils::parseInt($bookingId))
                        ->orWhere('tbm.viator_bookingRef', '=', $bookingId)
                        ->orWhere('tbm.get_your_guide_reference_booking', '=', $bookingId)
                        ->orWhere('tbm.ota_reference_booking', '=', $bookingId)
                        ->orWhere('tbm.reference_booking', '=', $bookingId)
                        ->orWhere('tbm.external_booking_id', '=', $bookingId);
                })
                ->where( "image.category", "=", "mainImage" );

            // featch the db results
            $booking = $query->get()->first();

            //return $query->toSql();

            //check the booking for standard errors
            $validate = BookingUtils::validateBooking($booking, 1);

            //get the number of tourists
            $paxCount = isset($booking) ? BookingUtils::getTouristCount(intval($booking->id)) : 0;

            //get the waiver information
            $waiverInfo = isset($booking) ? BookingUtils::getWaiverInfo($booking, $paxCount) : null;

            //get booking rates
            $rates = isset($booking) ? BookingService::totalInformation($booking->id) : null;

            //simplify the checkin object so the front end can easily establish if all users are checked in
            $allCheckedIn = isset($booking) && isset($rates) ? BookingUtils::checkedIn($rates) : null;

            //isPaid
            $isPaid = isset($booking) ? BookingUtils::isBookingPaid($booking->id) : null;

            // is Booking Partial Not Fully Paid
            $isBookingPartialUnpaid = isset($booking) ? BookingUtils::isBookingPartialUnpaid($booking) : null;

            //get items plain text list of allowed on the tour returned from teh booking query
            $thingsToBring = isset($booking) && !empty($booking->things_to_bring) ? json_decode($booking->things_to_bring) : [];

            //get restricted and allowed item images
            $restrictedAndAllowedItems = isset($booking) ? BookingUtils::getRestrictedAndAllowedItems($booking->tour_package_id) : ['NotAllowed' => [], 'Allowed' => [], 'Directions' => [], 'Splash' => []];

            //log success / error rate
            // Log::info("Pax Self Checkin, Booking: $bookingId Status: " . isset($validate['error']) ? $validate['error'] : "SUCCESS");

            //API Version
            $settings = DB::table('settings')->select(['key', 'value'])->get();

            if (!empty($settings)) {
                $apiVersion = 1;
                $isNoHide = true;
                foreach ($settings as $setting) {
                    if ($setting->key == 'KIOSK_API_VERSION') {
                        $apiVersion = $setting->value;
                    }

                    if ($setting->key == 'KIOSK_FORCE_UPDATE') {
                        $isNoHide = ($setting->value == '1') ? true : false;
                    }
                }
            }

            $bookingId = isset($booking) ? $booking->id : $bookingId;

            // Assign multiple guides and station details
            $assignedGuides = DB::table('tour_operator_staff as tos')
                ->join('booking_tour_guides as btg', 'btg.guide_id', '=', 'tos.id')
                ->join('stations as s', 's.id', '=', 'btg.station_id')
                ->where('btg.booking_id', $bookingId)
                ->select(
                    'tos.id as guide_id',
                    's.name as station_name',
                    'tos.profile_photo_path',
                    DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) AS guide_name"),
                    DB::raw('COALESCE(SUM(btg.tourists),0) as assigned_tourists'),
                    'tos.capacity'
                )
                ->groupBy('tos.id', 's.name', 'tos.profile_photo_path')
                ->get();

            $tourOperator = TourOperator::first('tenant_id');

            // Attach photo URLs if available
            if ($assignedGuides->isNotEmpty()) {
                $assignedGuides->transform(function ($guide) use ($tourOperator) {
                    if ($guide->profile_photo_path) {
                        $guide->profile_photo_path = Storage::disk('s3')->url(
                            'images/tour-operator/' . $tourOperator->tenant_id . '/users/' . $guide->profile_photo_path
                        );
                    }
                    return $guide;
                });
            }

            // Add an error message if booking is unpaid
            if (!$validate['code'] && !$validate['error'] && !empty($isPaid) && $isPaid['transactions']->isEmpty()) {
                $validate['code'] = "unpaid";
                $errMessages = BookingUtils::getBookingErrorsMessages();
                $validate['error'] = $errMessages[$validate['code']];
            }

            // Add an error message if booking is partial paid but not fully paid
            if (!$validate['code'] && !$validate['error'] && !$isBookingPartialUnpaid) {
                $validate['code'] = "partial_unpaid";
                $errMessages = BookingUtils::getBookingErrorsMessages();
                $validate['error'] = $errMessages[$validate['code']];
            }

            //return booking object
            return [
                'booking' => $booking,
                'pax_count' => $paxCount,
                'booking_id' => (string) $bookingId,
                'things_to_bring_text' => $thingsToBring,
                'allowed_items' => $restrictedAndAllowedItems['Allowed'],
                'restricted_items' => $restrictedAndAllowedItems['NotAllowed'],
                'directions' => $restrictedAndAllowedItems['Directions'],
                'splash_pages' => $restrictedAndAllowedItems['Splash'],
                'waiver' => $waiverInfo,
                'rates' => $rates,
                'all_checked_in' => $allCheckedIn,
                'payment_info' => $isPaid,
                'time_delta' => $validate['time_delta'],
                'apiVersion' =>  $apiVersion,
                'isNoHide' =>  $isNoHide,
                'assigned_guides' => $assignedGuides,
                'errorcode' => $validate['code'],
                'error' => $validate['error']
            ];
        } catch (Exception $err) {
            Log::error($err);
            return [
                'booking' => null,
                'errorcode' => 'general',
                'error' => [
                    'error' => "Server Error",
                    'message' => $err->getMessage(),
                ]   
            ];
        }
    }

    /**
     * Use the customerRepository to get the waiver information
     * @param $request the post request from the checkin controller or api
     * @required (bookingId,touristNum,isAdult))
     * @return array
     */
    public function waiver(Request $request)
    {
        $error = null;
        try {
            $params = [
                'bookingId' =>  $request->bookingId,
                'touristNum' => $request->touristNum,
                'isAdult' => $request->isAdult
            ];

            $waiverData = $this->customerRepository->getLiabilityWaiver(
                $params['bookingId'],
                $params['touristNum'],
                $params['isAdult']
            );
        } catch (Exception $err) {
            Log::error($err);
            $error = $err;
            $waiverData = null;
            $params = null;
        }

        return [
            'waiver' => $waiverData,
            'error' => $error,
            'params' => $params
        ];
    }

    /**
     * Sign Waiver and check in users
     * @param array $signatures
     * @param int|string $bookingId
     * @return array
     */
    public function signWaiver($signatures, $bookingId)
    {
        $req = new Request;
        $req->merge($signatures);
        return $this->customerRepository->updateLiabilityWaiver($req, $bookingId);
    }

    /**
     * Bulk Checkin
     * @param array $checkins
     * Example payload:
     *  [
     *      {   booking_id: 117193,
     *          tpr_id: 164,
     *          index: 0,
     *          check_in_status: 1
     *      },
     *      ...
     * ]
     * @return array
     */
    public function bulkCheckIn(array $checkins, $tourOpId = 0)
    {
        $res = [];
        foreach ($checkins as $chk) {
            $req = new Request;
            $req->merge([
                "booking_id" => $chk['booking_id'],
                "tpr_id" => $chk['tpr_id'],
                "index" => $chk['index'],
                "check_in_status" => $chk['check_in_status']
            ]);

            $res[] = $this->tourBookingRepository->updateCheckInStatus($req, $tourOpId);

        }
        return $res;
    }

    /**
     * Get the tour operator packages.
     *
     * @return mixed
     */
    public function getPackages()
    {
        try {
            $tourPackages = $this->tourPackage::active()
                ->where('bundle_package', 0)
                ->where('checkin', 1)
                ->where('checkin_type', 1)
                ->orderBy('Order_by')
                ->get(['id', 'name']);

            if(count($tourPackages) == 0) {
                $code = 'packagesnotfound';
                $error = "Packages Not Found";
                $message = "No packages found!!";

                return BookingUtils::getCustomErrorResponse($code, $error, $message);
            }

            return $tourPackages;
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * searchBooking
     * find the booking ID from booking Group ID
     * @param int|string $bookingGroupId
     * @return object
     */
    public function searchBooking($bookingGroupId)
    {
        $bookingQuery = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->where(function ($query) use ($bookingGroupId) {
                $query->where('tbm.group_id', '=', $bookingGroupId)
                    ->orWhere('tbm.external_booking_id', '=', $bookingGroupId);
            })
            ->select([
                'tbm.id as tour_booking_id',
                'tbm.tour_package_id',
                'tbm.status',
                'tbm.deleted_by',
                'tbm.name AS customer_name',
                'tbm.email AS customer_email',
                'tbm.phone_number AS customer_phone',
                'tbm.expire_at',
                DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
                'tp.name AS package_name',
                'tst.time_from as time',
                'tsd.date',
                'c.is_affiliate'
            ]);

        return $bookingQuery;
    }

    /**
     * getBookingInfo
     * Get booking details after scan QR code by group id in Web
     * @param int $bookingGroupId
     * @param int $tourPackageId
     * @return mixed
     */
    public function getBookingInfo($bookingGroupId, $tourPackageId)
    {
        $bookingQuery = $this->searchBooking($bookingGroupId);
        $bookingData = $bookingQuery->get();

        if(count($bookingData) == 0) {
            return response()->json([
				'code' => 'bookingnotfound',
                'message' => 'Booking Not Found!!'
			], 200);
        }

        $packages = $this->validatePackage($bookingData, $tourPackageId);

        if(empty($packages)) {
            return response()->json([
				'code' => 'packagenotfound',
                'message' => 'Package Not Found!!'
			], 200);
        }

        $getBooking = $bookingQuery->whereIn('tbm.tour_package_id', $packages)->get()->first();
        
        if(!$getBooking) {
            return response()->json([
				'code' => 'bookingnotfound',
                'message' => 'Booking Not Found!!'
			], 200);
        }

        return response()->json([
            'code' => 'bookingfound',
            'bookingId' => $getBooking->tour_booking_id
        ], 200);
    }

    /**
     * validatePackage
     * Validate the package along with booking group ID
     * @param object $bookingData
     * @param int $tourPackageId
     * @return array
     */
    public function validatePackage($bookingData, $tourPackageId)
    {
        foreach ($bookingData as $key => $booking) {
            if($tourPackageId == $booking->tour_package_id) {
                $packages[] = $tourPackageId;
                break;
            } else {
                $packages = $this->tourPackageBundleRelation::where('tour_package_id', $tourPackageId)
                    ->get()
                    ->pluck('parent_id')
                    ->toArray();
            }
        }

        return $packages;
    }

    /**
     * getBooking
     * find the booking from the qr code id or by the users input API.
     * @param int|string $bookingGroupId
     * @param int $tourPackageId
     * @return array
     */
    public function getBookingDetails($bookingGroupId, $tourPackageId)
    {
        try {
            $packages = [];
            $code = $error = null;
            $errMessages = BookingUtils::getBookingErrorsMessages();
            $bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
			$prefix = ( isset($bookingPrefix) && isset($bookingPrefix->value) ) ? $bookingPrefix->value : "";
            
            if(str_contains($bookingGroupId, $prefix)) {
                $groupId = explode("-", $bookingGroupId);
                $bookingGroupId = $groupId[1];
            }

            $bookingQuery = $this->searchBooking($bookingGroupId);
            $bookingData = $bookingQuery->get();

            if(count($bookingData) == 0) {
                return BookingUtils::getErrorResponse("notfound");
            }

            $packages = $this->validatePackage($bookingData, $tourPackageId);

            if(empty($packages)) {
                return BookingUtils::getErrorResponse("packagenotfound");
            }

            $getBooking = $bookingQuery->whereIn('tbm.tour_package_id', $packages)->get()->first();
            
            if(!$getBooking) {
                return BookingUtils::getErrorResponse("notfound");
            }

            /* Check weather booking is expired or not */
            if($getBooking->expire_at != null) {
                if(Carbon::now() >= $getBooking->expire_at) {
                    return BookingUtils::getErrorResponse("expired");
                }
            }

            //check the booking for standard errors
            $validate = BookingUtils::validateBooking($getBooking);

            //get the number of tourists
            $paxCount = isset($getBooking) ? BookingUtils::getTouristCount(intval($getBooking->tour_booking_id)) : 0;

            //get booking rates
            $rates = isset($getBooking) ? $this->bookingRedeemedNotRedeemedTickets($getBooking->tour_booking_id, $tourPackageId) : null;

            //API Version
            $settings = DB::table('settings')->select(['key', 'value'])->get();

            if (!empty($settings)) {
                $apiVersion = 1;
                $isNoHide = true;
                foreach ($settings as $setting) {
                    if ($setting->key == 'KIOSK_API_VERSION') {
                        $apiVersion = $setting->value;
                    }

                    if ($setting->key == 'KIOSK_FORCE_UPDATE') {
                        $isNoHide = ($setting->value == '1') ? true : false;
                    }
                }
            }

            $bookingId = $getBooking->tour_booking_id;
            $tourPackageInfo = TourPackage::find($tourPackageId);
            $tourOperator = TourOperator::find($tourPackageInfo->tour_operator_id);

            //return booking object
            return [
                'booking_id' => $bookingId,
                'package_id' => $getBooking->tour_package_id,
                'package_name' => $getBooking->package_name,
                'datetime' => $getBooking->tour_d_t,
                'time' => $getBooking->time,
                'date' => $getBooking->date,
                'expire_at' => $getBooking->expire_at,
                'is_affiliate' => $getBooking->is_affiliate,
                'customer_name' => $getBooking->customer_name,
                'customer_email' => $getBooking->customer_email,
                'customer_phone' => $getBooking->customer_phone,
                'timezone' => $tourOperator->timezone ?? '',
                'tourists' => (int) $paxCount,
                'totalTickets' => (int) $paxCount,
                'ticketsRedeemed' => $rates['redeemedTickets'],
                'ticketsNotRedeemed' => $rates['notRedeemedTickets'],
                'apiVersion' =>  (int) $apiVersion,
                'isNoHide' =>  $isNoHide,
                'errorcode' => $validate['code'],
                'error' => $validate['error']
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            return [
                'booking' => null,
                'errorcode' => 'general',
                'error' => [
                    'error' => "Server Error",
                    'message' => $exception->getMessage(),
                ]   
            ];
        }
    }

    /**
	 * Count number of redeemed and not redeemed tickets
	 *
	 * @param string $bookingId
     * @param integer $packageId
	 * @return array
	 */
    public function bookingRedeemedNotRedeemedTickets($bookingId, $packageId)
    {
        $redeemedTickets = 0;
        $notRedeemedTickets = 0;
        $mysqlDT = mysqlDT();
        $code = $error = null;
        $errMessages = BookingUtils::getBookingErrorsMessages();

        $transactQuery = DB::table('tour_bookings_transact AS tbt')
            ->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('tbt.tour_booking_id', $bookingId)
            ->where('tbt.deleted_by', 0);

        $bookingTransact = $transactQuery->get([
            'tbm.status', 
            'tbt.id', 
            'tbt.tourists', 
            'tbt.redeem_ticket', 
            'tbt.tour_package_rate_id',
            'tp.name as package_name'
        ]);

        if (count($bookingTransact) && $bookingTransact[0]->status == 'Cancelled') {
            $code = "cancelled";
            $error = "Booking Cancelled!!";
            $message = "The booking has been cancelled; tickets can not be redeemed.";

            return BookingUtils::getCustomErrorResponse($code, $error, $message);
        }

        $bookingDetails = $this->tourBooking::findOrFail($bookingId);
        $package = $this->tourPackage::findOrFail($packageId);

        if(!$bookingDetails) {
            $code = "notfound";
        }

        if(!$package) {
            $code = "packagenotfound";
        }

        if(!$bookingDetails || !$package) {
            return BookingUtils::getErrorResponse($code);
        }

        $parentPackage = $this->tourPackage::findOrFail($bookingDetails->tour_package_id);

        if(!$parentPackage) {
            return BookingUtils::getErrorResponse("packagenotfound");
        }

        // Get packages when package is bundle
		$packageList = BundlePackageBookingService::getBundlePackageBookings($parentPackage->bundle_package, $bookingId, $parentPackage->id);
        
        if(empty($packageList)) {
            return BookingUtils::getErrorResponse("packagenotfound");
        }

        for ($a = 0; $a < count($bookingTransact); $a++) {
            if($bookingTransact[$a]->redeem_ticket == null) {
                $redeemStatusInfo = [];
            } else {
                $redeemStatusInfo = json_decode($bookingTransact[$a]->redeem_ticket, TRUE);
            }
            
            // If redeem_ticket field is empty
            if(empty($redeemStatusInfo)) {
                for ($i = 0; $i < count($packageList); $i++) { 
                    $redeemStatusInfo[$i] = [
                        'id' => $packageList[$i]->tour_package_id,
                        'name' => $packageList[$i]->package_name,
                        'checkin_type' => $packageList[$i]->checkin_type,
                        'data' => []
                    ];
                    for ($b = 0 ; $b < $bookingTransact[$a]->tourists; $b++) { 
                        $redeemStatusInfo[$i]['data'][$b] = [
                            'status' => 'Active',
                            'dateTime' => $mysqlDT
                        ];
                    }
                }
            }
            
            // If redeem_ticket field is not empty
            for ($x = 0; $x < $bookingTransact[$a]->tourists; $x++) { 
                for ($y = 0; $y < count($redeemStatusInfo); $y++) { 
                    if(!isset($redeemStatusInfo[$y]['data'][$x])) {
                        $redeemStatusInfo[$y]['data'][$x] = [
                            'status' => 'Active',
                            'dateTime' => $mysqlDT
                        ];
                    }

                    $redeemStatusInfo[$y]['data'][$x] = [
                        'status' => $redeemStatusInfo[$y]['data'][$x]['status'],
                        'dateTime' => $redeemStatusInfo[$y]['data'][$x]['dateTime']
                    ];
                }
            }

            for ($x = 0; $x < $bookingTransact[$a]->tourists; $x++) { 
                for ($y = 0; $y < count($redeemStatusInfo); $y++) { 
                    if($packageId == $redeemStatusInfo[$y]['id']) {
                        if($redeemStatusInfo[$y]['data'][$x]['status'] == 'Active') {
                            $notRedeemedTickets++;
                        }
                        if($redeemStatusInfo[$y]['data'][$x]['status'] == 'Inactive') {
                            $redeemedTickets++;
                        }
                    }
                }
            }

            $redeemTicketStatusInfo[$a] = [
                'id' => $bookingTransact[$a]->id,
                'tour_package_rate_id' => $bookingTransact[$a]->tour_package_rate_id,
                'tourists' => $bookingTransact[$a]->tourists,
                'ticketData' => $redeemStatusInfo
            ];
        }

        return [
            'redeemTicketStatusInfo' => $redeemTicketStatusInfo,
            'redeemedTickets' => $redeemedTickets,
            'notRedeemedTickets' => $notRedeemedTickets,
            'parentPackage' => $parentPackage,
            'booking' => $bookingDetails,
            'packageList' => $packageList,
            'errorcode' => $code,
            'error' => $error
        ];
    }

    /**
     * Bulk Redeem Tickets
     * @param object $request
     * Example payload:
     * { 
     *      "booking_id": 117134,
     *      "package_id": 3,
     *      "tpr_id": 2, // Only for individual ticket redeem
     *      "index": 2, // Only for individual ticket redeem
     *      "status": "Active"
     * }
     * @return mixed
     */
    public function redeemTickets(object $request)
    {
        DB::beginTransaction();
        try {
            $updated = 0;
            $totalTourists = 0;
            $code = $error = null;
            $updatedRedeeemedTickets = $updatedNotRedeemedTickets = $remainingTickets = $updatedRedeemTicketCount = 0;
            $ticketCount = 1;
            $historyArr = [];

            $mysqlDT = mysqlDT();
            $errMessages = BookingUtils::getBookingErrorsMessages();
            $created_by_with_name = BookingService::createdUserInfo();

            $package = $this->tourPackage::findOrFail($request->package_id);
                
            if(!$package) {
                return BookingUtils::getErrorResponse("packagenotfound");
            }

            if($package->bundle_package == 1) {
                return BookingUtils::getErrorResponse("invalidpackage");
            }

            if($package->checkin_type == 0) {
                return BookingUtils::getErrorResponse("invalidcheckin");
            }

            $transact = $this->bookingRedeemedNotRedeemedTickets($request->booking_id, $request->package_id);

            if($transact['booking']->expire_at != null) {
                if(Carbon::now() >= $transact['booking']->expire_at) {
                    return BookingUtils::getErrorResponse("expired");
                }
            }

            if(!is_null($transact['errorcode'])) {
                return [
                    "totalTickets" => $totalTourists,
                    "ticketsRedeemed" => 0,
                    "ticketsNotRedeemed" => $totalTourists,
                    'package_name' => $package->name,
                    'errorcode' => $transact['errorcode'],
                    'error' => $transact['error']
                ];
            }

            // Verify booking with selected package/location id
            $bookingQuery = DB::table('tour_bookings_master AS tbm')
                ->select([
                    'tbm.id',
                    'tbm.tour_package_id',
                    'tbm.tourists',
                    'tbm.status',
                    'tbm.deleted_by',
                    DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t")
                ])
                ->join('tour_packages AS tpkg', 'tpkg.id', '=', 'tbm.tour_package_id')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->where('tbm.id', '=', $request->booking_id);

            if($transact['parentPackage']->bundle_package == 0) {
                $bookingQuery = $bookingQuery->where('tbm.tour_package_id', $request->package_id);
            } else {
                $bundlePackages = array_column(json_decode($transact['packageList']), 'tour_package_id');
                
                if(!in_array($request->package_id, $bundlePackages)) {
                    return BookingUtils::getErrorResponse("notfound");
                }

                $bookingQuery = $bookingQuery->where('tbm.tour_package_id', $transact['booking']->tour_package_id);
            }
            
            $booking = $bookingQuery->first();

            //check the booking for standard errors
            $validate = BookingUtils::validateBooking($booking);
            $totalTourists = ($booking) ? $booking->tourists : 0;

            if(!is_null($validate['code'])) {
                return [
                    "totalTickets" => $totalTourists,
                    "ticketsRedeemed" => 0,
                    "ticketsNotRedeemed" => $totalTourists,
                    'package_name' => $package->name,
                    'errorcode' => $validate['code'],
                    'error' => $validate['error']
                ];
            }

            $redeemTicketStatusInfo = $transact['redeemTicketStatusInfo'];
            $redeemedTickets = $transact['redeemedTickets'];
            $notRedeemedTickets = $transact['notRedeemedTickets'];

            // If number of tickets are greater than total tourists
            if($request->tickets > $totalTourists) {
                $remainingTickets = $totalTourists;
            }

            // If number of tickets are greater than remaining not-redeemed tickets
            if($request->tickets > $notRedeemedTickets) {
                $remainingTickets = $notRedeemedTickets;
            }

            if((($request->tickets > $totalTourists) || ($request->tickets > $notRedeemedTickets)) && $notRedeemedTickets != 0) {
                $code = "noupdate";
                $error = "Ticket redemption limit reached";
                $message = 'Only ' . $remainingTickets . ' ticket(s) are available to redeem for ' . $package->name . '.';

                return BookingUtils::getCustomErrorResponse($code, $error, $message);
            }

            // If tickets are not available OR all tickets are redeemed
            if($notRedeemedTickets == 0 && ($redeemedTickets >= $totalTourists)) {
                $code = "noupdate";
                $error = "Tickets are not available";
                $message = 'All tickets have already been redeemed for ' . $package->name . '.';

                return BookingUtils::getCustomErrorResponse($code, $error, $message);
            }

            foreach ($redeemTicketStatusInfo as $key => $redeemTicketStatus) {

                // For All => When number of tickets are same as total tourists
                if($totalTourists == $request->tickets) {
                    for ($x = 0; $x < $redeemTicketStatus['tourists']; $x++) { 
                        foreach ($redeemTicketStatus['ticketData'] as $keys => $ticket) {
                            if($request->package_id == $ticket['id']) {
                                $redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x] = [
                                    'status' => 'Inactive',
                                    'dateTime' => $mysqlDT
                                ];
                            }

                            if ($redeemTicketStatus['tourists'] < count($ticket['data'])) {
                                $arrCount = count($ticket['data']) - $redeemTicketStatus['tourists'];
                                $redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'] = array_slice($ticket['data'], 0, -$arrCount);
                            }
                        }
                    }

                    $updated = $updated + DB::table('tour_bookings_transact')
                        ->where('id', $redeemTicketStatus['id'])
                        ->update([
                            'redeem_ticket' => json_encode($redeemTicketStatusInfo[$key]['ticketData'])
                        ]);
                } else {
                    $rateGrp = DB::table('tour_package_rates AS tpr')
                        ->join('tour_bookings_transact AS tst', 'tst.tour_package_rate_id', '=', 'tpr.id')
                        ->where('tst.tour_booking_id', $request['booking_id'])
                        ->where('tpr.id', $redeemTicketStatus['tour_package_rate_id'])
                        ->where('tst.deleted_by', 0)
                        ->first(['tpr.rate_for', 'tst.tourists']);

                    if (!$rateGrp) {
                        $code = "notfound";
                        $error = "Booking rates are not Found";
                        $message = "Unable to redeem ticket(s), please see a representative at the front desk";

                        return BookingUtils::getCustomErrorResponse($code, $error, $message);
                    }

                    for ($x = 0; $x < $redeemTicketStatus['tourists']; $x++) { 
                        foreach ($redeemTicketStatus['ticketData'] as $keys => $ticket) {
                            if(!isset($redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x])) {
                                $redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x] = [
                                    'status' => 'Active',
                                    'dateTime' => $mysqlDT
                                ];
                            }

                            if($request->package_id == $ticket['id']) {
                                if($redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x]['status'] == 'Inactive') {
                                    $ticketCount--;
                                }

                                if($ticketCount <= $request->tickets) {
                                    if($redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x]['status'] == 'Active') {
                                        $redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x] = [
                                            'status' => 'Inactive',
                                            'dateTime' => $mysqlDT
                                        ];

                                        // History note updates
                                        if(!isset($historyArr[$key][$x])) {
                                            $historyArr[$key][$x] = [
                                                'status' => $redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'][$x]['status'],
                                                'who' => "{$rateGrp->rate_for} #" . ($x + 1),
                                                'checkin_type' => $package->checkin_type,
                                                'package' => $package->name,
                                                'created_by_with_name' => $created_by_with_name
                                            ];
                                        }
                                        
                                        $updatedRedeemTicketCount++;
                                    }
                                }
                            }

                            if ($redeemTicketStatus['tourists'] < count($ticket['data'])) {
                                $arrCount = count($ticket['data']) - $redeemTicketStatus['tourists'];
                                $redeemTicketStatusInfo[$key]['ticketData'][$keys]['data'] = array_slice($ticket['data'], 0, -$arrCount);
                            }
                        }
                        $ticketCount++;
                    }

                    $updated = $updated + DB::table('tour_bookings_transact')
                        ->where('id', $redeemTicketStatus['id'])
                        ->update([
                            'redeem_ticket' => json_encode($redeemTicketStatusInfo[$key]['ticketData'])
                        ]);
                }
            }

            if ($updated) {
                $updatedNotRedeemedTickets = $notRedeemedTickets - $request->tickets;
                $updatedRedeeemedTickets = $redeemedTickets + $request->tickets;

                if($totalTourists == $request->tickets) {
                    $whoRedeemedTickets = 'All';

                    $history = [
                        'package' => $package->name,
                        'status' => 'Inactive',
                        'checkin_type' => $package->checkin_type,
                        'who' => $whoRedeemedTickets,
                        'created_by_with_name' => $created_by_with_name
                    ];
                    
                    HistoryService::storeCheckInDataInHistory($request['booking_id'], $history, $mysqlDT);
                } else {
                    $whoRedeemedTickets = $updatedRedeemTicketCount;

                    if (!empty($historyArr)) {
                        foreach ($historyArr as $key => $history) {
                            foreach ($history as $k => $v) {
                                HistoryService::storeCheckInDataInHistory($request['booking_id'], $v, $mysqlDT);
                            }
                        }
                    }
                }
            } else {
                $code = "noupdate";
                $error = "Check-in status unchanged";
                $message = 'Check-in status could not be updated. The selected status may already be applied for ' . $package->name . '.';

                return BookingUtils::getCustomErrorResponse($code, $error, $message);
            }

            DB::commit();
            
            return [
                "totalTickets" => $totalTourists,
                "ticketsRedeemed" => $updatedRedeeemedTickets,
                "ticketsNotRedeemed" => $updatedNotRedeemedTickets,
                'checkInDateTime' => Carbon::now(),
                'package_name' => $package->name
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            return [
                'booking' => null,
                'errorcode' => 'general',
                'error' => [
                    'error' => "Server Error",
                    'message' => $exception->getMessage(),
                ]   
            ];
        }
    }
}