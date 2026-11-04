<?php

namespace App\Services\Network\Package;

use App\Models\SurchargeDate;
use App\Models\RateSlotMapping;

class PackageRateService
{
    /**
     * Get the custom date
     * 
     * @param int $packageId
     * @param int $affiliateId
     * @param string $date
     * 
     * @return SurchargeDate|null
     */
    public function getCustomDate($packageId, $affiliateId, $date): SurchargeDate|null
    {
        return SurchargeDate::where('tour_package_id', $packageId)
            ->where('affiliate_id', $affiliateId)
            ->where('date_range_start', '<=', $date)
            ->where('date_range_end', '>=', $date)
            ->orderBy('priority', 'DESC')
            ->first();
    }

    /**
     * Check the rate slot mapping
     * 
     * @param int $packageId
     * @param int $affiliateId
     * @param int $customDateId
     * @param string $timeFrom
     * 
     * @return int
     */
    public function checkRateSlotMapping($packageId, $affiliateId, $customDateId, $timeFrom): int
    {
        return RateSlotMapping::where('tour_package_id', $packageId)
            ->where('affiliate_id', $affiliateId)
            ->where('custom_date_id', $customDateId)
            ->where('time_from', $timeFrom)
            ->count();
    }
}
