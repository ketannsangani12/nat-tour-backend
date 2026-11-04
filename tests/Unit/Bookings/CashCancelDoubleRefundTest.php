<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (same root cause as CancelBookingAddonAlreadyPartiallyRefundedTest, exercised
 * end-to-end through cancel() this time, on Cash rather than Card): a Cash booking with 2 pax,
 * each with their own $15 paid add-on. One pax is removed and their add-on refunded first
 * (leaving a NEW 'refund' row alongside the untouched original 'charge' row -- pax removal never
 * marks the charge row itself as settled). Cancelling the remaining booking then called
 * refundAddonsByBookingId(), which queried every 'paid'+'charge' row at face value with no
 * awareness of the prior refund, so it refunded the ALREADY-refunded pax's add-on a second time
 * on top of the remaining pax's own -- $30 refunded against only $15 actually still owed,
 * pushing the booking's Payment Total negative (more refunded than was ever collected).
 */
class CashCancelDoubleRefundTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_cancel_after_pax_removal_addon_refund_does_not_double_refund(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Two pax, each with their own $15 Photography Package.
        $fieldRemoved = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800200, 'field_label' => 'Photography Package',
            'pax_index' => 'removed-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemoved->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        // Simulate: this pax was already removed earlier, and THEIR addon was already refunded
        // (via the pax-removal flow) -- a NEW 'refund' row, original 'charge' row untouched.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemoved->id,
            'payment_method' => 'cash', 'status' => 'refunded', 'operation' => 'refund',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        // The other pax, still owed in full.
        $fieldRemaining = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 800201, 'field_label' => 'Photography Package',
            'pax_index' => 'remaining-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemaining->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldIds = [$fieldRemoved->id, $fieldRemaining->id];

        try {
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'cash cancel double refund test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $removedFieldRefundCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemoved->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $removedFieldRefundCount, 'An already-refunded field must not be refunded again by the cancellation');

            $remainingFieldRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemaining->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($remainingFieldRefundRow, 'The still-owed field must be refunded by the cancellation');
            $this->assertEquals(15.00, (float) $remainingFieldRefundRow->field_price);

            // The history entry shown on the booking page must total only the $15 genuinely
            // refunded by this cancellation, not $30 (which would double count the pax-removal
            // refund that already happened).
            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->where('charge_type', 'addons')
                ->latest('id')
                ->first();
            $this->assertNotNull($historyRow);
            $details = json_decode($historyRow->details, true);
            $this->assertEquals(15.00, (float) $details['amount'], 'Cancel-triggered addon refund history must show only the $15 still owed, not $30');
            $this->assertCount(1, $details['addons'], 'History must list only the one field actually refunded by this cancellation');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
