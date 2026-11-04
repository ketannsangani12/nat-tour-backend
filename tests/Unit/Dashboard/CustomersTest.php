<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an aggregated booking array (output of aggregateBookings).
     * Only the fields computeCustomers() reads: net, tourists, total_pax.
     */
    private function makeBooking(array $overrides = []): array
    {
        $base = array_merge([
            'net'         => 200.00,
            'tourists'    => 2,
            'total_pax'   => 2,
            'has_payment' => true,
        ], $overrides);

        // Keep total_pax in sync with tourists when not explicitly overridden
        if (!isset($overrides['total_pax']) && isset($overrides['tourists'])) {
            $base['total_pax'] = $overrides['tourists'];
        }

        return $base;
    }

    // =========================================================================
    // computeCustomers — empty / zero state
    // =========================================================================

    public function test_empty_bookings_returns_zeroed_kpis_and_dash_names(): void
    {
        $result = DashboardCalculatorService::computeCustomers([], []);

        $kpis = $result['kpis'];
        $this->assertEquals('—', $kpis['top_customer_name']);
        $this->assertEquals(0,   $kpis['top_customer_revenue']);
        $this->assertEquals(0,   $kpis['top_customer_bookings']);
        $this->assertEquals(0,   $kpis['top_customer_tourists']);
        $this->assertEquals(0,   $kpis['top3_revenue']);
        $this->assertEquals('—', $kpis['top3_names']);
        $this->assertEquals(0,   $kpis['top3_pct']);
        $this->assertEquals(0,   $kpis['repeat_customers']);
        $this->assertEquals(0,   $kpis['total_customers']);
        $this->assertEquals(0,   $kpis['avg_rev_per_tourist']);
        $this->assertEquals(0,   $kpis['rev_per_tourist_min']);
        $this->assertEquals(0,   $kpis['rev_per_tourist_max']);
        $this->assertEmpty($result['chart_customers']['labels']);
        $this->assertEmpty($result['chart_customers']['revenue']);
        $this->assertEmpty($result['table_customers']);
        $this->assertEmpty($result['insights']);
    }

    public function test_empty_bookings_chart_group_avg_still_has_six_segment_labels(): void
    {
        $result = DashboardCalculatorService::computeCustomers([], []);

        $this->assertCount(6, $result['chart_group_avg']['labels']);
        $this->assertCount(6, $result['chart_group_avg']['avg']);
        $this->assertEquals(
            ['Solo(1)', 'Pair(2)', 'Small(3-4)', 'Medium(5-6)', 'Group(7-10)', 'Large(10+)'],
            $result['chart_group_avg']['labels']
        );
        $this->assertEquals([0, 0, 0, 0, 0, 0], $result['chart_group_avg']['avg']);
    }

    // =========================================================================
    // computeCustomers — KPIs
    // =========================================================================

    public function test_single_customer_single_booking_kpis(): void
    {
        $bookings          = [1 => $this->makeBooking(['net' => 300.00, 'tourists' => 3])];
        $customerByBooking = [1 => ['id' => 10, 'name' => 'Alice']];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $kpis = $result['kpis'];
        $this->assertEquals('Alice', $kpis['top_customer_name']);
        $this->assertEquals(300.00,  $kpis['top_customer_revenue']);
        $this->assertEquals(1,       $kpis['top_customer_bookings']);
        $this->assertEquals(3,       $kpis['top_customer_tourists']);
        $this->assertEquals(1,       $kpis['total_customers']);
        $this->assertEquals(0,       $kpis['repeat_customers']);
        $this->assertEquals(100.00,  $kpis['avg_rev_per_tourist']); // 300 / 3
    }

    public function test_avg_rev_per_tourist_across_multiple_bookings(): void
    {
        // 300 net / 3 tourists + 200 net / 2 tourists = 500 total / 5 tourists = 100.00
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'tourists' => 3]),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(100.00, $result['kpis']['avg_rev_per_tourist']);
    }

    public function test_top3_revenue_names_and_percentage(): void
    {
        // Total revenue = 1000; top 3 = 400+300+200 = 900 → 90%
        $bookings = [
            1 => $this->makeBooking(['net' => 400.00]),
            2 => $this->makeBooking(['net' => 300.00]),
            3 => $this->makeBooking(['net' => 200.00]),
            4 => $this->makeBooking(['net' => 100.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
            3 => ['id' => 3, 'name' => 'Carol'],
            4 => ['id' => 4, 'name' => 'Dave'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $kpis = $result['kpis'];
        $this->assertEquals(900.00, $kpis['top3_revenue']);
        $this->assertStringContainsString('Alice', $kpis['top3_names']);
        $this->assertStringContainsString('Bob',   $kpis['top3_names']);
        $this->assertStringContainsString('Carol', $kpis['top3_names']);
        $this->assertStringNotContainsString('Dave', $kpis['top3_names']);
        $this->assertEquals(90.0, $kpis['top3_pct']);
        $this->assertEquals(4,    $kpis['total_customers']);
    }

    public function test_top3_pct_is_100_when_only_one_customer(): void
    {
        $bookings          = [1 => $this->makeBooking(['net' => 500.00])];
        $customerByBooking = [1 => ['id' => 1, 'name' => 'Only Customer']];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(100.0,  $result['kpis']['top3_pct']);
        $this->assertEquals(500.00, $result['kpis']['top3_revenue']);
    }

    public function test_repeat_customers_counted_at_two_or_more_bookings(): void
    {
        // Alice books twice, Bob books once → repeat_customers = 1
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00]),
            2 => $this->makeBooking(['net' => 150.00]),
            3 => $this->makeBooking(['net' => 100.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 10, 'name' => 'Alice'],
            2 => ['id' => 10, 'name' => 'Alice'],
            3 => ['id' => 20, 'name' => 'Bob'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(1, $result['kpis']['repeat_customers']);
        $this->assertEquals(2, $result['kpis']['total_customers']);
        $this->assertEquals(2, $result['kpis']['top_customer_bookings']); // Alice has 2
    }

    public function test_revenue_aggregated_across_multiple_bookings_for_same_customer(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1]),
            2 => $this->makeBooking(['net' => 150.00, 'tourists' => 2]),
            3 => $this->makeBooking(['net' => 250.00, 'tourists' => 3]),
        ];
        $customerByBooking = [
            1 => ['id' => 99, 'name' => 'Alice'],
            2 => ['id' => 99, 'name' => 'Alice'],
            3 => ['id' => 99, 'name' => 'Alice'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(1,      $result['kpis']['total_customers']);
        $this->assertEquals(500.00, $result['kpis']['top_customer_revenue']); // 100+150+250
        $this->assertEquals(3,      $result['kpis']['top_customer_bookings']);
        $this->assertEquals(6,      $result['kpis']['top_customer_tourists']); // 1+2+3
        $this->assertEquals(1,      $result['kpis']['repeat_customers']);
    }

    // =========================================================================
    // computeCustomers — sorting and filtering
    // =========================================================================

    public function test_customers_sorted_by_revenue_descending(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00]),
            2 => $this->makeBooking(['net' => 500.00]),
            3 => $this->makeBooking(['net' => 300.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Low'],
            2 => ['id' => 2, 'name' => 'High'],
            3 => ['id' => 3, 'name' => 'Mid'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals('High', $result['kpis']['top_customer_name']);
        $this->assertEquals('High', $result['chart_customers']['labels'][0]);
        $this->assertEquals('Mid',  $result['chart_customers']['labels'][1]);
        $this->assertEquals('Low',  $result['chart_customers']['labels'][2]);
    }

    public function test_bookings_with_zero_or_negative_net_are_excluded(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 0.00]),
            2 => $this->makeBooking(['net' => -50.00]),
            3 => $this->makeBooking(['net' => 200.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Refunded'],
            2 => ['id' => 2, 'name' => 'Negative'],
            3 => ['id' => 3, 'name' => 'Valid'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(1,       $result['kpis']['total_customers']);
        $this->assertEquals('Valid', $result['kpis']['top_customer_name']);
    }

    public function test_unknown_customer_fallback_when_booking_not_in_map(): void
    {
        $bookings          = [99 => $this->makeBooking(['net' => 200.00])];
        $customerByBooking = []; // booking 99 has no mapping

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals('Unknown', $result['kpis']['top_customer_name']);
    }

    // =========================================================================
    // computeCustomers — group size segmentation
    // =========================================================================

    /**
     * @dataProvider groupSegmentProvider
     */
    public function test_group_segment_assigned_correctly(int $pax, string $expectedSegment): void
    {
        $bookings          = [1 => $this->makeBooking(['net' => 100.00, 'tourists' => $pax, 'total_pax' => $pax])];
        $customerByBooking = [1 => ['id' => 1, 'name' => 'Guest']];

        $result    = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $labels    = $result['chart_group_avg']['labels'];
        $avgValues = $result['chart_group_avg']['avg'];

        $idx = array_search($expectedSegment, $labels);
        $this->assertNotFalse($idx, "Expected segment '$expectedSegment' not found in labels");
        $this->assertEquals(100, $avgValues[$idx]); // 100 revenue / 1 booking
    }

    public static function groupSegmentProvider(): array
    {
        return [
            'pax=1 → Solo(1)'      => [1,  'Solo(1)'],
            'pax=2 → Pair(2)'      => [2,  'Pair(2)'],
            'pax=3 → Small(3-4)'   => [3,  'Small(3-4)'],
            'pax=4 → Small(3-4)'   => [4,  'Small(3-4)'],
            'pax=5 → Medium(5-6)'  => [5,  'Medium(5-6)'],
            'pax=6 → Medium(5-6)'  => [6,  'Medium(5-6)'],
            'pax=7 → Group(7-10)'  => [7,  'Group(7-10)'],
            'pax=10 → Group(7-10)' => [10, 'Group(7-10)'],
            'pax=11 → Large(10+)'  => [11, 'Large(10+)'],
            'pax=25 → Large(10+)'  => [25, 'Large(10+)'],
        ];
    }

    public function test_chart_group_avg_value_is_avg_revenue_per_booking_in_segment(): void
    {
        // 2 Small(3-4) bookings: net 300 + 100 = 400 → avg = 200
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'tourists' => 3, 'total_pax' => 3]),
            2 => $this->makeBooking(['net' => 100.00, 'tourists' => 4, 'total_pax' => 4]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $idx = array_search('Small(3-4)', $result['chart_group_avg']['labels']);
        $this->assertEquals(200, $result['chart_group_avg']['avg'][$idx]);
    }

    // =========================================================================
    // computeCustomers — rev per tourist range
    // =========================================================================

    public function test_rev_per_tourist_min_max_across_different_group_sizes(): void
    {
        // Solo:  100 net / 1 tourist = rpt 100
        // Pair:  300 net / 2 tourists = rpt 150  ← max
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1]),
            2 => $this->makeBooking(['net' => 300.00, 'tourists' => 2, 'total_pax' => 2]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(100.00, $result['kpis']['rev_per_tourist_min']);
        $this->assertEquals(150.00, $result['kpis']['rev_per_tourist_max']);
    }

    public function test_rev_per_tourist_min_equals_max_when_only_one_group_size(): void
    {
        // Both bookings are Pair(2), so only one rpt data point
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2]),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertEquals(
            $result['kpis']['rev_per_tourist_min'],
            $result['kpis']['rev_per_tourist_max']
        );
    }

    // =========================================================================
    // computeCustomers — chart and table data
    // =========================================================================

    public function test_chart_customers_and_table_capped_at_12_even_with_more_customers(): void
    {
        $bookings          = [];
        $customerByBooking = [];
        for ($i = 1; $i <= 15; $i++) {
            $bookings[$i]          = $this->makeBooking(['net' => (float) ($i * 100)]);
            $customerByBooking[$i] = ['id' => $i, 'name' => "Customer $i"];
        }

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $this->assertCount(12, $result['chart_customers']['labels']);
        $this->assertCount(12, $result['chart_customers']['revenue']);
        $this->assertCount(12, $result['table_customers']);
        $this->assertEquals(15, $result['kpis']['total_customers']); // all 15 still counted in KPI
    }

    public function test_chart_customers_shows_top_12_by_revenue(): void
    {
        $bookings          = [];
        $customerByBooking = [];
        for ($i = 1; $i <= 15; $i++) {
            $bookings[$i]          = $this->makeBooking(['net' => (float) ($i * 100)]);
            $customerByBooking[$i] = ['id' => $i, 'name' => "Customer $i"];
        }

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        // Customer 15 has highest revenue (1500), Customer 4 is rank 12
        $this->assertEquals('Customer 15', $result['chart_customers']['labels'][0]);
        $this->assertNotContains('Customer 1',  $result['chart_customers']['labels']); // rank 15 — excluded
        $this->assertNotContains('Customer 2',  $result['chart_customers']['labels']); // rank 14 — excluded
        $this->assertNotContains('Customer 3',  $result['chart_customers']['labels']); // rank 13 — excluded
    }

    public function test_table_row_has_all_required_fields(): void
    {
        $bookings          = [1 => $this->makeBooking(['net' => 400.00, 'tourists' => 4, 'total_pax' => 4])];
        $customerByBooking = [1 => ['id' => 5, 'name' => 'Alice']];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);

        $row = $result['table_customers'][0];
        $this->assertArrayHasKey('name',            $row);
        $this->assertArrayHasKey('revenue',         $row);
        $this->assertArrayHasKey('bookings',        $row);
        $this->assertArrayHasKey('tourists',        $row);
        $this->assertArrayHasKey('avg_pax_booking', $row);
        $this->assertArrayHasKey('rev_per_tourist', $row);
    }

    public function test_table_row_calculates_avg_pax_and_rev_per_tourist(): void
    {
        // 1 booking, 4 tourists, net 400 → avg_pax=4.0, rev_per_tourist=100.00
        $bookings          = [1 => $this->makeBooking(['net' => 400.00, 'tourists' => 4, 'total_pax' => 4])];
        $customerByBooking = [1 => ['id' => 1, 'name' => 'Alice']];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $row    = $result['table_customers'][0];

        $this->assertEquals(4.0,    $row['avg_pax_booking']); // 4 tourists / 1 booking
        $this->assertEquals(100.00, $row['rev_per_tourist']);  // 400 / 4
    }

    public function test_table_avg_pax_rounds_to_one_decimal(): void
    {
        // Alice: 2 bookings with 3 + 2 = 5 tourists → avg_pax = 2.5
        $bookings = [
            1 => $this->makeBooking(['net' => 150.00, 'tourists' => 3, 'total_pax' => 3]),
            2 => $this->makeBooking(['net' => 100.00, 'tourists' => 2, 'total_pax' => 2]),
        ];
        $customerByBooking = [
            1 => ['id' => 10, 'name' => 'Alice'],
            2 => ['id' => 10, 'name' => 'Alice'],
        ];

        $result = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $row    = $result['table_customers'][0];

        $this->assertEquals(2.5, $row['avg_pax_booking']);
    }

    // =========================================================================
    // computeCustomers — insights
    // =========================================================================

    public function test_insight_generated_when_large_and_solo_both_present(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00,  'tourists' => 1,  'total_pax' => 1]),  // Solo(1)
            2 => $this->makeBooking(['net' => 1200.00, 'tourists' => 12, 'total_pax' => 12]), // Large(10+)
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Solo Guest'],
            2 => ['id' => 2, 'name' => 'Big Group'],
        ];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringContainsString('Large groups', $combined);
        $this->assertStringContainsString('solo booking', $combined);
    }

    public function test_large_vs_solo_insight_not_generated_when_only_one_group_type(): void
    {
        // Only Solo bookings — no Large group to compare against
        $bookings          = [1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1])];
        $customerByBooking = [1 => ['id' => 1, 'name' => 'Guest']];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringNotContainsString('Large groups', $combined);
    }

    public function test_insight_generated_for_top3_customer_contribution(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 400.00]),
            2 => $this->makeBooking(['net' => 300.00]),
            3 => $this->makeBooking(['net' => 200.00]),
            4 => $this->makeBooking(['net' => 100.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
            3 => ['id' => 3, 'name' => 'Carol'],
            4 => ['id' => 4, 'name' => 'Dave'],
        ];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringContainsString('Top 3 customers', $combined);
        $this->assertStringContainsString('% of total revenue', $combined);
    }

    public function test_insight_generated_when_repeat_customers_exist(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00]),
            2 => $this->makeBooking(['net' => 150.00]),
            3 => $this->makeBooking(['net' => 100.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 10, 'name' => 'Alice'],
            2 => ['id' => 10, 'name' => 'Alice'],
            3 => ['id' => 20, 'name' => 'Bob'],
        ];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringContainsString('booked 2 or more times', $combined);
    }

    public function test_repeat_customer_insight_not_generated_when_all_first_time(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00]),
            2 => $this->makeBooking(['net' => 100.00]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringNotContainsString('booked 2 or more times', $combined);
    }

    public function test_rev_per_tourist_consistency_insight_uses_stable_label_when_spread_under_30pct(): void
    {
        // rpt for Solo: 100/1=100, rpt for Pair: 210/2=105 → spread=(105-100)/105 ≈ 4.8% → "remarkably stable"
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1]),
            2 => $this->makeBooking(['net' => 210.00, 'tourists' => 2, 'total_pax' => 2]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringContainsString('remarkably stable', $combined);
    }

    public function test_rev_per_tourist_consistency_insight_uses_varied_label_when_spread_over_30pct(): void
    {
        // rpt for Solo: 100/1=100, rpt for Large: 1 tourist 10 net = 10 → spread=(100-10)/100 = 90% → "varied"
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1]),
            2 => $this->makeBooking(['net' => 10.00,  'tourists' => 1, 'total_pax' => 11]),
        ];
        $customerByBooking = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob'],
        ];

        $result   = DashboardCalculatorService::computeCustomers($bookings, $customerByBooking);
        $combined = implode(' ', $result['insights']);

        $this->assertStringContainsString('varied', $combined);
    }
}
