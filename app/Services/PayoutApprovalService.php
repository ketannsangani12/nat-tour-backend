<?php

namespace App\Services;

use App\Models\PendingPayout;
use App\Models\PendingPayoutItem;
use App\Models\PayoutApprovalLog;
use App\Models\AutoPayoutRule;
use App\Models\AutoPayout;
use App\Models\AutoPayoutItem;
use App\Models\PayoutAdjustment;
use App\Models\TourBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;
use Stripe\Stripe;
use Stripe\Payout;
use Stripe\Account;
use Stripe\Balance;
use App\Mail\AutoPayout\PayoutNotification;
use App\Services\Payments\PaymentModeResolver;
use App\Models\Admin;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PayoutApprovalService
{
    // Number of failed attempts before a payout is moved to 'failed' status
    // and requires manual re-approval. With exponential backoff (capped at
    // 24h between attempts), 20 attempts span roughly 5 days of trying.
    const MAX_RETRY_COUNT = 20;

    // Maximum minutes between retries. Backoff = min(MAX_BACKOFF_MINUTES, 2^retry_count).
    const MAX_BACKOFF_MINUTES = 1440;

    public function __construct()
    {
        // Initialize Stripe with API key
        Stripe::setApiKey(getStripeSecretKey());
    }

    /**
     * Calculate payout amount for a specific Stripe account
     *
     * @param AutoPayoutRule $rule
     * @param string $stripeAccountId
     * @return array
     */
    public function calculateAccountPayoutAmount(AutoPayoutRule $rule, string $stripeAccountId): array
    {
        $timezone = tourOperator()->timezone;
        $now = Carbon::now($timezone);
        $bufferDays = $rule->buffer_days ?? 0;
        // Fallback when last_executed_at is null (first run): use the rule's
        // configured start_date as the floor so the operator controls how far
        // back the first payout reaches. Falls through to now() when no start
        // date is set, so a misconfigured rule never sweeps historical bookings.
        // Once last_executed_at is set, start_date no longer applies -- the
        // window advances from the previous run as normal.
        // Earlier fallbacks (created_at, then now()->subMonth()) caused the first
        // run after enabling to sweep months of historical bookings into one payout.
        //
        // Resolve the floor defensively: an unparseable start_date must never
        // throw and fail the scheduler tick -- fall through to now() instead.
        // A future start_date is allowed (it simply yields an empty window and
        // pays nothing), so no future-date guard is enforced here.
        $fallback = $now;
        if (!empty($rule->start_date)) {
            try {
                $fallback = Carbon::parse($rule->start_date, $timezone);
            } catch (\Throwable $e) {
                Log::warning("PayoutApprovalService: invalid start_date on rule {$rule->id}; using now() as floor.", [
                    'rule_id' => $rule->id,
                    'start_date' => (string) $rule->start_date,
                    'error' => $e->getMessage(),
                ]);
                $fallback = $now;
            }
        }

        $lastExecuted = $rule->last_executed_at
            ? Carbon::parse($rule->last_executed_at, $timezone)
            : $fallback;

        // Extend cycleStart back by buffer_days so previously-buffered bookings
        // are included. The payout_made=0 filter prevents double-paying.
        $cycleStart = $lastExecuted->copy()->subDays($bufferDays)->format('Y-m-d H:i:s');
        $bufferEnd = $now->copy()->subDays($bufferDays);
        // cycleEnd is bounded only by the buffer window. The payout_made=0
        // filter already prevents double-paying, so capping by
        // next_execution_at is unnecessary and causes the scheduler to
        // permanently stall if the execution window stops advancing.
        $cycleEnd = $bufferEnd->format('Y-m-d H:i:s');

        $query = DB::table('transactions as tra')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tra.tour_booking_id')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->whereIn('tra.status', ['Completed', 'Refund'])
            ->where('tra.via', 'Card')
            // Payout Simplification (2027+): 'via' => 'Card' is written by BOTH the Card and
            // Payment Link channels, and neither guarantees the money actually landed in this
            // connected account — a platform_held transaction settles entirely on the
            // platform's own Stripe balance. Auto Payout must only sweep DESTINATION-mode
            // money; including a platform_held row here would pay the operator for funds
            // they never received via Stripe, and the pre-send balance check would not catch
            // it if the account happens to hold unrelated legitimate balance.
            ->where('tra.payment_mode', PaymentModeResolver::DESTINATION)
            ->where('tra.request_from', '!=', 'Insert')
            ->where('tbm.comments', 'not like', '#%') // Exclude internal/test bookings prefixed with #
            ->where('tbm.payout_made', 0)
            ->whereBetween(DB::raw("TIMESTAMP(tst.date, tst.time_from)"), [$cycleStart, $cycleEnd])
            ->select(
                'tra.amount',
                'tra.fee_amount',
                'tra.status',
                'tra.tour_booking_id',
                'tbm.total as booking_total'
            )
            ->get();

        $totalNet = 0;
        $totalAmount = 0;
        $totalFees = 0;
        $sortedDataArray = [];

        foreach ($query as $value) {
            if (isset($sortedDataArray[$value->tour_booking_id])) {
                if ($value->status == "Completed") {
                    $sortedDataArray[$value->tour_booking_id]['amount'] = round($sortedDataArray[$value->tour_booking_id]['amount'] + $value->amount, 2);
                    $sortedDataArray[$value->tour_booking_id]['fee_amount'] = round($sortedDataArray[$value->tour_booking_id]['fee_amount'] + $value->fee_amount, 2);
                }
                if ($value->status == "Refund") {
                    $sortedDataArray[$value->tour_booking_id]['refund'] = round($sortedDataArray[$value->tour_booking_id]['refund'] + $value->amount, 2);
                    $sortedDataArray[$value->tour_booking_id]['refund_fee_amount'] = round($sortedDataArray[$value->tour_booking_id]['refund_fee_amount'] + $value->fee_amount, 2);
                    $sortedDataArray[$value->tour_booking_id]['refund_remove_fee'] = round(
                        $sortedDataArray[$value->tour_booking_id]['refund'] - $sortedDataArray[$value->tour_booking_id]['refund_fee_amount'], 2);
                }
                $sortedDataArray[$value->tour_booking_id]['net_total'] = round(
                    $sortedDataArray[$value->tour_booking_id]['amount'] -
                    $sortedDataArray[$value->tour_booking_id]['refund_remove_fee'] -
                    $sortedDataArray[$value->tour_booking_id]['fee_amount'], 2);
            } else {
                $newFilteredData = [];
                $newFilteredData['id'] = $value->tour_booking_id;
                $newFilteredData['amount'] = round(($value->status == "Completed") ? $value->amount : 0, 2);
                $newFilteredData['fee_amount'] = round(($value->status == "Completed") ? $value->fee_amount : 0, 2);
                $newFilteredData['refund'] = round(($value->status == "Refund") ? $value->amount : 0, 2);
                $newFilteredData['refund_fee_amount'] = round(($value->status == "Refund") ? $value->fee_amount : 0, 2);
                $newFilteredData['refund_remove_fee'] = round($newFilteredData['refund'] - $newFilteredData['refund_fee_amount'], 2);
                $newFilteredData['net_total'] = round(
                    $newFilteredData['amount'] - $newFilteredData['refund_remove_fee'] - $newFilteredData['fee_amount'], 2);
                $newFilteredData['booking_total'] = $value->booking_total;
                $sortedDataArray[$value->tour_booking_id] = $newFilteredData;
            }
        }

        if (!$rule->include_partial_payments) {
            $sortedDataArray = array_filter($sortedDataArray, function ($booking) {
                return $booking['amount'] >= (float) $booking['booking_total'];
            });
        }

        foreach ($sortedDataArray as $value) {
            $totalNet += (float)$value['net_total'];
            $totalAmount += (float)$value['amount'];
            $totalFees += (float)$value['fee_amount'];
        }

        // Include pending post-payout adjustments in the total.
        // Detection of new adjustments is handled by the caller (command/controller)
        // before invoking this method, not here -- to avoid running batch
        // detection queries on dashboard/report page loads.
        $adjustmentService = app(PayoutAdjustmentService::class);
        $adjustmentTotals = $adjustmentService->getPendingAdjustmentTotal();
        $adjustmentNet = $adjustmentTotals['net_adjustment'];

        $finalNet = round($totalNet + $adjustmentNet, 2);
        if ($finalNet < 0) {
            Log::warning("PayoutApprovalService: Adjustments exceed payout total. Booking net: {$totalNet}, Adjustment net: {$adjustmentNet}. Capping at 0.");
            $finalNet = 0;
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'platform_fee' => round($totalFees, 2),
            'net_amount' => $finalNet,
            'booking_count' => count($sortedDataArray),
            'booking_ids' => array_keys($sortedDataArray),
            'booking_details' => $sortedDataArray,
            // The exact window these bookings were collected from, persisted onto the
            // payout so it stays answerable after the rule's window advances. Matched
            // against the booking's tour datetime, not its creation date.
            'period_start' => $cycleStart,
            'period_end' => $cycleEnd,
            'adjustment_total' => $adjustmentNet,
            'adjustment_count' => $adjustmentTotals['count'],
            'adjustment_credit_total' => $adjustmentTotals['credit_total'],
            'adjustment_debit_total' => $adjustmentTotals['debit_total'],
            // Snapshot the exact adjustment ids folded into net_amount above so
            // execution applies precisely these (and recalculation re-validates
            // against the same set) rather than re-sweeping all pending rows.
            'adjustment_ids' => $adjustmentTotals['ids'],
        ];
    }

    /**
     * Verify that a Stripe account is set to manual payouts.
     *
     * @param string $stripeAccountId
     * @return array
     */
    public function verifyManualPayoutSchedule(string $stripeAccountId): array
    {
        $account = Account::retrieve($stripeAccountId);
        $interval = $account->settings->payouts->schedule->interval;
        $isManual = $interval === 'manual';

        return [
            'is_manual' => $isManual,
            'current_interval' => $interval,
            'stripe_account_id' => $stripeAccountId,
        ];
    }

    /**
     * Ensure a Stripe account is set to manual payouts before executing.
     * Attempts to auto-correct if not manual.
     *
     * @param string $stripeAccountId
     * @return void
     * @throws Exception
     */
    private function ensureManualPayoutSchedule(string $stripeAccountId): void
    {
        $scheduleCheck = $this->verifyManualPayoutSchedule($stripeAccountId);

        if (!$scheduleCheck['is_manual']) {
            Log::warning("Account {$stripeAccountId} payout schedule is '{$scheduleCheck['current_interval']}', attempting to set to manual");

            try {
                Account::update($stripeAccountId, [
                    'settings' => ['payouts' => ['schedule' => ['interval' => 'manual']]]
                ]);
                Log::info("Successfully set account {$stripeAccountId} to manual payouts");
            } catch (\Exception $e) {
                throw new Exception(
                    "Cannot execute payout: Stripe account {$stripeAccountId} is set to '{$scheduleCheck['current_interval']}' payouts "
                    . "and we could not switch it to manual. Please update the payout schedule to manual in Stripe dashboard before retrying."
                );
            }
        }
    }

    /**
     * Execute a payout immediately (without approval workflow)
     *
     * @param AutoPayoutRule $rule
     * @param string $stripeAccountId
     * @param array $payoutData
     * @return array
     */
    public function executeImmediatePayout(AutoPayoutRule $rule, string $stripeAccountId, array $payoutData): array
    {
        if ($payoutData['net_amount'] <= 0) {
            throw new Exception("Cannot execute payout with non-positive amount: {$payoutData['net_amount']}");
        }

        if ($rule->max_payout_amount && $payoutData['net_amount'] > (float) $rule->max_payout_amount) {
            throw new Exception("Payout amount {$payoutData['net_amount']} exceeds maximum ceiling of {$rule->max_payout_amount}");
        }

        // Verify account is set to manual payouts before sending money
        $this->ensureManualPayoutSchedule($stripeAccountId);

        $externalAccounts = Account::allExternalAccounts(
            $stripeAccountId,
            ['object' => 'bank_account', 'limit' => 1]
        );

        if (empty($externalAccounts->data)) {
            throw new Exception("No bank account found for account {$stripeAccountId}");
        }

        $bankAccountId = $externalAccounts->data[0]->id;

        // Retrieve the balance LAST -- immediately before Payout::create -- so the
        // sufficiency check reflects the balance at the moment of payout, not a
        // snapshot taken before the manual-schedule and bank-account round-trips.
        // This prevents two payouts executed back-to-back from both passing the
        // check against the same stale available balance and overdrawing.
        try {
            $balance = Balance::retrieve(['stripe_account' => $stripeAccountId]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception("Stripe balance check failed for {$stripeAccountId}: {$e->getMessage()}");
        }

        if (empty($balance->available) || !isset($balance->available[0])) {
            throw new Exception("No available balance data for account {$stripeAccountId}");
        }

        $availableBalance = $balance->available[0]->amount / 100;
        $pendingBalance = !empty($balance->pending) ? $balance->pending[0]->amount / 100 : 0;

        if ($availableBalance < $payoutData['net_amount']) {
            throw new Exception("Insufficient balance for {$stripeAccountId}. Available: {$availableBalance}, Required: {$payoutData['net_amount']}");
        }

        // Deterministic idempotency key scoped to (rule, account, day). If Stripe
        // succeeded but the response timed out, the retry replays the original
        // response instead of creating a duplicate payout. The daily firing
        // cadence and the cache lock ensure no legitimate second call per day.
        $idempotencyKey = 'immediate-' . $rule->id . '-' . $stripeAccountId . '-' . date('Ymd');
        $payoutReference = 'AP-' . date('Ymd') . '-' . strtoupper(substr($stripeAccountId, -6)) . '-' . strtoupper(Str::random(4));

        // Call Stripe OUTSIDE the DB transaction. If Stripe succeeds but the
        // DB write fails, the idempotency key prevents a duplicate payout on
        // retry, and we can detect the orphaned Stripe payout via metadata.
        //
        // Wrap amount-to-cents in (int) round(...) because PHP float math
        // (e.g. 0.59 * 100 = 58.99999...) would otherwise serialize a
        // non-integer that Stripe's API rejects.
        $stripePayout = Payout::create([
            'amount' => (int) round($payoutData['net_amount'] * 100),
            'currency' => currencyFormation(true),
            'destination' => $bankAccountId,
            'description' => "Auto payout: {$payoutReference}",
            'metadata' => [
                'payout_reference' => $payoutReference,
                'balance_available' => (string) $availableBalance,
                'balance_pending' => (string) $pendingBalance,
                'balance_currency' => $balance->available[0]->currency,
                'tenant_id' => tenant()->id,
            ]
        ], [
            'stripe_account' => $stripeAccountId,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Stripe payout succeeded -- now record everything locally in a transaction.
        DB::beginTransaction();

        try {
            $payout = AutoPayout::create([
                'payout_reference' => $payoutReference,
                'stripe_payout_id' => $stripePayout->id,
                'rule_id' => $rule->id,
                'total_amount' => $payoutData['total_amount'],
                'platform_fee' => $payoutData['platform_fee'],
                'net_amount' => $payoutData['net_amount'],
                'booking_count' => $payoutData['booking_count'],
                'period_start' => $payoutData['period_start'] ?? null,
                'period_end' => $payoutData['period_end'] ?? null,
                'processed_date' => now(),
                // Stripe API returning success only means the payout request was
                // accepted; the bank settles 1-3 business days later. The
                // payout.paid webhook (StripeWebhookController) promotes this to
                // 'completed', or payout.failed flips it to 'failed' and resets
                // payout_made on the included bookings.
                'status' => 'pending'
            ]);

            foreach ($payoutData['booking_details'] as $bookingId => $details) {
                AutoPayoutItem::create([
                    'payout_id' => $payout->id,
                    'booking_id' => $bookingId,
                    'total_amount' => $details['amount'],
                    'platform_fee' => $details['fee_amount'],
                    'net_amount' => $details['net_total']
                ]);
            }

            TourBooking::whereIn('id', $payoutData['booking_ids'])
                ->update(['payout_made' => true]);

            // Apply exactly the adjustments that were folded into this payout's
            // net at calculation time (not whatever happens to be pending now).
            $adjustmentService = app(PayoutAdjustmentService::class);
            $adjustmentService->applyAdjustmentsToPayout($payout, $payoutData['adjustment_ids'] ?? []);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::critical("Stripe payout {$stripePayout->id} succeeded but local DB write failed. Manual reconciliation required.", [
                'stripe_payout_id' => $stripePayout->id,
                'payout_reference' => $payoutReference,
                'stripe_account_id' => $stripeAccountId,
                'amount' => $payoutData['net_amount'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->sendPayoutNotification('executed', [
            'payout_reference' => $payout->payout_reference,
            'stripe_account_id' => $stripeAccountId,
            'total_amount' => $payoutData['total_amount'],
            'platform_fee' => $payoutData['platform_fee'],
            'net_amount' => $payoutData['net_amount'],
            'booking_count' => $payoutData['booking_count'],
            'stripe_payout_id' => $stripePayout->id,
            'balance_at_payout' => $availableBalance,
            'adjustment_total' => $payoutData['adjustment_total'] ?? 0,
            'adjustment_count' => $payoutData['adjustment_count'] ?? 0,
        ], $rule);

        return [
            'payout_reference' => $payout->payout_reference,
            'amount' => $payoutData['net_amount'],
            'stripe_payout_id' => $stripePayout->id,
        ];
    }

    /**
     * Get connected Stripe accounts from active tour packages
     *
     * @return \Illuminate\Support\Collection
     */
    public function getConnectedAccounts()
    {
        $stripeId = tourOperator()->stripe_destination_id;

        if (!$stripeId) {
            return collect();
        }

        return collect([(object) ['stripe_destination_id' => $stripeId]]);
    }

    /**
     * Create pending payout from calculation data
     *
     * @param AutoPayoutRule $rule
     * @param string $stripeAccountId
     * @param array $payoutData
     * @return PendingPayout
     */
    public function createPendingPayout(AutoPayoutRule $rule, string $stripeAccountId, array $payoutData): ?PendingPayout
    {
        try {
            // Guard against non-positive net amounts
            if ($payoutData['net_amount'] <= 0) {
                Log::warning("Skipping account {$stripeAccountId}: net amount is {$payoutData['net_amount']}");
                return null;
            }

            DB::beginTransaction();

            // Create the pending payout record
            $pendingPayout = PendingPayout::create([
                'payout_reference' => 'PP-' . date('Ymd') . '-' . strtoupper(substr($stripeAccountId, -6)) . '-' . strtoupper(Str::random(4)),
                'rule_id' => $rule->id,
                'stripe_account_id' => $stripeAccountId,
                'total_amount' => $payoutData['total_amount'],
                'platform_fee' => $payoutData['platform_fee'],
                'net_amount' => $payoutData['net_amount'],
                'booking_count' => $payoutData['booking_count'],
                'period_start' => $payoutData['period_start'] ?? null,
                'period_end' => $payoutData['period_end'] ?? null,
                'status' => 'pending_approval',
                'rule_snapshot' => $rule->toArray(),
                'calculation_data' => [
                    'booking_ids' => $payoutData['booking_ids'],
                    'booking_details' => $payoutData['booking_details'],
                    'calculation_date' => now()->toDateTimeString(),
                    'stripe_account_id' => $stripeAccountId,
                    // Exact pending adjustments folded into net_amount. Re-validated
                    // at recalculation and marked applied (by id) at execution.
                    'adjustment_ids' => $payoutData['adjustment_ids'] ?? [],
                ]
            ]);

            // Create payout items
            foreach ($payoutData['booking_details'] as $bookingId => $details) {
                PendingPayoutItem::create([
                    'pending_payout_id' => $pendingPayout->id,
                    'booking_id' => $bookingId,
                    'total_amount' => $details['amount'],
                    'platform_fee' => $details['fee_amount'],
                    'net_amount' => $details['net_total']
                ]);
            }

            // Log the creation
            $this->logAction($pendingPayout, 'created', null, request()->ip() ?? 'system', 'Pending payout created automatically');

            DB::commit();

            Log::info("Pending payout created successfully", [
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'net_amount' => $pendingPayout->net_amount,
                'stripe_account_id' => $stripeAccountId
            ]);

            $this->sendPayoutNotification('pending_approval', [
                'payout_reference' => $pendingPayout->payout_reference,
                'stripe_account_id' => $stripeAccountId,
                'total_amount' => $pendingPayout->total_amount,
                'platform_fee' => $pendingPayout->platform_fee,
                'net_amount' => $pendingPayout->net_amount,
                'booking_count' => $pendingPayout->booking_count,
            ], $rule);

            return $pendingPayout;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create pending payout: ' . $e->getMessage(), [
                'stripe_account_id' => $stripeAccountId,
                'payout_data' => $payoutData,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Recalculate a pending payout against current transaction data.
     * Removes fully refunded bookings, updates amounts for partially refunded ones.
     *
     * @param PendingPayout $pendingPayout
     * @return array Summary of changes
     */
    public function recalculatePendingPayout(PendingPayout $pendingPayout): array
    {
        if (!in_array($pendingPayout->status, ['pending_approval', 'approved'])) {
            throw new Exception("Cannot recalculate payout in status: {$pendingPayout->status}");
        }

        $bookingIds = $pendingPayout->items->pluck('booking_id')->toArray();

        if (empty($bookingIds)) {
            // No booking items, but the payout may still carry snapshotted
            // adjustments (a pure-adjustment payout). Re-validate net against
            // those adjustments that are still pending so an adjustment already
            // consumed by an earlier-executed payout drops out of the amount.
            $adjustmentNet = $this->snapshotAdjustmentNet($pendingPayout);
            $newNet = max(0, round($adjustmentNet, 2));
            $pendingPayout->update([
                'total_amount' => 0,
                'platform_fee' => 0,
                'net_amount' => $newNet,
                'booking_count' => 0,
            ]);
            return ['removed' => [], 'updated' => [], 'unchanged' => 0];
        }

        // Re-query current transaction state for these bookings
        $transactions = DB::table('transactions as tra')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tra.tour_booking_id')
            ->whereIn('tra.tour_booking_id', $bookingIds)
            ->whereIn('tra.status', ['Completed', 'Refund'])
            ->where('tra.via', 'Card')
            ->where('tra.request_from', '!=', 'Insert')
            ->select('tra.amount', 'tra.fee_amount', 'tra.status', 'tra.tour_booking_id', 'tbm.total as booking_total')
            ->get();

        // Build current amounts per booking (same logic as calculateAccountPayoutAmount)
        $currentData = [];
        foreach ($transactions as $t) {
            $bid = $t->tour_booking_id;
            if (!isset($currentData[$bid])) {
                $currentData[$bid] = [
                    'amount' => 0, 'fee_amount' => 0,
                    'refund' => 0, 'refund_fee_amount' => 0,
                    'refund_remove_fee' => 0, 'net_total' => 0,
                    'booking_total' => $t->booking_total,
                ];
            }
            if ($t->status === 'Completed') {
                $currentData[$bid]['amount'] += $t->amount;
                $currentData[$bid]['fee_amount'] += $t->fee_amount;
            }
            if ($t->status === 'Refund') {
                $currentData[$bid]['refund'] += $t->amount;
                $currentData[$bid]['refund_fee_amount'] += $t->fee_amount;
            }
            $currentData[$bid]['refund_remove_fee'] = $currentData[$bid]['refund'] - $currentData[$bid]['refund_fee_amount'];
            $currentData[$bid]['net_total'] = $currentData[$bid]['amount'] - $currentData[$bid]['refund_remove_fee'] - $currentData[$bid]['fee_amount'];
        }

        // Check which bookings were already paid out by another payout.
        // This prevents double-payment when multiple pending payouts
        // contain the same bookings (payout_made is only set on execution).
        $alreadyPaidIds = DB::table('tour_bookings_master')
            ->whereIn('id', $bookingIds)
            ->where('payout_made', 1)
            ->pluck('id')
            ->flip()
            ->toArray();

        $removed = [];
        $updated = [];
        $unchanged = 0;

        DB::beginTransaction();

        try {
            foreach ($pendingPayout->items as $item) {
                $bid = $item->booking_id;

                // Already paid out by another payout -- remove to prevent double-payment
                if (isset($alreadyPaidIds[$bid])) {
                    $removed[] = [
                        'booking_id' => $bid,
                        'previous_net' => (float) $item->net_amount,
                        'reason' => 'Already paid out in another payout',
                    ];
                    $item->delete();
                    continue;
                }

                $current = $currentData[$bid] ?? null;

                // Fully refunded or no transactions: net <= 0
                if (!$current || $current['net_total'] <= 0) {
                    $removed[] = [
                        'booking_id' => $bid,
                        'previous_net' => (float) $item->net_amount,
                        'reason' => !$current ? 'No valid transactions found' : 'Fully refunded',
                    ];
                    $item->delete();
                    continue;
                }

                $newAmount = round($current['amount'], 2);
                $newFee = round($current['fee_amount'], 2);
                $newNet = round($current['net_total'], 2);

                // Check if amounts changed
                if ((float) $item->total_amount !== $newAmount ||
                    (float) $item->platform_fee !== $newFee ||
                    (float) $item->net_amount !== $newNet) {
                    $updated[] = [
                        'booking_id' => $bid,
                        'previous_net' => (float) $item->net_amount,
                        'new_net' => $newNet,
                        'difference' => round($newNet - (float) $item->net_amount, 2),
                    ];
                    $item->update([
                        'total_amount' => $newAmount,
                        'platform_fee' => $newFee,
                        'net_amount' => $newNet,
                    ]);
                } else {
                    $unchanged++;
                }
            }

            // Recalculate payout totals from remaining items
            $remainingItems = PendingPayoutItem::where('pending_payout_id', $pendingPayout->id)->get();
            $newTotalAmount = $remainingItems->sum('total_amount');
            $newPlatformFee = $remainingItems->sum('platform_fee');
            $newBookingCount = $remainingItems->count();

            // Fold in the snapshotted adjustments that are still pending, mirroring
            // calculateAccountPayoutAmount at creation. Without this the recalc that
            // runs immediately before execution would strip adjustments from the
            // net (paying booking-only) while applyAdjustmentsToPayout still marked
            // them applied -- i.e. adjustments marked paid but never actually paid.
            // total_amount / platform_fee track bookings only (adjustments affect
            // net only), consistent with the create path.
            $adjustmentNet = $this->snapshotAdjustmentNet($pendingPayout);
            $newNetAmount = round($remainingItems->sum('net_amount') + $adjustmentNet, 2);
            if ($newNetAmount < 0) {
                Log::warning("recalculatePendingPayout: Adjustments exceed payout total for {$pendingPayout->payout_reference}. Capping at 0.");
                $newNetAmount = 0;
            }

            $pendingPayout->update([
                'total_amount' => round($newTotalAmount, 2),
                'platform_fee' => round($newPlatformFee, 2),
                'net_amount' => $newNetAmount,
                'booking_count' => $newBookingCount,
                'calculation_data' => array_merge($pendingPayout->calculation_data ?? [], [
                    'last_recalculated_at' => now()->toDateTimeString(),
                    'booking_ids' => $remainingItems->pluck('booking_id')->toArray(),
                ]),
            ]);

            // Log the recalculation
            $this->logAction($pendingPayout, 'recalculated', null, request()->ip() ?? 'system',
                'Payout recalculated: ' . count($removed) . ' booking(s) removed, ' . count($updated) . ' updated',
                ['removed' => $removed, 'updated' => $updated, 'unchanged' => $unchanged]
            );

            DB::commit();

            return [
                'removed' => $removed,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'new_totals' => [
                    'total_amount' => round($newTotalAmount, 2),
                    'platform_fee' => round($newPlatformFee, 2),
                    'net_amount' => round($newNetAmount, 2),
                    'booking_count' => $newBookingCount,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Signed net of the adjustments snapshotted into a pending payout that are
     * still pending (credits positive, debits negative). Adjustments already
     * consumed by an earlier-executed payout are 'applied' and excluded, so the
     * value reflects only what this payout will actually pay and mark applied.
     *
     * @param PendingPayout $pendingPayout
     * @return float
     */
    private function snapshotAdjustmentNet(PendingPayout $pendingPayout): float
    {
        $ids = $pendingPayout->calculation_data['adjustment_ids'] ?? [];

        if (empty($ids)) {
            return 0.0;
        }

        $net = 0.0;
        foreach (PayoutAdjustment::pending()->whereIn('id', $ids)->get() as $adjustment) {
            $net += $adjustment->signedNetAmount();
        }

        return round($net, 2);
    }

    /**
     * Approve a pending payout
     *
     * @param PendingPayout $pendingPayout
     * @param int $userId
     * @param string $ipAddress
     * @param string|null $notes
     * @return bool
     */
    public function approvePayout(PendingPayout $pendingPayout, int $userId, string $ipAddress, ?string $notes = null): bool
    {
        try {
            DB::beginTransaction();

            // Lock the row to prevent concurrent approval
            $fresh = PendingPayout::lockForUpdate()->find($pendingPayout->id);
            if (!$fresh || !$fresh->isPendingApproval()) {
                DB::rollBack();
                throw new Exception("Payout is not in pending approval status");
            }

            // Update payout status
            $fresh->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId
            ]);
            $pendingPayout->refresh();

            // Log the approval
            $this->logAction($pendingPayout, 'approved', $userId, $ipAddress, $notes);

            DB::commit();

            Log::info("Payout approved successfully", [
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'approved_by' => $userId,
                'net_amount' => $pendingPayout->net_amount
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve payout: ' . $e->getMessage(), [
                'payout_id' => $pendingPayout->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Reject a pending payout
     *
     * @param PendingPayout $pendingPayout
     * @param int $userId
     * @param string $reason
     * @param string $ipAddress
     * @return bool
     */
    public function rejectPayout(PendingPayout $pendingPayout, int $userId, string $reason, string $ipAddress): bool
    {
        try {
            if (!$pendingPayout->isPendingApproval()) {
                throw new Exception("Payout is not in pending approval status");
            }

            DB::beginTransaction();

            // Update payout status
            $pendingPayout->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => $userId,
                'rejection_reason' => $reason
            ]);

            // Log the rejection
            $this->logAction($pendingPayout, 'rejected', $userId, $ipAddress, $reason);

            // Void any pending adjustments linked to this payout
            $voidedCount = PayoutAdjustment::pending()
                ->where('payout_reference', $pendingPayout->payout_reference)
                ->update([
                    'status' => 'voided',
                    'voided_by' => $userId,
                    'voided_at' => now(),
                    'void_reason' => 'Payout rejected: ' . $reason,
                ]);

            DB::commit();

            Log::info("Payout rejected successfully", [
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'rejected_by' => $userId,
                'reason' => $reason,
                'adjustments_voided' => $voidedCount,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject payout: ' . $e->getMessage(), [
                'payout_id' => $pendingPayout->id,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Re-approve a failed payout for retry
     */
    public function reapprovePayout(PendingPayout $pendingPayout, int $userId, string $ipAddress, ?string $notes = null): bool
    {
        try {
            if (!$pendingPayout->isFailed()) {
                throw new Exception("Payout is not in failed status");
            }

            DB::beginTransaction();

            $pendingPayout->update([
                'status' => 'approved',
                'retry_count' => 0,
                'last_failed_at' => null,
                'last_failure_reason' => null,
            ]);

            $this->logAction($pendingPayout, 'approved', $userId, $ipAddress, 'Re-approved for retry' . ($notes ? ": {$notes}" : ''));

            DB::commit();

            Log::info("Payout re-approved for retry", [
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'reapproved_by' => $userId,
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to re-approve payout: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute approved payouts
     *
     * @return array
     */
    public function executeApprovedPayouts(): array
    {
        $executed = [];
        $failed = [];

        $approvedPayouts = PendingPayout::approved()->get();

        foreach ($approvedPayouts as $pendingPayout) {
            // Skip payouts still in their backoff window. Without this gate the
            // every-minute scheduler retries 60s after every failure, which
            // burns the retry budget on a single Stripe blip and strands the
            // payout in 'failed' status within minutes.
            if ($pendingPayout->last_failed_at && $pendingPayout->retry_count > 0) {
                $backoffMinutes = min(
                    self::MAX_BACKOFF_MINUTES,
                    2 ** min($pendingPayout->retry_count, 30)
                );
                $nextRetryAt = $pendingPayout->last_failed_at->copy()->addMinutes($backoffMinutes);
                if (now()->lt($nextRetryAt)) {
                    continue;
                }
            }

            try {
                $result = $this->executeSinglePayout($pendingPayout);
                if ($result['success']) {
                    $executed[] = $result;
                } else {
                    $failed[] = $result;
                }
            } catch (Exception $e) {
                Log::error('Failed to execute approved payout: ' . $e->getMessage(), [
                    'payout_id' => $pendingPayout->id,
                    'trace' => $e->getTraceAsString()
                ]);
                $failed[] = [
                    'payout_id' => $pendingPayout->id,
                    'payout_reference' => $pendingPayout->payout_reference,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'executed' => $executed,
            'failed' => $failed
        ];
    }

    /**
     * Execute a single approved payout
     *
     * @param PendingPayout $pendingPayout
     * @return array
     */
    private function executeSinglePayout(PendingPayout $pendingPayout): array
    {
        try {
            // Phase 1: Recalculate inside its own transaction (recalculatePendingPayout
            // already wraps in DB::beginTransaction / commit internally).
            $recalcResult = $this->recalculatePendingPayout($pendingPayout);
            $pendingPayout->refresh();

            if (!empty($recalcResult['removed']) || !empty($recalcResult['updated'])) {
                Log::info("executeSinglePayout: Recalculated payout {$pendingPayout->payout_reference} before execution", $recalcResult);
            }

            if ($pendingPayout->net_amount <= 0) {
                throw new Exception("Cannot execute payout with non-positive amount: {$pendingPayout->net_amount}");
            }

            $rule = $pendingPayout->rule;
            if ($rule && $rule->max_payout_amount && $pendingPayout->net_amount > (float) $rule->max_payout_amount) {
                throw new Exception("Payout amount {$pendingPayout->net_amount} exceeds maximum ceiling of {$rule->max_payout_amount}");
            }

            // Phase 2: Pre-flight checks against Stripe (outside any DB transaction)
            // Verify account is set to manual payouts before sending money
            $this->ensureManualPayoutSchedule($pendingPayout->stripe_account_id);

            // Get bank account for payout
            $externalAccounts = Account::allExternalAccounts(
                $pendingPayout->stripe_account_id,
                ['object' => 'bank_account', 'limit' => 1]
            );

            if (empty($externalAccounts->data)) {
                throw new Exception("No bank account found for account {$pendingPayout->stripe_account_id}");
            }

            $bankAccountId = $externalAccounts->data[0]->id;

            // Retrieve the balance LAST -- immediately before Payout::create -- so the
            // sufficiency check reflects the balance at the moment of payout, not a
            // snapshot taken before the manual-schedule and bank-account round-trips.
            // Without this, two approved payouts executed back-to-back in the same
            // run can both pass the check against the same stale balance and overdraw.
            try {
                $balance = Balance::retrieve(['stripe_account' => $pendingPayout->stripe_account_id]);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                throw new Exception("Stripe balance check failed for {$pendingPayout->stripe_account_id}: {$e->getMessage()}");
            }

            if (empty($balance->available) || !isset($balance->available[0])) {
                throw new Exception("No available balance data for account {$pendingPayout->stripe_account_id}");
            }

            $availableBalance = $balance->available[0]->amount / 100;
            $pendingStripeBalance = !empty($balance->pending) ? $balance->pending[0]->amount / 100 : 0;

            if ($availableBalance < $pendingPayout->net_amount) {
                throw new Exception("Insufficient balance. Available: {$availableBalance}, Required: {$pendingPayout->net_amount}");
            }

            // Phase 3: Call Stripe OUTSIDE the DB transaction. The idempotency key
            // prevents duplicate payouts if we need to retry after a local DB failure.
            $stripePayout = Payout::create([
                'amount' => (int) round($pendingPayout->net_amount * 100),
                'currency' => currencyFormation(true),
                'destination' => $bankAccountId,
                'description' => "Auto payout: {$pendingPayout->payout_reference}",
                'metadata' => [
                    'pending_payout_id' => $pendingPayout->id,
                    'payout_reference' => $pendingPayout->payout_reference,
                    'balance_available' => (string) $availableBalance,
                    'balance_pending' => (string) $pendingStripeBalance,
                    'balance_currency' => $balance->available[0]->currency,
                    'tenant_id' => tenant()->id,
                ]
            ], [
                'stripe_account' => $pendingPayout->stripe_account_id,
                'idempotency_key' => 'payout-' . $pendingPayout->payout_reference,
            ]);

            // Phase 4: Stripe succeeded -- record everything locally in a transaction.
            DB::beginTransaction();

            try {
                $autoPayout = AutoPayout::create([
                    // Deliberately the same string as the pending_payouts row and as the
                    // reference sent to Stripe above in metadata, description and the
                    // idempotency key, so one payout resolves to one reference everywhere.
                    // A random suffix used to be appended here; it made the stored
                    // reference disagree with Stripe's metadata and silently broke the
                    // per-booking adjustment lookups that match on the reference string.
                    // The unique indexes on auto_payouts.payout_reference and
                    // pending_payouts.payout_reference are per-table, so sharing is legal.
                    'payout_reference' => $pendingPayout->payout_reference,
                    'stripe_payout_id' => $stripePayout->id,
                    'rule_id' => $pendingPayout->rule_id,
                    'total_amount' => $pendingPayout->total_amount,
                    'platform_fee' => $pendingPayout->platform_fee,
                    'net_amount' => $pendingPayout->net_amount,
                    'booking_count' => $pendingPayout->booking_count,
                    // Carry the window the pending payout was calculated from, so the
                    // executed payout reports the range it actually collected, not the
                    // range that would be current at approval time.
                    'period_start' => $pendingPayout->period_start,
                    'period_end' => $pendingPayout->period_end,
                    'processed_date' => now(),
                    // See immediate-payout path: 'pending' until the
                    // payout.paid webhook promotes it to 'completed'.
                    'status' => 'pending'
                ]);

                foreach ($pendingPayout->items as $item) {
                    AutoPayoutItem::create([
                        'payout_id' => $autoPayout->id,
                        'booking_id' => $item->booking_id,
                        'total_amount' => $item->total_amount,
                        'platform_fee' => $item->platform_fee,
                        'net_amount' => $item->net_amount
                    ]);
                }

                // Use item-based booking IDs (authoritative) instead of stale calculation_data JSON
                $bookingIds = $pendingPayout->items->pluck('booking_id')->toArray();
                if (!empty($bookingIds)) {
                    TourBooking::whereIn('id', $bookingIds)->update(['payout_made' => true]);
                }

                // Apply exactly the adjustments snapshotted into this payout at
                // creation, and only those still pending. recalculatePendingPayout
                // (called above) already folded the same set into net_amount, so the
                // Stripe amount and the marked-applied set stay consistent.
                $snapshotAdjustmentIds = $pendingPayout->calculation_data['adjustment_ids'] ?? [];
                $adjustmentService = app(PayoutAdjustmentService::class);
                $adjustedCount = $adjustmentService->applyAdjustmentsToPayout($autoPayout, $snapshotAdjustmentIds);

                // Log successful execution
                $this->logAction($pendingPayout, 'executed', null, null, 'Payout executed successfully', [
                    'stripe_payout_id' => $stripePayout->id,
                    'auto_payout_id' => $autoPayout->id,
                    'balance_available' => $availableBalance,
                    'balance_pending' => $pendingStripeBalance,
                    'balance_after_payout' => round($availableBalance - $pendingPayout->net_amount, 2),
                    'adjustments_applied' => $adjustedCount,
                ]);

                // Mark the pending payout as executed (preserves audit trail)
                $pendingPayout->update(['status' => 'executed']);

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Log::critical("Stripe payout {$stripePayout->id} succeeded but local DB write failed. Manual reconciliation required.", [
                    'stripe_payout_id' => $stripePayout->id,
                    'payout_reference' => $pendingPayout->payout_reference,
                    'stripe_account_id' => $pendingPayout->stripe_account_id,
                    'amount' => $pendingPayout->net_amount,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->sendPayoutNotification('executed', [
                'payout_reference' => $pendingPayout->payout_reference,
                'stripe_account_id' => $pendingPayout->stripe_account_id,
                'total_amount' => $pendingPayout->total_amount,
                'platform_fee' => $pendingPayout->platform_fee,
                'net_amount' => $pendingPayout->net_amount,
                'booking_count' => $pendingPayout->booking_count,
                'stripe_payout_id' => $stripePayout->id,
                'balance_at_payout' => $availableBalance,
            ], $pendingPayout->rule);

            return [
                'success' => true,
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'stripe_payout_id' => $stripePayout->id,
                'amount' => $pendingPayout->net_amount
            ];

        } catch (Exception $e) {
            // Only increment retry if this was NOT a post-Stripe DB failure
            // (those are already logged as critical above)
            $pendingPayout->refresh();
            if ($pendingPayout->status !== 'executed') {
                $pendingPayout->increment('retry_count');
                $pendingPayout->update([
                    'last_failed_at' => now(),
                    'last_failure_reason' => $e->getMessage(),
                ]);

                $attemptNumber = $pendingPayout->retry_count;
                $maxRetries = self::MAX_RETRY_COUNT;

                if ($attemptNumber >= $maxRetries) {
                    $pendingPayout->update(['status' => 'failed']);
                    $this->logAction($pendingPayout, 'failed', null, null, "Payout failed permanently after {$maxRetries} attempts: " . $e->getMessage());

                    $this->sendPayoutNotification('failed', [
                        'payout_reference' => $pendingPayout->payout_reference,
                        'stripe_account_id' => $pendingPayout->stripe_account_id,
                        'total_amount' => $pendingPayout->total_amount,
                        'platform_fee' => $pendingPayout->platform_fee,
                        'net_amount' => $pendingPayout->net_amount,
                        'booking_count' => $pendingPayout->booking_count,
                        'error' => "Failed after {$maxRetries} attempts: " . $e->getMessage(),
                    ], $pendingPayout->rule);
                } else {
                    $this->logAction($pendingPayout, 'failed', null, null, "Attempt {$attemptNumber}/{$maxRetries} failed: " . $e->getMessage());
                }
            }

            return [
                'success' => false,
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Bulk approve payouts
     *
     * @param array $payoutIds
     * @param int $userId
     * @param string $ipAddress
     * @param string|null $notes
     * @return array
     */
    public function bulkApprove(array $payoutIds, int $userId, string $ipAddress, ?string $notes = null): array
    {
        $approved = [];
        $failed = [];

        foreach ($payoutIds as $payoutId) {
            try {
                $pendingPayout = PendingPayout::findOrFail($payoutId);
                $this->approvePayout($pendingPayout, $userId, $ipAddress, $notes);
                $approved[] = $pendingPayout->payout_reference;
            } catch (Exception $e) {
                $failed[] = [
                    'payout_id' => $payoutId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'approved' => $approved,
            'failed' => $failed
        ];
    }

    /**
     * Bulk reject payouts
     *
     * @param array $payoutIds
     * @param int $userId
     * @param string $reason
     * @param string $ipAddress
     * @return array
     */
    public function bulkReject(array $payoutIds, int $userId, string $reason, string $ipAddress): array
    {
        $rejected = [];
        $failed = [];

        foreach ($payoutIds as $payoutId) {
            try {
                $pendingPayout = PendingPayout::findOrFail($payoutId);
                $this->rejectPayout($pendingPayout, $userId, $reason, $ipAddress);
                $rejected[] = $pendingPayout->payout_reference;
            } catch (Exception $e) {
                $failed[] = [
                    'payout_id' => $payoutId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'rejected' => $rejected,
            'failed' => $failed
        ];
    }

    /**
     * Log approval action
     *
     * @param PendingPayout $pendingPayout
     * @param string $action
     * @param int|null $userId
     * @param string|null $ipAddress
     * @param string|null $notes
     * @param array|null $metadata
     * @return void
     */
    private function logAction(PendingPayout $pendingPayout, string $action, ?int $userId, ?string $ipAddress, ?string $notes = null, ?array $metadata = null): void
    {
        PayoutApprovalLog::create([
            'pending_payout_id' => $pendingPayout->id,
            'action' => $action,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => request()->userAgent(),
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Calculate the next execution time for a payout rule.
     * Shared by the command and controller to avoid divergent implementations.
     */
    public static function calculateNextExecutionTime(string $frequency, ?int $dayOfWeek, ?int $dayOfMonth, string $executionTime): Carbon
    {
        $timezone = tourOperator()->timezone;
        $appTimezone = config('app.timezone');
        $now = Carbon::now($timezone);
        $time = Carbon::parse($executionTime, $timezone);

        $days = [
            0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday',
            4 => 'thursday', 5 => 'friday', 6 => 'saturday',
        ];

        $todaySlot = $now->copy()->startOfDay()->setTime($time->hour, $time->minute);

        // Stored value must be in app timezone so the cron gate
        // (ProcessAutoPayouts where next_execution_at <= now()) compares
        // matching wall-clock values for tenants in non-app timezones.
        if ($frequency === 'daily') {
            $next = $todaySlot->gte($now) ? $todaySlot : $todaySlot->copy()->addDay();
            return $next->setTimezone($appTimezone);
        }

        if ($frequency === 'weekly') {
            $target = $dayOfWeek ?? 1;
            if ($now->dayOfWeek === $target && $todaySlot->gte($now)) {
                return $todaySlot->setTimezone($appTimezone);
            }
            return $now->copy()->next($days[$target])
                ->setTime($time->hour, $time->minute)
                ->setTimezone($appTimezone);
        }

        if ($frequency === 'monthly') {
            $target = $dayOfMonth ?? 1;
            $clampedThis = min($target, $now->daysInMonth);
            $thisSlot = $now->copy()->startOfMonth()
                ->addDays($clampedThis - 1)
                ->setTime($time->hour, $time->minute);
            if ($thisSlot->gte($now)) {
                return $thisSlot->setTimezone($appTimezone);
            }
            $nextMonth = $now->copy()->addMonth()->startOfMonth();
            $clampedNext = min($target, $nextMonth->daysInMonth);
            return $nextMonth->addDays($clampedNext - 1)
                ->setTime($time->hour, $time->minute)
                ->setTimezone($appTimezone);
        }

        return $now->copy()->addDay()->setTimezone($appTimezone);
    }

    /**
     * Get pending payouts count
     *
     * @return int
     */
    public function getPendingCount(): int
    {
        return PendingPayout::pendingApproval()->count();
    }

    /**
     * Get pending payouts with filters
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPendingPayouts(array $filters = [])
    {
        $query = PendingPayout::with(['rule', 'items', 'approvedBy', 'rejectedBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('payout_reference', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['min_amount'])) {
            $query->where('net_amount', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('net_amount', '<=', $filters['max_amount']);
        }

        $paginated = $query->paginate($filters['per_page'] ?? 15);

        $payoutRefs = $paginated->getCollection()->pluck('payout_reference')->filter()->toArray();
        if (!empty($payoutRefs)) {
            $adjCounts = PayoutAdjustment::whereIn('payout_reference', $payoutRefs)
                ->where('status', 'pending')
                ->selectRaw("payout_reference, COUNT(*) as count, SUM(CASE WHEN type = 'credit' THEN net_amount ELSE -net_amount END) as net_impact")
                ->groupBy('payout_reference')
                ->get()
                ->keyBy('payout_reference');

            $paginated->getCollection()->transform(function ($payout) use ($adjCounts) {
                $adj = $adjCounts->get($payout->payout_reference);
                $payout->adjustment_count = $adj ? (int) $adj->count : 0;
                $payout->adjustment_net_impact = $adj ? round((float) $adj->net_impact, 2) : 0;
                return $payout;
            });
        }

        return $paginated;
    }

    /**
     * Send payout notification emails to all admins.
     *
     * @param string $type One of: pending_approval, executed, failed
     * @param array $payoutData
     * @return void
     */
    private function sendPayoutNotification(string $type, array $payoutData, ?AutoPayoutRule $rule = null): void
    {
        try {
            if ($rule && !empty($rule->notification_emails)) {
                foreach ($rule->notification_emails as $email) {
                    Mail::to($email)->queue(new PayoutNotification($type, $payoutData));
                }
            } else {
                $admins = Admin::whereNotNull('email')->get();

                foreach ($admins as $admin) {
                    Mail::to($admin->email)->queue(new PayoutNotification($type, $payoutData));
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to send payout notification email', [
                'type' => $type,
                'payout_reference' => $payoutData['payout_reference'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }
}