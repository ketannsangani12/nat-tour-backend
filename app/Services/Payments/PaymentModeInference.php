<?php

namespace App\Services\Payments;

/**
 * For the small number of call sites that work from a raw Stripe PaymentIntent/Charge object rather
 * than the local `Transaction` model (which already carries a persisted `payment_mode` column decided
 * once at creation time), infers which settlement mode a charge actually used directly from the Stripe
 * object itself — ground truth, not a re-derivation from the booking's current tour date.
 *
 * A destination-mode charge always carries `transfer_data` (a plain, always-present field on a
 * PaymentIntent — no `expand` needed); a platform-held charge never does. Deliberately checks
 * `transfer_data` first, not the CHARGE's own `transfer` field: a PaymentIntent retrieved without
 * `expand: ['charges.data.transfer']` never populates that field at all, so relying on it alone would
 * silently misread a destination charge as platform-held whenever the caller didn't happen to expand
 * it. `transfer` (only meaningful on an explicitly-expanded Charge, e.g.
 * `App\Services\Dispute\DisputeService::handleDisputeLoss()`'s `retrieveCharge(..., ['expand' =>
 * ['transfer']])`) is checked as a second, independent signal for callers working from that shape
 * instead of a PaymentIntent.
 */
class PaymentModeInference
{
    /**
     * @param object $stripeObject A Stripe PaymentIntent or Charge.
     */
    public static function fromStripeObject(object $stripeObject): string
    {
        $hasTransfer = !empty($stripeObject->transfer_data) || !empty($stripeObject->transfer);

        return $hasTransfer ? PaymentModeResolver::DESTINATION : PaymentModeResolver::PLATFORM_HELD;
    }
}
