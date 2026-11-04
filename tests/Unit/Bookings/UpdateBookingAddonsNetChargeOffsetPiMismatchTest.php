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
 * Regression: editing a card booking's "Booking Add-ons" in one request that both removes a
 * priced item (Private Guide, $100 + $10 fee) AND adds two new priced items ($100 each) nets to a
 * CHARGE — processAddonFinancialTransactions() only ever confirms a brand-new PaymentIntent for
 * the NET amount still owed; the removed item's own price+fee is never actually refunded via
 * Stripe here (fully absorbed by the offset), it simply stays wherever it was already, genuinely
 * charged, untouched.
 *
 * The to_charge loop tied EVERY new item's FULL gross price to that SAME brand-new PaymentIntent
 * regardless, so the ledger ended up claiming more money sits on it than Stripe ever actually put
 * there. A later 100% cancel refund (refundAddonsByBookingId) would then ask Stripe to refund more
 * from that PI than it was ever charged, and Stripe would reject the whole cancellation with
 * "Refund amount ($X) is greater than charge amount ($Y)" — the mirror image of the bug already
 * fixed for ChangePeopleAddonService (see ChangePeopleAddonNetChargeOffsetPiMismatchTest), but in
 * the OTHER addon editor (the "Booking Add-ons" / "Add Passenger Add-ons" flow).
 */
class UpdateBookingAddonsNetChargeOffsetPiMismatchTest extends TestCase
{
    public function test_new_items_draw_from_the_untouched_original_pi_before_the_brand_new_one(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 10])
            ->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'UBA Net Charge Offset Form ' . uniqid(),
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
        $addonADef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'New Addon A',
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 100.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 1,
        ]);
        $addonBDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'New Addon B',
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 100.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 2,
        ]);

        // The removed item, still holding its ORIGINAL, untouched $110 (=$100+10% fee) charge.
        $guideField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $guideFieldDef->id,
            'pax_id' => null, 'field_label' => 'Private Guide',
            'pax_index' => null, 'unit_type' => 'one_time', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $guideField->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_orig_uba', 'field_price' => 100.00, 'field_fee' => 10.00,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 110.00, 'fee_amount' => 10.00,
            'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
            'transaction_number' => 'pi_orig_uba', 'created_by' => 1,
            'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        $newFieldAId = null;
        $newFieldBId = null;

        try {
            $newPi = 'pi_new_uba';
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->method('retrievePayment')->willReturn((object) [
                'id' => $newPi,
                'charges' => (object) ['data' => [(object) ['application_fee' => null, 'transfer' => null]]],
            ]);
            // The removed item is fully absorbed by the offset (its $100 price cap <= the $200
            // gross being added) — nothing left to actually refund via Stripe for it.
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'added' => [
                    [
                        'field_id' => $addonADef->id, 'option_id' => null, 'option_label' => null,
                        'value' => null, 'unit_type' => 'one_time', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ],
                    [
                        'field_id' => $addonBDef->id, 'option_id' => null, 'option_label' => null,
                        'value' => null, 'unit_type' => 'one_time', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ],
                ],
                'updated' => [],
                'deleted' => [],
                'financial' => [
                    'action' => 'charge',
                    'payment_method' => 'Card',
                    'payment_intent_id' => $newPi,
                    'net_amount' => 90.00,
                    'refund_type' => 'full_refund',
                    'to_charge' => [
                        [
                            'field_id' => $addonADef->id, 'label' => 'New Addon A',
                            'charge_amount' => 100.00, 'field_price' => 100.00, 'option_price' => 0,
                            'field_additional_fee' => true, 'option_additional_fee' => false,
                            'reason' => 'added',
                        ],
                        [
                            'field_id' => $addonBDef->id, 'label' => 'New Addon B',
                            'charge_amount' => 100.00, 'field_price' => 100.00, 'option_price' => 0,
                            'field_additional_fee' => true, 'option_additional_fee' => false,
                            'reason' => 'added',
                        ],
                    ],
                    'to_refund' => [
                        [
                            'db_id' => $guideField->id, 'field_id' => $guideFieldDef->id,
                            'label' => 'Private Guide',
                            'refund_amount' => 110.00, 'credit_amount' => 110.00, 'reason' => 'removed',
                        ],
                    ],
                ],
            ]);

            $newFieldAId = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $addonADef->id)->value('id');
            $newFieldBId = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $addonBDef->id)->value('id');
            $this->assertNotNull($newFieldAId);
            $this->assertNotNull($newFieldBId);

            $chargeRows = TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$newFieldAId, $newFieldBId])
                ->where('operation', 'charge')
                ->get();

            $totalsByPi = [];
            foreach ($chargeRows as $row) {
                $totalsByPi[$row->transaction_id] = ($totalsByPi[$row->transaction_id] ?? 0)
                    + (float) $row->field_price + (float) $row->field_fee;
            }

            $this->assertEqualsWithDelta(
                110.00,
                $totalsByPi['pi_orig_uba'] ?? 0,
                0.01,
                'The old, untouched PI genuinely still holds $110 (never refunded) — one new item must draw from it instead of everything landing on the brand-new PI'
            );
            $this->assertEqualsWithDelta(
                100.00,
                $totalsByPi['pi_new_uba'] ?? 0,
                0.01,
                'The brand-new PI must only carry what it was NOT possible to fund from the untouched old PI'
            );
            $this->assertCount(2, $totalsByPi);

            // Now cancel the booking with a 100% refund — this is the exact operation that used
            // to fail with "Refund amount ($X) is greater than charge amount ($Y)".
            $refundCalls = [];
            $fakeGateway2 = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway2->expects($this->exactly(2))
                ->method('refundPayment')
                ->willReturnCallback(function ($params) use (&$refundCalls) {
                    $refundCalls[$params['payment_intent']] = $params['amount'];
                    return (object) ['id' => 're_' . $params['payment_intent']];
                });
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway2);

            $repo->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $this->assertEquals(11000, $refundCalls['pi_orig_uba'] ?? null, 'Cancel must refund only what pi_orig_uba really has ($110.00), matching what was never actually touched');
            $this->assertEquals(10000, $refundCalls['pi_new_uba'] ?? null, 'Cancel must refund exactly what pi_new_uba was really charged ($100.00)');
        } finally {
            $ids = array_filter([$guideField->id, $newFieldAId, $newFieldBId]);
            if (!empty($ids)) {
                TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $ids)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->whereIn('id', [$guideFieldDef->id, $addonADef->id, $addonBDef->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
