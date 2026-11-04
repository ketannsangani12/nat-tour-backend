<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: a net-$0 swap between two pax on the SAME field (savePaxAddons()'s 'none' action
 * path — one downgrading to exactly the price the other is upgrading to) only ever moved the
 * DISPLAYED option, never the underlying `tour_booking_addon_transactions` row that says how much
 * was actually paid. "Don't Refund" on the swap correctly means no money moves for THAT edit (the
 * booking's aggregate nominal total is unchanged), but it left each row's `paid_price` pinned to
 * whatever was ORIGINALLY charged to that specific DB id — permanently decoupled from what the row
 * now displays. A LATER, perfectly genuine downgrade on the pax who'd been swapped INTO the pricier
 * option then computed its refund against that stale `paid_price` and wrongly found "nothing to
 * refund", even though the booking's real aggregate nominal total had now genuinely dropped (e.g.
 * $18 + $16 paid, ends up $16 + $16 selected — $2 is genuinely owed back). Fixed by repointing the
 * ledger between the two rows as part of the swap, so `paid_price` always follows the item a row
 * currently displays, exactly like the existing ADDED/DELETED reassignment case already does for a
 * field moved between pax.
 */
class SwapAddonLedgerFollowsSelectionTest extends TestCase
{
    public function test_swap_repoints_ledger_and_a_later_downgrade_produces_a_real_refund(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Swap Ledger Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $lunchField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Boxed Trail Lunch',
            'type' => 'dropdown',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 0,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // Pax A: Gluten-Free Wrap ($18 real, paid). Pax B: Classic Turkey Wrap ($16 real, paid).
        // Both charged on the SAME PaymentIntent (one combined "Add-ons" card charge).
        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $lunchField->id,
            'pax_id' => 700900, 'field_label' => 'Boxed Trail Lunch',
            'option_id' => 9001, 'option_label' => 'Gluten-Free Wrap',
            'pax_index' => 'swap-ledger-a', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $lunchField->id,
            'pax_id' => 700901, 'field_label' => 'Boxed Trail Lunch',
            'option_id' => 9002, 'option_label' => 'Classic Turkey Wrap',
            'pax_index' => 'swap-ledger-b', 'person_ordinal' => 1,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_swap_ledger', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 18.00, 'option_fee' => 1.77,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_swap_ledger', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 16.00, 'option_fee' => 1.57,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => 37.34,
            'fee_amount' => 3.34,
            'via' => 'Card',
            'payment_type' => 2,
            'status' => 'Completed',
            'charge_type' => 'addons',
            'transaction_number' => 'pi_swap_ledger',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);

            // Step 1: the net-$0 swap — pax A downgrades GFW -> CTW, pax B upgrades CTW -> GFW.
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => 700900, 'pax_index' => 'swap-ledger-a', 'person_ordinal' => 0,
                        'added' => [], 'deleted' => [],
                        'updated' => [[
                            'field_id' => $lunchField->id, 'db_id' => $fieldA->id,
                            'option_id' => 9002, 'option_label' => 'Classic Turkey Wrap',
                            'value' => 'Classic Turkey Wrap', 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                        ]],
                    ],
                    [
                        'pax_id' => 700901, 'pax_index' => 'swap-ledger-b', 'person_ordinal' => 1,
                        'added' => [], 'deleted' => [],
                        'updated' => [[
                            'field_id' => $lunchField->id, 'db_id' => $fieldB->id,
                            'option_id' => 9001, 'option_label' => 'Gluten-Free Wrap',
                            'value' => 'Gluten-Free Wrap', 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                        ]],
                    ],
                ],
                'financial' => [
                    'action' => 'none',
                    'net_amount' => 0,
                    'total_charge' => 2.00,
                    'total_refund' => 0,
                    'to_charge' => [[
                        'field_id' => $lunchField->id, 'pax_index' => 'swap-ledger-b',
                        'label' => 'Boxed Trail Lunch: Classic Turkey Wrap → Gluten-Free Wrap',
                        'option_id' => 9001, 'unit' => 1,
                        'new_price' => 18.00, 'old_db_id' => $fieldB->id, 'old_paid' => 16.00,
                        'charge_amount' => 2.00, 'field_price' => 0, 'option_price' => 2.00,
                        'field_additional_fee' => false, 'option_additional_fee' => true,
                        'reason' => 'upgraded',
                    ]],
                    'to_refund' => [[
                        'db_id' => $fieldA->id, 'field_id' => $lunchField->id,
                        'label' => 'Boxed Trail Lunch: Gluten-Free Wrap → Classic Turkey Wrap',
                        'refund_amount' => 0, 'credit_amount' => 2.00, 'reason' => 'downgraded',
                    ]],
                    'payment_method' => 'Card',
                    'refund_type' => 'dont_refund',
                ],
            ]);

            $this->assertEquals(9002, TourBookingAddonField::find($fieldA->id)->option_id, 'Pax A must now show Classic Turkey Wrap');
            $this->assertEquals(9001, TourBookingAddonField::find($fieldB->id)->option_id, 'Pax B must now show Gluten-Free Wrap');

            $txnNowOnA = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldA->id)->where('status', 'paid')->first();
            $txnNowOnB = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldB->id)->where('status', 'paid')->first();

            $this->assertEquals(16.00, (float) $txnNowOnA->option_price, "Pax A's row must now carry what was ACTUALLY paid for it (the \$16 originally charged to pax B) — not stay pinned to the old \$18 GFW charge");
            $this->assertEquals(18.00, (float) $txnNowOnB->option_price, "Pax B's row must now carry the \$18 that was actually paid — the swap moved the money along with the label");

            // Step 2: pax B genuinely downgrades their (now ledger-accurate) $18 item to a $16 one.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_swap_ledger'))
                ->willReturn((object) ['id' => 're_test_swap_ledger']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [[
                    'pax_id' => 700901, 'pax_index' => 'swap-ledger-b', 'person_ordinal' => 1,
                    'added' => [],
                    'updated' => [[
                        'field_id' => $lunchField->id, 'db_id' => $fieldB->id,
                        'option_id' => 9003, 'option_label' => 'Vegetarian Wrap',
                        'value' => 'Vegetarian Wrap', 'unit_type' => 'price_per_pax', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ]],
                    'deleted' => [],
                ]],
                'financial' => [
                    'action' => 'refund',
                    'net_amount' => 2.00,
                    'total_charge' => 0,
                    'total_refund' => 2.00,
                    'to_charge' => [],
                    'to_refund' => [[
                        'db_id' => $fieldB->id, 'field_id' => $lunchField->id,
                        'label' => 'Boxed Trail Lunch: Gluten-Free Wrap → Vegetarian Wrap',
                        'refund_amount' => 2.00, 'credit_amount' => 2.00, 'reason' => 'downgraded',
                    ]],
                    'payment_method' => 'Card',
                    'refund_type' => 'ticket_refund',
                ],
            ]);

            $historyRows = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->get();

            $this->assertGreaterThan(0, $historyRows->count(), 'The real downgrade must write a history row.');
        } finally {
            TourBookingAddonTransaction::where('tour_booking_id', $bookingId)->forceDelete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->where('id', $lunchField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
