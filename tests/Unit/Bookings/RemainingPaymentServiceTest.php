<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\RemainingPayment\RemainingPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Unit tests for RemainingPaymentService.
 *
 * Covers finalizeItems() and getPendingForBooking().
 *
 * CMD: php artisan test --filter=RemainingPaymentServiceTest
 */
class RemainingPaymentServiceTest extends TestCase
{
    protected static array $builders = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createRemainingLink(string $orderId, float $amount = 25.00): TourBookingPaymentLink
    {
        return TourBookingPaymentLink::create([
            'order_id'             => $orderId,
            'section'              => 'remaining_payment',
            'amount'               => $amount,
            'reason_of_payment'    => 'Test remaining payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);
    }

    private function createLinkItem(int $linkId, string $type = 'custom_charge', float $amount = 25.00, ?int $referenceId = null, array $details = []): TourBookingPaymentLinkItem
    {
        return TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $linkId,
            'type'                 => $type,
            'reference_id'         => $referenceId,
            'amount'               => $amount,
            'status'               => 'Unpaid',
            'details'              => !empty($details) ? json_encode($details) : null,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);
    }

    private function fakePaymentIntent(int $amountCents = 2500): object
    {
        return (object) [
            'id'      => 'pi_test_' . uniqid(),
            'amount'  => $amountCents,
            'charges' => (object) [
                'data' => [
                    (object) [
                        'application_fee'        => 'fee_test_' . uniqid(),
                        // Array (not stdClass) — matches Stripe's ArrayAccess-capable objects,
                        // which the handlers read via isset($cardInfo['card']['last4']).
                        'payment_method_details' => [
                            'card' => ['last4' => '4242', 'brand' => 'visa'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function cleanupLink(TourBookingPaymentLink $link): void
    {
        TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
        $link->delete();
    }

    /**
     * Ensure the booking has a group_id (CreateABooking does not set one by default).
     * We use the bookingId itself as a unique group_id for single-booking tests.
     */
    private function resolveGroupId(int $bookingId): string
    {
        $groupId = DB::table('tour_bookings_master')->where('id', $bookingId)->value('group_id');
        if (!$groupId) {
            $groupId = (string) $bookingId;
            DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        }
        return (string) $groupId;
    }

    // ---------------------------------------------------------------
    // finalizeItems — Link already Paid
    // ---------------------------------------------------------------

    /**
     * When the link is already Paid, finalizeItems must return immediately
     * without touching any items or running any DB writes.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_returnsEarlyWhenLinkAlreadyPaid
     */
    public function finalizeItems_returnsEarlyWhenLinkAlreadyPaid(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId);
        $link->status = 'Paid';
        $link->save();

        $item = $this->createLinkItem($link->id, 'custom_charge', 25.00);

        RemainingPaymentService::finalizeItems($link, $this->fakePaymentIntent(), $builder->bookingId);

        // Item must remain Unpaid — no processing occurred
        $this->assertEquals('Unpaid', TourBookingPaymentLinkItem::find($item->id)->status);
        $this->assertEquals('Paid', TourBookingPaymentLink::find($link->id)->status);

        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // finalizeItems — No unpaid items
    // ---------------------------------------------------------------

    /**
     * When the link has no Unpaid items (all Cancelled/Expired), finalizeItems
     * must still mark the link itself as Paid.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_marksLinkPaidWhenNoUnpaidItemsExist
     */
    public function finalizeItems_marksLinkPaidWhenNoUnpaidItemsExist(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId);
        $item = $this->createLinkItem($link->id, 'custom_charge', 25.00);
        TourBookingPaymentLinkItem::where('id', $item->id)->update(['status' => 'Cancelled']);

        RemainingPaymentService::finalizeItems($link, $this->fakePaymentIntent(), $builder->bookingId);

        $this->assertEquals('Paid',      TourBookingPaymentLink::find($link->id)->status);
        $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($item->id)->status);

        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // finalizeItems — Unknown item type (no handler)
    // ---------------------------------------------------------------

    /**
     * Items with an unknown type have no handler in the $handlers map.
     * finalizeItems must skip them (continue) but still mark the link Paid.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_skipsItemsWithUnknownType
     */
    public function finalizeItems_skipsItemsWithUnknownType(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId);
        $item = $this->createLinkItem($link->id, 'unknown_future_type', 25.00);

        RemainingPaymentService::finalizeItems($link, $this->fakePaymentIntent(), $builder->bookingId);

        // Link marked Paid; item stays Unpaid (skipped by continue)
        $this->assertEquals('Paid',   TourBookingPaymentLink::find($link->id)->status);
        $this->assertEquals('Unpaid', TourBookingPaymentLinkItem::find($item->id)->status);

        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // finalizeItems — Non-processable item (isProcessable returns false)
    // ---------------------------------------------------------------

    /**
     * CustomChargeItemHandler::isProcessable returns false when the referenced
     * custom_charge row does not exist (e.g. the reference_id points to a deleted charge).
     * finalizeItems must skip such items but still mark the link Paid.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_skipsNonProcessableItems
     */
    public function finalizeItems_skipsNonProcessableItems(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId);
        // reference_id=999999999 — no custom_charge row with this id exists
        $item = $this->createLinkItem($link->id, 'custom_charge', 25.00, 999999999);

        RemainingPaymentService::finalizeItems($link, $this->fakePaymentIntent(), $builder->bookingId);

        // isProcessable returned false → item stays Unpaid; link still marked Paid
        $this->assertEquals('Paid',   TourBookingPaymentLink::find($link->id)->status);
        $this->assertEquals('Unpaid', TourBookingPaymentLinkItem::find($item->id)->status);

        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // finalizeItems — Cancelled items excluded from Unpaid query
    // ---------------------------------------------------------------

    /**
     * Cancelled items are not returned by the Unpaid query inside finalizeItems.
     * They must be completely untouched while any Unpaid item with a valid handler is processed.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_doesNotTouchCancelledItems
     */
    public function finalizeItems_doesNotTouchCancelledItems(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 10.00);

        $cancelledItem = $this->createLinkItem($link->id, 'custom_charge', 25.00);
        TourBookingPaymentLinkItem::where('id', $cancelledItem->id)->update(['status' => 'Cancelled']);

        $unknownItem = $this->createLinkItem($link->id, 'unknown_type', 10.00);

        RemainingPaymentService::finalizeItems($link, $this->fakePaymentIntent(), $builder->bookingId);

        $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($cancelledItem->id)->status);
        $this->assertEquals('Unpaid',    TourBookingPaymentLinkItem::find($unknownItem->id)->status);
        $this->assertEquals('Paid',      TourBookingPaymentLink::find($link->id)->status);

        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // finalizeItems — Happy path (custom_charge actually finalized)
    // ---------------------------------------------------------------

    /**
     * A processable custom_charge item must actually be finalized: the referenced
     * tour_booking_custom_charges row is marked Paid, a Completed transaction row is created,
     * and both the item and the link are marked Paid.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_finalizesProcessableCustomChargeItem
     */
    public function finalizeItems_finalizesProcessableCustomChargeItem(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $chargeId = DB::table('tour_booking_custom_charges')->insertGetId([
            'tour_booking_id' => $builder->bookingId,
            'amount'          => 20.00,
            'fees'            => 1.80,
            'description'     => 'Test custom charge',
            'status'          => 'Open',
            'operation_by'    => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        $link = $this->createRemainingLink($groupId, 21.80);
        $item = $this->createLinkItem($link->id, 'custom_charge', 21.80, $chargeId);

        $pi = $this->fakePaymentIntent(2180);
        RemainingPaymentService::finalizeItems($link, $pi, $builder->bookingId, 'Card');

        $this->assertEquals('Paid', TourBookingPaymentLinkItem::find($item->id)->status);
        $this->assertEquals('Paid', TourBookingPaymentLink::find($link->id)->status);

        $chargeRow = DB::table('tour_booking_custom_charges')->find($chargeId);
        $this->assertEquals('Paid', $chargeRow->status);
        $this->assertEquals('Card', $chargeRow->payment_method);
        $this->assertEquals($pi->id, $chargeRow->transaction_id);

        $transaction = DB::table('transactions')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'custom_charge')
            ->first();
        $this->assertNotNull($transaction, 'A Completed transaction row must be created for the charge');
        $this->assertEquals('Completed', $transaction->status);
        $this->assertEquals(21.80, (float) $transaction->amount);

        // Regression: $item is always a TourBookingPaymentLinkItem, so this handler only ever
        // finalizes a Payment Link payment — operation_by must always resolve to the real
        // Customer via the booking's own email (unconditionally, never Auth::id(), since a
        // lingering admin/system session — e.g. a queue worker — must never get credited for a
        // payment the customer made), and created_by_with_name must be the static
        // "AppUser: (Customer)" label rather than any resolved admin/staff name.
        $historyRow = DB::table('tour_booking_history')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'custom_charge')
            ->first();
        $this->assertNotNull($historyRow);
        $this->assertEquals($builder->customerId, $historyRow->operation_by);
        $this->assertEquals('Customer', $historyRow->operation_by_user_type);
        $historyDetails = json_decode($historyRow->details, true);
        $this->assertEquals('AppUser: (Customer)', $historyDetails['created_by_with_name'] ?? '');

        DB::table('tour_booking_custom_charges')->where('id', $chargeId)->delete();
        DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'custom_charge')->delete();
        $this->cleanupLink($link);
    }

    /**
     * finalizeItems() is reached both from the customer-facing Payment Link completion flow
     * (no admin/staff session at all) AND from an admin using the "Make Payment" option
     * directly (a real authenticated session) — this test explicitly logs in as an Admin
     * before calling finalizeItems() and asserts the attribution correctly resolves to THAT
     * admin (not a hardcoded "Customer"), since a genuinely logged-in admin action must be
     * attributed to them, not masked as an anonymous customer payment.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_attributesToTheLoggedInAdminWhenMakePaymentIsUsed
     */
    public function finalizeItems_attributesToTheLoggedInAdminWhenMakePaymentIsUsed(): void
    {
        $admin = \App\Models\Admin::first() ?? \App\Models\Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $chargeId = DB::table('tour_booking_custom_charges')->insertGetId([
            'tour_booking_id' => $builder->bookingId,
            'amount'          => 20.00,
            'fees'            => 1.80,
            'description'     => 'Test custom charge',
            'status'          => 'Open',
            'operation_by'    => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        $link = $this->createRemainingLink($groupId, 21.80);
        $item = $this->createLinkItem($link->id, 'custom_charge', 21.80, $chargeId);

        $pi = $this->fakePaymentIntent(2180);
        RemainingPaymentService::finalizeItems($link, $pi, $builder->bookingId, 'Card');

        $historyRow = DB::table('tour_booking_history')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'custom_charge')
            ->first();
        $this->assertNotNull($historyRow);
        $this->assertEquals($admin->id, $historyRow->operation_by, 'A genuinely logged-in admin action must be attributed to that admin');
        $this->assertEquals('Admin', $historyRow->operation_by_user_type);
        $historyDetails = json_decode($historyRow->details, true);
        $this->assertStringContainsString('(Admin)', $historyDetails['created_by_with_name'] ?? '');

        $transaction = DB::table('transactions')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'custom_charge')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals($admin->id, $transaction->created_by);
        $this->assertEquals('Admin', $transaction->created_by_user_type);

        DB::table('tour_booking_custom_charges')->where('id', $chargeId)->delete();
        DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'custom_charge')->delete();
        $this->cleanupLink($link);
    }

    /**
     * Regression: ChangePeopleItemHandler's "new path" (no reference_id) previously wrote
     * `$details['created_by_with_name'] ?? $createdByUserName` — preferring whatever label was
     * already recorded on the item's details when it was FIRST created (e.g. a real admin/staff
     * name from the original change-people action). Since that left-hand value is basically
     * always already set, the static "AppUser: (Customer)" fallback never actually fired in
     * production. The finalize-time label must always win for a Payment Link completion.
     *
     * @test
     * CMD: php artisan test --filter=finalizeItems_overridesStaleCreatedByWithNameOnChangePeopleItem
     */
    public function finalizeItems_overridesStaleCreatedByWithNameOnChangePeopleItem(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 21.80);
        $item = $this->createLinkItem($link->id, 'change_people', 21.80, null, [
            'tourists'             => 1,
            'ticket_cost'          => 20.00,
            'platform_fees'        => 1.80,
            'tour_booking_id'      => $builder->bookingId,
            'added_transact_data'  => [],
            // The stale label recorded when this item was first created (e.g. by a real admin,
            // or a system account like "NAT Automation") — must NOT survive into the finalized
            // history row.
            'created_by_with_name' => 'NAT Automation: (Admin)',
        ]);

        $pi = $this->fakePaymentIntent(2180);
        RemainingPaymentService::finalizeItems($link, $pi, $builder->bookingId, 'Card');

        $historyRow = DB::table('tour_booking_history')
            ->where('tour_booking_id', $builder->bookingId)
            ->where('charge_type', 'change_people')
            ->first();
        $this->assertNotNull($historyRow);
        $historyDetails = json_decode($historyRow->details, true);
        $this->assertEquals('AppUser: (Customer)', $historyDetails['created_by_with_name'] ?? '');

        DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'change_people')->delete();
        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // getPendingForBooking
    // ---------------------------------------------------------------

    /**
     * Returns null when the booking has no Unpaid remaining_payment link.
     *
     * @test
     * CMD: php artisan test --filter=getPendingForBooking_returnsNullWhenNoUnpaidLink
     */
    public function getPendingForBooking_returnsNullWhenNoUnpaidLink(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $result = RemainingPaymentService::getPendingForBooking($builder->bookingId);
        $this->assertNull($result);
    }

    /**
     * Returns the pending link, items array, and total when an Unpaid link exists.
     *
     * @test
     * CMD: php artisan test --filter=getPendingForBooking_returnsPendingLinkWithItems
     */
    public function getPendingForBooking_returnsPendingLinkWithItems(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 25.00);
        $item = $this->createLinkItem($link->id, 'change_people', 25.00, null, [
            'created_by_with_name' => ['id' => 1, 'name' => 'Admin User'],
        ]);

        $result = RemainingPaymentService::getPendingForBooking($builder->bookingId);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('link',  $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals($link->id, $result['link']->id);
        $this->assertGreaterThanOrEqual(1, $result['items']->count());
        $this->assertEquals(25.00, (float) $result['total']);

        $firstItem = $result['items']->firstWhere('id', $item->id);
        $this->assertNotNull($firstItem);
        $this->assertEquals('change_people', $firstItem['type']);
        $this->assertEquals('Change People',  $firstItem['label']);
        $this->assertEquals(25.00, (float) $firstItem['amount']);

        $this->cleanupLink($link);
    }

    /**
     * Returns null when the booking's only link is Paid — no pending link.
     *
     * @test
     * CMD: php artisan test --filter=getPendingForBooking_excludesPaidLinks
     */
    public function getPendingForBooking_excludesPaidLinks(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 25.00);
        $link->status = 'Paid';
        $link->save();

        $result = RemainingPaymentService::getPendingForBooking($builder->bookingId);
        $this->assertNull($result);

        $this->cleanupLink($link);
    }

    /**
     * Labels unknown item types using a humanised fallback (ucwords + underscore->space).
     *
     * @test
     * CMD: php artisan test --filter=getPendingForBooking_usesHumanisedLabelForUnknownTypes
     */
    public function getPendingForBooking_usesHumanisedLabelForUnknownTypes(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 15.00);
        $item = $this->createLinkItem($link->id, 'some_future_type', 15.00);

        $result = RemainingPaymentService::getPendingForBooking($builder->bookingId);
        $this->assertNotNull($result);

        $mappedItem = $result['items']->firstWhere('id', $item->id);
        $this->assertNotNull($mappedItem);
        $this->assertEquals('Some Future Type', $mappedItem['label']);

        $this->cleanupLink($link);
    }

    /**
     * getPendingForBooking sums Unpaid items to produce the total; Cancelled items excluded.
     *
     * @test
     * CMD: php artisan test --filter=getPendingForBooking_totalsOnlyUnpaidItems
     */
    public function getPendingForBooking_totalsOnlyUnpaidItems(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 45.00);

        $this->createLinkItem($link->id, 'change_people', 20.00);
        $this->createLinkItem($link->id, 'change_people', 25.00);
        $cancelledItem = $this->createLinkItem($link->id, 'change_people', 100.00);
        TourBookingPaymentLinkItem::where('id', $cancelledItem->id)->update(['status' => 'Cancelled']);

        $result = RemainingPaymentService::getPendingForBooking($builder->bookingId);
        $this->assertNotNull($result);
        // Total should be 20+25 = 45, not 145
        $this->assertEquals(45.00, (float) $result['total']);

        $this->cleanupLink($link);
    }

