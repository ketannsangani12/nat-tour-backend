<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Repositories\Report\PaymentBreakdownRepository;
use App\Services\PaymentBreakdownService;
use App\Services\ExportService;
use App\Utils\BokunUtils;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentBreakdownController extends Controller
{
    protected PaymentBreakdownRepository $repository;
    protected PaymentBreakdownService $service;
    protected ExportService $exportService;
    private array $requestValidation;

    public function __construct(
        PaymentBreakdownRepository $repository,
        PaymentBreakdownService $service,
        ExportService $exportService
    ) {
        parent::__construct();

        $this->repository = $repository;
        $this->service = $service;
        $this->exportService = $exportService;

        // Apply tenant-aware middleware and permissions
        $this->middleware('permission:view-payment-breakdown-report')->only(['index', 'getFilters', 'getSavedFilters', 'getRealTimeCalculations', 'validateFilters', 'resetFilters']);
        $this->middleware('permission:export-payment-breakdown-report')->only(['export']);

        // Base validation rules
        $this->requestValidation = [
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'tourPackage' => 'nullable|numeric', // Keep for backward compatibility
            'tourPackages' => 'nullable|array', // New multi-select support
            'tourPackages.*' => 'numeric',
            'affiliate' => 'nullable|numeric', // Keep for backward compatibility
            'affiliates' => 'nullable|array', // New multi-select support
            'affiliates.*' => 'numeric',
            'excludeAllAffiliates' => 'nullable|in:0,1,true,false',
            'paymentTypes' => 'nullable|array',
            'paymentTypes.*' => 'string|in:Card,Cash,Invoice,Billable,Wire,Unpaid',
            'viewBy' => 'nullable|string|in:tourdate,purchasedate,createddate',
            'export' => 'nullable|boolean',
            'format' => 'nullable|string|in:pdf,csv,excel',
            'bookingStatus' => 'nullable|string|in:Booked,Cancelled',
            'checkInStatus' => 'nullable|string|in:Not Check-In,1st Check-in,2nd Check-in,Absent', // Keep for backward compatibility
            'checkInStatuses' => 'nullable|array', // New multi-select support
            'checkInStatuses.*' => 'string|in:Not Check-In,1st Check-in,2nd Check-in,Absent',
            'waiverStatus' => 'nullable|string|in:Signed,Not Signed',
            'thirdPartyPlatform' => 'nullable|string|in:Direct', // Keep for backward compatibility
            'thirdPartyPlatforms' => 'nullable|array', // New multi-select support
            'thirdPartyPlatforms.*' => ['string', Rule::in(array_merge(BokunUtils::getReportableOtaTitles(), ['FareHarbor']))],
            'excludeAllThirdParty' => 'nullable|in:0,1,true,false',
            'minAmount' => 'nullable|numeric|min:0',
            'maxAmount' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'permitFee' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'softBlocks' => 'nullable|string|in:0,1',
            'reallocatedFunds' => 'nullable|string|in:0',
            'chargeTypes' => 'nullable|array',
            'chargeTypes.*' => 'string|in:tours,custom,addons',
            'persistFilters' => 'nullable|boolean'
        ];

        // Add tour operator validation for Admin users
        if (session('userType') == 'Admin') {
            $this->requestValidation['tourOperator'] = 'required|numeric';
        }
    }

    /**
     * Display payment breakdown report page or return JSON data
     *
     * @param Request $request
     * @return \Illuminate\View\View|JsonResponse
     */
    public function index(Request $request)
    {
        // If this is an AJAX request or has specific parameters, return JSON data
        if ($request->ajax() || $request->has('ajax') || $request->expectsJson()) {
            return $this->getReportData($request);
        }

        // Otherwise, return the view for the standalone page
        $userType = session('userType');
        $routePrefix = routePrefix();

        return view('partials.reports.payment-breakdown-report', [
            'page_title' => 'Payment Breakdown Report',
            'userType' => $userType,
            'routePrefix' => $routePrefix
        ]);
    }

    /**
     * Get payment breakdown report data (JSON response)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getReportData(Request $request): JsonResponse
    {
        try {
            // Increase memory limit for large date ranges
            ini_set('memory_limit', '1G');

            // Increase max execution time for large datasets
            set_time_limit(300); // 5 minutes

            $request->validate($this->requestValidation);

            // Get filters from request
            $filters = $this->buildFilters($request);

            // Get raw data from repository
            $rawData = $this->repository->getPaymentBreakdownData($filters);

            // Get check-in status data (single optimized query for both aggregated and individual data)
            $checkInDataCombined = $this->repository->getCheckInStatusDataCombined($filters, $rawData);
            $checkInData = $checkInDataCombined['statusCounts'];
            $individualCheckInData = $checkInDataCombined['individualData'];

            // Get waiver status data
            $waiverData = $this->repository->getWaiverStatusData($filters);

            // Process data through service with metrics
            $processedData = $this->service->processPaymentBreakdownWithMetrics(
                $rawData->toArray(), 
                $checkInData, 
                $waiverData,
                $individualCheckInData
            );

            // Convert booking details to proper array format for JavaScript
            if (isset($processedData['bookingDetails'])) {
                $processedData['bookingDetails'] = array_map(function($booking) {
                    return (array) $booking;
                }, $processedData['bookingDetails']);
            }

            // Get disputes lost within this period (period-based, not booking-date-based)
            // Only when the DISPUTE_REVERSAL setting is enabled.
            $periodDisputes = hasSetting('DISPUTE_REVERSAL')
                ? $this->repository->getDisputeReversalsInPeriod($filters)
                : ['total' => 0.0, 'items' => []];

            // Add summary totals, then inject period-based dispute figures
            $summaryTotals = $this->service->generateSummaryTotals($processedData);
            $summaryTotals['totalDisputeReversals'] = $periodDisputes['total'];

            // Validate financial calculations
            $validationResults = $this->service->validateFinancialCalculations($processedData);

            return response()->json([
                'success' => true,
                'data' => $processedData['bookingDetails'] ?? [], // Send booking details for table display
                'paymentTypeBreakdown' => $processedData['paymentTypeBreakdown'] ?? [], // Consolidated payment type data with rate breakdown and transaction metrics
                'summary' => $summaryTotals,
                'charts' => $this->buildChartData($processedData, $periodDisputes['total']),
                'checkInData' => $checkInData, // Add raw check-in data for frontend processing
                'operationalMetrics' => $processedData['operationalMetrics'] ?? [], // Add operational metrics
                'disputeReversals' => $periodDisputes['items'],
                'validation' => $validationResults,
                'filters_applied' => $filters,
                'generated_at' => now()->toISOString()
            ]);

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Report Error: ' . $exception->getMessage(), [
                'request' => $request->all(),
                'trace' => $exception->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate payment breakdown report.',
                'error' => config('app.debug') ? $exception->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Export payment breakdown report
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function export(Request $request): StreamedResponse
    {
        // Handle HEAD requests for permission checking (used by frontend)
        if ($request->isMethod('HEAD')) {
            // Just return empty response with 200 status if user has permission
            // Middleware will block with 403 if no permission
            $headers = ['Content-Type' => 'text/plain'];
            return response()->stream(function() {}, 200, $headers);
        }

        try {
            // Increase memory limit for large date ranges
            ini_set('memory_limit', '1G');

            // Increase max execution time for large datasets
            set_time_limit(300); // 5 minutes

            $request->validate(array_merge($this->requestValidation, [
                'format' => 'required|string|in:pdf,csv,excel'
            ]));

            // Get filters from request
            $filters = $this->buildFilters($request);

            // Get raw data from repository
            $rawData = $this->repository->getPaymentBreakdownData($filters);

            // Get check-in status data (single optimized query for both aggregated and individual data)
            $checkInDataCombined = $this->repository->getCheckInStatusDataCombined($filters, $rawData);
            $checkInData = $checkInDataCombined['statusCounts'];
            $individualCheckInData = $checkInDataCombined['individualData'];

            // Get waiver status data
            $waiverData = $this->repository->getWaiverStatusData($filters);

            // Process data through service with metrics
            $processedData = $this->service->processPaymentBreakdownWithMetrics(
                $rawData->toArray(), 
                $checkInData, 
                $waiverData,
                $individualCheckInData
            );

            // Inject period-based dispute reversals for export (only when setting is enabled)
            $periodDisputes = hasSetting('DISPUTE_REVERSAL')
                ? $this->repository->getDisputeReversalsInPeriod($filters)
                : ['total' => 0.0, 'items' => []];
            $processedData['totalDisputes'] = $periodDisputes['total'];

            // Generate export based on format
            $format = $request->get('format', 'csv');

            switch ($format) {
                case 'pdf':
                    return $this->exportService->exportToPDF($processedData, $filters);
                case 'excel':
                    return $this->exportService->exportToExcel($processedData, $filters);
                case 'csv':
                default:
                    return $this->exportService->exportToCSV($processedData, $filters);
            }

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Export Error: ' . $exception->getMessage(), [
                'request' => $request->all(),
                'trace' => $exception->getTraceAsString()
            ]);

            // Return error as streamed response since we can't change return type
            $headers = [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="error.txt"'
            ];

            $callback = function () use ($exception) {
                echo "Export Error: " . $exception->getMessage();
            };

            return response()->stream($callback, 500, $headers);
        }
    }

    /**
     * Get available filter options with tenant isolation
     *
     * @return JsonResponse
     */
    public function getFilters(): JsonResponse
    {
        try {
            $userType = session('userType');
            
            // Get current tour operator
            $tourOperator = tourOperator();

            $tourOperatorId = $tourOperator->id;

            $filters = [
                'paymentTypes' => [
                    'Card', 'Cash', 'Invoice', 'Billable', 'Wire', 'Unpaid'
                ],
                'dateRangePresets' => [
                    'today' => [
                        'label' => 'Today',
                        'dateFrom' => now()->format('Y-m-d'),
                        'dateTo' => now()->format('Y-m-d')
                    ],
                    'yesterday' => [
                        'label' => 'Yesterday',
                        'dateFrom' => now()->subDay()->format('Y-m-d'),
                        'dateTo' => now()->subDay()->format('Y-m-d')
                    ],
                    'last7days' => [
                        'label' => 'Last 7 Days',
                        'dateFrom' => now()->subDays(7)->format('Y-m-d'),
                        'dateTo' => now()->format('Y-m-d')
                    ],
                    'last30days' => [
                        'label' => 'Last 30 Days',
                        'dateFrom' => now()->subDays(30)->format('Y-m-d'),
                        'dateTo' => now()->format('Y-m-d')
                    ],
                    'monthToDate' => [
                        'label' => 'Month to Date',
                        'dateFrom' => now()->startOfMonth()->format('Y-m-d'),
                        'dateTo' => now()->format('Y-m-d')
                    ],
                    'lastMonth' => [
                        'label' => 'Last Month',
                        'dateFrom' => now()->subMonth()->startOfMonth()->format('Y-m-d'),
                        'dateTo' => now()->subMonth()->endOfMonth()->format('Y-m-d')
                    ],
                    'yearToDate' => [
                        'label' => 'Year to Date',
                        'dateFrom' => now()->startOfYear()->format('Y-m-d'),
                        'dateTo' => now()->format('Y-m-d')
                    ],
                    'lastYear' => [
                        'label' => 'Last Year',
                        'dateFrom' => now()->subYear()->startOfYear()->format('Y-m-d'),
                        'dateTo' => now()->subYear()->endOfYear()->format('Y-m-d')
                    ]
                ],
                'viewByOptions' => [
                    'tourdate' => 'Tour Date',
                    'purchasedate' => 'Purchase Date',
                    'createddate' => 'Created Date'
                ],
                'exportFormats' => [
                    'csv' => 'CSV',
                    'excel' => 'Excel',
                    'pdf' => 'PDF'
                ],
                'bookingStatuses' => [
                    'Booked', 'Cancelled', 'Completed', 'Pending'
                ],
                'checkInStatuses' => [
                    'Not Check-In', '1st Check-in', '2nd Check-in', 'Absent'
                ],
                'waiverStatuses' => [
                    'Signed', 'Not Signed'
                ],
                'thirdPartyPlatforms' => $this->getEnabledThirdPartyPlatforms(),
            ];

            // Get tour packages
            $tourPackages = $this->repository->getTourPackages();
            $filters['tourPackages'] = $tourPackages;

            // Get affiliates
            $affiliates = $this->repository->getAffiliates();
            $filters['affiliates'] = $affiliates;

            // Add filter state management
            $filters['filterState'] = [
                'sessionKey' => 'payment_breakdown_filters',
                'persistFilters' => true,
                'autoRefresh' => true
            ];

            // Add branding information
            $filters['tenantBranding'] = [
                'company_name' => $tourOperator->name ?? 'Tour Company',
                'logo_url' => $tourOperator->logo ?? null,
                'currency' => session('currency', 'USD'),
                'timezone' => $tourOperator->timezone ?? config('app.timezone')
            ];

            return response()->json([
                'success' => true,
                'filters' => $filters,
                'metadata' => [
                    'userType' => $userType,
                    'tourOperatorId' => $tourOperatorId,
                    'timezone' => $tourOperator->timezone ?? config('app.timezone'),
                    'currency' => session('currency', 'USD')
                ]
            ]);

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Filters Error: ' . $exception->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $exception->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load filter options.',
                'error' => config('app.debug') ? $exception->getMessage() : 'Filter loading failed'
            ], 500);
        }
    }

    /**
     * Get saved filters from session with tenant isolation
     *
     * @return JsonResponse
     */
    public function getSavedFilters(): JsonResponse
    {
        try {
            $sessionKey = 'payment_breakdown_filters';
            $savedFilters = session($sessionKey, []);
            
            return response()->json([
                'success' => true,
                'filters' => $savedFilters,
                'hasFilters' => !empty($savedFilters)
            ]);

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Saved Filters Error: ' . $exception->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load saved filters.',
                'error' => config('app.debug') ? $exception->getMessage() : 'Filter loading failed'
            ], 500);
        }
    }

    /**
     * Get real-time calculation updates based on filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRealTimeCalculations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'dateFrom' => 'required|date',
                'dateTo' => 'required|date',
                'tourOperator' => 'nullable|numeric',
                'tourPackage' => 'nullable|numeric',
                'affiliate' => 'nullable|numeric',
                'paymentTypes' => 'nullable|array',
                'bookingStatus' => 'nullable|string',
                'checkInStatus' => 'nullable|string',
                'waiverStatus' => 'nullable|string',
                'thirdPartyPlatform' => 'nullable|string',
                'minAmount' => 'nullable|numeric',
                'maxAmount' => 'nullable|numeric'
            ]);

            // Get filters from request
            $filters = $this->buildFilters($request);

            // Get raw data from repository
            $rawData = $this->repository->getPaymentBreakdownData($filters);

            // Get check-in status data (single optimized query for both aggregated and individual data)
            $checkInDataCombined = $this->repository->getCheckInStatusDataCombined($filters, $rawData);
            $checkInData = $checkInDataCombined['statusCounts'];
            $individualCheckInData = $checkInDataCombined['individualData'];

            // Get waiver status data
            $waiverData = $this->repository->getWaiverStatusData($filters);

            // Process data through service with metrics
            $processedData = $this->service->processPaymentBreakdownWithMetrics(
                $rawData->toArray(), 
                $checkInData, 
                $waiverData,
                $individualCheckInData
            );

            // Generate summary totals for real-time updates
            $summaryTotals = $this->service->generateSummaryTotals($processedData);

            // Calculate key metrics for real-time display
            $realTimeMetrics = [
                'totalBookings' => $summaryTotals['totalBookings'],
                'totalPax' => $summaryTotals['totalPax'],
                'totalRevenue' => $summaryTotals['totalRevenue'],
                'totalRefunds' => $summaryTotals['totalRefunds'],
                'netRevenue' => $summaryTotals['netRevenue'],
                'averageBookingValue' => $summaryTotals['averageBookingValue'],
                'paymentTypeBreakdown' => $summaryTotals['paymentTypeBreakdown'],
                'affiliateRevenue' => $summaryTotals['affiliateRevenue'],
                'thirdPartyRevenue' => $summaryTotals['thirdPartyRevenue'],
                'directRevenue' => $summaryTotals['directRevenue']
            ];

            return response()->json([
                'success' => true,
                'metrics' => $realTimeMetrics,
                'filters_applied' => $filters,
                'updated_at' => now()->toISOString()
            ]);

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Real-time Calculations Error: ' . $exception->getMessage(), [
                'request' => $request->all(),
                'trace' => $exception->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate real-time metrics.',
                'error' => config('app.debug') ? $exception->getMessage() : 'Calculation error'
            ], 500);
        }
    }

    /**
     * Validate filter parameters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateFilters(Request $request): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'dateFrom' => 'required|date|before_or_equal:dateTo',
                'dateTo' => 'required|date|after_or_equal:dateFrom',
                'tourOperator' => 'nullable|numeric',
                'tourPackage' => 'nullable|numeric',
                'affiliate' => 'nullable|numeric',
                'paymentTypes' => 'nullable|array|min:1',
                'paymentTypes.*' => 'string|in:Card,Cash,Invoice,Billable,Wire,Unpaid,TripAdvisor,Headout,GetYourGuide,FareHarbor',
                'minAmount' => 'nullable|numeric|min:0',
                'maxAmount' => 'nullable|numeric|min:0|gte:minAmount'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Additional business logic validation
            $dateFrom = \Carbon\Carbon::parse($request->get('dateFrom'));
            $dateTo = \Carbon\Carbon::parse($request->get('dateTo'));
            
            if ($dateTo->diffInDays($dateFrom) > 365) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 365 days.',
                    'errors' => ['dateRange' => ['Date range too large']]
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Filters are valid.',
                'validated_filters' => $request->all()
            ]);

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Filter Validation Error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Filter validation error.',
                'error' => config('app.debug') ? $exception->getMessage() : 'Validation failed'
            ], 500);
        }
    }

    /**
     * Reset filters to default values with tenant isolation
     *
     * @return JsonResponse
     */
    public function resetFilters(): JsonResponse
    {
        try {
            // Clear saved filters from session
            $sessionKey = 'payment_breakdown_filters';
            session()->forget($sessionKey);

            // Return default filter values
            $defaultFilters = [
                'dateFrom' => now()->startOfMonth()->format('Y-m-d'),
                'dateTo' => now()->format('Y-m-d'),
                'paymentTypes' => ['Card', 'Cash', 'Invoice', 'Billable', 'Wire', 'TripAdvisor', 'Headout', 'GetYourGuide', 'FareHarbor', 'Unpaid'],
                'viewBy' => 'tourdate'
            ];

            return response()->json([
                'success' => true,
                'message' => 'Filters reset to default values.',
                'default_filters' => $defaultFilters
            ]);

        } catch (Exception $exception) {
            Log::error('Payment Breakdown Reset Filters Error: ' . $exception->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to reset filters.',
                'error' => config('app.debug') ? $exception->getMessage() : 'Reset failed'
            ], 500);
        }
    }

    /**
     * Build filters from request with tenant data isolation
     *
     * @param Request $request
     * @return array
     */
    private function buildFilters(Request $request): array
    {
        $userType = session('userType');
        $guard = session('guard');
        
        // Get current tour operator using helper
        $tourOperator = tourOperator();

        // Map frontend payment types to database payment types
        $paymentTypeMapping = [
            'Card' => 'Card',
            'Cash' => 'Cash',
            'Invoice' => 'Invoiced',  // Frontend sends 'Invoice', DB has 'Invoiced'
            'Billable' => 'Billable',
            'Wire' => 'Check/Wire',   // Frontend sends 'Wire', DB has 'Check/Wire'
            'Unpaid' => 'Unpaid'
        ];

        $requestedPaymentTypes = $request->get('paymentTypes', ['Card', 'Cash', 'Invoice', 'Billable', 'Wire', 'Unpaid']);
        $mappedPaymentTypes = [];
        
        foreach ($requestedPaymentTypes as $type) {
            if (isset($paymentTypeMapping[$type])) {
                $mappedPaymentTypes[] = $paymentTypeMapping[$type];
            }
        }

        // Get dates from request, ensuring they use the configured timezone
        $dateFrom = $request->get('dateFrom');
        $dateTo = $request->get('dateTo');
        
        // If dates are provided, ensure they're in the correct timezone format
        if ($dateFrom) {
            $dateFrom = \Carbon\Carbon::parse($dateFrom, config('app.timezone'))->format('Y-m-d');
        }
        if ($dateTo) {
            $dateTo = \Carbon\Carbon::parse($dateTo, config('app.timezone'))->format('Y-m-d');
        }

        // Build filters
        $filters = [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'tourOperatorId' => $tourOperator->id,
            'tourPackageIds' => $request->get('tourPackages', []), // Multi-select tour packages
            'affiliateIds' => $request->get('affiliates', []), // Multi-select affiliates
            'excludeAllAffiliates' => filter_var($request->get('excludeAllAffiliates', false), FILTER_VALIDATE_BOOLEAN), // Exclude all affiliates flag
            'paymentTypes' => $mappedPaymentTypes, // Use mapped payment types
            'viewBy' => $request->get('viewBy', 'tourdate'),
            'userType' => $userType,
            'userId' => auth($guard)->id()
        ];

        // Add additional filter options
        if ($request->has('bookingStatus')) {
            $filters['bookingStatus'] = $request->get('bookingStatus');
        }

        // Check-in Status - support both single and multiple
        if ($request->has('checkInStatuses') && !empty($request->get('checkInStatuses'))) {
            $filters['checkInStatuses'] = $request->get('checkInStatuses');
        } elseif ($request->has('checkInStatus')) {
            // Backward compatibility: single check-in status
            $filters['checkInStatus'] = $request->get('checkInStatus');
        }

        if ($request->has('waiverStatus')) {
            $filters['waiverStatus'] = $request->get('waiverStatus');
        }

        // Third Party Platforms - support both single and multiple
        if ($request->has('thirdPartyPlatforms') && !empty($request->get('thirdPartyPlatforms'))) {
            $filters['thirdPartyPlatforms'] = $request->get('thirdPartyPlatforms');
        } elseif ($request->has('thirdPartyPlatform')) {
            // Backward compatibility: single third party platform
            $filters['thirdPartyPlatform'] = $request->get('thirdPartyPlatform');
        }

        // Exclude all third party platforms flag
        if ($request->has('excludeAllThirdParty')) {
            $filters['excludeAllThirdParty'] = filter_var($request->get('excludeAllThirdParty', false), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('minAmount')) {
            $filters['minAmount'] = (float) $request->get('minAmount');
        }

        if ($request->has('maxAmount')) {
            $filters['maxAmount'] = (float) $request->get('maxAmount');
        }

        if ($request->has('rate')) {
            $filters['rate'] = (float) $request->get('rate');
        }

        if ($request->has('permitFee')) {
            $filters['permitFee'] = (float) $request->get('permitFee');
        }

        if ($request->has('tax')) {
            $filters['tax'] = (float) $request->get('tax');
        }

        if ($request->has('softBlocks') && $request->get('softBlocks') !== '') {
            $filters['softBlocks'] = $request->get('softBlocks');
        }

        if ($request->has('reallocatedFunds') && $request->get('reallocatedFunds') !== '') {
            $filters['reallocatedFunds'] = $request->get('reallocatedFunds');
        }

        // Charge Type filter (Tours/Custom/Add-ons) - only apply scoping when 1 or 2 of the
        // 3 options are selected; 0 selected or all 3 selected both mean "All Payment Types"
        // (i.e. current unfiltered behavior), so the key is omitted entirely in those cases.
        $requestedChargeTypes = array_values(array_intersect(
            array_unique($request->get('chargeTypes', [])),
            ['tours', 'custom', 'addons']
        ));
        if (count($requestedChargeTypes) > 0 && count($requestedChargeTypes) < 3) {
            $filters['chargeTypes'] = $requestedChargeTypes;
        }

        // Export options
        if ($request->has('includeCharts')) {
            $filters['includeCharts'] = filter_var($request->get('includeCharts'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('includeFilters')) {
            $filters['includeFilters'] = filter_var($request->get('includeFilters'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('includeBookingDetails')) {
            $filters['includeBookingDetails'] = filter_var($request->get('includeBookingDetails'), FILTER_VALIDATE_BOOLEAN);
        }

        // Store filters in session for persistence
        if ($request->get('persistFilters', true)) {
            session(['payment_breakdown_filters' => $filters]);
        }

        return $filters;
    }

    /**
     * Build chart data from processed data
     *
     * @param array $processedData
     * @return array
     */
    private function buildChartData(array $processedData, float $periodDisputeReversals = 0.0): array
    {
        $chartData = [
            'payment_types' => [
                'labels' => [],
                'values' => []
            ],
            'revenue_refunds' => [
                'revenue'           => 0,
                'refunds'           => 0,
                'fees'              => 0,
                'dispute_reversals' => 0,
                'net_revenue'       => 0
            ]
        ];

        if (isset($processedData['paymentTypeBreakdown'])) {
            foreach ($processedData['paymentTypeBreakdown'] as $type => $data) {
                // Payment type chart - use totalAmount from rate breakdown
                $chartData['payment_types']['labels'][] = $type;
                $chartData['payment_types']['values'][] = round($data['totalAmount'] ?? 0, 2);

                // Revenue vs refunds chart - use transaction-based metrics
                $chartData['revenue_refunds']['revenue']     += $data['transactionAmount'] ?? 0;
                $chartData['revenue_refunds']['refunds']     += $data['refundAmount'] ?? 0;
                $chartData['revenue_refunds']['fees']        += $data['feeAmount'] ?? 0;
                $chartData['revenue_refunds']['net_revenue'] += $data['netAmount'] ?? 0;
            }

            // Round all revenue/refunds values to 2 decimal places
            $chartData['revenue_refunds']['revenue']           = round($chartData['revenue_refunds']['revenue'], 2);
            $chartData['revenue_refunds']['refunds']           = round($chartData['revenue_refunds']['refunds'], 2);
            $chartData['revenue_refunds']['fees']              = round($chartData['revenue_refunds']['fees'], 2);
            $chartData['revenue_refunds']['dispute_reversals'] = round($periodDisputeReversals, 2);
            $chartData['revenue_refunds']['net_revenue']       = round(
                $chartData['revenue_refunds']['net_revenue'] - $periodDisputeReversals,
                2
            );
        }

        return $chartData;
    }

    /**
     * Get enabled third party platforms from settings
     *
     * @return array
     */
    private function getEnabledThirdPartyPlatforms(): array
    {
        $platforms = [];

        // Platform names are sourced dynamically from config('constants.otas') so a newly
        // added OTA becomes filterable without a code change. FareHarbor isn't in that
        // registry (it's detected via a comments marker, not an ota_reference_booking
        // prefix), so it's included alongside it as a fixed special case.
        $platformNames = array_merge(BokunUtils::getReportableOtaTitles(), ['FareHarbor']);

        $settingKeys = array_map(
            fn($name) => 'ENABLE_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name)),
            $platformNames
        );

        $settings = \App\Models\Setting::whereIn('key', $settingKeys)->get()->keyBy('key');

        foreach ($platformNames as $platformName) {
            $settingKey = 'ENABLE_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $platformName));

            // Only show a platform when its settings row exists and is explicitly enabled.
            // No row = hidden, until a tenant admin explicitly turns it on.
            if (isset($settings[$settingKey]) && $settings[$settingKey]->value == 1) {
                $platforms[] = $platformName;
            }
        }

        return $platforms;
    }
}