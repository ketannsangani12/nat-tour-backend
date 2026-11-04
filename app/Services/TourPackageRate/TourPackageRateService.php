<?php

namespace App\Services\TourPackageRate;

use App\Models\Customer;
use App\Models\SurchargeDate;
use App\Models\TourPackageRate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TourPackageRateService
{
	/**
	 * Get tour package rate groups
	 *
	 * @param Request $request
	 * @param array $vars
	 * @param string $date
	 *
	 * @return array
	 */
	public static function getRateGroups($request, $vars, $date, $customerId = '', $bookingId = '', $timeSlotId = ''): array
	{
		$customer = Customer::where('id', Auth::id())->first();

		//$vars["paymentOptions"] = $customer->affiliate_payment_options;
		$rateGroupsData = [];
		$vars['paxWiseGroupRateIds'] = [];
		$vars['paxWiseGroupRates'] = [];
		$vars['paxWiseGroupAdditionalCharges'] = [];

		if (($customerId != '' && $bookingId != '') || $timeSlotId != '') {
			$isBlackoutDate = isBlackoutDateEnabled($date);
			$affiliateIdForRate = (!$isBlackoutDate) ? Auth::id() : null;
			/* START: Logic to send slot info in helper */
			$standardRateDate = SurchargeDate::where('affiliate_id', $affiliateIdForRate)
				->where('tour_package_id', $vars['tourPackageId'])
				->where('date_range_start', '<=', $date)
				->where('date_range_end', '>=', $date)
				->orderBy('priority', 'DESC')
				->first();

			if (isset($standardRateDate) && $standardRateDate->apply_on_slots && $timeSlotId != '') {
				$customRateDate = isCustomDateRateEnabled($date, $vars['tourPackageId'], $affiliateIdForRate, $timeSlotId);
			} else {
				$customRateDate = isCustomDateRateEnabled($date, $vars['tourPackageId'], $affiliateIdForRate);
			}
			/* END */

			$rateGroupsData = DB::table('tour_package_rates AS tpr')
				->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
				->where([
					'tpr.deleted_by' => 0,
					'tpr.affiliate_id' => $affiliateIdForRate
				])
				->when($customRateDate, function ($query) use ($customRateDate) {
					return $query->where('tpr.custom_date_id', $customRateDate);
				}, function ($query): mixed {
					return $query->whereNull('tpr.custom_date_id');
				})
				->when($vars['tourPackageId'] > 0, function ($query) use ($vars) {
					return $query->where('tpr.tour_package_id', $vars['tourPackageId']);
				})
				->where('tpr.scheduling_year', date('Y', strtotime($date)))
				->orderBy('tpr.orderby', 'asc')
				->select([
					'tpr.id',
					'tpr.rate_for',
					'tpr.rate',
					'tpr.description',
					'tpr.additional_tax_percentage',
					'tpr.processing_charge_percentage',
					'tpr.additional_charge',
					'tpr.margin',
					'tpr.scheduling_year',
					'tpr.permit_fee',
					'tpr.is_group_rate',
					'tpr.min_group_size',
					'tpr.max_group_size',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage'
				])
				->get();

			$paxWiseGroupRateIds = array();
			$paxWiseGroupRates = array();
			foreach ($rateGroupsData as $key => $value) {
				$value->affiliate_processing_percentage = ($isBlackoutDate) ? $rateGroupsData[0]->service_commission_percentage : $customer->affiliate_processing_percentage;
				if (getNextYearCommissionPercentage($date)) {
					$affiliateCommission = ($isBlackoutDate) ? getNormalCommissionPercentage($vars['tourPackageId']) : getAffiliateCommissionPercentage($customer->id);
					$value->affiliate_processing_percentage = ($isBlackoutDate) ? $affiliateCommission['service_commission_percentage'] : $affiliateCommission['affiliate_processing_percentage'];
				}

				if ($value->is_group_rate == '1') {
					if ($value->min_group_size > 0 && $value->max_group_size > 0 && $value->min_group_size <= $value->max_group_size) {
						for ($i = $value->min_group_size; $i <= $value->max_group_size; $i++) {
							$paxWiseGroupRateIds[$i] = $value->id;
							$paxWiseGroupRates[$i] = (float)$value->rate;
						}
					}
				}
			}

			$vars['paxWiseGroupRateIds'] = $paxWiseGroupRateIds;
			$vars['paxWiseGroupRates'] = $paxWiseGroupRates;
		}

		$vars = [
			'rateGroups' => $rateGroupsData,
		] + $vars;

		return $vars;
	}

	/**
	 * Get tour package rate groups
	 *
	 * @param Request $request
	 * @param array $vars
	 * @param string $date
	 *
	 * @return array
	 */
	public static function getMappedRateGroups($tourBooking, $customer, $info): array
	{
		$isGroupBooking = $tourBooking->is_group_booking;
		$isPartnerBooking = $tourBooking->is_partner_booking;
		$isPartnerPackage = $info->is_partner_package;
		$isAffiliate = $customer->is_affiliate;
		$affiliateId = $customer->is_affiliate == 'Yes' ? $customer->id : null;
		$tourists = $tourBooking->tourists + $tourBooking->infants;

		$bookingT = DB::table('tour_bookings_transact AS tbt')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', $tourBooking->id)
			->where('tbt.deleted_by', 0)
			->get(['tbt.id', 'tbt.tour_package_rate_id', 'tbt.tourists', 'tbt.infants', 'tbt.is_infant_rate', 'tbt.permit_fee', 'tbt.partner_fees_type', 'tbt.partner_fees_percentage', 'tbt.partner_fees_amount', 'tpr.mapping_key']);


		$slotTimeId = 0;
		$isBlackoutDate = isBlackoutDateEnabled($info->date);
		$checkCustomRate = TourPackageRate::find($bookingT[0]->tour_package_rate_id);
		if ($checkCustomRate->custom_date_id) {
			$standardRateDate = SurchargeDate::find($checkCustomRate->custom_date_id);
			$slotTimeId = $standardRateDate->apply_on_slots ? $tourBooking->tour_slot_id : 0;
		}
		$affiliateIdForRate = ($isAffiliate == 'Yes' && !$isBlackoutDate) ? $affiliateId : null;
		$customRateDate = isCustomDateRateEnabled($info->date, $info->tour_package_id, $affiliateIdForRate, $slotTimeId);

		$rateGroups = array();
		$tourPackageRate = TourPackageRate::where('affiliate_id', $affiliateIdForRate)
			->where('scheduling_year', date('Y', strtotime($info->date)))
			->when($customRateDate, function ($query) use ($customRateDate) {
				return $query->where('custom_date_id', $customRateDate);
			}, function ($query): mixed {
				return $query->whereNull('custom_date_id');
			})
			->first();

		$defaultRateGroup = TourPackageRate::where('tour_package_id', $info->tour_package_id)
			->where('status', 'Active')
			->when($isGroupBooking == '1', function ($query) use ($tourists) {
				return $query->where('min_group_size', '<=', $tourists)
					->where('max_group_size', '>=', $tourists);
			})
			->when($isAffiliate == 'Yes' && $tourPackageRate && !$isBlackoutDate, function ($query) use ($affiliateIdForRate) {
				return $query->where('affiliate_id', $affiliateIdForRate);
			}, function ($query): mixed {
				return $query->whereNull('affiliate_id');
			})
			->when($customRateDate, function ($query) use ($customRateDate) {
				return $query->where('custom_date_id', $customRateDate);
			}, function ($query): mixed {
				return $query->whereNull('custom_date_id');
			})
			->where('scheduling_year', date('Y', strtotime($info->date)))
			->first();

		if (!$defaultRateGroup) {
			throw new Exception('No rate groups found. Please add rate groups for the package.');
		}

		if ($bookingT && $isGroupBooking != '1') {
			$mappingKeys = [];
			$mappingKeyWiseTourists = [];
			foreach ($bookingT as $key => $transact) {
				if ($transact->mapping_key > 0) {
					$mappingKeys[] = $transact->mapping_key;
					$mappingKeyWiseTourists[$transact->mapping_key] = ($transact->is_infant_rate) ? $transact->infants : $transact->tourists;
				}
			}

			$mappedRateGroups = TourPackageRate::where('tour_package_id', $info->tour_package_id)
				->whereIn('mapping_key', $mappingKeys)
				->where('status', 'Active')
				->when($isGroupBooking == 1, function ($query) use ($tourists) {
					return $query->where('min_group_size', '<=', $tourists)
						->where('max_group_size', '>=', $tourists);
				})
				->when($isAffiliate == 'Yes' && $tourPackageRate && !$isBlackoutDate, function ($query) use ($affiliateIdForRate) {
					return $query->where('affiliate_id', $affiliateIdForRate);
				}, function ($query): mixed {
					return $query->whereNull('affiliate_id');
				})
				->when($customRateDate, function ($query) use ($customRateDate) {
					return $query->where('custom_date_id', $customRateDate);
				}, function ($query): mixed {
					return $query->whereNull('custom_date_id');
				})
				->where('scheduling_year', date('Y', strtotime($info->date)))
				->get();

			if (count($mappedRateGroups) > 0) {
				$i = 0;
				foreach ($bookingT as $key => $transact) {
					foreach ($mappedRateGroups as $mappedRateGroup) {
						if ($transact->mapping_key == $mappedRateGroup->mapping_key  && isset($mappingKeyWiseTourists[$transact->mapping_key])) {
							$rateGroups[$i]['rate_group_id'] = $mappedRateGroup->id;
							$rateGroups[$i]['tourists'] = $mappingKeyWiseTourists[$transact->mapping_key];
							$rateGroups[$i]['rate'] = $mappedRateGroup->rate;
							$rateGroups[$i]['rate_for'] = $mappedRateGroup->rate_for;
							$rateGroups[$i]['permit_fee'] = $mappedRateGroup->permit_fee;
							$rateGroups[$i]['additional_charge'] = $mappedRateGroup->additional_charge;
							$rateGroups[$i]['additional_tax_percentage'] = ($isGroupBooking != '1') ? $mappedRateGroup->additional_tax_percentage : 0;
							$rateGroups[$i]['partner_fee_type'] = ($isPartnerPackage == 1) ? $mappedRateGroup->partner_fee_type : '';
							$rateGroups[$i]['partner_fee_percentage'] = ($isPartnerPackage == 1) ? $mappedRateGroup->partner_fee_percentage : 0.00;
							$rateGroups[$i]['partner_fee_amount'] = ($isPartnerPackage == 1) ? $mappedRateGroup->partner_fee_amount : 0.00;
							$rateGroups[$i]['mapping_key'] = $mappedRateGroup->mapping_key;
							$rateGroups[$i]['is_infant_rate'] = $mappedRateGroup->is_infant_rate;
						} else {
							$rateGroups[$i]['rate_group_id'] = $defaultRateGroup->id;
							$rateGroups[$i]['tourists'] = $transact->tourists;
							$rateGroups[$i]['rate'] = $defaultRateGroup->rate;
							$rateGroups[$i]['rate_for'] = $defaultRateGroup->rate_for;
							$rateGroups[$i]['permit_fee'] = $defaultRateGroup->permit_fee;
							$rateGroups[$i]['additional_charge'] = $defaultRateGroup->additional_charge;
							$rateGroups[$i]['additional_tax_percentage'] = ($isGroupBooking != '1') ? $defaultRateGroup->additional_tax_percentage : 0;
							$rateGroups[$i]['partner_fee_type'] = ($isPartnerPackage == 1) ? $defaultRateGroup->partner_fee_type : '';
							$rateGroups[$i]['partner_fee_percentage'] = ($isPartnerPackage == 1) ? $defaultRateGroup->partner_fee_percentage : 0.00;
							$rateGroups[$i]['partner_fee_amount'] = ($isPartnerPackage == 1) ? $defaultRateGroup->partner_fee_amount : 0.00;
							$rateGroups[$i]['mapping_key'] = $defaultRateGroup->mapping_key;
							$rateGroups[$i]['is_infant_rate'] = $defaultRateGroup->is_infant_rate;

						}
					}
					$i++;
				}
			} else {
				$rateGroups[] = [
					'rate_group_id' => $defaultRateGroup->id,
					'tourists' => $tourists,
					'rate' => $defaultRateGroup->rate,
					'rate_for' => $defaultRateGroup->rate_for,
					'additional_charge' => $defaultRateGroup->additional_charge,
					'permit_fee' => $defaultRateGroup->permit_fee,
					'additional_tax_percentage' => $defaultRateGroup->additional_tax_percentage,
					'partner_fee_type' => ($isPartnerPackage == 1) ? $defaultRateGroup->partner_fee_type : '',
					'partner_fee_percentage' => ($isPartnerPackage == 1) ? $defaultRateGroup->partner_fee_percentage : 0.00,
					'partner_fee_amount' => ($isPartnerPackage == 1) ? $defaultRateGroup->partner_fee_amount : 0.00
				];
			}


			$result = array();
			$result = array_reduce($rateGroups, function ($carry, $item) {
				if (!isset($carry[$item['rate_group_id']])) {
					$carry[$item['rate_group_id']] = [
						'rate_group_id' => $item['rate_group_id'],
						'tourists' => $item['tourists'],
						'rate' => $item['rate'],
						'rate_for' => $item['rate_for'],
						'permit_fee' => $item['permit_fee'],
						'additional_charge' => $item['additional_charge'],
						'is_infant_rate' => $item['is_infant_rate'] ?? 0,
						'additional_tax_percentage' => $item['additional_tax_percentage'],
						'partner_fee_type' => $item['partner_fee_type'],
						'partner_fee_percentage' => $item['partner_fee_percentage'],
						'partner_fee_amount' => $item['partner_fee_amount']
					];
				} else {
					$carry[$item['rate_group_id']]['tourists'] += $item['tourists'];
				}

				return $carry;
			});
			if ($result == null) {
				return array();
			} else {
				return $result;
			}
		} else {
			$rateGroups[] = [
				'rate_group_id' => $defaultRateGroup->id,
				'tourists' => $tourists,
				'rate' => $defaultRateGroup->rate,
				'rate_for' => $defaultRateGroup->rate_for,
				'additional_charge' => $defaultRateGroup->additional_charge,
				'permit_fee' => 0,
				'additional_tax_percentage' => 0,
				'partner_fee_type' => $defaultRateGroup->partner_fee_type,
				'partner_fee_percentage' => $defaultRateGroup->partner_fee_percentage,
				'partner_fee_amount' => $defaultRateGroup->partner_fee_amount
			];

			return $rateGroups;
		}
	}


	/**
	 * Retrieve the applicable custom date for a given tour package and date.
	 *
	 * @param object $request The request object containing tour package details.
	 * @return The custom rate date object if found, otherwise null.
	 */
	public static function getCustomDate(object $request)
	{
		return SurchargeDate::where('tour_package_id', $request->tourPackageId)
			->whereDate('date_range_start', '<=', $request->date)
			->whereDate('date_range_end', '>=', $request->date)
			->when($request->affiliateId, function ($query) use ($request) {
				return $query->where('affiliate_id', $request->affiliateId);
			}, function ($query) {
				return $query->whereNull('affiliate_id');
			})
			->orderByDesc('priority')
			->first();
	}
	/**
	 * Retrieve the number of tourists for a given tour slot and tour operator.
	 *
	 * @param int $tourSlotId The ID of the tour slot.
	 * @param int $tourOperatorId The ID of the tour operator.
	 * @return \Illuminate\Support\Collection The collection of tourists info.
	 */
	public static function getTouristsInfo(int $tourSlotId, int $tourOperatorId): \Illuminate\Support\Collection
	{
		return DB::table('tour_package_rates AS tpr')
			->leftJoin('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
			->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
			->where('tbm.tour_slot_id', $tourSlotId)
			->whereIn('tbm.status', ['Booked', 'Completed'])
			->where('tbt.deleted_by', 0)
			->where('tp.tour_operator_id', $tourOperatorId)
			->groupBy('tbt.tour_package_rate_id')
			->orderByDesc('tpr.age_from')
			->select([
				'tpr.rate_for',
				DB::raw('SUM(tbt.tourists) AS tourists')
			])
			->get();
	}
	/**
	 * Retrieve rate groups for a given tour package.
	 *
	 * @param object $request The request object containing tour package details.
	 * @param mixed $tourPackageRate The tour package rate object (if applicable).
	 * @param mixed $customRateDate The custom rate date object (if applicable).
	 * @param bool $isBlackoutDate Indicates if it's a blackout date.
	 * @return mixed
	 */
	public static function getRateGroupsOfTourPackage(object $request, mixed $tourPackageRate, mixed $customRateDate, $isBlackoutDate = false)
	{
		return DB::table('tour_package_rates AS tpr')
			->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
			->join('states AS st', 'st.id', '=', 'ct.state_id')
			->where('tpr.tour_package_id', $request->tourPackageId)
			->where('tpr.deleted_by', 0)
			->when($request->affiliateId != null && $tourPackageRate && !$isBlackoutDate, function ($query) use ($request) {
				return $query->where('tpr.affiliate_id', $request->affiliateId);
			}, function ($query): mixed {
				return $query->whereNull('tpr.affiliate_id');
			})
			->when($customRateDate, function ($query) use ($customRateDate) {
				return $query->where('tpr.custom_date_id', $customRateDate);
			}, function ($query): mixed {
				return $query->whereNull('tpr.custom_date_id');
			})
			->where('tpr.scheduling_year', date('Y', strtotime($request->date)))
			->orderBy('tpr.orderby', 'asc')
			->select([
				'tpr.id',
				'tpr.rate_for',
				'tpr.rate',
				'tpr.description',
				'tpr.mapping_key',
				'tpr.additional_tax_percentage',
				'tpr.processing_charge_percentage',
				'tpr.additional_charge',
				'tpr.margin',
				'tpr.scheduling_year',
				'tpr.permit_fee',
				'tpr.partner_fee_type',
				'tpr.partner_fee_percentage',
				'tpr.partner_fee_amount',
				'tpr.is_group_rate',
				'tpr.is_infant_rate',
				'tpr.is_cash_rate',
				'tpr.cash_rate',
				'tpr.cash_tax',
				'tpr.approval',
				'tpr.min_group_size',
				'tpr.max_group_size',
				'tp.id AS tour_package_id',
				'tp.service_commission_percentage',
				'tp.stripe_commission_percentage',
				'tp.is_partner_package',
			])
			->get();
	}
	/**
	 * Get the custom rate date based on the standard rate date conditions.
	 *
	 * @param object $info    Tour package and date info.
	 * @param object $request Request containing affiliate and slot details.
	 * @return mixed Returns true if custom rate is enabled, otherwise false/null.
	 */
	public static function getCustomRateDate(object $info, object $request)
	{
		$isBlackoutDate = isBlackoutDateEnabled($info->date);
		// Fetch the standard rate date for the given tour package and affiliate
		$standardRateDate = self::getStandardRateDate($info, $request);
		// Check if the standard rate applies to slots and call the appropriate function
		if ($standardRateDate && $standardRateDate->apply_on_slots) {
			return isCustomDateRateEnabled(
				$info->date,
				$info->tour_package_id,
				(!$isBlackoutDate) ? $request->affiliate_id : 0,
				$request->tour_slot_id
			);
		}

		return isCustomDateRateEnabled(
			$info->date,
			$info->tour_package_id,
			(!$isBlackoutDate) ? $request->affiliate_id : 0,
		);
	}

	/**
	 * Retrieve the standard rate date for a given tour package and affiliate.
	 *
	 * @param object $info    Tour package and date info.
	 * @param object $request Request containing affiliate details.
	 * @return object|null    Returns the standard rate date if found, otherwise null.
	 */
	private static function getStandardRateDate(object $info, object $request)
	{
		// Pick by priority only, the same as every other custom date caller. Passing the
		// slot here made a lower priority slot specific rule win over a higher priority
		// general rule (Ajoy "May-Oct" $52 over "June - Dec | Standardized Rate" $49).
		return SurchargeDate::where('affiliate_id', $request->affiliate_id ?: null)
			->where('tour_package_id', $info->tour_package_id)
			->where('date_range_start', '<=', $info->date)
			->where('date_range_end', '>=', $info->date)
			->orderBy('priority', 'DESC')
			->first();
	}

	/**
	 * Retrieve the applicable rate group for a tour package based on booking details for group booking affiliate.
	 *
	 * @param object $info           Tour package details.
	 * @param object $request        User request containing seats and affiliate info.
	 * @param mixed  $customRateDate Custom date ID if applicable.
	 * @param object $rateGroup      Existing rate group.
	 * @return array                 Returns an array with the new rate group ID and updated total.
	 * @throws Exception             Throws an exception if no rate group is found.
	 */
	public static function getUpdatedRateGroupForGroupBookingAffiliate(object $info, object $request, $customRateDate, object $rateGroup): array
	{
		// Fetch the new applicable rate group based on conditions
		$rateGroupNew = TourPackageRate::where('tour_package_id', $info->tour_package_id)
			->where('status', 'Active')
			->where(function ($query) use ($request) {
				return $query->where('min_group_size', '<=', $request->seats)
					->where('max_group_size', '>=', $request->seats);
			})
			->when($request->affiliate_id, function ($query) use ($request) {
				return $query->where('affiliate_id', $request->affiliate_id);
			}, function ($query) {
				return $query->whereNull('affiliate_id');
			})
			->when($customRateDate, function ($query) use ($customRateDate) {
				return $query->where('custom_date_id', $customRateDate);
			}, function ($query) {
				return $query->whereNull('custom_date_id');
			})
			->where('scheduling_year', date('Y', strtotime($info->date)))
			->first();

		// If no valid rate group is found, throw an exception
		if (!$rateGroupNew) {
			throw new Exception('Unable to update affiliate booking data.');
		}

		// Determine the new rate group ID and calculate the updated total
		$newRateGroupId = ($rateGroup->id !== $rateGroupNew->id) ? $rateGroupNew->id : $rateGroup->id;
		$updateTotal = $rateGroupNew->rate + $rateGroup->additional_charge;

		// Ensure the total is formatted correctly
		if ($updateTotal > 0) {
			$updateTotal = number_format((float) $updateTotal, 2, '.', '');
		}

		return [
			'rate_group_id' => $newRateGroupId,
			'updated_total' => $updateTotal,
		];
	}

	/**
	 * Retrieve the applicable rate group for a affiliate.
	 *
	 * @param object $info                      Tour package details.
	 * @param object $request                   User request containing seats and affiliate info.
	 * @param string   $isGroupRateEnabledForPackage Indicates if group rate is enabled.
	 * @param mixed  $customRateDate            Custom date ID if applicable.
	 * @return object|null                      Returns the rate group object or null if not found.
	 */
	public static function getApplicableRateGroupAffiliate(
		object $info,
		object $request,
		string $isGroupRateEnabledForPackage,
		$customRateDate
	) {
		$isBlackoutDate = isBlackoutDateEnabled($info->date);
		$affiliateId = ($isBlackoutDate) ? 0 : $request->affiliate_id;
		return TourPackageRate::where('tour_package_id', $info->tour_package_id)
			->where('status', 'Active')
			->when($isGroupRateEnabledForPackage == '1', function ($query) use ($request) {
				return $query->where('min_group_size', '<=', $request->seats)
					->where('max_group_size', '>=', $request->seats);
			})
			->when($affiliateId && !$isBlackoutDate, function ($query) use ($affiliateId) {
				return $query->where('affiliate_id', $affiliateId);
			}, function ($query) {
				return $query->whereNull('affiliate_id');
			})
			->when($customRateDate, function ($query) use ($customRateDate) {
				return $query->where('custom_date_id', $customRateDate);
			}, function ($query) {
				return $query->whereNull('custom_date_id');
			})
			->where('scheduling_year', date('Y', strtotime($info->date)))
			->first();
	}
	/**
	 * Check if the rate group is an infant rate.
	 *
	 * @param int $rateGroupId The ID of the rate group.
	 * @return int Returns 1 if it's an infant rate, otherwise 0.
	 */
	public static function checkIsInfantRate($rateGroupId)
	{
		$rateGroup = TourPackageRate::where('id', $rateGroupId)->select('id','is_infant_rate')->first();
		return $rateGroup ? $rateGroup->is_infant_rate : 0;
	}
}
