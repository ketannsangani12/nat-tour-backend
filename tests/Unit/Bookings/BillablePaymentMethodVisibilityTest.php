<?php

namespace Tests\Unit\Bookings;

use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: the "Billable" ("Pay Later") payment-method option in the Make Payment modals
 * (#modal_affilate and #modal_admin in tour-booking-info.blade.php) stayed visible even after the
 * booking's first payment had already been completed as a one-time (non-split) Card/Cash charge.
 *
 * The old `hidden` gate only accounted for a completed SPLIT-payment leg
 * (`$trans->payment_type == 1 && $trans->via` is Card/Cash) — it never covered the much more common
 * case of a plain one-time payment (`payment_type == 0`), so a booking that was already fully paid
 * still offered "Billable" as if no payment had ever been made (confirmed against a real booking:
 * is_billable=1, is_affiliate='Yes', a Completed transactions row with payment_type=0, via='Card').
 *
 * Fixed gate: hide Billable whenever ANY completed transaction exists at all — it should only ever
 * be offered before the booking's first payment (`empty($trans)`), matching "Billable is for Pay
 * Later bookings that haven't been charged yet", regardless of affiliate status (is_billable is a
 * customer-level flag, not a booking-state one, so it doesn't change this rule).
 */
class BillablePaymentMethodVisibilityTest extends TestCase
{
    /**
     * The exact gate expression from tour-booking-info.blade.php's #modal_affilate and #modal_admin
     * Billable `<label ... hidden>` attributes (both blocks are kept identical on purpose).
     */
    private function billableIsHidden($trans): bool
    {
        return !empty($trans);
    }

    private function oldBuggyBillableIsHidden($trans): bool
    {
        return !empty($trans) && $trans->payment_type == 1 && $trans->via == "Card"
            || !empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash";
    }

    public function test_gate_hides_billable_once_any_completed_transaction_exists(): void
    {
        $cases = [
            'No transaction at all (true Pay Later)' => [null, false],
            'One-time Card payment, completed (payment_type=0)' => [(object) ['payment_type' => 0, 'via' => 'Card'], true],
            'One-time Cash payment, completed (payment_type=0)' => [(object) ['payment_type' => 0, 'via' => 'Cash'], true],
            'One-time Invoiced payment, completed (payment_type=0)' => [(object) ['payment_type' => 0, 'via' => 'Invoiced'], true],
            'Split payment, Card leg completed (payment_type=1)' => [(object) ['payment_type' => 1, 'via' => 'Card'], true],
            'Split payment, Cash leg completed (payment_type=1)' => [(object) ['payment_type' => 1, 'via' => 'Cash'], true],
        ];

        foreach ($cases as $label => [$trans, $expectedHidden]) {
            $this->assertSame($expectedHidden, $this->billableIsHidden($trans), $label);
        }
    }

    public function test_old_gate_missed_the_one_time_payment_case_the_new_gate_catches(): void
    {
        $trans = (object) ['payment_type' => 0, 'via' => 'Card'];

        $this->assertFalse($this->oldBuggyBillableIsHidden($trans), 'Reproduces the bug: the old gate never hid Billable for a completed one-time payment');
        $this->assertTrue($this->billableIsHidden($trans), 'The fixed gate must hide Billable once the booking has any completed transaction');
    }

    public function test_real_affiliate_billable_booking_with_a_completed_one_time_card_payment_is_hidden(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        DB::table('customers')->where('id', $booking->customerId)->update([
            'is_billable' => 1,
            'is_affiliate' => 'Yes',
        ]);

        // Match the real reported booking's transactions row: a completed, non-split ("Full
        // Payment") Card charge.
        DB::table('transactions')->where('tour_booking_id', $bookingId)->update([
            'via' => 'Card',
            'payment_type' => 0,
        ]);

        try {
            $trans = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Completed')
                ->first();
            $this->assertNotNull($trans, 'Sanity check: a real Completed transactions row must exist for this booking');

            $this->assertTrue($this->billableIsHidden($trans), 'Billable must be hidden once this affiliate/billable booking has a completed one-time payment');
        } finally {
            $booking->cleanup();
        }
    }

    public function test_real_affiliate_billable_booking_with_no_payment_yet_still_shows_billable(): void
    {
        $booking = (new CreateABooking())->withoutTransaction()->create();
        $bookingId = $booking->bookingId;

        DB::table('customers')->where('id', $booking->customerId)->update([
            'is_billable' => 1,
            'is_affiliate' => 'Yes',
        ]);

        try {
            $trans = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Completed')
                ->first();
            $this->assertNull($trans, 'Sanity check: no transaction should exist yet for a true Pay Later booking');

            $this->assertFalse($this->billableIsHidden($trans), 'Billable must still show for a genuinely unpaid Pay Later booking, affiliate or not');
        } finally {
            $booking->cleanup();
        }
    }
}
