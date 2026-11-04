<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;

class Today_avialability_detailsController extends Controller
{
	/**
	 * Availability details
	 */
	public function indexAHR(string $fromDate = "",  int $tourPackageId = 0)
	{
		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = substr($fromDate, 0, 4) . '-' . substr($fromDate, 4, 2) . '-' . substr($fromDate, 6, 2);

		$qry = DB::table('tour_bookings_master AS tbm')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tbm.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tbm.tour_package_id', '=', $tourPackageId)
			->where('tsd.deleted_by', '=', '0')
			->where('tsd.date', [$fromDate])
			->select([
				'tbm.name as name', 'c.is_affiliate as is_affiliate ', 'tbm.created_at as bookingdate', 'tsd.created_at as createdate', 'tbm.status as status', 'tp.name as bookingid',  'tbm.amount as amount', 't.status as payment', 'to.logo as touroperatorlogo',
			]);

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('createdate', function ($createdate) {
				return substr($createdate->createdate, 0, 10);
			})
			->make(true);
	}

	/**
	 * Today availability list of the slots
	 */
	public function todayavialabilityList(string $fromDate = "", int $tourPackageId = 0)
	{
		try {
			if ($fromDate == "")
				$fromDate = date('Y-m-d');

			$qry = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
				->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
				->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
				->join('customers as c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
				->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tsd.date', [$fromDate])
				->where('tsd.deleted_by', '=', '0')
				->where('tbm.status', 'Booked')
				->where('t.status', 'Completed')
				->select([
					'tbm.id as bookingid', 'c.is_affiliate as is_affiliate', 'tbm.name as name', 'tbm.phone_number as phone_number', 'tsd.date as bookingdate',  DB::raw('DATE_FORMAT(tsd.created_at, "%Y-%m-%d") as createdate'), 'tsd.created_at as createdate', 'tbm.status as status', 'tbm.total as amount', DB::raw('IFNULL(t.status,"Pending") as payment'), 'to.logo as touroperatorlogo', 'tst.time_slot_seats as seats', 'tst.time_from as timefrom', 'tst.time_to as timeto', 'tbt.tourists as tourists', 'tbt.check_in_status as check_in_status', 'tbm.liability_waiver_signing as liability_waiver_signing', 'tpr.rate_for', 'tpr.age_from', 'tpr.age_to',
				]);

			if ($tourPackageId && $tourPackageId !== 0) {
				$qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
			}

			$qry = $qry->get();
			$result = json_decode($qry, true);

			$initialArray = array();
			foreach ($result as $resultValue) {
				if (!isset($initialArray[$resultValue['bookingid']])) {
					$notCheckIn = 0;
					$firstCheckIn = 0;
					$secondCheckIn = 0;
					$absent = 0;
					$remaining = 0;
					$checkInStatus = json_decode($resultValue['check_in_status'], true);
					if ($checkInStatus !== null) {
						foreach ($checkInStatus as $checkInStatusValue) {
							if ($checkInStatusValue['status'] == "N") {
								$notCheckIn += 1;
							} elseif ($checkInStatusValue['status'] == "1") {
								$firstCheckIn += 1;
							} elseif ($checkInStatusValue['status'] == "2") {
								$secondCheckIn += 1;
							} elseif ($checkInStatusValue['status'] == "A") {
								$absent += 1;
							}
						}
					}

					$calculate = $notCheckIn + $firstCheckIn + $secondCheckIn + $absent;
					$remaining = $resultValue['tourists'] - $calculate;

					$infant = 0;
					$Children = 0;
					$Adult = 0;
					$Teenager = 0;
					$SeniorCitizen = 0;
					if (Str::contains($resultValue['rate_for'], "Infant")) {
						$infant += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "Children")) {
						$Children += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "Adult")) {
						$Adult += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "Teenager")) {
						$Teenager += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "SeniorCitizen")) {
						$SeniorCitizen += $resultValue['tourists'];
					}

					$touristCountArray = json_decode($resultValue['liability_waiver_signing'], true);
					$touristCount = 0;
					if ($touristCountArray !== null) {
						foreach ($touristCountArray as $touristCountArrayValue) {
							if ($touristCountArrayValue[0] !== "") {
								$touristCount += 1;
							}
						}
					}

					$resultValue['infant'] = $infant;
					$resultValue['Children'] = $Children;
					$resultValue['Adult'] = $Adult;
					$resultValue['Teenager'] = $Teenager;
					$resultValue['SeniorCitizen'] = $SeniorCitizen;

					$resultValue['remainingCheckin'] = $remaining;
					$resultValue['notCheckIn'] = $notCheckIn;
					$resultValue['firstCheckIn'] = $firstCheckIn;
					$resultValue['secondCheckIn'] = $secondCheckIn;
					$resultValue['absent'] = $absent;
					$resultValue['liability_waiver_signing'] = $touristCount;

					$initialArray[$resultValue['bookingid']][] = $resultValue;
				} else {
					$previousData = $initialArray[$resultValue['bookingid']];

					$notCheckIn = 0;
					$firstCheckIn = 0;
					$secondCheckIn = 0;
					$absent = 0;
					$remaining = 0;

					$checkInStatus = json_decode($resultValue['check_in_status'], true);
					if ($checkInStatus !== null) {
						foreach ($checkInStatus as $checkInStatusValue) {
							if ($checkInStatusValue['status'] == "N") {
								$notCheckIn += 1;
							} elseif ($checkInStatusValue['status'] == "1") {
								$firstCheckIn += 1;
							} elseif ($checkInStatusValue['status'] == "2") {
								$secondCheckIn += 1;
							} elseif ($checkInStatusValue['status'] == "A") {
								$absent += 1;
							}
						}
					}

					$calculate = $notCheckIn + $firstCheckIn + $secondCheckIn + $absent;
					$remaining = $resultValue['tourists'] - $calculate;

					$infant = 0;
					$Children = 0;
					$Adult = 0;
					$Teenager = 0;
					$SeniorCitizen = 0;
					if (Str::contains($resultValue['rate_for'], "Infant")) {
						$infant += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "Children")) {
						$Children += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "Adult")) {
						$Adult += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "Teenager")) {
						$Teenager += $resultValue['tourists'];
					} elseif (Str::contains($resultValue['rate_for'], "SeniorCitizen")) {
						$SeniorCitizen += $resultValue['tourists'];
					}

					$touristCountArray = json_decode($resultValue['liability_waiver_signing'], true);
					$touristCount = 0;
					if ($touristCountArray !== null) {
						foreach ($touristCountArray as $touristCountArrayValue) {
							if ($touristCountArrayValue[0] !== "") {
								$touristCount += 1;
							}
						}
					}

					$resultValue['infant'] = $infant + (isset($previousData[0]) ? $previousData[0]['infant'] : 0);
					$resultValue['Children'] = $Children + (isset($previousData[0]) ? $previousData[0]['Children'] : 0);
					$resultValue['Adult'] = $Adult + (isset($previousData[0]) ? $previousData[0]['Adult'] : 0);
					$resultValue['Teenager'] = $Teenager + (isset($previousData[0]) ? $previousData[0]['Teenager'] : 0);
					$resultValue['SeniorCitizen'] = $SeniorCitizen + (isset($previousData[0]) ? $previousData[0]['SeniorCitizen'] : 0);
					$resultValue['remainingCheckin'] = $remaining + (isset($previousData[0]) ? $previousData[0]['remainingCheckin'] : 0);
					$resultValue['notCheckIn'] = $notCheckIn + (isset($previousData[0]) ? $previousData[0]['notCheckIn'] : 0);
					$resultValue['firstCheckIn'] = $firstCheckIn + (isset($previousData[0]) ? $previousData[0]['firstCheckIn'] : 0);
					$resultValue['secondCheckIn'] = $secondCheckIn + (isset($previousData[0]) ? $previousData[0]['secondCheckIn'] : 0);
					$resultValue['absent'] = $absent + (isset($previousData[0]) ? $previousData[0]['absent'] : 0);
					$resultValue['liability_waiver_signing'] = $touristCount;

					$initialArray[$resultValue['bookingid']][0] = $resultValue;
				}
			}

			$sortInitialValueArray = array();
			foreach ($initialArray as $key => $element) {
				$sortInitialValueArray[] = $element[0];
			}

			$sortedData = array();
			foreach ($sortInitialValueArray as $element) {
				$timestamp = strtotime($element['bookingdate']);
				$date =  date("m/d/Y", $timestamp);
				if (!isset($sortedData[$date])) { //first entry of that day
					$sortedData[$date] = array($element);
				} else { //just push current element onto existing array
					$sortedData[$date][] = $element;
				}
			}

			$mediumSortedData = array();
			foreach ($sortedData as $key => $elementValue) {
				foreach ($elementValue as $element) {
					$formatTimeFromRaw = $element['timefrom'];
					$formatedTimeFrom = date('g:i a', strtotime($formatTimeFromRaw));
					$formatTimeToRaw = $element['timeto'];
					$formatedTimeTo = date('g:i a', strtotime($formatTimeToRaw));

					$newArrayKey = $formatedTimeFrom . " - " . $formatedTimeTo;
					if (!isset($mediumSortedData[$key][$newArrayKey])) {
						$mediumSortedData[$key][$newArrayKey] = array($element);
					} else {
						$mediumSortedData[$key][$newArrayKey][] = $element;
					}
				}
			}

			return $mediumSortedData;
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}
}
