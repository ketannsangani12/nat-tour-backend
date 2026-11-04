<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\StripePaymentGateway;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Real Stripe (TEST MODE ONLY) check of what a guest's card statement would show for a charge made
 * on the platform account with no on_behalf_of — the platform-held shape for an operator whose
 * connected account lacks card_payments.
 *
 * Why it exists: Stripe's docs say a full statement_descriptor is for non-card payments only, but in
 * practice (verified 2026-10-06, online card and Terminal) it is accepted for card charges and
 * replaces the whole statement line, which is the only way to show the operator's name instead of
 * "<PLATFORM>* <OPERATOR>" without card_payments. The first assertion documents the problem Stripe
 * has; the second proves the gateway's fix. If Stripe ever changes this behaviour, this test fails
 * loudly instead of the guest statement silently changing.
 *
 * Skipped unless the configured key is a sk_test_ key. Creates small test-mode charges only; no
 * database access (tenancy off), so nothing to clean up locally.
 */
class PlatformHeldStatementDescriptorStripeTest extends TestCase
{
    protected $tenancy = false;

    private StripeClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_starts_with(getStripeSecretKey(), 'sk_test_')) {
            $this->markTestSkipped('Stripe TEST key required; never run this against a live key.');
        }

        $this->stripe = initStripeClient();
    }

    private function chargeStatement(array $extra, bool $viaGateway): string
    {
        $params = array_merge([
            'amount' => 100,
            'currency' => 'usd',
            'payment_method' => 'pm_card_visa',
            'payment_method_types' => ['card'],
            'confirm' => true,
            'off_session' => true,
            'metadata' => ['purpose' => 'platform-held-statement-descriptor-test'],
        ], $extra);

        $intent = $viaGateway
            ? (new StripePaymentGateway())->createPayment($params)
            : $this->stripe->paymentIntents->create($params);

        $this->assertSame('succeeded', $intent->status);

        $chargeId = $intent->latest_charge ?? $intent->charges->data[0]->id;

        return $this->stripe->charges->retrieve($chargeId)->calculated_statement_descriptor;
    }

    public function test_a_suffix_alone_shows_the_platform_name_but_the_gateway_shows_only_the_operator(): void
    {
        // Short enough that Stripe's 22-character limit doesn't truncate the platform-prefixed line.
        $operator = 'NAT OPERATOR';

        $raw = $this->chargeStatement(['statement_descriptor_suffix' => $operator], viaGateway: false);
        $this->assertStringContainsString('* ' . $operator, $raw, 'Stripe now behaves differently for a bare suffix; revisit PlatformChargeStatementDescriptor.');
        $this->assertNotSame($operator, $raw);

        $fixed = $this->chargeStatement(['statement_descriptor_suffix' => $operator], viaGateway: true);
        $this->assertSame($operator, $fixed);
    }
}
