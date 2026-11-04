<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Utils\IpGeolocationUtils;

class CustomerLocationController extends Controller
{
    /**
     * Get customer location information
     */
    public function getCustomerLocation($customerId)
    {
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        if (!$customer->ip_address) {
            return response()->json(['error' => 'No IP address stored for this customer'], 400);
        }

        return response()->json([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'ip_address' => $customer->ip_address,
            'country' => $customer->country,
            'region' => $customer->region,
            'city' => $customer->city,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'timezone' => $customer->timezone,
            'isp' => $customer->isp,
            'formatted_address' => $customer->formatted_location
        ]);
    }

    /**
     * Get locations for multiple customers
     */
    public function getCustomersWithLocations(Request $request)
    {
        $limit = $request->get('limit', 50);
        
        $customers = Customer::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->limit($limit)
            ->get();

        $customersWithLocations = $customers->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'ip_address' => $customer->ip_address,
                'country' => $customer->country,
                'region' => $customer->region,
                'city' => $customer->city,
                'latitude' => $customer->latitude,
                'longitude' => $customer->longitude,
                'timezone' => $customer->timezone,
                'isp' => $customer->isp,
                'formatted_address' => $customer->formatted_location
            ];
        });

        return response()->json([
            'customers' => $customersWithLocations,
            'total' => $customersWithLocations->count()
        ]);
    }

    /**
     * Update a customer's IP address manually and refresh location data
     */
    public function updateCustomerIp(Request $request, $customerId)
    {
        $request->validate([
            'ip_address' => 'required|ip'
        ]);

        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $customer->update([
            'ip_address' => $request->ip_address
        ]);

        // Get and store location data
        $locationData = IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, $request->ip_address);

        // Refresh customer data
        $customer->refresh();

        return response()->json([
            'message' => 'IP address and location data updated successfully',
            'customer_id' => $customer->id,
            'ip_address' => $customer->ip_address,
            'country' => $customer->country,
            'region' => $customer->region,
            'city' => $customer->city,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'timezone' => $customer->timezone,
            'isp' => $customer->isp,
            'formatted_address' => $customer->formatted_location
        ]);
    }
}