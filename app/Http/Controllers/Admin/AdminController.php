<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourOperator;
use Illuminate\Support\Facades\DB;
use App\Services\Dashboard\DashboardCalculatorService;
use App\Services\TourOperator\TourOperatorService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminController extends Controller
{
	public function dashboard(Request $request)
	{
	    $tourPackages = DB::table('tour_packages')
	        ->where('status', 'Active')
	        ->where('deleted_by', 0)
	        ->orderBy('name')
	        ->get(['id', 'name']);
		
		$tourOperator = TourOperatorService::getTourOperator();
        $tz = $tourOperator->timezone ?? config('app.timezone');

	    return view('users.admin.new-dashboard', [
	        'page_title'    => 'Dashboard',
	        'pageId'        => 'dashboard',
	        'tourPackages'  => $tourPackages,
			'timezone'      => $tz,
	    ]);
	}

	public function dashboardMetrics(Request $request)
	{
		$tourPackageIds      = array_filter((array) $request->input('tourPackageIds', []));
		$paymentTypes        = array_filter((array) $request->input('paymentTypes', []));
		$thirdPartyPlatforms = array_filter((array) $request->input('thirdPartyPlatforms', []));
		$requestFromValues   = array_filter((array) $request->input('requestFrom', []));
		$bookingSources      = array_filter((array) $request->input('bookingSources', []));
      	if ($request->filled('from') && $request->filled('to')) {
	        $fromDate = Carbon::parse($request->from)->startOfDay();
	        $toDate   = Carbon::parse($request->to)->endOfDay();
	    }

	    $now = Carbon::now();
		$currentMonthStart = $now->copy()->startOfMonth()->startOfDay();
		$currentMonthEnd   = $now->copy()->endOfMonth()->endOfDay();
		$prevMonthStart    = $now->copy()->subMonth()->startOfMonth()->startOfDay();
		$prevMonthEnd      = $now->copy()->subMonth()->endOfMonth()->endOfDay();

		$isCurrentMonth = $fromDate->equalTo($currentMonthStart) && $toDate->equalTo($currentMonthEnd);
		$isPrevMonth    = $fromDate->equalTo($prevMonthStart)    && $toDate->equalTo($prevMonthEnd);

		$isFullMonth = $isCurrentMonth || $isPrevMonth;
	   	$previousMetrics = null;

		if ($isFullMonth) {
		    $prevFrom = $fromDate->copy()->subMonth()->startOfMonth();
		    $prevTo   = $fromDate->copy()->subMonth()->endOfMonth();

		    $prevRows = DashboardCalculatorService::getDashboardRows(
		        $prevFrom,
		        $prevTo,
		        $tourPackageIds,
		        $paymentTypes,
		        $thirdPartyPlatforms,
		        $requestFromValues,
		        $bookingSources
		    );
		    $prevBookings = DashboardCalculatorService::aggregateBookings($prevRows);
		    $previousMetrics = DashboardCalculatorService::calculateFromAggregated($prevBookings);
		}

	    $rows = DashboardCalculatorService::getDashboardRows(
	        $fromDate,
	        $toDate,
	        $tourPackageIds,
	        $paymentTypes,
	        $thirdPartyPlatforms,
	        $requestFromValues,
	        $bookingSources
	    );

	    $purchaseRows = DashboardCalculatorService::getPurchaseDate(
	        $fromDate,
	        $toDate,
	        $tourPackageIds,
	        $paymentTypes,
	        $thirdPartyPlatforms,
			$requestFromValues,
			$bookingSources
	    );

		// Affiliate Summary — reuse already-fetched rows, no extra DB query
		$affiliateSummary = DashboardCalculatorService::getAffiliateSummary(
			$rows,
			$isFullMonth ? $prevRows : null
		);
		
	    $bookings = DashboardCalculatorService::aggregateBookings($rows);
	    $metrics = DashboardCalculatorService::calculateFromAggregated($bookings);
	    $dailyNet = DashboardCalculatorService::getDailyNetByDate($bookings);
		$netByPackage = DashboardCalculatorService::getNetAndBookingsByPackage($bookings);
		$peakTimes = DashboardCalculatorService::getPeakBookingTimes($bookings);
		$profitableDays = DashboardCalculatorService::getMostProfitableDays($bookings);
		$weekendVsWeekday = DashboardCalculatorService::getWeekendVsWeekday($bookings);
		$paymentDate = DashboardCalculatorService::getNetByPaymentDate($purchaseRows, $fromDate, $toDate);
		$purchaseDate = DashboardCalculatorService::aggregateBookings($purchaseRows);
		
		$touristsAndBookings = DashboardCalculatorService::getTouristsAndBookings(
			$bookings,
			$fromDate,
			$toDate
		);
		$isAffiliates = !empty($affiliateSummary);
		$stateTaxByDate = DashboardCalculatorService::getStateTaxByDate($bookings, $fromDate, $toDate);
		$avgTaxPerPerson = DashboardCalculatorService::getAvgTaxPerPersonByPackage($bookings);
		$packagePerformance = DashboardCalculatorService::getPackagePerformance($bookings);

	    return response()->json([
		    'success' => true,
		    'is_month' => $isFullMonth,
		    'metrics' => [
		        'gross'      => DashboardCalculatorService::formatCurrencyShort($metrics['total_gross']),
		        'net'        => DashboardCalculatorService::formatCurrencyShort($metrics['total_net']),
		        'refund'     => DashboardCalculatorService::formatCurrencyShort($metrics['total_refund']),
		        'discount'   => DashboardCalculatorService::formatCurrencyShort($metrics['total_discount']),
				'tax'        => DashboardCalculatorService::formatCurrencyShort($metrics['total_tax']),
				'permit_fee' => DashboardCalculatorService::formatCurrencyShort($metrics['total_permit_fee']),
		        'tourists'   => $metrics['total_tourists'],
		        'bookings'   => $metrics['total_bookings'],
		    ],
		    'comparison' => $previousMetrics ? [
		        'gross' => DashboardCalculatorService::percentageChange(
		            $metrics['total_gross'],
		            $previousMetrics['total_gross']
		        ),
		        'net' => DashboardCalculatorService::percentageChange(
		            $metrics['total_net'],
		            $previousMetrics['total_net']
		        ),
		        'refund' => DashboardCalculatorService::percentageChange(
		            $metrics['total_refund'],
		            $previousMetrics['total_refund']
		        ),
		        'discount' => DashboardCalculatorService::percentageChange(
		            $metrics['total_discount'],
		            $previousMetrics['total_discount']
		        ),
		        'tourists' => DashboardCalculatorService::percentageChange(
		            $metrics['total_tourists'],
		            $previousMetrics['total_tourists']
		        ),
		        'bookings' => DashboardCalculatorService::percentageChange(
		            $metrics['total_bookings'],
		            $previousMetrics['total_bookings']
		        ),
				'tax' => DashboardCalculatorService::percentageChange(
		            $metrics['total_tax'],
		            $previousMetrics['total_tax']
		        ),
				'permit_fee' => DashboardCalculatorService::percentageChange(
		            $metrics['total_permit_fee'],
		            $previousMetrics['total_permit_fee']
		        ),
		    ] : null,
		    'net_by_date' => $dailyNet,
		    'net_by_package' => $netByPackage,
		    'peak_times' => $peakTimes,
		    'profitable_days' => $profitableDays,
		    'weekend_vs_weekday' => $weekendVsWeekday,
		    'payment_date' => $paymentDate,
		    'purchase_date' => $purchaseDate,
			'tourists_and_bookings' => $touristsAndBookings,
			'state_tax_by_date'   => $stateTaxByDate,
			'avg_tax_per_person'  => $avgTaxPerPerson,
			'affiliate_summary' => $affiliateSummary,
			'package_performance' => $packagePerformance,
			'isAffiliates' => $isAffiliates,
		]);

	}

	public function paymentGroupsMetrics(Request $request)
	{
		$tourPackageIds      = array_filter((array) $request->input('tourPackageIds', []));
		$paymentTypes        = array_filter((array) $request->input('paymentTypes', []));
		$thirdPartyPlatforms = array_filter((array) $request->input('thirdPartyPlatforms', []));
		$requestFromValues   = array_filter((array) $request->input('requestFrom', []));
		$bookingSources      = array_filter((array) $request->input('bookingSources', []));

		if ($request->filled('from') && $request->filled('to')) {
			$fromDate = Carbon::parse($request->from)->startOfDay();
			$toDate   = Carbon::parse($request->to)->endOfDay();
		} else {
			$fromDate = Carbon::now()->startOfDay();
			$toDate   = Carbon::now()->endOfDay();
		}

		$data = DashboardCalculatorService::getPaymentAndGroupsData($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);

		// Format monetary fields using formatCurrencyShort
		$data['kpis']['total_revenue']      = DashboardCalculatorService::formatCurrencyShort($data['kpis']['total_revenue']);
		$data['kpis']['top_revenue_amount'] = DashboardCalculatorService::formatCurrencyShort($data['kpis']['top_revenue_amount']);
		$data['kpis']['premium_avg']        = DashboardCalculatorService::formatCurrencyShort($data['kpis']['premium_avg']);

		foreach ($data['breakdown'] as &$row) {
			$row['revenue']         = DashboardCalculatorService::formatCurrencyShort($row['revenue']);
			$row['avg_per_booking'] = DashboardCalculatorService::formatCurrencyShort($row['avg_per_booking']);
			$row['rev_per_tourist'] = DashboardCalculatorService::formatCurrencyShort($row['rev_per_tourist']);
		}
		unset($row);

		$data['breakdown_totals']['revenue']         = DashboardCalculatorService::formatCurrencyShort($data['breakdown_totals']['revenue']);
		$data['breakdown_totals']['avg_per_booking'] = DashboardCalculatorService::formatCurrencyShort($data['breakdown_totals']['avg_per_booking']);
		$data['breakdown_totals']['rev_per_tourist'] = DashboardCalculatorService::formatCurrencyShort($data['breakdown_totals']['rev_per_tourist']);

		return response()->json([
			'success' => true,
			'data'    => $data,
		]);
	}

	public function customersMetrics(Request $request)
	{
		$tourPackageIds      = array_filter((array) $request->input('tourPackageIds', []));
		$paymentTypes        = array_filter((array) $request->input('paymentTypes', []));
		$thirdPartyPlatforms = array_filter((array) $request->input('thirdPartyPlatforms', []));
		$requestFromValues   = array_filter((array) $request->input('requestFrom', []));
		$bookingSources      = array_filter((array) $request->input('bookingSources', []));

		if ($request->filled('from') && $request->filled('to')) {
			$fromDate = Carbon::parse($request->from)->startOfDay();
			$toDate   = Carbon::parse($request->to)->endOfDay();
		} else {
			$fromDate = Carbon::now()->startOfDay();
			$toDate   = Carbon::now()->endOfDay();
		}

		$data = DashboardCalculatorService::getCustomersData($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);

		$data['kpis']['top_customer_revenue'] = DashboardCalculatorService::formatCurrencyShort($data['kpis']['top_customer_revenue']);
		$data['kpis']['top3_revenue']         = DashboardCalculatorService::formatCurrencyShort($data['kpis']['top3_revenue']);
		$data['kpis']['avg_rev_per_tourist']  = number_format($data['kpis']['avg_rev_per_tourist'], 2);
		$data['kpis']['rev_per_tourist_min']  = number_format($data['kpis']['rev_per_tourist_min'], 2);
		$data['kpis']['rev_per_tourist_max']  = number_format($data['kpis']['rev_per_tourist_max'], 2);

		return response()->json([
			'success' => true,
			'data'    => $data,
		]);
	}

	public function insightsMetrics(Request $request)
	{
		$tourPackageIds      = array_filter((array) $request->input('tourPackageIds', []));
		$paymentTypes        = array_filter((array) $request->input('paymentTypes', []));
		$thirdPartyPlatforms = array_filter((array) $request->input('thirdPartyPlatforms', []));
		$requestFromValues   = array_filter((array) $request->input('requestFrom', []));
		$bookingSources      = array_filter((array) $request->input('bookingSources', []));

		if ($request->filled('from') && $request->filled('to')) {
			$fromDate = Carbon::parse($request->from)->startOfDay();
			$toDate   = Carbon::parse($request->to)->endOfDay();
		} else {
			$fromDate = Carbon::now()->startOfDay();
			$toDate   = Carbon::now()->endOfDay();
		}

		$data = DashboardCalculatorService::getInsightsData($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);

		return response()->json([
			'success' => true,
			'data'    => $data,
		]);
	}

	public function geographicMetrics(Request $request)
	{
		$tourPackageIds      = array_filter((array) $request->input('tourPackageIds', []));
		$paymentTypes        = array_filter((array) $request->input('paymentTypes', []));
		$thirdPartyPlatforms = array_filter((array) $request->input('thirdPartyPlatforms', []));
		$requestFromValues   = array_filter((array) $request->input('requestFrom', []));
		$bookingSources      = array_filter((array) $request->input('bookingSources', []));

		if ($request->filled('from') && $request->filled('to')) {
			$fromDate = Carbon::parse($request->from)->startOfDay();
			$toDate   = Carbon::parse($request->to)->endOfDay();
		} else {
			$fromDate = Carbon::now()->startOfDay();
			$toDate   = Carbon::now()->endOfDay();
		}

		$data = DashboardCalculatorService::getGeographicData($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);

		return response()->json([
			'success' => true,
			'data'    => $data,
		]);
	}

	public function affiliatesMetrics(Request $request)
	{
		$tourPackageIds      = array_filter((array) $request->input('tourPackageIds', []));
		$paymentTypes        = array_filter((array) $request->input('paymentTypes', []));
		$thirdPartyPlatforms = array_filter((array) $request->input('thirdPartyPlatforms', []));
		$requestFromValues   = array_filter((array) $request->input('requestFrom', []));
		$bookingSources      = array_filter((array) $request->input('bookingSources', []));

		if ($request->filled('from') && $request->filled('to')) {
			$fromDate = Carbon::parse($request->from)->startOfDay();
			$toDate   = Carbon::parse($request->to)->endOfDay();
		} else {
			$fromDate = Carbon::now()->startOfDay();
			$toDate   = Carbon::now()->endOfDay();
		}

		$data = DashboardCalculatorService::getAffiliatesData($fromDate, $toDate, $tourPackageIds, $paymentTypes, $thirdPartyPlatforms, $requestFromValues, $bookingSources);

		return response()->json([
			'success' => true,
			'data'    => $data,
		]);
	}

	public function exportDashboardPdf(Request $request)
	{
		$tourOperator = TourOperator::first();
		$logo = '';
		if ($tourOperator->logo != '') {
			$logo = tourOperatorLogo($tourOperator->tenant_id, $tourOperator->logo);
		}	
		$html     = $request->input('html', '');
		$filename = $request->input('filename', 'dashboard.pdf');

		if ($logo !== '') {
			$logoHtml = '<div style="text-align:center;margin-bottom:16px;"><img src="' . $logo . '" style="max-height:80px;object-fit:contain;" /></div>';
			if (stripos($html, '<body') !== false) {
				$html = preg_replace('/(<body[^>]*>)/i', '$1' . $logoHtml, $html, 1);
			} else {
				$html = $logoHtml . $html;
			}
		}

		$pdf = Pdf::loadHTML($html)
			->setPaper('a4', 'portrait')
			->setOptions([
				'isHtml5ParserEnabled' => true,
				'isRemoteEnabled'      => true,
				'defaultFont'          => 'sans-serif',
				'dpi'                  => 150,
			]);

		return $pdf->download($filename);
	}
}
