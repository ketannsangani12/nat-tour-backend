<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: removing a pax's Card-paid add-on from the "Update Booking" modal while the admin
 * picked "Cash" or "Check/Wire" (editBooking()'s $request->is_cash — reimbursing the customer by
 * hand instead of an actual Stripe refund, same override the TOUR-side refund already honors) was
 * refunding the add-on back to the card anyway — deletePaxAddons() had no way to know a manual
 * refund was chosen at all.
 */
class DeletePaxAddonsManualRefundViaTest extends TestCase
{
    /**
     * A real Card PI always has a matching `transactions` row in production (written by
     * TransactionService at charge time) — Payout Simplification's refund-mode lookup depends on
     * that row existing, so a synthetic addon PI needs one too. Rides along with the booking's
     * own cleanup (deletes by tour_booking_id).
     */
    protected function stubCardTransactionRow(int $bookingId, string $piId, float $amount): void
    {
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => $amount,
            'transaction_number' => $piId,
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

    public function test_manual_cash_refund_skips_stripe_and_tags_the_ledger_as_cash(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800100, 'field_label' => 'Photography Package',
            'pax_index' => 'manual-cash-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($bookingId, 'pi_manual_cash', 16.47);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_manual_cash', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 15.00, 'option_fee' => 1.47,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // The gateway must NEVER be called — this is the whole point of the manual override.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons(
                $bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, null, [$field->id], null,
                'Cash'
            );

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundRow);
            $this->assertEquals('Cash', $refundRow->payment_method);

            $ledgerRow = \Illuminate\Support\Facades\DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->where('status', 'Refund')
                ->first();
            $this->assertNotNull($ledgerRow, 'Add-on refund must still write its own ledger row');
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

    /**
     * Same combination reported as broken live (100% Refund + Check/Wire, one pax + its add-on):
     * confirms deletePaxAddons() itself is correct for this exact refund-type/manual-method pair,
     * isolating the live report's cause to editBooking()'s upstream field-id resolution rather than
     * this method's own refund logic.
     */
    public function test_100_percent_refund_with_check_wire_still_refunds_the_addon(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800150, 'field_label' => 'Photography Package',
            'pax_index' => 'diag-100-wire', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($bookingId, 'pi_diag_100_wire', 16.47);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_diag_100_wire', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 15.00, 'option_fee' => 1.47,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons(
                $bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, null, [$field->id], null,
                'Check/Wire'
            );

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);
            $this->assertNotNull($refundRow);
            $this->assertEquals('Check/Wire', $refundRow->payment_method);
            // 100% refund keeps the fee (unlike a plain ticket refund).
            $this->assertEquals(15.00, (float) $refundRow->option_price);
            $this->assertEquals(1.47, (float) $refundRow->option_fee);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_manual_check_wire_refund_skips_stripe_and_tags_the_ledger(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800200, 'field_label' => 'Sweater', 'option_label' => 'Medium',
            'pax_index' => 'manual-wire-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($bookingId, 'pi_manual_wire', 10.98);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_manual_wire', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 10.00, 'option_fee' => 0.98,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons(
                $bookingId, '[]', Constants::ADDONS_TICKET_REFUND, null, null, [$field->id], null,
                'Check/Wire'
            );

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundRow);
            $this->assertEquals('Check/Wire', $refundRow->payment_method);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    public function test_no_manual_override_still_refunds_via_stripe_as_before(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800300, 'field_label' => 'Photography Package',
            'pax_index' => 'no-override-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            $this->stubCardTransactionRow($bookingId, 'pi_no_override', 16.47);

            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_no_override', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 15.00, 'option_fee' => 1.47,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())->method('refundPayment')
                ->willReturn((object) ['id' => 're_no_override']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            // $manualRefundVia omitted (defaults to null) — must behave exactly as before this fix.
            $repo->deletePaxAddons($bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, null, [$field->id]);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundRow);
            $this->assertEquals('card', $refundRow->payment_method);

            // Unaffected by this fix: a real Stripe-backed refund still records its actual
            // PaymentIntent/application fee id on the ledger row.
            $ledgerRow = \Illuminate\Support\Facades\DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->where('status', 'Refund')
                ->first();
            $this->assertNotNull($ledgerRow);
            $this->assertEquals('pi_no_override', $ledgerRow->transaction_number);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: pax removed with Ticket Refund + a manual Cash override, but the add-on's own
     * checkbox was left UNCHECKED (deselected — kept, not refunded). The field is still correctly
     * hard-deleted with a $0/"Don't Refund" history entry (existing behavior), but that entry's
     * payment method badge was always the field's original card charge ("Card •••• 5556") instead
     * of the Cash/Check-Wire the admin picked for the whole delete operation.
     */
    public function test_deselected_addon_with_manual_cash_override_shows_cash_not_card(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800400, 'field_label' => 'Photography Package',
            'pax_index' => 'deselected-cash-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_deselected_cash', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 15.00, 'option_fee' => 1.47,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            // In scope (explicit field id) but deselected ($selectedFieldIds = []) — kept, not
            // refunded — while a manual Cash refund was chosen for the delete operation overall.
            $repo->deletePaxAddons(
                $bookingId, '[]', Constants::ADDONS_TICKET_REFUND, null, [], [$field->id], null,
                'Cash'
            );

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $historyRow = \Illuminate\Support\Facades\DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow);
            $this->assertEquals('Cash', $historyRow->payment_type);
            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Cash', $details['payment_type']);
            $this->assertEquals('Cash', $details['addons'][0]['payment_method']);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Same as the Cash version above but with 100% Refund + Check/Wire — the exact combination
     * reported live: pax refunded, add-on's own checkbox left unchecked. Must still be
     * hard-deleted with a $0/"Don't Refund" entry tagged Check/Wire, not Card.
     */
    public function test_deselected_addon_with_100_percent_refund_and_check_wire_shows_check_wire(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800500, 'field_label' => 'Photography Package',
            'pax_index' => 'deselected-100-wire-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_deselected_100_wire', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 15.00, 'option_fee' => 1.47,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->deletePaxAddons(
                $bookingId, '[]', Constants::ADDONS_FULL_REFUND, null, [], [$field->id], null,
                'Check/Wire'
            );

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $historyRow = \Illuminate\Support\Facades\DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow);
            $this->assertEquals('Check/Wire', $historyRow->payment_type);
            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Check/Wire', $details['payment_type']);
            $this->assertEquals('Check/Wire', $details['addons'][0]['payment_method']);
            // Raw slug stored, not the display label — resolveRefundData() translates
            // 'dont_refund' to "Don't Refund" at render time (matches every other refund-type
            // history entry's storage convention).
            $this->assertEquals('dont_refund', $details['refund_type']);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            $booking->cleanup();
        }
    }
}
