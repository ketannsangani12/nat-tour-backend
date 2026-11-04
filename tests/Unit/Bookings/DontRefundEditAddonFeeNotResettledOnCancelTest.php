<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: removing a general (not pax-tied) add-on via the "Edit Add-ons" flow
 * (updateBookingAddons() -> processAddonFinancialTransactions()) with "Don't Refund" correctly
 * zeroed out the PRICE side against the original charge (item['refund_amount'] is always the full
 * original price, independent of refund_type -- only refund_type gates whether Stripe is actually
 * called), but hard-coded the FEE side of the settlement record to $0 for 'dont_refund' -- the same
 * treatment as 'ticket_refund', which deliberately leaves its fee "open" for a possible later
 * upgrade to a 100% refund. Unlike ticket_refund, "Don't Refund" is a FINAL decision: leaving its
 * fee unmatched meant a LATER cancel-booking refund had no record that fee was ever settled and
 * refunded it anyway -- e.g. a $75 + $7.37 fee "Private Guide" add-on removed with Don't Refund
 * correctly showed $0.00 price but still paid out the $7.37 fee when the booking was cancelled.
 */
class DontRefundEditAddonFeeNotResettledOnCancelTest extends TestCase
{
    public function test_dont_refund_removal_settles_the_fee_so_a_later_cancel_never_refunds_it(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) \Illuminate\Support\Facades\DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Private Guide Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $guideField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Private Guide',
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 75.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $guideField->id,
            'pax_id' => 950100, 'field_label' => 'Private Guide',
            'pax_index' => 'general-addon', 'unit_type' => 'one_time', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'card', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => 'pi_private_guide', 'field_price' => 75.00, 'field_fee' => 7.37,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);

            // Remove the whole add-on with "Don't Refund" -- no Stripe call for this operation at
            // all, matching production (the frontend always sends the full original price as
            // refund_amount; refund_type alone decides whether money actually moves).
            $repo->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'added' => [], 'updated' => [],
                'deleted' => [['db_id' => $field->id, 'field_id' => $guideField->id]],
                'financial' => [
                    'action' => 'refund',
                    'to_refund' => [[
                        'db_id' => $field->id, 'field_id' => $guideField->id,
                        'label' => 'Private Guide',
                        'refund_amount' => 75.00, 'credit_amount' => 75.00, 'reason' => 'removed',
                    ]],
                    'payment_method' => 'Card',
                    'refund_type' => 'dont_refund',
                ],
            ]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $field->id]);

            $settlementRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($settlementRow, 'A settlement row must be recorded for the removed add-on');
            $this->assertEquals(75.00, (float) $settlementRow->field_price, 'Price already correctly nets to $0');
            $this->assertEquals(7.37, (float) $settlementRow->field_fee, 'Fee must ALSO be settled -- "Don\'t Refund" is final, not left open like Ticket Refund');

            // Now cancel the booking (100% refund) -- the fee must not be paid out a second time.
            $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
            $fakeGateway->expects($this->never())->method('refundPayment');
            $this->app->instance(PaymentGatewayInterface::class, $fakeGateway);

            $repo->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $refundCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $refundCount, 'A "Don\'t Refund" removal must never be refunded again by a later cancellation, for price OR fee');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', [$field->id])->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$field->id])->forceDelete();
            \Illuminate\Support\Facades\DB::table('addon_fields')->where('id', $guideField->id)->delete();
            \Illuminate\Support\Facades\DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
