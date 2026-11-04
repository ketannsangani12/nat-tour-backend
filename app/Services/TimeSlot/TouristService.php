<?php

namespace App\Services\TimeSlot;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TouristService
{
    /**
     * Count the tourists from the bookings
     * 
     * @param $request
     * @param $tourPackageFamily
     * @param $timeSlot
     */
    public static function countTotalTourists($tourPackageFamily, $timeSlot)
    {
        // Given tour_package_id and group_index
        $targetGroupIndex = $timeSlot->group_index;
        $totalTourists = 0;
        $slotWiseArr = [];
        foreach ($tourPackageFamily as $relation) {
            if ($targetGroupIndex == $relation->group_index) {
                $slot = self::getTotalTouristsForSlot($timeSlot, $relation);
                if ($slot == false) {
                    continue;
                }

                $slotWiseArr[] = $slot;
            }
        }

        if (count($slotWiseArr)) {
            // Use array_filter to find the element where parent is false
            $filtered = array_filter($slotWiseArr, fn($package) => $package['parent'] === true);

            // Get the first (and only) element of the filtered array
            $firstFilteredElement = reset($filtered);

            // The parent package can lack a resolvable slot for this date/time (e.g. it isn't
            // scheduled that day while a sibling package is) even though the group_index matched —
            // fall back to the current package's own count instead of crashing on a missing parent.
            if ($firstFilteredElement === false) {
                $targetPackageFiltered = array_filter($slotWiseArr, fn($package) => $package['current_package'] === true);
                $targetPackage = reset($targetPackageFiltered);

                return $targetPackage['tourists'] ?? 0;
            }

            // Extract the seats value
            $seats = $firstFilteredElement['seats'];

            $tourists = 0;
            $overbook = 0;
            $available = 0;
            foreach ($slotWiseArr as &$package) {
                $tourists += $package['tourists'];

                // Calculate available seats for each package
                $package['available'] = max(0, $package['seats'] - $package['tourists']);
                $available += $package['available'];

                // Calculate overbooking for each package
                $package['overbook'] = $package['tourists'] > $package['seats'] ? $package['tourists'] - $package['seats'] : 0;
                $overbook += $package['overbook'];
            }

            $targetPackageFiltered = array_filter($slotWiseArr, fn($package) => $package['current_package'] === true);
            $targetPackage = reset($targetPackageFiltered);

            $responseSeats = 0;
            $additionalTourists = 0;
            if ($tourists < $seats) {
                $vacantSeats = $seats - $tourists;
                $responseSeats = min($vacantSeats, $targetPackage['available']);
            } elseif ($firstFilteredElement['parent'] == true && $firstFilteredElement['current_package'] == true && $overbook == 0) {
                $additionalTourists = $tourists - $seats;
            }

            $totalTourists = ($targetPackage['seats'] - $responseSeats) + $targetPackage['overbook'] + $additionalTourists;
        }

        return $totalTourists;
    }

    /**
     * Count the tourists from the bookings with optimised logic
     * 
     * @param $request
     * @param $tourPackageFamily
     * @param $timeSlot
     */
    public static function countTotalTouristsOptimized($relations, $allSlotData, $timeSlot)
    {
        $slotWiseArr = [];
        foreach ($relations as $relation) {
            $key = "{$relation->tour_package_id}|{$timeSlot->date}|{$relation->time_from}";
            $slotData = $allSlotData[$key][0] ?? null;
            if ($slotData) {
                $type = $relation->is_parent ? true : false;
                $currentPackage = ($timeSlot->tour_package_id == $relation->tour_package_id) ? true : false;
                $slot = [
                    'seats' => $slotData->total_seats,
                    'tourists' => $slotData->total_tourists,
                    'parent' => $type,
                    'current_package' => $currentPackage,
                    'package_id' => $relation->tour_package_id,
                ];
                $slotWiseArr[] = $slot;
            }
        }

        if (count($slotWiseArr) == 0) {
            return 0;
        }

        // Find parent and current package
        $parentPackage = array_filter($slotWiseArr, fn($package) => $package['parent'] === true);
        $parentPackage = reset($parentPackage);
        
        $currentPackage = array_filter($slotWiseArr, fn($package) => $package['current_package'] === true);
        $currentPackage = reset($currentPackage);

        if (!$parentPackage || !$currentPackage) {
            return $currentPackage['tourists'] ?? 0;
        }

        // Calculate total tourists across all related packages
        $totalTouristsAllPackages = 0;
        $childTourists = 0;
        
        foreach ($slotWiseArr as $package) {
            $totalTouristsAllPackages += $package['tourists'];
            if (!$package['parent']) {
                $childTourists += $package['tourists'];
            }
        }

        // Apply the new logic based on package type
        if ($currentPackage['parent']) {
            // Parent Package: Show sum of Parent + Child bookings
            return $totalTouristsAllPackages;
        } else {
            // Child Package: Show only Child bookings
            return $currentPackage['tourists'];
        }
    }

