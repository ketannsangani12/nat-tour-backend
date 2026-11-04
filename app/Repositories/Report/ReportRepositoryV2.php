<?php
namespace App\Repositories\Report;

use Illuminate\Support\Facades\DB;
use App\Repositories\Repository;
use App\Utils\BookingUtils;
// use App\Utils\DateUtils;
use App\Repositories\Report\ReportRepository;

use App\Utils\StrUtils;


// use App\Utils\ReportUtils;
// use Exception;

class ReportRepositoryV2 extends Repository
{

    /**
     * @description Summation Report
     * @param $fromDate
     * @param $toDate,
     * @param $request
     */
   public function revenuneSummationReport($request,   
                                            string $fromDate = "",
                                            string $toDate = "",
                                           ){

        $tourOperatorId = intval($request->tourOperatorId);
        $tourPackageId =  intval($request->tourPackageId);
        $viewBy = $request->viewBy;
        //$_export = 0
        $reportRepo = new ReportRepository();
        $tourOperatorId = (userType() == 'Tour Operator') ? auth()->user()->tour_operator_id : intval($tourOperatorId);
        //print($fromDate. "  ". $toDate. "  ".$tourOperatorId. "  ".$tourPackageId. "  ".$viewBy);                  
        $reportIds = $reportRepo->revenueReport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy, 0, 1);
        
        // Chunk the IDs to avoid "too many placeholders" error (MySQL limit: 65,535)
        // Using 1000 per chunk to be safe (allows for other query parameters)
        $idChunks = array_chunk($reportIds, 1000);
        $collection = [];
        
        foreach ($idChunks as $chunk) {
            $query = DB::table('tour_bookings_transact as tbt')
                        ->distinct()
                        ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                        ->select('tbt.tour_booking_id AS bid', 
                         'tbt.tour_package_rate_id AS pkg_rate_id',
                         'tpr.rate_for AS pkg_rate_name',
                         'tpr.age_from',
                         DB::raw('CASE WHEN tbt.infants > 0 THEN tbt.infants ELSE tbt.tourists END AS pax'),
                         'tbt.processing_charge AS pro_chrg',
                         'tbt.additional_tax_percentage AS tax_%',
                         'tbt.processing_charge_percentage As pro_chrg_%',
                         'tbt.nat_fees_percentage AS plat_fees',
                         'tbt.stripe_fees_percentage AS stripe_fee_%',
                         'tbt.permit_fee',
                         'tbt.additional_charge AS tax',
                         'tbt.is_group_rate',
                         'tbt.state_tax',
                         'tbt.rate',
                         'tbt.total')
                        ->whereIn('tbt.tour_booking_id', $chunk)
                        ->where('tbt.deleted_by', 0);
            
            $chunkResults = $query->get()->toArray();
            $collection = array_merge($collection, $chunkResults);
        }
        $res = BookingUtils::getBookingsIndexById($collection, false);
        return response()->json($res);
    }

}