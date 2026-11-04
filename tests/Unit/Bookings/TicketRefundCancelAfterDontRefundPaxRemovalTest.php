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
 * Regression: TicketRefundCancelService's Card/single-transaction branch had NO mechanism to
 * exclude a "Don't Refund" pax removal's ticket cost from a later full cancellation. A "Don't
 * Refund" removal keeps the removed pax's ticket cost as pure business revenue -- no Stripe
 * refund happens, `details->amount` on that history row is zeroed to 0 -- so `$ref` (money
 * already refunded, netted out of the original ticket-cost basis) never counted it. But
 * `$transTicketCost` (the ORIGINAL Payment row's ticket cost) is never decremented when a pax
 * is removed, so it still included that forfeited pax's cost. A later full cancel via "Ticket
 * Cost" then handed the customer money back for a pax whose cost the business had already
 * permanently kept.
 * Concretely: 3 tourists @ $75 ($225 ticket) charged on Card, then 1 pax removed via "Don't
 * Refund" (customer gets nothing back for that pax), leaving 2 pax ($150 ticket cost) still
 * owed at full cancel -- but the buggy formula refunded the full original $225.
 */
class TicketRefundCancelAfterDontRefundPaxRemovalTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_cancel_excludes_ticket_cost_of_pax_removed_via_dont_refund(): void
    {
        $this->authAsAdmin();

        // 3 tourists @ $75 = $225 ticket, Card.
        $booking = (new CreateABooking())
            ->withTourists(3)
            ->withRate(75.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        $originalTransactionNumber = DB::table('transactions')->where('id', $booking->transactionId)->value('transaction_number');

        // Simulate: 1 pax already removed via "Don't Refund" -- no money moves, a $0 Refund
        // transaction row is created (as DeletePersonDontRefundService's caller does), and the
        // linked history row's details->amount is zeroed.
        $zeroTransactionId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $bookingId,
            'amount' => 0,
            'fee_amount' => 0,
            'transaction_number' => $originalTransactionNumber,
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

        // Current pax count reflects the removal; booking_total intentionally stays at the
        // original $225 (Card removals don't decrement it here) -- matching production.
        DB::table('tour_bookings_master')->where('id', $bookingId)->update([
            'tourists' => 2,
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
                'comment' => "ticket refund after don't refund pax removal test",
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $this->assertNotNull($capturedParams, 'Expected the cancellation to call refundPayment()');
            $this->assertEquals(
                15000,
                $capturedParams['amount'],
                'Refund sent to Stripe must be just the 2 remaining pax\' ticket cost ($150) -- not $225 (wrongly also refunding the pax whose ticket cost was already permanently kept via the earlier Don\'t Refund removal)'
            );

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $details = json_decode($cancelledRow->details, true);
            $this->assertEquals(150.00, (float) $details['amount']);
        } finally {
            $booking->cleanup();
        }
    }
}
