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
 * Verification pass for "Don't Refund" at cancel time -- same shape of scenario as the other
 * three refund types (Weather/100%, Ticket Refund, Partial Refund), all of which had a real bug
 * this session: one pax already removed and their paid add-on already refunded (their field now
 * soft-deleted), then the remaining booking is cancelled with "Don't Refund".
 *
 * Confirms refundAddonsByBookingId()'s shared fixes (net-out-prior-refunds, and withTrashed() for
 * the label) already cover this refund type too, since both fixes sit before the refund-type
 * switch inside that one shared method -- there is no Don't-Refund-specific code path that could
 * have its own separate bug here.
 */
class DontRefundCancelAddonSettlementTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_dont_refund_cancel_does_not_resettle_an_already_refunded_addon_and_labels_the_rest_correctly(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Pax already removed (field soft-deleted) and their $15 addon already refunded.
        $fieldRemoved = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 840100, 'field_label' => 'Photography Package',
            'pax_index' => 'removed-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemoved->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemoved->id,
            'payment_method' => 'cash', 'status' => 'refunded', 'operation' => 'refund',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);
        $fieldRemoved->delete();
        $this->assertTrue($fieldRemoved->trashed());

        // Remaining pax, still owed in full (still 'paid', untouched).
        $fieldRemaining = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 840101, 'field_label' => 'Photography Package',
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
                'refund_type' => 'dont_refund',
                'is_cash' => 'Cash',
                'comment' => 'dont refund settlement test',
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->cancel($request, $bookingId);

            // The already-refunded field must NOT get re-processed by this cancellation at all.
            $removedFieldSettlementCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemoved->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $removedFieldSettlementCount, 'An already-refunded field must not be touched again by a Don\'t-Refund cancellation');

            // The remaining field must be settled (money kept, $0 paid out) exactly once.
            $remainingSettlementRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemaining->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($remainingSettlementRow, 'The still-owed field must be settled by the cancellation');
            $this->assertEquals(0.00, (float) $remainingSettlementRow->field_price, "Don't Refund keeps the money -- nothing is paid out");

            // The history line item for the settled field must still show its real label, not a
            // blank one leaking from the soft-deleted sibling.
            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->where('charge_type', 'addons')
                ->latest('id')
                ->first();
            $this->assertNotNull($historyRow);
            $details = json_decode($historyRow->details, true);
            $this->assertCount(1, $details['addons'], 'Only the still-owed field should appear in this cancellation\'s settlement entry');
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);

            // Nothing must have moved for the ticket side either -- Don't Refund always keeps $0.
            $cancelledRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->latest('id')
                ->first();
            $this->assertNotNull($cancelledRow);
            $cancelDetails = json_decode($cancelledRow->details, true);
            $this->assertEquals(0.00, (float) $cancelDetails['amount'], "Don't Refund must never pay anything out on the ticket side");
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
