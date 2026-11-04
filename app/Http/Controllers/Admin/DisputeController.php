<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dispute\DisputeService;
use App\Services\Dispute\DisputeAuditLogger;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * DisputeController
 * 
 * Handles all dispute management operations for Admin users.
 * Provides CRUD operations, filtering, search, and evidence submission.
 * 
 * Requirements: 2.1, 2.2, 2.7, 4.1
 */
class DisputeController extends Controller
{
    /**
     * The dispute service instance.
     */
    protected DisputeService $disputeService;

    /**
     * The audit logger instance.
     */
    protected DisputeAuditLogger $auditLogger;

    /**
     * Create a new controller instance.
     */
    public function __construct(DisputeService $disputeService, DisputeAuditLogger $auditLogger)
    {
        parent::__construct();

        $this->disputeService = $disputeService;
        $this->auditLogger = $auditLogger;

        $this->middleware('can:view-payment-dispute');
    }

    /**
     * Display a listing of disputes with filtering, sorting, and search.
     * 
     * @param Request $request
     * @return View|JsonResponse|RedirectResponse
     */
    public function index(Request $request)
    {
        try {
            // Check if this is an analytics view request
            $viewType = $request->get('view', 'list');

            $page_title = 'Payment Disputes';

            if ($viewType === 'analytics') {
                return $this->showAnalyticsView($request);
            }

            // Build filters from session
            $filters = $this->buildFiltersFromSession(session('dispute_filters', []));

            // Get sorting parameters
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            // Validate sort parameters
            $allowedSortFields = ['created_at', 'evidence_due_by', 'amount', 'status', 'reason'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }

            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            // Add sorting to filters
            $filters['sort_by'] = $sortBy;
            $filters['sort_direction'] = $sortDirection;

            // Get paginated disputes with relationships
            $disputes = $this->disputeService->getPaginatedDisputesForTenant(
                $filters,
                $request->get('per_page', 25),
                ['booking', 'transaction', 'evidence']
            );

            // Get summary statistics
            $statistics = $this->disputeService->getDisputeStatistics();

            // Log dispute list access for audit trail
            $this->auditLogger->logListAccess('dispute_list_accessed', [
                'filters_applied' => $filters,
                'results_count' => $disputes->total() ?? $disputes->count(),
                'page' => $request->get('page', 1)
            ]);

            // If this is an AJAX request, return JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'disputes' => $disputes,
                        'statistics' => $statistics,
                        'filters' => $filters
                    ]
                ]);
            }

            // Return view for regular requests
            return view('users.admin.disputes.index', compact(
                'disputes',
                'statistics',
                'filters',
                'sortBy',
                'sortDirection',
                'page_title'
            ));
        } catch (Exception $e) {
            Log::error('Failed to load disputes index', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load disputes. Please try again.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to load disputes. Please try again.');
        }
    }

    /**
     * Show the analytics view for dispute reporting and trends
     * Requirements: 12.1, 12.2, 12.3 - Analytics dashboard with trends, breakdowns, and financial impact
     */
    protected function showAnalyticsView(Request $request)
    {
        try {
            $sessionFilters = session('dispute_analytics_filters', []);

            $dateFrom = $sessionFilters['date_from'] ?? null;
            $dateTo   = $sessionFilters['date_to']   ?? null;
            $groupBy  = $sessionFilters['group_by']  ?? 'month';
            $status   = $sessionFilters['status']    ?? null;

            $dateRange = [];
            if ($dateFrom) {
                $dateRange['start'] = \Carbon\Carbon::parse($dateFrom);
            }
            if ($dateTo) {
                $dateRange['end'] = \Carbon\Carbon::parse($dateTo);
            }

            if (empty($dateRange)) {
                $dateRange = [
                    'start' => now()->subDays(30),
                    'end'   => now(),
                ];
            }

            $trendsGroupBy = in_array($groupBy, ['day', 'week', 'month']) ? $groupBy : 'month';
            $statusFilter  = $status ?: null;

            // Get comprehensive analytics
            $analytics = $this->disputeService->getComprehensiveAnalytics($dateRange, $trendsGroupBy, $statusFilter);

            // Log analytics access
            $this->auditLogger->logListAccess('dispute_analytics_accessed', [
                'date_range' => $dateRange,
                'group_by' => $trendsGroupBy
            ]);

            // If this is an AJAX request, return JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'analytics' => $analytics,
                        'meta' => [
                            'date_range' => $dateRange,
                            'trends_group_by' => $trendsGroupBy
                        ]
                    ]
                ]);
            }

            // Return analytics view
            $totalReversed = collect($analytics['dispute_list'])->sum('reversed_amount');

            $page_title = 'Dispute Analytics';
            return view('users.admin.disputes.analytics', compact(
                'analytics',
                'dateRange',
                'trendsGroupBy',
                'statusFilter',
                'totalReversed',
                'page_title'
            ));
        } catch (Exception $e) {
            Log::error('Failed to load dispute analytics view', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load analytics. Please try again.'
                ], 500);
            }

            return redirect()->route(prefixedRouteName('disputes.index'))
                ->with('error', 'Failed to load analytics. Please try again.');
        }
    }

    /**
     * Display the specified dispute with timeline and evidence.
     * 
     * @param string $disputeId
     * @param Request $request
     * @return View|JsonResponse|RedirectResponse
     */
    public function show(string $disputeId, Request $request)
    {
        try {
            // Get dispute with all related data
            $dispute = $this->disputeService->getDisputeById(
                (int) $disputeId,
                ['booking.tourSlotTime', 'transaction', 'evidence', 'timeline']
            );

            if (!$dispute) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dispute not found.'
                    ], 404);
                }

                return redirect()->route(prefixedRouteName('disputes.index'))
                    ->with('error', 'Dispute not found.');
            }

            // Use already-eager-loaded relations, sorted in PHP to avoid extra queries
            $timeline = $dispute->timeline->sortByDesc('created_at');
            $evidence = $dispute->evidence->sortByDesc('created_at');

            // Calculate days until deadline
            $daysUntilDeadline = $dispute->getDaysUntilDeadline();
            $isOverdue = $dispute->isOverdue();
            $requiresAttention = $dispute->requiresAttention();

            // Log dispute access for audit trail
            $this->auditLogger->logDisputeAccess($dispute, 'dispute_viewed', [
                'dispute_id' => $dispute->id,
                'stripe_dispute_id' => $dispute->stripe_dispute_id,
                'view_type' => $request->ajax() ? 'ajax' : 'web',
                'includes_timeline' => true,
                'includes_evidence' => true
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'dispute' => $dispute,
                        'timeline' => $timeline,
                        'evidence' => $evidence,
                        'days_until_deadline' => $daysUntilDeadline,
                        'is_overdue' => $isOverdue,
                        'requires_attention' => $requiresAttention
                    ]
                ]);
            }

            $page_title = 'Dispute #' . $disputeId;
            return view('users.admin.disputes.show', compact(
                'dispute',
                'timeline',
                'evidence',
                'daysUntilDeadline',
                'isOverdue',
                'requiresAttention',
                'page_title'
            ));
        } catch (Exception $e) {
            Log::error('Failed to load dispute details', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load dispute details. Please try again.'
                ], 500);
            }

            return redirect()->route(prefixedRouteName('disputes.index'))
                ->with('error', 'Failed to load dispute details. Please try again.');
        }
    }

    /**
     * Submit evidence for a dispute.
     * 
     * @param Request $request
     * @param string $disputeId
     * @return JsonResponse
     */
    public function submitEvidence(Request $request, string $disputeId): JsonResponse
    {
        try {
            // Validate the request
            $evidenceTypes = 'receipt,customer_communication,service_documentation,shipping_documentation,duplicate_charge_documentation,refund_policy,cancellation_policy,customer_signature,uncategorized_file';
            $validator = Validator::make($request->all(), [
                'evidence_file_types'   => 'nullable|array',
                'evidence_file_types.*' => "nullable|string|in:{$evidenceTypes}",
                'evidence_files'        => 'nullable|array',
                'evidence_files.*'      => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
                'evidence_text'         => 'nullable|string|max:5000',
                'description'           => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare evidence data (text)
            $evidenceData = [];
            if ($request->filled('evidence_text')) {
                $evidenceData['uncategorized_text'] = $request->input('evidence_text');
            }

            // Build files array from multi-file submission — last file wins per type
            $files = [];
            $fileUploads = $request->file('evidence_files', []);
            $fileTypes   = $request->input('evidence_file_types', []);
            foreach ((array) $fileUploads as $index => $uploadedFile) {
                if ($uploadedFile) {
                    $type = $fileTypes[$index] ?? 'uncategorized_file';
                    $files[$type] = $uploadedFile;
                }
            }

            if (empty($files) && empty($evidenceData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide at least one file or some evidence text.',
                    'errors' => ['evidence_files' => ['Please provide at least one file or some evidence text.']]
                ], 422);
            }

            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId);
            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found.'
                ], 404);
            }

            // Check if dispute can accept evidence
            if (!in_array($dispute->status, ['warning_needs_response', 'under_review'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot submit evidence for disputes with status: ' . $dispute->status_label
                ], 400);
            }

            // Submit evidence to Stripe
            $success = $this->disputeService->submitEvidence(
                (int) $disputeId,
                $evidenceData,
                $files
            );

            if ($success) {
                try {
                    DisputeTimeline::logEvidenceSubmission($dispute, [
                        'evidence_types' => array_keys($files),
                        'file_count'     => count($files),
                        'has_text'       => $request->filled('evidence_text'),
                        'description'    => $request->input('description'),
                        'submitted_by'   => Auth::user()?->name ?? 'Unknown',
                        'submitted_at'   => now()->toISOString()
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to log evidence submission timeline', ['error' => $e->getMessage()]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Evidence submitted successfully.',
                    'data'    => [
                        'dispute_id'     => $disputeId,
                        'evidence_types' => array_keys($files),
                        'file_count'     => count($files),
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit evidence to Stripe. Please try again.'
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to submit evidence', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->except(['evidence_files'])
            ]);

            // Surface readable Stripe errors directly; hide internal details otherwise
            $raw = $e->getMessage();
            if (str_contains($raw, 'maximum number of evidence submissions')) {
                $userMessage = 'Stripe rejected this submission: you have already reached the maximum number of evidence submissions allowed for this dispute. No further evidence can be added.';
            } elseif (str_starts_with($raw, 'Invalid Stripe request: ')) {
                $userMessage = substr($raw, strlen('Invalid Stripe request: '));
            } else {
                $userMessage = 'Failed to submit evidence. Please try again.';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage
            ], 500);
        }
    }

    /**
     * Update dispute status or information.
     * 
     * @param Request $request
     * @param string $disputeId
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $disputeId): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:warning_needs_response,under_review,charge_refunded,lost,won',
                'notes' => 'nullable|string|max:1000',
                'internal_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId);
            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found.'
                ], 404);
            }

            $oldStatus = $dispute->status;
            $newStatus = $request->input('status');

            // Update dispute status
            $updatedDispute = $this->disputeService->updateDisputeStatus(
                (int) $disputeId,
                $newStatus,
                [
                    'notes' => $request->input('notes'),
                    'internal_notes' => $request->input('internal_notes'),
                    'updated_by' => Auth::user()?->name ?? 'Unknown',
                    'updated_via' => 'admin_interface'
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispute status updated successfully.',
                'data' => [
                    'dispute_id' => $disputeId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'status_label' => $updatedDispute->status_label
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update dispute status', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update dispute status. Please try again.'
            ], 500);
        }
    }

    /**
     * Sync dispute data with Stripe.
     * 
     * @param Request $request
     * @param string $disputeId
     * @return JsonResponse
     */
    public function syncWithStripe(Request $request, string $disputeId): JsonResponse
    {
        try {
            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId);
            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found.'
                ], 404);
            }

            // Sync with Stripe
            $syncedDispute = $this->disputeService->syncWithStripe($disputeId);

            return response()->json([
                'success' => true,
                'message' => 'Dispute synchronized with Stripe successfully.',
                'data' => [
                    'dispute' => $syncedDispute,
                    'synced_at' => now()->toISOString()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to sync dispute with Stripe', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync dispute with Stripe. Please try again.'
            ], 500);
        }
    }

    /**
     * Get dispute timeline.
     * 
     * @param Request $request
     * @param string $disputeId
     * @return JsonResponse
     */
    public function timeline(Request $request, string $disputeId): JsonResponse
    {
        try {
            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId);
            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found.'
                ], 404);
            }

            // Get timeline events
            $timeline = $dispute->timeline()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'timeline' => $timeline,
                    'count' => $timeline->count()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to load dispute timeline', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dispute timeline. Please try again.'
            ], 500);
        }
    }

    /**
     * Get dispute statistics and analytics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function analytics(Request $request)
    {
        return $this->showAnalyticsView($request);
    }

    /**
     * Export dispute analytics as a CSV download.
     */
    public function exportAnalytics(Request $request): StreamedResponse
    {
        $sessionFilters = session('dispute_analytics_filters', []);

        $dateFrom = $sessionFilters['date_from'] ?? null;
        $dateTo   = $sessionFilters['date_to']   ?? null;
        $groupBy  = $sessionFilters['group_by']  ?? 'month';

        $dateRange = [];
        if ($dateFrom) {
            $dateRange['start'] = \Carbon\Carbon::parse($dateFrom);
        }
        if ($dateTo) {
            $dateRange['end'] = \Carbon\Carbon::parse($dateTo);
        }

        $trendsGroupBy = in_array($groupBy, ['day', 'week', 'month']) ? $groupBy : 'month';

        $analytics = $this->disputeService->getComprehensiveAnalytics($dateRange, $trendsGroupBy);

        $dateFrom = isset($dateRange['start']) ? $dateRange['start']->format('Y-m-d') : 'all-time';
        $dateTo   = isset($dateRange['end'])   ? $dateRange['end']->format('Y-m-d')   : now()->format('Y-m-d');
        $fileName = 'dispute-analytics-' . $dateFrom . '-to-' . $dateTo . '.csv';
        $currency = currencyFormation(false, true);

        return response()->streamDownload(function () use ($analytics, $dateFrom, $dateTo, $currency) {
            $file = fopen('php://output', 'w');

            // Report header
            fputcsv($file, ['Dispute Analytics Report']);
            fputcsv($file, ['Date Range:', $dateFrom . ' to ' . $dateTo]);
            fputcsv($file, ['Generated:', now()->format('F j, Y \a\t g:i A')]);
            fputcsv($file, ['Currency:', $currency]);
            fputcsv($file, []);

            // Financial Impact Summary
            $summary = $analytics['financial_impact']['summary'];
            fputcsv($file, ['FINANCIAL IMPACT SUMMARY']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Total Disputes',        $summary['total_disputes']]);
            fputcsv($file, ['Total Disputed Amount', $currency . number_format($summary['total_disputed_amount'], 2)]);
            fputcsv($file, ['Lost Revenue',          $currency . number_format($summary['lost_revenue'], 2)]);
            fputcsv($file, ['Total Fees',            $currency . number_format($summary['total_fees'], 2)]);
            fputcsv($file, ['Net Financial Impact',  $currency . number_format($summary['net_financial_impact'], 2)]);
            fputcsv($file, ['Recovery Rate',         $summary['recovery_rate'] . '%']);
            fputcsv($file, []);

            // Reason Breakdown
            fputcsv($file, ['DISPUTE REASON BREAKDOWN']);
            fputcsv($file, ['Reason', 'Category', 'Count', 'Total Amount', 'Win Rate']);
            foreach ($analytics['reason_breakdown']['by_reason'] as $row) {
                fputcsv($file, [
                    $row['reason_label'],
                    $row['category'],
                    $row['count'],
                    $currency . number_format($row['total_amount'], 2),
                    $row['win_rate'] . '%',
                ]);
            }
            fputcsv($file, []);

            // Monthly Financial Impact
            fputcsv($file, ['MONTHLY FINANCIAL IMPACT']);
            fputcsv($file, ['Month', 'Disputes', 'Lost Amount', 'Fees', 'Net Impact']);
            foreach ($analytics['financial_impact']['monthly_breakdown'] as $row) {
                fputcsv($file, [
                    \Carbon\Carbon::createFromFormat('Y-m', $row['month'])->format('M Y'),
                    $row['dispute_count'],
                    $currency . number_format($row['lost_amount'], 2),
                    $currency . number_format($row['total_fees'], 2),
                    $currency . number_format($row['net_impact'], 2),
                ]);
            }
            fputcsv($file, []);

            // Volume Trends
            fputcsv($file, ['VOLUME TRENDS']);
            fputcsv($file, ['Period', 'Dispute Count', 'Total Amount']);
            foreach ($analytics['volume_trends'] as $row) {
                fputcsv($file, [
                    $row['period'],
                    $row['dispute_count'],
                    $currency . number_format($row['total_amount'], 2),
                ]);
            }

            fclose($file);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Download evidence file.
     *
     * @param Request $request
     * @param string $disputeId
     * @param string $evidenceId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\RedirectResponse
     */
    public function downloadEvidence(Request $request, string $disputeId, string $evidenceId)
    {
        try {
            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId);
            if (!$dispute) {
                return redirect()->route(prefixedRouteName('disputes.index'))
                    ->with('error', 'Dispute not found.');
            }

            // Get the evidence file
            $evidence = DisputeEvidence::where('dispute_id', $dispute->id)
                ->where('id', (int) $evidenceId)
                ->first();

            if (!$evidence) {
                return redirect()->route(prefixedRouteName('disputes.show'), $disputeId)
                    ->with('error', 'Evidence file not found.');
            }

            // Check if file exists
            if (!$evidence->fileExists()) {
                return redirect()->route(prefixedRouteName('disputes.show'), $disputeId)
                    ->with('error', 'Evidence file no longer exists in storage.');
            }

            // Log evidence download for audit trail
            $this->auditLogger->logDisputeAccess($dispute, 'evidence_downloaded', [
                'evidence_id' => $evidence->id,
                'file_name' => $evidence->file_name,
                'evidence_type' => $evidence->evidence_type,
                'downloaded_by' => Auth::user()?->name ?? 'Unknown',
                'downloaded_at' => now()->toISOString()
            ]);

            // Return file download response
            return Storage::disk('s3')->download($evidence->file_path, $evidence->file_name);
        } catch (Exception $e) {
            Log::error('Failed to download evidence file', [
                'dispute_id' => $disputeId,
                'evidence_id' => $evidenceId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->route(prefixedRouteName('disputes.show'), $disputeId)
                ->with('error', 'Failed to download evidence file. Please try again.');
        }
    }

    /**
     * Build filters array from request parameters.
     * 
     * @param Request $request
     * @return array
     */
    /**
     * Store filter values in session and redirect back to index.
     */
    public function storeFilters(Request $request): RedirectResponse
    {
        $data = array_filter(
            $request->only(['status', 'reason', 'search', 'amount_min', 'amount_max', 'date_from', 'date_to']),
            fn($v) => $v !== '' && $v !== null
        );
        session(['dispute_filters' => $data]);
        return redirect()->back();
    }

    /**
     * Clear session filters and redirect back to index.
     */
    public function clearFilters(): RedirectResponse
    {
        session()->forget('dispute_filters');
        return redirect()->back();
    }

    /**
     * Store analytics filter values in session and redirect to analytics page.
     */
    public function storeAnalyticsFilters(Request $request): RedirectResponse
    {
        $data = array_filter(
            $request->only(['date_from', 'date_to', 'group_by', 'status']),
            fn($v) => $v !== '' && $v !== null
        );
        session(['dispute_analytics_filters' => $data]);
        return redirect()->route(prefixedRouteName('disputes.analytics'));
    }

    /**
     * Clear analytics session filters and redirect to analytics page.
     */
    public function clearAnalyticsFilters(): RedirectResponse
    {
        session()->forget('dispute_analytics_filters');
        return redirect()->route(prefixedRouteName('disputes.analytics'));
    }

    /**
     * Build filters array from session data.
     */
    protected function buildFiltersFromSession(array $data): array
    {
        $filters = [];

        if (!empty($data['status'])) {
            $filters['status'] = [$data['status']];
        }
        if (!empty($data['reason'])) {
            $filters['reason'] = [$data['reason']];
        }
        if (!empty($data['date_from'])) {
            $filters['date_from'] = \Carbon\Carbon::parse($data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $filters['date_to'] = \Carbon\Carbon::parse($data['date_to']);
        }
        if (!empty($data['amount_min'])) {
            $filters['amount_min'] = (float) $data['amount_min'];
        }
        if (!empty($data['amount_max'])) {
            $filters['amount_max'] = (float) $data['amount_max'];
        }
        if (!empty($data['search'])) {
            $filters['search'] = $data['search'];
        }

        return $filters;
    }

    protected function buildFilters(Request $request): array
    {
        $filters = [];

        // Status filter
        if ($request->filled('status')) {
            $statuses = is_array($request->input('status'))
                ? $request->input('status')
                : [$request->input('status')];
            $filters['status'] = $statuses;
        }

        // Reason filter
        if ($request->filled('reason')) {
            $reasons = is_array($request->input('reason'))
                ? $request->input('reason')
                : [$request->input('reason')];
            $filters['reason'] = $reasons;
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $filters['date_from'] = $request->date('date_from');
        }
        if ($request->filled('date_to')) {
            $filters['date_to'] = $request->date('date_to');
        }

        // Amount range filters
        if ($request->filled('amount_min')) {
            $filters['amount_min'] = (float) $request->input('amount_min');
        }
        if ($request->filled('amount_max')) {
            $filters['amount_max'] = (float) $request->input('amount_max');
        }

        // Booking ID filter
        if ($request->filled('booking_id')) {
            $filters['booking_id'] = (int) $request->input('booking_id');
        }

        // Stripe dispute ID filter
        if ($request->filled('stripe_dispute_id')) {
            $filters['stripe_dispute_id'] = $request->input('stripe_dispute_id');
        }

        // Search filter
        if ($request->filled('search')) {
            $filters['search'] = $request->input('search');
        }

        // Special filters
        if ($request->boolean('requires_attention')) {
            $filters['requires_attention'] = true;
        }
        if ($request->boolean('overdue')) {
            $filters['overdue'] = true;
        }

        return $filters;
    }

    /**
     * Get dashboard data for dispute widgets.
     * 
     * @return JsonResponse
     */
    public function dashboardData(): JsonResponse
    {
        try {
            $notificationService = app(\App\Services\Dispute\DisputeNotificationService::class);
            $summary = $notificationService->getDisputeSummary();

            return response()->json($summary);
        } catch (Exception $e) {
            Log::error('Failed to get dispute dashboard data', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to load dashboard data',
                'active_disputes' => 0,
                'urgent_disputes' => 0,
                'overdue_disputes' => 0,
                'total_disputes' => 0,
                'status_breakdown' => [],
                'metrics' => [
                    'win_rate' => 'N/A',
                    'response_rate' => 'N/A',
                    'avg_response_time' => 'N/A'
                ],
                'last_updated' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get available response templates for a dispute reason.
     * 
     * @param Request $request
     * @param string $disputeId
     * @return JsonResponse
     */
    public function getResponseTemplates(Request $request, string $disputeId): JsonResponse
    {
        try {
            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId);
            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found.'
                ], 404);
            }

            // Get templates for this dispute reason
            $evidenceCompilerService = app(\App\Services\Dispute\EvidenceCompilerService::class);
            $templates = $evidenceCompilerService->getTemplatesForReason($dispute->reason);

            return response()->json([
                'success' => true,
                'data' => [
                    'dispute_reason' => $dispute->reason,
                    'dispute_reason_label' => $dispute->reason_label,
                    'templates' => $templates,
                    'count' => $templates->count()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get response templates for dispute', [
                'dispute_id' => $disputeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load response templates. Please try again.'
            ], 500);
        }
    }

    /**
     * Apply a response template to generate evidence content.
     * 
     * @param Request $request
     * @param string $disputeId
     * @return JsonResponse
     */
    public function applyResponseTemplate(Request $request, string $disputeId): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'template_id' => 'required|integer|exists:dispute_response_templates,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid template selection.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the dispute
            $dispute = $this->disputeService->getDisputeById((int) $disputeId, ['booking']);
            if (!$dispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute not found.'
                ], 404);
            }

            // Get the template
            $template = \App\Models\DisputeResponseTemplate::find($request->input('template_id'));
            if (!$template || !$template->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found or inactive.'
                ], 404);
            }

            // Check if template matches dispute reason
            if ($template->dispute_reason !== $dispute->reason) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template does not match dispute reason.'
                ], 400);
            }

            // Extract variables from booking data
            $variables = \App\Models\DisputeResponseTemplate::extractBookingVariables($dispute->booking, $dispute);

            // Apply template variables
            $appliedContent = $template->applyVariables($variables);

            Log::info('Response template applied to dispute', [
                'dispute_id' => $disputeId,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'applied_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => $template,
                    'applied_content' => $appliedContent,
                    'variables_used' => $variables,
                    'dispute_reason' => $dispute->reason
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to apply response template', [
                'dispute_id' => $disputeId,
                'template_id' => $request->input('template_id'),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to apply response template. Please try again.'
            ], 500);
        }
    }
}
