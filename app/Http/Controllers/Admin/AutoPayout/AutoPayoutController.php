<?php
namespace App\Http\Controllers\Admin\AutoPayout;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Exception;
use Illuminate\Http\Request;
use App\Models\AutoPayout;
use App\Models\AutoPayoutRule;
use App\Models\AutoPayoutRunLog;
use App\Models\PendingPayout;
use App\Models\TourPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Stripe\Account;
use Stripe\Stripe;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\PayoutAdjustment;
use App\Services\PayoutApprovalService;
use App\Services\PayoutAdjustmentService;
use App\Services\PayoutExportService;
use App\Services\PayoutAggregateService;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AutoPayoutController extends Controller
{
    /**
     * AutoPayoutController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // Initialize Stripe with API key
        Stripe::setApiKey(getStripeSecretKey());
    }

    /**
     * Show auto payout index page
     *
     * @return View|RedirectResponse
     */
    public function index(): View|RedirectResponse
    {
        try {
            return view('users.admin.auto-payout.index', [
                'page_title' => 'Auto Payouts',
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return redirect()->back()->with('error', 'Unable to fetch payout rules.');
        }
    }

    /**
     * Fetch auto payout rule
     *
     * @return JsonResponse
     */
    public function fetchRule(): JsonResponse
    {
        try {
            $payoutRules = AutoPayoutRule::first();

            return response()->json([
                'success' => true,
                'data' => $payoutRules,
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch payout rules.',
            ]);
        }
    }

    /**
     * Save auto payout rule
     *
     * @return JsonResponse
     */
    public function saveRule(Request $request): JsonResponse
    {
        try {
            // Validate incoming request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'frequency' => 'required|in:daily,weekly,monthly',
                'day_of_week' => 'nullable|integer|between:0,6',
                'day_of_month' => 'nullable|integer|between:1,28',
                'execution_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
                'minimum_amount' => 'required|numeric|min:0',
                'max_payout_amount' => 'nullable|numeric|min:0',
                'buffer_days' => 'required|integer|min:0|max:30',
                // First-run floor for the payout window. Nullable; no future-date
                // guard is enforced (a future date simply yields an empty window).
                'start_date' => 'nullable|date',
                'enabled' => 'required|boolean',
                'requires_approval' => 'boolean',
                'include_partial_payments' => 'boolean',
                'notification_emails' => 'nullable|array',
                'notification_emails.*' => 'email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Use firstOrNew to simplify model retrieval/creation
            $payoutRule = AutoPayoutRule::first();
            $payoutRule ??= new AutoPayoutRule();

            // NOTE: disabling (enabled=false) is intentionally NOT short-circuited
            // here. An earlier early-return updated only `enabled` and silently
            // discarded every other field on the request (start_date, buffer_days,
            // frequency, ...), so saving the form with the rule toggled off threw
            // away the operator's other edits. The normal save path below already
            // skips the Stripe manual-schedule call when enabled=false (see the
            // `if ($payoutRule->enabled)` guard), so a disable both persists all
            // fields and avoids the Stripe round-trip. The row is updated in place,
            // so history is preserved exactly as before.

            // First-run safety: when a brand-new rule is saved, default
            // last_executed_at to now() so the first cycle starts fresh.
            // Without this, PayoutApprovalService falls back to now()->subMonth()
            // and sweeps a month of historical bookings into one giant payout.
            $isNewRule = !$payoutRule->exists;

            // Update model attributes
            $payoutRule->fill([
                'name' => $request->name,
                'frequency' => $request->frequency,
                'day_of_week' => $request->frequency === 'weekly' ? $request->day_of_week : null,
                'day_of_month' => $request->frequency === 'monthly' ? $request->day_of_month : null,
                'execution_time' => $request->execution_time,
                'minimum_amount' => $request->minimum_amount,
                'max_payout_amount' => $request->max_payout_amount,
                'buffer_days' => $request->buffer_days,
                // Honor an explicit null (clearing the field in the form) but
                // preserve the existing value when the key is omitted entirely
                // (partial API saves). `has()` is true even when the value is null.
                'start_date' => $request->has('start_date') ? $request->start_date : $payoutRule->start_date,
                'notification_emails' => $request->notification_emails,
                'enabled' => $request->enabled,
                'requires_approval' => $request->requires_approval ?? true,
                'include_partial_payments' => $request->include_partial_payments ?? false,
                'last_executed_at' => $request->last_executed_at
                    ?? $payoutRule->last_executed_at
                    ?? ($isNewRule ? now() : null),
                'next_execution_at' => PayoutApprovalService::calculateNextExecutionTime($request->frequency, $request->day_of_week, $request->day_of_month, $request->execution_time)
            ]);

            // If approval was just turned off, auto-approve any orphaned pending payouts
            // so they get executed on the next cron run instead of being stranded.
            $wasRequiringApproval = $payoutRule->exists && $payoutRule->getOriginal('requires_approval');
            $nowRequiresApproval = $request->requires_approval ?? true;

            // Enforce manual payout mode on connected Stripe accounts BEFORE saving.
            // If the Stripe API rejects us, bubble up — the rule must not be persisted
            // as enabled while Stripe is still on an auto-payout schedule (otherwise
            // both systems would pay and duplicate money to the destination).
            if ($payoutRule->enabled) {
                $this->configureStripePayoutSchedules($payoutRule);
            }

            // Save the model
            $payoutRule->save();

            if ($wasRequiringApproval && !$nowRequiresApproval) {
                $autoApproved = PendingPayout::where('rule_id', $payoutRule->id)
                    ->where('status', 'pending_approval')
                    ->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                    ]);

                if ($autoApproved > 0) {
                    Log::info("Auto-approved {$autoApproved} pending payout(s) after approval requirement was disabled", [
                        'rule_id' => $payoutRule->id,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rule saved successfully',
                'data' => $payoutRule,
            ], 200);
        } catch (Exception $exception) {
            Log::error('Failed to save payout rule: ' . $exception->getMessage(), [
                'request' => $request->all(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save payout rule.',
            ], 500);
        }
    }

    /**
     * Configure Stripe payout schedules for connected accounts
     *
     * @param AutoPayoutRule $rule
     * @return void
     */
    /**
     * Ensure Stripe connected accounts are set to manual payouts.
     * This is critical -- Stripe must NEVER auto-pay on its own schedule.
     * Our app controls all payout timing via the API.
     */
    protected function configureStripePayoutSchedules(AutoPayoutRule $rule): void
    {
        $service = app(PayoutApprovalService::class);
        $connectedAccounts = $service->getConnectedAccounts();

        foreach ($connectedAccounts as $account) {
            Account::update(
                $account->stripe_destination_id,
                [
                    'settings' => [
                        'payouts' => [
                            'schedule' => [
                                'interval' => 'manual',
                            ],
                        ],
                    ],
                ]
            );

            Log::info("Stripe payout schedule set to manual for account {$account->stripe_destination_id}");
        }
    }

    /**
     * Process payouts for connected accounts based on the rule
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setupStripePayouts(Request $request): JsonResponse
    {
        try {
            // Get the current tenant (assuming tenancy package provides this)
            $tenant = tenancy()->tenant ?? null;
            Log::info('Tenant context: ' . ($tenant ? $tenant->id : 'none'));
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context not initialized.',
                ], 400);
            }

            $rule = AutoPayoutRule::first();
            if (!$rule || !$rule->enabled) {
                Log::error("No enabled payout rule found for tenant: {$tenant->id}");
                return response()->json([
                    'success' => false,
                    'message' => 'No enabled payout rule found.',
                ], 400);
            }

            // Check if approval is required
            if ($rule->requires_approval) {
                return $this->createPendingPayouts($rule);
            } else {
                // Execute immediately (existing logic)
                return $this->executePayoutsImmediately($rule);
            }
        } catch (Exception $e) {
            Log::error('Failed to process Stripe payouts: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process payouts.',
            ], 500);
        }
    }

    /**
     * Create pending payouts for approval
     *
     * @param AutoPayoutRule $rule
     * @return JsonResponse
     */
    private function createPendingPayouts(AutoPayoutRule $rule): JsonResponse
    {
        try {
            $approvalService = app(PayoutApprovalService::class);

            // Detect new post-payout adjustments before calculating amounts
            app(PayoutAdjustmentService::class)->detectAndCreateAdjustments();

            $connectedAccounts = $approvalService->getConnectedAccounts();

            $createdPayouts = [];
            $totalPendingAmount = 0;

            foreach ($connectedAccounts as $account) {
                try {
                    // Calculate payout amount for this account
                    $payoutData = $approvalService->calculateAccountPayoutAmount($rule, $account->stripe_destination_id);
                    
                    if ($payoutData['net_amount'] < $rule->minimum_amount) {
                        Log::info("Account {$account->stripe_destination_id}: Amount {$payoutData['net_amount']} below minimum {$rule->minimum_amount}");
                        continue;
                    }

                    // Create pending payout
                    $pendingPayout = $approvalService->createPendingPayout($rule, $account->stripe_destination_id, $payoutData);

                    if (!$pendingPayout) {
                        continue;
                    }

                    $createdPayouts[] = [
                        'account_id' => $account->stripe_destination_id,
                        'payout_reference' => $pendingPayout->payout_reference,
                        'amount' => $payoutData['net_amount'],
                        'status' => 'pending_approval'
                    ];

                    $totalPendingAmount += $payoutData['net_amount'];

                    Log::info("Pending payout created successfully", [
                        'payout_id' => $pendingPayout->id,
                        'payout_reference' => $pendingPayout->payout_reference,
                        'amount' => $payoutData['net_amount']
                    ]);

                } catch (Exception $e) {
                    Log::error('Failed to create pending payout for account ' . $account->stripe_destination_id . ': ' . $e->getMessage());
                }
            }

            // Always advance execution window to prevent permanent stalling
            $rule->last_executed_at = now();
            $rule->next_execution_at = PayoutApprovalService::calculateNextExecutionTime(
                $rule->frequency,
                $rule->day_of_week,
                $rule->day_of_month,
                $rule->execution_time
            );
            $rule->save();

            return response()->json([
                'success' => true,
                'message' => count($createdPayouts) > 0
                    ? 'Pending payouts created successfully and queued for approval.'
                    : 'No qualifying bookings found for payout.',
                'data' => [
                    'created_count' => count($createdPayouts),
                    'total_pending_amount' => $totalPendingAmount,
                    'pending_payouts' => $createdPayouts,
                    'requires_approval' => true
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to create pending payouts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to create pending payouts.',
            ], 500);
        }
    }

    /**
     * Execute payouts immediately (when approval is disabled)
     *
     * @param AutoPayoutRule $rule
     * @return JsonResponse
     */
    private function executePayoutsImmediately(AutoPayoutRule $rule): JsonResponse
    {
        try {
            $service = app(PayoutApprovalService::class);

            // Detect new post-payout adjustments before calculating amounts
            app(PayoutAdjustmentService::class)->detectAndCreateAdjustments();

            $connectedAccounts = $service->getConnectedAccounts();

            $processedPayouts = [];
            $totalProcessed = 0;

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

                    $result = $service->executeImmediatePayout($rule, $account->stripe_destination_id, $payoutData);

                    $processedPayouts[] = [
                        'account_id' => $account->stripe_destination_id,
                        'payout_reference' => $result['payout_reference'],
                        'amount' => $result['amount'],
                        'stripe_payout_id' => $result['stripe_payout_id']
                    ];

                    $totalProcessed += $result['amount'];

                    Log::info("Successfully processed payout", [
                        'payout_reference' => $result['payout_reference'],
                        'stripe_payout_id' => $result['stripe_payout_id'],
                        'amount' => $result['amount']
                    ]);

                } catch (Exception $e) {
                    Log::error('Failed to process payout for account ' . $account->stripe_destination_id . ': ' . $e->getMessage());
                }
            }

            // Always advance execution window to prevent permanent stalling
            $rule->last_executed_at = now();
            $rule->next_execution_at = PayoutApprovalService::calculateNextExecutionTime(
                $rule->frequency,
                $rule->day_of_week,
                $rule->day_of_month,
                $rule->execution_time
            );
            $rule->save();

            return response()->json([
                'success' => true,
                'message' => count($processedPayouts) > 0
                    ? 'Payouts processed successfully.'
                    : 'No qualifying bookings found for payout.',
                'data' => [
                    'processed_count' => count($processedPayouts),
                    'total_amount' => $totalProcessed,
                    'payouts' => $processedPayouts,
                    'requires_approval' => false
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to process immediate payouts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to process payouts.',
            ], 500);
        }
    }

    /**
     * Get payout dashboard data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPayoutDashboardData(Request $request): JsonResponse
    {
        try {
            $lastDaysFilter = $this->getPeriodNumber($request->period);
            $operator = tourOperator();
            $timezone = $operator->timezone;
            $now = Carbon::now($timezone);

            // Resolve tax rate from operator's state via city -> state -> taxes
            $taxRate = 0.09; // fallback
            if ($operator->city_id) {
                $stateId = DB::table('cities')->where('id', $operator->city_id)->value('state_id');
                if ($stateId) {
                    $tax = DB::table('taxes')
                        ->where('state_id', $stateId)
                        ->where('status', 'Active')
                        ->where('value_type', 'Percent')
                        ->orderBy('name')
                        ->first();
                    if ($tax) {
                        $taxRate = round((float) $tax->value / 100, 4);
                    }
                }
            }
            $periodStart = $now->copy()->subDays($lastDaysFilter)->startOfDay(); // July 23, 2025 00:00:00 IST
            $periodEnd = $now->copy()->endOfDay(); // August 22, 2025 23:59:59 IST
            $previousPeriodStart = $periodStart->copy()->subDays($lastDaysFilter)->startOfDay(); // June 23, 2025 00:00:00 IST
            $previousPeriodEnd = $periodStart->copy()->subDay()->endOfDay(); // July 22, 2025 23:59:59 IST

            // Fetch active payout rule for next payout date. The query is
            // already tenant-scoped: admin routes run through
            // InitializeTenancyByDomain middleware (routes/tenant.php), so
            // the default DB connection points at the tenant DB by the time
            // this runs.
            $payoutRule = AutoPayoutRule::first();
            $nextPayout = 'Not scheduled';
            if ($payoutRule && $payoutRule->next_execution_at) {
                $next = Carbon::parse($payoutRule->next_execution_at, $timezone);
                // If the stored date is in the past, the scheduler hasn't
                // advanced it yet (e.g. scheduler down, or intentionally
                // disabled in non-prod). Surface that as "Overdue" rather
                // than silently forward-rolling — a stale date is a signal,
                // not a display bug to paper over.
                $nextPayout = $next->isPast()
                    ? 'Overdue (' . $next->format('M d') . ')'
                    : $next->format('M d');
            }

            // Pending Amount: calculated from the service using buffer_days
            $pendingAmount = 0;
            if ($payoutRule && $payoutRule->enabled) {
                $service = app(PayoutApprovalService::class);
                $accounts = $service->getConnectedAccounts();
                foreach ($accounts as $account) {
                    $payoutData = $service->calculateAccountPayoutAmount($payoutRule, $account->stripe_destination_id);
                    $pendingAmount += $payoutData['net_amount'];
                }
            }

            // Total Paid Out (current period): Sum of completed payouts in last 30 days
            $totalPaidOut = DB::table('auto_payouts as ap')
                ->where('ap.status', 'completed')
                ->whereBetween('ap.processed_date', [$periodStart, $periodEnd])
                ->sum('ap.net_amount');

            // Total Paid Out (previous period) for percentage change
            $previousTotalPaidOut = DB::table('auto_payouts as ap')
                ->where('ap.status', 'completed')
                ->whereBetween('ap.processed_date', [$previousPeriodStart, $previousPeriodEnd])
                ->sum('ap.net_amount');

            $paidOutChange = ($previousTotalPaidOut > 0) ? round(($totalPaidOut - $previousTotalPaidOut) / $previousTotalPaidOut * 100, 1) : 0;

            // Active Packages: Count of distinct active packages with payouts
            $activePackages = TourPackage::active()->count();

            // Recent Payouts: Last 10 payouts with package details
            $recentPayouts = DB::table('auto_payouts as ap')
                ->select(
                    'ap.id',
                    'ap.payout_reference as name',
                    'ap.booking_count as completed_bookings',
                    'ap.total_amount',
                    'ap.platform_fee',
                    'ap.net_amount',
                    'ap.status',
                    'ap.processed_date',
                    // Drives the "Activity Before" column, which used to repeat the
                    // processed date rather than showing the collection cutoff.
                    'ap.period_start',
                    'ap.period_end'
                )
                ->orderByDesc('ap.processed_date')
                ->limit(10)
                ->get()
                ->map(function ($payout) {
                    $payout->initials = strtoupper(implode('', array_map(fn($word) => substr($word, 0, 1), explode(' ', $payout->name))));
                    return $payout;
                });

            // Payout Summary
            $totalPayouts = DB::table('auto_payouts as ap')
                ->whereBetween('ap.processed_date', [$periodStart, $periodEnd])
                ->count();

            $completedPayouts = DB::table('auto_payouts as ap')
                ->where('ap.status', 'completed')
                ->whereBetween('ap.processed_date', [$periodStart, $periodEnd])
                ->count();

            $averagePayout = ($totalPayouts > 0) ? round($totalPaidOut / $totalPayouts, 2) : 0;
            $successRate = ($totalPayouts > 0) ? round($completedPayouts / $totalPayouts * 100, 1) : 0;
            $processingTime = '2-3 days';

            // Check Stripe payout schedule status
            $stripeScheduleStatus = null;
            if ($payoutRule && $payoutRule->enabled) {
                $scheduleService = app(PayoutApprovalService::class);
                $scheduleAccounts = $scheduleService->getConnectedAccounts();
                if ($scheduleAccounts->isNotEmpty()) {
                    try {
                        $stripeScheduleStatus = $scheduleService->verifyManualPayoutSchedule($scheduleAccounts->first()->stripe_destination_id);
                    } catch (\Exception $e) {
                        Log::error('Failed to check Stripe payout schedule: ' . $e->getMessage());
                    }
                }
            }

            // Failed payouts that need attention
            $failedPayouts = DB::table('pending_payouts')
                ->where('status', 'failed')
                ->select('id', 'payout_reference', 'net_amount', 'last_failure_reason', 'last_failed_at')
                ->orderByDesc('last_failed_at')
                ->get();

            // Pending payouts (pending_approval + approved)
            $pendingPayoutsList = DB::table('pending_payouts')
                ->whereIn('status', ['pending_approval', 'approved'])
                ->select('payout_reference', 'total_amount', 'platform_fee', 'net_amount', 'booking_count', 'status', 'created_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            // Pending payout adjustments summary
            $adjustmentService = app(PayoutAdjustmentService::class);
            $adjustmentSummary = $adjustmentService->getPendingAdjustmentTotal();

            // Upcoming bookings in the buffer window (not yet eligible for payout)
            $upcomingBookings = [];
            if ($payoutRule && $payoutRule->enabled && ($payoutRule->buffer_days ?? 0) > 0) {
                $bufferDays = $payoutRule->buffer_days;
                $bufferStart = $now->copy()->subDays($bufferDays);

                $upcomingBookings = DB::table('transactions as tra')
                    ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tra.tour_booking_id')
                    ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                    ->where('tra.status', 'Completed')
                    ->where('tra.via', 'Card')
                    // Payout Simplification (2027+): mirror calculateAccountPayoutAmount()'s
                    // payment_mode filter — a platform_held booking will never be swept by
                    // Auto Payout, so it must not show up here as "upcoming" payout money.
                    ->where('tra.payment_mode', PaymentModeResolver::DESTINATION)
                    ->where('tra.request_from', '!=', 'Insert')
                    ->where('tbm.comments', 'not like', '#%')
                    ->where('tbm.payout_made', 0)
                    ->whereRaw("TIMESTAMP(tst.date, tst.time_from) > ?", [$bufferStart->format('Y-m-d H:i:s')])
                    ->whereRaw("TIMESTAMP(tst.date, tst.time_from) <= ?", [$now->format('Y-m-d H:i:s')])
                    ->select(
                        'tbm.id as booking_id',
                        DB::raw("DATE_FORMAT(tst.date, '%b %d, %Y') as tour_date"),
                        'tbm.tourists',
                        DB::raw('SUM(tra.amount) as amount_paid'),
                        DB::raw('SUM(tra.fee_amount) as fees'),
                        DB::raw("CONCAT(DATEDIFF(DATE_ADD(tst.date, INTERVAL {$bufferDays} DAY), CURDATE()), ' day(s)') as eligible_in")
                    )
                    ->groupBy('tbm.id', 'tst.date', 'tst.time_from', 'tbm.tourists')
                    ->orderBy('tst.date', 'asc')
                    ->limit(50)
                    ->get();
            }

            // AUTO_PAYOUT global setting state - lets the dashboard render
            // a red banner when the service is disabled at the tenant level.
            // hasSetting() returns false unless the row's value is exactly 1.
            $autoPayoutEnabled = hasSetting('AUTO_PAYOUT');
            $settingsUrl = route('admin.tour-operator.setting.edit');

            // Surface the most recent scheduler tick error on the dashboard
            // so admins don't have to navigate to the Activity Log to discover
            // why payouts aren't running. Only render when the latest tick
            // itself is an error -- a single recovered error in history is
            // not actionable noise.
            $latestRunError = null;
            $latestRun = AutoPayoutRunLog::orderByDesc('id')->first();
            if ($latestRun && $latestRun->outcome === AutoPayoutRunLog::OUTCOME_ERROR) {
                // Pull out the err= fragment from the message so the banner
                // shows just the exception, not the counter prefix.
                $msg = $latestRun->message ?? '';
                $errStart = strpos($msg, 'err=');
                $errText = $errStart !== false ? substr($msg, $errStart + 4) : $msg;

                $latestRunError = [
                    'id' => $latestRun->id,
                    'created_at' => $latestRun->created_at,
                    'message' => $errText,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'auto_payout_enabled' => $autoPayoutEnabled,
                    'settings_url' => $settingsUrl,
                    'latest_run_error' => $latestRunError,
                    'pending_amount' => round((float)$pendingAmount, 2),
                    'total_paid_out' => round((float)$totalPaidOut, 2),
                    'paid_out_change' => $paidOutChange,
                    'active_packages' => $activePackages,
                    'next_payout' => $nextPayout,
                    'recent_payouts' => $recentPayouts,
                    'average_payout' => round((float)$averagePayout, 2),
                    'total_payouts' => number_format($totalPayouts, 0),
                    'success_rate' => $successRate,
                    'processing_time' => $processingTime,
                    'failed_payouts' => $failedPayouts,
                    'pending_payouts_list' => $pendingPayoutsList,
                    'stripe_schedule_status' => $stripeScheduleStatus,
                    'pending_adjustments' => $adjustmentSummary,
                    'upcoming_bookings' => $upcomingBookings,
                    'tax_rate' => $taxRate,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to generate payout dashboard data: " . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate payout dashboard data.',
            ], 500);
        }
    }

    /**
     * Get payout report data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPayoutReportData(Request $request): JsonResponse
    {
        try {
            $lastDaysFilter = $this->getPeriodNumber($request->period);
            $timezone = tourOperator()->timezone;
            $now = Carbon::now($timezone); // Current date: August 22, 2025, 5:06 PM IST
            $periodStart = $request->dateFrom ? Carbon::parse($request->dateFrom, $timezone)->startOfDay() : $now->copy()->subDays($lastDaysFilter)->startOfDay(); // Default: July 23, 2025 00:00:00 IST
            $periodEnd = $request->dateTo ? Carbon::parse($request->dateTo, $timezone)->endOfDay() : $now->copy()->endOfDay(); // Default: August 22, 2025 23:59:59 IST
            $previousPeriodStart = $periodStart->copy()->subDays($lastDaysFilter)->startOfDay();
            $previousPeriodEnd = $periodStart->copy()->subDay()->endOfDay();

            // Filter by status if provided
            $statusFilter = ($request->status && $request->status !== 'all') ? explode(',', $request->status) : ['pending', 'processing', 'completed', 'failed', 'pending_approval', 'approved'];

            // Auto payouts (completed/processing)
            $autoPayoutStatuses = array_intersect($statusFilter, ['pending', 'processing', 'completed', 'failed']);
            // Exclude 'executed' from pending_payouts -- those are already shown as 'completed' in auto_payouts
            $pendingPayoutStatuses = array_intersect($statusFilter, ['pending_approval', 'approved', 'rejected', 'failed']);

            $autoPayouts = collect();
            if (!empty($autoPayoutStatuses)) {
                $autoPayouts = DB::table('auto_payouts as ap')
                    ->join('auto_payout_rules as apr', 'ap.rule_id', '=', 'apr.id')
                    ->select(
                        'ap.id',
                        DB::raw("'auto_payout' as source"),
                        'ap.payout_reference as name',
                        'ap.booking_count as completed_bookings',
                        'ap.total_amount',
                        'ap.platform_fee',
                        'ap.net_amount',
                        'ap.status',
                        'ap.processed_date as sort_date'
                    )
                    ->whereIn('ap.status', $autoPayoutStatuses)
                    ->whereBetween('ap.processed_date', [$periodStart, $periodEnd])
                    ->when($request->search, function ($query) use ($request) {
                        $query->where('ap.payout_reference', 'like', "%{$request->search}%");
                    })
                    ->when($request->booking_id, function ($query) use ($request) {
                        $query->join('auto_payout_items as api', 'api.payout_id', '=', 'ap.id')
                              ->where('api.booking_id', $request->booking_id)
                              ->distinct();
                    })
                    ->get();
            }

            // Pending payouts (pending_approval, approved, failed, executed)
            $pendingPayouts = collect();
            if (!empty($pendingPayoutStatuses)) {
                $pendingPayouts = DB::table('pending_payouts as pp')
                    ->select(
                        'pp.id',
                        DB::raw("'pending_payout' as source"),
                        'pp.payout_reference as name',
                        'pp.booking_count as completed_bookings',
                        'pp.total_amount',
                        'pp.platform_fee',
                        'pp.net_amount',
                        'pp.status',
                        'pp.created_at as sort_date'
                    )
                    ->whereIn('pp.status', $pendingPayoutStatuses)
                    ->whereBetween('pp.created_at', [$periodStart, $periodEnd])
                    ->when($request->search, function ($query) use ($request) {
                        $query->where('pp.payout_reference', 'like', "%{$request->search}%");
                    })
                    ->when($request->booking_id, function ($query) use ($request) {
                        $query->join('pending_payout_items as ppi', 'ppi.pending_payout_id', '=', 'pp.id')
                              ->where('ppi.booking_id', $request->booking_id)
                              ->distinct();
                    })
                    ->get();
            }

            // Get adjustment counts per payout
            $payoutIds = $autoPayouts->pluck('id')->toArray();
            $adjustmentsByPayout = collect();
            if (!empty($payoutIds)) {
                $adjustmentsByPayout = DB::table('payout_adjustments')
                    ->where(function ($q) use ($payoutIds) {
                        $q->whereIn('payout_id', $payoutIds)
                          ->orWhereNull('payout_id');
                    })
                    ->where('status', 'pending')
                    ->selectRaw('payout_id, COUNT(*) as adjustment_count, SUM(CASE WHEN type = "credit" THEN net_amount ELSE -net_amount END) as adjustment_net')
                    ->groupBy('payout_id')
                    ->get()
                    ->keyBy('payout_id');
            }

            // Orphaned adjustment totals (no original payout)
            $orphanedAdjustments = DB::table('payout_adjustments')
                ->whereNull('payout_id')
                ->selectRaw("COUNT(*) as count, COALESCE(SUM(CASE WHEN type = 'credit' THEN net_amount ELSE 0 END), 0) as credit_total, COALESCE(SUM(CASE WHEN type = 'debit' THEN net_amount ELSE 0 END), 0) as debit_total")
                ->where('status', 'pending')
                ->first();

            // Merge and sort
            $payouts = $autoPayouts->concat($pendingPayouts)
                ->sortByDesc('sort_date')
                ->values()
                ->map(function ($payout) use ($adjustmentsByPayout) {
                    $payout->initials = strtoupper(implode('', array_map(fn($word) => substr($word, 0, 1), explode(' ', $payout->name ?? ''))));
                    $payout->net_amount = round((float) $payout->net_amount, 2);
                    $adj = $adjustmentsByPayout->get($payout->id);
                    $payout->has_adjustments = $adj !== null;
                    $payout->adjustment_count = $adj ? (int) $adj->adjustment_count : 0;
                    $payout->adjustment_net = $adj ? round((float) $adj->adjustment_net, 2) : 0;
                    return $payout;
                });

            // Summary metrics
            $totalPaidOut = DB::table('auto_payouts')
                ->where('status', 'completed')
                ->whereBetween('processed_date', [$periodStart, $periodEnd])
                ->sum('net_amount');

            $previousTotalPaidOut = DB::table('auto_payouts')
                ->where('status', 'completed')
                ->whereBetween('processed_date', [$previousPeriodStart, $previousPeriodEnd])
                ->sum('net_amount');

            $paidOutChange = ($previousTotalPaidOut > 0) ? round(($totalPaidOut - $previousTotalPaidOut) / $previousTotalPaidOut * 100, 1) : 0;

            $completedCount = DB::table('auto_payouts')
                ->where('status', 'completed')
                ->whereBetween('processed_date', [$periodStart, $periodEnd])
                ->count();

            $failedCount = DB::table('pending_payouts')
                ->where('status', 'failed')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $pendingCount = DB::table('pending_payouts')
                ->whereIn('status', ['pending_approval', 'approved'])
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $totalPayouts = $completedCount + $failedCount + $pendingCount;
            $averagePayout = ($completedCount > 0) ? round($totalPaidOut / $completedCount, 2) : 0;
            $successRate = ($totalPayouts > 0) ? round($completedCount / $totalPayouts * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_paid_out' => round((float)$totalPaidOut, 2),
                    'paid_out_change' => $paidOutChange,
                    'average_payout' => round((float)$averagePayout, 2),
                    'total_payouts' => $totalPayouts,
                    'success_rate' => $successRate,
                    'payouts' => $payouts,
                    'pending_adjustments' => [
                        'orphaned_count' => (int) ($orphanedAdjustments->count ?? 0),
                        'orphaned_credit_total' => round((float) ($orphanedAdjustments->credit_total ?? 0), 2),
                        'orphaned_debit_total' => round((float) ($orphanedAdjustments->debit_total ?? 0), 2),
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to generate payout report data: " . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate payout report data.',
            ], 500);
        }
    }

    /**
     * Get auto payout details by ID
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function getPayoutDetails(int $id, Request $request): JsonResponse
    {
        try {
            $autoPayout = AutoPayout::with('rule')->findOrFail($id);

            $perPage = (int) $request->get('per_page', 25);
            $itemsQuery = $autoPayout->items()
                ->with([
                    'booking:id,name,tourists,total,status,tour_package_id,tour_slot_id',
                    'booking.tourPackage:id,name',
                    'booking.tourSlotTime:id,date,time_from',
                ])
                ->select('id', 'payout_id', 'booking_id', 'total_amount', 'platform_fee', 'net_amount');

            if ($request->filled('booking_id')) {
                $itemsQuery->where('booking_id', $request->booking_id);
            }

            $items = $itemsQuery->paginate($perPage);

            // Flag items that carry an adjustment, matched by foreign key or by reference
            // so that payouts written with a suffixed reference still resolve.
            $bookingIds = collect($items->items())->pluck('booking_id')->toArray();
            $adjustedBookingIds = PayoutAdjustment::forExecutedPayout($autoPayout)
                ->whereIn('booking_id', $bookingIds)
                ->select('booking_id', 'type', 'net_amount', 'status', 'reason')
                ->get()
                ->groupBy('booking_id');

            // Get refund totals per booking for transparency
            $refundsByBooking = [];
            if (!empty($bookingIds)) {
                $refundsByBooking = DB::table('transactions')
                    ->whereIn('tour_booking_id', $bookingIds)
                    ->where('status', 'Refund')
                    ->where('via', 'Card')
                    ->groupBy('tour_booking_id')
                    ->select('tour_booking_id', DB::raw('SUM(amount) as refund_total'), DB::raw('COUNT(*) as refund_count'))
                    ->get()
                    ->keyBy('tour_booking_id')
                    ->toArray();
            }

            $itemsWithFlags = collect($items->items())->map(function ($item) use ($adjustedBookingIds, $refundsByBooking) {
                $item->has_adjustment = isset($adjustedBookingIds[$item->booking_id]);
                $item->adjustments = $adjustedBookingIds[$item->booking_id] ?? [];
                $item->customer_name = $item->booking?->name ?? null;
                $item->tour_package_name = $item->booking?->tourPackage?->name ?? null;
                $item->tour_date = $item->booking?->tourSlotTime?->date ?? null;
                $item->tourist_count = $item->booking?->tourists ?? null;
                $item->booking_total = $item->booking?->total ?? null;
                $item->booking_status = $item->booking?->status ?? null;
                $refund = $refundsByBooking[$item->booking_id] ?? null;
                $item->refund_total = $refund ? round((float) $refund->refund_total, 2) : 0;
                $item->refund_count = $refund ? (int) $refund->refund_count : 0;
                unset($item->booking);
                return $item;
            });

            // Get adjustments applied in this payout
            $appliedAdjustments = PayoutAdjustment::where('applied_in_payout_id', $autoPayout->id)
                ->select('id', 'payout_id', 'payout_reference', 'booking_id', 'type', 'gross_amount', 'platform_fee', 'net_amount', 'reason', 'status')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'payout' => $autoPayout,
                    // Preformatted server-side: the window is stored as operator-local
                    // wall time, so letting the browser reinterpret it would shift it.
                    'period_display' => $autoPayout->periodDisplay(),
                    'rule_snapshot' => $autoPayout->rule ? [
                        'name' => $autoPayout->rule->name,
                    ] : null,
                    'items' => $itemsWithFlags->values(),
                    'items_total' => $items->total(),
                    'items_page' => $items->currentPage(),
                    'items_last_page' => $items->lastPage(),
                    'applied_adjustments' => $appliedAdjustments,
                    'logs' => []
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch auto payout details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch payout details.'
            ], 500);
        }
    }

    /**
     * Export all payout items as JSON (no pagination) with tour date.
     */
    public function exportPayoutDetails(int $id, PayoutExportService $exportService): JsonResponse
    {
        try {
            $autoPayout = AutoPayout::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $exportService->buildForAutoPayout($autoPayout),
            ]);
        } catch (ModelNotFoundException $e) {
            // A missing payout is a bad request, not a server fault. This previously fell
            // through to the generic handler below and surfaced as a 500 -- which is what
            // the pending-payout export was hitting, because pending payout ids do not
            // exist in auto_payouts.
            Log::warning('Auto payout export requested for an unknown payout.', [
                'payout_id' => $id,
                'tenant' => tenant('id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'That payout could not be found. It may belong to a payout awaiting approval.',
            ], 404);
        } catch (\Throwable $e) {
            // Throwable, not Exception: an out-of-memory or timeout on a large export is an
            // Error and was escaping this handler entirely, leaving nothing in the log.
            Log::error('Failed to export payout details: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'payout_id' => $id,
                'tenant' => tenant('id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to export payout details.',
            ], 500);
        }
    }

    /**
     * Period roll-up for an executed payout: bookings, tourists and amount per
     * category, what is still unpaid, and the Stripe balance the deposit landed on.
     *
     * @param int $id
     * @param PayoutAggregateService $aggregateService
     * @return JsonResponse
     */
    public function getPayoutSummary(int $id, PayoutAggregateService $aggregateService): JsonResponse
    {
        try {
            $autoPayout = AutoPayout::findOrFail($id);

            $summary = $aggregateService->buildForAutoPayout($autoPayout);
            $summary['balance_before_deposit'] = $this->balanceBeforeDeposit($autoPayout);
            $summary['payout_reference'] = $autoPayout->payout_reference;
            $summary['net_amount'] = round((float) $autoPayout->net_amount, 2);

            return response()->json(['success' => true, 'data' => $summary]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'That payout could not be found.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to build payout summary: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'payout_id' => $id,
                'tenant' => tenant('id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to build the payout summary.',
            ], 500);
        }
    }

    /**
     * The connected account's available balance immediately before this payout was
     * sent, recorded in the execution log at the moment of payout.
     *
     * Immediate-mode payouts never pass through the approval log, so there is nothing
     * to report for those -- null, rendered as "Not recorded", rather than a live
     * balance that would silently mean something else.
     */
    private function balanceBeforeDeposit(AutoPayout $autoPayout): ?float
    {
        $log = DB::table('payout_approval_logs')
            ->where('action', 'executed')
            ->where('metadata->auto_payout_id', $autoPayout->id)
            ->orderByDesc('id')
            ->first();

        if (!$log || empty($log->metadata)) {
            return null;
        }

        $metadata = json_decode($log->metadata, true);

        return isset($metadata['balance_available']) ? round((float) $metadata['balance_available'], 2) : null;
    }

    /**
     * Test the auto payout command (for development/testing)
     *
     * @return JsonResponse
     */
    public function testCommand(): JsonResponse
    {
        try {
            // Get current tenant
            $tenant = tenancy()->tenant ?? null;
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenant context available.',
                ], 400);
            }

            // Run the command with tenant context and dry-run flag
            Artisan::call('tenants:run', [
                'commandname' => 'autopayout:process',
                '--tenants'   => [$tenant->id],
                '--option'    => ['dry-run'],
            ]);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Command executed successfully (dry run)',
                'output' => $output
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Command execution failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force-run the auto-payout process on demand.
     *
     * Invokes the same command the scheduler runs, without --dry-run.
     * ProcessAutoPayouts::handle() owns its own cache lock, so this
     * cannot race a concurrent scheduler tick in production.
     */
    public function forceRun(): JsonResponse
    {
        try {
            $tenant = tenancy()->tenant ?? null;
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenant context available.',
                ], 400);
            }

            $adminId = Auth::guard('admin')->id();
            Log::info('Auto payout force-run triggered', [
                'tenant_id' => $tenant->id,
                'admin_id'  => $adminId,
            ]);

            Artisan::call('tenants:run', [
                'commandname' => 'autopayout:process',
                '--tenants'   => [$tenant->id],
            ]);
            $output = Artisan::output();

            Log::info('Auto payout force-run completed', [
                'tenant_id' => $tenant->id,
                'admin_id'  => $adminId,
                'output'    => $output,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout process executed successfully.',
                'output'  => $output,
            ]);
        } catch (Exception $e) {
            Log::error('Auto payout force-run failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payout execution failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fix Stripe payout schedule by setting all connected accounts to manual.
     *
     * @return JsonResponse
     */
    public function fixStripeSchedule(): JsonResponse
    {
        try {
            $rule = AutoPayoutRule::first();

            if (!$rule || !$rule->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'No enabled payout rule found.',
                ], 400);
            }

            $service = app(PayoutApprovalService::class);
            $accounts = $service->getConnectedAccounts();

            if ($accounts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No connected Stripe accounts found.',
                ], 400);
            }

            $fixed = 0;
            foreach ($accounts as $account) {
                Account::update(
                    $account->stripe_destination_id,
                    [
                        'settings' => [
                            'payouts' => [
                                'schedule' => ['interval' => 'manual'],
                            ],
                        ],
                    ]
                );
                Log::info("Stripe payout schedule set to manual for account {$account->stripe_destination_id}");
                $fixed++;
            }

            return response()->json([
                'success' => true,
                'message' => "Stripe payout schedule set to manual for {$fixed} account(s).",
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fix Stripe payout schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Stripe payout schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse period string to days
     *
     * @param string $period
     * @return int
     */
    public function getPeriodNumber($period)
    {
        $number = (int) filter_var($period, FILTER_SANITIZE_NUMBER_INT);
        $unit   = preg_replace('/[0-9]/', '', $period); // get only unit (d, m, y)

        switch ($unit) {
            case 'd':
                $days = $number;
                break;
            case 'm':
                $days = $number * 30; // approx 30 days
                break;
            case 'y':
                $days = $number * 365; // approx 365 days
                break;
            default:
                $days = $number;
        }

        return $days;
    }

    /**
     * Return the most recent scheduler tick log rows for the Activity tab.
     *
     * The scheduler writes one row per minute regardless of whether anything
     * happened, so this stream is how an admin diagnoses why payouts aren't
     * running (most recent outcome = skipped_disabled means AUTO_PAYOUT is off,
     * skipped_lock_held means a previous tick is wedged, etc.).
     */
    public function getRunLog(Request $request): JsonResponse
    {
        try {
            $limit = min(max((int) $request->query('limit', 50), 1), 200);

            $rows = AutoPayoutRunLog::orderByDesc('id')
                ->limit($limit)
                ->get([
                    'id',
                    'rule_id',
                    'outcome',
                    'message',
                    'payouts_created',
                    'payouts_executed',
                    'duration_ms',
                    'created_at',
                ]);

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch run log.',
            ], 500);
        }
    }
}
