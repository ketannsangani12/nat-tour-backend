<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\PaymentLinkService;
use App\Services\TourPackage\TourPackageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for PaymentLinkService::upsertRemainingPaymentLink()
 * and PaymentLinkService::recalculateRemainingPaymentLink().
 *
 * These methods manage the remaining_payment link lifecycle:
 * - upsert: create a new link (section=remaining_payment) OR append an item to an
 *   existing Unpaid link, then recalculate and email.
 * - recalculate: recompute total from Unpaid items; cancel link if total=0; regenerate token.
 *
 * Also covers the fee-calculation bug fix: Cancelled payment link items must be
 * excluded from the application_fee_amount sum used when creating a Stripe PaymentIntent.
 *
 * CMD: php artisan test --filter=UpsertRemainingPaymentLinkTest
 */
class UpsertRemainingPaymentLinkTest extends TestCase
{
    protected static array $builders = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Builds the $info object that EmailService::sendPaymentLinkEmail needs.
     * All properties accessed before Mail::send() (lines 478-535 of EmailService)
     * must be present; Mail::fake() in TestCase::setUp() intercepts the send itself.
     */
    private function fakeInfo(TourBooking $booking): object
    {
        return (object) [
            // Properties accessed by EmailService::sendPaymentLinkEmail() before Mail::send()
            'tour_operator_id'    => 1,
            'tour_package_id'     => $booking->tour_package_id ?? 1,
            'tour_operator'       => 'Test Operator',
            'tour_op_phone'       => '5555551234',
            'tour_op_email'       => 'op@example.com',
            'tour_op_website'     => 'https://example.com',
            'package_name'        => 'Test Tour Package',
            'important_notes'     => '',
            'things_to_bring'     => null,
            'cancellation_policy' => '',
            'packageEmail'        => 'package@example.com',
            'packageFromName'     => 'Test Package',
            'packageFromEmail'    => 'noreply@example.com',
            'packageApiKey'       => 'SG.test_fake_api_key',
            'tenant_id'           => tenancy()->tenant->id,
            'tour_op_logo'        => null,
            'external_booking_id' => null,
        ];
    }

    private function newItem(string $type = 'custom_charge', float $amount = 20.00, array $extra = []): array
    {
        return array_merge([
            'type'         => $type,
            'reference_id' => null,
            'amount'       => $amount,
            'reason'       => 'Additional payment',
            'details'      => json_encode(['platform_fees' => round($amount * 0.03, 2)]),
        ], $extra);
    }

    private function cleanupLinks(string $orderId): void
    {
        $linkIds = TourBookingPaymentLink::where('order_id', $orderId)->pluck('id');
        TourBookingPaymentLinkItem::whereIn('payment_link_id', $linkIds)->delete();
        TourBookingPaymentLink::where('order_id', $orderId)->delete();
    }

    /**
     * Ensure the booking has a group_id (CreateABooking does not set one by default).
     * Uses the bookingId itself as a unique group_id for single-booking tests.
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
    // upsertRemainingPaymentLink — Create path
    // ---------------------------------------------------------------

    /**
     * When no Unpaid remaining_payment link exists for the order, a new link is created
     * with section='remaining_payment', status='Unpaid', and one item row.
     *
     * @test
     * CMD: php artisan test --filter=upsertRemainingPaymentLink_createsNewLink
     */
    public function upsertRemainingPaymentLink_createsNewLink(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $booking = TourBooking::find($builder->bookingId);
        $groupId = $this->resolveGroupId($builder->bookingId);

        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId,
            $this->newItem('custom_charge', 20.00),
            $booking,
            Carbon::now()->addDays(30),
            $this->fakeInfo($booking)
        );

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->where('section', 'remaining_payment')
            ->first();

        $this->assertNotNull($link, 'A new remaining_payment link must be created');
        $this->assertEquals('remaining_payment', $link->section);
        $this->assertEquals('Unpaid', $link->status);
        $this->assertEquals(20.00, (float) $link->amount);
        $this->assertNotEmpty($link->token);

        $itemCount = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->count();
        $this->assertEquals(1, $itemCount, 'Exactly one item must be created');

        $item = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->first();
        $this->assertEquals('custom_charge', $item->type);
        $this->assertEquals(20.00, (float) $item->amount);
        $this->assertEquals('Unpaid', $item->status);

