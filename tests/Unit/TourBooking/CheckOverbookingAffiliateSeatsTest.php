<?php

namespace Tests\Unit\TourBooking;

use App\Models\Customer;
use App\Repositories\TourBooking\TourBookingRepository as TourBookingRepositoryV1;
use App\Repositories\TourBooking\V3\TourBookingRepository as TourBookingRepositoryV3;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression tests for the See Canyon incident (2026-08-28): an affiliate reschedule
 * was blocked with "You can not book slot above limit of available seats." even
 * though the tour had plenty of physical capacity left and the affiliate's own
 * dedicated seat block was nowhere near full. Root cause was two-fold:
 *
 *  1. checkOverbooking() hardcoded `afl_seats = 0` for affiliates configured with
 *     show_all_package_bookings = 1 (i.e. affiliates who share the whole tour's
 *     pool instead of a dedicated per-slot block), so they always appeared to have
 *     zero seats available regardless of real capacity.
 *  2. The reschedule-preview JS (public/js/user-common/tour-booking-info.js) clamped
 *     the affiliate's own seat allocation down to the tour-wide remaining seats
 *     BEFORE subtracting the affiliate's own usage -- but tour-wide remaining
 *     already has the affiliate's own usage baked in, so the affiliate's own usage
 *     got subtracted twice. Whenever the shared pool got tight (other customers
 *     filling the tour), an affiliate with a healthy dedicated block could still be
 *     wrongly blocked.
 *
 * This test class locks down the PHP side of the data contract (#1, and the raw
 * ingredients #2's fix depends on). There is no jsdom test harness in this repo, so
 * the JS math fix itself needs live/manual verification -- see the comment above
 * the fix in tour-booking-info.js for the corrected formula.
 */
class CheckOverbookingAffiliateSeatsTest extends TestCase
{
    protected static array $builders = [];

    protected function authAsAffiliate(int $tourOperatorId): Customer
    {
        $now = Carbon::now();
        $customerId = DB::table('customers')->insertGetId([
            'tour_operator_id' => $tourOperatorId,
            'name' => 'Test Affiliate',
            'email' => 'affiliate-' . uniqid() . '@test.com',
            'phone_number' => '5550002222',
            'is_affiliate' => 'Yes',
            'status' => 'Active',
            'remember_token' => '',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => $now,
        ]);

        $affiliate = Customer::find($customerId);
        $this->actingAs($affiliate, 'affiliate');
        Session::put('guard', 'affiliate');

        return $affiliate;
    }

    /** @return array<string, object> */
    protected function repositories(): array
    {
        return [
            'V1' => app(TourBookingRepositoryV1::class),
            'V3' => app(TourBookingRepositoryV3::class),
        ];
    }

    /**
     * See Canyon's real numbers on the day of the incident: a 75-seat slot with 67
     * seats already booked tour-wide (12 by other customers, 55 by the affiliate's
     * own standing group booking), against the affiliate's own 70-seat dedicated
     * block. The affiliate's own remaining quota (15) and the tour's true remaining
     * capacity (8) both comfortably cover a 2-person reschedule -- checkOverbooking()
     * must report the raw numbers that make that obvious to the caller.
     */
    public function testCheckOverbookingReturnsAffiliatesOwnAllocationWhenPoolIsNearlyFull(): void
    {
        $tourOperatorId = DB::table('tour_operators')->value('id');
        $this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

        $date = Carbon::today()->addDays(30)->toDateString();
        $timeFrom = '14:45:00';
        $timeTo = '15:45:00';
        $seats = 75;

        $base = (new CreateABooking())
            ->withNewPackage()
            ->withDate($date)
            ->withTimeSlot($timeFrom, $timeTo)
            ->withSeats($seats)
            ->withTourists(0)
            ->create();
        static::$builders[] = $base;

        $affiliate = $this->authAsAffiliate($tourOperatorId);

        // Other customers use 12 of the tour-wide pool.
        $others = (new CreateABooking())
            ->usePackage($base->packageId)
            ->useSlotTime($base->slotTimeId)
            ->withTourists(12)
            ->create();
        static::$builders[] = $others;

        // The affiliate's own standing booking already uses 55 of their 70-seat block.
        $existingAffiliateBooking = (new CreateABooking())
            ->usePackage($base->packageId)
            ->useSlotTime($base->slotTimeId)
            ->useCustomer($affiliate->id)
            ->withTourists(55)
            ->create();
        static::$builders[] = $existingAffiliateBooking;

        $assignmentId = DB::table('package_affiliate_assignments')->insertGetId([
            'package_id' => $base->packageId,
            'affiliate_id' => $affiliate->id,
            'show_all_package_bookings' => 0,
            'created_at' => Carbon::now(),
            'created_by_user_type' => 'Admin',
        ]);

        $tstaId = DB::table('tour_slot_times_affiliate')->insertGetId([
            'tour_package_id' => $base->packageId,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'status' => 'Active',
            'created_at' => Carbon::now(),
            'created_by_user_type' => 'Tour Operator',
        ]);

        $astId = DB::table('affiliate_slot_times')->insertGetId([
            'tour_slot_time_id' => $tstaId,
            'affiliate_id' => $affiliate->id,
            'seat_limit' => 70,
            'created_at' => Carbon::now(),
            'created_by_user_type' => 'Tour Operator',
        ]);

        try {
            foreach ($this->repositories() as $label => $repo) {
                $request = new Request();
                $request->merge([
                    'slot_time' => $base->slotTimeId,
                    'package_id' => $base->packageId,
                ]);

                $result = $repo->checkOverbooking($request);

                $this->assertEquals(75, $result->seats, "[$label] tour-wide seat capacity");
                $this->assertEquals(67, $result->booked_seats, "[$label] tour-wide booked seats (12 others + 55 affiliate)");
                $this->assertEquals(70, $result->afl_seats, "[$label] affiliate's own dedicated seat-block allocation");
                $this->assertEquals(55, $result->afl_booked_seats, "[$label] affiliate's own booked seats on this slot");

                // This is the corrected math tour-booking-info.js now applies: the
                // affiliate can book min(own remaining, tour-wide remaining) more
                // seats -- NOT (own allocation clamped to tour-wide remaining) minus
                // own usage, which double-subtracted the affiliate's own usage and
                // is what wrongly blocked the See Canyon reschedule.
                $ownRemaining = $result->afl_seats - $result->afl_booked_seats; // 70 - 55 = 15
                $tourWideRemaining = $result->seats - $result->booked_seats;    // 75 - 67 = 8
                $correctlyAvailable = min($ownRemaining, $tourWideRemaining);   // 8

                $this->assertEquals(8, $correctlyAvailable, "[$label] correct affiliate availability");
                $this->assertGreaterThanOrEqual(
                    2,
                    $correctlyAvailable,
                    "[$label] a 2-person reschedule must be allowed: the affiliate's own block has 15 free and the tour has 8 physically free"
                );
            }
        } finally {
            DB::table('affiliate_slot_times')->where('id', $astId)->delete();
            DB::table('tour_slot_times_affiliate')->where('id', $tstaId)->delete();
            DB::table('package_affiliate_assignments')->where('id', $assignmentId)->delete();
            Session::forget('guard');
        }
    }

    /**
     * An affiliate assigned via show_all_package_bookings = 1 shares the whole
     * tour's pool and never gets tour_slot_times_affiliate / affiliate_slot_times
     * rows configured -- there's nothing to limit. checkOverbooking() must report
     * their available seats as the tour's real remaining capacity, not a hardcoded
     * 0 (which made every pooled affiliate look permanently sold out).
     */
    public function testCheckOverbookingPooledAffiliateGetsTourWideCapacityNotZero(): void
    {
        $tourOperatorId = DB::table('tour_operators')->value('id');
        $this->assertNotNull($tourOperatorId, 'At least one tour operator must exist');

        $date = Carbon::today()->addDays(31)->toDateString();
        $seats = 20;

        $base = (new CreateABooking())
            ->withNewPackage()
            ->withDate($date)
            ->withTimeSlot('10:00:00', '11:00:00')
            ->withSeats($seats)
            ->withTourists(0)
            ->create();
        static::$builders[] = $base;

        $affiliate = $this->authAsAffiliate($tourOperatorId);

        $others = (new CreateABooking())
            ->usePackage($base->packageId)
            ->useSlotTime($base->slotTimeId)
            ->withTourists(5)
            ->create();
        static::$builders[] = $others;

        $assignmentId = DB::table('package_affiliate_assignments')->insertGetId([
            'package_id' => $base->packageId,
            'affiliate_id' => $affiliate->id,
            'show_all_package_bookings' => 1,
            'created_at' => Carbon::now(),
            'created_by_user_type' => 'Admin',
        ]);

        // Deliberately no tour_slot_times_affiliate / affiliate_slot_times rows --
        // that's the real-world state for a show-all-bookings affiliate.

        try {
            foreach ($this->repositories() as $label => $repo) {
                $request = new Request();
                $request->merge([
                    'slot_time' => $base->slotTimeId,
                    'package_id' => $base->packageId,
                ]);

                $result = $repo->checkOverbooking($request);

                $this->assertEquals(20, $result->seats, "[$label] tour-wide seat capacity");
                $this->assertEquals(5, $result->booked_seats, "[$label] tour-wide booked seats");
                $this->assertEquals(
                    20,
                    $result->afl_seats,
                    "[$label] a pooled affiliate's afl_seats must equal the tour's total capacity, not 0"
                );
                $this->assertEquals(
                    5,
                    $result->afl_booked_seats,
                    "[$label] pooled affiliate's afl_booked_seats mirrors tour-wide booked seats"
                );
            }
        } finally {
            DB::table('package_affiliate_assignments')->where('id', $assignmentId)->delete();
            Session::forget('guard');
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        static::$builders = [];
    }
}
