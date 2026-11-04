<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\Admin;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Unit tests for the "resend payment link" feature.
 *
 * "Resend" is implemented by calling createPaymentLink() again —
 * the service cancels the previous Unpaid link and creates a new one.
 *
 * CMD: php artisan test --filter=PaymentLinkTest
 */
class PaymentLinkTest extends TestCase
{
    protected TourBookingRepository $repo;
    protected static array $builders = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourBookingRepository::class);
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    /**
     * Build a minimal createPaymentLink request from a fixture builder.
     */
    protected function makePaymentLinkRequest(CreateABooking $builder, array $overrides = []): Request
    {
        $request = new Request();
        $request->merge(array_merge([
            'booking_id'        => $builder->bookingId,
            'tour_slot_id'      => $builder->slotTimeId,
            'amount'            => 100.00,
            'reason_of_payment' => 'Booking Payment',
            'customer_email'    => 'test@example.com',
            'customer_phone_no' => '5550001111',
        ], $overrides));

        return $request;
    }

    /**
     * Clean up payment links by order_id after each test.
     * order_id is varchar in tour_booking_payment_links but backed by an integer group_id.
     */
    protected function cleanupPaymentLinks(int|string $orderId): void
    {
        DB::table('tour_booking_payment_links')->where('order_id', $orderId)->delete();
    }

    // ---------------------------------------------------------------
    // Tests
    // ---------------------------------------------------------------

    /**
     * A new payment link record is persisted with the correct values.
     *
     * CMD: php artisan test --filter=testCreatePaymentLinkStoresRecord
     */
    public function testCreatePaymentLinkStoresRecord(): void
    {
        $this->authAsAdmin();

        $email = 'createpaymentlink-test-' . uniqid() . '@test.com';
        $builder = (new CreateABooking())
            ->withTourists(2)
            ->withRate(50.00)
            ->withCustomer('Jane Doe', $email, '5550001111')
            ->create();
        static::$builders[] = $builder;

        // group_id is unsignedBigInteger — use the bookingId as a safe unique integer
        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')
            ->where('id', $builder->bookingId)
            ->update(['group_id' => $groupId]);

        // Capture any exception thrown by the repo so the test failure message is informative
        $exception = null;
        try {
            $this->repo->createPaymentLink($this->makePaymentLinkRequest($builder, [
                'amount'         => 200.00,
                'customer_email' => $email,
            ]));
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull(
            $exception,
            'createPaymentLink should not throw. Got: ' . ($exception ? $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine() : '')
        );

        $link = DB::table('tour_booking_payment_links')
            ->where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->first();

        // If not found by order_id, show all recent links to aid diagnosis
        if (!$link) {
            $recent = DB::table('tour_booking_payment_links')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'order_id', 'status', 'amount', 'email'])
                ->toArray();
            $this->fail(
                'Payment link record should be created. ' .
                'Last 5 links in DB: ' . json_encode($recent) . ' | ' .
                'Expected order_id: ' . $groupId
            );
        }

        $this->assertEquals(200.00, (float) $link->amount);
        $this->assertEquals($email, $link->email);
        $this->assertEquals('Booking Payment', $link->reason_of_payment);
        $this->assertNotEmpty($link->token, 'Token should be generated');

        $this->cleanupPaymentLinks($groupId);
    }

    /**
     * Calling createPaymentLink() a second time (resend) cancels the
     * previous Unpaid link and creates a new Unpaid one.
     *
     * CMD: php artisan test --filter=testResendPaymentLinkCancelsPreviousUnpaidLink
     */
    public function testResendPaymentLinkCancelsPreviousUnpaidLink(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->create();
        static::$builders[] = $builder;

        // group_id is unsignedBigInteger — use the bookingId as a safe unique integer
        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')
            ->where('id', $builder->bookingId)
            ->update(['group_id' => $groupId]);

        $request = $this->makePaymentLinkRequest($builder);

        // First send
        $this->repo->createPaymentLink($request);
        $firstLink = DB::table('tour_booking_payment_links')
            ->where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->first();
        $this->assertNotNull($firstLink, 'First payment link should be Unpaid');

        // Resend (second send)
        $this->repo->createPaymentLink($request);

        // First link must now be Cancelled
        $firstLinkRefreshed = DB::table('tour_booking_payment_links')
            ->where('id', $firstLink->id)
            ->first();
        $this->assertEquals('Cancelled', $firstLinkRefreshed->status, 'Original link should be Cancelled after resend');

        // A new Unpaid link should exist
        $newLink = DB::table('tour_booking_payment_links')
            ->where('order_id', $groupId)
            ->where('status', 'Unpaid')
            ->first();
        $this->assertNotNull($newLink, 'New Unpaid link should exist after resend');
        $this->assertNotEquals($firstLink->id, $newLink->id, 'Resend should create a different link record');

        $this->cleanupPaymentLinks($groupId);
    }

    /**
     * paymentLinkStatusCheck() returns true when an Unpaid link exists.
     *
     * CMD: php artisan test --filter=testPaymentLinkStatusCheckReturnsTrueWhenUnpaidExists
     */
    public function testPaymentLinkStatusCheckReturnsTrueWhenUnpaidExists(): void
    {
        $this->authAsAdmin();

        $groupId = 'pl-test-' . uniqid();
        DB::table('tour_booking_payment_links')->insert([
            'order_id'          => $groupId,
            'amount'            => 50.00,
            'reason_of_payment' => 'Test',
            'email'             => 'test@example.com',
            'phone_number'      => '5550001111',
            'token'             => 'test-token-' . uniqid(),
            'status'            => 'Unpaid',
            'override_status'   => 'No',
            'expired_at'        => now()->addDays(7),
            'created_by'        => 1,
            'created_by_user_type' => 'Admin',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $request = new Request(['order_id' => $groupId]);
        $result = $this->repo->paymentLinkStatusCheck($request);

        $this->assertTrue($result, 'Should return true when an Unpaid payment link exists');

        $this->cleanupPaymentLinks($groupId);
    }

    /**
     * paymentLinkStatusCheck() returns false when no Unpaid link exists.
     *
     * CMD: php artisan test --filter=testPaymentLinkStatusCheckReturnsFalseWhenNoUnpaidExists
     */
    public function testPaymentLinkStatusCheckReturnsFalseWhenNoUnpaidExists(): void
    {
        $this->authAsAdmin();

        $request = new Request(['order_id' => 'non-existent-order-' . uniqid()]);
        $result = $this->repo->paymentLinkStatusCheck($request);

        $this->assertFalse($result, 'Should return false when no Unpaid payment link exists');
    }

    /**
     * paymentLinkStatusUpdate() sets an Unpaid link's status to Cancelled.
     *
     * CMD: php artisan test --filter=testPaymentLinkStatusUpdateCancelsLink
     */
    public function testPaymentLinkStatusUpdateCancelsLink(): void
    {
        $this->authAsAdmin();

        $groupId = 'pl-test-' . uniqid();
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id'          => $groupId,
            'amount'            => 75.00,
            'reason_of_payment' => 'Test cancellation',
            'email'             => 'cancel@example.com',
            'phone_number'      => '5550002222',
            'token'             => 'test-token-' . uniqid(),
            'status'            => 'Unpaid',
            'override_status'   => 'No',
            'expired_at'        => now()->addDays(7),
            'created_by'        => 1,
            'created_by_user_type' => 'Admin',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $request = new Request(['id' => $linkId]);
        $result = $this->repo->paymentLinkStatusUpdate($request);

        $this->assertTrue($result);

        $link = DB::table('tour_booking_payment_links')->where('id', $linkId)->first();
        $this->assertEquals('Cancelled', $link->status, 'Status should be Cancelled after update');

        $this->cleanupPaymentLinks($groupId);
    }

    /**
     * When a completed transaction already exists, createPaymentLink() should
     * NOT send a payment link email (the customer has already paid).
     *
     * CMD: php artisan test --filter=testCreatePaymentLinkSkipsEmailWhenAlreadyPaid
     */
    public function testCreatePaymentLinkSkipsEmailWhenAlreadyPaid(): void
    {
        $this->authAsAdmin();

        // Enable payment link feature so the only gate left is the transaction check
        DB::table('settings')->where('key', 'PAYMENT_LINK')->update(['value' => 1]);

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->create(); // withTransaction() is true by default → Completed transaction exists
        static::$builders[] = $builder;

        // group_id is unsignedBigInteger — use the bookingId as a safe unique integer
        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')
            ->where('id', $builder->bookingId)
            ->update(['group_id' => $groupId]);

        $this->repo->createPaymentLink($this->makePaymentLinkRequest($builder));

        Mail::assertNothingSent();

        DB::table('settings')->where('key', 'PAYMENT_LINK')->update(['value' => 0]);
        $this->cleanupPaymentLinks($groupId);
    }

    /**
     * Each resend generates a unique token — no two links share the same token.
     *
     * CMD: php artisan test --filter=testResendPaymentLinkGeneratesUniqueToken
     */
    public function testResendPaymentLinkGeneratesUniqueToken(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->create();
        static::$builders[] = $builder;

        // group_id is unsignedBigInteger — use the bookingId as a safe unique integer
        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')
            ->where('id', $builder->bookingId)
            ->update(['group_id' => $groupId]);

        $request = $this->makePaymentLinkRequest($builder);

        $this->repo->createPaymentLink($request);
        $this->repo->createPaymentLink($request);

        $tokens = DB::table('tour_booking_payment_links')
            ->where('order_id', $groupId)
            ->pluck('token')
            ->toArray();

        $this->assertCount(2, $tokens, 'Two links should have been created');
        $this->assertCount(2, array_unique($tokens), 'Each link must have a unique token');

        $this->cleanupPaymentLinks($groupId);
    }

    // ---------------------------------------------------------------
    // booking() — pendingBookingPaymentLink (whole-booking Payment Link status badge)
    // ---------------------------------------------------------------

    /**
     * A totally-unpaid booking (e.g. Pay Later, no completed transaction) that has had a
     * general Payment Link sent (section=NULL, status=Unpaid) must surface it via
     * booking()'s $vars['pendingBookingPaymentLink'] — this is what the "Payment:" status
     * badge on the booking info page uses to show "Payment Link" instead of "Pay Later".
     *
     * @test
     * CMD: php artisan test --filter=booking_exposesPendingBookingPaymentLinkForUnpaidBooking
     */
    public function booking_exposesPendingBookingPaymentLinkForUnpaidBooking(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withoutTransaction()
            ->create();
        static::$builders[] = $builder;

        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')
            ->where('id', $builder->bookingId)
            ->update(['group_id' => $groupId]);

        DB::table('tour_booking_payment_links')->insert([
            'order_id'             => $groupId,
            'amount'               => 50.00,
            'reason_of_payment'    => 'Test',
            'email'                => 'test@example.com',
            'phone_number'         => '5550001111',
            'token'                => 'test-token-' . uniqid(),
            'status'               => 'Unpaid',
            'section'              => null,
            'override_status'      => 'No',
            'expired_at'           => now()->addDays(7),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $vars = $this->repo->booking($builder->bookingId, false);

        $this->assertArrayHasKey('pendingBookingPaymentLink', $vars);
        $this->assertNotNull($vars['pendingBookingPaymentLink']);
        $this->assertEquals(50.00, (float) $vars['pendingBookingPaymentLink']->amount);

        $this->cleanupPaymentLinks($groupId);
    }

    /**
     * A remaining_payment-section link must NOT be surfaced as pendingBookingPaymentLink —
     * that section is a distinct add-on-charges flow with its own "Pending Payment Link"
     * banner ($vars['pendingRemainingLink']) and must not affect the whole-booking status badge.
     *
     * @test
     * CMD: php artisan test --filter=booking_excludesRemainingPaymentSectionFromPendingBookingPaymentLink
     */
    public function booking_excludesRemainingPaymentSectionFromPendingBookingPaymentLink(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withoutTransaction()
            ->create();
        static::$builders[] = $builder;

        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')
            ->where('id', $builder->bookingId)
            ->update(['group_id' => $groupId]);

        DB::table('tour_booking_payment_links')->insert([
            'order_id'             => $groupId,
            'amount'               => 20.00,
            'reason_of_payment'    => 'Test remaining payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5550001111',
            'token'                => 'test-token-' . uniqid(),
            'status'               => 'Unpaid',
            'section'              => 'remaining_payment',
            'override_status'      => 'Yes',
            'expired_at'           => now()->addDays(7),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $vars = $this->repo->booking($builder->bookingId, false);

        $this->assertArrayHasKey('pendingBookingPaymentLink', $vars);
        $this->assertNull($vars['pendingBookingPaymentLink']);
        $this->assertNotNull($vars['pendingRemainingLink'], 'The remaining_payment link must still be exposed as pendingRemainingLink');

        $this->cleanupPaymentLinks($groupId);
    }

    // ---------------------------------------------------------------
    // booking() — addonsNetPaidAmount (Payment:/Payment Total: must include paid add-ons)
    // ---------------------------------------------------------------

    /**
     * An add-on billed and paid via its own separate payment/transaction (charge_type='addons')
     * is NOT included in $trans (scoped to charge_type IS NULL/'discount' — the tour side only),
     * so the "Payment:"/"Payment Total:" figures used to silently omit it even once fully paid.
     * booking()'s $vars['addonsNetPaidAmount'] must carry that collected amount so the blade can
     * fold it in.
     *
     * @test
     * CMD: php artisan test --filter=booking_exposesAddonsNetPaidAmountForCompletedAddonTransaction
     */
    public function booking_exposesAddonsNetPaidAmountForCompletedAddonTransaction(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        DB::table('transactions')->insert([
            'tour_booking_id'      => $builder->bookingId,
            'amount'                => 17.57,
            'fee_amount'            => 1.57,
            'transaction_number'    => 'pi_addon_paid_test',
            'application_fee_id'    => 'fee_addon_paid_test',
            'charge_type'           => 'addons',
            'status'                => 'Completed',
            'via'                   => 'Card',
            'payment_type'          => 0,
            'created_by'            => 1,
            'created_by_user_type'  => 'Admin',
            'created_at'            => now(),
        ]);

        $vars = $this->repo->booking($builder->bookingId, false);

        $this->assertArrayHasKey('addonsNetPaidAmount', $vars);
        $this->assertEquals(17.57, (float) $vars['addonsNetPaidAmount']);

        DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'addons')->delete();
    }

    /**
     * A later refund of that same add-on must be netted out of addonsNetPaidAmount.
     *
     * @test
     * CMD: php artisan test --filter=booking_nettsAddonsNetPaidAmountAgainstAddonRefund
     */
    public function booking_nettsAddonsNetPaidAmountAgainstAddonRefund(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        DB::table('transactions')->insert([
            [
                'tour_booking_id'      => $builder->bookingId,
                'amount'                => 17.57,
                'fee_amount'            => 1.57,
                'transaction_number'    => 'pi_addon_refund_test',
                'application_fee_id'    => 'fee_addon_refund_test',
                'charge_type'           => 'addons',
                'status'                => 'Completed',
                'via'                   => 'Card',
                'payment_type'          => 0,
                'created_by'            => 1,
                'created_by_user_type'  => 'Admin',
                'created_at'            => now(),
            ],
            [
                'tour_booking_id'      => $builder->bookingId,
                'amount'                => 17.57,
                'fee_amount'            => 1.57,
                'transaction_number'    => 'pi_addon_refund_test',
                'application_fee_id'    => 'fee_addon_refund_test',
                'charge_type'           => 'addons',
                'status'                => 'Refund',
                'via'                   => 'Card',
                'payment_type'          => 0,
                'created_by'            => 1,
                'created_by_user_type'  => 'Admin',
                'created_at'            => now(),
            ],
        ]);

        $vars = $this->repo->booking($builder->bookingId, false);

        $this->assertArrayHasKey('addonsNetPaidAmount', $vars);
        $this->assertEquals(0.0, (float) $vars['addonsNetPaidAmount'], 'Fully refunded add-on must net to zero, not double-count as still paid');

        DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'addons')->delete();
    }

    /**
     * $vars['paymentOnlyCheck'] feeds "Payment Total:"/"Payment:" (via $paidF = $paymentOnlyCheck
     * - $amount in the blade) alongside $addonsNetPaidAmount, which the blade folds in separately.
     * Before this fix, $paymentOnlyCheck summed EVERY tour_booking_history 'Payment' row
     * regardless of charge_type, so an add-on's own Payment row (written by
     * HistoryService::storeAddonsPaymentInHistory(), charge_type='addons') was counted here AND
     * again via $addonsNetPaidAmount — double-counting the add-on amount into the displayed total.
     *
     * A first attempt at the fix scoped $paymentOnly to charge_type IS NULL/'discount' only, which
     * broke real bookings that had gone through a Change People payment: that Payment history row
     * is written with charge_type='change_people' (see ChangePeopleItemHandler), so it isn't NULL
     * or 'discount' either — undercounting paymentOnlyCheck for those bookings. The real fix is a
     * blacklist: every charge_type EXCEPT 'addons' is ticket-side and belongs in paymentOnlyCheck
     * ('discount', 'change_people', 'switch_package', 'custom_charge', 'tip', and null all do).
     *
     * Reproduces the real-world case (booking 253626, dixies tenant): 2 original-payment rows +
     * 1 change_people row + 1 addons row → paymentOnlyCheck must total the first 3, and the blade's
     * $paidF (paymentOnlyCheck + addonsNetPaidAmount) must equal the booking's actual total paid.
     *
     * @test
     * CMD: php artisan test --filter=booking_paymentOnlyCheckExcludesOnlyAddonPaymentHistoryRows
     */
    public function booking_paymentOnlyCheckExcludesOnlyAddonPaymentHistoryRows(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;

        // Change People Payment history row, exactly as ChangePeopleItemHandler writes it —
        // ticket-side money, but NOT charge_type NULL/'discount'.
        DB::table('tour_booking_history')->insert([
            'tour_booking_id'       => $builder->bookingId,
            'transaction_id'        => null,
            'tourists'              => 2,
            'operation'             => 'Payment',
            'charge_type'           => 'change_people',
            'ticket_cost'           => 100.00,
            'discount'              => 0,
            'permit_fees'           => 0,
            'processing_fees'       => 0,
            'platform_fees'         => 10.00,
            'payment_type'          => 'Card',
            'payment_method'        => 'Card',
            'details'               => json_encode(['amount' => 110.00]),
            'operation_at'          => now(),
            'operation_by'          => 1,
            'operation_by_user_type' => 'Admin',
            'created_at'            => now(),
        ]);

        // Add-on's own Payment history row, exactly as storeAddonsPaymentInHistory() writes it.
        DB::table('tour_booking_history')->insert([
            'tour_booking_id'       => $builder->bookingId,
            'transaction_id'        => null,
            'tourists'              => 1,
            'operation'             => 'Payment',
            'charge_type'           => 'addons',
            'ticket_cost'           => 10.00,
            'discount'              => 0,
            'permit_fees'           => 0,
            'processing_fees'       => 0,
            'platform_fees'         => 0.98,
            'payment_type'          => 'Card',
            'payment_method'        => 'Card',
            'details'               => json_encode(['amount' => 10.98]),
            'operation_at'          => now(),
            'operation_by'          => 1,
            'operation_by_user_type' => 'Admin',
            'created_at'            => now(),
        ]);

        // Matching addon transaction — $vars['addonsNetPaidAmount'] is computed from `transactions`,
        // not `tour_booking_history`, so this is needed for the $paidF assertion below.
        DB::table('transactions')->insert([
            'tour_booking_id'      => $builder->bookingId,
            'amount'               => 10.98,
            'fee_amount'           => 0.98,
            'transaction_number'   => 'pi_addon_change_people_test',
            'application_fee_id'   => 'fee_addon_change_people_test',
            'charge_type'          => 'addons',
            'status'               => 'Completed',
            'via'                  => 'Card',
            'payment_type'         => 0,
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => now(),
        ]);

        $vars = $this->repo->booking($builder->bookingId, false);

        $this->assertArrayHasKey('paymentOnlyCheck', $vars);
        $this->assertEquals(160.00, (float) $vars['paymentOnlyCheck'], 'paymentOnlyCheck must include the change_people row (ticket-side) but exclude the addons row');

        $this->assertArrayHasKey('addonsNetPaidAmount', $vars);
        $paidF = (float) $vars['paymentOnlyCheck'] + (float) $vars['addonsNetPaidAmount'];
        $this->assertEquals(170.98, $paidF, 'Displayed Payment Total must equal ticket-side + add-on paid, counted exactly once each');

        DB::table('tour_booking_history')->where('tour_booking_id', $builder->bookingId)->whereIn('charge_type', ['addons', 'change_people'])->delete();
        DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->where('charge_type', 'addons')->delete();
    }

    // ---------------------------------------------------------------
    // booking() — pendingPaxInfo (the per-pax "Unpaid" badge)
    // ---------------------------------------------------------------

    /**
     * Reproduces a real-world bug (booking 253790, dixies tenant): a pax gets added (payment
     * link item A, Unpaid), then removed again before paying — which cancels item A. But the
     * parent payment link stays status='Unpaid' because ANOTHER change_people item (B, for a
     * different, still-pending pax) is under the same link.
     *
     * booking()'s pendingPaxInfo query only checked the parent LINK's status, not each ITEM's
     * own status, so the cancelled item A kept surfacing. Since "Change People" adjusts an
     * existing transact row's tourist count in place (add then remove nets back to the same
     * row id), item A's stale added_transact_data (keyed by that same transact_id) got counted
     * against the row's CURRENT (lower) tourist count — wrongly flagging an already-paid pax
     * (e.g. "Adult Ages 8+ #2") as "Unpaid" even though only the now-removed pax was ever unpaid.
     *
     * @test
     * CMD: php artisan test --filter=booking_pendingPaxInfoExcludesCancelledChangePeopleItem
     */
    public function booking_pendingPaxInfoExcludesCancelledChangePeopleItem(): void
    {
        $this->authAsAdmin();

        // 2 paid adults sharing one transact row (id = $adultsTransactId).
        $builder = (new CreateABooking())
            ->withTourists(2)
            ->withRate(55.00)
            ->withPaymentMethod('Card')
            ->create();
        static::$builders[] = $builder;
        $adultsTransactId = $builder->transactId;

        $groupId = $builder->bookingId;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $groupId]);

        $link = TourBookingPaymentLink::create([
            'order_id'             => (string) $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 161.00,
            'reason_of_payment'    => 'Test remaining payment',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => \Illuminate\Support\Str::random(64),
            // Stays Unpaid — a different pending item (the children, not modelled here) keeps
            // the link open even though the item below (the removed adult) is Cancelled.
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        // The temp 3rd adult that was added then removed — its own item is Cancelled, but
        // references the SAME transact row the 2 remaining (paid) adults now live on.
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'change_people',
            'reference_id'         => null,
            'amount'               => 80.50,
            'status'               => 'Cancelled',
            'details'              => json_encode([
                'added_transact_data' => [
                    ['transact_id' => $adultsTransactId, 'added_count' => 1],
                ],
            ]),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        $vars = $this->repo->booking($builder->bookingId, false);

        $this->assertArrayHasKey('pendingPaxInfo', $vars);
        $this->assertArrayNotHasKey(
            $adultsTransactId,
            $vars['pendingPaxInfo'],
            'A Cancelled change_people item must not mark the paid adults row as having pending/unpaid pax'
        );

        TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
        $link->delete();
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
