<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Models\TourBookingHistory;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: cancelling a Card booking with the admin's manual "Cash" checkbox checked (100%
 * refund) stored $0.00 Platform Fees in the "Booking Cancelled" history entry, even though the
 * original card payment really did carry a platform/Stripe fee. Root cause: WeatherRefundCancelService's
 * Cash/Invoiced/Wire branch only computed the real fee_amount (from stripe_fees_percentage) when
 * `$isCashFees=='1' && $transaction->via=='Cash'` — since $isCashFees is a separate "does this
 * tenant apply fees to genuinely-cash bookings" policy toggle, a manually-overridden CARD booking
 * with that toggle off fell into the else branch, which hardcodes fee_amount to null. That null
 * flows into transactions.fee_amount, which Cancel100RefundService then copies verbatim into
 * tour_booking_history.platform_fees — the actual DB column the "Platform Fees" row on screen
 * reads (resolveCancelData() in app/helpers.php), NOT the JSON `details.refund_keep_us`.
 */
class CancelBookingManualOverridePlatformFeeTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_100_percent_refund_with_cash_override_still_records_the_real_platform_fee(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(100.00)
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['stripe_fees_percentage' => 3.5])
            ->create();
        $bookingId = $booking->bookingId;

        try {
            // A manual override must never touch Stripe — same guarantee as the addon-side fix.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'Platform fee cash-override test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $cancelledHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();

            $this->assertNotNull($cancelledHistory, 'Cancelling must write a Cancelled history row');
            $this->assertGreaterThan(
                0.0,
                (float) $cancelledHistory->platform_fees,
                'Platform Fees must reflect the real fee from the original Card payment, not be zeroed just because it was manually settled as Cash'
            );

            $refundTxn = \Illuminate\Support\Facades\DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Refund')
                ->whereNull('charge_type')
                ->latest('id')
                ->first();

            $this->assertNotNull($refundTxn);
            $this->assertGreaterThan(0.0, (float) $refundTxn->fee_amount, 'transactions.fee_amount must also carry the real fee');
            $this->assertEquals('Cash', $refundTxn->via);
        } finally {
            $booking->cleanup();
        }
    }
}
