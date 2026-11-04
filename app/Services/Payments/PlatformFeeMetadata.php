<?php

namespace App\Services\Payments;

/**
 * Keeps NAT's fee on platform-held (2027+) PaymentIntents, so it can be read back when the payment is
 * completed.
 *
 * A destination charge carries NAT's fee as Stripe's application_fee_amount; the code that completes a
 * payment reads it back from there. A platform-held charge has no application fee, so that read-back
 * returned 0 and the payment was saved with NAT fee 0. buildConnectedPaymentData() still knows the fee,
 * so for platform-held charges it adds it under a private key; the gateway turns that key into
 * metadata.platform_fee_cents (merged, never replacing the caller's metadata) right before calling
 * Stripe, and the key itself never reaches Stripe.
 */
final class PlatformFeeMetadata
{
	/** Private key carried from buildConnectedPaymentData() to the gateway. Never sent to Stripe. */
	public const MARKER = '_nat_platform_fee_cents';

	/** Metadata key on the PaymentIntent. */
	public const METADATA_KEY = 'platform_fee_cents';

	/**
	 * Gateway side: move the private key into metadata. Used for PaymentIntent create/update params and
	 * for a Checkout Session's payment_intent_data.
	 */
	public static function applyToPaymentIntentParams(array $params): array
	{
		if (!array_key_exists(self::MARKER, $params)) {
			return $params;
		}

		$cents = $params[self::MARKER];
		unset($params[self::MARKER]);

		if (is_int($cents) || (is_string($cents) && ctype_digit($cents))) {
			$metadata = isset($params['metadata']) && is_array($params['metadata']) ? $params['metadata'] : [];
			$metadata += [self::METADATA_KEY => (string) $cents];
			$params['metadata'] = $metadata;
		}

		return $params;
	}

	public static function applyToCheckoutSessionParams(array $params): array
	{
		unset($params[self::MARKER]);

		if (isset($params['payment_intent_data']) && is_array($params['payment_intent_data'])) {
			$params['payment_intent_data'] = self::applyToPaymentIntentParams($params['payment_intent_data']);
		}

		return $params;
	}

	/**
	 * Completion side: NAT's fee for a completed payment, in currency units.
	 *
	 * Destination charges return $destinationFee untouched, so callers keep exactly their existing
	 * read-back. Platform-held charges return the fee recorded on the PaymentIntent, or 0 when it was
	 * created before this was recorded (the previous behaviour).
	 */
	public static function feeFor(object $paymentIntent, float|int $destinationFee): float
	{
		if (PaymentModeInference::fromStripeObject($paymentIntent) === PaymentModeResolver::DESTINATION) {
			return (float) $destinationFee;
		}

		$metadata = $paymentIntent->metadata ?? null;
		$cents = match (true) {
			$metadata instanceof \ArrayAccess, is_array($metadata) => $metadata[self::METADATA_KEY] ?? null,
			is_object($metadata) => $metadata->{self::METADATA_KEY} ?? null,
			default => null,
		};

		return is_numeric($cents) ? round(((int) $cents) / 100, 2) : 0.0;
	}
}
