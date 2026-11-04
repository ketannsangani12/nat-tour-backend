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
 * Regression, closing a gap in the original hasRefundOffset fix (RefundOffsetAddedItemFeeTest):
 * that fix always gave a new offsetting item its OWN native fee (round($fieldPrice * feePct/100))
 * and always tied it to a SINGLE original transaction ($refOriginalTxn — whichever to_refund item
 * happened to be found first), regardless of how many distinct PaymentIntents the removed items
 * actually spanned or what $refund_type applied.
 *
 * Two distinct problems that produces:
 *  1) Multi-PI: if the removed items span two different PIs, every new item was still tied to
 *     just ONE of them — the same PI-mismatch shape already fixed for hasChargeOffset.
 *  2) Non-'full_refund': for 'ticket_refund'/'dont_refund', the removed item's fee was NEVER
 *     going to be refunded regardless of any offset (permanently kept against THAT field, by
 *     admin choice) — so there is no fee "slack" freed up by the offset to redirect. Fabricating
 *     one anyway overstates the new item's true value beyond what the offset genuinely funded.
 */
class RefundOffsetMultiPiAndRefundTypeTest extends TestCase
{
    public function test_offsetting_item_splits_across_multiple_original_pis(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 10])
            ->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Multi-PI Refund Offset Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $fieldA = AddonField::create([
            'form_id' => $form->id, 'label' => 'Old Item A', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 30.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $fieldB = AddonField::create([
            'form_id' => $form->id, 'label' => 'Old Item B', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 30.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 1,
        ]);
        $newField = AddonField::create([
            'form_id' => $form->id, 'label' => 'New Item', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 0.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 2,
        ]);

        // Two removed items, each fully paid on a DIFFERENT original PI.
        $rowA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $fieldA->id,
            'pax_id' => null, 'field_label' => 'Old Item A', 'pax_index' => null,
            'unit_type' => 'one_time', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $rowA->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_multi_a', 'field_price' => 30.00, 'field_fee' => 3.00,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        $rowB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $fieldB->id,
            'pax_id' => null, 'field_label' => 'Old Item B', 'pax_index' => null,
            'unit_type' => 'one_time', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $rowB->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_multi_b', 'field_price' => 30.00, 'field_fee' => 3.00,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        foreach (['pi_multi_a', 'pi_multi_b'] as $pi) {
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId, 'amount' => 33.00, 'fee_amount' => 3.00,
                'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
                'transaction_number' => $pi, 'created_by' => 1,
                'created_by_user_type' => 'Admin', 'created_at' => now(),
            ]);
        }

        $newFieldId = null;

        try {
            // Remove BOTH old items ($33 + $33 = $66) while adding ONE new item worth $40 —
            // nets to a refund, offset by the $40 addition. The cascade drains the
            // last-inserted PI group first (pi_multi_b): its full $30 price is absorbed,
            // leaving it with nothing left to actually refund via Stripe. The remaining $10 of
            // offset then drains PART of pi_multi_a's $30, leaving $20 price (+$2 fee) genuinely
            // still refundable there — $22.00 real Stripe refund. The $40 new item must be split
            // to match: $30+$3fee=$33 from pi_multi_b, $10+$1fee=$11 from pi_multi_a.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_multi_a' && $params['amount'] === 2200))
                ->willReturn((object) ['id' => 're_multi']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'added' => [[
                    'field_id' => $newField->id, 'option_id' => null, 'option_label' => null,
                    'value' => null, 'unit_type' => 'one_time', 'unit' => 1,
                    'rule_id' => null, 'applied_rule_snapshot' => null,
                ]],
                'updated' => [], 'deleted' => [],
                'financial' => [
                    'action' => 'refund',
                    'payment_method' => 'Card',
                    'refund_type' => 'full_refund',
                    'to_charge' => [[
                        'field_id' => $newField->id, 'label' => 'New Item',
                        'charge_amount' => 40.00, 'field_price' => 40.00, 'option_price' => 0,
                        'field_additional_fee' => true, 'option_additional_fee' => false,
                        'reason' => 'added',
                    ]],
                    'to_refund' => [
                        [
                            'db_id' => $rowA->id, 'field_id' => $fieldA->id, 'label' => 'Old Item A',
                            'refund_amount' => 33.00, 'credit_amount' => 33.00, 'reason' => 'removed',
                        ],
                        [
                            'db_id' => $rowB->id, 'field_id' => $fieldB->id, 'label' => 'Old Item B',
                            'refund_amount' => 33.00, 'credit_amount' => 33.00, 'reason' => 'removed',
                        ],
                    ],
                ],
            ]);

            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $newField->id)->value('id');
            $this->assertNotNull($newFieldId);

            $chargeRows = TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)
                ->where('operation', 'charge')->get();

            $totalsByPi = [];
            foreach ($chargeRows as $row) {
                $totalsByPi[$row->transaction_id] = ($totalsByPi[$row->transaction_id] ?? 0)
                    + (float) $row->field_price + (float) $row->field_fee;
            }

            $this->assertCount(2, $totalsByPi, 'The $40 new item must be split across BOTH original PIs, not funneled onto just one');
            $this->assertEqualsWithDelta(33.00, $totalsByPi['pi_multi_b'] ?? 0, 0.01, 'pi_multi_b (drained last-inserted-first, per the same cascade order the refund side already uses) supplies its full $30 + $3 fee');
            $this->assertEqualsWithDelta(11.00, $totalsByPi['pi_multi_a'] ?? 0, 0.01, 'pi_multi_a supplies the remaining $10 + $1 fee');
        } finally {
            $ids = array_filter([$rowA->id, $rowB->id, $newFieldId]);
            if (!empty($ids)) {
                TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $ids)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->whereIn('id', [$fieldA->id, $fieldB->id, $newField->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }

    public function test_offsetting_item_gets_no_fabricated_fee_under_ticket_refund(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 10])
            ->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Ticket Refund Offset Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $oldFieldDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Old Item', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 100.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $newFieldDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'New Item', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 0.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 1,
        ]);

        $oldRow = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $oldFieldDef->id,
            'pax_id' => null, 'field_label' => 'Old Item', 'pax_index' => null,
            'unit_type' => 'one_time', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $oldRow->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_ticket_refund', 'field_price' => 100.00, 'field_fee' => 10.00,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 110.00, 'fee_amount' => 10.00,
            'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
            'transaction_number' => 'pi_ticket_refund', 'created_by' => 1,
            'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        $newFieldId = null;

        try {
            // Ticket Refund on the $100 removal (fee permanently kept, per admin choice) while
            // adding a $20 new item in the same edit. Real refund is price-only, offset-reduced:
            // ($100 - $20) = $80, fee still $0 (ticket_refund never returns fee, offset or not).
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_ticket_refund' && $params['amount'] === 8000))
                ->willReturn((object) ['id' => 're_ticket_refund']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'added' => [[
                    'field_id' => $newFieldDef->id, 'option_id' => null, 'option_label' => null,
                    'value' => null, 'unit_type' => 'one_time', 'unit' => 1,
                    'rule_id' => null, 'applied_rule_snapshot' => null,
                ]],
                'updated' => [], 'deleted' => [],
                'financial' => [
                    'action' => 'refund',
                    'payment_method' => 'Card',
                    'refund_type' => 'ticket_refund',
                    'to_charge' => [[
                        'field_id' => $newFieldDef->id, 'label' => 'New Item',
                        'charge_amount' => 20.00, 'field_price' => 20.00, 'option_price' => 0,
                        'field_additional_fee' => true, 'option_additional_fee' => false,
                        'reason' => 'added',
                    ]],
                    'to_refund' => [[
                        'db_id' => $oldRow->id, 'field_id' => $oldFieldDef->id, 'label' => 'Old Item',
                        'refund_amount' => 100.00, 'credit_amount' => 100.00, 'reason' => 'removed',
                    ]],
                ],
            ]);

            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $newFieldDef->id)->value('id');
            $this->assertNotNull($newFieldId);

            $chargeRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)
                ->where('operation', 'charge')->first();
            $this->assertNotNull($chargeRow);
            $this->assertEquals('pi_ticket_refund', $chargeRow->transaction_id);
            $this->assertEquals(20.00, (float) $chargeRow->field_price);
            $this->assertEquals(
                0.00,
                (float) $chargeRow->field_fee,
                'Ticket Refund never returns the removed item\'s fee regardless of any offset — there is no fee "slack" for the new item to inherit, so fabricating one here would overstate what the offset genuinely funded'
            );
        } finally {
            $ids = array_filter([$oldRow->id, $newFieldId]);
            if (!empty($ids)) {
                TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $ids)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->whereIn('id', [$oldFieldDef->id, $newFieldDef->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