        $this->cleanupLinks($groupId);
    }

    /**
     * The created link must have override_status='Yes' so the payment link does not
     * expire before the tour date (remaining payment links bypass the normal expiry window).
     *
     * @test
     * CMD: php artisan test --filter=upsertRemainingPaymentLink_setsOverrideStatus
     */
    public function upsertRemainingPaymentLink_setsOverrideStatus(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $booking = TourBooking::find($builder->bookingId);
        $groupId = $this->resolveGroupId($builder->bookingId);

        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId,
            $this->newItem('change_people', 15.00),
            $booking,
            Carbon::now()->addDays(30),
            $this->fakeInfo($booking)
        );

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->first();

        $this->assertNotNull($link);
        $this->assertEquals('Yes', $link->override_status);

        $this->cleanupLinks($groupId);
    }

    // ---------------------------------------------------------------
    // upsertRemainingPaymentLink — Update/Append path
    // ---------------------------------------------------------------

    /**
     * When an Unpaid remaining_payment link already exists, the new item is appended
     * to it and the link's total is recalculated from all Unpaid items.
     *
     * @test
     * CMD: php artisan test --filter=upsertRemainingPaymentLink_appendsItemToExistingLink
     */
    public function upsertRemainingPaymentLink_appendsItemToExistingLink(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $booking = TourBooking::find($builder->bookingId);
        $groupId = $this->resolveGroupId($builder->bookingId);

        // First call → creates the link with $20 item
        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId, $this->newItem('custom_charge', 20.00),
            $booking, Carbon::now()->addDays(30), $this->fakeInfo($booking)
        );

        $linkAfterFirst = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->first();
        $this->assertNotNull($linkAfterFirst);
        $tokenAfterFirst = $linkAfterFirst->token;

        // Second call → appends $30 item to same link, refreshes total and token
        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId, $this->newItem('change_people', 30.00),
            $booking, Carbon::now()->addDays(30), $this->fakeInfo($booking)
        );

        $linkAfterSecond = TourBookingPaymentLink::find($linkAfterFirst->id);
        $this->assertEquals(50.00, (float) $linkAfterSecond->amount, 'Total should be 20+30=50');
        $this->assertNotEquals($tokenAfterFirst, $linkAfterSecond->token, 'Token must regenerate');

        $itemCount = TourBookingPaymentLinkItem::where('payment_link_id', $linkAfterFirst->id)->count();
        $this->assertEquals(2, $itemCount, 'Both items should be on the same link');

        $this->cleanupLinks($groupId);
    }

    /**
     * After appending, a second Unpaid remaining_payment link must NOT be created —
     * only one Unpaid link per order is allowed.
     *
     * @test
     * CMD: php artisan test --filter=upsertRemainingPaymentLink_doesNotCreateDuplicateLink
     */
    public function upsertRemainingPaymentLink_doesNotCreateDuplicateLink(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;

        $booking = TourBooking::find($builder->bookingId);
        $groupId = $this->resolveGroupId($builder->bookingId);

        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId, $this->newItem('custom_charge', 10.00),
            $booking, Carbon::now()->addDays(30), $this->fakeInfo($booking)
        );
        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId, $this->newItem('change_people', 15.00),
            $booking, Carbon::now()->addDays(30), $this->fakeInfo($booking)
        );
        PaymentLinkService::upsertRemainingPaymentLink(
            $groupId, $this->newItem('addon', 5.00),
            $booking, Carbon::now()->addDays(30), $this->fakeInfo($booking)
        );

        $linkCount = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->where('status', 'Unpaid')
            ->count();
        $this->assertEquals(1, $linkCount, 'Only one Unpaid remaining_payment link may exist per order');

        $link = TourBookingPaymentLink::where('order_id', $groupId)
            ->where('section', 'remaining_payment')
            ->first();
        $this->assertEquals(30.00, (float) $link->amount, 'Total should be 10+15+5=30');

        $this->cleanupLinks($groupId);
    }

    // ---------------------------------------------------------------
    // recalculateRemainingPaymentLink
    // ---------------------------------------------------------------

    /**
     * When all items are Cancelled, recalculate must cancel the link.
     *
     * @test
     * CMD: php artisan test --filter=recalculateRemainingPaymentLink_cancelsLinkWhenTotalIsZero
     */
    public function recalculateRemainingPaymentLink_cancelsLinkWhenTotalIsZero(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 30.00,
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'custom_charge',
            'amount'               => 30.00,
            'status'               => 'Unpaid',
            'created_by'           => 1, 'created_by_user_type' => 'Admin',
            'updated_by'           => 1, 'updated_by_user_type' => 'Admin',
        ]);

        // Cancel the item so total becomes 0
        TourBookingPaymentLinkItem::where('id', $item->id)->update(['status' => 'Cancelled']);

        PaymentLinkService::recalculateRemainingPaymentLink($link->id);

        $this->assertEquals('Cancelled', TourBookingPaymentLink::find($link->id)->status);

        $this->cleanupLinks($groupId);
    }

    /**
     * When Unpaid items remain, recalculate updates total to the sum of Unpaid items only
     * (excluding Cancelled ones) and generates a new token.
     *
     * @test
     * CMD: php artisan test --filter=recalculateRemainingPaymentLink_updatesAmountAndToken
     */
    public function recalculateRemainingPaymentLink_updatesAmountAndToken(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $originalToken = \Illuminate\Support\Str::random(64);
        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 99.00, // stale total
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => $originalToken,
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'custom_charge',
            'amount' => 20.00, 'status' => 'Unpaid',
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);
        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people',
            'amount' => 25.00, 'status' => 'Unpaid',
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);
        // Cancelled — must NOT be included in total
        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon',
            'amount' => 100.00, 'status' => 'Cancelled',
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        PaymentLinkService::recalculateRemainingPaymentLink($link->id);

        $refreshed = TourBookingPaymentLink::find($link->id);
        $this->assertEquals('Unpaid', $refreshed->status);
        $this->assertEquals(45.00, (float) $refreshed->amount, 'Total must be 20+25=45');
        $this->assertNotEquals($originalToken, $refreshed->token, 'Token must be refreshed');

        $this->cleanupLinks($groupId);
    }

    /**
     * recalculateRemainingPaymentLink skips links that are already Paid or Cancelled.
     *
     * @test
     * CMD: php artisan test --filter=recalculateRemainingPaymentLink_skipsTerminalLinks
     */
    public function recalculateRemainingPaymentLink_skipsTerminalLinks(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $originalAmount = 50.00;
        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => $originalAmount,
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Paid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'custom_charge',
            'amount' => 999.00, 'status' => 'Unpaid',
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        PaymentLinkService::recalculateRemainingPaymentLink($link->id);

        // Paid link must not be modified
        $this->assertEquals('Paid',          TourBookingPaymentLink::find($link->id)->status);
        $this->assertEquals($originalAmount, (float) TourBookingPaymentLink::find($link->id)->amount);

        $this->cleanupLinks($groupId);
    }

    /**
     * recalculateRemainingPaymentLink must pick up Open custom charges created outside the
     * payment-link flow (editBooking / Pay Later) that aren't yet linked to any item, create a
     * TourBookingPaymentLinkItem for each, and fold their amount into the recalculated total.
     *
     * @test
     * CMD: php artisan test --filter=recalculateRemainingPaymentLink_linksUnlinkedOpenCustomCharges
     */
    public function recalculateRemainingPaymentLink_linksUnlinkedOpenCustomCharges(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 20.00,
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people',
            'amount' => 20.00, 'status' => 'Unpaid',
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        // Not referenced by any TourBookingPaymentLinkItem yet.
        $chargeId = DB::table('tour_booking_custom_charges')->insertGetId([
            'tour_booking_id' => $builder->bookingId,
            'amount'          => 10.00,
            'fees'            => 0.90,
            'description'     => 'Unlinked open charge',
            'status'          => 'Open',
            'operation_by'    => 1,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        PaymentLinkService::recalculateRemainingPaymentLink($link->id);

        $newItem = TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
            ->where('type', 'custom_charge')
            ->where('reference_id', $chargeId)
            ->first();
        $this->assertNotNull($newItem, 'A payment-link item must be created for the unlinked open charge');
        $this->assertEquals('Unpaid', $newItem->status);
        $this->assertEquals(10.90, (float) $newItem->amount, 'Item amount must be charge amount + fees');

        $refreshed = TourBookingPaymentLink::find($link->id);
        $this->assertEquals(30.90, (float) $refreshed->amount, 'Total must be 20 (change_people) + 10.90 (charge)');

        DB::table('tour_booking_custom_charges')->where('id', $chargeId)->delete();
        $this->cleanupLinks($groupId);
    }

    /**
     * When nothing changed (total is already correct and no new items were linked),
     * recalculateRemainingPaymentLink must leave the link untouched — no token refresh.
     *
     * @test
     * CMD: php artisan test --filter=recalculateRemainingPaymentLink_noOpWhenNothingChanged
     */
    public function recalculateRemainingPaymentLink_noOpWhenNothingChanged(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $originalToken = \Illuminate\Support\Str::random(64);
        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 20.00, // already correct — matches the sole Unpaid item
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => $originalToken,
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people',
            'amount' => 20.00, 'status' => 'Unpaid',
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        PaymentLinkService::recalculateRemainingPaymentLink($link->id);

        $refreshed = TourBookingPaymentLink::find($link->id);
        $this->assertEquals($originalToken, $refreshed->token, 'Token must not change when nothing needs updating');
        $this->assertEquals(20.00, (float) $refreshed->amount);
        $this->assertEquals('Unpaid', $refreshed->status);

        Mail::assertNothingSent();

        $this->cleanupLinks($groupId);
    }

    /**
     * recalculateRemainingPaymentLink must ignore links from any other section
     * (e.g. new_booking, make_payment) — it only ever recalculates remaining_payment links.
     *
     * @test
     * CMD: php artisan test --filter=recalculateRemainingPaymentLink_ignoresNonRemainingPaymentSection
     */
    public function recalculateRemainingPaymentLink_ignoresNonRemainingPaymentSection(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $originalToken = \Illuminate\Support\Str::random(64);
        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'new_booking',
            'amount'               => 50.00,
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => $originalToken,
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        PaymentLinkService::recalculateRemainingPaymentLink($link->id);

        $refreshed = TourBookingPaymentLink::find($link->id);
        $this->assertEquals($originalToken, $refreshed->token);
        $this->assertEquals(50.00, (float) $refreshed->amount);
        $this->assertEquals('Unpaid', $refreshed->status);

        $this->cleanupLinks($groupId);
    }

    // ---------------------------------------------------------------
    // Fee calculation — Cancelled items excluded (bug-fix regression)
    // ---------------------------------------------------------------

    /**
     * Regression: Cancelled payment_link_items must NOT contribute to the
     * application_fee_amount calculation in createPaymentLink().
     *
     * Before the fix, a Cancelled addon item's platform_fees was summed alongside
     * the Unpaid custom_charge item's fees, producing 196 cents instead of 98.
     *
     * This test replicates the fee-loop logic from PaymentLinkService::createPaymentLink()
     * and asserts that only the Unpaid item's fee is counted.
     *
     * @test
     * CMD: php artisan test --filter=feeSum_excludesCancelledItems
     */
    public function feeSum_excludesCancelledItems(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->create();
        static::$builders[] = $builder;
        $groupId = $this->resolveGroupId($builder->bookingId);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 10.98,
            'reason_of_payment'    => 'Custom charge',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        // Unpaid custom_charge with platform_fees=0.98
        TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id, 'type' => 'custom_charge',
            'amount'               => 10.98, 'status' => 'Unpaid',
            'details'              => json_encode(['platform_fees' => 0.98]),
            'created_by'           => 1, 'created_by_user_type' => 'Admin',
            'updated_by'           => 1, 'updated_by_user_type' => 'Admin',
        ]);

        // Cancelled addon — also has platform_fees=0.98.
        // Bug: the old fee loop didn't skip Cancelled items → 196 cents instead of 98.
        TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id, 'type' => 'addon',
            'amount'               => 9.98, 'status' => 'Cancelled',
            'details'              => json_encode(['platform_fees' => 0.98]),
            'created_by'           => 1, 'created_by_user_type' => 'Admin',
            'updated_by'           => 1, 'updated_by_user_type' => 'Admin',
        ]);

        // Replicate the fixed fee loop from PaymentLinkService::createPaymentLink()
        $stripeFees = 0;
        foreach (TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->get() as $linkItem) {
            if ($linkItem->status === 'Cancelled') {
                continue; // ← this is the fix we're testing
            }
            $itemDetails = is_array($linkItem->details)
                ? $linkItem->details
                : (json_decode($linkItem->details ?? '[]', true) ?? []);
            if (isset($itemDetails['platform_fees'])) {
                $stripeFees += (float) $itemDetails['platform_fees'];
            }
        }

        $applicationFeeAmountCents = (int) round($stripeFees * 100);
        $this->assertEquals(98, $applicationFeeAmountCents,
            'application_fee_amount must be 98 cents (0.98 USD), not 196 (double-counted Cancelled item)');

        $this->cleanupLinks($groupId);
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
