<?php

namespace App\Services\Invoice;

use Illuminate\Support\Facades\DB;

class InvoiceDataService
{
	/**
	 * Query to get the bookings from specific time frame.
	 *
	 * @param object|null $invoice
	 * @param array|false $ids
	 * @return string
	 */
	public static function bookings($invoice = null, $ids = false): string
	{
		$departmentPackage = departmentPackage();

		$sql = DB::table('invoice_bookings AS ib')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'ib.tour_booking_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->where('t.status', 'Completed')
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tp.id', $departmentPackage);
			})
			->select([
				'tbm.id as bookingid',
				'tbm.status as status',
				'tbm.total as amount',
				'tbm.liability_waiver_signing as liability_waiver_signing',
				DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
				DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
				DB::raw(
					"IFNULL((SELECT GROUP_CONCAT(details SEPARATOR ',\n')
					FROM tour_booking_history AS tbh
					WHERE tbh.tour_booking_id = tbm.id
					AND tbh.operation = 'Reference Booking'), 'N/A') AS reference_booking"
				),
				'to.logo as touroperatorlogo',
				'c.name as name',
				'tbt.tourists as tourists',
				'tbt.infants as infants',
				'tp.name as package',
				'tpr.rate_for',
				'tpr.age_from',
				'tpr.age_to',
				't.status as payment_status',
				'tst.time_to',
				'tst.time_from',
				// Note: despite the column name, this also folds in 'addons' charge_type rows —
				// both are extra charges on top of tbm.total, and users.admin.invoice.show adds
				// this straight onto 'amount', so excluding addons here silently dropped them from
				// the per-booking row (while the invoice's own top-level amount was never filtered
				// this way and already included them, producing a mismatched Invoice Details modal).
				DB::raw('(SELECT SUM(case operation when "Payment" then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			        WHERE tbh.tour_booking_id = tbm.id AND `tbh`.`charge_type` IN ("custom_charge", "addons")) AS `custom_charge_amount`'),
				DB::raw('(SELECT SUM(case operation when "Refund" then  `tbh`.`ticket_cost` else 0 end)  FROM tour_booking_history AS tbh
			        WHERE tbh.tour_booking_id = tbm.id AND `tbh`.`charge_type` IN ("custom_charge", "addons")) AS `custom_charge_refund_amount`')
			]);
		if ($ids) {
			$sql->whereIn('ib.invoice_id', $ids);
		} else {
			$sql->where('ib.invoice_id', $invoice->id);
		}

		$bookings = $sql->orderBy('tsd.date', 'DESC')->get();
		return $bookings;
	}

	/**
	 * Stub the data booking data to display in invoice section.
	 *
	 * @param string $invoice
	 * @return array
	 */
	public static function invoiceStub(string $invoice): array
	{
		$result = json_decode($invoice, true);
		$initialArray = array();

		foreach ($result as $resultValue) {
			$ageGroup = str_replace(' ', '_', $resultValue['rate_for']);

			if (!isset($initialArray[$resultValue['bookingid']])) {
				$ageGroups = [];
				$ageGroups[$ageGroup] = [
					'tourists' => 0,
					'infants' => 0
				];

				$ageGroups[$ageGroup]['tourists'] += $resultValue['tourists'];
				$ageGroups[$ageGroup]['infants'] += $resultValue['infants'];
				$ageGroups[$ageGroup]['rate_for'] = $resultValue['rate_for'];
				$resultValue['ageGroups'] = $ageGroups;
				$resultValue['booking_status'] = $resultValue['status'];

				$initialArray[$resultValue['bookingid']][] = $resultValue;
			} else {
				$previousData = $initialArray[$resultValue['bookingid']];
				if (isset($previousData) && !isset($previousData[0]['ageGroups'][$ageGroup])) {
					$ageGroups[$ageGroup] = [
						'tourists' => 0,
						'infants' => 0
					];

					$ageGroups[$ageGroup]['tourists'] += $resultValue['tourists'];
					$ageGroups[$ageGroup]['infants'] += $resultValue['infants'];
					if (!isset($ageGroups[$ageGroup]['rate_for'])) {
						$ageGroups[$ageGroup]['rate_for'] = $resultValue['rate_for'];
					}

					$resultValue['ageGroups'] = (isset($previousData[0]['ageGroups'])) ? $previousData[0]['ageGroups'] + $ageGroups : $ageGroups;
				}

				$resultValue['booking_status'] = $resultValue['status'];
				if(!isset($resultValue['ageGroups'])){
					$resultValue['ageGroups'] = $previousData[0]['ageGroups'];
				}
				$initialArray[$resultValue['bookingid']][0] = $resultValue;
			}
		}

		$touristGrandTotal = 0;
		$infantsGrandTotal = 0;
		$sortInitialValueArray = array();
		foreach ($initialArray as $key => $element) {
			$touristTotal = 0;
			$infantsTotal = 0;
			if (!empty($element[0]['ageGroups'])) {
				foreach ($element[0]['ageGroups'] as $ageGroup) {
					$touristTotal += $ageGroup['tourists'];
					$infantsTotal += $ageGroup['infants'];
				}
			}

			$touristGrandTotal += $touristTotal;
			$infantsGrandTotal += $infantsTotal;
			$element[0]['InfantsTotal'] = $infantsTotal;
			$element[0]['TouristTotal'] = $touristTotal;
			$sortInitialValueArray[] = $element[0];
		}

		$vars['invoice_data'] = $sortInitialValueArray;
		$vars['touristGrandTotal'] = $touristGrandTotal;
		$vars['infantsGrandTotal'] = $infantsGrandTotal;

		return $vars;
	}
}
