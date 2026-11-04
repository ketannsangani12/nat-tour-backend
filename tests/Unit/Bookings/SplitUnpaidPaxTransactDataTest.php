<?php

namespace Tests\Unit\Bookings;

use App\Events\BookingNew;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: splitting off unpaid pax into a new booking must carry a resolvable
 * added_transact_data mapping on the new booking's remaining_payment link item — not the
 * hardcoded `[]` it used to ship. Every downstream unpaid-pax lookup
 * (PaymentLinkService::unpaidCountByRateGroupFromFormIds(), cancelPendingChangePeopleItems(),
 * UnpaidPaxService, the booking-detail $pendingPaxInfo builder, etc.) keys off
 * added_transact_data[].transact_id — an empty array makes them silently treat the split-off
 * booking's unpaid pax as if they don't exist.
 *
 * CMD: php artisan test --filter=SplitUnpaidPaxTransactDataTest
 */
class SplitUnpaidPaxTransactDataTest extends TestCase
{
    protected static array $builders = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Scoped to just the broadcast event — a blanket Event::fake() would also silence
        // TourBookingObserver::created() (it runs on the same 'eloquent.created' event), which is
        // what assigns group_id to the new split booking created via TourBooking::create().
        Event::fake([BookingNew::class]);
        Queue::fake();
    }

    /**
     * @test
     * CMD: php artisan test --filter=splitPeopleBooking_populatesAddedTransactDataForAllUnpaidSplit
     */
    public function splitPeopleBooking_populatesAddedTransactDataForAllUnpaidSplit(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $builder = (new CreateABooking())
            ->withTourists(3)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        $request = new Request();
        $request->merge([
            'bookingID'                 => $builder->bookingId,
            'check'                     => ['0.0' => $builder->rateId],
            'permitF'                   => [],
            'split_is_all_unpaid'       => '1',
            'split_pending_item_ids'    => '',
            'split_unpaid_check_counts' => '',
            'tourits_numbers'           => '',
        ]);

        $repo   = app(TourBookingRepository::class);
        $result = $repo->splitPeopleBooking($request);

        $this->assertNotEmpty($result['bookingId'] ?? null, 'A new booking must be created for the split-off pax');
        $newBookingId = (int) $result['bookingId'];

        $newTransactRow = DB::table('tour_bookings_transact')
            ->where('tour_booking_id', $newBookingId)
            ->where('tour_package_rate_id', $builder->rateId)
            ->first();
        $this->assertNotNull($newTransactRow, 'The new booking must have its own transact row for the split pax');

        $newBooking = TourBooking::find($newBookingId);
        $link = TourBookingPaymentLink::where('order_id', $newBooking->group_id)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        $this->assertNotNull($link, 'A remaining_payment link must be created for the unpaid split-off pax');

        $item = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'change_people')
            ->first();
        $this->assertNotNull($item);

        $details = is_array($item->details) ? $item->details : json_decode($item->details ?? '[]', true);

        $this->assertNotEmpty(
            $details['added_transact_data'] ?? [],
            'added_transact_data must not be empty — it must map to the new booking\'s own transact row'
        );
        $this->assertEquals($newTransactRow->id, $details['added_transact_data'][0]['transact_id']);
        $this->assertEquals(1, $details['added_transact_data'][0]['added_count']);

        DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
        DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
        DB::table('tour_bookings_transact')->where('tour_booking_id', $newBookingId)->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $newBookingId)->delete();
        DB::table('tour_bookings_master')->where('id', $newBookingId)->delete();
    }

    /**
     * Mixed (paid + unpaid) split: attachUnpaidPaxToSplitBooking() moves the unpaid pax onto a
     * new booking that the Extend*Service already created for the paid pax. Invoked directly via
     * Reflection (it's private) so this test doesn't need a real Stripe charge through
     * ExtendBookingCardService — the new booking here just stands in for whatever that service
     * would have created.
     *
     * @test
     * CMD: php artisan test --filter=attachUnpaidPaxToSplitBooking_populatesAddedTransactData
     */
    public function attachUnpaidPaxToSplitBooking_populatesAddedTransactData(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $oldBooking = (new CreateABooking())
            ->withTourists(3)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $oldBooking;

        // Stands in for the booking ExtendBookingCardService would have created for the paid pax.
        $newBooking = (new CreateABooking())
            ->usePackage($oldBooking->packageId)
            ->useRate($oldBooking->rateId)
            ->useSlotTime($oldBooking->slotTimeId)
            ->useCustomer($oldBooking->customerId)
            ->withTourists(2)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $newBooking;
        DB::table('tour_bookings_master')->where('id', $newBooking->bookingId)->update(['group_id' => $newBooking->bookingId]);

        $repo = app(TourBookingRepository::class);
        $method = new ReflectionMethod($repo, 'attachUnpaidPaxToSplitBooking');
        $method->setAccessible(true);
        $method->invoke(
            $repo,
            $newBooking->bookingId,
            $oldBooking->bookingId,
            [$oldBooking->rateId => 1], // 1 unpaid pax to move
            [],
            0
        );

        // attachUnpaidPaxToSplitBooking() inserts a SEPARATE transact row for the moved unpaid
        // pax rather than merging into the fixture's own (paid-pax) row for the same rate group —
        // it's the newest one, with tourists=1.
        $newTransactRow = DB::table('tour_bookings_transact')
            ->where('tour_booking_id', $newBooking->bookingId)
            ->where('tour_package_rate_id', $oldBooking->rateId)
            ->where('tourists', 1)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($newTransactRow, 'A new transact row for the moved unpaid pax must exist');

        $link = TourBookingPaymentLink::where('order_id', $newBooking->bookingId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        $this->assertNotNull($link, 'A remaining_payment link must be created for the unpaid split-off pax');

        $item = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'change_people')
            ->first();
        $this->assertNotNull($item);

        $details = is_array($item->details) ? $item->details : json_decode($item->details ?? '[]', true);
        $this->assertNotEmpty(
            $details['added_transact_data'] ?? [],
            'added_transact_data must not be empty — it must map to the moved pax\'s own new transact row'
        );
        $this->assertEquals($newTransactRow->id, $details['added_transact_data'][0]['transact_id']);
        $this->assertEquals(1, $details['added_transact_data'][0]['added_count']);

        DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
        DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
    }

    /**
     * Regression: when the pax being split off (mixed paid+unpaid split) has their own add-on,
     * that add-on must move to the new booking along with them — not stay orphaned on the old
     * booking. attachUnpaidPaxToSplitBooking() previously had no add-on-moving step at all (unlike
     * splitUnpaidPaxBooking(), the all-unpaid sibling, which already called
     * AddonBookingService::splitBookingPaxAddons()).
     *
     * @test
     * CMD: php artisan test --filter=attachUnpaidPaxToSplitBooking_movesUnpaidPaxOwnAddon
     */
    public function attachUnpaidPaxToSplitBooking_movesUnpaidPaxOwnAddon(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $oldBooking = (new CreateABooking())
            ->withTourists(3)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $oldBooking;

        // The 3rd person (0-based ordinal 2) is the unpaid one being split off, with their own add-on.
        $addonField = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId,
            'form_id'         => 1,
            'field_id'        => 1,
            'pax_id'          => $oldBooking->transactId,
            'field_label'     => 'Boxed Trail Lunch',
            'pax_index'       => 'unpaid-split-pax-2',
            'person_ordinal'  => 2,
            'unit_type'       => 'price_per_pax',
            'unit'            => 1,
        ]);

        $newBooking = (new CreateABooking())
            ->usePackage($oldBooking->packageId)
            ->useRate($oldBooking->rateId)
            ->useSlotTime($oldBooking->slotTimeId)
            ->useCustomer($oldBooking->customerId)
            ->withTourists(2)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $newBooking;
        DB::table('tour_bookings_master')->where('id', $newBooking->bookingId)->update(['group_id' => $newBooking->bookingId]);

        try {
            $repo = app(TourBookingRepository::class);
            $method = new ReflectionMethod($repo, 'attachUnpaidPaxToSplitBooking');
            $method->setAccessible(true);
            $method->invoke(
                $repo,
                $newBooking->bookingId,
                $oldBooking->bookingId,
                [$oldBooking->rateId => 1],
                [],
                0,
                ['2.2' => $oldBooking->rateId] // unpaidCheck: ordinal 2 selected for rate $oldBooking->rateId
            );

            $newTransactRow = DB::table('tour_bookings_transact')
                ->where('tour_booking_id', $newBooking->bookingId)
                ->where('tour_package_rate_id', $oldBooking->rateId)
                ->where('tourists', 1)
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($newTransactRow);

            $movedField = TourBookingAddonField::find($addonField->id);
            $this->assertEquals($newBooking->bookingId, $movedField->tour_booking_id, 'The add-on must move to the new booking');
            $this->assertEquals($newTransactRow->id, $movedField->pax_id, 'The add-on must re-point to the pax\'s new transact row');

            $link = TourBookingPaymentLink::where('order_id', $newBooking->bookingId)
                ->where('section', 'remaining_payment')->where('status', 'Unpaid')->first();
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
        } finally {
            TourBookingAddonField::withTrashed()->where('id', $addonField->id)->forceDelete();
        }
    }

    /**
     * Regression: an add-on has its OWN separate remaining_payment link item (type='addon',
     * created via the dedicated "add-on payment link" flow — see
     * TourBookingAddonRepository::sendAddonPaymentLink()-style code), distinct from the pax's own
     * type='change_people' item. Splitting off the pax must cancel that addon item on the OLD
     * booking's link and create a matching one on the NEW booking's link — otherwise
     * splitBookingPaxAddons() moves the add-on FIELD, but the money still owed for it stays stuck
     * on the old booking, exactly the real-world bug reported (the field moves, but its unpaid
     * charge doesn't, so paying the new booking's link never collects for the add-on).
     *
     * @test
     * CMD: php artisan test --filter=splitPeopleBooking_movesAddonPaymentLinkItemForUnpaidSplitPax
     */
    public function splitPeopleBooking_movesAddonPaymentLinkItemForUnpaidSplitPax(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $builder = (new CreateABooking())
            ->withTourists(2)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        // The 2nd pax (0-based ordinal 1) is the one being split off, with a real ($16) add-on.
        $addonField = TourBookingAddonField::create([
            'tour_booking_id' => $builder->bookingId,
            'form_id'         => 1,
            'field_id'        => 34,
            'pax_id'          => $builder->transactId,
            'field_label'     => 'Boxed Trail Lunch',
            'pax_index'       => 'addon-payment-link-test-1',
            'person_ordinal'  => 1,
            'unit_type'       => 'price_per_pax',
            'unit'            => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 17.57,
            'reason_of_payment'    => 'Payment for add-ons on your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);
        $addonItem = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'addon',
            'reference_id'         => null,
            'amount'               => 17.57,
            'status'               => 'Unpaid',
            'details'              => [
                'field_to_db' => ['34_addon-payment-link-test-1' => $addonField->id],
                // Matches the real shape movePendingAddonPaymentLinkItems() actually keys off:
                // financial.to_charge[].old_db_id, not the (potentially multi-pax, incomplete)
                // field_to_db map alone.
                'financial'   => [
                    'to_charge' => [
                        ['old_db_id' => $addonField->id, 'field_id' => 34, 'pax_index' => 'addon-payment-link-test-1'],
                    ],
                ],
                'booking_id'  => $builder->bookingId,
            ],
            'created_by'           => 1, 'created_by_user_type' => 'Admin',
            'updated_by'           => 1, 'updated_by_user_type' => 'Admin',
        ]);

        $newBookingId = null;
        try {
            $request = new Request();
            $request->merge([
                'bookingID'                 => $builder->bookingId,
                'check'                     => ['0.1' => $builder->rateId], // split off ordinal 1
                'permitF'                   => [],
                'split_is_all_unpaid'       => '1',
                'split_pending_item_ids'    => '',
                'split_unpaid_check_counts' => '',
                'tourits_numbers'           => '',
            ]);

            $repo   = app(TourBookingRepository::class);
            $result = $repo->splitPeopleBooking($request);
            $newBookingId = (int) $result['bookingId'];

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($addonItem->id)->status,
                'The addon item must be cancelled on the OLD booking\'s link');

            $newBooking = TourBooking::find($newBookingId);
            $newLink = TourBookingPaymentLink::where('order_id', $newBooking->group_id)
                ->where('section', 'remaining_payment')->where('status', 'Unpaid')->first();
            $this->assertNotNull($newLink, 'A remaining_payment link must exist for the new booking');

            $newAddonItem = TourBookingPaymentLinkItem::where('payment_link_id', $newLink->id)
                ->where('type', 'addon')->first();
            $this->assertNotNull($newAddonItem, 'A new addon-type item must be created on the new booking\'s link');
            $this->assertEquals(17.57, (float) $newAddonItem->amount);
            $this->assertEquals('Unpaid', $newAddonItem->status);

            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $newLink->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $newLink->id)->delete();
        } finally {
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('id', $addonField->id)->forceDelete();
            $this->deleteSplitOffBooking($newBookingId, $groupId);
        }
    }

    /**
     * Regression (real-world report): a request that combines "add pax B's own $0 add-on
     * selection" with "edit pax A's EXISTING add-on to a paid one" in one go leaves
     * details['field_to_db'] holding ONLY pax B's field (upsertBookingAddonFields() only ever
     * populated field_to_db for newly-ADDED fields, never for edits to existing ones) — even
     * though the item's actual charge is 100% pax A's. Splitting off pax B must NOT move this
     * item: the money belongs to pax A, who isn't moving. Matching must key off
     * financial.to_charge/to_refund (the item's real line items), not the field_to_db map alone.
     *
     * @test
     * CMD: php artisan test --filter=splitPeopleBooking_doesNotMoveAddonPaymentLinkItemBelongingToAnUnrelatedPax
     */
    public function splitPeopleBooking_doesNotMoveAddonPaymentLinkItemBelongingToAnUnrelatedPax(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $builder = (new CreateABooking())
            ->withTourists(2)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        // Pax A (ordinal 0) — NOT being split — has the actual paid add-on.
        $addonFieldA = TourBookingAddonField::create([
            'tour_booking_id' => $builder->bookingId,
            'form_id'         => 1,
            'field_id'        => 34,
            'pax_id'          => $builder->transactId,
            'field_label'     => 'Boxed Trail Lunch',
            'pax_index'       => 'unrelated-pax-test-0',
            'person_ordinal'  => 0,
            'unit_type'       => 'price_per_pax',
            'unit'            => 1,
        ]);
        // Pax B (ordinal 1) — the pax being split off — has a $0 "No lunch needed" add-on.
        $addonFieldB = TourBookingAddonField::create([
            'tour_booking_id' => $builder->bookingId,
            'form_id'         => 1,
            'field_id'        => 34,
            'pax_id'          => $builder->transactId,
            'field_label'     => 'Boxed Trail Lunch',
            'pax_index'       => 'unrelated-pax-test-1',
            'person_ordinal'  => 1,
            'unit_type'       => 'price_per_pax',
            'unit'            => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 17.57,
            'reason_of_payment'    => 'Payment for add-ons on your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);
        $addonItem = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'addon',
            'reference_id'         => null,
            'amount'               => 17.57,
            'status'               => 'Unpaid',
            'details'              => [
                // The incomplete/misleading map: only pax B's field is present (simulating the
                // pre-fix upsertBookingAddonFields() bug), even though this charge is pax A's.
                'field_to_db' => ['34_unrelated-pax-test-1' => $addonFieldB->id],
                'financial'   => [
                    'to_charge' => [
                        ['old_db_id' => $addonFieldA->id, 'field_id' => 34, 'pax_index' => 'unrelated-pax-test-0'],
                    ],
                ],
                'booking_id'  => $builder->bookingId,
            ],
            'created_by'           => 1, 'created_by_user_type' => 'Admin',
            'updated_by'           => 1, 'updated_by_user_type' => 'Admin',
        ]);

        $newBookingId = null;
        try {
            $request = new Request();
            $request->merge([
                'bookingID'                 => $builder->bookingId,
                'check'                     => ['0.1' => $builder->rateId], // split off ordinal 1 (pax B)
                'permitF'                   => [],
                'split_is_all_unpaid'       => '1',
                'split_pending_item_ids'    => '',
                'split_unpaid_check_counts' => '',
                'tourits_numbers'           => '',
            ]);

            $repo   = app(TourBookingRepository::class);
            $result = $repo->splitPeopleBooking($request);
            $newBookingId = (int) $result['bookingId'];

            $this->assertEquals('Unpaid', TourBookingPaymentLinkItem::find($addonItem->id)->status,
                'Pax A\'s addon item must stay Unpaid on the OLD booking\'s link — it has nothing to do with pax B\'s split');

            $newBooking = TourBooking::find($newBookingId);
            $newLink = TourBookingPaymentLink::where('order_id', $newBooking->group_id)
                ->where('section', 'remaining_payment')->where('status', 'Unpaid')->first();

            if ($newLink) {
                $newAddonItem = TourBookingPaymentLinkItem::where('payment_link_id', $newLink->id)
                    ->where('type', 'addon')->first();
                $this->assertNull($newAddonItem, 'No addon-type item must be created on the new booking\'s link — pax A\'s add-on never moved');
                DB::table('tour_booking_payment_link_items')->where('payment_link_id', $newLink->id)->delete();
                DB::table('tour_booking_payment_links')->where('id', $newLink->id)->delete();
            }
        } finally {
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$addonFieldA->id, $addonFieldB->id])->forceDelete();
            $this->deleteSplitOffBooking($newBookingId, $groupId);
        }
    }

    // ---------------------------------------------------------------
    // Teardown
    // ---------------------------------------------------------------

    /**
     * Delete the booking a split created (the fixture only knows its own booking), plus its
     * payment links when they are its own (not the original booking's group).
     */
    private function deleteSplitOffBooking(?int $newBookingId, int $originalGroupId): void
    {
        if (!$newBookingId) {
            return;
        }

        $newGroupId = (int) DB::table('tour_bookings_master')->where('id', $newBookingId)->value('group_id');
        if ($newGroupId && $newGroupId !== $originalGroupId) {
            $linkIds = DB::table('tour_booking_payment_links')->where('order_id', $newGroupId)->pluck('id');
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $linkIds)->delete();
            DB::table('tour_booking_payment_links')->whereIn('id', $linkIds)->delete();
        }
        DB::table('transactions')->where('tour_booking_id', $newBookingId)->delete();
        DB::table('tour_bookings_transact')->where('tour_booking_id', $newBookingId)->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $newBookingId)->delete();
        DB::table('tour_bookings_master')->where('id', $newBookingId)->delete();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        parent::tearDownAfterClass();
    }
}
