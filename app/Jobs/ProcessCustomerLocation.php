<?php

namespace App\Jobs;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Customer;
use App\Utils\IpGeolocationUtils;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessCustomerLocation implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public array $backoff = [10, 30];

    public function __construct(public readonly int $customerId)
    {
    }

    public function handle(): void
    {
        $customer = Customer::find($this->customerId);

        if (!$customer) {
            Log::warning('ProcessCustomerLocation: customer not found', ['customer_id' => $this->customerId]);
            return;
        }

        // Skip if location was already filled in by another job
        if (!empty($customer->country)) {
            return;
        }

        $paymentIntentId = $this->getLatestPaymentIntentId($this->customerId);

        if (!$paymentIntentId) {
            Log::info('ProcessCustomerLocation: no payment intent found', ['customer_id' => $this->customerId]);
            return;
        }

        $locationData = $this->getLocationFromStripe($paymentIntentId);

        if (!$locationData) {
            Log::info('ProcessCustomerLocation: no billing address in Stripe', [
                'customer_id'       => $this->customerId,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        IpGeolocationUtils::updateCustomerLocationData($this->customerId, $locationData);

        Log::info('ProcessCustomerLocation: updated', [
            'customer_id' => $this->customerId,
            'location'    => IpGeolocationUtils::getFormattedAddress($locationData),
        ]);
    }

    public function failed(Exception $exception): void
    {
        Log::error('ProcessCustomerLocation: job failed', [
            'customer_id' => $this->customerId,
            'error'       => $exception->getMessage(),
        ]);
    }

    private function getLatestPaymentIntentId(int $customerId): ?string
    {
        return DB::table('transactions')
            ->join('tour_bookings_master', 'transactions.tour_booking_id', '=', 'tour_bookings_master.id')
            ->where('tour_bookings_master.customer_id', $customerId)
            ->whereNotNull('transactions.transaction_number')
            ->where('transactions.transaction_number', '!=', '')
            ->where('transactions.transaction_number', 'like', 'pi_%')
            ->orderByDesc('transactions.id')
            ->value('transactions.transaction_number');
    }

    private function getLocationFromStripe(string $paymentIntentId): ?array
    {
        $retrieveOptions = ['expand' => ['charges.data.payment_method_details']];
        $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

        $chargeData = isset($paymentIntent->charges->data[0])
            ? $paymentIntent->charges->data[0]->toArray()
            : [];

        $address = $chargeData['billing_details']['address'] ?? [];

        $countryCode = ($address['country'] ?? null)
            ?: ($chargeData['payment_method_details']['card']['country'] ?? null);

        if (!$countryCode) {
            return null;
        }

        return [
            'country'   => $this->isoCodeToCountryName($countryCode),
            'region'    => $address['state'] ?? null,
            'city'      => $address['city']  ?? null,
            'latitude'  => null,
            'longitude' => null,
            'timezone'  => null,
            'isp'       => null,
        ];
    }

    private function isoCodeToCountryName(string $code): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-' . strtoupper($code), 'en');
            if ($name && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }
}
