<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an aggregated booking array (output of aggregateBookings).
     * Fields read by computeInsights: date, net, tourists, total_pax.
     */
    private function makeBooking(array $overrides = []): array
    {
        $base = array_merge([
            'date'        => '2026-04-09',
            'net'         => 200.00,
            'tourists'    => 2,
            'total_pax'   => 2,
            'has_payment' => true,
        ], $overrides);

        if (!isset($overrides['total_pax']) && isset($overrides['tourists'])) {
            $base['total_pax'] = $overrides['tourists'];
        }

        return $base;
    }

    private function makeCustomer(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name];
    }

    private function findSection(array $sections, string $label): ?array
    {
        foreach ($sections as $section) {
            if ($section['label'] === $label) {
                return $section;
            }
        }
        return null;
    }

    private function findKeyPattern(array $keyPatterns, int $num): ?array
    {
        foreach ($keyPatterns as $kp) {
            if ($kp['num'] === $num) {
                return $kp;
            }
        }
        return null;
    }

    // =========================================================================
    // computeInsights — empty / zero state
    // =========================================================================

    public function test_empty_bookings_returns_empty_sections_and_key_patterns(): void
    {
        $result = DashboardCalculatorService::computeInsights([], [], []);

        $this->assertArrayHasKey('sections',     $result);
        $this->assertArrayHasKey('key_patterns', $result);
        $this->assertEmpty($result['sections']);
        $this->assertEmpty($result['key_patterns']);
    }

    public function test_zero_and_negative_net_bookings_produce_empty_sections(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 0.00]),
            2 => $this->makeBooking(['net' => -100.00]),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], []);

        $this->assertEmpty($result['sections']);
        $this->assertEmpty($result['key_patterns']);
    }

    // =========================================================================
    // computeInsights — REVENUE PERFORMANCE section
    // =========================================================================

    public function test_revenue_section_label_is_correct(): void
    {
        $bookings = [1 => $this->makeBooking(['net' => 500.00, 'date' => '2026-04-09'])];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 10]);

        $labels = array_column($result['sections'], 'label');
        $this->assertContains('REVENUE PERFORMANCE', $labels);
    }

    public function test_single_week_generates_revenue_this_week_card(): void
    {
        // Both bookings fall in ISO week 15 of 2026 — 2026-04-06 Mon, 2026-04-08 Wed
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-06']), // Mon week 15
            2 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-08']), // Wed week 15
        ];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        $this->assertNotNull($revSection);
        $titles = implode(' ', array_column($revSection['cards'], 'title'));
        $this->assertStringContainsString('Revenue This Week', $titles);
    }

    public function test_two_weeks_generates_weekly_revenue_trend_card(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-06']), // week 15
            2 => $this->makeBooking(['net' => 600.00, 'date' => '2026-04-13']), // week 16
        ];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        $this->assertNotNull($revSection);
        $titles = implode(' ', array_column($revSection['cards'], 'title'));
        $this->assertStringContainsString('Weekly Revenue', $titles);
        $this->assertStringContainsString('Weeks', $titles);
    }

    public function test_weekly_trend_title_contains_grew_when_last_week_exceeds_first(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-06']), // week 15 — first
            2 => $this->makeBooking(['net' => 800.00, 'date' => '2026-04-13']), // week 16 — last
        ];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        $titles = implode(' ', array_column($revSection['cards'], 'title'));
        $this->assertStringContainsString('Grew', $titles);
    }

    public function test_high_revenue_days_card_is_generated_for_any_booking(): void
    {
        $bookings = [1 => $this->makeBooking(['net' => 5000.00, 'date' => '2026-04-09'])];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 10]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        $this->assertNotNull($revSection);
        $titles = implode(' ', array_column($revSection['cards'], 'title'));
        $this->assertStringContainsString('Day', $titles);
    }

    public function test_peak_month_card_not_generated_with_single_month(): void
    {
        // All bookings in April 2026 — requires >= 2 months
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-10']),
            2 => $this->makeBooking(['net' => 400.00, 'date' => '2026-04-20']),
        ];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        if ($revSection) {
            $titles = implode(' ', array_column($revSection['cards'], 'title'));
            $this->assertStringNotContainsString('Was the Peak Month', $titles);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_peak_month_card_generated_with_two_months(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 700.00, 'date' => '2026-04-15']),
        ];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        $this->assertNotNull($revSection);
        $titles = implode(' ', array_column($revSection['cards'], 'title'));
        $this->assertStringContainsString('Was the Peak Month', $titles);
    }

    public function test_peak_month_card_title_contains_peak_month_name(): void
    {
        // April 2026 has higher revenue → it is the peak month; month name appears in the title
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 700.00, 'date' => '2026-04-15']),
        ];

        $result     = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $revSection = $this->findSection($result['sections'], 'REVENUE PERFORMANCE');

        $this->assertNotNull($revSection);
        $titles = implode(' ', array_column($revSection['cards'], 'title'));
        $this->assertStringContainsString('Apr', $titles);
        $this->assertStringContainsString('Was the Peak Month', $titles);
    }

    // =========================================================================
    // computeInsights — PRICING & YIELD section
    // =========================================================================

    public function test_pricing_section_label_is_correct(): void
    {
        // Two different group sizes are needed to generate the pricing section
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-16']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $labels = array_column($result['sections'], 'label');
        $this->assertContains('PRICING & YIELD', $labels);
    }

    public function test_rpt_card_not_generated_when_only_one_group_size_present(): void
    {
        // All Pair(2) bookings — only one segment has data
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-16']),
        ];

        $result         = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        if ($pricingSection) {
            $titles = implode(' ', array_column($pricingSection['cards'], 'title'));
            $this->assertStringNotContainsString('Revenue Per Tourist Is', $titles);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_rpt_card_generated_with_two_different_group_sizes(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 400.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-16']),
        ];

        $result         = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        $this->assertNotNull($pricingSection);
        $titles = implode(' ', array_column($pricingSection['cards'], 'title'));
        $this->assertStringContainsString('Revenue Per Tourist Is', $titles);
    }

    public function test_advance_bookers_card_generated_when_far_advance_has_larger_avg_pax(): void
    {
        // 0–7 day window: 1 pax, 180+ day window: 4 pax → advance bookers card shown
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 800.00, 'tourists' => 4, 'total_pax' => 4, 'date' => '2026-04-16']),
        ];
        $windowByBooking = [1 => 3, 2 => 200]; // booking 1: 3 days ahead (0–7), booking 2: 200 days (180+)

        $result         = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        $this->assertNotNull($pricingSection);
        $bodies = implode(' ', array_column($pricingSection['cards'], 'body'));
        $this->assertStringContainsString('180+', $bodies);
    }

    public function test_booking_window_fallback_card_when_same_day_pax_exceeds_advance(): void
    {
        // Same-day avg pax (4) > far-advance avg pax (1) → fallback to most-common-bucket card
        $bookings = [
            1 => $this->makeBooking(['net' => 400.00, 'tourists' => 4, 'total_pax' => 4, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-16']),
        ];
        $windowByBooking = [1 => 3, 2 => 200];

        $result         = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        $this->assertNotNull($pricingSection);
        $titles = implode(' ', array_column($pricingSection['cards'], 'title'));
        $this->assertStringContainsString('Most Common Booking Window', $titles);
    }

    public function test_mom_rpt_card_generated_with_two_months_data(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 400.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-15']),
        ];

        $result         = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        $this->assertNotNull($pricingSection);
        $titles = implode(' ', array_column($pricingSection['cards'], 'title'));
        $this->assertStringContainsString('Revenue Per Tourist', $titles);
        $this->assertStringContainsString('Month-on-Month', $titles);
    }

    public function test_mom_rpt_card_title_says_rose_when_yield_increases(): void
    {
        // Mar RPT: 100/1=100, Apr RPT: 400/2=200 → yield rose
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 400.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-15']),
        ];

        $result         = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        $this->assertNotNull($pricingSection);
        $titles = implode(' ', array_column($pricingSection['cards'], 'title'));
        $this->assertStringContainsString('Rose', $titles);
    }

    public function test_mom_rpt_card_title_says_declined_when_yield_falls(): void
    {
        // Mar RPT: 400/2=200, Apr RPT: 100/1=100 → yield declined
        $bookings = [
            1 => $this->makeBooking(['net' => 400.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
        ];

        $result         = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $pricingSection = $this->findSection($result['sections'], 'PRICING & YIELD');

        $this->assertNotNull($pricingSection);
        $titles = implode(' ', array_column($pricingSection['cards'], 'title'));
        $this->assertStringContainsString('Declined', $titles);
    }

    // =========================================================================
    // computeInsights — CUSTOMER & BOOKING BEHAVIOUR section
    // =========================================================================

    public function test_customer_section_label_is_correct(): void
    {
        $bookings          = [1 => $this->makeBooking(['net' => 500.00])];
        $customerByBooking = [1 => $this->makeCustomer(1, 'Alice')];

        $result = DashboardCalculatorService::computeInsights($bookings, $customerByBooking, [1 => 5]);
        $labels = array_column($result['sections'], 'label');
        $this->assertContains('CUSTOMER & BOOKING BEHAVIOUR', $labels);
    }

    public function test_top_customers_card_title_shows_count_revenue_and_percentage(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 400.00, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-16']),
        ];
        $customerByBooking = [
            1 => $this->makeCustomer(1, 'Alice'),
            2 => $this->makeCustomer(2, 'Bob'),
        ];

        $result      = DashboardCalculatorService::computeInsights($bookings, $customerByBooking, [1 => 5, 2 => 5]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $titles = implode(' ', array_column($custSection['cards'], 'title'));
        $this->assertStringContainsString('Customers Generated', $titles);
        $this->assertStringContainsString('% of Revenue', $titles);
    }

    public function test_top_customers_card_body_lists_top_customer_name(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 400.00, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-16']),
        ];
        $customerByBooking = [
            1 => $this->makeCustomer(1, 'Alice'),
            2 => $this->makeCustomer(2, 'Bob'),
        ];

        $result      = DashboardCalculatorService::computeInsights($bookings, $customerByBooking, [1 => 5, 2 => 5]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $bodies = implode(' ', array_column($custSection['cards'], 'body'));
        $this->assertStringContainsString('Alice', $bodies);
    }

    public function test_unknown_customer_name_used_when_booking_not_in_customer_map(): void
    {
        $bookings = [99 => $this->makeBooking(['net' => 200.00])];
        // No entry in customerByBooking for booking 99

        $result      = DashboardCalculatorService::computeInsights($bookings, [], [99 => 5]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $bodies = implode(' ', array_column($custSection['cards'], 'body'));
        $this->assertStringContainsString('Unknown', $bodies);
    }

    public function test_most_common_booking_window_card_generated(): void
    {
        // 3 bookings in the 0–7 window → it becomes the most common
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 100.00, 'date' => '2026-04-16']),
            3 => $this->makeBooking(['net' => 100.00, 'date' => '2026-04-17']),
            4 => $this->makeBooking(['net' => 100.00, 'date' => '2026-04-18']),
        ];
        $windowByBooking = [1 => 3, 2 => 5, 3 => 7, 4 => 60];

        $result      = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $titles = implode(' ', array_column($custSection['cards'], 'title'));
        $this->assertStringContainsString('Most Common Booking Window', $titles);
        $this->assertStringContainsString('0–7 days', $titles);
    }

    public function test_dow_card_title_identifies_day_with_highest_avg_per_booking(): void
    {
        // 2026-04-06 is Monday (dow=1), 2026-04-05 is Sunday (dow=0)
        // Monday gets $1000, Sunday gets $100 → Monday has highest avg per booking
        $bookings = [
            1 => $this->makeBooking(['net' => 1000.00, 'date' => '2026-04-06']),
            2 => $this->makeBooking(['net' => 100.00,  'date' => '2026-04-05']),
        ];

        $result      = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $titles = implode(' ', array_column($custSection['cards'], 'title'));
        $this->assertStringContainsString('Highest Revenue Per Booking', $titles);
        $this->assertStringContainsString('Monday', $titles);
    }

    public function test_dow_card_body_mentions_lowest_avg_day(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 1000.00, 'date' => '2026-04-06']), // Monday
            2 => $this->makeBooking(['net' => 100.00,  'date' => '2026-04-05']), // Sunday
        ];

        $result      = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $bodies = implode(' ', array_column($custSection['cards'], 'body'));
        $this->assertStringContainsString('Sunday', $bodies);
        $this->assertStringContainsString('lowest', $bodies);
    }

    public function test_booking_window_card_body_contains_median_days(): void
    {
        // Single booking with window = 15 days → median = 15
        $bookings = [1 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-09'])];

        $result      = DashboardCalculatorService::computeInsights($bookings, [], [1 => 15]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $bodies = implode(' ', array_column($custSection['cards'], 'body'));
        $this->assertStringContainsString('median', $bodies);
        $this->assertStringContainsString('15', $bodies);
    }

    // =========================================================================
    // computeInsights — booking window bucket boundaries
    // =========================================================================

    /**
     * @dataProvider bookingWindowBucketProvider
     */
    public function test_booking_window_assigned_to_correct_bucket(int $daysAhead, string $expectedBucket): void
    {
        // Single booking in one bucket → it is the most common bucket
        $bookings = [1 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-09'])];

        $result      = DashboardCalculatorService::computeInsights($bookings, [], [1 => $daysAhead]);
        $custSection = $this->findSection($result['sections'], 'CUSTOMER & BOOKING BEHAVIOUR');

        $this->assertNotNull($custSection);
        $titles = implode(' ', array_column($custSection['cards'], 'title'));
        $this->assertStringContainsString($expectedBucket, $titles);
    }

    public static function bookingWindowBucketProvider(): array
    {
        return [
            '0 days → 0–7 days'      => [0,   '0–7 days'],
            '7 days → 0–7 days'      => [7,   '0–7 days'],
            '8 days → 8–30 days'     => [8,   '8–30 days'],
            '30 days → 8–30 days'    => [30,  '8–30 days'],
            '31 days → 31–90 days'   => [31,  '31–90 days'],
            '90 days → 31–90 days'   => [90,  '31–90 days'],
            '91 days → 91–180 days'  => [91,  '91–180 days'],
            '180 days → 91–180 days' => [180, '91–180 days'],
            '181 days → 180+ days'   => [181, '180+ days'],
        ];
    }

    // =========================================================================
    // computeInsights — card structure
    // =========================================================================

    public function test_all_cards_have_required_fields(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 600.00, 'tourists' => 4, 'total_pax' => 4, 'date' => '2026-04-15']),
        ];
        $customerByBooking = [
            1 => $this->makeCustomer(1, 'Alice'),
            2 => $this->makeCustomer(2, 'Bob'),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, $customerByBooking, [1 => 5, 2 => 200]);

        foreach ($result['sections'] as $section) {
            foreach ($section['cards'] as $card) {
                $this->assertArrayHasKey('title',  $card, "Card in section '{$section['label']}' missing 'title'");
                $this->assertArrayHasKey('body',   $card, "Card in section '{$section['label']}' missing 'body'");
                $this->assertArrayHasKey('accent', $card, "Card in section '{$section['label']}' missing 'accent'");
            }
        }
    }

    public function test_card_accent_is_one_of_valid_values(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 600.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-15']),
        ];

        $result       = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);
        $validAccents = ['green', 'teal', 'orange'];

        foreach ($result['sections'] as $section) {
            foreach ($section['cards'] as $card) {
                $this->assertContains($card['accent'], $validAccents, "Unexpected accent '{$card['accent']}' in card '{$card['title']}'");
            }
        }
    }

    // =========================================================================
    // computeInsights — key_patterns
    // =========================================================================

    public function test_key_pattern_rows_have_required_fields(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-03-15']),
            2 => $this->makeBooking(['net' => 600.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-15']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        foreach ($result['key_patterns'] as $kp) {
            $this->assertArrayHasKey('num',        $kp);
            $this->assertArrayHasKey('pattern',    $kp);
            $this->assertArrayHasKey('data_point', $kp);
            $this->assertArrayHasKey('scale',      $kp);
            $this->assertArrayHasKey('period',     $kp);
        }
    }

    public function test_key_patterns_are_in_ascending_num_order(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-06']),
            2 => $this->makeBooking(['net' => 400.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-13']),
            3 => $this->makeBooking(['net' => 600.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-03-15']),
        ];
        $windowByBooking = [1 => 3, 2 => 200, 3 => 50];

        $result = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);

        $nums   = array_column($result['key_patterns'], 'num');
        $sorted = $nums;
        sort($sorted);
        $this->assertEquals($sorted, $nums, 'Key patterns should be ordered by num ascending');
    }

    public function test_key_pattern_1_identifies_group_segment_with_most_revenue(): void
    {
        // Large(10+) generates $2000 vs Solo(1) $100 → Large wins
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00,  'tourists' => 1,  'total_pax' => 1,  'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 2000.00, 'tourists' => 12, 'total_pax' => 12, 'date' => '2026-04-16']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp1 = $this->findKeyPattern($result['key_patterns'], 1);
        $this->assertNotNull($kp1);
        $this->assertStringContainsString('Large(10+)', $kp1['pattern']);
        $this->assertStringContainsString('most revenue', $kp1['pattern']);
        $this->assertEquals('Full period', $kp1['period']);
    }

    public function test_key_pattern_2_identifies_most_frequent_booking_type(): void
    {
        // Pair(2) has 3 bookings vs Solo(1) has 1 → Pair is most frequent
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-16']),
            3 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-17']),
            4 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-18']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5, 3 => 5, 4 => 5]);

        $kp2 = $this->findKeyPattern($result['key_patterns'], 2);
        $this->assertNotNull($kp2);
        $this->assertStringContainsString('Pair(2)', $kp2['pattern']);
        $this->assertStringContainsString('most frequent', $kp2['pattern']);
        $this->assertEquals('Full period', $kp2['period']);
    }

    public function test_key_pattern_3_identifies_segment_with_highest_rev_per_booking(): void
    {
        // Large(10+): $2000/1 booking = $2000 avg vs Solo(1): $100/1 booking = $100 avg
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00,  'tourists' => 1,  'total_pax' => 1,  'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 2000.00, 'tourists' => 12, 'total_pax' => 12, 'date' => '2026-04-16']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp3 = $this->findKeyPattern($result['key_patterns'], 3);
        $this->assertNotNull($kp3);
        $this->assertStringContainsString('Large(10+)', $kp3['pattern']);
        $this->assertStringContainsString('highest rev per booking', $kp3['pattern']);
    }

    public function test_key_pattern_4_not_generated_with_single_group_size(): void
    {
        // All Pair(2) — only one group-size segment, no RPT stability pattern
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-16']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp4 = $this->findKeyPattern($result['key_patterns'], 4);
        $this->assertNull($kp4);
    }

    public function test_key_pattern_4_rpt_stability_generated_with_two_group_sizes(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'tourists' => 2, 'total_pax' => 2, 'date' => '2026-04-16']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp4 = $this->findKeyPattern($result['key_patterns'], 4);
        $this->assertNotNull($kp4);
        $this->assertStringContainsString('Revenue per tourist is stable', $kp4['pattern']);
        $this->assertEquals('Full period', $kp4['period']);
    }

    public function test_key_pattern_5_not_generated_with_single_month(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-10']),
            2 => $this->makeBooking(['net' => 400.00, 'date' => '2026-04-20']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp5 = $this->findKeyPattern($result['key_patterns'], 5);
        $this->assertNull($kp5);
    }

    public function test_key_pattern_5_steepest_growth_month_identified(): void
    {
        // Feb 2026: $200, Mar 2026: $600 (+200%), Apr 2026: $300 (-50%) → Mar is steepest growth month
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00, 'date' => '2026-02-15']),
            2 => $this->makeBooking(['net' => 600.00, 'date' => '2026-03-15']),
            3 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-15']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5, 3 => 5]);

        $kp5 = $this->findKeyPattern($result['key_patterns'], 5);
        $this->assertNotNull($kp5);
        $this->assertStringContainsString('steepest growth month', $kp5['pattern']);
        // period uses Carbon::format('F Y') — "Mar 2026" or "March 2026" depending on locale
        $this->assertStringContainsString('Mar', $kp5['period']);
    }

    public function test_key_pattern_6_not_generated_without_both_window_buckets(): void
    {
        // Only 0–7 day bucket filled — no 180+ days data
        $bookings = [
            1 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-16']),
        ];
        $windowByBooking = [1 => 3, 2 => 5]; // both in 0–7 bucket

        $result = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);

        $kp6 = $this->findKeyPattern($result['key_patterns'], 6);
        $this->assertNull($kp6);
    }

    public function test_key_pattern_6_advance_bookers_generated_with_both_window_buckets(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 400.00, 'tourists' => 4, 'total_pax' => 4, 'date' => '2026-04-16']),
        ];
        $windowByBooking = [1 => 3, 2 => 200]; // 0–7 bucket + 180+ bucket

        $result = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);

        $kp6 = $this->findKeyPattern($result['key_patterns'], 6);
        $this->assertNotNull($kp6);
        $this->assertStringContainsString('Advance bookers bring bigger groups', $kp6['pattern']);
        $this->assertEquals('Full period', $kp6['period']);
    }

    public function test_key_pattern_6_data_point_shows_avg_pax_comparison(): void
    {
        // 0–7 days: 1 booking × 1 pax = avg 1.0, 180+ days: 1 booking × 4 pax = avg 4.0
        $bookings = [
            1 => $this->makeBooking(['net' => 100.00, 'tourists' => 1, 'total_pax' => 1, 'date' => '2026-04-15']),
            2 => $this->makeBooking(['net' => 400.00, 'tourists' => 4, 'total_pax' => 4, 'date' => '2026-04-16']),
        ];
        $windowByBooking = [1 => 3, 2 => 200];

        $result = DashboardCalculatorService::computeInsights($bookings, [], $windowByBooking);

        $kp6 = $this->findKeyPattern($result['key_patterns'], 6);
        $this->assertNotNull($kp6);
        $this->assertStringContainsString('4', $kp6['data_point']); // 4.0 pax avg for 180+
        $this->assertStringContainsString('1', $kp6['data_point']); // 1.0 pax avg for same-day
    }

    public function test_key_pattern_7_dow_analysis_generated(): void
    {
        $bookings = [
            1 => $this->makeBooking(['net' => 1000.00, 'date' => '2026-04-06']), // Monday
            2 => $this->makeBooking(['net' => 200.00,  'date' => '2026-04-05']), // Sunday
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp7 = $this->findKeyPattern($result['key_patterns'], 7);
        $this->assertNotNull($kp7);
        $this->assertStringContainsString('highest total revenue', $kp7['pattern']);
        $this->assertStringContainsString('highest per booking', $kp7['pattern']);
    }

    public function test_key_pattern_8_not_generated_with_single_week(): void
    {
        // Both bookings in ISO week 15 of 2026 (Mon 2026-04-06, Wed 2026-04-08)
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-06']),
            2 => $this->makeBooking(['net' => 200.00, 'date' => '2026-04-08']),
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp8 = $this->findKeyPattern($result['key_patterns'], 8);
        $this->assertNull($kp8);
    }

    public function test_key_pattern_8_weekly_growth_generated_with_two_weeks(): void
    {
        // week 15: $300, week 16: $900 → multiple = 3×
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-06']), // week 15
            2 => $this->makeBooking(['net' => 900.00, 'date' => '2026-04-13']), // week 16
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5]);

        $kp8 = $this->findKeyPattern($result['key_patterns'], 8);
        $this->assertNotNull($kp8);
        $this->assertStringContainsString('Weekly revenue grew', $kp8['pattern']);
        $this->assertStringContainsString('3×', $kp8['pattern']);
    }

    public function test_key_pattern_8_scale_contains_week_count(): void
    {
        // Three consecutive ISO weeks of April 2026 (weeks 15, 16, 17)
        $bookings = [
            1 => $this->makeBooking(['net' => 300.00, 'date' => '2026-04-06']), // week 15
            2 => $this->makeBooking(['net' => 600.00, 'date' => '2026-04-13']), // week 16
            3 => $this->makeBooking(['net' => 900.00, 'date' => '2026-04-20']), // week 17
        ];

        $result = DashboardCalculatorService::computeInsights($bookings, [], [1 => 5, 2 => 5, 3 => 5]);

        $kp8 = $this->findKeyPattern($result['key_patterns'], 8);
        $this->assertNotNull($kp8);
        $this->assertStringContainsString('3-week', $kp8['scale']);
    }
}
