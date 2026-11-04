<?php

namespace Tests\Feature;

use App\Repositories\Report\PaymentBreakdownRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Covers the "Charge Type" filter (Tours / Custom / Add-ons) on the Payment
 * Breakdown Report. See app/Repositories/Report/PaymentBreakdownRepository.php
 * aggregateTransactionData() for the implementation.
 *
 * Uses two bookings under one dedicated, newly-created tour package so the
 * report query (scoped by tourPackageIds + a tight date range) never touches
 * unrelated real data in the shared `dixies` tenant DB:
 *
 *   Booking A: a tour payment ($50, charge_type NULL) + a custom charge
 *              ($20, charge_type 'custom_charge').
 *   Booking B: only an add-on charge ($15, charge_type 'addons') - no tour
 *              payment at all, simulating a 3rd-party (Bokun/Headout) booking
 *              where only the add-on was charged through this platform.
 */
class PaymentBreakdownChargeTypeFilterTest extends TestCase
{
    private ?CreateABooking $bookingA = null;
    private ?CreateABooking $bookingB = null;

    protected function tearDown(): void
    {
        // Extra safety net in case a test fails before its own finally-block
        // cleanup runs - never leave orphaned rows in the shared dixies DB.
        $this->cleanupBookings();
        parent::tearDown();
    }

    private function cleanupBookings(): void
    {
        // Booking B before A: A owns the shared package these bookings use.
        $this->bookingB?->cleanup();
        $this->bookingA?->cleanup();
        $this->bookingB = null;
        $this->bookingA = null;
    }

