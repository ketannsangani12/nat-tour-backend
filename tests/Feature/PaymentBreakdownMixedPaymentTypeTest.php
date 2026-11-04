<?php

namespace Tests\Feature;

use App\Repositories\Report\PaymentBreakdownRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression coverage for the "Payment Type" mislabel on bookings whose
 * transactions span more than one payment method - e.g. a Headout booking
 * that later gets a Cash custom charge added on the booking-info page.
 *
 * Before the fix, aggregateTransactionData() in
 * app/Repositories/Report/PaymentBreakdownRepository.php labeled the whole
 * booking with whichever transaction row the query happened to return
 * first - arbitrary once two transactions tie on the query's ORDER BY
 * (tst.date, tbm.created_at), since neither column differs between two
 * transactions on the same booking. A Headout booking with a later Cash
 * charge could flip to showing "Cash" as its Payment Type despite the
 * Headout payment being the larger (or only real) amount.
 *
 * The fix labels the booking with whichever `via` contributed the most
 * completed money, independent of insertion/return order.
 */
class PaymentBreakdownMixedPaymentTypeTest extends TestCase
{
    private ?CreateABooking $bookingA = null;
    private ?CreateABooking $bookingB = null;
    private ?CreateABooking $bookingC = null;

    protected function tearDown(): void
    {
        $this->cleanupBookings();
        parent::tearDown();
    }

    private function cleanupBookings(): void
    {
        $this->bookingC?->cleanup();
        $this->bookingB?->cleanup();
        $this->bookingA?->cleanup();
        $this->bookingC = null;
        $this->bookingB = null;
        $this->bookingA = null;
    }

    private function fetch(PaymentBreakdownRepository $repository, array $filters, int $bookingId): object
    {
        $result = $repository->getPaymentBreakdownData($filters)->keyBy('booking_id');
        $this->assertTrue($result->has($bookingId), "Booking {$bookingId} missing from Payment Breakdown report result");

        return $result[$bookingId];
    }

    public function test_larger_transaction_wins_regardless_of_which_one_is_inserted_first(): void
    {
        $today = Carbon::now()->toDateString();
        $repository = app(PaymentBreakdownRepository::class);
        $baseFilters = ['dateFrom' => $today, 'dateTo' => $today, 'viewBy' => 'tourdate'];

        try {
            // Booking A: mirrors the real incident - a Headout OTA payment ($293.20
            // equivalent, scaled down) followed by a smaller Cash custom charge.
            // The Cash row is inserted SECOND (like the real custom charge, added
            // long after the original booking) - if the old "first row wins" logic
            // were still in place this would still show "Headout" by luck of
            // insertion order, so this alone wouldn't prove the fix. Booking B below
            // (reversed order) is what actually proves it's amount-driven.
            $this->bookingA = (new CreateABooking())
                ->withNewPackage()
                ->withDate($today)
                ->withRate(200.00)
                ->withTourists(1)
                ->withPaymentMethod('Headout')
                ->create();

            DB::table('transactions')->insert([
                'tour_booking_id' => $this->bookingA->bookingId,
                'amount' => 50.00,
                'fee_amount' => 0,
                'transaction_number' => 'TEST-MIXED-CASH-' . uniqid(),
                'via' => 'Cash',
                'payment_type' => 1,
                'charge_type' => 'custom_charge',
                'response_init' => '{}',
                'response_end' => '{}',
                'response_end_datetime' => now(),
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now()->addMinute(),
            ]);

            // Booking B: same split ($200 Headout / $50 Cash) but the SMALLER Cash
            // transaction is inserted FIRST. Under the old logic this booking would
            // show "Cash" (first row wins); the fix must still show "Headout"
            // (largest completed amount wins) - proving the label no longer
            // depends on row order.
            $this->bookingB = (new CreateABooking())
                ->usePackage($this->bookingA->packageId)
                ->withDate($today)
                ->withRate(50.00)
                ->withTourists(1)
                ->withPaymentMethod('Cash')
                ->create();

            DB::table('transactions')->insert([
                'tour_booking_id' => $this->bookingB->bookingId,
                'amount' => 200.00,
                'fee_amount' => 0,
                'transaction_number' => 'TEST-MIXED-HEADOUT-' . uniqid(),
                'via' => 'Headout',
                'payment_type' => 2,
                'charge_type' => 'custom_charge',
                'response_init' => '{}',
                'response_end' => '{}',
                'response_end_datetime' => now(),
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now()->addMinute(),
            ]);

            $filters = $baseFilters + ['tourPackageIds' => [$this->bookingA->packageId]];

            $rowA = $this->fetch($repository, $filters, $this->bookingA->bookingId);
            $this->assertSame('Headout', $rowA->purchase_type, 'Booking A: $200 Headout + $50 Cash must be labeled by the larger, Headout');
            $this->assertEquals(250.0, (float) $rowA->amount);

            $rowB = $this->fetch($repository, $filters, $this->bookingB->bookingId);
            $this->assertSame('Headout', $rowB->purchase_type, 'Booking B: $50 Cash inserted first must NOT win over the larger $200 Headout amount');
            $this->assertEquals(250.0, (float) $rowB->amount);
        } finally {
            $this->cleanupBookings();
        }
    }

