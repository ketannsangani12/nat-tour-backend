<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Models\Admin;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression for the real end-to-end bug (unlike the other Cancel*DoubleRefund tests, which
 * simulate an already-refunded field by hand-inserting a matching 'refund' row): a pax is removed
 * via the actual deletePaxAddons() with refund_type='dont_refund' -- which, before this fix,
 * created NO transaction row at all for the kept add-on, leaving its original 'paid' charge row
 * completely unmatched. Cancelling the remaining booking afterward then had no way to know that
 * charge had already been finally settled (kept, not refunded) and refunded it anyway, undoing the
 * admin's earlier "Don't Refund" decision.
 */
class DontRefundPaxRemovalThenCancelDoubleRefundTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_dont_refund_pax_removal_addon_is_not_refunded_again_by_a_later_cancellation(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Two pax, each with their own $15 Photography Package, both genuinely paid.
        $fieldRemoved = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 900100, 'field_label' => 'Photography Package',
            'pax_index' => 'removed-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemoved->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldRemaining = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 900101, 'field_label' => 'Photography Package',
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
            // Step 1: remove the pax, keeping their add-on money -- the real production path,
            // not a hand-inserted fixture row.
            app(TourBookingAddonRepository::class)->deletePaxAddons(
                $bookingId,
                json_encode(['removed-pax']),
                Constants::ADDONS_DONT_REFUND,
                'pax removed, addon kept'
            );

            $this->assertTrue(
                TourBookingAddonField::withTrashed()->find($fieldRemoved->id)->trashed(),
                'The removed pax addon field must be soft-deleted by deletePaxAddons()'
            );

            // The fix: a same-amount 'refunded' row must now exist so the charge nets to $0 and
            // is never picked up again -- even though the customer was never actually paid back.
            $settlementRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemoved->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($settlementRow, 'deletePaxAddons() must record a settlement row for a kept ("Don\'t Refund") add-on');
            $this->assertEquals(15.00, (float) $settlementRow->field_price, 'The settlement row must match the original charge so it nets to $0');

            // Step 2: cancel the remaining booking with a 100% refund.
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'dont refund then cancel test',
                'api' => 1,
            ]);
            app(TourBookingRepository::class)->cancel($request, $bookingId);

            // The kept add-on must NOT get refunded a second time by the cancellation.
            $removedFieldRefundCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemoved->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $removedFieldRefundCount, 'A "Don\'t Refund" pax-removal add-on must not be refunded again when the booking is later cancelled');

            // The still-owed remaining pax's add-on must genuinely be refunded by the cancellation.
            $remainingFieldRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemaining->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($remainingFieldRefundRow, 'The still-owed field must be refunded by the cancellation');
            $this->assertEquals(15.00, (float) $remainingFieldRefundRow->field_price);

            // The cancellation's own addon-refund history entry must total only the $15 genuinely
            // refunded now, not $30 (which would double count the kept add-on).
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
