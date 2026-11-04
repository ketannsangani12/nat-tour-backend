<?php

namespace App\Services\Network\Customer;

use App\Models\Customer;
use App\Utils\IpGeolocationUtils;

class CustomerService
{
    /**
     * Get the customer
     * 
     * @param array $customerInfo
     * 
     * @return Customer
     */
    public function findOrCreateCustomer(array $customerInfo): Customer
    {
        $customer = Customer::where('email', $customerInfo['email'])->first();

        if (!$customer) {
            $customer = new Customer();
            $customer->tour_operator_id = $customerInfo['tour_operator_id'] ?? tourOperator()->id ?? 1;
            $customer->email = $customerInfo['email'];
            $customer->phone_number = $customerInfo['phone'];
            $customer->name = $customerInfo['firstName'] . ' ' . $customerInfo['lastName'];
            $customer->ip_address = request()->ip(); // Capture IP address for network customers
            $customer->save();

            // Get and store location data for the new network customer
            if ($customer->id && $customer->ip_address) {
                IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, $customer->ip_address);
            }
        }

        return $customer;
    }
}