    /**
     * getPendingForBooking must also surface Open custom charges that were created outside
     * the payment-link flow (e.g. editBooking / Pay Later) and are not yet linked to any item —
     * they must be included in both the items list and the total.
     *
     * @test
     * CMD: php artisan test --filter=getPendingForBooking_includesUnlinkedOpenCustomCharges
     */
    public function getPendingForBooking_includesUnlinkedOpenCustomCharges(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = $this->createRemainingLink($groupId, 20.00);
        $this->createLinkItem($link->id, 'change_people', 20.00);

        // Not referenced by any TourBookingPaymentLinkItem — must be picked up by the
        // "unlinked Open custom charges" fallback query.
        DB::table('tour_booking_custom_charges')->insertGetId([
            'tour_booking_id' => $builder->bookingId,
            'amount'          => 12.00,
            'fees'            => 1.00,
            'description'     => 'Unlinked open charge',
            'status'          => 'Open',
            'operation_by'    => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        $result = RemainingPaymentService::getPendingForBooking($builder->bookingId);

        $this->assertNotNull($result);
        // 20 (linked change_people item) + 13 (12 amount + 1 fees, unlinked open charge) = 33
        $this->assertEquals(33.00, (float) $result['total']);

        $unlinkedItem = $result['items']->firstWhere('label', 'Custom Charge');
        $this->assertNotNull($unlinkedItem, 'The unlinked Open custom charge must appear in the items list');
        $this->assertNull($unlinkedItem['id'], 'Unlinked custom charges have no payment_link_item id');
        $this->assertEquals(13.00, (float) $unlinkedItem['amount']);

        DB::table('tour_booking_custom_charges')->where('tour_booking_id', $builder->bookingId)->delete();
        $this->cleanupLink($link);
    }

    // ---------------------------------------------------------------
    // Teardown
    // ---------------------------------------------------------------

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        parent::tearDownAfterClass();
    }
}
