<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\TourOperator;
use App\Models\TourPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Repositories\Report\ReportRepository;
use App\Http\Requests\API\Report\CashReportRequest;
use App\Http\Requests\API\Report\CardReportRequest;
use App\Http\Requests\API\Report\CheckWireReportRequest;
use App\Http\Requests\API\Report\TaxCollectionReportRequest;
use App\Http\Requests\API\Report\TransactionReportRequest;
use App\Http\Requests\API\Report\TransactionSummaryReportRequest;
use App\Http\Requests\API\Report\AddonsPackageReportRequest;
use App\Http\Requests\API\Report\AddonsPackageDetailsReportRequest;

class ReportController extends Controller
{
	protected $reportRepository;

	/**
	 * @param  ReportRepository  $reportRepository
	 */
	private $requestValidation;

	public function __construct(ReportRepository $reportRepository)
	{
		parent::__construct();

		$this->requestValidation = [
			'dateFrom' => 'required|date',
			'dateTo' => 'required|date',
			'tourPackage' => 'numeric',
		];

		if (session('userType') == 'Admin') {
			$this->requestValidation['tourOperator'] = 'required|numeric';
		}

		$this->reportRepository = $reportRepository;
	}

	/**
	 * Get the software fee report
	 *
	 * @param Request $request
	 */
	public function softwarefee(Request $request)
	{
		$request->validate($this->requestValidation);

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->tourOperator;
		} else {
			$tourOperatorId = auth()->user()->tour_operator_id;
		}

