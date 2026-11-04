<?php

namespace App\Repositories\PartnerAPI;

use App\Repositories\Repository;
use Exception;

use Stancl\Tenancy\Database\Models\Tenant;

use App\Models\TourPackage;
use App\Http\Controllers\API\V1\ApiController;

use App\Models\TourPackageImage;
use App\Models\TourOperator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TourPackageRate;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Models\BookingTransact;
use App\Services\PartnerApiService;
use App\Services\PartnerApiCancelBookingService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\CustomerController;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TimeSlot\TimeSlotService;
use App\Repositories\Octo\OctoRepository;
use App\Services\PartnerBookings;
use App\Models\OctoBooking;
use App\Models\PartnerCrossTenantBooking;
use App\Helpers\OctoErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PartnerApiRepository extends Repository
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
	 * Summary of tourOperatorsRequest
	 * This method retrieves a list of tour operators for a given reseller ID and all tours accross tenants.
	 * @param mixed $request
	 */
	public function tourOperatorsRequest($request)
	{
		try {

			$tourOperators = [
				'responseType' => "TourOperatorsResponse",
				'data' => [
					'ResellerId' => $request['data']['ResellerId'],
					'Timestamp' => Carbon::now('UTC'),
					'TourOperators' => []
				]
			];
			$resellerId  = $request['data']['ResellerId'] ?? 0;
			$affiliateId = $request['data']['AffiliateId'] ?? 0; // note this comes from the auth method
			$unionQuery = PartnerApiRepository::generateUnionQuery($resellerId, $affiliateId);
			$operators = DB::select($unionQuery);
			$tourOperators["data"]["TourOperators"] = $operators;

			return $this->apiController::response(null, $tourOperators);
		} catch (Exception $exception) {
			PartnerApiRepository::logError($exception->getMessage(),  __FUNCTION__, __FILE__, __LINE__);
			$tourOperators = [
				"responseType" => "TourOperatorsResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $tourOperators);
		}
	}

	/**
	 * Provide a list of available tours for Supplier
	 * @param mixed $request
	 * @return mixed
	 */
	public function tourListRequest($request)
	{
		try {
			$tourList = [
				'responseType' => "TourListResponse",
				'data' => [
					'SupplierId' => $request['data']['SupplierId'],
					'ResellerId' => $request['data']['ResellerId'],
					'TenantId' => tenant()->id ?? null,
					'Timestamp' => Carbon::now('UTC'),
					'Tours' => [],
				]
			];

			$operator = $this->tourOperator::where('supplier_id', $request['data']['SupplierId'])->first();
			if (!$operator) {
				$tourList['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "404",
						"ErrorMessage" => "Incorrect SupplierId or ResellerId.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $tourList);
			}

			$resellerId = $request['data']['ResellerId'] ?? 0;
			$affiliateId = $request['data']['AffiliateId'] ?? 0;

			$packages = DB::select("SELECT
			    `t_o`.`id` as tourOperatorId,
				`t_o`.`name` as tourOperatorName,
				`t_o`.`timezone` as timezone,
				`tp`.`id` as tourPackageId,
				`tp`.`productCode`,
				`c`.`id` as affiliate_id,
				`c`.`name` as affiliate_name,
				`tp`.`name` as package_name,
				`tp`.`latitude` as latitude,
				`tp`.`longitude` as longitude,
				`tp`.`hours`,
				`tp`.`minutes`,
				`tp`.`package_has_waiver`,
				`tp`.`short_description`,
				`tp`.`long_description`,
				`tp`.`cancellation`,
				`c`.`status`
				FROM `tour_packages` as tp
				INNER JOIN `tour_operators` as t_o ON `t_o`.`id` = `tp`.`tour_operator_id`
				INNER JOIN customers as c ON `c`.`tour_operator_id` = `t_o`.`id`
				WHERE `tp`.`tour_operator_id` = " . $operator->id . "
				AND `tp`.`id` IN (
					SELECT `package_id`
					FROM package_affiliate_assignments
					WHERE `affiliate_id` = '" . $affiliateId . "'
				)
				AND `c`.`reseller_id` = '" . $resellerId . "'
				AND `c`.`is_affiliate` = 'Yes'
				AND `c`.`deleted_by` = 0
				AND `tp`.`productCode` != ''
				AND `tp`.`status` = 'Active'
				AND `tp`.`deleted_by` = 0
			");

			$status = (count($packages) > 0) ? "SUCCESS" : "ERROR";

			if ($status == "ERROR") {
				$tourList['data']['RequestStatus'] = [
					'Status' => $status,
					'Error' => [
						'ErrorCode' => 404,
						'ErrorMessage' => 'No Tour Packages Found. Please check again later.'
					]
				];
				PartnerApiRepository::logError("No Tour Packages Found for SupplierId: " . $request['data']['SupplierId'], __FUNCTION__, __FILE__, __LINE__);
			} else {
				$tourList['data']['RequestStatus']['Status'] = $status;
			}


			// Optional fields integration
			foreach ($packages as $key => $value) {
				$tourList['data']['Tours'][] = [
					'ProductCode' => $value->productCode,
					'ProductName' => $value->package_name,
					'TimeZone' => $operator->timezone,
					'OperatorId' => $operator->id,
					'OperatorName' => $operator->name,
					'OperatorAddress' => $operator->address,
					'OperatorPhone' => $operator->phone_number,
					'OperatorEmail' => $operator->email,
					'OperatorWebsite' => $operator->website,
					'AffiliateId' => $value->affiliate_id,
					'AffiliateName' => $value->affiliate_name,
					'ShortDescription' => $value->short_description,
					'FullDescription' => $value->long_description,
					'DurationHours' => $value->hours,
					'DurationMinutes' => $value->minutes,
					'WaiverRequred' => $value->package_has_waiver,
					'GeoLocation' => [
						'Latitude' => $value->latitude,
						'Longitude' => $value->longitude
					],
				];
			}

			return $this->apiController::response(null, $tourList);
		} catch (Exception $exception) {
			PartnerApiRepository::logError($exception->getMessage(),  __FUNCTION__, __FILE__, __LINE__);
			$tourList = [
				"responseType" => "TourListResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $tourList);
		}
	}

	/**
	 * Get a list of rate groups for a specific tour package
	 * @param mixed $request
	 *   $SupplierId
	 * 	 $ResellerId
	 * 	 $ProductCode
	 * @return mixed
	 */
	public function rateGroupsRequest($request)
	{

		$affiliateId = $request['data']['AffiliateId'] ?? 0;
		$productCode = $request['data']['ProductCode'];
		$tourOperatorId = $request['data']['AffiliateTourOperatorId'] ?? 0;

		$tourPackage = $this->tourPackage::where('productCode', $productCode)
			->where('tour_operator_id', $tourOperatorId)
			->where('deleted_by', 0)
			->first();

		$rates = PartnerApiRepository::getRateGroups($tourPackage->id, $affiliateId);
		return $this->apiController::response(null, [
			'responseType' => "RateGroupsResponse",
			'data' => [
				'SupplierId' => $request['data']['SupplierId'],
				'ResellerId' => $request['data']['ResellerId'],
				'TenantId' => tenant()->id ?? null,
				'ProductCode' => $request['data']['ProductCode'],
				'RateGroups' => $rates,
				'Timestamp' => Carbon::now('UTC')
			]
		]);
	}

	/**
	 * Provide a list of timeslots for Reseller
	 * @param mixed $request
	 */
	public function timeSlotsRequest($request)
	{
		try {

			$affiliateId = $request['data']['AffiliateId'] ?? 0;
			$tourOperatorId = $request['data']['AffiliateTourOperatorId'] ?? 0;
			$tourPackage = $this->tourPackage::where('productCode', $request['data']['ProductCode'])
				->where('tour_operator_id', $tourOperatorId)
				->where('deleted_by', 0)
				->first();
			$timeSlots = $this->affiliateTimeSlots($affiliateId, $tourPackage->id);
			return $this->apiController::response(null, [
				'responseType' => "TimeSlotsResponse",
				'data' => [
					'SupplierId' => $request['data']['SupplierId'],
					'ResellerId' => $request['data']['ResellerId'],
					'ProductCode' => $request['data']['ProductCode'],
					'TimeSlots' => $timeSlots,
					'TenantId' => tenant()->id ?? null,
					'Timestamp' => Carbon::now('UTC')
				]
			]);
		} catch (Exception $exception) {
			PartnerApiRepository::logError($exception->getMessage(),  __FUNCTION__, __FILE__, __LINE__);
			return $this->apiController::response(null, [
				"responseType" => "TimeSlotsResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			]);
		}
	}

	/**
	 * Provide a list of bookings for Reseller
	 * @param mixed $request
	 */
	public function bookingListRequest($request)
	{
		try {
			$resellerId = $request['data']['ResellerId'] ?? 0;
			$limit = $request['data']['Limit'] ?? 100;
			$offset = $request['data']['Offset'] ?? 0;

			$totalCount = DB::table('tour_bookings_master')->where("reseller_id", "=", $resellerId)->count();
			$bookings = DB::table('tour_bookings_master AS tbm')
				->select(
					'tbm.id AS BookingId',
					'tbm.status AS BookingStatus',
					'tbm.id AS SupplierConfirmationNumber',
					'tbm.reseller_id AS ResellerId',
					'tp.productCode AS ProductCode',
					'tp.name AS TourName',
					'tp.short_description AS TourShortDescription',
					'tsd.date AS TourDate',
					'tst.time_from AS TourStartTime',
					'tst.time_to AS TourEndTime',
					'tp.checkin AS TourCheckin',
					'tp.checkin_type AS TourCheckinType',
					'tp.type AS TourType',
					'tp.latitude AS TourLatitude',
					'tp.longitude AS TourLongitude',
					'tbm.id AS CustomerId',
					'tbm.name AS CustomerName',
					'tbm.email AS CustomerEmail',
				)
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->where('tbm.reseller_id', "=", $resellerId)
				->limit($limit)
				->offset($offset);

			if (isset($request['data']['TourDate']) && !empty($request['data']['TourDate'])) {
				$bookings->where('tsd.date', '=', $request['data']['TourDate']);
			}

			if (isset($request['data']['BookingStatus']) && !empty($request['data']['BookingStatus'])) {
				$bookings->where('tbm.status', '=', $request['data']['BookingStatus']);
			}

			$bookings = $bookings->get();


			return $this->apiController::response(null, [
				'responseType' => "BookingListResponse",
				'data' => [
					'SupplierId' => $request['data']['SupplierId'],
					'ResellerId' => $resellerId,
					'TenantId' => tenant()->id ?? null,
					'Bookings' => $bookings,
					'Timestamp' => Carbon::now('UTC'),
					'Pagination' => [
						'TotalCount' => $totalCount,
						'Limit' => $limit,
						'Offset' => $offset,
						'Pages' => ceil($totalCount / $limit)
					]
				]
			]);
		} catch (Exception $exception) {
			PartnerApiRepository::logError($exception->getMessage(),  __FUNCTION__, __FILE__, __LINE__);
			return $this->apiController::response(null, [
				"responseType" => "BookingsListResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			]);
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
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'TenantId' => tenant()->id ?? null,
					'Timestamp' => Carbon::now('UTC')
				]
			];

			$operator = $this->tourOperator::where('supplier_id', $request['data']['SupplierId'])->first();


			if (!$operator) {
				$batchAvailability['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "404",
						"ErrorMessage" => "Incorrect SupplierId or ResellerId.",
						"ErrorDetails" => "Data not available. No Tour Operator found for this SupplierId or ResellerId."
					]
				];

				return $this->apiController::response(null, $batchAvailability);
			}

			$resellerId = $request['data']['ResellerId'];

			$packages = DB::select("SELECT `t_o`.`id` as tourOperatorId, `tp`.`id` as tourPackageId, `tp`.`productCode`, `c`.`id` as affiliateId, `c`.`name` as affiliate_name, `tp`.`name` as package_name, `c`.`status`
				FROM `tour_packages` as tp
				INNER JOIN `tour_operators` as t_o ON `t_o`.`id` = `tp`.`tour_operator_id`
				INNER JOIN customers as c ON `c`.`tour_operator_id` = `t_o`.`id`
				WHERE `tp`.`tour_operator_id` = " . $operator->id . "
				AND `c`.`reseller_id` = " . intval($resellerId) . "
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
						"ErrorCode" => "404",
						"ErrorMessage" => "Either rate groups(ageband) for " . $resellerId . " is missing or data not found.",
						"ErrorDetails" => "Data not available."
					]
				];

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
							"ErrorCode" => "404",
							"ErrorMessage" => "Affiliate isn't integrated yet. Please add it and try again.",
							"ErrorDetails" => "Data not available."
						]
					];
					PartnerApiRepository::logError("Affiliate isn't integrated yet for ResellerId: " . $resellerId, __FUNCTION__, __FILE__, __LINE__);
					if ($count == 1) {
						break;
					}
				}

				if ($request['data']['StartDate'] < Carbon::now()->format('Y-m-d')) {
					$batchAvailability['data']['BatchTourAvailability'][$key] = [
						'Date' => $request['data']['StartDate'],
						'ProductCode' => $value->productCode,
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
							'ProductCode' => $value->productCode,
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
								'ProductCode' => $value->productCode,
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
								'ProductCode' => $value->productCode,
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
								'ProductCode' => $value->productCode,
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
			PartnerApiRepository::logError($exception->getMessage(),  __FUNCTION__, __FILE__, __LINE__);
			$batchAvailability = [
				"responseType" => "BatchAvailabilityResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $batchAvailability);
		}
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
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'ProductCode' => $request['data']['ProductCode'],
					'TenantId' => tenant()->id ?? null,
					'Timestamp' => Carbon::now('UTC'),
					'TourAvailability' => [],
				]
			];

			$resellerId = $request['data']['ResellerId'];

			$package = collect(DB::select("SELECT `t_o`.`id` as tourOperatorId, `tp`.`id` as tourPackageId, `tp`.`productCode`, `c`.`id` as affiliateId, `c`.`name` as affiliate_name, `tp`.`name` as package_name, `c`.`status`
				FROM `tour_packages` as tp
				INNER JOIN `tour_operators` as t_o ON `t_o`.`id` = `tp`.`tour_operator_id`
				INNER JOIN customers as c ON `c`.`tour_operator_id` = `t_o`.`id`
				WHERE `tp`.`productCode` = '" . $request['data']['ProductCode'] . "'
				AND `c`.`reseller_id` = " . intval($resellerId) . "
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
						"ErrorCode" => "404",
						"ErrorMessage" => "Either rate groups(ageband) are missing/inactive OR Package not active OR ProductCode not found.",
						"ErrorDetails" => "Data not available."
					]
				];

				PartnerApiRepository::logError("Either rate groups(ageband) for are missing/inactive OR Package not active OR ProductCode not found for ResellerId: " . $resellerId, __FUNCTION__, __FILE__, __LINE__);
				return $this->apiController::response(null, $realTimeAvailability);
			}

			$realTimeAvailability['data']['RequestStatus']['Status'] = "SUCCESS";
			$realTimeAvailability['data']['ProductCode'] = $request['data']['ProductCode'];

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
			PartnerApiRepository::logError($exception->getMessage(),  __FUNCTION__, __FILE__, __LINE__);
			$realTimeAvailability = [
				"responseType" => "AvailabilityResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $realTimeAvailability);
		}
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
					'ResellerId' => $request['data']['ResellerId'],
					'SupplierId' => $request['data']['SupplierId'],
					'ProductCode' => $request['data']['ProductCode'],
					'Timestamp' => Carbon::now('UTC'),
					'BookingReference' => $request['data']['BookingReference']
				]
			];

			// Get tour package using requested Supplier Product Code
			$package = $this->tourPackage::where('productCode', $request['data']['ProductCode'])
				->where('status', 'Active')
				->first();
			if (!$package) {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "404",
						"ErrorMessage" => "Incorrect ProductCode OR Package Not Active.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $booking);
			}

			// Get tour operator ID
			$operator = $this->tourOperator::where('supplier_id', $request['data']['SupplierId'])->first();
			if (!$operator) {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "404",
						"ErrorMessage" => "Incorrect SupplierId or ResellerId.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $booking);
			}

			// Get Affiliate Partner
			$affiliateId = $request['data']['AffiliateId'] ?? NULL;


			if (!$affiliateId) {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "404",
						"ErrorMessage" => "Affiliate isn't integrated yet. Please add it and try again.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $booking);
			}

			$isAvailable = 0;
			$touristsTotal = 0;
			$status = ($isAvailable == 1) ? "SUCCESS" : "ERROR";
			if ($request['data']['TravelDate'] < date('Y-m-d')) {
				$booking['data']['RequestStatus'] = [
					'Status' => $status,
					'Error' => [
						"ErrorCode" => "422",
						'ErrorMessage' => "The slot you're going to book has already passed or is not available. Please look for other dates and slots.",
						"ErrorDetails" => "Data not available."
					]
				];

				return $this->apiController::response(null, $booking);
			}

			$checkBooking = $this->checkSelectedTimeSlotBooking($request['data']['TourOptions']['TourDepartureTime'], $package->id, $request['data']['TravelDate'], $affiliateId);

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
							"ErrorCode" => "404",
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
									"ErrorCode" => "404",
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
										"ErrorCode" => "401",
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
						"ErrorCode" => "401",
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
						"ErrorCode" => "401",
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

			// Get rate groups for the Affiliate
			$rateGroups = PartnerApiRepository::getRateGroups($package->id, $affiliateId, $paxArr);
			$affiliateName = $request['data']['AffiliateName'] ?? "";
			if (count($rateGroups) == 0) {
				$booking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					'Error' => [
						"ErrorCode" => "404",
						"ErrorMessage" => "Please add rate groups for " . $affiliateName . " for the selected tour package.",
						"ErrorDetails" => "Data not available."
					]
				];

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
				//'email' => isset($request['data']['RequiredInfo']) ? (($request['data']['RequiredInfo']['Question'][0]['QuestionAnswer'] != null) ? $request['data']['RequiredInfo']['Question'][0]['QuestionAnswer'] : null) : null
				'email' => $request['data']['ContactEmail']
			], [
				'name' => $request['data']['ContactDetail']['ContactName'],
				'tour_operator_id' => $operator->id,
				'phone_number' => $request['data']['ContactDetail']['ContactValue'],
			]);

			# $customer = Customer::where('id', $request['data']['AffiliateId'])->first();
			DB::commit();

			if ($customer) {
				// Store booking information in tour_bookings_master table
				$tourBooking = $this->tourBooking::create([
					'external_booking_id' => $request['data']['BookingReference'],
					'reference_booking' => $request['data']['BookingReference'],
					'tour_package_id' => $package->id,
					'tour_slot_id' => $slot->tour_slot_id,
					'customer_id' => $affiliateId,
					'name' => $request['data']['ContactDetail']['ContactName'],
					'email' => $customer->email,
					'phone_number' => $customer->phone_number,
					'tourists' => $totalTourists,
					'comments' => (isset($request['data']['SpecialRequirement'])) ? $request['data']['SpecialRequirement'] : "",
					'mask_required' => 'Yes',
					'total' => $request['data']['Amount'] ?? $bookingTotal,
					'booking_total' => $request['data']['Amount'] ?? $bookingTotal,
					'status' => 'Booked',
					'created_by' => $affiliateId,
					'created_by_user_type' => 'Affiliate',
					'reseller_id' => $request['data']['ResellerId'] ?? null,
				]);
				DB::commit();

				if ($tourBooking) {
					// Close the time slot on booking
					TimeSlotService::closeSlotOnBooking($tourBooking->tour_package_id, $tourBooking->tour_slot_id);

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
								'created_by_user_type' => 'Affiliate',
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
					$transactionId = NULL;
					// Store payment details in transactions table
					// $transactionId = DB::table('transactions')
					// 	->insertGetId([
					// 		'tour_booking_id' => $tourBooking->id,
					// 		'amount' => $request['data']['Amount'] ?? $touristsSum,
					// 		'fee_amount' => $serviceCommission,
					// 		'via' => 'AffiliatePartner',
					// 		'response_init' => '',
					// 		'response_end' => '',
					// 		'response_end_datetime' => '0000-00-00 00:00:00',
					// 		'status' => 'Completed',
					// 		'payment_type' => 0,
					// 		'visitor' => request()->getClientIp(),
					// 		'request_from' => 'AffiliatePartner',
					// 		'created_by' => $customer->id,
					// 		'created_by_user_type' => 'Affiliate',
					// 		'created_at' => Carbon::now(),
					// 	]);
					DB::commit();
					$affiliateName = $request['data']['AffiliateName'] ?? "Affiliate Partner";
					// Store Payment details in tour_booking_history table
					$historyId = DB::table('tour_booking_history')
						->insertGetId([
							'tour_booking_id' => $tourBooking->id,
							'tourists' => $totalTourists,
							'transaction_id' => $transactionId,
							'operation' => 'Note',
							'details' => "{$affiliateName} Affiliate Booking created successfully",
							'operation_at' => Carbon::now(),
							'operation_by' => $affiliateId,
							'operation_by_user_type' => 'Affiliate',
							'updated_at' => Carbon::now(),
							'created_at' => Carbon::now(),
						]);
					DB::commit();

					// Call service to store history update details
					PartnerApiService::run($tourBooking, $transactionId, $historyId);

					TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

					$booking['data']['RequestStatus']['Status'] = "SUCCESS";
					$booking['data']['TransactionStatus'] = ['Status' => "CONFIRMED"];
					$booking['data']['SupplierConfirmationNumber'] = $tourBooking->id;

					// Call function to create PDF to send in response
					$bookingPdf = $this->customerController::TripAdvisorBookingPdf(Crypt::encryptString($tourBooking->id));
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
									"ErrorCode" => "500",
									"ErrorMessage" => "Something went wrong while creating booking PDF, please try again later.",
									"ErrorDetails" => "Internal Server Error"
								]
							];
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
							"ErrorCode" => "500",
							"ErrorMessage" => "Something went wrong, please try again later.",
							"ErrorDetails" => "Unknown internal error"
						]
					];
				}
			} else {
				$booking['data']['RequestStatus'] = [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => "Something went wrong, please try again later.",
						"ErrorDetails" => "Unknown internal error"
					]
				];
			}

			return $this->apiController::response(null, $booking);
		} catch (Exception $exception) {
			PartnerApiRepository::logError($exception->getMessage(), __FUNCTION__, __FILE__, __LINE__);
			DB::rollback();

			$booking = [
				"responseType" => "BookingResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
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
						"ErrorCode" => "404",
						"ErrorMessage" => "Incorrect SupplierConfirmationNumber(Booking not found).",
						"ErrorDetails" => "Data not available."
					]
				];


				return $this->apiController::response(null, $cancelBooking);
			}

			if ($booking->status == "Cancelled") {
				$cancelBooking['data']['RequestStatus'] = [
					'Status' => "ERROR",
					"Error" => [
						"ErrorCode" => "502",
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
						"ErrorCode" => "400",
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
						"ErrorCode" => "404",
						"ErrorMessage" => "Booking Not Found.",
						"ErrorDetails" => "Data not available."
					]
				];


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
				// Reopen the time slot on cancellation
				TimeSlotService::reopenSlotOnCancellation($tourBooking->pkg_id, $tourBooking->tour_slot_id, (int)$request['data']['SupplierConfirmationNumber']);

				if (!empty($transaction)) {
					$cancelBookingRefund = PartnerApiCancelBookingService::run($transaction, $request['data']['SupplierConfirmationNumber'], $tourBooking, $request);

					// Below line is for to update tour booking history table
					PartnerApiCancelBookingService::historyUpdate($cancelBookingRefund['historyId'], $cancelBookingRefund['via'], $cancelBookingRefund['transaction_id']);
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
			Log::error("AffiliatePartner Cancel Booking API Exception Error ========= " . json_encode($request->all()));
			DB::rollback();

			$cancelBooking = [
				"responseType" => "BookingCancellationResponse",
				"RequestStatus" => [
					"Status" => "ERROR",
					"Error" => [
						"ErrorCode" => "500",
						"ErrorMessage" => $exception->getMessage(),
						"ErrorDetails" => "Internal Server Error."
					]
				]
			];

			return $this->apiController::response(null, $cancelBooking);
		}
	}


	/**
	 * Staic Class or helper methods
	 * --------------------------------------------------------------------
	 *
	 * */


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
                    AND `tbh`.`payment_type` = 'AffiliatePartner'
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
	 * Generate union query for all tenants to get tour operators and packages
	 * Used to generate union query for all tenants to get tour operators and packages
	 * @param int $resellerId the affiliate account reseller the tours
	 * @return string
	 */
	public static function generateUnionQuery($resellerId, $affiliateId)
	{

		$tenants = Tenant::all();
		$unionQuery = '';
		$tenantURL = str_replace("https://", ".", $_SERVER['APP_URL']);

		foreach ($tenants as $tenant) {
			$unionQuery .= "select
							opt.id AS OperatorId,
							opt.name AS OperatorName,
							opt.supplier_id AS SupplierId,
							opt.address AS OperatorAddress,
							opt.phone_number AS OperatorPhone,
							opt.email AS OperatorEmail,
							opt.website AS OperatorWebsite,
							opt.description AS OperatorDescription,
							'https://" . $tenant->id . "" . $tenantURL . "/api/v1/partner-api' AS OperatorTenantURL,
							'$tenant->id' AS TenantId,
							tp.name AS ProductName,
							tp.productCode AS ProductCode,
							tp.short_description AS ShortDescription,
							tp.long_description AS FullDescription,
							tp.hours AS DurationHours,
							tp.minutes AS DurationMinutes,
							tp.package_has_waiver AS WaiverRequred,
							tp.latitude AS Latitude,
							tp.longitude AS Longitude
							FROM " . $tenant->tenancy_db_name . ".tour_operators AS opt
							LEFT JOIN tour_packages AS tp ON tp.tour_operator_id = opt.id
							WHERE tp.deleted_at IS NULL
							AND tp.id IN (SELECT package_id FROM " . $tenant->tenancy_db_name . ".package_affiliate_assignments WHERE affiliate_id = '" . $affiliateId . "')

							";
			if ($tenant->id != $tenants->last()->id) {
				$unionQuery .= " \n UNION ALL \n ";
			}
		}
		return $unionQuery;
	}


	public static function getRateGroups($packageId, $affiliateId, $paxArr = NULL)
	{

		$query = "
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
					`tpr`.`tour_package_id` = " . $packageId . "
				AND
					`c`.`id` = " . $affiliateId . "
				AND
					`tpr`.`status` = 'Active'
			";

		if (!is_null($paxArr)) {
			$query .= " AND `tpr`.`rate_for` IN ('" . implode("', '", $paxArr) . "') ";
		}

		$query .= " ORDER BY `tpr`.`orderby` ASC";

		$rateGroups = DB::select($query);
		return $rateGroups;
	}


	public static function logError($error, $funct, $file, $line)
	{
		$msg = "PartnerAPI ERROR: " . $error . " in function " . $funct . " at line " . $line . " in file " . $file;
		Log::error($msg);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// OCTO-shaped Partner endpoints (cross-tenant packages)
	//
	// These mirror the OCTO API endpoint names, inputs, and response shapes but
	// live under /api/v1/partner-api/*. They REUSE the OCTO implementation
	// read-only (an OctoRepository instance is resolved from the container and
	// its public methods are called) so responses are byte-identical; OCTO code
	// is never modified. On top of that they layer the cross-tenant
	// behaviour via App\Services\PartnerBookings:
	//   - availability is intersected across every origin tenant
	//   - confirmation fans a real booking out to each origin tenant
	//   - cancellation reverses those origin bookings
	// A package with no cross-tenant origin rows behaves exactly like plain OCTO.
	// ══════════════════════════════════════════════════════════════════════════

	private const PARTNER_CAPABILITIES = ['pricing', 'content', 'notifications'];

	/** Read-only OCTO implementation used for shaping + local (lc) persistence. */
	protected function octo(): OctoRepository
	{
		return app(OctoRepository::class);
	}

	/**
	 * Resolve the partner affiliate and prime the request with the OCTO context
	 * (octo_reseller + octo_capabilities) that the reused OCTO code reads.
	 * Returns the Customer, or a JsonResponse error to short-circuit.
	 */
	protected function setupOctoContext(Request $request): Customer|JsonResponse
	{
		$affiliate = $this->resolvePartnerAffiliate($request);
		if (!$affiliate) {
			return OctoErrorResponse::forbidden('Invalid or missing partner API key.');
		}
		if (empty($affiliate->ota_id) || (int) $affiliate->is_ota !== 1) {
			return OctoErrorResponse::unprocessableEntity('Partner affiliate is not configured for the cross-tenant API (missing ota_id / is_ota).');
		}

		$request->merge([
			'octo_reseller'     => $affiliate->ota_id,
			'octo_capabilities' => $this->parsePartnerCapabilities($request),
		]);

		return $affiliate;
	}

	/** Partner affiliate from the merged auth payload, Bearer token, or ApiKey. */
	protected function resolvePartnerAffiliate(Request $request): ?Customer
	{
		$affiliateId = $request['data']['AffiliateId'] ?? null;
		if ($affiliateId) {
			return Customer::find($affiliateId);
		}
		$bearer = $this->partnerBearerToken($request);
		if ($bearer) {
			return Customer::where('reseller_api_key', $bearer)->first();
		}
		$apiKey = $request['data']['ApiKey'] ?? null;
		if ($apiKey) {
			return Customer::where('reseller_api_key', $apiKey)->first();
		}
		return null;
	}

	protected function partnerBearerToken(Request $request): ?string
	{
		$header = $request->header('Authorization', '');
		if (!str_starts_with($header, 'Bearer ')) {
			return null;
		}
		$token = trim(substr($header, 7));
		return $token !== '' ? $token : null;
	}

	/** Parse Octo-Capabilities exactly like OctoAuthentication does. */
	protected function parsePartnerCapabilities(Request $request): array
	{
		$requested = array_filter(array_map(function (string $cap): string {
			$cap = trim($cap);
			return str_starts_with($cap, 'octo/') ? substr($cap, 5) : $cap;
		}, explode(',', $request->header('Octo-Capabilities', ''))));

		return array_values(array_intersect($requested, self::PARTNER_CAPABILITIES));
	}

	// ── Endpoints ─────────────────────────────────────────────────────────────

	/** GET /supplier */
	public function getSupplier(Request $request): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		$response = $this->octo()->getSupplier();
		if ($response->getStatusCode() === 200) {
			$data             = $response->getData(true);
			$data['endpoint'] = rtrim(config('app.url'), '/') . '/api/v1/partner-api';
			return response()->json($data);
		}
		return $response;
	}

	/** GET /products */
	public function getProductList(Request $request): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->octo()->getProductList();
	}

	/** GET /products/{productId} */
	public function getProductById(Request $request, string $productId): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->octo()->getProductById($productId);
	}

	/** POST /availability/calendar */
	public function availabilityCalendar(Request $request): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->applyCrossTenantCalendar($request, $this->octo()->getAvailabilityCalendar());
	}

	/** POST /availability */
	public function availabilityCheck(Request $request): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->applyCrossTenantAvailability($request, $this->octo()->getAvailabilityCheck());
	}

	/** GET /bookings — list by resellerReference or supplierReference. */
	public function getBookingList(Request $request): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->octo()->listBookings();
	}

	/**
	 * GET /bookings/{uuid} — authoritative state of a single booking.
	 *
	 * For cross-tenant packages this reports the local (lc) booking, which is the one
	 * the reseller contracted against; origin-tenant bookings are an implementation
	 * detail of fulfilment and are not surfaced separately.
	 */
	public function getBookingByUuid(Request $request, string $uuid): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->octo()->getBooking($uuid);
	}

	/** POST /bookings — Booking Reservation (local ON_HOLD, no origin writes yet). */
	public function bookingReservation(Request $request): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		return $this->octo()->createBooking();
	}

	/** POST /bookings/{uuid}/confirm — confirm locally, then fan out to origins. */
	public function bookingConfirmation(Request $request, string $uuid): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx; // $ctx = affiliate Customer

		$response = $this->octo()->confirmBooking($uuid);
		if ($response->getStatusCode() !== 200) {
			return $response;
		}

		// LC partners are invoiced rather than settled at booking time. Reclassify the
		// local (lc) payment as 'Invoiced' so it reads as Billable and is picked up by the
		// affiliate invoice subsystem. Partner-api only — the shared OCTO write is untouched.
		if ((int) ($ctx->is_billable ?? 0) === 1) {
			$this->markLocalBookingBillable($uuid);
		}

		return $this->fanOutConfirmedOrigins($uuid, $response);
	}

	/**
	 * Reclassify a freshly-confirmed partner booking's local (lc) payment from the reseller
	 * label to 'Invoiced', so it appears as Billable and flows into affiliate invoicing.
	 * A fresh booking has exactly one transaction and one Payment history row.
	 *
	 * Only the two classification fields are touched; request_from, ota_reference_booking,
	 * created_by, amounts, and transaction status ('Completed') are left intact so both
	 * attribution and the invoice-harvest requirements (via='Invoiced' + status='Completed')
	 * are satisfied.
	 */
	protected function markLocalBookingBillable(string $uuid): void
	{
		try {
			$booking = OctoBooking::where('uuid', $uuid)->first();
			if (!$booking || !$booking->tour_booking_id) {
				return;
			}
			$tbId = $booking->tour_booking_id;

			DB::table('transactions')
				->where('tour_booking_id', $tbId)
				->update(['via' => 'Invoiced']);

			DB::table('tour_booking_history')
				->where('tour_booking_id', $tbId)
				->where('operation', 'Payment')
				->update(['payment_type' => 'Invoiced']);
		} catch (\Throwable $e) {
			// Non-fatal: booking is confirmed; log for follow-up rather than failing the API call.
			Log::error('Partner billable reclassification failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
		}
	}

	/** POST /bookings/{uuid}/cancel — cancel locally, then reverse origins. */
	public function bookingCancellation(Request $request, string $uuid): JsonResponse
	{
		$ctx = $this->setupOctoContext($request);
		if ($ctx instanceof JsonResponse) return $ctx;

		$response = $this->octo()->cancelBooking($uuid);
		if ($response->getStatusCode() === 200) {
			$this->fanOutCancelledOrigins($uuid);
		}
		return $response;
	}

	// ── Cross-tenant layering ────────────────────────────────────────────────────────

	/**
	 * After a successful local confirmation, create the booking in each origin
	 * tenant. On any origin failure, the local (lc) confirmation is rolled back
	 * and an error is returned so no half-fulfilled cross-tenant booking is left confirmed.
	 */
	protected function fanOutConfirmedOrigins(string $uuid, JsonResponse $response): JsonResponse
	{
		// Idempotent: a retried confirm must not double-book the origins.
		if (PartnerCrossTenantBooking::where('booking_uuid', $uuid)->exists()) {
			return $response;
		}

		$booking = OctoBooking::where('uuid', $uuid)->first();
		if (!$booking || $booking->status !== 'CONFIRMED') {
			return $response;
		}

		$package = TourPackage::find($booking->tour_package_id);
		if (!$package || !$package->isCrossTenantPartnerPackage()) {
			return $response; // plain (single-tenant) partner package — nothing to fan out
		}

		$lcSlot = DB::table('tour_slot_times')->where('id', $booking->tour_slot_id)->first();
		if (!$lcSlot) {
			return $response;
		}

		try {
			$originBookings = PartnerBookings::confirmOrigins(
				$uuid,
				$package,
				$lcSlot,
				(int) $booking->tourists,
				(int) $booking->infants,
				$booking->contact ?? []
			);

			PartnerCrossTenantBooking::create([
				'booking_uuid'    => $uuid,
				'tour_package_id' => $package->id,
				'origin_bookings' => $originBookings,
				'status'          => 'CONFIRMED',
			]);

			return $response;
		} catch (\Throwable $e) {
			Log::error('Partner cross-tenant fan-out failed; rolling back local confirmation', [
				'uuid'  => $uuid,
				'error' => $e->getMessage(),
			]);

			// Undo the local lc booking so the partner booking does not stay
			// confirmed without its origin bookings.
			try {
				$this->octo()->cancelBooking($uuid);
			} catch (\Throwable $ce) {
				Log::error('Partner cross-tenant local rollback failed', [
					'uuid'  => $uuid,
					'error' => $ce->getMessage(),
				]);
			}

			return OctoErrorResponse::unprocessableEntity(
				'One or more origin operators could not fulfil this cross-tenant booking. The reservation was not confirmed.'
			);
		}
	}

	/** After a successful local cancellation, reverse the origin bookings. */
	protected function fanOutCancelledOrigins(string $uuid): void
	{
		$link = PartnerCrossTenantBooking::where('booking_uuid', $uuid)
			->where('status', 'CONFIRMED')
			->first();
		if (!$link) {
			return;
		}

		try {
			PartnerBookings::cancelOrigins($link->origin_bookings ?? []);
			$link->update(['status' => 'CANCELLED']);
		} catch (\Throwable $e) {
			// Leave status CONFIRMED so a later retry / reconciliation can finish.
			Log::error('Partner cross-tenant origin cancellation failed', [
				'uuid'  => $uuid,
				'error' => $e->getMessage(),
			]);
		}
	}

	/** Cap calendar vacancies by the origin intersection (cross-tenant only). */
	protected function applyCrossTenantCalendar(Request $request, JsonResponse $response): JsonResponse
	{
		if ($response->getStatusCode() !== 200) return $response;

		$package = TourPackage::find($request->input('productId'));
		if (!$package || !$package->isCrossTenantPartnerPackage()) return $response;

		$start = $request->input('localDateStart');
		$end   = $request->input('localDateEnd') ?? $start;
		if (!$start) return $response;

		// A date is bookable when ANY departure is bookable that date, so take the
		// max across departures. Single-departure uses all origins (null).
		$departures = PartnerBookings::departureTimes($package);
		$byDate = [];
		if (empty($departures)) {
			$byDate = PartnerBookings::aggregateVacancies($package, $start, $end);
		} else {
			foreach ($departures as $t) {
				foreach (PartnerBookings::aggregateVacancies($package, $start, $end, $t) as $date => $v) {
					$byDate[$date] = max($byDate[$date] ?? 0, $v);
				}
			}
		}

		$data = $response->getData(true);
		foreach ($data as &$entry) {
			$left               = $byDate[$entry['localDate'] ?? ''] ?? 0;
			$entry['vacancies'] = min((int) ($entry['vacancies'] ?? 0), $left);
			$entry['available'] = ($entry['vacancies'] > 0) && ($entry['available'] ?? false);
			if ($entry['vacancies'] <= 0) {
				$entry['status']    = 'SOLD_OUT';
				$entry['available'] = false;
			}
		}
		unset($entry);

		return response()->json($data);
	}

	/** Cap slot-level vacancies by the matching departure's origin intersection (cross-tenant only). */
	protected function applyCrossTenantAvailability(Request $request, JsonResponse $response): JsonResponse
	{
		if ($response->getStatusCode() !== 200) return $response;

		$package = TourPackage::find($request->input('productId'));
		if (!$package || !$package->isCrossTenantPartnerPackage()) return $response;

		$start = $request->input('localDateStart');
		$end   = $request->input('localDateEnd') ?? $start;
		if (!$start) return $response; // byIds path is not cross-tenant-capped in v1

		// Per departure time => [date => vacancy]. Single-departure uses key ''
		// (all origins, capped by date); multi-departure caps each slot by its own
		// time's origin group.
		$departures = PartnerBookings::departureTimes($package);
		$vacByTime  = [];
		if (empty($departures)) {
			$vacByTime[''] = PartnerBookings::aggregateVacancies($package, $start, $end);
		} else {
			foreach ($departures as $t) {
				$vacByTime[$t] = PartnerBookings::aggregateVacancies($package, $start, $end, $t);
			}
		}

		$data = $response->getData(true);
		foreach ($data as &$entry) {
			$dt   = $entry['localDateTimeStart'] ?? '';
			$date = substr($dt, 0, 10);
			$key  = empty($departures) ? '' : (strlen($dt) >= 16 ? substr($dt, 11, 5) : '');
			$left = $vacByTime[$key][$date] ?? 0;

			$entry['vacancies'] = min((int) ($entry['vacancies'] ?? 0), $left);
			$entry['available'] = ($entry['vacancies'] > 0) && ($entry['available'] ?? false);
			if ($entry['vacancies'] <= 0) {
				$entry['status'] = 'SOLD_OUT';
			}
		}
		unset($entry);

		return response()->json($data);
	}
}
