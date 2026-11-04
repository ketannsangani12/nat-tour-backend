<?php

namespace App\Http\Requests\API\PartnerApi;
use App\Models\Customer;

class ApiAuthRequest 
{
    /**
     * Authorize the partner API request based on the provided API key.
     *
     * @param array $data
     * @return array|null
     */
    public static function PartnerAuthorize($req): array|null
    {
        $data = $req['data'] ?? [];
        if (empty($data) || !isset($data['ApiKey'])) { 
            return NULL;
        }

        $apiKey = $data['ApiKey'];

        if ($apiKey === null || empty($apiKey)) {
            return NULL;
        }

        $customer = Customer::where('reseller_api_key', $apiKey)->first();
        
        if (!$customer) {
            return NULL;
        }

        // return the affiliate data from customer model
        return [
            'AffiliateName' => $customer->name,
            'AffiliateId' => $customer->id,
            'AffiliateTourOperatorId' => $customer->tour_operator_id,
        ];

       
    }
   
}