<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Carbon\Carbon;
use Tests\TestCase;

class DashboardCalculatorServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeRow(array $overrides = []): object
    {
        return (object) array_merge([
            'tour_booking_id'  => 1,
            'date'             => '2026-03-19',
            'time_from'        => '08:00:00',
            'package_name'     => 'General Tour',
            'amount'           => 257.94,
            'fee_amount'       => 23.04,
            'status'           => 'Completed',
            'discount'         => 0.00,
            'tourists'         => 3,
            'infants'          => 0,
            'total_tax'        => 0.00,
            'total_permit_fee' => 0.00,
            'via'              => '',
        ], $overrides);
    }

    private function makeBooking(array $overrides = []): array
    {
        return array_merge([
            'date'              => '2026-03-19',
            'time'              => '08:00:00',
            'package'           => 'General Tour',
            'amount'            => 257.94,
            'refund'            => 0.00,
            'fee_amount'        => 23.04,
            'refund_fee_amount' => 0.00,
            'refund_remove_fee' => 0.00,
            'total_tax'         => 0.00,
            'total_permit_fee'  => 0.00,
            'discount'          => 0.00,
            'tourists'          => 3,
            'infants'           => 0,
            'net'               => 234.90,
            'has_payment'       => true,
        ], $overrides);
    }

    private function makeTransactionRow(array $overrides = []): object
    {
        return (object) array_merge([
            'status'     => 'Completed',
            'amount'     => 257.94,
            'fee_amount' => 23.04,
            'created_at' => '2026-03-19 08:00:00',
        ], $overrides);
    }

    // =========================================================================
    // percentageChange
    // =========================================================================

    public function test_percentage_change_returns_null_when_previous_is_zero(): void
    {
        $this->assertNull(DashboardCalculatorService::percentageChange(100, 0));
    }

    public function test_percentage_change_positive_growth(): void
    {
        $this->assertEquals(50.0, DashboardCalculatorService::percentageChange(150, 100));
    }

    public function test_percentage_change_negative_growth(): void
    {
        $this->assertEquals(-50.0, DashboardCalculatorService::percentageChange(50, 100));
    }

    public function test_percentage_change_no_change(): void
    {
        $this->assertEquals(0.0, DashboardCalculatorService::percentageChange(100, 100));
    }

    public function test_percentage_change_rounds_to_one_decimal(): void
    {
        // (110 - 75) / 75 * 100 = 46.666... → 46.7
        $this->assertEquals(46.7, DashboardCalculatorService::percentageChange(110, 75));
    }

    // =========================================================================
    // aggregateBookings
    // =========================================================================

    public function test_aggregate_bookings_single_completed_row(): void
    {
        $result  = DashboardCalculatorService::aggregateBookings([$this->makeRow()]);
        $booking = $result[1];

        $this->assertCount(1, $result);
        $this->assertEquals(257.94, $booking['amount']);
        $this->assertEquals(23.04,  $booking['fee_amount']);
        $this->assertEquals(0,      $booking['refund']);
        // net = 257.94 - 0 - 23.04 = 234.9
        $this->assertEquals(234.9,  $booking['net']);
    }

    public function test_aggregate_bookings_with_refund(): void
    {
        $rows = [
            $this->makeRow(['tour_booking_id' => 2, 'status' => 'Completed', 'amount' => 200, 'fee_amount' => 20]),
            $this->makeRow(['tour_booking_id' => 2, 'status' => 'Refund',    'amount' => 200, 'fee_amount' => 20]),
        ];
        $b = DashboardCalculatorService::aggregateBookings($rows)[2];

        $this->assertEquals(200, $b['amount']);
        $this->assertEquals(200, $b['refund']);
        $this->assertEquals(20,  $b['refund_fee_amount']);
        // refund_remove_fee = 200 - 20 = 180
        $this->assertEquals(180, $b['refund_remove_fee']);
        // net = 200 - 180 - 20 = 0
        $this->assertEquals(0,   $b['net']);
    }

    public function test_aggregate_bookings_tourists_includes_infants(): void
    {
        $result = DashboardCalculatorService::aggregateBookings(
            [$this->makeRow(['tourists' => 3, 'infants' => 2])]
        );
        $this->assertEquals(5, $result[1]['tourists']);
    }

    public function test_aggregate_bookings_groups_multiple_rows_by_booking_id(): void
    {
        $rows = [
            $this->makeRow(['tour_booking_id' => 10, 'status' => 'Completed', 'amount' => 50, 'fee_amount' => 5]),
            $this->makeRow(['tour_booking_id' => 10, 'status' => 'Completed', 'amount' => 50, 'fee_amount' => 5]),
        ];
        $result = DashboardCalculatorService::aggregateBookings($rows);

        $this->assertCount(1, $result);
        $this->assertEquals(100, $result[10]['amount']);
        $this->assertEquals(10,  $result[10]['fee_amount']);
        // net = 100 - 0 - 10 = 90
        $this->assertEquals(90,  $result[10]['net']);
    }

    public function test_aggregate_bookings_null_discount_defaults_to_zero(): void
    {
        $result = DashboardCalculatorService::aggregateBookings(
            [$this->makeRow(['discount' => null])]
        );
        $this->assertEquals(0, $result[1]['discount']);
    }

    /** total_tax and total_permit_fee are taken from the first row (subquery values). */
    public function test_aggregate_bookings_carries_tax_and_permit_fee_from_row(): void
    {
        $result = DashboardCalculatorService::aggregateBookings([
            $this->makeRow(['total_tax' => 12.50, 'total_permit_fee' => 5.00]),
        ]);

        $this->assertEquals(12.50, $result[1]['total_tax']);
        $this->assertEquals(5.00,  $result[1]['total_permit_fee']);
    }

    /** Failed / unknown statuses should not affect amount or fee totals. */
    public function test_aggregate_bookings_ignores_non_completed_non_refund_status(): void
    {
        $rows = [
            $this->makeRow(['status' => 'Completed', 'amount' => 100, 'fee_amount' => 10]),
            $this->makeRow(['status' => 'Pending',   'amount' => 999, 'fee_amount' => 99]),
        ];
        $b = DashboardCalculatorService::aggregateBookings($rows)[1];

        $this->assertEquals(100, $b['amount']);
        $this->assertEquals(10,  $b['fee_amount']);
        $this->assertEquals(90,  $b['net']);
    }

    // =========================================================================
    // calculateFromAggregated
    // =========================================================================

    public function test_calculate_from_aggregated_totals_correctly(): void
    {
        $bookings = [
            1 => $this->makeBooking(['amount' => 100, 'fee_amount' => 10, 'refund' => 0,  'refund_remove_fee' => 0,  'discount' => 5,  'tourists' => 2, 'net' => 90,  'total_tax' => 3.00, 'total_permit_fee' => 1.00]),
            2 => $this->makeBooking(['amount' => 200, 'fee_amount' => 20, 'refund' => 50, 'refund_remove_fee' => 40, 'discount' => 10, 'tourists' => 3, 'net' => 140, 'total_tax' => 6.00, 'total_permit_fee' => 2.00]),
        ];

        $result = DashboardCalculatorService::calculateFromAggregated($bookings);

        $this->assertEquals(300.00, $result['total_gross']);
        $this->assertEquals(230.00, $result['total_net']);      // 90 + 140
        $this->assertEquals(40.00,  $result['total_refund']);
        $this->assertEquals(15.00,  $result['total_discount']);
        $this->assertEquals(30.00,  $result['total_fees']);
        $this->assertEquals(9.00,   $result['total_tax']);
        $this->assertEquals(3.00,   $result['total_permit_fee']);
        $this->assertEquals(5,      $result['total_tourists']);
        $this->assertEquals(2,      $result['total_bookings']);
    }

    public function test_calculate_from_aggregated_empty_bookings(): void
    {
        $result = DashboardCalculatorService::calculateFromAggregated([]);

        $this->assertEquals(0.00, $result['total_gross']);
        $this->assertEquals(0.00, $result['total_net']);
        $this->assertEquals(0.00, $result['total_tax']);
        $this->assertEquals(0.00, $result['total_permit_fee']);
        $this->assertEquals(0,    $result['total_bookings']);
    }

    // =========================================================================
    // getDailyNetByDate
    // =========================================================================

    public function test_get_daily_net_by_date_groups_by_date(): void
    {
        $bookings = [
            $this->makeBooking(['date' => '2026-03-01', 'net' => 100, 'refund_remove_fee' => 0]),
            $this->makeBooking(['date' => '2026-03-01', 'net' => 50,  'refund_remove_fee' => 20]),
            $this->makeBooking(['date' => '2026-03-19', 'net' => 80,  'refund_remove_fee' => 0]),
        ];

        $result = DashboardCalculatorService::getDailyNetByDate($bookings);

        $this->assertCount(2, $result['labels']);
        $this->assertEquals('Mar 1',  $result['labels'][0]);
        $this->assertEquals('Mar 19', $result['labels'][1]);
        $this->assertEquals(150, $result['booked'][0]);   // 100 + 50
        $this->assertEquals(20,  $result['refund'][0]);
        $this->assertEquals(80,  $result['booked'][1]);
        $this->assertEquals(0,   $result['refund'][1]);
    }

    public function test_get_daily_net_by_date_empty_returns_empty_arrays(): void
    {
        $result = DashboardCalculatorService::getDailyNetByDate([]);

        $this->assertEmpty($result['labels']);
        $this->assertEmpty($result['booked']);
        $this->assertEmpty($result['refund']);
    }

    // =========================================================================
    // getNetAndBookingsByPackage
    // =========================================================================

    public function test_get_net_and_bookings_by_package_groups_and_sorts_by_net_desc(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'net' => 50,  'refund' => 0]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 200, 'refund' => 0]),
            $this->makeBooking(['package' => 'Deluxe Tour',  'net' => 150, 'refund' => 30]),
        ];

        $result = DashboardCalculatorService::getNetAndBookingsByPackage($bookings);

        // Deluxe Tour first (net 350 > 50)
        $this->assertEquals('Deluxe Tour',  $result['labels'][0]);
        $this->assertEquals('General Tour', $result['labels'][1]);
        $this->assertEquals(350.00, $result['net'][0]);
        $this->assertEquals(30.00,  $result['refund'][0]);
        $this->assertEquals(2,      $result['bookings'][0]);
        $this->assertEquals(1,      $result['bookings'][1]);
    }

    public function test_get_net_and_bookings_by_package_single_package(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'net' => 100, 'refund' => 0]),
        ];

        $result = DashboardCalculatorService::getNetAndBookingsByPackage($bookings);

        $this->assertCount(1, $result['labels']);
        $this->assertEquals('General Tour', $result['labels'][0]);
        $this->assertEquals(100.00, $result['net'][0]);
        $this->assertEquals(1,      $result['bookings'][0]);
    }

    public function test_get_net_and_bookings_by_package_empty_returns_empty_arrays(): void
    {
        $result = DashboardCalculatorService::getNetAndBookingsByPackage([]);

        $this->assertEmpty($result['labels']);
        $this->assertEmpty($result['net']);
        $this->assertEmpty($result['refund']);
        $this->assertEmpty($result['bookings']);
    }

    // =========================================================================
    // getPeakBookingTimes
    // =========================================================================

    public function test_get_peak_booking_times_counts_bookings_per_slot(): void
    {
        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'time' => '09:00:00']),
            $this->makeBooking(['date' => '2026-03-19', 'time' => '09:00:00']),
            $this->makeBooking(['date' => '2026-03-19', 'time' => '14:00:00']),
        ];

        $result = DashboardCalculatorService::getPeakBookingTimes($bookings);

        $this->assertCount(2, $result);
        $this->assertEquals('09:00 AM',    $result[0]['booking_time']);
        $this->assertEquals('Mar 19', $result[0]['booking_date']);
        $this->assertEquals(2,              $result[0]['booking_count']);
        $this->assertEquals('02:00 PM',    $result[1]['booking_time']);
        $this->assertEquals('Mar 19', $result[1]['booking_date']);
        $this->assertEquals(1,              $result[1]['booking_count']);
    }

    /** Same time on different dates must produce separate entries (not merged). */
    public function test_get_peak_booking_times_same_time_different_dates_are_separate(): void
    {
        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'time' => '09:00:00']),
            $this->makeBooking(['date' => '2026-03-20', 'time' => '09:00:00']),
        ];

        $result = DashboardCalculatorService::getPeakBookingTimes($bookings);

        $this->assertCount(2, $result);
        $this->assertEquals('Mar 19', $result[0]['booking_date']);
        $this->assertEquals('Mar 20', $result[1]['booking_date']);
        $this->assertEquals(1, $result[0]['booking_count']);
        $this->assertEquals(1, $result[1]['booking_count']);
    }

    public function test_get_peak_booking_times_skips_empty_time(): void
    {
        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'time' => '08:00:00']),
            $this->makeBooking(['date' => '2026-03-19', 'time' => '']),
        ];

        $result = DashboardCalculatorService::getPeakBookingTimes($bookings);

        $this->assertCount(1, $result);
        $this->assertEquals('08:00 AM',    $result[0]['booking_time']);
        $this->assertEquals('Mar 19', $result[0]['booking_date']);
    }

    public function test_get_peak_booking_times_sorts_by_datetime_asc(): void
    {
        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'time' => '15:00:00']),
            $this->makeBooking(['date' => '2026-03-19', 'time' => '08:00:00']),
        ];

        $result = DashboardCalculatorService::getPeakBookingTimes($bookings);

        $this->assertEquals('08:00 AM', $result[0]['booking_time']);
        $this->assertEquals('03:00 PM', $result[1]['booking_time']);
    }

    public function test_get_peak_booking_times_returns_empty_when_all_times_empty(): void
    {
        $bookings = [
            $this->makeBooking(['time' => '']),
            $this->makeBooking(['time' => null]),
        ];

        $result = DashboardCalculatorService::getPeakBookingTimes($bookings);

        $this->assertEmpty($result);
    }

    // =========================================================================
    // resolveViaFilter
    // =========================================================================

    public function test_resolve_via_filter_returns_empty_when_both_arrays_empty(): void
    {
        // Empty result → caller should fall back to whereNotIn(['Bokun'])
        $result = DashboardCalculatorService::resolveViaFilter([], []);

        $this->assertEmpty($result);
    }

    public function test_resolve_via_filter_returns_payment_types_only(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(['Stripe', 'PayPal'], []);

        $this->assertEquals(['Stripe', 'PayPal'], $result);
    }

    public function test_resolve_via_filter_returns_third_party_platforms_only(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter([], ['Viator', 'Expedia']);

        $this->assertEquals(['Viator', 'Expedia'], $result);
    }

    public function test_resolve_via_filter_merges_payment_types_and_platforms(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(['Stripe'], ['Viator', 'Expedia']);

        $this->assertEquals(['Stripe', 'Viator', 'Expedia'], $result);
    }

    public function test_resolve_via_filter_non_empty_signals_whereIn_mode(): void
    {
        // Any non-empty result means whereIn should be used (not the Bokun exclusion)
        $result = DashboardCalculatorService::resolveViaFilter(['Stripe'], []);

        $this->assertNotEmpty($result);
    }

    // =========================================================================
    // getMostProfitableDays
    // =========================================================================

    public function test_get_most_profitable_days_returns_correct_day_name(): void
    {
        //Thursday
        $bookings = [
            1 => $this->makeBooking(['date' => '2026-03-19', 'amount' => 257.94, 'refund_remove_fee' => 0, 'fee_amount' => 23.04]),
        ];

        $result = DashboardCalculatorService::getMostProfitableDays($bookings);

        $this->assertEquals('Thursday',   $result[0]['day_name']);
        // net = 257.94 - 0 - 23.04 = 234.9
        $this->assertEquals(234.9, $result[0]['net_total']);
    }

    public function test_get_most_profitable_days_sorts_by_day_order(): void
    {
        // 2026-03-15 = Sunday (day_order 0), 2026-03-20 = Friday (day_order 5)
        $bookings = [
            1 => $this->makeBooking(['date' => '2026-03-20', 'amount' => 100, 'refund_remove_fee' => 0, 'fee_amount' => 10]),
            2 => $this->makeBooking(['date' => '2026-03-15', 'amount' => 50,  'refund_remove_fee' => 0, 'fee_amount' => 5]),
        ];

        $result = DashboardCalculatorService::getMostProfitableDays($bookings);

        $this->assertEquals('Sunday', $result[0]['day_name']);
        $this->assertEquals('Friday', $result[1]['day_name']);
    }

    /** Multiple bookings on the same date should accumulate net_total. */
    public function test_get_most_profitable_days_accumulates_net_for_same_date(): void
    {
        $bookings = [
            1 => $this->makeBooking(['date' => '2026-03-19', 'amount' => 100, 'refund_remove_fee' => 0, 'fee_amount' => 10, 'net' => 90]),
            2 => $this->makeBooking(['date' => '2026-03-19', 'amount' => 200, 'refund_remove_fee' => 0, 'fee_amount' => 20, 'net' => 180]),
        ];

        $result = DashboardCalculatorService::getMostProfitableDays($bookings);

        $this->assertCount(1, $result);
        // net = (100-0-10) + (200-0-20) = 90 + 180 = 270
        $this->assertEquals(270, $result[0]['net_total']);
    }

    // =========================================================================
    // getWeekendVsWeekday
    // =========================================================================

    public function test_get_weekend_vs_weekday_splits_correctly(): void
    {
        $bookings = [
            // Saturday Mar 21  → week 12 (Mon Mar 16 – Sun Mar 22)
            1 => $this->makeBooking(['date' => '2026-03-21', 'amount' => 200, 'refund_remove_fee' => 0, 'fee_amount' => 20, 'net' => 180]),
            // Monday Mar 23    → week 13 (Mon Mar 23 – Sun Mar 29)
            2 => $this->makeBooking(['date' => '2026-03-23', 'amount' => 100, 'refund_remove_fee' => 0, 'fee_amount' => 10, 'net' => 90]),
        ];

        $result = DashboardCalculatorService::getWeekendVsWeekday($bookings);

        $this->assertCount(2, $result);

        $this->assertEquals('2026-03-16', $result[0]['week_start']); // week of Mar 21
        $this->assertEquals(180,          $result[0]['weekend_total']); // net = 200-0-20
        $this->assertEquals(0,            $result[0]['weekday_total']);

        $this->assertEquals('2026-03-23', $result[1]['week_start']); // week of Mar 23
        $this->assertEquals(0,            $result[1]['weekend_total']);
        $this->assertEquals(90,           $result[1]['weekday_total']); // net = 100-0-10
    }

    public function test_get_weekend_vs_weekday_sunday_counts_as_weekend(): void
    {
        // 2026-03-22 is Sunday → week 12 (Mon Mar 16)
        $bookings = [
            1 => $this->makeBooking(['date' => '2026-03-22', 'amount' => 300, 'refund_remove_fee' => 0, 'fee_amount' => 30, 'net' => 270]),
        ];

        $result = DashboardCalculatorService::getWeekendVsWeekday($bookings);

        $this->assertCount(1, $result);
        $this->assertEquals('2026-03-16', $result[0]['week_start']);
        // net = 300 - 0 - 30 = 270
        $this->assertEquals(270, $result[0]['weekend_total']);
        $this->assertEquals(0,   $result[0]['weekday_total']);
    }

    public function test_get_weekend_vs_weekday_weekday_only(): void
    {
        // Wednesday 2026-03-18 → week 12
        $bookings = [
            1 => $this->makeBooking(['date' => '2026-03-18', 'amount' => 150, 'refund_remove_fee' => 0, 'fee_amount' => 15, 'net' => 135]),
        ];

        $result = DashboardCalculatorService::getWeekendVsWeekday($bookings);

        $this->assertCount(1, $result);
        $this->assertEquals(0,   $result[0]['weekend_total']);
        // net = 150 - 0 - 15 = 135
        $this->assertEquals(135, $result[0]['weekday_total']);
    }

    /** Both Saturday and Sunday in the same week should both add to weekend_total. */
    public function test_get_weekend_vs_weekday_both_weekend_days_in_same_week(): void
    {
        $bookings = [
            1 => $this->makeBooking(['date' => '2026-03-21', 'amount' => 100, 'refund_remove_fee' => 0, 'fee_amount' => 10, 'net' => 90]),  // Saturday
            2 => $this->makeBooking(['date' => '2026-03-22', 'amount' => 200, 'refund_remove_fee' => 0, 'fee_amount' => 20, 'net' => 180]), // Sunday
        ];

        $result = DashboardCalculatorService::getWeekendVsWeekday($bookings);

        $this->assertCount(1, $result);
        // net = (100-10) + (200-20) = 90 + 180 = 270
        $this->assertEquals(270, $result[0]['weekend_total']);
        $this->assertEquals(0,   $result[0]['weekday_total']);
    }

    // =========================================================================
    // getNetByPaymentDate
    // =========================================================================

    public function test_get_net_by_payment_date_sums_completed_transactions(): void
    {
        $rows = [
            $this->makeTransactionRow(['amount' => 200, 'fee_amount' => 20, 'created_at' => '2026-03-19 09:00:00']),
            $this->makeTransactionRow(['amount' => 100, 'fee_amount' => 10, 'created_at' => '2026-03-19 11:00:00']),
        ];

        $result = DashboardCalculatorService::getNetByPaymentDate($rows, Carbon::parse('2026-03-19'), Carbon::parse('2026-03-19'));

        // net = (200+100) - (20+10) = 270
        $this->assertEquals(270.00, $result['values'][0]);
    }

    public function test_get_net_by_payment_date_deducts_refund_with_amount(): void
    {
        $rows = [
            $this->makeTransactionRow(['status' => 'Completed', 'amount' => 200, 'fee_amount' => 20, 'created_at' => '2026-03-19 09:00:00']),
            $this->makeTransactionRow(['status' => 'Refund',    'amount' => 50,  'fee_amount' => 0,  'created_at' => '2026-03-19 10:00:00']),
        ];

        $result = DashboardCalculatorService::getNetByPaymentDate($rows, Carbon::parse('2026-03-19'), Carbon::parse('2026-03-19'));

        // total_amount = 200 - 50 = 150; net = 150 - 20 = 130
        $this->assertEquals(130.00, $result['values'][0]);
    }

    public function test_get_net_by_payment_date_deducts_fee_on_zero_amount_refund(): void
    {
        $rows = [
            $this->makeTransactionRow(['status' => 'Completed', 'amount' => 200, 'fee_amount' => 20, 'created_at' => '2026-03-19 09:00:00']),
            $this->makeTransactionRow(['status' => 'Refund',    'amount' => 0,   'fee_amount' => 20, 'created_at' => '2026-03-19 10:00:00']),
        ];

        $result = DashboardCalculatorService::getNetByPaymentDate($rows, Carbon::parse('2026-03-19'), Carbon::parse('2026-03-19'));

        // total_amount = 200 - 20 = 180; net = 180 - 20 = 160
        $this->assertEquals(160.00, $result['values'][0]);
    }

    public function test_get_net_by_payment_date_fills_missing_dates_with_zero(): void
    {
        $result = DashboardCalculatorService::getNetByPaymentDate([], Carbon::parse('2026-03-19'), Carbon::parse('2026-03-21'));

        $this->assertCount(3, $result['labels']);
        $this->assertEquals([0, 0, 0], $result['values']);
    }

    public function test_get_net_by_payment_date_skips_failed_transactions(): void
    {
        $rows = [
            $this->makeTransactionRow(['status' => 'Failed', 'amount' => 500, 'fee_amount' => 50, 'created_at' => '2026-03-19 09:00:00']),
        ];

        $result = DashboardCalculatorService::getNetByPaymentDate($rows, Carbon::parse('2026-03-19'), Carbon::parse('2026-03-19'));

        $this->assertEquals(0, $result['values'][0]);
    }

    public function test_get_net_by_payment_date_labels_format_correctly(): void
    {
        $result = DashboardCalculatorService::getNetByPaymentDate([], Carbon::parse('2026-03-19'), Carbon::parse('2026-03-21'));

        $this->assertEquals(['Mar 19', 'Mar 20', 'Mar 21'], $result['labels']);
    }

    /** Transactions on different dates within the range bucket correctly. */
    public function test_get_net_by_payment_date_buckets_multiple_dates(): void
    {
        $rows = [
            $this->makeTransactionRow(['amount' => 100, 'fee_amount' => 10, 'created_at' => '2026-03-19 08:00:00']),
            $this->makeTransactionRow(['amount' => 200, 'fee_amount' => 20, 'created_at' => '2026-03-21 08:00:00']),
        ];

        $result = DashboardCalculatorService::getNetByPaymentDate($rows, Carbon::parse('2026-03-19'), Carbon::parse('2026-03-21'));

        $this->assertEquals(90,  $result['values'][0]); // Mar 19: 100-10
        $this->assertEquals(0,   $result['values'][1]); // Mar 20: gap
        $this->assertEquals(180, $result['values'][2]); // Mar 21: 200-20
    }

    // =========================================================================
    // getTouristsAndBookings
    // =========================================================================

    public function test_get_tourists_and_bookings_daily_grouping_within_90_days(): void
    {
        $from = Carbon::parse('2026-03-19');
        $to   = Carbon::parse('2026-03-21');

        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'tourists' => 3]),
            $this->makeBooking(['date' => '2026-03-19', 'tourists' => 2]),
            $this->makeBooking(['date' => '2026-03-21', 'tourists' => 4]),
        ];

        $result = DashboardCalculatorService::getTouristsAndBookings($bookings, $from, $to);

        $this->assertEquals('date', $result['group_by']);
        $this->assertEquals(['Mar 19', 'Mar 20', 'Mar 21'], $result['labels']);
        $this->assertEquals([5, 0, 4], $result['tourists']);   // 3+2, gap, 4
        $this->assertEquals([2, 0, 1], $result['bookings']);   // 2 bookings, gap, 1
    }

    public function test_get_tourists_and_bookings_fills_gap_dates_with_zero(): void
    {
        $from = Carbon::parse('2026-03-19');
        $to   = Carbon::parse('2026-03-21');

        // Only Mar 19 has data; Mar 20 and Mar 21 must be 0
        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'tourists' => 5]),
        ];

        $result = DashboardCalculatorService::getTouristsAndBookings($bookings, $from, $to);

        $this->assertEquals([5, 0, 0], $result['tourists']);
        $this->assertEquals([1, 0, 0], $result['bookings']);
    }

    public function test_get_tourists_and_bookings_monthly_grouping_over_90_days(): void
    {
        $from = Carbon::parse('2026-01-01');
        $to   = Carbon::parse('2026-04-30'); // 119 days → month grouping

        $bookings = [
            $this->makeBooking(['date' => '2026-01-15', 'tourists' => 10]),
            $this->makeBooking(['date' => '2026-01-20', 'tourists' => 5]),
            $this->makeBooking(['date' => '2026-03-10', 'tourists' => 8]),
        ];

        $result = DashboardCalculatorService::getTouristsAndBookings($bookings, $from, $to);

        $this->assertEquals('month', $result['group_by']);

        // Labels: Jan, 2026 / Feb, 2026 / Mar, 2026 / Apr, 2026
        $this->assertCount(4, $result['labels']);
        $this->assertEquals('Jan, 2026', $result['labels'][0]);

        // Jan: tourists 15, bookings 2
        $this->assertEquals(15, $result['tourists'][0]);
        $this->assertEquals(2,  $result['bookings'][0]);

        // Feb: 0
        $this->assertEquals(0, $result['tourists'][1]);
        $this->assertEquals(0, $result['bookings'][1]);

        // Mar: tourists 8, bookings 1
        $this->assertEquals(8, $result['tourists'][2]);
        $this->assertEquals(1, $result['bookings'][2]);
    }

    public function test_get_tourists_and_bookings_empty_bookings_returns_zero_filled_labels(): void
    {
        $from = Carbon::parse('2026-03-19');
        $to   = Carbon::parse('2026-03-21');

        $result = DashboardCalculatorService::getTouristsAndBookings([], $from, $to);

        $this->assertCount(3, $result['labels']);
        $this->assertEquals([0, 0, 0], $result['tourists']);
        $this->assertEquals([0, 0, 0], $result['bookings']);
    }

    public function test_get_tourists_and_bookings_exactly_90_days_uses_daily(): void
    {
        $from = Carbon::parse('2026-01-01');
        $to   = Carbon::parse('2026-04-01'); // exactly 90 days

        $result = DashboardCalculatorService::getTouristsAndBookings([], $from, $to);

        $this->assertEquals('date', $result['group_by']);
    }

    public function test_get_tourists_and_bookings_91_days_uses_monthly(): void
    {
        $from = Carbon::parse('2026-01-01');
        $to   = Carbon::parse('2026-04-02'); // 91 days

        $result = DashboardCalculatorService::getTouristsAndBookings([], $from, $to);

        $this->assertEquals('month', $result['group_by']);
    }

    // =========================================================================
    // Multiple Tour Packages — filter selection scenarios
    // =========================================================================

    /** aggregateBookings keeps bookings for different packages fully separate. */
    public function test_aggregate_bookings_multiple_packages_kept_separate(): void
    {
        $rows = [
            $this->makeRow(['tour_booking_id' => 1, 'package_name' => 'General Tour',  'amount' => 100, 'fee_amount' => 10, 'tourists' => 2, 'infants' => 0]),
            $this->makeRow(['tour_booking_id' => 2, 'package_name' => 'Deluxe Tour',   'amount' => 300, 'fee_amount' => 30, 'tourists' => 3, 'infants' => 0]),
            $this->makeRow(['tour_booking_id' => 3, 'package_name' => 'Budget Tour',   'amount' => 150, 'fee_amount' => 15, 'tourists' => 1, 'infants' => 0]),
        ];

        $result = DashboardCalculatorService::aggregateBookings($rows);

        $this->assertCount(3, $result);
        $this->assertEquals('General Tour', $result[1]['package']);
        $this->assertEquals('Deluxe Tour',  $result[2]['package']);
        $this->assertEquals('Budget Tour',  $result[3]['package']);
    }

    /** getNetAndBookingsByPackage groups correctly when multiple packages selected. */
    public function test_get_net_and_bookings_multiple_selected_packages(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour',  'net' => 90,  'refund' => 0]),
            $this->makeBooking(['package' => 'Deluxe Tour',   'net' => 270, 'refund' => 0]),
            $this->makeBooking(['package' => 'Deluxe Tour',   'net' => 270, 'refund' => 0]),
            $this->makeBooking(['package' => 'Budget Tour',   'net' => 135, 'refund' => 0]),
        ];

        $result = DashboardCalculatorService::getNetAndBookingsByPackage($bookings);

        // Deluxe has highest net (540), then Budget (135), then General (90)
        $this->assertEquals('Deluxe Tour',  $result['labels'][0]);
        $this->assertEquals('Budget Tour',  $result['labels'][1]);
        $this->assertEquals('General Tour', $result['labels'][2]);

        $this->assertEquals(540.00, $result['net'][0]);
        $this->assertEquals(2,      $result['bookings'][0]);
        $this->assertEquals(1,      $result['bookings'][1]);
        $this->assertEquals(1,      $result['bookings'][2]);
    }

    /** getTouristsAndBookings correctly sums tourists across multiple selected packages. */
    public function test_get_tourists_and_bookings_multiple_packages_same_date(): void
    {
        $from = Carbon::parse('2026-03-19');
        $to   = Carbon::parse('2026-03-19');

        $bookings = [
            $this->makeBooking(['date' => '2026-03-19', 'tourists' => 3, 'package' => 'General Tour']),
            $this->makeBooking(['date' => '2026-03-19', 'tourists' => 4, 'package' => 'Deluxe Tour']),
            $this->makeBooking(['date' => '2026-03-19', 'tourists' => 2, 'package' => 'Budget Tour']),
        ];

        $result = DashboardCalculatorService::getTouristsAndBookings($bookings, $from, $to);

        // All on same date — 3+4+2 = 9 tourists, 3 bookings
        $this->assertEquals([9], $result['tourists']);
        $this->assertEquals([3], $result['bookings']);
    }

    /** getAvgTaxPerPersonByPackage correctly isolates tax per package when multiple selected. */
    public function test_get_avg_tax_per_person_multiple_selected_packages(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 12.00, 'tourists' => 3]),  // avg 4.00
            $this->makeBooking(['package' => 'Deluxe Tour',  'total_tax' => 30.00, 'tourists' => 3]),  // avg 10.00
            $this->makeBooking(['package' => 'Budget Tour',  'total_tax' => 6.00,  'tourists' => 3]),  // avg 2.00
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        // Sorted DESC by avg: Deluxe (10), General (4), Budget (2)
        $this->assertEquals(['Deluxe Tour', 'General Tour', 'Budget Tour'], $result['labels']);
        $this->assertEquals([10.00, 4.00, 2.00], $result['values']);
    }

    /** calculateFromAggregated totals correctly across bookings from multiple packages. */
    public function test_calculate_from_aggregated_multiple_packages(): void
    {
        $bookings = [
            1 => $this->makeBooking(['package' => 'General Tour', 'amount' => 100, 'fee_amount' => 10, 'refund' => 0,  'refund_remove_fee' => 0,  'discount' => 0, 'tourists' => 2, 'net' => 90,  'total_tax' => 4.00,  'total_permit_fee' => 0]),
            2 => $this->makeBooking(['package' => 'Deluxe Tour',  'amount' => 300, 'fee_amount' => 30, 'refund' => 0,  'refund_remove_fee' => 0,  'discount' => 0, 'tourists' => 3, 'net' => 270, 'total_tax' => 10.00, 'total_permit_fee' => 0]),
            3 => $this->makeBooking(['package' => 'Budget Tour',  'amount' => 150, 'fee_amount' => 15, 'refund' => 50, 'refund_remove_fee' => 40, 'discount' => 0, 'tourists' => 1, 'net' => 95,  'total_tax' => 2.00,  'total_permit_fee' => 0]),
        ];

        $result = DashboardCalculatorService::calculateFromAggregated($bookings);

        $this->assertEquals(550.00, $result['total_gross']);    // 100+300+150
        $this->assertEquals(455.00, $result['total_net']);      // 90+270+95
        $this->assertEquals(40.00,  $result['total_refund']);
        $this->assertEquals(16.00,  $result['total_tax']);      // 4+10+2
        $this->assertEquals(3,      $result['total_bookings']);
        $this->assertEquals(6,      $result['total_tourists']); // 2+3+1
    }

    // =========================================================================
    // formatCurrencyShort
    // =========================================================================

    public function test_format_currency_short_millions(): void
    {
        $this->assertEquals('1.23M', DashboardCalculatorService::formatCurrencyShort(1234567));
    }

    public function test_format_currency_short_thousands(): void
    {
        $this->assertEquals('1.2k', DashboardCalculatorService::formatCurrencyShort(1200));
    }

    public function test_format_currency_short_below_thousand(): void
    {
        $this->assertEquals('500.00', DashboardCalculatorService::formatCurrencyShort(500));
    }

    public function test_format_currency_short_zero(): void
    {
        $this->assertEquals('0.00', DashboardCalculatorService::formatCurrencyShort(0));
    }

    // =========================================================================
