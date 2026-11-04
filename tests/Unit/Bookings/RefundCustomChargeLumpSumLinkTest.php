<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBookingPaymentLink;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Regression: a custom charge added at BOOKING-CREATION time (bundled into that booking's
 * original lump-sum Payment Link via PaymentLinkService::storeTourBookingPaymentLink() --
 * section=NULL, no itemized TourBookingPaymentLinkItem row) must still correctly shrink and
 * regenerate that link when the admin later removes the charge from the Booking Details page.
 *
 * Before this fix: refundCustomCharge() only knew how to cancel an ITEMIZED payment-link item
 * (section='remaining_payment', created by a later edit) -- for a charge with no such item, it
 * silently returned "Pending payment link has been cancelled and expired." without ever touching
 * the actual, still-stale TourBookingPaymentLink row. The customer would still be asked to pay
 * the old, too-high amount.
 *
 * CMD: php artisan test --filter=RefundCustomChargeLumpSumLinkTest
 */
class RefundCustomChargeLumpSumLinkTest extends TestCase
{
    protected TourBookingRepository $repo;
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourBookingRepository::class);
        Queue::fake();
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_removing_a_non_itemized_custom_charge_shrinks_and_regenerates_the_lump_sum_link(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        // The custom charge added inline while creating the booking -- Open (unpaid), no
        // transaction_id, never given its own TourBookingPaymentLinkItem row.
        $chargeId = DB::table('tour_booking_custom_charges')->insertGetId([
            'tour_booking_id' => $builder->bookingId,
            'parent_id'       => null,
            'amount'          => 20.00,
            'fees'            => 1.96,
            'taxable'         => 0,
            'description'     => 'Test custom charge added at booking creation',
            'payment_method'  => 'Pay Later',
            'status'          => 'Open',
            'operation_by'    => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // The ORIGINAL lump-sum link storeTourBookingPaymentLink() creates: section=NULL, whole
        // booking amount (here: some ticket-cost stand-in + this $21.96 custom charge), no items.
        $originalAmount = 100.00;
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'             => $groupId,
            'section'              => null,
            'amount'               => $originalAmount,
            'reason_of_payment'    => 'Booking payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        try {
            $request = new Request();
            $request->merge([
                'custom_charge_ids' => json_encode([$chargeId]),
                'refund_type'       => 'dont_refund',
            ]);

            $response = $this->repo->refundCustomCharge($request, $builder->bookingId);
            $this->assertEquals(200, $response->getStatusCode());

            // The charge itself is cancelled.
            $this->assertEquals('Cancelled', DB::table('tour_booking_custom_charges')->find($chargeId)->status);

            // The OLD lump-sum link must be expired, not left dangling as the live Unpaid link.
            $this->assertEquals('Expired', DB::table('tour_booking_payment_links')->find($linkId)->status);

            // A NEW Unpaid link for the same order must exist, correctly shrunk by the removed
            // charge's amount + fees ($20.00 + $1.96 = $21.96).
            $newLink = TourBookingPaymentLink::where('order_id', $groupId)->where('status', 'Unpaid')->first();
            $this->assertNotNull($newLink, 'A regenerated Unpaid link must exist for the customer to still pay the correct remaining amount');
            $this->assertEqualsWithDelta(
                $originalAmount - 21.96,
                (float) $newLink->amount,
                0.001,
                'The regenerated link must be shrunk by exactly the removed charge\'s amount + fees'
            );
        } finally {
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_custom_charges')->where('id', $chargeId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->delete();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        static::$builders = [];
        parent::tearDownAfterClass();
    }
}
