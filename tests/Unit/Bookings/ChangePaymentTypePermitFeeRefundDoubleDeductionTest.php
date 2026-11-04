<?php

namespace Tests\Unit\Bookings;

use App\Models\BookingTransact;
use App\Services\Booking\BookingService;
use App\Services\Booking\EditBookingService;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: several independent recomputations of a booking's ticket subtotal blindly
 * subtracted the sum of every `refund_permitfee_status` entry from a subtotal built off the
 * CURRENT `permit_fee` -- double-deducting whenever `permit_fee` already reflected the
 * reduction (the common case: every tourist on the transact row was reduced together, so
 * TourBookingRepository::editBooking()'s permit-fee-update branch updates the column to match).
 *
 * `permit_fee` is a SINGLE value per transact row (per rate group), but a reduction can be
 * applied to only SOME of that row's tourists -- when that happens, the column is deliberately
 * left at the pre-reduction fee (see the same editBooking() branch: `$value` is the count of
 * tourists actually reduced, and the column only updates `if ($btset->tourists == $value)`, i.e.
 * only when ALL of them were). Real production data confirms this "mixed" state exists (e.g.
 * transact row 218540 in tenant `kens`: 5 tourists, permit_fee stuck at 8.00, with only 2 of 5
 * `refund_permitfee_status` entries marked 'Y'). Blindly REMOVING the subtraction (an earlier,
 * incomplete version of this fix) would overcharge those 2 tourists' fee a second time.
 *
 * The correct fix, implemented in BookingService::calculatePendingPermitFeeAdjustment(): for each
 * refunded (non-`is_pax_added`) tourist, only count the gap between the CURRENT permit_fee and
 * that tourist's true fee (`new_permit_fee`, or 0 for legacy full-refund entries with no such
 * field). That gap is 0 once the column has caught up (the common, uniform case), and the correct
 * non-zero amount when it hasn't (the mixed case) -- so it can never double-count and never drops
 * a genuinely-still-owed reduction.
 *
 * This shared, corrected helper is used by every independent reimplementation of "recompute
 * ticket subtotal from a BookingTransact row":
 *  - EditBookingService::processCalculationsChangePaymentType() and ...ChangePaymentTypeInvoice()
 *    (the "Switch"/change-payment-type-to-Card action)
 *  - BookingService::calculateSubTotal() (shared by paymentNewBooking() in both the V1 and V3
 *    TourBookingRepository -- the actual "Make Payment" button)
 *  - BookingService::calculateTotalAmount()
 *  - CartService::calculateCartBookingTotal() and CartService::calculateTotalAmount() (cart /
 *    payment-link flows, and read by both Stripe webhook handlers below)
 *  - StripePaymentIntentSucceededForMakePayment's own inline recomputation (the webhook that
 *    finalizes a Card payment after Stripe confirms it)
 */
class ChangePaymentTypePermitFeeRefundDoubleDeductionTest extends TestCase
{
    public function test_subtotal_is_not_double_deducted_after_a_permit_fee_reduction(): void
    {
        $refundEntry = [
            'status' => 'Y',
            'amount' => 5,
            'original_permit_fee' => '20.00',
            'new_permit_fee' => 15,
            'processing_fees' => 0,
            'is_partial_refund' => true,
            'dateTime' => now()->toDateTimeString(),
        ];

        $booking = (new CreateABooking())
            ->withTourists(10)
            ->withRate(49.00)
            ->withPermitFee(15.00)
            ->withPaymentMethod('Pay Later')
            ->withoutTransaction()
            ->withoutHistory()
            ->withTransactOverrides([
                'permit_fee' => 15.00,
                'additional_charge' => 0.00,
                'refund_permitfee_status' => json_encode(array_fill(0, 10, $refundEntry)),
            ])
            ->create();

        try {
            // Rate-level fields the calculation may fall back to (via cashPriceEnabled()) must
            // agree with the transact row so the expected total doesn't depend on that setting.
            DB::table('tour_package_rates')->where('id', $booking->rateId)->update([
                'additional_charge' => 0.00,
                'processing_charge_percentage' => 0,
            ]);

            $bookingT = BookingTransact::with('packageRate')
                ->where('tour_booking_id', $booking->bookingId)
                ->where('deleted_by', 0)
                ->get();

            $info = (object) ['discount2_value' => 0];
            $request = new Request();

            $result = EditBookingService::processCalculationsChangePaymentType(
                $bookingT,
                $request,
                $info,
                isGroupBooking: '0',
                natFeesPercentage: 0,
                stripeFeesPercentage: 0,
                isPartnerBooking: 0
            );

            $this->assertEqualsWithDelta(
                640.00,
                $result['total'],
                0.001,
                'Ticket subtotal must reflect the already-reduced permit_fee once, not subtract the refund history a second time.'
            );

            $freshTransact = DB::table('tour_bookings_transact')->find($booking->transactId);
            $this->assertEqualsWithDelta(640.00, (float) $freshTransact->total, 0.001);
        } finally {
            $booking->cleanup();
        }
    }

    /**
     * BookingService::calculateSubTotal() is the shared helper behind paymentNewBooking() in
     * both the V1 and V3 TourBookingRepository -- i.e. the actual "Make Payment" button. Exact
     * numbers from the real repro booking that triggered this: rate $49, permit_fee reduced
     * 20 -> 15 for all 10 pax (a $50 reduction), additional_charge (tax) $2.94/pax.
     */
    public function test_shared_subtotal_helper_does_not_double_deduct_the_permit_fee_reduction(): void
    {
        $subTotal = BookingService::calculateSubTotal(
            rate: 49.00,
            permitFee: 15.00,
            additionalCharge: 2.94,
            tourists: 10,
            isGroupBooking: false,
            partnerFees: 0
        );

        $this->assertEqualsWithDelta(
            669.40,
            $subTotal,
            0.001,
            'permit_fee already reflects the reduction; refund_permitfee_status must not be subtracted again.'
        );
    }

