<?php

namespace Tests\Unit\Dashboard;

use App\Services\Dashboard\DashboardCalculatorService;
use Tests\TestCase;

/**
 * Tests for the "All Sources" dropdown filter on the dashboard.
 *
 * The dropdown has four options (Backend, Frontend, Headout, TripAdvisor) which
 * map to `tra.request_from` values (BACKEND, FRONTEND, HEADOUT, TRIPADVISOR).
 * The frontend sends these as `requestFrom[]` in the AJAX payload; the controller
 * passes them straight to getDashboardRows() as $requestFromValues, where they
 * become a `->whereIn('tra.request_from', $requestFromValues)` clause.
 *
 * Because the whereIn logic lives inside getDashboardRows() (which needs a real DB),
 * these unit tests focus on the surrounding contract:
 *   • resolveViaFilter() — the payment-method/platform merge that runs alongside
 *     the sources filter on every request.
 *   • The known valid source values used by the dashboard.
 *   • Edge cases: no selection, full selection, single-source selection.
 */
class AllSourcesFilterTest extends TestCase
{
    // =========================================================================
    // Constants — known valid source values
    // =========================================================================

    /** The four requestFrom values the dashboard UI exposes. */
    private const VALID_SOURCES = ['BACKEND', 'FRONTEND', 'HEADOUT', 'TRIPADVISOR'];

    /** Default paymentTypes sent when all payment checkboxes are ticked. */
    private const DEFAULT_PAYMENT_TYPES = ['Cash', 'Card', 'Check/Wire', 'Invoiced'];

    /** Default thirdPartyPlatforms sent when all platform checkboxes are ticked. */
    private const DEFAULT_THIRD_PARTY_PLATFORMS = ['TripAdvisor', 'Headout', 'FareHarbor'];

    // =========================================================================
    // Valid source values
    // =========================================================================

    public function test_all_four_source_values_are_known(): void
    {
        $this->assertCount(4, self::VALID_SOURCES);
        $this->assertContains('BACKEND',     self::VALID_SOURCES);
        $this->assertContains('FRONTEND',    self::VALID_SOURCES);
        $this->assertContains('HEADOUT',     self::VALID_SOURCES);
        $this->assertContains('TRIPADVISOR', self::VALID_SOURCES);
    }

    public function test_source_values_are_uppercase_strings(): void
    {
        foreach (self::VALID_SOURCES as $source) {
            $this->assertSame($source, strtoupper($source), "Source '$source' should be uppercase");
        }
    }

    // =========================================================================
    // resolveViaFilter — with actual dashboard default values
    // =========================================================================

    public function test_resolve_via_filter_with_all_default_payment_types_and_platforms(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(
            self::DEFAULT_PAYMENT_TYPES,
            self::DEFAULT_THIRD_PARTY_PLATFORMS
        );

        // All payment types should appear
        $this->assertContains('Cash',       $result);
        $this->assertContains('Card',       $result);
        $this->assertContains('Check/Wire', $result);
        $this->assertContains('Invoiced',   $result);

        // All third-party platforms should appear
        $this->assertContains('TripAdvisor', $result);
        $this->assertContains('Headout',     $result);
        $this->assertContains('FareHarbor',  $result);

        $this->assertCount(7, $result);
    }

    public function test_resolve_via_filter_with_default_payment_types_only(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(self::DEFAULT_PAYMENT_TYPES, []);

        $this->assertEquals(self::DEFAULT_PAYMENT_TYPES, $result);
    }

    public function test_resolve_via_filter_with_default_third_party_platforms_only(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter([], self::DEFAULT_THIRD_PARTY_PLATFORMS);

        $this->assertEquals(self::DEFAULT_THIRD_PARTY_PLATFORMS, $result);
    }

    // =========================================================================
    // All sources selected — "All Sources" label state
    // =========================================================================

    public function test_all_sources_selected_returns_full_source_array(): void
    {
        $selected = self::VALID_SOURCES;

        $this->assertCount(4, $selected);
        $this->assertSame(count(self::VALID_SOURCES), count($selected));
    }

    public function test_all_sources_selected_with_full_via_filter_is_non_empty(): void
    {
        // When all sources are selected alongside all payment methods,
        // resolveViaFilter must be non-empty (triggers whereIn, not whereNotIn Bokun)
        $viaFilter = DashboardCalculatorService::resolveViaFilter(
            self::DEFAULT_PAYMENT_TYPES,
            self::DEFAULT_THIRD_PARTY_PLATFORMS
        );

        $this->assertNotEmpty($viaFilter);
    }

    // =========================================================================
    // No sources selected — "No Sources" label state
    // =========================================================================

    public function test_no_sources_selected_produces_empty_request_from_array(): void
    {
        $selected = [];

        // An empty requestFrom passed to getDashboardRows becomes whereIn([], [])
        // which Laravel resolves to WHERE 1=0 — no rows returned.
        $this->assertEmpty($selected);
    }

