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
 * Companion to CashCancelDoubleRefundTest (which covers "Weather / 100%") and
 * CancelBookingAddonAlreadyPartiallyRefundedTest (which covers Card): confirms the
 * refundAddonsByBookingId() add-on-netting fix also holds for "Ticket Refund" (full_refund /
 * Constants::ADDONS_TICKET_REFUND) on a Cash booking -- same shape as the other two (one pax
 * already removed and their paid add-on already refunded, then the remaining booking is
 * cancelled), just exercised through the OTHER refund-type/payment-method combination.
 */
class TicketRefundCashAddonDoubleRefundTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_ticket_refund_cancel_after_pax_removal_addon_refund_does_not_double_refund(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(2)
            ->withRate(75.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Pax already removed, their $15 addon already refunded (Ticket-Refund style: fee zeroed).
        $fieldRemoved = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 810200, 'field_label' => 'Photography Package',
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

        // Remaining pax, still owed in full.
        $fieldRemaining = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => 1, 'field_id' => 1,
            'pax_id' => 810201, 'field_label' => 'Photography Package',
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
                'refund_type' => 'full_refund', // "Ticket Refund" / "Ticket Cost"
                'is_cash' => 'Cash',
                'comment' => 'cash ticket refund double test',
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
        } finally {
            TourBookingAddonTransaction::whereIn('tour_booking_addon_id', $fieldIds)->forceDelete();
            TourBookingAddonField::withTrashed()->whereIn('id', $fieldIds)->forceDelete();
            $booking->cleanup();
        }
    }
}
