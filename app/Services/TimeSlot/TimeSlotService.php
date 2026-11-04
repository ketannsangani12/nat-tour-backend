<?php

namespace App\Services\TimeSlot;

use App\Constants\Constants;
use App\Models\RateSlotMapping;
use App\Models\TourSlotTime;
use App\Services\Booking\BookingService;
use App\Services\Calendar\CalendarService;
use App\Utils\BokunUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimeSlotService
{
    /**
     * Acquire a row lock on a tour_slot_times row so concurrent seat-availability
     * checks/writes against this slot (Bokun reservation, OCTO hold, or any other
     * writer) serialize on a common resource instead of racing on stale reads.
     * Must be called inside an open DB transaction, before reading seat availability.
     *
     * @param int $slotId
     *
     * @return void
     */
    public static function lockSlotForUpdate(int $slotId): void
    {
        DB::table('tour_slot_times')->where('id', $slotId)->lockForUpdate()->first();
    }

    /**
     * Update slot status
     *
     * @param string $date
     *
     * @return void
     */
    public static function updateSlotStatus($date): void
    {
        $updateTourStatus = DB::table('tour_slot_times AS tst')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->join('tour_operators AS t_op', 't_op.id', '=', 'tp.tour_operator_id')
            ->where('tst.deleted_by', '=', '0')
            ->where('tst.date', $date)
            ->where('tp.package_has_slots', Constants::STATUS_ONE)
            ->select(['tst.id', 'tp.duration', 'tp.day_hours', 'tst.date', 'tst.time_from', 'tst.bookable_status', 't_op.timezone'])
            ->get()
            ->toArray();

        for ($a = 0; $a < count($updateTourStatus); $a++) {
            if ($updateTourStatus[$a]->bookable_status != 'Only by Phone') {
                $currentTime = ($updateTourStatus[$a]->timezone != '') ? Carbon::now($updateTourStatus[$a]->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
                $currentTime = Carbon::parse($currentTime);
                $dataTime = Carbon::parse($updateTourStatus[$a]->date . ' ' . $updateTourStatus[$a]->time_from);
                if ($dataTime >= $currentTime) {
                    $status = "Open";
                    if ($updateTourStatus[$a]->day_hours !== null && $updateTourStatus[$a]->duration !== 0) {
                        if ($updateTourStatus[$a]->day_hours == 1 || $updateTourStatus[$a]->day_hours == 2) {
                            if ($updateTourStatus[$a]->duration !== 0) {
                                $generatedTime = ($updateTourStatus[$a]->timezone != '') ? Carbon::now($updateTourStatus[$a]->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
                                $now = Carbon::parse($updateTourStatus[$a]->date . ' ' . $updateTourStatus[$a]->time_from);
                                if ($updateTourStatus[$a]->day_hours == 1) {
                                    $hour = $updateTourStatus[$a]->duration * 24;
                                    $v = -1;
                                } else {
                                    $hour = $updateTourStatus[$a]->duration - 1;
                                    $v = -1;
                                }
                                $generatedTime = Carbon::parse($generatedTime);
                                $generatedTime = $generatedTime->modify('+' . $hour . 'hours');
                                $difference = $now->diffInHours($generatedTime, false);
                                if ($difference > $v) {
                                    $status = "Closed";
                                } else {
                                    $status = "Open";
                                }
                            } else {
                                $generatedTime = ($updateTourStatus[$a]->timezone != '') ? Carbon::now($updateTourStatus[$a]->timezone) : Carbon::now();
                                $now = Carbon::parse($updateTourStatus[$a]->date . ' ' . $updateTourStatus[$a]->time_from);
                                $generatedTime = Carbon::parse($generatedTime);
                                $difference = $now->diffInHours($generatedTime, false);
                                if ($difference > 0) {
                                    $status = "Closed";
                                } else {
                                    $status = "Open";
                                }
                            }
                        }
                    } else {
                        $generatedTime = ($updateTourStatus[$a]->timezone != '') ? Carbon::now($updateTourStatus[$a]->timezone) : Carbon::now();
                        $now = Carbon::parse($updateTourStatus[$a]->date . ' ' . $updateTourStatus[$a]->time_from);
                        $difference = $generatedTime->diffInMinutes($now);

                        $convertToDecimal = $difference / 60;
                        if ($convertToDecimal <= 0) {
                            $status = "Closed";
                        } else {
                            $status = "Open";
                        }
                    }
                    // Update status in database
                    if ($status == "Closed") {
                        DB::table('tour_slot_times')
                            ->where('id', '=', $updateTourStatus[$a]->id)
                            ->update([
                                'bookable_status' => $status
                            ]);
                    }
                } else {
                    $generatedTime = ($updateTourStatus[$a]->timezone != '') ? Carbon::now($updateTourStatus[$a]->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
                    $now = Carbon::parse($updateTourStatus[$a]->date . ' ' . $updateTourStatus[$a]->time_from);

                    $generatedTime = Carbon::parse($generatedTime);
                    $difference = $now->diffInHours($generatedTime, false);
                    if ($difference > 0) {
                        DB::table('tour_slot_times')
                            ->where('id', '=', $updateTourStatus[$a]->id)
                            ->update([
                                'bookable_status' => 'Closed'
                            ]);
                    }
                }
            }
        }
    }


    /**
     * Get time slot data
     * 
     * @param string $slotId
     * 
     * @return mixed
     */
    public static function getTimeSlot($timeSlotId)
    {
        $timeSlot = DB::table('tour_slot_times AS tst')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->where('tst.id', $timeSlotId)
            ->first(['tst.id', 'tp.tour_operator_id', 'tst.tour_package_id', 'tst.date']);

        return $timeSlot;
    }

    /**
     * Summary of closeTheTimeSlot
     *
     * @param int $slotId
     * @return void
     */
    public static function closeTheTimeSlot($slotId): void
    {
        $tourSlot = TourSlotTime::find($slotId);
        $tourSlot->bookable_status = 'Closed';
        $tourSlot->save();
    }

    /**
     * Close a time slot when a booking is created.
     * Only acts when the package has slot_close_on_booking = 1.
     * Closes the slot regardless of remaining seat count (private/single-guide tours).
     * Also closes all related family package slots for the same date and time.
     *
     * @param int $tourPackageId
     * @param int $tourSlotId
     * @return void
     */
    public static function closeSlotOnBooking(int $tourPackageId, int $tourSlotId): void
    {
        // Check if the package has the slot_close_on_booking setting enabled
        $slotCloseOnBooking = DB::table('tour_packages')
            ->where('id', $tourPackageId)
            ->value('slot_close_on_booking');

        // Skip if the setting is disabled for this package
        if (!$slotCloseOnBooking) {
            return;
        }

        // Close the slot — only if it is currently Open or Only by Phone
        // (avoids overwriting a slot that was already manually Closed)
        DB::table('tour_slot_times')
            ->where('id', $tourSlotId)
            ->whereIn('bookable_status', ['Open', 'Only by Phone'])
            ->update([
                'bookable_status'      => 'Closed',
                'updated_at'           => Carbon::now(),
                'updated_by'           => Auth::id() ?? 1,
                'updated_by_user_type' => userType() ?? 'Admin',
            ]);

        // Also close all related family package slots for the same date and time_from
        $slot = DB::table('tour_slot_times')
            ->where('id', $tourSlotId)
            ->first(['date', 'time_from']);

        if ($slot) {
            self::closeRelatedFamilySlots($tourPackageId, $slot->date, $slot->time_from);
        }
    }

    /**
     * Reopen a time slot when a booking is cancelled.
     * Only acts when:
     *   - The package has slot_close_on_booking = 1
     *   - The slot date+time has not yet passed (future slot only)
     *   - No other active (non-cancelled) bookings remain on the slot
     *
     * @param int $tourPackageId
     * @param int $tourSlotId
     * @param int $cancelledBookingId  The booking that was just cancelled (excluded from active count)
     * @return void
     */
    public static function reopenSlotOnCancellation(int $tourPackageId, int $tourSlotId, int $cancelledBookingId): void
    {
        // Check if the package has the slot_close_on_booking setting enabled
        $slotCloseOnBooking = DB::table('tour_packages')
            ->where('id', $tourPackageId)
            ->value('slot_close_on_booking');

        // Skip if the setting is disabled for this package
        if (!$slotCloseOnBooking) {
            return;
        }

        // Fetch both date and time_from to do a full datetime comparison
        $slot = DB::table('tour_slot_times')
            ->where('id', $tourSlotId)
            ->first(['date', 'time_from']);

        // Only reopen if the slot's date+time is still in the future
        // Using time_from ensures same-day slots that have already started are not reopened
        if (!$slot || Carbon::parse($slot->date . ' ' . $slot->time_from)->lte(Carbon::now())) {
            return;
        }

        // Count active bookings on this slot, excluding the one just cancelled
        // If another booking still holds the slot, it must remain Closed
        $activeBookingsCount = DB::table('tour_bookings_master')
            ->where('tour_slot_id', $tourSlotId)
            ->where('status', '!=', 'Cancelled')
            ->where('id', '!=', $cancelledBookingId)
            ->whereNull('deleted_at')
            ->count();

        // Reopen only when no other active booking exists for this slot
        if ($activeBookingsCount === 0) {
            DB::table('tour_slot_times')
                ->where('id', $tourSlotId)
                ->where('bookable_status', 'Closed')
                ->update([
                    'bookable_status'      => 'Open',
                    'updated_at'           => Carbon::now(),
                    'updated_by'           => Auth::id() ?? 1,
                    'updated_by_user_type' => userType() ?? 'Admin',
                ]);

            // Also reopen related family package slots that have no active bookings of their own
            self::reopenRelatedFamilySlots($tourPackageId, $slot->date, $slot->time_from);
        }
    }

    /**
     * Return all sibling package IDs in the same tour_package_families group
     * as the given package for a specific date and time_from.
     *
     * Siblings share the same parent_package_id, group_index, and relation_year
     * but have a different tour_package_id.
     *
     * @param int    $tourPackageId
     * @param string $date      e.g. '2025-07-15'
     * @param string $timeFrom  e.g. '09:00:00'
     * @return \Illuminate\Support\Collection  Collection of int package IDs (empty when not in a family)
     */
    private static function getFamilySiblingPackageIds(int $tourPackageId, string $date, string $timeFrom): \Illuminate\Support\Collection
    {
        $relationYear = (int) Carbon::parse($date)->format('Y');

        $familyEntry = DB::table('tour_package_families')
            ->where('tour_package_id', $tourPackageId)
            ->where('time_from', $timeFrom)
            ->where('relation_year', $relationYear)
            ->first(['parent_package_id', 'group_index']);

        if (!$familyEntry) {
            return collect();
        }

        return DB::table('tour_package_families')
            ->where('parent_package_id', $familyEntry->parent_package_id)
            ->where('group_index', $familyEntry->group_index)
            ->where('relation_year', $relationYear)
            ->where('tour_package_id', '!=', $tourPackageId)
            ->pluck('tour_package_id');
    }

    /**
     * Close all family-sibling slots for the given package/date/time_from.
     * Only slots currently Open or Only by Phone are updated (same guard as the primary close).
     *
     * @param int    $tourPackageId
     * @param string $date
     * @param string $timeFrom
     * @return void
     */
    private static function closeRelatedFamilySlots(int $tourPackageId, string $date, string $timeFrom): void
    {
        $siblingPackageIds = self::getFamilySiblingPackageIds($tourPackageId, $date, $timeFrom);

        if ($siblingPackageIds->isEmpty()) {
            return;
        }

        DB::table('tour_slot_times')
            ->whereIn('tour_package_id', $siblingPackageIds)
            ->where('date', $date)
            ->where('time_from', $timeFrom)
            ->whereIn('bookable_status', ['Open', 'Only by Phone'])
            ->update([
                'bookable_status'      => 'Closed',
                'updated_at'           => Carbon::now(),
                'updated_by'           => Auth::id() ?? 1,
                'updated_by_user_type' => userType() ?? 'Admin',
            ]);
    }

    /**
     * Reopen family-sibling slots that have no active bookings of their own.
     * Called only after the primary slot has already been confirmed for reopening,
     * so siblings are only reopened when the whole family's date/time is clear.
     *
     * @param int    $tourPackageId
     * @param string $date
     * @param string $timeFrom
     * @return void
     */
    private static function reopenRelatedFamilySlots(int $tourPackageId, string $date, string $timeFrom): void
    {
        $siblingPackageIds = self::getFamilySiblingPackageIds($tourPackageId, $date, $timeFrom);

        if ($siblingPackageIds->isEmpty()) {
            return;
        }

        foreach ($siblingPackageIds as $siblingPackageId) {
            $siblingSlotId = DB::table('tour_slot_times')
                ->where('tour_package_id', $siblingPackageId)
                ->where('date', $date)
                ->where('time_from', $timeFrom)
                ->value('id');

            if (!$siblingSlotId) {
                continue;
            }

            // Only reopen if the sibling slot itself has no active bookings
            $activeBookingsCount = DB::table('tour_bookings_master')
                ->where('tour_slot_id', $siblingSlotId)
                ->where('status', '!=', 'Cancelled')
                ->whereNull('deleted_at')
                ->count();

            if ($activeBookingsCount === 0) {
                DB::table('tour_slot_times')
                    ->where('id', $siblingSlotId)
                    ->where('bookable_status', 'Closed')
                    ->update([
                        'bookable_status'      => 'Open',
                        'updated_at'           => Carbon::now(),
                        'updated_by'           => Auth::id() ?? 1,
                        'updated_by_user_type' => userType() ?? 'Admin',
                    ]);
            }
        }
    }

    /**
     * Main orchestrator function: Fetch slot times with fields and conditions tailored to user type and affiliate visibility.
     * Combines helper functions for fields, query, and affiliate-specific adjustments.
     */
    public static function getTourSlotTimes($request, $tourOperatorId, $userType, $showAllPackageBookings, $affiliateSql = '')
    {
        // Get the base fields for the query
        $fields = self::getSlotFields($request, $affiliateSql);

        // Add affiliate-specific booked seats if applicable
        if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
            $fields[] = self::getAffiliateBookedSeats();
        }

        // Include bookable status field if requested
        if ($request->has('allBookableStatus')) {
            $fields[] = 'tst.bookable_status';
        }

        // Build the slot times query
        $slotTimesQuery = self::getSlotTimesQuery($request, $tourOperatorId);

        // Execute and return the query results with the defined fields
        return $slotTimesQuery->get($fields);
    }

    /**
     * Main orchestrator function: Fetch slot times with fields and conditions tailored to user type and affiliate visibility.
     * Combines helper functions for fields, query, and affiliate-specific adjustments.
     */
    public static function getTourSlotTimesV2($request, $tourOperatorId, $userType, $showAllPackageBookings, $affiliateSql = '')
    {
        // Get the base fields for the query
        $year = Carbon::parse($request->date)->format('Y');
        $fields = [
            'tst.id',
            'tsd.tour_package_id',
            'tsd.date',
            'tst.time_from',
            'tst.time_to',
            'tst.bookable_status',
            'tst.hide_slot',
            DB::raw('COALESCE(tst.time_slot_seats, 0) AS seats'),
            DB::raw("IFNULL((SELECT SUM(tbm.tourists) FROM tour_bookings_master AS tbm WHERE tbm.tour_slot_id = tst.id AND tbm.tour_package_id = " . $request->tourPackageId . " AND tbm.status != 'Cancelled' GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
            DB::raw('COALESCE(tss.available_seats, 0) AS available_seats'),
            DB::raw('COALESCE(tss.overbooked_count, 0) AS overbooked_count'),
            DB::raw("IFNULL((SELECT SUM(tbm.tourists) FROM tour_bookings_master AS tbm JOIN customers AS c ON c.id = tbm.customer_id WHERE tbm.tour_slot_id = tst.id AND c.is_affiliate = 'Yes' AND tbm.status != 'Cancelled' AND tbm.tour_package_id = " . $request->tourPackageId . " $affiliateSql GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS afl_tourists"),
            DB::raw("IFNULL((SELECT tpf.is_parent FROM tour_package_families AS tpf WHERE tpf.tour_package_id = tsd.tour_package_id AND tst.time_from = tpf.time_from AND tpf.relation_year = $year AND tpf.tour_package_id = " . $request->tourPackageId . " GROUP BY tpf.tour_package_id), 0) AS is_parent"),
            DB::raw("IFNULL((SELECT tpf.group_index FROM tour_package_families AS tpf WHERE tpf.tour_package_id = tsd.tour_package_id AND tst.time_from = tpf.time_from AND tpf.relation_year = $year AND tpf.tour_package_id = " . $request->tourPackageId . " GROUP BY tpf.tour_package_id), 0) AS group_index"),
        ];
        self::getSlotFields($request, $affiliateSql);

        // Add affiliate-specific booked seats if applicable
        if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
            $fields[] = self::getAffiliateBookedSeats();
        }

        // Include bookable status field if requested
        if ($request->has('allBookableStatus')) {
            $fields[] = 'tst.bookable_status';
        }

        // Build the slot times query
        $slotTimesQuery = DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->leftJoin('tour_slot_summaries AS tss', function ($join) use ($request) {
                $join->on('tss.tour_slot_id', '=', 'tst.id')
                    ->where('tss.tour_package_id', '=', $request->tourPackageId);
            })
            ->where('tsd.date', $request->date)
            ->where('tsd.tour_package_id', $request->tourPackageId)
            ->where('tsd.status', 'Active')
            ->where('tst.deleted_by', '=', '0')
            ->where('tsd.deleted_by', '=', '0')
            ->orderBy('tst.time_from');

        // Execute and return the query results with the defined fields
        return $slotTimesQuery->get($fields);
    }
    /**
     * Define the fields required for the slot times query.
     */
    public static function getSlotFields($request, $affiliateSql)
    {
        $year = Carbon::parse($request->date)->format('Y');
        return [
            'tst.id',
            'tsd.tour_package_id',
            'tsd.date',
            'tst.time_from',
            'tst.time_to',
            'tst.bookable_status',
            DB::raw("IFNULL(tst.time_slot_seats, 0) AS seats"),
            DB::raw("IFNULL((SELECT SUM(tbm.tourists) FROM tour_bookings_master AS tbm WHERE tbm.tour_slot_id = tst.id AND tbm.status != 'Cancelled' AND tbm.tour_package_id = " . $request->tourPackageId . " $affiliateSql GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
            DB::raw("IFNULL((SELECT SUM(tbm.tourists) FROM tour_bookings_master AS tbm JOIN customers AS c ON c.id = tbm.customer_id WHERE tbm.tour_slot_id = tst.id AND c.is_affiliate = 'Yes' AND tbm.status != 'Cancelled' AND tbm.tour_package_id = " . $request->tourPackageId . " $affiliateSql GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS afl_tourists"),
            DB::raw("IFNULL((SELECT tpf.is_parent FROM tour_package_families AS tpf WHERE tpf.tour_package_id = tsd.tour_package_id AND tst.time_from = tpf.time_from AND tpf.relation_year = $year AND tpf.tour_package_id = " . $request->tourPackageId . " GROUP BY tpf.tour_package_id), 0) AS is_parent"),
            DB::raw("IFNULL((SELECT tpf.group_index FROM tour_package_families AS tpf WHERE tpf.tour_package_id = tsd.tour_package_id AND tst.time_from = tpf.time_from AND tpf.relation_year = $year AND tpf.tour_package_id = " . $request->tourPackageId . " GROUP BY tpf.tour_package_id), 0) AS group_index"),
        ];
    }

    /**
     * Build the base query for fetching slot times.
     */
    public static function getSlotTimesQuery($request, $tourOperatorId)
    {
        return DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
            ->where('tsd.date', $request->date)
            ->where('tsd.tour_package_id', $request->tourPackageId)
            ->where('tsd.status', 'Active')
            ->where('tst.deleted_by', '=', '0')
            ->where('tsd.deleted_by', '=', '0')
            ->where('tp.tour_operator_id', $tourOperatorId)
            ->orderBy('tst.time_from');
    }

    /**
     * Define the affiliate-specific booked seats field.
     */
    public static function getAffiliateBookedSeats()
    {
        return DB::raw('(SELECT SUM(tbm2.tourists) FROM tour_bookings_master AS tbm2 WHERE tbm2.tour_slot_id = tst.id AND tbm2.status != "Cancelled" GROUP BY tbm2.tour_slot_id) AS booked_seats');
    }

    /**
     * Fetch affiliate-specific time slots with seat limits.
     */
    public static function getAffiliateTimeSlots($affiliate, $request)
    {
        // Use the same query structure as the working calendar logic
        return DB::select("
            SELECT tsta.id as time_slot_id, tsta.time_from, tsta.time_to, IFNULL(ast.seat_limit, 0) as seat_limit
            FROM tour_slot_times_affiliate tsta
            JOIN affiliate_slot_times ast ON ast.tour_slot_time_id = tsta.id
            WHERE ast.affiliate_id = ?
                AND tsta.tour_package_id = ?
                AND tsta.status = 'Active'
                AND tsta.deleted_by = 0
                AND ast.deleted_by = 0
        ", [$affiliate->id, $request->tourPackageId]);
    }

    /**
     * Retrieve the rate slot mapping for a given tour package, custom date, and time slot.
     *
     * @param object $request The request object containing affiliate and tour package details.
     * @param $customDateId The ID of the applicable custom date.
     * @param $timeFrom The starting time of the slot.
     * @return mixed The rate slot mapping object if found, otherwise null.
     */
    public static function getRateSlot(object $request, $customDateId, $timeFrom)
    {
        return RateSlotMapping::where('tour_package_id', $request->tourPackageId)
            ->where('custom_date_id', $customDateId)
            ->where('time_from', $timeFrom)
            ->when($request->affiliateId, function ($query) use ($request) {
                return $query->where('affiliate_id', $request->affiliateId);
            }, function ($query) {
                return $query->whereNull('affiliate_id');
            })
            ->first();
    }
    /**
     * Get bookings for a given time slot.
     *
     * @param int $timeSlotId
     * @param object $tourSlot
     * @param string $userType
     * @return \Illuminate\Support\Collection
     */
    public static function getBookings(int $timeSlotId, object $tourSlot, string $userType, string $showAllPackageBookings)
    {
        // Base query for retrieving tour bookings
        $bookings = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->leftJoin('transactions AS tr', 'tr.tour_booking_id', '=', 'tbm.id')
            ->leftJoin(DB::raw('
			(SELECT cart_id, COUNT(*) as cart_booking_count
			 FROM tour_bookings_master
			 GROUP BY cart_id) as grouped
		'), 'tbm.cart_id', '=', 'grouped.cart_id')
            ->where('tbm.tour_slot_id', $timeSlotId)
            ->groupBy('tbt.tour_booking_id')
            ->orderBy('c.name', 'asc');

        // Check if user type is 'Affiliate' and apply additional filters
        if ($userType === 'Affiliate') {
            if ($showAllPackageBookings) {
                $bookings->where('tbm.customer_id', Auth::id());
            }
        }

        // Retrieve and return bookings with selected fields
        return $bookings->get([
            'tbm.id',
            'tbm.cart_id',
            'tbm.viator_bookingRef AS tripadvisor_id',
            'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
            'tbm.ota_reference_booking AS ota_booking_id',
            'tbm.name AS cust_name',
            'c.is_affiliate',
            'tbm.is_reserved_for_affiliate',
            DB::raw('(tbm.tourists + tbm.infants) as tourists'),
            'tbm.total',
            'tbm.created_at AS booking_d_t',
            'tbm.status',
            'tbm.reference_booking',
            'grouped.cart_booking_count as cartBookingsCount',
            'tr.via',
            'tp.package_has_slots',
            'tp.package_has_waiver',
            'tp.package_has_permit',
            DB::raw("(SELECT tst.date FROM tour_slot_times AS tst WHERE tst.id = tbm.tour_slot_id) AS date"),
            DB::raw("(SELECT SUM(t.amount) FROM transactions AS t WHERE tour_booking_id = tbm.id AND t.status = 'Completed') AS paid"),
            DB::raw("(SELECT SUM(t.amount) FROM transactions AS t WHERE tour_booking_id = tbm.id AND t.status = 'Completed' AND payment_type='1') AS paid_split"),
            DB::raw("(SELECT GROUP_CONCAT(
                        CASE 
                        WHEN tbt.is_infant_rate = 1 
                        THEN 
                        CONCAT(LEFT(tpr.rate_for, 1), ':', tbt.infants,'^',tpr.rate_for) 
                        ELSE
                        CONCAT(LEFT(tpr.rate_for, 1), ':', tbt.tourists,'^',tpr.rate_for) 
                        END
                        ORDER BY age_from DESC, age_to DESC SEPARATOR ' | ' 
                  ) AS t_groups 
                  FROM tour_bookings_transact AS tbt 
                  INNER JOIN tour_package_rates AS tpr 
                  ON tpr.id = tbt.tour_package_rate_id 
                  WHERE tbt.tour_booking_id = tbm.id 
                  AND tbt.deleted_by = 0) AS t_groups"),
            DB::raw('0 AS relational_booking'),
        ]);
    }

    /**
     * Process bookings to add additional details.
     *
     * @param \Illuminate\Support\Collection $bookings
     * @param string $timezone
     * @return mixed
     */
    public static function processBookings($bookings, string $timezone, string $status = '')
    {
        if ($bookings->isEmpty()) {
            return $bookings;
        }
        $bookingPrefix = bookingPrefixSetting();
        foreach ($bookings as $key => $booking) {
            // Process tourist groups
            $tGroupsChanged = CalendarService::touristGroups($booking->t_groups);
            $booking->t_groups = $tGroupsChanged ? implode(" | ", $tGroupsChanged) : '';

            if ($status != 'Cancelled') {

                // Fetch invoice status
                $invoiced = DB::table('invoices AS i')
                    ->join('invoice_bookings AS ib', 'ib.invoice_id', '=', 'i.id')
                    ->where('ib.tour_booking_id', $booking->id)
                    ->first(['status']);

                $booking->invoiceStatus = !empty($invoiced) ? $invoiced->status : '0';
            }

            // Determine if it's a Tripadvisor or GetYourGuide booking
            $booking->is_tripadvisor_booking = !empty($booking->tripadvisor_id) ? 1 : 0;
            $booking->is_get_your_guide_booking = !empty($booking->get_your_guide_booking_id) ? 1 : 0;
            $booking->is_ota_booking = !empty($booking->ota_booking_id) ? 1 : 0;

            // Apply background color class
            $booking->background_colour_class = BookingService::backgroundColourClass($booking);

            // Set booking prefix ID
            $booking->prefixedId = ($bookingPrefix != '') ? $bookingPrefix . $booking->id : $booking->id;
            $otaTitle = BokunUtils::getOTAChannelNameByBookingId($booking->id);
            $booking->ota_title = strtolower($otaTitle);
            $booking->ota_logo  = BokunUtils::getOTALogoByTitle($otaTitle);

            // Convert booking date-time to the required timezone
            $booking->booking_d_t = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $booking->booking_d_t,
                config('app.timezone')
            )->setTimezone($timezone)->format('Y-m-d H:i:s');
        }

        return $bookings;
    }
    /**
     * Retrieve cancelled bookings for a specific time slot.
     *
     * @param int $timeSlotId
     * @param string $userType
     * @param mixed $showAllPackageBookings
     * @return mixed
     */
    public static function getCancelledBookings(int $timeSlotId, string $userType, mixed $showAllPackageBookings)
    {
        $query = DB::table('tour_bookings_master AS tbm')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->leftJoin(DB::raw('
			(SELECT cart_id, COUNT(*) as cart_booking_count
			 FROM tour_bookings_master
			 GROUP BY cart_id) as grouped
		'), 'tbm.cart_id', '=', 'grouped.cart_id')
            ->where('tbm.status', 'Cancelled')
            ->where('tbm.tour_slot_id', $timeSlotId)
            ->where('tbt.deleted_by', 0)
            ->groupBy('tbt.tour_booking_id')
            ->orderBy('c.name', 'asc');

        // Restrict results for Affiliates if necessary
        if ($userType === 'Affiliate' && $showAllPackageBookings) {
            $query->where('tbm.customer_id', Auth::id());
        }

        return $query->get([
            'tbm.id',
            'tbm.cart_id',
            'tbm.viator_bookingRef AS tripadvisor_id',
            'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
            'tbm.ota_reference_booking AS ota_booking_id',
            'tbm.name AS cust_name',
            'grouped.cart_booking_count as cartBookingsCount',
            'c.is_affiliate',
            DB::raw('(tbm.tourists + tbm.infants) as tourists'),
            'tbm.total',
            'tbm.created_at AS booking_d_t',
            'tbm.status',
            'tbm.is_reserved_for_affiliate',
            'tbm.reference_booking',
            'tp.package_has_slots',
            'tp.package_has_waiver',
            'tp.package_has_permit',
            DB::raw("(SELECT tst.date FROM tour_slot_times AS tst WHERE tst.id = tbm.tour_slot_id) AS date"),
            DB::raw("(SELECT SUM(t.amount) FROM transactions AS t WHERE tour_booking_id = tbm.id AND t.status = 'Completed') AS paid"),
            DB::raw("(SELECT GROUP_CONCAT(
                CONCAT(
                    LEFT(tpr.rate_for, 1), ':',
                    CASE 
                        WHEN tbt.infants > 0 THEN tbt.infants 
                        ELSE tbt.tourists 
                    END,
                    '^',
                    tpr.rate_for
                )
                ORDER BY tpr.age_from DESC, tpr.age_to DESC
                SEPARATOR ' | '
            )
            FROM tour_bookings_transact AS tbt
            INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id
            WHERE tbt.tour_booking_id = tbm.id 
            AND tbt.deleted_by = 0
          ) AS t_groups"),
            DB::raw('0 AS relational_booking')
        ]);
    }
}
