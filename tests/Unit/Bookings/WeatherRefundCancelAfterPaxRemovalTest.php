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
 * Regression: WeatherRefundCancelService's Card/single-transaction branch ("100% Refund" cancel
 * option) computed the refund as `$transTicketCost + $transServiceCommission` -- the FULL
 * original charge amount from the transaction's first Payment history row -- without netting out
 * `$ref` (the total already refunded by an earlier partial refund, e.g. Remove Pax), even though
 * $ref is computed right above it and used only for a gating check. Concretely: 2 tourists @ $75
 * ($150 ticket + $20 fee = $170 charged) on Card, then 1 pax removed via a ticket-cost-only
 * refund ($75 back, its $10 platform fee kept as revenue, never refunded) left $95 unrefunded on
 * the charge -- but the buggy formula computed $150 + $20 = $170, exceeding the unrefunded amount
 * and getting rejected by Stripe with "Refund amount ($X) is greater than unrefunded amount on
 * charge ($Y)". A first fix pass subtracted $ref but stopped there, producing $95 -- which does
 * fit under Stripe's ceiling but wrongly hands back the $10 platform fee that was already kept as
 * revenue (not refunded, not charged separately) by the earlier removal. The correct amount also
 * nets out that already-kept fee: $150 + $20 - $75 - $10 = $85 -- exactly the remaining pax's own
 * ticket ($75) plus their own share of the fee ($10), leaving the first pax's kept fee alone.
 *
 * Also covers a second, previously-undiscovered bug in the SAME call: CancelTransactionUpdateService
 * ::run() -- the shared helper this method (and every other CancelBooking service) uses to write the
 * refund's own `transactions` row -- never set `payment_mode` at all, always defaulting to
 * 'destination' regardless of the real, already-persisted original transaction's mode. For a
 * platform_held (2027+) booking this meant the refund row was silently mis-recorded even though the
 * correct dollar amount was refunded and the real Stripe refund call itself correctly read the
 * original transaction's mode -- only the LOCAL ledger row was wrong.
 */
class WeatherRefundCancelAfterPaxRemovalTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_100_percent_cancel_nets_out_both_prior_refund_and_prior_kept_fee(): void
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

        // Original Payment history row: $150 ticket + $20 platform fee = $170 charged.
        DB::table('tour_booking_history')
            ->where('transaction_id', $booking->transactionId)
            ->where('operation', 'Payment')
            ->update(['platform_fees' => 20.00]);

        // Simulate: 1 pax already removed via a ticket-cost-only refund -- $75 ticket cost sent
        // back to the card, its $10 platform fee kept as revenue (never charged separately, never
        // refunded).
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
        // original $150 -- matching production, where this exact mismatch is what lets the buggy
        // full-amount refund even get attempted.
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
                'refund_type' => '100_refund', // "100% Refund"
                'comment' => '100 percent refund after pax removal test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $this->assertNotNull($capturedParams, 'Expected the cancellation to call refundPayment()');
            $this->assertEquals(
                8500,
                $capturedParams['amount'],
                'Refund sent to Stripe must net out both the $75 already refunded AND the $10 fee already kept as revenue by the prior pax removal ($150 + $20 - $75 - $10 = $85) -- not $170 (ignoring the prior refund entirely) and not $95 (correctly excluding the ticket cost already refunded but wrongly still handing back the already-kept fee)'
            );

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $details = json_decode($cancelledRow->details, true);
            $this->assertEquals(85.00, (float) $details['amount']);

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
