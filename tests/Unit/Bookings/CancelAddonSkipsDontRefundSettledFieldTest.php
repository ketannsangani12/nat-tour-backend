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
 * Regression: a field already settled as "Don't Refund" by an earlier operation (e.g. a pax
 * removal where the admin chose to keep that pax's addon money) writes its refund row with ALL
 * FOUR amount columns at 0, by design -- nothing is actually paid out. That made it invisible to
 * refundAddonsByBookingId()'s dollar-based netting fix (CancelBookingAddonAlreadyPartiallyRefundedTest):
 * charge($16) net a $0-valued refund row still nets to "$16 still owed", so a field the admin
 * already explicitly decided to KEEP got swept right back into a LATER cancellation's refund
 * total as if never dispositioned at all -- inflating the requested Stripe refund past what the
 * shared PaymentIntent has left. Confirmed live: booking KEN-41814, "Unable to cancel booking.
 * Refund amount ($128.47) is greater than unrefunded amount on charge ($120.42)" -- $128.47 being
 * the full original add-ons total, wrongly re-including a $17.57 item already settled as Don't
 * Refund, when only $110.90 was genuinely still owed.
 */
class CancelAddonSkipsDontRefundSettledFieldTest extends TestCase
{
    public function test_refund_addons_by_booking_id_skips_a_field_already_settled_as_dont_refund(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $piId = 'pi_shared_' . uniqid();

        // A real Card PI always has a matching `transactions` row in production (written by
        // TransactionService at charge time) -- Payout Simplification's refund-mode lookup depends
        // on that row existing, so this synthetic shared PI needs one too.
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 61.49, 'transaction_number' => $piId,
            'via' => 'Card', 'payment_type' => 2, 'response_init' => '{}', 'response_end' => '{}',
            'response_end_datetime' => now(), 'status' => 'Completed', 'charge_type' => 'addons',
            'created_by' => 0, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        // Field A: already settled as "Don't Refund" (kept) by an earlier operation -- charge row
        // has a real amount, its refund row is all-zero.
        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 850100, 'field_label' => 'Boxed Trail Lunch',
            'option_label' => 'Vegetarian Wrap', 'pax_index' => 'dont-refund-settled',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 16.00, 'field_fee' => 1.57,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'refunded', 'operation' => 'refund',
            'transaction_id' => $piId, 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        // Field B: still genuinely owed in full, sharing the same PaymentIntent.
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 2,
            'pax_id' => 850101, 'field_label' => 'Camera Rental Type',
            'option_label' => 'DSLR Camera', 'pax_index' => 'still-owed',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 40.00, 'field_fee' => 3.92,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldIds = [$fieldA->id, $fieldB->id];

        try {
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(function ($params) use ($piId) {
                    // Must refund only field B's $43.92 -- never field A's already-settled $17.57
                    // on top of it (which is what "the full original total" would look like).
                    return $params['payment_intent'] === $piId && $params['amount'] === 4392;
                }))
                ->willReturn((object) []);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            app(TourBookingAddonRepository::class)->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            // Field A must NOT get a second refund row -- it was already dispositioned.
            $fieldARefundCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldA->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $fieldARefundCount, 'A field already settled as Don\'t Refund must not be touched again');

            // Field B must be refunded in full.
            $fieldBRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldB->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($fieldBRefundRow, 'The still-owed field must be refunded');
            $this->assertEquals(40.00, (float) $fieldBRefundRow->field_price);
            $this->assertEquals(3.92, (float) $fieldBRefundRow->field_fee);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: the all-zero "Don't Refund" signature above is written not just by historical
     * data but by chargeAddonFields()'s own live Don't-Refund branch, and a single
     * tour_booking_addon_id can legitimately carry MORE than one 'paid' charge row over its
     * lifetime without the field ever being soft-deleted/recreated (e.g. an option upgrade on an
     * existing field -- see processAddonFinancialTransactions()'s "multiple PaymentIntents over
     * its lifetime" comment). A field-id-only "settled, skip forever" check can't tell those two
     * charges apart: it would skip BOTH the already-settled original charge AND a genuinely new,
     * still-owed charge added to the same field id afterwards. Gating the skip by transaction id
     * (only charges at-or-before the settlement's own id) is what tells them apart.
     */
    public function test_refund_addons_by_booking_id_still_refunds_a_later_charge_on_a_dont_refund_settled_field(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $piId = 'pi_upgrade_' . uniqid();

        // A real Card PI always has a matching `transactions` row in production -- see the
        // identical note in the test above.
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 21.96, 'transaction_number' => $piId,
            'via' => 'Card', 'payment_type' => 2, 'response_init' => '{}', 'response_end' => '{}',
            'response_end_datetime' => now(), 'status' => 'Completed', 'charge_type' => 'addons',
            'created_by' => 0, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 3,
            'pax_id' => 850102, 'field_label' => 'Photography Package',
            'option_label' => 'Standard Package', 'pax_index' => 'settled-then-upgraded',
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // Original charge, later settled as "Don't Refund" (kept) -- all-zero refund row, exactly
        // the signature chargeAddonFields()'s Don't-Refund branch writes today.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $piId, 'field_price' => 20.00, 'field_fee' => 1.96,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'card', 'status' => 'refunded', 'operation' => 'refund',
            'transaction_id' => $piId, 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        // Later, unrelated to that settlement: the SAME field id is upgraded to a pricier
        // option, on its own PaymentIntent -- a genuinely new, still-owed charge.
        $upgradePiId = 'pi_upgrade2_' . uniqid();
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 32.92, 'transaction_number' => $upgradePiId,
            'via' => 'Card', 'payment_type' => 2, 'response_init' => '{}', 'response_end' => '{}',
            'response_end_datetime' => now(), 'status' => 'Completed', 'charge_type' => 'addons',
            'created_by' => 0, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => $upgradePiId, 'field_price' => 30.00, 'field_fee' => 2.92,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldIds = [$field->id];

        try {
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(function ($params) use ($upgradePiId) {
                    // Only the upgrade's own PI, for its own $32.92 -- never the settled $20/$1.96
                    // charge, and never charged against the wrong (original) PI.
                    return $params['payment_intent'] === $upgradePiId && $params['amount'] === 3292;
                }))
                ->willReturn((object) []);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            app(TourBookingAddonRepository::class)->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $refundRows = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->orderBy('id')
                ->get();

            // Exactly one NEW refund row was written (the pre-existing all-zero settlement row,
            // plus one new one for the upgrade) -- the settled charge must not get a second,
            // redundant refund row.
            $this->assertCount(2, $refundRows, 'Only the upgrade charge should produce a new refund row');
            $newRefundRow = $refundRows->last();
            $this->assertEquals(30.00, (float) $newRefundRow->field_price);
            $this->assertEquals(2.92, (float) $newRefundRow->field_fee);
            $this->assertEquals($upgradePiId, $newRefundRow->transaction_id);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
