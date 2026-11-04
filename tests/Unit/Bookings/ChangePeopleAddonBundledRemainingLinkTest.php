<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: admin adds a pax via "Change People" using "Payment Link" (deferred) -- this
 * creates a `tour_booking_payment_links` row (section='remaining_payment') covering that pax's
 * still-unpaid ticket cost. Admin then edits that SAME still-unpaid pax's add-on selection (an
 * upgrade) and pays by Card. The frontend combines BOTH amounts (the pending link + the add-on
 * delta) into ONE Stripe PaymentIntent, which Stripe correctly charges in full.
 *
 * Real-world repro (tenant `kens`, booking 348698): Stripe's own dashboard confirmed PaymentIntent
 * pi_3ULMCLFWb9Y3fHFE1gOaXf0i succeeded for $107.95 -- but our own `transactions` table recorded
 * only $5.49 (the add-on delta) against that exact PaymentIntent number, and the pending
 * `remaining_payment` link ($102.46) was left `Unpaid` forever. The customer was charged
 * correctly; our own ledger silently failed to record ~$102.46 of it -- a worse failure mode than
 * an undercharge, since the booking still looks unpaid despite having already been paid in full,
 * risking a double-charge later.
 *
 * Root cause: `createPaymentIntent()`/`createBookingCard()` in
 * app/Repositories/TourBooking/V3/TourBookingRepository.php only looked up and finalized a
 * pending `remaining_payment` link INSIDE the `empty($data['skip_tour_side_ledger'])` guard.
 * `skip_tour_side_ledger` is true whenever THIS delta's own tour-side amount is $0 (the normal,
 * correct case for an add-ons-only Change People edit) -- but that has nothing to do with whether
 * an UNRELATED pending remaining_payment link was also covered by the same combined PaymentIntent.
 * Fixed by hoisting the link lookup/finalize out from under that guard so it always runs whenever
 * `change_people == 1`, regardless of skip_tour_side_ledger.
 */
class ChangePeopleAddonBundledRemainingLinkTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_pending_remaining_payment_link_is_finalized_when_bundled_with_an_addon_only_change_people_charge(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(55.00)
            ->withPaymentMethod('Card')
            ->create();

        $groupId = DB::table('tour_bookings_master')->where('id', $booking->bookingId)->value('group_id');

        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'amount' => 102.46,
            'order_id' => $groupId,
            'section' => 'remaining_payment',
            'status' => 'Unpaid',
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('tour_booking_payment_link_items')->insertGetId([
            'payment_link_id' => $linkId,
            'type' => 'change_people',
            'amount' => 102.46,
            'status' => 'Unpaid',
            'details' => json_encode([
                'ticket_cost' => 95.00,
                'platform_fees' => 7.46,
                'tourists' => 1,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $fakePaymentIntentId = 'pi_test_' . uniqid();
            $fakePaymentIntent = (object) [
                'id' => $fakePaymentIntentId,
                'status' => 'succeeded',
                'client_secret' => 'secret_' . uniqid(),
                'charges' => (object) [
                    'data' => [
                        (object) [
                            'application_fee' => null,
                            'payment_method_details' => null,
                            'transfer' => null,
                        ],
                    ],
                ],
            ];

            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->method('updatePayment')->willReturn($fakePaymentIntent);
            $fakeGateway->method('retrievePayment')->willReturn($fakePaymentIntent);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $info = DB::table('tour_bookings_master as tbm')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->where('tbm.id', $booking->bookingId)
                ->select('tst.date', 'tst.time_from', 'tp.name as package_name', 'tp.tour_operator_id')
                ->first();

            // Mirrors changePeopleBooking()'s own $data shape for an add-ons-only edit: tour-side
            // amount is $0 (skip_tour_side_ledger=true), only the add-on's own delta is charged
            // directly -- the pending remaining_payment link is a SEPARATE, unrelated amount that
            // this same combined PaymentIntent also collected.
            $data = [
                'skip_tour_side_ledger' => true,
                'total' => 0,
                'customer_id' => 1,
                'customer_name' => 'Promo Test',
                'customer_email' => 'change-people-' . uniqid() . '@test.com',
                'customer_phone' => '5550001111',
                'tourists' => 2,
                'booking_id' => $booking->bookingId,
                'group_id' => $groupId,
                'partnerFees' => 0,
                'isPartnerPackage' => 0,
                'partner_destination_id' => null,
                'tour_package' => $info->package_name,
                'date' => $info->date,
                'time_from' => $info->time_from,
                'subtotal' => 0,
                'serviceCommission' => 0,
                'destination' => null,
                'slot_time' => $booking->slotTimeId,
                'tour_operator_id' => $info->tour_operator_id,
                'statement_descriptor' => null,
                'card_reader_prefix' => null,
                'note' => '',
                'booked_by_staff' => '',
                'split_value' => null,
                'split_pending_amount' => 0,
                'change_people' => 1,
                'discount_percentage' => 0,
                'discount2_value' => 0,
                'addedpeoplesetexts' => [],
                'deletedpeoplestexts' => [],
                'addedtourists' => 0,
                'deletedtourits' => 0,
                'paymenttotal' => 0,
                'refundtotal' => 0,
                'webhook' => 0,
                'payment_intent_id' => $fakePaymentIntentId,
            ];

            app(TourBookingRepository::class)->createPaymentIntent($data);

            $freshLink = DB::table('tour_booking_payment_links')->find($linkId);
            $this->assertSame('Paid', $freshLink->status, 'The pending remaining_payment link must be finalized when its money was collected via this combined PaymentIntent.');

            $freshItem = DB::table('tour_booking_payment_link_items')->find($itemId);
            $this->assertSame('Paid', $freshItem->status);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $booking->bookingId)
                ->where('charge_type', 'change_people')
                ->first();
            $this->assertNotNull($historyRow, 'A history row must record the finalized remaining_payment amount.');
            $this->assertEqualsWithDelta(102.46, (float) $historyRow->ticket_cost + (float) $historyRow->platform_fees, 0.01);

            $transactionRow = DB::table('transactions')->where('transaction_number', $fakePaymentIntentId)->first();
            $this->assertNotNull($transactionRow, 'A transactions row must exist for the pending link amount against this PaymentIntent.');
            $this->assertEqualsWithDelta(102.46, (float) $transactionRow->amount, 0.01);
        } finally {
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $linkId)->delete();
            DB::table('tour_booking_payment_links')->where('id', $linkId)->delete();
            DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $booking->bookingId)->where('charge_type', 'change_people')->delete();
            $booking->cleanup();
        }
    }
}
