<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\PaymentModeResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PaymentModeResolver is the single place the 2027-01-01 Payout Simplification boundary is
 * expressed in code. These tests read/write the tenant-scoped `PLATFORM_HELD_PAYOUT_ENABLED`
 * settings row (never touches any other table), and always restore it to its safe default
 * (0/disabled) in tearDown — the same state a fresh migration leaves every tenant in.
 */
class PaymentModeResolverTest extends TestCase
{
    private PaymentModeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PaymentModeResolver();
    }

    protected function tearDown(): void
    {
        DB::table('settings')->where('key', PaymentModeResolver::KILL_SWITCH_SETTING_KEY)->update(['value' => 0]);
        parent::tearDown();
    }

    private function setKillSwitch(bool $enabled): void
    {
        DB::table('settings')->where('key', PaymentModeResolver::KILL_SWITCH_SETTING_KEY)->update(['value' => $enabled ? 1 : 0]);
    }

    public function test_fails_closed_to_destination_when_kill_switch_disabled(): void
    {
        $this->setKillSwitch(false);

        $this->assertSame(
            PaymentModeResolver::DESTINATION,
            $this->resolver->resolveForSingleDate('2027-06-15'),
            'A 2027+ date must still resolve to DESTINATION when the kill switch is off — the whole point of failing closed.'
        );
    }

    public function test_resolves_destination_for_a_pre_boundary_date_when_enabled(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(PaymentModeResolver::DESTINATION, $this->resolver->resolveForSingleDate('2026-12-31'));
    }

    public function test_resolves_platform_held_for_the_exact_boundary_date_when_enabled(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(PaymentModeResolver::PLATFORM_HELD, $this->resolver->resolveForSingleDate('2027-01-01'));
    }

    public function test_resolves_platform_held_for_a_post_boundary_date_when_enabled(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(PaymentModeResolver::PLATFORM_HELD, $this->resolver->resolveForSingleDate('2027-06-15'));
    }

    public function test_mixed_cart_follows_the_later_date(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(
            PaymentModeResolver::PLATFORM_HELD,
            $this->resolver->resolve(['2026-05-01', '2027-01-01', '2026-11-30']),
            'Any single 2027+ date in the cart must make the WHOLE charge platform-held.'
        );
    }

    public function test_all_pre_boundary_dates_stay_destination(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(
            PaymentModeResolver::DESTINATION,
            $this->resolver->resolve(['2026-05-01', '2026-11-30', '2026-12-31'])
        );
    }

    public function test_null_and_empty_dates_are_skipped_not_treated_as_a_match(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(
            PaymentModeResolver::DESTINATION,
            $this->resolver->resolve([null, '', '2026-11-30']),
            'A booking with no resolvable date must not accidentally trip platform-held.'
        );
    }

    public function test_accepts_a_datetime_string_with_time_component(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(
            PaymentModeResolver::PLATFORM_HELD,
            $this->resolver->resolveForSingleDate('2027-01-01 00:00:00')
        );
    }

    public function test_accepts_a_carbon_instance(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(
            PaymentModeResolver::PLATFORM_HELD,
            $this->resolver->resolveForSingleDate(\Carbon\Carbon::parse('2027-03-01'))
        );
    }

    /**
     * Regression: a client-side "Invalid date" placeholder (moment.js's own literal string for an
     * unparseable/empty date input) must fail closed to DESTINATION like null/empty, not get
     * silently miscategorized as PLATFORM_HELD. Before the fix, normalizeDate() blindly took the
     * first 10 characters of ANY non-empty string ("Invalid da") and compared it lexically against
     * '2027-01-01' — and 'I' > '2' as plain characters, so garbage always "won" the comparison.
     */
    public function test_garbage_date_string_is_treated_as_unresolvable_not_platform_held(): void
    {
        $this->setKillSwitch(true);

        $this->assertSame(
            PaymentModeResolver::DESTINATION,
            $this->resolver->resolve(['Invalid date']),
            'A garbage date string must fail closed to DESTINATION, not lexically outrank a real 2027-01-01 boundary.'
        );

        $this->assertSame(
            PaymentModeResolver::PLATFORM_HELD,
            $this->resolver->resolve(['Invalid date', '2027-01-01']),
            'A garbage entry must not mask a REAL 2027+ date elsewhere in the same cart.'
        );
    }
}
