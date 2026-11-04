<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Models\BookingTransact;
use Illuminate\Http\Request;
use App\Repositories\Report\ReportRepository;
use App\Repositories\Report\ReportRepositoryV2;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SummaryReportController extends Controller
{
	protected $reportRepository;

	public function __construct(ReportRepository $reportRepository)
	{
		parent::__construct();

		$this->reportRepository = $reportRepository;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return array
	 */
	public function bookingSummaryList(string $fromDate = "", string $toDate = "", int $tourPackageId = 0)
	{
		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		$qry = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tsd.deleted_by', '=', '0')
			->where('tsd.date', '=', $fromDate)
			->select([
				'tbm.id as bookingid',
				'tbm.name as name',
				'tbm.created_at as createdate',
				'tbm.status as status',
				'tbm.total as amount',
				'tbm.tourists as tourists',
				'tsd.date as bookingdate',
				'tst.time_slot_seats as seats',
				'tst.time_from as timefrom',
				'tst.time_to as timeto',
				'tp.id as packageid',
				'tp.name as packagename',
				'to.logo as touroperatorlogo',
				DB::raw('IFNULL(t.status,"Pending") as Payment'),
				DB::raw('IFNULL(t.via,"Pending") as paywith')/*'t.via as paywith'*/
			])
			->orderBy('createdate', 'DESC');

		if ($tourPackageId && $tourPackageId !== 0) {
			$qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		$qry = $qry->get();
		$result = json_decode($qry, true);

		$sortedData = array();
		foreach ($result as $element) {
			$timestamp = strtotime($element['bookingdate']);
			$date =  date("Y-m-d", $timestamp);

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
				$formatedTimeFrom = date('h:i A', strtotime($formatTimeFromRaw));
				$formatTimeToRaw = $element['timeto'];
				$formatedTimeTo = date('h:i A', strtotime($formatTimeToRaw));

				$newArrayKey = $formatedTimeFrom . "-" . $formatedTimeTo;
				if (!isset($mediumSortedData[$key][$newArrayKey])) {
					$mediumSortedData[$key][$newArrayKey] = array($element);
				} else {
					$mediumSortedData[$key][$newArrayKey][] = $element;
				}
			}
		}

		$newSortedData = [];
		if ($mediumSortedData) {
			foreach ($mediumSortedData as $key => $sortedDataValueMain) {
				foreach ($sortedDataValueMain as $key2 => $sortedDataValue) {
					$amountTotal = 0;
					$refundTotal = 0;
					$profit = 0;
					$totalSeats = 0;
					$bookedSeatsTotal = 0;
					$cancelledSeatsTotal = 0;
					$availableSeatsTotal = 0;
					$imageName = "";

					foreach ($sortedDataValue as $sortedDataElementsValue) {
						if ($sortedDataElementsValue['status'] == "Booked") {
							$amountTotal += $sortedDataElementsValue['amount'];
						} elseif ($sortedDataElementsValue['status'] == "Cancelled") {
							$refundTotal += $sortedDataElementsValue['amount'];
							$amountTotal += $refundTotal;
						}

						$profit = $amountTotal - $refundTotal;
						$totalSeats = $sortedDataElementsValue['seats'];

						if ($sortedDataElementsValue['status'] == "Booked") {
							$bookedSeatsTotal += $sortedDataElementsValue['tourists'];
						} else if ($sortedDataElementsValue['status'] == "Cancelled") {
							$cancelledSeatsTotal += $sortedDataElementsValue['tourists'];
						}

						$availableSeatsTotal = $totalSeats - $bookedSeatsTotal;
						$imageName = $sortedDataElementsValue['touroperatorlogo'];
						$bookingid = $sortedDataElementsValue['bookingid'];
					}

					$newFilteredData = array();
					$newFilteredData['bookingid'] = $bookingid;
					$newFilteredData['bookingdate'] = $key;
					$newFilteredData['timeslot'] = $key2;
					$newFilteredData['amountTotal'] = $amountTotal;
					$newFilteredData['refundTotal'] = $refundTotal;
					$newFilteredData['profit'] = $profit;
					$newFilteredData['seats'] = $totalSeats;
					$newFilteredData['bookedSeatsTotal'] = $bookedSeatsTotal;
					$newFilteredData['cancelledSeatsTotal'] = $cancelledSeatsTotal;
					$newFilteredData['availableSeats'] = $availableSeatsTotal;
					$newFilteredData['imageName'] = $imageName;
					$newFilteredData['createdate'] =  $sortedDataElementsValue['createdate'];
					$newSortedData[$key][$key2] = $newFilteredData;
				}
			}
		}

		return $newSortedData;
	}

	/**
	 * Cancel Booking Report
	 *
	 * @return mixed
	 */
	public function cancelbookingreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
	{
		try {
			return $this->reportRepository->cancelbookingreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $export);
		} catch (Exception $exception) {
			Log::error($exception);

			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Daily Activity Report
	 *
	 * @return mixed
	 */
	public function dailyactivityreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0, string $viewBy = "tourdate", int $export = 0)
	{
		try {
			return $this->reportRepository->dailyactivityreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy, $export);
		} catch (Exception $exception) {
			Log::error($exception);

			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Daily activity card block
	 */
	public function dailyActivityCardBlock1($fromDate, $toDate, $tourOperatorId, $tourPackageId): array
	{
		$via = "Card";

		$bookingTransactsSql = $this->bookingTransactionsData($via, $fromDate, $toDate, $tourOperatorId, $tourPackageId);

		$bookingTransactsData = DB::select($bookingTransactsSql);

		$totalRefundedProcessingPermitFees = 0;
		$affiliatePaymentTotal = 0;
		$nonAffiliatePaymentTotal = 0;
		foreach ($bookingTransactsData as $value) {
			if ($value->is_affiliate == "No") {
				$nonAffiliatePaymentTotal += $value->base_amount;
			} else {
				$affiliatePaymentTotal += $value->base_amount;
			}

			if ($value->refund_permitfee_status != '') {
				$refundPermitFeeStatuses = json_decode($value->refund_permitfee_status);
				foreach ($refundPermitFeeStatuses as $refundPermitFeeStatus) {
					if ($refundPermitFeeStatus->status == 'Y') {
						$totalRefundedProcessingPermitFees += $refundPermitFeeStatus->processing_fees;
					}
				}
			}
		}

		// total fee amount including the service commision
		$cardFeeTotal = DB::select('select ifnull(sum(aps.fee_amount), 0) as card_fee_amount from (' . $bookingTransactsSql . ') as aps');

		$totalSql = 'select
			`tbm`.`id` as `tour_booking_id`,
			`tbm`.`status` as `status`,
			`tbm`.`comments`,
			`tbm`.`created_by` as `createdBy`,
			`tbm`.`created_by_user_type` as `TourUserType`,
			`tbh`.`details`,
			SUM(JSON_EXTRACT(`tbh`.`details`, "$.amount")) as `completed_amount`,
			ifnull(SUM(`t`.`fee_amount`), 0) as `fee_amount`,
			sum(`tbm`.`service_commission`) as `service_commission`,
			DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
			`tbm`.`name` as `customer`,
			`tbm`.`email`,
			`tbm`.`phone_number`,
			`tst`.`time_from`,
			`tst`.`time_to`,
			`tsd`.`date`,
			`tbm`.`booking_total`
		from
			`tour_booking_history` as `tbh`
			inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id` and `tbm`.`status` != "Cancelled"
			inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
			inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
			inner join `transactions` as `t` on `t`.`tour_booking_id` = `tbm`.`id` and `t`.`status` = "Completed"';

		$totalSql .= ' where
			`t`.`status` = "Completed"
			and `tbh`.`operation` = "Payment"
			and `tbm`.`status` != "Cancelled"
			and `t`.`via` = "Card"
			and JSON_EXTRACT(`tbh`.`details`, "$.payment_type") = "' . $via . '"
			 ';

		$paymentTotalSqlMain = $totalSql;

		$paymentTotalSqlMain .= ' and `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '"';

		if ($tourPackageId) {
			$paymentTotalSqlMain .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$paymentTotalSqlMain .= ' and `tp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$paymentTotalSqlMain .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}

		$paymentTotalSql = $paymentTotalSqlMain . ' GROUP BY `tbm`.`id`';

		$cardPaymentData = DB::select($paymentTotalSql);

		// count the total number of bookings
		$cardBookingCount = count($cardPaymentData);

		// total gross amount based on complete amount
		$cardPaymentTotal = DB::select('select ifnull(sum(aps.completed_amount), 0) as card_total_amount from (' . $paymentTotalSql . ') as aps');

		// total service commission amount for all bookings
		$cardServiceCommission = DB::select('select sum(aps.service_commission) as card_service_commission from (' . $paymentTotalSql . ') as aps');

		$paymentTotalSqlMain .= ' and `tbm`.`comments` LIKE "%#%"';

		// farharbor total amount based on the special comments
		$cardFarharborTotal = DB::select('select ifnull(sum(aps.completed_amount), 0) as card_farharbour_amount from (' . $paymentTotalSqlMain . ') as aps');

		$refundTotalSql = 'select
			0 as completed_amount,
			`tbm`.`id` as `tour_booking_id`,
			`tbm`.`status` as `status`,
			`tbm`.`created_by` as `createdBy`,
			`tbh`.`details`,
			json_unquote(json_extract(`tbh`.`details`, "$.refund_type")) as refund_type,
			json_extract(`tbh`.`details`, "$.amount") as refund_amount,
			`tbm`.`created_by_user_type` as `TourUserType`,
			DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as created_at,
			`tbm`.`name` as `customer`,
			`tbm`.`email`,
			`tbm`.`phone_number`,
			`tst`.`time_from`,
			`tst`.`time_to`,
			`tsd`.`date`,
			`tbm`.`booking_total`,
			`tbh`.`operation`,
			`c`.`is_affiliate`
		from
			`tour_booking_history` as `tbh`
			inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
			inner join `tour_bookings_transact` as `tbt` on `tbt`.`tour_booking_id` = `tbh`.`tour_booking_id`
			inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
			inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
			inner join `customers` as `c` on `c`.`id` = `tbm`.`customer_id`
		where
			(
				`tbh`.`operation` = "Refund"
				or `tbh`.`operation` = "Cancelled"
			)
			and JSON_CONTAINS(
				`tbh`.`details`, json_quote("' . $via . '"), "$.payment_type"
			)
			and (
				not JSON_CONTAINS(
					`tbh`.`details`, json_quote("Don\'t Refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbh`.`details`, json_quote("don\'t refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbh`.`details`, json_quote("fareharbor : don\'t refund"), "$.refund_type"
				)
			) ';

		if ($tourPackageId) {
			$refundTotalSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}

		$refundTotalSql = $refundTotalSql . ' and `tbh`.`operation_at` between  "' . $fromDate . ' 00:00:00" and "' . $toDate . ' 23:59:59" ';

		// total refund amount based on refund amount for card booking only
		$refundTotal = DB::select('select ifnull(sum(aps.refund_amount), 0) as card_refund_amount from (' . $refundTotalSql . ') as aps');

		// total net amount based on above calculations
		$finalTotal = $cardPaymentTotal[0]->card_total_amount - $refundTotal[0]->card_refund_amount;

		$refundTotalSqlbook = $refundTotalSql . ' GROUP BY `tbm`.`id`';

		$refundDatabooking = DB::select($refundTotalSqlbook);

		$reverseRefund = 0;
		foreach ($refundDatabooking as $value) {
			if ($value->is_affiliate == "No" && ($value->refund_type == "Partial Refund" || $value->refund_type == "100 % Refund" || $value->refund_type == "Full Refund")) {
				$convertedAmount = ($value->refund_amount / 106 * 100);
				$destinationAmount = ($convertedAmount - ($convertedAmount * 6 / 100));
				$destinationAmount = $convertedAmount - $destinationAmount;
				$destinationAmount = round($destinationAmount, 2);
				$value->service_commission = $destinationAmount;
			} else {
				$value->service_commission = 0;
			}
			$reverseRefund += $value->service_commission;
		}

		$nonRefundedServiceFee = $this->nonRefundedServiceFee($tourPackageId, $tourOperatorId, $cardPaymentData);

		return [
			'total_bookings' => $cardBookingCount,
			'gross' => $cardPaymentTotal[0]->card_total_amount - $cardFarharborTotal[0]->card_farharbour_amount,
			'farharbor_total' => $cardFarharborTotal[0]->card_farharbour_amount,
			'total_software_fees' => $cardServiceCommission[0]->card_service_commission, // - $totalRefundedProcessingPermitFees,
			'refunded_software_fees' => $reverseRefund, // This is based on operation date
			'non_refunded_software_fees' => $nonRefundedServiceFee, // This is based on the card booking id's
			'refunds' => $refundTotal[0]->card_refund_amount,
			'processing_charge' => $cardFeeTotal[0]->card_fee_amount,
			'net' => $finalTotal,
			'positive_net' => $finalTotal > 0 ? $finalTotal : 0,
			'affiliate_base_amount' => $affiliatePaymentTotal,
			'non_affiliate_base_amount' => $nonAffiliatePaymentTotal
		];
	}

	/**
	 * Daily Activity Booking Transactions Query
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 */
	public function bookingTransactionsData($via, $fromDate, $toDate, $tourPackageId, $tourOperatorId)
	{
		$bookingTransacts = 'select
			`tbt`.`tourists`,
			`tbt`.`processing_charge_percentage`,
			sum(`tbt`.`tourists` * (`tbt`.`rate` + `tbt`.`additional_charge` + `tbt`.`permit_fee`)) as `base_amount`,
			sum(
				(
					(
						`tbt`.`tourists` * (`tbt`.`rate` + `tbt`.`additional_charge` + `tbt`.`permit_fee`)
					) * `tbt`.`processing_charge_percentage`
				) / 100
			) as `fee_amount`,
			`tbm`.`service_commission`,
			`tbt`.`permit_fee`,
			`tbt`.`additional_charge`,
			`tbt`.`state_tax`,
			`tbt`.`rate`,
			`tbt`.`total`,
			`tbt`.`refund_permitfee_status`,
			`tbm`.`id` as `tour_booking_id`,
			`tbm`.`status` as `status`,
			`tbm`.`created_by` as `createdBy`,
			`tbm`.`created_by_user_type` as `TourUserType`,
			`t`.`transaction_number`,
			`t`.`via`,
			DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as `created_at`,
			`tbm`.`name` as `customer`,
			`tbm`.`email`,
			`tbm`.`phone_number`,
			`tst`.`time_from`,
			`tst`.`time_to`,
			`tsd`.`date`,
			`t`.`status` as `payment_status`,
			`tp`.`name` as `package_name`,
			`to`.`name` as `tour_operator_name`,
			`tbm`.`tour_package_id`,
			`c`.`is_affiliate`,
			`tbm`.`discount2_value`
		from
			`tour_bookings_transact` as `tbt`
			inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbt`.`tour_booking_id`
			inner join `transactions` as `t` on `t`.`tour_booking_id` = `tbt`.`tour_booking_id`
			inner join `tour_booking_history` as `tbh` on `tbh`.`tour_booking_id` = `tbt`.`tour_booking_id`
			inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
			inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
			inner join `tour_operators` as `to` on `to`.`id` = `tp`.`tour_operator_id`
			inner join `customers` as `c` on `c`.`id` = `tbm`.`customer_id`
		where
			`t`.`status` = "Completed"
		    and `tbt`.`deleted_by` = "0"
			and `tbh`.`operation` = "Payment"
			and `tbm`.`status` != "Cancelled"
			/* and `tbt`.`processing_charge_percentage` > 0 */
			and `t`.`via` = "' . $via . '"
			and `tsd`.`date` between "' . $fromDate . '" and "' . $toDate . '" ';

		if ($tourPackageId) {
			$bookingTransacts .= ' and `tbm`.`tour_package_id` = "' . $tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$bookingTransacts .= ' and `tp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$bookingTransacts .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}

		$bookingTransactsSql = $bookingTransacts . ' GROUP BY `tbm`.`id`';

		return $bookingTransactsSql;
	}

	/**
	 * Non refunded software fee only for selected date
	 * For daily activity report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 */
	public function nonRefundedServiceFee($tourPackageId, $tourOperatorId, $cardPaymentData)
	{
		$via = "Card";

		$refundTotalSql = 'select
			0 as completed_amount,
			`tbm`.`id` as `tour_booking_id`,
			`tbm`.`status` as `status`,
			`tbm`.`created_by` as `createdBy`,
			`tbh`.`details`,
			json_unquote(json_extract(`tbh`.`details`, "$.refund_type")) as refund_type,
			json_extract(`tbh`.`details`, "$.amount") as refund_amount,
			`tbm`.`created_by_user_type` as `TourUserType`,
			DATE_FORMAT(`tbm`.`created_at`, "%Y-%m-%d") as created_at,
			`tbm`.`name` as `customer`,
			`tbm`.`email`,
			`tbm`.`phone_number`,
			`tst`.`time_from`,
			`tst`.`time_to`,
			`tsd`.`date`,
			`tbm`.`booking_total`,
			`tbh`.`operation`,
			`c`.`is_affiliate`
		from
			`tour_booking_history` as `tbh`
			inner join `tour_bookings_master` as `tbm` on `tbm`.`id` = `tbh`.`tour_booking_id`
			inner join `tour_bookings_transact` as `tbt` on `tbt`.`tour_booking_id` = `tbh`.`tour_booking_id`
			inner join `tour_slot_times` as `tst` on `tst`.`id` = `tbm`.`tour_slot_id`
			inner join `tour_slot_dates` as `tsd` on `tsd`.`id` = `tst`.`tour_slot_id`
			inner join `tour_packages` as `tp` on `tp`.`id` = `tbm`.`tour_package_id`
			inner join `customers` as `c` on `c`.`id` = `tbm`.`customer_id`
		where
			(
				`tbh`.`operation` = "Refund"
				or `tbh`.`operation` = "Cancelled"
			)
			and JSON_CONTAINS(
				`tbh`.`details`, json_quote("' . $via . '"), "$.payment_type"
			)
			and (
				not JSON_CONTAINS(
					`tbh`.`details`, json_quote("Don\'t Refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbh`.`details`, json_quote("don\'t refund"), "$.refund_type"
				)
				and not JSON_CONTAINS(
					`tbh`.`details`, json_quote("fareharbor : don\'t refund"), "$.refund_type"
				)
			) ';

		if ($tourPackageId) {
			$refundTotalSql .= ' and `tp`.`id` = "' . $tourPackageId . '" ';
		}

		if (session('userType') == 'Tour Operator') {
			$refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . Auth::user()->tour_operator_id . '" ';
		} else {
			$refundTotalSql .= ' and `tp`.`tour_operator_id` = "' . $tourOperatorId . '" ';
		}

		// Below line is to find the refund of payment data so we can calculate the non refunded service commission
		$escapingQuery = "'" . implode("','", array_column($cardPaymentData, 'tour_booking_id')) . "'";

		$refundTotalSql = $refundTotalSql . ' and `tbm`.`id` in  (' . $escapingQuery . ')';

		$refundTotalSqlbook = $refundTotalSql . ' GROUP BY `tbm`.`id`';

		$refundDatabooking = DB::select($refundTotalSqlbook);

		$reverseRefund = 0;
		foreach ($refundDatabooking as $value) {
			if ($value->is_affiliate == "No" && ($value->refund_type == "Partial Refund" || $value->refund_type == "Permitfees Refund")) {
				$convertedAmount = ($value->refund_amount / 106 * 100);
				$destinationAmount = ($convertedAmount - ($convertedAmount * 6 / 100));
				$destinationAmount = $convertedAmount - $destinationAmount;
				$destinationAmount = round($destinationAmount, 2);
				$reverseRefund += $destinationAmount;
			}
		}

		return $reverseRefund;
	}

	/**
	 * Calculation total processing fees for daily activity report
	 */
	private function calculateprocessingfees($fromDate, $toDate, $tourPackageId, $tourOperatorId)
	{
		$bookingtransacts = DB::table('tour_bookings_transact AS tbt')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
			->join('transactions AS t', 't.tour_booking_id', '=', 'tbt.tour_booking_id')
			->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=', 'tbt.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS to', 'to.id', '=', 'tp.tour_operator_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->where('t.status', 'Completed')
			->where('tbh.operation', 'Payment')
			->where('tbt.processing_charge_percentage', '>', 0)
			->whereIn('t.via', array('Card', 'Invoiced'))
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->select(
				'tbt.tour_booking_id',
				'tbt.tourists',
				'tbt.processing_charge_percentage',
				'tbt.permit_fee',
				'tbt.additional_charge',
				'tbt.state_tax',
				'tbt.rate',
				'tbt.total',
				'tbt.refund_permitfee_status',
				'tbm.id as tour_booking_id',
				'tbm.status as status',
				'tbm.created_by as createdBy',
				'tbm.created_by_user_type as TourUserType',
				't.transaction_number',
				't.via',
				DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
				'tbm.name AS customer',
				'tbm.email',
				'tbm.phone_number',
				'tst.time_from',
				'tst.time_to',
				'tsd.date',
				't.status as payment_status',
				'tp.name as package_name',
				'tbm.service_commission',
				'to.name as tour_operator_name',
				'tbm.tour_package_id'
			);

		if ($tourPackageId && $tourPackageId !== 0) {
			$bookingtransacts = $bookingtransacts->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if (session('userType') == 'Tour Operator') {
			$bookingtransacts = $bookingtransacts->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$bookingtransacts = $bookingtransacts->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$bookingtransacts = $bookingtransacts->get();
		$bookingtransacts = json_decode($bookingtransacts, true);
		$bookingtransactscash = DB::table('tour_bookings_transact AS tbt')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
			->join('transactions AS t', 't.tour_booking_id', '=', 'tbt.tour_booking_id')
			->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=', 'tbt.tour_booking_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS to', 'to.id', '=', 'tp.tour_operator_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->where('t.status', 'Completed')
			->where('tbh.operation', 'Payment')
			->where('tbt.processing_charge_percentage', '>', 0)
			->where('t.via', 'Cash')
			->whereBetween('tbh.operation_at', [$fromDate, $toDate])
			->select(
				'tbt.tour_booking_id',
				'tbt.tourists',
				'tbt.processing_charge_percentage',
				'tbt.permit_fee',
				'tbt.additional_charge',
				'tbt.state_tax',
				'tbt.rate',
				'tbt.total',
				'tbt.refund_permitfee_status',
				'tbm.id as tour_booking_id',
				'tbm.status as status',
				'tbm.created_by as createdBy',
				'tbm.created_by_user_type as TourUserType',
				't.transaction_number',
				't.via',
				DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
				'tbm.name AS customer',
				'tbm.email',
				'tbm.phone_number',
				'tst.time_from',
				'tst.time_to',
				'tsd.date',
				't.status as payment_status',
				'tp.name as package_name',
				'tbm.service_commission',
				'to.name as tour_operator_name',
				'tbm.tour_package_id'
			);

		if ($tourPackageId && $tourPackageId !== 0) {
			$bookingtransactscash = $bookingtransactscash->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if (session('userType') == 'Tour Operator') {
			$bookingtransactscash = $bookingtransactscash->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$bookingtransactscash = $bookingtransactscash->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$bookingtransactscash = $bookingtransactscash->get();
		$bookingtransactscash = json_decode($bookingtransactscash, true);

		$bookingTransactsArray = array_merge($bookingtransactscash, $bookingtransacts);

		$processingFeesData = array();
		$cashProcessingFeeAmount = 0;
		$invoicedProcessingFeeAmount = 0;
		$wireTransferProcessingFeeAmount = 0;
		$Id = [];

		foreach ($bookingTransactsArray as $key1 => $value) {
			$processingFees = 0;
			$Id[] = $value['tour_booking_id'];
			if ($value['refund_permitfee_status'] != '') {
				$refundpermitFeeStatuses = json_decode($value['refund_permitfee_status']);
				$touritsts = $value['tourists'];
				$totalRefundedProcessingPermitfees = 0;

				foreach ($refundpermitFeeStatuses as $refundpermitFeeStatus) {
					if ($refundpermitFeeStatus->status == 'Y') {
						$totalRefundedProcessingPermitfees += $refundpermitFeeStatus->processing_fees;
					}
				}

				$processingFees = (((($touritsts) * ($value['rate'] +
					$value['additional_charge'] +
					$value['permit_fee']
				)) * (float)$value['processing_charge_percentage']) / 100) - $totalRefundedProcessingPermitfees;
			} else if ($value['processing_charge_percentage'] > 0) {
				$Id[] = $value['tour_booking_id'];
				$uniqueId = array_unique($Id);

				foreach ($uniqueId as $key => $ids) {
					$BookingTransact = BookingTransact::where('tour_booking_id', $ids)->get();
					foreach ($BookingTransact as $va) {
						$processingFees += (($va->tourists * ($va->rate +
							$va->additional_charge +
							$va->permit_fee
						)) * (float)$va->processing_charge_percentage) / 100;
					}
				}
			}

			if ($value['via'] == "Card") {
				$processingFeesData['Card'][$value['package_name']]['ProcessingFeesAmount'] = $processingFees;
			}

			if ($value['via'] == "Cash") {
				$CaIds = [];
				$CaIds[] = $value['tour_booking_id'];
				$uniqueId = array_unique($CaIds);
				$processingFeesss = 0;

				foreach ($uniqueId as $key => $ids) {
					$BookingTransact = BookingTransact::where('tour_booking_id', $CaIds)->get();
					foreach ($BookingTransact as $va) {
						$processingFeesss += (($va->tourists * ($va->rate +
							$va->additional_charge +
							$va->permit_fee
						)) * (float)$va->processing_charge_percentage) / 100;
					}
				}

				$cashProcessingFeeAmount = $cashProcessingFeeAmount + $processingFeesss;
				$processingFeesData['Cash'][$value['package_name']]['ProcessingFeesAmount'] = $cashProcessingFeeAmount;
			}

			if ($value['via'] == "Invoiced") {
				$InId = [];
				$InId[] = $value['tour_booking_id'];
				$uniqueId = array_unique($InId);
				$processingFeess = 0;

				foreach ($uniqueId as $key => $ids) {
					$BookingTransact = BookingTransact::where('tour_booking_id', $InId)->get();
					foreach ($BookingTransact as $va) {
						$processingFeess += (($va->tourists * ($va->rate +
							$va->additional_charge +
							$va->permit_fee
						)) * (float)$va->processing_charge_percentage) / 100;
					}
				}

				$invoicedProcessingFeeAmount = $invoicedProcessingFeeAmount + $processingFeess;
				$processingFeesData['Invoiced'][$value['package_name']]['ProcessingFeesAmount'] = $invoicedProcessingFeeAmount;
			}

			if ($value['via'] == "Check/Wire") {
				$wireTransferProcessingFeeAmount = $wireTransferProcessingFeeAmount + $processingFees;
				$processingFeesData['Wire'][$value['package_name']]['ProcessingFeesAmount'] = $wireTransferProcessingFeeAmount;
			}
		}

		return $processingFeesData;
	}

	/**
	 * Invalid Bookings Report
	 *
	 * @return array
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * 
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function invalidbookingsreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->invalidbookingsreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Tip Report
	 *
	 * @return mixed
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @throws Exception
	 */
	public function tipreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->tipreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Tip Summary Report
	 *
	 * @return array
	 *
	 * @param Request $request
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * 
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function tipsummaryreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->tipsummaryreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 * @throws Exception
	 */
	public function refundpayment(string $fromDate = "", string $toDate = "", $tourOperatorId = 0, $tourPackageId = 0, $export = 0)
	{
		try {
			return $this->reportRepository->refundpayment($fromDate, $toDate, $tourOperatorId, $tourPackageId, $export);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Refund payment report
	 */
	public function refundpayment1(string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0)
	{
		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		$qry = DB::table('tour_bookings_master AS tbm')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->leftjoin('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->where('tbh.operation', 'Cancelled')
			->whereJsonContains('tbh.details->amount', 0)
			->whereBetween('tbh.operation_at', [$fromDate, $toDate])
			->select([
				'tbm.id as bookingid',
				'tbh.tour_booking_id as id',
				'c.name as name',
				'c.phone_number as phone',
				'c.email as email',
				'tbh.details',
				DB::raw('(IFNULL((t.amount), 0)) as amount'),
				't.via',
				DB::raw('DATE_FORMAT(tbh.operation_at, "%Y-%m-%d") as refunddate'),
				'to.logo',
				't.status as payment_status',
				'tbm.status as booking_status',
				DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
				'tst.time_from'
			])
			->orderBy('tbh.tour_booking_id', 'DESC')
			->groupBy('tbh.tour_booking_id');

		if ($tourPackageId && $tourPackageId !== 0) {
			$qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if (session('userType') == 'Tour Operator') {
			$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$qry = $qry->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$qryResult = $qry->pluck('id')->toArray();

		$qry1 = DB::table('tour_bookings_master AS tbm')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->leftjoin('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->where('tbh.operation', 'Cancelled')
			->where('t.status', '!=', 'Failed')
			->whereBetween('tbh.operation_at', [$fromDate, $toDate])
			->select([
				'tbm.id as bookingid',
				'tbh.tour_booking_id as id',
				'c.name as name',
				'c.phone_number as phone',
				'c.email as email',
				'tbh.details',
				DB::raw('(IFNULL((t.amount), 0)) as amount'),
				't.via',
				DB::raw('DATE_FORMAT(tbh.operation_at, "%Y-%m-%d") as refunddate'),
				'to.logo',
				't.status as payment_status',
				'tbm.status as booking_status',
				DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
				'tst.time_from'
			])
			->whereNotIn('tbh.tour_booking_id', $qryResult)
			->orderBy('tbh.tour_booking_id', 'DESC')
			->groupBy('tbh.tour_booking_id');

		if ($tourPackageId && $tourPackageId !== 0) {
			$qry1 = $qry1->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if (session('userType') == 'Tour Operator') {
			$qry1 = $qry1->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		} else {
			$qry1 = $qry1->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$qryResult1 = $qry1->get();

		foreach ($qryResult1 as $key => $value) {
			$dataAr  = json_decode($value->details);
			$dataAr->refund_type = ($dataAr->refund_type) ?? '';
			$refund_amount = $dataAr->amount;
			$qryResult1[$key]->refund_type = $dataAr;
			$qryResult1[$key]->refund_amount = number_format((float)$refund_amount, 2);
			$qryResult1[$key]->time_from = date("g:i: A", strtotime($value->time_from));
			$qryResult1[$key]->bookingdate = date("m/d/Y", strtotime($value->bookingdate));
		}

		return DataTables::of($qryResult1)
			->addIndexColumn()
			->make(true);
	}

	/**
	 * FareHarbor Booking Report
	 *
	 * @return array
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * 
	 * @return mixed
	 * 
	 * @throws Exception
	 */
	public function fareharaborbookingreport(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->fareharaborbookingreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return array
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $customerId
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 * 
	 * @return mixed
	 * 
	 * @throws Exception
	 */
	public function affiliatereport(Request $request, string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
	{
		try {
			return $this->reportRepository->affiliatereport($request, $fromDate, $toDate, $customerId, $tourOperatorId, $tourPackageId, $export);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Affiliate report
	 */
	public function affiliatereport1(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
	{
		try {
			if ($fromDate == "")
				$fromDate = date('Y-m-d');
			else
				$fromDate = $fromDate . " 00:00:00";

			if ($toDate == "")
				$toDate = date('Y-m-d');
			else
				$toDate = $toDate . " 23:59:59";

			$qry = DB::table('tour_bookings_master AS tbm')
				->leftJoin('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
				->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
				->join('customers as c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_bookings_transact AS tbt', 'tbt.tour_booking_id', 'tbm.id')
				->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
				->where('tbm.status', 'Booked')
				->where('c.is_affiliate', 'Yes')
				->whereBetween('tsd.date', [$fromDate, $toDate])
				->select([
					'tbm.id as bookingid',
					'tbm.status as status',
					'tbm.total as amount',
					'tbm.booking_total',
					'tbm.liability_waiver_signing as liability_waiver_signing',
					DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
					DB::raw('DATE_FORMAT(tsd.date, "%Y-%m-%d") as bookingdate'),
					'to.logo as touroperatorlogo',
					'c.name as name',
					'tbt.tourists as tourists',
					'tp.name as package',
					'tpr.rate_for',
					'tpr.age_from',
					'tpr.age_to',
					't.status as payment_status',
					'tst.time_from',
					'tst.time_to',
					'tbh.details',
					'tbh.operation'
				])
				->orderBy('tsd.date', 'DESC');

			if ($tourPackageId && $tourPackageId !== 0) {
				$qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
			}

			if (session('userType') == 'Tour Operator') {
				$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else {
				$qry = $qry->where('tp.tour_operator_id', '=', $tourOperatorId);
			}

			if ($customerId !== 0) {
				$qry = $qry->where('c.id', '=', $customerId);
			}

			$qry = $qry->distinct()->get();
			$result = json_decode($qry, true);

			$initialArray = $this->affiliateData($result, 0);

			$sortInitialValueArray = array();
			foreach ($initialArray as $key => $element) {
				$sortInitialValueArray[] = $element;
			}

			$sortedData = array();
			$unpaidAmount = 0;
			$paidAmount = 0;
			$refundAmount = 0;
			$bookingAmount = 0;
			foreach ($sortInitialValueArray as $key => $sortInitialValue) {
				if (($sortInitialValue['booking_total']) != "0.00") {
					$amt = $sortInitialValue['booking_total'];
				} else {
					$amt = $sortInitialValue['amount'];
				}

				$bookingAmount += $amt;
				$paidAmount += $sortInitialValue['payment_amount'];
				$unpaidAmount += $sortInitialValue['unpaid_amount'];
				$refundAmount += $sortInitialValue['refundamount'];

				$sortInitialValue['time_to'] = date("g:i: A", strtotime($sortInitialValue['time_to']));
				$sortInitialValue['time_from'] = date("g:i: A", strtotime($sortInitialValue['time_from']));
				$timestamp = strtotime($sortInitialValue['bookingdate']);

				$date =  date("m/d/Y", $timestamp);
				if (!isset($sortedData[$date])) {
					$sortedData[$date] = array($sortInitialValue);
				} else {
					$sortedData[$date][] = $sortInitialValue;
				}
			}

			$sortedData['booking_amount'] = $bookingAmount;
			$sortedData['unpaid_amount'] = $unpaidAmount;
			$sortedData['paid_amount'] = $paidAmount;
			$sortedData['refund_amount'] = $refundAmount;

			if ($export) {
				return $this->AffiliateReportExportCsvFile($fromDate, $toDate, $sortedData);
			}

			return $sortedData;
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	/**
	 * @return array
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $customerId
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 * 
	 * @return mixed
	 * 
	 * @throws Exception
	 */
	public function affiliateallreport(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
	{
		try {
			return $this->reportRepository->affiliateallreport($fromDate, $toDate, $customerId, $tourOperatorId, $tourPackageId, $export);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return array
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $customerId
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 * 
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function affiliatecomprehensivereport(string $fromDate = "", string $toDate = "", int $customerId = 0,  $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
	{
		try {
			return $this->reportRepository->affiliatecomprehensivereport($fromDate, $toDate, $customerId, $tourOperatorId, $tourPackageId, $export);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Email report start
	 */
	public function emailreport(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0, int $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->emailreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Daily Report start
	 */
	public function dailyreport(Request $request, string $fromDate = "", string $toDate = "", $tourOperatorId = 0, int $tourPackageId = 0, int $export = 0)
	{
		try {
			return $this->reportRepository->dailyreport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Daily Report Details Start
	 */
	public function dailyreportdetails(Request $request, string $fromDate = "", string $toDate = "",  $tourOperatorId = 0, $tourPackageId = 0, int $export = 0)
	{
		try {
			return $this->reportRepository->dailyreportdetails($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Revenue Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $customerId
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param string $groupBy
	 * @param int $export
	 */
	public function revenueReport(
		Request $request,
		string $fromDate,
		string $toDate,
		int $tourOperatorId = 0,
		int $tourPackageId = 0,
		string $viewBy = "",
		bool $export = false
	) {
		try {
			return $this->reportRepository->revenueReport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $viewBy, $export);
		} catch (Exception $ex) {
			Log::error($ex);
			return sendJsonErrMsg($ex->getMessage());
		}
	}

	/**
	 * Revenue Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $customerId
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param string $groupBy
	 * @param int $export
	 * /{fromDate}/{toDate}/{customerId?}/{tourOperatorId?}/{tourPackageId?}/{groupBy?}
	 */
	public function invoiceInsightsReport(Request $request)
	{
		try {
			return $this->reportRepository->invoiceInsightsReport($request);
		} catch (Exception $ex) {
			Log::error($ex);
			return sendJsonErrMsg($ex->getMessage());
		}
	}

	/**
	 * Promotion Report
	 * In this method you can view the data in web page as well you can export in CSV file.
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $promocodeId
	 * @param int $export
	 * 
	 * @return mixed
	 */
	public function promotionReport(
		string $fromDate,
		string $toDate,
		int $tourOperatorId = 0,
		int $tourPackageId = 0,
		int $promocodeId = 0,
		bool $export = false,
	) {
		try {
			return $this->reportRepository->promotionReportData($fromDate, $toDate, $tourOperatorId, $tourPackageId, $promocodeId, $export);
		} catch (Exception $ex) {
			Log::error($ex);
			return sendJsonErrMsg($ex->getMessage());
		}
	}

	/**
	 * stripeRefundReport
	 *
	 * @return mixed
	 */
	public function stripeRefundReport(Request $request)
	{
		try {
			return $this->reportRepository->stripeRefundReport($request);
		} catch (Exception $ex) {
			Log::error($ex);
			return sendJsonErrMsg($ex->getMessage());
		}
	}

	/**
	 * Staff Booking Report
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $customerId
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param string $groupBy
	 * @param int $export
	 */
	public function staffBookingReport(Request $request, string $fromDate, string $toDate, int $tourOperatorId = 0, int $tourPackageId = 0, bool $export = false)
	{
		try {
			return $this->reportRepository->staffBookingReport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $export);
		} catch (Exception $ex) {
			Log::error($ex);
			return sendJsonErrMsg($ex->getMessage());
		}
	}

	/**
	 * Detail Sum Report
	 */
	public function revenuneSummationReport(Request $request, string $fromDate, string $toDate)
	{
		try {
			$repo = new ReportRepositoryV2();
			return $repo->revenuneSummationReport($request, $fromDate, $toDate);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 *
	 * @param Request $request
	 * @throws Exception
	 */
	public function affiliateSeatsReport(Request $request)
	{
		try {
			return $this->reportRepository->affiliateSeatsReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function affiliateSeatsSync()
	{
		try {
			return $this->reportRepository->affiliateSeatsSync();
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Activity Logs Report
	 *
	 * @return mixed
	 *
	 * @param $request
	 * @throws Exception
	 */
	public function activityLogsReport(Request $request)
	{
		$fromDate = date('Y-m-d') . " 00:00:00";
		if ($request->has("fromDate")) {
			$fromDate = Carbon::parse($request->fromDate)->format("Y-m-d H:i:s");
		}
		$toDate = date('Y-m-d') . " 23:59:59";
		if ($request->has("toDate")) {
			$toDate = Carbon::parse($request->toDate)->format("Y-m-d H:i:s");
		}

		$eventType = $request->has("eventType") ? $request->eventType : "";
		$userName = $request->has("userName") ? $request->userName : "";

		try {
			return $this->reportRepository->activityLogsReportList($fromDate, $toDate, $eventType, $userName);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 *
	 * @param Request $request
	 * @throws Exception
	 */
	public function saveCreditCardReport(Request $request)
	{
		try {
			return $this->reportRepository->saveCreditCardReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
}
