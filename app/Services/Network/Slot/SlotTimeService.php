<?php

namespace App\Services\Network\Slot;

use Illuminate\Support\Facades\DB;
use App\Constants\Constants;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotTimeService
{
    /**
     * Get the slots
     * 
     * @param int $packageId
     * @param int $affiliateId
     * @param string $date
     * @param string $blockDate
     * @param string $blockTime
     * @param bool $hasSlots
     * 
     * @return Collection
     */
    public function getSlotTimes($packageId, $affiliateId, $date, $blockDate, $blockTime, $hasSlots): Collection
    {
        $year = Carbon::parse($date)->format('Y');
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
                        AND tpf.tour_package_id = $packageId
                        AND tpf.relation_year = $year
                    GROUP BY tpf.tour_package_id), 0) AS is_parent"),
            DB::raw("IFNULL((SELECT tpf.group_index
                    FROM tour_package_families AS tpf
                    WHERE tpf.tour_package_id = tst.tour_package_id
                        AND tst.time_from = tpf.time_from
                        AND tpf.tour_package_id = $packageId
                        AND tpf.relation_year = $year
                    GROUP BY tpf.tour_package_id), 0) AS group_index"),
        ];

        return DB::table('tour_slot_times AS tst')
            ->whereDate('tst.date', $date)
            ->where('tst.tour_package_id', $packageId)
            ->where('tst.deleted_by', Constants::STATUS_ZERO)
            ->whereDate('tst.date', '>=', $blockDate)
            ->when($hasSlots && $date == $blockDate, function ($query) use ($blockTime) {
                $query->whereTime('tst.time_from', '>=', $blockTime);
            })
            ->where('tst.hide_slot', Constants::STATUS_ZERO)
            ->orderBy('tst.time_from')
            ->get($fields);
    }

    /**
     * Calculate block date and time based on affiliate duration
     *
     * @param object $affiliate
     * @return array
     */
    public function calculateBlockTime($affiliate): array
    {
        $duration = $affiliate->duration ?? Constants::STATUS_ZERO;
        if (($affiliate->day_hours ?? Constants::STATUS_ZERO) === Constants::STATUS_ONE) {
            $duration *= 24;
        }

        $now = Carbon::now()->addHours((int) $duration);

        return [$now->format('Y-m-d'), $now->format('H:i:s')];
    }

    /**
     * Calculate available seats
     *
     * @param int $totalSeats
     * @param int $affiliateSeats
     * @param int $affiliateBooked
     * @param int $bookedSeats
     * @return int
     */
    public function calculateAvailableSeats(int $totalSeats, int $affiliateSeats, int $affiliateBooked, int $bookedSeats): int
    {
        if ($bookedSeats >= $totalSeats || $affiliateBooked >= $affiliateSeats) {
            return Constants::STATUS_ZERO;
        }

        $globalAvailable  = $totalSeats - $bookedSeats;
        $affiliateAvailable = $affiliateSeats - $affiliateBooked;

        if ($affiliateSeats === 0) {
            return $globalAvailable;
        }

        $vacant = min($globalAvailable, $affiliateAvailable);
        return $vacant >= $affiliateSeats ? $affiliateSeats : $vacant;
    }
}
