<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\PlatformFeeMetadata;
use App\Services\Payments\StripePaymentGateway;
use Stripe\PaymentIntent;
use Tests\TestCase;

/**
 * NAT's fee on platform-held (2027+) PaymentIntents: recorded when the PaymentIntent is built, read
 * back when the payment is completed. No Stripe calls (the real round trip, including "destination
 * charges are unchanged", is in PlatformFeeMetadataStripeTest).
 */
class PlatformFeeMetadataTest extends TestCase
{
	protected $tenancy = false;

	public function test_platform_held_data_carries_the_fee(): void
	{
		$data = (new StripePaymentGateway())->buildConnectedPaymentData('', 829, null, PaymentModeResolver::PLATFORM_HELD);

		$this->assertSame(829, $data[PlatformFeeMetadata::MARKER]);
		$this->assertArrayNotHasKey('application_fee_amount', $data);
		$this->assertArrayNotHasKey('transfer_data', $data);
	}

	public function test_marker_becomes_metadata_and_never_replaces_the_callers_metadata(): void
	{
		$params = PlatformFeeMetadata::applyToPaymentIntentParams([
			'amount' => 10000,
			'metadata' => ['booking_id' => 42, 'platform_fee' => '8.29'],
			PlatformFeeMetadata::MARKER => 829,
		]);

		$this->assertArrayNotHasKey(PlatformFeeMetadata::MARKER, $params);
		$this->assertSame(['booking_id' => 42, 'platform_fee' => '8.29', 'platform_fee_cents' => '829'], $params['metadata']);

		$own = PlatformFeeMetadata::applyToPaymentIntentParams(['metadata' => ['platform_fee_cents' => '5'], PlatformFeeMetadata::MARKER => 829]);
		$this->assertSame('5', $own['metadata']['platform_fee_cents'], 'A caller that already set the key wins');

		$untouched = ['amount' => 100, 'metadata' => ['a' => 'b']];
		$this->assertSame($untouched, PlatformFeeMetadata::applyToPaymentIntentParams($untouched), 'No marker: params untouched');
	}

	public function test_checkout_session_moves_the_marker_into_payment_intent_data(): void
	{
		$params = PlatformFeeMetadata::applyToCheckoutSessionParams([
			'mode' => 'payment',
			PlatformFeeMetadata::MARKER => 1,
			'payment_intent_data' => ['metadata' => ['booking_id' => 7], PlatformFeeMetadata::MARKER => 300],
		]);

		$this->assertArrayNotHasKey(PlatformFeeMetadata::MARKER, $params);
		$this->assertArrayNotHasKey(PlatformFeeMetadata::MARKER, $params['payment_intent_data']);
		$this->assertSame(['booking_id' => 7, 'platform_fee_cents' => '300'], $params['payment_intent_data']['metadata']);
	}

	public function test_completing_a_payment_reads_the_right_fee(): void
	{
		$destination = PaymentIntent::constructFrom(['id' => 'pi_d', 'transfer_data' => ['destination' => 'acct_x'], 'metadata' => ['platform_fee_cents' => '999']]);
		$this->assertSame(8.29, PlatformFeeMetadata::feeFor($destination, 8.29), '2026 charges keep their existing read-back');
		$this->assertSame(0.0, PlatformFeeMetadata::feeFor($destination, 0));

		$platformHeld = PaymentIntent::constructFrom(['id' => 'pi_p', 'metadata' => ['platform_fee_cents' => '829']]);
		$this->assertSame(8.29, PlatformFeeMetadata::feeFor($platformHeld, 0));

		$olderPlatformHeld = PaymentIntent::constructFrom(['id' => 'pi_o', 'metadata' => []]);
		$this->assertSame(0.0, PlatformFeeMetadata::feeFor($olderPlatformHeld, 0), 'Unknown stays 0, never guessed');
	}
}
