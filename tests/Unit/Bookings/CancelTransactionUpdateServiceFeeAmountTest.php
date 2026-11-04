<?php

namespace Tests\Unit\Bookings;

use App\Services\CancelBooking\CancelTransactionUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * CancelTransactionUpdateService::run() writes the refund transaction row shared by every
 * CancelBooking service (Ticket Refund, 100% Refund, Partial Refund, Don't Refund).
 * fee_amount is always a real number (0 when no fee was refunded in this transaction) -- most
 * cancellation refund types deliberately keep the platform fee, so they pass null/0 for those,
 * which is normalized to 0, never stored as null. application_fee_id references a real Stripe
 * application fee object, so it stays null unless a genuine non-zero fee was actually refunded
 * (e.g. a real 100% Refund), which keeps a real value for both columns.
 */
class CancelTransactionUpdateServiceFeeAmountTest extends TestCase
{
    private function fakeRequest(): Request
    {
        $request = new Request();
        $request->merge(['api' => 1]);
        return $request;
    }

    public function test_null_fee_amount_input_stores_zero_fee_and_null_application_fee_id(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();

        try {
            $transaction = (object) ['id' => 1, 'transaction_number' => 'pi_test_original'];
            $result = CancelTransactionUpdateService::run(
                $booking->bookingId, 75.00, '', null, null, 'Card', $transaction, $this->fakeRequest(), mode: 'destination'
            );

            $row = DB::table('transactions')->where('id', $result->id)->first();
            $this->assertEquals(0.0, (float) $row->fee_amount);
            $this->assertNull($row->application_fee_id);
        } finally {
            DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_zero_fee_amount_input_is_stored_as_zero_not_null(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();

        try {
            $transaction = (object) ['id' => 1, 'transaction_number' => 'pi_test_original'];
            $result = CancelTransactionUpdateService::run(
                $booking->bookingId, 75.00, '', 0, '', 'Card', $transaction, $this->fakeRequest(), mode: 'destination'
            );

            $row = DB::table('transactions')->where('id', $result->id)->first();
            $this->assertEquals(0.0, (float) $row->fee_amount, 'A 0 fee_amount input (a "keep the fee" refund type) must be stored as 0, not null');
            $this->assertNull($row->application_fee_id, 'An empty-string application_fee_id must be stored as null, not an empty string');
        } finally {
            DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_genuine_non_zero_fee_amount_is_stored_as_given(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();

        try {
            $transaction = (object) ['id' => 1, 'transaction_number' => 'pi_test_original'];
            $result = CancelTransactionUpdateService::run(
                $booking->bookingId, 90.00, 'pi_test_original', 8.10, 'fee_test_real', 'Card', $transaction, $this->fakeRequest(), mode: 'destination'
            );

            $row = DB::table('transactions')->where('id', $result->id)->first();
            $this->assertEquals(8.10, (float) $row->fee_amount, 'A genuine non-zero fee refund (100% Refund) keeps its real amount');
            $this->assertEquals('fee_test_real', $row->application_fee_id);
        } finally {
            DB::table('transactions')->where('tour_booking_id', $booking->bookingId)->delete();
            $booking->cleanup();
        }
    }
}
