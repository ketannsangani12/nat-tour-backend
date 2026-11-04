<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Regression: tour_bookings_master.booking_total is incremented the moment a Payment-Link
 * 'change_people' pending item is created (changePeopleBooking()'s real-settlement branch bumps
 * total AND booking_total together, regardless of whether the pax is actually paid yet) — but
 * PaymentLinkService::cancelPendingChangePeopleItems() only ever decremented total back,
 * leaving booking_total permanently stale/overstated once the item was cancelled.
 *
 * Fix: cancelPendingChangePeopleItems() now also decrements booking_total, gated by its own
 * independent $skipBookingTotalUpdate flag (NOT the same flag as $skipTotalUpdate) — because
 * different call sites' own pre-update code handles total/booking_total differently: some
 * decrement both together already (skip both here), others only ever decrement total, by an old
 * — now corrected — assumption that booking_total is never bumped for an unpaid pax (skip only
 * total here, let this method correct booking_total).
 *
 * CMD: php artisan test --filter=CancelPendingChangePeopleItemsBookingTotalTest
 */
class CancelPendingChangePeopleItemsBookingTotalTest extends TestCase
{
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * @return array{link: TourBookingPaymentLink, item: TourBookingPaymentLinkItem}
     */
    private function createPendingChangePeopleItem(int $bookingId, string $groupId, float $amount, float $fees): array
    {
        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => $amount,
            'reason_of_payment'    => 'Additional payment for your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'change_people',
            'reference_id'         => null,
            'amount'               => $amount,
            'status'               => 'Unpaid',
            'details'              => json_encode([
                'tour_booking_id'     => $bookingId,
                'ticket_cost'         => round($amount - $fees, 2),
                'platform_fees'       => $fees,
                'added_transact_data' => [],
            ]),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        return ['link' => $link, 'item' => $item];
    }

    public function test_cancelling_a_pending_item_decrements_both_total_and_booking_total_by_default(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        // Simulate the state right after a Payment-Link pax addition: both total and
        // booking_total were bumped by the same item amount (confirmed convention at
        // TourBookingRepository.php's changePeopleBooking() real-settlement branch).
        $baseTotal = (float) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('total');
        $itemAmount = 80.50;
        $itemFees   = 7.20;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update([
            'total'              => $baseTotal + $itemAmount,
            'booking_total'      => $baseTotal + $itemAmount,
            'service_commission' => $itemFees,
        ]);

        $created = $this->createPendingChangePeopleItem($builder->bookingId, $groupId, $itemAmount, $itemFees);

        try {
            PaymentLinkService::cancelPendingChangePeopleItems((string) $created['item']->id, null);

            $fresh = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();
            $this->assertEqualsWithDelta($baseTotal, (float) $fresh->total, 0.001, 'total must be decremented back');
            $this->assertEqualsWithDelta($baseTotal, (float) $fresh->booking_total, 0.001, 'booking_total must ALSO be decremented back, not left stale');
            $this->assertEqualsWithDelta(0.0, (float) $fresh->service_commission, 0.001);

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($created['item']->id)->status);
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $created['link']->id)->delete();
            $created['link']->delete();
        }
    }

    public function test_skip_total_update_true_skips_total_but_still_corrects_booking_total(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        $baseTotal = (float) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('total');
        $itemAmount = 50.00;

        // A caller like deletePeopleChangePeople()'s no-transaction branch already decremented
        // `total` itself (so it passes skipTotalUpdate=true) but never touches booking_total —
        // simulate that: total already reflects the removal, booking_total still includes it.
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update([
            'total'         => $baseTotal,
            'booking_total' => $baseTotal + $itemAmount,
        ]);

        $created = $this->createPendingChangePeopleItem($builder->bookingId, $groupId, $itemAmount, 0.00);

        try {
            PaymentLinkService::cancelPendingChangePeopleItems((string) $created['item']->id, null, true, false);

            $fresh = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();
            $this->assertEqualsWithDelta($baseTotal, (float) $fresh->total, 0.001, 'total must be left untouched (caller already decremented it)');
            $this->assertEqualsWithDelta($baseTotal, (float) $fresh->booking_total, 0.001, 'booking_total must still be corrected');
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $created['link']->id)->delete();
            $created['link']->delete();
        }
    }

    public function test_skip_booking_total_update_true_skips_booking_total_but_still_decrements_total(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $groupId = (string) $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        $baseTotal = (float) DB::table('tour_bookings_master')->where('id', $builder->bookingId)->value('total');
        $itemAmount = 50.00;

        // A caller like attachUnpaidPaxToSplitBooking() already decremented BOTH total and
        // booking_total together in its own loop before calling this — simulate that: only
        // `total` still needs this method's decrement in this test scenario (booking_total is
        // already correct and must not be touched again).
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update([
            'total'         => $baseTotal + $itemAmount,
            'booking_total' => $baseTotal,
        ]);

        $created = $this->createPendingChangePeopleItem($builder->bookingId, $groupId, $itemAmount, 0.00);

        try {
            PaymentLinkService::cancelPendingChangePeopleItems((string) $created['item']->id, null, false, true);

            $fresh = DB::table('tour_bookings_master')->where('id', $builder->bookingId)->first();
            $this->assertEqualsWithDelta($baseTotal, (float) $fresh->total, 0.001, 'total must be decremented');
            $this->assertEqualsWithDelta($baseTotal, (float) $fresh->booking_total, 0.001, 'booking_total must be left untouched (caller already decremented it)');
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $created['link']->id)->delete();
            $created['link']->delete();
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
