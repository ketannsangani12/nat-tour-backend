<?php

namespace Tests\Unit\Payments;

use App\Models\Tenant;
use App\Models\TourOperator;
use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\StripePaymentGateway;
use Tests\TestCase;

/**
 * Covers buildConnectedPaymentData()'s PLATFORM_HELD branch and buildRefundParams(). Both
 * PLATFORM_HELD and DESTINATION now call connectedAccountHasCardPayments(), which hits the real
 * Stripe Accounts API — these tests run against real connected accounts on real tenants
 * (dixies = US, has card_payments; aswta = CA, transfers-only) rather than mocking it, matching
 * how this account's actual capability is what determines the correct Stripe param shape.
 *
 * Regression (2026-09-19): on_behalf_of alone (no transfer_data) was believed to work regardless
 * of the connected account's card_payments capability, based on an earlier verification that
 * never actually completed a charge (it only created/confirmed an unconfirmed or 3DS-stalled
 * PaymentIntent). Actually completing a charge (off_session + confirm) against a real Canadian
 * test account with only `transfers` active gets a hard rejection from Stripe: "You cannot create
 * a charge with the `on_behalf_of` parameter set to a connected account with `transfers` but
 * without the `card_payments` capability enabled." So PLATFORM_HELD now gates on_behalf_of on the
 * same capability check DESTINATION already used, and falls back to a full `statement_descriptor`
 * override (a property of the charging/platform account, not the connected account, so it needs
 * no capability) for accounts that lack it — confirmed empirically to still render as the
 * operator's own name via Charge::calculated_statement_descriptor, not the platform's.
 */
class StripePaymentGatewayModeTest extends TestCase
{
    private StripePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new StripePaymentGateway();
    }

    private function switchTenant(string $name): void
    {
        tenancy()->end();
        tenancy()->initialize($name);
    }

    private function realDestinationId(): string
    {
        return TourOperator::first()->stripe_destination_id;
    }

    public function test_platform_held_omits_transfer_data_and_application_fee(): void
    {
        // dixies (US, has card_payments) so the capability check this now runs doesn't throw.
        $data = $this->gateway->buildConnectedPaymentData($this->realDestinationId(), 500, 'MY TOUR CO', PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey('transfer_data', $data);
        $this->assertArrayNotHasKey('application_fee_amount', $data);
    }

    public function test_platform_held_uses_on_behalf_of_when_account_has_card_payments(): void
    {
        // dixies' real connected account has card_payments active.
        $data = $this->gateway->buildConnectedPaymentData($this->realDestinationId(), 500, 'MY TOUR CO', PaymentModeResolver::PLATFORM_HELD);

        $this->assertSame($this->realDestinationId(), $data['on_behalf_of'] ?? null);
        $this->assertSame('MY TOUR CO', $data['statement_descriptor_suffix'] ?? null);
        $this->assertArrayNotHasKey('statement_descriptor', $data);
    }

    public function test_platform_held_falls_back_to_full_statement_descriptor_when_account_lacks_card_payments(): void
    {
        // 'aswta' is a real Canadian, transfers-only tenant that exists in the shared dev DB but
        // is never created by the isolated CI DB (.docker-cicd/run-tests.sh only creates 'dixies').
        // Skip rather than fail when it's absent -- same pattern as EmailFromAddressResolutionTest.
        if (!Tenant::find('aswta')) {
            $this->markTestSkipped("Tenant 'aswta' is not available in this environment.");
        }

        $this->switchTenant('aswta');
        $destinationId = $this->realDestinationId();

        $data = $this->gateway->buildConnectedPaymentData($destinationId, 500, 'MY TOUR CO', PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey(
            'on_behalf_of',
            $data,
            'on_behalf_of must not be sent for an account lacking card_payments -- Stripe rejects the charge outright when it actually completes'
        );
        $this->assertSame('MY TOUR CO', $data['statement_descriptor'] ?? null);
        $this->assertArrayNotHasKey('statement_descriptor_suffix', $data);
    }

    public function test_platform_held_omits_statement_descriptor_when_null(): void
    {
        $data = $this->gateway->buildConnectedPaymentData($this->realDestinationId(), 500, null, PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey('statement_descriptor_suffix', $data);
        $this->assertArrayNotHasKey('statement_descriptor', $data);
    }

    public function test_platform_held_omits_on_behalf_of_when_destination_id_is_empty(): void
    {
        $data = $this->gateway->buildConnectedPaymentData('', 500, 'MY TOUR CO', PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey('on_behalf_of', $data);
        // No connected account to check capability against -- falls back to the same full
        // statement_descriptor override used for an account that lacks card_payments, not the
        // suffix (which only makes sense layered on top of on_behalf_of).
        $this->assertSame('MY TOUR CO', $data['statement_descriptor'] ?? null);
        $this->assertArrayNotHasKey('statement_descriptor_suffix', $data);
    }

    public function test_refund_params_strip_reverse_transfer_and_refund_application_fee_for_platform_held(): void
    {
        $params = [
            'payment_intent' => 'pi_test123',
            'amount' => 5000,
            'reverse_transfer' => true,
            'refund_application_fee' => true,
        ];

        $result = $this->gateway->buildRefundParams($params, PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey('reverse_transfer', $result);
        $this->assertArrayNotHasKey('refund_application_fee', $result);
        // Everything else survives untouched.
        $this->assertSame('pi_test123', $result['payment_intent']);
        $this->assertSame(5000, $result['amount']);
    }

    public function test_refund_params_leave_destination_mode_params_untouched(): void
    {
        $params = [
            'payment_intent' => 'pi_test123',
            'amount' => 5000,
            'reverse_transfer' => true,
            'refund_application_fee' => true,
        ];

        $result = $this->gateway->buildRefundParams($params, PaymentModeResolver::DESTINATION);

        $this->assertTrue($result['reverse_transfer']);
        $this->assertTrue($result['refund_application_fee']);
    }

    public function test_refund_params_never_adds_keys_the_caller_did_not_include(): void
    {
        // A destination-mode refund that deliberately omitted refund_application_fee (e.g. a
        // partial, non-full refund) must not have it added back for either mode.
        $params = [
            'payment_intent' => 'pi_test123',
            'amount' => 2500,
            'reverse_transfer' => true,
        ];

        $destinationResult = $this->gateway->buildRefundParams($params, PaymentModeResolver::DESTINATION);
        $platformHeldResult = $this->gateway->buildRefundParams($params, PaymentModeResolver::PLATFORM_HELD);

        $this->assertArrayNotHasKey('refund_application_fee', $destinationResult);
        $this->assertArrayNotHasKey('refund_application_fee', $platformHeldResult);
    }
}
