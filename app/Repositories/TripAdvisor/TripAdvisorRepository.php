<?php

namespace App\Repositories\TripAdvisor;

use App\Repositories\Repository;
use Exception;
use GuzzleHttp\Client;
use App\Models\TourPackage;
use App\Http\Controllers\API\V1\ApiController;
use App\Constants\Constants;
use App\Models\TourPackageImage;
use App\Models\TourOperator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TourPackageRate;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\BookingTransact;
use App\Services\TripAdvisorBookingService;
use App\Models\Transaction;
use App\Services\TripAdvisorCancelBookingService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\CustomerController;
use App\Models\SurchargeDate;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;

class TripAdvisorRepository extends Repository
{
	public $tourPackage = TourPackage::class;

	public $apiController = ApiController::class;

	public $tourPackageImage = TourPackageImage::class;

	public $tourOperator = TourOperator::class;

	public $tourPackageRate = TourPackageRate::class;

	public $customer = Customer::class;

	public $tourBooking = TourBooking::class;

	public $bookingTransact = BookingTransact::class;

	public $transaction = Transaction::class;

	public $customerController = CustomerController::class;

	/**
	 * Provide a list of available tours for Supplier
	 * @param mixed $request
	 * @return mixed
	 */
	public function tourListRequest($request)
	{
		try {
			$tourList = [];
			$tourList = [
				'responseType' => "TourListResponse",
				'data' => [
					'ApiKey' => $request['data']['ApiKey'],
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'Timestamp' => Carbon::now('UTC')
				]
			];

			$operator = $this->tourOperator::where('tripadvisor_supplier_id', $request['data']['SupplierId'])->where('tripadvisor_reseller_id', $request['data']['ResellerId'])->first();
			if (!$operator) {
				$tourList['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Incorrect SupplierId or ResellerId.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Tour List API Error Response ========== " . json_encode($tourList));
				return $this->apiController::response(null, $tourList);
			}

			$packages = $this->tourPackage::where('tour_operator_id', $operator->id)
				->where('productCode', '!=', '')
				->where('status', 'Active')
				->get();
			$status = (count($packages) > 0) ? "SUCCESS" : "ERROR";

			if ($status == "ERROR") {
				$tourList['data']['RequestStatus'] = [
					'Status' => $status,
					'Error' => [
						'ErrorCode' => 404,
						'ErrorMessage' => 'No any Tour Package Found. Please check again later.'
					]
				];

				// Log::info("TripAdvisor Tour List API Error Response ========== " . json_encode($tourList));
			} else {
				$tourList['data']['RequestStatus']['Status'] = $status;
			}

			// Optional fields integration
			foreach ($packages as $key => $value) {
				$tourList['data']['Tour'][] = [
					'SupplierProductCode' => $value->productCode,
					'SupplierProductName' => $value->name,
					'CountryCode' => 'US',
					'DestinationCode' => 'PGA',
					'DestinationName' => $operator->address,
					'TourDescription' => $value->short_description //$value->long_description
				];
			}

			return $this->apiController::response(null, $tourList);
		} catch (Exception $exception) {
			Log::error("TripAdvisor Tour List API Exception Error ========= " . $exception->getMessage());

			$tourList = [
				"responseType" => "TourListResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $tourList);
		}
	}

	/**
	 * Provide a list of available/unavailable timeslots with date/daterange for Supplier
	 * @param mixed $request
	 * @return mixed
	 */
	public function batchAvailabilityRequest($request)
	{
		try {
			$batchAvailability = [];
			$i = 0;
			$batchAvailability = [
				'responseType' => "BatchAvailabilityResponse",
				'data' => [
					'ApiKey' => $request['data']['ApiKey'],
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'Timestamp' => Carbon::now('UTC')
				]
			];

			$operator = $this->tourOperator::where('tripadvisor_supplier_id', $request['data']['SupplierId'])->where('tripadvisor_reseller_id', $request['data']['ResellerId'])->first();
			if (!$operator) {
				$batchAvailability['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Incorrect SupplierId or ResellerId.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Batch Availability API Error Response ========== " . json_encode($batchAvailability));
				return $this->apiController::response(null, $batchAvailability);
			}

			$packages = DB::select("SELECT `t_o`.`id` as tourOperatorId, `tp`.`id` as tourPackageId, `tp`.`productCode`, `c`.`id` as affiliateId, `c`.`name` as affiliate_name, `tp`.`name` as package_name, `c`.`status`
				FROM `tour_packages` as tp
				INNER JOIN `tour_operators` as t_o ON `t_o`.`id` = `tp`.`tour_operator_id`
				INNER JOIN customers as c ON `c`.`tour_operator_id` = `t_o`.`id`
				WHERE `tp`.`tour_operator_id` = " . $operator->id . "
				AND `c`.`name` = 'TripAdvisor'
				AND `c`.`is_affiliate` = 'Yes'
				AND `c`.`deleted_by` = 0
				AND `tp`.`productCode` != ''
				AND `tp`.`status` = 'Active'
				AND `tp`.`deleted_by` = 0
			");

			$status = (count($packages) > 0) ? "SUCCESS" : "ERROR";
			if ($status == "ERROR") {
				$batchAvailability['data']['RequestStatus'] = [
					"Status" => $status,
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Either rate groups(ageband) for TripAdvisor is missing or data not found.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Batch Availability API Error Response ========== " . json_encode($batchAvailability));
				return $this->apiController::response(null, $batchAvailability);
			} else {
				$batchAvailability['data']['RequestStatus']['Status'] = $status;
			}

			$count = 0;
			foreach ($packages as $key => $value) {
				$count++;
				if ($value->status != 'Active') {
					$batchAvailability['data']['RequestStatus'] = [
						'Status' => "ERROR",
						'Error' => [
							"ErrorCode" => "TGDS0028",
							"ErrorMessage" => "TripAdvisor affiliate isn't integrated yet. Please add it and try again.",
							"ErrorDetails" => "Data not available."
						]
					];

					// Log::info("TripAdvisor Batch Availability API Error Response ========== " . json_encode($batchAvailability));
					if ($count == 1) {
						break;
					}
				}

				if ($request['data']['StartDate'] < Carbon::now()->format('Y-m-d')) {
					$batchAvailability['data']['BatchTourAvailability'][$key] = [
						'Date' => $request['data']['StartDate'],
						'SupplierProductCode' => $value->productCode,
						'AvailabilityStatus' => [
							'Status' => 'UNAVAILABLE',
							'UnavailabilityReason' => 'INACTIVE'
						]
					];
				}

				// Single date / multiple products response
				if (!isset($request['data']['EndDate']) || empty($request['data']['EndDate'])) {
					$getTimeSlotsByDate = $this->getTimeSlotsByDate($value->tourOperatorId, $value->tourPackageId, $value->affiliateId, $request['data']['StartDate'], 0, null);
					if (empty($getTimeSlotsByDate)) {
						$batchAvailability['data']['BatchTourAvailability'][] = [
							'Date' => $request['data']['StartDate'],
							'SupplierProductCode' => $value->productCode,
							'TourOptions' => [
								'SupplierOptionName' => $value->package_name
							],
							'AvailabilityStatus' => [
								'Status' => 'UNAVAILABLE',
								'UnavailabilityReason' => 'INACTIVE'
							]
						];
					} else {
						foreach ($getTimeSlotsByDate as $keys => $slot) {
							$currentTime = ($slot->timezone != '') ? Carbon::now($slot->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
							$currentTime = Carbon::parse($currentTime);
							$dateTime = Carbon::parse($slot->date . ' ' . $slot->time_from);

							$slot->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

							$batchAvailability['data']['BatchTourAvailability'][$i] = [
								'Date' => $request['data']['StartDate'],
								'SupplierProductCode' => $value->productCode,
								'TourOptions' => [
									'SupplierOptionName' => $value->package_name,
									'TourDepartureTime' => $slot->time_from
								]
							];

							if ($slot->slotStatus == 'Closed') {
								$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['Status'] = 'UNAVAILABLE';
								$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['UnavailabilityReason'] = 'INACTIVE';
							} else {
								if ($slot->seats <= 0) {
									$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['Status'] = 'UNAVAILABLE';
									$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['UnavailabilityReason'] = 'SOLD_OUT';
								} else {
									$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['Status'] = 'AVAILABLE';
								}
							}

							$i++;
						}
					}
				} else {
					// Date range / single product response
					$getTimeSlotsByDate = $this->getTimeSlotsByDate($value->tourOperatorId, $value->tourPackageId, $value->affiliateId, $request['data']['StartDate'], 1, $request['data']['EndDate']);

					if (empty($getTimeSlotsByDate)) {
						$dates = $this->getDatesBetweenRange($request['data']['StartDate'], $request['data']['EndDate']);
						foreach ($dates as $date) {
							$batchAvailability['data']['BatchTourAvailability'][] = [
								'Date' => $date,
								'SupplierProductCode' => $value->productCode,
								'TourOptions' => [
									'SupplierOptionName' => $value->package_name
								],
								'AvailabilityStatus' => [
									'Status' => 'UNAVAILABLE',
									'UnavailabilityReason' => 'INACTIVE'
								]
							];
						}
					} else {
						foreach ($getTimeSlotsByDate as $keyk => $slot) {
							$currentTime = ($slot->timezone != '') ? Carbon::now($slot->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
							$currentTime = Carbon::parse($currentTime);
							$dateTime = Carbon::parse($slot->date . ' ' . $slot->time_from);

							$slot->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

							$batchAvailability['data']['BatchTourAvailability'][$i] = [
								'Date' => $slot->date,
								'SupplierProductCode' => $value->productCode,
								'TourOptions' => [
									'SupplierOptionName' => $value->package_name,
									'TourDepartureTime' => $slot->time_from
								]
							];

							if ($slot->slotStatus == 'Closed') {
								$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['Status'] = 'UNAVAILABLE';
								$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['UnavailabilityReason'] = 'INACTIVE';
							} else {
								if ($slot->seats <= 0) {
									$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['Status'] = 'UNAVAILABLE';
									$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['UnavailabilityReason'] = 'SOLD_OUT';
								} else {
									$batchAvailability['data']['BatchTourAvailability'][$i]['AvailabilityStatus']['Status'] = 'AVAILABLE';
								}
							}

							$i++;
						}
					}
				}
			}

			return $this->apiController::response(null, $batchAvailability);
		} catch (Exception $exception) {
			Log::error("TripAdvisor Batch Availability API Exception Error ========= " . $exception->getMessage());

			$batchAvailability = [
				"responseType" => "BatchAvailabilityResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $batchAvailability);
		}
	}

	/**
	 * Summary of getTimeSlotsByDate
	 * @param mixed $tourOperatorId
	 * @param mixed $tourPackageId
	 * @param mixed $affiliateId
	 * @param mixed $startDate
	 * @param mixed $dateRange
	 * @param mixed $endDate
	 * @return array
	 */
	public function getTimeSlotsByDate($tourOperatorId, $tourPackageId, $affiliateId, $startDate, $dateRange, $endDate)
	{
		$timeSlots = [];
		$slotTimes = "SELECT `tp`.`id` as `package_id`, `tst`.`id`, `tst`.`time_from`, `tst`.`time_to`, `tst`.`time_slot_seats` AS `seats`, `tst`.`bookable_status`, `tst`.`date`, `t_op`.`timezone`,
            IFNULL((SELECT SUM(`tbm`.`tourists`)
                FROM `tour_bookings_master` AS `tbm`
                WHERE `tbm`.`tour_slot_id` = `tst`.`id`
                    AND `tbm`.`status` != 'Cancelled'
                	AND `tbm`.`tour_package_id` = " . $tourPackageId . "
                GROUP BY `tbm`.`tour_slot_id`, `tbm`.`tour_package_id`), 0) AS tourists,
            IFNULL((SELECT SUM(`tbm2`.`tourists`)
				FROM `tour_bookings_master` AS `tbm2`
				WHERE `tbm2`.`tour_slot_id` = `tst`.`id`
					AND `tbm2`.`status` != 'Cancelled'
				GROUP BY `tbm2`.`tour_slot_id`), 0) AS booked_seats,
            IFNULL((SELECT SUM(`tbm`.`tourists`)
                FROM `tour_bookings_master` AS tbm
                JOIN `customers` AS `c` ON `c`.`id` = `tbm`.`customer_id`
                WHERE `tbm`.`tour_slot_id` = `tst`.`id`
                    AND `c`.`is_affiliate` = 'Yes'
                    AND `tbm`.`status` != 'Cancelled'
                    AND `tbm`.`tour_package_id` = " . $tourPackageId . "
                GROUP BY `tbm`.`tour_slot_id`, `tbm`.`tour_package_id`), 0) AS afl_tourists
            FROM `tour_slot_times` AS `tst`
            INNER JOIN `tour_packages` AS tp ON `tp`.`id` = `tst`.`tour_package_id`
            INNER JOIN `tour_operators` AS t_op ON `t_op`.`id` = `tp`.`tour_operator_id`";

		if ($dateRange == 1) {
			$slotTimes .= " WHERE (`tst`.`date` BETWEEN '" . $startDate . "' AND '" . $endDate . "')";
		} else {
			$slotTimes .= " WHERE (`tst`.`date` BETWEEN '" . $startDate . "' AND '" . $startDate . "')";
		}

		$slotTimes .= " AND `tst`.`tour_package_id` = " . $tourPackageId . "
            AND `tp`.`tour_operator_id` = " . $tourOperatorId . "
            AND `tst`.`hide_slot` = 0
			AND `tst`.`deleted_by` = 0
			AND `tst`.`bookable_status` != 'Closed'
			ORDER BY `tp`.`id` ASC";

		$slotTimes = DB::select($slotTimes);

		$affiliateTimeSlots = $this->affiliateTimeSlots($affiliateId, $tourPackageId);

		foreach ($slotTimes as $key => $slot) {
			$timeSlots[] = $this->limitedSeatsCalculation($slot, $affiliateTimeSlots);
		}

		return $timeSlots;
	}

	/**
	 * Summary of affiliateTimeSlots
	 * @param mixed $affiliateId
	 * @param mixed $tourPackageId
	 * @return array
	 */
	public function affiliateTimeSlots($affiliateId, $tourPackageId)
	{
		$affiliateTimeSlots = DB::table('tour_slot_times_affiliate as tsta')
			->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
			->where('ast.affiliate_id', $affiliateId)
			->where('tsta.tour_package_id', $tourPackageId)
			->where('tsta.status', 'Active')
			->where('tsta.deleted_by', 0)
			->where('ast.seat_limit', '>', 0)
			->select('tsta.id as time_slot_id', 'tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
			->get()
			->toArray();

		return $affiliateTimeSlots;
	}

	/**
	 * Summary of limitedSeatsCalculation
	 * @param mixed $slot
	 * @param mixed $affiliateTimeSlots
	 * @return mixed
	 */
	public function limitedSeatsCalculation($slot, $affiliateTimeSlots)
	{
		$tourists = (int)$slot->booked_seats;
		$seats = $slot->seats;
		$availableSeats = $seats - $tourists;
		$remainingSeats = ($tourists > $seats) ? 0 : $availableSeats;

		$checkSlotFromTimeExists = array_search($slot->time_from, array_column($affiliateTimeSlots, 'time_from'));
		$checkSlotTotimeExists = array_search($slot->time_to, array_column($affiliateTimeSlots, 'time_to'));
		if ($checkSlotFromTimeExists !== false && $checkSlotTotimeExists !== false) {
			if (isset($affiliateTimeSlots[$checkSlotFromTimeExists]) && isset($affiliateTimeSlots[$checkSlotTotimeExists])) {
				$slot->seats = $affiliateTimeSlots[$checkSlotFromTimeExists]->seat_limit;

				if ($remainingSeats <= 0) {
					$slot->seats = 0;
				} else {
					if ($remainingSeats < $affiliateTimeSlots[$checkSlotFromTimeExists]->seat_limit) {
						if ($tourists == 0) {
							$slot->seats = $remainingSeats;
						} else {
							$aflTourists = (int)$slot->afl_tourists;
							$aflSeats = $affiliateTimeSlots[$checkSlotFromTimeExists]->seat_limit;

							$slot->seats = ($aflTourists == $aflSeats) ? 0 : $remainingSeats;
						}
					} elseif ($tourists == $affiliateTimeSlots[$checkSlotFromTimeExists]->seat_limit) {
						$slot->seats = 0;
					} else {
						$slot->seats = $affiliateTimeSlots[$checkSlotFromTimeExists]->seat_limit - $slot->afl_tourists;
					}
				}
			} else {
				$slot->seats = 0;
			}
		} else {
			$slot->seats = 0;
		}

		return $slot;
	}

	/**
	 * Summary of getDatesBetweenRange
	 * @param mixed $startDate
	 * @param mixed $endDate
	 * @return array
	 */
	public function getDatesBetweenRange($startDate, $endDate)
	{
		$rangArray = [];
		$startDate = strtotime($startDate);
		$endDate = strtotime($endDate);

		for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {
			$date = date('Y-m-d', $currentDate);
			$rangArray[] = $date;
		}

		return $rangArray;
	}

	/**
	 * Check real-time availability of seats as per date/timeslots
	 * @param mixed $request
	 * @return mixed
	 */
	public function realTimeAvailabilityRequest($request)
	{
		try {
			$realTimeAvailability = [];
			$index = 0;
			$realTimeAvailability = [
				'responseType' => "AvailabilityResponse",
				'data' => [
					'ApiKey' => $request['data']['ApiKey'],
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'Timestamp' => Carbon::now('UTC')
				]
			];

			$package = collect(DB::select("SELECT `t_o`.`id` as tourOperatorId, `tp`.`id` as tourPackageId, `tp`.`productCode`, `c`.`id` as affiliateId, `c`.`name` as affiliate_name, `tp`.`name` as package_name, `c`.`status`
				FROM `tour_packages` as tp
				INNER JOIN `tour_operators` as t_o ON `t_o`.`id` = `tp`.`tour_operator_id`
				INNER JOIN customers as c ON `c`.`tour_operator_id` = `t_o`.`id`
				WHERE `tp`.`productCode` = '" . $request['data']['SupplierProductCode'] . "'
				AND `c`.`name` = 'TripAdvisor'
				AND `c`.`is_affiliate` = 'Yes'
				AND `c`.`deleted_by` = 0
				AND `tp`.`deleted_by` = 0
				AND `tp`.`status` = 'Active'
				AND `c`.`status` = 'Active'
			"))->first();

			if (!$package) {
				$realTimeAvailability['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Either rate groups(ageband) for TripAdvisor is missing/inactive OR Package not active OR SupplierProductCode not found.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Real-time Availability API Error Response ========== " . json_encode($realTimeAvailability));
				return $this->apiController::response(null, $realTimeAvailability);
			}

			$realTimeAvailability['data']['RequestStatus']['Status'] = "SUCCESS";
			$realTimeAvailability['data']['SupplierProductCode'] = $request['data']['SupplierProductCode'];

			// Need to integrate response with remaining seats for all timeslots
			// Code to check request pax with availability
			$isAvailable = 0;
			$remainingSeats = 0;
			$touristsTotal = 0;
			if (isset($request['data']['TourOptions'])) {
				if (isset($request['data']['TourOptions']['TourDepartureTime']) && !empty($request['data']['TourOptions']['TourDepartureTime'])) {
					if (!isset($request['data']['EndDate']) || empty($request['data']['EndDate'])) {
						$realTimeAvailability['data']['TourAvailability'][$index] = [
							'Date' => $request['data']['StartDate'],
							'BookingCutoff' => [
								'NotApplicable' => 'true'
							]
						];

						$checkBooking = $this->checkSelectedTimeSlotBooking($request['data']['TourOptions']['TourDepartureTime'], $package->tourPackageId, $request['data']['StartDate'], $package->affiliateId);
						if ($checkBooking) {
							$currentTime = ($checkBooking->timezone != '') ? Carbon::now($checkBooking->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
							$currentTime = Carbon::parse($currentTime);
							$dateTime = Carbon::parse($checkBooking->date . ' ' . $checkBooking->time_from);
							$checkBooking->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

							$realTimeAvailability['data']['TourAvailability'][$index]['TourOptions'] = [
								'SupplierOptionName' => $package->package_name,
								'TourDepartureTime' => $checkBooking->time_from
							];

							if ($checkBooking->slotStatus == 'Closed') {
								$remainingSeats = 0;
								$realTimeAvailability['data']['TourAvailability'][$index]['AvailabilityStatus'] = [
									'Status' => 'UNAVAILABLE',
									'UnavailabilityReason' => 'INACTIVE'
								];
							} else {
								if (isset($request['data']['TravellerMix'])) {
									// Check requested total seats are compatiable or less or equal than actual remaining seats
									if (isset($request['data']['TravellerMix']['Total'])) {
										$totalPax = $request['data']['TravellerMix'];
										unset($totalPax['Total']);
										$touristsTotal = array_sum($totalPax);
									} else {
										foreach ($request['data']['TravellerMix'] as $key => $value) {
											$touristsTotal += $value;
										}
									}

									$remainingSeats = $checkBooking->seats;
									if ($touristsTotal > $remainingSeats) {
										$realTimeAvailability['data']['TourAvailability'][$index]['AvailabilityStatus'] = [
											'Status' => 'UNAVAILABLE',
											'UnavailabilityReason' => 'TRAVELLER_MISMATCH'
										];
									} else {
										$isAvailable = 1;
										$realTimeAvailability['data']['TourAvailability'][$index]['AvailabilityStatus'] = [
											'Status' => ($isAvailable == 1) ? 'AVAILABLE' : 'UNAVAILABLE'
										];
									}
								} else {
									$remainingSeats = $checkBooking->seats;
									if ($remainingSeats > 0) {
										$isAvailable = 1;
									}
									$realTimeAvailability['data']['TourAvailability'][$index]['AvailabilityStatus'] = [
										'Status' => ($isAvailable == 1) ? 'AVAILABLE' : 'UNAVAILABLE'
									];
								}
							}
						} else {
							$realTimeAvailability['data']['TourAvailability'][$index] = [
								'Date' => $request['data']['StartDate'],
								'BookingCutoff' => [
									'NotApplicable' => 'true'
								],
								'TourOptions' => [
									'SupplierOptionName' => $package->package_name,
									'TourDepartureTime' => $request['data']['TourOptions']['TourDepartureTime']
								],
								'AvailabilityStatus' => [
									'Status' => 'UNAVAILABLE',
									'UnavailabilityReason' => 'INACTIVE'
								]
							];
						}

						$realTimeAvailability['data']['TourAvailability'][$index]['Capacity'] = [
							"Simple" => [
								"Remaining" => $remainingSeats
							]
						];
					}
				} else {
					$realTimeAvailability = $this->realTimeAvailabilityDateOnlyResponse($package, $request, $index, $remainingSeats, $isAvailable, $realTimeAvailability, $touristsTotal);
				}
			} else {
				if (!isset($request['data']['EndDate']) || empty($request['data']['EndDate'])) {
					$realTimeAvailability = $this->realTimeAvailabilityDateOnlyResponse($package, $request, $index, $remainingSeats, $isAvailable, $realTimeAvailability, $touristsTotal);
				}
			}

			return $this->apiController::response(null, $realTimeAvailability);
		} catch (Exception $exception) {
			Log::error("TripAdvisor Real-time Availability API Exception Error ========= " . $exception->getMessage());

			$realTimeAvailability = [
				"responseType" => "AvailabilityResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $realTimeAvailability);
		}
	}

	/**
	 * Summary of realTimeAvailabilityDateOnlyResponse
	 * @param mixed $package
	 * @param mixed $request
	 * @param mixed $index
	 * @param mixed $remainingSeats
	 * @param mixed $isAvailable
	 * @param mixed $realTimeAvailability
	 * @param mixed $touristsTotal
	 * @return mixed
	 */
	public function realTimeAvailabilityDateOnlyResponse($package, $request, $index, $remainingSeats, $isAvailable, $realTimeAvailability, $touristsTotal)
	{
		$timeSlots = $this->getTimeSlotsByDate($package->tourOperatorId, $package->tourPackageId, $package->affiliateId, $request['data']['StartDate'], 0, null);
		if ($timeSlots) {
			foreach ($timeSlots as $key => $timeSlot) {
				$currentTime = ($timeSlot->timezone != '') ? Carbon::now($timeSlot->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
				$currentTime = Carbon::parse($currentTime);
				$dateTime = Carbon::parse($timeSlot->date . ' ' . $timeSlot->time_from);

				$timeSlot->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

				$realTimeAvailability['data']['TourAvailability'][$key] = [
					'Date' => $request['data']['StartDate'],
					'BookingCutoff' => [
						'NotApplicable' => 'true'
					]
				];

				$realTimeAvailability['data']['TourAvailability'][$key]['TourOptions'] = [
					'SupplierOptionName' => $package->package_name,
					'TourDepartureTime' => $timeSlot->time_from
				];

				if ($timeSlot->slotStatus == 'Closed') {
					$remainingSeats = 0;
					$realTimeAvailability['data']['TourAvailability'][$key]['AvailabilityStatus'] = [
						'Status' => 'UNAVAILABLE',
						'UnavailabilityReason' => 'INACTIVE'
					];
				} else {
					// If request of TravellerMix exists
					if (isset($request['data']['TravellerMix'])) {
						// Check requested total seats are compatiable or less or equal than actual remaining seats
						if (isset($request['data']['TravellerMix']['Total'])) {
							$totalPax = $request['data']['TravellerMix'];
							unset($totalPax['Total']);
							$touristsTotal = array_sum($totalPax);
						} else {
							foreach ($request['data']['TravellerMix'] as $key => $value) {
								$touristsTotal += $value;
							}
						}

						$remainingSeats = $timeSlot->seats;
						if ($touristsTotal > $remainingSeats) {
							$realTimeAvailability['data']['TourAvailability'][$key]['AvailabilityStatus'] = [
								'Status' => 'UNAVAILABLE',
								'UnavailabilityReason' => 'TRAVELLER_MISMATCH'
							];
						} else {
							$isAvailable = 1;
							$realTimeAvailability['data']['TourAvailability'][$key]['AvailabilityStatus'] = [
								'Status' => ($isAvailable == 1) ? 'AVAILABLE' : 'UNAVAILABLE'
							];
						}
					} else {
						$remainingSeats = $timeSlot->seats;
						$isAvailable = 1;
						$realTimeAvailability['data']['TourAvailability'][$key]['AvailabilityStatus'] = [
							'Status' => ($isAvailable == 1) ? 'AVAILABLE' : 'UNAVAILABLE'
						];
					}
				}

				$realTimeAvailability['data']['TourAvailability'][$key]['Capacity'] = [
					"Simple" => [
						"Remaining" => $remainingSeats
					]
				];
			}
		} else {
			$realTimeAvailability['data']['TourAvailability'][$index] = [
				'Date' => $request['data']['StartDate'],
				'BookingCutoff' => [
					'NotApplicable' => 'true'
				],
				'AvailabilityStatus' => [
					'Status' => 'UNAVAILABLE',
					'UnavailabilityReason' => 'INACTIVE'
				],
				"Capacity" => [
					"Simple" => [
						"Remaining" => $remainingSeats
					]
				]
			];
		}

		return $realTimeAvailability;
	}

	/**
	 * Check the overbooking for the specific slot user trying to book
	 * @param mixed $slotTime
	 * @param mixed $tourPackageId
	 * @param mixed $startDate
	 * @param mixed $affiliateId
	 * @return mixed
	 */
	public function checkSelectedTimeSlotBooking($slotTime, $tourPackageId, $startDate, $affiliateId)
	{
		$timeSlot = collect(DB::select("
			SELECT `tst`.`time_from`,`tst`.`time_to`,`tst`.`time_slot_seats` AS `seats`,`tp`.`overbook_seats`,`t_op`.`timezone`,`tst`.`date`,
			IFNULL((SELECT SUM(`tbm`.`tourists`)
                FROM tour_bookings_master AS tbm
                WHERE `tbm`.`tour_slot_id` = `tst`.`id`
                    AND `tbm`.`status` != 'Cancelled'
                GROUP BY `tbm`.`tour_slot_id`),0) AS booked_seats,
            IFNULL((SELECT SUM(`tbm`.`tourists`)
                FROM `tour_bookings_master` AS tbm
                JOIN customers AS c ON `c`.`id` = `tbm`.`customer_id`
                JOIN tour_booking_history AS tbh ON `tbh`.`tour_booking_id` = `tbm`.`id`
                WHERE `tbm`.`tour_slot_id` = `tst`.`id`
                    AND `tbm`.`status` != 'Cancelled'
                    AND `tbm`.`tour_package_id` = " . $tourPackageId . "
                    AND `tbh`.`payment_type` = 'TripAdvisor'
                GROUP BY `tbm`.`tour_slot_id`, `tbm`.`tour_package_id`), 0) AS afl_tourists
            FROM tour_slot_times AS tst
            INNER JOIN tour_packages AS tp ON `tp`.`id` = `tst`.`tour_package_id`
            INNER JOIN tour_operators AS t_op ON `t_op`.`id` = `tp`.`tour_operator_id`
            WHERE `tst`.`date` = '" . $startDate . "'
			AND `tst`.`time_from` = '" . $slotTime . "'
			AND `tst`.`bookable_status` != 'Closed'
			AND `tp`.`id` = " . $tourPackageId . "
		"))->first();

		$affiliateTimeSlots = $this->affiliateTimeSlots($affiliateId, $tourPackageId);

		if ($timeSlot) {
			$timeSlot = $this->limitedSeatsCalculation($timeSlot, $affiliateTimeSlots);
			return $timeSlot;
		}
	}

	/**
	 * Summary of getRateGroupAvailability
	 * @param mixed $pkgId
	 * @param mixed $isAffiliate
	 * @param mixed $affiliateId
	 * @param mixed $startDate
	 * @param mixed $operator
	 * @return \Illuminate\Support\Collection
	 */
	public function getRateGroupAvailability($pkgId, $isAffiliate, $affiliateId, $startDate, $operator)
	{
		$customRateDate = isCustomDateRateEnabled($startDate, $pkgId, $isAffiliate);
		$isBlackoutDate = isBlackoutDateEnabled($startDate);
		$affiliateIdForRate = ($isAffiliate == 'Yes' && !$isBlackoutDate) ? $affiliateId : null;
		
		$rateGroups = DB::table('tour_package_rates AS tpr')
			->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
			->where([
				'tpr.tour_package_id' => $pkgId,
				'tpr.deleted_by' => 0
			])
			->when($isAffiliate == 'Yes' && !$isBlackoutDate, function ($query) use ($affiliateIdForRate) {
				return $query->where('tpr.affiliate_id', $affiliateIdForRate);
			}, function ($query): mixed {
				return $query->whereNull('tpr.affiliate_id');
			})
			->when($customRateDate, function ($query) use ($customRateDate) {
				return $query->where('tpr.custom_date_id', $customRateDate);
			}, function ($query): mixed {
				return $query->whereNull('tpr.custom_date_id');
			})
			->where('tpr.scheduling_year', date('Y', strtotime($startDate)))
			->orderBy('tpr.orderby', 'asc')
			->select([
				'tpr.id',
				'tpr.rate_for',
				'tpr.rate',
				'tpr.description',
				'tpr.permit_fee',
				'tpr.additional_tax_percentage',
				'tpr.processing_charge_percentage',
				'tpr.additional_charge',
				'tp.service_commission_percentage',
				'tp.stripe_commission_percentage'
			])
			->get();

		return $rateGroups;
	}

	/**
	 * Used to create booking in our system
	 * @param mixed $request
	 * @return mixed
	 */
	public function CreateBookingRequest($request)
	{
		DB::beginTransaction();
		try {
			$booking = [];
			$paxArr = [];
			$touristCount = [];
			$addedPeoplesText = [];
			$totalTourists = 0;
			$serviceCommission = 0;
			$platformFees = 0;
			$bookingTotal = 0;
			$touristsSum = 0;
			$booking = [
				'responseType' => "BookingResponse",
				'data' => [
					'ApiKey' => $request['data']['ApiKey'],
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'Timestamp' => Carbon::now('UTC'),
					'BookingReference' => $request['data']['BookingReference']
				]
			];

			// Get tour package using requested Supplier Product Code
			$package = $this->tourPackage::where('productCode', $request['data']['SupplierProductCode'])
				->where('status', 'Active')
				->first();
			if (!$package) {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Incorrect SupplierProductCode OR Package Not Active.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
				return $this->apiController::response(null, $booking);
			}

			// Get tour operator ID
			$operator = $this->tourOperator::where('tripadvisor_supplier_id', $request['data']['SupplierId'])->where('tripadvisor_reseller_id', $request['data']['ResellerId'])->first();
			if (!$operator) {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Incorrect SupplierId or ResellerId.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
				return $this->apiController::response(null, $booking);
			}

			// Get TripAdvisor Affiliate
			$affiliate = $this->customer::where('tour_operator_id', $operator->id)->where('name', 'TripAdvisor')->where('is_affiliate', 'Yes')->first();
			if (!$affiliate) {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "TripAdvisor affiliate isn't integrated yet. Please add it and try again.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
				return $this->apiController::response(null, $booking);
			}

			$isAvailable = 0;
			$touristsTotal = 0;
			$status = ($isAvailable == 1) ? "SUCCESS" : "ERROR";
			if ($request['data']['TravelDate'] < date('Y-m-d')) {
				$booking['data']['RequestStatus'] = [
					'Status' => $status,
					'Error' => [
						"ErrorCode" => "TGDS0028",
						'ErrorMessage' => "The slot you're going to book has already passed or is not available. Please look for other dates and slots.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $booking);
			}

			$checkBooking = $this->checkSelectedTimeSlotBooking($request['data']['TourOptions']['TourDepartureTime'], $package->id, $request['data']['TravelDate'], $affiliate->id);

			if ($checkBooking) {
				$currentTime = ($checkBooking->timezone != '') ? Carbon::now($checkBooking->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
				$currentTime = Carbon::parse($currentTime);
				$dateTime = Carbon::parse($checkBooking->date . ' ' . $checkBooking->time_from);

				$checkBooking->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

				if ($checkBooking->slotStatus == 'Closed') {
					$isAvailable = 0;
					$booking['data']['RequestStatus'] = [
						'Status' => "ERROR",
						'Error' => [
							"ErrorCode" => "TGDS0028",
							"ErrorMessage" => "The slot you're going to book has already passed or is not available. Please look for other dates and slots.",
							"ErrorDetails" => "Data not available."
						]
					];

					return $this->apiController::response(null, $booking);
				} else {
					// If request of TravellerMix exists
					if (isset($request['data']['TravellerMix'])) {
						// Check requested total seats are compatiable or less or equal than actual remaining seats
						if (isset($request['data']['TravellerMix']['Total'])) {
							$totalPax = $request['data']['TravellerMix'];
							unset($totalPax['Total']);
							$touristsTotal = array_sum($totalPax);
						} else {
							foreach ($request['data']['TravellerMix'] as $key => $value) {
								$touristsTotal += $value;
							}
						}

						$remainingSeats = $checkBooking->seats;
						if ($touristsTotal > $remainingSeats) {
							$isAvailable = 0;
						} else {
							$isAvailable = 1;
						}

						if ($remainingSeats == 0) {
							$booking['data']['RequestStatus'] = [
								'Status' => "ERROR",
								'Error' => [
									"ErrorCode" => "TGDS0028",
									"ErrorMessage" => "No seats are available.",
									"ErrorDetails" => "Data not available."
								]
							];
							return $this->apiController::response(null, $booking);
						} else {
							if ($isAvailable == 0) {
								$booking['data']['RequestStatus'] = [
									'Status' => "ERROR",
									'Error' => [
										"ErrorCode" => "TGDS0028",
										"ErrorMessage" => "You can book up to " . $remainingSeats . " tourists because only " . $remainingSeats . " seats are available.",
										"ErrorDetails" => "Data not available."
									]
								];

								return $this->apiController::response(null, $booking);
							}
						}
					} else {
						$isAvailable = 1;
					}
				}
			} else {
				$booking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					'Error' => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "The slot you're going to book has already passed or is not available. Please look for other dates and slots.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $booking);
			}

			if ($isAvailable == 0) {
				$booking['data']['RequestStatus'] = [
					'Status' => $status,
					'Error' => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "The slot you're going to book is sold out. Please look for other dates and slots.",
						"ErrorDetails" => "Data not available."
					]
				];

				$booking['data']['TransactionStatus'] = [
					'Status' => "REJECTED",
					'RejectionReasonDetails' => 'The slot you\'re going to book is sold out. Please look for other dates and slots.',
					'RejectionReason' => 'BOOKED_OUT_ALT_TIMES'
				];

				return $this->apiController::response(null, $booking);
			}

			// Store PAX info information in tour_bookings_transact table
			foreach ($request['data']['TravellerMix'] as $key => $pax) {
				if ($key != "Total") {
					$paxArr[] = $key;
					$touristCount[] = $pax;
				}
			}

			// Get rate groups for TripAdvisor Affiliate
			$rateGroups = DB::select("
				SELECT
					`tpr`.`id`, `tpr`.`rate_for`,
					`tpr`.`rate`, `tpr`.`additional_tax_percentage`,
					`tpr`.`processing_charge_percentage`, `tpr`.`additional_charge`,
					`tpr`.`permit_fee`
				FROM
					`tour_package_rates` AS `tpr`
				INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tpr`.`tour_package_id`
				INNER JOIN `customers` AS `c` ON `c`.`id` = `tpr`.`affiliate_id`
				WHERE
					`tpr`.`deleted_by` = 0
				AND
					`tpr`.`tour_package_id` = " . $package->id . "
				AND
					`c`.`id` = " . $affiliate->id . "
				AND
					`tpr`.`rate_for` IN ('" . implode("', '", $paxArr) . "')
				AND
					`tpr`.`status` = 'Active'
				ORDER BY
					`tpr`.`orderby` ASC
			");

			if (count($rateGroups) == 0) {
				$booking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					'Error' => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Please add rate groups for TripAdvisor for selected tour package.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
				return $this->apiController::response(null, $booking);
			}

			// Calculate total tourists for this booking
			$tourPax = $request['data']['TravellerMix'];
			unset($tourPax['Total']);

			$activeRateGroups = array_column($rateGroups, 'rate_for');
			foreach ($tourPax as $key => $value) {
				if (!in_array($key, $activeRateGroups)) {
					unset($tourPax[$key]);
				}
			}

			$totalTourists = array_sum($tourPax);

			// find the time slot ID in the tour_slot_times table from the requested time
			$slot = collect(DB::select("
				SELECT
					`tst`.`id` as `tour_slot_id`
				FROM
					`tour_slot_times` AS `tst`
				INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tst`.`tour_package_id`
				WHERE
					`tst`.`bookable_status` = 'Open'
				AND
					`tst`.`hide_slot` = 0
				AND
					`tst`.`time_from` = '" . $request['data']['TourOptions']['TourDepartureTime'] . "'
				AND
					`tst`.`date` = '" . $request['data']['TravelDate'] . "'
				AND
					`tp`.`id` = '" . $package->id . "'
			"))->first();

			// Create Customer For This Booking
			$customer = $this->customer::firstOrCreate([
				// 'email' => isset($request['data']['RequiredInfo']) ? (($request['data']['RequiredInfo']['Question'][0]['QuestionAnswer'] != null) ? $request['data']['RequiredInfo']['Question'][0]['QuestionAnswer'] : null) : null
				'email' => $request['data']['ContactEmail']
			], [
				'name' => $request['data']['ContactDetail']['ContactName'],
				'tour_operator_id' => $operator->id,
				'phone_number' => $request['data']['ContactDetail']['ContactValue'],
			]);
			DB::commit();

			if ($customer) {
				// Store booking information in tour_bookings_master table
				$tourBooking = $this->tourBooking::create([
					'viator_bookingRef' => $request['data']['BookingReference'],
					'tour_package_id' => $package->id,
					'tour_slot_id' => $slot->tour_slot_id,
					'customer_id' => $customer->id,
					'name' => $request['data']['ContactDetail']['ContactName'],
					'email' => $customer->email,
					'phone_number' => $customer->phone_number,
					'tourists' => $totalTourists,
					'comments' => (isset($request['data']['SpecialRequirement'])) ? $request['data']['SpecialRequirement'] : "",
					'mask_required' => 'Yes',
					'total' => $request['data']['Amount'] ?? $bookingTotal,
					'booking_total' => $request['data']['Amount'] ?? $bookingTotal,
					'status' => 'Booked',
					'created_by' => $customer->id,
					'created_by_user_type' => 'Customer'
				]);
				DB::commit();

				if ($tourBooking) {
					foreach ($rateGroups as $key => $rate) {
						$groupTotal = 0;
						$keyVal = array_search($rate->rate_for, $paxArr);
						$tourists = (isset($keyVal)) ? $touristCount[$keyVal] : 0;

						$groupTotal = ($tourists * ($rate->rate + $rate->additional_charge + $rate->permit_fee));
						$groupTotal += ($groupTotal * (float)$rate->processing_charge_percentage) / 100;

						if ($tourists > 0) {
							$this->bookingTransact::create([
								'tour_booking_id' => $tourBooking->id,
								'tour_package_rate_id' => $rate->id,
								'tourists' => $tourists,
								'additional_tax_percentage' => $rate->additional_tax_percentage,
								'processing_charge_percentage' => $rate->processing_charge_percentage,
								'nat_fees_percentage' => $package->service_commission_percentage,
								'stripe_fees_percentage' => $package->stripe_commission_percentage,
								'additional_charge' => ($rate->additional_charge),
								'permit_fee' => $rate->permit_fee,
								'rate' => $rate->rate,
								'total' => $groupTotal,
								'created_by' => $customer->id,
								'created_by_user_type' => 'Customer',
								'created_at' => Carbon::now(),
							]);

							$addedPeoplesText[] = "{$rate->rate_for} person $tourists";
						}
						DB::commit();
					}

					$touristsSum = $this->bookingTransact::select('total')->where('tour_booking_id', $tourBooking->id)->sum('total');

					// Update tour_booking_master table to update service commission
					$this->tourBooking::where('id', $tourBooking->id)->update([
						'total' => $touristsSum,
						'booking_total' => $touristsSum,
						'service_commission' => $platformFees
					]);
					DB::commit();

					// Store History details in tour_booking_history
					$touristHistory = [
						'tour_booking_id' => $tourBooking->id,
						'tourists' => $totalTourists,
						'operation' => 'Added',
						'details' => json_encode([
							'sub_operation' => 'Tourists Added',
							'discount_percentage' => 0,
							'discount2_value' => 0,
							'added' => $addedPeoplesText,
							'newTourists' => $totalTourists
						], JSON_NUMERIC_CHECK),
						'operation_at' => Carbon::now(),
						'operation_by' => $customer->id,
						'operation_by_user_type' => 'Customer',
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					];

					DB::table('tour_booking_history')->insert($touristHistory);

					// Store payment details in transactions table
					$transactionId = DB::table('transactions')
						->insertGetId([
							'tour_booking_id' => $tourBooking->id,
							'amount' => $request['data']['Amount'] ?? $touristsSum,
							'fee_amount' => $serviceCommission,
							'via' => 'TripAdvisor',
							'response_init' => '',
							'response_end' => '',
							'response_end_datetime' => null,
							'status' => 'Completed',
							'payment_type' => 0,
							'visitor' => request()->getClientIp(),
							'request_from' => 'TRIPADVISOR',
							'created_by' => $customer->id,
							'created_by_user_type' => 'Customer',
							'created_at' => Carbon::now(),
						]);
					DB::commit();

					// Store Payment details in tour_booking_history table
					$historyId = DB::table('tour_booking_history')
						->insertGetId([
							'tour_booking_id' => $tourBooking->id,
							'tourists' => $totalTourists,
							'transaction_id' => $transactionId,
							'operation' => 'Payment',
							'details' => json_encode(['amount' => $request['data']['Amount'] ?? $touristsSum, 'payment_type' => 'TripAdvisor'], JSON_NUMERIC_CHECK),
							'operation_at' => Carbon::now(),
							'operation_by' => $customer->id,
							'operation_by_user_type' => 'Customer',
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);
					DB::commit();

					// Call service to store history update details
					TripAdvisorBookingService::run($tourBooking, $transactionId, $historyId);

					TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

					$booking['data']['RequestStatus']['Status'] = "SUCCESS";
					$booking['data']['TransactionStatus'] = ['Status' => "CONFIRMED"];
					$booking['data']['SupplierConfirmationNumber'] = $tourBooking->id;

					// Call function to create PDF to send in response
					$bookingPdf = $this->customerController::tripAdvisorBookingPdf(Crypt::encryptString($tourBooking->id));
					if (config('app.env') != "production" || config('app.env') != "staging" || config('app.env') != "development") {
						if ($bookingPdf) {
							$booking['data']['TourTicket'] = [
								'MimeType' => "application/pdf",
								'Url' => $bookingPdf
							];
						} else {
							$booking['data']['RequestStatus'] = [
								"Status" => "ERROR",
								"Error" => [
									"ErrorCode" => "TGDS0006",
									"ErrorMessage" => "Something went wrong while creating booking PDF, please try again later.",
									"ErrorDetails" => "Internal Server Error"
								]
							];
							// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
						}
					} else {
						$booking['data']['TourTicket'] = [
							'MimeType' => "application/pdf",
							'Url' => $bookingPdf
						];
					}
				} else {
					$booking['data']['RequestStatus'] = [
						"Status" => "ERROR",
						"Error" => [
							"ErrorCode" => "TGDS0006",
							"ErrorMessage" => "Something went wrong, please try again later.",
							"ErrorDetails" => "Unknown internal error"
						]
					];
					// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
				}
			} else {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => "Something went wrong, please try again later.",
						"ErrorDetails" => "Unknown internal error"
					]
				];
				// Log::info("TripAdvisor Booking API Error Response ========== " . json_encode($booking));
			}

			return $this->apiController::response(null, $booking);
		} catch (Exception $exception) {
			Log::error("TripAdvisor Booking API Exception Error ========= " . $exception->getMessage());
			DB::rollback();

			$booking = [
				"responseType" => "BookingResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $booking);
		}
	}

	/**
	 * Used to cancel booking in our system
	 * @param mixed $request
	 * @return mixed
	 */
	public function cancelBookingRequest($request)
	{
		DB::beginTransaction();
		try {
			$cancelBooking = [
				'responseType' => "BookingCancellationResponse",
				'data' => [
					'ApiKey' => $request['data']['ApiKey'],
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'Timestamp' => Carbon::now('UTC'),
				]
			];

			// Check wheather the booking is already cancelled or not
			$booking = $this->tourBooking::where('id', $request['data']['SupplierConfirmationNumber'])->first();

			if (!$booking) {
				$cancelBooking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => "Incorrect SupplierConfirmationNumber(Booking not found).",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Cancel Booking API Error Response ========== " . json_encode($cancelBooking));

				return $this->apiController::response(null, $cancelBooking);
			}

			if ($booking->status == "Cancelled") {
				$cancelBooking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0026",
						"ErrorMessage" => "This booking is already cancelled.",
						"ErrorDetails" => "Invalid request."
					]
				];

				return $this->apiController::response(null, $cancelBooking);
			}

			// Perform cancel booking integration
			$booking = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $request['data']['SupplierConfirmationNumber'])
				->first([
					DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t")
				]);

			if (!$booking) {
				return $this->apiController::response('message', 'Booking Not Found!!');
			}

			$twoHoursAgo = date($request['data']['CancelDate'] . ' H:i:s', strtotime('-2 hours'));

			$failedCancellation = 0;
			if ($request['data']['CancelDate'] < Carbon::now()->format('Y-m-d')) {
				$failedCancellation = 1;
				$errorMessage = 'The requested cancellation date is in the past.';
				$errorReason = 'PAST_CANCEL_DATE';
			}

			if ($booking->tour_d_t < $twoHoursAgo) {
				$failedCancellation = 1;
				$errorMessage = 'In order to cancel a reservation, kindly contact the tour operator';
				$errorReason = 'OTHER';
			}

			if ($failedCancellation == 1) {
				$cancelBooking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					'Error' => [
						"ErrorCode" => "TGDS0026",
						"ErrorMessage" => $errorMessage,
						"ErrorDetails" => "Invalid Request."
					]
				];

				$cancelBooking['data']['TransactionStatus'] = [
					'Status' => "REJECTED",
					'RejectionReasonDetails' => $errorMessage,
					'RejectionReason' => $errorReason
				];

				return $this->apiController::response(null, $cancelBooking);
			}

			// Get customer of the booking
			$tourBookingQry = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->where('tbm.id', $request['data']['SupplierConfirmationNumber']);

			$tourBooking = $tourBookingQry->first([
				'tbm.tour_package_id AS pkg_id',
				'tbm.tourists',
				'tbm.status',
				'tbm.customer_id',
				'tst.id AS tour_slot_id',
				'tp.tour_operator_id',
				'tbm.email',
				'tbm.name',
				'tbm.phone_number',
				'cus.is_affiliate',
				DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
				'tst.time_from',
				'tst.time_to',
				'tsd.date',
				'tst.time_slot_seats as seats',
				'tp.name AS package_name',
				'tp.id as tour_package_id',
				'tp.things_to_bring',
				'tp.important_notes',
				't_o.cancellation_policy',
				't_o.id as tour_operator_id',
				't_o.name AS tour_operator',
				't_o.email AS tour_op_email',
				't_o.phone_number AS tour_op_phone',
				't_o.website AS tour_op_website',
				't_o.logo AS tour_op_logo',
				'tp.service_commission_percentage',
				'cus.affiliate_processing_percentage',
				'cus.stripe_affiliate_percentage',
				'tp.stripe_commission_percentage',
				'tp.email as packageEmail',
				'tp.from_name as packageFromName',
				'tp.from_email as packageFromEmail',
				'tp.api_key as packageApiKey',
				'tbm.service_commission',
				DB::raw("(SELECT COUNT(tbm.tourists)
				FROM tour_bookings_master AS tbm
				WHERE tbm.tour_slot_id = tst.id
					AND tbm.status != 'Cancelled'
				GROUP BY tbm.tour_slot_id) AS booked_seats")
			]);

			if (!$tourBooking) {
				$cancelBooking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0026",
						"ErrorMessage" => "Booking Not Found.",
						"ErrorDetails" => "Data not available."
					]
				];

				// Log::info("TripAdvisor Cancel Booking API Error Response ========== " . json_encode($cancelBooking));

				return $this->apiController::response(null, $cancelBooking);
			}

			$transaction = $this->transaction::where('tour_booking_id', $request['data']['SupplierConfirmationNumber'])->where('status', 'Completed')->first();

			$customer = $this->customer::find($tourBooking->customer_id);

			$update = $tourBookingQry->update([
				'tbm.status' => 'Cancelled',
				'tbm.updated_by' => $customer->id,
				'tbm.updated_by_user_type' => 'Customer'
			]);

			if ($update) {
				if (!empty($transaction)) {
					$cancelBookingRefund = TripAdvisorCancelBookingService::run($transaction, $request['data']['SupplierConfirmationNumber'], $tourBooking, $request);

					// Below line is for to update tour booking history table
					TripAdvisorCancelBookingService::historyUpdate($cancelBookingRefund['historyId'], $cancelBookingRefund['via'], $cancelBookingRefund['transaction_id']);
				} else {
					DB::table('tour_booking_history')
						->insertGetId([
							'tour_booking_id' => $booking->id,
							'tourists' => $tourBooking->tourists,
							'operation' => 'Cancelled',
							'is_processed' => 1,
							'details' => json_encode(['amount' => 0, 'payment_type' => '', 'note' => $request['data']['Reason']], JSON_NUMERIC_CHECK),
							'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
							'operation_by' => $customer->id,
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);
					DB::commit();
				}
			}

			$refund = $this->transaction::where('tour_booking_id', $request['data']['SupplierConfirmationNumber'])
				->where('amount', '>', 0)
				->where('status', 'Refund')
				->count();

			if ($refund) {
				$cancelBooking['data']['RequestStatus']['Status'] = "SUCCESS";
				$cancelBooking['data']['BookingReference'] = $request['data']['BookingReference'];
				$cancelBooking['data']['SupplierConfirmationNumber'] = $request['data']['SupplierConfirmationNumber'];
				$cancelBooking['data']['TransactionStatus']['Status'] = "CONFIRMED";
			}

			return $this->apiController::response(null, $cancelBooking);
		} catch (Exception $exception) {
			Log::error("TripAdvisor Cancel Booking API Exception Error ========= " . json_encode($request->all()));
			DB::rollback();

			$cancelBooking = [
				"responseType" => "BookingCancellationResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $cancelBooking);
		}
	}

	/**
	 * API Enables suppliers to receive amendment requests to confirmed bookings from Viator systems in real time
	 * @param mixed $request
	 * @return mixed
	 */
	public function amendmentBookingRequest($request)
	{
		DB::beginTransaction();
		try {
			$amendmentBooking = [
				'responseType' => "BookingAmendmentResponse",
				'data' => [
					'ApiKey' => $request['data']['ApiKey'],
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'Timestamp' => Carbon::now('UTC'),
					'BookingReference' => $request['data']['BookingReference']
				]
			];

			// Get tour operator ID
			$operator = $this->tourOperator::where('tripadvisor_supplier_id', $request['data']['SupplierId'])->where('tripadvisor_reseller_id', $request['data']['ResellerId'])->first();
			if (!$operator) {
				$errMsg = "Incorrect SupplierId or ResellerId.";
				$amendmentBooking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => $errMsg,
						"ErrorDetails" => "Data not available."
					]
				];
				$amendmentBooking['data']['TransactionStatus'] = [
					'Status' => "REJECTED",
					'RejectionReasonDetails' => $errMsg,
					'RejectionReason' => 'OTHER'
				];
				return $this->apiController::response(null, $amendmentBooking);
			}

			// Get tour package using requested Supplier Product Code
			$package = $this->tourPackage::where('productCode', $request['data']['SupplierProductCode'])
				->where('status', 'Active')
				->first();
			if (!$package) {
				$errorMsg = "Incorrect SupplierProductCode OR Package Not Active.";
				$amendmentBooking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => $errorMsg,
						"ErrorDetails" => "Data not available."
					]
				];

				$amendmentBooking['data']['TransactionStatus'] = [
					'Status' => "REJECTED",
					'RejectionReasonDetails' => $errorMsg,
					'RejectionReason' => 'OTHER'
				];

				return $this->apiController::response(null, $amendmentBooking);
			}

			// Get TripAdvisor Affiliate
			$affiliate = $this->customer::where('tour_operator_id', $operator->id)->where('name', 'TripAdvisor')->where('is_affiliate', 'Yes')->first();
			if (!$affiliate) {
				$erMsg = "TripAdvisor affiliate isn't integrated yet. Please add it and try again.";
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0028",
						"ErrorMessage" => $erMsg,
						"ErrorDetails" => "Data not available."
					]
				];

				$amendmentBooking['data']['TransactionStatus'] = [
					'Status' => "REJECTED",
					'RejectionReasonDetails' => $erMsg,
					'RejectionReason' => 'OTHER'
				];

				return $this->apiController::response(null, $booking);
			}

			$isAvailable = 0;
			$touristsTotal = 0;
			$status = ($isAvailable == 1) ? "SUCCESS" : "ERROR";
			if ($request['data']['TravelDate'] < date('Y-m-d')) {
				$errorMsgs = "The slot you're going to book has already passed or is not available. Please look for other dates and slots.";
				$amendmentBooking['data']['RequestStatus'] = [
					'Status' => $status,
					'Error' => [
						"ErrorCode" => "TGDS0028",
						'ErrorMessage' => $errorMsgs,
						"ErrorDetails" => "Data not available."
					]
				];

				$amendmentBooking['data']['TransactionStatus'] = [
					'Status' => "REJECTED",
					'RejectionReasonDetails' => $errorMsgs,
					'RejectionReason' => 'OTHER'
				];

				return $this->apiController::response(null, $amendmentBooking);
			}

			// Check booking exists or not
			// $bookingExists = $this->tourBooking::where('id',$request['data']['SupplierConfirmationNumber'])->where('viator_bookingRef',$request['data']['BookingReference'])->first();
			$bookingExists = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
				->where('tbm.id', $request['data']['SupplierConfirmationNumber'])
				->where('tbm.viator_bookingRef', $request['data']['BookingReference'])
				->first(['tbm.tourists', 'tsd.date', 'tst.time_from', 'tbm.name', 'tbm.email', 'tbm.phone_number', 'tbm.name', 'tbm.tour_slot_id', 'tbm.id', 'tbm.customer_id']);

			if (!$bookingExists) {
				return $this->CreateBookingRequest($request);
			} else {
				$checkBooking = $this->checkSelectedTimeSlotBooking($request['data']['TourOptions']['TourDepartureTime'], $package->id, $request['data']['TravelDate'], $affiliate->id);
				if ($checkBooking) {
					$currentTime = ($checkBooking->timezone != '') ? Carbon::now($checkBooking->timezone)->format('Y-m-d H:i:s') : Carbon::now()->format('Y-m-d H:i:s');
					$currentTime = Carbon::parse($currentTime);
					$dateTime = Carbon::parse($checkBooking->date . ' ' . $checkBooking->time_from);
					$checkBooking->slotStatus = ($dateTime >= $currentTime) ? 'Open' : 'Closed';

					if ($checkBooking->slotStatus == 'Closed') {
						$isAvailable = 0;
						$errorMsg = "The slot you're going to book has already passed or is not available. Please look for other dates and slots.";
						$amendmentBooking['data']['RequestStatus'] = [
							'Status' => "ERROR",
							'Error' => [
								"ErrorCode" => "TGDS0028",
								"ErrorMessage" => $errorMsg,
								"ErrorDetails" => "Data not available."
							]
						];

						$amendmentBooking['data']['TransactionStatus'] = [
							'Status' => "REJECTED",
							'RejectionReasonDetails' => $errorMsg,
							'RejectionReason' => 'OTHER'
						];

						return $this->apiController::response(null, $amendmentBooking);
					} else {
						// If request of TravellerMix exists
						if (isset($request['data']['TravellerMix'])) {
							// Check requested total seats are compatiable or less or equal than actual remaining seats
							if (isset($request['data']['TravellerMix']['Total'])) {
								$totalPax = $request['data']['TravellerMix'];
								unset($totalPax['Total']);
								$touristsTotal = array_sum($totalPax);
							} else {
								foreach ($request['data']['TravellerMix'] as $key => $value) {
									$touristsTotal += $value;
								}
							}

							$remainingSeats = $checkBooking->seats;
							$isAvailable = ($touristsTotal > $remainingSeats) ? 0 : 1;

							if ($remainingSeats == 0) {
								$errorMsg = "No seats are available.";
								$amendmentBooking['data']['RequestStatus'] = [
									'Status' => "ERROR",
									'Error' => [
										"ErrorCode" => "TGDS0028",
										"ErrorMessage" => $errorMsg,
										"ErrorDetails" => "Data not available."
									]
								];

								$amendmentBooking['data']['TransactionStatus'] = [
									'Status' => "REJECTED",
									'RejectionReasonDetails' => $errorMsg,
									'RejectionReason' => 'OTHER'
								];

								return $this->apiController::response(null, $amendmentBooking);
							} else {
								if ($isAvailable == 0) {
									$errorMsg = "You can book up to " . $remainingSeats . " tourists because only " . $remainingSeats . " seats are available.";
									$amendmentBooking['data']['RequestStatus'] = [
										'Status' => "ERROR",
										'Error' => [
											"ErrorCode" => "TGDS0028",
											"ErrorMessage" => $errorMsg,
											"ErrorDetails" => "Data not available."
										]
									];

									$amendmentBooking['data']['TransactionStatus'] = [
										'Status' => "REJECTED",
										'RejectionReasonDetails' => $errorMsg,
										'RejectionReason' => 'OTHER'
									];

									return $this->apiController::response(null, $amendmentBooking);
								}
							}
						} else {
							$isAvailable = 1;
						}
					}
				} else {
					$errorMsg = "The slot you're going to book has already passed or is not available. Please look for other dates and slots.";
					$amendmentBooking['data']['RequestStatus'] = [
						'Status' => "ERROR",
						'Error' => [
							"ErrorCode" => "TGDS0028",
							"ErrorMessage" => $errorMsg,
							"ErrorDetails" => "Data not available."
						]
					];

					$amendmentBooking['data']['TransactionStatus'] = [
						'Status' => "REJECTED",
						'RejectionReasonDetails' => $errorMsg,
						'RejectionReason' => 'OTHER'
					];

					return $this->apiController::response(null, $amendmentBooking);
				}

				if ($isAvailable == 0) {
					$errorMsg = "The slot you're going to book is sold out. Please look for other dates and slots.";
					$amendmentBooking['data']['RequestStatus'] = [
						'Status' => $status,
						'Error' => [
							"ErrorCode" => "TGDS0028",
							"ErrorMessage" => $errorMsg,
							"ErrorDetails" => "Data not available."
						]
					];

					$amendmentBooking['data']['TransactionStatus'] = [
						'Status' => "REJECTED",
						'RejectionReasonDetails' => $errorMsg,
						'RejectionReason' => 'BOOKED_OUT_ALT_TIMES'
					];

					return $this->apiController::response(null, $amendmentBooking);
				}

				// Reschedule Booking [In case of only date or timeslot is changed]
				$rescheduleBooking = DB::table('tour_slot_times AS tst')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
					->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
					->where('tst.time_from', $request['data']['TourOptions']['TourDepartureTime'])
					->where('tsd.date', $request['data']['TravelDate'])
					->where('tp.id', $package->id)
					->first([
						'tst.id',
						'tst.time_slot_seats as seats',
						'tst.time_from',
						'tst.time_to',
						'tsd.date',
						'tp.name AS package_name',
						'tp.id as tour_package_id',
						'tp.things_to_bring',
						'tp.important_notes',
						't_o.cancellation_policy',
						't_o.id as tour_operator_id',
						't_o.name AS tour_operator',
						't_o.email AS tour_op_email',
						't_o.phone_number AS tour_op_phone',
						't_o.website AS tour_op_website',
						't_o.logo AS tour_op_logo',
						'tp.email as packageEmail',
						'tp.from_name as packageFromName',
						'tp.from_email as packageFromEmail',
						'tp.api_key as packageApiKey',
						DB::raw("(SELECT SUM(tbm.tourists)
							FROM tour_bookings_master AS tbm
							WHERE tbm.tour_slot_id = tst.id
								AND tbm.status != 'Cancelled'
							GROUP BY tbm.tour_slot_id) AS booked_seats")
					]);

				if (!$rescheduleBooking) {
					$errorMsg = "Timeslot not found.";
					$amendmentBooking['data']['RequestStatus'] = [
						'Status' => "ERROR",
						'Error' => [
							"ErrorCode" => "TGDS0028",
							"ErrorMessage" => $errorMsg,
							"ErrorDetails" => "Data not available."
						]
					];

					$amendmentBooking['data']['TransactionStatus'] = [
						'Status' => "REJECTED",
						'RejectionReasonDetails' => $errorMsg,
						'RejectionReason' => 'BOOKED_OUT_ALT_TIMES'
					];
				}

				// Reschedule Booking
				if ($bookingExists->tour_slot_id != $rescheduleBooking->id) {
					// Store date and time-slot in temp variable
					$oldDate = $bookingExists->date;
					$oldTimeSlot = $bookingExists->time_from;

					$tourBookingUpdate = [
						'tour_slot_id' => $rescheduleBooking->id,
						'updated_at' => mysqlDT(),
						'updated_by' => $bookingExists->customer_id,
						'updated_by_user_type' => "Customer"
					];

					// Update tour_bookings_master table
					$this->tourBooking::where('id', $request['data']['SupplierConfirmationNumber'])->update($tourBookingUpdate);
					DB::commit();

					// Update tour_booking_history table
					DB::table('tour_booking_history')
						->insert([
							'tour_booking_id' => $bookingExists->id,
							'operation' => 'Rescheduled',
							'details' => json_encode([
								'dateFrom' => $oldDate,
								'timeFrom' => $oldTimeSlot,
								'dateTo' => $rescheduleBooking->date,
								'timeTo' => $rescheduleBooking->time_from,
								'created_by_with_name' => $bookingExists->name
							]),
							'operation_at' => mysqlDT(),
							'operation_by' => $bookingExists->customer_id,
							'operation_by_user_type' => "Customer",
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);
					DB::commit();

					$amendmentBooking['data']['RequestStatus']['Status'] = "SUCCESS";
					$amendmentBooking['data']['TransactionStatus'] = ['Status' => "CONFIRMED"];
					$amendmentBooking['data']['SupplierConfirmationNumber'] = $bookingExists->id;
				}
				// Edit Booking
				// Update tour_bookings_master table
				// $tourBooking = $this->tourBooking::where('id', $request['data']['SupplierConfirmationNumber'])->update($tourBookingUpdate);

				// Update tour_bookings_transact table

				// Update tour_booking_history table

				// Update transactions table
				$amendmentBooking['data']['SupplierConfirmationNumber'] = $bookingExists->id;
			}

			return $this->apiController::response(null, $amendmentBooking);
		} catch (Exception $exception) {
			Log::error("TripAdvisor Amendment Booking API Exception Error ========= " . $exception->getMessage());
			DB::rollback();
			$booking = [
				"responseType" => "BookingAmendmentResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "TGDS0006",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $booking);
		}
	}
}
