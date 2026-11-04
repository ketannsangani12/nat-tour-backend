<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: TicketRefundCancelService's Card/single-transaction branch computed the full
 * cancellation refund as `$transTicketCost + $oldRefundKeepUs`, using the ORIGINAL transaction's
 * ticket cost (from its first Payment history row) and never netting out ticket cost already
 * sent back by an earlier partial refund (e.g. Remove Pax). $ref (the already-refunded total)
 * was computed but discarded -- so a full cancel after a prior pax removal re-refunded money the
 * customer already got back, attempting to refund MORE than remained unrefunded on the Stripe
 * charge and getting rejected with "Refund amount ($X) is greater than unrefunded amount on
 * charge ($Y)". Concretely: 2 tourists @ $75 ($150 ticket) charged on Card, then 1 pax removed
 * via a "Full Refund minus Fees" removal ($75 ticket refunded, $10 of that pax's platform fee
 * recorded as `refund_keep_us` -- kept as revenue, never charged as its own transaction and never
 * refunded) left exactly $75 ticket cost (the 1 remaining pax's own cost) owed at full cancel --
 * but the buggy formula computed $150 + $10 = $160. An earlier fix pass wrongly added
 * $oldRefundKeepUs back into the cash amount (producing $85), mistaking that bookkeeping-only
 * "fee kept as revenue" figure for money still owed to the customer; this test locks in the
 * correct $75, matching just the remaining ticket cost with no double-refund of that kept fee.
 *
 * Also covers a second, previously-undiscovered bug in the SAME call: CancelTransactionUpdateService
 * ::run() -- the shared helper this method (and every other CancelBooking service) uses to write the
 * refund's own `transactions` row -- never set `payment_mode` at all, always defaulting to
 * 'destination' regardless of what the real, already-persisted original transaction's mode actually
 * was. For a platform_held (2027+) booking this meant the refund row was silently mis-recorded even
 * though the correct dollar amount was refunded and the real Stripe refund call itself correctly read
 * the original transaction's mode -- only the LOCAL ledger row was wrong.
 */
class TicketRefundCancelAfterPaxRemovalTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_cancel_refunds_only_remaining_ticket_cost_not_previously_kept_fee(): void
    {
        $this->authAsAdmin();

        // 2 tourists @ $75 = $150 ticket, Card.
        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        // The original charge is platform_held (a 2027+ booking) -- the refund must inherit this
        // exact mode, not silently default to 'destination'.
        DB::table('transactions')->where('id', $booking->transactionId)->update(['payment_mode' => 'platform_held']);

        // Simulate: 1 pax already removed via "Full Refund minus Fees" -- $75 ticket cost sent
        // back to the card. `refund_keep_us: 10` records that $10 of that pax's platform fee is
        // being kept as revenue (reporting only) -- no separate $10 charge or refund ever happened
        // for it, so a later cancellation must not hand it back as cash.
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'tourists' => 1,
            'operation' => 'Refund',
            'ticket_cost' => 75.00,
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Card',
            'payment_method' => 'Full Refund minus Fees',
            'charge_type' => null,
            'details' => json_encode([
                'amount' => 75.00,
                'refund_type' => 'Full Refund minus Fees',
                'refund_keep_us' => 10.00,
            ]),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // Current pax count reflects the removal; booking_total intentionally stays at the
        // original $150 (Card removals don't decrement it here) -- matching production, where
        // this exact mismatch is what lets the buggy full-amount refund even get attempted.
        DB::table('tour_bookings_master')->where('id', $bookingId)->update([
            'tourists' => 1,
        ]);

        $capturedParams = null;
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('refundPayment')
            ->willReturnCallback(function (array $params, string $mode) use (&$capturedParams) {
                $capturedParams = $params;
                return (object) ['id' => 're_test_' . uniqid()];
            });
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'full_refund', // "Ticket Refund" / "Ticket Cost"
                'comment' => 'ticket refund after pax removal test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $this->assertNotNull($capturedParams, 'Expected the cancellation to call refundPayment()');
            $this->assertEquals(
                7500,
                $capturedParams['amount'],
                'Refund sent to Stripe must be just the remaining ticket cost ($150 original - $75 already refunded = $75) -- not $160 (ignoring the prior refund entirely) and not $85 (wrongly adding back the $10 kept-fee bookkeeping figure as if it were still owed in cash)'
            );

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $details = json_decode($cancelledRow->details, true);
            $this->assertEquals(75.00, (float) $details['amount']);

            $refundTransaction = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Refund')
                ->latest('id')
                ->first();
            $this->assertSame(
                'platform_held',
                $refundTransaction->payment_mode,
                'The refund transaction row must inherit the original charge\'s platform_held mode, not default to destination'
            );
        } finally {
            $booking->cleanup();
        }
    }
}
