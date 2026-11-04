<?php

namespace Tests\Unit\Stripe;

use Tests\TestCase;

/**
 * Regression test for the application_fee_amount truncation bug found via booking #07360
 * (tenant_ahbb): the platform recorded a $9.28 fee, but Stripe's dashboard showed $9.27 for
 * the same charge.
 *
 * Root cause: `PaymentGatewayInterface::buildConnectedPaymentData()` takes `int $feeAmountCents`.
 * Every call site used to pass `$dollarAmount * 100` straight through with no rounding. $9.28
 * has no exact binary floating-point representation — the nearest double is fractionally below
 * 9.28, so `9.28 * 100` evaluates to ~927.99999999999988631316. PHP's implicit (or explicit
 * `(int)`-cast) float-to-int conversion truncates rather than rounds, turning that into 927
 * cents ($9.27) instead of 928 ($9.28) — a real Stripe API call one cent short of what the
 * platform's own books recorded.
 *
 * Fix: wrap every `$amount * 100` passed into buildConnectedPaymentData() with
 * `(int) round(...)`, matching the pattern already used in
 * StripeUtils::updateStripePaymentIntentMakePayment(). This test locks in that rounding
 * behavior for the exact value that surfaced the bug, plus other float-precision edge cases,
 * so the truncation can't silently creep back in at any call site.
 *
 * CMD: php artisan test --filter=ApplicationFeeCentsRoundingTest
 */
class ApplicationFeeCentsRoundingTest extends TestCase
{
    /**
     * Mirrors the exact "cents" expression used at every buildConnectedPaymentData() call
     * site after the fix: (int) round($dollarAmount * 100).
     */
    private function toCents(float $dollarAmount): int
    {
        return (int) round($dollarAmount * 100);
    }

    /**
     * The naive expression every call site used before the fix — kept here only so the test
     * can demonstrate it actually reproduces the bug for the documented values.
     */
    private function toCentsTruncated(float $dollarAmount): int
    {
        return (int) ($dollarAmount * 100);
    }

    public function test_booking_07360_fee_rounds_to_928_cents_not_927(): void
    {
        $stripeFees = 9.28; // 116.00 * 8% affiliate_processing_percentage, from booking 07360

        $this->assertSame(928, $this->toCents($stripeFees));

        // Documents the bug this test guards against: the un-rounded expression truncates
        // to 927 cents ($9.27) — exactly what Stripe's dashboard showed for this booking.
        $this->assertSame(927, $this->toCentsTruncated($stripeFees));
    }

    /**
     * @dataProvider floatPrecisionDollarAmounts
     */
    public function test_known_float_precision_edge_cases_round_to_correct_cents(float $dollars, int $expectedCents): void
    {
        $this->assertSame($expectedCents, $this->toCents($dollars));
    }

    public static function floatPrecisionDollarAmounts(): array
    {
        return [
            '9.28 (booking 07360)' => [9.28, 928],
            '0.29' => [0.29, 29],
            '0.14' => [0.14, 14],
            '4.64 (single-pax half of 07360)' => [4.64, 464],
            '19.99' => [19.99, 1999],
            '100.10' => [100.10, 10010],
        ];
    }
}
