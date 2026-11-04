<?php

namespace App\Http\Controllers\Admin\AutoPayout;

use App\Http\Controllers\Controller;
use App\Models\PayoutAdjustment;
use App\Services\PayoutAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class PayoutAdjustmentController extends Controller
{
	protected PayoutAdjustmentService $adjustmentService;

	public function __construct()
	{
		parent::__construct();
		$this->adjustmentService = new PayoutAdjustmentService();
	}

	/**
	 * List adjustments with optional filters.
	 */
	public function index(Request $request): JsonResponse
	{
		try {
			$filters = $request->only([
				'status', 'booking_id', 'payout_reference', 'type',
				'date_from', 'date_to', 'per_page',
			]);

			$adjustments = $this->adjustmentService->getAdjustments($filters);

			return response()->json([
				'success' => true,
				'data' => $adjustments,
			]);
		} catch (Exception $e) {
			Log::error('PayoutAdjustmentController@index: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to fetch adjustments.',
			], 500);
		}
	}

	/**
	 * Show a single adjustment with full detail.
	 */
	public function show(PayoutAdjustment $payoutAdjustment): JsonResponse
	{
		try {
			$payoutAdjustment->load([
				'originalPayout',
				'appliedInPayout',
				'booking',
				'voidedByAdmin',
			]);

			return response()->json([
				'success' => true,
				'data' => $payoutAdjustment,
			]);
		} catch (Exception $e) {
			Log::error('PayoutAdjustmentController@show: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to fetch adjustment details.',
			], 500);
		}
	}

	/**
	 * Get summary statistics for adjustments.
	 */
	public function summary(): JsonResponse
	{
		try {
			$summary = $this->adjustmentService->getSummary();

			return response()->json([
				'success' => true,
				'data' => $summary,
			]);
		} catch (Exception $e) {
			Log::error('PayoutAdjustmentController@summary: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to fetch adjustment summary.',
			], 500);
		}
	}

	/**
	 * Manually trigger adjustment detection.
	 */
	public function detect(): JsonResponse
	{
		try {
			$newAdjustments = $this->adjustmentService->detectAndCreateAdjustments();

			return response()->json([
				'success' => true,
				'message' => $newAdjustments->isEmpty()
					? 'No new adjustments detected.'
					: $newAdjustments->count() . ' new adjustment(s) detected.',
				'data' => [
					'count' => $newAdjustments->count(),
					'adjustments' => $newAdjustments,
				],
			]);
		} catch (Exception $e) {
			Log::error('PayoutAdjustmentController@detect: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to detect adjustments: ' . $e->getMessage(),
			], 500);
		}
	}

	/**
	 * Void a pending adjustment.
	 */
	public function void(Request $request, PayoutAdjustment $payoutAdjustment): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'reason' => 'required|string|min:5|max:500',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed.',
				'errors' => $validator->errors(),
			], 422);
		}

		try {
			$this->adjustmentService->voidAdjustment(
				$payoutAdjustment,
				Auth::guard('admin')->id(),
				$request->input('reason')
			);

			return response()->json([
				'success' => true,
				'message' => 'Adjustment voided successfully.',
			]);
		} catch (Exception $e) {
			Log::error('PayoutAdjustmentController@void: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => $e->getMessage(),
			], 422);
		}
	}

	/**
	 * Create a manual adjustment.
	 */
	public function store(Request $request): JsonResponse
	{
		$validator = Validator::make($request->all(), [
			'payout_id' => 'required|integer',
			'payout_source' => 'nullable|in:auto,pending',
			'booking_id' => 'required|integer|exists:tour_bookings_master,id',
			'type' => 'required|in:credit,debit',
			'net_amount' => 'required|numeric|min:0.01',
			'gross_amount' => 'nullable|numeric|min:0.01',
			'platform_fee' => 'nullable|numeric|min:0',
			'reason' => 'required|string|min:5|max:500',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed.',
				'errors' => $validator->errors(),
			], 422);
		}

		try {
			$adjustment = $this->adjustmentService->createManualAdjustment(
				$request->only(['payout_id', 'payout_source', 'booking_id', 'type', 'net_amount', 'gross_amount', 'platform_fee', 'reason']),
				Auth::guard('admin')->id()
			);

			return response()->json([
				'success' => true,
				'message' => 'Manual adjustment created successfully.',
				'data' => $adjustment,
			]);
		} catch (Exception $e) {
			Log::error('PayoutAdjustmentController@store: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => $e->getMessage(),
			], 422);
		}
	}
}