    /**
     * CartService::calculateCartBookingTotal() feeds both Stripe webhook handlers
     * (StripePaymentIntentSucceededForNewBooking and ...ForMakePayment) and had the same
     * double-deduction bug.
     */
    public function test_cart_booking_total_does_not_double_deduct_the_permit_fee_reduction(): void
    {
        $refundEntry = [
            'status' => 'Y',
            'amount' => 5,
            'original_permit_fee' => '20.00',
            'new_permit_fee' => 15,
            'processing_fees' => 0,
            'is_partial_refund' => true,
            'dateTime' => now()->toDateTimeString(),
        ];

        $booking = (new CreateABooking())
            ->withNewPackage()
            ->withTourists(10)
            ->withRate(49.00)
            ->withPermitFee(15.00)
            ->withPaymentMethod('Pay Later')
            ->withoutTransaction()
            ->withoutHistory()
            ->withTransactOverrides([
                'permit_fee' => 15.00,
                'additional_charge' => 0.00,
                'refund_permitfee_status' => json_encode(array_fill(0, 10, $refundEntry)),
            ])
            ->create();

        try {
            // Force a deterministic package (0% commission, no processing fee) so `total` is
            // purely the ticket subtotal this test is about, not a real tenant package's rates.
            DB::table('tour_packages')->where('id', $booking->packageId)->update([
                'service_commission_percentage' => 0,
            ]);
            DB::table('tour_package_rates')->where('id', $booking->rateId)->update([
                'additional_charge' => 0.00,
                'processing_charge_percentage' => 0,
            ]);

            $result = CartService::calculateCartBookingTotal($booking->bookingId);

            $this->assertEqualsWithDelta(
                640.00,
                $result['total'],
                0.001,
                'Cart booking total must reflect the already-reduced permit_fee once, not subtract the refund history a second time.'
            );
        } finally {
            $booking->cleanup();
        }
    }

    /**
     * Mixed case reproduced from real production data (tenant `kens`, transact row 218540):
     * 5 tourists, only 2 fully refunded (legacy schema -- just `amount`, no `new_permit_fee`,
     * meaning their true fee is 0). Because not ALL 5 were refunded, editBooking() leaves the
     * aggregate permit_fee column at the pre-refund value (8.00) instead of updating it -- so
     * the pending adjustment for those 2 tourists is NOT yet reflected and must still be
     * subtracted once (not zero, and not double-subtracted).
     */
    public function test_pending_adjustment_covers_a_legacy_full_refund_not_yet_reflected_in_permit_fee(): void
    {
        $refundPermitFeeStatus = json_encode([
            ['status' => 'Y', 'amount' => '8.00', 'processing_fees' => 0, 'dateTime' => now()->toDateTimeString()],
            ['status' => 'Y', 'amount' => '8.00', 'processing_fees' => 0, 'dateTime' => now()->toDateTimeString()],
            ['status' => 'N'],
            ['status' => 'N'],
            ['status' => 'N'],
        ]);

        $adjustment = BookingService::calculatePendingPermitFeeAdjustment($refundPermitFeeStatus, 8.00);
        $this->assertEqualsWithDelta(16.00, $adjustment, 0.001);

        $subTotal = BookingService::calculateSubTotal(
            rate: 55.00,
            permitFee: 8.00,
            additionalCharge: 0.00,
            tourists: 5,
            isGroupBooking: false,
            partnerFees: 0,
            refundPermitFeeStatus: $refundPermitFeeStatus
        );

        // True blended total: 2 tourists at $55 (fee zeroed) + 3 tourists at $55+$8.
        $this->assertEqualsWithDelta(299.00, $subTotal, 0.001);
    }

    /**
     * Same shape as above but with the current (newer) schema, where a partial reduction records
     * `original_permit_fee`/`new_permit_fee` per tourist instead of implying a full refund to 0.
     * 6 of 10 tourists reduced $20 -> $15; the column stays at 20 because not all 10 were reduced.
     */
    public function test_pending_adjustment_covers_a_partial_reduction_not_yet_reflected_in_permit_fee(): void
    {
        $reducedEntry = [
            'status' => 'Y',
            'amount' => 5,
            'original_permit_fee' => '20.00',
            'new_permit_fee' => 15,
            'processing_fees' => 0,
            'is_partial_refund' => true,
            'dateTime' => now()->toDateTimeString(),
        ];
        $refundPermitFeeStatus = json_encode(array_merge(
            array_fill(0, 6, $reducedEntry),
            array_fill(0, 4, ['status' => 'N'])
        ));

        $adjustment = BookingService::calculatePendingPermitFeeAdjustment($refundPermitFeeStatus, 20.00);
        $this->assertEqualsWithDelta(30.00, $adjustment, 0.001, '6 reduced tourists x $5 not-yet-applied gap.');

        $subTotal = BookingService::calculateSubTotal(
            rate: 49.00,
            permitFee: 20.00,
            additionalCharge: 0.00,
            tourists: 10,
            isGroupBooking: false,
            partnerFees: 0,
            refundPermitFeeStatus: $refundPermitFeeStatus
        );

        // True blended total: 6 tourists at $49+$15 + 4 tourists at $49+$20.
        $this->assertEqualsWithDelta(660.00, $subTotal, 0.001);
    }
}
