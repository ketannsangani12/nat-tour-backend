<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AutoPayout;
use App\Models\AutoPayoutRule;
use App\Models\AutoPayoutRunLog;
use App\Models\PendingPayout;
use App\Http\Controllers\StripeWebhookController;
use App\Services\PayoutApprovalService;
use App\Services\PayoutAdjustmentService;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Payout;
use Exception;

class ProcessAutoPayouts extends Command
{
    protected $signature = 'autopayout:process {--dry-run : Run without executing payouts}';

    protected $description = 'Process auto payouts (create pending or execute approved)';

    /**
     * Wall-clock start of handle(), used to compute duration_ms for the run log row.
     */
    private float $startedAt = 0.0;

    public function handle()
    {
        $this->startedAt = microtime(true);

        // Probability-gated prune of old run log rows. With 1440 ticks/day and
        // a 1% gate, this runs ~14 times/day per tenant and trims up to 500
        // rows each pass -- far more than the 1440/day insert rate, so any
        // backlog drains within days. Bounded LIMIT keeps the DELETE cheap.
        if (mt_rand(0, 99) === 0) {
            $this->pruneOldRunLogs();
        }

        // Skipped-state outcomes (disabled / no rule / no enabled rule / lock
        // held) are intentionally NOT written to auto_payout_run_logs. The
        // dashboard reads hasSetting('AUTO_PAYOUT') directly and the rule list
        // is its own source of truth -- repeating that signal every 60 seconds
        // just bloats the table without telling an operator anything new.
        // Only meaningful activity (a rule firing, a payout shipping, an error)
        // is logged below.
        if (!hasSetting('AUTO_PAYOUT')) {
            $this->info('Auto payout is disabled. Skipping.');
            return 0;
        }

        if (!AutoPayoutRule::query()->exists()) {
            $this->info('No payout rule configured. Skipping.');
            return 0;
        }

        // Skip if no enabled rule exists AND there are no approved payouts
        // waiting to execute. (Approved payouts from a previously enabled
        // rule should still execute even if the rule is later disabled.)
        $hasEnabledRule = AutoPayoutRule::where('enabled', true)->exists();
        $hasApprovedPayouts = PendingPayout::approved()->exists();
        // Payouts already shipped to Stripe but not yet settled locally must
        // still be reconciled (the webhook backstop below) even if the rule was
        // disabled after they were sent -- otherwise their status stays stale.
        $hasUnsettledPayouts = AutoPayout::where('status', 'pending')
            ->whereNotNull('stripe_payout_id')
            ->exists();

        if (!$hasEnabledRule && !$hasApprovedPayouts && !$hasUnsettledPayouts) {
            $this->info('No enabled rule, no approved payouts, nothing to reconcile. Skipping.');
            return 0;
        }

        // Prevent concurrent execution -- if another run is in progress, skip
        $lock = cache()->lock('autopayout:processing', 900);
        if (!$lock->get()) {
            $this->info('Another payout process is running. Skipping.');
            return 0;
        }

        $totalCreated = 0;
        $totalExecuted = 0;
        $reconciled = 0;
        $runRuleId = null;

        try {
            $this->info('Starting auto payout processing...');

            $service = app(PayoutApprovalService::class);

            // Step 0: Reconcile payouts already sent to Stripe whose status
            // webhook never arrived (or failed to process). This is the backstop
            // that keeps a missed payout.* webhook from leaving status stale --
            // it reads the real status from Stripe and applies it.
            $this->info('Reconciling pending payouts with Stripe...');
            $reconciled = $this->reconcilePendingPayouts();
            if ($reconciled > 0) {
                $this->info("  Reconciled {$reconciled} payout(s) from Stripe");
            }

            // Step 1: Process rules that are due for execution
            $this->info('Processing payout rules...');
            $ruleStats = $this->processRules($service);
            $totalCreated += $ruleStats['created'];
            // Rules with requires_approval=0 pay out inline via
            // executeImmediatePayout, so their count only exists here. Omitting
            // it logged executed=0 for a run that really did pay, which reads
            // identically to "did nothing" and can force outcome=error below.
            $totalExecuted += $ruleStats['executed'];
            $runRuleId = $ruleStats['rule_id'];

            // Step 2: Execute approved payouts
            $this->info('Executing approved payouts...');
            $results = $this->executeApprovedPayouts($service);
            $totalExecuted += count($results['executed']);

            $this->info('Payout processing completed.');
            $this->info('Executed: ' . count($results['executed']));
            $this->info('Failed: ' . count($results['failed']));

            if (!empty($results['failed'])) {
                $this->warn('Some payouts failed to execute:');
                foreach ($results['failed'] as $failed) {
                    $this->warn("- {$failed['payout_reference']}: {$failed['error']}");
                }
            }

            $perAccountFailed = $ruleStats['account_failed'] ?? 0;
            $perRuleFailed = $ruleStats['rule_failed'] ?? 0;
            $approvedFailed = count($results['failed']);

            $parts = [
                "created={$totalCreated}",
                "executed={$totalExecuted}",
                "reconciled={$reconciled}",
                "account_failed={$perAccountFailed}",
                "rule_failed={$perRuleFailed}",
                "approved_failed={$approvedFailed}",
            ];

            // Surface the first exception we hit so the operator can
            // diagnose without shelling into the container's laravel.log.
            $firstErr = $ruleStats['first_error'] ?? null;
            if ($firstErr) {
                $parts[] = 'err=' . substr($firstErr, 0, 600);
            }

            // If we caught a per-rule exception or every account failed,
            // record outcome=error so the row visibly flags itself.
            $outcome = ($perRuleFailed > 0 || ($perAccountFailed > 0 && $totalCreated === 0 && $totalExecuted === 0))
                ? AutoPayoutRunLog::OUTCOME_ERROR
                : AutoPayoutRunLog::OUTCOME_PROCESSED;

            // Only persist the run log row when something meaningful happened:
            // a rule actually fired, a payout was created or executed, or any
            // failure occurred. The every-minute scheduler used to write a
            // "processed created=0 executed=0" row on every tick where the
            // rule wasn't due, producing 1440 noise rows per tenant per day.
            $hadActivity = $runRuleId !== null
                || $totalCreated > 0
                || $totalExecuted > 0
                || $reconciled > 0
                || $perAccountFailed > 0
                || $perRuleFailed > 0
                || $approvedFailed > 0;

            if ($hadActivity) {
                $this->writeRunLog(
                    $outcome,
                    implode(' ', $parts),
                    $runRuleId,
                    $totalCreated,
                    $totalExecuted
                );
            }

            return 0;

        } catch (Exception $e) {
            $this->error('Auto payout processing failed: ' . $e->getMessage());
            Log::error('Auto payout processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->writeRunLog(
                AutoPayoutRunLog::OUTCOME_ERROR,
                substr($e->getMessage(), 0, 1000),
                $runRuleId,
                $totalCreated,
                $totalExecuted
            );
            return 1;
        } finally {
            $lock->forceRelease();
        }
    }

