<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: the SAME "Don't Refund pax removal, then full cancel" bug as
 * TicketRefundCancelAfterDontRefundPaxRemovalTest (Card), but in TicketRefundCancelService's
 * Cash/Invoiced/Wire Transfer branch. `$net_amount = $net_amount_split - $ref;` netted only
 * money genuinely already refunded ($ref, correctly 0 for a Don't Refund removal -- no money
 * ever moved) against the ORIGINAL, never-decremented payment total ($net_amount_split) -- with
 * no mechanism to exclude a Don't-Refund-removed pax's forfeited ticket cost. A later full
 * cancel via "Ticket Cost" then refunded the ENTIRE original amount, including the pax whose
 * cost the business had already permanently kept.
 * Concretely: 3 tourists @ $75 ($225 ticket) paid Cash, then 1 pax removed via "Don't Refund"
 * (customer gets nothing back for that pax), leaving 2 pax ($150 ticket cost) still owed at
 * full cancel -- but the buggy formula refunded the full original $225.
 */
class TicketRefundCancelAfterDontRefundPaxRemovalCashTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_full_cancel_excludes_ticket_cost_of_pax_removed_via_dont_refund_on_cash_booking(): void
    {
        $this->authAsAdmin();

        // 3 tourists @ $75 = $225 ticket, Cash.
        $booking = (new CreateABooking())
            ->withTourists(3)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Simulate: 1 pax already removed via "Don't Refund" -- no money moves, details->amount
        // on the linked history row is zeroed (same as the Card scenario; DeletePersonDontRefundService
        // is payment-method-agnostic).
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'tourists' => 2,
            'operation' => 'Refund',
            'ticket_cost' => 0,
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Cash',
            'payment_method' => "Don't Refund",
            'charge_type' => null,
            'details' => json_encode([
                'amount' => 0,
                'refund_type' => "Don't Refund",
                'fees' => 0,
                'refund_keep_us' => 75.00,
            ]),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // Current pax count reflects the removal; total intentionally stays at the original
        // $225 -- matching production (the reported bug's own screenshot shows Booking Total
        // unchanged at cancel time).
        DB::table('tour_bookings_master')->where('id', $bookingId)->update([
            'tourists' => 2,
        ]);

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'full_refund', // "Ticket Refund" / "Ticket Cost"
                'comment' => "ticket refund after don't refund pax removal test (cash)",
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $this->assertNotNull($cancelledRow, 'Expected a Cancelled history row to be recorded');
            $details = json_decode($cancelledRow->details, true);
            $this->assertEquals(
                150.00,
                (float) $details['amount'],
                'Refund recorded must be just the 2 remaining pax\' ticket cost ($150) -- not $225 (wrongly also refunding the pax whose ticket cost was already permanently kept via the earlier Don\'t Refund removal)'
            );

            $refundTransaction = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Refund')
                ->latest('id')
                ->first();
            $this->assertNotNull($refundTransaction);
            $this->assertEquals(150.00, (float) $refundTransaction->amount);
        } finally {
            $booking->cleanup();
        }
    }
}
