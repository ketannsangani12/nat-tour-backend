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
 * Regression: TicketRefundCancelService's Card/single-transaction branch accumulated $ref (the
 * total already refunded, to be netted out of $transTicketCost) straight from each prior Refund
 * history row's `details->amount`. For a "100 % Refund" (Weather) pax removal, that JSON `amount`
 * bundles the ticket cost together with the platform fee refunded alongside it (e.g. $85 = $75
 * ticket + $10 fee) -- while the row's own `platform_fees`/`ticket_cost` COLUMNS already record the
 * split correctly. $transTicketCost is a pure ticket-cost basis, so subtracting the fee-inclusive
 * $ref from it double-counted the fee: once because it was genuinely refunded during the pax
 * removal, and again here by shrinking the ticket-cost-only basis by the fee amount too.
 * Concretely: 2 tourists @ $75 ($150 ticket) charged on Card, then 1 pax removed via a Weather
 * "100 % Refund" ($75 ticket cost + $10 platform fee = $85 refunded), leaving exactly $75 ticket
 * cost (the 1 remaining pax's own cost) owed at full cancel -- but the buggy formula computed
 * $150 - $85 = $65, under-refunding the customer by the $10 fee already returned during the pax
 * removal.
 */
class TicketRefundCancelAfterWeatherPaxRemovalTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_cancel_refunds_remaining_ticket_cost_after_weather_pax_removal(): void
    {
        $this->authAsAdmin();

        // 2 tourists @ $75 = $150 ticket, Card.
        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        // Simulate: 1 pax already removed via a Weather "100 % Refund" -- $75 ticket cost AND
        // $10 platform fee sent back together as one $85 Stripe refund. The row's ticket_cost/
        // platform_fees columns record the correct split (as DeletePerson100RefundService does);
        // details->amount stays at the combined $85 (used for display/history totals elsewhere).
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'tourists' => 1,
            'operation' => 'Refund',
            'ticket_cost' => 75.00,
            'platform_fees' => 10.00,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Card',
            'payment_method' => '100 % Refund',
            'charge_type' => null,
            'details' => json_encode([
                'amount' => 85.00,
                'refund_type' => '100 % Refund',
            ]),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // Current pax count reflects the removal; booking_total intentionally stays at the
        // original $150 (Card removals don't decrement it here) -- matching production.
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
                'comment' => 'ticket refund after weather pax removal test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $this->assertNotNull($capturedParams, 'Expected the cancellation to call refundPayment()');
            $this->assertEquals(
                7500,
                $capturedParams['amount'],
                'Refund sent to Stripe must be just the remaining ticket cost ($150 original - $75 ticket cost already refunded = $75) -- not $65 ($150 - $85, wrongly also subtracting the $10 platform fee that was already refunded separately during the pax removal)'
            );

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $details = json_decode($cancelledRow->details, true);
            $this->assertEquals(75.00, (float) $details['amount']);
        } finally {
            $booking->cleanup();
        }
    }
}