    public function test_charge_type_filter_scopes_amounts_and_row_inclusion(): void
    {
        $today = Carbon::now()->toDateString();

        try {
            // Booking A: tour payment ($50) + custom charge ($20) = $70 unfiltered
            $this->bookingA = (new CreateABooking())
                ->withNewPackage()
                ->withDate($today)
                ->withRate(50.00)
                ->withTourists(1)
                ->withPaymentMethod('Card')
                ->create();

            DB::table('transactions')->insert([
                'tour_booking_id' => $this->bookingA->bookingId,
                'amount' => 20.00,
                'fee_amount' => 0,
                'transaction_number' => 'TEST-CUSTOM-' . uniqid(),
                'via' => 'Card',
                'payment_type' => 2,
                'charge_type' => 'custom_charge',
                'response_init' => '{}',
                'response_end' => '{}',
                'response_end_datetime' => now(),
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            // Booking B: add-on charge only ($15), no tour payment transaction at all
            $this->bookingB = (new CreateABooking())
                ->usePackage($this->bookingA->packageId)
                ->withDate($today)
                ->withRate(50.00)
                ->withTourists(1)
                ->withoutTransaction()
                ->withoutHistory()
                ->create();

            DB::table('transactions')->insert([
                'tour_booking_id' => $this->bookingB->bookingId,
                'amount' => 15.00,
                'fee_amount' => 0,
                'transaction_number' => 'TEST-ADDON-' . uniqid(),
                'via' => 'Card',
                'payment_type' => 2,
                'charge_type' => 'addons',
                'response_init' => '{}',
                'response_end' => '{}',
                'response_end_datetime' => now(),
                'status' => 'Completed',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
            ]);

            $repository = app(PaymentBreakdownRepository::class);
            // paymentTypes is intentionally omitted: an empty/absent filter still
            // resolves to the 'paid_only' query path (see determineQueryType()) and
            // skips the t.via WHERE clause entirely, so both Card transactions pass
            // through without needing the frontend->DB payment-type name mapping
            // that normally happens in PaymentBreakdownController::buildFilters().
            $baseFilters = [
                'dateFrom' => $today,
                'dateTo' => $today,
                'viewBy' => 'tourdate',
                'tourPackageIds' => [$this->bookingA->packageId],
            ];

            $byId = fn($collection) => $collection->keyBy('booking_id');

            // No filter -> baseline, unchanged behavior: both bookings, full amounts.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters));
            $this->assertEquals(70.0, (float) $result[$this->bookingA->bookingId]->amount);
            $this->assertEquals(15.0, (float) $result[$this->bookingB->bookingId]->amount);

            // All 3 charge types selected -> identical to no filter.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['tours', 'custom', 'addons']]));
            $this->assertEquals(70.0, (float) $result[$this->bookingA->bookingId]->amount);
            $this->assertEquals(15.0, (float) $result[$this->bookingB->bookingId]->amount);

            // Tours only -> no row dropped; amounts scoped to the tour-only bucket.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['tours']]));
            $this->assertCount(2, $result, 'Tours-only must never drop a booking row');
            $this->assertEquals(50.0, (float) $result[$this->bookingA->bookingId]->amount);
            $this->assertEquals(0.0, (float) $result[$this->bookingB->bookingId]->amount);

            // Custom only -> only booking A appears (has a custom charge), amount = custom only.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['custom']]));
            $this->assertCount(1, $result);
            $this->assertTrue($result->has($this->bookingA->bookingId));
            $this->assertEquals(20.0, (float) $result[$this->bookingA->bookingId]->amount);

            // Add-ons only -> only booking B appears, amount = add-on only.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['addons']]));
            $this->assertCount(1, $result);
            $this->assertTrue($result->has($this->bookingB->bookingId));
            $this->assertEquals(15.0, (float) $result[$this->bookingB->bookingId]->amount);

            // Tours + Custom (no Add-ons) -> no row dropped; add-on amount excluded.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['tours', 'custom']]));
            $this->assertCount(2, $result, 'Tours+Custom must never drop a booking row');
            $this->assertEquals(70.0, (float) $result[$this->bookingA->bookingId]->amount);
            $this->assertEquals(0.0, (float) $result[$this->bookingB->bookingId]->amount);
        } finally {
            $this->cleanupBookings();
        }
    }

    /**
     * Direct regression test for the concern: a genuinely $0 / fully-unpaid
     * booking (no transaction rows at all - e.g. a 3rd-party booking with
     * nothing charged through this platform) must still appear when no
     * Charge Type filter is selected, exactly as it does today.
     */
    public function test_zero_amount_unpaid_booking_is_never_removed_by_charge_type_filter(): void
    {
        $today = Carbon::now()->toDateString();

        try {
            $this->bookingA = (new CreateABooking())
                ->withNewPackage()
                ->withDate($today)
                ->withTourists(1)
                ->withoutTransaction()
                ->withoutHistory()
                ->create();

            $repository = app(PaymentBreakdownRepository::class);
            // 'Unpaid' alone routes to buildUnpaidBookingsQuery() directly (see
            // determineQueryType()) - the query that finds bookings with zero
            // transaction rows and synthesizes amount = 0 / charge_type = NULL.
            $baseFilters = [
                'dateFrom' => $today,
                'dateTo' => $today,
                'viewBy' => 'tourdate',
                'tourPackageIds' => [$this->bookingA->packageId],
                'paymentTypes' => ['Unpaid'],
            ];

            $byId = fn($collection) => $collection->keyBy('booking_id');

            // No filter -> the $0 booking is present, exactly like today.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters));
            $this->assertTrue($result->has($this->bookingA->bookingId), 'Zero-amount booking must appear with no Charge Type filter');
            $this->assertEquals(0.0, (float) $result[$this->bookingA->bookingId]->amount);

            // All 3 selected -> identical to no filter.
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['tours', 'custom', 'addons']]));
            $this->assertTrue($result->has($this->bookingA->bookingId));

            // Tours only -> still present (Tours never drops a row).
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['tours']]));
            $this->assertTrue($result->has($this->bookingA->bookingId), 'Zero-amount booking must not be dropped when Tours is selected');
            $this->assertEquals(0.0, (float) $result[$this->bookingA->bookingId]->amount);

            // Custom only / Add-ons only -> correctly excluded (no matching charge at all).
            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['custom']]));
            $this->assertFalse($result->has($this->bookingA->bookingId));

            $result = $byId($repository->getPaymentBreakdownData($baseFilters + ['chargeTypes' => ['addons']]));
            $this->assertFalse($result->has($this->bookingA->bookingId));
        } finally {
            $this->cleanupBookings();
        }
    }
}
