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
 * Regression: from the "Add Passenger Add-ons" popup, removing 2 pax add-ons that were each
 * originally charged on a DIFFERENT PaymentIntent, together with adding 1 new add-on of the same
 * price (net action='refund', offset by the addition), wrote one history row whose "Added
 * (offset)" pill was attached to the WRONG PaymentIntent's row: whichever PI group happened to be
 * written first in processAddonFinancialTransactions()'s per-PI loop, rather than the PI group the
 * offset actually reduced (the cascade subtracts the offset starting from the *last*-inserted PI
 * group, while history rows are written in *first*-inserted order — a different group whenever a
 * refund spans 2+ PIs). That produced a real $15 refund row showing a self-contradictory
 * "Net Refunded ($15.00 − $15.00)" footer next to an actual $15.00 amount, while the PI genuinely
 * absorbed by the offset (netting to a real $0) wrote a bare, unexplained flat "$0.00" entry with
 * no offset pairing at all — two confusing rows instead of one row per PI, each internally
 * consistent with its own math.
 */
class UpdatePaxAddonsOffsetAttributionTest extends TestCase
{
    public function test_offset_is_attached_only_to_the_pi_group_it_actually_reduced(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Offset Attribution Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // Two pax, each already holding the SAME add-on, but charged on two DIFFERENT
        // PaymentIntents (e.g. added in two separate earlier "Add Add-ons" operations).
        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoField->id,
            'pax_id' => 900500, 'field_label' => 'Photography Package',
            'pax_index' => 'offset-attr-a', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $photoField->id,
            'pax_id' => 900501, 'field_label' => 'Photography Package',
            'pax_index' => 'offset-attr-b', 'person_ordinal' => 1,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldA->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_offset_attr_a', 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldB->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_offset_attr_b', 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        // Each PI's own original "Completed" charge — needed for processAddonFinancialTransactions()'s
        // $piAvailable cap check to allow a real Stripe refund to be attempted.
        foreach (['pi_offset_attr_a', 'pi_offset_attr_b'] as $pi) {
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId,
                'amount' => 15.00,
                'fee_amount' => 0,
                'via' => 'Card',
                'payment_type' => 2,
                'status' => 'Completed',
                'charge_type' => 'addons',
                'transaction_number' => $pi,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);
        }

        // Only ONE PI should ever be refunded via Stripe — the offset fully absorbs the other.
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->expects($this->once())
            ->method('refundPayment')
            ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_offset_attr_a'))
            ->willReturn((object) ['id' => 're_test_offset_attr']);
        $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => 900500, 'pax_index' => 'offset-attr-a', 'person_ordinal' => 0,
                        'added' => [], 'updated' => [],
                        'deleted' => [['db_id' => $fieldA->id, 'field_id' => $photoField->id]],
                    ],
                    [
                        'pax_id' => 900501, 'pax_index' => 'offset-attr-b', 'person_ordinal' => 1,
                        'added' => [], 'updated' => [],
                        'deleted' => [['db_id' => $fieldB->id, 'field_id' => $photoField->id]],
                    ],
                    [
                        'pax_id' => 900502, 'pax_index' => 'offset-attr-new', 'person_ordinal' => 2,
                        'added' => [[
                            'field_id' => $photoField->id, 'option_id' => null, 'option_label' => null,
                            'value' => 'checked', 'unit_type' => 'price_per_pax', 'unit' => 1,
                            'rule_id' => null, 'applied_rule_snapshot' => null,
                            'paid_price' => 0, 'paid_fee' => 0,
                        ]],
                        'updated' => [], 'deleted' => [],
                    ],
                ],
                'financial' => [
                    'action' => 'refund',
                    'net_amount' => 15.00,
                    'total_charge' => 15.00,
                    'total_refund' => 30.00,
                    'to_charge' => [[
                        'field_id' => $photoField->id,
                        'pax_index' => 'offset-attr-new',
                        'label' => 'Photography Package',
                        'option_id' => null,
                        'unit' => 1,
                        'new_price' => 15.00,
                        'old_db_id' => null,
                        'old_paid' => 0,
                        'charge_amount' => 15.00,
                        'field_price' => 15.00,
                        'option_price' => 0,
                        'field_additional_fee' => true,
                        'option_additional_fee' => false,
                        'reason' => 'added',
                    ]],
                    'to_refund' => [
                        [
                            'db_id' => $fieldA->id, 'field_id' => $photoField->id,
                            'label' => 'Photography Package',
                            'refund_amount' => 15.00, 'credit_amount' => 15.00, 'reason' => 'removed',
                        ],
                        [
                            'db_id' => $fieldB->id, 'field_id' => $photoField->id,
                            'label' => 'Photography Package',
                            'refund_amount' => 15.00, 'credit_amount' => 15.00, 'reason' => 'removed',
                        ],
                    ],
                    'payment_method' => 'Card',
                    'refund_type' => 'ticket_refund',
                ],
            ]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $fieldA->id]);
            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $fieldB->id]);

            $historyRows = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->orderBy('id')
                ->get();

            $this->assertCount(
                2,
                $historyRows,
                'Two PaymentIntents genuinely settled (one real Stripe refund, one fully offset) must write exactly one history row each — no more, no fewer.'
            );

            $realRefundRow = $historyRows->firstWhere('ticket_cost', 15.00);
            $leftoverRow   = $historyRows->firstWhere('ticket_cost', 0.00);

            $this->assertNotNull($realRefundRow, 'The PI the offset never touched must still show its real $15.00 refund');
            $this->assertNotNull($leftoverRow, 'The PI the offset actually reduced to $0 must still get its own history row');

            $realDetails = json_decode($realRefundRow->details, true);
            $this->assertArrayNotHasKey(
                'added_addons',
                $realDetails,
                'The real $15 refund was never touched by the offset — attaching the "Added (offset)" pill here is what produced the self-contradictory ($15.00 − $15.00) footer next to an actual $15.00 refund'
            );
            $this->assertEquals('Photography Package', $realDetails['addons'][0]['name']);
            $this->assertEquals(15.00, $realDetails['addons'][0]['price']);

            $leftoverDetails = json_decode($leftoverRow->details, true);
            $this->assertArrayHasKey(
                'added_addons',
                $leftoverDetails,
                'The PI genuinely absorbed by the offset must carry the "Added (offset)" pill that explains why it nets to $0 instead of showing an unexplained bare $0.00 entry'
            );
            $this->assertEquals('Photography Package', $leftoverDetails['added_addons'][0]['name']);
            $this->assertEquals(15.00, $leftoverDetails['added_addons'][0]['price']);
            $this->assertEquals('Photography Package', $leftoverDetails['addons'][0]['name']);
            $this->assertEquals(15.00, $leftoverDetails['addons'][0]['price'], 'Gross removed amount must still show even though nothing was actually refunded via Stripe for this PI');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$fieldA->id, $fieldB->id])->forceDelete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->where('id', $photoField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
