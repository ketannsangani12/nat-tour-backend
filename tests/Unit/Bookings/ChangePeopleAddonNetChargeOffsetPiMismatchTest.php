<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\ChangePeopleAddonService;
use App\Models\TourBooking;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: using "Change People" to swap one pax's priced add-on for TWO new pax' add-ons in
 * one edit (net CHARGE, offset by the removed pax's add-on) only ever confirms a brand-new
 * PaymentIntent for the NET amount still owed — the removed item's own price+fee is never
 * actually refunded via Stripe (settleAddonTransactions() never executes a real refund for it in
 * this shape, its money simply stays wherever it already was, untouched).
 *
 * settleAddonTransactions()'s chargeItems loop tied EVERY new item's FULL gross price to that
 * SAME brand-new PaymentIntent regardless, so the ledger ended up claiming more money sits on it
 * than Stripe ever actually put there. A later 100% cancel refund (refundAddonsByBookingId) then
 * asked Stripe to refund more from that PI than it ever charged, and Stripe rejected the whole
 * cancellation with "Refund amount ($X) is greater than charge amount ($Y)" — the booking was
 * left stuck, uncancellable.
 */
class ChangePeopleAddonNetChargeOffsetPiMismatchTest extends TestCase
{
    public function test_new_items_draw_from_the_untouched_original_pi_before_the_brand_new_one(): void
    {
        $booking = (new CreateABooking())
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 10])
            ->create();
        $bookingId = $booking->bookingId;
        $tourBooking = TourBooking::find($bookingId);

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'CPA Net Charge Offset Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoFieldDef = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 50.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // The pax being removed, still holding its ORIGINAL, untouched $55 (=$50+10% fee) charge.
        $oldField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoFieldDef->id,
            'pax_id' => 800100, 'field_label' => 'Photography Package',
            'pax_index' => 'cpa-old', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $oldField->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_orig_card', 'field_price' => 50.00, 'field_fee' => 5.00,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 55.00, 'fee_amount' => 5.00,
            'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
            'transaction_number' => 'pi_orig_card', 'created_by' => 1,
            'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        $newFieldDbIdMap = null;

        try {
            // Change People: remove the old pax's Photography Package while adding it for TWO
            // brand-new pax in the same edit. Net owed = (2 x $55) - $55 = $55 — that is exactly
            // what the NEW PaymentIntent (pi_new_card) is really charged for.
            $request = (object) [
                'payment_method' => 'Card',
                'addons_removed' => [
                    ['db_id' => $oldField->id],
                ],
                'addons_added' => [
                    [
                        'field_id' => $photoFieldDef->id, 'pax_id' => 800200, 'pax_index' => 'cpa-new-a',
                        'person_ordinal' => 'Adult 1', 'unit_type' => 'price_per_pax', 'unit' => 1,
                        'option_id' => null, 'option_label' => null, 'value' => null,
                        'field_label' => 'Photography Package',
                    ],
                    [
                        'field_id' => $photoFieldDef->id, 'pax_id' => 800201, 'pax_index' => 'cpa-new-b',
                        'person_ordinal' => 'Adult 2', 'unit_type' => 'price_per_pax', 'unit' => 1,
                        'option_id' => null, 'option_label' => null, 'value' => null,
                        'field_label' => 'Photography Package',
                    ],
                ],
                'addons_updated' => [],
            ];

            $result = ChangePeopleAddonService::processAddons($request, $bookingId, [], [
                'action' => 'charge',
                'payment_method' => 'Card',
                'transaction_id' => 'pi_new_card',
                // Matches changePeopleBooking()'s real charge-branch behavior: an embedded addon
                // removal in a net-charge edit is settled as a full refund of that item, not left
                // as an unmatched "ticket refund" fee (see the addon refund_type default fix).
                'refund_type' => 'full_refund',
            ]);

            $newFieldDbIdMap = $result['field_db_id_map'];
            $newFieldAId = $newFieldDbIdMap[$photoFieldDef->id . '_cpa-new-a'] ?? null;
            $newFieldBId = $newFieldDbIdMap[$photoFieldDef->id . '_cpa-new-b'] ?? null;
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
                55.00,
                $totalsByPi['pi_orig_card'] ?? 0,
                0.01,
                'The old, untouched PI genuinely still holds $55 (never refunded) — one new item must draw from it instead of everything landing on the brand-new PI'
            );
            $this->assertEqualsWithDelta(
                55.00,
                $totalsByPi['pi_new_card'] ?? 0,
                0.01,
                'The brand-new PI was only ever really charged its NET $55 — the ledger tied to it must not exceed that'
            );
            $this->assertCount(2, $totalsByPi, 'Every dollar of the two new items must be attributed to a PI that genuinely holds it, no more no less');

            // Now cancel the booking with a 100% refund — this is the exact operation that used
            // to fail with "Refund amount ($X) is greater than charge amount ($Y)".
            $refundCalls = [];
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->exactly(2))
                ->method('refundPayment')
                ->willReturnCallback(function ($params) use (&$refundCalls) {
                    $refundCalls[$params['payment_intent']] = $params['amount'];
                    return (object) ['id' => 're_' . $params['payment_intent']];
                });
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo = app(TourBookingAddonRepository::class);
            $repo->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $this->assertEquals(5500, $refundCalls['pi_orig_card'] ?? null, 'Cancel must refund only what pi_orig_card really has ($55.00), matching what was never actually touched');
            $this->assertEquals(5500, $refundCalls['pi_new_card'] ?? null, 'Cancel must refund exactly what pi_new_card was really charged ($55.00)');
        } finally {
            $ids = array_filter([
                $oldField->id,
                $newFieldDbIdMap[$photoFieldDef->id . '_cpa-new-a'] ?? null,
                $newFieldDbIdMap[$photoFieldDef->id . '_cpa-new-b'] ?? null,
            ]);
            if (!empty($ids)) {
                TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $ids)->forceDelete();
            }
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->where('id', $photoFieldDef->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
