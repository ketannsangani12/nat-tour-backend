<?php

namespace App\Services\Customer;

use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Get customers
     * 
     * @return object
     */
    public static function getCustomers(): object
    {
        $departmentPackage = departmentPackage();

        $customer = DB::table('customers AS c')
            ->where('is_affiliate', 'Yes')
            ->where('deleted_by', 0)
            ->where('status', 'Active')
            ->whereNotIn('name', ['TripAdvisor', 'Bokun'])
			->where('bokun', 0)
            ->when(userType() == 'Affiliate', function ($query) {
                return $query->where('id', auth()->id());
            })
            ->when($departmentPackage, function ($query) {
                return $query->where('department_id', auth()->user()->department_id);
            })
            ->orderBy('name')
            ->get(['c.id', 'c.name', 'c.email', 'c.phone_number']);

        return $customer;
    }

    /**
     * Get customer with booking
     * 
     * @param int $customerId
     * @param int $bookingId
     * 
     * @return object|null
     */
    public static function getCustomerWithBooking($customerId, $bookingId): object
    {
        $customer = DB::table('customers AS c')
            ->join('tour_bookings_master AS tbm', 'tbm.customer_id', '=', 'c.id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('c.id', $customerId)
            ->where('tbm.id', $bookingId)
            ->first(['tbm.name', 'tbm.email', 'tbm.phone_number', 'tp.tour_operator_id', 'tbm.tour_package_id', 'c.is_affiliate', 'c.id']);

        return $customer;
    }
}
