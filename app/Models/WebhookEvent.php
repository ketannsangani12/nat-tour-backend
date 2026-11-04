<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'tenant_id',
        'payload',
        'status',
        'error_message',
        'ip_address',
        'received_at',
        'dispatched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    /**
     * Mark the event as successfully dispatched to a tenant queue job.
     * Called immediately after ProcessWebhookForTenant is dispatched.
     */
    public function markAsDispatched(): void
    {
        $this->update(['status' => 'dispatched', 'dispatched_at' => now()]);
    }

    /**
     * Mark the event as unroutable when no tenant could be resolved.
     * The event is acknowledged with HTTP 200 so Stripe does not retry it.
     *
     * @param string $reason Human-readable explanation stored for debugging.
     */
    public function markAsUnroutable(string $reason): void
    {
        $this->update(['status' => 'unroutable', 'error_message' => $reason]);
    }

    /**
     * Mark the event as failed after an unexpected exception during processing.
     *
     * @param string $reason Exception message or description of the failure.
     */
    public function markAsFailed(string $reason): void
    {
        $this->update(['status' => 'failed', 'error_message' => $reason]);
    }

    /**
     * Resolve which tenant owns a disputed payment intent.
     *
     * Entry point for dispute tenant resolution. Tries the fast central-log
     * lookup first; falls back to a full cross-tenant DB scan only when no
     * prior webhook_events record exists (e.g. the dispute is for a payment
     * that was made before this webhook system was deployed).
     *
     * @param string $paymentIntentId Stripe PaymentIntent ID from the dispute object.
     * @return string|null Tenant ID, or null if the payment intent cannot be matched to any tenant.
     */
    public static function resolveDisputeTenant(string $paymentIntentId): ?string
    {
        return self::findTenantByPaymentIntent($paymentIntentId)
            ?? self::findTenantByPaymentIntentCrossDB($paymentIntentId);
    }

    /**
     * Look up the tenant from a previously logged payment_intent.succeeded event.
     *
     * When a payment succeeds, the central webhook logs it here with tenant_id set.
     * Disputes that arrive later can use that record to identify the correct tenant
     * without touching any tenant database.
     *
     * @param string $paymentIntentId Stripe PaymentIntent ID to search for.
     * @return string|null Tenant ID if a matching succeeded event was found, null otherwise.
     */
    private static function findTenantByPaymentIntent(string $paymentIntentId): ?string
    {
        $event = self::where('event_type', 'payment_intent.succeeded')
            ->whereNotNull('tenant_id')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.object.id')) = ?", [$paymentIntentId])
            ->select('tenant_id')
            ->first();

        return $event?->tenant_id;
    }

    /**
     * Scan every tenant's transactions table to find who owns the payment intent.
     *
     * Last-resort fallback used when findTenantByPaymentIntent() returns null —
     * meaning the original payment was processed before the central webhook log
     * existed. Iterates all tenants, initialises each DB context, and checks
     * the transactions table (transaction_number stores the Stripe PaymentIntent ID).
     * Tenancy context is always released in the finally block before moving on.
     *
     * @param string $paymentIntentId Stripe PaymentIntent ID to search for.
     * @return string|null Tenant ID of the first tenant whose transactions table
     *                     contains a completed card transaction for this intent,
     *                     or null if no match is found across all tenants.
     */
    private static function findTenantByPaymentIntentCrossDB(string $paymentIntentId): ?string
    {
        foreach (Tenant::all() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $exists = DB::table('transactions')
                    ->where('transaction_number', $paymentIntentId)
                    ->where('via', 'Card')
                    ->where('status', 'Completed')
                    ->exists();
            } finally {
                tenancy()->end();
            }

            if ($exists) {
                return $tenant->id;
            }
        }

        return null;
    }
}