    /**
     * Calculate available seats based on parent-child relationship
     * 
     * @param $relations
     * @param $allSlotData
     * @param $timeSlot
     * @return array
     */
    public static function calculateAvailabilityOptimized($relations, $allSlotData, $timeSlot)
    {
        $slotWiseArr = [];
        foreach ($relations as $relation) {
            $key = "{$relation->tour_package_id}|{$timeSlot->date}|{$relation->time_from}";
            $slotData = $allSlotData[$key][0] ?? null;
            if ($slotData) {
                $type = $relation->is_parent ? true : false;
                $currentPackage = ($timeSlot->tour_package_id == $relation->tour_package_id) ? true : false;
                $slot = [
                    'seats' => $slotData->total_seats,
                    'tourists' => $slotData->total_tourists,
                    'parent' => $type,
                    'current_package' => $currentPackage,
                    'package_id' => $relation->tour_package_id,
                ];
                $slotWiseArr[] = $slot;
            }
        }

        if (count($slotWiseArr) == 0) {
            return [
                'available_seats' => 0,
                'total_seats' => 0,
                'overbooked' => 0,
                'tourists_shown' => 0
            ];
        }

        // Find parent and current package
        $parentPackage = array_filter($slotWiseArr, fn($package) => $package['parent'] === true);
        $parentPackage = reset($parentPackage);
        
        $currentPackage = array_filter($slotWiseArr, fn($package) => $package['current_package'] === true);
        $currentPackage = reset($currentPackage);

        if (!$parentPackage || !$currentPackage) {
            $package = $currentPackage ?: reset($slotWiseArr);
            return [
                'available_seats' => max(0, $package['seats'] - $package['tourists']),
                'total_seats' => $package['seats'],
                'overbooked' => max(0, $package['tourists'] - $package['seats']),
                'tourists_shown' => $package['tourists']
            ];
        }

        // Calculate total tourists across all related packages
        $totalTouristsAllPackages = 0;
        foreach ($slotWiseArr as $package) {
            $totalTouristsAllPackages += $package['tourists'];
        }

        if ($currentPackage['parent']) {
            // Parent Package Logic
            $parentSeats = $parentPackage['seats'];
            $totalBookings = $totalTouristsAllPackages; // Parent + Child bookings
            
            return [
                'available_seats' => max(0, $parentSeats - $totalBookings),
                'total_seats' => $parentSeats,
                'overbooked' => max(0, $totalBookings - $parentSeats),
                'tourists_shown' => $totalBookings
            ];
        } else {
            // Child Package Logic
            $parentSeats = $parentPackage['seats'];
            $totalBookings = $totalTouristsAllPackages; // Parent + Child bookings
            $childBookings = $currentPackage['tourists']; // Only child bookings
            $childSeats = $currentPackage['seats'];
            
            // Child availability is constrained by both child capacity and parent's remaining capacity
            $childOwnAvailability = max(0, $childSeats - $childBookings);
            $parentRemainingCapacity = max(0, $parentSeats - $totalBookings);
            $childAvailability = min($childOwnAvailability, $parentRemainingCapacity);
            
            // Child overbooking is based on its own seat limit
            $childOverbooked = max(0, $childBookings - $childSeats);
            
            return [
                'available_seats' => $childAvailability,
                'total_seats' => $childSeats,
                'overbooked' => $childOverbooked,
                'tourists_shown' => $childBookings
            ];
        }
    }

