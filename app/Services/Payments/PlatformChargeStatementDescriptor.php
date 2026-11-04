<?php

namespace App\Services\Payments;

/**
 * Keeps the operator's name on the guest's card statement for a platform-held (2027+) charge
 * whose connected account has no card_payments capability (e.g. most Canadian operators).
 *
 * Such a charge cannot carry on_behalf_of, so Stripe has nothing to take the descriptor prefix
 * from and falls back to the PLATFORM's own static descriptor: a call site that only sets
 * statement_descriptor_suffix produces "<PLATFORM>* <OPERATOR>" on the statement. A full
 * statement_descriptor does not need any connected-account capability and replaces the whole
 * line. Confirmed in Stripe test mode (online card and Terminal card_present): suffix only ->
 * "TOURS* ASWTA TOURS", full descriptor only -> "ASWTA TOURS". If both are sent, Stripe uses the
 * suffix, so the suffix must be removed, not just supplemented. Stripe's own docs say a full
 * descriptor is for non-card payments only, so tests/Unit/Payments/PlatformHeldStatementDescriptorStripeTest
 * re-checks the real behaviour against the Stripe test account.
 *
 * Applied once, at the single PaymentIntent-creation choke point (StripePaymentGateway::createPayment),
 * so every call site is covered without each one having to know about the capability check.
 */
class PlatformChargeStatementDescriptor
{
    private const MIN_LENGTH = 5;
    private const MAX_LENGTH = 22;

    /**
     * Only a charge made directly on the platform account, with no transfer_data and no
     * on_behalf_of, is rewritten. Destination charges (transfer_data), charges that already carry
     * on_behalf_of, and direct charges on a connected account (stripe_account option, which has its
     * own prefix) are returned untouched, exactly as before.
     *
     * @param array $params PaymentIntent create params
     * @param array $opts   Stripe request options
     */
    public static function normalize(array $params, array $opts = []): array
    {
        if (!empty($opts['stripe_account'])) {
            return $params;
        }

        if (!empty($params['transfer_data']) || !empty($params['on_behalf_of'])) {
            return $params;
        }

        if (isset($params['statement_descriptor'])) {
            return $params;
        }

        $suffix = $params['statement_descriptor_suffix'] ?? null;
        if (!is_string($suffix) || trim($suffix) === '') {
            return $params;
        }

        $descriptor = self::sanitize($suffix);
        if ($descriptor === null) {
            // Not a valid full descriptor (too short / no letter). Keep the suffix rather than
            // risk Stripe rejecting a charge the guest is already making.
            return $params;
        }

        unset($params['statement_descriptor_suffix']);
        $params['statement_descriptor'] = $descriptor;

        return $params;
    }

    /**
     * Applies Stripe's full-descriptor rules: no < > \ ' " *, at most 22 characters, at least 5,
     * and at least one letter. Returns null when the value cannot form a valid descriptor.
     */
    private static function sanitize(string $value): ?string
    {
        $value = preg_replace('/[<>\\\\\'"*]/u', '', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        $value = rtrim(mb_substr($value, 0, self::MAX_LENGTH));

        if (mb_strlen($value) < self::MIN_LENGTH || !preg_match('/\p{L}/u', $value)) {
            return null;
        }

        return $value;
    }
}
