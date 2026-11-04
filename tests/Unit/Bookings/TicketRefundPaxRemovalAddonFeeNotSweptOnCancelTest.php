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
 * Regression: a pax removed via "Ticket Cost" (Constants::ADDONS_TICKET_REFUND) correctly keeps
 * their TICKET-side fee forever -- that pax's ticket record is simply gone, so there's no later
 * mechanism that could ever revisit it. But their ADD-ON's fee is a different story:
 * deletePaxAddons() deliberately leaves an add-on's fee portion unmatched for Ticket Refund (so a
 * direct edit to that SAME item can still upgrade it to a 100% refund later), and
 * refundAddonsByBookingId() (run again at cancel-booking time) had no way to tell "deliberately
 * left open for a future edit" apart from "genuinely still owed" -- so cancelling the booking swept
 * up and refunded that already-decided fee anyway, inconsistent with the final decision already
 * made on the very same pax's ticket side.
 */
class TicketRefundPaxRemovalAddonFeeNotSweptOnCancelTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_ticket_refund_pax_removal_addon_fee_is_not_swept_up_by_a_later_cancellation(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Two pax, each with their own $15 + $1.47 fee Photography Package, genuinely paid.
        $fieldRemoved = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 960100, 'field_label' => 'Photography Package',
            'pax_index' => 'removed-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemoved->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldRemaining = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 960101, 'field_label' => 'Photography Package',
            'pax_index' => 'remaining-pax', 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $fieldRemaining->id,
            'payment_method' => 'cash', 'status' => 'paid', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 1.47,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        $fieldIds = [$fieldRemoved->id, $fieldRemaining->id];

        try {
            // Step 1: remove the pax using "Ticket Cost" -- price refunded, fee deliberately kept
            // (open for a possible later direct upgrade to a 100% refund on this same item).
            app(TourBookingAddonRepository::class)->deletePaxAddons(
                $bookingId,
                json_encode(['removed-pax']),
                Constants::ADDONS_TICKET_REFUND,
                'pax removed via ticket cost'
            );

            $this->assertTrue(
                TourBookingAddonField::withTrashed()->find($fieldRemoved->id)->trashed()
            );
            $settlementRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemoved->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($settlementRow);
            $this->assertEquals(15.00, (float) $settlementRow->field_price, 'Price is refunded by Ticket Refund');
            $this->assertEquals(0.00, (float) $settlementRow->field_fee, 'Fee is deliberately left unmatched by Ticket Refund at removal time');

            // Step 2: cancel the remaining booking with a 100% refund -- this must NOT sweep up
            // and pay out the fee that was deliberately left open on the already-removed pax.
            $request = new Request();
            $request->merge([
                'cancel_booking' => 1,
                'refund_type' => '100_refund',
                'is_cash' => 'Cash',
                'comment' => 'ticket refund pax then full cancel test',
                'api' => 1,
            ]);
            app(TourBookingRepository::class)->cancel($request, $bookingId);

            $removedFieldRefundCount = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemoved->id)
                ->where('operation', 'refund')
                ->count();
            $this->assertSame(1, $removedFieldRefundCount, 'The already-removed pax\'s addon (price OR fee) must never be touched again by a later cancellation');

            // The still-owed remaining pax's add-on (price + fee) must genuinely be refunded.
            $remainingFieldRefundRow = TourBookingAddonTransaction::where('tour_booking_addon_id', $fieldRemaining->id)
                ->where('operation', 'refund')
                ->first();
            $this->assertNotNull($remainingFieldRefundRow);
            $this->assertEquals(15.00, (float) $remainingFieldRefundRow->field_price);
            $this->assertEquals(1.47, (float) $remainingFieldRefundRow->field_fee);

            // The cancellation's own addon-refund history entry must total only the remaining
            // pax's $16.47, not $17.94 (which would double count the already-removed pax's fee).
            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->where('charge_type', 'addons')
                ->latest('id')
                ->first();
            $this->assertNotNull($historyRow);
            $details = json_decode($historyRow->details, true);
            $this->assertEquals(16.47, (float) $details['amount'], 'Cancel-triggered addon refund history must show only the remaining pax\'s $16.47, not $17.94');
            $this->assertCount(1, $details['addons'], 'History must list only the one field actually refunded by this cancellation');
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