    /**
     * Count the tourists from the bookings
     * 
     * @param $request
     * @param $tourPackageFamily
     * @param $timeSlot
     */
    public static function countTotalTouristsWithSlotSummary($tourPackageFamily, $timeSlot)
    {
        // Given tour_package_id and group_index
        $targetGroupIndex = $timeSlot->group_index;
        $totalTourists = 0;
        $slotWiseArr = [];
        foreach ($tourPackageFamily as $relation) {
            if ($targetGroupIndex == $relation->group_index) {
                $slot = self::getTotalTouristsForSlotWithSlotSummary($timeSlot, $relation);
                if ($slot == false) {
                    continue;
                }

                $slotWiseArr[] = $slot;
            }
        }

        if (count($slotWiseArr)) {
            // Use array_filter to find the element where parent is false
            $filtered = array_filter($slotWiseArr, fn($package) => $package['parent'] === true);

            // Get the first (and only) element of the filtered array
            $firstFilteredElement = reset($filtered);

            // The parent package can lack a resolvable slot for this date/time (e.g. it isn't
            // scheduled that day while a sibling package is) even though the group_index matched —
            // fall back to the current package's own count instead of crashing on a missing parent.
            if ($firstFilteredElement === false) {
                $targetPackageFiltered = array_filter($slotWiseArr, fn($package) => $package['current_package'] === true);
                $targetPackage = reset($targetPackageFiltered);

                return $targetPackage['tourists'] ?? 0;
            }

            // Extract the seats value
            $seats = $firstFilteredElement['seats'];

            $tourists = 0;
            $overbook = 0;
            $available = 0;
            foreach ($slotWiseArr as &$package) {
                $tourists += $package['tourists'];

                // Calculate available seats for each package
                $package['available'] = max(0, $package['seats'] - $package['tourists']);
                $available += $package['available'];

                // Calculate overbooking for each package
                $package['overbook'] = $package['tourists'] > $package['seats'] ? $package['tourists'] - $package['seats'] : 0;
                $overbook += $package['overbook'];
            }

            $targetPackageFiltered = array_filter($slotWiseArr, fn($package) => $package['current_package'] === true);
            $targetPackage = reset($targetPackageFiltered);

            $responseSeats = 0;
            $additionalTourists = 0;
            if ($tourists < $seats) {
                $vacantSeats = $seats - $tourists;
                $responseSeats = min($vacantSeats, $targetPackage['available']);
            } elseif ($firstFilteredElement['parent'] == true && $firstFilteredElement['current_package'] == true && $overbook == 0) {
                $additionalTourists = $tourists - $seats;
            }


            $totalTourists = ($targetPackage['seats'] - $responseSeats) + $targetPackage['overbook'] + $additionalTourists;
        }

        return $totalTourists;
    }
    /**
     * Count the tourists from the slots
     * 
     * @param $request
     * @param $relation
     */
    public static function getTotalTouristsForSlot($timeSlot, $relation)
    {
        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats as seats',
            'tst.bookable_status',
            DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                AND tbm.tour_package_id = tst.tour_package_id
                GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
        ];

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->whereDate('tst.date', $timeSlot->date)
            ->where('tst.time_from', $relation->time_from)
            ->where('tst.tour_package_id', $relation->tour_package_id)
            ->first($fields);

        $type = $relation->is_parent ? true : false;
        $currentPackage = ($timeSlot->tour_package_id == $relation->tour_package_id) ? true : false;
        $seats = 0;
        $tourists = 0;
        if ($slotTimes) {
            $seats = $slotTimes->seats;
            $tourists = $slotTimes->tourists;
        } else {
            return false;
        }

