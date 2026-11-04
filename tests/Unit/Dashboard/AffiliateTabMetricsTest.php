<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Tests\TestCase;

/**
 * Tests for DashboardCalculatorService::computeAffiliatesData()
 *
 * Covers: KPI aggregation, payment-method routing, cancellation tracking,
 * table-row building, paid_amount calculation, and formatCurrencyShort output.
 */
class AffiliateTabMetricsTest extends TestCase
{
    // =========================================================================
    // Helper — invoke private computeAffiliatesData via reflection
    // =========================================================================

    private function compute(array $rows): array
    {
        $method = new \ReflectionMethod(DashboardCalculatorService::class, 'computeAffiliatesData');
        $method->setAccessible(true);
        return $method->invoke(null, collect($rows));
    }

    private function makeRow(array $overrides = []): object
    {
        return (object) array_merge([
            'tour_booking_id' => 1,
            'customer_id'     => 101,
            'customer_name'   => 'Ajoy Tours',
            'is_affiliate'    => 'Yes',
            'amount'          => 100.00,
            'status'          => 'Completed',
            'booking_status'  => 'Booked',
            'via'             => 'Card',
            'package_name'    => 'General Tour',
            'total_pax'       => 2,
        ], $overrides);
    }

    // =========================================================================
    // Empty / no-affiliate guards
    // =========================================================================

    public function test_empty_collection_returns_zero_array(): void
    {
        $result = $this->compute([]);

        $this->assertSame(0, $result['total_bookings']);
        $this->assertSame(0, $result['total_affiliates']);
        $this->assertSame(0, $result['cancellation_bookings']);
        $this->assertSame([], $result['table_rows']);
    }

    public function test_non_affiliate_rows_are_excluded_from_all_kpis(): void
    {
        $result = $this->compute([
            $this->makeRow(['is_affiliate' => 'No',  'amount' => 500]),
            $this->makeRow(['is_affiliate' => null,  'amount' => 500]),
            $this->makeRow(['is_affiliate' => '',    'amount' => 500]),
        ]);

        $this->assertSame(0, $result['total_bookings']);
        $this->assertSame([], $result['table_rows']);
    }

    public function test_mixed_affiliate_and_non_affiliate_only_counts_affiliates(): void
    {
        $result = $this->compute([
            $this->makeRow(['customer_id' => 101, 'tour_booking_id' => 1, 'is_affiliate' => 'Yes', 'amount' => 200]),
            $this->makeRow(['customer_id' => 202, 'tour_booking_id' => 2, 'is_affiliate' => 'No',  'amount' => 999]),
        ]);

        $this->assertSame(1, $result['total_bookings']);
        $this->assertSame(1, $result['total_affiliates']);
    }

    // =========================================================================
    // total_affiliates and total_bookings
    // =========================================================================

    public function test_total_affiliates_counts_unique_customer_ids(): void
    {
        $result = $this->compute([
            $this->makeRow(['customer_id' => 101, 'tour_booking_id' => 1]),
            $this->makeRow(['customer_id' => 101, 'tour_booking_id' => 2]),
            $this->makeRow(['customer_id' => 202, 'tour_booking_id' => 3]),
        ]);

        $this->assertSame(2, $result['total_affiliates']);
    }

