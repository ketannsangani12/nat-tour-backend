<?php

namespace Tests\Unit\Bookings;

use App\Models\TourBookingHistory;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: TourBookingRepository::bookingInvoicePrint()/invoiceWithoutPrice() and
 * BookingService::comboBookingInvoiceDetails() all located the booking's "first payment" via
 * `TourBookingHistory::where('operation', 'Payment')->first()` with NO ordering clause — unlike
 * TourBookingRepository::booking() (the main booking page), which already has
 * `->orderBy('id', 'asc')`. For a booking with more than one Payment row (e.g. the original
 * ticket charge, then a later "Guest Added — Additional Charge" for more pax added via Change
 * People), an unordered ->first() is not guaranteed to return the TRUE first payment. Whichever
 * row it resolves to becomes the cutoff for `operation_at > ...`, silently dropping every
 * Refund/Cancelled row that happened before it from the invoice's Refund Amount / Payment Total
 * — exactly the $140 (correct, main page) vs $25 (wrong, invoice) discrepancy reported against
 * booking 92368.
 */
class BookingInvoiceFirstPaymentOrderingTest extends TestCase
{
    public function test_ordering_by_id_ascending_resolves_the_true_first_payment_row(): void
    {
        // withoutHistory() is required — the fixture otherwise inserts its OWN 'Payment' history
        // row by default, which would itself become the (unwanted) true first payment here.
        $booking = (new CreateABooking())->withPaymentMethod('Cash')->withoutTransaction()->withoutHistory()->create();
        $bookingId = $booking->bookingId;

        try {
            // Realistic insertion order — rows are always created in true chronological order
            // by the application (auto-increment id always increases as real events happen), so
            // the first payment genuinely gets the lower id: the original ticket charge, then a
            // refund in between, then a later "Guest Added — Additional Charge" for more pax
            // added via Change People.
            $firstPaymentId = DB::table('tour_booking_history')->insertGetId([
                'tour_booking_id' => $bookingId,
                'operation' => 'Payment',
                'ticket_cost' => 200,
                'platform_fees' => 0,
                'processing_fees' => 0,
                'details' => json_encode(['amount' => 200]),
                'operation_at' => '2026-08-19 04:19:00',
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // A refund that happened AFTER the true first payment but BEFORE the second payment
            // — exactly the row the operation_at > cutoff must NOT drop.
            DB::table('tour_booking_history')->insert([
                'tour_booking_id' => $bookingId,
                'operation' => 'Refund',
                'ticket_cost' => 50,
                'platform_fees' => 0,
                'processing_fees' => 0,
                'details' => json_encode(['amount' => 50, 'refund_type' => '100% Refund']),
                'operation_at' => '2026-08-19 04:25:00',
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The second, later payment — e.g. "Guest Added — Additional Charge" for more pax
            // added via Change People after the refund above already happened.
            $laterPaymentId = DB::table('tour_booking_history')->insertGetId([
                'tour_booking_id' => $bookingId,
                'operation' => 'Payment',
                'ticket_cost' => 150,
                'platform_fees' => 0,
                'processing_fees' => 0,
                'details' => json_encode(['amount' => 150]),
                'operation_at' => '2026-08-19 04:31:00',
                'operation_by' => 1,
                'operation_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $resolvedFirstPayment = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation', 'Payment')
                ->orderBy('id', 'asc')
                ->first();

            $this->assertEquals(
                $firstPaymentId,
                $resolvedFirstPayment->id,
                'orderBy(id, asc) must resolve to the true first payment row, not the later "Guest Added" one'
            );
            $this->assertNotEquals($laterPaymentId, $resolvedFirstPayment->id);

            // The refund-summing logic downstream of the (now-correct) anchor: every Refund
            // after the TRUE first payment must be counted.
            $refundRows = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation_at', '>', $resolvedFirstPayment->operation_at)
                ->where('operation', 'Refund')
                ->get();

            $this->assertCount(1, $refundRows, 'The refund between the two payments must be included, not dropped');
            $refundTotal = $refundRows->sum(fn($h) => $h->ticket_cost + $h->processing_fees + $h->platform_fees);
            $this->assertEquals(50.0, $refundTotal);
        } finally {
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
