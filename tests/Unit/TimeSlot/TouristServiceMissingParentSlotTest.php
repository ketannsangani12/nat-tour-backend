<?php

namespace Tests\Unit\TimeSlot;

use App\Services\TimeSlot\TouristService;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (production report, tenant "kens"): a package-family relation whose PARENT package
 * has no tour_slot_times row for the date/time being viewed (e.g. the parent isn't scheduled that
 * day while a sibling package is) crashed with "Trying to access array offset on false" —
 * array_filter(...'parent' === true...) came back empty, so reset() on it is false.
 *
 * TouristService::countTotalTourists() and countTotalTouristsWithSlotSummary() now fall back to
 * the current package's own tourist count instead of assuming a parent slot always resolves,
 * matching the fallback already used by their "Optimized" siblings.
 */
class TouristServiceMissingParentSlotTest extends TestCase
{
    public function test_falls_back_to_current_package_count_when_parent_slot_is_unresolvable(): void
    {
        $date = now()->addDays(5)->toDateString();
        $timeFrom = '09:00:00';

        $booking = (new CreateABooking())
            ->withNewPackage()
            ->withDate($date)
            ->withTimeSlot($timeFrom, '11:00:00')
            ->withSeats(10)
            ->withTourists(2)
            ->create();

        try {
            $timeSlot = (object) [
                'date' => $date,
                'time_from' => $timeFrom,
                'tour_package_id' => $booking->packageId,
                'group_index' => 1,
            ];

            // The "parent" relation points at a package with no tour_slot_times row at all for
            // this date/time — getTotalTouristsForSlot() resolves it to false and it gets
            // filtered out, leaving no 'parent' === true element for either method to find.
            $tourPackageFamily = [
                (object) [
                    'tour_package_id' => $booking->packageId,
                    'time_from' => $timeFrom,
                    'is_parent' => 0,
                    'group_index' => 1,
                ],
                (object) [
                    'tour_package_id' => 999999999,
                    'time_from' => $timeFrom,
                    'is_parent' => 1,
                    'group_index' => 1,
                ],
            ];

            $this->assertSame(2, TouristService::countTotalTourists($tourPackageFamily, $timeSlot));

            // countTotalTouristsWithSlotSummary() reads from tour_slot_summaries, which this
            // fixture doesn't populate, so it isn't expected to also report 2 here — the point is
            // only that the missing-parent-slot case no longer throws.
            TouristService::countTotalTouristsWithSlotSummary($tourPackageFamily, $timeSlot);
        } finally {
            $booking->cleanup();
        }
    }
}
