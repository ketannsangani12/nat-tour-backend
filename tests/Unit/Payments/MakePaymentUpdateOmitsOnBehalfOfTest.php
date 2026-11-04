<?php

namespace Tests\Unit\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Utils\StripeUtils;
use Tests\TestCase;

/**
 * Regression: a Make Payment "reuse the existing unconfirmed PaymentIntent" refresh (triggered
 * whenever the booking's total/fee changed since the PI was first created — e.g. applying a
 * discount) throws a real Stripe 500: "Received unknown parameter: on_behalf_of", and once that
 * was stripped, "Received unknown parameter: transfer_data[destination]" (production, tenant
 * kens, 2026-10-16). Stripe's PaymentIntent UPDATE endpoint accepts neither — both are fixed at
 * CREATE time (update only takes transfer_data[amount]) — unlike application_fee_amount and
 * statement_descriptor(_suffix), which remain updatable pre-confirmation.
 * buildConnectedPaymentData() is shared with every CREATE call site, where both are valid, so
 * StripeUtils::updateStripePaymentIntentMakePayment() must strip them before spreading that
 * result into an updatePayment() call.
 */
class MakePaymentUpdateOmitsOnBehalfOfTest extends TestCase
{
    private function fakeGatewayCapturing(&$capturedParams): PaymentGatewayInterface
    {
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        // Simulates buildConnectedPaymentData()'s real output for a connected account that DOES
        // have card_payments active — the exact case that includes on_behalf_of.
        $fakeGateway->method('buildConnectedPaymentData')
            ->willReturn([
                'application_fee_amount' => 500,
                'transfer_data' => ['destination' => 'acct_test123'],
                'on_behalf_of' => 'acct_test123',
                'statement_descriptor_suffix' => 'TEST TOURS',
            ]);
        $fakeGateway->method('updatePayment')
            ->willReturnCallback(function (string $id, array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return (object) ['id' => $id, 'client_secret' => 'secret_test'];
            });
        return $fakeGateway;
    }

    public function test_on_behalf_of_is_stripped_before_updating_an_existing_payment_intent(): void
    {
        $capturedParams = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedParams));

        $tourBooking = (object) [
            'package_name' => 'General Tour',
            'Date' => '2026-06-15',
            'StartTime' => '09:00:00',
            'tourists' => 2,
            'infants' => 0,
            'Email' => 'guest@example.com',
            'Name' => 'Test Guest',
            'ContactNumber' => '5551234567',
            'is_affiliate' => 'No',
            'statement_descriptor' => 'TEST TOURS',
            'tour_operator' => 'Test Tours',
            'stripe_destination_id' => 'acct_test123',
        ];
        $request = (object) [
            'bookingID' => 99999,
            'split_value' => null,
        ];

        StripeUtils::updateStripePaymentIntentMakePayment('pi_test_existing', $tourBooking, $request, 100.00, 5.00);

        $this->assertNotNull($capturedParams, 'updatePayment() must have been called');
        $this->assertArrayNotHasKey(
            'on_behalf_of',
            $capturedParams,
            'on_behalf_of is create-only — Stripe rejects it on PaymentIntent update with "Received unknown parameter: on_behalf_of".'
        );
        $this->assertArrayNotHasKey(
            'transfer_data',
            $capturedParams,
            'transfer_data[destination] is create-only — Stripe rejects it on PaymentIntent update with "Received unknown parameter: transfer_data[destination]".'
        );
        // The updatable parts of buildConnectedPaymentData()'s result must survive untouched.
        $this->assertSame(500, $capturedParams['application_fee_amount']);
        $this->assertSame('TEST TOURS', $capturedParams['statement_descriptor_suffix']);
    }
}
