<?php

namespace App\Services\Network\Package;

use App\Models\TourPackage;
use App\Models\PackageAffiliateAssignment;
use Illuminate\Database\Eloquent\Collection;
use App\Constants\Constants;

class PackageService
{
    /**
     * Get the package
     * 
     * @param int $packageId
     * 
     * @return TourPackage|null
     */
    public function getPackage($packageId): TourPackage|null
    {
        return TourPackage::findOrFail($packageId);
    }

    /**
     * Get the assigned packages
     * 
     * @param int $affiliateId
     * 
     * @return Collection
     */
    public function getAssignedPackages($affiliateId): Collection
    {
        return PackageAffiliateAssignment::where('affiliate_id', $affiliateId)
            ->where('show_all_package_bookings', Constants::STATUS_ZERO)
            ->orderBy('package_id')
            ->get();
    }
}
