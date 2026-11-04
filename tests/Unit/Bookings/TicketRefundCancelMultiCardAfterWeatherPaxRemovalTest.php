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
 * Regression: the SAME bug as TicketRefundCancelAfterWeatherPaxRemovalTest, but in
 * TicketRefundCancelService's MULTI-transaction Card branch (a booking with more than one
 * separate Card charge, e.g. an original payment plus a later "guest added" charge on a
 * different card). That loop computed each transaction's remaining ticket cost as
 * `$histPaymentDetails->ticket_cost - $totalrefundedamountfortransaction - $dontrefundamount`,
 * where `$totalrefundedamountfortransaction` sums `transactions.amount` for prior Refund rows
 * tied to that transaction_number. For a prior "100 % Refund" (Weather) pax removal, that
 * `amount` is fee-inclusive (ticket + platform fee refunded together as one Stripe refund),
 * while `ticket_cost` is ticket-only -- so netting them double-subtracted the fee.
 *
 * Concretely: transaction A charges 2 pax @ $75 ($150 ticket, $20 fee); transaction B (a
 * separately charged, later-added pax) charges 1 pax @ $75 ($75 ticket, $10 fee). One pax on
 * transaction A is then removed via a Weather "100 % Refund" ($75 ticket + $10 fee = $85
 * refunded together, back to transaction A). Cancelling the remaining 2 pax (1 on each
 * transaction) via "Ticket Cost" should refund $150 total ($75 + $75) -- but the buggy formula
 * computed transaction A's remaining amount as $150 - $85 = $65, under-refunding by the $10 fee
 * already refunded during the pax removal, for a wrong $140 total.
 */
class TicketRefundCancelMultiCardAfterWeatherPaxRemovalTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_cancel_across_multiple_card_transactions_refunds_remaining_ticket_cost_after_weather_pax_removal(): void
    {
        $this->authAsAdmin();

        // Transaction A: 2 tourists @ $75 = $150 ticket, Card.
        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        // Transaction A's own Payment history row didn't record a platform fee by default in the
        // fixture -- set it to $20 to match the scenario (2 pax x $10 fee/pax).
        DB::table('tour_booking_history')
            ->where('id', $booking->historyId)
            ->update(['platform_fees' => 20.00]);

        // Transaction B: a 3rd pax added later, charged separately to a different card.
        $transactionBNumber = 'TEST-B-' . uniqid();
        $transactionBId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $bookingId,
            'amount' => 85.00,
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
            'platform_fees' => 10.00,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Card',
            'payment_method' => 'Full Payment',
            'details' => json_encode([
                'payment_type' => 'Card',
                'payment_method' => 'Full Payment',
                'created_by_with_name' => 'Test: (Admin)',
                'amount' => 85.00,
            ]),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // 1 pax on transaction A removed via a Weather "100 % Refund" -- $75 ticket cost AND $10
        // platform fee sent back together as one $85 Stripe refund against transaction A.
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => 85.00,
            'fee_amount' => 10.00,
            'transaction_number' => $booking->transactionId ? DB::table('transactions')->where('id', $booking->transactionId)->value('transaction_number') : null,
            'via' => 'Card',
            'payment_type' => 2,
            'status' => 'Refund',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
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
                'comment' => 'ticket refund multi-card after weather pax removal test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $this->assertCount(2, $capturedCalls, 'Expected one Stripe refund call per Card transaction');

            $amountsByPaymentIntent = [];
            foreach ($capturedCalls as $call) {
                $amountsByPaymentIntent[$call['payment_intent']] = $call['amount'];
            }

            $transactionANumber = DB::table('transactions')->where('id', $booking->transactionId)->value('transaction_number');

            $this->assertEquals(
                7500,
                $amountsByPaymentIntent[$transactionANumber] ?? null,
                'Transaction A must refund only its remaining ticket cost ($150 - $75 already refunded = $75) -- not $65 ($150 - $85, wrongly also subtracting the $10 fee already refunded during the pax removal)'
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
