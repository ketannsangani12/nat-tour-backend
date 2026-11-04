<?php

namespace App\Http\Controllers\API\V2;

use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\V2\BaseController;
use App\Models\Customer;
use App\Contracts\Payments\PaymentGatewayInterface;
use Exception;

class PaymentController extends BaseController
{
    /**
     * Prepare to save card on PaymentIntent.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function prepareToSaveCardOnPaymentIntent(Request $request)
    {
        try {
            // ✅ Step 1: Validate incoming data
            $validated = $this->validateRequest($request);

            // ✅ Step 2: Find or create a Stripe customer
            $customer = $this->getOrCreateCustomer($validated);

            // ✅ Step 3: Attempt to update the PaymentIntent
            try {
                $paymentIntent = $this->updatePaymentIntent(
                    $validated['payment_intent_id'],
                    $validated['email'],
                    $customer->id
                );
            } catch (Exception $e) {
                // 🎯 Bonus Tip: Handle already completed PaymentIntents gracefully
                if (str_contains($e->getMessage(), 'PaymentIntent is not in a valid state for updating.') || str_contains($e->getMessage(), 'Cannot update PaymentIntent in status: succeeded')) {
                    Log::info('PaymentIntent already finalized, retrieving instead of failing.', [
                        'payment_intent_id' => $validated['payment_intent_id'],
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);

                    // Just retrieve the existing one
                    $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($validated['payment_intent_id']);
                } else {
                    throw $e; // Re-throw if it’s some other error
                }
            }

            // ✅ Step 4: Respond with your standardized success structure
            return $this->sendSuccessResponse($paymentIntent->id, $customer->id);
        } catch (ApiErrorException $exception) {
            // 🧭 Stripe specific error handling
            Log::error('Stripe API error in prepareToSaveCard', [
                'request' => $request->all(),
                'error' => $exception->getMessage(),
                'code' => $exception->getStripeCode(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->sendErrorResponse(
                'We could not process your payment at the moment. Please refresh the page and try again.',
                422
            );
        } catch (Exception $exception) {
            // 🧭 Fallback error handling
            Log::error('Unexpected error in prepareToSaveCard', [
                'request' => $request->all(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->sendErrorResponse(
                'An unexpected error occurred. Please refresh the page and try again.',
                500
            );
        }
    }

    /**
     * Validate the request data.
     *
     * @param Request $request
     * @return array
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'payment_intent_id' => 'required|string',
            'email' => 'required|email|max:255',
            'name' => 'max:255',
            'phone_number' => 'max:20',
        ]);
    }

    /**
     * Get or create a Stripe customer.
     *
     * @param array $data
     * @return \Stripe\Customer
     */
    private function getOrCreateCustomer(array $data): \Stripe\Customer
    {
        $gateway = app(PaymentGatewayInterface::class);

        $searchParams = [
            'query' => "email:'{$data['email']}'",
            'limit' => 1,
        ];
        $customers = $gateway->searchCustomers($searchParams);

        if (!empty($customers->data)) {
            return $customers->data[0];
        }

        $newCustomerParams = [
            'email' => $data['email'],
            'name'  => $data['name'] ?? null,
            'phone' => $data['phone_number'] ?? null,
        ];
        return $gateway->createCustomer($newCustomerParams);
    }

    /**
     * Update the PaymentIntent with customer details.
     *
     * @param string $paymentIntentId
     * @param string $email
     * @param string $customerId
     * @return \Stripe\PaymentIntent
     */
    private function updatePaymentIntent(string $paymentIntentId, string $email, string $customerId): \Stripe\PaymentIntent
    {
        // Retrieve the PaymentIntent first
        $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId);

        // ✅ Check status before trying to update
        if (!in_array($paymentIntent->status, ['requires_payment_method', 'requires_confirmation'])) {
            // If it's already succeeded or canceled or processing, don't try to update
            // You can either throw, log, or gracefully return
            throw new Exception("Cannot update PaymentIntent in status: {$paymentIntent->status}");
        }

        // ✅ If a customer is already attached, no update needed
        if (!empty($paymentIntent->customer) && $paymentIntent->customer === $customerId) {
            return $paymentIntent;
        }

        // ✅ Safe to update only when status is valid and no customer is attached yet
        $isAffiliate = Customer::where('email', $email)->where('is_affiliate', 'Yes')->exists();

        $updateParams = [
            'customer' => $customerId,
        ];

        // Allow saving card if:
        // 1) DISABLE CARD SAVE is disabled
        // 2) NOT affiliate
        // 3) Affiliate AND card save not disabled
        if (hasSetting('DISABLE_CARD_SAVE') == false) {
            if (!$isAffiliate || ($isAffiliate && hasSetting('DISABLE_AFFILIATE_CARD_SAVE') == false)) {
                $updateParams['setup_future_usage'] = 'off_session';
            }
        }

        return app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);
    }

    /**
     * Send a standardized success response.
     *
     * @param string $paymentIntentId
     * @param string $customerId
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendSuccessResponse(string $paymentIntentId, string $customerId): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'paymentIntentId' => $paymentIntentId,
            'stripeCustomerId' => $customerId,
            'message' => 'PaymentIntent updated successfully for saving card.',
        ], 200);
    }

    /**
     * Send a standardized error response.
     *
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendErrorResponse(string $message, int $statusCode): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], $statusCode);
    }
}