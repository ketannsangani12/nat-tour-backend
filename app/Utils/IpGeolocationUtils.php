<?php

namespace App\Utils;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Customer;

class IpGeolocationUtils
{
    /**
     * Get location information from IP address and optionally store in customer record
     * 
     * @param string $ipAddress
     * @param int|null $customerId - If provided, will update customer record with location data
     * @return array|null
     */
    public static function getLocationFromIp($ipAddress, $customerId = null)
    {
        // Skip local/private IP addresses
        if (self::isPrivateIp($ipAddress)) {
            $locationData = [
                'ip' => $ipAddress,
                'country' => 'Local/Private',
                'region' => 'Local/Private',
                'city' => 'Local/Private',
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
                'isp' => 'Local/Private'
            ];

            // Store in customer record if customerId provided
            if ($customerId) {
                self::updateCustomerLocationData($customerId, $locationData);
            }

            return $locationData;
        }

        // Check cache first (cache for 24 hours)
        $cacheKey = 'ip_location_' . $ipAddress;
        $cachedResult = Cache::get($cacheKey);
        
        if ($cachedResult) {
            // Store in customer record if customerId provided
            if ($customerId) {
                self::updateCustomerLocationData($customerId, $cachedResult);
            }
            return $cachedResult;
        }

        $location = null;

        // Try multiple free IP geolocation services
        $services = [
            'ipapi' => function($ip) {
                return self::getLocationFromIpApi($ip);
            },
            'ipinfo' => function($ip) {
                return self::getLocationFromIpInfo($ip);
            },
            'freeipapi' => function($ip) {
                return self::getLocationFromFreeIpApi($ip);
            }
        ];

        foreach ($services as $serviceName => $serviceFunction) {
            try {
                $location = $serviceFunction($ipAddress);
                if ($location && isset($location['country'])) {
                    // Log::info("IP geolocation successful using {$serviceName}", ['ip' => $ipAddress, 'location' => $location]);
                    break;
                }
            } catch (\Exception $e) {
                Log::warning("IP geolocation failed for {$serviceName}", ['ip' => $ipAddress, 'error' => $e->getMessage()]);
                continue;
            }
        }

        // If all services fail, return basic info
        if (!$location) {
            $location = [
                'ip' => $ipAddress,
                'country' => 'Unknown',
                'region' => 'Unknown',
                'city' => 'Unknown',
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
                'isp' => 'Unknown'
            ];
        }

        // Cache the result for 24 hours
        Cache::put($cacheKey, $location, 60 * 24);

        // Store in customer record if customerId provided
        if ($customerId) {
            self::updateCustomerLocationData($customerId, $location);
        }

        return $location;
    }

    /**
     * Get location from ip-api.com (free, no API key required)
     */
    private static function getLocationFromIpApi($ipAddress)
    {
        $response = Http::timeout(10)->get("http://ip-api.com/json/{$ipAddress}");
        
        if ($response->successful()) {
            $data = $response->json();
            
            if ($data['status'] === 'success') {
                return [
                    'ip' => $ipAddress,
                    'country' => $data['country'] ?? 'Unknown',
                    'region' => $data['regionName'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown',
                    'latitude' => $data['lat'] ?? null,
                    'longitude' => $data['lon'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'isp' => $data['isp'] ?? 'Unknown'
                ];
            }
        }
        
        return null;
    }

    /**
     * Get location from ipinfo.io (free tier available)
     */
    private static function getLocationFromIpInfo($ipAddress)
    {
        $response = Http::timeout(10)->get("https://ipinfo.io/{$ipAddress}/json");
        
        if ($response->successful()) {
            $data = $response->json();
            
            $location = explode(',', $data['loc'] ?? '');
            
            return [
                'ip' => $ipAddress,
                'country' => $data['country'] ?? 'Unknown',
                'region' => $data['region'] ?? 'Unknown',
                'city' => $data['city'] ?? 'Unknown',
                'latitude' => isset($location[0]) ? (float)$location[0] : null,
                'longitude' => isset($location[1]) ? (float)$location[1] : null,
                'timezone' => $data['timezone'] ?? null,
                'isp' => $data['org'] ?? 'Unknown'
            ];
        }
        
        return null;
    }

    /**
     * Get location from freeipapi.com
     */
    private static function getLocationFromFreeIpApi($ipAddress)
    {
        $response = Http::timeout(10)->get("https://freeipapi.com/api/json/{$ipAddress}");
        
        if ($response->successful()) {
            $data = $response->json();
            
            return [
                'ip' => $ipAddress,
                'country' => $data['countryName'] ?? 'Unknown',
                'region' => $data['regionName'] ?? 'Unknown',
                'city' => $data['cityName'] ?? 'Unknown',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'timezone' => $data['timeZone'] ?? null,
                'isp' => 'Unknown'
            ];
        }
        
        return null;
    }

    /**
     * Check if IP address is private/local
     */
    private static function isPrivateIp($ipAddress)
    {
        return !filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get formatted address string from location data
     */
    public static function getFormattedAddress($locationData)
    {
        if (!$locationData) {
            return 'Unknown Location';
        }

        $parts = [];
        
        if (!empty($locationData['city']) && $locationData['city'] !== 'Unknown') {
            $parts[] = $locationData['city'];
        }
        
        if (!empty($locationData['region']) && $locationData['region'] !== 'Unknown') {
            $parts[] = $locationData['region'];
        }
        
        if (!empty($locationData['country']) && $locationData['country'] !== 'Unknown') {
            $parts[] = $locationData['country'];
        }

        return !empty($parts) ? implode(', ', $parts) : 'Unknown Location';
    }

    /**
     * Get customer's current location based on their stored IP address
     */
    public static function getCustomerLocation($customerId)
    {
        $customer = Customer::find($customerId);
        
        if (!$customer || !$customer->ip_address) {
            return null;
        }

        return self::getLocationFromIp($customer->ip_address);
    }

    /**
     * Update customer record with location data
     */
    public static function updateCustomerLocationData($customerId, $locationData)
    {
        try {
            Customer::where('id', $customerId)->update([
                'country' => $locationData['country'] ?? null,
                'region' => $locationData['region'] ?? null,
                'city' => $locationData['city'] ?? null,
                'latitude' => $locationData['latitude'] ?? null,
                'longitude' => $locationData['longitude'] ?? null,
                'timezone' => $locationData['timezone'] ?? null,
                'isp' => $locationData['isp'] ?? null,
            ]);

            // Log::info("Customer location data updated", ['customer_id' => $customerId, 'location' => $locationData]);
        } catch (\Exception $e) {
            Log::error("Failed to update customer location data", ['customer_id' => $customerId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get and store location for a customer by their IP address
     */
    public static function updateCustomerLocationFromIp($customerId, $ipAddress)
    {
        $locationData = self::getLocationFromIp($ipAddress, $customerId);
        return $locationData;
    }
}