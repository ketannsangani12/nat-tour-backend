<?php

namespace Tests\Feature;

use App\Jobs\Affiliate\CorrectAffiliateBooking;
use App\Jobs\Affiliate\CorrectAffiliateSoftBlockBooking;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * CorrectAffiliateBooking / CorrectAffiliateSoftBlockBooking re-apply a
 * corrected package rate onto an existing booking's tourists/total. Both
 * already skipped a booking with any transaction; now they also skip one
 * that's ever had a split or a pax removed (via the shared
 * hasSplitOrRemovedPax() helper in app/helpers.php), for the same reason
 * SoftBlockController's mass-update tool does -- overwriting it here would
 * corrupt that state just as blindly as a mass update would.
 *
 * Both jobs check the guard before doing any rate lookup at all, so these
 * tests use a minimal booking (no package-rate-matching setup needed) --
 * if the guard doesn't trip, nothing here would fully exercise the rest of
 * the job anyway.
 */
class CorrectAffiliateBookingSplitOrRemovedGuardTest extends TestCase
{
    public function test_correct_affiliate_soft_block_booking_skips_a_split_booking()
    {
        $this->runWithBooking(function ($booking) {
            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $booking->bookingId,
                'tourists' => 7,
                'operation' => 'Split Booking Original',
                'details' => json_encode(['extended_booking_id' => 999999]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            (new CorrectAffiliateSoftBlockBooking($booking->bookingId, ['spots' => 5, 'seat_preference' => 0]))->handle();

            $this->assertSame(10, (int) DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('tourists'));
        });
    }

    public function test_correct_affiliate_booking_skips_a_booking_with_pax_removed()
    {
        $this->runWithBooking(function ($booking) {
            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $booking->bookingId,
                'tourists' => 8,
                'operation' => 'Refund',
                'details' => json_encode(['amount' => 100, 'deleted' => 'Adult person 2']),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $originalTotal = DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('total');

            (new CorrectAffiliateBooking($booking->bookingId))->handle();

            $this->assertEquals($originalTotal, DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('total'));
        });
    }

    /**
     * Build a package + rate + affiliate customer + booking (10 seats, no
     * transaction), run $callback with it, and guarantee cleanup.
     */
    private function runWithBooking(callable $callback): void
    {
        $packageId = null;
        $rateId = null;
        $customerId = null;
        $booking = null;

        try {
            $packageId = DB::table('tour_packages')->insertGetId([
                'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
                'name' => 'Correct Affiliate Booking Test Package ' . uniqid(),
                'duration' => 2,
                'status' => 'Active',
                'type' => 'Regular',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $customerId = DB::table('customers')->insertGetId([
                'tour_operator_id' => DB::table('tour_operators')->value('id') ?? 1,
                'name' => 'Test Affiliate',
                'email' => 'test-affiliate-' . uniqid() . '@test.com',
                'phone_number' => '5550002222',
                'is_affiliate' => 'Yes',
                'status' => 'Active',
                'remember_token' => '',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $rateId = DB::table('tour_package_rates')->insertGetId([
                'tour_package_id' => $packageId,
                'affiliate_id' => $customerId,
                'rate_for' => 'Adult',
                'age_from' => 18,
                'age_to' => 64,
                'rate' => 50.00,
                'primary_rate' => 50.00,
                'permit_fee' => 0.00,
                'description' => '',
                'scheduling_year' => (int) now()->format('Y'),
                'status' => 'Active',
                'orderby' => 1,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $booking = (new CreateABooking())
                ->withTourists(10)
                ->withRate(50.00)
                ->withDate(now()->addDays(14)->toDateString())
                ->withTimeSlot('09:00:00', '11:00:00')
                ->usePackage($packageId)
                ->useRate($rateId)
                ->useCustomer($customerId)
                ->withBookingOverrides(['is_reserved_for_affiliate' => 0])
                ->withoutTransaction()
                ->create();

            $callback($booking);
        } finally {
            $booking?->cleanup();
            if ($rateId) {
                DB::table('tour_package_rates')->where('id', $rateId)->delete();
            }
            if ($customerId) {
                DB::table('customers')->where('id', $customerId)->delete();
            }
            if ($packageId) {
                DB::table('tour_packages')->where('id', $packageId)->delete();
            }
        }
    }
}