    /**
     * Process payout rules that are due for execution.
     *
     * @return array{created:int, executed:int, rule_id:?int, account_failed:int, rule_failed:int, first_error:?string}
     */
    protected function processRules(PayoutApprovalService $service): array
    {
        $rules = AutoPayoutRule::where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_execution_at')
                    ->orWhere('next_execution_at', '<=', now());
            })
            ->get();

        if ($rules->isEmpty()) {
            $this->info('No payout rules are due for execution.');
            return [
                'created' => 0,
                'executed' => 0,
                'rule_id' => null,
                'account_failed' => 0,
                'rule_failed' => 0,
                'first_error' => null,
            ];
        }

        $totalCreated = 0;
        $totalExecuted = 0;
        $totalAccountFailed = 0;
        $totalRuleFailed = 0;
        $firstRuleError = null;
        $firstRuleId = $rules->first()->id;

        foreach ($rules as $rule) {
            $this->info("Processing rule: {$rule->name}");

            try {
                if ($this->option('dry-run')) {
                    $this->info("DRY RUN: Would process rule {$rule->name}");
                    continue;
                }

                // Detect new post-payout adjustments before calculating amounts
                app(PayoutAdjustmentService::class)->detectAndCreateAdjustments();

                $connectedAccounts = $service->getConnectedAccounts();
                $createdCount = 0;
                $executedCount = 0;
                $totalAmount = 0;
                $failedCount = 0;
                $firstError = null;

                foreach ($connectedAccounts as $account) {
                    try {
                        $payoutData = $service->calculateAccountPayoutAmount($rule, $account->stripe_destination_id);

                        if ($payoutData['net_amount'] < $rule->minimum_amount) {
                            Log::info("Account {$account->stripe_destination_id}: Amount {$payoutData['net_amount']} below minimum {$rule->minimum_amount}");
                            continue;
                        }

                        if ($payoutData['net_amount'] <= 0) {
                            Log::warning("Account {$account->stripe_destination_id}: Skipping non-positive net amount {$payoutData['net_amount']}");
                            continue;
                        }

                        if ($rule->requires_approval) {
                            $pendingPayout = $service->createPendingPayout($rule, $account->stripe_destination_id, $payoutData);
                            if ($pendingPayout) {
                                $createdCount++;
                                $totalAmount += $payoutData['net_amount'];
                            }
                        } else {
                            $result = $service->executeImmediatePayout($rule, $account->stripe_destination_id, $payoutData);
                            $executedCount++;
                            $totalAmount += $result['amount'];
                        }
                    } catch (Exception $e) {
                        $failedCount++;
                        if ($firstError === null) {
                            $firstError = $e->getMessage();
                        }
                        $this->warn("  Failed for account {$account->stripe_destination_id}: " . $e->getMessage());
                    }
                }

                // Always advance the execution window unless every account
                // threw an exception (failedCount > 0 with nothing created).
                // Not advancing when there are simply no qualifying bookings
                // or an existing pending payout blocks creation causes the
                // scheduler to permanently stall.
                if ($failedCount > 0 && $createdCount === 0 && $executedCount === 0) {
                    $this->warn("  All accounts failed - execution window not advanced, will retry next run");
                    // Surface the stall to ops logs so CloudWatch alerts can trip.
                    Log::warning('Auto payout rule stalled: every account failed', [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'failed_count' => $failedCount,
                        'first_error' => $firstError,
                    ]);
                } else {
                    $rule->last_executed_at = now();
                    $rule->next_execution_at = PayoutApprovalService::calculateNextExecutionTime(
                        $rule->frequency,
                        $rule->day_of_week,
                        $rule->day_of_month,
                        $rule->execution_time
                    );
                    $rule->save();
                }

                if ($rule->requires_approval) {
                    $this->info("  Created {$createdCount} pending payouts totaling {$totalAmount}");
                } else {
                    $this->info("  Executed {$executedCount} payouts totaling {$totalAmount}");
                }

                $totalCreated += $createdCount;
                $totalExecuted += $executedCount;
                $totalAccountFailed += $failedCount;
                if ($firstRuleError === null && $firstError !== null) {
                    $firstRuleError = "[rule={$rule->id}/account] " . $firstError;
                }

            } catch (Exception $e) {
                $totalRuleFailed++;
                if ($firstRuleError === null) {
                    $firstRuleError = "[rule={$rule->id}] " . $e->getMessage();
                }
                $this->error("Failed to process rule {$rule->name}: " . $e->getMessage());
                Log::error("Auto payout rule processing failed", [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [
            'created' => $totalCreated,
            'executed' => $totalExecuted,
            'rule_id' => $firstRuleId,
            'account_failed' => $totalAccountFailed,
            'rule_failed' => $totalRuleFailed,
            'first_error' => $firstRuleError,
        ];
    }

    protected function executeApprovedPayouts(PayoutApprovalService $service): array
    {
        if ($this->option('dry-run')) {
            $approvedCount = PendingPayout::approved()->count();
            $this->info("DRY RUN: Would execute {$approvedCount} approved payouts");
            return ['executed' => [], 'failed' => []];
        }

        return $service->executeApprovedPayouts();
    }

    /**
     * Backstop for missed or failed payout webhooks.
     *
     * The payout lifecycle (pending -> completed/failed) is driven by Stripe
     * payout.* webhooks. If one is never delivered or fails to process, the
     * AutoPayout row -- and the payout_made flag on its bookings -- is left
     * stale forever, with no way to self-heal. This re-reads the real status
     * from Stripe for any payout still 'pending' and applies it by reusing the
     * exact same handler the webhook uses, so there is no second copy of the
     * status / payout_made-reset logic to drift.
     *
     * @return int number of payouts whose status was applied from Stripe
     */
    private function reconcilePendingPayouts(): int
    {
        // Give the webhook a head start; only reconcile payouts that have been
        // pending long enough that a webhook should already have settled them.
        $stale = AutoPayout::where('status', 'pending')
            ->whereNotNull('stripe_payout_id')
            ->where('created_at', '<', now()->subMinutes(5))
            ->get();

        if ($stale->isEmpty()) {
            return 0;
        }

        $accountId = tourOperator()->stripe_destination_id;
        if (!$accountId) {
            Log::warning('reconcilePendingPayouts: no stripe_destination_id for tenant; cannot reconcile.');
            return 0;
        }

        Stripe::setApiKey(getStripeSecretKey());
        $controller = app(StripeWebhookController::class);
        $updated = 0;

        foreach ($stale as $autoPayout) {
            try {
                $payout = Payout::retrieve(
                    $autoPayout->stripe_payout_id,
                    ['stripe_account' => $accountId]
                );

                // Map the Stripe payout status to the event type the webhook
                // handler expects. Non-terminal states (pending / in_transit)
                // are left untouched so we never promote a payout early.
                $type = match ($payout->status) {
                    'paid' => 'payout.paid',
                    'failed', 'canceled' => 'payout.failed',
                    default => null,
                };

                if ($type === null) {
                    continue;
                }

                // Synthesize the same shape the webhook delivers. The handler
                // matches the existing row by stripe_payout_id and takes the
                // update branch (never the synthesize branch), so no duplicate
                // record is created. On 'failed' it also resets payout_made=0.
                $event = (object) [
                    'type' => $type,
                    'data' => (object) ['object' => $payout],
                ];

                $controller->handleStripeAutoPayout($event);
                $updated++;

                Log::info("reconcilePendingPayouts: {$autoPayout->payout_reference} ({$autoPayout->stripe_payout_id}) -> {$payout->status}");
            } catch (Exception $e) {
                // Transient Stripe errors: leave the row pending and retry next tick.
                Log::warning("reconcilePendingPayouts: {$autoPayout->payout_reference} ({$autoPayout->stripe_payout_id}): " . $e->getMessage());
            }
        }

        return $updated;
    }

    /**
     * Delete auto_payout_run_logs rows older than 30 days. Bounded by LIMIT
     * so a stale backlog can't turn one tick into a multi-second DELETE.
     *
     * Wrapped in try/catch so a missing table or transient DB error never
     * blocks the actual payout pipeline.
     */
    private function pruneOldRunLogs(): void
    {
        try {
            $deleted = AutoPayoutRunLog::where('created_at', '<', now()->subDays(30))
                ->limit(500)
                ->delete();

            if ($deleted > 0) {
                Log::info("ProcessAutoPayouts: pruned {$deleted} auto_payout_run_logs rows older than 30 days");
            }
        } catch (Exception $e) {
            Log::warning('ProcessAutoPayouts: failed to prune run logs: ' . $e->getMessage());
        }
    }

    /**
     * Persist a single row to auto_payout_run_logs for this tick.
     *
     * Wrapped in try/catch because run-log writes must never fail a tick:
     * if the log table is missing in a tenant DB that hasn't migrated yet,
     * we still want the payout pipeline to run.
     */
    private function writeRunLog(
        string $outcome,
        ?string $message = null,
        ?int $ruleId = null,
        int $payoutsCreated = 0,
        int $payoutsExecuted = 0
    ): void {
        try {
            AutoPayoutRunLog::create([
                'rule_id' => $ruleId,
                'outcome' => $outcome,
                'message' => $message,
                'payouts_created' => $payoutsCreated,
                'payouts_executed' => $payoutsExecuted,
                'duration_ms' => (int) round((microtime(true) - $this->startedAt) * 1000),
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to write auto payout run log: ' . $e->getMessage());
        }
    }
}
