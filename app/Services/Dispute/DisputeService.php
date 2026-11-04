<?php

namespace App\Services\Dispute;

use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Repositories\Dispute\DisputeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;
use Stripe\Exception\ApiErrorException;
use Exception;
use Carbon\Carbon;

/**
 * DisputeService
 * 
 * Core business logic service for dispute management.
 * Orchestrates dispute operations, manages Stripe API interactions,
 * and coordinates evidence compilation.
 * 
 * Requirements: 3.4, 4.1, 4.6
 */
class DisputeService
{
    /**
     * The tenant-aware dispute repository.
     */
    protected DisputeRepository $disputeRepository;

    /**
     * Evidence compiler service.
     */
    protected EvidenceCompilerService $evidenceCompiler;

    /**
     * Stripe error handler service.
     */
    protected StripeErrorHandler $errorHandler;

    /**
     * Dispute audit logger service.
     */
    protected DisputeAuditLogger $auditLogger;

    /**
     * Dispute notification service.
     */
    protected DisputeNotificationService $notificationService;

    /**
     * Maximum retry attempts for Stripe API calls.
     */
    protected int $maxRetries = 3;

    /**
     * Initialize the dispute service.
     */
    public function __construct(
        DisputeRepository $disputeRepository,
        EvidenceCompilerService $evidenceCompiler,
        StripeErrorHandler $errorHandler,
        DisputeAuditLogger $auditLogger,
        DisputeNotificationService $notificationService
    ) {
        $this->disputeRepository = $disputeRepository;
        $this->evidenceCompiler = $evidenceCompiler;
        $this->errorHandler = $errorHandler;
        $this->auditLogger = $auditLogger;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new dispute from Stripe webhook data.
     * 
     * @param array $stripeDisputeData The dispute data from Stripe webhook
     * @return Dispute
     * @throws Exception When dispute creation fails
     */
    public function createFromStripeData(array $stripeDisputeData): ?Dispute
    {
        try {
            DB::beginTransaction();

            // Extract dispute information from Stripe data
            $disputeData = $this->extractDisputeDataFromStripe($stripeDisputeData);

            // Find associated booking — required. If no booking exists this payment
            // originated outside this environment (e.g. a local dev server) so skip.
            $booking = $this->findAssociatedBooking($stripeDisputeData);
            if (!$booking) {
                DB::rollBack();
                // Log::info('Skipping dispute creation — no matching booking found in this environment', [
                //     'stripe_dispute_id' => $stripeDisputeData['id'],
                //     'payment_intent'    => $stripeDisputeData['payment_intent'] ?? null,
                // ]);
                return null;
            }

            $disputeData['booking_id'] = $booking->id;

            // Try to find associated transaction
            $transaction = $this->findAssociatedTransaction($stripeDisputeData, $booking);
            if ($transaction) {
                $disputeData['transaction_id'] = $transaction->id;
            }

            // Create the dispute record
            $dispute = $this->disputeRepository->create($disputeData);

            // Log the dispute creation in timeline
            DisputeTimeline::logDisputeCreated($dispute, [
                'source' => 'stripe_webhook',
                'stripe_data' => $stripeDisputeData
            ]);

            // Log dispute creation for audit trail
            $this->auditLogger->logDisputeAccess($dispute, 'dispute_created', [
                'source' => 'stripe_webhook',
                'stripe_dispute_id' => $dispute->stripe_dispute_id,
                'amount' => $dispute->amount,
                'reason' => $dispute->reason,
                'booking_id' => $dispute->booking_id
            ]);

            DB::commit();

            // Log::info('Dispute created from Stripe data', [
            //     'dispute_id' => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'booking_id' => $dispute->booking_id,
            //     'amount' => $dispute->amount
            // ]);

            // Send new dispute notification
            try {
                $this->notificationService->sendNewDisputeNotification($dispute);
            } catch (Exception $e) {
                Log::warning('Failed to send new dispute notification', [
                    'dispute_id' => $dispute->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the dispute creation if notification fails
            }

            return $dispute;

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create dispute from Stripe data', [
                'stripe_dispute_id' => $stripeDisputeData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception("Failed to create dispute: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Synchronize dispute data with Stripe API.
     * 
     * @param string $disputeId The local dispute ID or Stripe dispute ID
     * @return Dispute
     * @throws Exception When synchronization fails
     */
    public function syncWithStripe(int|string $disputeId): Dispute
    {
        try {
            // Determine if this is a local ID or Stripe ID
            $dispute = is_numeric($disputeId)
                ? $this->disputeRepository->findById((int) $disputeId)
                : $this->disputeRepository->findByStripeId($disputeId);

            if (!$dispute) {
                throw new Exception("Dispute not found: {$disputeId}");
            }

            // Retrieve current data from Stripe
            $stripeDispute = $this->retrieveDisputeFromStripe($dispute->stripe_dispute_id);

            // Store original values for change detection
            $originalData = [
                'status' => $dispute->status,
                'amount' => $dispute->amount,
                'currency' => $dispute->currency,
                'reason' => $dispute->reason,
                'evidence_due_by' => $dispute->evidence_due_by
            ];

            // Update local dispute with Stripe data
            $updateData = $this->extractDisputeDataFromStripe($stripeDispute);
            unset($updateData['stripe_dispute_id']); // Don't update the ID
            // balance_transactions aren't expanded in API retrievals, so don't overwrite
            // a previously stored fee with null.
            if ($updateData['stripe_dispute_fee'] === null) {
                unset($updateData['stripe_dispute_fee']);
            }

            $dispute = $this->disputeRepository->update($dispute->id, $updateData);

            // Detect changes for audit logging
            $changes = [];
            foreach ($originalData as $field => $originalValue) {
                $newValue = $dispute->$field;
                if ($originalValue != $newValue) {
                    $changes[$field] = [
                        'from' => $originalValue,
                        'to' => $newValue
                    ];
                }
            }

            // Log synchronization with detailed change information
            DisputeTimeline::logSynchronization($dispute, [
                'sync_source' => 'stripe_api',
                'sync_timestamp' => now()->toISOString(),
                'changes_detected' => $changes,
                'stripe_data' => $stripeDispute,
                'api_call_successful' => true,
                'evidence_details' => $stripeDispute['evidence_details'] ?? null
            ]);

            // If significant changes occurred, log them separately for visibility
            if (!empty($changes)) {
                DisputeTimeline::logSystemAction($dispute, [
                    'action_description' => 'Dispute data synchronized with changes from Stripe',
                    'changes' => $changes,
                    'sync_trigger' => 'webhook_processing',
                    'stripe_last_updated' => isset($stripeDispute['created']) ? 
                        Carbon::createFromTimestamp($stripeDispute['created'])->toISOString() : null
                ]);
            }

            // Log::info('Dispute synchronized with Stripe', [
            //     'dispute_id' => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'changes_count' => count($changes),
            //     'changes' => $changes,
            //     'sync_successful' => true
            // ]);

            return $dispute;

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during dispute sync', [
                'dispute_id' => $disputeId,
                'error_code' => $e->getStripeCode(),
                'error_message' => $e->getMessage(),
                'error_param' => $e->getError()?->param ?? null
            ]);

            // Log the sync failure in timeline if we have a dispute
            if (isset($dispute)) {
                DisputeTimeline::logSystemAction($dispute, [
                    'action_description' => 'Dispute synchronization failed',
                    'error_type' => 'stripe_api_error',
                    'error_code' => $e->getStripeCode(),
                    'error_message' => $e->getMessage(),
                    'sync_attempted_at' => now()->toISOString()
                ]);
            }

            throw new Exception("Stripe API error: {$e->getMessage()}", 0, $e);

        } catch (Exception $e) {
            Log::error('Failed to sync dispute with Stripe', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ]);

            // Log the sync failure in timeline if we have a dispute
            if (isset($dispute)) {
                DisputeTimeline::logSystemAction($dispute, [
                    'action_description' => 'Dispute synchronization failed',
                    'error_type' => 'general_error',
                    'error_message' => $e->getMessage(),
                    'sync_attempted_at' => now()->toISOString()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Submit evidence for a dispute to Stripe.
     * 
     * @param int $disputeId The local dispute ID
     * @param array $evidenceData The evidence data to submit
     * @param array $files Optional files to upload
     * @return bool True if submission was successful
     * @throws Exception When evidence submission fails
     */
    public function submitEvidence(int $disputeId, array $evidenceData, array $files = []): bool
    {
        try {
            DB::beginTransaction();

            $dispute = $this->disputeRepository->findById($disputeId);
            if (!$dispute) {
                throw new Exception("Dispute not found: {$disputeId}");
            }

            // Validate dispute status
            if (!in_array($dispute->status, ['warning_needs_response', 'under_review'])) {
                throw new Exception("Cannot submit evidence for dispute with status: {$dispute->status}");
            }

            // Process and upload files to Stripe
            $stripeFileIds = [];
            foreach ($files as $evidenceType => $file) {
                if ($file instanceof UploadedFile) {
                    $stripeFileId = $this->uploadFileToStripe($file);
                    $stripeFileIds[$evidenceType] = $stripeFileId;

                    // Store evidence record locally
                    $this->storeEvidenceRecord($dispute, $evidenceType, $file, $stripeFileId);
                }
            }

            // Prepare evidence for Stripe submission
            $stripeEvidenceData = $this->prepareStripeEvidenceData($evidenceData, $stripeFileIds);

            // Submit evidence to Stripe
            $evidenceUpdateParams = [
                'evidence' => $stripeEvidenceData,
                'submit'   => true,
            ];
            $result = $this->errorHandler->executeWithErrorHandling(
                fn() => app(PaymentGatewayInterface::class)->updateDispute($dispute->stripe_dispute_id, $evidenceUpdateParams),
                [
                    'operation_type' => 'evidence_submit',
                    'dispute_id' => $dispute->stripe_dispute_id,
                    'evidence_types' => array_keys($evidenceData),
                    'tenant_id' => session('tenant_id')
                ]
            );

            // Update dispute status if changed
            if (isset($result['status']) && $result['status'] !== $dispute->status) {
                $this->disputeRepository->update($dispute->id, [
                    'status' => $result['status']
                ]);
            }

            // Log evidence submission
            DisputeTimeline::logEvidenceSubmission($dispute, [
                'evidence_types' => array_keys($evidenceData),
                'file_count' => count($files),
                'submitted_at' => now(),
                'stripe_response' => $result
            ]);

            // Log evidence submission for audit trail
            $this->auditLogger->logEvidenceSubmission($dispute, $evidenceData, $files);

            DB::commit();

            // Log::info('Evidence submitted successfully', [
            //     'dispute_id' => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'evidence_types' => array_keys($evidenceData),
            //     'file_count' => count($files)
            // ]);

            return true;

        } catch (ApiErrorException $e) {
            DB::rollBack();
            
            Log::error('Stripe API error during evidence submission', [
                'dispute_id' => $disputeId,
                'error_code' => $e->getStripeCode(),
                'error_message' => $e->getMessage()
            ]);

            throw new Exception("Failed to submit evidence to Stripe: {$e->getMessage()}", 0, $e);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to submit evidence', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get disputes for the current tenant with filtering.
     * 
     * @param array $filters Optional filters
     * @param array $with Optional relationships to eager load
     * @return Collection
     */
    public function getDisputesForTenant(array $filters = [], array $with = []): Collection
    {
        return $this->disputeRepository->findAll($filters, $with);
    }

    /**
     * Get paginated disputes for the current tenant.
     * 
     * @param array $filters Optional filters
     * @param int $perPage Items per page
     * @param array $with Optional relationships to eager load
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getPaginatedDisputesForTenant(array $filters = [], int $perPage = 25, array $with = [])
    {
        return $this->disputeRepository->getPaginated($filters, $perPage, $with);
    }

    /**
     * Get a specific dispute by ID.
     * 
     * @param int $disputeId The dispute ID
     * @param array $with Optional relationships to eager load
     * @return Dispute|null
     */
    public function getDisputeById(int $disputeId, array $with = []): ?Dispute
    {
        return $this->disputeRepository->findById($disputeId, $with);
    }

    /**
     * Get disputes by booking ID.
     * 
     * @param int $bookingId The booking ID
     * @param array $with Optional relationships to eager load
     * @return Collection
     */
    public function getDisputesByBookingId(int $bookingId, array $with = []): Collection
    {
        return $this->disputeRepository->findByBookingId($bookingId, $with);
    }

    /**
     * Get active disputes requiring attention.
     * 
     * @param int $daysUntilDeadline Days until deadline to consider urgent
     * @return Collection
     */
    public function getActiveDisputesRequiringAttention(int $daysUntilDeadline = 3): Collection
    {
        return $this->disputeRepository->getActiveDisputesRequiringAttention($daysUntilDeadline);
    }

    /**
     * Update dispute status.
     * 
     * @param int $disputeId The dispute ID
     * @param string $status The new status
     * @param array $metadata Optional metadata
     * @return Dispute
     * @throws Exception When update fails
     */
    public function updateDisputeStatus(int $disputeId, string $status, array $metadata = []): Dispute
    {
        try {
            $dispute = $this->disputeRepository->findById($disputeId);
            if (!$dispute) {
                throw new Exception("Dispute not found: {$disputeId}");
            }

            $oldStatus = $dispute->status;

            $updateData = ['status' => $status];

            if (hasSetting('DISPUTE_REVERSAL')) {
                if ($status === 'lost' && $oldStatus !== 'lost' && !$dispute->stripe_reversal_id) {
                    // Manually marking as lost — stamp the loss timestamp and record the
                    // dispute amount as the reversed amount (cents). Only applied when no
                    // real Stripe reversal exists (i.e. webhook hasn't already handled it).
                    $updateData['lost_at']         = now();
                    $updateData['reversed_amount'] = (int) round($dispute->amount * 100);
                } elseif ($oldStatus === 'lost' && $status !== 'lost') {
                    // Reverting from lost — undo the manual loss stamp so the record no
                    // longer appears in the Payment Breakdown dispute reversals query.
                    $updateData['lost_at']            = null;
                    $updateData['reversed_amount']    = null;
                    $updateData['stripe_reversal_id'] = null;
                }
            }

            $dispute = $this->disputeRepository->update($disputeId, $updateData);

            // Log status change
            DisputeTimeline::logStatusChange($dispute, $oldStatus, $status, $metadata);

            // Log status change for audit trail
            $this->auditLogger->logDisputeModification($dispute, 'status_change', [
                'status' => ['from' => $oldStatus, 'to' => $status]
            ], $metadata);

            // Send status change notification
            try {
                $this->notificationService->sendStatusChangeNotification($dispute, $oldStatus, $status, $metadata);
            } catch (Exception $e) {
                Log::warning('Failed to send status change notification', [
                    'dispute_id' => $disputeId,
                    'status_change' => "{$oldStatus} -> {$status}",
                    'error' => $e->getMessage()
                ]);
                // Don't fail the status update if notification fails
            }

            // Log::info('Dispute status updated', [
            //     'dispute_id' => $disputeId,
            //     'old_status' => $oldStatus,
            //     'new_status' => $status,
            //     'metadata' => $metadata
            // ]);

            return $dispute;

        } catch (Exception $e) {
            Log::error('Failed to update dispute status', [
                'dispute_id' => $disputeId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Validate evidence file before upload.
     * 
     * @param UploadedFile $file The file to validate
     * @return array Validation result
     */
    public function validateEvidenceFile(UploadedFile $file): array
    {
        $maxSize = config('dispute.evidence.max_file_size', 10 * 1024 * 1024); // 10MB default
        $allowedTypes = config('dispute.evidence.allowed_types', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

        $errors = [];

        // Check file size
        if ($file->getSize() > $maxSize) {
            $errors[] = "File size exceeds maximum allowed size of " . ($maxSize / 1024 / 1024) . "MB";
        }

        // Check file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = "File type '{$extension}' is not supported. Allowed types: " . implode(', ', $allowedTypes);
        }

        // Check if file is valid
        if (!$file->isValid()) {
            $errors[] = "File upload failed or file is corrupted";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get dispute statistics for the current tenant.
     * 
     * @param array $dateRange Optional date range
     * @return array
     */
    public function getDisputeStatistics(array $dateRange = []): array
    {
        return $this->disputeRepository->getStatistics($dateRange);
    }

    /**
     * Get dispute volume trends over time for analytics dashboard
     * Requirements: 12.1 - Generate dispute volume trends over time
     */
    public function getDisputeVolumeTrends(array $dateRange = [], string $groupBy = 'day'): array
    {
        return $this->disputeRepository->getDisputeVolumeTrends($dateRange, $groupBy);
    }

    /**
     * Get detailed dispute reason breakdown and categorization
     * Requirements: 12.2 - Show breakdown by dispute category and reason codes
     */
    public function getDisputeReasonBreakdown(array $dateRange = []): array
    {
        return $this->disputeRepository->getDisputeReasonBreakdown($dateRange);
    }

    /**
     * Calculate financial impact including dispute fees and lost revenue
     * Requirements: 12.3 - Calculate financial impact including dispute fees and lost revenue
     */
    public function getFinancialImpactAnalysis(array $dateRange = []): array
    {
        return $this->disputeRepository->getFinancialImpactAnalysis($dateRange);
    }

    /**
     * Get comprehensive analytics data for dashboard
     * Combines all analytics methods for complete reporting
     */
    public function getComprehensiveAnalytics(array $dateRange = [], string $trendsGroupBy = 'day', ?string $status = null): array
    {
        return [
            'basic_statistics' => $this->getDisputeStatistics($dateRange),
            'volume_trends'    => $this->getDisputeVolumeTrends($dateRange, $trendsGroupBy),
            'reason_breakdown' => $this->getDisputeReasonBreakdown($dateRange),
            'financial_impact' => $this->getFinancialImpactAnalysis($dateRange),
            'dispute_list'     => $this->disputeRepository->getDisputeListForAnalytics($dateRange, $status),
            'date_range'       => $dateRange,
            'trends_group_by'  => $trendsGroupBy,
        ];
    }

    /**
     * Reverse the original transfer after a dispute loss.
     *
     * Calls transfers->createReversal() on the tr_xxx transfer, pulling the net
     * transfer amount (ticket cost only, excluding the platform application fee)
     * back from the connected account balance to the platform.
     *
     * e.g. charge $161, platform fee $14.40 → reversal $146.60 (transfer.amount).
     * The operator is only debited what they actually received — the platform
     * absorbs the application fee as a dispute cost.
     *
     * @param Dispute $dispute        The local dispute model (post-sync)
     * @param object  $stripeDispute  The raw Stripe dispute object from the webhook
     */
    public function handleDisputeLoss(Dispute $dispute, object $stripeDispute): void
    {
        try {
            $chargeId = $stripeDispute->charge ?? null;

            if (!$chargeId) {
                Log::warning('handleDisputeLoss: no charge ID on Stripe dispute, skipping', [
                    'dispute_id'        => $dispute->id,
                    'stripe_dispute_id' => $dispute->stripe_dispute_id,
                ]);
                return;
            }

            // Retrieve charge and expand the transfer to get its ID and net amount in one call
            $retrieveOptions = ['expand' => ['transfer']];
            $charge   = app(PaymentGatewayInterface::class)->retrieveCharge($chargeId, $retrieveOptions);
            $transfer = $charge->transfer ?? null;

            if (!$transfer || !isset($transfer->id)) {
                // Payout Simplification (2027+): a platform-held charge never carries a
                // transfer at all — its full amount already sits on the platform's own Stripe
                // balance, so there is nothing to reverse from a connected account. This will
                // fire on every lost dispute for a platform-held booking, so it's logged as an
                // expected outcome (info), not a warning. For a destination charge, a missing
                // transfer has no other explanation and is a genuine anomaly worth surfacing
                // loudly (error) — before this feature shipped, that was the only possibility.
                $mode = PaymentModeInference::fromStripeObject($charge);

                if ($mode === PaymentModeResolver::PLATFORM_HELD) {
                    Log::info('handleDisputeLoss: platform-held charge has no transfer to reverse — money never left the platform balance', [
                        'dispute_id' => $dispute->id,
                        'charge_id'  => $chargeId,
                    ]);

                    DisputeTimeline::logSystemAction($dispute, [
                        'action_description' => 'Dispute loss transfer reversal not applicable — charge settled platform-held, no transfer ever existed',
                        'charge_id'          => $chargeId,
                    ]);
                    return;
                }

                Log::error('handleDisputeLoss: no transfer on charge, skipping', [
                    'dispute_id' => $dispute->id,
                    'charge_id'  => $chargeId,
                ]);

                DisputeTimeline::logSystemAction($dispute, [
                    'action_description' => 'Dispute loss transfer reversal skipped — no transfer found on charge',
                    'charge_id'          => $chargeId,
                ]);
                return;
            }

            // Use the transfer amount (ticket cost net of platform fee), not the
            // full dispute amount — the operator is only liable for what they received.
            $reversalAmount = $transfer->amount;

            $reversalParams = [
                'amount'   => $reversalAmount,
                'metadata' => [
                    'reason'            => 'dispute_lost',
                    'stripe_dispute_id' => $dispute->stripe_dispute_id,
                    'dispute_id'        => (string) $dispute->id,
                    'platform_charge'   => $chargeId,
                    'tenant_id'         => tenant()->id,
                ],
            ];
            $reversal = app(PaymentGatewayInterface::class)->reverseTransfer($transfer->id, $reversalParams);

            // Persist the reversal details so they can be queried directly
            // without hitting the Stripe API or parsing timeline JSON.
            $dispute->update([
                'stripe_reversal_id' => $reversal->id,
                'reversed_amount'    => $reversalAmount,
                'lost_at'            => now(),
            ]);

            // Log::info('Transfer reversal created for lost dispute', [
            //     'dispute_id'        => $dispute->id,
            //     'stripe_dispute_id' => $dispute->stripe_dispute_id,
            //     'transfer_id'       => $transfer->id,
            //     'reversal_id'       => $reversal->id,
            //     'amount_cents'      => $reversalAmount,
            // ]);

            DisputeTimeline::logSystemAction($dispute, [
                'action_description' => 'Transfer reversal created after dispute loss (ticket cost only)',
                'transfer_id'        => $transfer->id,
                'reversal_id'        => $reversal->id,
                'amount_reversed'    => $reversalAmount / 100,
                'charge_id'          => $chargeId,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during dispute loss transfer reversal', [
                'dispute_id'        => $dispute->id,
                'stripe_dispute_id' => $dispute->stripe_dispute_id,
                'error'             => $e->getMessage(),
                'stripe_code'       => $e->getStripeCode(),
            ]);

            DisputeTimeline::logSystemAction($dispute, [
                'action_description' => 'Transfer reversal failed (Stripe API error)',
                'error'              => $e->getMessage(),
            ]);

        } catch (Exception $e) {
            Log::error('Unexpected error during dispute loss transfer reversal', [
                'dispute_id'        => $dispute->id,
                'stripe_dispute_id' => $dispute->stripe_dispute_id,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract dispute data from Stripe dispute object.
     *
     * @param array $stripeDispute The Stripe dispute data
     * @return array
     */
    protected function extractDisputeDataFromStripe(array $stripeDispute): array
    {
        return [
            'stripe_dispute_id'  => $stripeDispute['id'],
            'amount'             => $stripeDispute['amount'] / 100,
            'currency'           => strtoupper($stripeDispute['currency']),
            'reason'             => $stripeDispute['reason'],
            'status'             => $stripeDispute['status'],
            'evidence_due_by'    => isset($stripeDispute['evidence_details']['due_by'])
                ? Carbon::createFromTimestamp($stripeDispute['evidence_details']['due_by'])
                : null,
            'stripe_dispute_fee' => $stripeDispute['stripe_dispute_fee'] ?? null,
        ];
    }

    /**
     * Find associated booking from Stripe dispute data.
     * 
     * @param array $stripeDispute The Stripe dispute data
     * @return TourBooking|null
     */
    protected function findAssociatedBooking(array $stripeDispute): ?TourBooking
    {
        // transaction_number stores the payment intent ID (pi_xxx) — use that to find the booking.
        if (!empty($stripeDispute['payment_intent'])) {
            $transaction = Transaction::where('transaction_number', $stripeDispute['payment_intent'])
                ->where('via', 'Card')
                ->first();
            if ($transaction?->tour_booking_id) {
                return TourBooking::find($transaction->tour_booking_id);
            }
        }

        Log::warning('Could not find associated booking for dispute', [
            'stripe_dispute_id' => $stripeDispute['id'],
            'payment_intent'    => $stripeDispute['payment_intent'] ?? null,
            'charge'            => $stripeDispute['charge'] ?? null,
        ]);

        return null;
    }

    /**
     * Find associated transaction from Stripe dispute data.
     * 
     * @param array $stripeDispute The Stripe dispute data
     * @param TourBooking $booking The associated booking
     * @return Transaction|null
     */
    protected function findAssociatedTransaction(array $stripeDispute, TourBooking $booking): ?Transaction
    {
        // transaction_number stores the payment intent ID (pi_xxx).
        if (!empty($stripeDispute['payment_intent'])) {
            $transaction = Transaction::where('tour_booking_id', $booking->id)
                ->where('transaction_number', $stripeDispute['payment_intent'])
                ->first();
            if ($transaction) {
                return $transaction;
            }
        }

        // Fallback: most recent completed card transaction for this booking.
        return Transaction::where('tour_booking_id', $booking->id)
            ->where('via', 'Card')
            ->where('status', 'Completed')
            ->first();
    }

    /**
     * Retrieve dispute from Stripe API.
     * 
     * @param string $stripeDisputeId The Stripe dispute ID
     * @return array
     * @throws ApiErrorException
     */
    protected function retrieveDisputeFromStripe(string $stripeDisputeId): array
    {
        return $this->errorHandler->executeWithErrorHandling(
            fn() => app(PaymentGatewayInterface::class)->retrieveDispute($stripeDisputeId)->toArray(),
            [
                'operation_type' => 'dispute_retrieve',
                'dispute_id' => $stripeDisputeId,
                'tenant_id' => session('tenant_id')
            ],
            true // Enable fallback
        );
    }

    /**
     * Upload file to Stripe and return file ID.
     * 
     * @param UploadedFile $file The file to upload
     * @return string The Stripe file ID
     * @throws Exception When upload fails
     */
    protected function uploadFileToStripe(UploadedFile $file): string
    {
        try {
            // Validate file first
            $validation = $this->validateEvidenceFile($file);
            if (!$validation['valid']) {
                throw new Exception("File validation failed: " . implode(', ', $validation['errors']));
            }

            // Upload to Stripe
            $fileParams = [
                'file'    => fopen($file->getPathname(), 'r'),
                'purpose' => 'dispute_evidence',
            ];
            $stripeFile = $this->errorHandler->executeWithErrorHandling(
                fn() => app(PaymentGatewayInterface::class)->createFile($fileParams),
                [
                    'operation_type' => 'file_upload',
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'tenant_id' => session('tenant_id')
                ]
            );

            // Log::info('File uploaded to Stripe', [
            //     'stripe_file_id' => $stripeFile->id,
            //     'original_name' => $file->getClientOriginalName(),
            //     'size' => $file->getSize()
            // ]);

            return $stripeFile->id;

        } catch (ApiErrorException $e) {
            Log::error('Stripe file upload failed', [
                'file_name' => $file->getClientOriginalName(),
                'error_code' => $e->getStripeCode(),
                'error_message' => $e->getMessage()
            ]);

            throw new Exception("Failed to upload file to Stripe: {$e->getMessage()}", 0, $e);

        } catch (Exception $e) {
            Log::error('File upload failed', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Store evidence record locally.
     * 
     * @param Dispute $dispute The dispute
     * @param string $evidenceType The evidence type
     * @param UploadedFile $file The uploaded file
     * @param string $stripeFileId The Stripe file ID
     * @return DisputeEvidence
     */
    protected function storeEvidenceRecord(
        Dispute $dispute, 
        string $evidenceType, 
        UploadedFile $file, 
        string $stripeFileId
    ): DisputeEvidence {
        $tenantId = tenancy()->tenant->id ?? 'default';
        $storagePath = $file->store('evidence/' . $tenantId . '/' . $dispute->id, 's3');

        return DisputeEvidence::create([
            'dispute_id' => $dispute->id,
            'evidence_type' => $evidenceType,
            'file_path' => $storagePath,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'content_type' => $file->getMimeType(),
            'stripe_file_id' => $stripeFileId,
            'submitted_at' => now(),
            'created_by' => Auth::id()
        ]);
    }

    /**
     * Prepare evidence data for Stripe submission.
     * 
     * @param array $evidenceData The evidence data
     * @param array $stripeFileIds The Stripe file IDs
     * @return array
     */
    protected function prepareStripeEvidenceData(array $evidenceData, array $stripeFileIds): array
    {
        $stripeEvidence = [];

        // Map evidence types to Stripe fields
        $evidenceMapping = [
            'receipt' => 'receipt',
            'customer_communication' => 'customer_communication',
            'service_documentation' => 'service_documentation',
            'shipping_documentation' => 'shipping_documentation',
            'duplicate_charge_documentation' => 'duplicate_charge_documentation',
            'refund_policy' => 'refund_policy',
            'cancellation_policy' => 'cancellation_policy',
            'customer_signature' => 'customer_signature',
            'uncategorized_file' => 'uncategorized_file'
        ];

        // Add file evidence
        foreach ($stripeFileIds as $evidenceType => $fileId) {
            $stripeField = $evidenceMapping[$evidenceType] ?? 'uncategorized_file';
            $stripeEvidence[$stripeField] = $fileId;
        }

        // Add text evidence
        foreach ($evidenceData as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $stripeEvidence[$key] = $value;
            }
        }

        return $stripeEvidence;
    }

    /**
     * Execute a function with retry logic for Stripe API calls.
     * 
     * @param callable $operation The operation to execute
     * @return mixed
     * @throws Exception When max retries exceeded
     */
    protected function executeWithRetry(callable $operation)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $operation();
            } catch (ApiErrorException $e) {
                $lastException = $e;
                $attempt++;

                // Only retry on rate limit or network errors
                if ($e->getStripeCode() === 'rate_limit' && $attempt < $this->maxRetries) {
                    $delay = min(pow(2, $attempt), 60); // Exponential backoff, max 60s
                    Log::warning("Stripe rate limit hit, retrying in {$delay} seconds", [
                        'attempt' => $attempt,
                        'max_retries' => $this->maxRetries
                    ]);
                    sleep($delay);
                    continue;
                }

                // Don't retry on other errors
                break;
            }
        }

        throw new Exception("Max retries exceeded for Stripe operation", 0, $lastException);
    }
}