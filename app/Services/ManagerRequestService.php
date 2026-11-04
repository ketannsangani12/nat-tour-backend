<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use App\Utils\ManagerRequestUtils;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Auth;

class ManagerRequestService
{
    /**
     * The manager Request query
     * @param $status
     * @param $userType
     */
    public function getManagerRequests($status, $userType)
    {
        $query = DB::table('tour_bookings_master as tbm')
            ->join('booking_requests as br', 'br.order_id', '=', 'tbm.group_id')
            ->join('tour_operator_staff as tos', 'tos.id', '=', 'br.manager_id')
            ->orderBy('br.created_at', 'desc') // Sort by descending to show new requests first
            ->distinct()
            ->select(
                'br.tour_booking_id',
                'br.bookings',
                'br.order_id',
                'tbm.name as customer_name',
                'br.created_at',
                'br.request_type',
                'br.id',
                'br.staff_type',
                'br.staff_id',
                'br.status',
                DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as manager_name")
            )
            ->groupBy('br.id');

        if ($status == "Pending") {
            $query = $query->where('br.status', 'Pending');
        } else {
            $query = $query->where('br.status', '!=', 'Pending');
        }

        if ($userType == "Tour Operator") {
            $query = $query->where('br.manager_id', Auth::id());
        }

        $requests = $query->get();

        // Add staff or customer name
        foreach ($requests as $request) {
            $request->name = $this->getStaffOrCustomerName($request);
        }

        return $requests;
    }

     /**
     * Get the saff name
     * @param $request
     */
    private function getStaffOrCustomerName($request)
    {
        if ($request->staff_type == "Tour Operator") {
            $staff = Staff::find($request->staff_id);
            return $staff ? $staff->name : '';
        } else {
            $customer = Customer::find($request->staff_id);
            return $customer ? $customer->name : '';
        }
    }

    /**
     * Summary of requestPermissions
     * @param mixed $id
     * @return array
     */
    public static function fetchDetailsOfRequestFields($requestType, $details, $bookingId): array
    {
        $details = (array)json_decode($details);
        $fields = ManagerRequestUtils::requestFieldsMappings($requestType);
        $touristsGroups = [];

        // Handle 'Change' request type
        if ($requestType == 'Change') {
            $touristsGroups = ManagerRequestUtils::getTouristsGroupsForChangePeople($details['rate_group'], $details['tourists'], $bookingId);
        }
        // Handle 'Discount' request type and decode discountHistory if exists
        if ($requestType == 'Discount' && isset($details['discountHistory']) && !empty($details['discountHistory'])) {
            $details = (array)json_decode($details['discountHistory']);
        }

        $data = [];

        foreach ($fields as $key => $field) {
            // Skip empty or non-existent fields in details
            if (!isset($details[$key]) || $details[$key] == "") continue;

            $value = $details[$key];

            // Handle specific field mappings based on requestType
            switch ($key) {
                case 'refund_type':
                case 'refund_Type':
                    $value = ManagerRequestUtils::requestRefundType($value);
                    break;
                case 'refundType':
                    $value = ManagerRequestUtils::requestRefundTypeForAddons($value);
                    break;
                case 'rate_group':
                    if ($requestType == 'Change') {
                        $value = ($touristsGroups['added'] != '') ? $touristsGroups['added'] : '';
                    }
                    break;
                case 'tourists':
                    if ($requestType == 'Change') {
                        $value = ($touristsGroups['deleted'] != '') ? $touristsGroups['deleted'] : '';
                    }
                    break;
                case 'new_discount2_percentage':
                    if ($requestType == 'Discount' && $value > 0) {
                        $value = $value . "%";
                    } else {
                        $key = $value = $field = '';  // Clear unnecessary fields
                    }
                    break;
                case 'tour_package_switch':
                    if ($requestType == 'Switch') {
                        $tourPackage = TourPackage::find($value);
                        $value = $tourPackage->name ?? '';
                    }
                    break;
                case 'tour_slot_time_switch':
                case 'slotId':
                    if (in_array($requestType, ['Switch', 'Closure Email'])) {
                        $value = ManagerRequestUtils::requestSlotDateTime($details[$key]);
                    }
                    break;
                case 'custom_charge_ids':
                    if ($requestType == 'Refund Custom Charge') {
                        $value = ManagerRequestUtils::requestCustomChargesInfo($details[$key], $bookingId);
                    }
                    break;
                case 'customer_id':
                    if ($requestType == 'Rebook') {
                        $customerInfo = Customer::find($details[$key]);
                        $value = $customerInfo->name;
                    }
                    break;
                case 'refundItems':
                    if ($requestType == 'Refund Addons') {
                        $value = ManagerRequestUtils::requestAddOnsInfo($details[$key], $bookingId);
                    }
                    break;
                case 'refund_Type':
                    if ($requestType == 'Permit') {
                        $value = 'Permitfees Refund';
                    } elseif ($requestType == 'Split Booking') {
                        $key = $value = $field = '';  // Clear unnecessary fields
                    }
                    break;
                case 'check':
                    if (in_array($requestType, ['Delete', 'Permit', 'Split Booking'])) {
                        $value = ManagerRequestUtils::touristsRequestOptions($key, $details[$key]);
                    }
                    break;
                case 'partial_Refund_value':
                    if ($requestType == 'Delete' && $value == 'NaN') {
                        $key = $value = $field = '';  // Clear unnecessary fields
                    }
                    break;
                case 'tax_on_custom_charge':
                    if ($requestType == 'Tax On Custom Charge') {
                        $value = currencyFormation(false, true) . $value;
                    }
                    break;
            }

            // Add valid fields to the result
            if ($field != '') {
                $data[] = [
                    'name' => $field,
                    'value' => $value
                ];
            }
        }

        return $data;
    }
}
