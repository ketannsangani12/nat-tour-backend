<?php

namespace Tests\Unit\Bookings;

use App\Events\BookingNew;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): booking with 2 unpaid pax (e.g. "Children Ages 4-7") sharing
 * ONE pending remaining_payment link item. Splitting off only ONE of them into a new booking must
 * PARTIALLY reduce that item (so the pax staying behind on the old booking keeps a correct, smaller
 * pending link) — not cancel it outright, which silently wipes out the still-unpaid pax's payment
 * link entirely.
 *
 * Root cause: tour-booking-info.blade.php's splitPeopleBooking() JS only ever pushed the bare
 * `pending-item-id` for each checked pax (`pendingItemIds.push(pendingItemId)`), never the precise
 * "itemId:transactId:count" format that PaymentLinkService::cancelPendingChangePeopleItems() needs
 * to tell a partial removal from a full one — a bare "itemId" entry has exactly 1 ':'-separated
 * part, which that parser treats as the LEGACY full-delete format (see its own
 * `count($parts) === 1` branch), regardless of how many pax the item actually still covers. The
 * sibling "Delete Pax" flow (editBookingDeletePeople()) already built this correctly; the split
 * flow just never followed the same pattern.
 *
 * This test exercises attachUnpaidPaxToSplitBooking() (via Reflection, like its sibling tests in
 * SplitUnpaidPaxTransactDataTest) directly with both payload shapes to characterize the bug and
 * lock in the fix.
 */
class SplitBookingPendingItemPartialReductionTest extends TestCase
{
    protected static array $builders = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([BookingNew::class]);
        Queue::fake();
    }

    private function makeSharedPendingItem(CreateABooking $oldBooking, int $groupId): array
    {
        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'                => 100.00,
            'reason_of_payment'    => 'Payment for your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        // Both unpaid children (added_count=2) sharing this ONE item, on the SAME transact row.
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'change_people',
            'reference_id'         => null,
            'amount'               => 100.00,
            'status'               => 'Unpaid',
            'details'              => [
                'tour_booking_id'     => $oldBooking->bookingId,
                'tourists'            => 2,
                'ticket_cost'         => 100.00,
                'platform_fees'       => 0,
                'added_transact_data' => [
                    ['transact_id' => $oldBooking->transactId, 'added_count' => 2],
                ],
            ],
            'created_by'           => 1, 'created_by_user_type' => 'Admin',
            'updated_by'           => 1, 'updated_by_user_type' => 'Admin',
        ]);

        return [$link, $item];
    }

    private function makeStandInNewBooking(CreateABooking $oldBooking): CreateABooking
    {
        $newBooking = (new CreateABooking())
            ->usePackage($oldBooking->packageId)
            ->useRate($oldBooking->rateId)
            ->useSlotTime($oldBooking->slotTimeId)
            ->useCustomer($oldBooking->customerId)
            ->withTourists(1)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $newBooking;
        DB::table('tour_bookings_master')->where('id', $newBooking->bookingId)->update(['group_id' => $newBooking->bookingId]);

        return $newBooking;
    }

    /** @test */
    public function old_bare_item_id_format_wrongly_cancels_the_whole_shared_item(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $oldBooking = (new CreateABooking())->withTourists(2)->withRate(50.00)->withPaymentMethod('Card')->create();
        static::$builders[] = $oldBooking;
        $groupId = $oldBooking->bookingId;
        DB::table('tour_bookings_master')->where('id', $oldBooking->bookingId)->update(['group_id' => $groupId]);

        [$link, $item] = $this->makeSharedPendingItem($oldBooking, $groupId);
        $newBooking = $this->makeStandInNewBooking($oldBooking);

        $repo = app(TourBookingRepository::class);
        $method = new ReflectionMethod($repo, 'attachUnpaidPaxToSplitBooking');
        $method->setAccessible(true);
        $method->invoke(
            $repo,
            $newBooking->bookingId,
            $oldBooking->bookingId,
            [$oldBooking->rateId => 1], // only 1 of the 2 unpaid pax is being split off
            ["{$item->id}"],            // BUG: bare item id, no transactId:count
            0
        );

        $this->assertEquals(
            'Cancelled',
            TourBookingPaymentLinkItem::find($item->id)->status,
            'Reproduces the bug: the bare item-id format is parsed as a full delete, wiping out the pending link for the pax who did NOT move'
        );

        DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
        DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
    }

    /** @test */
    public function fixed_precise_format_partially_reduces_the_shared_item(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $oldBooking = (new CreateABooking())->withTourists(2)->withRate(50.00)->withPaymentMethod('Card')->create();
        static::$builders[] = $oldBooking;
        $groupId = $oldBooking->bookingId;
        DB::table('tour_bookings_master')->where('id', $oldBooking->bookingId)->update(['group_id' => $groupId]);

        [$link, $item] = $this->makeSharedPendingItem($oldBooking, $groupId);
        $newBooking = $this->makeStandInNewBooking($oldBooking);

        $repo = app(TourBookingRepository::class);
        $method = new ReflectionMethod($repo, 'attachUnpaidPaxToSplitBooking');
        $method->setAccessible(true);
        $method->invoke(
            $repo,
            $newBooking->bookingId,
            $oldBooking->bookingId,
            [$oldBooking->rateId => 1], // only 1 of the 2 unpaid pax is being split off
            ["{$item->id}:{$oldBooking->transactId}:1"], // FIXED: precise itemId:transactId:count
            0
        );

        $refreshed = TourBookingPaymentLinkItem::find($item->id);
        $this->assertEquals(
            'Unpaid',
            $refreshed->status,
            'The pax staying behind on the old booking must keep a live pending link, not have it cancelled'
        );
        $this->assertEqualsWithDelta(50.00, (float) $refreshed->amount, 0.01, 'The item must be halved, not zeroed out');

        $details = is_array($refreshed->details) ? $refreshed->details : json_decode($refreshed->details ?? '[]', true);
        $this->assertCount(1, $details['added_transact_data']);
        $this->assertEquals(1, $details['added_transact_data'][0]['added_count'], 'Exactly 1 remaining unpaid pax must still be tracked on the old booking\'s item');

        // The new booking must ALSO get its own correct pending link for the split-off pax.
        $newBookingFresh = TourBooking::find($newBooking->bookingId);
        $newLink = TourBookingPaymentLink::where('order_id', $newBookingFresh->group_id)
            ->where('section', 'remaining_payment')->where('status', 'Unpaid')->first();
        $this->assertNotNull($newLink, 'A remaining_payment link must be created for the split-off pax on the new booking');

        DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
        DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
        DB::table('tour_booking_payment_link_items')->where('payment_link_id', $newLink->id)->delete();
        DB::table('tour_booking_payment_links')->where('id', $newLink->id)->delete();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        parent::tearDownAfterClass();
    }
}
