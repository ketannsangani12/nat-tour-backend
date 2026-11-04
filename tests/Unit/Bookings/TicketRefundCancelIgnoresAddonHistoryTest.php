<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: TicketRefundCancelService's Cash/Invoiced/Wire-Transfer branch recomputed
 * $RefundHistory with NO charge_type scoping (`->where('operation', 'Refund')->get()`), unlike
 * its own top-of-function definition and WeatherRefundCancelService's equivalent, both of which
 * correctly scope to ticket-side refunds only (NULL/'discount' charge_type). That let a PRIOR
 * add-on refund (charge_type='addons', e.g. from an earlier pax removal) get summed into $ref
 * alongside genuine ticket refunds, so the remaining ticket cost still owed at cancel time came
 * out short by however much unrelated add-on money had already been refunded -- e.g. cancelling a
 * booking with a $75 ticket still owed (after a prior pax removal) but a $15 add-on refund
 * already on record only refunded $60 instead of the correct $75.
 */
class TicketRefundCancelIgnoresAddonHistoryTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_ticket_refund_amount_ignores_prior_addon_refund_history(): void
    {
        $this->authAsAdmin();

        // 2 tourists @ $75 = $150 ticket, Cash.
        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Simulate: 1 pax already removed (ticket refund $75, charge_type NULL) AND that pax's
        // $15 addon already refunded separately (charge_type 'addons').
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'tourists' => 1,
            'operation' => 'Refund',
            'ticket_cost' => 75.00,
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Cash',
            'payment_method' => '100 % Refund',
            'charge_type' => null,
            'details' => json_encode(['amount' => 75.00, 'refund_type' => '100 % Refund']),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        DB::table('tour_booking_history')->insert([
            'tour_booking_id' => $bookingId,
            'tourists' => 1,
            'operation' => 'Refund',
            'ticket_cost' => 0,
            'platform_fees' => 0,
            'discount' => 0,
            'permit_fees' => 0,
            'processing_fees' => 0,
            'payment_type' => 'Cash',
            'payment_method' => '100 % Refund',
            'charge_type' => 'addons',
            'details' => json_encode(['amount' => 15.00, 'refund_type' => '100 % Refund']),
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // Reflect the post-removal booking state (1 pax, $75 total remaining).
        DB::table('tour_bookings_master')->where('id', $bookingId)->update([
            'tourists' => 1,
            'total' => 75.00,
            'booking_total' => 75.00,
        ]);

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => 'full_refund', // "Ticket Refund" / "Ticket Cost"
                'is_cash' => 'Cash',
                'comment' => 'ticket refund ignores addon history test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $details = json_decode($cancelledRow->details, true);
            $this->assertEquals(
                75.00,
                (float) $details['amount'],
                'Ticket Refund at cancel must refund the remaining $75 ticket cost, not be reduced by the unrelated $15 addon refund already recorded'
            );
        } finally {
            $booking->cleanup();
        }
    }
}