    public function test_no_sources_with_empty_via_filter_both_empty(): void
    {
        $requestFrom = [];
        $viaFilter   = DashboardCalculatorService::resolveViaFilter([], []);

        $this->assertEmpty($requestFrom);
        $this->assertEmpty($viaFilter);
    }

    // =========================================================================
    // Single-source selections
    // =========================================================================

    public function test_backend_only_selection(): void
    {
        $selected = ['BACKEND'];

        $this->assertContains('BACKEND', $selected);
        $this->assertNotContains('FRONTEND',    $selected);
        $this->assertNotContains('HEADOUT',     $selected);
        $this->assertNotContains('TRIPADVISOR', $selected);
    }

    public function test_frontend_only_selection(): void
    {
        $selected = ['FRONTEND'];

        $this->assertContains('FRONTEND', $selected);
        $this->assertNotContains('BACKEND',     $selected);
        $this->assertNotContains('HEADOUT',     $selected);
        $this->assertNotContains('TRIPADVISOR', $selected);
    }

    public function test_headout_only_selection(): void
    {
        $selected = ['HEADOUT'];

        $this->assertContains('HEADOUT', $selected);
        $this->assertNotContains('BACKEND',     $selected);
        $this->assertNotContains('FRONTEND',    $selected);
        $this->assertNotContains('TRIPADVISOR', $selected);
    }

    public function test_tripadvisor_only_selection(): void
    {
        $selected = ['TRIPADVISOR'];

        $this->assertContains('TRIPADVISOR', $selected);
        $this->assertNotContains('BACKEND',  $selected);
        $this->assertNotContains('FRONTEND', $selected);
        $this->assertNotContains('HEADOUT',  $selected);
    }

    // =========================================================================
    // Partial-source selections
    // =========================================================================

    public function test_backend_and_frontend_selection_excludes_third_party(): void
    {
        $selected = ['BACKEND', 'FRONTEND'];

        $this->assertCount(2, $selected);
        $this->assertNotContains('HEADOUT',     $selected);
        $this->assertNotContains('TRIPADVISOR', $selected);
    }

    public function test_headout_and_tripadvisor_selection_excludes_direct(): void
    {
        $selected = ['HEADOUT', 'TRIPADVISOR'];

        $this->assertCount(2, $selected);
        $this->assertNotContains('BACKEND',  $selected);
        $this->assertNotContains('FRONTEND', $selected);
    }

    public function test_three_sources_selected_is_not_all_sources(): void
    {
        $selected = ['BACKEND', 'FRONTEND', 'HEADOUT'];
        $total    = count(self::VALID_SOURCES);

        $this->assertCount(3, $selected);
        $this->assertNotSame($total, count($selected), 'Three sources should not equal "All Sources"');
    }

    // =========================================================================
    // resolveViaFilter — edge cases alongside source selection
    // =========================================================================

    public function test_card_only_via_filter_resolves_correctly(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(['Card'], []);

        $this->assertEquals(['Card'], $result);
        $this->assertNotEmpty($result);
    }

    public function test_cash_only_via_filter_resolves_correctly(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(['Cash'], []);

        $this->assertEquals(['Cash'], $result);
    }

    public function test_tripadvisor_platform_via_filter_resolves_correctly(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter([], ['TripAdvisor']);

        $this->assertEquals(['TripAdvisor'], $result);
    }

    public function test_headout_platform_via_filter_resolves_correctly(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter([], ['Headout']);

        $this->assertEquals(['Headout'], $result);
    }

    public function test_fareharbor_platform_via_filter_resolves_correctly(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter([], ['FareHarbor']);

        $this->assertEquals(['FareHarbor'], $result);
    }

    public function test_card_and_tripadvisor_combined_via_filter(): void
    {
        $result = DashboardCalculatorService::resolveViaFilter(['Card'], ['TripAdvisor']);

        $this->assertEquals(['Card', 'TripAdvisor'], $result);
        $this->assertCount(2, $result);
    }

    public function test_empty_via_filter_signals_bokun_exclusion_mode(): void
    {
        // Empty result from resolveViaFilter means the caller should use
        // whereNotIn('via', ['Bokun']) instead of whereIn
        $result = DashboardCalculatorService::resolveViaFilter([], []);

        $this->assertEmpty($result, 'Empty via filter should trigger Bokun-exclusion mode');
    }

    public function test_via_filter_preserves_insertion_order(): void
    {
        $paymentTypes = ['Cash', 'Card', 'Invoiced'];
        $platforms    = ['TripAdvisor', 'Headout'];

        $result = DashboardCalculatorService::resolveViaFilter($paymentTypes, $platforms);

        // paymentTypes come first, then platforms
        $this->assertSame('Cash',        $result[0]);
        $this->assertSame('Card',        $result[1]);
        $this->assertSame('Invoiced',    $result[2]);
        $this->assertSame('TripAdvisor', $result[3]);
        $this->assertSame('Headout',     $result[4]);
    }
}
