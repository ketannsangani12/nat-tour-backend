<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\StripePaymentGateway;
use Exception;
use Tests\TestCase;

/**
 * LC-2147: a bad refund calculation (unpaid split rows counted as prior refunds) produced negative
 * amounts that went straight to Stripe and came back as a raw API error. refundPayment() now fails
 * before calling Stripe with a message naming the payment and the bad amount. These cases throw
 * before any network call, so no Stripe stub is needed.
 */
class RefundPaymentRejectsNonPositiveAmountTest extends TestCase
{
    public function test_negative_amount_is_rejected_before_stripe(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Refund not sent: the calculated refund amount for pi_test_123 is -258.38.');

        (new StripePaymentGateway())->refundPayment([
            'payment_intent' => 'pi_test_123',
            'amount' => -258.38 * 100,
        ], PaymentModeResolver::DESTINATION);
    }

    public function test_zero_amount_is_rejected_before_stripe(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is 0.00');

        (new StripePaymentGateway())->refundPayment([
            'payment_intent' => 'pi_test_123',
            'amount' => 0,
        ], PaymentModeResolver::DESTINATION);
    }
}
