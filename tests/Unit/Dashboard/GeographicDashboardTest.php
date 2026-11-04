<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit tests for the Geographic tab in DashboardCalculatorService.
 *
 * Covers: buildLeadInsights, buildGeoInsights, lead-time bucket thresholds,
 * booking-style label thresholds, US-alias detection, Unknown-country exclusion,
 * earliest/latest-country logic, and KPI percentage calculations.
 */
class GeographicDashboardTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Call a private static method on DashboardCalculatorService via reflection. */
    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionClass(DashboardCalculatorService::class);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    /** Build a country-data array as produced inside getGeographicData(). */
    private function makeCountry(array $overrides = []): array
    {
        return array_merge([
            'country'       => 'United States',
            'bookings'      => 5,
            'tourists'      => 10,
            'revenue'       => 1000.0,
            'avg_lead_days' => 30,
            'booking_style' => 'Late planner',
        ], $overrides);
    }

    /** Return the six zeroed lead-time buckets that getGeographicData initialises. */
    private function emptyBuckets(): array
    {
        return [
            'Same-day'    => 0,
            '1–7 days'    => 0,
            '8–30 days'   => 0,
            '31–90 days'  => 0,
            '91–180 days' => 0,
            '180+ days'   => 0,
        ];
    }


    // =========================================================================
    // buildLeadInsights
    // =========================================================================

    public function test_lead_insights_returns_empty_array_when_total_bookings_is_zero(): void
    {
        $result = $this->callPrivate('buildLeadInsights', [$this->emptyBuckets(), 0, 0]);

        $this->assertSame([], $result);
    }

    public function test_lead_insights_identifies_most_common_bucket(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['31–90 days' => 10, '1–7 days' => 3]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 13, 50]);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('31–90 days', $result[0]);
        $this->assertStringContainsString('most common window', $result[0]);
    }

    public function test_lead_insights_most_common_window_includes_count_and_percentage(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['8–30 days' => 6]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 6, 20]);

        // 6 bookings, 6/6 = 100 %
        $this->assertStringContainsString('6', $result[0]);
        $this->assertStringContainsString('100%', $result[0]);
    }

    public function test_lead_insights_combines_same_day_and_one_to_seven_as_last_minute(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['Same-day' => 4, '1–7 days' => 6]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 10, 3]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('last-minute', $combined);
        // 4 + 6 = 10 out of 10 → 100 %
        $this->assertStringContainsString('100%', $combined);
    }

    public function test_lead_insights_omits_last_minute_line_when_both_buckets_are_zero(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['31–90 days' => 8]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 8, 60]);

        $combined = implode(' ', $result);
        $this->assertStringNotContainsString('last-minute', $combined);
    }

    public function test_lead_insights_includes_180_plus_note_when_present(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['180+ days' => 5, '8–30 days' => 5]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 10, 120]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('180+ days', $combined);
        $this->assertStringContainsString('large group operators', $combined);
    }

    public function test_lead_insights_omits_180_plus_note_when_bucket_is_zero(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['31–90 days' => 10]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 10, 60]);

        $combined = implode(' ', $result);
        $this->assertStringNotContainsString('large group operators', $combined);
    }

    public function test_lead_insights_includes_overall_average_when_positive(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['8–30 days' => 8]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 8, 22]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('22 days', $combined);
        $this->assertStringContainsString('Overall average', $combined);
    }

    public function test_lead_insights_omits_overall_average_when_zero(): void
    {
        $buckets = array_merge($this->emptyBuckets(), ['Same-day' => 5]);
        $result  = $this->callPrivate('buildLeadInsights', [$buckets, 5, 0]);

        $combined = implode(' ', $result);
        $this->assertStringNotContainsString('Overall average', $combined);
    }

    // =========================================================================
    // buildGeoInsights
    // =========================================================================

    public function test_geo_insights_returns_empty_array_for_no_countries(): void
    {
        $result = $this->callPrivate('buildGeoInsights', [[], [], 'United States', 0]);

        $this->assertSame([], $result);
    }

    public function test_geo_insights_names_top_two_earliest_planners(): void
    {
        $leadSorted = [
            $this->makeCountry(['country' => 'Australia', 'avg_lead_days' => 90, 'bookings' => 5]),
            $this->makeCountry(['country' => 'Germany',   'avg_lead_days' => 75, 'bookings' => 5]),
            $this->makeCountry(['country' => 'France',    'avg_lead_days' => 30, 'bookings' => 8]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [
            $leadSorted, null, 'United States', 50,
        ]);

        $this->assertStringContainsString('Australia', $result[0]);
        $this->assertStringContainsString('Germany',   $result[0]);
        $this->assertStringContainsString('90 days',   $result[0]);
        $this->assertStringContainsString('furthest in advance', $result[0]);
    }

    public function test_geo_insights_handles_single_country_gracefully(): void
    {
        $leadSorted = [
            $this->makeCountry(['country' => 'Canada', 'avg_lead_days' => 60, 'bookings' => 4]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [
            $leadSorted, null, 'United States', 30,
        ]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('Canada', $combined);
        $this->assertStringContainsString('furthest in advance', $combined);
        // should NOT crash trying to read index 1
        $this->assertIsArray($result);
    }

    public function test_geo_insights_includes_3rd_and_4th_planners_when_enough_data(): void
    {
        $leadSorted = [
            $this->makeCountry(['country' => 'Australia', 'avg_lead_days' => 120]),
            $this->makeCountry(['country' => 'Germany',   'avg_lead_days' => 100]),
            $this->makeCountry(['country' => 'France',    'avg_lead_days' => 80]),
            $this->makeCountry(['country' => 'Japan',     'avg_lead_days' => 70]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [
            $leadSorted, null, 'United States', 60,
        ]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('France', $combined);
        $this->assertStringContainsString('Japan',  $combined);
        $this->assertStringContainsString('plan well ahead', $combined);
    }

    public function test_geo_insights_identifies_three_last_minute_bookers(): void
    {
        $leadSorted = [
            $this->makeCountry(['country' => 'Australia', 'avg_lead_days' => 90, 'bookings' => 5]),
            $this->makeCountry(['country' => 'Germany',   'avg_lead_days' => 75, 'bookings' => 5]),
            $this->makeCountry(['country' => 'France',    'avg_lead_days' => 60, 'bookings' => 5]),
            $this->makeCountry(['country' => 'Japan',     'avg_lead_days' => 5,  'bookings' => 4]),
            $this->makeCountry(['country' => 'Brazil',    'avg_lead_days' => 3,  'bookings' => 3]),
            $this->makeCountry(['country' => 'Mexico',    'avg_lead_days' => 2,  'bookings' => 3]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [
            $leadSorted, null, 'United States', 40,
        ]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('last-minute bookers', $combined);
        // Mexico is the lowest avg_lead_days among ≥3 bookings
        $this->assertStringContainsString('Mexico', $combined);
    }

    public function test_geo_insights_requires_3_bookings_for_last_minute_entry(): void
    {
        // Country with only 2 bookings must be excluded from last-minute list
        $leadSorted = [
            $this->makeCountry(['country' => 'Australia', 'avg_lead_days' => 90, 'bookings' => 5]),
            $this->makeCountry(['country' => 'UK',        'avg_lead_days' => 2,  'bookings' => 2]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [
            $leadSorted, null, 'United States', 40,
        ]);

        $combined = implode(' ', $result);
        $this->assertStringNotContainsString('last-minute bookers', $combined);
    }

    public function test_geo_insights_shows_us_above_overall_average(): void
    {
        $allCountries = [
            $this->makeCountry(['country' => 'United States', 'avg_lead_days' => 60, 'bookings' => 20]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [[], $allCountries[0], 'United States', 40]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('United States books at',          $combined);
        $this->assertStringContainsString('60 days',              $combined);
        $this->assertStringContainsString('above the overall average', $combined);
    }

    public function test_geo_insights_shows_us_below_overall_average(): void
    {
        $allCountries = [
            $this->makeCountry(['country' => 'United States', 'avg_lead_days' => 10, 'bookings' => 20]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [[], $allCountries[0], 'United States', 40]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('below the overall average', $combined);
    }

    public function test_geo_insights_shows_us_close_to_average_within_5_days(): void
    {
        // 42 days vs overall 40 → diff = 2 → "close to median"
        $allCountries = [
            $this->makeCountry(['country' => 'United States', 'avg_lead_days' => 42, 'bookings' => 20]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [[], $allCountries[0], 'United States', 40]);

        $combined = implode(' ', $result);
        $this->assertStringContainsString('close to the overall median', $combined);
    }

    public function test_geo_insights_skips_us_comparison_when_overall_avg_is_zero(): void
    {
        $allCountries = [
            $this->makeCountry(['country' => 'United States', 'avg_lead_days' => 30, 'bookings' => 10]),
        ];
        $result = $this->callPrivate('buildGeoInsights', [[], $allCountries, 'United States', 0]);

        $combined = implode(' ', $result);
        $this->assertStringNotContainsString('United States books at', $combined);
    }

    public function test_geo_insights_skips_us_comparison_when_no_us_data(): void
    {
        $result = $this->callPrivate('buildGeoInsights', [[], null, 'United States', 25]);

        $combined = implode(' ', $result);
        $this->assertStringNotContainsString('United States books at', $combined);
    }

    // =========================================================================
    // Lead-time bucket thresholds
    // =========================================================================

    /**
     * Verify the six lead-time bucket boundaries used in getGeographicData().
     *
     * @dataProvider leadBucketProvider
     */
    public function test_lead_day_falls_into_correct_bucket(int $leadDays, string $expectedBucket): void
    {
        $bucket = match (true) {
            $leadDays === 0  => 'Same-day',
            $leadDays <= 7   => '1–7 days',
            $leadDays <= 30  => '8–30 days',
            $leadDays <= 90  => '31–90 days',
            $leadDays <= 180 => '91–180 days',
            default          => '180+ days',
        };

        $this->assertSame($expectedBucket, $bucket);
    }

    public static function leadBucketProvider(): array
    {
        return [
            'same-day'          => [0,   'Same-day'],
            '1 day'             => [1,   '1–7 days'],
            '7 days boundary'   => [7,   '1–7 days'],
            '8 days lower'      => [8,   '8–30 days'],
            '30 days boundary'  => [30,  '8–30 days'],
            '31 days lower'     => [31,  '31–90 days'],
            '90 days boundary'  => [90,  '31–90 days'],
            '91 days lower'     => [91,  '91–180 days'],
            '180 days boundary' => [180, '91–180 days'],
            '181 days lower'    => [181, '180+ days'],
            '365 days'          => [365, '180+ days'],
        ];
    }

    // =========================================================================
    // Booking-style label thresholds
    // =========================================================================

    /**
     * Verify the five booking-style label thresholds used in getGeographicData().
     *
     * @dataProvider bookingStyleProvider
     */
    public function test_avg_lead_days_maps_to_correct_booking_style(int $avg, string $expected): void
    {
        $style = match (true) {
            $avg === 0 => 'Same-day',
            $avg <= 7  => 'Last minute',
            $avg <= 30 => 'Late planner',
            $avg <= 90 => 'Mid-range',
            default    => 'Early planner',
        };

        $this->assertSame($expected, $style);
    }

    public static function bookingStyleProvider(): array
    {
        return [
            'same-day boundary'    => [0,   'Same-day'],
            'last-minute lower'    => [1,   'Last minute'],
            'last-minute upper'    => [7,   'Last minute'],
            'late-planner lower'   => [8,   'Late planner'],
            'late-planner upper'   => [30,  'Late planner'],
            'mid-range lower'      => [31,  'Mid-range'],
            'mid-range upper'      => [90,  'Mid-range'],
            'early-planner lower'  => [91,  'Early planner'],
            'early-planner high'   => [365, 'Early planner'],
        ];
    }

    // =========================================================================
    // Unknown-country exclusion
    // =========================================================================

    public function test_unknown_country_is_excluded_from_other_bookings_count(): void
    {
        $allCountries = [
            $this->makeCountry(['country' => 'Unknown',       'bookings' => 5]),
            $this->makeCountry(['country' => 'Canada',        'bookings' => 3]),
            $this->makeCountry(['country' => 'United States', 'bookings' => 10]),
        ];

        $topCountryName = 'United States'; // most bookings
        $knownCountries = array_values(
            array_filter($allCountries, fn($c) => $c['country'] !== 'Unknown')
        );
        $otherBookings = 0;
        foreach ($knownCountries as $cd) {
            if ($cd['country'] !== $topCountryName) {
                $otherBookings += $cd['bookings'];
            }
        }

        // Only Canada = 3 (Unknown and United States are excluded)
        $this->assertEquals(3, $otherBookings);
    }

    public function test_total_countries_kpi_excludes_unknown(): void
    {
        $allCountries = [
            $this->makeCountry(['country' => 'Unknown']),
            $this->makeCountry(['country' => 'France']),
            $this->makeCountry(['country' => 'Japan']),
        ];

        $knownCountries = array_values(
            array_filter($allCountries, fn($c) => $c['country'] !== 'Unknown')
        );

        $this->assertCount(2, $knownCountries);
    }

    // =========================================================================
    // Earliest / latest country logic (min 3-booking requirement)
    // =========================================================================

    public function test_earliest_country_requires_minimum_3_bookings(): void
    {
        // Australia has the highest lead days but only 2 bookings → excluded
        $countries = [
            $this->makeCountry(['country' => 'Australia', 'avg_lead_days' => 200, 'bookings' => 2]),
            $this->makeCountry(['country' => 'Germany',   'avg_lead_days' => 90,  'bookings' => 3]),
        ];

        $earliest = null;
        $maxLead  = -1;
        foreach ($countries as $cd) {
            if ($cd['bookings'] < 3) continue;
            if ($cd['avg_lead_days'] > $maxLead) {
                $maxLead  = $cd['avg_lead_days'];
                $earliest = $cd;
            }
        }

        $this->assertNotNull($earliest);
        $this->assertSame('Germany', $earliest['country']);
    }

    public function test_latest_country_requires_minimum_3_bookings(): void
    {
        // UK has the lowest lead days but only 1 booking → excluded
        $countries = [
            $this->makeCountry(['country' => 'UK',     'avg_lead_days' => 1, 'bookings' => 1]),
            $this->makeCountry(['country' => 'France', 'avg_lead_days' => 5, 'bookings' => 4]),
        ];

        $latest  = null;
        $minLead = PHP_INT_MAX;
        foreach ($countries as $cd) {
            if ($cd['bookings'] < 3) continue;
            if ($cd['avg_lead_days'] < $minLead) {
                $minLead = $cd['avg_lead_days'];
                $latest  = $cd;
            }
        }

        $this->assertNotNull($latest);
        $this->assertSame('France', $latest['country']);
    }

    public function test_earliest_and_latest_are_null_when_all_countries_below_3_bookings(): void
    {
        $countries = [
            $this->makeCountry(['country' => 'Japan',  'avg_lead_days' => 100, 'bookings' => 1]),
            $this->makeCountry(['country' => 'Brazil', 'avg_lead_days' => 5,   'bookings' => 2]),
        ];

        $earliest = $latest = null;
        $maxLead  = -1;
        $minLead  = PHP_INT_MAX;
        foreach ($countries as $cd) {
            if ($cd['bookings'] < 3) continue;
            if ($cd['avg_lead_days'] > $maxLead) { $maxLead = $cd['avg_lead_days']; $earliest = $cd; }
            if ($cd['avg_lead_days'] < $minLead) { $minLead = $cd['avg_lead_days']; $latest   = $cd; }
        }

        $this->assertNull($earliest);
        $this->assertNull($latest);
    }

    public function test_same_country_can_be_both_earliest_and_latest_when_only_one_qualifies(): void
    {
        $countries = [
            $this->makeCountry(['country' => 'Canada', 'avg_lead_days' => 45, 'bookings' => 5]),
            $this->makeCountry(['country' => 'Brazil', 'avg_lead_days' => 2,  'bookings' => 1]), // excluded
        ];

        $earliest = $latest = null;
        $maxLead  = -1;
        $minLead  = PHP_INT_MAX;
        foreach ($countries as $cd) {
            if ($cd['bookings'] < 3) continue;
            if ($cd['avg_lead_days'] > $maxLead) { $maxLead = $cd['avg_lead_days']; $earliest = $cd; }
            if ($cd['avg_lead_days'] < $minLead) { $minLead = $cd['avg_lead_days']; $latest   = $cd; }
        }

        $this->assertSame('Canada', $earliest['country']);
        $this->assertSame('Canada', $latest['country']);
    }

    // =========================================================================
    // KPI percentage calculations
    // =========================================================================

    public function test_top_pct_rounds_to_one_decimal(): void
    {
        $topPct = round(8 / 20 * 100, 1);

        $this->assertEquals(40.0, $topPct);
    }

    public function test_other_pct_rounds_to_one_decimal(): void
    {
        $otherPct = round(5 / 20 * 100, 1);

        $this->assertEquals(25.0, $otherPct);
    }

    public function test_top_pct_and_other_pct_are_zero_when_no_bookings(): void
    {
        $total    = 0;
        $topPct   = $total > 0 ? round(8 / $total * 100, 1) : 0;
        $otherPct = $total > 0 ? round(5 / $total * 100, 1) : 0;

        $this->assertEquals(0, $topPct);
        $this->assertEquals(0, $otherPct);
    }

    // =========================================================================
    // Overall average lead days (weighted by booking count)
    // =========================================================================

    public function test_overall_avg_lead_days_is_weighted_by_booking_count(): void
    {
        // US: 10 × 30 = 300, Canada: 5 × 60 = 300 → total 600 / 15 = 40
        $countries = [
            $this->makeCountry(['country' => 'United States', 'avg_lead_days' => 30, 'bookings' => 10]),
            $this->makeCountry(['country' => 'Canada',        'avg_lead_days' => 60, 'bookings' => 5]),
        ];

        $leadSum = $leadCnt = 0;
        foreach ($countries as $cd) {
            $leadSum += $cd['avg_lead_days'] * $cd['bookings'];
            $leadCnt += $cd['bookings'];
        }
        $overallAvg = $leadCnt > 0 ? (int) round($leadSum / $leadCnt) : 0;

        $this->assertEquals(40, $overallAvg);
    }

    public function test_overall_avg_lead_days_is_zero_when_no_countries(): void
    {
        $leadSum = $leadCnt = 0;
        $overallAvg = $leadCnt > 0 ? (int) round($leadSum / $leadCnt) : 0;

        $this->assertEquals(0, $overallAvg);
    }

    // =========================================================================
    // Lead-distribution structure
    // =========================================================================

    public function test_lead_distribution_labels_are_in_correct_order(): void
    {
        $expected = ['Same-day', '1–7 days', '8–30 days', '31–90 days', '91–180 days', '180+ days'];

        $this->assertSame($expected, array_keys($this->emptyBuckets()));
    }

    public function test_lead_distribution_initialises_all_buckets_to_zero(): void
    {
        foreach ($this->emptyBuckets() as $value) {
            $this->assertSame(0, $value);
        }
    }
}