        return [
            'seats' => $seats,
            'tourists' => (int) $tourists,
            'parent' => $type,
            'current_package' => $currentPackage
        ];
    }
    /**
     * Count the tourists from the slots
     * 
     * @param $request
     * @param $relation
     */
    public static function getTotalTouristsForSlotWithSlotSummary($timeSlot, $relation)
    {
        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.time_from',
            'tst.time_to',
            'tst.bookable_status',
            DB::raw('COALESCE(tst.time_slot_seats, 0) as seats'),
            'tss.booked_tourists as tourists'
        ];

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->leftJoin('tour_slot_summaries AS tss', function ($join) use ($relation) {
                $join->on('tss.tour_slot_id', '=', 'tst.id')
                    ->where('tss.tour_package_id', '=', $relation->tour_package_id);
            })
            ->whereDate('tst.date', $timeSlot->date)
            ->where('tst.time_from', $relation->time_from)
            ->where('tst.tour_package_id', $relation->tour_package_id)
            ->first($fields);
        $type = $relation->is_parent ? true : false;
        $currentPackage = ($timeSlot->tour_package_id == $relation->tour_package_id) ? true : false;
        $seats = 0;
        $tourists = 0;
        if ($slotTimes) {
            $seats = $slotTimes->seats;
            $tourists = $slotTimes->tourists;
        } else {
            return false;
        }

        return [
            'seats' => $seats,
            'tourists' => (int) $tourists,
            'parent' => $type,
            'current_package' => $currentPackage
        ];
    }
    /**
     * Manage all data using the array instead of query
     * 
     * @param mixed $tourPackageFamily
     * @param mixed $dateSlots
     * @param mixed $timeSlot
     */
    public static function getTotalTouristsForSlots($tourPackageFamily, $dateSlots, $timeSlot)
    {
        // Given tour_package_id and group_index
        $targetPackageId = $timeSlot->tour_package_id;
        $targetGroupIndex = $timeSlot->group_index;

        $targetParentPackageId = null;

        foreach ($tourPackageFamily as $family) {
            if ($family->tour_package_id == $targetPackageId && $family->group_index == $targetGroupIndex) {
                $targetParentPackageId = $family->parent_package_id;
                break;
            }
        }

        $relatedPackageIds = [];
        if ($targetParentPackageId) {
            // Now filter all related packages within same parent group and group index
            $relatedPackages = array_filter($tourPackageFamily, fn($item) => $item->parent_package_id == $targetParentPackageId && $item->group_index == $targetGroupIndex);

            // Example: collect tour_package_id from relatedPackages if needed
            $relatedPackageIds = array_map(fn($item) => $item->tour_package_id, $relatedPackages);
        }

        $totalTourists = 0;
        $slotWiseArr = [];
        foreach ($tourPackageFamily as $relation) {
            if ($targetGroupIndex == $relation->group_index && in_array($relation->tour_package_id, $relatedPackageIds)) {
                $slot = array_filter($dateSlots, fn($entry) => $entry->tour_package_id == $relation->tour_package_id && $entry->time_from == $relation->time_from && $entry->date == $timeSlot->date && $entry->group_index == $targetGroupIndex);

                if (count($slot) == false) {
                    continue;
                }

                $slotWiseArr[] = $slot;
            }
        }

        if (count($slotWiseArr)) {
            // Convert objects to associative arrays
            $convertedData = array_map(fn($item) => array_map(fn($obj) => (array) $obj, $item), $slotWiseArr);

            // Flatten the array without using explicit foreach loops
            $flattenedData = call_user_func_array('array_merge', array_map('array_values', $convertedData));

            // Use array_filter to find the element where parent is false
            $filtered = array_filter($flattenedData, fn($package) => $package['is_parent'] === 1);

            // Get the first (and only) element of the filtered array
            $firstFilteredElement = reset($filtered);

            // Extract the seats value
            $seats = is_array($firstFilteredElement) ? ($firstFilteredElement['seats'] ?? 0) : 0;

            $tourists = 0;
            $overbook = 0;
            $available = 0;
            foreach ($flattenedData as &$package) {
                $tourists += $package['tourists'];

                // Calculate available seats for each package
                $package['available'] = max(0, $package['seats'] - $package['tourists']);
                $available += $package['available'];

                // Calculate overbooking for each package
                $package['overbook'] = $package['tourists'] > $package['seats'] ? $package['tourists'] - $package['seats'] : 0;
                $overbook += $package['overbook'];
            }

            $targetPackageFiltered = array_filter($flattenedData, fn($package) => $package['tour_package_id'] === $targetPackageId);
            $targetPackage = reset($targetPackageFiltered);

            $responseSeats = 0;
            $additionalTourists = 0;
            if ($tourists <= $seats) {
                $vacantSeats = $seats - $tourists;
                $responseSeats = min($vacantSeats, $targetPackage['available']);
            } elseif ($seats && $firstFilteredElement['is_parent'] == 1 && $targetPackage['tour_package_id'] === $targetPackageId && $overbook == 0) {
                $additionalTourists = $tourists - $seats;
            }

            $totalTourists = ($targetPackage['seats'] - $responseSeats) + $targetPackage['overbook'] + $additionalTourists;
        }

        return $totalTourists;
    }

    /**
     * Get time slot information using time slot ID
     * 
     * @param int $tourSlotId
     * @param int $tourPackageId
     */
    public static function getTimeSlotInfo($tourSlotId, $tourPackageId)
    {
        $slotTime = TimeSlotService::getTimeSlot($tourSlotId);
        $year = Carbon::parse($slotTime->date)->format('Y');

        // Check if package relations are enabled
        $packageRelationSetting = hasSetting('PACKAGE_RELATION');

        $fields = [
            'tst.id',
            'tst.tour_package_id',
            'tst.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats as seats',
            'tst.bookable_status',
        ];

        // Build tourist count query - include related packages if relations are enabled
        if ($packageRelationSetting) {
            $touristQuery = "IFNULL((
                SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id IN (
                    SELECT tst2.id 
                    FROM tour_slot_times AS tst2
                    JOIN tour_slot_dates AS tsd2 ON tsd2.id = tst2.tour_slot_id
                    WHERE tst2.time_from = tst.time_from 
                        AND tsd2.date = tst.date
                        AND tsd2.tour_package_id IN (
                            SELECT tpf.tour_package_id
                            FROM tour_package_families AS tpf
                            WHERE tpf.parent_package_id IN (
                                SELECT tpf2.parent_package_id
                                FROM tour_package_families AS tpf2
                                WHERE tpf2.tour_package_id = " . $tourPackageId . "
                                    AND tpf2.relation_year = $year
                            )
                            AND tpf.relation_year = $year
                            AND tpf.time_from = tst.time_from
                        )
                )
                AND tbm.status != 'Cancelled'
            ), 0) AS tourists";
        } else {
            $touristQuery = "IFNULL((SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id = tst.id
                    AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id = " . $tourPackageId . "
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists";
        }

        $fields[] = DB::raw($touristQuery);

        $fields[] = DB::raw("IFNULL((SELECT DISTINCT(tpf.is_parent)
            FROM tour_package_families AS tpf
            WHERE tpf.tour_package_id = tst.tour_package_id
                AND tst.time_from = tpf.time_from
                AND tpf.relation_year = $year
                AND tpf.tour_package_id = " . $tourPackageId . "), 0) AS is_parent");

        $fields[] = DB::raw("IFNULL((SELECT DISTINCT(tpf.group_index)
            FROM tour_package_families AS tpf
            WHERE tpf.tour_package_id = tst.tour_package_id
                AND tst.time_from = tpf.time_from
                AND tpf.relation_year = $year
                AND tpf.tour_package_id = " . $tourPackageId . "), 0) AS group_index");

        if (userType() == 'Affiliate') {
            $fields[] = DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
                FROM tour_bookings_master AS tbm2
                WHERE tbm2.tour_slot_id = tst.id
                    AND tbm2.status != 'Cancelled'
                    AND tbm2.customer_id = " . Auth::id() . "
                GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_tourists");
        }

        $slotTime = DB::table('tour_slot_times AS tst')
            ->where('tst.id', $tourSlotId)
            ->where('tst.tour_package_id', $tourPackageId)
            ->where('tst.deleted_by', '0')
            ->first($fields);

        return $slotTime;
    }
}
