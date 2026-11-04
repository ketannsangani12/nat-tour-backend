<?php

namespace App\Http\Controllers\Admin;

use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\Staff;
use App\Http\Controllers\Controller;
use App\Models\TourPromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Repositories\Report\ReportRepository;
use Exception;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use App\Models\Admin;
use App\Models\Customer;
use App\Utils\BokunUtils;
use Illuminate\Support\Facades\Auth;

class SummarydetailsReportController extends Controller
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
	 * @return mixed
	 */
	public function get_tour_operator_data(int $tourOperatorId)
	{
		try {
			$tourPackages = TourPackage::where('status', 'Active')
				->where('tour_operator_id', $tourOperatorId)
				->orderBy('name')
				->get(['id', 'name']);

			$staff = Staff::where('status', 'Active')
				->where('tour_operator_id', $tourOperatorId)
				->orderBy('first_name')
				->orderBy('last_name')
				->get(['id', 'first_name', 'last_name']);

			$taxes = DB::table('taxes AS t')
				->join('states AS s', 's.id', '=', 't.state_id')
				->join('cities AS ct', 'ct.state_id', '=', 's.id')
				->join('tour_operators AS t_o', 't_o.city_id', '=', 'ct.id')
				->where('t.status', 'Active')
				->where('t_o.id', $tourOperatorId)
				->orderBy('t.name')
				->get(['t.name', 't.value', 't.value_type']);

			return [
				'tourPackages' => $tourPackages,
				'staff' => $staff,
				'taxes' => $taxes,
			];
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	public function report(Request $request)
	{
		// Get the preselected report from session (set when redirecting from v2)
		$preselectedReport = session('selected_report', null);
		
		// Clear the session variable after reading it
		session()->forget('selected_report');
		
		$vars = [
			'page_title' => $preselectedReport ?? 'Reports',
			'pageId' => 'reports',
			'slotTimes' => [],
			'rateGroups' => [],
			'tourOperatorId' => Cookie::has('tourOpId') ? Cookie::get('tourOpId') : 0,
			'tourPackageId' => 0,
			'date' => date('Y-m-d'),
			'uType' => session('userType'),
			'staicReportList' => staticReportList(),
			'adminStaicReportList' => adminStaicReportList(),
			'touristsGroups' => [],
			'timeSlotId' => 0,
			'promocodes' => [],
			'departmentPackage' => departmentPackage(),
			'preselectedReport' => $preselectedReport // Pass to view for JavaScript
		];
		$departmentPackage = $vars['departmentPackage'];

		if (session('userType') == 'Admin') {
			$affiliates = DB::table('customers')
				->where('is_affiliate', 'Yes')
				->whereNotIn('name', ['TripAdvisor', 'Bokun'])
				->where('bokun', 0)
				->where('deleted_by', 0)
				->get(['id', 'name']);
		} else if (session('userType') == 'Affiliate') {
			$affiliates = DB::table('customers')
				->where('is_affiliate', 'Yes')
				->whereNotIn('name', ['TripAdvisor', 'Bokun'])
				->where('bokun', 0)
				->where('customers.id', Auth::id())
				->where('deleted_by', 0)
				->get(['id', 'name']);
		} else {
			$affiliates = DB::table('customers')
				->where('is_affiliate', 'Yes')
				->whereNotIn('name', ['TripAdvisor', 'Bokun'])
				->where('bokun', 0)
				->where('deleted_by', 0)
				->when($departmentPackage, function ($query) {
					return $query->where('department_id', Auth::user()->department_id);
				})
				->get(['id', 'name']);
		}

		$promocodes = TourPromotion::get(['id', 'code','status']);

		$vars = [
			'affiliates' => $affiliates,
			'promocodes' => $promocodes
		] + $vars;

		$userNames = [];

		if ($departmentPackage) {
			$admins = Admin::where('department_id', Auth::user()->department_id)->get(['id', 'name']);
			$staff = Staff::where('department_id', Auth::user()->department_id)->get();
			$customers = Customer::where('department_id', Auth::user()->department_id)->isAffiliate()->get(['id', 'name']);
		} else {
			$admins = Admin::all(['id', 'name']);
			$staff = Staff::all();
			$customers = Customer::isAffiliate()->get(['id', 'name']);
		}

		foreach ($admins as $admin) {
			$role = $admin->roles();
			$userNames[] = [
				'id' => $admin->id,
				'name' => $admin->name,
				'role' => isset($role->first()->display_name) ? $role->first()->display_name : null,
			];
		}

		foreach ($staff as $staffMember) {
			$role = $staffMember->roles();
			$userNames[] = [
				'id' => $staffMember->id,
				'name' => $staffMember->first_name . ' ' . $staffMember->last_name,
				'role' => isset($role->first()->display_name) ? $role->first()->display_name : null,
			];
		}

		foreach ($customers as $customer) {
			$role = $customer->roles();
			$userNames[] = [
				'id' => $customer->id,
				'name' => $customer->name,
				'role' => isset($role->first()->display_name) ? $role->first()->display_name : null,
			];
		}

		$vars['userNames'] = $userNames;

		$list = [];
		if (session('userType') == 'Admin') {
			$tourOperators = TourOperator::where('status', 'Active')
				->orderBy('name')
				->get(['id', 'name','logo']);

			$vars['tourOperators'] = $tourOperators;
			$vars['tourPackages'] = $vars['taxes'] = $vars['staff'] = [];
			$vars['tourOperatorName'] = '';
			$vars['tourOperatorLogo'] = ($tourOperators[0]->logo != '') ? tourOperatorLogo(null,$tourOperators[0]->logo) : '';
		} else {
			$tourOperatorDetails = TourOperator::find(Auth::user()->tour_operator_id);
			$vars['tourOperatorName'] = $tourOperatorDetails->name;
			$vars['tourOperatorId'] = Auth::user()->tour_operator_id;
			$vars['tourOperatorLogo'] = ($tourOperatorDetails->logo != '') ? tourOperatorLogo(null,$tourOperatorDetails->logo) : '';

			if ($departmentPackage) {
				$vars['tourPackages'] = TourPackage::when(userType() == 'Affiliate', function ($q) {
					return $q->whereHas('packageAffiliate', function ($q) {
						$q->where('affiliate_id', Auth::id());
					});
				})
					->when($departmentPackage, function ($query) use ($departmentPackage) {
						return $query->whereIn('id', $departmentPackage);
					})
					->orderBy('name')
					->get(['id', 'name']);
			} else {
				$vars['tourPackages'] = TourPackage::when(userType() == 'Affiliate', function ($q) {
					return $q->whereHas('packageAffiliate', function ($q) {
						$q->where('affiliate_id', Auth::id());
					});
				})
					->orderBy('name')
					->get(['id', 'name']);
			}

			$vars['reports'] = TourOperator::select('reports')->where('id', $vars['tourOperatorId'])->first();
			$reportlist = json_decode($vars['reports']['reports']) ?? [];
			$reportArray = array();
			foreach ($reportlist as $key => $report) {
				if ($request->api == 1) {
					if ($report != 'Financial Report') {
						$reportArray[] = $report;
					}
				}
			}

			if ($request->api == 1) {
				foreach ($reportArray as $key => $report) {
					$list[$key]['name'] = $report;
				}
			} else {
				foreach ($reportlist as $key => $report) {
					$list[$key]['name'] = $report;
				}
			}
		}

		$data = DB::table('tour_package_form as tpf')
			->join('tour_packages as tp', 'tp.id',  '=', 'tpf.form_package_id')
			->get(['tpf.form_fields', 'tpf.id as tour_package_form_id', 'tp.id as tour_package_id', 'tp.name as tour_package_name']);

		$addonsName = [];
		foreach ($data as $value) {
			$form_fields = json_decode($value->form_fields);
			foreach ($form_fields as $form) {
				$addonsName[] = $form->name;
			}
		}

		// Also surface labels from the newer relational add-ons builder
		// (addon_fields), not just the legacy JSON form builder above -
		// otherwise every add-on created since the schema migration is
		// unfilterable in this dropdown.
		$addonsName = array_merge($addonsName, DB::table('addon_fields')->whereNull('deleted_at')->pluck('label')->all());

		$vars['addonsName'] = array_unique($addonsName);

		if (userType() != "Admin") {
			if (userType() == "Tour Operator") {
				$staffPermission = Staff::with('permissions')->where('id', Auth::user()->id)->first();
			} else {
				$staffPermission = Customer::with('permissions')->where('id', Auth::user()->id)->first();
			}
			$permissionsArray = [];
			foreach ($staffPermission->permissions as $permission) {
				$permissionsArray[] = $permission->display_name;
			}
			$reportsArray = json_decode($vars['reports']->reports) ?? [];
			$arrayIntersect = array_intersect($permissionsArray, $reportsArray);
			if ($staffPermission && $staffPermission->can('view-payment-breakdown-report')) {
				if (in_array('Payment Breakdown Report', $reportsArray)) {
					$arrayIntersect[] = 'Payment Breakdown Report';
					sort($arrayIntersect);
				}
			}
			$vars['reports']->reports = json_encode($arrayIntersect);
		}

		$vars['ota_platforms'] = BokunUtils::getAllOtaTitles();
		if ($request->api == 1) {
			return [
				'detalis' => $vars,
				'report_list' => $list,
			];
		} else {
			return view('users.common.report', $vars);
		}
	}

	/**
	 * Pending Booking Report
	 *
	 * @return mixed
	 */
	public function pendingReport(string $fromDate = "", string $toDate = "",  $tourOperatorId = 0,  $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->pendingReport($fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	public function indexAHR(string $fromDate = "", string $toDate = "", int $tourPackageId = 0)
	{
		if ($fromDate == "")
			$fromDate = date('Y-m-d');

		if ($toDate == "")
			$toDate = date('Y-m-d');

		$qry = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tsd.deleted_by', '=', '0')
			->whereBetween('tbm.created_at', [$fromDate, $toDate])
			->select([
				'tbm.id as bookingid',
				'c.is_affiliate as is_affiliate',
				'tbm.name as name',
				'tbm.total as amount',
				'tsd.date as bookingdate',
				'tbm.created_at as createdate',
				'tbm.tourists as tourists',
				DB::raw('IFNULL(t.status,"Pending")  as payment'),
				'to.logo as touroperatorlogo',
				'tbm.status as status'
			])->orderBy('tbm.id', 'DESC');

		if ($tourPackageId && $tourPackageId !== 0) {
			$qry = $qry->where('tbm.tour_package_id', '=', $tourPackageId);
		}

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('createdate', function ($createdate) {
				return substr($createdate->createdate, 0, 10);
			})
			->make(true);
	}
}
