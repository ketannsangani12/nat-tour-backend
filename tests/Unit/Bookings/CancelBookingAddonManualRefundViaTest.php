<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: cancelling a Card booking with the admin's "Cash" manual-settlement checkbox
 * checked correctly refunded the TICKET amount as Cash (WeatherRefundCancelService/
 * TicketRefundCancelService/etc. already honor $request->is_cash for the tour-side refund), but
 * the add-ons kept refunding through Stripe against the original card — refundAddonsByBookingId()
 * had no way to know a manual override was chosen at all, unlike deletePaxAddons() which already
 * supports this via its $manualRefundVia parameter.
 */
class CancelBookingAddonManualRefundViaTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function createPaidCardAddon(int $bookingId, string $paxIndex): TourBookingAddonField
    {
        $piId = 'pi_manual_override_test_' . uniqid();

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 900200, 'field_label' => 'Photography Package',
            'pax_index' => $paxIndex, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // A real Card PI always has a matching `transactions` row in production (written by
        // TransactionService at charge time) — Payout Simplification's refund-mode lookup depends
        // on that row existing, so this synthetic PI needs one too.
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 16.47, 'transaction_number' => $piId,
            'via' => 'Card', 'payment_type' => 2, 'response_init' => '{}', 'response_end' => '{}',
            'response_end_datetime' => now(), 'status' => 'Completed', 'charge_type' => 'addons',
            'created_by' => 0, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        return $field;
    }

    public function test_100_percent_refund_with_cash_override_settles_the_card_addon_as_cash_not_stripe(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->createPaidCardAddon($bookingId, 'cancel-manual-cash-override');

        try {
            // The whole point of this test: Stripe must NEVER be touched for the addon (or the
            // ticket) once the admin has chosen to reimburse manually.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'Manual cash override cancel test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();

            $this->assertNotNull($refundRow, 'Cancelling with 100% refund must still refund the paid addon');
            $this->assertEquals('Cash', $refundRow->payment_method, 'Addon refund must follow the is_cash override, not stay tagged card');

            $ledgerRow = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->where('status', 'Refund')
                ->first();

            $this->assertNotNull($ledgerRow, 'Addon refund must still write its own ledger row');
            $this->assertEquals('Cash', $ledgerRow->via);
            // No real Stripe operation backs a manual refund — the original card's PaymentIntent/
            // application fee id must not appear on this ledger row.
            $this->assertEquals('', $ledgerRow->transaction_number);
            $this->assertEquals('', $ledgerRow->application_fee_id);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_100_percent_refund_without_override_still_refunds_the_card_addon_via_stripe(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Card')
            ->create();
        $bookingId = $booking->bookingId;

        $field = $this->createPaidCardAddon($bookingId, 'cancel-no-override-card');

        try {
            // No is_cash override this time — a genuine card cancellation must still hit Stripe
            // exactly as before this fix.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->atLeastOnce())->method('refundPayment')->willReturn((object) []);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'comment' => 'Genuine card cancel test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();

            $this->assertNotNull($refundRow);
            $this->assertEquals('card', $refundRow->payment_method, 'Without an override, the addon refund must keep its real card payment method');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }
}
