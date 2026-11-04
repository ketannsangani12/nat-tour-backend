<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisputeResponseTemplate;
use App\Services\Dispute\EvidenceCompilerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * DisputeResponseTemplateController
 * 
 * Handles CRUD operations for dispute response templates.
 * Provides template categorization by dispute reason and variable substitution.
 * 
 * Requirements: 11.1, 11.3, 11.6
 */
class DisputeResponseTemplateController extends Controller
{
    /**
     * The evidence compiler service instance.
     */
    protected EvidenceCompilerService $evidenceCompilerService;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->middleware('can:view-payment-dispute');
    }

    /**
     * Get the evidence compiler service instance (lazy loading).
     */
    protected function getEvidenceCompilerService(): EvidenceCompilerService
    {
        if (!isset($this->evidenceCompilerService)) {
            $this->evidenceCompilerService = app(EvidenceCompilerService::class);
        }
        return $this->evidenceCompilerService;
    }

    /**
     * Display a listing of dispute response templates.
     * 
     * @param Request $request
     * @return View|JsonResponse|RedirectResponse
     */
    public function index(Request $request)
    {
        try {
            // Request params take priority over persisted session filters
            $requestFilters = $this->buildFilters($request);
            $filters = !empty($requestFilters)
                ? $requestFilters
                : $this->buildFiltersFromSession(session('dispute_template_filters', []));
            
            // Get sorting parameters
            $sortBy = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            // Validate sort parameters
            $allowedSortFields = ['name', 'dispute_reason', 'is_active', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'name';
            }
            
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }

            // Build query with filters and sorting
            $query = DisputeResponseTemplate::query();

            // Apply filters
            if (isset($filters['dispute_reason'])) {
                $query->whereIn('dispute_reason', $filters['dispute_reason']);
            }

            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('template_content', 'like', "%{$search}%");
                });
            }

            if (isset($filters['created_by'])) {
                $query->where('created_by', $filters['created_by']);
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortDirection);

            $templates = $query->paginate(25);

            // Get dispute reason statistics
            $reasonStats = $this->getDisputeReasonStatistics();

            // Get available dispute reasons for filtering
            $availableReasons = $this->getAvailableDisputeReasons();

            // If this is an AJAX request, return JSON
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'templates' => $templates,
                        'reason_stats' => $reasonStats,
                        'available_reasons' => $availableReasons,
                        'filters' => $filters
                    ]
                ]);
            }

            $page_title = 'Dispute Response Templates';
            return view('users.admin.dispute-templates.index', compact(
                'page_title',
                'templates',
                'reasonStats',
                'availableReasons',
                'filters',
                'sortBy',
                'sortDirection'
            ));

        } catch (Exception $e) {
            Log::error('Failed to load dispute response templates index', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load templates. Please try again.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to load templates. Please try again.');
        }
    }

    /**
     * Show the form for creating a new dispute response template.
     * 
     * @return View
     */
    public function create(): View
    {
        $page_title = 'Create Dispute Template';
        $availableReasons = $this->getAvailableDisputeReasons();
        $availableVariables = DisputeResponseTemplate::getAvailableVariables();

        return view('users.admin.dispute-templates.create', compact(
            'page_title',
            'availableReasons',
            'availableVariables'
        ));
    }

    /**
     * Store a newly created dispute response template.
     * 
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:dispute_response_templates,name',
                'dispute_reason' => 'required|string|in:fraudulent,subscription_canceled,product_unacceptable,credit_not_processed,duplicate,product_not_received,unrecognized,general',
                'template_content' => 'required|string|min:50|max:10000',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Create the template
            $template = DisputeResponseTemplate::create([
                'name' => $request->input('name'),
                'dispute_reason' => $request->input('dispute_reason'),
                'template_content' => $request->input('template_content'),
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id(),
                'created_by_user_type' => userType()
            ]);

            Log::info('Dispute response template created', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'dispute_reason' => $template->dispute_reason,
                'created_by' => Auth::id(),
                'created_by_type' => userType()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Template created successfully.',
                    'data' => [
                        'template' => $template,
                        'redirect_url' => route(prefixedRouteName('dispute-templates.show'), $template->id)
                    ]
                ]);
            }

            return redirect()->route(prefixedRouteName('dispute-templates.show'), $template->id)
                ->with('success', 'Template created successfully.');

        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (Exception $e) {
            Log::error('Failed to create dispute response template', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->except(['template_content'])
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create template: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to create template. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified dispute response template.
     * 
     * @param DisputeResponseTemplate $template
     * @param Request $request
     * @return View|JsonResponse|RedirectResponse
     */
    public function show(DisputeResponseTemplate $template, Request $request)
    {
        try {
            // Load the creator relationship
            $template->load('creator');

            // Get available variables for this template
            $availableVariables = DisputeResponseTemplate::getAvailableVariables();

            // Get usage statistics for this template
            $usageStats = $this->getTemplateUsageStatistics($template->id);

            // Get other templates for the same dispute reason
            $relatedTemplates = DisputeResponseTemplate::where('dispute_reason', $template->dispute_reason)
                ->where('id', '!=', $template->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(5)
                ->get();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'template' => $template,
                        'available_variables' => $availableVariables,
                        'usage_stats' => $usageStats,
                        'related_templates' => $relatedTemplates
                    ]
                ]);
            }

            $page_title = $template->name;
            return view('users.admin.dispute-templates.show', compact(
                'page_title',
                'template',
                'availableVariables',
                'usageStats',
                'relatedTemplates'
            ));

        } catch (Exception $e) {
            Log::error('Failed to load dispute response template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load template details. Please try again.'
                ], 500);
            }

            return redirect()->route(prefixedRouteName('dispute-templates.index'))
                ->with('error', 'Failed to load template details. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified dispute response template.
     * 
     * @param DisputeResponseTemplate $template
     * @return View
     */
    public function edit(DisputeResponseTemplate $template): View
    {
        $page_title = 'Edit Dispute Template';
        $availableReasons = $this->getAvailableDisputeReasons();
        $availableVariables = DisputeResponseTemplate::getAvailableVariables();

        return view('users.admin.dispute-templates.edit', compact(
            'page_title',
            'template',
            'availableReasons',
            'availableVariables'
        ));
    }

    /**
     * Update the specified dispute response template.
     * 
     * @param Request $request
     * @param DisputeResponseTemplate $template
     * @return JsonResponse|RedirectResponse
     */
    public function update(Request $request, DisputeResponseTemplate $template)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:dispute_response_templates,name,' . $template->id,
                'dispute_reason' => 'required|string|in:fraudulent,subscription_canceled,product_unacceptable,credit_not_processed,duplicate,product_not_received,unrecognized,general',
                'template_content' => 'required|string|min:50|max:10000',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Store old values for logging
            $oldValues = [
                'name' => $template->name,
                'dispute_reason' => $template->dispute_reason,
                'is_active' => $template->is_active
            ];

            // Update the template
            $template->update([
                'name' => $request->input('name'),
                'dispute_reason' => $request->input('dispute_reason'),
                'template_content' => $request->input('template_content'),
                'is_active' => $request->boolean('is_active', true)
            ]);

            Log::info('Dispute response template updated', [
                'template_id' => $template->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'name' => $template->name,
                    'dispute_reason' => $template->dispute_reason,
                    'is_active' => $template->is_active
                ],
                'updated_by' => Auth::id()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Template updated successfully.',
                    'data' => [
                        'template' => $template->fresh(),
                        'redirect_url' => route(prefixedRouteName('dispute-templates.show'), $template->id)
                    ]
                ]);
            }

            return redirect()->route(prefixedRouteName('dispute-templates.show'), $template->id)
                ->with('success', 'Template updated successfully.');

        } catch (ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (Exception $e) {
            Log::error('Failed to update dispute response template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->except(['template_content'])
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update template: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update template. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified dispute response template.
     * 
     * @param DisputeResponseTemplate $template
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(DisputeResponseTemplate $template, Request $request)
    {
        try {
            // Check if template is being used in any active disputes
            $usageCount = $this->getTemplateActiveUsageCount($template->id);
            
            if ($usageCount > 0) {
                $message = "Cannot delete template '{$template->name}' as it is currently being used in {$usageCount} active dispute(s).";
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ], 400);
                }

                return redirect()->back()->with('error', $message);
            }

            $templateName = $template->name;
            $templateId = $template->id;

            // Delete the template
            $template->delete();

            Log::info('Dispute response template deleted', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'deleted_by' => Auth::id()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Template deleted successfully.',
                    'data' => [
                        'redirect_url' => route(prefixedRouteName('dispute-templates.index'))
                    ]
                ]);
            }

            return redirect()->route(prefixedRouteName('dispute-templates.index'))
                ->with('success', 'Template deleted successfully.');

        } catch (Exception $e) {
            Log::error('Failed to delete dispute response template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete template: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to delete template. Please try again.');
        }
    }

    /**
     * Activate a dispute response template.
     * 
     * @param DisputeResponseTemplate $template
     * @param Request $request
     * @return JsonResponse
     */
    public function activate(DisputeResponseTemplate $template, Request $request): JsonResponse
    {
        try {
            if ($template->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template is already active.'
                ], 400);
            }

            $template->update(['is_active' => true]);

            Log::info('Dispute response template activated', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'activated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template activated successfully.',
                'data' => [
                    'template' => $template->fresh()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to activate dispute response template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate a dispute response template.
     * 
     * @param DisputeResponseTemplate $template
     * @param Request $request
     * @return JsonResponse
     */
    public function deactivate(DisputeResponseTemplate $template, Request $request): JsonResponse
    {
        try {
            if (!$template->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template is already inactive.'
                ], 400);
            }

            $template->update(['is_active' => false]);

            Log::info('Dispute response template deactivated', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'deactivated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template deactivated successfully.',
                'data' => [
                    'template' => $template->fresh()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to deactivate dispute response template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview template with sample data.
     * 
     * @param DisputeResponseTemplate $template
     * @param Request $request
     * @return JsonResponse
     */
    public function preview(DisputeResponseTemplate $template, Request $request): JsonResponse
    {
        try {
            // Get sample booking data for preview
            $sampleData = $this->getSampleBookingData();
            
            // Apply template variables
            $previewContent = $template->applyVariables($sampleData);

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => $template,
                    'preview_content' => $previewContent,
                    'sample_data' => $sampleData,
                    'available_variables' => DisputeResponseTemplate::getAvailableVariables()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to preview dispute response template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get templates for a specific dispute reason.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTemplatesForReason(Request $request): JsonResponse
    {
        try {
            // Route uses {dispute_reason} path param; fall back to query/body param for API flexibility
            $disputeReason = $request->route('dispute_reason') ?? $request->input('dispute_reason');

            $validator = Validator::make(['dispute_reason' => $disputeReason], [
                'dispute_reason' => 'required|string|in:fraudulent,subscription_canceled,product_unacceptable,credit_not_processed,duplicate,product_not_received,unrecognized,general'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid dispute reason.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $templates = $this->getEvidenceCompilerService()->getTemplatesForReason($disputeReason);

            return response()->json([
                'success' => true,
                'data' => [
                    'dispute_reason' => $disputeReason,
                    'templates' => $templates,
                    'count' => $templates->count()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get templates for dispute reason', [
                'dispute_reason' => $request->route('dispute_reason') ?? $request->input('dispute_reason'),
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build filters array from request parameters.
     * 
     * @param Request $request
     * @return array
     */
    public function storeFilters(Request $request): RedirectResponse
    {
        $data = array_filter(
            $request->only(['dispute_reason', 'is_active', 'search']),
            fn($v) => $v !== '' && $v !== null
        );
        session(['dispute_template_filters' => $data]);
        return redirect()->back();
    }

    public function clearFilters(): RedirectResponse
    {
        session()->forget('dispute_template_filters');
        return redirect()->back();
    }

    protected function buildFiltersFromSession(array $data): array
    {
        $filters = [];

        if (!empty($data['dispute_reason'])) {
            $filters['dispute_reason'] = [$data['dispute_reason']];
        }

        if (isset($data['is_active']) && $data['is_active'] !== '') {
            $filters['is_active'] = (bool) $data['is_active'];
        }

        if (!empty($data['search'])) {
            $filters['search'] = $data['search'];
        }

        return $filters;
    }

    protected function buildFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('dispute_reason')) {
            $reasons = is_array($request->input('dispute_reason'))
                ? $request->input('dispute_reason')
                : [$request->input('dispute_reason')];
            $filters['dispute_reason'] = $reasons;
        }

        if ($request->filled('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->input('search');
        }

        if ($request->filled('created_by')) {
            $filters['created_by'] = (int) $request->input('created_by');
        }

        return $filters;
    }

    /**
     * Get available dispute reasons.
     * 
     * @return array
     */
    protected function getAvailableDisputeReasons(): array
    {
        return [
            'fraudulent' => 'Fraudulent',
            'subscription_canceled' => 'Subscription Canceled',
            'product_unacceptable' => 'Product Unacceptable',
            'credit_not_processed' => 'Credit Not Processed',
            'duplicate' => 'Duplicate',
            'product_not_received' => 'Product Not Received',
            'unrecognized' => 'Unrecognized',
            'general' => 'General'
        ];
    }

    /**
     * Get dispute reason statistics.
     * 
     * @return array
     */
    protected function getDisputeReasonStatistics(): array
    {
        $stats = DisputeResponseTemplate::selectRaw('dispute_reason, COUNT(*) as count, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count')
            ->groupBy('dispute_reason')
            ->get()
            ->keyBy('dispute_reason')
            ->toArray();

        $availableReasons = $this->getAvailableDisputeReasons();
        $result = [];

        foreach ($availableReasons as $reason => $label) {
            $result[$reason] = [
                'label' => $label,
                'total_count' => $stats[$reason]['count'] ?? 0,
                'active_count' => $stats[$reason]['active_count'] ?? 0
            ];
        }

        return $result;
    }

    /**
     * Get template usage statistics.
     * 
     * @param int $templateId
     * @return array
     */
    protected function getTemplateUsageStatistics(int $templateId): array
    {
        // This would typically query dispute evidence or timeline tables
        // to see how often this template has been used
        // For now, returning placeholder data
        return [
            'total_usage' => 0,
            'recent_usage' => 0,
            'last_used_at' => null,
            'success_rate' => null
        ];
    }

    /**
     * Get active usage count for a template.
     * 
     * @param int $templateId
     * @return int
     */
    protected function getTemplateActiveUsageCount(int $templateId): int
    {
        // This would typically check if the template is referenced
        // in any active disputes or evidence submissions
        // For now, returning 0 to allow deletion
        return 0;
    }

    /**
     * Get sample booking data for template preview.
     * 
     * @return array
     */
    protected function getSampleBookingData(): array
    {
        return [
            'booking_reference' => 'BK-2024-001234',
            'customer_name' => 'John Smith',
            'customer_email' => 'john.smith@example.com',
            'tour_name' => 'Grand Canyon Adventure Tour',
            'tour_date' => '2024-03-15',
            'tour_time' => '09:00 AM',
            'booking_amount' => '$299.99',
            'payment_date' => 'March 1, 2024',
            'transaction_id' => 'TXN-789456123',
            'dispute_amount' => '$299.99',
            'dispute_reason' => 'Product Not Received',
            'company_name' => 'Adventure Tours Inc.',
            'company_email' => 'support@adventuretours.com',
            'company_phone' => '(555) 123-4567',
            'current_date' => now()->format('M j, Y')
        ];
    }
}