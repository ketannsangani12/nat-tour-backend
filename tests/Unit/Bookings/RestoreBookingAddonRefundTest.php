<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: restoring a cancelled booking put the TICKET and custom-charge sides back the way
 * they were (restoreBooking() already deletes the cancellation's own ticket refund transaction/
 * history row and calls CustomChargeBookingService::restoreCustomCharges()), but never touched
 * add-ons — a cancelled booking with paid add-ons stayed permanently refunded even after being
 * restored to 'Booked'.
 *
 * Restorability is decided purely by each addon refund's own payment_method
 * (TourBookingAddonRepository::restoreAddonsRefundedByCancelBooking()): Cash/Invoiced/Check-Wire
 * (and a manually-settled Card→Cash override) never touched a real payment gateway, so they're
 * always safe to reverse. A genuine 'card' refund moved real money through Stripe — reactivating
 * that DB row without re-charging the customer would falsely show the add-on as paid again while
 * they keep the refund, so those are left alone.
 */
class RestoreBookingAddonRefundTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function createPaidAddon(int $bookingId, string $paxIndex, string $paymentMethod, ?string $transactionId = null): TourBookingAddonField
    {
        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 900300, 'field_label' => 'Camera Rental Type',
            'option_label' => 'DSLR Camera', 'pax_index' => $paxIndex,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        if ($paymentMethod === 'card' && $transactionId) {
            // A real Card PI always has a matching `transactions` row in production (written by
            // TransactionService at charge time) — Payout Simplification's refund-mode lookup
            // depends on that row existing, so this synthetic PI needs one too.
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId,
                'amount' => 40.00,
                'transaction_number' => $transactionId,
                'via' => 'Card',
                'payment_type' => 2,
                'response_init' => '{}',
                'response_end' => '{}',
                'response_end_datetime' => now(),
                'status' => 'Completed',
                'charge_type' => 'addons',
                'created_by' => 0,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);
        }

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => $paymentMethod, 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $transactionId, 'field_price' => 40.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        return $field;
    }

    public function test_restoring_a_cancelled_cash_booking_restores_its_addon_refund(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;
        $field = $this->createPaidAddon($bookingId, 'restore-booking-cash', 'cash');

        try {
            $repo = app(TourBookingRepository::class);

            $cancelRequest = new Request();
            $cancelRequest->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'Restore-booking cash addon test',
                'api' => 1,
            ]);
            $repo->cancel($cancelRequest, $bookingId);

            $this->assertNotNull(
                TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)->where('operation', 'refund')->first(),
                'Precondition: cancelling must have refunded the addon'
            );

            $restoreRequest = new Request();
            $restoreRequest->merge(['api' => 1]);
            $repo->restoreBooking($restoreRequest, $bookingId);

            $this->assertEquals('Booked', TourBooking::find($bookingId)->status);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')->first();
            $this->assertNull($refundRow, 'Restoring the booking must undo a Cash addon refund');

            $chargeRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'charge')->where('status', 'paid')->first();
            $this->assertNotNull($chargeRow, 'The original paid charge row must still be there, active again');

            $this->assertSame(
                0,
                DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->count(),
                'The addon refund ledger row must be removed too'
            );
            $this->assertSame(
                0,
                DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->where('operation', 'Refund')->count(),
                'The addon refund history row must be removed too'
            );
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * The case the user specifically called out: a genuine Card refund moved real money through
     * Stripe, so restoring the booking must NOT silently reactivate it in our records.
     */
    public function test_restoring_a_cancelled_card_booking_does_not_restore_its_addon_refund(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;
        $field = $this->createPaidAddon($bookingId, 'restore-booking-card', 'card', 'pi_restore_card_test');

        try {
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->method('refundPayment')->willReturn((object) []);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingRepository::class);

            // No is_cash override — a genuine card cancellation, refunded for real via Stripe.
            $cancelRequest = new Request();
            $cancelRequest->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'comment' => 'Restore-booking card addon test',
                'api' => 1,
            ]);
            $repo->cancel($cancelRequest, $bookingId);

            $this->assertNotNull(
                TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)->where('operation', 'refund')->first(),
                'Precondition: cancelling must have refunded the addon'
            );

            $restoreRequest = new Request();
            $restoreRequest->merge(['api' => 1]);
            $repo->restoreBooking($restoreRequest, $bookingId);

            $this->assertEquals('Booked', TourBooking::find($bookingId)->status);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')->first();
            $this->assertNotNull($refundRow, 'A real Card refund must survive Restore Booking — the money already went back to the customer');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * A Card booking manually settled as Cash (the is_cash override) never actually touched
     * Stripe for its add-ons — the refund row is tagged 'Cash', not 'card' — so it restores just
     * like a genuinely-cash booking would, even though the underlying booking is Card.
     */
    public function test_restoring_a_manually_overridden_card_booking_still_restores_its_addon_refund(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;
        $field = $this->createPaidAddon($bookingId, 'restore-booking-manual-override', 'card', 'pi_restore_manual_test');

        try {
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingRepository::class);

            $cancelRequest = new Request();
            $cancelRequest->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash', // manual override — never actually hits Stripe
                'comment' => 'Restore-booking manual override addon test',
                'api' => 1,
            ]);
            $repo->cancel($cancelRequest, $bookingId);

            $restoreRequest = new Request();
            $restoreRequest->merge(['api' => 1]);
            $repo->restoreBooking($restoreRequest, $bookingId);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')->first();
            $this->assertNull($refundRow, 'A manually-settled (never touched Stripe) addon refund must restore even on a Card booking');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }
}
