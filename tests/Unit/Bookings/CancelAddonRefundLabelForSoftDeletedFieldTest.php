<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: cancelling a booking whose pax had ALREADY been removed earlier (soft-deleting
 * their TourBookingAddonField), but whose paid add-on was never refunded at removal time (e.g.
 * the admin didn't select it in the "Update Booking" modal's add-ons block), correctly refunds
 * that add-on's money at cancel time via refundAddonsByBookingId() -- but the resulting history/
 * ledger line item rendered with a BLANK name. `addonField` is a SoftDeletes-scoped belongsTo, so
 * `$txn->addonField` came back null for a transaction whose field was already soft-deleted, and
 * `$txn->addonField->field_label ?? null` silently collapsed to null (PHP suppresses "read
 * property on null" through `??`) instead of throwing, leaving `array_filter([null, null])` empty
 * and the built "name" an empty string -- visible in the booking's History & Notes as a refund
 * line item with a dollar amount but no label at all.
 */
class CancelAddonRefundLabelForSoftDeletedFieldTest extends TestCase
{
    public function test_refunding_an_addon_whose_field_was_already_soft_deleted_still_shows_its_label(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;

        $field = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 820100, 'field_label' => 'Photography Package',
            'pax_index' => 'soft-deleted-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $field->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        // Simulate: this pax was removed earlier (their field soft-deleted), but their add-on was
        // never refunded at that point -- it's still sitting there 'paid'.
        $field->delete();
        $this->assertTrue($field->trashed());

        try {
            app(TourBookingAddonRepository::class)->refundAddonsByBookingId($bookingId, Constants::ADDONS_FULL_REFUND);

            $refundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($refundRow, 'The soft-deleted field\'s paid addon must still be refunded');
            $this->assertEquals(15.00, (float) $refundRow->field_price);

            $historyRow = \Illuminate\Support\Facades\DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->where('charge_type', 'addons')
                ->latest('id')
                ->first();
            $this->assertNotNull($historyRow);
            $details = json_decode($historyRow->details, true);
            $this->assertNotEmpty(
                $details['addons'][0]['name'] ?? '',
                'The refund history line item must still show the field\'s label even though its field was already soft-deleted'
            );
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $field->id)->forceDelete();
            TourBookingAddonField::withTrashed()->where('id', $field->id)->forceDelete();
            $booking->cleanup();
        }
    }
}