    public function test_total_bookings_counts_unique_booking_ids(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 10, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 10, 'status' => 'Refund',    'booking_status' => 'Booked']),
            $this->makeRow(['tour_booking_id' => 20, 'status' => 'Completed']),
        ]);

        $this->assertSame(2, $result['total_bookings']);
    }

    // =========================================================================
    // KPI — booking_amount (TOTAL GROSS)
    // =========================================================================

    public function test_completed_transactions_accumulate_booking_amount(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 169.12, 'status' => 'Completed']),
        ]);

        // formatCurrencyShort returns a string
        $this->assertSame('169.12', $result['booking_amount']);
    }

    public function test_multiple_completed_transactions_sum_booking_amount(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 100.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 2, 'amount' =>  50.50, 'status' => 'Completed']),
        ]);

        $this->assertSame('150.50', $result['booking_amount']);
    }

    public function test_refund_transactions_do_not_add_to_booking_amount(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 200.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'amount' =>  50.00, 'status' => 'Refund', 'booking_status' => 'Booked']),
        ]);

        $this->assertSame('200.00', $result['booking_amount']);
    }

    // =========================================================================
    // KPI — payment method routing (NET CARD / CASH / INVOICED etc.)
    // =========================================================================

    public function test_card_via_routes_to_net_card(): void
    {
        $result = $this->compute([
            $this->makeRow(['via' => 'Card', 'amount' => 169.12, 'status' => 'Completed']),
        ]);

        $this->assertSame('169.12', $result['net_card']);
        $this->assertSame('0.00',   $result['net_cash']);
    }

    public function test_cash_via_routes_to_net_cash(): void
    {
        $result = $this->compute([
            $this->makeRow(['via' => 'Cash', 'amount' => 80.00, 'status' => 'Completed']),
        ]);

        $this->assertSame('80.00', $result['net_cash']);
        $this->assertSame('0.00',  $result['net_card']);
    }

    public function test_invoice_via_routes_to_net_invoiced(): void
    {
        $result = $this->compute([
            $this->makeRow(['via' => 'Invoiced', 'amount' => 120.00, 'status' => 'Completed']),
        ]);

        $this->assertSame('120.00', $result['net_invoiced']);
    }

    public function test_tripadvisor_via_routes_to_net_tripadvisor(): void
    {
        $result = $this->compute([
            $this->makeRow(['via' => 'TripAdvisor', 'amount' => 55.00, 'status' => 'Completed']),
        ]);

        $this->assertSame('55.00', $result['net_tripadvisor']);
    }

    public function test_headout_via_routes_to_net_headout(): void
    {
        $result = $this->compute([
            $this->makeRow(['via' => 'Headout', 'amount' => 70.00, 'status' => 'Completed']),
        ]);

        $this->assertSame('70.00', $result['net_headout']);
    }

    public function test_fareharbor_via_routes_to_net_fareharbor(): void
    {
        $result = $this->compute([
            $this->makeRow(['via' => 'FareHarbor', 'amount' => 90.00, 'status' => 'Completed']),
        ]);

        $this->assertSame('90.00', $result['net_fareharbor']);
    }

    public function test_refund_on_invoice_reduces_net_invoiced(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'via' => 'Invoiced', 'amount' => 200.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'via' => 'Invoiced', 'amount' =>  50.00, 'status' => 'Refund', 'booking_status' => 'Booked']),
        ]);

        $this->assertSame('150.00', $result['net_invoiced']);
    }

    public function test_refund_on_tripadvisor_reduces_net_tripadvisor(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'via' => 'TripAdvisor', 'amount' => 100.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'via' => 'TripAdvisor', 'amount' =>  30.00, 'status' => 'Refund', 'booking_status' => 'Booked']),
        ]);

        $this->assertSame('70.00', $result['net_tripadvisor']);
    }

    public function test_multiple_payment_methods_accumulated_independently(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'via' => 'Card',    'amount' => 100.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 2, 'via' => 'Cash',    'amount' =>  50.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 3, 'via' => 'Invoiced','amount' =>  75.00, 'status' => 'Completed']),
        ]);

        $this->assertSame('100.00', $result['net_card']);
        $this->assertSame('50.00',  $result['net_cash']);
        $this->assertSame('75.00',  $result['net_invoiced']);
        $this->assertSame('225.00', $result['booking_amount']);
    }

    // =========================================================================
    // KPI — cancellation_bookings
    // =========================================================================

    public function test_refund_with_cancelled_booking_status_counted_in_cancellation_bookings(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'status' => 'Completed', 'booking_status' => 'Cancelled', 'amount' => 169.12]),
            $this->makeRow(['tour_booking_id' => 1, 'status' => 'Refund',    'booking_status' => 'Cancelled', 'amount' => 156.60]),
        ]);

        $this->assertSame(1, $result['cancellation_bookings']);
    }

    public function test_refund_with_booked_status_not_counted_in_cancellation_bookings(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'status' => 'Completed', 'amount' => 200.00]),
            $this->makeRow(['tour_booking_id' => 1, 'status' => 'Refund', 'booking_status' => 'Booked', 'amount' => 50.00]),
        ]);

        $this->assertSame(0, $result['cancellation_bookings']);
    }

    public function test_cancellation_bookings_counts_unique_booking_ids_only(): void
    {
        // Same booking ID with two refund rows (e.g. split refund) → still 1 cancellation
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 5, 'status' => 'Completed', 'booking_status' => 'Cancelled', 'amount' => 300.00]),
            $this->makeRow(['tour_booking_id' => 5, 'status' => 'Refund',    'booking_status' => 'Cancelled', 'amount' => 100.00]),
            $this->makeRow(['tour_booking_id' => 5, 'status' => 'Refund',    'booking_status' => 'Cancelled', 'amount' =>  50.00]),
        ]);

        $this->assertSame(1, $result['cancellation_bookings']);
    }

    public function test_multiple_cancelled_bookings_counted_separately(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'status' => 'Refund', 'booking_status' => 'Cancelled', 'amount' => 100.00]),
            $this->makeRow(['tour_booking_id' => 2, 'status' => 'Refund', 'booking_status' => 'Cancelled', 'amount' => 200.00]),
        ]);

        $this->assertSame(2, $result['cancellation_bookings']);
    }

    // =========================================================================
    // KPI — formatCurrencyShort output (string, not float)
    // =========================================================================

    public function test_kpi_monetary_values_are_strings(): void
    {
        $result = $this->compute([
            $this->makeRow(['amount' => 100.00, 'status' => 'Completed']),
        ]);

        $this->assertIsString($result['booking_amount']);
        $this->assertIsString($result['refund_amount']);
        $this->assertIsString($result['net_card']);
        $this->assertIsString($result['net_cash']);
        $this->assertIsString($result['net_invoiced']);
        $this->assertIsString($result['total_net_collected']);
        $this->assertIsString($result['net_tripadvisor']);
        $this->assertIsString($result['net_headout']);
        $this->assertIsString($result['net_fareharbor']);
    }

    public function test_large_amounts_formatted_with_k_suffix(): void
    {
        $result = $this->compute([
            $this->makeRow(['amount' => 1500.00, 'status' => 'Completed']),
        ]);

        $this->assertStringContainsString('k', $result['booking_amount']);
    }

    public function test_million_amounts_formatted_with_m_suffix(): void
    {
        $result = $this->compute([
            $this->makeRow(['amount' => 1_500_000.00, 'status' => 'Completed']),
        ]);

        $this->assertStringContainsString('M', $result['booking_amount']);
    }

    // =========================================================================
    // table_rows — structure and per-booking amounts
    // =========================================================================

    public function test_table_rows_contains_expected_keys(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 99, 'amount' => 150.00, 'status' => 'Completed']),
        ]);

        $this->assertCount(1, $result['table_rows']);
        $row = $result['table_rows'][0];

        foreach (['id', 'name', 'package', 'tourists', 'booking_status', 'via', 'booking_amount', 'refund_amount', 'paid_amount'] as $key) {
            $this->assertArrayHasKey($key, $row, "Missing key: $key");
        }
    }

    public function test_table_row_id_matches_tour_booking_id(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 41336]),
        ]);

        $this->assertSame(41336, $result['table_rows'][0]['id']);
    }

    public function test_table_row_booking_amount_sums_completed_transactions(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 100.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'amount' =>  69.12, 'status' => 'Completed']),
        ]);

        $this->assertSame(169.12, $result['table_rows'][0]['booking_amount']);
    }

    public function test_table_row_refund_amount_sums_refund_transactions(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 169.12, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 156.60, 'status' => 'Refund', 'booking_status' => 'Cancelled']),
        ]);

        $this->assertSame(156.60, $result['table_rows'][0]['refund_amount']);
    }

    public function test_table_row_paid_amount_equals_booking_minus_refund(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 169.12, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 156.60, 'status' => 'Refund', 'booking_status' => 'Cancelled']),
        ]);

        $row = $result['table_rows'][0];
        $this->assertSame(
            round($row['booking_amount'] - $row['refund_amount'], 2),
            $row['paid_amount']
        );
        $this->assertSame(12.52, $row['paid_amount']);
    }

    public function test_table_row_paid_amount_is_zero_when_fully_refunded(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 200.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 1, 'amount' => 200.00, 'status' => 'Refund', 'booking_status' => 'Cancelled']),
        ]);

        $this->assertSame(0.0, $result['table_rows'][0]['paid_amount']);
    }

    public function test_same_booking_id_appears_once_in_table_rows(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 10, 'status' => 'Completed', 'amount' => 300.00]),
            $this->makeRow(['tour_booking_id' => 10, 'status' => 'Refund',    'amount' =>  50.00, 'booking_status' => 'Booked']),
        ]);

        $this->assertCount(1, $result['table_rows']);
    }

    public function test_multiple_bookings_produce_separate_table_rows(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'customer_name' => 'Ajoy Tours', 'amount' => 100.00, 'status' => 'Completed']),
            $this->makeRow(['tour_booking_id' => 2, 'customer_name' => 'Mei Tours',  'amount' => 200.00, 'status' => 'Completed']),
        ]);

        $this->assertCount(2, $result['table_rows']);
        $ids = array_column($result['table_rows'], 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
    }

    public function test_table_row_captures_via_and_booking_status(): void
    {
        $result = $this->compute([
            $this->makeRow([
                'tour_booking_id' => 1,
                'via'             => 'Card',
                'booking_status'  => 'Cancelled',
                'status'          => 'Completed',
            ]),
        ]);

        $this->assertSame('Card',      $result['table_rows'][0]['via']);
        $this->assertSame('Cancelled', $result['table_rows'][0]['booking_status']);
    }

    public function test_table_row_captures_tourist_count(): void
    {
        $result = $this->compute([
            $this->makeRow(['tour_booking_id' => 1, 'total_pax' => 5]),
        ]);

        $this->assertSame(5, $result['table_rows'][0]['tourists']);
    }

    // =========================================================================
    // Real-world scenario — booking 41336 (Cancelled Card)
    // =========================================================================

    public function test_real_world_cancelled_card_booking_scenario(): void
    {
        // Booking 41336: $169.12 charged (Completed), $156.60 refunded (Cancelled)
        $result = $this->compute([
            $this->makeRow([
                'tour_booking_id' => 41336,
                'customer_name'   => 'Affiliate Test',
                'package_name'    => 'General Tour',
                'via'             => 'Card',
                'amount'          => 169.12,
                'status'          => 'Completed',
                'booking_status'  => 'Cancelled',
                'total_pax'       => 3,
            ]),
            $this->makeRow([
                'tour_booking_id' => 41336,
                'customer_name'   => 'Affiliate Test',
                'package_name'    => 'General Tour',
                'via'             => 'Card',
                'amount'          => 156.60,
                'status'          => 'Refund',
                'booking_status'  => 'Cancelled',
                'total_pax'       => 3,
            ]),
        ]);

        $this->assertSame('169.12', $result['booking_amount']);
        $this->assertSame('169.12', $result['net_card']);
        $this->assertSame(1,        $result['total_bookings']);
        $this->assertSame(1,        $result['cancellation_bookings']);

        $row = $result['table_rows'][0];
        $this->assertSame(41336,  $row['id']);
        $this->assertSame(169.12, $row['booking_amount']);
        $this->assertSame(156.60, $row['refund_amount']);
        $this->assertSame(12.52,  $row['paid_amount']);
    }
}
