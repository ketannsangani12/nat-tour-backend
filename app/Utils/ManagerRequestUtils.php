<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

class ManagerRequestUtils
{
    /**
     * Get manager request options and store as JSON
     * @return array
     */
    public static function getManagerRequestOptions($requestType, $data)
    {
        if ($requestType == 'Cancel') {
            $options = [
                'cancel_booking' => 1,
                'refund_type' => $data['refund_type'],
                'comment' => $data['comment'],
                'partial_refund_value' => $data['partial_refund_value'] ?? '',
                'is_cash' => $data['is_cash'] ?? ''
            ];
            $options['field_type'] = $requestType . '_' . $data['refund_type'];
        } else if ($requestType == 'Refund') {
            $options = [
                'refund_type' => $data['refund_type'],
                'comment' => $data['comment'],
                'partial_refund_value' => $data['partial_refund_value'] ?? '',
                'is_cash' => $data['is_cash'] ?? ''
            ];
            $options['field_type'] = $requestType . '_' . $data['refund_type'];
        }
        $options['request_type'] = $requestType;

        return $options;
    }
    /**
     * Get manager request fields mappings
     * @return array
     */
    public static function requestFieldsMappings($requestType)
    {
        $fields = [
            'Cancel' => [
                'refund_type' => 'Refund Type',
                'comment' => 'Comment',
                'partial_refund_value' => 'Partial Refund Amount',
                'is_cash' => 'Cash-Check/Wire'
            ],
            'Refund' => [
                'refund_type' => 'Refund Type',
                'comment' => 'Comment',
                'partial_refund_value' => 'Partial Refund Amount',
                'is_cash' => 'Cash-Check/Wire'
            ],
            'Reschedule' => [
                'tour_slot_time' => 'Reschedule To',
            ],
            'Delete' => [
                'refund_Type' => 'Refund Type',
                'is_cash' => 'Cash-Check/Wire',
                'partial_Refund_value' => 'Partial Refund Amount',
                'check' => 'Tourits',
            ],
            'Permit' => [
                'refund_Type' => 'Refund Type',
                'check' => 'Tourists',
            ],
            'Split Booking' => [
                'refund_Type' => 'Refund Type',
                'check' => 'Tourits',
            ],
            'Change' => [
                'rate_group' => 'Added',
                'tourists' => 'Deleted',
            ],
            'Discount' => [
                'discount_type' => 'Discount Type',
                'new_discount2_value' => 'Discount Amount',
                'new_discount2_percentage' => 'Discount Percentage',
                'new_discount_promo_code' => 'Promo Code',
                'discount_fixed_money' => 'Discount Amount',
                'discount_percentage' => 'Discount Percentage',
                'discount_promo_code' => 'Promo Code'
            ],
            'Switch' => [
                'tour_package_switch' => 'Switch Tour Package',
                'tour_slot_time_switch' => 'Switch Slot Time'
            ],
            'Refund Custom Charge' => [
                'refund_type' => 'Refund Type',
                'custom_charge_ids' => 'Custom Charges'
            ],
            'Rebook' => [
                'customer_id' => 'Customer'
            ],
            'Closure Email' => [
                'slotId' => 'Tour Slot Time'
            ],
            'Refund Addons' => [
                'refundType' => 'Refund Type',
                'refundItems' => 'Addons Selected'
            ],
            'Tax On Custom Charge' => [
                'customCharge' => 'Custom Charge',
                'customChargeFees' => 'Fees'
            ]
        ];

        return $fields[$requestType];
    }
    /**
     * Get formatted refund type to display in manager request info
     * @return string
     */
    public static function requestRefundType($refundType)
    {
        $refundTypes = [
            '100_refund' => '100% Refund',
            'full_refund' => 'Ticket Refund',
            'partial_refund' => 'Partial Refund',
            'dont_refund' => 'Don\'t Refund'
        ];
        return $refundTypes[$refundType] ?? '';
    }
    /**
     * Get formatted refund type to display in manager request info for addons
     * @return string
     */
    public static function requestRefundTypeForAddons($refundType)
    {
        $refundTypes = [
            'full_refund' => '100% Refund',
            'ticket_refund' => 'Ticket Refund',
            'dont_refund' => 'Don\'t Refund'
        ];
        return $refundTypes[$refundType] ?? '';
    }
    /**
     * Get and modify reschedule request options
     * @return array
     */
    public static function requestSlotDateTime($slotId)
    {
        $slotDetails = DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->where('tst.id', $slotId)
            ->first([
                'tst.time_from',
                'tsd.date'
            ]);
        $slotTime = date('d M Y, g:i a', strtotime($slotDetails->date . ' ' . $slotDetails->time_from));
        return $slotTime;
    }
    /**
     * Get and format selected tourists from JSON of manager request
     * @return array
     */
    public static function touristsRequestOptions($field, $val)
    {

        if ($field == 'check') {
            $touristsArray = $val;
            $checkArrays = array();
            $tourists = '';
            if (!empty($touristsArray)) {
                foreach ($touristsArray as $key => $value) {
                    $explode = explode('.', $key);
                    // if (isset($checkArrays[$value])) {
                    //     $checkArrays[$value]['pax'] += 1;
                    // } else {
                    $checkArrays[] = [
                        'rateGroup' => $explode[0],
                        'pax' => $explode[1]
                    ];
                    //}
                }
                $checkArrays = array_values($checkArrays);
                if (!empty($checkArrays)) {
                    foreach ($checkArrays as $key => $value) {
                        $tourists .= $value['rateGroup'] . ' Person #' . $value['pax'] + 1 . '<br>';
                    }
                }
            }
            return $tourists;
        }
    }
    /**
     * Get and format selected tourists from JSON of manager request for change people
     * @return array
     */
    public static function getTouristsGroupsForChangePeople($rateGroups, $tourists, $bookingId)
    {
        $bookingT = DB::table('tour_bookings_transact AS tbt')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->where('tbt.tour_booking_id', $bookingId)
            ->where('tbt.deleted_by', 0)
            ->orderBy('tpr.orderby', 'asc')
            ->get(['tpr.rate_for', 'tbt.tour_package_rate_id', 'tbt.tourists']);

        $rateGroupWiseTourists = array();
        $rateGroupWiseRateFor = array();
        $totalTouristsOfBooking = 0;
        foreach ($bookingT as $value) {
            $rateGroupWiseTourists[$value->tour_package_rate_id] = $value->tourists;
            $rateGroupWiseRateFor[$value->tour_package_rate_id] = $value->rate_for;
            $totalTouristsOfBooking += $value->tourists;
        }
        $added = '';
        $deleted = '';
        for ($a = 0, $ac = count($tourists); $a < $ac; $a++) {
            $rateGroupWiseaddedPeople[$a] = 0;
            $rateGroupWisedeletedPeople[$a] = 0;

            if (isset($rateGroups[$a]) && isset($rateGroupWiseTourists[$rateGroups[$a]]) && $tourists[$a] > $rateGroupWiseTourists[$rateGroups[$a]]) {
                $added .= $rateGroupWiseRateFor[$rateGroups[$a]] . ' person ' . ((int)$tourists[$a] - $rateGroupWiseTourists[$rateGroups[$a]]) . '<br>';
            }
            if (isset($rateGroups[$a]) && isset($rateGroupWiseTourists[$rateGroups[$a]]) && $tourists[$a] < $rateGroupWiseTourists[$rateGroups[$a]]) {
                $deleted .= $rateGroupWiseRateFor[$rateGroups[$a]] . ' person ' . ((int)$rateGroupWiseTourists[$rateGroups[$a]] - $tourists[$a]) . '<br>';
            }
            if (!isset($rateGroupWiseTourists[$rateGroups[$a]]) && $tourists[$a] > 0) {
                $rateGroupInfo = DB::table('tour_package_rates AS tpr')
                    ->where('tpr.id', $rateGroups[$a])
                    ->where('tpr.deleted_by', 0)
                    ->first(['tpr.rate_for']);
                if ($rateGroupInfo) {
                    $added .= $rateGroupInfo->rate_for . ' person ' . (int)$tourists[$a] . '<br>';
                }
            }
        }
        return [
            'added' => $added,
            'deleted' => $deleted
        ];
    }
    /**
     * Get and modify refund custom charges information
     * @return array
     */
    public static function requestCustomChargesInfo($customCharges, $bookingId)
    {
        $customChargesInfo = DB::table('tour_booking_custom_charges AS tbcc')
            ->whereIn('tbcc.id', $customCharges)
            ->where('tbcc.tour_booking_id', $bookingId)
            ->get([
                'tbcc.amount',
                'tbcc.fees',
                'tbcc.description'
            ]);
        $data = '';
        if (!empty($customChargesInfo)) {
            foreach ($customChargesInfo as $key => $value) {
                $data .= currencyFormation(false, true) . ($value->amount + $value->fees) . ' (' . $value->description . ')<br>';
            }
        }
        return $data;
    }
    /**
     * Get and modify refund custom charges information
     * @return array
     */
    public static function requestAddOnsInfo($addOnsItems, $bookingId)
    {
        $data = '';
        if (!empty($addOnsItems)) {
            foreach ($addOnsItems as $key => $value) {
                if (isset($value->bookingAddonId) && $value->bookingAddonId != '') {
                    $addOnInfo = DB::table('tour_booking_addons AS tba')
                        ->where('tba.id', $value->bookingAddonId)
                        ->where('tba.booking_id', $bookingId)
                        ->first([
                            'tba.description',
                            'tba.name',
                            'tba.price',
                            'tba.fee'
                        ]);
                    if ($addOnInfo) {
                        $desc = ($addOnInfo->description != '') ? $addOnInfo->description : $addOnInfo->name;
                        $data .= currencyFormation(false, true) . ($addOnInfo->price + $addOnInfo->fee);
                        if ($desc != '') {
                            $data .= ' (' . $desc . ')';
                        }
                        $data .= '<br>';
                    }
                }
            }
        }
        return $data;
    }
}