// getStateTaxByDate
// =========================================================================

public function test_get_state_tax_by_date_sums_tax_per_day(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-19');

    $bookings = [
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 5.00]),
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 7.50]),
    ];

    $result = DashboardCalculatorService::getStateTaxByDate($bookings, $from, $to);

    $this->assertCount(1, $result['labels']);
    $this->assertEquals('Mar 19', $result['labels'][0]);
    $this->assertEquals(12.50,    $result['values'][0]);
}

public function test_get_state_tax_by_date_fills_missing_dates_with_zero(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-21');

    $bookings = [
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 10.00]),
        // Mar 20 intentionally missing
        $this->makeBooking(['date' => '2026-03-21', 'total_tax' => 6.00]),
    ];

    $result = DashboardCalculatorService::getStateTaxByDate($bookings, $from, $to);

    $this->assertCount(3, $result['labels']);
    $this->assertEquals(['Mar 19', 'Mar 20', 'Mar 21'], $result['labels']);
    $this->assertEquals(10.00, $result['values'][0]);
    $this->assertEquals(0.00,  $result['values'][1]); // gap
    $this->assertEquals(6.00,  $result['values'][2]);
}

public function test_get_state_tax_by_date_empty_bookings_returns_zero_filled_range(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-21');

    $result = DashboardCalculatorService::getStateTaxByDate([], $from, $to);

    $this->assertCount(3, $result['labels']);
    $this->assertEquals([0, 0, 0], $result['values']);
}

