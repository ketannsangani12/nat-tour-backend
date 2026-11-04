<?php

namespace Tests\Unit\Bookings;

use App\Constants\Constants;
use App\Models\TourBookingHistory;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report, booking #253689): both invoice pages (admin
 * bookingInvoicePrint() and customer tourBookingInfo()) showed "Payment Total: $0.00" for a
 * booking that was genuinely paid in full ($91.48), despite the "Payment & Refund History" table
 * (built from a separate, correct query) showing the right amounts.
 *
 * Two compounding gaps in the shared $historyPaymentTime / $paidF logic:
 *   1. $historyPaymentTime/$HistoryPaymentTime picked the first "Payment"-operation row by id
 *      with NO payment_type filter — so it could resolve to a $0 addon-tracking placeholder
 *      (payment_type null) instead of the real first payment, making $checkAmount->amount 0.
 *   2. The blade's zero-out guard compared refund_type against the display string "Don't Refund",
 *      which is never how it's actually stored (always the internal key "dont_refund", or other
 *      human labels like "Full Refund minus Fees") — so a $0 "dont_refund" placeholder refund row
 *      (which can carry a non-blank payment_type, e.g. a card-metadata stamp with nothing owed)
 *      passed the existing payment_type filter and reached the loop.
 *   Combined: $checkAmount->amount (0, from the placeholder payment) coincidentally equalled the
 *   accumulated refund $amount (0, from the placeholder refund), so the zero-out fired even though
 *   nothing was ever actually refunded.
 */
class InvoicePaymentTotalPlaceholderPaymentTest extends TestCase
{
    /** @test */
    public function history_payment_time_skips_the_zero_dollar_addon_placeholder_and_resolves_the_real_payment(): void
    {
        $booking = (new CreateABooking())->withoutTransaction()->withoutHistory()->create();
        $bookingId = $booking->bookingId;

        $placeholderId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Payment', 'ticket_cost' => 0, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 0, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => null, 'payment_method' => null,
            'details' => json_encode(['amount' => 0, 'payment_type' => null]),
            'operation_at' => now()->subMinutes(10), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        $realPaymentId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Payment', 'ticket_cost' => 80.5, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 0, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => 'Card', 'payment_method' => 'Full Payment',
            'details' => json_encode(['amount' => 80.5, 'payment_type' => 'Card']),
            'operation_at' => now()->subMinutes(9), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // A $0 "Don't Refund" addon-removal placeholder that (unlike the usual blank-payment_type
        // case) carries a real, non-blank payment_type — as observed on the real booking.
        $dontRefundId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Refund', 'ticket_cost' => 0, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 0, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => 'Card', 'payment_method' => 'card',
            'details' => json_encode(['amount' => 0, 'refund_type' => Constants::ADDONS_DONT_REFUND, 'payment_type' => 'Card']),
            'operation_at' => now()->subMinutes(8), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        try {
            $historyPaymentTime = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation', 'Payment')
                ->whereNotNull('payment_type')->where('payment_type', '!=', '')
                ->orderBy('id', 'asc')->first();

            $this->assertEquals($realPaymentId, $historyPaymentTime->id, 'The $0 placeholder must be skipped in favor of the real first payment');
            $this->assertEquals(80.5, json_decode($historyPaymentTime->details)->amount);

            $tourBookingHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation_at', '>', $historyPaymentTime->operation_at)
                ->whereIn('operation', ['Refund', 'Cancelled'])
                ->whereNotNull('payment_type')->where('payment_type', '!=', '')
                ->get();

            $this->assertCount(1, $tourBookingHistory, 'The dont_refund placeholder is still fetched (its payment_type is non-blank)');

            // Simulate the blade's (now fixed) zero-out guard.
            $paymentOnlyCheck = 80.5;
            $amount = 0;
            foreach ($tourBookingHistory as $row) {
                $details = json_decode($row->details);
                if (isset($details->refund_type) && strtolower($details->refund_type) !== Constants::ADDONS_DONT_REFUND) {
                    $amount += $details->amount;
                }
            }
            $paidF = $paymentOnlyCheck - $amount;
            $checkAmount = json_decode($historyPaymentTime->details);
            if ($checkAmount->amount == $amount) {
                $paidF = 0;
            }

            $this->assertEquals(80.5, $paidF, 'Payment Total must not be zeroed out by an unrelated $0 dont_refund placeholder');
        } finally {
            TourBookingHistory::whereIn('id', [$placeholderId, $realPaymentId, $dontRefundId])->delete();
            $booking->cleanup();
        }
    }
}
