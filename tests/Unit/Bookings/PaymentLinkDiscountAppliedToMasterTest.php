<?php

namespace Tests\Unit\Bookings;

use App\Models\TourBooking;
use App\Services\Booking\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): Make Payment → "Pay with Link" with a discount entered (e.g.
 * Amount $4 on a $75 affiliate booking) sent the link for the full undiscounted amount and never
 * stored the discount on tour_bookings_master — handleMakePaymentSection() ran before any
 * discount handling. DiscountService::applyPaymentLinkDiscount() now stores it first.
 */
class PaymentLinkDiscountAppliedToMasterTest extends TestCase
{
    private function request(array $extra): Request
    {
        $request = new Request();
        $request->merge(array_merge(['payment_method' => 'Payment Link'], $extra));
        return $request;
    }

    private function rows(int $bookingId): array
    {
        $row = DB::table('tour_bookings_master')->where('id', $bookingId)->first();
        return [[$row], $row];
    }

    /** @test */
    public function fixed_amount_discount_is_stored_on_master_and_reduces_the_link_base(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        try {
            DB::table('tour_bookings_master')->where('id', $booking->bookingId)
                ->update(['total' => 75, 'booking_total' => 75, 'discount2_value' => 0, 'discount2_percentage' => 0]);
            [$tourBooking, $info] = $this->rows($booking->bookingId);

            $applied = DiscountService::applyPaymentLinkDiscount($this->request([
                'bookingID' => $booking->bookingId, 'discount_type' => 'Fixed Money', 'discount_fixed_money' => 4,
            ]), $tourBooking, $info);

            $this->assertEqualsWithDelta(4.00, $applied, 0.001);
            $master = TourBooking::find($booking->bookingId);
            $this->assertEqualsWithDelta(71.00, (float) $master->total, 0.001);
            $this->assertEqualsWithDelta(71.00, (float) $master->booking_total, 0.001);
            $this->assertEqualsWithDelta(4.00, (float) $master->discount2_value, 0.001);
            $this->assertEqualsWithDelta(0.00, (float) $master->discount2_percentage, 0.001);
            $this->assertEqualsWithDelta(71.00, (float) $tourBooking[0]->total, 0.001, 'Link amount must be computed from the discounted total');
            $this->assertEqualsWithDelta(4.00, (float) $info->discount2_value, 0.001);
        } finally {
            $booking->cleanup();
        }
    }

    /** @test */
    public function percentage_discount_stores_the_percentage(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        try {
            DB::table('tour_bookings_master')->where('id', $booking->bookingId)
                ->update(['total' => 80, 'booking_total' => 80, 'discount2_value' => 0, 'discount2_percentage' => 0]);
            [$tourBooking, $info] = $this->rows($booking->bookingId);

            DiscountService::applyPaymentLinkDiscount($this->request([
                'bookingID' => $booking->bookingId, 'discount_type' => 'Percentage', 'discount_percentage' => 10,
            ]), $tourBooking, $info);

            $master = TourBooking::find($booking->bookingId);
            $this->assertEqualsWithDelta(72.00, (float) $master->total, 0.001);
            $this->assertEqualsWithDelta(8.00, (float) $master->discount2_value, 0.001);
            $this->assertEqualsWithDelta(10.00, (float) $master->discount2_percentage, 0.001);
        } finally {
            $booking->cleanup();
        }
    }

    /** @test */
    public function no_discount_other_methods_or_pending_link_resend_leave_the_booking_untouched(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        try {
            DB::table('tour_bookings_master')->where('id', $booking->bookingId)
                ->update(['total' => 75, 'booking_total' => 75, 'discount2_value' => 0, 'discount2_percentage' => 0]);

            $cases = [
                ['bookingID' => $booking->bookingId],
                ['bookingID' => $booking->bookingId, 'discount_type' => 'Fixed Money', 'discount_fixed_money' => ''],
                ['bookingID' => $booking->bookingId, 'discount_type' => 'Fixed Money', 'discount_fixed_money' => 4, 'payment_method' => 'Card'],
                ['bookingID' => $booking->bookingId, 'discount_type' => 'Fixed Money', 'discount_fixed_money' => 4, 'remaining_link_id' => 1],
            ];
            foreach ($cases as $case) {
                [$tourBooking, $info] = $this->rows($booking->bookingId);
                $this->assertSame(0.0, DiscountService::applyPaymentLinkDiscount($this->request($case), $tourBooking, $info));
            }

            $master = TourBooking::find($booking->bookingId);
            $this->assertEqualsWithDelta(75.00, (float) $master->total, 0.001);
            $this->assertEqualsWithDelta(0.00, (float) $master->discount2_value, 0.001);
        } finally {
            $booking->cleanup();
        }
    }

    /** @test */
    public function discount_above_the_ticket_cost_is_rejected(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        try {
            DB::table('tour_bookings_master')->where('id', $booking->bookingId)
                ->update(['total' => 75, 'booking_total' => 75, 'discount2_value' => 0, 'discount2_percentage' => 0]);
            [$tourBooking, $info] = $this->rows($booking->bookingId);

            $this->expectException(\Exception::class);
            DiscountService::applyPaymentLinkDiscount($this->request([
                'bookingID' => $booking->bookingId, 'discount_type' => 'Fixed Money', 'discount_fixed_money' => 100,
            ]), $tourBooking, $info);
        } finally {
            $this->assertEqualsWithDelta(75.00, (float) TourBooking::find($booking->bookingId)->total, 0.001);
            $booking->cleanup();
        }
    }
}
