<?php

namespace Tests\Unit\Bookings;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Services\Booking\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Unit tests for TransactionService payment store methods.
 *
 * Each store method (storePaymentInTransaction, storeCustomChargeInTransaction,
 * storeAddonsPaymentInTransaction) must UPDATE an existing Initialized reservation
 * row rather than always inserting a new one. This prevents duplicate transaction
 * rows when the admin pays via the Make Payment / Stripe card UI.
 *
 * CMD: php artisan test --filter=TransactionServiceTest
 */
class TransactionServiceTest extends TestCase
{
    protected static array $builders = [];

    /** @return object Fake Stripe PaymentIntent shape that every store method expects. */
    private function fakePaymentIntent(int $amountCents = 1098): object
    {
        return (object) [
            'id'      => 'pi_test_' . uniqid(),
            'amount'  => $amountCents,
            'charges' => (object) [
                'data' => [
                    (object) [
                        'application_fee'        => 'fee_test_' . uniqid(),
                        'payment_method_details' => (object) [
                            'card' => (object) ['last4' => '4242', 'brand' => 'visa'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Insert a minimal Initialized reservation row (charge_type = NULL). */
    private function insertInitializedRow(int $bookingId, string $piId = ''): int
    {
        return DB::table('transactions')->insertGetId([
            'tour_booking_id'       => $bookingId,
            'amount'                => 0,
            'fee_amount'            => 0,
            'transaction_number'    => $piId,
            'application_fee_id'    => '',
            'via'                   => 'Card',
            'payment_type'          => 0,
            'response_init'         => '',
            'response_end'          => '',
            'response_end_datetime' => null, // nullable column; no updated_at in this table
            'status'                => 'Initialized',
            'charge_type'           => null,
            'visitor'               => '127.0.0.1',
            'request_from'          => 'Backend',
            'created_by'            => 1,
            'created_by_user_type'  => 'Admin',
            'created_at'            => Carbon::now(),
        ]);
    }

    /**
     * Insert an Initialized row that already has a charge_type set — e.g. left over from a
     * different in-flight payment on the same booking. The "reservation" lookup in every store
     * method filters on whereNull('charge_type'), so this row must never be matched/updated.
     */
    private function insertInitializedRowWithChargeType(int $bookingId, string $chargeType, string $piId = ''): int
    {
        return DB::table('transactions')->insertGetId([
            'tour_booking_id'       => $bookingId,
            'amount'                => 0,
            'fee_amount'            => 0,
            'transaction_number'    => $piId,
            'application_fee_id'    => '',
            'via'                   => 'Card',
            'payment_type'          => 0,
            'response_init'         => '',
            'response_end'          => '',
            'response_end_datetime' => null,
            'status'                => 'Initialized',
            'charge_type'           => $chargeType,
            'visitor'               => '127.0.0.1',
            'request_from'          => 'Backend',
            'created_by'            => 1,
            'created_by_user_type'  => 'Admin',
            'created_at'            => Carbon::now(),
        ]);
    }

    private function cleanupTransactions(int $bookingId): void
    {
        DB::table('transactions')->where('tour_booking_id', $bookingId)->delete();
    }

    // ---------------------------------------------------------------
    // storePaymentInTransaction
    // ---------------------------------------------------------------

    /**
     * When an Initialized (charge_type=NULL) row already exists for the booking,
     * storePaymentInTransaction must UPDATE it to Completed — not insert a new row.
     *
     * @test
     * CMD: php artisan test --filter=storePaymentInTransaction_updatesExistingInitializedRow
     */
    public function storePaymentInTransaction_updatesExistingInitializedRow(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $initId = $this->insertInitializedRow($builder->bookingId);
        $pi     = $this->fakePaymentIntent();
        $txId   = TransactionService::storePaymentInTransaction(
            $builder->bookingId, 91.47, 8.23, 'Card', 0, $pi->id, 'fee_test_abc'
        );

        $this->assertEquals($initId, (int) $txId, 'Should return the updated Initialized row id');

        $row = DB::table('transactions')->where('id', $initId)->first();
        $this->assertEquals('Completed', $row->status);
        $this->assertEquals(91.47, (float) $row->amount);
        $this->assertEquals(8.23,  (float) $row->fee_amount);
        $this->assertEquals($pi->id, $row->transaction_number);
        $this->assertNull($row->charge_type);

        $count = DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->count();
        $this->assertEquals(1, $count, 'Exactly one transaction row should exist');

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * When NO Initialized row exists, storePaymentInTransaction inserts a new Completed row.
     *
     * @test
     * CMD: php artisan test --filter=storePaymentInTransaction_insertsNewRowWhenNoInitializedExists
     */
    public function storePaymentInTransaction_insertsNewRowWhenNoInitializedExists(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $pi   = $this->fakePaymentIntent();
        $txId = TransactionService::storePaymentInTransaction(
            $builder->bookingId, 55.00, 4.95, 'Card', 0, $pi->id, 'fee_test_new'
        );

        $this->assertGreaterThan(0, $txId);
        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertNotNull($row);
        $this->assertEquals('Completed', $row->status);
        $this->assertEquals(55.00, (float) $row->amount);
        $this->assertNull($row->charge_type);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * A leftover Initialized row that already has a charge_type set (e.g. from a different
     * concurrent payment on the same booking) must NOT be matched by the reservation lookup
     * (whereNull('charge_type')) — a brand new row must be inserted instead of clobbering it.
     *
     * @test
     * CMD: php artisan test --filter=storePaymentInTransaction_insertsNewRowWhenInitializedRowHasChargeType
     */
    public function storePaymentInTransaction_insertsNewRowWhenInitializedRowHasChargeType(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $staleId = $this->insertInitializedRowWithChargeType($builder->bookingId, 'addons');
        $pi      = $this->fakePaymentIntent();
        $txId    = TransactionService::storePaymentInTransaction(
            $builder->bookingId, 30.00, 2.70, 'Card', 0, $pi->id, 'fee_test_xyz'
        );

        $this->assertNotEquals($staleId, $txId, 'Must not update the row with a non-NULL charge_type');

        $staleRow = DB::table('transactions')->where('id', $staleId)->first();
        $this->assertEquals('Initialized', $staleRow->status, 'Unrelated Initialized row must be left untouched');
        $this->assertEquals('addons', $staleRow->charge_type);

        $newRow = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals('Completed', $newRow->status);
        $this->assertNull($newRow->charge_type);

        $count = DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->count();
        $this->assertEquals(2, $count, 'Both the stale row and the new row must exist');

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * Regression: storePaymentInTransaction() is reached from customer-facing payment-link
     * completion flows (e.g. RemainingPaymentService's SwitchPackageItemHandler finalizing a
     * switch-package Payment Link) with no admin/staff session — Auth::id() is null there. Before
     * this fix, created_by was set straight to Auth::id() with no fallback, which threw "Column
     * 'created_by' cannot be null" on tenants where that column is NOT NULL. It must fall back to
     * the real Customer resolved by the booking's own email (BookingService::
     * customerIdByBookingEmail()), with created_by_user_type stamped as the static 'Customer'.
     *
     * @test
     * CMD: php artisan test --filter=storePaymentInTransaction_fallsBackToCustomerIdWhenUnauthenticated
     */
    public function storePaymentInTransaction_fallsBackToCustomerIdWhenUnauthenticated(): void
    {
        // Deliberately no actingAs() — Auth::id() is null here, exactly like a customer paying
        // via a public Payment Link with no admin/staff session.
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $pi   = $this->fakePaymentIntent();
        $txId = TransactionService::storePaymentInTransaction(
            $builder->bookingId, 46.56, 4.16, 'Card', 0, $pi->id, 'fee_test_customer'
        );

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertNotNull($row->created_by, 'created_by must never be null even without an authenticated admin session');
        // CreateABooking gives the booking and its customer the same email, so the email-based
        // lookup resolves to this same customer id.
        $this->assertEquals($builder->customerId, $row->created_by);
        $this->assertEquals('Customer', $row->created_by_user_type);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * Proves the fallback resolves the Customer by the booking's own EMAIL — not by the
     * tour_bookings_master.customer_id foreign key — as explicitly requested: two different
     * Customer rows share the same email domain but only one matches the booking's email
     * exactly; created_by must resolve to THAT one, even though customer_id points elsewhere.
     *
     * @test
     * CMD: php artisan test --filter=storePaymentInTransaction_resolvesCreatedByEmailNotCustomerIdColumn
     */
    public function storePaymentInTransaction_resolvesCreatedByEmailNotCustomerIdColumn(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        // A second, unrelated Customer whose email matches the booking's own email — the
        // booking's customer_id still points at $builder->customerId, so if the fallback were
        // still reading customer_id (instead of looking up by email) it would resolve to the
        // WRONG id here.
        $mismatchedEmail = 'mismatched-' . uniqid() . '@test.com';
        $otherCustomerId = DB::table('customers')->insertGetId([
            'tour_operator_id'     => DB::table('tour_operators')->value('id') ?? 1,
            'name'                 => 'Other Customer',
            'email'                => $mismatchedEmail,
            'phone_number'         => '5559990000',
            'is_affiliate'         => 'No',
            'status'               => 'Active',
            'remember_token'       => '',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
        ]);
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['email' => $mismatchedEmail]);

        $pi   = $this->fakePaymentIntent();
        $txId = TransactionService::storePaymentInTransaction(
            $builder->bookingId, 46.56, 4.16, 'Card', 0, $pi->id, 'fee_test_email_lookup'
        );

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals($otherCustomerId, $row->created_by, 'created_by must resolve via the booking\'s email, not customer_id');
        $this->assertNotEquals($builder->customerId, $row->created_by);
        $this->assertEquals('Customer', $row->created_by_user_type);

        DB::table('customers')->where('id', $otherCustomerId)->delete();
        $this->cleanupTransactions($builder->bookingId);
    }

    // ---------------------------------------------------------------
    // storeCustomChargeInTransaction
    // ---------------------------------------------------------------

    /**
     * When an Initialized (charge_type=NULL) row exists, storeCustomChargeInTransaction
     * must UPDATE it to Completed with charge_type='custom_charge'.
     *
     * @test
     * CMD: php artisan test --filter=storeCustomChargeInTransaction_updatesExistingInitializedRow
     */
    public function storeCustomChargeInTransaction_updatesExistingInitializedRow(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $initId = $this->insertInitializedRow($builder->bookingId);
        $pi     = $this->fakePaymentIntent();
        $txId   = TransactionService::storeCustomChargeInTransaction(
            $builder->bookingId, 10.00, 0.98, 'Card', $pi
        );

        $this->assertEquals($initId, (int) $txId, 'Should return the updated Initialized row id');

        $row = DB::table('transactions')->where('id', $initId)->first();
        $this->assertEquals('Completed',     $row->status);
        $this->assertEquals('custom_charge', $row->charge_type);
        $this->assertEquals(10.98, (float)   $row->amount);
        $this->assertEquals(0.98,  (float)   $row->fee_amount);
        $this->assertEquals($pi->id,         $row->transaction_number);

        $count = DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->count();
        $this->assertEquals(1, $count, 'Exactly one transaction row should exist');

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * When NO Initialized row exists, storeCustomChargeInTransaction inserts a new row.
     *
     * @test
     * CMD: php artisan test --filter=storeCustomChargeInTransaction_insertsNewRowWhenNoInitializedExists
     */
    public function storeCustomChargeInTransaction_insertsNewRowWhenNoInitializedExists(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $pi   = $this->fakePaymentIntent();
        $txId = TransactionService::storeCustomChargeInTransaction(
            $builder->bookingId, 20.00, 1.80, 'Card', $pi
        );

        $this->assertGreaterThan(0, $txId);
        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertNotNull($row);
        $this->assertEquals('Completed',     $row->status);
        $this->assertEquals('custom_charge', $row->charge_type);
        $this->assertEquals(21.80, (float)   $row->amount);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * Same guard as storePaymentInTransaction: an Initialized row with a non-NULL charge_type
     * must be ignored, and a fresh row inserted for the custom charge.
     *
     * @test
     * CMD: php artisan test --filter=storeCustomChargeInTransaction_insertsNewRowWhenInitializedRowHasChargeType
     */
    public function storeCustomChargeInTransaction_insertsNewRowWhenInitializedRowHasChargeType(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $staleId = $this->insertInitializedRowWithChargeType($builder->bookingId, 'addons');
        $pi      = $this->fakePaymentIntent();
        $txId    = TransactionService::storeCustomChargeInTransaction(
            $builder->bookingId, 12.00, 1.08, 'Card', $pi
        );

        $this->assertNotEquals($staleId, $txId, 'Must not update the row with a non-NULL charge_type');

        $staleRow = DB::table('transactions')->where('id', $staleId)->first();
        $this->assertEquals('Initialized', $staleRow->status);
        $this->assertEquals('addons', $staleRow->charge_type);

        $newRow = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals('Completed', $newRow->status);
        $this->assertEquals('custom_charge', $newRow->charge_type);

        $this->cleanupTransactions($builder->bookingId);
    }

    // ---------------------------------------------------------------
    // storeAddonsPaymentInTransaction
    // ---------------------------------------------------------------

    /**
     * When an Initialized (charge_type=NULL) row exists, storeAddonsPaymentInTransaction
     * must UPDATE it to Completed with charge_type='addons'.
     *
     * @test
     * CMD: php artisan test --filter=storeAddonsPaymentInTransaction_updatesExistingInitializedRow
     */
    public function storeAddonsPaymentInTransaction_updatesExistingInitializedRow(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $booking = \App\Models\TourBooking::find($builder->bookingId);

        $initId = $this->insertInitializedRow($builder->bookingId);
        $pi     = $this->fakePaymentIntent();
        $txId   = TransactionService::storeAddonsPaymentInTransaction(
            $booking, 'Card', 15.00, 1.35, $pi
        );

        $this->assertEquals($initId, (int) $txId, 'Should return the updated Initialized row id');

        $row = DB::table('transactions')->where('id', $initId)->first();
        $this->assertEquals('Completed', $row->status);
        $this->assertEquals('addons',    $row->charge_type);
        $this->assertEquals(16.35, (float) $row->amount);
        $this->assertEquals(1.35,  (float) $row->fee_amount);

        $count = DB::table('transactions')->where('tour_booking_id', $builder->bookingId)->count();
        $this->assertEquals(1, $count, 'Exactly one transaction row should exist');

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * When NO Initialized row exists, storeAddonsPaymentInTransaction inserts a new row.
     *
     * @test
     * CMD: php artisan test --filter=storeAddonsPaymentInTransaction_insertsNewRowWhenNoInitializedExists
     */
    public function storeAddonsPaymentInTransaction_insertsNewRowWhenNoInitializedExists(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $booking = \App\Models\TourBooking::find($builder->bookingId);

        $pi   = $this->fakePaymentIntent();
        $txId = TransactionService::storeAddonsPaymentInTransaction(
            $booking, 'Card', 12.00, 1.08, $pi
        );

        $this->assertGreaterThan(0, $txId);
        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertNotNull($row);
        $this->assertEquals('Completed', $row->status);
        $this->assertEquals('addons',    $row->charge_type);
        $this->assertEquals(13.08, (float) $row->amount);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * Same guard as the other store methods: an Initialized row with a non-NULL charge_type
     * must be ignored, and a fresh row inserted for the addon payment.
     *
     * @test
     * CMD: php artisan test --filter=storeAddonsPaymentInTransaction_insertsNewRowWhenInitializedRowHasChargeType
     */
    public function storeAddonsPaymentInTransaction_insertsNewRowWhenInitializedRowHasChargeType(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $booking = \App\Models\TourBooking::find($builder->bookingId);

        $staleId = $this->insertInitializedRowWithChargeType($builder->bookingId, 'custom_charge');
        $pi      = $this->fakePaymentIntent();
        $txId    = TransactionService::storeAddonsPaymentInTransaction(
            $booking, 'Card', 18.00, 1.62, $pi
        );

        $this->assertNotEquals($staleId, $txId, 'Must not update the row with a non-NULL charge_type');

        $staleRow = DB::table('transactions')->where('id', $staleId)->first();
        $this->assertEquals('Initialized', $staleRow->status);
        $this->assertEquals('custom_charge', $staleRow->charge_type);

        $newRow = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals('Completed', $newRow->status);
        $this->assertEquals('addons', $newRow->charge_type);

        $this->cleanupTransactions($builder->bookingId);
    }

    // ---------------------------------------------------------------
    // Refund methods: fee_amount is always a real number (0 when the fee itself isn't being
    // refunded -- Ticket Refund, Don't Refund, Partial Refund all keep the fee). application_fee_id
    // references a real Stripe application fee object, so it stays null unless a genuine non-zero
    // fee IS being refunded (e.g. 100% Refund), which carries a real value for both columns.
    // ---------------------------------------------------------------

    /**
     * @test
     * CMD: php artisan test --filter=storeRefundInTransaction_storesZeroFeeWhenFeeIsZero
     */
    public function storeRefundInTransaction_storesZeroFeeWhenFeeIsZero(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $txId = TransactionService::storeRefundInTransaction(
            $builder->bookingId, 75.00, 0, 'Card', 'pi_test_keep_fee', 'fee_test_keep', null, false, 'destination'
        );

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals('Refund', $row->status);
        $this->assertEquals(0.0, (float) $row->fee_amount, 'fee_amount must be 0, not null, when the fee is kept rather than refunded');
        $this->assertNull($row->application_fee_id, 'application_fee_id must be null when no fee is being refunded');

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * @test
     * CMD: php artisan test --filter=storeRefundInTransaction_storesRealFeeWhenFeeIsRefunded
     */
    public function storeRefundInTransaction_storesRealFeeWhenFeeIsRefunded(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $txId = TransactionService::storeRefundInTransaction(
            $builder->bookingId, 90.00, 8.10, 'Card', 'pi_test_full_fee', 'fee_test_full', null, false, 'destination'
        );

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals(8.10, (float) $row->fee_amount, 'A genuine non-zero fee refund keeps its real amount');
        $this->assertEquals('fee_test_full', $row->application_fee_id);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * @test
     * CMD: php artisan test --filter=storeAddonsRefundInTransaction_storesZeroFeeWhenFeeIsZero
     */
    public function storeAddonsRefundInTransaction_storesZeroFeeWhenFeeIsZero(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $booking = \App\Models\TourBooking::find($builder->bookingId);
        $bookingAddonProxy = (object) ['payment_method' => 'card'];

        $txId = TransactionService::storeAddonsRefundInTransaction($booking, $bookingAddonProxy, [
            'price' => 25.00,
            'fee' => 0,
            'transaction_id' => 'pi_test_addon_keep_fee',
            'application_fee_id' => 'fee_test_addon_keep',
        ]);

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals(25.00, (float) $row->amount);
        $this->assertEquals(0.0, (float) $row->fee_amount, 'A Ticket Refund/Don\'t Refund/Partial Refund add-on keeps its fee -- must store 0, not null');
        $this->assertNull($row->application_fee_id);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * @test
     * CMD: php artisan test --filter=storeAddonsRefundInTransaction_storesRealFeeWhenFeeIsRefunded
     */
    public function storeAddonsRefundInTransaction_storesRealFeeWhenFeeIsRefunded(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;
        $booking = \App\Models\TourBooking::find($builder->bookingId);
        $bookingAddonProxy = (object) ['payment_method' => 'card'];

        $txId = TransactionService::storeAddonsRefundInTransaction($booking, $bookingAddonProxy, [
            'price' => 25.00,
            'fee' => 2.25,
            'transaction_id' => 'pi_test_addon_full_fee',
            'application_fee_id' => 'fee_test_addon_full',
        ]);

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals(2.25, (float) $row->fee_amount, 'A 100% Refund add-on genuinely refunds its fee -- keeps the real amount');
        $this->assertEquals('fee_test_addon_full', $row->application_fee_id);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * @test
     * CMD: php artisan test --filter=storeCustomChargeRefundInTransaction_storesZeroFeeWhenFeeIsZero
     */
    public function storeCustomChargeRefundInTransaction_storesZeroFeeWhenFeeIsZero(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $txId = TransactionService::storeCustomChargeRefundInTransaction(
            $builder->bookingId, 40.00, 0, 'Card', 'pi_test_cc_keep_fee', 'fee_test_cc_keep'
        );

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals(0.0, (float) $row->fee_amount);
        $this->assertNull($row->application_fee_id);

        $this->cleanupTransactions($builder->bookingId);
    }

    /**
     * @test
     * CMD: php artisan test --filter=storeCustomChargeRefundInTransaction_storesRealFeeWhenFeeIsRefunded
     */
    public function storeCustomChargeRefundInTransaction_storesRealFeeWhenFeeIsRefunded(): void
    {
        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        static::$builders[] = $builder;

        $txId = TransactionService::storeCustomChargeRefundInTransaction(
            $builder->bookingId, 40.00, 3.60, 'Card', 'pi_test_cc_full_fee', 'fee_test_cc_full'
        );

        $row = DB::table('transactions')->where('id', $txId)->first();
        $this->assertEquals(3.60, (float) $row->fee_amount);
        $this->assertEquals('fee_test_cc_full', $row->application_fee_id);

        $this->cleanupTransactions($builder->bookingId);
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
