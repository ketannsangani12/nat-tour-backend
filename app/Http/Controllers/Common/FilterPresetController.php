<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Http\Requests\Common\StoreFilterPresetRequest;
use App\Http\Requests\Common\UpdateFilterPresetRequest;
use App\Repositories\FilterPresetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class FilterPresetController extends Controller
{
    protected FilterPresetRepository $repository;
    private const MAX_PRESETS_PER_USER = 50;

    public function __construct(FilterPresetRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;

        // Apply permission middleware for managing filter presets
        // Anyone with view permission can list (index) and load (show) presets
        // Only users with manage permission can create (store), update, or delete (destroy) presets
        $this->middleware('permission:manage-payment-breakdown-filters')->only(['store', 'update', 'destroy']);
    }

    /**
     * Get current user ID and type from session
     *
     * @return array
     */
    private function getCurrentUser(): array
    {
        $userType = session('userType', 'Admin');
        $guard = session('guard', 'admin');
        
        // Get authenticated user ID
        $userId = auth($guard)->id();

        return [
            'user_id' => $userId,
            'user_type' => $userType
        ];
    }

    /**
     * Display a listing of the user's filter presets
     *
     * GET /api/filter-presets?report_type=payment_breakdown
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $reportType = $request->input('report_type', 'payment_breakdown');
            $user = $this->getCurrentUser();

            $presets = $this->repository->getUserPresets(
                $user['user_id'],
                $user['user_type'],
                $reportType
            );

            return response()->json([
                'success' => true,
                'data' => $presets
            ]);
        } catch (Exception $e) {
            Log::error('FilterPresetController::index Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filter presets.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created filter preset
     *
     * POST /api/filter-presets
     *
     * @param StoreFilterPresetRequest $request
     * @return JsonResponse
     */
    public function store(StoreFilterPresetRequest $request): JsonResponse
    {
        try {
            $user = $this->getCurrentUser();
            $reportType = $request->input('report_type');
            $name = $request->input('name');

            // Check preset limit
            $presetCount = $this->repository->getUserPresetCount(
                $user['user_id'],
                $user['user_type'],
                $reportType
            );

            if ($presetCount >= self::MAX_PRESETS_PER_USER) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have reached the maximum limit of ' . self::MAX_PRESETS_PER_USER . ' presets. Please delete some presets before creating new ones.',
                    'code' => 'PRESET_LIMIT_EXCEEDED'
                ], 422);
            }

            // Check if preset name already exists
            $nameExists = $this->repository->presetNameExists(
                $user['user_id'],
                $user['user_type'],
                $name,
                $reportType
            );

            if ($nameExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A preset with this name already exists.',
                    'code' => 'DUPLICATE_NAME'
                ], 409);
            }

            // Create the preset
            $preset = $this->repository->createPreset(
                $user['user_id'],
                $user['user_type'],
                $name,
                $reportType,
                $request->input('filters')
            );

            return response()->json([
                'success' => true,
                'data' => $preset,
                'message' => 'Filter preset saved successfully.'
            ], 201);
        } catch (Exception $e) {
            Log::error('FilterPresetController::store Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save filter preset.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified filter preset
     *
     * GET /api/filter-presets/{id}
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->getCurrentUser();

            $preset = $this->repository->getPreset(
                $id,
                $user['user_id'],
                $user['user_type']
            );

            if (!$preset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter preset not found or you do not have permission to access it.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $preset
            ]);
        } catch (Exception $e) {
            Log::error('FilterPresetController::show Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filter preset.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified filter preset
     *
     * PUT /api/filter-presets/{id}
     *
     * @param UpdateFilterPresetRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateFilterPresetRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->getCurrentUser();

            // Check if preset exists and belongs to user
            $preset = $this->repository->getPreset(
                $id,
                $user['user_id'],
                $user['user_type']
            );

            if (!$preset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter preset not found or you do not have permission to modify it.'
                ], 404);
            }

            // If name is being updated, check for duplicates
            if ($request->has('name')) {
                $nameExists = $this->repository->presetNameExists(
                    $user['user_id'],
                    $user['user_type'],
                    $request->input('name'),
                    $preset->report_type,
                    $id
                );

                if ($nameExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A preset with this name already exists.',
                        'code' => 'DUPLICATE_NAME'
                    ], 409);
                }
            }

            // Update the preset
            $updated = $this->repository->updatePreset(
                $id,
                $user['user_id'],
                $user['user_type'],
                $request->only(['name', 'filters'])
            );

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update filter preset.'
                ], 500);
            }

            // Fetch updated preset
            $preset = $this->repository->getPreset(
                $id,
                $user['user_id'],
                $user['user_type']
            );

            return response()->json([
                'success' => true,
                'data' => $preset,
                'message' => 'Filter preset updated successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('FilterPresetController::update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update filter preset.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified filter preset
     *
     * DELETE /api/filter-presets/{id}
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = $this->getCurrentUser();

            // Check if preset exists and belongs to user
            $preset = $this->repository->getPreset(
                $id,
                $user['user_id'],
                $user['user_type']
            );

            if (!$preset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter preset not found or you do not have permission to delete it.'
                ], 404);
            }

            // Delete the preset
            $deleted = $this->repository->deletePreset(
                $id,
                $user['user_id'],
                $user['user_type']
            );

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete filter preset.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Filter preset deleted successfully.'
            ]);
        } catch (Exception $e) {
            Log::error('FilterPresetController::destroy Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete filter preset.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
