<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Models\TourBookingPaymentLink;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): deleting pax from a never-paid Group-rate booking that has an
 * Unpaid booking Payment Link crashed with "Undefined variable $tourBookingTotalForPayLater".
 * editBooking() hands group-rate deletes to EditBookingService::deletePaxFromGroupBooking(),
 * skipping the single-booking branch that initialises that variable, but then still falls through
 * to the shared "Payment link query" block that reads it to shrink the link.
 */
class GroupRateDeletePaxPayLaterPaymentLinkTest extends TestCase
{
    public function test_deleting_pax_from_pay_later_group_booking_shrinks_payment_link_instead_of_crashing(): void
    {
        Mail::fake();
        $this->actingAs(Admin::first() ?? Admin::factory()->create(), 'admin');

        // 6 pax on a "4-6 Person Group" tier priced $300 for the whole group (no NAT fee).
        $booking = (new CreateABooking())
            ->withNewPackage()
            ->withTourists(6)
            ->withRate(50.00)
            ->withPaymentMethod('Pay Later')
            ->withoutTransaction()
            ->create();
        $bookingId = $booking->bookingId;

        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['is_group_booking' => 1, 'group_id' => $bookingId]);
        DB::table('tour_package_rates')->where('id', $booking->rateId)->update(['rate' => 300.00, 'min_group_size' => 4, 'max_group_size' => 6]);
        DB::table('tour_bookings_transact')->where('id', $booking->transactId)->update(['rate' => 300.00]);

        // Removing 3 pax drops the group into the "1-3 Person Group" tier priced $200.
        $smallTierRateId = DB::table('tour_package_rates')->insertGetId([
            'tour_package_id' => $booking->packageId,
            'rate_for' => 'Adult',
            'age_from' => 18,
            'age_to' => 64,
            'rate' => 200.00,
            'primary_rate' => 200.00,
            'permit_fee' => 0,
            'min_group_size' => 1,
            'max_group_size' => 3,
            'scheduling_year' => (int) DB::table('tour_package_rates')->where('id', $booking->rateId)->value('scheduling_year'),
            'status' => 'Active',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => null, 'amount' => 300.00,
            'reason_of_payment' => 'A payment link has been generated for Test Package, with a total amount of $300.00.',
            'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            $request = new Request();
            $request->merge([
                'bookingID' => $bookingId,
                'action_type' => 'delete',
                'payment_type' => 'Pay Later',
                'is_cash' => '',
                'refund_Type' => '100_refund',
                'check' => ['Adult.0' => $booking->rateId, 'Adult.1' => $booking->rateId, 'Adult.2' => $booking->rateId],
                'total_tourist' => 6,
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->editBooking($request);

            $this->assertEquals(3, DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'));
            $this->assertEquals('Expired', TourBookingPaymentLink::find($link->id)->status, 'The stale $300 link must be superseded');

            $updatedLink = TourBookingPaymentLink::where('order_id', $bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($updatedLink, 'A fresh Unpaid link reflecting the removal must be created');
            $this->assertEqualsWithDelta(200.00, (float) $updatedLink->amount, 0.01, 'The link must shrink by the $100 group tier price drop');
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $bookingId)->delete();
            // The delete moved the transact row onto the small tier — point it back so the
            // extra rate can be removed before the fixture cleans up its own rate/package.
            DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->update(['tour_package_rate_id' => $booking->rateId]);
            DB::table('tour_package_rates')->where('id', $smallTierRateId)->delete();
            $booking->cleanup();
        }
    }
}
