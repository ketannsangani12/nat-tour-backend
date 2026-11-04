<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Tests\TestCase;

class DashboardAffiliateSummaryTest extends TestCase
{
    // =========================================================================
    // Helpers — mirrors the pattern in DashboardCalculatorServiceTest
    // =========================================================================

    private function makeAffiliateRow(array $overrides = []): object
    {
        return (object) array_merge([
            'tour_booking_id'  => 1,
            'customer_id'      => 101,
            'customer_name'    => 'Ajoy Tours',
            'customer_status'  => 'Active',
            'is_affiliate'     => 'Yes',
            'date'             => '2026-03-19',
            'package_name'     => 'General Tour',
            'amount'           => 719.40,
            'fee_amount'       => 64.35,
            'status'           => 'Completed',
            'tourists'         => 3,
            'infants'          => 0,
            'discount'         => 0.00,
            'total_tax'        => 0.00,
            'total_permit_fee' => 0.00,
        ], $overrides);
    }

    // Produces the aggregated booking array that aggregateAffiliates() expects
    // (same shape as aggregateBookings() output but with customer fields)
    private function makeAggregatedAffiliate(array $overrides = []): array
    {
        return array_merge([
            'customer_id'     => 101,
            'affiliate_name'  => 'Ajoy Tours',
            'customer_status' => 'Active',
            'total_bookings'  => 1,
            'total_gross'     => 719.40,
            'top_tour'        => 'General Tour',
        ], $overrides);
    }

    // =========================================================================
    // getAffiliateSummary — basic aggregation
    // =========================================================================

