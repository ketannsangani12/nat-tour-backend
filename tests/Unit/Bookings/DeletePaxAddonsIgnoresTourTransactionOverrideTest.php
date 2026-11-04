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
 * Regression: booking KEN-348645 (confirmed against the real `kens` tenant DB). Add-ons were
 * charged on their own separate PaymentIntent (pi_...2gi4LVYQ, $98.82). A "Delete People -> Ticket
 * Refund" operation with the TICKET PaymentIntent (pi_...1JmhezY4, the "Update Pax Payment"
 * charge) selected as the admin's "Tour Transaction" pick in the modal's Block 2 redirected BOTH
 * the $146.60 ticket refund AND the $45.00 add-on refund onto that ticket PI — real
 * `tour_booking_addon_transactions` rows confirmed `transaction_id = pi_...1JmhezY4` for the
 * add-on refund rows, even though the add-on's own charge rows say `pi_...2gi4LVYQ`. The add-on's
 * real origin PI ended up with zero refund activity despite genuinely holding that money, and the
 * "Add-ons only" balance shown for it in the modal on next open silently drifted (computed from
 * which add-on fields still exist, not from any real per-PI refund record against it).
 *
 * Per the modal's own documented design (Block 2 = "tour amount only", Block 3 add-ons = "own
 * block, never merged with the tour amount" — _update-booking-modal.blade.php's header comment)
 * and matching refundAddonsByBookingId() (the whole-booking cancel flow's sibling method, which
 * has never had any transaction-override mechanism at all), deletePaxAddons() must never let the
 * admin's Tour Transaction pick redirect an add-on's refund away from its own real origin PI —
 * regardless of what $targetTransactionNumber is passed, add-ons always settle against the PI that
 * actually charged them.
 */
class DeletePaxAddonsIgnoresTourTransactionOverrideTest extends TestCase
{
    public function test_addon_refund_always_uses_its_own_origin_pi_even_with_a_tour_transaction_override(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1, 'pax_id' => 700645,
            'field_label' => 'Photography Package', 'option_id' => 1, 'option_label' => 'Premium',
            'pax_index' => 'ignore-override-test', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            // The add-on's own real origin PaymentIntent -- charged separately from the ticket PI,
            // exactly like booking KEN-348645's "Add-ons +$98.82" event.
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 25.00, 'fee_amount' => 2.45,
                'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
                'transaction_number' => 'pi_addon_own_origin', 'created_by' => 0,
                'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);
            TourBookingAddonTransaction::create([
                'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
                'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
                'transaction_id' => 'pi_addon_own_origin', 'field_price' => 0, 'field_fee' => 0,
                'option_price' => 25.00, 'option_fee' => 2.45,
                'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
            ]);

            // The admin's Tour Transaction pick for the TICKET side of this same delete-people
            // operation -- a genuinely different PI, with plenty of its own real balance, exactly
            // like pi_...1JmhezY4 ($241.50 charged, $146.60 already refunded by the ticket-side
            // portion). Must be completely untouched by the add-on refund below.
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 241.50, 'fee_amount' => 21.60,
                'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => null,
                'transaction_number' => 'pi_ticket_side', 'created_by' => 0,
                'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 146.60, 'fee_amount' => 0,
                'via' => 'Card', 'payment_type' => 2, 'status' => 'Refund', 'charge_type' => null,
                'transaction_number' => 'pi_ticket_side', 'created_by' => 0,
                'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(function ($params) {
                    // Must go to the add-on's own real origin PI, never the Tour Transaction pick.
                    return $params['payment_intent'] === 'pi_addon_own_origin'
                        && $params['amount'] === 2500;
                }))
                ->willReturn((object) ['id' => 're_own_origin_test']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            // $targetTransactionNumber ('pi_ticket_side') is passed exactly as editBooking() would
            // when the admin picks a Tour Transaction -- it must have zero effect on this add-on.
            $repo->deletePaxAddons(
                $bookingId,
                '[]',
                Constants::ADDONS_TICKET_REFUND,
                null,
                null,
                [$field->id],
                'pi_ticket_side'
            );

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $this->assertDatabaseHas('transactions', [
                'tour_booking_id' => $bookingId,
                'transaction_number' => 'pi_addon_own_origin',
                'amount' => 25.00,
                'charge_type' => 'addons',
                'status' => 'Refund',
            ]);
            // The ticket-side PI must show no addon-refund activity at all -- the exact
            // misattribution confirmed on booking KEN-348645.
            $this->assertDatabaseMissing('transactions', [
                'tour_booking_id' => $bookingId,
                'transaction_number' => 'pi_ticket_side',
                'charge_type' => 'addons',
            ]);
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $field->id,
                'operation' => 'refund',
                'status' => 'refunded',
                'transaction_id' => 'pi_addon_own_origin',
            ]);
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)
                ->whereIn('transaction_number', ['pi_addon_own_origin', 'pi_ticket_side'])
                ->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            $booking->cleanup();
        }
    }
}
