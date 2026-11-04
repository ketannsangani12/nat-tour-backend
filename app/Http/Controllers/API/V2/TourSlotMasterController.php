<?php

namespace App\Http\Controllers\API\V2;

use App\Constants\Constants;
use App\Http\Controllers\API\V2\BaseController;
use App\Models\Customer;
use App\Models\RateSlotMapping;
use App\Models\SurchargeDate;
use App\Models\TourPackage;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Services\TimeSlot\TouristService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TourSlotMasterController extends BaseController
{
    protected $tourSlotRepository;

    public function __construct(TourSlotRepository $tourSlotRepository)
    {
        $this->tourSlotRepository = $tourSlotRepository;
    }

    /**
     * Get Color Date for booking calendar
     * 
     * @param int $tourPackageId
     * @param int $month
     */
    public function getColorDate($month, $year, $tourPackageId, $affiliateId = 0)
    {
        // Always use the first day of the requested month/year for calendar data
        // The repository method will handle filtering from current date if needed
        $date = Carbon::createFromDate($year, $month, 1)->toDateString();

        if ($affiliateId) {
            $tourPackage = TourPackage::find($tourPackageId);
            //$data = $this->tourSlotRepository->getCalendarSeatsForAffiliate($date, $tourPackage, $tourPackageId, $affiliateId);
            $data = $this->tourSlotRepository->getSlotsWithRelationsForAffiliate($date, $tourPackage, $tourPackageId, $affiliateId);
        } else {
            //$data = $this->tourSlotRepository->getCalendarSeats($date, $tourPackageId);
            $data = $this->tourSlotRepository->getSlotsWithRelations($date, $tourPackageId);
        }

        return $data;
    }
/**
     * Get Color Date for booking calendar
     * 
     * @param int $tourPackageId
     * @param int $month
     */
    public function getColorDateOld($month, $year, $tourPackageId, $affiliateId = 0)
    {
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        $date = ($month == $currentMonth && $year == $currentYear) ? Carbon::now()->toDateString() : Carbon::createFromDate($year, $month, 1)->toDateString();

        if ($affiliateId) {
            $tourPackage = TourPackage::find($tourPackageId);
            $data = $this->tourSlotRepository->getCalendarSeatsForAffiliate($date, $tourPackage, $tourPackageId, $affiliateId);
        } else {
            $data = $this->tourSlotRepository->getCalendarSeats($date, $tourPackageId);
        }

        return $data;
    }
    /**
     * Get the slots
     * 
     * @param string $requestDate
     * @param int $packageId
     * @param int $affiliateId
     */
    public function getSlotTimes($requestDate, $packageId, $affiliateId = Constants::STATUS_ZERO)
    {
        $tourPackage = TourPackage::find($packageId);

        if ($tourPackage == null) {
            return $this->sendError('Slot Not Found.');
        }

        $today = Carbon::now();
        $todayDate = $today->format('Y-m-d');

        $duration = Constants::STATUS_ZERO;
        if (isset($tourPackage->duration) && $tourPackage->duration !== null) {
            $duration = $tourPackage->duration;
            if ($tourPackage->day_hours == Constants::STATUS_ONE) {
                $duration *= 24;
            }
        }

        $duration = (int) $duration;
        $currentTime = $today->format('H:i:s');
        $carbonDate = Carbon::parse($currentTime);
        $setDate = $carbonDate->addHours($duration);
        $blockDate = $setDate->format('Y-m-d');
        $blockTime = $setDate->format('H:i:s');

        // Calculate Call To Book block time when CALL_TO_BOOK setting is enabled
        $ctbBlockDate = null;
        $ctbBlockTime = null;
        if (hasSetting('CALL_TO_BOOK') && isset($tourPackage->block_ctb_duration) && $tourPackage->block_ctb_duration > 0) {
            $ctbDuration = (int) $tourPackage->block_ctb_duration;
            $ctbSetDate = Carbon::parse($today->format('H:i:s'))->addHours($ctbDuration);
            $ctbBlockDate = $ctbSetDate->format('Y-m-d');
            $ctbBlockTime = $ctbSetDate->format('H:i:s');
        }

        stopNextYearBookings($requestDate);

        if ($affiliateId > Constants::STATUS_ZERO) {
            return $this->getAffiliateSlots($requestDate, $todayDate, $tourPackage, $packageId, $affiliateId, $ctbBlockDate, $ctbBlockTime);
        }

        return $this->getPackageSlots($requestDate, $todayDate, $blockDate, $blockTime, $tourPackage, $packageId, $ctbBlockDate, $ctbBlockTime);
    }

    /**
     * Query to return the slot and tourist info for package
     * 
     * @param string $requestDate
     * @param string $todayDate
     * @param string $blockDate
     * @param string $blockTime
     * @param object $tourPackage
     * @param int $packageId
     */
    public function getPackageSlots($requestDate, $todayDate, $blockDate, $blockTime, $tourPackage, $packageId, $ctbBlockDate = null, $ctbBlockTime = null)
    {
        $year = Carbon::parse($requestDate)->format('Y');
        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats as seats',
            'tst.bookable_status',
            DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id = " . $packageId . "
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
            DB::raw("IFNULL((SELECT tpf.is_parent
                    FROM tour_package_families AS tpf
                    WHERE tpf.tour_package_id = tst.tour_package_id
                        AND tst.time_from = tpf.time_from
                        AND tpf.relation_year = $year
                        AND tpf.tour_package_id = $packageId
                    GROUP BY tpf.tour_package_id), 0) AS is_parent"),
            DB::raw("IFNULL((SELECT tpf.group_index
                    FROM tour_package_families AS tpf
                    WHERE tpf.tour_package_id = tst.tour_package_id
                        AND tst.time_from = tpf.time_from
                        AND tpf.relation_year = $year
                        AND tpf.tour_package_id = $packageId
                    GROUP BY tpf.tour_package_id), 0) AS group_index"),
        ];

        $slotTimes = DB::table(table: 'tour_slot_times AS tst')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->whereDate('tst.date', $requestDate)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->when($requestDate < $blockDate, function ($query) {
                $query->whereRaw('0 = 1');
            })
            ->when($requestDate == $blockDate && $tourPackage->package_has_slots, function ($query) use ($blockTime) {
                $query->where('tst.time_from', '>=', $blockTime);
            })
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->orderBy('tst.time_from')
            ->get($fields);

        /* START - mapping tour package and reducing the relational seats */
        $packageRelationSetting = hasSetting('PACKAGE_RELATION');
        $tourPackageFamily = [];
        if ($packageRelationSetting) {
            $tourPackageFamily = DB::select("SELECT *
                FROM tour_package_families
                WHERE relation_year = $year
                AND parent_package_id IN (
                    SELECT parent_package_id
                    FROM tour_package_families
                    WHERE tour_package_id = $packageId
                        AND relation_year = $year
                )");
        }
        /* END */

        $totalTourists = Constants::STATUS_ZERO;
        $slots = [];
        foreach ($slotTimes as $key => $responseVal) {
            $slotDateTime = "$requestDate {$responseVal->time_from}";

            /* START - mapping tour package and reducing the relational seats */
            $relationPackage = [];
            $totalTourists = Constants::STATUS_ZERO;
            if ($packageRelationSetting && count($tourPackageFamily)) {
                $relationPackage = array_filter($tourPackageFamily, function ($obj) use ($responseVal) {
                    return $obj->tour_package_id == $responseVal->tour_package_id && $obj->group_index == $responseVal->group_index;
                });

                $totalTourists = TouristService::countTotalTourists($tourPackageFamily, $responseVal);
            }
            /* END */

            $customDate = SurchargeDate::where('tour_package_id', $packageId)
                ->where('date_range_start', '<=', $requestDate)
                ->where('date_range_end', '>=', $requestDate)
                ->whereNull('affiliate_id')
                ->orderBy('priority', 'DESC')
                ->first();

            $rateSlotMapping = 0;
            if ($customDate && $customDate->apply_on_slots) {
                $rateSlotMapping = RateSlotMapping::where('tour_package_id', $packageId)
                    ->where('custom_date_id', $customDate->id)
                    ->where('time_from', $responseVal->time_from)
                    ->whereNull('affiliate_id')
                    ->first();
            }

            // Check if slot is within block time (not bookable due to duration requirement)
            $isWithinBlockTime = false;
            if ($tourPackage->package_has_slots && $requestDate == $blockDate) {
                $isWithinBlockTime = $responseVal->time_from < $blockTime;
            }

            // Check if slot is within Call To Book block time
            $isWithinCtbBlockTime = false;
            if ($ctbBlockDate !== null && $ctbBlockTime !== null && $tourPackage->package_has_slots && $requestDate == $ctbBlockDate) {
                $isWithinCtbBlockTime = $responseVal->time_from < $ctbBlockTime;
            }

            // Slot should be closed if within either block time or CTB block time
            $shouldBeClosed = $isWithinBlockTime || $isWithinCtbBlockTime;

            $slots[$key]['Id']                  = $responseVal->id;
            $slots[$key]['Time']                = date('g:i A', strtotime($slotDateTime));
            $slots[$key]['date']                = $requestDate;
            $slots[$key]['dd']                  = ($totalTourists > Constants::STATUS_ZERO && !empty($relationPackage)) ? $totalTourists : $responseVal->tourists;
            $slots[$key]['seats']               = $responseVal->seats;
            $slots[$key]['slot_time']           = $responseVal->time_from;
            $slots[$key]['bookable_status']     = $shouldBeClosed ? 'Closed' : $responseVal->bookable_status;
            $slots[$key]['custom_rate']         = $rateSlotMapping ? $customDate->id : 0;
            $slots[$key]['is_blocked']          = $shouldBeClosed;
        }

        return $this->getCommonResponse($packageId, $requestDate, $todayDate, $slots);
    }

    /**
     * Query to return the slot and tourist info for affiliate
     * 
     * @param string $requestDate
     * @param string $todayDate
     * @param object $tourPackage
     * @param int $packageId
     * @param int $affiliateId
     */
    public function getAffiliateSlots($requestDate, $todayDate, $tourPackage, $packageId, $affiliateId, $ctbBlockDate = null, $ctbBlockTime = null)
    {
        $affiliate = Customer::findOrFail($affiliateId);

        $duration = Constants::STATUS_ZERO;
        if (isset($affiliate->duration) && $affiliate->duration !== null) {
            $duration = $affiliate->duration;
            if ($affiliate->day_hours == Constants::STATUS_ONE) {
                $duration *= 24;
            }
        }

        $duration = (int) $duration;
        $today = Carbon::now();
        $currentTime = $today->format('H:i:s');
        $carbonDate = Carbon::parse($currentTime);
        $setDate = $carbonDate->addHours($duration);
        $blockDate = $setDate->format('Y-m-d');
        $blockTime = $setDate->format('H:i:s');
        $year = Carbon::parse($requestDate)->format('Y');

        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats as seats',
            'tst.bookable_status',
            DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                    AND tbm.customer_id = " . $affiliateId . "
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS afl_tourists"),
            DB::raw('(SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != "Cancelled"
                    GROUP BY tbm.tour_slot_id) AS tourists'),
            DB::raw("IFNULL((SELECT tpf.is_parent
                    FROM tour_package_families AS tpf
                    WHERE tpf.tour_package_id = tst.tour_package_id
                        AND tst.time_from = tpf.time_from
                        AND tpf.relation_year = $year
                        AND tpf.tour_package_id = $packageId
                    GROUP BY tpf.tour_package_id), 0) AS is_parent"),
            DB::raw("IFNULL((SELECT tpf.group_index
                    FROM tour_package_families AS tpf
                    WHERE tpf.tour_package_id = tst.tour_package_id
                        AND tst.time_from = tpf.time_from
                        AND tpf.relation_year = $year
                        AND tpf.tour_package_id = $packageId
                    GROUP BY tpf.tour_package_id), 0) AS group_index"),
        ];

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
            ->whereDate('tst.date', $requestDate)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', '=', Constants::STATUS_ZERO)
            ->when($requestDate < $blockDate, function ($query) {
                $query->whereRaw('0 = 1');
            })
            ->when($requestDate == $blockDate && $tourPackage->package_has_slots, function ($query) use ($blockTime) {
                $query->where('tst.time_from', '>=', $blockTime);
            })
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->orderBy('tst.time_from')
            ->get($fields);

        $affiliateTimeSlots = DB::table('tour_slot_times_affiliate as tsta')
            ->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
            ->where('ast.affiliate_id', $affiliate->id)
            ->where('tsta.tour_package_id', $packageId)
            ->where('tsta.status', Constants::STATUS_ACTIVE)
            ->where('tsta.deleted_by', Constants::STATUS_ZERO)
            ->select('tsta.id as time_slot_id', 'tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
            ->get()
            ->toArray();

        /* START - mapping tour package and reducing the relational seats */
        $packageRelationSetting = hasSetting('PACKAGE_RELATION');
        $tourPackageFamily = [];
        $relatedPackageBookingsLookup = []; // Pre-load related package bookings
        $parentPackageDataLookup = []; // Pre-load parent package data

        if ($packageRelationSetting) {
            $tourPackageFamily = DB::select("SELECT *
                FROM tour_package_families
                WHERE relation_year = $year
                AND parent_package_id IN (
                    SELECT parent_package_id
                    FROM tour_package_families
                    WHERE tour_package_id = $packageId
                        AND relation_year = $year
                )");

            // PERFORMANCE FIX: Pre-load all related package bookings for this date
            if (!empty($tourPackageFamily)) {
                $relatedPackageIds = array_unique(array_column($tourPackageFamily, 'tour_package_id'));
                $relatedPackageIds = array_filter($relatedPackageIds, fn($id) => $id != $packageId);

                if (!empty($relatedPackageIds)) {
                    $relatedBookingsResults = DB::table('tour_bookings_master AS tbm')
                        ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                        ->whereIn('tbm.tour_package_id', $relatedPackageIds)
                        ->where('tbm.status', '!=', 'Cancelled')
                        ->whereDate('tst.date', $requestDate)
                        ->select('tbm.tour_package_id', 'tst.time_from', DB::raw('SUM(tbm.tourists) as total_bookings'))
                        ->groupBy('tbm.tour_package_id', 'tst.time_from')
                        ->get();

                    // Build lookup: package_id|time_from => bookings
                    foreach ($relatedBookingsResults as $result) {
                        $lookupKey = $result->tour_package_id . '|' . $result->time_from;
                        $relatedPackageBookingsLookup[$lookupKey] = (int)$result->total_bookings;
                    }
                }

                // Pre-load parent package data for child packages
                $isChildPackage = false;
                foreach ($tourPackageFamily as $family) {
                    if ($family->tour_package_id == $packageId && $family->is_parent == 0) {
                        $isChildPackage = true;
                        $parentPackageId = $family->parent_package_id;

                        // Get parent package data
                        $parentSlotResults = DB::table('tour_slot_times AS tst')
                            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                            ->leftJoin('tour_bookings_master AS tbm', function($join) use ($parentPackageId) {
                                $join->on('tbm.tour_slot_id', '=', 'tst.id')
                                     ->where('tbm.tour_package_id', '=', $parentPackageId)
                                     ->where('tbm.status', '!=', 'Cancelled');
                            })
                            ->where('tst.tour_package_id', $parentPackageId)
                            ->whereDate('tst.date', $requestDate)
                            ->where('tst.deleted_by', Constants::STATUS_ZERO)
                            ->where('tsd.deleted_by', Constants::STATUS_ZERO)
                            ->select('tst.time_from', 'tst.time_slot_seats', DB::raw('IFNULL(SUM(tbm.tourists), 0) as parent_bookings'))
                            ->groupBy('tst.time_from', 'tst.time_slot_seats')
                            ->get();

                        foreach ($parentSlotResults as $result) {
                            $parentPackageDataLookup[$result->time_from] = [
                                'seats' => $result->time_slot_seats,
                                'bookings' => (int)$result->parent_bookings
                            ];
                        }
                        break;
                    }
                }
            }
        }
        /* END */

        $slots = [];
        foreach ($slotTimes as $key => $responseVal) {
            $slotDateTime = "$requestDate {$responseVal->time_from}";

            /* START - mapping tour package and reducing the relational seats */
            $relationPackage = [];
            if ($packageRelationSetting && count($tourPackageFamily)) {
                $relationPackage = array_filter($tourPackageFamily, function ($obj) use ($responseVal) {
                    return $obj->tour_package_id == $responseVal->tour_package_id && $obj->group_index == $responseVal->group_index;
                });
            }
            /* END */

            $affiliateTimeSlots = (isset($affiliateTimeSlots) && !empty($affiliateTimeSlots)) ? $affiliateTimeSlots : [];
            $desiredSlot = collect($affiliateTimeSlots)->first(function ($slot) use ($responseVal) {
                return $slot->time_from === $responseVal->time_from;
            });

            // Affiliate seats calculation
            $tourists = (int) $responseVal->afl_tourists;
            $affiliateSeats = isset($desiredSlot->seat_limit) ? (($responseVal->seats > Constants::STATUS_ZERO) ? (($responseVal->seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($responseVal->seats)) : Constants::STATUS_ZERO) : Constants::STATUS_ZERO;

            // FIXED: Apply parent-child package logic for affiliates (same as calendarAHR)
            if ($affiliateSeats > 0) {
                // Check if this is a parent package or child package
                $isParentPackage = !empty($relationPackage) && $responseVal->is_parent == 1;
                $isChildPackage = !empty($relationPackage) && $responseVal->is_parent == 0;

                $globalBookings = (int)$responseVal->tourists;

                if ($packageRelationSetting && count($tourPackageFamily)) {
                    if ($isParentPackage) {
                        // PARENT package assigned to affiliate: include child bookings to prevent overbooking
                        $childBookings = 0;

                        // Use pre-loaded child bookings data (NO QUERIES IN LOOP!)
                        foreach ($tourPackageFamily as $family) {
                            if ($family->group_index == $responseVal->group_index && 
                                $family->tour_package_id != $responseVal->tour_package_id) {
                                
                                $lookupKey = $family->tour_package_id . '|' . $responseVal->time_from;
                                $childBookings += $relatedPackageBookingsLookup[$lookupKey] ?? 0;
                            }
                        }

                        $globalBookings += $childBookings;
                    } elseif ($isChildPackage) {
                        // CHILD package assigned to affiliate: need to check parent capacity and sibling bookings
                        $parentTotalSeats = 0;
                        $parentTotalBookings = 0;

                        // Use pre-loaded parent package data (NO QUERIES IN LOOP!)
                        $parentData = $parentPackageDataLookup[$responseVal->time_from] ?? null;
                        if ($parentData) {
                            $parentTotalSeats = $parentData['seats'];
                            $parentTotalBookings = $parentData['bookings'];
                        }

                        // Add sibling bookings using pre-loaded data
                        foreach ($tourPackageFamily as $family) {
                            if ($family->group_index == $responseVal->group_index && 
                                $family->tour_package_id != $responseVal->tour_package_id &&
                                $family->is_parent == 0) {

                                $lookupKey = $family->tour_package_id . '|' . $responseVal->time_from;
                                $parentTotalBookings += $relatedPackageBookingsLookup[$lookupKey] ?? 0;
                            }
                        }

                        // Add current child package bookings
                        $parentTotalBookings += $globalBookings;

                        // For child packages: availability is constrained by parent's remaining capacity
                        if ($parentTotalSeats > 0) {
                            $parentRemainingCapacity = max(0, $parentTotalSeats - $parentTotalBookings);
                            $childOwnAvailability = max(0, $affiliateSeats - $tourists);
                            $availableSeats = min($childOwnAvailability, $parentRemainingCapacity);

                            // Also constrain by child's own slot capacity
                            $globalRemaining = max(0, $responseVal->seats - $globalBookings);
                            $availableSeats = min($availableSeats, $globalRemaining);
                        } else {
                            // No parent capacity found - use child's own capacity only
                            $globalRemaining = max(0, $responseVal->seats - $globalBookings);
                            $affiliateRemaining = max(0, $affiliateSeats - $tourists);
                            $availableSeats = min($globalRemaining, $affiliateRemaining);
                        }
                    } else {
                        // No relation package - use standard logic
                        $globalRemaining = max(0, $responseVal->seats - $globalBookings);
                        $affiliateRemaining = max(0, $affiliateSeats - $tourists);
                        $availableSeats = min($globalRemaining, $affiliateRemaining);
                    }
                } else {
                    // No package relations - use standard logic
                    $globalRemaining = max(0, $responseVal->seats - $globalBookings);
                    $affiliateRemaining = max(0, $affiliateSeats - $tourists);
                    $availableSeats = min($globalRemaining, $affiliateRemaining);
                }

                // For parent packages, we already calculated availableSeats above
                if ($isParentPackage) {
                    $globalRemaining = max(0, $responseVal->seats - $globalBookings);
                    $affiliateRemaining = max(0, $affiliateSeats - $tourists);
                    $availableSeats = min($globalRemaining, $affiliateRemaining);
                }
            } else {
                // No affiliate seat allocation
                $availableSeats = Constants::STATUS_ZERO;
            }

            $customDate = SurchargeDate::where('tour_package_id', $packageId)
                ->where('affiliate_id', $affiliateId)
                ->where('date_range_start', '<=', $requestDate)
                ->where('date_range_end', '>=', $requestDate)
                ->orderBy('priority', 'DESC')
                ->first();

            $rateSlotMapping = 0;
            if ($customDate && $customDate->apply_on_slots) {
                $rateSlotMapping = RateSlotMapping::where('tour_package_id', $packageId)
                    ->where('affiliate_id', $affiliateId)
                    ->where('custom_date_id', $customDate->id)
                    ->where('time_from', $responseVal->time_from)
                    ->count();
            }

            // Check if slot is within block time (not bookable due to duration requirement)
            $isWithinBlockTime = false;
            if ($tourPackage->package_has_slots && $requestDate == $blockDate) {
                $isWithinBlockTime = $responseVal->time_from < $blockTime;
            }

            // Check if slot is within Call To Book block time
            $isWithinCtbBlockTime = false;
            if ($ctbBlockDate !== null && $ctbBlockTime !== null && $tourPackage->package_has_slots && $requestDate == $ctbBlockDate) {
                $isWithinCtbBlockTime = $responseVal->time_from < $ctbBlockTime;
            }

            // Slot should be closed if within either block time or CTB block time
            $shouldBeClosed = $isWithinBlockTime || $isWithinCtbBlockTime;

            $slots[$key]['Id']                  = $responseVal->id;
            $slots[$key]['Time']                = date('g:i A', strtotime($slotDateTime));
            $slots[$key]['date']                = $requestDate;
            $slots[$key]['seats']               = $shouldBeClosed ? 0 : $availableSeats;
            $slots[$key]['dd']                  = Constants::STATUS_ZERO;
            $slots[$key]['slot_time']           = $responseVal->time_from;
            $slots[$key]['bookable_status']     = $shouldBeClosed ? 'Closed' : $responseVal->bookable_status;
            $slots[$key]['custom_rate']         = $rateSlotMapping ? $customDate->id : 0;
            $slots[$key]['is_blocked']          = $shouldBeClosed;
        }

        return $this->getCommonResponse($packageId, $requestDate, $todayDate, $slots);
    }

    /**
     * Common response for normal and affiliate time slots
     * 
     * @param int $packageId
     * @param string $requestDate
     * @param string $todayDate
     * @param array $slots
     */
    public function getCommonResponse($packageId, $requestDate, $todayDate, $slots)
    {
        $year = date('Y', strtotime($todayDate));
        $todayDate = date('Y-m-d', strtotime($todayDate));

        $firstSlotDate = DB::table('tour_slot_dates AS tsd')
            ->where('tsd.tour_package_id', $packageId)
            ->where('tsd.status', 'Active')
            ->where('tsd.deleted_by', Constants::STATUS_ZERO)
            ->whereYear('tsd.date', $year)
            ->orderBy('tsd.date')
            ->first('tsd.date');

        $begins = null;
        if ($firstSlotDate && $firstSlotDate->date > $todayDate && $firstSlotDate->date > $requestDate) {
            $begins = date('l, F j, Y', strtotime($firstSlotDate->date));
        }

        if (count($slots) > Constants::STATUS_ZERO) {
            $closedSlotVisibility = hasSetting('SOLDOUT_SLOT_VISIBILITY');

            $slotSeats = max(array_column($slots, 'seats'));

            return response()->json([
                'TotalAvailableSeats' => $slotSeats,
                'Time' => $slots,
                'begins' => $begins,
                'closedSlotVisibility' => $closedSlotVisibility
            ]);
        } else {
            return $this->sendError('Slot Not Found.', [
                'begins' => $begins
            ]);
        }
    }
}
