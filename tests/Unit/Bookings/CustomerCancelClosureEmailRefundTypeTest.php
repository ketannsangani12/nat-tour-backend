<?php

namespace Tests\Unit\Bookings;

use App\Http\Controllers\CustomerController;
use App\Models\TourBookingHistory;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: booking #116439 (dixies, 2026-08-29) crashed with "Trying to access array
 * offset on null" in TourBookingRepository::handleCancelRefundJobs() when a customer
 * self-cancelled from their emailed booking link.
 *
 * Root cause was two compounding bugs in CustomerController:
 *
 * 1. handleCancellation() (used to render the customer's booking page) detects a prior
 *    "closure email sent" history row with the pattern '%closure email sent%' and, when
 *    found, hands the page the literal display value refund_basis => 'full_amount' /
 *    refund_percent => 100 — which the customer's browser then POSTs back verbatim as
 *    refund_type=full_amount.
 * 2. tourBookingCancel() (which actually processes the cancel) is supposed to catch that
 *    and override refund_type back to '100_refund', but its own re-check used the LIKE
 *    pattern "%{$search}" (no trailing %), which only matches history text that literally
 *    ENDS in "closure email sent". ClosureEmailService's spam-complaint variant writes
 *    "... closure email sent in a spam complaint" — text after "sent" — so that re-check
 *    missed it, the override never fired, and refund_type stayed the literal display
 *    string 'full_amount'.
 *
 * 'full_amount' is not one of the 4 cases handleCancelRefundJobs() switches on
 * (dont_refund/full_refund/partial_refund/100_refund), and that switch had no default
 * and never pre-initialized $cancelBookingRefund — so it silently returned null, and the
 * very next line's array access (`$cancelBookingRefund['historyId']`) blew up with
 * "Trying to access array offset on null", rolling back the whole cancellation.
 *
 * Fixed by:
 *  - CustomerController::tourBookingCancel() using "%{$search}%" (matches handleCancellation()).
 *  - TourBookingRepository::handleCancelRefundJobs() adding a `default` that throws a clear
 *    exception instead of silently returning null for any future unmapped refund_type.
 */
class CustomerCancelClosureEmailRefundTypeTest extends TestCase
{
    public function test_customer_cancel_after_spam_complaint_closure_email_completes_as_full_refund(): void
    {
        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(50.00)
            ->withPaymentMethod('Cash')
            ->create();
        $bookingId = $booking->bookingId;

        // Mirrors ClosureEmailService::run()'s 'complaint' branch: "{operator} closure email
        // sent in a spam complaint" -- text trails "closure email sent", unlike the plain
        // "{operator} closure email sent" variant from EmailSent.php.
        $closureHistory = TourBookingHistory::create([
            'tour_booking_id' => $bookingId,
            'operation' => 'Email',
            'details' => 'Test Tour Operator closure email sent in a spam complaint',
            'operation_at' => now(),
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        try {
            // Exactly what cust-booking-info.js POSTs after handleCancellation() rendered the
            // closure-email display values into the hidden #refund-type/#refund-percent fields.
            $request = new Request();
            $request->merge([
                'api' => 1,
                'cancel_booking' => 1,
                'refund_type' => 'full_amount',
                'refund_percent' => 100,
            ]);

            $response = app(CustomerController::class)->tourBookingCancel(
                $request,
                Crypt::encryptString($bookingId)
            );

            $this->assertEquals(200, $response->getData(true)['code']);

            $status = DB::table('tour_bookings_master')->where('id', $bookingId)->value('status');
            $this->assertEquals('Cancelled', $status, 'Booking must actually be cancelled, not rolled back by a crash mid-transaction');

            // handleCancelRefundJobs() must have resolved to the 100_refund branch (not silently
            // returned null), which records a 'Cancelled' history entry with a real refund_type.
            $cancelledHistory = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Cancelled')
                ->first();
            $this->assertNotNull($cancelledHistory, 'A successful 100% refund cancellation must record a Cancelled history entry');
        } finally {
            $closureHistory->delete();
            $booking->cleanup();
        }
    }

    public function test_handle_cancel_refund_jobs_throws_for_unmapped_refund_type(): void
    {
        $request = new Request();
        $request->merge(['refund_type' => 'full_amount']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid refund type: full_amount');

        TourBookingRepository::handleCancelRefundJobs(null, 0, null, $request, '', 'Customer', null);
    }
}
