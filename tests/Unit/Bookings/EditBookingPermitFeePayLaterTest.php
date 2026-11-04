<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: removing a pax's permit fee on a Pay Later (genuinely unpaid) booking wrote a
 * "Refund" row into the `transactions` table (empty via/transaction_number, $0 fee_amount) even
 * though nothing was ever actually collected — there is no local Completed transaction, no
 * Stripe charge, no cash in hand, nothing to refund. editBooking()'s permit-fee-removal
 * catch-all branch (for non-Card/non-Cash payment types, e.g. Headout/Viator OTA imports) fell
 * back to `TransactionService::storeRefundInTransaction()` unconditionally whenever a nonzero
 * refund amount was computed, using $request->payment_type as `via` when no local $TS
 * transaction existed — fabricating a refund transaction for money nobody ever paid.
 */
class EditBookingPermitFeePayLaterTest extends TestCase
{
    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_removing_permit_fee_on_pay_later_booking_does_not_write_a_transaction_row(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPermitFee(15.00)
            ->withoutTransaction() // genuinely Pay Later: no Completed transaction row at all
            ->create();
        $bookingId = $booking->bookingId;

        try {
            $request = new Request();
            $request->merge([
                'bookingID' => $bookingId,
                'action_type' => 'update',
                'payment_type' => 'Pay Later',
                'is_cash' => '',
                'check' => [$booking->rateId],
                'permitF' => ['Adult.0' => 0], // remove the permit fee entirely for tourist #1
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->editBooking($request);

            $transactionCount = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->count();

            $this->assertSame(
                0,
                $transactionCount,
                'A genuinely unpaid Pay Later booking must not get a fabricated transactions row when its permit fee is removed'
            );

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('payment_method', 'Permitfees Refund')
                ->latest('id')
                ->first();

            $this->assertNotNull($historyRow, 'The permit fee removal must still be recorded in history');
            $this->assertEquals(15.00, (float) $historyRow->ticket_cost, 'History must still show the real removed permit fee amount');
        } finally {
            $booking->cleanup();
        }
    }

    /**
     * Guards against over-broadening the fix above: a booking with a REAL Completed transaction
     * on a non-Card/non-Cash channel (e.g. Invoiced, or an OTA import like Headout/Viator) still
     * has genuine money to account for, so removing its permit fee must still write the refund
     * transaction row — same as before this fix.
     */
    public function test_removing_permit_fee_on_a_real_non_card_cash_transaction_still_writes_a_refund_row(): void
    {
        $this->authAsAdmin();

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPermitFee(15.00)
            ->withPaymentMethod('Invoiced') // a real Completed transaction, just not Card/Cash
            ->create();
        $bookingId = $booking->bookingId;

        try {
            $request = new Request();
            $request->merge([
                'bookingID' => $bookingId,
                'action_type' => 'update',
                'payment_type' => 'Invoiced',
                'is_cash' => '',
                'check' => [$booking->rateId],
                'permitF' => ['Adult.0' => 0],
                'api' => 1,
            ]);

            app(TourBookingRepository::class)->editBooking($request);

            $refundTxn = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('status', 'Refund')
                ->latest('id')
                ->first();

            $this->assertNotNull($refundTxn, 'A booking with a real (non-Card/Cash) transaction must still get its refund transaction row');
            $this->assertEquals(15.00, (float) $refundTxn->amount);
            $this->assertEquals('Invoiced', $refundTxn->via);
        } finally {
            $booking->cleanup();
        }
    }
}
