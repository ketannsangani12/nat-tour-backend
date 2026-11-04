<?php

namespace App\Services\TimeSlot;

use App\Models\TourSlotSummary;
use App\Models\TourSlotTime;
use Carbon\Carbon;

class TimeSlotAvailabilityService
{
    /**
     * Increment/decrement slot summary
     */
    public static function updateSlotSummary($slotId, $touristsDiff)
    {
        $slot = TourSlotTime::find($slotId);
        if ($slot && Carbon::parse($slot->date)->year >= 2025) {
            // ensure slot summary exists
            $slot = TourSlotSummary::firstOrCreate(
                [
                    'tour_slot_id' => $slot->id,
                    'tour_package_id' => $slot->tour_package_id,
                ],
                [
                    'total_seats' => $slot->time_slot_seats,
                    'booked_tourists' => 0,
                    'available_seats' => 0,
                    'overbooked_count' => 0,
                    'date' => $slot->date,
                    'time_from' => $slot->time_from,
                    'time_to' => $slot->time_to,
                ]
            );

            $slot->booked_tourists += $touristsDiff;
            $slot->available_seats = max(0, $slot->total_seats - $slot->booked_tourists);
            $slot->overbooked_count = max(0, $slot->booked_tourists - $slot->total_seats);
            $slot->save();
        }
    }
}
