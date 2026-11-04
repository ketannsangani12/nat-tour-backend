<?php

namespace App\Http\Controllers\Admin\AutoPayout;


use App\Http\Controllers\Controller;
use App\Models\PendingPayout;
use App\Models\PayoutApprovalLog;
use App\Services\PayoutApprovalService;
use App\Services\PayoutExportService;
use App\Services\PayoutAggregateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Balance;
use Exception;

class PayoutApprovalController extends Controller
{
    protected PayoutApprovalService $approvalService;

    public function __construct()
    {
        parent::__construct();
        $this->approvalService = new PayoutApprovalService();
        Stripe::setApiKey(getStripeSecretKey());
    }

    /**
     * Get pending payouts for approval
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPendingPayouts(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->get('status', 'pending_approval'),
                'search' => $request->get('search'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'min_amount' => $request->get('min_amount'),
                'max_amount' => $request->get('max_amount'),
                'per_page' => $request->get('per_page', 15)
            ];

            $payouts = $this->approvalService->getPendingPayouts($filters);

            return response()->json([
                'success' => true,
                'data' => $payouts->items(),
                'pagination' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'per_page' => $payouts->perPage(),
                    'total' => $payouts->total(),
                    'from' => $payouts->firstItem(),
                    'to' => $payouts->lastItem()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch pending payouts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch pending payouts.'
            ], 500);
        }
    }

    /**
     * Get payout details for review
     *
     * @param PendingPayout $pendingPayout
     * @return JsonResponse
     */
    public function getPayoutDetails(PendingPayout $pendingPayout, Request $request): JsonResponse
    {
        try {
            $pendingPayout->load(['rule', 'logs.user', 'approvedBy', 'rejectedBy']);

            $perPage = (int) $request->get('per_page', 500);
            $itemsQuery = $pendingPayout->items()
                ->with([
                    'booking:id,name,tourists,total,status,tour_package_id,tour_slot_id',
                    'booking.tourPackage:id,name',
                    'booking.tourSlotTime:id,date,time_from',
                ])
                ->select('id', 'pending_payout_id', 'booking_id', 'total_amount', 'platform_fee', 'net_amount');

            if ($request->filled('booking_id')) {
                $itemsQuery->where('booking_id', $request->booking_id);
            }

            $items = $itemsQuery->paginate($perPage);

            // Flag items that have adjustments (matched by payout_reference)
            $bookingIds = collect($items->items())->pluck('booking_id')->toArray();
            $adjustedBookingIds = \App\Models\PayoutAdjustment::forPayoutReference($pendingPayout->payout_reference)
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

            // Adjustment summary for approval context
            $adjustmentSummary = \App\Models\PayoutAdjustment::forPayoutReference($pendingPayout->payout_reference)
                ->selectRaw("
                    COUNT(*) as count,
                    COALESCE(SUM(CASE WHEN type = 'credit' THEN net_amount ELSE 0 END), 0) as credit_total,
                    COALESCE(SUM(CASE WHEN type = 'debit' THEN net_amount ELSE 0 END), 0) as debit_total
                ")
                ->first();

            // Fetch current Stripe balance for the connected account
            $stripeBalance = null;
            if ($pendingPayout->stripe_account_id) {
                try {
                    $balance = Balance::retrieve(['stripe_account' => $pendingPayout->stripe_account_id]);
                    $available = $balance->available[0] ?? null;
                    $pending = $balance->pending[0] ?? null;
                    $stripeBalance = [
                        'available' => $available ? $available->amount / 100 : 0,
                        'pending' => $pending ? $pending->amount / 100 : 0,
                        'currency' => $available ? $available->currency : 'usd',
                    ];
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch Stripe balance for account ' . $pendingPayout->stripe_account_id . ': ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payout' => $pendingPayout,
                    // See AutoPayoutController::getPayoutDetails -- formatted server-side
                    // because the window is stored as operator-local wall time.
                    'period_display' => $pendingPayout->periodDisplay(),
                    'rule_snapshot' => $pendingPayout->rule_snapshot,
                    'stripe_balance' => $stripeBalance,
                    'items' => $itemsWithFlags->values(),
                    'items_total' => $items->total(),
                    'items_page' => $items->currentPage(),
                    'items_last_page' => $items->lastPage(),
                    'adjustment_summary' => [
                        'count' => (int) ($adjustmentSummary->count ?? 0),
                        'credit_total' => round((float) ($adjustmentSummary->credit_total ?? 0), 2),
                        'debit_total' => round((float) ($adjustmentSummary->debit_total ?? 0), 2),
                    ],
                    'logs' => $pendingPayout->logs->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'action' => $log->formatted_action,
                            'user' => $log->actor_name,
                            'notes' => $log->notes,
                            'created_at' => $log->created_at->format('M d, Y H:i'),
                            'ip_address' => $log->ip_address,
                            'metadata' => $log->metadata
                        ];
                    })
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch payout details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch payout details.'
            ], 500);
        }
    }

    /**
     * Export all items of a pending payout as JSON (no pagination).
     *
     * The dashboard's "Export CSV" button is shown for both payout sources. Before this
     * existed it always called the auto-payout export, which looked a pending payout id
     * up in auto_payouts -- returning a 500, or worse, a different payout's rows.
     *
     * @param PendingPayout $pendingPayout
     * @param PayoutExportService $exportService
     * @return JsonResponse
     */
    public function exportPayoutDetails(PendingPayout $pendingPayout, PayoutExportService $exportService): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $exportService->buildForPendingPayout($pendingPayout),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to export pending payout details: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'pending_payout_id' => $pendingPayout->id,
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
     * Period roll-up for a payout that has not executed yet.
     *
     * The weekly email goes out before the money moves, so "balance before deposit"
     * here is the account's current available balance -- which is exactly the balance
     * this payout will be taken from.
     *
     * @param PendingPayout $pendingPayout
     * @param PayoutAggregateService $aggregateService
     * @return JsonResponse
     */
    public function getPayoutSummary(PendingPayout $pendingPayout, PayoutAggregateService $aggregateService): JsonResponse
    {
        try {
            $summary = $aggregateService->buildForPendingPayout($pendingPayout);
            $summary['payout_reference'] = $pendingPayout->payout_reference;
            $summary['net_amount'] = round((float) $pendingPayout->net_amount, 2);
            $summary['balance_before_deposit'] = null;

            if ($pendingPayout->stripe_account_id) {
                try {
                    $balance = Balance::retrieve(['stripe_account' => $pendingPayout->stripe_account_id]);
                    $available = $balance->available[0] ?? null;
                    $summary['balance_before_deposit'] = $available ? round($available->amount / 100, 2) : null;
                } catch (\Exception $e) {
                    // A Stripe hiccup must not take the whole summary down -- the
                    // category roll-up is still useful without the balance line.
                    Log::warning('Failed to fetch Stripe balance for payout summary: ' . $e->getMessage(), [
                        'stripe_account_id' => $pendingPayout->stripe_account_id,
                    ]);
                }
            }

            return response()->json(['success' => true, 'data' => $summary]);
        } catch (\Throwable $e) {
            Log::error('Failed to build pending payout summary: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'pending_payout_id' => $pendingPayout->id,
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
     * Approve a pending payout
     *
     * @param Request $request
     * @param PendingPayout $pendingPayout
     * @return JsonResponse
     */
    public function approve(Request $request, PendingPayout $pendingPayout): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$pendingPayout->isPendingApproval()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payout is not in pending approval status.'
                ], 400);
            }

            $userId = Auth::guard('admin')->id();
            $ipAddress = $request->ip();
            $notes = $request->get('notes');

            $this->approvalService->approvePayout($pendingPayout, $userId, $ipAddress, $notes);

            return response()->json([
                'success' => true,
                'message' => 'Payout approved successfully.',
                'data' => [
                    'payout_reference' => $pendingPayout->payout_reference,
                    'status' => 'approved'
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to approve payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to approve payout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a pending payout
     *
     * @param Request $request
     * @param PendingPayout $pendingPayout
     * @return JsonResponse
     */
    public function reject(Request $request, PendingPayout $pendingPayout): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$pendingPayout->isPendingApproval()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payout is not in pending approval status.'
                ], 400);
            }

            $userId = Auth::guard('admin')->id();
            $ipAddress = $request->ip();
            $reason = $request->get('reason');

            $this->approvalService->rejectPayout($pendingPayout, $userId, $reason, $ipAddress);

            return response()->json([
                'success' => true,
                'message' => 'Payout rejected successfully.',
                'data' => [
                    'payout_reference' => $pendingPayout->payout_reference,
                    'status' => 'rejected',
                    'reason' => $reason
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to reject payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to reject payout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve payouts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payout_ids' => 'required|array|min:1',
                'payout_ids.*' => 'required|integer|exists:pending_payouts,id',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payoutIds = $request->get('payout_ids');
            $userId = Auth::guard('admin')->id();
            $ipAddress = $request->ip();
            $notes = $request->get('notes');

            $result = $this->approvalService->bulkApprove($payoutIds, $userId, $ipAddress, $notes);

            return response()->json([
                'success' => true,
                'message' => 'Bulk approval completed.',
                'data' => [
                    'approved_count' => count($result['approved']),
                    'failed_count' => count($result['failed']),
                    'approved' => $result['approved'],
                    'failed' => $result['failed']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to bulk approve payouts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to bulk approve payouts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reject payouts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkReject(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payout_ids' => 'required|array|min:1',
                'payout_ids.*' => 'required|integer|exists:pending_payouts,id',
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payoutIds = $request->get('payout_ids');
            $userId = Auth::guard('admin')->id();
            $ipAddress = $request->ip();
            $reason = $request->get('reason');

            $result = $this->approvalService->bulkReject($payoutIds, $userId, $reason, $ipAddress);

            return response()->json([
                'success' => true,
                'message' => 'Bulk rejection completed.',
                'data' => [
                    'rejected_count' => count($result['rejected']),
                    'failed_count' => count($result['failed']),
                    'rejected' => $result['rejected'],
                    'failed' => $result['failed']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to bulk reject payouts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to bulk reject payouts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approval dashboard data
     *
     * @return JsonResponse
     */
    public function getDashboardData(): JsonResponse
    {
        try {
            $pendingCount = $this->approvalService->getPendingCount();
            
            $approvedToday = PendingPayout::approved()
                ->whereDate('approved_at', today())
                ->count();

            $rejectedToday = PendingPayout::rejected()
                ->whereDate('rejected_at', today())
                ->count();

            $totalPendingAmount = PendingPayout::pendingApproval()
                ->sum('net_amount');

            $failedCount = PendingPayout::failed()->count();
            $failedAmount = PendingPayout::failed()->sum('net_amount');

            $recentActivity = PendingPayout::with(['approvedBy', 'rejectedBy'])
                ->whereIn('status', ['approved', 'rejected', 'failed'])
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($payout) {
                    return [
                        'payout_reference' => $payout->payout_reference,
                        'status' => $payout->status,
                        'amount' => $payout->net_amount,
                        'user' => $payout->status === 'approved' 
                            ? $payout->approvedBy?->name 
                            : $payout->rejectedBy?->name,
                        'date' => $payout->updated_at->format('M d, H:i')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_count' => $pendingCount,
                    'approved_today' => $approvedToday,
                    'rejected_today' => $rejectedToday,
                    'total_pending_amount' => round((float)$totalPendingAmount, 2),
                    'failed_count' => $failedCount,
                    'failed_amount' => round((float)$failedAmount, 2),
                    'recent_activity' => $recentActivity
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch approval dashboard data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard data.'
            ], 500);
        }
    }

    /**
     * Get activity log entries
     */
    public function getActivityLog(Request $request): JsonResponse
    {
        try {
            $query = PayoutApprovalLog::with(['pendingPayout', 'user'])
                ->orderBy('created_at', 'desc');

            if ($request->get('action') && $request->get('action') !== 'all') {
                $query->where('action', $request->get('action'));
            }

            if ($request->get('search')) {
                $query->whereHas('pendingPayout', function ($q) use ($request) {
                    $q->where('payout_reference', 'like', '%' . $request->get('search') . '%');
                });
            }

            if ($request->get('date_from')) {
                $query->whereDate('created_at', '>=', $request->get('date_from'));
            }

            if ($request->get('date_to')) {
                $query->whereDate('created_at', '<=', $request->get('date_to'));
            }

            $logs = $query->paginate($request->get('per_page', 25));

            $data = collect($logs->items())->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'payout_reference' => $log->pendingPayout?->payout_reference ?? 'N/A',
                    'user' => $log->user?->name ?? 'System',
                    'notes' => $log->notes,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('M d, Y H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch activity log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch activity log.'
            ], 500);
        }
    }

    /**
     * Re-approve a failed payout
     */
    public function reapprove(Request $request, PendingPayout $pendingPayout): JsonResponse
    {
        try {
            if (!$pendingPayout->isFailed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payout is not in failed status.'
                ], 400);
            }

            $userId = Auth::guard('admin')->id();
            $ipAddress = $request->ip();
            $notes = $request->get('notes');

            $this->approvalService->reapprovePayout($pendingPayout, $userId, $ipAddress, $notes);

            return response()->json([
                'success' => true,
                'message' => 'Payout re-approved for retry.',
                'data' => [
                    'payout_reference' => $pendingPayout->payout_reference,
                    'status' => 'approved'
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to re-approve payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to re-approve payout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a failed payout
     */
    public function cancel(Request $request, PendingPayout $pendingPayout): JsonResponse
    {
        try {
            if (!$pendingPayout->isFailed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only failed payouts can be cancelled.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide a reason for cancellation.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::guard('admin')->id();
            $ipAddress = $request->ip();

            DB::beginTransaction();

            $pendingPayout->update(['status' => 'cancelled']);

            PayoutApprovalLog::create([
                'pending_payout_id' => $pendingPayout->id,
                'action' => 'cancelled',
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $request->userAgent(),
                'notes' => $request->get('reason'),
                'created_at' => now(),
            ]);

            DB::commit();

            Log::info("Payout cancelled", [
                'payout_id' => $pendingPayout->id,
                'payout_reference' => $pendingPayout->payout_reference,
                'cancelled_by' => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout cancelled successfully.',
                'data' => [
                    'payout_reference' => $pendingPayout->payout_reference,
                    'status' => 'cancelled'
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to cancel payout: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate a pending payout against current transaction data.
     * Removes fully refunded bookings and updates changed amounts.
     */
    public function recalculate(PendingPayout $pendingPayout): JsonResponse
    {
        try {
            if (!in_array($pendingPayout->status, ['pending_approval', 'approved'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or approved payouts can be recalculated.'
                ], 422);
            }

            $result = $this->approvalService->recalculatePendingPayout($pendingPayout);

            $removedCount = count($result['removed']);
            $updatedCount = count($result['updated']);

            $message = 'Payout recalculated.';
            if ($removedCount > 0 || $updatedCount > 0) {
                $parts = [];
                if ($removedCount > 0) $parts[] = "{$removedCount} booking(s) removed";
                if ($updatedCount > 0) $parts[] = "{$updatedCount} booking(s) updated";
                $message = 'Payout recalculated: ' . implode(', ', $parts) . '.';
            } else {
                $message = 'Payout recalculated. No changes detected.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Failed to recalculate payout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to recalculate payout: ' . $e->getMessage()
            ], 500);
        }
    }
}