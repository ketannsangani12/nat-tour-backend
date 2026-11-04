<?php

namespace App\Repositories\Bokun;

use App\Repositories\Repository;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Models\TourPackage;
use App\Models\BokunReservation;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\BookingTransact;
use App\Models\Transaction;
use App\Models\TourPackageRate;
use App\Services\BokunService;
use App\Services\Booking\BookingService;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\Booking\HistoryService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TourPackageRate\TourPackageRateService;
use App\Utils\BokunUtils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class BokunRepository extends Repository
{
    public $tourPackage = TourPackage::class;

    public $bokunReservation = BokunReservation::class;

    public $customer = Customer::class;

    public $tourBooking = TourBooking::class;

    public $bookingTransact = BookingTransact::class;

    public $transaction = Transaction::class;

    public $tourPackageRate = TourPackageRate::class;

    protected $tourBookingRepository;

    /**
	 * @param  TourBookingRepository $tourBookingRepository
	 * 
	 * @version 1.0.0
	 */
	public function __construct(TourBookingRepository $tourBookingRepository)
	{
		$this->tourBookingRepository = $tourBookingRepository;
	}

    /**
     * Provide plugin definition information (what features etc this plugin supports).
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function pluginDefinition()
    {
        try {
            $definition = [
                'name' => 'NativeAmericanTours',
                'description' => 'Custom Bokun integration for Native American Tours (NAT) to sync availability and reservations.',
                'capabilities' => [
                    'SUPPORTS_RESERVATIONS',
                    'SUPPORTS_AVAILABILITY',
                    'SUPPORTS_RESERVATION_CANCELLATION',
                    'SUPPORTS_AMENDMENT'
                ],
                'parameters' => [
                    [
                        'name' => 'partner_id',
                        'type' => 'STRING',
                        'required' => true,
                    ],
                ],
            ];

            return response()->json($definition);
        } catch (Exception $exception) {
            Log::error("Plugin Definition API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to retrieve plugin definition details", $exception->getMessage(), "Plugin Definition");
        }
    }

    /**
     * Return information about products
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function searchProduct($request)
    {
        try {
            $productName = $request->productName;

            // Fetch packages matching the search term
            $packages = DB::table('tour_packages as tp')
                ->join('tour_package_rates as tpr', 'tpr.tour_package_id', '=', 'tp.id')
                ->join('customers as c', 'c.id', '=', 'tpr.affiliate_id')
                ->where([
                    ['tp.deleted_by', 0],
                    ['tp.status', 'Active'],
                    ['c.bokun', 1],
                    ['c.is_affiliate', 'Yes']
                ])
                ->whereNull('tp.deleted_at')
                ->whereNotNull('tp.bokun_product_code')
                ->where('tp.bokun_product_code', '!=', '')
                ->when(!empty($productName), function ($query) use ($productName) {
                    $query->where('tp.name', 'LIKE', "%{$productName}%");
                })
                ->select([
                    'tp.id as package_id',
                    'tp.name',
                    'tp.bokun_product_code',
                    'tp.short_description',
                    'tpr.affiliate_id'
                ])
                ->distinct()
                ->get();

            $searchProduct = $packages->map(function ($package) {
                // Fetch rate groups using Eloquent relationships
                $rateGroups = BokunService::rateGroups($package);

                return [
                    'id' => (string) $package->bokun_product_code,
                    'name' => $package->name,
                    'description' => $package->short_description,
                    'pricingCategories' => $rateGroups
                ];
            });

            return response()->json($searchProduct);
        } catch (Exception $exception) {
            Log::error("Product Search API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to retrieve product details", $exception->getMessage(), "Product Search");
        }
    }

    /**
     * Retrieve detailed information about given product by ID (Bokun Product ID)
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function productDetailsById($request)
    {
        try {
            $package = DB::table('tour_packages as tp')
                ->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
                ->join('cities as ct', 'ct.id', '=', 'to.city_id')
                ->join('states as st', 'st.id', '=', 'ct.state_id')
                ->join('countries as cn', 'cn.id', '=', 'st.country_id')
                ->join('customers as c', 'c.tour_operator_id', '=', 'to.id')
                ->where([
                    ['tp.bokun_product_code', '=', $request->externalId],
                    ['tp.deleted_by', '=', 0],
                    ['c.bokun', '=', 1],
                    ['c.is_affiliate', '=', 'Yes']
                ])
                ->whereNull('tp.deleted_at')
                ->select(
                    'tp.id as package_id',
                    'tp.name',
                    'tp.bokun_product_code',
                    'tp.short_description',
                    'to.name as operator_id',
                    'c.id as affiliate_id',
                    'ct.name as city',
                    'cn.code as country'
                )
                ->firstOrFail();

            // Service calls
            $rateGroups = BokunService::rateGroups($package);
            $rates = BokunService::rates($package);

            // Prepare affiliate and data object
            $affiliate = (object)[
                'id' => $package->affiliate_id
            ];
            $data = (object)[
                'tourPackageId' => $package->package_id
            ];

            $timeSlots = TimeSlotService::getAffiliateTimeSlots($affiliate, $data);

            // Convert time slots to required format
            $initTimeSlots = collect($timeSlots)->map(function ($slot) {
                $time = Carbon::parse($slot->time_from);
                return [
                    'hour' => $time->hour,
                    'minute' => $time->minute
                ];
            })->all();

            // Assemble product details
            $productDetails = [
                'id' => $package->bokun_product_code,
                'name' => $package->name,
                'description' => $package->short_description,
                'pricingCategories' => $rateGroups,
                'rates' => $rates,
                'bookingType' => 'DATE_AND_TIME',
                'dropoffAvailable' => false,
                'productCategory' => 'ACTIVITIES',
                'ticketSupport' => ['TICKET_PER_BOOKING'],
                'countries' => [$package->country],
                'cities' => [$package->city],
                'startTimes' => $initTimeSlots,
                'ticketType' => 'QR_CODE',
                'meetingType' => 'MEET_ON_LOCATION',
            ];

            return response()->json($productDetails);
        } catch (Exception $exception) {
            Log::error("Product Details By ID API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to retrieve product details", $exception->getMessage(), "Product GetById");
        }
    }

    /**
     * A set of product ids provided, return their availability over given date range
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function productGetAvailable($request)
    {
        try {
            $productCodes = $request['externalProductIds'] ?? [];
            $requiredCapacity = (int) $request['requiredCapacity'];

            $fromDate = Carbon::createFromDate(
                $request['range']['from']['year'],
                $request['range']['from']['month'],
                $request['range']['from']['day']
            )->toDateString();

            $toDate = Carbon::createFromDate(
                $request['range']['to']['year'],
                $request['range']['to']['month'],
                $request['range']['to']['day']
            )->toDateString();

            $availableProducts = [];

            foreach ($productCodes as $productCode) {
                $package = BokunService::getPackageDetails($productCode);

                if (!$package) {
                    continue;
                }

                $timeSlots = BokunService::getTimeSlotsByDate(
                    $package->operator_id,
                    $package->package_id,
                    $package->affiliate_id,
                    $fromDate,
                    $toDate
                );

                $maxCapacity = collect($timeSlots)->max(function ($slot) use ($package) {
                    $timezone = $slot->timezone ?: 'UTC';
                    $nowInTz = Carbon::now($timezone);
                    $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone);
                    $cutoffDateTime = $nowInTz->copy()->addHours((int) ($package->ota_duration ?? 0));
                    $isBlocked = $package->package_has_slots && $slotDateTime->lt($cutoffDateTime);

                    return $isBlocked ? 0 : (int) $slot->available_seats;
                }) ?? 0;

                if ($maxCapacity >= $requiredCapacity) {
                    $availableProducts[] = [
                        'productId' => $productCode,
                        'actualCheckDone' => true,
                    ];
                }
            }

            return response()->json($availableProducts);
        } catch (Exception $exception) {
            Log::error("Get Available Products API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to retrieve available products", $exception->getMessage(), "Get Available Products");
        }
    }

    /**
     * Get availability of a particular product over a date range
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function getProductAvailability($request)
    {
        try {
            $productAvailability = [];

            // Convert input range to Carbon instances
            $fromDate = Carbon::createFromDate(
                $request['range']['from']['year'],
                $request['range']['from']['month'],
                $request['range']['from']['day']
            )->toDateString();

            $toDate = Carbon::createFromDate(
                $request['range']['to']['year'],
                $request['range']['to']['month'],
                $request['range']['to']['day']
            )->toDateString();

            // Fetch package details
            $package = BokunService::getPackageDetails($request->productId);

            if (!$package) {
                return $this->errorResponse("Product Not Found!!", "The product does not exist.", "Product Availability");
            }

            // Get available time slots
            $timeSlots = BokunService::getTimeSlotsByDate(
                $package->operator_id,
                $package->package_id,
                $package->affiliate_id,
                $fromDate,
                $toDate
            );

            if (empty($timeSlots)) {
                return response()->json([]);
            }

            // Get rate groups — use fromDate year so availability for future years shows correct rates
            $rateGroups = BokunService::rateGroups($package, 'availability', Carbon::parse($fromDate)->year);

            if (!$rateGroups) {
                return $this->errorResponse(
                    "Rate Groups Not Found!!",
                    "Either rate groups (ageband) are missing or data not found.",
                    "Product Availability"
                );
            }

            // Prepare pricing for each rate group
            $pricingCategories = collect($rateGroups)->map(function ($group) {
                $base = $group->rate + $group->additional_charge + $group->permit_fee;
                $total = $base + ($base * ((float) $group->processing_charge_percentage) / 100);

                return [
                    'pricingCategoryId' => (string) $group->id,
                    'price' => [
                        'currency' => 'USD',
                        'amount' => (string) round($total, 2) // converted to string
                    ]
                ];
            })->values()->all();

            // Get rate types and attach pricing
            $rateTypesArr = collect(BokunService::rates($package))->map(fn($rate) => [
                'rateId' => $rate['id'],
                'pricePerPerson' => [
                    'pricingCategoryWithPrice' => $pricingCategories
                ]
            ])->values()->all();

            // Final availability data with date/time breakdown
            foreach ($timeSlots as $slot) {
                $date = Carbon::parse($slot->date);
                $time = Carbon::parse($slot->time_from);

                // Compare slot datetime against the OTA cutoff using the operator's timezone.
                // ota_duration=0 means no cutoff beyond now (past slots are still excluded via availableSeats=0).
                $timezone = $slot->timezone ?: 'UTC';
                $nowInTz = Carbon::now($timezone);
                $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->date . ' ' . $slot->time_from, $timezone);
                $cutoffDateTime = $nowInTz->copy()->addHours((int) ($package->ota_duration ?? 0));
                $isBlocked = $package->package_has_slots && $slotDateTime->lt($cutoffDateTime);

                $availableSeats = $isBlocked ? 0 : $slot->available_seats;

                if ($availableSeats > 0) {
                    $productAvailability[] = [
                        'capacity' => $availableSeats,
                        'date' => [
                            'year' => $date->year,
                            'month' => $date->month,
                            'day' => $date->day
                        ],
                        'time' => [
                            'hour' => $time->hour,
                            'minute' => $time->minute
                        ],
                        'rates' => $rateTypesArr
                    ];
                }
            }

            return response()->json($productAvailability);
        } catch (Exception $exception) {
            Log::error("Product Availability API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to retrieve product availability", $exception->getMessage(), "Product Availability");
        }
    }

    /**
     * This call secures necessary resource(s), such as activity time slot which can later become a booking.
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function bookingReservation($request)
    {
        DB::beginTransaction();
        try {
            $productCode = $request['reservationData']['productId'];
            $reservationDate = $request['reservationData']['date'];
            $reservationTime = $request['reservationData']['time'];

            $tourDate = Carbon::createFromDate($reservationDate['year'], $reservationDate['month'], $reservationDate['day'])->toDateString();

            if (!$reservationTime || !isset($reservationTime['hour'], $reservationTime['minute'])) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'A valid start time is required. Ensure this product\'s start times are mapped in Bokun.']
                ]);
            }
            $tourTime = Carbon::createFromTime($reservationTime['hour'], $reservationTime['minute'])->format('H:i');

            // 1. Validate Package
            $package = BokunService::getPackageDetails($productCode);
            if (!$package) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'This product does not exist.']
                ]);
            }

            // 2. Validate Rate Groups
            $rateGroups = BokunService::rateGroups($package, 'availability', Carbon::parse($tourDate)->year);
            if (!$rateGroups) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'Rate groups (age bands) are missing or unavailable.']
                ]);
            }

            // 3. Past date check
            if (Carbon::parse($tourDate)->lt(Carbon::now()->startOfDay())) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'The requested travel date is in the past.']
                ]);
            }

            // 4. Resolve the tour_slot_times row for this date/time/package and lock it for
            // the rest of this transaction, so a concurrent Bokun or OCTO request against the
            // same slot can't read stale availability before this reservation commits.
            $slot = collect(DB::select("
                SELECT `tst`.`id` as `tour_slot_id`
                FROM `tour_slot_times` AS `tst`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tst`.`tour_package_id`
                WHERE `tst`.`bookable_status` = 'Open'
                AND `tst`.`hide_slot` = 0
                AND `tst`.`time_from` = ?
                AND `tst`.`date` = ?
                AND `tp`.`id` = ?
            ", [$tourTime, $tourDate, $package->package_id]))->first();

            if ($slot && $slot->tour_slot_id) {
                TimeSlotService::lockSlotForUpdate((int) $slot->tour_slot_id);
            }

            // 5. Check availability for this slot (active reservations are subtracted inside)
            $availability = BokunService::checkSelectedTimeSlotBooking(
                $tourTime,
                $package->package_id,
                $tourDate,
                $package->affiliate_id
            );

            if (!$availability) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'The selected tour date or time is unavailable or has already passed.']
                ]);
            }

            // 5b. Reject reservation if slot falls within the OTA booking cutoff window.
            // Uses operator timezone so cutoff and slot time are compared in the same timezone.
            if (!empty($package->ota_duration) && $package->package_has_slots) {
                $timezone = $availability->timezone ?: 'UTC';
                $cutoffDateTime = Carbon::now($timezone)->addHours((int) $package->ota_duration);
                $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $tourDate . ' ' . $tourTime . ':00', $timezone);
                if ($slotDateTime->lt($cutoffDateTime)) {
                    DB::rollback();
                    return response()->json([
                        'failedReservation' => ['reservationError' => 'This slot is no longer available for online booking as it is within the cutoff window.']
                    ]);
                }
            }

            // 6. Determine if slot is open
            $availability->slot_status = $this->resolveSlotOpenStatus($availability);

            if ($availability->slot_status === 'Closed') {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'The selected slot is no longer available. Please choose another time.']
                ]);
            }

            // 7. Validate rates and count tourists
            $rates = BokunService::rates($package);
            $validRateIds = array_column($rates, 'id');

            // Infants do not occupy seats — count only non-infant passengers for seat capacity checks.
            $totalNonInfantPax = 0;
            $totalInfantPax = 0;
            collect($request['reservationData']['reservations'])
                ->filter(fn($r) => in_array($r['rateId'], $validRateIds) && is_array($r['passengers']))
                ->each(function ($r) use (&$totalNonInfantPax, &$totalInfantPax) {
                    foreach ($r['passengers'] as $passenger) {
                        $catId = (int)($passenger['pricingCategoryId'] ?? 0);
                        if ($catId > 0) {
                            TourPackageRateService::checkIsInfantRate($catId)
                                ? $totalInfantPax++
                                : $totalNonInfantPax++;
                        }
                    }
                });

            // 8. Seat availability check (available_seats already accounts for active reservations)
            $totalSeats = $availability->available_seats;

            if ($totalSeats === 0) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => 'No seats are available for the selected date or time.']
                ]);
            }

            if ($totalNonInfantPax > $totalSeats) {
                DB::rollback();
                return response()->json([
                    'failedReservation' => ['reservationError' => "Only {$totalSeats} seat(s) are available."]
                ]);
            }

            // $slot was already resolved and locked in step 4 above.

            $reservationCode = BokunService::generateReservationCode();
            $expirationDateTimeString = Carbon::now()->addMinutes(30)->format('Y-m-d H:i:s');

            // Create reservation — store only non-infant pax so the seat hold
            // matches how available_seats is tracked (infants excluded from capacity).
            $this->bokunReservation::create([
                'tour_package_id' => $package->package_id,
                'tour_slot_id' => $slot->tour_slot_id ?? null,
                'product_code' => $productCode,
                'reservation_code' => $reservationCode,
                'date' => $tourDate,
                'time' => $tourTime,
                'seats' => $totalNonInfantPax,
                'infants' => $totalInfantPax,
                'expired_at' => $expirationDateTimeString,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'successfulReservation' => ['reservationConfirmationCode' => $reservationCode]
            ]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error("Booking Reservation API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to reserve the product details for the booking", $exception->getMessage(), "Booking Reservation");
        }
    }

    /**
     * This call allows the reseller to indicate that previously made reservation should be cancelled.
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function bookingCancelReservation($request)
    {
        DB::beginTransaction();
        try {
            $reservation = $this->bokunReservation::where('reservation_code', $request['reservationConfirmationCode'])->first();

            if (!$reservation) {
                return response()->json([
                    'failedReservationCancellation' => [
                        'reservationCancellationError' => 'Reservation not found or already expired.'
                    ]
                ]);
            }

            $reservation->delete();
            DB::commit();

            return response()->json([
                'successfulReservationCancellation' => new \stdClass
            ]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error("Booking Cancel Reservation API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to cancel the reserved product details for the booking", $exception->getMessage(), "Booking Cancel Reservation");
        }
    }

    /**
     * This call is to proceed with the booking.
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function bookingConfirmation($request)
    {
        DB::beginTransaction();
        try {
            $logContext = ['ts' => now()->toDateTimeString()];

            $bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
            $prefix = optional($bookingPrefix)->value ?? "";
            $productCode = $request['reservationData']['productId'];
            $reservationDate = $request['reservationData']['date'];
            $reservationTime = $request['reservationData']['time'];
            $reservationConfirmationCode = $request['reservationConfirmationCode'];

            $tourDate = Carbon::createFromDate($reservationDate['year'], $reservationDate['month'], $reservationDate['day'])->toDateString();

            if (!$reservationTime || !isset($reservationTime['hour'], $reservationTime['minute'])) {
                return response()->json([
                    'failedBooking' => ['bookingError' => 'A valid start time is required. Ensure this product\'s start times are mapped in Bokun.']
                ]);
            }
            $tourTime = Carbon::createFromTime($reservationTime['hour'], $reservationTime['minute'])->format('H:i');

            $logContext += ['productCode' => $productCode, 'tourDate' => $tourDate, 'tourTime' => $tourTime, 'reservationCode' => $reservationConfirmationCode];
            // Log::error("Bokun Confirm [START] " . json_encode($logContext));

            /* Check availability before confirm booking */
            // 1. Validate Package
            $package = BokunService::getPackageDetails($productCode);
            if (!$package) {
                // Log::error("Bokun Confirm [FAIL] Package not found. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'This product does not exist.']
                ]);
            }

            $logContext['packageId'] = $package->package_id;
            $logContext['affiliateId'] = $package->affiliate_id;

            /* If reservation expired */
            $reservation = $this->bokunReservation::where([
                ['tour_package_id', $package->package_id],
                ['reservation_code', $reservationConfirmationCode],
                ['date', $tourDate],
                ['time', $tourTime],
                ['expired_at', '>=', now()]
            ])->first();

            if (!$reservation || now()->greaterThanOrEqualTo($reservation->expired_at)) {
                // Log::error("Bokun Confirm [FAIL] Reservation expired or not found. " . json_encode($logContext + ['reservationFound' => (bool) $reservation, 'expiredAt' => $reservation->expired_at ?? null]));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'Reservation not found or already expired.']
                ]);
            }

            // 2. Validate Rate Groups
            $rateGroups = BokunService::rateGroups($package, 'availability', Carbon::parse($tourDate)->year);
            if (!$rateGroups) {
                // Log::error("Bokun Confirm [FAIL] No rate groups. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'Rate groups (age bands) are missing or unavailable.']
                ]);
            }

            // 3. Past date check
            if (Carbon::parse($tourDate)->lt(Carbon::now()->startOfDay())) {
                // Log::error("Bokun Confirm [FAIL] Past date. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'The requested travel date is in the past.']
                ]);
            }

            // 5. Check availability for this slot (exclude this reservation from deduction — it's being confirmed, not pending)
            $availability = BokunService::checkSelectedTimeSlotBooking(
                $tourTime,
                $package->package_id,
                $tourDate,
                $package->affiliate_id,
                $reservationConfirmationCode
            );

            if (!$availability) {
                // Log::error("Bokun Confirm [FAIL] Availability check returned null (slot not found). " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'The selected tour date or time is unavailable or has already passed.']
                ]);
            }

            // CTB check intentionally omitted at confirmation. A valid non-expired reservation
            // already exists (validated above), meaning the slot cleared the CTB gate at reservation
            // time. Re-checking here causes a race condition where the CTB window shifts between
            // reservation and confirmation, rejecting a legitimately held slot after Viator has
            // already confirmed and charged the customer (confirmed by Bokun support, 2026-05-22).

            $logContext['availableSeats'] = $availability->available_seats;
            $logContext['bookedSeats'] = $availability->booked_seats;
            $logContext['bookableStatus'] = $availability->bookable_status;
            $logContext['slotTimeFrom'] = $availability->time_from;

            // 6. Determine if slot is open
            $availability->slot_status = $this->resolveSlotOpenStatus($availability);

            if ($availability->slot_status === 'Closed') {
                // Log::error("Bokun Confirm [FAIL] Slot closed. currentTime={$currentTime}, slotTime={$slotTime}. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'The selected slot is no longer available. Please choose another time.']
                ]);
            }

            // // 7. Validate rates and count tourists
            $rates = BokunService::rates($package);
            $validRateIds = array_column($rates, 'id');

            // Infants do not occupy seats — count only non-infant passengers for seat capacity checks.
            $totalNonInfantPax = collect($request['reservationData']['reservations'])
                ->filter(fn($r) => in_array($r['rateId'], $validRateIds) && is_array($r['passengers']))
                ->reduce(function ($carry, $r) {
                    foreach ($r['passengers'] as $passenger) {
                        $catId = (int)($passenger['pricingCategoryId'] ?? 0);
                        if ($catId > 0 && !TourPackageRateService::checkIsInfantRate($catId)) {
                            $carry++;
                        }
                    }
                    return $carry;
                }, 0);

            // 8. Seat availability check
            $totalSeats = $availability->available_seats;

            if ($totalSeats === 0) {
                // Log::error("Bokun Confirm [FAIL] No seats available (totalSeats===0, type=" . gettype($totalSeats) . "). " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'No seats are available for the selected date or time.']
                ]);
            }

            if(($reservation->seats > $totalSeats) || ($totalNonInfantPax > $totalSeats)) {
                // Log::error("Bokun Confirm [FAIL] Not enough seats. reservationSeats={$reservation->seats}, totalNonInfantPax={$totalNonInfantPax}, totalSeats={$totalSeats}. " . json_encode($logContext));
                // Log::error("Bokun Confirm [FAIL] Not enough seats. reservationSeats={$reservation->seats}, totalPax={$totalPax}, totalSeats={$totalSeats}. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => "Only {$totalSeats} seat(s) are available."]
                ]);
            }

            // Use the tour_slot_id stored at reservation time; fall back to a direct lookup
            // for reservations that predate this field being stored.
            $tourSlotId = $reservation->tour_slot_id;

            if (!$tourSlotId) {
                $slotRow = collect(DB::select("
                    SELECT `tst`.`id` as `tour_slot_id`
                    FROM `tour_slot_times` AS `tst`
                    INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tst`.`tour_package_id`
                    WHERE `tst`.`bookable_status` = 'Open'
                    AND `tst`.`hide_slot` = 0
                    AND `tst`.`time_from` = ?
                    AND `tst`.`date` = ?
                    AND `tp`.`id` = ?
                ", [$tourTime, $tourDate, $package->package_id]))->first();
                $tourSlotId = $slotRow->tour_slot_id ?? null;
            }

            if (!$tourSlotId) {
                // Log::error("Bokun Confirm [FAIL] Slot ID lookup failed. tourTime={$tourTime}, tourDate={$tourDate}, packageId={$package->package_id}. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'Selected time slot not found or is not bookable.']
                ]);
            }

            $logContext['tourSlotId'] = $tourSlotId;

            $firstPassenger = $request['reservationData']['customerContact'] ?? '';
            $firstName = $firstPassenger['firstName'] ?? '';
            $lastName = $firstPassenger['lastName'] ?? '';
            $email = $firstPassenger['email'] ?? '';
            $phoneNumber = $firstPassenger['phone'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);

            $bookingTotal = $platformFees = 0;
            $totalTourists = $totalInfants = 0;
            $tbTransact = [];
            $addedPeoplesText = [];
            $paxCount = [];

            foreach ($rateGroups as $key => $rate) {
                $paxCount[(string)$rate->id] = 0; // add each rate id as key with value 0
            }

            /* Calculate total booking amount start */
            foreach ($request['reservationData']['reservations'] as $key => $passengerReservation) {
                foreach ($passengerReservation['passengers'] as $key => $passenger) {
                    $rateId = (string)$passenger['pricingCategoryId'];
                    $price = $passenger['pricePerPassenger']['amount'] ?? null;

                    if (!is_numeric($price)) {
                        return response()->json([
                            'failedBooking' => ['bookingError' => 'Each passenger must include a valid pricePerPassenger.amount.']
                        ]);
                    }

                    $bookingTotal += $price;

                    if (isset($paxCount[$rateId])) {
                        $paxCount[$rateId]++;
                    }
                }
            }
            /* Calculate total booking amount end */

            // Now calculate tourist vs infant totals
            foreach ($paxCount as $rateId => $count) {
                $checkIsInfantRate = TourPackageRateService::checkIsInfantRate($rateId);

                if ($checkIsInfantRate == 1) {
                    $totalInfants += $count;
                } else {
                    $totalTourists += $count;
                }
            }

            // Check Customer named Bokun and with field `bokun` = 1
            $customer = $this->customer::find($package->affiliate_id);

            if (!$customer) {
                // Log::error("Bokun Confirm [FAIL] Customer not found. affiliateId={$package->affiliate_id}. " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'Failed to create or retrieve customer.']
                ]);
            }

            $bookingChannel = $request['reservationData']['bookingSource']['bookingChannel']['title'] ?? '';
            $notes = isset($request['reservationData']['notes']) ? $request['reservationData']['notes'] : '';

            $bookingChannel = $request['reservationData']['bookingSource']['bookingChannel']['title'] ?? '';
            $channelDetails = BokunUtils::generateBookingReferenceCode($bookingChannel);

            if (!$channelDetails) {
                // Log::error("Bokun Confirm [FAIL] Channel details null. bookingChannel='{$bookingChannel}', request.bookingSource=" . json_encode($request['reservationData']['bookingSource'] ?? null) . ". " . json_encode($logContext));
                return response()->json([
                    'failedBooking' => ['bookingError' => 'Unable to determine booking channel source.']
                ]);
            }

            // $bookingConfirmationCode = $channelDetails['booking_code'];
            $otaPrefix = $channelDetails['ota_prefix'];
            $otaReferenceCode = $otaPrefix . '-' . (
                $otaPrefix === 'BOKUNTEST'
                    ? ($request['reservationData']['platformBookingId'] ?? $request['reservationData']['platformId'])
                    : ($request['reservationData']['externalSaleId'] ?? $request['reservationData']['platformId'])
            ); // The original OTA reference code is generated on OTAs (Viator, GetYourGuide, Headout)
            $bookingVia = $channelDetails['booking_via'];
            $requestFrom = $channelDetails['request_from'];

            // Bokun's own booking ID — unique per booking event, even on reschedule.
            // Used as the bookingConfirmationCode returned to Bokun so that cancel/amend
            // calls reference this unique ID rather than the OTA reference (which is reused
            // across reschedules and would otherwise cause duplicate records).
            $platformBookingId = $request['reservationData']['platformBookingId'] ?? null;
            $bokunReferenceCode = $platformBookingId ? ($otaPrefix . '-' . $platformBookingId) : null;

            // Store booking information in tour_bookings_master table
            $tourBooking = $this->tourBooking::create([
                'ota_reference_booking' => $otaReferenceCode,
                'bokun_reference_code'  => $bokunReferenceCode,
                'tour_package_id' => $package->package_id,
                'tour_slot_id' => $tourSlotId,
                'customer_id' => $customer->id,
                'name' => $fullName,
                'email' => $email,
                'phone_number' => $phoneNumber,
                'service_commission' => $platformFees,
                'tourists' => $totalTourists,
                'infants' => $totalInfants,
                'comments' => $notes,
                'note' => '',
                'mask_required' => 'Yes',
                'total' => $bookingTotal,
                'booking_total' => $bookingTotal,
                'liability_waiver_signing' => '',
                'status' => 'Booked',
                'created_by' => $customer->id,
                'created_by_user_type' => 'Affiliate'
            ]);

            if (!$tourBooking) {
                return response()->json([
                    'failedBooking' => ['bookingError' => 'Failed to create tour booking.']
                ]);
            }

            if (!empty($notes)) {
                DB::table('tour_booking_history')
                    ->insert([
                        'tour_booking_id' => $tourBooking->id,
                        'operation' => 'Comments',
                        'details' => $notes,
                        'operation_at' => mysqlDT(),
                        'operation_by' => $customer->id,
                        'operation_by_user_type' => 'Affiliate',
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ]);
            }

            // Log::info("Booking Confirmation API Request ================== " . json_encode([
            //     'request' => $request->all(),
            //     'paxCount' => $paxCount,
            //     'rateGroups' => $rateGroups
            // ]));
            foreach ($rateGroups as $key => $rate) {
                $touristsForRate = $paxCount[(string)$rate->id] ?? 0;
                $isInfantRate = $rate->is_infant_rate;
			    $infants = ($isInfantRate == 1) ? $touristsForRate : 0;
                $groupTotal = ($touristsForRate * ($rate->rate + $rate->additional_charge + $rate->permit_fee)) * (1 + (float)$rate->processing_charge_percentage / 100);

                if ($touristsForRate > 0) {
                    $tbTransact[] = [
                        'tour_booking_id' => $tourBooking->id,
                        'tour_package_rate_id' => $rate->id,
                        'tourists' => ($isInfantRate == 0) ? $touristsForRate : 0,
                        'infants' => $infants,
                        'is_infant_rate' => $isInfantRate,
                        'additional_tax_percentage' => $rate->additional_tax_percentage,
                        'processing_charge_percentage' => $rate->processing_charge_percentage,
                        'nat_fees_percentage' => $package->service_commission_percentage,
                        'stripe_fees_percentage' => $package->stripe_commission_percentage,
                        'additional_charge' => ($rate->additional_charge),
                        'permit_fee' => $rate->permit_fee,
                        'rate' => $rate->rate,
                        'total' => $groupTotal,
                        'check_in_status' => '',
                        'created_by' => $customer->id,
                        'created_by_user_type' => 'Affiliate',
                        'created_at' => now(),
                    ];

                    $addedPeoplesText[] = "{$rate->rate_for} person $touristsForRate";
                }
            }

            DB::table('tour_bookings_transact')->insert($tbTransact);

            $touristsSum = $this->bookingTransact::select('total')->where('tour_booking_id', $tourBooking->id)->sum('total');

            // Update tour_booking_master table to update booking total and booking_total
            $this->tourBooking::where('id', $tourBooking->id)->update([
                'total' => $touristsSum,
                'booking_total' => $touristsSum
            ]);

            // Store History details in tour_booking_history
            $touristHistory = [
                'tour_booking_id' => $tourBooking->id,
                'tourists' => $totalTourists + $totalInfants,
                'operation' => 'Added',
                'details' => json_encode([
                    'sub_operation' => 'Tourists Added',
                    'discount_percentage' => 0,
                    'discount2_value' => 0,
                    'added' => $addedPeoplesText,
                    'newTourists' => $totalTourists + $totalInfants
                ], JSON_NUMERIC_CHECK),
                'operation_at' => now(),
                'operation_by' => $customer->id,
                'operation_by_user_type' => 'Affiliate',
                'updated_at' => now(),
                'created_at' => now(),
            ];

            DB::table('tour_booking_history')->insert($touristHistory);

            // Store payment details in transactions table
            $transactionId = DB::table('transactions')
                ->insertGetId([
                    'tour_booking_id' => $tourBooking->id,
                    'amount' => $touristsSum,
                    'fee_amount' => $platformFees,
                    'via' => $bookingVia,
                    'response_init' => '',
                    'response_end' => '',
                    'response_end_datetime' => now(),
                    'status' => 'Completed',
                    'payment_type' => 0,
                    'visitor' => request()->getClientIp(),
                    'request_from' => $requestFrom,
                    'created_by' => $customer->id,
                    'created_by_user_type' => 'Affiliate',
                    'created_at' => now(),
                ]);

            // Store Payment details in tour_booking_history table
            $historyId = DB::table('tour_booking_history')
                ->insertGetId([
                    'tour_booking_id' => $tourBooking->id,
                    'tourists' => $totalTourists + $totalInfants,
                    'transaction_id' => $transactionId,
                    'operation' => 'Payment',
                    'details' => json_encode(['amount' => $touristsSum, 'payment_type' => $bookingVia], JSON_NUMERIC_CHECK),
                    'operation_at' => now(),
                    'operation_by' => $customer->id,
                    'operation_by_user_type' => 'Affiliate',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);

            // Call service to store history update details
            BokunService::storePricingInHistory($tourBooking, $transactionId, $historyId, $bookingVia);

            // Update available seats in tour_slot_summaries table
            TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

            if ($reservation) {
                $reservation->delete();
            }

            DB::commit();

            $createBooking['successfulBooking'] = [
                'bookingConfirmationCode' => $bokunReferenceCode ?? $otaReferenceCode,
                'bookingTicket' => [
                    'qrTicket' => [
                        'ticketBarcode' => $prefix . "" . $tourBooking->id
                    ]
                ]
            ];

            // Log::error("Bokun Confirm [SUCCESS] bookingId={$tourBooking->id}, code={$otaReferenceCode}, tourists={$totalTourists}, infants={$totalInfants}, total={$touristsSum}. " . json_encode($logContext));

            return response()->json($createBooking);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error("Bokun Confirm [EXCEPTION] " . $exception->getMessage() . " at " . $exception->getFile() . ":" . $exception->getLine() . ". Request: " . json_encode($request->all()));
            return $this->errorResponse("Failed to confirm booking", $exception->getMessage(), "Booking Confirmation");
        }
    }

    /**
     * This call is to proceed with the cancel booking.
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function bookingCancellation($request)
    {
        DB::beginTransaction();
        try {
            $bookingReference = $request['bookingConfirmationCode'];

            // Validate and identify OTA source from the booking reference prefix.
            preg_match('/^([A-Z]+)-[A-Z0-9]+$/i', $bookingReference, $matches);
            $bookingReferencePrefix = $matches[1] ?? null;

            if (!$bookingReferencePrefix) {
                return response()->json([
                    'failedCancellation' => ['cancellationError' => 'Invalid booking reference format.']
                ]);
            }

            $otaPlatformTitle = BokunUtils::getTitleFromOtaBookingCode($bookingReference);

            if (!$otaPlatformTitle) {
                return response()->json([
                    'failedCancellation' => ['cancellationError' => 'Unknown booking source.']
                ]);
            }

            $cancelNote  = $otaPlatformTitle . ' booking cancelled';
            $refundVia   = $otaPlatformTitle;
            $requestFrom = strtoupper($otaPlatformTitle);

            // Fetch booking with operator timezone and cancellation window.
            $tourBooking = DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('tour_operators AS t_op', 't_op.id', '=', 'tp.tour_operator_id')
                ->where(function ($query) use ($bookingReference) {
                    $query->where('tbm.bokun_reference_code', $bookingReference)
                        ->orWhere('tbm.get_your_guide_reference_booking', $bookingReference)
                        ->orWhere('tbm.viator_bookingRef', $bookingReference)
                        ->orWhere('tbm.ota_reference_booking', $bookingReference);
                })
                ->orderByRaw("CASE WHEN tbm.status != 'Cancelled' THEN 0 ELSE 1 END")
                ->select(
                    'tbm.id AS booking_id',
                    'tbm.customer_id',
                    'tbm.tourists',
                    'tbm.infants',
                    'tbm.status',
                    't_op.timezone',
                    't_op.cancellation_within_days',
                    DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_date_time")
                )
                ->first();

            if (!$tourBooking) {
                return response()->json([
                    'failedCancellation' => ['cancellationError' => 'Booking not found!']
                ]);
            }

            if ($tourBooking->status === 'Cancelled') {
                return response()->json([
                    'failedCancellation' => ['cancellationError' => 'The booking has already been cancelled.']
                ]);
            }

            // Timezone-aware datetime — slot is stored in the operator's local timezone.
            $timezone     = $tourBooking->timezone ?: 'UTC';
            $tourDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $tourBooking->tour_date_time, $timezone);
            $nowInTz      = Carbon::now($timezone);

            // Timing restrictions only apply to future bookings.
            // Past-dated bookings may be cancelled when the OTA requests it (e.g. post-tour refund).
            // Note: deliberately does NOT block on check-in/redeemed status — once the OTA (Viator,
            // Headout, etc.) calls this endpoint, we must honor it and stay in sync with their side,
            // the same principle already applied to the OTA cutoff check being skipped on confirm.
            if ($tourDateTime->gt($nowInTz)) {
                // Cutoff is the exact tour slot datetime minus the operator's cancellation window.
                // E.g. cancellation_within_days=2 with tour at 09:45 → cutoff at 09:45 two days prior.
                $cancellationWithinDays = (int) ($tourBooking->cancellation_within_days ?? 0);
                if ($cancellationWithinDays > 0) {
                    $cutoffDateTime = $tourDateTime->copy()->subDays($cancellationWithinDays);
                    if ($nowInTz->gte($cutoffDateTime)) {
                        return response()->json([
                            'failedCancellation' => ['cancellationError' => 'Too late to cancel. Contact the tour operator.']
                        ]);
                    }
                }
            }

            $transaction = $this->transaction::where('tour_booking_id', $tourBooking->booking_id)
                ->where('status', 'Completed')
                ->first();

            if (!$transaction) {
                return response()->json([
                    'failedCancellation' => ['cancellationError' => 'No valid payment transaction found.']
                ]);
            }

            $customer = $this->customer::find($tourBooking->customer_id);

            // Update booking status
            $updated = DB::table('tour_bookings_master')
                ->where('id', $tourBooking->booking_id)
                ->update([
                    'status'               => 'Cancelled',
                    'updated_by'           => $customer->id,
                    'updated_by_user_type' => 'Affiliate',
                    'updated_at'           => now()
                ]);

            if (!$updated) {
                DB::rollBack();
                return response()->json([
                    'failedCancellation' => ['cancellationError' => 'Failed to update booking status.']
                ]);
            }

            BokunService::cancelBooking($tourBooking, $refundVia, $requestFrom, $cancelNote);

            DB::commit();

            return response()->json([
                'successfulCancellation' => new \stdClass
            ]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error("Booking Cancellation API Error: " . $exception->getMessage());
            return $this->errorResponse("Failed to cancel booking", $exception->getMessage(), "Booking Cancellation");
        }
    }

    /**
     * This call is to proceed with the amendment booking [Reschedule | Add People | Delete People]
     * @param object $request
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function bookingAmendment($request)
    {
        try {
            $bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
            $prefix = optional($bookingPrefix)->value ?? "";

            $bookingConfirmationCode = $request['bookingConfirmationCode'];
            $reservationData = $request['reservationData'];

            $productCode = $reservationData['productId'];
            $reservationDate = $reservationData['date'];
            $reservationTime = $reservationData['time'] ?? null;

            $tourDate = Carbon::createFromDate($reservationDate['year'], $reservationDate['month'], $reservationDate['day'])->toDateString();

            if (!$reservationTime || !isset($reservationTime['hour'], $reservationTime['minute'])) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'A valid start time is required. Ensure this product\'s start times are mapped in Bokun.']
                ]);
            }
            $tourTime = Carbon::createFromTime($reservationTime['hour'], $reservationTime['minute'])->format('H:i');

            // 1. Find existing booking by confirmation code.
            // New bookings return bokun_reference_code as the confirmation code (unique per Bokun
            // booking event). Older bookings that pre-date this column fall back to ota_reference_booking.
            // Order prefers active bookings so that OTA reschedule duplicates (same ota_reference_booking,
            // one Cancelled + one active) resolve to the active row. The status check below handles
            // the case where only a Cancelled booking exists (returns the correct error message).
            $tourBooking = $this->tourBooking::where(function ($query) use ($bookingConfirmationCode) {
                    $query->where('bokun_reference_code', $bookingConfirmationCode)
                        ->orWhere('ota_reference_booking', $bookingConfirmationCode);
                })
                ->orderByRaw("CASE WHEN status != 'Cancelled' THEN 0 ELSE 1 END")
                ->first();

            if (!$tourBooking) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'Booking not found.']
                ]);
            }

            if ($tourBooking->status === 'Cancelled') {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'Cannot amend a cancelled booking.']
                ]);
            }

            // 2. Validate Package
            $package = BokunService::getPackageDetails($productCode);
            if (!$package) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'This product does not exist.']
                ]);
            }

            // 3. Validate Rate Groups
            $rateGroups = BokunService::rateGroups($package, 'availability', Carbon::parse($tourDate)->year);
            if (!$rateGroups) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'Rate groups (age bands) are missing or unavailable.']
                ]);
            }

            // 4. Past date check
            if (Carbon::parse($tourDate)->lt(Carbon::now()->startOfDay())) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'The requested travel date is in the past.']
                ]);
            }

            // 5. Check availability for the new slot
            $availability = BokunService::checkSelectedTimeSlotBooking(
                $tourTime,
                $package->package_id,
                $tourDate,
                $package->affiliate_id
            );

            if (!$availability) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'The selected tour date or time is unavailable or has already passed.']
                ]);
            }

            // Determine early whether the slot is changing — needed for cutoff and seat checks below.
            $isSlotChanged = ($tourBooking->tour_slot_id != $availability->id);

            // 5c. Reject reschedule if the new slot falls within the OTA booking cutoff window.
            // Only applies when the slot is actually changing — pax-only changes on an already-confirmed
            // slot should not be blocked by the cutoff (the booking already cleared the gate).
            if ($isSlotChanged && !empty($package->ota_duration) && $package->package_has_slots) {
                $timezone = $availability->timezone ?: 'UTC';
                $cutoffDateTime = Carbon::now($timezone)->addHours((int) $package->ota_duration);
                $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $tourDate . ' ' . $tourTime . ':00', $timezone);
                if ($slotDateTime->lt($cutoffDateTime)) {
                    return response()->json([
                        'failedAmendment' => ['amendmentError' => 'This slot is no longer available for online booking as it is within the cutoff window.']
                    ]);
                }
            }

            // 6. Determine if slot is open
            $availability->slot_status = $this->resolveSlotOpenStatus($availability);

            if ($availability->slot_status === 'Closed') {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'The selected slot is no longer available. Please choose another time.']
                ]);
            }

            // 7. Validate rates and count tourists
            $rates = BokunService::rates($package);
            $validRateIds = array_column($rates, 'id');

            // Reject amendments that would leave the booking with no passengers at all —
            // use booking/cancel to cancel a booking instead of zeroing it out via amend.
            $totalRequestedPax = collect($reservationData['reservations'])
                ->filter(fn($r) => is_array($r['passengers'] ?? null))
                ->reduce(fn($carry, $r) => $carry + count($r['passengers']), 0);

            if ($totalRequestedPax === 0) {
                return response()->json([
                    'failedAmendment' => ['amendmentError' => 'Amendment would leave the booking with zero passengers. Use booking/cancel to cancel the booking instead.']
                ]);
            }

            // $totalPax = collect($reservationData['reservations'])
            //     ->filter(fn($r) => in_array($r['rateId'], $validRateIds) && is_array($r['passengers']))
            //     ->reduce(fn($carry, $r) => $carry + count($r['passengers']), 0);

            // Infants do not occupy seats — exclude infant-rate passengers from capacity checks.
            // Note: rateId is a generic rate-type identifier (e.g. '1' = Global Rate), not a
            // TourPackageRate ID. The actual rate group per passenger is pricingCategoryId.
            $totalNonInfantPax = collect($reservationData['reservations'])
                ->filter(fn($r) => in_array($r['rateId'], $validRateIds) && is_array($r['passengers']))
                ->reduce(function ($carry, $r) {
                    foreach ($r['passengers'] as $passenger) {
                        $catId = (int)($passenger['pricingCategoryId'] ?? 0);
                        if ($catId > 0 && !TourPackageRateService::checkIsInfantRate($catId)) {
                            $carry++;
                        }
                    }
                    return $carry;
                }, 0);

            // Determine early whether the slot is changing — needed for the capacity check below.
            // 8. Seat availability check (infants excluded — they never consume seat capacity)
            $totalSeats = $availability->available_seats;

            if ($isSlotChanged) {
                // Reschedule: this booking is NOT yet on the new slot, so available_seats
                // does not account for it. All non-infant seats must fit in the new slot.
                Log::info('[Amendment] Reschedule seat check', [
                    'confirmationCode'      => $bookingConfirmationCode,
                    'currentSlotId'         => $tourBooking->tour_slot_id,
                    'targetSlotId'          => $availability->id,
                    'targetDate'            => $tourDate,
                    'targetTime'            => $tourTime,
                    'targetBookableStatus'  => $availability->bookable_status,
                    'slotCapacity'          => $availability->seats,
                    'aflBookedSeats'        => $availability->booked_seats,
                    'otherBookedSeats'      => $availability->global_booked_seats - (int) $availability->booked_seats,
                    'totalBookedSeats'      => $availability->global_booked_seats,
                    'availableSeats'        => $totalSeats,
                    'requestedNonInfantPax' => $totalNonInfantPax,
                ]);
                if ($totalSeats === 0) {
                    return response()->json([
                        'failedAmendment' => ['amendmentError' => 'No seats are available for the selected date or time.']
                    ]);
                }

                if ($totalNonInfantPax > $totalSeats) {
                    return response()->json([
                        'failedAmendment' => ['amendmentError' => "Only {$totalSeats} seat(s) are available."]
                    ]);
                }
            } else {
                // Same slot, pax change only: available_seats already excludes this booking's
                // current non-infant seats, so only the NET new non-infant pax needs to fit.
                // Infants are not counted against available_seats in either direction.
                $netNewPax = max(0, $totalNonInfantPax - $tourBooking->tourists);

                if ($netNewPax > 0 && $totalSeats === 0) {
                    return response()->json([
                        'failedAmendment' => ['amendmentError' => 'No seats are available for the selected date or time.']
                    ]);
                }

                if ($netNewPax > $totalSeats) {
                    return response()->json([
                        'failedAmendment' => ['amendmentError' => "Only {$totalSeats} seat(s) are available."]
                    ]);
                }
            }

            // 9. Detect pax changes
            $newPaxByRateGroup = [];
            foreach ($reservationData['reservations'] as $reservation) {
                if (!is_array($reservation['passengers'] ?? null)) continue;
                foreach ($reservation['passengers'] as $passenger) {
                    $catId = (int)($passenger['pricingCategoryId'] ?? 0);
                    if ($catId > 0) {
                        $newPaxByRateGroup[$catId] = ($newPaxByRateGroup[$catId] ?? 0) + 1;
                    }
                }
            }

            $existingTransacts = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)
                ->where('deleted_by', 0)
                ->get();

            $existingPaxByRateGroup = [];
            foreach ($existingTransacts as $et) {
                $count = ($et->is_infant_rate == 1) ? $et->infants : $et->tourists;
                $existingPaxByRateGroup[(int)$et->tour_package_rate_id] = $count;
            }

            $addedPax   = [];
            $deletedPax = [];
            $allRateGroupIds = array_unique(array_merge(array_keys($newPaxByRateGroup), array_keys($existingPaxByRateGroup)));

            foreach ($allRateGroupIds as $rgId) {
                $diff = ($newPaxByRateGroup[$rgId] ?? 0) - ($existingPaxByRateGroup[$rgId] ?? 0);
                if ($diff > 0)     $addedPax[$rgId]   = $diff;
                elseif ($diff < 0) $deletedPax[$rgId] = abs($diff);
            }
            $hasPaxChange = !empty($addedPax) || !empty($deletedPax);

            $bookingChannel = $request['reservationData']['bookingSource']['bookingChannel']['title'] ?? '';
            $channelDetails = BokunUtils::generateBookingReferenceCode($bookingChannel);
            $requestFrom = $channelDetails['request_from'];

            // Nothing changed — return early
            if (!$isSlotChanged && !$hasPaxChange) {
                return response()->json([
                    'successfulAmendment' => [
                        'amendmentConfirmationCode' => $bookingConfirmationCode,
                        'bookingTicket' => [
                            'qrTicket' => [
                                'ticketBarcode' => $prefix . "" . $tourBooking->id
                            ]
                        ]
                    ]
                ]);
            }

            // 10. Reschedule only if slot/date changed
            if ($isSlotChanged) {
                DB::beginTransaction();
                
                $rescheduleRequest = new \Illuminate\Http\Request();
                $rescheduleRequest->merge([
                    'tour_slot_time' => $availability->id
                ]);

                try {
                    $this->tourBookingRepository->reschedule(
                        $rescheduleRequest,
                        $tourBooking->id,
                        'Affiliate',
                        $package->affiliate_id
                    );
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error("Booking Amendment Reschedule Error: " . $e->getMessage());
                    return response()->json([
                        'failedAmendment' => ['amendmentError' => $e->getMessage()]
                    ]);
                }

                DB::commit();
            }

            // 11. Handle pax changes
            if ($hasPaxChange) {
                $tourBooking->refresh();

                $existingTransaction = $this->transaction::where('tour_booking_id', $tourBooking->id)
                    ->where('status', 'Completed')
                    ->whereNull('charge_type')
                    ->orderBy('id', 'ASC')
                    ->first();
                $paymentVia = $existingTransaction ? $existingTransaction->via : 'Bokun';

                $createdByWithName = BookingService::createdUserInfo(false, null, $package->affiliate_id, 'Affiliate');

                DB::beginTransaction();

                // 11a. Deleted pax — update bookingTransact rows, accumulate totals
                $totalRefundAmount      = 0;
                $totalRefundTicketCost  = 0;
                $totalRefundPermitFees  = 0;
                $netDeletedTourists     = 0;
                $netDeletedInfants      = 0;
                $deletedLabels          = [];
                $refundType             = 'Full Refund minus Fees';
                $pendingRefundHistories = [];

                foreach ($deletedPax as $rateGroupId => $count) {
                    $transact = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)
                        ->where('tour_package_rate_id', $rateGroupId)
                        ->where('deleted_by', 0)
                        ->first();
                    if (!$transact) continue;

                    $isInfant         = ($transact->is_infant_rate == 1);
                    $rate             = $transact->rate + $transact->additional_charge + $transact->permit_fee + $transact->partner_fees_amount;
                    $per              = $rate * $transact->additional_tax_percentage / 100;
                    $total            = round(($rate + $per) * $count, 2);

                    $newTourists      = $isInfant ? $transact->tourists : max(0, $transact->tourists - $count);
                    $newInfants       = $isInfant ? max(0, $transact->infants - $count) : $transact->infants;
                    $newTransactTotal = round($transact->total - $total, 2);

                    if ($newTourists == 0 && $newInfants == 0) {
                        $this->bookingTransact::where('id', $transact->id)->update([
                            'total'                   => $newTransactTotal,
                            'tourists'                => $newTourists,
                            'infants'                 => $newInfants,
                            'check_in_status'         => '',
                            'updated_at'              => now(),
                            'updated_by'              => $package->affiliate_id,
						    'updated_by_user_type'    => 'Affiliate',
                        ]);
                    } else {
                        $this->bookingTransact::where('id', $transact->id)->update([
                            'total'                => $newTransactTotal,
                            'tourists'             => $newTourists,
                            'infants'              => $newInfants,
                            'updated_at'           => now(),
                            'updated_by'           => $package->affiliate_id,
                            'updated_by_user_type' => 'Affiliate'
                        ]);
                    }

                    $rateGroupLabel        = optional($this->tourPackageRate::withTrashed()->find($rateGroupId))->rate_for ?? 'person';
                    $deletedLabels[]        = $rateGroupLabel . ' person ' . $count;
                    $totalRefundAmount     += $total;
                    $totalRefundTicketCost += round($rate * $count, 2);
                    $totalRefundPermitFees += round($transact->permit_fee * $count, 2);
                    $netDeletedTourists    += $isInfant ? 0 : $count;
                    $netDeletedInfants     += $isInfant ? $count : 0;

                    // Collect per-rate refund data to store after the transaction is created
                    // so transaction_id can be linked
                    $pendingRefundHistories[] = [
                        'total'      => $total,
                        'label'      => $rateGroupLabel . ' person ' . $count,
                        'permitFees' => round($transact->permit_fee * $count, 2),
                    ];
                }

                // 11b. Added pax — update bookingTransact rows, accumulate totals
                $totalAddAmount     = 0;
                $totalAddTicketCost = 0;
                $totalAddPermitFees = 0;
                $netAddedTourists   = 0;
                $netAddedInfants    = 0;
                $addedLabels        = [];

                foreach ($addedPax as $rateGroupId => $count) {
                    $transact = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)
                        ->where('tour_package_rate_id', $rateGroupId)
                        ->where('deleted_by', 0)
                        ->first();

                    if ($transact) {
                        // Existing transact row — update it
                        $isInfant  = ($transact->is_infant_rate == 1);
                        $rate      = $transact->rate + $transact->additional_charge + $transact->permit_fee + $transact->partner_fees_amount;
                        $per       = $rate * $transact->additional_tax_percentage / 100;
                        $addAmount = round(($rate + $per) * $count, 2);

                        $this->bookingTransact::where('id', $transact->id)->update([
                            'total'                => round($transact->total + $addAmount, 2),
                            'tourists'             => $isInfant ? $transact->tourists : $transact->tourists + $count,
                            'infants'              => $isInfant ? $transact->infants + $count : $transact->infants,
                            'updated_by'           => $package->affiliate_id,
                            'updated_by_user_type' => 'Affiliate'
                        ]);

                        $permitFeeForAccum = $transact->permit_fee;
                    } else {
                        // No transact row exists (e.g. infants being added to a booking that originally had none)
                        // Look up rate details from $rateGroups fetched earlier in this method
                        $rateGroupObj = collect($rateGroups)->firstWhere('id', $rateGroupId);
                        if (!$rateGroupObj) continue;

                        $isInfant          = ($rateGroupObj->is_infant_rate == 1);
                        $rate              = $rateGroupObj->rate + $rateGroupObj->additional_charge + $rateGroupObj->permit_fee;
                        $per               = $rate * $rateGroupObj->additional_tax_percentage / 100;
                        $addAmount         = round(($rate + $per) * $count, 2);
                        $referenceTransact = $existingTransacts->first();

                        DB::table('tour_bookings_transact')->insert([
                            'tour_booking_id'              => $tourBooking->id,
                            'tour_package_rate_id'         => $rateGroupId,
                            'tourists'                     => $isInfant ? 0 : $count,
                            'infants'                      => $isInfant ? $count : 0,
                            'is_infant_rate'               => $isInfant ? 1 : 0,
                            'rate'                         => $rateGroupObj->rate,
                            'additional_charge'            => $rateGroupObj->additional_charge,
                            'permit_fee'                   => $rateGroupObj->permit_fee,
                            'additional_tax_percentage'    => $rateGroupObj->additional_tax_percentage,
                            'processing_charge_percentage' => $rateGroupObj->processing_charge_percentage,
                            'nat_fees_percentage'          => $referenceTransact->nat_fees_percentage ?? $package->service_commission_percentage,
                            'stripe_fees_percentage'       => $referenceTransact->stripe_fees_percentage ?? $package->stripe_commission_percentage,
                            'partner_fees_amount'          => 0,
                            'total'                        => $addAmount,
                            'deleted_by'                   => 0,
                            'check_in_status'              => '',
                            'created_by'                   => $package->affiliate_id,
                            'created_by_user_type'         => 'Affiliate',
                            'updated_by'                   => $package->affiliate_id,
                            'updated_by_user_type'         => 'Affiliate',
                            'created_at'                   => now(),
                            'updated_at'                   => now(),
                        ]);

                        $permitFeeForAccum = $rateGroupObj->permit_fee;
                    }

                    $rateGroupLabel = optional($this->tourPackageRate::withTrashed()->find($rateGroupId))->rate_for ?? 'person';
                    $addedLabels[]  = $rateGroupLabel . ' person ' . $count;

                    $totalAddAmount     += $addAmount;
                    $totalAddTicketCost += round($rate * $count, 2);
                    $totalAddPermitFees += round($permitFeeForAccum * $count, 2);
                    $netAddedTourists   += $isInfant ? 0 : $count;
                    $netAddedInfants    += $isInfant ? $count : 0;
                }

                // Net pax change — follows changePeopleBooking() cash payment logic for 3 cases:
                // Case 1 (Add only):   netAmount > 0  → payment path  (total + booking_total increase)
                // Case 2 (Delete only): netAmount < 0 → refund path   (only total decreases)
                // Case 3 (Mixed):      sign of netAmount determines which path is taken
                $netAmount = round($totalAddAmount - $totalRefundAmount, 2);

                // ONE tourBooking update — tourists/infants always, total/booking_total by net sign
                $tourBooking->refresh();
                $tourBookingUpdates = [
                    'tourists' => max(0, $tourBooking->tourists + $netAddedTourists - $netDeletedTourists),
                    'infants'  => max(0, $tourBooking->infants  + $netAddedInfants  - $netDeletedInfants),
                ];
                if ($netAmount > 0) {
                    // Net payment: update both total and booking_total
                    $tourBookingUpdates['total']         = round($tourBooking->total + $netAmount, 2);
                    $tourBookingUpdates['booking_total'] = round($tourBooking->booking_total + $netAmount, 2);
                } elseif ($netAmount < 0) {
                    // Net refund (Cash path): only total decreases; booking_total unchanged for all refund cases
                    $tourBookingUpdates['total'] = round($tourBooking->total + $netAmount, 2);
                }
                $tourBooking->update($tourBookingUpdates);

                // ONE transaction for net amount (stored first so its ID can be linked to history)
                $transactionId = null;
                if ($netAmount > 0) {
                    $transactionId = BokunService::storePaymentInTransaction($tourBooking->id, $netAmount, 0, $paymentVia, 'Completed', $tourBooking->customer_id, $requestFrom);
                } elseif ($netAmount < 0) {
                    $transactionId = BokunService::storePaymentInTransaction($tourBooking->id, abs($netAmount), 0, $paymentVia, 'Refund', $tourBooking->customer_id, $requestFrom);
                }

                // Per-rate-group refund histories — delete-only path only.
                // Mirrors deletePeopleChangePeople: one entry per rate group.
                if (!empty($pendingRefundHistories) && empty($addedPax)) {
                    $tourBooking->refresh();
                    foreach ($pendingRefundHistories as $refundHistory) {
                        BokunService::storeRefundDataInHistory(
                            $tourBooking->id,
                            $tourBooking->tourists + $tourBooking->infants,
                            $createdByWithName,
                            $refundType,
                            $refundHistory['total'],
                            $paymentVia,
                            0,
                            $refundHistory['label'],
                            $refundHistory['total'],
                            0,
                            // $refundHistory['permitFees'],
                            $package->affiliate_id,
                            0,
                            $transactionId
                        );
                    }
                }

                // Mixed net refund: ONE combined refund history entry for the net refund amount.
                // refundType = 'Partial Refund' (matches changePeopleBooking Cash path).
                // ticket_cost = abs(netAmount) — the actual net being refunded, consistent with
                // amount, and avoids double-counting permit_fee that is stored separately.
                // permit_fees = net permit fee (deleted minus added) since it is a partial change.
                if (!empty($addedPax) && !empty($deletedPax) && $netAmount < 0) {
                    $tourBooking->refresh();
                    $netPermitFeeRefund = max(0, round($totalRefundPermitFees - $totalAddPermitFees, 2));
                    BokunService::storeRefundDataInHistory(
                        $tourBooking->id,
                        $tourBooking->tourists + $tourBooking->infants,
                        $createdByWithName,
                        'Partial Refund',
                        abs($netAmount),
                        $paymentVia,
                        0,
                        implode(', ', $deletedLabels),
                        abs($netAmount),
                        $netPermitFeeRefund,
                        $package->affiliate_id,
                        0,
                        $transactionId,
                        $totalAddAmount,
                        $totalRefundAmount
                    );
                }

                // Payment history — add-only OR mixed net payment.
                // paymenttotal/refundtotal carry actual per-direction amounts (not the net),
                // matching changePeopleBooking storeCashPaymentInHistory behaviour.
                if ($netAmount > 0) {
                    $tourBooking->refresh();
                    // Mixed net payment: ticket_cost = net amount charged (add minus delete).
                    // Add-only: ticket_cost = full add ticket cost (no delete to subtract).
                    $paymentTicketCost = !empty($deletedPax) ? $netAmount : $totalAddTicketCost;
                    BokunService::storePaymentInHistory(
                        $tourBooking->id,
                        $tourBooking->tourists + $tourBooking->infants,
                        [
                            'amount'         => $netAmount,
                            'payment_type'   => $paymentVia,
                            'payment_method' => 'Full Payment',
                            'paymenttotal'   => $totalAddAmount,
                            'refundtotal'    => $totalRefundAmount,
                            'swipeHistoryId' => 0,
                        ],
                        0,
                        $createdByWithName,
                        $paymentTicketCost,
                        // $totalAddPermitFees,
                        0,
                        $paymentVia,
                        'Full Payment',
                        $package->affiliate_id,
                        $transactionId
                    );
                }

                // changeTouristInHistory — only for add-only or mixed (add+delete); NOT for delete-only
                if (!empty($addedPax)) {
                    $tourBooking->refresh();
                    $changePeopleData = [
                        'tourists'            => $tourBooking->tourists + $tourBooking->infants,
                        'discount_percentage' => 0,
                        'discount2_value'     => 0,
                        'addedpeoplesetexts'  => $addedLabels,
                        'deletedpeoplestexts' => $deletedLabels,
                        'addedtourists'       => $netAddedTourists + $netAddedInfants,
                        'deletedtourits'      => $netDeletedTourists + $netDeletedInfants,
                        'addedinfants'        => $netAddedInfants,
                        'deletedinfants'      => $netDeletedInfants,
                        'paymenttotal'        => $totalAddAmount,
                        'refundtotal'         => $totalRefundAmount,
                    ];
                    HistoryService::changeTouristInHistory($tourBooking->id, $changePeopleData, $createdByWithName, $package->affiliate_id, 'Affiliate');
                }

                // 11c. Update slot seat summary — infants excluded, matching changePeopleBooking logic
                $seatsDiff = $netAddedTourists - $netDeletedTourists;
                if ($seatsDiff !== 0) {
                    TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $seatsDiff);
                }

                DB::commit();
            }

            return response()->json([
                'successfulAmendment' => [
                    'amendmentConfirmationCode' => $bookingConfirmationCode,
                    'bookingTicket' => [
                        'qrTicket' => [
                            'ticketBarcode' => $prefix . "" . $tourBooking->id
                        ]
                    ]
                ]
            ]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error("Booking Amendment API Error: " . $exception->getMessage());
            return response()->json([
                'failedAmendment' => ['amendmentError' => $exception->getMessage()]
            ]);
        }
    }

    /**
     * Determines whether a slot is Open or Closed relative to current time
     * in the operator's timezone. Both the current time and the slot datetime
     * are resolved in the same timezone to avoid cross-timezone comparison errors.
     *
     * @param object $availability
     * @return string 'Open'|'Closed'
     */
    private function resolveSlotOpenStatus(object $availability): string
    {
        // Respect the system-defined bookable_status first.
        // 'Only by Phone' means no online booking allowed; 'Closed' means operator closed the slot.
        if (in_array($availability->bookable_status, ['Closed', 'Only by Phone'])) {
            return 'Closed';
        }

        // Then check if the slot time has already passed in the operator's timezone.
        $timezone = $availability->timezone ?: 'UTC';
        $currentTime = Carbon::now($timezone);
        $slotTime = Carbon::createFromFormat('Y-m-d H:i:s', $availability->date . ' ' . $availability->time_from, $timezone);
        return $slotTime->gte($currentTime) ? 'Open' : 'Closed';
    }

    /**
     * JSON response for errors
     * @param string $errorCode
     * @param string $errorMessage
     * 
     * @return mixed|\Illuminate\Http\JsonResponse
     */

    public function errorResponse($error, $message, $api)
    {
        $response = [
            "error" => $error,
            "message" => $message
        ];

        Log::error("Bokun " . $api . " API Error Response ========== " . json_encode($response));

        return response()->json($response, 500);
    }
}