<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\API\V2\BaseController;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Log;

class SettingController extends BaseController
{
    /**
     * Get settings for a tour operator
     *
     * @param int $tourOperatorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettings()
    {
        try {
            $needSettings = ['GA4_CODE'];
            
            $settings = Setting::whereIn('key', $needSettings)
                ->orderBy('order_number')
                ->get();

            if ($settings->isEmpty()) {
                return $this->sendError('No settings found', [], 404);
            }

            $formattedSettings = $settings->map(function ($setting) {
                return [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'order_number' => $setting->order_number,
                    'comment' => $setting->comment,
                    'input_type' => $setting->input_type,
                ];
            });

            return $this->sendResponse($formattedSettings, 'Settings retrieved successfully');
        } catch (Exception $exception) {
            Log::error('Error fetching settings: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString()
            ]);

            return $this->sendError('Failed to retrieve settings', [], 500);
        }
    }
}
