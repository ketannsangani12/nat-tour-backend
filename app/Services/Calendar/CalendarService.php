<?php

namespace App\Services\Calendar;

use App\Constants\Constants;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\PackageAffiliateAssignment;
use App\Models\Partnership;
use App\Models\TourPackage;
use App\Models\TourPackageFamily;
use App\Models\TourPackageRate;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\TimeSlot\TouristService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarService
{
	/**
	 * Execute the tourist groups service to get the tourist groups count.
	 *
	 * @param mixed $id
	 *
	 * @return array
	 */
	public static function touristGroups($tGroups): array
	{
		$tGroups = explode('|', $tGroups);
		$tGroupsChanged = [];
		$rateGroupWithTourists = [];

		if (!empty($tGroups)) {
			foreach ($tGroups as $val) {
				$rateGroupWithTourists = explode('^', $val);
				if (!empty($rateGroupWithTourists)) {
					if (Str::contains($val, "Adults - Military Teachers")) {
						$tGroupsChanged[] = "AM:" . substr($rateGroupWithTourists[0], 2);
					} elseif (Str::contains($val, "Children (Ages 8-12) - Military/Teachers") || Str::contains($val, "Children (Ages 4-7) - Military/Teachers")) {
						$tGroupsChanged[] = "CM:" . substr($rateGroupWithTourists[0], 2);
					} else {
						$tGroupsChanged[] = $rateGroupWithTourists[0];
					}
				}
			}
		}

		return $tGroupsChanged;
	}

	/**
	 * Mapping tour package and reducing the relational seats
	 *
	 * @param array $tourPackageId
	 * @param int $tourOperatorId
	 * @param string $date
	 * @param string $groupIndex
	 * @param string $isParent
	 * @param int $tourists
	 * @param array $tourPackageFamilyTimeFrom
	 *
	 * @return int
	 */
	public static function mapTourPackage($tourPackageIds, $tourOperatorId, $date, $groupIndex, $isParent, $tourists, $tourPackageFamilyTimeFrom): int
	{
		$parent = [];
		$child = [];

		if (count($tourPackageIds)) {

			$packageSlotTimes = static::getSlotTimes($tourOperatorId, $tourPackageIds, $date, $tourPackageFamilyTimeFrom);


			foreach ($packageSlotTimes as $item) {
				if ($item->group_index != 0) {
					if ($item->is_parent) {
						$parent[$item->tour_package_id][$item->group_index][] = $item;
					} else {
						$child[$item->tour_package_id][$item->group_index][] = $item;
					}
				}
			}
		}
		$totalTourists = 0;
		if (count($tourPackageIds) && !empty($parent) || !empty($child)) {
			if ($groupIndex != 0) {
				if ($isParent == 0) {
					$slotTimesTourists = $parent;
				} else {
					$slotTimesTourists = $child;
				}

				$packageGroupSums = [];

				foreach ($slotTimesTourists as $packageId => $groups) {
					$totalTourists = 0;

					if (isset($groups[$groupIndex])) {
						foreach ($groups[$groupIndex] as $slot) {
							$totalTourists += (int)$slot->tourists;
						}
					}

					$packageGroupSums[$packageId][$groupIndex] = $totalTourists;
				}

				$maxTotal = 0;
				if (count($packageGroupSums)) {
					$maxTotal = max(array_map('max', $packageGroupSums));
				}

				$totalTourists = $tourists + $maxTotal;

				if ($isParent == 0) {
					$totalTourists = 0;
				}
			} else {
				$totalTourists = 0;
			}
		}

		return $totalTourists;
	}

	/**
	 * Query to return the slot and tourist info
	 *
	 * @param mixed $tourOperatorId
	 * @param mixed $packageIds
	 * @param mixed $date
	 */
	public static function getSlotTimes($tourOperatorId, $packageIds, $date, $tourPackageFamilyTimeFrom)
	{
		$fields = [
			'tst.id',
			'tsd.tour_package_id',
			'tst.time_from',
			'tst.date',
			DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                    FROM tour_bookings_master AS tbm
                    WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                    AND tbm.tour_package_id IN (" . implode(',', $packageIds) . ")
                    GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists")
		];


		$slotTimes = DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->where('tsd.date', $date) // separator is not necessary in date
			->whereIn('tsd.tour_package_id', $packageIds)
			->whereIn('tst.time_from', $tourPackageFamilyTimeFrom)
			->where('tsd.status', 'Active')
			->where('tst.deleted_by', '=', '0')
			->where('tsd.deleted_by', '=', '0')
			->orderBy('tst.time_from');

		$slotTimes = $slotTimes->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tp.tour_operator_id', $tourOperatorId)
			->where('tp.deleted_by', '=', '0');


		$slotTimes = $slotTimes->get($fields);

		$packageRelationSetting = hasSetting('PACKAGE_RELATION');

		foreach ($slotTimes as $time) {
			if ($packageRelationSetting) {
				$year = Carbon::parse($time->date)->format('Y');
				$tourPackageFamily = TourPackageFamily::where('tour_package_id', $time->tour_package_id)
					->where('time_from', $time->time_from)
					->where('relation_year', $year)
					->first();

				$time->is_parent = $tourPackageFamily->is_parent ?? 0;
				$time->group_index = $tourPackageFamily->group_index ?? 0;
			} else {
				$time->is_parent = 0;
				$time->group_index = 0;
			}
		}

		return $slotTimes;
	}

	/**
	 * Get relational bookings of the slot
	 *
	 * @param int $timeSlotId
	 * @param int $tourPackageId
	 * @param string $status
	 *
	 *
	 * @return mixed
	 */
	public static function relationalPackagesBookings($timeSlotId, $tourPackageId, $status = ''): mixed
	{
		$userType = userType();

		$slotTime = TimeSlotService::getTimeSlot($timeSlotId);
		$year = Carbon::parse($slotTime->date)->format('Y');

		$familySelects = fn($field) => "IFNULL((
				SELECT tpf.$field
				FROM tour_package_families AS tpf
				WHERE tpf.tour_package_id = tsd.tour_package_id
					AND tst.time_from = tpf.time_from
					AND tpf.tour_package_id = $tourPackageId
					AND tpf.relation_year = $year
				GROUP BY tpf.tour_package_id
			), 0) AS $field";

		$groupIndexQuery = $familySelects('group_index');
		$isParentQuery = $familySelects('is_parent');

		$timeSlot = DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->select([
				'tst.tour_package_id',
				'tst.time_from',
				'tsd.date',
				DB::raw($groupIndexQuery),
				DB::raw($isParentQuery)
			])
			->where('tst.id', $timeSlotId)
			->first();

		$bookings = [];
		$data = [];
		if ($timeSlot && $timeSlot->is_parent == 1) {
			$date = $timeSlot->date;
			$timeFrom = $timeSlot->time_from;
			$groupIndex = $timeSlot->group_index;
			$isParent = $timeSlot->is_parent;
			$year = Carbon::parse($date)->format('Y');

			$tourPackageFamily = DB::select("SELECT *
				FROM tour_package_families
				WHERE relation_year = $year
				AND parent_package_id IN (
					SELECT parent_package_id
					FROM tour_package_families
					WHERE tour_package_id = $tourPackageId
						AND relation_year = $year
				)");

			$slotIds = [];
			if (count($tourPackageFamily)) {
				$packageIds = array_column($tourPackageFamily, 'tour_package_id');
				$parent = [];
				$child = [];

				$slotTimes = DB::table('tour_slot_times AS tst')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->where('tst.time_from', $timeFrom)
					->where('tsd.date', $date) // separator is not necessary in date
					->whereIn('tsd.tour_package_id', $packageIds)
					->where('tsd.status', 'Active')
					->where('tst.deleted_by', '=', '0')
					->where('tsd.deleted_by', '=', '0')
					->select(['tst.id as slotId', 'tst.time_from', 'tst.date', 'tst.tour_package_id'])
					->get();

				foreach ($slotTimes as $time) {
					$year = Carbon::parse($time->date)->format('Y');
					$tourPackageFamily = TourPackageFamily::where('tour_package_id', $time->tour_package_id)
						->where('time_from', $time->time_from)
						->where('relation_year', $year)
						->first();

					$time->is_parent = $tourPackageFamily->is_parent ?? 0;
					$time->group_index = $tourPackageFamily->group_index ?? 0;
				}

				foreach ($slotTimes as $item) {
					if ($item->group_index != 0) {
						if ($item->is_parent) {
							$parent[$item->tour_package_id][$item->group_index][] = $item;
						} else {
							$child[$item->tour_package_id][$item->group_index][] = $item;
						}
					}
				}

				if (!empty($parent) || !empty($child)) {
					if ($groupIndex != 0) {
						if ($isParent == 0) {
							$slotTimesTourists = $parent;
						} else {
							$slotTimesTourists = $child;
						}

						foreach ($slotTimesTourists as $groups) {
							if (isset($groups[$groupIndex])) {
								foreach ($groups[$groupIndex] as $slot) {
									$slotIds[] = (int)$slot->slotId;
								}
							}
						}
					}
				}

				$data['slotIds'] = $slotIds;
				if (!empty($slotIds)) {
					$data['bookings'] = self::getBookingsByStatus($status, $slotIds, $userType);
				}
			}
		}

		return $data;
	}

	private static function getBookingsByStatus($status, $slotIds, $userType)
	{
		$baseQuery = DB::table('tour_bookings_master AS tbm')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->whereIn('tbm.tour_slot_id', $slotIds)
			->where('tbt.deleted_by', 0)
			->groupBy('tbt.tour_booking_id')
			->orderBy('c.name', 'asc');

		if ($userType === 'Affiliate') {
			$baseQuery->where('tbm.customer_id', Auth::id());
		}

		$commonSelects = [
			'tbm.id',
			'tbm.cart_id',
			'tbm.viator_bookingRef AS tripadvisor_id',
			'tbm.get_your_guide_reference_booking as get_your_guide_booking_id',
			'tbm.ota_reference_booking as ota_booking_id',
			'tbm.name AS cust_name',
			'c.is_affiliate',
			'tbm.tourists',
			'tbm.total',
			'tbm.created_at AS booking_d_t',
			'tbm.status',
			'tbm.is_reserved_for_affiliate',
			'tbm.reference_booking',
			'tp.package_has_slots',
			'tp.package_has_waiver',
			'tp.package_has_permit',
			DB::raw("(SELECT SUM(t.amount) FROM transactions AS t WHERE tour_booking_id = tbm.id AND t.status = 'Completed') AS paid"),
			DB::raw("(SELECT GROUP_CONCAT(
            CONCAT(
                LEFT(tpr.rate_for, 1), ':',
                CASE 
                    WHEN tbt.infants > 0 THEN tbt.infants 
                    ELSE tbt.tourists 
                END,
                '^',
                tpr.rate_for
            )
            ORDER BY tpr.age_from DESC, tpr.age_to DESC
            SEPARATOR ' | '
			) 
			FROM tour_bookings_transact AS tbt
			INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id
			WHERE tbt.tour_booking_id = tbm.id 
			AND tbt.deleted_by = 0
    		) AS t_groups"),
			DB::raw('1 as relational_booking'),
		];

		if ($status === 'Cancelled') {
			$bookings = $baseQuery
				->where('tbm.status', '=', 'Cancelled')
				->get($commonSelects);
		} else {
			$bookings = $baseQuery
				->leftJoin('transactions as tr', 'tr.tour_booking_id', '=', 'tbm.id')
				->get(array_merge($commonSelects, [DB::raw("(SELECT SUM(t.amount) FROM transactions AS t WHERE tour_booking_id = tbm.id AND t.status = 'Completed' AND payment_type='1') AS paid_split"), 'tr.via']));
		}

		return $bookings;
	}

	/**
	 * Get relational bookings of the slot
	 *
	 * @param string $userType
	 *
	 * @return mixed
	 */
	public static function getPartnersData($userType): mixed
	{
		$loggedInUserId = Auth::id();

		$partnership = Partnership::where('current_user_id', $loggedInUserId)
			->where('current_user_type', $userType)
			->get();

		return $partnership;
	}

	public static function getActivePackages(string $userType)
	{
		$packages = TourPackage::where('status', 'Active')
			->when($userType == 'Affiliate', function ($query) {
				return $query->join('package_affiliate_assignments', 'tour_packages.id', '=', 'package_affiliate_assignments.package_id')
					->where('package_affiliate_assignments.affiliate_id', Auth::id());
			})
			->when(departmentPackage(), function ($query) {
				return $query->whereHas('departments', function ($subQuery) {
					$subQuery->where('department_id', Auth::user()->department_id);
				});
			})
			->select([
				'tour_packages.id',
				'tour_packages.name'
			])
			->get();

		return $packages;
	}
	/**
	 * Check if the affiliate has access to view all package bookings.
	 *
	 * @param int $packageId
	 * @param int|null $affiliateId
	 * @return bool
	 */
	public static function canViewAllPackageBookings(int $packageId, ?int $affiliateId = null): bool
	{
		$affiliateId = $affiliateId ?? Auth::id();

		return PackageAffiliateAssignment::where([
			['package_id', $packageId],
			['affiliate_id', $affiliateId],
			['show_all_package_bookings', Constants::STATUS_ONE]
		])->count();
	}

	/**
	 * Check if the affiliate has access to view all package bookings.
	 *
	 * @param int $packageId
	 * @param int|null $affiliateId
	 * @return bool
	 */
	public static function showAllPackageBookings(int $packageId, ?int $affiliateId = null): bool
	{
		$affiliateId = $affiliateId ?? Auth::id();

		return PackageAffiliateAssignment::where([
			['package_id', $packageId],
			['affiliate_id', $affiliateId],
			['show_all_package_bookings', 0]
		])->count();
	}

	/**
	 * Get the booked/reserved seats
	 *
	 * @param mixed $request
	 * @param string $userType
	 * @param int|null $excludeBookingId Optional booking ID to exclude from calculations (for editing)
	 */
	public static function getReservedSeats($request, $userType, $excludeBookingId = null)
	{
		$slotTime = TimeSlotService::getTimeSlot($request->tour_slot_id);
		$year = Carbon::parse($slotTime->date)->format('Y');
		$info = DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
			->join('states AS st', 'st.id', '=', 'ct.state_id')
			->where('tst.id', $request->tour_slot_id)
			->select([
				'tst.time_slot_seats as seats',
				'tsd.date',
				'tsd.tour_package_id',
				'tst.time_from',
				'tst.time_to',
				'ct.state_id',
				'st.permit_fee',
				'tp.service_commission_percentage',
				'tp.stripe_commission_percentage',
				't_o.tenant_id',
				'tp.is_group_rate_enabled',
				't_o.name AS tour_operator',
				't_o.phone_number AS tour_op_phone',
				't_o.email AS tour_op_email',
				't_o.website AS tour_op_website',
				't_o.logo AS tour_op_logo',
				't_o.cancellation_policy',
				'tp.tour_operator_id',
				'tp.name AS tour_package',
				'tp.name AS package_name',
				'tp.important_notes',
				'tp.overbook_seats',
				'tp.arrival_time_duration',
				'tp.package_has_slots',
				'tp.package_has_waiver',
				'tp.package_has_permit',
				'tp.things_to_bring',
				'tp.email as packageEmail',
				'tp.from_name as packageFromName',
				'tp.from_email as packageFromEmail',
				'tp.api_key as packageApiKey',
				'tp.attach_waiver_pdf',
				'tp.waiver_pdf',
				DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
				DB::raw("(SELECT SUM(tbm.tourists)
				FROM tour_bookings_master AS tbm
				WHERE tbm.tour_slot_id = tst.id
					AND tbm.status != 'Cancelled'
				GROUP BY tbm.tour_slot_id) AS booked_seats"),
				DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
                    FROM tour_bookings_master AS tbm2
                    WHERE tbm2.tour_slot_id = tst.id
                        AND tbm2.status != 'Cancelled'
						AND tbm2.customer_id = " . $request->affiliate_id . 
						($excludeBookingId ? " AND tbm2.id != " . $excludeBookingId : "") . "
                    GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_booked_seats"),
				DB::raw("IFNULL((SELECT DISTINCT(tpf.is_parent)
					FROM tour_package_families AS tpf
					WHERE tpf.tour_package_id = tst.tour_package_id
						AND tst.time_from = tpf.time_from
						AND tpf.relation_year = $year), 0) AS is_parent"),
				DB::raw("IFNULL((SELECT DISTINCT(tpf.group_index)
					FROM tour_package_families AS tpf
					WHERE tpf.tour_package_id = tst.tour_package_id
						AND tst.time_from = tpf.time_from
						AND tpf.relation_year = $year), 0) AS group_index")
			])
			->first();

		if ($info && $userType == 'Affiliate') {
			$desiredSlot = DB::table('tour_slot_times_affiliate as tsta')
				->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
				->where('ast.affiliate_id', $request->affiliate_id)
				->where('tsta.tour_package_id', $info->tour_package_id)
				->where('tsta.time_from', $info->time_from)
				->where('tsta.status', 'Active')
				->where('tsta.deleted_by', 0)
				->select('ast.seat_limit')
				->first();

			$info->afl_seats = $desiredSlot ? $desiredSlot->seat_limit : 0;

			$year = Carbon::parse($info->date)->format('Y');

			$relationPackage = [];
			$totalTourists = 0;
			/* START - mapping tour package and reducing the relational seats */
			if (hasSetting('PACKAGE_RELATION')) {
				$tourPackageFamily = DB::select("SELECT *
					FROM tour_package_families
					WHERE relation_year = $year
					AND parent_package_id IN (
						SELECT parent_package_id
						FROM tour_package_families
						WHERE tour_package_id = $info->tour_package_id
							AND relation_year = $year
					)");

				if (count($tourPackageFamily)) {
					$relationPackage = array_filter($tourPackageFamily, function ($obj) use ($info) {
						return $obj->tour_package_id == $info->tour_package_id && $obj->group_index == $info->group_index;
					});

					$totalTourists = TouristService::countTotalTourists($tourPackageFamily, $info);
				}
			}
			/* END */

			// Actual seats calculation
			$bookedSeatsPerSlot = ((int) $totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $info->booked_seats;
			$totalSeatsPerSlot = $info->seats;

			// Affiliate seats calculation
			$tourists = (int) $info->afl_booked_seats;
			$seats = isset($desiredSlot->seat_limit) ? (($info->seats > 0) ? (($info->seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($info->seats)) : 0) : 0;

			if ($bookedSeatsPerSlot >= $totalSeatsPerSlot || $tourists >= $seats) {
				$availableSeats = 0;
			} else {
				$globalTotal = $totalSeatsPerSlot - $bookedSeatsPerSlot;
				$affliateTotal = $seats - $tourists;
				$vacant = min($globalTotal, $affliateTotal);
				if ($vacant >= $seats) {
					$availableSeats = $seats;
				} else {
					$availableSeats = $vacant;
				}
			}

			if ($availableSeats <= 0) {
				throw new Exception("The selected slot is already full. No seats are available for selected slot.");
			} else if ($request->seats > $availableSeats) {
				throw new Exception("There are only " . $availableSeats . " seats available. Please adjust your selection accordingly.");
			}
		}

		return $info;
	}

	/**
	 * Check seat availability and return appropriate error messages if full.
	 *
	 * @param object $info    The booking information including seats and availability.
	 * @param object $request The user request containing the number of seats requested.
	 * @return mixed          Returns an error response if seats are unavailable, otherwise null.
	 */
	public static function checkSeatAvailability(object $info, object $request)
	{
		try {
			$tourists = (int) $info->afl_booked_seats;
			$seats = (int) $info->afl_seats;
			$availableSeats = $tourists > $seats ? 0 : $seats - $tourists; // Ensures availableSeats is never negative

			if ($availableSeats == 0) {
				throw new Exception('Seats are not available for selected slot.');
			}

			if ($availableSeats <= 0) {
				throw new Exception('The selected slot is already full. No seats are available for selected slot.');
			}

			if ($request->seats > $availableSeats) {
				throw new Exception("$availableSeats seat(s) are only available for this slot. You can't add more than $seats seat(s).");
			}

			return null; // No errors, proceed with booking
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
}