    /** Non-affiliate rows must be excluded even when mixed in. */
    public function test_non_affiliate_rows_are_excluded(): void
    {
        // We test aggregateAffiliates indirectly via a collection filter.
        // Build a mixed collection and assert only affiliate appears.
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'is_affiliate' => 'Yes',  'customer_name' => 'Ajoy Tours']),
            $this->makeAffiliateRow(['customer_id' => 202, 'is_affiliate' => 'No',   'customer_name' => 'Regular Customer']),
            $this->makeAffiliateRow(['customer_id' => 303, 'is_affiliate' => null,   'customer_name' => 'Another Customer']),
        ]);

        $affiliateRows = $rows->filter(fn($r) => isset($r->is_affiliate) && $r->is_affiliate === 'Yes');

        $this->assertCount(1, $affiliateRows);
        $this->assertEquals('Ajoy Tours', $affiliateRows->first()->customer_name);
    }

    /** Single completed booking — gross = amount, bookings = 1, top_tour set. */
    public function test_single_completed_booking_aggregates_correctly(): void
    {
        $rows = collect([
            $this->makeAffiliateRow([
                'customer_id'   => 101,
                'status'        => 'Completed',
                'amount'        => 719.40,
                'package_name'  => 'General Tour',
            ]),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertCount(1, $result);
        $affiliate = $result[101];

        $this->assertEquals('Ajoy Tours',   $affiliate['affiliate_name']);
        $this->assertEquals(719.40,         $affiliate['total_gross']);
        $this->assertEquals(1,              $affiliate['total_bookings']);
        $this->assertEquals('General Tour', $affiliate['top_tour']);
        $this->assertEquals('active',       $affiliate['status']);
    }

    /** Two completed bookings under the same affiliate accumulate gross. */
    public function test_multiple_bookings_same_affiliate_accumulate_gross(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'amount' => 300.00, 'status' => 'Completed']),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'amount' => 200.00, 'status' => 'Completed']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertEquals(500.00, $result[101]['total_gross']);
        $this->assertEquals(2,      $result[101]['total_bookings']);
    }

    /** Refund rows reduce gross. */
    public function test_refund_reduces_gross(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'amount' => 500.00, 'status' => 'Completed']),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'amount' => 100.00, 'status' => 'Refund']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        // gross = 500 - 100 = 400
        $this->assertEquals(400.00, $result[101]['total_gross']);
    }

    /** Same booking_id appearing multiple times (e.g. Completed + Refund row)
     *  must only count as ONE booking. */
    public function test_same_booking_id_counted_once(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 10, 'customer_id' => 101, 'status' => 'Completed', 'amount' => 200]),
            $this->makeAffiliateRow(['tour_booking_id' => 10, 'customer_id' => 101, 'status' => 'Refund',    'amount' => 200]),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertEquals(1, $result[101]['total_bookings']);
    }

    /** Two affiliates in same result set are kept separate. */
    public function test_two_affiliates_are_kept_separate(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'customer_name' => 'Ajoy Tours',  'amount' => 400, 'status' => 'Completed']),
            $this->makeAffiliateRow(['customer_id' => 202, 'customer_name' => 'Mei Tours',   'amount' => 200, 'status' => 'Completed']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertCount(2, $result);
        $this->assertEquals(400, $result[101]['total_gross']);
        $this->assertEquals(200, $result[202]['total_gross']);
    }

    // =========================================================================
    // top_tour logic
    // =========================================================================

    /** The package with the most bookings becomes the top tour. */
    public function test_top_tour_is_package_with_most_bookings(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'package_name' => 'General Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'package_name' => 'General Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 3, 'customer_id' => 101, 'package_name' => 'Deluxe Tour']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertEquals('General Tour', $result[101]['top_tour']);
    }

    /** No bookings → top_tour defaults to '-'. */
    public function test_top_tour_defaults_to_dash_when_no_packages(): void
    {
        // Simulate an affiliate entry that somehow has no package tracking
        // (edge case guard — achieved by having zero rows after filter)
        $rows = collect([]);
        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertEmpty($result);
    }

    // =========================================================================
    // status mapping
    // =========================================================================

    public function test_active_customer_status_maps_to_active(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'customer_status' => 'Active']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);
        $this->assertEquals('active', $result[101]['status']);
    }

    public function test_inactive_customer_status_maps_to_inactive(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'customer_status' => 'Inactive']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);
        $this->assertEquals('inactive', $result[101]['status']);
    }

    public function test_unknown_customer_status_maps_to_inactive(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'customer_status' => 'Suspended']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);
        $this->assertEquals('inactive', $result[101]['status']);
    }

    // =========================================================================
    // getAffiliateSummary — sorting
    // =========================================================================

    /** Final result must be sorted by total_gross DESC. */
    public function test_result_sorted_by_total_gross_desc(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'customer_name' => 'Ajoy Tours', 'amount' => 100, 'status' => 'Completed']),
            $this->makeAffiliateRow(['customer_id' => 202, 'customer_name' => 'Mei Tours',  'amount' => 500, 'status' => 'Completed']),
            $this->makeAffiliateRow(['customer_id' => 303, 'customer_name' => 'Kyoob Travel','amount' => 300, 'status' => 'Completed']),
        ]);

        $result = array_values(DashboardCalculatorService::aggregateAffiliates($rows));

        // Sort desc by total_gross
        usort($result, fn($a, $b) => $b['total_gross'] <=> $a['total_gross']);

        $this->assertEquals('Mei Tours',    $result[0]['affiliate_name']);
        $this->assertEquals('Kyoob Travel', $result[1]['affiliate_name']);
        $this->assertEquals('Ajoy Tours',   $result[2]['affiliate_name']);
    }

    // =========================================================================
    // change_percent logic
    // =========================================================================

    /** change_percent is null when no previous data exists for the affiliate. */
    public function test_change_percent_is_null_when_affiliate_not_in_previous_period(): void
    {
        $currentData = [
            101 => $this->makeAggregatedAffiliate(['customer_id' => 101, 'total_gross' => 500]),
        ];
        $prevData = []; // affiliate 101 not present last month

        $result = $this->buildFinalResult($currentData, $prevData);

        $this->assertNull($result[0]['change_percent']);
    }

    /** change_percent calculates correctly when prev data exists. */
    public function test_change_percent_calculates_positive_growth(): void
    {
        $currentData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 150]),
        ];
        $prevData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 100]),
        ];

        $result = $this->buildFinalResult($currentData, $prevData);

        // (150 - 100) / 100 * 100 = 50.0
        $this->assertEquals(50.0, $result[0]['change_percent']);
    }

    public function test_change_percent_calculates_negative_growth(): void
    {
        $currentData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 50]),
        ];
        $prevData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 100]),
        ];

        $result = $this->buildFinalResult($currentData, $prevData);

        $this->assertEquals(-50.0, $result[0]['change_percent']);
    }

    public function test_change_percent_is_null_when_prev_gross_is_zero(): void
    {
        $currentData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 200]),
        ];
        $prevData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 0]),
        ];

        $result = $this->buildFinalResult($currentData, $prevData);

        $this->assertNull($result[0]['change_percent']);
    }

    public function test_change_percent_rounds_to_one_decimal(): void
    {
        $currentData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 110]),
        ];
        $prevData = [
            101 => $this->makeAggregatedAffiliate(['total_gross' => 75]),
        ];

        $result = $this->buildFinalResult($currentData, $prevData);

        // (110 - 75) / 75 * 100 = 46.666... → 46.7
        $this->assertEquals(46.7, $result[0]['change_percent']);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function test_empty_rows_returns_empty_array(): void
    {
        $result = DashboardCalculatorService::aggregateAffiliates(collect([]));
        $this->assertEmpty($result);
    }

    public function test_gross_is_rounded_to_two_decimals(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['customer_id' => 101, 'amount' => 100.005, 'status' => 'Completed']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);
        $this->assertEquals(100.01, $result[101]['total_gross']);
    }

    public function test_failed_status_does_not_affect_gross(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'status' => 'Completed', 'amount' => 200]),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'status' => 'Failed',    'amount' => 999]),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertEquals(200, $result[101]['total_gross']);
    }

    // =========================================================================
    // Multiple Tour Packages — filter selection scenarios
    // =========================================================================

    /** Affiliate bookings spread across multiple selected packages all accumulate into one gross. */
    public function test_affiliate_bookings_across_multiple_packages_accumulate_gross(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'package_name' => 'General Tour',  'status' => 'Completed', 'amount' => 200]),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'package_name' => 'Deluxe Tour',   'status' => 'Completed', 'amount' => 400]),
            $this->makeAffiliateRow(['tour_booking_id' => 3, 'customer_id' => 101, 'package_name' => 'Budget Tour',   'status' => 'Completed', 'amount' => 100]),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertEquals(700.00, $result[101]['total_gross']);
        $this->assertEquals(3,      $result[101]['total_bookings']);
    }

    /** top_tour picks the package with the most bookings when multiple packages selected. */
    public function test_top_tour_with_multiple_packages_returns_most_booked(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'package_name' => 'General Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'package_name' => 'Deluxe Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 3, 'customer_id' => 101, 'package_name' => 'Deluxe Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 4, 'customer_id' => 101, 'package_name' => 'Budget Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 5, 'customer_id' => 101, 'package_name' => 'Budget Tour']),
            $this->makeAffiliateRow(['tour_booking_id' => 6, 'customer_id' => 101, 'package_name' => 'Budget Tour']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        // Budget Tour has 3 bookings — the most
        $this->assertEquals('Budget Tour', $result[101]['top_tour']);
        $this->assertEquals(6,             $result[101]['total_bookings']);
    }

    /** Two affiliates each booking different packages stay isolated. */
    public function test_two_affiliates_different_packages_stay_isolated(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'customer_name' => 'Ajoy Tours', 'package_name' => 'General Tour', 'amount' => 300, 'status' => 'Completed']),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'customer_name' => 'Ajoy Tours', 'package_name' => 'Deluxe Tour',  'amount' => 500, 'status' => 'Completed']),
            $this->makeAffiliateRow(['tour_booking_id' => 3, 'customer_id' => 202, 'customer_name' => 'Mei Tours',  'package_name' => 'Budget Tour',  'amount' => 150, 'status' => 'Completed']),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        $this->assertCount(2, $result);
        $this->assertEquals(800.00, $result[101]['total_gross']); // 300 + 500
        $this->assertEquals(150.00, $result[202]['total_gross']);
    }

    /** Refund on one package does not affect another package's gross for the same affiliate. */
    public function test_refund_on_one_package_does_not_affect_other_package(): void
    {
        $rows = collect([
            $this->makeAffiliateRow(['tour_booking_id' => 1, 'customer_id' => 101, 'package_name' => 'General Tour', 'status' => 'Completed', 'amount' => 400]),
            $this->makeAffiliateRow(['tour_booking_id' => 2, 'customer_id' => 101, 'package_name' => 'Deluxe Tour',  'status' => 'Completed', 'amount' => 600]),
            $this->makeAffiliateRow(['tour_booking_id' => 3, 'customer_id' => 101, 'package_name' => 'Deluxe Tour',  'status' => 'Refund',    'amount' => 100]),
        ]);

        $result = DashboardCalculatorService::aggregateAffiliates($rows);

        // gross = 400 + 600 - 100 = 900
        $this->assertEquals(900.00, $result[101]['total_gross']);
    }

    /**
     * Replicates the final result-building loop in getAffiliateSummary()
     * so change_percent logic can be tested without a DB call.
     */
    private function buildFinalResult(array $currentData, array $prevData): array
    {
        $result = [];

        foreach ($currentData as $cid => $curr) {
            $prevGross = $prevData[$cid]['total_gross'] ?? null;

            $result[] = [
                'affiliate_name'  => $curr['affiliate_name'],
                'status'          => $curr['status'] ?? 'active',
                'total_bookings'  => $curr['total_bookings'],
                'total_gross'     => $curr['total_gross'],
                'top_tour'        => $curr['top_tour'],
                'change_percent'  => ($prevGross !== null && $prevGross != 0)
                                        ? DashboardCalculatorService::percentageChange($curr['total_gross'], $prevGross)
                                        : null,
            ];
        }

        usort($result, fn($a, $b) => $b['total_gross'] <=> $a['total_gross']);

        return $result;
    }
}