public function test_get_state_tax_by_date_rounds_to_two_decimals(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-19');

    $bookings = [
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 3.333]),
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 3.333]),
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 3.334]),
    ];

    $result = DashboardCalculatorService::getStateTaxByDate($bookings, $from, $to);

    $this->assertEquals(10.00, $result['values'][0]);
}

public function test_get_state_tax_by_date_single_day_range(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-19');

    $result = DashboardCalculatorService::getStateTaxByDate([], $from, $to);

    $this->assertCount(1, $result['labels']);
    $this->assertEquals('Mar 19', $result['labels'][0]);
    $this->assertEquals(0.00,     $result['values'][0]);
}

public function test_get_state_tax_by_date_bookings_outside_range_are_excluded(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-19');

    // Mar 18 and Mar 20 are outside the requested range
    $bookings = [
        $this->makeBooking(['date' => '2026-03-18', 'total_tax' => 99.00]),
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 5.00]),
        $this->makeBooking(['date' => '2026-03-20', 'total_tax' => 99.00]),
    ];

    $result = DashboardCalculatorService::getStateTaxByDate($bookings, $from, $to);

    $this->assertCount(1, $result['labels']);
    $this->assertEquals(5.00, $result['values'][0]);
}

public function test_get_state_tax_by_date_zero_tax_bookings_contribute_zero(): void
{
    $from = Carbon::parse('2026-03-19');
    $to   = Carbon::parse('2026-03-19');

    $bookings = [
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 0.00]),
        $this->makeBooking(['date' => '2026-03-19', 'total_tax' => 0.00]),
    ];

    $result = DashboardCalculatorService::getStateTaxByDate($bookings, $from, $to);

    $this->assertEquals(0.00, $result['values'][0]);
}

