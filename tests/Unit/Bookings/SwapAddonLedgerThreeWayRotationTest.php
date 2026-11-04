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
 * Extends SwapAddonLedgerFollowsSelectionTest's 2-pax case to N pax: a full 3-way rotation where
 * no single pair's price delta matches another (so a price-only matcher would miss it entirely),
 * but the SELECTIONS themselves rotate in a closed loop — A ends up with what C had, B ends up
 * with what A had, C ends up with what B had. savePaxAddons()'s repoint logic matches on the
 * SELECTION identity (field_id + option_id), not price, and executes every move via a per-source
 * sentinel so a row that is simultaneously a move's source and another move's destination never
 * loses data mid-swap.
 */
class SwapAddonLedgerThreeWayRotationTest extends TestCase
{
    public function test_three_way_rotation_repoints_ledger_for_every_pax(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Three Way Rotation Form ' . uniqid(),
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

        // A holds option X ($10 real, paid). B holds option Y ($8 real, paid). C holds option Z
        // ($6 real, paid). All three on the SAME PaymentIntent (one combined "Add-ons" charge).
        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $lunchField->id,
            'pax_id' => 701900, 'field_label' => 'Boxed Trail Lunch',
            'option_id' => 9101, 'option_label' => 'Option X',
            'pax_index' => 'rot-a', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $lunchField->id,
            'pax_id' => 701901, 'field_label' => 'Boxed Trail Lunch',
            'option_id' => 9102, 'option_label' => 'Option Y',
            'pax_index' => 'rot-b', 'person_ordinal' => 1,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldC = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $lunchField->id,
            'pax_id' => 701902, 'field_label' => 'Boxed Trail Lunch',
            'option_id' => 9103, 'option_label' => 'Option Z',
            'pax_index' => 'rot-c', 'person_ordinal' => 2,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_rotation', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 10.00, 'option_fee' => 0.98,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_rotation', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 8.00, 'option_fee' => 0.79,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldC->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_rotation', 'field_price' => 0, 'field_fee' => 0,
            'option_price' => 6.00, 'option_fee' => 0.59,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => 26.36,
            'fee_amount' => 2.36,
            'via' => 'Card',
            'payment_type' => 2,
            'status' => 'Completed',
            'charge_type' => 'addons',
            'transaction_number' => 'pi_rotation',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);

            // Rotation: A -> Y (was B's), B -> Z (was C's), C -> X (was A's). Net: A drops
            // $10->$8 (credit $2), B drops $8->$6 (credit $2), C rises $6->$10 (charge $4). Nets
            // to exactly $0 across the whole batch, which is why this reaches savePaxAddons() at
            // all — no single pair's delta matches another (2, 2, and 4 — not a clean 1:1 pair).
            $repo->savePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => 701900, 'pax_index' => 'rot-a', 'person_ordinal' => 0,
                        'added' => [], 'deleted' => [],
                        'updated' => [[
                            'field_id' => $lunchField->id, 'db_id' => $fieldA->id,
                            'option_id' => 9102, 'option_label' => 'Option Y',
                            'value' => 'Option Y', 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                        ]],
                    ],
                    [
                        'pax_id' => 701901, 'pax_index' => 'rot-b', 'person_ordinal' => 1,
                        'added' => [], 'deleted' => [],
                        'updated' => [[
                            'field_id' => $lunchField->id, 'db_id' => $fieldB->id,
                            'option_id' => 9103, 'option_label' => 'Option Z',
                            'value' => 'Option Z', 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                        ]],
                    ],
                    [
                        'pax_id' => 701902, 'pax_index' => 'rot-c', 'person_ordinal' => 2,
                        'added' => [], 'deleted' => [],
                        'updated' => [[
                            'field_id' => $lunchField->id, 'db_id' => $fieldC->id,
                            'option_id' => 9101, 'option_label' => 'Option X',
                            'value' => 'Option X', 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                        ]],
                    ],
                ],
                'financial' => [
                    'action' => 'none',
                    'net_amount' => 0,
                    'total_charge' => 4.00,
                    'total_refund' => 0,
                    'to_charge' => [[
                        'field_id' => $lunchField->id, 'pax_index' => 'rot-c',
                        'label' => 'Boxed Trail Lunch: Option Z → Option X',
                        'option_id' => 9101, 'unit' => 1,
                        'new_price' => 10.00, 'old_db_id' => $fieldC->id, 'old_paid' => 6.00,
                        'charge_amount' => 4.00, 'field_price' => 0, 'option_price' => 4.00,
                        'field_additional_fee' => false, 'option_additional_fee' => true,
                        'reason' => 'upgraded',
                    ]],
                    'to_refund' => [
                        [
                            'db_id' => $fieldA->id, 'field_id' => $lunchField->id,
                            'label' => 'Boxed Trail Lunch: Option X → Option Y',
                            'refund_amount' => 0, 'credit_amount' => 2.00, 'reason' => 'downgraded',
                        ],
                        [
                            'db_id' => $fieldB->id, 'field_id' => $lunchField->id,
                            'label' => 'Boxed Trail Lunch: Option Y → Option Z',
                            'refund_amount' => 0, 'credit_amount' => 2.00, 'reason' => 'downgraded',
                        ],
                    ],
                    'payment_method' => 'Card',
                    'refund_type' => 'dont_refund',
                ],
            ]);

            $this->assertEquals(9102, TourBookingAddonField::find($fieldA->id)->option_id, 'A must now show Option Y');
            $this->assertEquals(9103, TourBookingAddonField::find($fieldB->id)->option_id, 'B must now show Option Z');
            $this->assertEquals(9101, TourBookingAddonField::find($fieldC->id)->option_id, 'C must now show Option X');

            $txnOnA = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldA->id)->where('status', 'paid')->first();
            $txnOnB = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldB->id)->where('status', 'paid')->first();
            $txnOnC = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldC->id)->where('status', 'paid')->first();

            $this->assertEquals(8.00, (float) $txnOnA->option_price, "A's row must carry the \$8 that was actually paid for Option Y (B's original charge)");
            $this->assertEquals(6.00, (float) $txnOnB->option_price, "B's row must carry the \$6 that was actually paid for Option Z (C's original charge)");
            $this->assertEquals(10.00, (float) $txnOnC->option_price, "C's row must carry the \$10 that was actually paid for Option X (A's original charge)");

            // Now C genuinely downgrades their (ledger-accurate, post-rotation) $10 item to $8.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_rotation'))
                ->willReturn((object) ['id' => 're_test_rotation']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [[
                    'pax_id' => 701902, 'pax_index' => 'rot-c', 'person_ordinal' => 2,
                    'added' => [],
                    'updated' => [[
                        'field_id' => $lunchField->id, 'db_id' => $fieldC->id,
                        'option_id' => 9102, 'option_label' => 'Option Y',
                        'value' => 'Option Y', 'unit_type' => 'price_per_pax', 'unit' => 1,
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
                        'db_id' => $fieldC->id, 'field_id' => $lunchField->id,
                        'label' => 'Boxed Trail Lunch: Option X → Option Y',
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

            $this->assertGreaterThan(0, $historyRows->count(), 'The real post-rotation downgrade must write a history row.');
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
