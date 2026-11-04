<?php

namespace Tests\Unit\Bookings;

use App\Exceptions\Core\GeneralCoreException;
use App\Repositories\TourBooking\TourBookingRepository;
use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: applying/increasing a discount refunds a % of the TICKET cost, but neither
 * editDiscount() nor applyDiscount()/editDiscountRefund() ever checked whether the ticket
 * cost had already been refunded elsewhere (Partial Refund, Remove Pax, etc) before issuing
 * that refund -- their only guard compared the WHOLE booking's charges to its refunds across
 * every charge_type combined, which can't tell "tickets are done, but add-ons/Don't-Refund
 * settlements are still outstanding" apart from "genuinely nothing left to refund at all".
 *
 * Confirmed live on booking D-71132 (dixies tenant): a pax was removed with a 100% ticket
 * refund, fully zeroing the remaining pax's ticket cost, and a 10% discount was then applied
 * anyway -- its $8.05 refund had no ticket money left to legitimately come from, and silently
 * drew down a separately-tracked "Don't Refund" add-on settlement instead.
 *
 * BookingService::ensureDiscountRefundWithinTicketAvailability() closes this by checking the
 * discount's own refund amount against calculateRemainingTotalTicketCost() -- the same
 * ticket-only "net available" figure PartialRefundCancelService already caps ticket refunds
 * against -- before editDiscount() even returns its confirm-dialog preview, and again in
 * applyDiscount()/editDiscountRefund() right before anything is written/refunded.
 */
class DiscountRefundBlockedWhenTicketAlreadyRefundedTest extends TestCase
{
    public function test_editDiscount_blocks_a_discount_refund_once_the_ticket_cost_is_already_refunded(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $bookingId = $booking->bookingId;

        try {
            // Simulate the exact D-71132 state: the ticket side has already been refunded down
            // to $0 elsewhere (Partial Refund / Remove Pax), so tour_bookings_master.total --
            // what calculateRemainingTotalTicketCost() reads as "still available" for a
            // single-transaction Card booking -- is already 0, even though the nominal per-pax
            // tour_bookings_transact.total (what the discount % is computed FROM) is untouched.
            DB::table('tour_bookings_master')->where('id', $bookingId)->update(['total' => 0]);

            $request = new Request();
            $request->merge([
                'bookingID' => $bookingId,
                'edit_discount_type' => 'Percentage',
                'edit_discount_percentage' => 10,
                'payment_method' => 'Card',
            ]);

            $thrown = null;
            try {
                app(TourBookingRepository::class)->editDiscount($request);
            } catch (GeneralCoreException $e) {
                $thrown = $e;
            }

            $this->assertNotNull($thrown, 'editDiscount() must reject a discount refund with no ticket cost left to draw from');
            $this->assertStringContainsString('still available to discount', $thrown->message());

            // Nothing should have been written -- the guard fires in the preview step, before
            // any transaction/history/discount row is created.
            $this->assertSame(0, DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('operation', 'Refund')->count());
            $this->assertSame(1, DB::table('transactions')->where('tour_booking_id', $bookingId)->count(), 'Only the original payment transaction should exist');
        } finally {
            $booking->cleanup();
        }
    }

    public function test_editDiscount_still_allows_a_discount_refund_when_ticket_cost_is_available(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $bookingId = $booking->bookingId;

        try {
            // Ticket cost was never touched -- the full $50 is still available.
            $request = new Request();
            $request->merge([
                'bookingID' => $bookingId,
                'edit_discount_type' => 'Percentage',
                'edit_discount_percentage' => 10,
                'payment_method' => 'Card',
            ]);

            $response = app(TourBookingRepository::class)->editDiscount($request);

            // A normal preview response (not an exception) -- the guard must not false-positive
            // on a booking whose ticket cost was never refunded.
            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('discountHistory', $data);
            $this->assertEquals('refund', $data['discountHistory']['payment_type']);
        } finally {
            $booking->cleanup();
        }
    }

    public function test_ensureDiscountRefundWithinTicketAvailability_throws_only_when_amount_exceeds_available(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withRate(50.00)->create();
        $bookingId = $booking->bookingId;

        try {
            // Sanity check on the helper directly: with the full $50 still available...
            BookingService::ensureDiscountRefundWithinTicketAvailability($bookingId, 5.00); // does not throw
            $this->assertTrue(true);

            DB::table('tour_bookings_master')->where('id', $bookingId)->update(['total' => 0]);

            $this->expectException(\Exception::class);
            $this->expectExceptionMessageMatches('/still available to discount/');
            BookingService::ensureDiscountRefundWithinTicketAvailability($bookingId, 5.00);
        } finally {
            $booking->cleanup();
        }
    }
}
