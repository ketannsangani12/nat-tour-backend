<?php

namespace App\Services\Booking;

use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\DB;
use Exception;

class BundlePackageBookingService
{
    /**
	 * Get bundle package's child packages
	 *
     * @param integer $isBundlePackage
     * @param integer $bookingId
     * @param integer $packageId
	 *
	 * @return object
	 */
    public static function getBundlePackageBookings($isBundlePackage, $bookingId, $packageId, $tenant = null): object
    {
        // Select DB connection based on tenant
        $db = $tenant ? DB::connection('master') : DB::connection();

        if ($isBundlePackage == 1) {
            $packages = $db->table('tour_package_bundle_relations as tpbr')
                ->join('tour_bookings_master as tbm', 'tbm.tour_package_id', '=', 'tpbr.parent_id')
                ->join('tour_packages as tp', 'tp.id', '=', 'tpbr.tour_package_id')
                ->where('tbm.id', $bookingId)
                ->where('tpbr.parent_id', $packageId)
                ->whereNull('tpbr.deleted_at')
                ->where('tpbr.deleted_by', 0)
                ->get([
                    'tpbr.parent_id',
                    'tpbr.tour_package_id',
                    'tpbr.package_name',
                    'tp.checkin_type',
                    'tp.bundle_package',
                    'tp.package_has_slots',
                    'tp.package_has_permit',
                    'tp.tax_applicable',
                    'tp.checkin',
                ]);
        } else {
            $packages = $db->table('tour_bookings_master as tbm')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->where('tbm.id', $bookingId)
                ->where('tp.id', $packageId)
                ->get([
                    'tp.id as parent_id',
                    'tp.id as tour_package_id',
                    'tp.name as package_name',
                    'tp.checkin_type',
                    'tp.bundle_package',
                    'tp.package_has_slots',
                    'tp.package_has_permit',
                    'tp.tax_applicable',
                    'tp.checkin',
                ]);
        }

        if ($packages->isEmpty()) {
            throw new Exception("Tour package details not found.");
        }

        return $packages;
    }

    /**
	 * Get the package list of check-ins and redeem tickets for bundle package bookings
	 *
     * @param integer $isBundlePackage
     * @param integer $bookingId
     * @param integer $packageId
     * @param integer $isGroupBooking
	 *
	 * @return array
	 */
    public static function bundlePackageListCheckInsAndTickets($isBundlePackage, $bookingId, $packageId, $isGroupBooking) : array
    {
        $checkInsPackageList = [];
        $ticketsPackageList = [];

        $bookingT = BookingService::totalInformation($bookingId, $isGroupBooking);

        if($isBundlePackage == 1) {
            foreach ($bookingT as $key => $booking) {
                foreach ($booking->bundle_package_checkin_types as $key => $bk) {
                    if($bk['checkin_type'] == 0) {
                        $checkInsPackageList[] = (object) $bk;
                    } else {
                        $ticketsPackageList[] = (object) $bk;
                    }
                }
            }

            $temp = array_unique(array_column($ticketsPackageList, 'tour_package_id'));
            $ticketsPackageList = array_intersect_key($ticketsPackageList, $temp);

            $temp1 = array_unique(array_column($checkInsPackageList, 'tour_package_id'));
            $checkInsPackageList = array_intersect_key($checkInsPackageList, $temp1);
        }

        return [
            'checkins' => $checkInsPackageList,
            'tickets' => $ticketsPackageList
        ];
    }
}
