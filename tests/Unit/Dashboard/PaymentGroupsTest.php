<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Tests\TestCase;

class PaymentGroupsTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an aggregated booking array (output of aggregateBookings).
     * total_pax defaults to tourists count, via defaults to 'Card'.
     */
    private function makeBooking(array $overrides = []): array
    {
        $base = array_merge([
            'date'               => '2026-03-19',
            'time'               => '08:00:00',
            'package'            => 'General Tour',
            'amount'             => 200.00,
            'refund'             => 0.00,
            'fee_amount'         => 20.00,
            'refund_fee_amount'  => 0.00,
            'refund_remove_fee'  => 0.00,
            'total_tax'          => 0.00,
            'total_permit_fee'   => 0.00,
            'discount'           => 0.00,
            'tourists'           => 2,
            'infants'            => 0,
            'via'                => 'Card',
            'total_pax'          => 2,
            'net'                => 180.00,
            'has_payment'        => true,
        ], $overrides);

        // keep total_pax in sync with tourists when not explicitly overridden
        if (!isset($overrides['total_pax']) && isset($overrides['tourists'])) {
            $base['total_pax'] = $overrides['tourists'];
        }

        return $base;
    }

    // =========================================================================
    // computePaymentAndGroups — KPIs
    // =========================================================================

    public function test_total_bookings_counts_all_bookings_with_valid_pax(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'net' => 100.00]),
            2 => $this->makeBooking(['total_pax' => 2, 'net' => 200.00]),
            3 => $this->makeBooking(['total_pax' => 3, 'net' => 0.00]),   // zero net still counted
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals(3, $result['kpis']['total_bookings']);
    }

    public function test_total_bookings_excludes_zero_pax(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 2, 'net' => 100.00]),
            2 => $this->makeBooking(['total_pax' => 0, 'net' => 200.00]),  // pax=0 excluded
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals(1, $result['kpis']['total_bookings']);
    }

    public function test_revenue_pct_is_always_100(): void
    {
        $bookings = [1 => $this->makeBooking()];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals(100.0, $result['kpis']['revenue_pct']);
    }

    public function test_most_common_group_is_segment_with_most_bookings(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'net' => 100.00]),  // Solo(1)
            2 => $this->makeBooking(['total_pax' => 3, 'net' => 100.00]),  // Small(3-4)
            3 => $this->makeBooking(['total_pax' => 4, 'net' => 100.00]),  // Small(3-4)
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals('Small(3-4)', $result['kpis']['most_common_group']);
        $this->assertEquals(2, $result['kpis']['most_common_bookings']);
    }

    public function test_top_revenue_group_has_highest_net(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'tourists' => 1, 'net' => 50.00]),   // Solo
            2 => $this->makeBooking(['total_pax' => 8, 'tourists' => 8, 'net' => 800.00]),  // Group(7-10)
            3 => $this->makeBooking(['total_pax' => 3, 'tourists' => 3, 'net' => 200.00]),  // Small
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals('Group(7-10)', $result['kpis']['top_revenue_group']);
        $this->assertEquals(800.00, $result['kpis']['top_revenue_amount']);
    }

    public function test_premium_group_is_highest_avg_revenue_per_booking(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'tourists' => 1, 'net' => 500.00]),  // Solo — avg 500
            2 => $this->makeBooking(['total_pax' => 3, 'tourists' => 3, 'net' => 100.00]),  // Small — avg 100
            3 => $this->makeBooking(['total_pax' => 4, 'tourists' => 4, 'net' => 100.00]),  // Small — avg 100
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals('Solo(1)', $result['kpis']['premium_group']);
        $this->assertEquals(500.00, $result['kpis']['premium_avg']);
    }

    // =========================================================================
    // computePaymentAndGroups — Group segment assignment
    // =========================================================================

    /**
     * @dataProvider groupSegmentProvider
     */
    public function test_group_segment_assigned_correctly(int $pax, string $expectedSegment): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => $pax, 'tourists' => $pax, 'net' => 100.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $row = collect($result['breakdown'])->firstWhere('label', $expectedSegment);
        $this->assertNotNull($row, "Expected segment '{$expectedSegment}' not found");
        $this->assertEquals(1, $row['bookings']);
    }

    public static function groupSegmentProvider(): array
    {
        return [
            'pax=1 → Solo(1)'        => [1,  'Solo(1)'],
            'pax=2 → Pair(2)'        => [2,  'Pair(2)'],
            'pax=3 → Small(3-4)'     => [3,  'Small(3-4)'],
            'pax=4 → Small(3-4)'     => [4,  'Small(3-4)'],
            'pax=5 → Medium(5-6)'    => [5,  'Medium(5-6)'],
            'pax=6 → Medium(5-6)'    => [6,  'Medium(5-6)'],
            'pax=7 → Group(7-10)'    => [7,  'Group(7-10)'],
            'pax=10 → Group(7-10)'   => [10, 'Group(7-10)'],
            'pax=11 → Large(10+)'    => [11, 'Large(10+)'],
            'pax=20 → Large(10+)'    => [20, 'Large(10+)'],
        ];
    }

    // =========================================================================
    // computePaymentAndGroups — Breakdown table
    // =========================================================================

    public function test_breakdown_totals_match_segment_sums(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'tourists' => 1, 'net' => 100.00]),
            2 => $this->makeBooking(['total_pax' => 2, 'tourists' => 2, 'net' => 200.00]),
            3 => $this->makeBooking(['total_pax' => 3, 'tourists' => 3, 'net' => 300.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $segmentBookings = array_sum(array_column($result['breakdown'], 'bookings'));
        $segmentRevenue  = array_sum(array_column($result['breakdown'], 'revenue'));

        $this->assertEquals($segmentBookings, $result['breakdown_totals']['bookings']);
        $this->assertEquals($segmentRevenue,  $result['breakdown_totals']['revenue']);
    }

    public function test_breakdown_pct_bookings_sums_to_100(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'net' => 100.00]),
            2 => $this->makeBooking(['total_pax' => 2, 'net' => 100.00]),
            3 => $this->makeBooking(['total_pax' => 3, 'net' => 100.00]),
            4 => $this->makeBooking(['total_pax' => 5, 'net' => 100.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $totalPct = array_sum(array_column($result['breakdown'], 'pct_bookings'));
        $this->assertEquals(100.0, $totalPct);
    }

    public function test_breakdown_avg_per_booking_calculated_correctly(): void
    {
        // 2 bookings in Small(3-4) with net 300 + 100 = 400 → avg = 200
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 3, 'tourists' => 3, 'net' => 300.00]),
            2 => $this->makeBooking(['total_pax' => 4, 'tourists' => 4, 'net' => 100.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $row = collect($result['breakdown'])->firstWhere('label', 'Small(3-4)');
        $this->assertEquals(200, $row['avg_per_booking']);
    }

    public function test_breakdown_rev_per_tourist_calculated_correctly(): void
    {
        // 1 booking, 4 tourists, net 400 → rev_per_tourist = 100
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 4, 'tourists' => 4, 'net' => 400.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $row = collect($result['breakdown'])->firstWhere('label', 'Small(3-4)');
        $this->assertEquals(100.00, $row['rev_per_tourist']);
    }

    public function test_zero_net_booking_appears_in_segment_breakdown(): void
    {
        // Previously skipped by net<=0 guard — now must be included
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 2, 'tourists' => 2, 'net' => 0.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $row = collect($result['breakdown'])->firstWhere('label', 'Pair(2)');
        $this->assertEquals(1, $row['bookings']);
        $this->assertEquals(1, $result['kpis']['total_bookings']);
    }

    // =========================================================================
    // computePaymentAndGroups — Payment types
    // =========================================================================

    public function test_payment_types_groups_by_via(): void
    {
        $bookings = [
            1 => $this->makeBooking(['via' => 'Card',     'net' => 100.00, 'total_pax' => 1]),
            2 => $this->makeBooking(['via' => 'Card',     'net' => 200.00, 'total_pax' => 2]),
            3 => $this->makeBooking(['via' => 'Invoiced', 'net' => 300.00, 'total_pax' => 1]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals(2,      $result['payment_types']['Card']['bookings']);
        $this->assertEquals(300.00, $result['payment_types']['Card']['revenue']);
        $this->assertEquals(1,      $result['payment_types']['Invoiced']['bookings']);
        $this->assertEquals(300.00, $result['payment_types']['Invoiced']['revenue']);
    }

    public function test_payment_types_excludes_non_positive_net(): void
    {
        $bookings = [
            1 => $this->makeBooking(['via' => 'Card', 'net' => 100.00, 'total_pax' => 1]),
            2 => $this->makeBooking(['via' => 'Card', 'net' => 0.00,   'total_pax' => 2]),
            3 => $this->makeBooking(['via' => 'Card', 'net' => -10.00, 'total_pax' => 3]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals(1, $result['payment_types']['Card']['bookings']);
        $this->assertEquals(100.00, $result['payment_types']['Card']['revenue']);
    }

    // =========================================================================
    // computePaymentAndGroups — Chart data
    // =========================================================================

    public function test_chart_groups_labels_contain_all_six_segments(): void
    {
        $bookings = [1 => $this->makeBooking(['total_pax' => 1, 'net' => 50.00])];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertCount(6, $result['chart_groups']['labels']);
        $this->assertContains('Solo(1)',     $result['chart_groups']['labels']);
        $this->assertContains('Large(10+)', $result['chart_groups']['labels']);
    }

    public function test_chart_groups_bookings_and_revenue_align_with_labels(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 1, 'tourists' => 1, 'net' => 100.00]),
            2 => $this->makeBooking(['total_pax' => 8, 'tourists' => 8, 'net' => 500.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $labels   = $result['chart_groups']['labels'];
        $bookingC = $result['chart_groups']['bookings'];
        $revenues = $result['chart_groups']['revenue'];

        $soloIdx  = array_search('Solo(1)',     $labels);
        $groupIdx = array_search('Group(7-10)', $labels);

        $this->assertEquals(1,      $bookingC[$soloIdx]);
        $this->assertEquals(100.00, $revenues[$soloIdx]);
        $this->assertEquals(1,      $bookingC[$groupIdx]);
        $this->assertEquals(500.00, $revenues[$groupIdx]);
    }

    // =========================================================================
    // computePaymentAndGroups — Insights
    // =========================================================================

    public function test_insights_contains_top_revenue_bullet(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 8, 'tourists' => 8, 'net' => 1500.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $found = collect($result['insights'])->contains(fn($s) => str_contains($s, 'Group(7-10)') && str_contains($s, 'most revenue'));
        $this->assertTrue($found, 'Expected top-revenue insight bullet not found');
    }

    public function test_insights_contains_most_frequent_bullet(): void
    {
        $bookings = [
            1 => $this->makeBooking(['total_pax' => 3, 'tourists' => 3, 'net' => 100.00]),
            2 => $this->makeBooking(['total_pax' => 4, 'tourists' => 4, 'net' => 150.00]),
        ];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $found = collect($result['insights'])->contains(fn($s) => str_contains($s, 'most frequent'));
        $this->assertTrue($found, 'Expected most-frequent booking type insight not found');
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function test_empty_bookings_returns_zero_kpis(): void
    {
        $result = DashboardCalculatorService::computePaymentAndGroups([]);

        $this->assertEquals(0, $result['kpis']['total_bookings']);
        $this->assertEquals(0, $result['kpis']['total_revenue']);
        $this->assertEmpty($result['payment_types']);
        $this->assertEmpty($result['insights']);
    }

    public function test_single_booking_most_common_pct_is_100(): void
    {
        $bookings = [1 => $this->makeBooking(['total_pax' => 2, 'net' => 100.00])];

        $result = DashboardCalculatorService::computePaymentAndGroups($bookings);

        $this->assertEquals(100.0, $result['kpis']['most_common_pct']);
    }

    public function test_breakdown_has_six_rows_always(): void
    {
        $result = DashboardCalculatorService::computePaymentAndGroups([]);

        $this->assertCount(6, $result['breakdown']);
    }
}
