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
 * Regression: downgrading a STILL-ACTIVE (not removed) booking add-on with "Ticket Refund" — e.g.
 * Souvenir T-Shirt Size $25 (X-Large) -> $20 (Medium), refunding the $5 price delta and (by design)
 * keeping the fee — left the item's ENTIRE original fee sitting unmatched against a price that no
 * longer exists. A later cancel-booking 100% refund then paid out the whole stale fee (calculated
 * off the old $25) instead of only the portion still genuinely attributable to the item's current,
 * lower price -- double-counting the slice of fee that was already permanently forfeited alongside
 * the $5 price reduction, the same way processAddonFinancialTransactions()'s own 'full_refund'
 * branch already prices a downgrade's fee proportionally to what's actually being refunded.
 */
class TicketRefundDowngradeFeeProportionalOnCancelTest extends TestCase
{
    public function test_ticket_refund_downgrade_only_settles_the_proportional_fee_slice_so_cancel_refunds_the_rest(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Shirt Downgrade Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $shirtField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Souvenir T-Shirt Size',
            'type' => 'dropdown',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 20.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $shirtField->id,
            'pax_id' => 970100, 'field_label' => 'Souvenir T-Shirt Size', 'option_label' => 'Medium',
            'pax_index' => 'shirt-downgrade', 'unit_type' => 'one_time', 'unit' => 1,
        ]);
        // Original charge, still at the OLD (X-Large) price/fee.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_shirt_downgrade', 'field_price' => 25.00, 'field_fee' => 2.46,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId, 'amount' => 25.00, 'fee_amount' => 2.46,
            'via' => 'Card', 'payment_type' => 2, 'status' => 'Completed', 'charge_type' => 'addons',
            'transaction_number' => 'pi_shirt_downgrade', 'created_by' => 1,
            'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);

            // Downgrade X-Large -> Medium via Ticket Refund: $5 price back, fee (at this moment)
            // deliberately kept -- no Stripe call needed for this step (fee-only, $0 net here would
            // still trigger a $5 refund though, so mock it).
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_shirt_downgrade' && $params['amount'] === 500))
                ->willReturn((object) ['id' => 're_downgrade']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'added' => [], 'updated' => [], 'deleted' => [],
                'financial' => [
                    'action' => 'refund',
                    'to_refund' => [[
                        'db_id' => $field->id, 'field_id' => $shirtField->id,
                        'label' => 'Souvenir T-Shirt Size: X-Large -> Medium',
                        'refund_amount' => 5.00, 'credit_amount' => 5.00, 'reason' => 'updated',
                    ]],
                    'payment_method' => 'Card',
                    'refund_type' => 'ticket_refund',
                ],
            ]);

            // The field itself is still ACTIVE -- never removed, only downgraded.
            $this->assertDatabaseHas('tour_booking_addon_fields', ['id' => $field->id, 'deleted_at' => null]);

            $settlementRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($settlementRow);
            $this->assertEquals(5.00, (float) $settlementRow->field_price, 'The $5 price delta is genuinely refunded');
            $this->assertEquals(0.49, (float) $settlementRow->field_fee, 'Only the proportional 20% slice of the fee ($5 of $25) is settled, not the full $2.46');

            // Now cancel the booking (100% refund) -- must refund the item's CURRENT remaining
            // balance ($20 price + the fee still actually attributable to it), not the stale
            // fee that was priced for the no-longer-relevant $25.
            $fakeGateway2 = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway2->expects($this->once())
                ->method('refundPayment')
                ->with($this->callback(fn ($params) => $params['payment_intent'] === 'pi_shirt_downgrade' && $params['amount'] === 2197))
                ->willReturn((object) ['id' => 're_cancel']);
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway2);

            $repo->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $cancelRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->orderByDesc('id')
                ->first();
            $this->assertEquals(20.00, (float) $cancelRefundRow->field_price);
            $this->assertEquals(1.97, (float) $cancelRefundRow->field_fee, 'Cancel must refund only the $1.97 still attributable to the current $20 item, not the stale $2.46');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            DB::table('transactions')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->where('charge_type', 'addons')->delete();
            DB::table('addon_fields')->where('id', $shirtField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
