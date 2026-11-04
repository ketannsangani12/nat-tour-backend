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
 * Regression: the SAME bug as TicketRefundCancelAfterDontRefundPaxRemovalTest, but in
 * TicketRefundCancelService's MULTI-transaction Card branch. That loop DID have an existing
 * mechanism intended to net a "Don't Refund" removal's ticket cost out of
 * `$histPaymentDetails->ticket_cost` (`$dontrefundamount`, sourced from the linked $0 Refund
 * transaction's history row) -- but it read `details->amount`, which is always zeroed to 0 for
 * a Don't Refund removal, so the safeguard never actually fired.
 * Concretely: transaction A charges 2 pax @ $75 ($150 ticket); transaction B (a separately
 * charged, later-added pax) charges 1 pax @ $75 ($75 ticket). One pax on transaction A is then
 * removed via "Don't Refund" (no money moves). Cancelling the remaining 2 pax (1 on each
 * transaction) via "Ticket Cost" should refund $150 total ($75 + $75) -- but the buggy formula
 * refunded transaction A's full original $150, for a wrong $225 total.
 */
class TicketRefundCancelMultiCardAfterDontRefundPaxRemovalTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_cancel_across_multiple_card_transactions_excludes_ticket_cost_of_pax_removed_via_dont_refund(): void
    {
        $this->authAsAdmin();

        // Transaction A: 2 tourists @ $75 = $150 ticket, Card.
        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;
        $transactionANumber = DB::table('transactions')->where('id', $booking->transactionId)->value('transaction_number');

        // Transaction B: a 3rd pax added later, charged separately to a different card.
        $transactionBNumber = 'TEST-B-' . uniqid();
        $transactionBId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $bookingId,
            'amount' => 75.00,
            'transaction_number' => $transactionBNumber,
            'via' => 'Card',
            'payment_type' => 2,
            'response_init' => '{}',
            'response_end' => '{}',
            'response_end_datetime' => now(),
            'status' => 'Completed',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'transaction_id' => $transactionBId,
            'tourists' => 1,
            'operation' => 'Payment',
            'ticket_cost' => 75.00,
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Card',
            'payment_method' => 'Full Payment',
            'details' => json_encode([
                'payment_type' => 'Card',
                'payment_method' => 'Full Payment',
                'created_by_with_name' => 'Test: (Admin)',
                'amount' => 75.00,
            ]),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // 1 pax on transaction A removed via "Don't Refund" -- no money moves, a $0 Refund
        // transaction row is created against transaction A's own transaction_number.
        $zeroTransactionId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $bookingId,
            'amount' => 0,
            'fee_amount' => 0,
            'transaction_number' => $transactionANumber,
            'via' => 'Card',
            'payment_type' => 2,
            'status' => 'Refund',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'transaction_id' => $zeroTransactionId,
            'tourists' => 2,
            'operation' => 'Refund',
            'ticket_cost' => 0,
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Card',
            'payment_method' => "Don't Refund",
            'charge_type' => null,
            'details' => json_encode([
                'amount' => 0,
                'refund_type' => "Don't Refund",
                'fees' => 0,
                'refund_keep_us' => 85.00,
            ]),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // Booking now has 2 remaining pax (1 on transaction A, 1 on transaction B).
        DB::table('tour_bookings_master')->where('id', $bookingId)->update([
            'tourists' => 2,
        ]);

        $capturedCalls = [];
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('refundPayment')
            ->willReturnCallback(function (array $params, string $mode) use (&$capturedCalls) {
                $capturedCalls[] = $params;
                return (object) ['id' => 're_test_' . uniqid()];
            });
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'full_refund', // "Ticket Refund" / "Ticket Cost"
                'comment' => "ticket refund multi-card after don't refund pax removal test",
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $this->assertCount(2, $capturedCalls, 'Expected one Stripe refund call per Card transaction');

            $amountsByPaymentIntent = [];
            foreach ($capturedCalls as $call) {
                $amountsByPaymentIntent[$call['payment_intent']] = $call['amount'];
            }

            $this->assertEquals(
                7500,
                $amountsByPaymentIntent[$transactionANumber] ?? null,
                'Transaction A must refund only its remaining ticket cost ($150 - $75 already forfeited by the Don\'t Refund removal = $75) -- not $150 (wrongly also refunding the pax whose ticket cost was already permanently kept)'
            );
            $this->assertEquals(
                7500,
                $amountsByPaymentIntent[$transactionBNumber] ?? null,
                'Transaction B (never touched by the earlier pax removal) must refund its full $75 ticket cost'
            );
        } finally {
            $booking->cleanup();
        }
    }
}
