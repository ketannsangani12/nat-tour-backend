<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourPackage;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Constants\Constants;
use Illuminate\Support\Facades\Log;

class SoftBlockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return mixed
     */
    public function index()
    {
        $affiliates = Customer::isAffiliate()->get();
        $packages = TourPackage::active()->get();
        $admins = Admin::all();

        $vars = [
            'page_title' => 'Soft Block',
            'date' => date('m/d/Y'),
            'affiliates' => $affiliates,
            'packages' => $packages,
            'admins' => $admins
        ];

        return view('users.admin.soft-block.index', $vars);
    }

    /**
     * Fetch available time slots for a package, affiliate, and date range.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetchTimeSlots(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'packageId' => 'required|exists:tour_packages,id',
                'affiliateId' => 'required|exists:customers,id',
                'dateRange' => 'required|string',
            ]);

            $dateRange = explode(' / ', $validated['dateRange']);
            if (count($dateRange) !== 2) {
                return response()->json(['error' => 'Invalid date range format'], 400);
            }

            $startDate = Carbon::createFromFormat('F d, Y', $dateRange[0]);
            $endDate = Carbon::createFromFormat('F d, Y', $dateRange[1]);

            if (!$startDate || !$endDate || $startDate > $endDate) {
                return response()->json(['error' => 'Invalid date range'], 400);
            }

            $times = DB::table('tour_slot_times as tst')
                ->where('tst.tour_package_id', $validated['packageId'])
                ->where('tst.bookable_status', 'Open')
                ->whereBetween('tst.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->distinct()
                ->pluck('tst.time_from')
                ->map(fn($time) => Carbon::createFromFormat('H:i:s', $time)->format('H:i:s'))
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return response()->json(['times' => $times], 200);
        } catch (Exception $exception) {
            Log::error('Error in fetchTimeSlots: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while fetching time slots'], 500);
        }
    }

    /**
     * Preview slot availability for soft blocks.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function previewSoftBlocks(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'packageId' => 'required|exists:tour_packages,id',
                'affiliateId' => 'required|exists:customers,id',
                'isReservedForAffiliate' => 'required|in:0,1',
                'adminEmail' => 'nullable|email',
                'updateExistingBookings' => 'nullable|boolean',
                'timeSlots' => 'required|array',
                'timeSlots.*.date_range' => 'required|string',
                'timeSlots.*.times' => 'required|array',
                'timeSlots.*.times.*' => 'required|string|regex:/^\d{2}:\d{2}:\d{2}$/',
                'timeSlots.*.seats' => 'required|integer|min:1',
                'timeSlots.*.seat_preference' => 'required|in:0,1',
                'timeSlots.*.days' => 'nullable|array',
                'timeSlots.*.days.*' => 'nullable|integer|between:1,7',
            ]);

            $availability = [];
            $dayNames = [
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday',
            ];

            foreach ($validated['timeSlots'] as $slot) {
                foreach ($slot['times'] as $time) {
                    if (!isset($availability[$time])) {
                        $availability[$time] = [];
                    }

                    $dateRange = explode(' / ', $slot['date_range']);
                    if (count($dateRange) !== 2) {
                        return response()->json(['error' => 'Invalid date range format'], 400);
                    }

                    $startDate = Carbon::createFromFormat('F d, Y', $dateRange[0]);
                    $endDate = Carbon::createFromFormat('F d, Y', $dateRange[1]);

                    if (!$startDate || !$endDate || $startDate > $endDate) {
                        return response()->json(['error' => 'Invalid date range'], 400);
                    }

                    $selectedDays = !empty($slot['days']) ? array_map('intval', $slot['days']) : [];

                    $currentDate = $startDate->copy();
                    while ($currentDate <= $endDate) {
                        $dayOfWeek = $currentDate->dayOfWeekIso;
                        $dateString = $currentDate->format('Y-m-d');
                        $formattedDate = $currentDate->format('F d, Y');

                        if (empty($selectedDays) || in_array($dayOfWeek, $selectedDays)) {
                            $tourSlot = DB::table('tour_slot_times as tst')
                                ->where('tst.tour_package_id', $validated['packageId'])
                                ->where('tst.date', $dateString)
                                ->where('tst.time_from', $time)
                                ->where('tst.bookable_status', 'Open')
                                ->select('tst.id', 'tst.date')
                                ->first();

                            if ($tourSlot) {
                                $existingBooking = DB::table('tour_bookings_master')
                                    ->where('tour_package_id', $validated['packageId'])
                                    ->where('tour_slot_id', $tourSlot->id)
                                    ->where('customer_id', $validated['affiliateId'])
                                    ->where('is_reserved_for_affiliate', $validated['isReservedForAffiliate'])
                                    ->where('status', 'Booked')
                                    ->first();

                                $data = [
                                    'tour_slot_id' => $tourSlot->id,
                                    'package_id' => $validated['packageId'],
                                    'spots' => (int) $slot['seats'],
                                ];

                                $availableSeats = $this->availableSeats($data);

                                $availability[$time][] = [
                                    'bookingExists' => $existingBooking->id ?? null,
                                    'date' => $formattedDate,
                                    'day' => $dayNames[$dayOfWeek],
                                    'seatsRequested' => (int) $slot['seats'],
                                    'seatsAvailable' => $availableSeats,
                                    'seatsExist' => $existingBooking->tourists ?? 0,
                                    'seatPreference' => $slot['seat_preference'] === '1' ? 'Available' : 'Fixed',
                                    // Lets the preview table flag this row before submit -- the
                                    // mass tool skips (see skipIneligibleBookings()) any booking
                                    // that's ever had a split, a pax removed, or any payment,
                                    // rather than touching it or failing the whole batch over it.
                                    'hasSplitOrRemoved' => $existingBooking ? hasSplitOrRemovedPax($existingBooking->id) : false,
                                    'hasPayment' => $existingBooking ? hasAnyPayment($existingBooking->id) : false,
                                ];
                            }
                        }

                        $currentDate->addDay();
                    }
                }
            }

            return response()->json(['availability' => $availability], 200);
        } catch (Exception $exception) {
            Log::error('Error in previewSoftBlocks: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while fetching slot availability'], 500);
        }
    }

    /**
     * Create or update bookings for soft blocks.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSoftBlocks(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            $slotsToBook = $this->processTimeSlots($validated);

            $warnings = [];
            if ($validated['updateExistingBookings'] ?? false) {
                [$slotsToBook, $warnings] = $this->skipIneligibleBookings($slotsToBook);
            }

            DB::beginTransaction();
            try {
                $adminEmail = $validated['adminEmail'] ?? Auth::user()->email;
                $admin = Admin::where('email', $adminEmail)->first();
                if (!$admin) {
                    return response()->json(['error' => 'Admin not found'], 400);
                }

                $customer = Customer::find($validated['affiliateId']);
                if (!$customer) {
                    return response()->json(['error' => 'Affiliate not found'], 400);
                }
                $updateExistingBookings = $validated['updateExistingBookings'] ?? false;
                foreach ($slotsToBook as $slot) {
                    $isBlackoutDate = isBlackoutDateEnabled($slot['date']);
                    $packageRate = $this->getPackageRate($slot, $validated, $isBlackoutDate);
                    if (!$packageRate) {
                        return response()->json(['error' => "Sorry, we couldn't find a package rate for " . date('F d, Y', strtotime($slot['date'])) . ". Please ensure the order value is set to 1."], 400);
                    }

                    if (getNextYearCommissionPercentage($slot['date'])) {
                        if ($isBlackoutDate) {
                            $serviceCommission = getNormalCommissionPercentage($validated['packageId']);
                            $customer->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
                            $customer->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
                        } else {
                            $affiliateCommission = getAffiliateCommissionPercentage($customer->id);
                            $customer->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'] ?? 0;
                            $customer->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'] ?? 0;
                        }
                    } else {
                        if ($isBlackoutDate) {
                            $tourPackageInfo = TourPackage::find($validated['packageId']);
                            $customer->affiliate_processing_percentage = $tourPackageInfo->service_commission_percentage;
                            $customer->stripe_affiliate_percentage = $tourPackageInfo->stripe_commission_percentage;
                        }
                    }

                    if ($updateExistingBookings && isset($slot['booking_id'])) {
                        $tourBooking = TourBooking::find($slot['booking_id']);
                        $oldTourists = $tourBooking ? $tourBooking->tourists : 0;
                        $seatsDiff = $slot['seats'] - $oldTourists;

                        // Update existing booking
                        DB::table('tour_bookings_master')
                            ->where('id', $slot['booking_id'])
                            ->update([
                                'tourists' => $slot['seats'],
                                'updated_at' => now(),
                            ]);

                        $rate = $packageRate->rate + $packageRate->permit_fee + $packageRate->additional_charge;
                        $subtotal = number_format((float) $rate, 2, '.', '');
                        $totalCost = $slot['seats'] * $subtotal;

                        DB::table('tour_bookings_transact')
                            ->where('tour_booking_id', $slot['booking_id'])
                            ->update([
                                'tourists' => $slot['seats'],
                                'total' => number_format((float) $totalCost, 2, '.', ''),
                                'updated_at' => now(),
                            ]);

                        $this->updateBookingTotal($slot['booking_id']);
                        $this->insertBookingHistory($slot['booking_id'], $slot, $packageRate, $admin, 'Added', 'Tourists Added', true);
                        $seatsDiff = $slot['seats'] - $oldTourists;
                        TimeSlotAvailabilityService::updateSlotSummary($slot['tour_slot_id'], $seatsDiff);
                    } else {
                        if ($slot['seats'] > 0) {
                            // Create new booking
                            // Before the insertGetId call
                            $lastGroupId = TourBooking::select('group_id')
                                ->whereNotNull('group_id')
                                ->latest('group_id')
                                ->value('group_id');
                            
                            $groupId = $lastGroupId ? $lastGroupId + 1 : Constants::GROUP_ID;
    
                            // Create new booking
                            $bookingId = DB::table('tour_bookings_master')->insertGetId([
                                'tour_package_id' => $validated['packageId'],
                                'tour_slot_id' => $slot['tour_slot_id'],
                                'customer_id' => $validated['affiliateId'],
                                'is_reserved_for_affiliate' => $validated['isReservedForAffiliate'],
                                'tourists' => $slot['seats'],
                                'status' => 'Booked',
                                'group_id' => $groupId,
                                'name' => $customer->name,
                                'email' => $customer->email,
                                'phone_number' => $customer->phone_number,
                                'visitor' => $request->ip(),
                                'request_from' => 'BACKEND',
                                'created_by' => $admin->id,
                                'created_by_user_type' => userType(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $rate = $packageRate->rate + $packageRate->permit_fee + $packageRate->additional_charge;
                            $subtotal = number_format((float) $rate, 2, '.', '');
                            $totalCost = $slot['seats'] * $subtotal;

                            DB::table('tour_bookings_transact')->insert([
                                'tour_booking_id' => $bookingId,
                                'tour_package_rate_id' => $packageRate->id,
                                'tourists' => $slot['seats'],
                                'rate' => $packageRate->rate,
                                'permit_fee' => $packageRate->permit_fee,
                                'additional_charge' => $packageRate->additional_charge,
                                'total' => number_format((float) $totalCost, 2, '.', ''),
                                'nat_fees_percentage' => $customer->affiliate_processing_percentage,
                                'stripe_fees_percentage' => $customer->stripe_affiliate_percentage,
                                'created_by' => $admin->id,
                                'created_by_user_type' => userType(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $this->updateBookingTotal($bookingId);
                            $this->insertBookingHistory($bookingId, $slot, $packageRate, $admin, 'Added', 'Tourists Added', false);
                            TimeSlotAvailabilityService::updateSlotSummary($slot['tour_slot_id'], $slot['seats']);
                        }
                    }
                }

                DB::commit();
                return response()->json([
                    'message' => 'Bookings processed successfully',
                    'warnings' => $warnings,
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error processing bookings: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to process bookings'], 500);
            }
        } catch (Exception $exception) {
            Log::error('Error in createSoftBlocks: ' . $exception->getMessage(), ['exception' => $exception]);
            return response()->json(['error' => 'An error occurred while processing bookings'], 500);
        }
    }

    /**
     * Validate the request for creating soft blocks.
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'packageId' => 'required|exists:tour_packages,id',
            'affiliateId' => 'required|exists:customers,id',
            'isReservedForAffiliate' => 'required|in:0,1',
            'adminEmail' => 'nullable|email',
            'updateExistingBookings' => 'nullable|boolean',
            'timeSlots' => 'required|array',
            'timeSlots.*.date_range' => 'required|string',
            'timeSlots.*.times' => 'required|array',
            'timeSlots.*.times.*' => 'required|string|regex:/^\d{2}:\d{2}:\d{2}$/',
            'timeSlots.*.seats' => 'required|integer', //|min:1
            'timeSlots.*.seat_preference' => 'required|in:0,1',
            'timeSlots.*.days' => 'nullable|array',
            'timeSlots.*.days.*' => 'nullable|integer|between:1,7',
            'timeSlots.*.modified_times' => 'nullable|array',
            'timeSlots.*.modified_times.*' => 'integer',//|min:1
            'timeSlots.*.booking_ids' => 'nullable|array',
            'timeSlots.*.booking_ids.*' => 'integer|exists:tour_bookings_master,id',
        ]);
    }

    /**
     * Process time slots and prepare booking data.
     *
     * @param array $validated
     * @return array
     * @throws Exception
     */
    private function processTimeSlots(array $validated): array
    {
        $slotsToBook = [];

        foreach ($validated['timeSlots'] as $slot) {
            $modifiedTimes = $slot['modified_times'] ?? [];
            $bookingIds = $slot['booking_ids'] ?? [];

            foreach ($slot['times'] as $time) {
                $dateRange = explode(' / ', $slot['date_range']);
                if (count($dateRange) !== 2) {
                    throw new Exception('Invalid date range format');
                }

                $startDate = Carbon::createFromFormat('F d, Y', $dateRange[0]);
                $endDate = Carbon::createFromFormat('F d, Y', $dateRange[1]);

                if (!$startDate || !$endDate || $startDate > $endDate) {
                    throw new Exception('Invalid date range');
                }

                $selectedDays = !empty($slot['days']) ? array_map('intval', $slot['days']) : [];

                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    $dayOfWeek = $currentDate->dayOfWeekIso;
                    $dateString = $currentDate->format('Y-m-d');
                    $formattedDate = $currentDate->format('F d, Y');

                    if (empty($selectedDays) || in_array($dayOfWeek, $selectedDays)) {
                        $bookingId = $bookingIds["{$time}_{$formattedDate}"] ?? null;

                        $tourSlot = DB::table('tour_slot_times as tst')
                            ->where('tst.tour_package_id', $validated['packageId'])
                            ->where('tst.date', $dateString)
                            ->where('tst.time_from', $time)
                            ->where('tst.bookable_status', 'Open')
                            ->select('tst.id', 'tst.date')
                            ->first();

                        if ($tourSlot && (!$bookingId || $validated['updateExistingBookings'])) {
                            $seats = $modifiedTimes["{$time}_{$formattedDate}"] ?? $slot['seats'];

                            $data = [
                                'tour_slot_id' => $tourSlot->id,
                                'package_id' => $validated['packageId'],
                                'spots' => $seats,
                            ];

                            // if ($slot['seat_preference']) {
                            //     $availableSeats = $this->availableSeats($data);
                            //     $data['spots'] = min($availableSeats, $data['spots']);
                            //     // $data['spots'] = ($availableSeats > 0) ? $availableSeats : $data['spots'];
                            // }

                            if ($data['spots'] > 0) {
                                $slotsToBook[] = [
                                    'tour_slot_id' => $tourSlot->id,
                                    'date' => $dateString,
                                    'time' => $time,
                                    'seats' => $data['spots'],
                                    'seat_preference' => $slot['seat_preference'],
                                    'booking_id' => $bookingId,
                                ];
                            }
                        }
                    }

                    $currentDate->addDay();
                }
            }
        }

        return $slotsToBook;
    }

    /**
     * Get package rate for a booking slot.
     *
     * @param array $slot
     * @param array $validated
     * @return object|null
     */
    private function getPackageRate(array $slot, array $validated, $isBlackoutDate = false): ?object
    {
        $validated['affiliateId'] = $isBlackoutDate ? NULL : $validated['affiliateId'];
        $standardRateDate = DB::table('surcharge_dates')
            ->where('affiliate_id', $validated['affiliateId'])
            ->where('tour_package_id', $validated['packageId'])
            ->where('date_range_start', '<=', $slot['date'])
            ->where('date_range_end', '>=', $slot['date'])
            ->orderBy('priority', 'DESC')
            ->first();

        $customRateDate = null;
        if ($standardRateDate && $standardRateDate->apply_on_slots) {
            $customRateDate = isCustomDateRateEnabled($slot['date'], $validated['packageId'], $validated['affiliateId'], $slot['tour_slot_id']);
        } else {
            $customRateDate = isCustomDateRateEnabled($slot['date'], $validated['packageId'], $validated['affiliateId']);
        }

        return DB::table('tour_package_rates as tpr')
            ->where('tpr.tour_package_id', $validated['packageId'])
            ->where('tpr.affiliate_id', $validated['affiliateId'])
            ->when($customRateDate, fn($query) => $query->where('tpr.custom_date_id', $customRateDate), fn($query) => $query->whereNull('tpr.custom_date_id'))
            ->where('tpr.scheduling_year', Carbon::parse($slot['date'])->year)
            ->where('tpr.orderby', 1)
            ->first();
    }

    /**
     * Update booking total in tour_bookings_master.
     *
     * @param int $bookingId
     * @return void
     */
    private function updateBookingTotal(int $bookingId): void
    {
        $transactBookingTotal = DB::table('tour_bookings_transact')
            ->where('tour_booking_id', $bookingId)
            ->sum('total');

        $bookingTotal = number_format((float) $transactBookingTotal, 2, '.', '');

        DB::table('tour_bookings_master')
            ->where('id', $bookingId)
            ->update([
                'total' => $bookingTotal,
                'booking_total' => $bookingTotal,
                'updated_at' => now(),
            ]);
    }

    /**
     * Insert or update booking history record.
     *
     * @param int $bookingId
     * @param array $slot
     * @param object $packageRate
     * @param Admin $admin
     * @param string $operation
     * @param string $subOperation
     * @param bool $isUpdate
     * @return void
     */
    private function insertBookingHistory(int $bookingId, array $slot, object $packageRate, Admin $admin, string $operation, string $subOperation, bool $isUpdate): void
    {
        $tourBookingHistory = null;
        if ($bookingId) {
            $tourBookingHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation', 'Added')
                ->first();
        }

        if ($isUpdate && $tourBookingHistory) {
            $tourBookingHistory->tourists = $slot['seats'];
            $jsonData = json_decode($tourBookingHistory->details, true);
            $jsonData['newTourists'] = $slot['seats'];
            if (isset($jsonData['added'][0])) {
                $currentValue = $jsonData['added'][0];
                $newValue = preg_replace('/(\d+)$/', $slot['seats'], $currentValue);
                $jsonData['added'][0] = $newValue;
            }

            $tourBookingHistory->details = json_encode($jsonData);
            $tourBookingHistory->save();
        } else {
            $addedPeoplesText = [$packageRate->rate_for . " person " . $slot['seats']];
            $createdByWithName = $admin->name . userType();

            // Insert new history record
            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $bookingId,
                'tourists' => $slot['seats'],
                'operation' => $operation,
                'details' => json_encode([
                    'sub_operation' => $subOperation,
                    'discount_percentage' => 0,
                    'discount2_value' => 0,
                    'added' => $addedPeoplesText,
                    'newTourists' => $slot['seats'],
                    'created_by_with_name' => $createdByWithName
                ], JSON_NUMERIC_CHECK),
                'operation_at' => now()->format('Y-m-d H:i:s'),
                'operation_by' => $admin->id,
                'operation_by_user_type' => userType(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Drop any slot targeting a booking that isn't eligible for the mass
     * tool out of the batch, instead of rejecting the whole request over
     * it -- a single problem booking in a large date-range submission
     * shouldn't block every other, perfectly valid, seat change in the same
     * batch. Kept deliberately simple -- plain "has this ever happened"
     * checks, not seat-count/payment calculations. Skipped bookings need to
     * be edited individually instead.
     *
     * Two independent reasons can disqualify a booking, and both are
     * reported together when they both apply:
     *  - it's ever had a split or a pax removed (see hasSplitOrRemovedPax())
     *  - it's ever received any payment -- this tool is for unpaid
     *    ("Pay Later") bookings only (see hasAnyPayment())
     *
     * @param array $slotsToBook
     * @return array{0: array, 1: array} [the slots actually safe to process, human-readable warnings for the skipped ones]
     */
    private function skipIneligibleBookings(array $slotsToBook): array
    {
        $kept = [];
        $warnings = [];

        foreach ($slotsToBook as $slot) {
            if (empty($slot['booking_id'])) {
                $kept[] = $slot;
                continue;
            }

            $reasons = [];
            if (hasSplitOrRemovedPax($slot['booking_id'])) {
                $reasons[] = 'already has a split and/or removed pax';
            }
            if (hasAnyPayment($slot['booking_id'])) {
                $reasons[] = 'has already received a payment (this tool is only for unpaid bookings)';
            }

            if (empty($reasons)) {
                $kept[] = $slot;
                continue;
            }

            $warnings[] = "Booking #{$slot['booking_id']} on " . date('F d, Y', strtotime($slot['date'])) .
                " at {$slot['time']} was skipped — it " . implode(' and it ', $reasons) .
                '. Please edit that booking directly instead.';
        }

        return [$kept, $warnings];
    }


    /**
     * Fetch bookings for a specific month based on the provided criteria.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetchSoftBlocks(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'affiliateId' => 'array',
                'affiliateId.*' => 'integer|exists:customers,id',
                'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            ]);

            $timezone = tourOperator()->timezone;

            $month = trim($validated['month']);
            $affiliateIds = $validated['affiliateId'];

            // Parse the month to get start and end dates
            $startDate = Carbon::createFromFormat('Y-m-d H:i:s', "$month-01 00:00:00", $timezone)->startOfMonth();
            $endDate   = Carbon::createFromFormat('Y-m-d H:i:s', "$month-01 00:00:00", $timezone)->endOfMonth();

            // Query for bookings within the month for all specified affiliates
            $query = DB::table('tour_bookings_master as tbm')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('customers as cust', 'cust.id', '=', 'tbm.customer_id')
                ->when($affiliateIds, fn($query) => $query->whereIn('tbm.customer_id', $affiliateIds))
                ->whereBetween('tst.date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('cust.is_affiliate', 'Yes')
                ->whereNull('cust.deleted_at')
                ->where('tbm.status', '!=', 'Cancelled')
                ->select(
                    'tbm.id',
                    DB::raw("DATE_FORMAT(tst.date, '%M %d, %Y') as date"),
                    'tst.time_from as time',
                    'tbm.tourists as seats',
                    'tbm.total',
                    'tbm.status',
                    'tbm.customer_id as affiliate_id',
                    'cust.name as affiliate_name'
                );

            $bookings = $query->get()->map(fn($booking) => [
                'id' => $booking->id,
                'date' => $booking->date,
                'time' => $booking->time,
                'seats' => $booking->seats,
                'total' => $booking->total,
                'status' => $booking->status,
                'affiliate_id' => $booking->affiliate_id,
                'affiliate_name' => $booking->affiliate_name,
            ])->toArray();

            return response()->json(['bookings' => $bookings], 200);
        } catch (Exception $e) {
            Log::error('Error in fetchSoftBlocks: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An error occurred while fetching bookings'], 500);
        }
    }

    /**
     * Calculate the available seats for a given tour slot.
     *
     * @param array $data
     * @return int
     */
    protected function availableSeats(array $data): int
    {
        $slotTimes = DB::table('tour_slot_times as tst')
            ->where('tst.id', $data['tour_slot_id'])
            ->where('tst.tour_package_id', $data['package_id'])
            ->where('tst.bookable_status', 'Open')
            ->where('tst.deleted_by', '0')
            ->select([
                'tst.time_slot_seats as seats',
                DB::raw("IFNULL((
                    SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                        AND tbm.tour_package_id = {$data['package_id']}
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id
                ), 0) AS tourists")
            ])
            ->first();

        $availableSeats = 0;

        if ($slotTimes) {
            $availableSeats = max(0, $slotTimes->seats - $slotTimes->tourists);
        }

        return $availableSeats;
    }
}
