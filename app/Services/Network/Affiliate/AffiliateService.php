<?php

namespace App\Services\Network\Affiliate;

use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Constants\Constants;

class AffiliateService
{
    /**
     * Get the affiliate
     * 
     * @return Customer|null
     */
    public function getAffiliate(): Customer|null
    {
        return Customer::where('email', 'network@nativeamericantours.com')->isAffiliate()->first();
    }

    /**
     * Get the affiliate time slots
     * 
     * @param int $affiliateId
     * @param int $packageId
     * 
     * @return array
     */
    public function getAffiliateTimeSlots($affiliateId, $packageId): array
    {
        return DB::table('tour_slot_times_affiliate as tsta')
            ->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
            ->where('ast.affiliate_id', $affiliateId)
            ->where('tsta.tour_package_id', $packageId)
            ->where('tsta.status', Constants::STATUS_ACTIVE)
            ->where('tsta.deleted_by', Constants::STATUS_ZERO)
            ->select('tsta.id as time_slot_id', 'tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
            ->get()
            ->toArray();
    }

    /**
     * Get the commission percentage
     * 
     * @param Customer $customer
     * @param string $date
     * 
     * @return float
     */
    public function getCommissionPercentage(Customer $customer, $date): float
    {
        if ($customer->is_ota) {
            $percentage = $customer->ota_affiliate_percentage;
            if (getNextYearCommissionPercentage($date)) {
                $percentage = $customer->next_year_ota_affiliate_percentage;
            }
            return (float) $percentage;
        }

        $serviceCommissionPercentage = $customer->affiliate_processing_percentage;
        if (getNextYearCommissionPercentage($date)) {
            $affiliateCommission = getAffiliateCommissionPercentage($customer->id);
            $serviceCommissionPercentage = $affiliateCommission['affiliate_processing_percentage'];
        }

        return $serviceCommissionPercentage;
    }
}