    public function test_single_transaction_booking_is_unaffected(): void
    {
        $today = Carbon::now()->toDateString();
        $repository = app(PaymentBreakdownRepository::class);

        try {
            $this->bookingA = (new CreateABooking())
                ->withNewPackage()
                ->withDate($today)
                ->withRate(75.00)
                ->withTourists(1)
                ->withPaymentMethod('Card')
                ->create();

            $filters = [
                'dateFrom' => $today,
                'dateTo' => $today,
                'viewBy' => 'tourdate',
                'tourPackageIds' => [$this->bookingA->packageId],
            ];

            $row = $this->fetch($repository, $filters, $this->bookingA->bookingId);
            $this->assertSame('Card', $row->purchase_type);
            $this->assertEquals(75.0, (float) $row->amount);
        } finally {
            $this->cleanupBookings();
        }
    }

    /**
     * Reproduces the staging report: a Headout booking (tour payment, charge_type
     * NULL) with several later Cash custom charges. Filtering to Charge Type =
     * Custom scopes `via_totals` down to a single method (Cash) - the original
     * Headout row never contributed to it at all, since it doesn't match the
     * Custom bucket. The label must follow the scoped money (Cash), not the
     * unscoped first-seen transaction (Headout), even though only one `via`
     * ends up in via_totals.
     */
    public function test_charge_type_filter_labels_by_the_scoped_via_not_the_unscoped_first_transaction(): void
    {
        $today = Carbon::now()->toDateString();
        $repository = app(PaymentBreakdownRepository::class);

        try {
            // Original tour payment via Headout - charge_type NULL ('tours' bucket).
            // withRate() is a PER-PERSON rate; 3 tourists x $73.30 = $219.90 total,
            // matching the real staging booking.
            $this->bookingA = (new CreateABooking())
                ->withNewPackage()
                ->withDate($today)
                ->withRate(73.30)
                ->withTourists(3)
                ->withPaymentMethod('Headout')
                ->create();

            // Three later Cash custom charges, mirroring the staging booking.
            foreach ([20.00, 10.00, 50.00] as $amount) {
                DB::table('transactions')->insert([
                    'tour_booking_id' => $this->bookingA->bookingId,
                    'amount' => $amount,
                    'fee_amount' => 0,
                    'transaction_number' => 'TEST-CUSTOM-CASH-' . uniqid(),
                    'via' => 'Cash',
                    'payment_type' => 1,
                    'charge_type' => 'custom_charge',
                    'response_init' => '{}',
                    'response_end' => '{}',
                    'response_end_datetime' => now(),
                    'status' => 'Completed',
                    'created_by' => 1,
                    'created_by_user_type' => 'Admin',
                    'created_at' => now()->addMinute(),
                ]);
            }

            $filters = [
                'dateFrom' => $today,
                'dateTo' => $today,
                'viewBy' => 'tourdate',
                'tourPackageIds' => [$this->bookingA->packageId],
            ];

            // No Charge Type filter -> whole-booking total, Headout ($219.90) legitimately
            // dominates over Cash ($80), so the label should stay Headout here.
            $row = $this->fetch($repository, $filters, $this->bookingA->bookingId);
            $this->assertSame('Headout', $row->purchase_type);
            $this->assertEquals(299.90, (float) $row->amount);

            // Charge Type = Custom -> only the $80 of Cash custom charges count towards
            // the displayed amount; the label must follow that, not the unscoped Headout
            // tour payment which this view doesn't even include.
            $row = $this->fetch($repository, $filters + ['chargeTypes' => ['custom']], $this->bookingA->bookingId);
            $this->assertSame('Cash', $row->purchase_type, 'Charge Type=Custom must label the booking by the Cash money actually shown, not the excluded Headout tour payment');
            $this->assertEquals(80.0, (float) $row->amount);
        } finally {
            $this->cleanupBookings();
        }
    }

