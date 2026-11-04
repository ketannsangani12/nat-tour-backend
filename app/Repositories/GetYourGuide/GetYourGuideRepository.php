<?php

namespace App\Repositories\GetYourGuide;

use App\Repositories\Repository;
use App\Http\Controllers\API\V1\ApiController;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\BookingTransact;
use App\Models\Transaction;
use App\Models\GetYourGuideReservation;
use App\Repositories\TripAdvisor\TripAdvisorRepository;
use App\Services\GetYourGuideBookingService;
use App\Services\GetYourGuideCancelBookingService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class GetYourGuideRepository extends Repository
{
    public $apiController = ApiController::class;

    public $tourOperator = TourOperator::class;

    public $tourPackage = TourPackage::class;

    public $customer = Customer::class;

    public $tourBooking = TourBooking::class;

    public $bookingTransact = BookingTransact::class;

    public $transaction = Transaction::class;

    public $getYourGuideReservation = GetYourGuideReservation::class;

    public $tripAdvisorRepository;

    /**
     * @param  TripAdvisorRepository  $tripAdvisorRepository
     */
    public function __construct(TripAdvisorRepository $tripAdvisorRepository)
    {
        $this->tripAdvisorRepository = $tripAdvisorRepository;
    }

    /**
     * Provide a list of available tours/packages for Supplier
     * @param int|string $supplierId
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function packageList($supplierId)
    {
        try {
            $operator = $this->tourOperator::where('get_your_guide_supplier_id', $supplierId)->first();

            if (!$operator) {
                return $this->errorResponse("INVALID_SUPPLIER", "Supplier ID not found", "Package List");
            }

            $packages = $this->tourPackage::where('tour_operator_id', $operator->id)
                ->where('get_your_guide_product_code', '!=', '')
                ->where('status', 'Active')
                ->get([
                    'get_your_guide_product_code as productId',
                    'name as productTitle'
                ]);

            $packageList['data'] = [
                "supplierId" => $supplierId,
                "supplierName" => $operator->name,
                "products" => $packages
            ];

            return response()->json($packageList);
        } catch (Exception $exception) {
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Package List");
        }
    }

    /**
     * Provide details of selected package/tour for Supplier
     * @param int|string $productId
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function packageDetails($productId)
    {
        try {
            $package = $this->tourPackage::where('get_your_guide_product_code', $productId)
                ->where('status', 'Active')
                ->first();

            if (!$package) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist", "Package Details");
            }

            $operator = DB::table('tour_operators as t_o')
                ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
                ->join('states AS st', 'st.id', '=', 'ct.state_id')
                ->join('countries AS cn', 'cn.id', '=', 'st.country_id')
                ->select(
                    't_o.get_your_guide_supplier_id',
                    'ct.name as city',
                    'cn.name as country'
                )
                ->first();

            if (!$operator) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist", "Package Details");
            }

            $packageDetails['data'] = [
                "supplierId" => $operator->get_your_guide_supplier_id,
                "productTitle" => $package->name,
                "productDescription" => $package->long_description,
                "destinationLocation" => [
                    "city" => $operator->city,
                    "country" => ($operator->country == "USA") ? "USA" : $operator->country
                ]
            ];

            return response()->json($packageDetails);
        } catch (Exception $exception) {
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Package Details");
        }
    }

    /**
     * Provide pricing details of selected package/tour for Supplier
     * @param int|string $productId
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function packagePricing($productId)
    {
        try {
            $pricing = [];
            $currency = currencyFormation(code: true);

            $package = $this->tourPackage::select('id')->where('get_your_guide_product_code', $productId)
                ->where('status', 'Active')
                ->first();

            if (!$package) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist", "Package Pricing");
            }

            $currentYear = Carbon::now()->format('Y');

            $rateGroups = DB::table('tour_package_rates AS tpr')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->join('customers AS c', 'c.id', '=', 'tpr.affiliate_id')
                ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
                ->join('states AS st', 'st.id', '=', 'ct.state_id')
                ->where([
                    'tpr.tour_package_id' => $package->id,
                    'tpr.deleted_by' => 0
                ])
                ->where('c.get_your_guide', 1)
                ->where('tpr.scheduling_year', $currentYear)
                ->orderBy('tpr.orderby', 'asc')
                ->select([
                    'tpr.id',
                    'tpr.age_from',
                    'tpr.age_to',
                    'tpr.rate_for',
                    'tpr.rate',
                    'tpr.additional_tax_percentage',
                    'tpr.processing_charge_percentage',
                    'tpr.additional_charge',
                    'tpr.scheduling_year',
                    'tpr.permit_fee'
                ])
                ->get();

            foreach ($rateGroups as $key => $rateGroup) {
                $ratePrice = 0;

                $ratePrice = $rateGroup->rate + $rateGroup->additional_charge + $rateGroup->permit_fee;
                $ratePrice += ($ratePrice * (float)$rateGroup->processing_charge_percentage) / 100;
                $ratePrice = (int) ($ratePrice * 100);

                $pricing[] = [
                    "category" => strtoupper($rateGroup->rate_for),
                    "ageFrom" => $rateGroup->age_from,
                    "ageTo" => $rateGroup->age_to,
                    "bookingCategory" => "STANDARD",
                    "price" => [
                        [
                            "priceType" => "RETAIL_PRICE",
                            "price" => $ratePrice,
                            "currency" => $currency
                        ]
                    ]
                ];
            }

            $packagePricing['data']['pricingCategories'] = $pricing;

            return response()->json($packagePricing);
        } catch (Exception $exception) {
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Package Pricing");
        }
    }

    /**
     * Provide availability details of product/package(s) listed on GetYourGuide for Supplier
     * @param object $request
     * @param array $availabilityCheck
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function packageAvailability($request = null, $availabilityCheck = [])
    {
        try {
            $i = 0;
            $availability = $rates = [];
            $currency = currencyFormation(code: true);

            if ($request == null && !empty($availabilityCheck)) {
                $productCode = $availabilityCheck['productId'];
                $startDate = $availabilityCheck['dateTime'];
                $endDate = $availabilityCheck['dateTime'];
            } else {
                $productCode = $request->get('productId');
                $startDate = $request->get('fromDateTime');
                $endDate = $request->get('toDateTime');
            }

            $package = GetYourGuideBookingService::getPackageDetails($productCode);

            if (!$package) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist.", "Package Availability");
            }

            $rateGroups = GetYourGuideBookingService::getRateGroups($productCode);

            if (!$rateGroups) {
                return $this->errorResponse("VALIDATION_FAILURE", "Either rate groups(ageband) are missing or data not found.", "Package Availability");
            }

            // If requested date type is time point [Date With Time Slot]
            $fromDate = $startDate;
            $toDate = $endDate;

            if (($startDate == trim($startDate) && str_contains($startDate, ' ')) && ($endDate == trim($endDate) && str_contains($endDate, ' '))) {
                $fromDate = explode(" ", $startDate);
                $fromDate = $fromDate[0];

                $toDate = explode(" ", $startDate);
                $toDate = $toDate[0];
            }

            $fromDateString = Carbon::parse($fromDate)->format('Y-m-d');
            $toDateString = Carbon::parse($toDate)->format('Y-m-d');

            if ($fromDateString < Carbon::now()->format('Y-m-d')) {
                return $this->errorResponse("VALIDATION_FAILURE", "The requested travel date is in the past.", "Package Availability");
            }

            if ($toDateString < Carbon::now()->format('Y-m-d')) {
                return $this->errorResponse("VALIDATION_FAILURE", "The requested travel date is in the past.", "Package Availability");
            }

            $getTimeSlotsByDate = GetYourGuideBookingService::getTimeSlotsByDate(
                $package->tourOperatorId,
                $package->tourPackageId,
                $package->affiliateId,
                $fromDateString,
                $toDateString
            );

            if (empty($getTimeSlotsByDate)) {
                $packageAvailability['data']['availabilities'] = $availability;

                return $packageAvailability;
            }

            foreach ($rateGroups as $key => $rateGroup) {
                $ratePrice = 0;

                $ratePrice = $rateGroup->rate + $rateGroup->additional_charge + $rateGroup->permit_fee;
                $ratePrice += ($ratePrice * (float)$rateGroup->processing_charge_percentage) / 100;
                $ratePrice = (int) ($ratePrice * 100);

                $rates[] = [
                    "category" => strtoupper($rateGroup->rate_for),
                    "price" => $ratePrice
                ];
            }

            foreach ($getTimeSlotsByDate as $key => $timeSlot) {
                $dateTimeSlot = $timeSlot->date . ' ' . $timeSlot->time_from;
                $dateTime = Carbon::parse($dateTimeSlot)->toIso8601String();
                $seats = $timeSlot->seats;

                $availability[$i] = [
                    "dateTime" => $dateTime,
                    "productId" => $productCode,
                    "vacancies" => $seats,
                    "currency" => $currency,
                    "pricesByCategory" => [
                        "retailPrices" => $rates
                    ]
                ];

                $i++;
            }

            $packageAvailability['data']['availabilities'] = $availability;

            return response()->json($packageAvailability);
        } catch (Exception $exception) {
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Package Availability");
        }
    }

    /**
     * Reserve quantities of product/package(s) in cart listed on GetYourGuide for Supplier
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function packageReservation($request)
    {
        DB::beginTransaction();
        try {
            $length = 10;
            $isAvailable = 0;
            $touristsTotal = 0;
            $packageReservation = $availability = [];
            $productCode = $request['data']['productId'];
            $dateTime = $request['data']['dateTime'];
            $dateTimeString = Carbon::parse($dateTime)->format('Y-m-d H:i:s');
            $dateString = explode(" ", $dateTimeString)[0];
            $timeString = explode(" ", $dateTimeString)[1];

            $reservationReference = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 1, $length);
            $expirationTime = Carbon::now()->addMinutes(60)->toIso8601String(); // Set expiration time of 60 minutes
            $expirationDateTimeString = Carbon::parse($expirationTime)->format('Y-m-d H:i:s');

            /* Check availability before reserve start */
            $package = GetYourGuideBookingService::getPackageDetails($productCode);

            if (!$package) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist.", "Package Reservation");
            }

            $rateGroups = GetYourGuideBookingService::getRateGroups($productCode);

            if (!$rateGroups) {
                return $this->errorResponse("VALIDATION_FAILURE", "Either rate groups(ageband) are missing or data not found.", "Package Reservation");
            }

            if ($dateString < Carbon::now()->format('Y-m-d')) {
                return $this->errorResponse("VALIDATION_FAILURE", "The requested travel date is in the past.", "Package Reservation");
            }

            $availabilityCheck = GetYourGuideBookingService::checkSelectedTimeSlotBooking($timeString, $package->tourPackageId, $dateString, $package->affiliateId);

            if (!$availabilityCheck) {
                $message = "The tour for selected date/time is not available or has already passed. Please look for other dates and slots.";

                return $this->errorResponse("NO_AVAILABILITY", $message, "Package Reservation");
            }

            $currentTime = ($availabilityCheck->timezone != '') ? Carbon::now($availabilityCheck->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
            $currentTime = Carbon::parse($currentTime);
            $dateTime = Carbon::parse($availabilityCheck->date . ' ' . $availabilityCheck->time_from);

            $availabilityCheck->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

            if ($availabilityCheck->slotStatus == 'Closed') {
                $message = "The tour for selected date/time is not available or has already passed. Please look for other dates and slots.";

                return $this->errorResponse("NO_AVAILABILITY", $message, "Package Reservation");
            }

            $remainingSeats = $availabilityCheck->seats;

            if (isset($request['data']['bookingItems'])) {
                foreach ($request['data']['bookingItems'] as $key => $value) {
                    $touristsTotal += $value['count'];
                }
            }

            if ($touristsTotal > $remainingSeats) {
                $isAvailable = 0;
            } else {
                $isAvailable = 1;
            }

            $availability[] = [
                "dateTime" => $request['data']['dateTime'],
                "productId" => $productCode,
                "vacancies" => $remainingSeats
            ];

            if ($remainingSeats == 0 || $isAvailable == 0) {
                $packageReservation['data']['availabilities'] = $availability;

                return response()->json($packageReservation);
            }
            /* Check availability before reserve end */

            /* Store data in `get_your_guide_reservations` table */
            $reservations = $this->getYourGuideReservation::create([
                'product_code' => $productCode,
                'reservation_reference' => $reservationReference,
                'reservation_expiration' => $expirationDateTimeString,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            DB::commit();

            $packageReservation['data'] = [
                "reservationReference" => $reservationReference,
                "reservationExpiration" => $expirationTime
            ];

            return response()->json($packageReservation);
        } catch (Exception $exception) {
            DB::rollback();
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Package Reservation");
        }
    }

    /**
     * Cancel reservation of product/package(s) and removed from cart listed on GetYourGuide for Supplier
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function packageCancelReservation($request)
    {
        DB::beginTransaction();
        try {
            $packageCancelReservation = [];
            $packageCancelReservation['data'] = $packageCancelReservation;

            $reservation = $this->getYourGuideReservation::where('reservation_reference', $request['data']['reservationReference'])->first();

            /* If reservation not found */
            if (!$reservation) {
                return $this->errorResponse("INVALID_RESERVATION", "Reservation not found or expired.", "Package Reservation Cancellation");
            }

            $reservation->delete();
            DB::commit();

            return response()->json($packageCancelReservation);
        } catch (Exception $exception) {
            DB::rollback();
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Package Reservation Cancellation");
        }
    }

    /**
     * Create booking for product/package(s) from cart listed on GetYourGuide for Supplier
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function createBooking($request)
    {
        DB::beginTransaction();
        try {
            $isAvailable = $touristsTotal = $bookingTotal = $platformFees = 0;
            $tbTransact = $paxArr = $touristCount = $addedPeoplesText = $tickets = $availability = $createBooking = [];
            $codeType = "QR_CODE";
            $bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
            $prefix = (isset($bookingPrefix) && isset($bookingPrefix->value)) ? $bookingPrefix->value : "";

            $productCode = $request['data']['productId'];
            $dateTime = $request['data']['dateTime'];
            $dateTimeString = Carbon::parse($dateTime)->format('Y-m-d H:i:s');
            $dateString = explode(" ", $dateTimeString)[0];
            $timeString = explode(" ", $dateTimeString)[1];

            /* Check availability before reserve start */
            $package = GetYourGuideBookingService::getPackageDetails($productCode);

            if (!$package) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist.", "Create Booking");
            }

            /* If reservation expired */
            $reservation = $this->getYourGuideReservation::where('reservation_reference', $request['data']['reservationReference'])->first();

            if (!$reservation) {
                return $this->errorResponse("INVALID_RESERVATION", "Reservation not found or expired.", "Create Booking");
            }

            if (Carbon::now()->format('Y-m-d H:i:s') > $reservation->reservation_expiration) {
                return $this->errorResponse("INVALID_RESERVATION", "Expired reservation; 60mins hold time was exceeded.", "Create Booking");
            }

            if ($dateString < Carbon::now()->format('Y-m-d')) {
                return $this->errorResponse("VALIDATION_FAILURE", "The requested travel date is in the past.", "Create Booking");
            }

            $availabilityCheck = GetYourGuideBookingService::checkSelectedTimeSlotBooking($timeString, $package->tourPackageId, $dateString, $package->affiliateId);

            if (!$availabilityCheck) {
                $message = "The tour for selected date/time is not available or has already passed. Please look for other dates and slots.";

                return $this->errorResponse("NO_AVAILABILITY", $message, "Create Booking");
            }

            $currentTime = ($availabilityCheck->timezone != '') ? Carbon::now($availabilityCheck->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
            $currentTime = Carbon::parse($currentTime);
            $dateTime = Carbon::parse($availabilityCheck->date . ' ' . $availabilityCheck->time_from);

            $availabilityCheck->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

            if ($availabilityCheck->slotStatus == 'Closed') {
                $message = "The tour for selected date/time is not available or has already passed. Please look for other dates and slots.";

                return $this->errorResponse("NO_AVAILABILITY", $message, "Create Booking");
            }

            $remainingSeats = $availabilityCheck->seats;

            if (isset($request['data']['bookingItems'])) {
                foreach ($request['data']['bookingItems'] as $key => $value) {
                    $touristsTotal += $value['count'];
                }
            }

            if ($touristsTotal > $remainingSeats) {
                $isAvailable = 0;
            } else {
                $isAvailable = 1;
            }

            $availability[] = [
                "dateTime" => $request['data']['dateTime'],
                "productId" => $productCode,
                "vacancies" => $remainingSeats
            ];

            if ($remainingSeats == 0 || $isAvailable == 0) {
                $createBooking['data']['availabilities'] = $availability;

                return response()->json($createBooking);
            }
            /* Check availability before reserve end */

            // find the time slot ID in the tour_slot_times table from the requested time
            $slot = collect(DB::select("
                SELECT
                    `tst`.`id` as `tour_slot_id`
                FROM
                    `tour_slot_times` AS `tst`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tst`.`tour_package_id`
                WHERE
                    `tst`.`bookable_status` = 'Open'
                AND
                    `tst`.`hide_slot` = 0
                AND
                    `tst`.`time_from` = '" . $timeString . "'
                AND
                    `tst`.`date` = '" . $dateString . "'
                AND
                    `tp`.`id` = '" . $package->tourPackageId . "'
            "))->first();

            $firstName = $request['data']['travelers'][0]['firstName'];
            $lastName = $request['data']['travelers'][0]['lastName'];
            $email = $request['data']['travelers'][0]['email'];
            $phoneNumber = $request['data']['travelers'][0]['phoneNumber'];
            $fullName = isset($request['data']['travelers'][0]['lastName']) ? $firstName . ' ' . $lastName : $firstName;

            /* Calculate total booking amount start */
            foreach ($request['data']['bookingItems'] as $key => $value) {
                $bookingTotal += ($value['retailPrice'] / 100) * $value['count'];
                $paxArr[] = strtolower($value['category']);
                $touristCount[] = $value['count'];
            }
            /* Calculate total booking amount end */

            // Get rate groups for Get Your Guide Affiliate
            $rateGroups = DB::select("
                SELECT
                    `tpr`.`id`, `tpr`.`rate_for`,
                    `tpr`.`rate`, `tpr`.`additional_tax_percentage`,
                    `tpr`.`processing_charge_percentage`, `tpr`.`additional_charge`,
                    `tpr`.`permit_fee`
                FROM
                    `tour_package_rates` AS `tpr`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tpr`.`tour_package_id`
                INNER JOIN `customers` AS `c` ON `c`.`id` = `tpr`.`affiliate_id`
                WHERE
                    `tpr`.`deleted_by` = 0
                AND
                    `tpr`.`tour_package_id` = " . $package->tourPackageId . "
                AND
                    `c`.`id` = " . $package->affiliateId . "
                AND
                    `tpr`.`rate_for` IN ('" . implode("', '", $paxArr) . "')
                AND
                    `tpr`.`status` = 'Active'
                ORDER BY
                    `tpr`.`orderby` ASC
            ");

            if (!$rateGroups) {
                return $this->errorResponse("VALIDATION_FAILURE", "Rate group(s) for Get Your Guide not found.", "Create Booking");
            }

            // Create Customer For This Booking
            $customer = $this->customer::firstOrCreate([
                'email' => $email
            ], [
                'name' => $fullName,
                'tour_operator_id' => $package->tourOperatorId,
                'phone_number' => $phoneNumber,
            ]);
            DB::commit();

            if ($customer) {
                // Store booking information in tour_bookings_master table
                $tourBooking = $this->tourBooking::create([
                    'get_your_guide_reference_booking' => $request['data']['gygBookingReference'],
                    'tour_package_id' => $package->tourPackageId,
                    'tour_slot_id' => $slot->tour_slot_id,
                    'customer_id' => $customer->id,
                    'name' => $fullName,
                    'email' => $customer->email,
                    'phone_number' => $customer->phone_number,
                    'service_commission' => $platformFees,
                    'tourists' => $touristsTotal,
                    'comments' => (isset($request['data']['comment'])) ? $request['data']['comment'] : "",
                    'mask_required' => 'Yes',
                    'total' => $bookingTotal,
                    'booking_total' => $bookingTotal,
                    'status' => 'Booked',
                    'created_by' => $customer->id,
                    'created_by_user_type' => 'Customer'
                ]);
                DB::commit();

                if ($tourBooking) {
                    foreach ($rateGroups as $key => $rate) {
                        $groupTotal = 0;
                        $keyVal = array_search(strtolower($rate->rate_for), $paxArr);
                        $tourists = (isset($keyVal)) ? $touristCount[$keyVal] : 0;

                        $groupTotal = ($tourists * ($rate->rate + $rate->additional_charge + $rate->permit_fee));
                        $groupTotal += ($groupTotal * (float)$rate->processing_charge_percentage) / 100;

                        $tbTransact[] = [
                            'tour_package_rate_id' => $rate->id,
                            'tourists' => $tourists,
                            'additional_tax_percentage' => $rate->additional_tax_percentage,
                            'processing_charge_percentage' => $rate->processing_charge_percentage,
                            'nat_fees_percentage' => $package->service_commission_percentage,
                            'stripe_fees_percentage' => $package->stripe_commission_percentage,
                            'additional_charge' => ($rate->additional_charge),
                            'permit_fee' => $rate->permit_fee,
                            'rate' => $rate->rate,
                            'total' => $groupTotal,
                            'created_by' => $customer->id,
                            'created_by_user_type' => 'Customer',
                            'created_at' => Carbon::now(),
                        ];

                        $addedPeoplesText[] = "{$rate->rate_for} person $tourists";
                    }

                    for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
                        $tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
                    }

                    DB::table('tour_bookings_transact')->insert($tbTransact);
                    DB::commit();

                    $touristsSum = $this->bookingTransact::select('total')->where('tour_booking_id', $tourBooking->id)->sum('total');

                    // Update tour_booking_master table to update booking total and booking_total
                    $this->tourBooking::where('id', $tourBooking->id)->update([
                        'total' => $touristsSum,
                        'booking_total' => $touristsSum
                    ]);
                    DB::commit();

                    // Store History details in tour_booking_history
                    $touristHistory = [
                        'tour_booking_id' => $tourBooking->id,
                        'tourists' => $touristsTotal,
                        'operation' => 'Added',
                        'details' => json_encode([
                            'sub_operation' => 'Tourists Added',
                            'discount_percentage' => 0,
                            'discount2_value' => 0,
                            'added' => $addedPeoplesText,
                            'newTourists' => $touristsTotal
                        ], JSON_NUMERIC_CHECK),
                        'operation_at' => Carbon::now(),
                        'operation_by' => $customer->id,
                        'operation_by_user_type' => 'Customer',
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ];

                    DB::table('tour_booking_history')->insert($touristHistory);
                    DB::commit();

                    // Store payment details in transactions table
                    $transactionId = DB::table('transactions')
                        ->insertGetId([
                            'tour_booking_id' => $tourBooking->id,
                            'amount' => $touristsSum,
                            'fee_amount' => $platformFees,
                            'via' => 'GetYourGuide',
                            'response_init' => '',
                            'response_end' => '',
                            'response_end_datetime' => null,
                            'status' => 'Completed',
                            'payment_type' => 0,
                            'visitor' => request()->getClientIp(),
                            'request_from' => 'GETYOURGUIDE',
                            'created_by' => $customer->id,
                            'created_by_user_type' => 'Customer',
                            'created_at' => Carbon::now(),
                        ]);
                    DB::commit();

                    // Store Payment details in tour_booking_history table
                    $historyId = DB::table('tour_booking_history')
                        ->insertGetId([
                            'tour_booking_id' => $tourBooking->id,
                            'tourists' => $touristsTotal,
                            'transaction_id' => $transactionId,
                            'operation' => 'Payment',
                            'details' => json_encode(['amount' => $touristsSum, 'payment_type' => 'GetYourGuide'], JSON_NUMERIC_CHECK),
                            'operation_at' => Carbon::now(),
                            'operation_by' => $customer->id,
                            'operation_by_user_type' => 'Customer',
                            'updated_at' => Carbon::now(),
                            'created_at' => Carbon::now(),
                        ]);
                    DB::commit();

                    // Call service to store history update details
                    GetYourGuideBookingService::storePricingInHistory($tourBooking, $transactionId, $historyId);

                    TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);
                } else {
                    return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", "Something went wrong, please try again later", "Create Booking");
                }
            } else {
                return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", "Something went wrong, please try again later", "Create Booking");
            }

            if ($reservation) {
                $reservation->delete();
            }
            DB::commit();

            // Integrate response for each pax
            foreach ($request['data']['bookingItems'] as $key => $value) {
                for ($i = 0; $i < $value['count']; $i++) {
                    $tickets[] = [
                        "category" => $value['category'],
                        "ticketCode" => $prefix . "" . $tourBooking->id,
                        "ticketCodeType" => $codeType
                    ];
                }
            }

            $createBooking['data'] = [
                "bookingReference" => $tourBooking->id,
                "tickets" => $tickets
            ];

            return response()->json($createBooking);
        } catch (Exception $exception) {
            DB::rollback();
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Create Booking");
        }
    }

    /**
     * Cancel booking for product/package(s) from cart listed on GetYourGuide for Supplier
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function cancelBooking($request)
    {
        DB::beginTransaction();
        try {
            $cancel = $cancelBooking = [];
            $cancelNote = "GetYourGuide booking cancelled";
            $bookingId = $request['data']['bookingReference'];
            $getYourGuideReferenceBooking = $request['data']['gygBookingReference'];
            $productCode = $request['data']['productId'];
            $todayDate = Carbon::now()->format('Y-m-d H:i:s');
            $twoHoursAgo = date($todayDate, strtotime('-2 hours'));

            $package = GetYourGuideBookingService::getPackageDetails($productCode);

            if (!$package) {
                return $this->errorResponse("INVALID_PRODUCT", "This product does not exist.", "Cancel Booking");
            }

            $booking = DB::table('tour_bookings_master AS tbm')
                ->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->where('tbm.id', $bookingId)
                ->where('tbm.get_your_guide_reference_booking', $getYourGuideReferenceBooking)
                ->select(
                    DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
                    'tbm.customer_id',
                    'tbm.tourists',
                    'tbm.status',
                    'tbt.check_in_status',
                    'tbt.redeem_ticket'
                );

            $tourBooking = $booking->first();

            if (!$tourBooking) {
                return $this->errorResponse("INVALID_BOOKING", "Booking not found!!", "Cancel Booking");
            }

            /* If booking already cancelled or not */
            if ($tourBooking->status == "Cancelled") {
                return $this->errorResponse("BOOKING_ALREADY_CANCELLED", "The booking has been cancelled already.", "Cancel Booking");
            }

            /* If booking in past */
            if ($tourBooking->tour_d_t < $todayDate) {
                return $this->errorResponse("BOOKING_IN_PAST", "The requested booking is in the past, kindly contact the tour operator.", "Cancel Booking");
            }

            /* Check if booking already redeemed/checked in */
            if ($tourBooking->check_in_status != "" || $tourBooking->redeem_ticket != null) {
                return $this->errorResponse("BOOKING_REDEEMED", "The booking was already used or redeemed by the traveler.", "Cancel Booking");
            }

            if ($tourBooking->tour_d_t < $twoHoursAgo) {
                return $this->errorResponse("VALIDATION_FAILURE", "In order to cancel a reservation, kindly contact the tour operator.", "Cancel Booking");
            }

            $transaction = $this->transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->first();

            $customer = $this->customer::find($tourBooking->customer_id);

            $update = $booking->update([
                'tbm.status' => 'Cancelled',
                'tbm.updated_by' => $customer->id,
                'tbm.updated_by_user_type' => 'Customer'
            ]);

            if ($update) {
                if (!empty($transaction)) {
                    $cancelBookingRefund = GetYourGuideCancelBookingService::run($bookingId, $tourBooking, $cancelNote);

                    // Below line is to update tour booking history table
                    GetYourGuideCancelBookingService::updateRefundHistory($cancelBookingRefund['historyId'], $cancelBookingRefund['via'], $cancelBookingRefund['transaction_id']);
                } else {
                    DB::rollBack();
                    return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", "Unable to cancel booking; something went wrong.", "Cancel Booking");
                }
            } else {
                DB::rollBack();
                return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", "Unable to cancel booking; something went wrong.", "Cancel Booking");
            }

            $cancelBooking['data'] = $cancel;

            return response()->json($cancelBooking);
        } catch (Exception $exception) {
            DB::rollback();
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Cancel Booking");
        }
    }

    /**
     * Notify affected product/package and the event for package/tour for supplier
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function notification($request)
    {
        try {
            $notificationArray = [];
            $notification['data'] = $notificationArray;

            return response()->json($notification);
        } catch (Exception $exception) {
            DB::rollback();
            return $this->errorResponse("INTERNAL_SYSTEM_FAILURE", $exception->getMessage(), "Notification");
        }
    }

    /**
     * JSON response for errors
     * @param string $errorCode
     * @param string $errorMessage
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function errorResponse($errorCode, $errorMessage, $api)
    {
        $response = [
            "errorCode" => $errorCode,
            "errorMessage" => $errorMessage
        ];

        Log::error("Get Your Guide " . $api . " API Error Response ========== " . json_encode($response));
        return $this->apiController::response(null, $response);
    }
}
