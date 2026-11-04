<?php

namespace App\Services\Network\Stripe;

use App\Models\Customer;
use App\Contracts\Payments\PaymentGatewayInterface;

class StripeService
{
    /**
     * Attach payment intent metadata to booking
     * 
     * @param Customer $customer
     * @param int $bookingId
     * @param string $paymentIntentId
     * 
     * @return void
     */
    public function attachPaymentIntent(Customer $customer, int $bookingId, string $paymentIntentId)
    {
        $updateParams = [
            'metadata' => [
                'customer_name' => $customer->name,
                'booking_id' => $bookingId,
                'tenant_id' => tenant()->id,
            ]
        ];
        app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);
    }

    /**
     * Create payment intent
     * 
     * @param array $request
     * 
     * @return array
     */
    public function createPaymentIntent(array $request): array
    {
        $paymentIntentParams = [
            'amount'               => $request['amount'],
            'currency'             => $request['currency'],
            'payment_method_types' => ['card'],
            'metadata'             => [
                'customer_name' => $request['customerName'],
                'booking_id'    => $request['bookingId'],
                'tenant_id'     => tenant()->id,
            ],
        ];
        $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

        return $paymentIntent->toArray();
    }
}