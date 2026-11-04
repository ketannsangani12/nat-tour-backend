<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LacCapacityController extends Controller
{
    public const GENERAL_PACKAGE_ID   = 1;
    public const DELUXE_PACKAGE_ID    = 2;
    public const LAC_PACKAGE_ID       = 6;
    public const AFFILIATE_PACKAGE_ID = 37;

    public const CAPACITY_REGULAR_GEN = 75;
    public const CAPACITY_REGULAR_AFF = 70;
    public const CAPACITY_DELUXE      = 69; // same for both Gen and Aff

    public const DATE_START = '2026-07-07';
    public const DATE_END   = '2026-12-31';

    public const HOLIDAYS = [
        ['2026-09-04', '2026-09-07'], // Labor Day
        ['2026-10-09', '2026-10-12'], // Columbus Day
        ['2026-11-20', '2026-11-30'], // Thanksgiving
        ['2026-12-20', '2026-12-26'], // Christmas
        ['2026-12-27', '2026-12-31'], // New Year's
    ];

    /**
     * Reallocate unused LAC capacity to General and Affiliate slots.
     * Pass ?dry_run=1 to preview without saving.
     */
    public function reallocate(): JsonResponse
    {
        try {
            $dryRun = (bool) request('dry_run', false);

            $slots = $this->fetchSlots();

            if (empty($slots)) {
                return response()->json([
                    'message'     => 'No slots matched the criteria.',
                    'slots_found' => 0,
                    'dry_run'     => $dryRun,
                ]);
            }

            $lacUpdates = [];
            $genUpdates = [];
            $affUpdates = [];

            foreach ($slots as $slot) {
                $newLac = (int) $slot->lac_bookings;
                $newGen = (int) $slot->gen_max_capacity - $newLac;
                $newAff = (int) $slot->aff_max_capacity - $newLac;

                if ((int) $slot->lac_current !== $newLac) {
                    $lacUpdates[$slot->lac_id] = $newLac;
                }
                if ((int) $slot->gen_current !== $newGen) {
                    $genUpdates[$slot->gen_id] = $newGen;
                }
                if ((int) $slot->aff_current !== $newAff) {
                    $affUpdates[$slot->aff_id] = $newAff;
                }
            }

            $result = [
                'slots_found'   => \count($slots),
                'lac_to_update' => \count($lacUpdates),
                'gen_to_update' => \count($genUpdates),
                'aff_to_update' => \count($affUpdates),
                'dry_run'       => $dryRun,
            ];

            if (!$dryRun) {
                $result['lac_updated'] = $this->bulkUpdate($lacUpdates);
                $result['gen_updated'] = $this->bulkUpdate($genUpdates);
                $result['aff_updated'] = $this->bulkUpdate($affUpdates);

                Log::info('LAC capacity reallocated', $result);
            }

            return response()->json([
                'message' => $dryRun
                    ? 'Dry run complete — no changes saved.'
                    : 'LAC capacity reallocated successfully.',
                'data' => $result,
            ]);

        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    private function fetchSlots(): array
    {
        $holidayExclusions = collect(self::HOLIDAYS)
            ->map(fn ($r) => "NOT (lac.date BETWEEN '{$r[0]}' AND '{$r[1]}')")
            ->implode(' AND ');

        return DB::select("
            SELECT
                lac.id              AS lac_id,
                lac.time_slot_seats AS lac_current,
                gen.id              AS gen_id,
                gen.time_slot_seats AS gen_current,
                aff.id              AS aff_id,
                aff.time_slot_seats AS aff_current,
                lac.date,
                lac.time_from,
                COALESCE(bk.total_booked, 0) AS lac_bookings,
                CASE WHEN dlx.id IS NOT NULL
                    THEN :cap_deluxe
                    ELSE :cap_regular_gen
                END AS gen_max_capacity,
                CASE WHEN dlx.id IS NOT NULL
                    THEN :cap_deluxe2
                    ELSE :cap_regular_aff
                END AS aff_max_capacity
            FROM tour_slot_times lac
            JOIN tour_slot_times gen
                ON  gen.date            = lac.date
                AND gen.time_from       = lac.time_from
                AND gen.tour_package_id = :gen_pkg
                AND gen.deleted_at      IS NULL
            JOIN tour_slot_times aff
                ON  aff.date            = lac.date
                AND aff.time_from       = lac.time_from
                AND aff.tour_package_id = :aff_pkg
                AND aff.deleted_at      IS NULL
            LEFT JOIN (
                SELECT tour_slot_id, COALESCE(SUM(tourists), 0) AS total_booked
                FROM tour_bookings_master
                WHERE status != 'Cancelled'
                GROUP BY tour_slot_id
            ) bk ON bk.tour_slot_id = lac.id
            LEFT JOIN tour_slot_times dlx
                ON  dlx.date            = lac.date
                AND dlx.time_from       = lac.time_from
                AND dlx.tour_package_id = :dlx_pkg
                AND dlx.deleted_at      IS NULL
            WHERE lac.tour_package_id = :lac_pkg
              AND lac.deleted_at IS NULL
              AND lac.date BETWEEN :date_start AND :date_end
              AND {$holidayExclusions}
            ORDER BY lac.date, lac.time_from
        ", [
            'cap_deluxe'      => self::CAPACITY_DELUXE,
            'cap_deluxe2'     => self::CAPACITY_DELUXE,
            'cap_regular_gen' => self::CAPACITY_REGULAR_GEN,
            'cap_regular_aff' => self::CAPACITY_REGULAR_AFF,
            'gen_pkg'         => self::GENERAL_PACKAGE_ID,
            'aff_pkg'         => self::AFFILIATE_PACKAGE_ID,
            'dlx_pkg'         => self::DELUXE_PACKAGE_ID,
            'lac_pkg'         => self::LAC_PACKAGE_ID,
            'date_start'      => self::DATE_START,
            'date_end'        => self::DATE_END,
        ]);
    }

    private function bulkUpdate(array $updates): int
    {
        if (empty($updates)) {
            return 0;
        }

        $count = 0;

        foreach (array_chunk($updates, 500, true) as $chunk) {
            $ids   = array_keys($chunk);
            $cases = implode(' ', array_map(
                fn ($id, $seats) => "WHEN {$id} THEN {$seats}",
                $ids,
                array_values($chunk)
            ));

            DB::statement("
                UPDATE tour_slot_times
                SET time_slot_seats = CASE id {$cases} END,
                    updated_at      = NOW()
                WHERE id IN (" . implode(',', $ids) . ")
            ");

            $count += \count($chunk);
        }

        return $count;
    }
}
