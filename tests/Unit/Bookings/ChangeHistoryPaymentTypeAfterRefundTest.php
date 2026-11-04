<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for D-14966 / booking #14966 (tenant_dixies):
 *
 *   A billable (Invoiced) booking had a pax removed, which logs a Cash/Invoiced
 *   "refund" transaction purely as a ledger adjustment -- no money ever moved
 *   through Stripe. changeHistoryPaymentType() then permanently refused to let
 *   staff switch the booking's payment type, because its refund guard summed
 *   *any* Refund-status transaction regardless of `via`. Staff had to cancel
 *   the booking and rebook instead of just fixing the payment type (the real
 *   booking's history even has a note: "unable to change from billable to
 *   cash -nikki").
 *
 * Fix: the guard now only blocks on refunds where via = 'Card' -- the one case
 * with real gateway state that a payment-type switch could desync. Cash /
 * Invoiced refunds (pax removal, discounts, etc.) are ledger-only and must not
 * block the switch. Covered here in three places sharing the same logic:
 * TourBookingRepository (legacy), V3\TourBookingRepository (current), and
 * StripeService::stripeCreatePaymentIntentForChangePaymentType.
 *
 * A second, related guard in the same function (counting *any*
 * operation = 'Payment' history row) was also over-broad -- add-ons and
 * custom charges log their own 'Payment' row and would likewise lock the
 * booking after the first add-on/custom charge. That's covered here too.
 *
 * Unblocking the guard exposed a third gap: add-on/custom charge/discount
 * transactions and history rows settle independently of the base ticket row
 * and were never updated when the booking's payment type changed, leaving them
 * permanently stuck on the booking's *previous* payment type. Since that
 * previously could never be reached (the guard always blocked it first), this
 * had never fired in production -- covered here too, alongside the guard fixes.
 *
 * CMD: php artisan test --filter=ChangeHistoryPaymentTypeAfterRefundTest
 */
class ChangeHistoryPaymentTypeAfterRefundTest extends TestCase
{
    protected TourBookingRepository $repo;

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

    public function test_ledger_only_invoiced_refund_from_removing_a_pax_no_longer_blocks_payment_type_change(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withPaymentMethod('Invoiced')
            ->withTourists(2)
            ->withRate(70.00)
            ->create();

        try {
            // Simulate DeletePersonPartialRefundService: removing one pax logs a
            // Refund-status transaction against the Invoiced booking, but no card
            // was ever charged -- this is a pure ledger adjustment.
            $refundTransactionId = DB::table('transactions')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'amount' => 70.00,
                'fee_amount' => 0,
                'via' => 'Invoiced',
                'payment_type' => 0,
                'status' => 'Refund',
                'transaction_number' => '',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $builder->bookingId,
                'transaction_id' => $refundTransactionId,
                'operation' => 'Refund',
                'ticket_cost' => 70.00,
                'payment_type' => 'Invoiced',
                'payment_method' => '100 % Refund',
                'details' => json_encode([
                    'amount' => 70,
                    'payment_type' => 'Invoiced',
                    'refund_type' => '100 % Refund',
                    'deleted' => 'Adult (Ages 8 & Up) person 2',
                ]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $request = new Request();
            $request->merge([
                'bookingID' => $builder->bookingId,
                'payment_method' => 'Cash',
            ]);

            $response = $this->repo->changeHistoryPaymentType($request, $builder->historyId);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($builder->bookingId, $response->getData(true)['tourBookingId']);
            $this->assertSame('Cash', DB::table('tour_booking_history')->where('id', $builder->historyId)->value('payment_type'));
        } finally {
            $builder->cleanup();
        }
    }

    public function test_addon_and_custom_charge_payment_rows_no_longer_count_as_a_second_payment(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withPaymentMethod('Cash')
            ->create();

        try {
            // Simulate an add-on purchase after the booking was made: it logs its
            // own operation = 'Payment' row (charge_type = 'addons'), separate from
            // the booking's original ticket payment.
            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $builder->bookingId,
                'operation' => 'Payment',
                'charge_type' => 'addons',
                'ticket_cost' => 10.00,
                'payment_type' => 'Cash',
                'payment_method' => 'Full Payment',
                'details' => json_encode(['operation' => 'added', 'amount' => 10]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $request = new Request();
            $request->merge([
                'bookingID' => $builder->bookingId,
                'payment_method' => 'Pay Later',
            ]);

            $response = $this->repo->changeHistoryPaymentType($request, $builder->historyId);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('Invoiced', DB::table('tour_booking_history')->where('id', $builder->historyId)->value('payment_type'));
        } finally {
            $builder->cleanup();
        }
    }

    public function test_custom_charge_transaction_and_history_cascade_to_the_new_payment_type(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withPaymentMethod('Invoiced')
            ->create();

        try {
            // An active custom charge, settled separately from the base ticket -- must
            // follow the booking's payment type when it changes.
            $customChargeTransactionId = DB::table('transactions')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'amount' => 15.00,
                'fee_amount' => 0,
                'via' => 'Invoiced',
                'payment_type' => 0,
                'status' => 'Completed',
                'charge_type' => 'custom_charge',
                'transaction_number' => '',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $customChargeHistoryId = DB::table('tour_booking_history')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'transaction_id' => $customChargeTransactionId,
                'operation' => 'Payment',
                'charge_type' => 'custom_charge',
                'ticket_cost' => 15.00,
                'payment_type' => 'Invoiced',
                'payment_method' => 'Full Payment',
                'details' => json_encode(['operation' => 'added', 'amount' => 15]),
                'operation_at' => now(),
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            // An already-refunded custom charge -- must NOT be rewritten; it's a
            // historical record of what was actually charged and refunded at the time.
            $refundedCustomChargeTransactionId = DB::table('transactions')->insertGetId([
                'tour_booking_id' => $builder->bookingId,
                'amount' => 5.00,
                'fee_amount' => 0,
                'via' => 'Invoiced',
                'payment_type' => 0,
                'status' => 'Refund',
                'charge_type' => 'custom_charge',
                'transaction_number' => '',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $request = new Request();
            $request->merge([
                'bookingID' => $builder->bookingId,
                'payment_method' => 'Cash',
            ]);

            $response = $this->repo->changeHistoryPaymentType($request, $builder->historyId);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('Cash', DB::table('transactions')->where('id', $customChargeTransactionId)->value('via'));
            $this->assertSame('Cash', DB::table('tour_booking_history')->where('id', $customChargeHistoryId)->value('payment_type'));
            $this->assertSame('Invoiced', DB::table('transactions')->where('id', $refundedCustomChargeTransactionId)->value('via'));
        } finally {
            $builder->cleanup();
        }
    }

    public function test_a_genuine_card_refund_still_blocks_the_payment_type_change(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withPaymentMethod('Cash')
            ->create();

        try {
            // A real Card refund actually moved money through Stripe -- the guard
            // must still block a payment-type switch in this case.
            DB::table('transactions')->insert([
                'tour_booking_id' => $builder->bookingId,
                'amount' => 50.00,
                'fee_amount' => 0,
                'via' => 'Card',
                'payment_type' => 0,
                'status' => 'Refund',
                'transaction_number' => 'TEST-CARD-REFUND-' . uniqid(),
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $request = new Request();
            $request->merge([
                'bookingID' => $builder->bookingId,
                'payment_method' => 'Pay Later',
            ]);

            $thrown = null;
            try {
                $this->repo->changeHistoryPaymentType($request, $builder->historyId);
            } catch (\Exception $e) {
                $thrown = $e;
            }

            $this->assertNotNull($thrown, 'A genuine Card refund should still block the payment type change.');
            $this->assertStringContainsString('one or more refunds done for the booking', $thrown->getMessage());
        } finally {
            $builder->cleanup();
        }
    }
}
