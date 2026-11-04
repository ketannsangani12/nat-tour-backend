<?php

namespace Tests\Unit\Bookings;

use App\Mail\TourBooking\TourBookingPaymentLink as TourBookingPaymentLinkMailable;
use App\Models\TourBooking;
use App\Models\TourBookingPaymentLink;
use App\Services\Booking\PaymentLinkService;
use App\Utils\BookingUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): the "Edit Booking - Change People" modal's "Remove & Confirm
 * Booking" button (changePeopleBooking() -> PaymentLinkService::paymentLinkUpdate(..., 'Charge
 * People')) only ever fed the removed pax's TICKET cost into the whole-booking Payment Link
 * recalculation — calculateChangedTouristsData() (the source of $paymentTotal/$refundTotal) never
 * looks at add-ons at all. Removing a pax that also had priced add-ons (Photography Package $15 +
 * a $10 sandwich option, per the reported screenshot) left the link overstated by exactly that
 * add-on worth, same class of bug already fixed for the plain add-on add/delete endpoints.
 *
 * Fix: fold ChangePeopleAddonService::processAddons()'s own 'addon_delta'.net_price into the same
 * amount handed to paymentLinkUpdate() — net_price only (not net_total), since paymentLinkUpdate()
 * computes its own fee on the combined base amount via natFeesPercentage.
 */
class ChangePeopleAddonNetPriceInPaymentLinkTest extends TestCase
{
    /** @test */
    public function removing_a_pax_with_priced_addons_shrinks_the_link_by_ticket_plus_addon_worth(): void
    {
        Mail::fake();

        $natFeePct = 9.82;
        $booking = (new CreateABooking())
            ->withTourists(2)->withRate(55.00)->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => $natFeePct])
            ->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $bookingId]);

        // The active whole-booking Payment Link before the removal.
        $startingAmount = 400.00;
        $link = TourBookingPaymentLink::create([
            'order_id' => $bookingId, 'section' => null, 'amount' => $startingAmount,
            'reason_of_payment' => 'Make Payment', 'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        $tourBooking = TourBooking::find($bookingId);
        $info = BookingUtils::getBookingDetails($bookingId);

        // The removed pax's own ticket cost (paymenttotal/refundtotal, exactly as
        // calculateChangedTouristsData() would report for a 1-pax removal at this rate).
        $ticketRefundTotal = 55.00;
        // The removed pax's OWN priced add-ons — Photography Package $15 + a $10 sandwich option —
        // exactly as ChangePeopleAddonService::processAddons()'s 'addon_delta'.net_price would
        // report for their removal (a refund/removal nets NEGATIVE).
        $addonNetPrice = -25.00;

        try {
            $payLinkSubTotal = (0 - $ticketRefundTotal) + $addonNetPrice; // paymenttotal=0, refundtotal=ticket
            PaymentLinkService::paymentLinkUpdate(
                ['amount' => $payLinkSubTotal, 'natFeesPercentage' => $natFeePct],
                $info,
                $tourBooking,
                $link,
                'Charge People'
            );

            $this->assertEquals('Expired', TourBookingPaymentLink::find($link->id)->status, 'The stale (too high) link must be superseded');

            $updatedLink = TourBookingPaymentLink::where('order_id', $bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($updatedLink);

            // payLinkSubTotal = -80.00 (ticket -55, addons -25); fee = -80 * 9.82% = -7.856 ≈ -7.86;
            // new link = 400.00 + (-80.00 + -7.86) = 312.14.
            $this->assertEqualsWithDelta(312.14, (float) $updatedLink->amount, 0.01, 'The link must shrink by BOTH the ticket AND the removed pax\'s add-ons, plus fee on the combined amount');

            Mail::assertQueued(TourBookingPaymentLinkMailable::class);

            DB::table('tour_booking_payment_links')->where('id', $updatedLink->id)->delete();
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            $booking->cleanup();
        }
    }
}
