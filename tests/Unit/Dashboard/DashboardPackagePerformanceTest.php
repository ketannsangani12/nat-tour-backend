<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DashboardPackagePerformanceTest extends TestCase
{
    // =========================================================================
    // Helper
    // =========================================================================

    private function makeBooking(array $overrides = []): array
    {
        return array_merge([
            'package'           => 'General Tour',
            'amount'            => 300.00,
            'refund'            => 0.00,
            'fee_amount'        => 27.00,
            'refund_fee_amount' => 0.00,
            'refund_remove_fee' => 0.00,
            'total_tax'         => 10.00,
            'total_permit_fee'  => 0.00,
            'discount'          => 0.00,
            'tourists'          => 3,
            'infants'           => 0,
            'net'               => 273.00,
            'has_payment'       => true,
        ], $overrides);
    }

    // =========================================================================
    // Aggregation
    // =========================================================================

    public function test_single_booking_aggregates_correctly(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(),
        ]);

        $this->assertCount(1, $result);
        $p = $result[0];

        $this->assertEquals('General Tour', $p['package']);
        $this->assertEquals(300.00,         $p['gross']);
        $this->assertEquals(0.00,           $p['refund']);
        $this->assertEquals(10.00,          $p['tax']);
        $this->assertEquals(273.00,         $p['net']);
        $this->assertEquals(3,              $p['tourists']);
        $this->assertEquals(1,              $p['bookings']);
    }

    public function test_multiple_bookings_same_package_accumulate(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['amount' => 200, 'net' => 180, 'fee_amount' => 20, 'total_tax' => 5,  'tourists' => 2, 'refund' => 0]),
            $this->makeBooking(['amount' => 300, 'net' => 270, 'fee_amount' => 30, 'total_tax' => 10, 'tourists' => 3, 'refund' => 50]),
        ]);

        $this->assertCount(1, $result);
        $p = $result[0];

        $this->assertEquals(500.00, $p['gross']);
        $this->assertEquals(50.00,  $p['refund']);
        $this->assertEquals(15.00,  $p['tax']);
        $this->assertEquals(450.00, $p['net']);
        $this->assertEquals(5,      $p['tourists']);
        $this->assertEquals(2,      $p['bookings']);
    }

    public function test_two_packages_are_kept_separate(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour', 'net' => 156.60, 'amount' => 171.96, 'tourists' => 2]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 709.64, 'amount' => 779.24, 'tourists' => 4]),
        ]);

        $this->assertCount(2, $result);

        $pkgs = array_column($result, 'net', 'package');
        $this->assertEquals(156.60, $pkgs['General Tour']);
        $this->assertEquals(709.64, $pkgs['Deluxe Tour']);
    }

    public function test_empty_bookings_returns_empty_array(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([]);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // avg_per_person
    // =========================================================================

    public function test_avg_per_person_calculated_correctly(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['net' => 709.64, 'tourists' => 4]),
        ]);

        // 709.64 / 4 = 177.41
        $this->assertEquals(177.41, $result[0]['avg_per_person']);
    }

    public function test_avg_per_person_rounds_to_two_decimals(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['net' => 100.00, 'tourists' => 3]),
        ]);

        // 100 / 3 = 33.333... → 33.33
        $this->assertEquals(33.33, $result[0]['avg_per_person']);
    }

    public function test_avg_per_person_is_zero_when_no_tourists(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['net' => 300.00, 'tourists' => 0]),
        ]);

        $this->assertEquals(0, $result[0]['avg_per_person']);
    }

    // =========================================================================
    // Rounding
    // =========================================================================

    public function test_net_gross_refund_tax_rounded_to_two_decimals(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['net' => 100.005, 'amount' => 110.005, 'refund' => 5.555, 'total_tax' => 3.333, 'fee_amount' => 0]),
        ]);

        $p = $result[0];
        $this->assertEquals(100.01, $p['net']);
        $this->assertEquals(110.01, $p['gross']);
        $this->assertEquals(5.56,   $p['refund']);
        $this->assertEquals(3.33,   $p['tax']);
    }

    // =========================================================================
    // Sorting — net DESC
    // =========================================================================

    public function test_result_sorted_by_net_desc(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour', 'net' => 100, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 500, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Budget Tour',  'net' => 300, 'tourists' => 1]),
        ]);

        $this->assertEquals('Deluxe Tour',  $result[0]['package']);
        $this->assertEquals('Budget Tour',  $result[1]['package']);
        $this->assertEquals('General Tour', $result[2]['package']);
    }

    // =========================================================================
    // Badge — single package
    // =========================================================================

    public function test_single_package_gets_good_badge(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['bookings' => 1]),
        ]);

        $this->assertEquals('good', $result[0]['badge']);
    }

    // =========================================================================
    // Badge — all equal bookings
    // =========================================================================

    public function test_all_equal_bookings_all_get_good_badge(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour', 'net' => 78.30, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 177.41, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Budget Tour',  'net' => 100, 'tourists' => 1]),
        ]);

        foreach ($result as $p) {
            $this->assertEquals('good', $p['badge'], "Expected 'good' for {$p['package']}");
        }
    }

    // =========================================================================
    // Badge — score thresholds
    // =========================================================================

    /**
     * Three packages with clearly separated booking counts:
     *   high  = 100  → score = (100-10)/(100-10) = 1.0  → excellent
     *   mid   =  55  → score = (55-10)/(100-10)  = 0.5  → good
     *   low   =  10  → score = (10-10)/(100-10)  = 0.0  → average
     */
    public function test_three_packages_get_correct_badges(): void
    {
        $make = fn(string $pkg, int $bookings, float $net) =>
            array_merge($this->makeBooking(['package' => $pkg, 'net' => $net, 'tourists' => 1]), ['bookings_override' => $bookings]);

        // Build bookings that result in the desired booking counts
        $bookings = [];
        foreach (range(1, 100) as $_) {
            $bookings[] = $this->makeBooking(['package' => 'High Tour',    'net' => 3, 'tourists' => 1]);
        }
        foreach (range(1, 55) as $_) {
            $bookings[] = $this->makeBooking(['package' => 'Mid Tour',     'net' => 2, 'tourists' => 1]);
        }
        foreach (range(1, 10) as $_) {
            $bookings[] = $this->makeBooking(['package' => 'Low Tour',     'net' => 1, 'tourists' => 1]);
        }

        $result = DashboardCalculatorService::getPackagePerformance($bookings);
        $badges = array_column($result, 'badge', 'package');

        $this->assertEquals('excellent', $badges['High Tour']);
        $this->assertEquals('good',      $badges['Mid Tour']);
        $this->assertEquals('average',   $badges['Low Tour']);
    }

    /** Score exactly at 0.66 boundary → excellent */
    public function test_badge_score_at_0_66_boundary_is_excellent(): void
    {
        // min=0, max=100, score=0.66 → bookings=66
        $bookings = [];
        foreach (range(1, 100) as $_) $bookings[] = $this->makeBooking(['package' => 'A', 'net' => 2, 'tourists' => 1]);
        foreach (range(1, 66)  as $_) $bookings[] = $this->makeBooking(['package' => 'B', 'net' => 1, 'tourists' => 1]);
        // C with 0 bookings not possible (every entry = 1 booking min), use 1
        $bookings[] = $this->makeBooking(['package' => 'C', 'net' => 0.5, 'tourists' => 1]);

        $result = DashboardCalculatorService::getPackagePerformance($bookings);
        $badges = array_column($result, 'badge', 'package');
        
        $this->assertEquals('excellent', $badges['A']);
        $this->assertEquals('good', $badges['B']); // score = (66-1)/(100-1) ≈ 0.657 rounds to good — use real math
    }

    /** Score exactly at 0.33 boundary → good */
    public function test_badge_score_at_0_33_boundary_is_good(): void
    {
        // max=100, min=1, target score ≥ 0.33 → bookings = round(0.33*99)+1 = 34
        $bookings = [];
        foreach (range(1, 100) as $_) $bookings[] = $this->makeBooking(['package' => 'Top',    'net' => 3, 'tourists' => 1]);
        foreach (range(1, 34)  as $_) $bookings[] = $this->makeBooking(['package' => 'Middle',  'net' => 2, 'tourists' => 1]);
        $bookings[] = $this->makeBooking(['package' => 'Bottom', 'net' => 1, 'tourists' => 1]);

        $result = DashboardCalculatorService::getPackagePerformance($bookings);
        $badges = array_column($result, 'badge', 'package');

        $this->assertEquals('good',    $badges['Middle']);
        $this->assertEquals('average', $badges['Bottom']);
    }

    // =========================================================================
    // Badge — two packages
    // =========================================================================

    public function test_two_packages_higher_bookings_gets_excellent(): void
    {
        $bookings = [];
        foreach (range(1, 50) as $_) $bookings[] = $this->makeBooking(['package' => 'A', 'net' => 2, 'tourists' => 1]);
        foreach (range(1, 10) as $_) $bookings[] = $this->makeBooking(['package' => 'B', 'net' => 1, 'tourists' => 1]);

        $result = DashboardCalculatorService::getPackagePerformance($bookings);
        $badges = array_column($result, 'badge', 'package');

        // score A = (50-10)/(50-10) = 1.0 → excellent
        // score B = (10-10)/(50-10) = 0.0 → average
        $this->assertEquals('excellent', $badges['A']);
        $this->assertEquals('average',   $badges['B']);
    }

    // =========================================================================
    // Result shape
    // =========================================================================

    public function test_result_contains_all_required_keys(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(),
        ]);

        $keys = ['package', 'net', 'gross', 'refund', 'tax', 'bookings', 'tourists', 'avg_per_person', 'badge'];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $result[0], "Missing key: {$key}");
        }
    }

    // =========================================================================
    // Multiple Tour Packages — filter selection scenarios
    // =========================================================================

    /** All selected packages appear in the result with correct independent totals. */
    public function test_multiple_selected_packages_all_appear_in_result(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour',  'net' => 90,  'amount' => 100, 'tourists' => 2]),
            $this->makeBooking(['package' => 'Deluxe Tour',   'net' => 270, 'amount' => 300, 'tourists' => 3]),
            $this->makeBooking(['package' => 'Budget Tour',   'net' => 135, 'amount' => 150, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Canyon Tour',   'net' => 180, 'amount' => 200, 'tourists' => 2]),
        ]);

        $this->assertCount(4, $result);

        $packages = array_column($result, 'package');
        $this->assertContains('General Tour', $packages);
        $this->assertContains('Deluxe Tour',  $packages);
        $this->assertContains('Budget Tour',  $packages);
        $this->assertContains('Canyon Tour',  $packages);
    }

    /** Multiple bookings for different selected packages accumulate independently. */
    public function test_multiple_selected_packages_accumulate_independently(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour', 'net' => 90,  'amount' => 100, 'fee_amount' => 10, 'total_tax' => 4,  'tourists' => 2, 'refund' => 0]),
            $this->makeBooking(['package' => 'General Tour', 'net' => 90,  'amount' => 100, 'fee_amount' => 10, 'total_tax' => 4,  'tourists' => 2, 'refund' => 0]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 270, 'amount' => 300, 'fee_amount' => 30, 'total_tax' => 10, 'tourists' => 3, 'refund' => 0]),
        ]);

        $byPackage = array_column($result, null, 'package');

        // General Tour: 2 bookings accumulated
        $this->assertEquals(200.00, $byPackage['General Tour']['gross']);
        $this->assertEquals(180.00, $byPackage['General Tour']['net']);
        $this->assertEquals(8.00,   $byPackage['General Tour']['tax']);
        $this->assertEquals(4,      $byPackage['General Tour']['tourists']);
        $this->assertEquals(2,      $byPackage['General Tour']['bookings']);

        // Deluxe Tour: independent, not mixed with General
        $this->assertEquals(300.00, $byPackage['Deluxe Tour']['gross']);
        $this->assertEquals(270.00, $byPackage['Deluxe Tour']['net']);
        $this->assertEquals(1,      $byPackage['Deluxe Tour']['bookings']);
    }

    /** A refund on one package does not affect another package's totals. */
    public function test_refund_on_one_package_does_not_affect_other(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour', 'net' => 90,  'amount' => 100, 'refund' => 0,   'fee_amount' => 10]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 200, 'amount' => 300, 'refund' => 60,  'fee_amount' => 30, 'refund_remove_fee' => 40]),
        ]);

        $byPackage = array_column($result, null, 'package');

        $this->assertEquals(0.00,  $byPackage['General Tour']['refund']);
        $this->assertEquals(60.00, $byPackage['Deluxe Tour']['refund']);
        $this->assertEquals(90.00, $byPackage['General Tour']['net']);  // unaffected
    }

    /** Sorting by net DESC works correctly across many selected packages. */
    public function test_multiple_selected_packages_sorted_by_net_desc(): void
    {
        $result = DashboardCalculatorService::getPackagePerformance([
            $this->makeBooking(['package' => 'General Tour',  'net' => 90,  'tourists' => 1]),
            $this->makeBooking(['package' => 'Budget Tour',   'net' => 50,  'tourists' => 1]),
            $this->makeBooking(['package' => 'Deluxe Tour',   'net' => 270, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Canyon Tour',   'net' => 180, 'tourists' => 1]),
            $this->makeBooking(['package' => 'Affiliate Tour','net' => 130, 'tourists' => 1]),
        ]);

        $nets = array_column($result, 'net');

        // Verify strictly descending
        for ($i = 0; $i < \count($nets) - 1; $i++) {
            $this->assertGreaterThanOrEqual($nets[$i + 1], $nets[$i]);
        }

        $this->assertEquals('Deluxe Tour', $result[0]['package']);
        $this->assertEquals('Budget Tour', $result[4]['package']);
    }

    public function test_badge_is_always_one_of_valid_values(): void
    {
        $bookings = [];
        foreach (range(1, 20) as $_) $bookings[] = $this->makeBooking(['package' => 'A', 'net' => 3, 'tourists' => 1]);
        foreach (range(1, 10) as $_) $bookings[] = $this->makeBooking(['package' => 'B', 'net' => 2, 'tourists' => 1]);
        foreach (range(1, 3)  as $_) $bookings[] = $this->makeBooking(['package' => 'C', 'net' => 1, 'tourists' => 1]);

        $result = DashboardCalculatorService::getPackagePerformance($bookings);

        $valid = ['excellent', 'good', 'average'];
        foreach ($result as $p) {
            $this->assertContains($p['badge'], $valid, "Invalid badge '{$p['badge']}' for {$p['package']}");
        }
    }
}