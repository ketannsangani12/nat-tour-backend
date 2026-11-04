<?php

namespace App\Services\Network\Package;

use Illuminate\Support\Facades\DB;
use App\Constants\Constants;
use App\Services\TimeSlot\TouristService;

class PackageRelationService
{
    /**
     * Get the package relations
     * 
     * @param int $packageId
     * 
     * @return array
     */
    public function getPackageRelations($packageId, $requestDate): array
    {
        $year = date('Y', strtotime($requestDate));
        return DB::select("SELECT * FROM tour_package_families WHERE relation_year = $year AND parent_package_id IN (
                SELECT parent_package_id FROM tour_package_families WHERE tour_package_id = $packageId AND relation_year = $year)");
    }

    /**
     * Get the relation package and tourists
     * 
     * @param bool $packageRelationSetting
     * @param array $packageFamilies
     * @param object $slot
     * 
     * @return array
     */
    public function getRelationPackageAndTourists(bool $packageRelationSetting, array $packageFamilies, $slot): array
    {
        if (!$packageRelationSetting || empty($packageFamilies)) {
            return [[], Constants::STATUS_ZERO];
        }

        $relationPackage = array_filter($packageFamilies, fn($pkg) => $pkg->tour_package_id == $slot->tour_package_id && $pkg->group_index == $slot->group_index);

        $totalTourists = TouristService::countTotalTourists($packageFamilies, $slot);

        return [$relationPackage, $totalTourists];
    }
}
