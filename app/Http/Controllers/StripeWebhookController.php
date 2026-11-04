<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookForTenant;
use App\Jobs\StripeInvoiceUpdateStatus;
use App\Models\AutoPayout;
use App\Models\AutoPayoutRule;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\TourBooking;
use App\Models\Dispute;
use App\Models\DisputeTimeline;
use App\Models\AutoPayoutItem;
use App\Models\WebhookAudit;
use App\Services\Webhook\StripePaymentIntentSucceededForNewBooking;
use App\Services\Webhook\StripePaymentIntentSucceededForMakePayment;
use App\Services\Webhook\StripePaymentIntentSucceededForTip;
use App\Services\Webhook\StripePaymentIntentSucceededForDiscountPayment;
use App\Services\Webhook\StripePaymentIntentSucceededForPaymentLink;
use App\Services\Webhook\StripePaymentIntentSucceededForTapToPayTip;
use App\Services\Dispute\DisputeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StripeWebhookController extends Controller
{
    /**
     * The dispute service instance.
     */
    protected DisputeService $disputeService;

    /**
     * Initialize the controller with required services.
     */
    public function __construct(DisputeService $disputeService)
    {
        $this->disputeService = $disputeService;
    }
    /**
     * Receive a Stripe webhook, verify the signature using the central
     * webhook secret, resolve the target tenant, and dispatch a queued job
     * that runs the actual processing inside that tenant's DB context.
     */
    public function stripeWebhook(Request $request)
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        // Read from the central settings table (managed via admin UI).
        // Falls back to env if the row is missing, empty, or the table doesn't
        // exist yet (e.g. before the central migration has run).
        try {
            $secret = DB::connection(config('tenancy.central_connection'))
                ->table('settings')
                ->where('key', 'STRIPE_WEBHOOK_SECRET')
                ->value('value')
                ?: config('services.stripe.webhook_secret');
        } catch (Exception $e) {
            $secret = config('services.stripe.webhook_secret');
        }

        if (empty($secret)) {
            Log::error('stripe-webhook: STRIPE_WEBHOOK_SECRET is not configured.');
            return response()->json(['message' => 'Webhook secret not configured'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($rawBody, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('stripe-webhook: invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('stripe-webhook: invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $tenantId = $this->resolveTenantId($event);

        $centralEvent = WebhookEvent::firstOrCreate(
            ['stripe_event_id' => $event->id],
            [
                'event_type'  => $event->type,
                'tenant_id'   => $tenantId,
                'payload'     => $event->toArray(),
                'status'      => 'received',
                'ip_address'  => request()->ip(),
                'received_at' => now(),
            ]
        );

        if (!$tenantId) {
            Log::warning('stripe-webhook: unroutable event — no tenant_id resolvable', [
                'event_id'   => $event->id,
                'event_type' => $event->type,
            ]);
            $centralEvent->markAsUnroutable("No tenant_id resolvable for {$event->type}");
            return response()->json(['message' => 'Webhook received'], 200);
        }

        // nat shares its Stripe account with a separate product (the Laguna Creek
        // POS), so Stripe fans every account event out to nat's endpoint too. Those
        // events carry a tenant_id that belongs to no nat tenant — it is the other
        // product's tenant identifier (e.g. "production/pos/tenant/..."), not one of
        // our UUIDs. Record it (the webhook_events row above keeps the full payload
        // and metadata) but do NOT dispatch a job that would only fail with
        // "tenant not found". Stripe still gets a 200 so it does not retry.
        if ($this->isForeignEvent($tenantId)) {
            $centralEvent->markAsUnroutable(
                "Foreign tenant_id ({$tenantId}); not a nat tenant — event from another product on the shared Stripe account."
            );
            return response()->json(['message' => 'Webhook received'], 200);
        }

        // Dispute events must run after payment_intent.succeeded stores the transaction.
        // Both webhooks arrive almost simultaneously so the PI event is already in
        // webhook_events by the time we check — the tenant resolves immediately and
        // both jobs land in the queue at the same time. Delay dispute jobs by 30 s
        // to guarantee the PI job finishes first.
        $delay = str_starts_with($event->type, 'charge.dispute.')
            ? now()->addSeconds(30)
            : null;

        ProcessWebhookForTenant::dispatch($tenantId, $event->toArray())->delay($delay);
        $centralEvent->markAsDispatched();

        return response()->json(['message' => 'Webhook received'], 200);
    }

    /**
     * Resolve the tenant that owns this Stripe event.
     *
     * Primary:  metadata.tenant_id on the event object.
     * Fallback: for dispute events, look up the payment_intent in webhook_events.
     *           Both webhooks arrive nearly simultaneously so the PI event is
     *           already stored in webhook_events by the time this runs.
     *           The dispute job itself is delayed 30 s (see stripeWebhook) to
     *           ensure the PI job finishes storing the transaction first.
     */
    private function resolveTenantId(\Stripe\Event $event): ?string
    {
        $object = $event->data->object;

        if (!empty($object->metadata->tenant_id)) {
            return $object->metadata->tenant_id;
        }

        if (str_starts_with($event->type, 'charge.dispute.')) {
            $paymentIntentId = $object->payment_intent ?? null;
            if ($paymentIntentId) {
                return WebhookEvent::resolveDisputeTenant($paymentIntentId);
            }
        }

        return null;
    }

    /**
     * Whether this event was created by a different product that shares nat's
     * Stripe account (e.g. the Laguna Creek POS). Such events carry a tenant_id
     * that matches none of our tenants. Reached only after the null-tenant_id
     * check in stripeWebhook(), so $tenantId is non-null here.
     */
    private function isForeignEvent(?string $tenantId): bool
    {
        return $tenantId !== null && Tenant::find($tenantId) === null;
    }

    /**
     * Process the webhook event based on its type.
     * 
     * @param \Stripe\Event $event The Stripe event
     * @param WebhookAudit|null $webhookAudit The webhook audit record
     * @return void
     */
    public function processWebhookEvent(\Stripe\Event $event, ?WebhookAudit $webhookAudit): void
    {
        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
                $this->handleStripePaymentIntentSucceeded($paymentIntent);
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
                $this->handleStripePaymentIntentPaymentFailed($paymentIntent);
                break;
            case 'payment_intent.canceled':
                $paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
                $this->handleStripePaymentIntentCanceled($paymentIntent);
                break;
            case 'invoice.paid':
            case 'invoice.created':
            case 'invoice.finalized':
            case 'invoice.deleted':
            case 'invoice.marked_uncollectible':
                $this->stripeInvoiceStatusChange($event->data->object, $event->type);
                break;
            case 'payout.created':
            case 'payout.paid':
            case 'payout.failed':
                $this->handleStripeAutoPayout($event);
                break;
            case 'charge.dispute.created':
            case 'charge.dispute.updated':
            case 'charge.dispute.closed':
                $stripeDispute = $event->data->object;
                match ($event->type) {
                    'charge.dispute.created' => $this->handleDisputeCreated($stripeDispute, $webhookAudit),
                    'charge.dispute.updated' => $this->handleDisputeUpdated($stripeDispute, $webhookAudit),
                    'charge.dispute.closed'  => $this->handleDisputeClosed($stripeDispute, $webhookAudit),
                };
                break;
            default:
                // Log::info("Received unhandled webhook event type: {$event->type}", [
                //     'event_id' => $event->id,
                //     'tenant_id' => tenant()->id
                // ]);
        }
    }

    /**
     * Additional method to handle the jobs for different payments
     *
     * @param object $paymentIntent
     *
     * @return void
     */
    public function handleStripePaymentIntentSucceeded($paymentIntent): void
    {
        if (isset($paymentIntent->metadata->section) && isset($paymentIntent->metadata->app) && $paymentIntent->metadata->app == 1 && isset($paymentIntent->metadata->tenant_id) && tenant()->id == $paymentIntent->metadata->tenant_id) {
            $section = (int) $paymentIntent->metadata->section;
            switch ($section) {
                case config('constants.payment_sections.new_booking'):
                    StripePaymentIntentSucceededForNewBooking::run($paymentIntent);
                    break;
                case config('constants.payment_sections.make_payment'):
                    StripePaymentIntentSucceededForMakePayment::run($paymentIntent);
                    break;
                case config('constants.payment_sections.change_people'):
                    break;
                case config('constants.payment_sections.tip_payment'):
                    StripePaymentIntentSucceededForTip::run($paymentIntent);
                    break;
                case config('constants.payment_sections.discount_payment'):
                    StripePaymentIntentSucceededForDiscountPayment::run($paymentIntent);
                    break;
                case config('constants.payment_sections.payment_link'):
                    StripePaymentIntentSucceededForPaymentLink::run($paymentIntent);
                    break;
                case config('constants.payment_sections.tap_to_pay_tip_payment'):
                    StripePaymentIntentSucceededForTapToPayTip::run($paymentIntent);
                    break;    
            }
        }
    }

    /**
     * Additional method to handle the jobs for different payments
     *
     * @param object $paymentIntent
     *
     * @return void
     */
    public function handleStripePaymentIntentPaymentFailed($paymentIntent): void
    {
        if (isset($paymentIntent->metadata->section) && isset($paymentIntent->metadata->app) && $paymentIntent->metadata->app == 1 && isset($paymentIntent->metadata->tenant_id) && tenant()->id == $paymentIntent->metadata->tenant_id) {
            $bookingId = trim($paymentIntent->metadata['booking_id']);

            $tourBooking = TourBooking::find($bookingId);

            if ($tourBooking) {
                DB::table('tour_booking_history')
                    ->insert([
                        'tour_booking_id' => $bookingId,
                        'operation' => 'Error',
                        'details' => $paymentIntent->last_payment_error->message,
                        'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
                        'operation_by' => $tourBooking->created_by,
                        'operation_by_user_type' => $tourBooking->created_by_user_type,
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ]);
            }
        }
    }

    /**
     * Additional method to handle the jobs for different payments
     *
     * @param object $paymentIntent
     *
     * @return void
     */
    public function handleStripePaymentIntentCanceled($paymentIntent): void
    {
        if (isset($paymentIntent->metadata->section) && isset($paymentIntent->metadata->app) && $paymentIntent->metadata->app == 1 && isset($paymentIntent->metadata->tenant_id) && tenant()->id == $paymentIntent->metadata->tenant_id) {
            $bookingId = trim($paymentIntent->metadata['booking_id']);

            $tourBooking = TourBooking::find($bookingId);

            if ($tourBooking) {
                DB::table('tour_booking_history')
                    ->insert([
                        'tour_booking_id' => $bookingId,
                        'operation' => 'Error',
                        'details' => $paymentIntent->last_payment_error->message,
                        'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
                        'operation_by' => $tourBooking->created_by,
                        'operation_by_user_type' => $tourBooking->created_by_user_type,
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ]);
            }
        }
    }

    /**
     * Update the invoice status based on the invoice website event
     * @param object $stripeInvoice
     */
    public function stripeInvoiceStatusChange($stripeInvoice, $eventType): void
    {
        dispatch(new StripeInvoiceUpdateStatus($stripeInvoice, $eventType));
    }

    /**
     * Handle Stripe auto payout events and store data in auto_payouts and auto_payout_items
     *
     * @param \Stripe\Event $event
     * @return void
     */
    public function handleStripeAutoPayout($event): void
    {
        try {
            $payout = $event->data->object; // Contains a \Stripe\Payout
            Log::info("Processing payout event: {$event->type} for payout ID: {$payout->id}");

            $timezone = tourOperator()->timezone;
            $now = Carbon::now($timezone);

            // Match app-created payouts by Stripe's payout ID (stored at creation).
            // Fall back to legacy payout_reference match so historical rows created
            // before the stripe_payout_id column existed still resolve.
            $autoPayout = AutoPayout::where('stripe_payout_id', $payout->id)
                ->orWhere('payout_reference', $payout->id)
                ->first();

            // Rule is only required to synthesize a new AutoPayout record for a
            // payout that originated outside the app (rare). Existing records must
            // still receive status updates and booking-flag resets even if the
            // rule was disabled between payout execution and webhook arrival.
            $rule = AutoPayoutRule::where('enabled', true)->first();

            if (!$autoPayout && !$rule) {
                Log::error("No existing AutoPayout and no enabled rule for payout {$payout->id}; cannot synthesize record.");
                return;
            }

            if ($rule) {
                $lastExecutedDate = Carbon::parse($rule->last_executed_at ?? $now->copy()->subMonth(), $timezone)->format('Y-m-d H:i');
                $nextExecutionAt = Carbon::parse($rule->next_execution_at ?? $now, $timezone);
                $cycleStart = "$lastExecutedDate:00";
                $cycleEnd = $nextExecutionAt->copy()->subSecond();
                Log::info("Payout cycle determined from {$cycleStart} to {$cycleEnd} for payout {$payout->id}");
            }

            if ($autoPayout) {
                // Update existing record with new status
                $autoPayout->status = $event->type === 'payout.paid' ? 'completed' : ($event->type === 'payout.failed' ? 'failed' : 'pending');
                if ($payout->arrival_date) {
                    $autoPayout->processed_date = Carbon::parse($payout->arrival_date, $timezone);
                }
                $autoPayout->save();

                // When Stripe reports the payout failed, release the bookings so
                // they re-qualify in the next payout cycle. Without this, bookings
                // are marked payout_made=1 but the money never arrived.
                if ($event->type === 'payout.failed') {
                    $bookingIds = AutoPayoutItem::where('payout_id', $autoPayout->id)
                        ->pluck('booking_id');
                    if ($bookingIds->isNotEmpty()) {
                        TourBooking::whereIn('id', $bookingIds)->update(['payout_made' => 0]);
                        Log::warning("Stripe payout {$payout->id} failed; reset payout_made=0 for {$bookingIds->count()} bookings so they requalify next cycle.");
                    }
                }

                Log::info("Updated existing payout {$payout->id} with status {$autoPayout->status}");
            } else {
                Log::info("Creating new payout record for payout ID {$payout->id}");
                // Calculate total amounts based on transactions in the cycle
                $transactions = DB::table('transactions as tra')
                    ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tra.tour_booking_id')
                    ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->whereNot('tra.status', 'Failed')
                    ->where('tra.via', 'Card')
                    ->where('tra.request_from', '!=', 'Insert')
                    ->where('tbm.comments', 'not like', '#%')
                    ->where('tbm.payout_made', 0)
                    ->whereBetween(DB::raw("TIMESTAMP(tst.date, tst.time_from)"), [$cycleStart, $cycleEnd])
                    ->select('tra.tour_booking_id', 'tra.amount', 'tra.fee_amount', 'tra.status')
                    ->get();

                $totalAmount = 0;
                $platformFee = 0;
                $netAmount = 0;
                $bookingCount = 0;
                $bookingData = [];

                foreach ($transactions as $transaction) {
                    $bookingId = $transaction->tour_booking_id;
                    if (!isset($bookingData[$bookingId])) {
                        $bookingData[$bookingId] = [
                            'total_amount' => 0,
                            'platform_fee' => 0,
                            'net_amount' => 0,
                        ];
                        $bookingCount++;
                    }

                    switch ($transaction->status) {
                        case 'Completed':
                            $bookingData[$bookingId]['total_amount'] += $transaction->amount;
                            $bookingData[$bookingId]['platform_fee'] += $transaction->fee_amount;
                            $bookingData[$bookingId]['net_amount'] += $transaction->amount - $transaction->fee_amount;
                            // To add to overall totals
                            $totalAmount += $transaction->amount;
                            $platformFee += $transaction->fee_amount;
                            $netAmount += $transaction->amount - $transaction->fee_amount;
                            break;
                        case 'Refund':
                            $bookingData[$bookingId]['total_amount'] -= $transaction->amount;
                            $bookingData[$bookingId]['platform_fee'] -= $transaction->fee_amount;
                            $bookingData[$bookingId]['net_amount'] -= $transaction->amount - $transaction->fee_amount;
                            // To subtract from overall totals
                            $totalAmount -= $transaction->amount;
                            $platformFee -= $transaction->fee_amount;
                            $netAmount -= $transaction->amount - $transaction->fee_amount;
                            break;
                    }
                }

                // Create new record
                $autoPayout = new AutoPayout();
                $autoPayout->payout_reference = $payout->id;
                $autoPayout->rule_id = $rule->id;
                $autoPayout->total_amount = number_format($totalAmount, 2, '.', '');
                $autoPayout->platform_fee = number_format($platformFee, 2, '.', '');
                $autoPayout->net_amount = number_format($netAmount, 2, '.', ''); // Ensure two decimal places without rounding issues
                $autoPayout->booking_count = $bookingCount;
                $autoPayout->status = $event->type === 'payout.paid' ? 'completed' : ($event->type === 'payout.failed' ? 'failed' : 'pending');
                $autoPayout->processed_date = $payout->arrival_date ? Carbon::parse($payout->arrival_date, $timezone) : $now;
                $autoPayout->save();

                Log::info("Payout cycle from {$cycleStart} to {$cycleEnd} with total amount {$autoPayout->total_amount}, platform fee {$autoPayout->platform_fee}, net amount {$autoPayout->net_amount}, booking count {$autoPayout->booking_count}");

                // Store in auto_payout_items
                foreach ($bookingData as $bookingId => $data) {
                    $autoPayoutItem = new AutoPayoutItem();
                    $autoPayoutItem->payout_id = $autoPayout->id;
                    $autoPayoutItem->booking_id = $bookingId;
                    $autoPayoutItem->total_amount = number_format($data['total_amount'], 2, '.', '');
                    $autoPayoutItem->platform_fee = number_format($data['platform_fee'], 2, '.', '');
                    $autoPayoutItem->net_amount = number_format($data['net_amount'], 2, '.', '');
                    $autoPayoutItem->save();

                    Log::info("Added payout item for booking {$bookingId} in payout {$autoPayout->id}");

                    $booking = TourBooking::find($bookingId);
                    $booking->payout_made = 1;
                    $booking->save();

                    Log::info("Marked booking {$bookingId} as payout made.");
                }

                Log::info("Created new payout {$payout->id} with status {$autoPayout->status}");
            }
        } catch (Exception $e) {
            $payoutId = isset($payout) ? $payout->id : ($event->data->object->id ?? 'unknown');
            Log::error("Error processing payout {$payoutId}: " . $e->getMessage());
        }
    }

    /**
     * Handle charge.dispute.created webhook event.
     * 
     * @param object $stripeDispute The Stripe dispute object
     * @param WebhookAudit|null $webhookAudit The webhook audit record
     * @return void
     */
    public function handleDisputeCreated($stripeDispute, ?WebhookAudit $webhookAudit = null): void
    {
        try {
            // Log::info('Processing charge.dispute.created webhook', [
            //     'stripe_dispute_id' => $stripeDispute->id,
            //     'amount' => $stripeDispute->amount,
            //     'reason' => $stripeDispute->reason,
            //     'status' => $stripeDispute->status,
            //     'tenant_id' => tenant()?->id,
            //     'webhook_audit_id' => $webhookAudit?->id
            // ]);

            // Convert Stripe dispute object to array for processing
            $disputeData = $this->convertStripeDisputeToArray($stripeDispute);

            // Check if dispute already exists to prevent duplicates
            $existingDispute = Dispute::where('stripe_dispute_id', $stripeDispute->id)->first();
            if ($existingDispute) {
                // Log::info('Dispute already exists, skipping creation', [
                //     'stripe_dispute_id' => $stripeDispute->id,
                //     'existing_dispute_id' => $existingDispute->id
                // ]);

                // Log webhook received event in timeline
                DisputeTimeline::logWebhookReceived($existingDispute, [
                    'webhook_type' => 'charge.dispute.created',
                    'webhook_audit_id' => $webhookAudit?->id,
                    'action' => 'duplicate_creation_attempt',
                    'stripe_data' => $disputeData
                ]);

                return;
            }

            // Create the dispute using the service (returns null when no booking found)
            $dispute = $this->disputeService->createFromStripeData($disputeData);
            if (!$dispute) {
                return;
            }

            // Enhanced timeline logging with webhook information
            DisputeTimeline::logWebhookReceived($dispute, [
                'webhook_type' => 'charge.dispute.created',
                'webhook_audit_id' => $webhookAudit?->id,
                'action' => 'dispute_created',
                'stripe_data' => $disputeData,
                'evidence_due_by' => $dispute->evidence_due_by?->toISOString(),
                'associated_booking_id' => $dispute->booking_id,
                'associated_transaction_id' => $dispute->transaction_id
            ]);

            // Store complete webhook payload in dispute timeline for audit
            DisputeTimeline::logSystemAction($dispute, [
                'action_description' => 'Webhook payload stored for audit trail',
                'webhook_payload_stored' => true,
                'webhook_audit_id' => $webhookAudit?->id,
                'payload_size' => strlen(json_encode($disputeData))
            ]);

            // Log::info('Successfully created dispute from webhook', [
            //     'dispute_id' => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'booking_id' => $dispute->booking_id,
            //     'amount' => $dispute->amount,
            //     'webhook_audit_id' => $webhookAudit?->id
            // ]);

        } catch (Exception $e) {
            Log::error('Failed to process charge.dispute.created webhook', [
                'stripe_dispute_id' => $stripeDispute->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_audit_id' => $webhookAudit?->id
            ]);

            // Don't throw the exception to prevent webhook retry loops
            // Stripe will retry failed webhooks automatically
        }
    }

    /**
     * Handle charge.dispute.updated webhook event.
     * 
     * @param object $stripeDispute The Stripe dispute object
     * @param WebhookAudit|null $webhookAudit The webhook audit record
     * @return void
     */
    public function handleDisputeUpdated($stripeDispute, ?WebhookAudit $webhookAudit = null): void
    {
        try {
            // Log::info('Processing charge.dispute.updated webhook', [
            //     'stripe_dispute_id' => $stripeDispute->id,
            //     'status' => $stripeDispute->status,
            //     'tenant_id' => tenant()->id,
            //     'webhook_audit_id' => $webhookAudit?->id
            // ]);

            // Find the existing dispute
            $dispute = Dispute::where('stripe_dispute_id', $stripeDispute->id)->first();
            if (!$dispute) {
                Log::warning('Dispute not found for update webhook, creating new dispute', [
                    'stripe_dispute_id' => $stripeDispute->id,
                    'webhook_audit_id' => $webhookAudit?->id
                ]);
                
                // If dispute doesn't exist, create it
                $this->handleDisputeCreated($stripeDispute, $webhookAudit);
                return;
            }

            // Store old values for comparison
            $oldStatus = $dispute->status;
            $oldAmount = $dispute->amount;
            $oldEvidenceDueBy = $dispute->evidence_due_by;

            // Convert Stripe dispute object to array for processing
            $disputeData = $this->convertStripeDisputeToArray($stripeDispute);

            // Sync the dispute with Stripe data
            $updatedDispute = $this->disputeService->syncWithStripe($dispute->id);

            // Detect what changed
            $changes = [];
            if ($oldStatus !== $updatedDispute->status) {
                $changes['status'] = ['from' => $oldStatus, 'to' => $updatedDispute->status];
            }
            if ((string) $oldAmount !== (string) $updatedDispute->amount) {
                $changes['amount'] = ['from' => $oldAmount, 'to' => $updatedDispute->amount];
            }
            if ($oldEvidenceDueBy != $updatedDispute->evidence_due_by) {
                $changes['evidence_due_by'] = [
                    'from' => $oldEvidenceDueBy?->toISOString(),
                    'to' => $updatedDispute->evidence_due_by?->toISOString()
                ];
            }

            // Log webhook received event with detailed change information
            DisputeTimeline::logWebhookReceived($updatedDispute, [
                'webhook_type' => 'charge.dispute.updated',
                'webhook_audit_id' => $webhookAudit?->id,
                'action' => 'dispute_synchronized',
                'changes_detected' => $changes,
                'stripe_data' => $disputeData,
                'sync_timestamp' => now()->toISOString()
            ]);

            // If significant changes occurred, log them separately
            if (!empty($changes)) {
                DisputeTimeline::logSystemAction($updatedDispute, [
                    'action_description' => 'Dispute data synchronized from Stripe with changes',
                    'changes' => $changes,
                    'webhook_audit_id' => $webhookAudit?->id,
                    'previous_values' => [
                        'status' => $oldStatus,
                        'amount' => $oldAmount,
                        'evidence_due_by' => $oldEvidenceDueBy?->toISOString()
                    ]
                ]);
            }

            // Log::info('Successfully updated dispute from webhook', [
            //     'dispute_id' => $updatedDispute->id,
            //     'stripe_dispute_id' => $updatedDispute->stripe_dispute_id,
            //     'old_status' => $oldStatus,
            //     'new_status' => $updatedDispute->status,
            //     'changes_count' => count($changes),
            //     'webhook_audit_id' => $webhookAudit?->id
            // ]);

        } catch (Exception $e) {
            Log::error('Failed to process charge.dispute.updated webhook', [
                'stripe_dispute_id' => $stripeDispute->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_audit_id' => $webhookAudit?->id
            ]);

            // Don't throw the exception to prevent webhook retry loops
        }
    }

    /**
     * Handle charge.dispute.closed webhook event.
     * 
     * @param object $stripeDispute The Stripe dispute object
     * @param WebhookAudit|null $webhookAudit The webhook audit record
     * @return void
     */
    public function handleDisputeClosed($stripeDispute, ?WebhookAudit $webhookAudit = null): void
    {
        try {
            // Log::info('Processing charge.dispute.closed webhook', [
            //     'stripe_dispute_id' => $stripeDispute->id,
            //     'status' => $stripeDispute->status,
            //     'tenant_id' => tenant()->id,
            //     'webhook_audit_id' => $webhookAudit?->id
            // ]);

            // Find the existing dispute
            $dispute = Dispute::where('stripe_dispute_id', $stripeDispute->id)->first();
            if (!$dispute) {
                Log::warning('Dispute not found for closed webhook, creating new dispute', [
                    'stripe_dispute_id' => $stripeDispute->id,
                    'webhook_audit_id' => $webhookAudit?->id
                ]);
                
                // If dispute doesn't exist, create it with closed status
                $this->handleDisputeCreated($stripeDispute, $webhookAudit);
                return;
            }

            // Store old status for comparison
            $oldStatus = $dispute->status;

            // Sync the dispute with final Stripe data
            $updatedDispute = $this->disputeService->syncWithStripe($dispute->id);

            // Convert Stripe dispute object to array for processing
            $disputeData = $this->convertStripeDisputeToArray($stripeDispute);

            // Log webhook received event
            DisputeTimeline::logWebhookReceived($updatedDispute, [
                'webhook_type' => 'charge.dispute.closed',
                'webhook_audit_id' => $webhookAudit?->id,
                'action' => 'dispute_closed',
                'closure_reason' => $stripeDispute->status,
                'stripe_data' => $disputeData,
                'final_status' => $updatedDispute->status,
                'status_transition' => ['from' => $oldStatus, 'to' => $updatedDispute->status]
            ]);

            // Log the dispute closure with additional metadata
            DisputeTimeline::logDisputeClosure($updatedDispute, [
                'closure_reason' => $stripeDispute->status,
                'webhook_received_at' => now(),
                'webhook_audit_id' => $webhookAudit?->id,
                'final_amount' => $stripeDispute->amount / 100, // Convert from cents
                'stripe_data' => $disputeData,
                'resolution_details' => [
                    'outcome' => $updatedDispute->status,
                    'processing_duration' => $dispute->created_at->diffInDays(now()),
                    'evidence_submitted' => $dispute->evidence()->count() > 0,
                    'evidence_count' => $dispute->evidence()->count()
                ]
            ]);

            // Store comprehensive closure audit information
            DisputeTimeline::logSystemAction($updatedDispute, [
                'action_description' => 'Dispute closure processing completed',
                'webhook_audit_id' => $webhookAudit?->id,
                'closure_metadata' => [
                    'total_timeline_events' => $updatedDispute->timeline()->count(),
                    'total_evidence_files' => $updatedDispute->evidence()->count(),
                    'dispute_lifecycle_days' => $dispute->created_at->diffInDays(now()),
                    'final_stripe_status' => $stripeDispute->status,
                    'local_status' => $updatedDispute->status
                ]
            ]);

            // If the dispute was lost and the DISPUTE_REVERSAL setting is enabled,
            // reverse the transfer to recover the ticket cost from the connected account.
            if ($updatedDispute->status === 'lost' && hasSetting('DISPUTE_REVERSAL')) {
                $this->disputeService->handleDisputeLoss($updatedDispute, $stripeDispute);
            }

            // Log::info('Successfully processed dispute closure from webhook', [
            //     'dispute_id' => $updatedDispute->id,
            //     'stripe_dispute_id' => $updatedDispute->stripe_dispute_id,
            //     'old_status' => $oldStatus,
            //     'final_status' => $updatedDispute->status,
            //     'amount' => $updatedDispute->amount,
            //     'webhook_audit_id' => $webhookAudit?->id
            // ]);

        } catch (Exception $e) {
            Log::error('Failed to process charge.dispute.closed webhook', [
                'stripe_dispute_id' => $stripeDispute->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_audit_id' => $webhookAudit?->id
            ]);

            // Don't throw the exception to prevent webhook retry loops
        }
    }

    protected function convertStripeDisputeToArray($stripeDispute): array
    {
        return [
            'id' => $stripeDispute->id,
            'amount' => $stripeDispute->amount,
            'currency' => $stripeDispute->currency,
            'reason' => $stripeDispute->reason,
            'status' => $stripeDispute->status,
            'charge' => $stripeDispute->charge ?? null,
            'payment_intent' => $stripeDispute->payment_intent ?? null,
            'evidence_details' => isset($stripeDispute->evidence_details) ? [
                'due_by' => $stripeDispute->evidence_details->due_by ?? null,
                'has_evidence' => $stripeDispute->evidence_details->has_evidence ?? false,
                'past_due' => $stripeDispute->evidence_details->past_due ?? false,
                'submission_count' => $stripeDispute->evidence_details->submission_count ?? 0
            ] : null,
            'created' => $stripeDispute->created ?? null,
            'metadata' => isset($stripeDispute->metadata) ? (array) $stripeDispute->metadata : [],
            'stripe_dispute_fee' => $this->extractDisputeFeeFromBalanceTransactions(
                $stripeDispute->balance_transactions ?? []
            ),
        ];
    }

    protected function extractDisputeFeeFromBalanceTransactions($balanceTransactions): ?int
    {
        if (empty($balanceTransactions)) {
            return null;
        }

        foreach ($balanceTransactions as $txn) {
            if (($txn->type ?? '') === 'adjustment' && isset($txn->fee) && $txn->fee > 0) {
                return (int) $txn->fee;
            }
        }

        return null;
    }

    /**
     * Handle webhook processing errors with retry logic.
     * 
     * @param string $eventType The webhook event type
     * @param object $eventData The event data
     * @param Exception $exception The exception that occurred
     * @return void
     */
    protected function handleWebhookError(string $eventType, $eventData, Exception $exception): void
    {
        $errorData = [
            'event_type' => $eventType,
            'stripe_object_id' => $eventData->id ?? 'unknown',
            'tenant_id' => tenant()?->id,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'timestamp' => now()->toISOString()
        ];

        // Log the error for monitoring
        Log::error('Webhook processing error', $errorData);

        // Store webhook error for later analysis (optional)
        try {
            DB::table('webhook_processing_errors')->insert([
                'event_type' => $eventType,
                'stripe_object_id' => $eventData->id ?? null,
                'error_message' => $exception->getMessage(),
                'error_data' => json_encode($errorData),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (Exception $e) {
            // If we can't log to database, just continue
            Log::warning('Failed to store webhook error to database', [
                'original_error' => $exception->getMessage(),
                'logging_error' => $e->getMessage()
            ]);
        }
    }
}