    /**
     * Reproduces the two kens-tenant bookings reported: both are $146.60 Headout
     * tickets that later got several Cash custom charges added and mostly
     * refunded. Booking A ends up with only a small net Cash amount retained
     * ($21.96 of $142.76 charged); Booking B has a much larger GROSS Cash total
     * ($241.60) but almost all of it was fully refunded back, leaving a similar
     * small net Cash amount retained ($76.87, via a "Don't Refund" removal that
     * keeps the money but zeroes the ledger entry). Before this fix, dominance
     * was computed on gross Completed sums only, so Booking B's $241.60 gross
     * Cash beat its $146.60 Headout and showed "Cash" - while Booking A's
     * smaller gross Cash ($142.76) narrowly lost to Headout and showed
     * "Headout" - an inconsistent result for two structurally similar bookings.
     * Netting refunds per-via makes both consistently show Headout, matching
     * where the money actually ended up.
     */
    public function test_fully_refunded_cash_charges_do_not_outweigh_a_smaller_net_headout_payment(): void
    {
        $today = Carbon::now()->toDateString();
        $repository = app(PaymentBreakdownRepository::class);

        try {
            // --- Booking A: Headout $146.60 + 3 Cash custom charges, 2 of the 3 refunded ---
            $this->bookingA = (new CreateABooking())
                ->withNewPackage()
                ->withDate($today)
                ->withRate(73.30)
                ->withTourists(2)
                ->withPaymentMethod('Headout')
                ->create();

            $this->insertCashCustomChargeAndRefund($this->bookingA->bookingId, 20.00, 1.96, refund: false); // kept
            $this->insertCashCustomChargeAndRefund($this->bookingA->bookingId, 100.00, 9.82, refund: true, refundAmount: 100.00); // "Ticket Cost" refund
            $this->insertCashCustomChargeAndRefund($this->bookingA->bookingId, 10.00, 0.98, refund: true, refundAmount: 10.98); // "100% Refund"

            // --- Booking B: same $146.60 Headout ticket, but the Cash side is 3 LARGER
            // custom charges, 2 fully refunded and 1 removed via "Don't Refund" (kept,
            // $0 ledger entry) - much bigger gross Cash, similar net Cash retained.
            $this->bookingB = (new CreateABooking())
                ->usePackage($this->bookingA->packageId)
                ->withDate($today)
                ->withRate(73.30)
                ->withTourists(2)
                ->withPaymentMethod('Headout')
                ->create();

            $this->insertCashCustomChargeAndRefund($this->bookingB->bookingId, 100.00, 9.82, refund: true, refundAmount: 100.00); // "Ticket Cost" refund
            $this->insertCashCustomChargeAndRefund($this->bookingB->bookingId, 50.00, 4.91, refund: true, refundAmount: 54.91); // "100% Refund"
            $this->insertCashCustomChargeAndRefund($this->bookingB->bookingId, 70.00, 6.87, refund: true, refundAmount: 0.00); // "Don't Refund" - $0 ledger row, money kept

            $filters = [
                'dateFrom' => $today,
                'dateTo' => $today,
                'viewBy' => 'tourdate',
                'tourPackageIds' => [$this->bookingA->packageId],
            ];

            $rowA = $this->fetch($repository, $filters, $this->bookingA->bookingId);
            $this->assertSame('Headout', $rowA->purchase_type, 'Booking A: net Cash retained ($21.96) is far smaller than Headout ($146.60)');

            $rowB = $this->fetch($repository, $filters, $this->bookingB->bookingId);
            $this->assertSame(
                'Headout',
                $rowB->purchase_type,
                'Booking B: gross Cash ($241.60) is mostly refunded back out - net Cash retained ($76.87) must not outweigh the $146.60 Headout payment'
            );
        } finally {
            $this->cleanupBookings();
        }
    }

    /**
     * Inserts a Completed Cash custom-charge transaction and, optionally, its
     * matching Refund-status transaction - mirroring
     * TransactionService::storeCustomChargeRefundInTransaction(), which always
     * writes a Refund row (amount 0 for a "Don't Refund" removal that keeps the
     * money, or the real refunded amount otherwise).
     */
    private function insertCashCustomChargeAndRefund(int $bookingId, float $amount, float $fee, bool $refund, float $refundAmount = 0.00): void
    {
        DB::table('transactions')->insert([
            'tour_booking_id' => $bookingId,
            'amount' => $amount + $fee,
            'fee_amount' => $fee,
            'transaction_number' => 'TEST-CC-' . uniqid(),
            'via' => 'Cash',
            'payment_type' => 1,
            'charge_type' => 'custom_charge',
            'response_init' => '{}',
            'response_end' => '{}',
            'response_end_datetime' => now(),
            'status' => 'Completed',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now()->addMinute(),
        ]);

        if ($refund) {
            DB::table('transactions')->insert([
                'tour_booking_id' => $bookingId,
                'amount' => $refundAmount,
                'fee_amount' => 0,
                'transaction_number' => 'TEST-CC-REFUND-' . uniqid(),
                'via' => 'Cash',
                'payment_type' => 1,
                'charge_type' => 'custom_charge',
                'response_init' => '',
                'response_end' => '',
                'response_end_datetime' => now(),
                'status' => 'Refund',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now()->addMinutes(2),
            ]);
        }
    }
}