// =========================================================================
// getAvgTaxPerPersonByPackage
// =========================================================================

    public function test_get_avg_tax_per_person_by_package_calculates_average_correctly(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 30.00, 'tourists' => 3]),
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        $this->assertCount(1, $result['labels']);
        $this->assertEquals('General Tour', $result['labels'][0]);
        $this->assertEquals(10.00,          $result['values'][0]); // 30 / 3
    }

    public function test_get_avg_tax_per_person_by_package_aggregates_multiple_bookings_per_package(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 20.00, 'tourists' => 2]),
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 40.00, 'tourists' => 4]),
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        $this->assertCount(1, $result['labels']);
        // total_tax = 60, total_tourists = 6 → avg = 10.00
        $this->assertEquals(10.00, $result['values'][0]);
    }

    public function test_get_avg_tax_per_person_by_package_sorts_by_avg_desc(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 10.00, 'tourists' => 2]),  // avg 5.00
            $this->makeBooking(['package' => 'Deluxe Tour',  'total_tax' => 60.00, 'tourists' => 3]),  // avg 20.00
            $this->makeBooking(['package' => 'Affiliate Tour',  'total_tax' => 9.00,  'tourists' => 3]),  // avg 3.00
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        $this->assertEquals(['Deluxe Tour', 'General Tour', 'Affiliate Tour'], $result['labels']);
        $this->assertEquals([20.00, 5.00, 3.00], $result['values']);
    }

    public function test_get_avg_tax_per_person_by_package_rounds_to_two_decimals(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 10.00, 'tourists' => 3]),
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        // 10 / 3 = 3.333... → 3.33
        $this->assertEquals(3.33, $result['values'][0]);
    }

    public function test_get_avg_tax_per_person_by_package_zero_tourists_returns_zero_avg(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 50.00, 'tourists' => 0]),
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        $this->assertEquals(0, $result['values'][0]);
    }

    public function test_get_avg_tax_per_person_by_package_zero_tax_returns_zero_avg(): void
    {
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 0.00, 'tourists' => 5]),
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        $this->assertEquals(0.00, $result['values'][0]);
    }

    public function test_get_avg_tax_per_person_by_package_empty_returns_empty_arrays(): void
    {
        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage([]);

        $this->assertEmpty($result['labels']);
        $this->assertEmpty($result['values']);
    }

    public function test_get_avg_tax_per_person_by_package_multiple_packages_isolated(): void
    {
        // Ensures taxes from one package don't leak into another
        $bookings = [
            $this->makeBooking(['package' => 'General Tour', 'total_tax' => 100.00, 'tourists' => 4]), // avg 25.00
            $this->makeBooking(['package' => 'Deluxe Tour', 'total_tax' => 30.00,  'tourists' => 6]), // avg 5.00
        ];

        $result = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);

        $this->assertEquals('General Tour', $result['labels'][0]);
        $this->assertEquals(25.00,       $result['values'][0]);
        $this->assertEquals('Deluxe Tour', $result['labels'][1]);
        $this->assertEquals(5.00,        $result['values'][1]);
    }


}