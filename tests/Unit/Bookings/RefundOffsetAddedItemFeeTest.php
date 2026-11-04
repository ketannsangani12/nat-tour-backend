<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
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
 * Regression: editing a card booking's add-ons in one request that BOTH removes a priced item
 * (e.g. Private Guide, $100 + $10 fee) AND adds a different priced item (e.g. Preferred Tour
 * Language, $20) nets to a REFUND — Stripe is only ever refunded the NET amount, so the new
 * item's $20 is effectively "kept" out of what would otherwise have been refunded.
 *
 * processAddonFinancialTransactions() recorded that new item's ledger row with $0 fee
 * (fieldFee forced to 0 whenever action='refund', regardless of whether it is itself offsetting
 * the refund) even though the removed item's FULL fee is what funds the gap: the real Stripe
 * refund is computed on the OFFSET-REDUCED total, so it returns LESS fee than the removed item's
 * ledger row claims was refunded. The difference (here: $2.00) silently vanishes — it stays
 * charged on the customer's card forever, but no add-on field's ledger balance accounts for it,
 * so a later 100% cancel refund (refundAddonsByBookingId) never sees or refunds it either.
 */
class RefundOffsetAddedItemFeeTest extends TestCase
{
    public function test_new_item_added_to_offset_a_refund_gets_its_fee_recorded_and_later_refunded(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 10])
            ->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Refund Offset Fee Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $guideFieldDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Private Guide',
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 100.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $languageFieldDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Preferred Tour Language',
            'type' => 'dropdown',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 0.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 1,
        ]);

        $guideField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $guideFieldDef->id,
            'pax_id' => null, 'field_label' => 'Private Guide',
            'pax_index' => null, 'unit_type' => 'one_time', 'unit' => 1,
        ]);
        // Original charge: $100 price + $10 fee (10%), the only money ever on this PI so far.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $guideField->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_offset_fee', 'field_price' => 100.00, 'field_fee' => 10.00,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 110.00, 'fee_amount' => 10.00,
            'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
            'transaction_number' => 'pi_offset_fee', 'created_by' => 1,
            'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        $repo = app(TourBookingAddonRepository::class);
        $newFieldId = null;

        try {
            // Remove Private Guide entirely ($110 full refund_amount) while adding Preferred
            // Tour Language ($20) in the SAME edit — the $20 offsets the refund, so Stripe is
            // only refunded the net: ($100 - $20) price + 10% fee on that reduced $80 = $88.00.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_offset_fee' && $params['amount'] === 8800))
                ->willReturn((object) ['id' => 're_offset_fee']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'added' => [[
                    'field_id' => $languageFieldDef->id,
                    'option_id' => null, 'option_label' => 'Spanish',
                    'value' => 'Spanish', 'unit_type' => 'one_time', 'unit' => 1,
                    'rule_id' => null, 'applied_rule_snapshot' => null,
                ]],
                'updated' => [],
                'deleted' => [],
                'financial' => [
                    'action' => 'refund',
                    'to_charge' => [[
                        'field_id' => $languageFieldDef->id,
                        'label' => 'Preferred Tour Language',
                        'charge_amount' => 20.00,
                        'field_price' => 20.00,
                        'option_price' => 0,
                        'field_additional_fee' => true,
                        'option_additional_fee' => false,
                        'reason' => 'added',
                    ]],
                    'to_refund' => [[
                        'db_id' => $guideField->id, 'field_id' => $guideFieldDef->id,
                        'label' => 'Private Guide',
                        'refund_amount' => 110.00, 'credit_amount' => 110.00, 'reason' => 'removed',
                    ]],
                    'payment_method' => 'Card',
                    'refund_type' => 'full_refund',
                ],
            ]);

            $newFieldId = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $languageFieldDef->id)
                ->value('id');
            $this->assertNotNull($newFieldId, 'The new Preferred Tour Language field row must have been created');

            $newFieldCharge = TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)
                ->where('operation', 'charge')
                ->first();
            $this->assertNotNull($newFieldCharge);
            $this->assertEquals(20.00, (float) $newFieldCharge->field_price);
            $this->assertEquals(
                2.00,
                (float) $newFieldCharge->field_fee,
                'The new item is genuinely funded by the reduced refund (fee included) — its ledger row must carry that fee, not silently drop it to $0'
            );

            // Now cancel the booking with a 100% refund — the new item's remaining $22.00
            // ($20 price + $2 fee) must be the ONLY thing still owed on this PI (Private Guide
            // nets to $0 — fully closed out by its own removal above) and must be refunded.
            $fakeGateway2 = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway2->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_offset_fee' && $params['amount'] === 2200))
                ->willReturn((object) ['id' => 're_offset_fee_cancel']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway2);

            $repo->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $cancelRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $newFieldId)
                ->where('operation', 'refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($cancelRefundRow, 'Cancelling must actually refund the new item — it must not be silently invisible to refundAddonsByBookingId()');
            $this->assertEquals(20.00, (float) $cancelRefundRow->field_price);
            $this->assertEquals(2.00, (float) $cancelRefundRow->field_fee, 'The $2.00 fee this item was funded by must be refunded on cancellation, not permanently lost');
        } finally {
            $ids = array_filter([$guideField->id, $newFieldId]);
            if (!empty($ids)) {
                TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $ids)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->whereIn('id', [$guideFieldDef->id, $languageFieldDef->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
