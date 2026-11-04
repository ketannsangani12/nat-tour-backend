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
 * Regression: cancelling a booking that had one pax's paid add-on ALREADY refunded earlier (e.g.
 * via a pax removal, which only ever inserts a NEW 'refund' row -- it never updates the ORIGINAL
 * 'charge' row's status away from 'paid') tried to refund that same add-on a SECOND time when
 * refundAddonsByBookingId() ran at cancel-time. It queried every 'paid'+'charge'
 * tour_booking_addon_transactions row at face value with no awareness of any prior refund on that
 * same field, inflating the requested Stripe refund for the shared PaymentIntent past what it
 * actually still has left. Stripe then rejected the WHOLE cancellation ("Refund amount ($60.39)
 * is greater than unrefunded amount on charge ($43.92)") -- after the ticket-side refund had
 * already committed elsewhere in the cancel flow, leaving the booking half-cancelled (pax/ticket
 * refunded, add-ons refund never even attempted).
 */
class CancelBookingAddonAlreadyPartiallyRefundedTest extends TestCase
{
    public function test_refund_addons_by_booking_id_nets_out_a_field_already_refunded_elsewhere(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $piId = 'pi_shared_' . uniqid();

        // A real Card PI always has a matching `transactions` row in production (written by
        // TransactionService at charge time) — Payout Simplification's refund-mode lookup depends
        // on that row existing, so this synthetic shared PI needs one too.
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 43.92, 'transaction_number' => $piId,
            'via' => 'Card', 'payment_type' => 2, 'response_init' => '{}', 'response_end' => '{}',
            'response_end_datetime' => now(), 'status' => 'Completed', 'charge_type' => 'addons',
            'created_by' => 0, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        // Field A: already fully refunded earlier (e.g. its pax was removed) -- both the original
        // charge row AND its matching refund row already exist.
        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 700100, 'field_label' => 'Photography Package',
            'pax_index' => 'already-refunded', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'refunded', 'operation' => 'refund',
            'transaction_id' => $piId, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        // Fields B and C: still owed in full, sharing the SAME PaymentIntent as field A.
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 700101, 'field_label' => 'Photography Package',
            'pax_index' => 'still-owed-1', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldC = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 2,
            'pax_id' => 700101, 'field_label' => 'Preferred Tour Language',
            'pax_index' => 'still-owed-2', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldC->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 10.00, 'field_fee' => 0.98,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldIds = [$fieldA->id, $fieldB->id, $fieldC->id];

        try {
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(function ($params) use ($piId) {
                    // Must refund only the NET remaining amount for fields B+C ($16.47 + $10.98 =
                    // $27.45 = 2745 cents) -- never the full original charge total, which would
                    // double count field A's already-refunded $16.47 on top of that.
                    return $params['payment_intent'] === $piId && $params['amount'] === 2745;
                }))
                ->willReturn((object) []);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            app(TourBookingAddonRepository::class)->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            // Field A must NOT get a second refund row -- it was already fully settled.
            $fieldARefundCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldA->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $fieldARefundCount, 'An already-refunded field must not be refunded again');

            // Fields B and C must each get their own new refund row.
            $this->assertNotNull(
                TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldB->id)->where('operation', 'refund')->first(),
                'Field B (still owed) must be refunded'
            );
            $this->assertNotNull(
                TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldC->id)->where('operation', 'refund')->first(),
                'Field C (still owed) must be refunded'
            );
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
