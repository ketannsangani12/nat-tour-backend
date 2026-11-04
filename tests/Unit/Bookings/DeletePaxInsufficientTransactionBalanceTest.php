<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: deleting pax from the "Edit Booking" delete-pax modal ("Update Booking") while the
 * admin's selected Tour Transaction (checked_transactions) is an add-ons-only PaymentIntent with
 * LESS remaining balance than the ticket refund owed used to silently succeed instead of erroring,
 * for BOTH refund types that move real money on delete ("Weather / 100%" and "Ticket Refund"):
 *
 *  1. $paymentTransCountForBooking (editBooking()'s delete branch) only counted
 *     whereNull('charge_type') transactions, so a booking with exactly one ticket PaymentIntent
 *     plus a separate add-ons-only one (charge_type = 'addons') was treated as having just 1
 *     payment transaction -- skipping BOTH the "must select a transaction" guard and the
 *     balance-sufficiency validation entirely, even though the modal's own Tour Transaction
 *     picker offers the add-ons-only PaymentIntent as a selectable option.
 *  2. The 100_refund Stripe-refund branch then silently shrank the actual refund amount to
 *     whatever balance was left on the selected (insufficient) transaction -- draining it --
 *     while the booking history/ledger still recorded the FULL originally-owed amount, so the
 *     system believed more was refunded than Stripe ever actually returned to the customer.
 *  3. The full_refund ("Ticket Refund") Stripe-refund branch had NO local balance check of its own
 *     at all -- it sent the full ticket amount to Stripe unconditionally, relying purely on
 *     Stripe/the gateway to reject an over-refund with no guarantee that happens, and even when it
 *     does, that's after the tour booking's tourist count/total have already been committed above.
 *
 * All three are fixed: the count now includes charge_type = 'addons' transactions (matching what
 * the modal already lets the admin pick), and both Stripe-refund branches now fail loudly with the
 * same "choose another transaction" message instead of clamping/proceeding unchecked.
 */
class DeletePaxInsufficientTransactionBalanceTest extends TestCase
{
    /**
     * @return array{0: CreateABooking, 1: string} [$booking, $addonOnlyTransactionNumber]
     */
    private function makeBookingWithInsufficientAddonOnlyTransaction(float $addonOnlyBalance): array
    {
        // 4 tourists @ $55 rate + $15 permit fee, no processing/tax/nat fee percentages -- so
        // removing 2 of them owes a $140.00 ticket refund with $0 fees, easy to reason about.
        $booking = (new CreateABooking())
            ->withTourists(4)
            ->withRate(55.00)
            ->withPermitFee(15.00)
            ->withPaymentMethod('Card')
            ->create();

        // A separate add-ons-only PaymentIntent with far less than the $140 ticket refund owed --
        // nowhere near enough to cover it, even though the modal lets the admin pick it anyway.
        $addonOnlyTransactionNumber = 'TEST-ADDONS-' . uniqid();
        DB::table('transactions')->insert([
            'tour_booking_id' => $booking->bookingId,
            'amount' => $addonOnlyBalance,
            'transaction_number' => $addonOnlyTransactionNumber,
            'via' => 'Card',
            'payment_type' => 2,
            'charge_type' => 'addons',
            'response_init' => '{}',
            'response_end' => '{}',
            'response_end_datetime' => now(),
            'status' => 'Completed',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        return [$booking, $addonOnlyTransactionNumber];
    }

    private function assertDeleteWasBlocked(CreateABooking $booking, Request $request): void
    {
        $bookingId = $booking->bookingId;

        $thrown = null;
        try {
            app(TourBookingRepository::class)->editBooking($request);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Deleting pax with an insufficient selected transaction must fail, not silently succeed.');
        $this->assertStringContainsString('choose', strtolower($thrown->getMessage()));

        // Nothing must have moved: the booking's tourist count must be untouched, and no refund
        // history or refund transaction row must exist -- proving this was blocked BEFORE any DB
        // mutation, not partially applied.
        $freshBooking = DB::table('tour_bookings_master')->find($bookingId);
        $this->assertEquals(4, $freshBooking->tourists, 'Tourist count must be unchanged when the delete is blocked.');

        $refundHistoryCount = DB::table('tour_booking_history')
            ->where('tour_booking_id', $bookingId)
            ->where('operation', 'Refund')
            ->count();
        $this->assertSame(0, $refundHistoryCount, 'No refund history row should be written when the delete is blocked.');

        $refundTransactionCount = DB::table('transactions')
            ->where('tour_booking_id', $bookingId)
            ->where('status', 'Refund')
            ->count();
        $this->assertSame(0, $refundTransactionCount, 'No refund transaction row should be written when the delete is blocked.');
    }

    public function test_weather_100_percent_refund_blocks_instead_of_shrinking_the_refund(): void
    {
        [$booking, $addonOnlyTransactionNumber] = $this->makeBookingWithInsufficientAddonOnlyTransaction(30.00);

        try {
            // The gateway must NEVER be called -- the whole point of the fix is that this is
            // caught before any Stripe refund is attempted, not shrunk-and-sent.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $request = new Request();
            $request->merge([
                'bookingID' => $booking->bookingId,
                'action_type' => 'delete',
                'payment_type' => 'Card',
                'is_cash' => '',
                'refund_Type' => '100_refund',
                'checked_transactions' => $addonOnlyTransactionNumber,
                'check' => ['Adult.0' => $booking->rateId, 'Adult.1' => $booking->rateId],
                'total_tourist' => 4, // must differ from count($check)=2, or the "delete all" guard fires
                'api' => 1,
            ]);

            $this->assertDeleteWasBlocked($booking, $request);
        } finally {
            $booking->cleanup();
        }
    }

    public function test_ticket_refund_blocks_instead_of_sending_an_unchecked_over_refund_to_stripe(): void
    {
        [$booking, $addonOnlyTransactionNumber] = $this->makeBookingWithInsufficientAddonOnlyTransaction(20.00);

        try {
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $request = new Request();
            $request->merge([
                'bookingID' => $booking->bookingId,
                'action_type' => 'delete',
                'payment_type' => 'Card',
                'is_cash' => '',
                'refund_Type' => 'full_refund',
                'checked_transactions' => $addonOnlyTransactionNumber,
                'check' => ['Adult.0' => $booking->rateId, 'Adult.1' => $booking->rateId],
                'total_tourist' => 4,
                'api' => 1,
            ]);

            $this->assertDeleteWasBlocked($booking, $request);
        } finally {
            $booking->cleanup();
        }
    }
}
