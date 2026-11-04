<?php

namespace Tests\Unit\Bookings;

use App\Models\Transaction;
use App\Models\TourBookingHistory;
use App\Services\Booking\HistoryService;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): the customer-facing invoice (CustomerController::
 * tourBookingInfo(), /tour-booking-info/{token}) renders the SAME shared blade as the admin
 * invoice (TourBookingRepository::bookingInvoicePrint(), /booking-invoice/{id}) but computed its
 * data differently, so a booking with a real pax-removal refund history showed different (wrong)
 * numbers and was missing the "Payment & Refund History" table, "Refunded Pax" and "Refund Amount"
 * rows entirely on the customer page.
 *
 * Three gaps, all now matching bookingInvoicePrint()'s already-correct equivalents:
 *   1. $transactions query-builder reuse (mutates in place — corrupts $transP and $paymentTrans).
 *   2. $historyPaymentTime picked ANY "Payment"-operation row instead of the genuinely FIRST one
 *      (no orderBy('id', 'asc')), and $tourBookingHistory had no payment_type filter, together
 *      letting Refund Amount/Payment Total go wrong whenever a booking has any $0/blank-
 *      payment_type "Payment" or "Refund" history rows (Pay-Later add-on tracking placeholders).
 *   3. paymentHistoryLog was never computed/passed at all, so the "Payment & Refund History"
 *      table silently never rendered (the blade only shows it when the variable is isset()).
 */
class CustomerBookingInfoInvoiceParityTest extends TestCase
{
    /** @test */
    public function fresh_transaction_queries_resolve_the_correct_earliest_and_full_count(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $secondTransactionId = DB::table('transactions')->insertGetId([
            'tour_booking_id' => $bookingId, 'amount' => 32.94, 'fee_amount' => 2.94,
            'transaction_number' => 'pi_second_' . uniqid(), 'via' => 'Card', 'payment_type' => 0,
            'response_init' => '{}', 'response_end' => '{}', 'response_end_datetime' => now(),
            'status' => 'Completed', 'created_by' => 1, 'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);

        try {
            $firstTransactionId = $booking->transactionId;

            $trans  = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->orderByDesc('id')->first();
            $transP = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->orderBy('id')->first();
            $paymentTrans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->get();

            $this->assertEquals($secondTransactionId, $trans->id);
            $this->assertEquals($firstTransactionId, $transP->id, '$transP must be the earliest transaction, not collapse onto $trans');
            $this->assertCount(2, $paymentTrans, 'Every Completed transaction must be counted, not truncated by a stray reused-builder LIMIT');
        } finally {
            DB::table('transactions')->where('id', $secondTransactionId)->delete();
            $booking->cleanup();
        }
    }

    /** @test */
    public function history_payment_time_resolves_the_genuinely_first_payment_row_and_excludes_blank_payment_type_refunds(): void
    {
        $booking = (new CreateABooking())->withoutTransaction()->withoutHistory()->create();
        $bookingId = $booking->bookingId;

        // payment_type/payment_method/ticket_cost/etc. are NOT in TourBookingHistory's own
        // $fillable (see CreateABooking::createHistory()'s identical comment) — insert directly.
        $placeholderId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Payment', 'ticket_cost' => 0, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 0, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => '', 'payment_method' => '',
            'details' => json_encode(['amount' => 0]),
            'operation_at' => now()->subMinutes(20), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // A $0 "Don't Refund" placeholder for an addon removal — also blank payment_type.
        $fakeRefundId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Refund', 'ticket_cost' => 0, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 0, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => '', 'payment_method' => '',
            'details' => json_encode(['amount' => 0, 'refund_type' => 'dont_refund']),
            'operation_at' => now()->subMinutes(19), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // The real, later payment.
        $realPaymentId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Payment', 'ticket_cost' => 161, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 14.40, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => 'Card', 'payment_method' => 'Full Payment',
            'details' => json_encode(['amount' => 161, 'payment_type' => 'Card']),
            'operation_at' => now(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        // A GENUINE refund after the real payment — must still be picked up.
        $realRefundId = DB::table('tour_booking_history')->insertGetId([
            'tour_booking_id' => $bookingId, 'transaction_id' => 0, 'package_swipe_history_id' => 0,
            'tourists' => 1, 'operation' => 'Refund', 'ticket_cost' => 0, 'discount' => 0,
            'permit_fees' => 0, 'processing_fees' => 0, 'platform_fees' => 0, 'partner_fees' => 0,
            'change_amount' => 0, 'payment_type' => 'Card', 'payment_method' => 'Full Payment',
            'details' => json_encode(['amount' => 50, 'payment_type' => 'Card']),
            'operation_at' => now()->addMinute(), 'operation_by' => 1, 'operation_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        try {
            $historyPaymentTime = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation', 'Payment')->orderBy('id', 'asc')->first();
            $this->assertEquals($placeholderId, $historyPaymentTime->id, 'Sanity check: the $0 placeholder is genuinely the first Payment-operation row by id');

            $tourBookingHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
                ->where('operation_at', '>', $historyPaymentTime->operation_at)
                ->whereIn('operation', ['Refund', 'Cancelled'])
                ->whereNotNull('payment_type')->where('payment_type', '!=', '')
                ->get();

            $this->assertCount(1, $tourBookingHistory, 'The blank-payment_type fake refund must be excluded; the real refund must be included');
            $this->assertEquals($realRefundId, $tourBookingHistory->first()->id);
        } finally {
            TourBookingHistory::whereIn('id', [$placeholderId, $fakeRefundId, $realPaymentId, $realRefundId])->delete();
            $booking->cleanup();
        }
    }

    /** @test */
    public function payment_history_log_is_built_from_the_same_history_the_admin_invoice_uses(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $history = DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->orderByDesc('operation_at')->get();
        $log = HistoryService::customerFacingPaymentLog($history);

        $this->assertNotEmpty($log, 'A freshly card-paid booking must have at least one entry in its payment log');
        $this->assertEquals('in', $log[0]['direction']);

        $booking->cleanup();
    }
}