		try {
			$query = DB::table('tour_bookings_master AS tbm')
				->join('transactions AS t', 't.tour_booking_id', '=', 'tbm.id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->where('tp.tour_operator_id', $tourOperatorId)
				->whereBetween('tbm.created_at', [$request->dateFrom . ' 00:00:00', $request->dateTo . ' 23:59:59'])
				->where('tbm.status', '!=', 'Cancelled')
				->where('t.status', 'Completed')
				->groupBy('tbm.id')
				->orderByDesc('tbm.created_at');

			if ($request->has('tourPackage')) {
				$query = $query->where('tp.id', $request->tourPackage);
			}

			$payments = $query->select([
					'tbm.created_at',
					'tbm.id as tour_booking_id',
					'tbm.total AS payable',
					DB::raw('SUM(t.amount) AS paid'),
					't.fee_amount as fees'
				])
				->get();

			$taxCollections = [];
			for ($a = 0, $ac = count($payments); $a < $ac; $a++) {
				$date = substr($payments[$a]->created_at, 0, 10);

				if (!isset($taxCollections[$date])) {
					$taxCollections[$date] = ['additionalTax' => 0, 'permitFee' => 0, 'stateTax' => 0, 'softwarefee' => 0, 'processing_charge_percentage' => 0];
					$fees = DB::table('tour_bookings_transact')
						->where('tour_booking_id', $payments[$a]->tour_booking_id)
						->get([
							'rate',
							'additional_charge',
							'tourists',
							'additional_tax_percentage',
							'permit_fee',
							'additional_charge',
							'processing_charge_percentage'
						]);
				}

				if ($payments[$a]->payable > 0) {
					$payableToPaidRatio = ($payments[$a]->paid > 0) ? $payments[$a]->payable / $payments[$a]->paid : 0;
				}

				for ($b = 0, $bc = count($fees); $b < $bc; $b++) {
					$tourOpFee = ($fees[$b]->rate + $fees[$b]->additional_charge) * $fees[$b]->tourists;
					$taxCollections[$date]['processing_charge_percentage'] += $fees[$b]->processing_charge_percentage;
					$taxCollections[$date]['additionalTax'] = ($tourOpFee * $payableToPaidRatio) * ($fees[$b]->additional_tax_percentage / 100) + $taxCollections[$date]['additionalTax'];
					$taxCollections[$date]['permitFee'] = ($fees[$b]->permit_fee * $fees[$b]->tourists) + $taxCollections[$date]['permitFee'];
					$taxCollections[$date]['stateTax'] += $fees[$b]->additional_charge;
					$taxCollections[$date]['softwarefee'] += $payments[$a]->fees;
				}
			}

			// calculating totals here as JavaScript isn't reliable for rounding numbers
			$additionalTaxTotal = $permitFeeTotal = $stateTaxTotal = $processing_charge_percentage = 0;

			$softwarefee = 0;
			foreach ($taxCollections as $date => $taxes) {
				$taxCollections[$date]['processing_charge_percentage'] = sprintf('%.2f', $taxCollections[$date]['processing_charge_percentage']);
				$taxCollections[$date]['softwarefee'] = sprintf('%.2f', $taxCollections[$date]['softwarefee']);
				$taxCollections[$date]['additionalTax'] = sprintf('%.2f', $taxCollections[$date]['additionalTax']);
				$taxCollections[$date]['permitFee'] = sprintf('%.2f', $taxCollections[$date]['permitFee']);
				$taxCollections[$date]['stateTax'] = sprintf('%.2f', $taxCollections[$date]['stateTax']);
				$taxCollections[$date]['total'] = sprintf('%.2f',  $taxCollections[$date]['softwarefee'] + $taxCollections[$date]['additionalTax'] + $taxCollections[$date]['permitFee'] + $taxCollections[$date]['stateTax'] + $taxCollections[$date]['processing_charge_percentage']);
				$softwarefee += $taxCollections[$date]['softwarefee'];
				$processing_charge_percentage += $taxCollections[$date]['processing_charge_percentage'];
				$additionalTaxTotal += $taxCollections[$date]['additionalTax'];
				$permitFeeTotal += $taxCollections[$date]['permitFee'];
				$stateTaxTotal += $taxCollections[$date]['stateTax'];
			}

			return response()->json([
				'individual' => $taxCollections,
				'totals' => [
					'processing_charge_percentage' => sprintf('%.2f', $processing_charge_percentage),
					'softwarefee' => sprintf('%.2f', $softwarefee),
					'additionalTax' => sprintf('%.2f', $additionalTaxTotal),
					'permitFee' => sprintf('%.2f', $permitFeeTotal),
					'stateTax' => sprintf('%.2f', $stateTaxTotal),
					'all' => sprintf('%.2f', $additionalTaxTotal + $permitFeeTotal + $stateTaxTotal + $softwarefee + $processing_charge_percentage),
				]
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to get data of taxes and payments.');
		}
	}

	/**
	 * Financial Report
	 *
	 * @param Request $request
	 */
	public function FinancialReport(Request $request)
	{
		if ($request->csv == 0) {
			$request->validate($this->requestValidation);
		}

		$fromDate = $request->dateFrom;
		$toDate = $request->dateTo;
		$tourPackageId = $request->tourPackage;

		// Overall Booking Total
		$overallBookingTotal = 0;
		$overallPaymentTotal = 0;
		$overallRefundTotal = 0;
		$overallTouristTotal = 0;
		$overallCancellationTotal = 0;
		$overallUnpaidTotal = 0;

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->tourOperator;
		} else {
			$tourOperatorId = auth()->user()->tour_operator_id;
		}

		$query =  DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->whereIn('tbm.status', ['Booked', 'Cancelled'])
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->groupBy('tbm.id')
			->select([
				'tbm.id as tbmId', 'tbm.name as name', 'tbm.tourists as tbmtourists', 'tbm.total as amount', 'tbm.status as status', 'tsd.date as bookingdate', 'tst.time_slot_seats as seats', 'tp.id as packageId', 't.status as payment_status', 't.payment_type'
			])
			->orderBy('bookingdate', 'DESC');

		if ($tourPackageId && $tourPackageId !== 0) {
			$query = $query->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if ($tourOperatorId && $tourOperatorId !== 0) {
			$query = $query->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$query = $query->get();
		$result = json_decode($query, true);

		$sortedData = array();
		foreach ($result as $key => $value) {
			foreach ($result as $keys => $values) {
				if ($key != $keys && $value['tbmId'] == $values['tbmId']) {
					if ($value['payment_status'] == "Failed") {
						unset($result[$key]);
					}

					if ($value['payment_status'] == "Refund") {
						unset($result[$key]);
					}
				}
			}
		}

		$result = array_map("unserialize", array_unique(array_map("serialize", $result)));

		$bookingids = array();
		foreach ($result as $element) {
			$timestamp = strtotime($element['bookingdate']);
			$date =  date("d-m-Y", $timestamp);

			if (!isset($sortedData[$date])) { //first entry of that day
				$sortedData[$date] = array($element);
			} else { //just push current element onto existing array
				$sortedData[$date][] = $element;
			}

			$bookingids[] = $element['tbmId'];
		}

		$unpaidQuery =  DB::table('tour_bookings_master AS tbm1')
			->join('tour_slot_times as tst1', 'tst1.id', '=', 'tbm1.tour_slot_id')
			->join('tour_slot_dates as tsd1', 'tsd1.id', '=', 'tst1.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd1.tour_package_id')
			->leftJoin('transactions AS t2', 't2.tour_booking_id', '=', 'tbm1.id')
			->whereNull('t2.tour_booking_id')
			->where('tbm1.status', '=', 'Booked')
			->where('tsd1.deleted_by', '=', '0')
			->whereBetween('tsd1.date', [$fromDate, $toDate])
			->select('tbm1.id as tbookm_id', 'tbm1.total as TBMAmount', 'tsd1.date as bookDate', 'tsd1.date as bookingdate')
			->orderBy('bookingdate', 'DESC')
			->groupBy('tbm1.id');

		if ($tourPackageId && $tourPackageId !== 0) {
			$unpaidQuery = $unpaidQuery->where('tbm1.tour_package_id', '=', $tourPackageId);
		}

		if ($tourOperatorId && $tourOperatorId !== 0) {
			$unpaidQuery = $unpaidQuery->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$TBMData = $unpaidQuery->get()->toArray();

		$sortedTBMData = array();
		foreach ($TBMData as $pkg => $amt) {
			$timestamp = strtotime($amt->bookDate);
			$date1 =  date("d-m-Y", $timestamp);
			if (!isset($sortedTBMData[$date1])) { //first entry of that day
				$sortedTBMData[$date1] = array($amt);
			} else { //just push current element onto existing array
				$sortedTBMData[$date1][] = $amt;
			}
		}

		// Cancelled Booking count
		$cancelledBooking = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tbm.status', 'Cancelled')
			->where('t.status', 'Completed')
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->groupBy('tbm.id')
			->select(
				'tbm.id as booking_id',
				'tbm.name as name',
				'tbm.tourists as tbmtourists',
				'tbm.total as amount',
				'tbm.status as booking_status',
				'tsd.date as bookingdate',
				'tp.id as packageId',
				't.status as transaction_status'
			);

		if ($tourPackageId && $tourPackageId !== 0) {
			$cancelledBooking = $cancelledBooking->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if ($tourOperatorId && $tourOperatorId !== 0) {
			$cancelledBooking = $cancelledBooking->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$cancelledBooking = $cancelledBooking->get();
		$cancelledBookingResult = json_decode($cancelledBooking, true);

		$sortedCancelledData = array();
		foreach ($cancelledBookingResult as $element) {
			$timestamp = strtotime($element['bookingdate']);
			$date =  date("d-m-Y", $timestamp);
			if (!isset($sortedCancelledData[$date])) { //first entry of that day
				$sortedCancelledData[$date] = array($element);
			} else { //just push current element onto existing array
				$sortedCancelledData[$date][] = $element;
			}
		}

		$newSortedData = [];
		if ($sortedData) {
			foreach ($sortedData as $key2 => $sortedDataValue) {
				$TotalTourists = 0;
				$infantCount = 0;
				$EightToTwelveChildrenCount = 0;
				$FourtoSevenChildrenCount = 0;
				$AdultCount = 0;
				$TeenagerCount = 0;
				$SeniorCitizenCount = 0;
				$TLCCount = 0;
				$LocalsCount = 0;
				$GuidesCount = 0;
				$americansCount = 0;
				$adultMilitryCount = 0;
				$EightToTwelveChildrenMilitryCount = 0;
				$FourToSevenChildrenMilitryCount = 0;

				$BookingTotal = 0;
				$SplitAmount = 0;
				$RefundAmountVar = 0;
				$PaymentTotal = 0;
				$cancelledBookingAmount = 0;
				$unpaidAmountTotal = 0;
				$failedAndEmptyAmount = 0;

				$infant = '';
				$EightToTwelveChildren = '';
				$FourtoSevenChildren = '';
				$Adult = '';
				$Teenager = '';
				$SeniorCitizen = '';
				$TLC = '';
				$Locals = '';
				$Guides = '';
				$americans = '';
				$AdultMilitryTeachers = '';
				$EightToTwelveChildrenMilitryTeachers = '';
				$FourToSevenChildrenMilitryTeachers = '';

				foreach ($sortedCancelledData as $keyTBM2 => $sortedCancelledDataValue) {
					if ($key2 == $keyTBM2) {
						foreach ($sortedCancelledDataValue as $keyTBM3 => $sortedCancelledDataElementsValue) {
							$cancelledBookingAmount = $cancelledBookingAmount + $sortedCancelledDataElementsValue['amount'];
						}
					}
				}

				foreach ($sortedTBMData as $keyTBM2 => $sortedTBMDataValue) {
					if ($key2 == $keyTBM2) {
						foreach ($sortedTBMDataValue as $keyTBM3 => $sortedTBMDataElementsValue) {
							$unpaidAmountTotal = $unpaidAmountTotal + $sortedTBMDataElementsValue->TBMAmount;
						}
					}
				}

				foreach ($sortedDataValue as $sortedDataElementsValue) {
					if ($sortedDataElementsValue['status'] == 'Booked') {
						$TourPkgRates = DB::table('tour_package_rates as tpr')
							->join('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
							->where('tpr.tour_package_id', '=', $sortedDataElementsValue['packageId'])
							->where('tbt.tour_booking_id', '=', $sortedDataElementsValue['tbmId'])
							->where('tbt.deleted_by',0)
							->select('tpr.id as tpr_id', 'tpr.rate_for', 'tbt.tourists as tourists')
							->get()
							->toArray();

						$touristsCount = 0;
						foreach ($TourPkgRates as $pkg => $pkg_rates) {
							$TourPkgRates['rate_for'] = $pkg_rates->rate_for;

							if (Str::contains($TourPkgRates['rate_for'], "Infant")) {
								$infantCount += $pkg_rates->tourists;
								$infant = number_format($infantCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 8-12) - Military/Teachers")) {
								$EightToTwelveChildrenMilitryCount += $pkg_rates->tourists;
								$EightToTwelveChildrenMilitryTeachers = number_format($EightToTwelveChildrenMilitryCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 4-7) - Military/Teachers")) {
								$FourToSevenChildrenMilitryCount += $pkg_rates->tourists;
								$FourToSevenChildrenMilitryTeachers = number_format($FourToSevenChildrenMilitryCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 8-12)")) {
								$EightToTwelveChildrenCount += $pkg_rates->tourists;
								$EightToTwelveChildren = number_format($EightToTwelveChildrenCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 4 -7)")) {
								$FourtoSevenChildrenCount += $pkg_rates->tourists;
								$FourtoSevenChildren = number_format($FourtoSevenChildrenCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Teenager")) {
								$TeenagerCount += $pkg_rates->tourists;
								$Teenager = number_format($TeenagerCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "SeniorCitizen")) {
								$SeniorCitizenCount += $pkg_rates->tourists;
								$SeniorCitizen = number_format($SeniorCitizenCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "TLC Combo Spot")) {
								$TLCCount += $pkg_rates->tourists;
								$TLC = number_format($TLCCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Locals")) {
								$LocalsCount += $pkg_rates->tourists;
								$Locals = number_format($LocalsCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Tour Guides")) {
								$GuidesCount += $pkg_rates->tourists;
								$Guides = number_format($GuidesCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Native Americans")) {
								$americansCount += $pkg_rates->tourists;
								$americans = number_format($americansCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Adults - Military/Teacher")) {
								$adultMilitryCount += $pkg_rates->tourists;
								$AdultMilitryTeachers = number_format($adultMilitryCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Adult")) {
								$AdultCount += $pkg_rates->tourists;
								$Adult = number_format($AdultCount, 0, '.', ',') . ' ' . $TourPkgRates['rate_for'];
							}

							$rate = array();
							$rate['infant'] = $infant;
							$rate['EightToTwelveChildren'] = $EightToTwelveChildren;
							$rate['FourtoSevenChildren'] = $FourtoSevenChildren;
							$rate['Adult'] = $Adult;
							$rate['Teenager'] = $Teenager;
							$rate['SeniorCitizen'] = $SeniorCitizen;
							$rate['TLC'] = $TLC;
							$rate['Locals'] = $Locals;
							$rate['Guides'] = $Guides;
							$rate['americans'] = $americans;
							$rate['AdultMilitryTeachers'] = $AdultMilitryTeachers;
							$rate['EightToTwelveChildrenMilitryTeachers'] = $EightToTwelveChildrenMilitryTeachers;
							$rate['FourToSevenChildrenMilitryTeachers'] = $FourToSevenChildrenMilitryTeachers;

							if ($rate['infant'] == "") {
								unset($rate['infant']);
							}
							if ($rate['EightToTwelveChildren'] == "") {
								unset($rate['EightToTwelveChildren']);
							}
							if ($rate['FourtoSevenChildren'] == "") {
								unset($rate['FourtoSevenChildren']);
							}
							if ($rate['Adult'] == "") {
								unset($rate['Adult']);
							}
							if ($rate['Teenager'] == "") {
								unset($rate['Teenager']);
							}
							if ($rate['SeniorCitizen'] == "") {
								unset($rate['SeniorCitizen']);
							}
							if ($rate['TLC'] == "") {
								unset($rate['TLC']);
							}
							if ($rate['Locals'] == "") {
								unset($rate['Locals']);
							}
							if ($rate['Guides'] == "") {
								unset($rate['Guides']);
							}
							if ($rate['americans'] == "") {
								unset($rate['americans']);
							}
							if ($rate['AdultMilitryTeachers'] == "") {
								unset($rate['AdultMilitryTeachers']);
							}
							if ($rate['EightToTwelveChildrenMilitryTeachers'] == "") {
								unset($rate['EightToTwelveChildrenMilitryTeachers']);
							}
							if ($rate['FourToSevenChildrenMilitryTeachers'] == "") {
								unset($rate['FourToSevenChildrenMilitryTeachers']);
							}

							$touristsCount += $pkg_rates->tourists;
						}

						$TotalTourists += $sortedDataElementsValue['tbmtourists'];
						$refundData = DB::table('tour_booking_history as tbh')
							->select('tbh.details', 'tbh.operation_at', 'tbh.tour_booking_id', 'tbh.operation')
							->where('tbh.tour_booking_id', '=', $sortedDataElementsValue['tbmId'])
							->where('tbh.operation', 'Payment')
							->distinct('tbh.tour_booking_id')
							->get();

						if ($sortedDataElementsValue['payment_status'] == "Failed") {
							$failedAndEmptyAmount = $failedAndEmptyAmount + $sortedDataElementsValue['amount'];
							$BookingTotal = $BookingTotal + $sortedDataElementsValue['amount'];
						}

						if ($sortedDataElementsValue['payment_status'] == null) {
							$failedAndEmptyAmount = $failedAndEmptyAmount + $sortedDataElementsValue['amount'];
							$BookingTotal = $BookingTotal + $sortedDataElementsValue['amount'];
						}

						if ($sortedDataElementsValue['payment_type'] == '1') {
							$BookingTotal = $BookingTotal + $sortedDataElementsValue['amount'];
						}

						$operation_at = '';
						$SplitTotal = 0;
						foreach ($refundData as $key => $value) {
							if ($key == 0) {
								$operation_at = $value->operation_at;
							}

							$refund_details = json_decode($value->details, true);
							if ($value->tour_booking_id == $sortedDataElementsValue['tbmId']) {
								if ($value->operation == "Payment" && $sortedDataElementsValue['payment_type'] != '1') {
									$BookingTotal = $BookingTotal + (float)$refund_details['amount'];
								}

								if ($value->operation == "Payment" && $sortedDataElementsValue['payment_type'] == '1') {
									$SplitTotal = $SplitTotal + (float)$refund_details['amount'];
								}

								if ($value->operation == "Payment" && $sortedDataElementsValue['payment_status'] == "Failed") {
									$BookingTotal = $BookingTotal - $sortedDataElementsValue['amount'];
									$failedAndEmptyAmount = $failedAndEmptyAmount - $sortedDataElementsValue['amount'];
								}
							}
						}

						if ($operation_at != '') {
							$history = DB::table('tour_booking_history as tbh')
								->select('tbh.id as tbhID', 'tbh.tour_booking_id as tbhBookingID', 'tbh.operation as operation', 'tbh.details as details')
								->where('tbh.operation_at', '>', $operation_at)
								->where('tbh.tour_booking_id', '=', $sortedDataElementsValue['tbmId'])
								->where(function ($query) {
									return $query->where('tbh.operation', '=', 'Refund')
										->orWhere('tbh.operation', '=', 'Cancelled');
								})
								->get();

							foreach ($history as $key => $refund) {
								$details = json_decode($refund->details, true);
								if ($refund->operation == "Refund") {
									$RefundAmountVar = $RefundAmountVar + (float)$details['amount'];
								}

								if ($refund->operation == 'Cancelled') {
									$RefundAmountVar = $RefundAmountVar + (float)$details['amount'];
								}
							}
						}

						if ($sortedDataElementsValue['payment_type'] == '1') {
							$SplitAmount = $sortedDataElementsValue['amount'] - $SplitTotal;
						}

						$PaymentTotal = ($BookingTotal - $SplitAmount) - $RefundAmountVar - $failedAndEmptyAmount;
					}
				}

				$overallBookingTotal = $overallBookingTotal + $BookingTotal;
				$overallPaymentTotal = $overallPaymentTotal + $PaymentTotal;
				$overallUnpaidTotal = $overallUnpaidTotal + $unpaidAmountTotal;
				$overallRefundTotal = $overallRefundTotal + $RefundAmountVar;
				$overallCancellationTotal = $overallCancellationTotal + $cancelledBookingAmount;
				$overallTouristTotal = $overallTouristTotal + $TotalTourists;

				$date = date('m-d-Y', strtotime($key2));

				$newFilteredData = array();
				$newFilteredData['bookingdate'] = $date;
				$newFilteredData['amountTotal'] = $BookingTotal;
				$newFilteredData['paidAmountTotal'] = $PaymentTotal;
				$newFilteredData['unpaidAmountTotal'] = $unpaidAmountTotal;
				$newFilteredData['refundTotal'] = $RefundAmountVar;
				$newFilteredData['cancelledBookingAmount'] = $cancelledBookingAmount;
				$newFilteredData['touristsTotal'] = $TotalTourists;
				$newFilteredData['CustomerRateValue'] = isset($rate) ? $rate : 0;
				$newSortedData[$key2] = $newFilteredData;
			}
		}

		$overallData = array();
		if ($request->csv == 1) {
			$overallData['overallBookingTotal'] = $overallBookingTotal;
			$overallData['overallPaymentTotal'] = $overallPaymentTotal;
			$overallData['overallUnpaidTotal'] = $overallUnpaidTotal;
			$overallData['overallRefundTotal'] = $overallRefundTotal;
			$overallData['overallCancellationTotal'] = $overallCancellationTotal;
			$overallData['overallTouristTotal'] = $overallTouristTotal;

			return $this->FinancialReportExportCsvFile($fromDate, $toDate, $newSortedData, $overallData, $tourOperatorId, $tourPackageId);
		}

		return $newSortedData;
	}

	/**
	 * Financial Report Export Csv File
	 */
	public function FinancialReportExportCsvFile($fromDate, $toDate, $newSortedData, $overallData, $tourOperatorId = 0, $tourPackageId = 0)
	{
		$fileName = 'financial_report_' . $fromDate . '_' . $toDate . '.csv';

		$headers = csvHeaders($fileName);
		$TopColumns = csvColumns('financial_report_TopColumns');
		$columns = csvColumns('financial_report_column');
		$userType = userType();
		$tourOperatorName = '';
		$tourPackageName = '';

		if ($userType == 'Admin') {
			if ($tourOperatorId != 0 && $tourOperatorId != '') {
				$tourOperatorDetails = TourOperator::find($tourOperatorId);
				$tourOperatorName = $tourOperatorDetails->name;
			}
		} else {
			$tourOperatorDetails = TourOperator::find(auth()->user()->tour_operator_id);
			$tourOperatorName = $tourOperatorDetails->name;
		}

		if ($tourPackageId != 0 && $tourPackageId != '') {
			$tourPackageDetails = TourPackage::where('id', $tourPackageId)->withTrashed()->first(['id', 'name']);
			$tourPackageName = $tourPackageDetails->name;
		}

		$callback = function () use ($newSortedData, $TopColumns, $columns, $fromDate, $toDate, $overallData, $tourOperatorName, $tourPackageName) {
			$file = fopen('php://output', 'w');  // Create a file
			if (!empty($tourOperatorName)) {
				$BlankLine = '';
				if ($tourOperatorName) {
					$tourOperatorNameRow = array('Tour Operator:', $tourOperatorName, $BlankLine, $BlankLine, $BlankLine);
					fputcsv($file, $tourOperatorNameRow);
				}

				if ($tourPackageName) {
					$tourPackageNameRow = array('Tour Package:', $tourPackageName, $BlankLine, $BlankLine, $BlankLine);
					fputcsv($file, $tourPackageNameRow);
				}

				fputcsv($file, array($BlankLine, $BlankLine, $BlankLine, $BlankLine, $BlankLine));
			}

			fputcsv($file, $TopColumns);

			$DateRange = date("m-d-Y", strtotime($fromDate)) . ' - ' . date("m-d-Y", strtotime($toDate));
			$CreatedDate = date("m-d-Y");
			$overallBookingTotal = sprintf('%.2f', $overallData['overallBookingTotal']);
			$overallPaymentTotal = sprintf('%.2f', $overallData['overallPaymentTotal']);
			$overallUnpaidTotal = sprintf('%.2f', $overallData['overallUnpaidTotal']);
			$overallRefundTotal = sprintf('%.2f', $overallData['overallRefundTotal']);
			$overallCancellationTotal = sprintf('%.2f', $overallData['overallCancellationTotal']);
			$overallTouristTotal = $overallData['overallTouristTotal'];
			fputcsv($file, array($DateRange, $CreatedDate, $overallTouristTotal, $overallBookingTotal, $overallPaymentTotal, $overallUnpaidTotal, $overallRefundTotal, $overallCancellationTotal));

			$BlankLine = '';
			fputcsv($file, array($BlankLine));
			fputcsv($file, $columns);

			$toEnd = count($newSortedData);
			foreach ($newSortedData as $result_data) {
				$bookingDate = isset($result_data['bookingdate']) ? $result_data['bookingdate'] : '';
				$TotalTourists = isset($result_data['touristsTotal']) ? $result_data['touristsTotal'] : '';
				$BookingTotal = isset($result_data['amountTotal']) ? $result_data['amountTotal'] : '';
				$PaymentTotal = isset($result_data['paidAmountTotal']) ? $result_data['paidAmountTotal'] : '';
				$unpaidAmountTotal = isset($result_data['unpaidAmountTotal']) ? $result_data['unpaidAmountTotal'] : '';
				$RefundAmountVar = isset($result_data['refundTotal']) ? $result_data['refundTotal'] : '';
				$cancelledBookingAmount = isset($result_data['cancelledBookingAmount']) ? $result_data['cancelledBookingAmount'] : '';

				$tourist_rate = '';
				if (@$result_data['CustomerRateValue']['Adult'] != "") {
					$tourist_rate = @$result_data['CustomerRateValue']['Adult'];
				}
				if (@$result_data['CustomerRateValue']['EightToTwelveChildren'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['EightToTwelveChildren'];
				}
				if (@$result_data['CustomerRateValue']['AdultMilitryTeachers'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['AdultMilitryTeachers'];
				}
				if (@$result_data['CustomerRateValue']['FourtoSevenChildren'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['FourtoSevenChildren'];
				}
				if (@$result_data['CustomerRateValue']['FourToSevenChildrenMilitryTeachers'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['FourToSevenChildrenMilitryTeachers'];
				}
				if (@$result_data['CustomerRateValue']['EightToTwelveChildrenMilitryTeachers'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['EightToTwelveChildrenMilitryTeachers'];
				}
				if (@$result_data['CustomerRateValue']['infant'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['infant'];
				}
				if (@$result_data['CustomerRateValue']['Teenager'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['Teenager'];
				}
				if (@$result_data['CustomerRateValue']['TLC'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['TLC'];
				}
				if (@$result_data['CustomerRateValue']['Locals'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['Locals'];
				}
				if (@$result_data['CustomerRateValue']['Guides'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['Guides'];
				}
				if (@$result_data['CustomerRateValue']['americans'] != "") {
					$tourist_rate .= "\n" . @$result_data['CustomerRateValue']['americans'];
				}

				$Tourists_Rate = $tourist_rate;
				fputcsv($file, array($bookingDate, $TotalTourists, $BookingTotal, $PaymentTotal, $unpaidAmountTotal, $RefundAmountVar, $cancelledBookingAmount, $Tourists_Rate));
			}

			if (0 !== --$toEnd) {
				fputcsv($file, array($BlankLine));
			}

			fclose($file);
		};

		return response()->stream($callback, 200, $headers);
	}

	/**
	 * Financial Report 1
	 *
	 * @param Request $request
	 */
	public function FinancialReport1(Request $request)
	{
		$unpaidAmountTotal = 0;
		$RefundAmountVar = 0;
		$CancelledAmountVar = 0;
		$BookingTotal = 0;
		$PaymentTotal = 0;
		$unpainBookingTotalTras = 0;
		$request->validate($this->requestValidation);

		$fromDate = $request->dateFrom;
		$toDate = $request->dateTo;
		$tourPackageId = $request->tourPackage;

		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = $fromDate . " 00:00:00";

		if ($toDate == "")
			$toDate = date('Y-m-d');
		else
			$toDate = $toDate . " 23:59:59";

		if (session('userType') == 'Admin') {
			$tourOperatorId = $request->tourOperator;
		} else {
			$tourOperatorId = auth()->user()->tour_operator_id;
		}

		$query = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tbm.status', 'Booked')
			->where('tsd.deleted_by', '=', '0')
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->select([
				'tbm.id as tbmId', 'tbm.name as name', 'tbm.tourists as tbmtourists', 'tbm.total as amount', 'tbm.status as status',/*'tbm.created_at as createdate','tst.time_from as timefrom','tst.time_to as timeto',*/ 'tsd.date as bookingdate', 'tst.time_slot_seats as seats', 'tp.id as packageId'/*,'tp.name as packageName'*//*,'t.amount as tAmount',*//*'t.status as paymentStatus',*//*'tpr.rate_for',*//*'t.via as paywith','to.logo as touroperatorlogo','tpr.age_from','tpr.age_to'*/
			])
			->orderBy('bookingdate', 'DESC');


		if ($tourPackageId && $tourPackageId !== 0) {
			$query = $query->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		if ($tourOperatorId && $tourOperatorId !== 0) {
			$query = $query->where('tp.tour_operator_id', '=', $tourOperatorId);
		}

		$query = $query->get();
		$result = json_decode($query, true);

		$sortedData = array();
		foreach ($result as $element) {
			$timestamp = strtotime($element['bookingdate']);
			$date =  date("d-m-Y", $timestamp);
			if (!isset($sortedData[$date])) { //first entry of that day
				$sortedData[$date] = array($element);
			} else { //just push current element onto existing array
				$sortedData[$date][] = $element;
			}
		}

		$TBMData = DB::table('tour_bookings_master AS tbm1')
			->select('tbm1.total')
			->join('tour_slot_times as tst1', 'tst1.id', '=', 'tbm1.tour_slot_id')
			->join('tour_slot_dates as tsd1', 'tsd1.id', '=', 'tst1.tour_slot_id')
			->leftJoin('transactions AS t2', 't2.tour_booking_id', '=', 'tbm1.id')
			->whereNull('t2.tour_booking_id')
			->where('tbm1.status', '=', 'Booked')
			->where('tsd1.deleted_by', '=', '0')
			->whereBetween('tsd1.date', [$fromDate, $toDate])
			->select('tbm1.id as tbookm_id', 'tbm1.total as TBMAmount', 'tsd1.date as bookDate')
			->get()
			->toArray();

		$sortedTBMData = array();
		foreach ($TBMData as $amt) {
			$timestamp = strtotime($amt->bookDate);
			$date1 =  date("d-m-Y", $timestamp);
			if (!isset($sortedTBMData[$date1])) { //first entry of that day
				$sortedTBMData[$date1] = array($amt);
			} else { //just push current element onto existing array
				$sortedTBMData[$date1][] = $amt;
			}
		}

		foreach ($sortedTBMData as $keyTBM2 => $sortedTBMDataValue) {
			foreach ($sortedTBMDataValue as $keyTBM3 => $sortedTBMDataElementsValue) {
				$unpaidAmountTotal += $sortedTBMDataElementsValue->TBMAmount;
			}
		}

		$newSortedData = [];
		if ($sortedData) {
			foreach ($sortedData as $key2 => $sortedDataValue) {
				$amountTotal = 0;
				$TotalTourists = 0;
				$refundTotal = 0;

				$infantCount = 0;
				$EightToTwelveChildrenCount = 0;
				$FourtoSevenChildrenCount = 0;
				$AdultCount = 0;
				$TeenagerCount = 0;
				$SeniorCitizenCount = 0;
				$TLCCount = 0;
				$LocalsCount = 0;
				$GuidesCount = 0;
				$americansCount = 0;
				$adultMilitryCount = 0;
				$EightToTwelveChildrenMilitryCount = 0;
				$FourToSevenChildrenMilitryCount = 0;

				$infant = '';
				$EightToTwelveChildren = '';
				$FourtoSevenChildren = '';
				$Adult = '';
				$Teenager = '';
				$SeniorCitizen = '';
				$TLC = '';
				$Locals = '';
				$Guides = '';
				$americans = '';
				$AdultMilitryTeachers = '';
				$EightToTwelveChildrenMilitryTeachers = '';
				$FourToSevenChildrenMilitryTeachers = '';

				foreach ($sortedDataValue as $sortedDataElementsValue) {
					if ($sortedDataElementsValue['status'] == 'Booked') {
						$TourPkgRates = DB::table('tour_package_rates as tpr')
							->join('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
							->where('tpr.tour_package_id', '=', $sortedDataElementsValue['packageId'])
							->where('tbt.tour_booking_id', '=', $sortedDataElementsValue['tbmId'])
							->select('tpr.id as tpr_id', 'tpr.rate_for', 'tbt.tourists as tourists')
							->get()
							->toArray();

						$touristsCount = 0;
						foreach ($TourPkgRates as $pkg => $pkg_rates) {
							$TourPkgRates['rate_for'] = $pkg_rates->rate_for;

							if (Str::contains($TourPkgRates['rate_for'], "Infant")) {
								$infantCount += $pkg_rates->tourists;
								$infant = $infantCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 8-12) - Military/Teachers")) {
								$EightToTwelveChildrenMilitryCount += $pkg_rates->tourists;
								$EightToTwelveChildrenMilitryTeachers = $EightToTwelveChildrenMilitryCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 4-7) - Military/Teachers")) {
								$FourToSevenChildrenMilitryCount += $pkg_rates->tourists;
								$FourToSevenChildrenMilitryTeachers = $FourToSevenChildrenMilitryCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 8-12)")) {
								$EightToTwelveChildrenCount += $pkg_rates->tourists;
								$EightToTwelveChildren = $EightToTwelveChildrenCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Children (Ages 4 -7)")) {
								$FourtoSevenChildrenCount += $pkg_rates->tourists;
								$FourtoSevenChildren = $FourtoSevenChildrenCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Teenager")) {
								$TeenagerCount += $pkg_rates->tourists;
								$Teenager = $TeenagerCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "SeniorCitizen")) {
								$SeniorCitizenCount += $pkg_rates->tourists;
								$SeniorCitizen = $SeniorCitizenCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "TLC Combo Spot")) {
								$TLCCount += $pkg_rates->tourists;
								$TLC = $TLCCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Locals")) {
								$LocalsCount += $pkg_rates->tourists;
								$Locals = $LocalsCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Tour Guides")) {
								$GuidesCount += $pkg_rates->tourists;
								$Guides = $GuidesCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Native Americans")) {
								$americansCount += $pkg_rates->tourists;
								$americans = $americansCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Adults - Military/Teacher")) {
								$adultMilitryCount += $pkg_rates->tourists;
								$AdultMilitryTeachers = $adultMilitryCount . ' ' . $TourPkgRates['rate_for'];
							} elseif (Str::contains($TourPkgRates['rate_for'], "Adult")) {
								$AdultCount += $pkg_rates->tourists;
								$Adult = $AdultCount . ' ' . $TourPkgRates['rate_for'];
							}

							$rate = array();
							$rate['infant'] = $infant;
							$rate['EightToTwelveChildren'] = $EightToTwelveChildren;
							$rate['FourtoSevenChildren'] = $FourtoSevenChildren;
							$rate['Adult'] = $Adult;
							$rate['Teenager'] = $Teenager;
							$rate['SeniorCitizen'] = $SeniorCitizen;
							$rate['TLC'] = $TLC;
							$rate['Locals'] = $Locals;
							$rate['Guides'] = $Guides;
							$rate['americans'] = $americans;
							$rate['AdultMilitryTeachers'] = $AdultMilitryTeachers;
							$rate['EightToTwelveChildrenMilitryTeachers'] = $EightToTwelveChildrenMilitryTeachers;
							$rate['FourToSevenChildrenMilitryTeachers'] = $FourToSevenChildrenMilitryTeachers;

							if ($rate['infant'] == "") {
								unset($rate['infant']);
							}
							if ($rate['EightToTwelveChildren'] == "") {
								unset($rate['EightToTwelveChildren']);
							}
							if ($rate['FourtoSevenChildren'] == "") {
								unset($rate['FourtoSevenChildren']);
							}
							if ($rate['Adult'] == "") {
								unset($rate['Adult']);
							}
							if ($rate['Teenager'] == "") {
								unset($rate['Teenager']);
							}
							if ($rate['SeniorCitizen'] == "") {
								unset($rate['SeniorCitizen']);
							}
							if ($rate['TLC'] == "") {
								unset($rate['TLC']);
							}
							if ($rate['Locals'] == "") {
								unset($rate['Locals']);
							}
							if ($rate['Guides'] == "") {
								unset($rate['Guides']);
							}
							if ($rate['americans'] == "") {
								unset($rate['americans']);
							}
							if ($rate['AdultMilitryTeachers'] == "") {
								unset($rate['AdultMilitryTeachers']);
							}
							if ($rate['EightToTwelveChildrenMilitryTeachers'] == "") {
								unset($rate['EightToTwelveChildrenMilitryTeachers']);
							}
							if ($rate['FourToSevenChildrenMilitryTeachers'] == "") {
								unset($rate['FourToSevenChildrenMilitryTeachers']);
							}

							$touristsCount += $pkg_rates->tourists;
						}
					}

					$history = DB::table('tour_booking_history as tbh')
						->select('tbh.id as tbhID', 'tbh.tour_booking_id as tbhBookingID', 'tbh.operation as operation', 'tbh.details as details')
						->where('tbh.tour_booking_id', '=', $sortedDataElementsValue['tbmId'])
						->where(function ($query) {
							return $query->where('tbh.operation', '=', 'Refund')
								->orWhere('tbh.operation', '=', 'Cancelled')
								->orWhere('tbh.operation', '=', 'Payment');
						})
						->get()
						->toArray();

					$Transactions = Transaction::where('tour_booking_id', $sortedDataElementsValue['tbmId'])->first();

					if (empty($history)) {
						$BookingTotal = $BookingTotal + $sortedDataElementsValue['amount'];
						$unpainBookingTotalTras = $unpainBookingTotalTras + $sortedDataElementsValue['amount'];
					}

					if ((!empty($history)) && (empty($Transactions))) {
						$BookingTotal = $BookingTotal + $sortedDataElementsValue['amount'];
						$unpainBookingTotalTras = $unpainBookingTotalTras + $sortedDataElementsValue['amount'];
					}

					if ((!empty($history)) && (!empty($Transactions)) && ($Transactions->status == Null)) {
						$BookingTotal = $BookingTotal + $sortedDataElementsValue['amount'];
						$unpainBookingTotalTras = $unpainBookingTotalTras + $sortedDataElementsValue['amount'];
					}

					foreach ($history as $historyData) {
						$details = json_decode($historyData->details, true);
						if ($historyData->tbhBookingID == $sortedDataElementsValue['tbmId']) {
							if ($historyData->operation == 'Payment') {
								$BookingTotal = $BookingTotal + (float)$details['amount'];
							}

							if ($historyData->operation == 'Refund') {
								$RefundAmountVar = $RefundAmountVar + $details['amount'];
							}

							if ($historyData->operation == 'Cancelled') {
								$CancelledAmountVar = $CancelledAmountVar + $details['amount'];
							}
						}
					}

					$PaymentTotal = $BookingTotal - ($RefundAmountVar + $unpainBookingTotalTras);

					$amountTotal += $sortedDataElementsValue['amount'];

					if ($sortedDataElementsValue['status'] == "Booked") {
						$TotalTourists += $sortedDataElementsValue['tbmtourists'];
					} elseif ($sortedDataElementsValue['status'] == "Cancelled") {
						$refundTotal += $sortedDataElementsValue['amount'];
					}

					if ($CancelledAmountVar) {
						$refundTotal = $CancelledAmountVar += $refundTotal;
					}
				}

				$date = date('m-d-Y', strtotime($key2));

				$newFilteredData = array();
				$newFilteredData['bookingdate'] = $date;
				$newFilteredData['amountTotal'] = $BookingTotal;
				$newFilteredData['paidAmountTotal'] = $PaymentTotal;
				$newFilteredData['unpaidAmountTotal'] = $unpaidAmountTotal;
				$newFilteredData['refundTotal'] = $RefundAmountVar;
				$newFilteredData['touristsTotal'] = $TotalTourists;
				$newFilteredData['CustomerRateValue'] = $rate;
				$newSortedData[$key2] = $newFilteredData;
			}
		}

		return $newSortedData;
	}

	/**
	 * Tax Collection Report
	 *
	 * @param TaxCollectionReportRequest $request
	 * @return \Illuminate\Http\Response
	 */
	public function taxCollection(TaxCollectionReportRequest $request)
	{
		try {
			return $this->reportRepository->taxCollection($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Card Collection Report
	 * In this method you can view the data in web page as well you can export in CSV file.
	 *
	 * @param \App\Http\Requests\Common\BookingRequest $r
	 * @return \Illuminate\Http\Response
	 */
	public function payments(CardReportRequest $request)
	{
		try {
			return $this->reportRepository->cardcollection($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Payments Export
	 */
	public function paymentsExport(string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0, $selectedReport = "Card Collection Report", $viewBy, int $export = 0, $type = "")
	{
		$request = new CardReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'selectedReport' => $selectedReport,
			'viewDate' => $viewBy,
			'export' => $export,
			'type' => $type
		]);

		return $this->payments($request);
	}

	/**
	 * Cash Collection Report
	 *
	 * @param CashReportRequest $request
	 * @return \Illuminate\Http\Response
	 */
	public function cashcollection(CashReportRequest $request)
	{
		try {
			return $this->reportRepository->cashcollection($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Cash Export
	 */
	public function cashExport(string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0, $selectedReport = "Cash Collection Report", $viewBy, int $export = 0)
	{
		$request = new CashReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'selectedReport' => $selectedReport,
			'viewDate' => $viewBy,
			'export' => $export
		]);

		return $this->cashcollection($request);
	}

	/**
	 * Check/Wire Collection Report
	 *
	 * @param CheckWireReportRequest $request
	 * @return \Illuminate\Http\Response
	 */
	public function wirecollection(CheckWireReportRequest $request)
	{
		try {
			return $this->reportRepository->wireCollection($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Export
	 * Wire Collction Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 *
	 * @param array $response
	 */
	public function wireExport(string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0, $viewBy, int $export = 1)
	{
		$request = new CheckWireReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'viewDate' => $viewBy,
			'export' => $export
		]);

		return $this->wirecollection($request);
	}

	/**
	 * Logic to get the report of closure emails sent logs
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 */
	public function lowerAntelopeCanyonClosureReport(string $fromDate = "", string $toDate = "", $tourOperatorId = 0,  $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->lowerAntelopeCanyonClosureEmailReport($fromDate, $toDate, $tourOperatorId,  $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Logic to get the customers of specific tour operators
	 *
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 */
	public function getCustomers($tourOperatorId = 0,  $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->getCustomers($tourOperatorId,  $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Transaction Report
	 *
	 * @param TransactionReportRequest $request
	 * @return \Illuminate\Http\Response
	 */
	public function transactionReport(TransactionReportRequest $request)
	{
		try {
			return $this->reportRepository->transactionReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


	/**
	 * Export
	 * Transaction Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 *
	 * @param array $response
	 */

	public function transactionExport(string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 1,  $checkCard = "" , $checkCash = "", $checkInvoiced = "", $checkCancel = "", $checkWire = "", $excludeExternal = "", $includeExternal = "", $onlyShowExternal = "", $includeExternalWithFee = "", $viewDate = "")
	{
		$request = new TransactionReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'export' => $export,
			'checkCard' => $checkCard,
			'checkCash' => $checkCash,
			'checkInvoiced' => $checkInvoiced,
			'checkCancel' => $checkCancel,
			'checkWire' => $checkWire,
			'excludeExternal' => $excludeExternal,
			'includeExternal' => $includeExternal,
			'onlyShowExternal' => $onlyShowExternal,
			'includeExternalWithFee' => $includeExternalWithFee,
			'viewDate' => $viewDate
		]);

		return $this->transactionReport($request);
	}


	/**
     * Transaction Summary Report
     *
     * @param TransactionSummaryReportRequest $request
     * @return \Illuminate\Http\Response
     */
    public function transactionSummaryReport(TransactionSummaryReportRequest $request)
	{
		try {
			return $this->reportRepository->transactionSummaryReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


	/**
	 * Export
	 * Transaction Summary Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 *
	 * @param array $response
	 */

	public function transactionSummaryExport(string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 1,  $checkCard = "" , $checkCash = "", $checkInvoiced = "", $checkCancel = "", $checkWire = "", $excludeExternal = "", $includeExternal = "", $onlyShowExternal = "", $includeExternalWithFee = "", $viewDate = "", $checkUnpaid = "")
	{
		$request = new TransactionSummaryReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'export' => $export,
			'checkCard' => $checkCard,
			'checkCash' => $checkCash,
			'checkInvoiced' => $checkInvoiced,
			'checkCancel' => $checkCancel,
			'checkWire' => $checkWire,
			'excludeExternal' => $excludeExternal,
			'includeExternal' => $includeExternal,
			'onlyShowExternal' => $onlyShowExternal,
			'includeExternalWithFee' => $includeExternalWithFee,
			'viewDate' => $viewDate,
			'checkUnpaid' => $checkUnpaid
		]);

		return $this->transactionSummaryReport($request);
	}

	/**
	 * @return JsonResponse
	 * @param  Request $request
	 */
	public function getAffiliateSeatsReportDetail(Request $request)
	{
		try {
			$response = $this->reportRepository->getAffiliateSeatsReportDetail($request);
			return response()->json($response, 200);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
     * Add-ons Package Report
     *
     * @param AddonsPackageReportRequest $request
     * @return \Illuminate\Http\Response
     */
    public function addonsPackageReport(AddonsPackageReportRequest $request)
	{
		try {
			return $this->reportRepository->addonsPackageReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Export
	 * Add-ons Package Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 * @param int $addOnName
	 *
	 * @param array $response
	 */

	public function addonsPackageExport(string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 1, string $addOnName = "")
	{
		$request = new AddonsPackageReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'export' => $export,
			'addOnName' => $addOnName,
		]);

		return $this->addonsPackageReport($request);
	}

	/**
     * Add-ons Package Details Report
     *
     * @param AddonsPackageDetailsReportRequest $request
     * @return \Illuminate\Http\Response
     */
    public function addonsPackageDetailsReport(AddonsPackageDetailsReportRequest $request)
	{
		try {
			return $this->reportRepository->addonsPackageDetailsReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Export
	 * Add-ons Package Details Report
	 *
	 * @param string $fromDate
	 * @param string $toDate
	 * @param int $tourOperatorId
	 * @param int $tourPackageId
	 * @param int $export
	 *
	 * @param array $response
	 */

	public function addonsPackageDetailsExport(string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0, int $export = 1, string $addOnName = "")
	{
		$request = new AddonsPackageDetailsReportRequest([
			'dateFrom' => $fromDate,
			'dateTo' => $toDate,
			'tourOperator' => $tourOperatorId,
			'tourPackage' => $tourPackageId,
			'export' => $export,
			'addOnName' => $addOnName,
		]);

		return $this->addonsPackageDetailsReport($request);
	}

	/**
     * Add-ons Package Value Report
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function addonsPackageValueReport(Request $request)
	{
		try {
			return $this->reportRepository->addonsPackageValueReport($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * OTAs [TripAdvisor | GetYourGuide] Report
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function otaBookingReport(Request $request, string $fromDate, string $toDate, int $tourOperatorId = 0, int $tourPackageId = 0, string $otaPlatform = "", bool $export = false)
	{
		try {
			return $this->reportRepository->otaBookingReport($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId, $otaPlatform, $export);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
}
