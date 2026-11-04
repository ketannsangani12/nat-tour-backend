<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\PlatformFeeMetadata;
use App\Services\Payments\StripePaymentGateway;
use Tests\TestCase;

/**
 * Real Stripe test-mode round trip (skipped with any non-test key): the private fee key never reaches
 * Stripe (Stripe rejects unknown parameters), the fee lands in the PaymentIntent's metadata next to
 * the caller's own metadata, and completing the payment reads it back. Destination charges are
 * unchanged.
 *
 * @group stripe-sandbox
 */
class PlatformFeeMetadataStripeTest extends TestCase
{
	private StripePaymentGateway $gateway;

	protected function setUp(): void
	{
		parent::setUp();

		if (!str_starts_with((string) getStripeSecretKey(), 'sk_test_')) {
			$this->markTestSkipped('Needs a Stripe test-mode key.');
		}
		$this->gateway = new StripePaymentGateway();
	}

	public function test_platform_held_payment_create_update_and_read_back(): void
	{
		$paymentIntent = $this->gateway->createPayment([
			'amount' => 10000,
			'currency' => 'usd',
			'payment_method' => 'pm_card_visa',
			'payment_method_types' => ['card'],
			'confirm' => true,
			'off_session' => true,
			'metadata' => ['booking_id' => 'test-ptf', 'tenant_id' => 'dixies'],
			...$this->gateway->buildConnectedPaymentData('', 829, null, PaymentModeResolver::PLATFORM_HELD),
		]);

		$this->assertSame('succeeded', $paymentIntent->status);
		$this->assertSame('829', $paymentIntent->metadata['platform_fee_cents']);
		$this->assertSame('test-ptf', $paymentIntent->metadata['booking_id']);
		$this->assertSame(8.29, PlatformFeeMetadata::feeFor($this->gateway->retrievePayment($paymentIntent->id), 0));

		$updated = $this->gateway->updatePayment($paymentIntent->id, [
			'description' => 'updated',
			...$this->gateway->buildConnectedPaymentData('', 900, null, PaymentModeResolver::PLATFORM_HELD),
		]);
		$this->assertSame('test-ptf', $updated->metadata['booking_id'], 'Update merges, keeps existing metadata');
	}

	public function test_checkout_session_with_platform_held_data_is_accepted(): void
	{
		$session = $this->gateway->createCheckoutSession([
			'mode' => 'payment',
			'success_url' => 'https://example.com/ok',
			'cancel_url' => 'https://example.com/cancel',
			'line_items' => [['price_data' => ['currency' => 'usd', 'unit_amount' => 5000, 'product_data' => ['name' => 'Test']], 'quantity' => 1]],
			'payment_intent_data' => [
				'metadata' => ['booking_id' => 'test-ptf'],
				...$this->gateway->buildConnectedPaymentData('', 410, null, PaymentModeResolver::PLATFORM_HELD),
			],
		]);

		$this->assertStringStartsWith('cs_test_', $session->id);
		$this->gateway->expireCheckoutSession($session->id);
	}

	public function test_destination_charge_is_unchanged(): void
	{
		$account = (string) tourOperator()->stripe_destination_id;

		$data = $this->gateway->buildConnectedPaymentData($account, 829, null, PaymentModeResolver::DESTINATION);
		$this->assertArrayNotHasKey(PlatformFeeMetadata::MARKER, $data);

		$paymentIntent = $this->gateway->createPayment([
			'amount' => 10000,
			'currency' => 'usd',
			'payment_method' => 'pm_card_visa',
			'payment_method_types' => ['card'],
			'confirm' => true,
			'off_session' => true,
			...$data,
		]);

		$this->assertSame(829, $paymentIntent->application_fee_amount);
		$this->assertArrayNotHasKey('platform_fee_cents', $paymentIntent->metadata->toArray());
		$this->assertSame(8.29, PlatformFeeMetadata::feeFor($paymentIntent, $paymentIntent->application_fee_amount / 100));
	}
}
