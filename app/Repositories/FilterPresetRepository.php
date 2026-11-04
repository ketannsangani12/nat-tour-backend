<?php

namespace App\Repositories;

use App\Models\FilterPreset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

class FilterPresetRepository extends Repository
{
    public function __construct()
    {
        $this->model = FilterPreset::class;
    }

    /**
     * Get all presets for current user and report type
     *
     * @param int $userId
     * @param string $userType
     * @param string $reportType
     * @return Collection
     */
    public function getUserPresets(int $userId, string $userType, string $reportType): Collection
    {
        try {
            return FilterPreset::forUser($userId, $userType)
                ->forReport($reportType)
                ->orderBy('name', 'asc')
                ->get();
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::getUserPresets Error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'report_type' => $reportType
            ]);
            return collect([]);
        }
    }

    /**
     * Get single preset by ID
     *
     * @param int $id
     * @param int $userId
     * @param string $userType
     * @return FilterPreset|null
     */
    public function getPreset(int $id, int $userId, string $userType): ?FilterPreset
    {
        try {
            return FilterPreset::forUser($userId, $userType)
                ->find($id);
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::getPreset Error: ' . $e->getMessage(), [
                'id' => $id,
                'user_id' => $userId,
                'user_type' => $userType
            ]);
            return null;
        }
    }

    /**
     * Create new preset
     *
     * @param int $userId
     * @param string $userType
     * @param string $name
     * @param string $reportType
     * @param array $filters
     * @return FilterPreset
     * @throws Exception
     */
    public function createPreset(int $userId, string $userType, string $name, string $reportType, array $filters): FilterPreset
    {
        try {
            return FilterPreset::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'name' => trim($name),
                'report_type' => $reportType,
                'filters' => $filters
            ]);
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::createPreset Error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'name' => $name,
                'report_type' => $reportType
            ]);
            throw $e;
        }
    }

    /**
     * Update existing preset
     *
     * @param int $id
     * @param int $userId
     * @param string $userType
     * @param array $data
     * @return bool
     */
    public function updatePreset(int $id, int $userId, string $userType, array $data): bool
    {
        try {
            $preset = $this->getPreset($id, $userId, $userType);
            
            if (!$preset) {
                return false;
            }

            // Trim name if provided
            if (isset($data['name'])) {
                $data['name'] = trim($data['name']);
            }

            return $preset->update($data);
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::updatePreset Error: ' . $e->getMessage(), [
                'id' => $id,
                'user_id' => $userId,
                'user_type' => $userType
            ]);
            return false;
        }
    }

    /**
     * Delete preset
     *
     * @param int $id
     * @param int $userId
     * @param string $userType
     * @return bool
     */
    public function deletePreset(int $id, int $userId, string $userType): bool
    {
        try {
            $preset = $this->getPreset($id, $userId, $userType);
            
            if (!$preset) {
                return false;
            }

            return $preset->delete();
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::deletePreset Error: ' . $e->getMessage(), [
                'id' => $id,
                'user_id' => $userId,
                'user_type' => $userType
            ]);
            return false;
        }
    }

    /**
     * Check if preset name exists for user
     *
     * @param int $userId
     * @param string $userType
     * @param string $name
     * @param string $reportType
     * @param int|null $excludeId
     * @return bool
     */
    public function presetNameExists(int $userId, string $userType, string $name, string $reportType, ?int $excludeId = null): bool
    {
        try {
            $query = FilterPreset::forUser($userId, $userType)
                ->forReport($reportType)
                ->where('name', trim($name));

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            return $query->exists();
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::presetNameExists Error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'name' => $name,
                'report_type' => $reportType
            ]);
            return false;
        }
    }

    /**
     * Get preset count for user and report type
     *
     * @param int $userId
     * @param string $userType
     * @param string $reportType
     * @return int
     */
    public function getUserPresetCount(int $userId, string $userType, string $reportType): int
    {
        try {
            return FilterPreset::forUser($userId, $userType)
                ->forReport($reportType)
                ->count();
        } catch (Exception $e) {
            Log::error('FilterPresetRepository::getUserPresetCount Error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'report_type' => $reportType
            ]);
            return 0;
        }
    }
}
