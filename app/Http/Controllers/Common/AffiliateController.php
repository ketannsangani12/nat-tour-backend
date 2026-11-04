<?php

namespace App\Http\Controllers\Common;

use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\AffiliateRequest;
use App\Models\Customer;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Models\PackageAffiliateAssignment;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Common\AffiliateprofileRequest;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use App\Models\TourOperator;
use Carbon\Carbon;
use App\Models\TourSlotTime;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Department;
use App\Services\Permissions\PermissionsService;
use App\Services\Permissions\PermissionCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Utils\BokunUtils;

class AffiliateController extends Controller
{
	/**
	 * The permission cache service instance.
	 *
	 * @var PermissionCacheService
	 */
	protected $permissionCache;

	/**
	 * Create a new controller instance.
	 *
	 * @param PermissionCacheService $permissionCache
	 */
	public function __construct(PermissionCacheService $permissionCache)
	{
		parent::__construct();

		$this->permissionCache = $permissionCache;
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$setting = hasSetting('INVOICE_ENABLE_DISABLE');

		$vars = [
			'page_title' => 'Add Affiliate',
			'affiliate' => new Customer,
			'setting' => $setting,
		];

		switch (session('userType')) {
			case 'Admin':
				$vars['tourOperators'] = tourOperator();
				break;
			default:
				break;
		}
		$routePrefix = routePrefix();

		$affiliateDetails = DB::table('customers AS afl')
			->where('afl.is_affiliate', 'Yes')
			->where('afl.deleted_by', 0)
			->where('afl.affiliate_processing_percentage', '>', 0)
			->where('afl.stripe_affiliate_percentage', '>', 0)
			->get(['name', 'affiliate_processing_percentage', 'stripe_affiliate_percentage']);

		$vars['affiliateDetails'] = $affiliateDetails;

		$vars['formSubmitRoute'] = route($routePrefix . '.affiliate.store');
		$vars['roles'] = Role::where('guard_name', 'affiliate')->get();
		$vars['permissions'] = Permission::where('guard_name', 'affiliate')->get(['id', 'display_name', 'guard_name']);
		$vars['permissionsArray'] = [];
		$vars['departments'] = Department::where('status', 'Active')->get();

		return view('users.common.affiliate-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param \App\Http\Requests\Common\AffiliateRequest $request
	 * @return \Illuminate\Http\Response | JsonResponse
	 */
	public function store(AffiliateRequest $request)
	{
		try {
			DB::beginTransaction();

			// Get the current tour operator (assumed tenant-specific)
			$tourOperator = tourOperator();

			// Check for existing customer with the provided email
			$affiliate = Customer::where('email', $request->email)->first();

			if ($affiliate && $affiliate->is_affiliate === 'Yes') {
				return sendJsonErrMsg('An affiliate account with the supplied email address already exist.');
			} elseif ($affiliate && $affiliate->is_affiliate === 'No') {
				$response = [
					'data' => 'exists_as_customer',
					'url' => route(routePrefix() . '.user-type-update'),
					'id' => $affiliate->id,
					'tour_operator_id' => $tourOperator->id,
					'is_subscribed' => $request->has('is_subscribed') ? 'Yes' : 'No',
					'is_price_show' => $request->has('is_price_show') ? 'Yes' : 'No',
					'is_billable' => $request->has('is_billable') ? 1 : 0,
					'is_ota' => $request->has('is_ota') ? 1 : 0,
					'is_affiliate' => 'Yes',
				];

				$response += $request->all();

				return response()->json($response);
			}

			// Prepare customer data
			$customerData = $request->except('password');
			$customerData['tour_operator_id'] = $tourOperator->id;
			$customerData['is_subscribed'] = $request->has('is_subscribed') ? 'Yes' : 'No';
			$customerData['is_price_show'] = $request->has('is_price_show') ? 'Yes' : 'No';
			$customerData['password'] = Hash::make($request->password);
			$customerData['is_affiliate'] = 'Yes';
			$customerData['is_billable'] = $request->has('is_billable') ? 1 : 0;
			$customerData['is_ota'] = $request->has('is_ota') ? 1 : 0;
			if ($request->has('is_ota')) {
				$customerData['ota_id'] = $this->generateOtaId($request->name);
			} else {
				$customerData['ota_affiliate_percentage'] = 0;
				$customerData['next_year_ota_affiliate_percentage'] = 0;
				$customerData['min_pax'] = null; // Clear pax restrictions when not an OTA affiliate
				$customerData['max_pax'] = null;
				$customerData['ota_id'] = null;
			}
			$customerData['expire_in_days'] = $request->expire_in_days;
			$customerData['department_id'] = $request->department_id;

			// Create the affiliate customer
			$customer = Customer::create($customerData);

			// Fetch the role from the current tenant's database
			$role = Role::where('guard_name', 'affiliate')
				->where('id', $request->role)
				->firstOrFail();

			// Assign the role
			$customer->assignRole($role->name);

			// Handle permissions
			if ($request->has('permission') && is_array($request->permission)) {
				// Fetch permissions from the current tenant's database
				$permissions = Permission::where('guard_name', 'affiliate')
					->whereIn('id', $request->permission)
					->pluck('name')
					->toArray();

				$customer->givePermissionTo($permissions);
			}

			DB::commit();

			// Clear cached permissions after commit
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return sendJsonErrMsg('Unable to add the affiliate.');
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\Response | JsonResponse
	 */
	public function updateSettings($id, Request $request)
	{
		// If both are provided, max must be >= min
		if ($request->min_pax && $request->max_pax && $request->max_pax < $request->min_pax) {
			return sendJsonErrMsg('Max pax must be greater than or equal to min pax.');
		}

		DB::beginTransaction();
		try {
			$affiliate = Customer::where('id', $id)->first();

			if ($affiliate) {
				$affiliate->affiliate_processing_percentage = $request->affiliate_processing_percentage;
				$affiliate->stripe_affiliate_percentage = $request->stripe_affiliate_percentage;
				$affiliate->next_year_affiliate_processing_percentage = $request->next_year_affiliate_processing_percentage;
				$affiliate->next_year_stripe_affiliate_percentage = $request->next_year_stripe_affiliate_percentage;
				$affiliate->ota_affiliate_percentage = $affiliate->is_ota ? ($request->ota_affiliate_percentage ?? $affiliate->ota_affiliate_percentage) : 0;
				$affiliate->next_year_ota_affiliate_percentage = $affiliate->is_ota ? ($request->next_year_ota_affiliate_percentage ?? $affiliate->next_year_ota_affiliate_percentage) : 0;
				$affiliate->stripe_destination_id = $request->stripe_destination_id;
				// Minimum/maximum pax per booking; null = no restriction
				$affiliate->min_pax = $request->min_pax ?: null;
				$affiliate->max_pax = $request->max_pax ?: null;
				$affiliate->updated_by = Auth::id();
				$affiliate->updated_by_user_type = userType();
				$affiliate->save();
			}


			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return sendJsonErrMsg('Unable to update the affiliate.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Affiliates'];

		return view('users.common.affiliates', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$departmentPackage = departmentPackage();

		$selectFields = ['afl.id', 'afl.name', 'afl.email', 'afl.phone_number', 'afl.status'];

		$query = DB::table('customers AS afl')
			->where('afl.is_affiliate', 'Yes')
			->where('afl.deleted_by', 0)
			->when($departmentPackage, function ($query) {
				return $query->where('afl.department_id', Auth::user()->department_id);
			})
			->select($selectFields);

		$routePrefix = routePrefix();

		return $dt->of($query)
			->addIndexColumn()
			->editColumn('status', function ($affiliate) {
				if($affiliate->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $affiliate->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $affiliate->status . '</span>';
				}
			})
			->editColumn('action', function ($affiliate) use ($routePrefix) {
				return $this->getEditButton(route($routePrefix . '.editpackageAffiliates', ['id' => $affiliate->id, 'year' => date('Y')]))
					. $this->getDeleteButton(route($routePrefix . '.affiliate.destroy', ['affiliate' => $affiliate->id]));
			})
			->rawColumns(['action', 'status'])
			->make(true);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function editpackage($id, $year)
	{
		$vars = ['page_title' => 'Edit Affiliate'];

		$affiliate = Customer::where('is_affiliate', 'Yes')->findOrFail($id);

		$routePrefix = routePrefix();

		$year = request()->year ?: null;

		$tourPackageList = TourPackage::departmentPackage()
			->where('status', 'Active')
			->where('deleted_by', '!=', 1)
			->get();

		$affTourPackage = TourPackageRate::where('affiliate_id', $id)
			->when($year != null, function ($query) use ($year) {
				$query->where('scheduling_year', $year);
			})
			->when($year == null, function ($query) {
				$query->where('scheduling_year', 0)
					->orWhere('scheduling_year', null);
			})
			->groupby('affiliate_id')
			->first(['affiliate_id', 'tour_package_id']);

		$packageRates = [];
		if ($affTourPackage != null) {
			$packageRates = TourPackageRate::where('tour_package_id', $affTourPackage->tour_package_id)
				->where('affiliate_id', $id)
				->when($year != null, function ($query) use ($year) {
					$query->where('scheduling_year', $year);
				})
				->when($year == null, function ($query) {
					$query->where('scheduling_year', 0)
						->orWhere('scheduling_year', null);
				})
				->orderByDesc('age_from')
				->orderByDesc('age_to')
				->get([
					'id',
					'tour_package_id',
					'rate_for',
					'age_from',
					'age_to',
					'rate',
					'description',
					'is_group_rate',
					'min_group_size',
					'max_group_size',
					'additional_charge',
					'orderby',
					'margin',
					'scheduling_year',
					'status',
					'permit_fee',
					'min_pax_allowed',
					'max_pax_allowed',
					'mapping_key',
					'global_rate'
				]);

			if (!$packageRates) {
				abort(500);
			}

			$tourPackageFirst = TourPackage::find($affTourPackage->tour_package_id);
		}

		$isGroupRateEnabled = (isset($tourPackageFirst) && $tourPackageFirst != null) ? $tourPackageFirst->is_group_rate_enabled : 0;
		$affiliateTourPackage = DB::table('tour_slot_times_affiliate as tsta')
			->leftJoin('affiliate_slot_times as ast', function ($query) use ($id) {
				return $query->on('ast.tour_slot_time_id', '=', 'tsta.id')
					->where('ast.affiliate_id', $id);
			})
			->first(['ast.affiliate_id', 'tsta.tour_package_id']);

		// Get time slots to manage affiliate seats limitation
		$checkAffiliateSeats = DB::table('affiliate_slot_times')->where('affiliate_id', $id)->where('deleted_by', 0)->count();
		$affiliateQuery = DB::table('tour_slot_times_affiliate as tsta')
			->leftjoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
			->join('tour_packages as tp', 'tp.id', '=', 'tsta.tour_package_id')
			->where('ast.affiliate_id', $id)
			->where('tsta.status', 'Active')
			->where('tp.tour_operator_id', $affiliate->tour_operator_id);

		$packageAffiliateTimeSlots = [];
		if ($affiliateTourPackage != null) {
			$packageAffiliateTimeSlots = $affiliateQuery->where('tour_package_id', $affiliateTourPackage->tour_package_id);
		} else {
			$packageAffiliateTimeSlots = $affiliateQuery;
		}

		if ($checkAffiliateSeats > 0) {
			$getSlotCount = DB::table('tour_slot_times_affiliate as tsta')
				->where('tsta.status', 'Active')
				->where('tsta.deleted_by', 0)
				->count();

			$getAffiliateSeatCount = DB::table('affiliate_slot_times as ast')
				->where('ast.affiliate_id', $id)
				->count();

			if ($getSlotCount == $getAffiliateSeatCount) {
				$timeSlots = $packageAffiliateTimeSlots
					->where('ast.affiliate_id', $id)
					->where('tsta.deleted_by', 0)
					->select(
						'tsta.id as time_slot_id',
						'tsta.tour_package_id',
						'tsta.time_from',
						'tsta.time_to',
						'tsta.status',
						'ast.affiliate_id',
						'ast.id as affiliate_seat_id',
						'ast.seat_limit as seats'
					)
					->orderBy('tsta.time_from')
					->get();
			} else {
				$timeSlots = $packageAffiliateTimeSlots
					->where('tsta.deleted_by', 0)
					->select(
						'tsta.id as time_slot_id',
						'tsta.tour_package_id',
						'tsta.time_from',
						'tsta.time_to',
						'tsta.status',
						'ast.affiliate_id',
						'ast.id as affiliate_seat_id',
						DB::raw('IFNULL(ast.seat_limit,0) as seats')
					)
					->orderBy('tsta.time_from')
					->get();
			}
		} else {
			$timeSlots = $packageAffiliateTimeSlots
				->where('tsta.deleted_by', 0)
				->select(
					'tsta.id as time_slot_id',
					'tsta.tour_package_id',
					'tsta.time_from',
					'tsta.time_to',
					'tsta.status',
					'ast.affiliate_id',
					DB::raw('(CASE WHEN ast.affiliate_id = ' . $id . ' THEN ast.seat_limit ELSE 0 END) AS seats'),
					DB::raw('(CASE WHEN ast.affiliate_id = ' . $id . ' THEN ast.id ELSE 0 END) AS affiliate_seat_id')
				)
				->groupBy('tsta.id')
				->orderBy('tsta.time_from')
				->get();
		}

		$tourPackage = $tourPackageList->first();
		$setting = hasSetting('INVOICE_ENABLE_DISABLE');
		$vars['roles'] = Role::where('guard_name', 'affiliate')->get();
		$vars['modelHasRole'] = DB::table('model_has_roles')->where('model_type', 'App\Models\Customer')->where('model_id', $id)->first();
		$vars['permissions'] = Permission::where('guard_name', 'affiliate')->get(['id', 'display_name', 'guard_name']);
		$permissions = DB::table('model_has_permissions')->where('model_id', $id)->where('model_type', 'App\Models\Customer')->get(['permission_id']);
		$vars['permissionsArray'] = [];
		foreach ($permissions as $key => $value) {
			$vars['permissionsArray'][] = $value->permission_id;
		}

		$assignedPackages = PackageAffiliateAssignment::where('affiliate_id', $id)
			->orderBy('package_id')
			->get();

		$vars['departments'] = Department::where('status', 'Active')->get();

		$vars += [
			'tourOperators' => tourOperator(),
			'affiliate' => $affiliate,
			'tourPackageList' => $tourPackageList,
			'packageRates' => $packageRates,
			'aff_tourPackage' => $affTourPackage,
			'aff_tourPackage_for_timeslots' => $affiliateTourPackage,
			'formSubmitRoute' => route($routePrefix . '.affiliate.update', ['affiliate' => $id]),
			'timeSlots' => $timeSlots,
			'setting' => $setting,
			'tourPackage' => $tourPackage,
			'isGroupRateEnabled' => $isGroupRateEnabled,
			'assignedPackages' => $assignedPackages,
			'id' => $id,
		];

		return view('users.common.affiliate-add-edit', $vars);
	}

	/**
	 * Assign packages to affiliate.
	 *
	 * @param int $id
	 * @param Request $request
	 * @return mixed
	 */
	public function assignPackage($id, Request $request)
	{
		DB::beginTransaction();
		$tourPackageId = $request->packageId;
		$isAssigned = ($request->has('isAssigned')) ? $request->isAssigned : '';
		$showAllPackageBookings = ($request->has('showAllPackageBookings')) ? $request->showAllPackageBookings : '';
		$checkExistence = PackageAffiliateAssignment::where('package_id', $tourPackageId)
			->where('affiliate_id', $id)
			->first();

		if (!$checkExistence && $showAllPackageBookings != '') {
			return sendJsonErrMsg("Please assign the package first.");
		}

		if ($checkExistence && ($isAssigned != '' && $isAssigned == 0)) {
			$checkExistence->forceDelete();
			DB::commit();
		} else if ($isAssigned == 1) {
			$assignment = new PackageAffiliateAssignment();
			$assignment->affiliate_id = $id;
			$assignment->package_id = $tourPackageId;
			$assignment->show_all_package_bookings = 0;
			$assignment->created_by = Auth::id();
			$assignment->created_by_user_type = userType();
			$assignment->save();
			DB::commit();
		} else if ($checkExistence && $showAllPackageBookings != '') {
			$checkExistence->show_all_package_bookings = $showAllPackageBookings;
			$checkExistence->save();
			DB::commit();
		}

		return response()->json([]);
	}

	/**
	 * Add or update the package rate for affiliate
	 *
	 * @param Request $request
	 */
	public function addUpdateRatebyaffiliate(Request $request)
	{
		$tourPackage = TourPackage::find($request->tour_package_id);

		if ($tourPackage->is_group_rate_enabled == '1') {
			$request->validate([
				'rate_for' => 'required|max:50',
				'age_from' => 'max:255',
				'age_to' => 'max:255',
				'rate' => 'required|min:0|max:99999.99',
				'additional_charge' => 'max:99999.99',
				'orderby' => 'max:100',
				'mapping_key' => 'required|numeric|min:0|max:99999',
				'min_group_size' => 'required|numeric|min:1|max:99999',
				'max_group_size' => 'required|numeric|min:1|max:99999'
			]);
		} else {
			$request->validate([
				'rate_for' => 'required|max:50',
				'age_from' => 'max:255',
				'age_to' => 'max:255',
				'rate' => 'required|min:0|max:99999.99',
				'additional_charge' => 'max:99999.99',
				'orderby' => 'max:100',
				'mapping_key' => 'required|numeric|min:0|max:99999',
			]);
		}

		try {
			$TourPackageRate = TourPackageRate::where('id', $request->tour_package_rate_id)
				->where('affiliate_id', $request->affiliate_id)
				->where('scheduling_year', $request->scheduling_year)
				->first();

			if (!empty($TourPackageRate)) {
				$mappingKey = $request->mapping_key;
				$mappingKeyRateGroupExist = TourPackageRate::where('tour_package_id', $request->tour_package_id)
					->where('id', '!=', $request->tour_package_rate_id)
					->where('affiliate_id', $request->affiliate_id)
					->where('mapping_key', $mappingKey)
					->where('scheduling_year', $request->scheduling_year)
					->count();

				if ($mappingKey != 0 && $mappingKeyRateGroupExist > 0) {
					return sendJsonErrMsg("Mapping key has been already taken.");
				}

				$TourPackageRate->affiliate_id = $request->affiliate_id;
				$TourPackageRate->tour_package_id = $request->tour_package_id;
				$TourPackageRate->rate_for = $request->rate_for;
				$TourPackageRate->age_from = $request->age_from;
				$TourPackageRate->age_to = $request->age_to;
				$TourPackageRate->rate = $request->rate;
				$TourPackageRate->permit_fee = $request->permit_fee;
				$TourPackageRate->additional_charge = $request->additional_charge;
				$TourPackageRate->min_pax_allowed = $request->min_pax_allowed;
				$TourPackageRate->max_pax_allowed = $request->max_pax_allowed;
				$TourPackageRate->orderby = $request->orderby;
				$TourPackageRate->mapping_key = $mappingKey;
				$TourPackageRate->margin = $request->margin;
				$TourPackageRate->description = $request->description;
				$TourPackageRate->global_rate = $request->global_rate;
				$TourPackageRate->status = $request->status;
				if ($tourPackage->is_group_rate_enabled == '1') {
					$TourPackageRate->is_group_rate = 1;
					$TourPackageRate->min_group_size = ($request->has('min_group_size')) ? $request->min_group_size : 0;
					$TourPackageRate->max_group_size = ($request->has('max_group_size')) ? $request->max_group_size : 0;
				}
				$TourPackageRate->updated_by = Auth::id();
				$TourPackageRate->updated_by_user_type = userType();
			} else {
				$mappingKey = $request->mapping_key;
				$mappingKeyRateGroupExist = TourPackageRate::where('tour_package_id', $request->tour_package_id)
					->where('scheduling_year', $request->scheduling_year)
					->where('affiliate_id', $request->affiliate_id)
					->where('mapping_key', $mappingKey)
					->count();
				if ($mappingKey != 0 && $mappingKeyRateGroupExist > 0) {
					return sendJsonErrMsg("Mapping key has been already taken.");
				}

				$TourPackageRate = new TourPackageRate;
				$TourPackageRate->affiliate_id = $request->affiliate_id;
				$TourPackageRate->tour_package_id = $request->tour_package_id;
				$TourPackageRate->rate_for = $request->rate_for;
				$TourPackageRate->age_from = $request->age_from;
				$TourPackageRate->age_to = $request->age_to;
				$TourPackageRate->rate = $request->rate;
				$TourPackageRate->permit_fee = $request->permit_fee;
				$TourPackageRate->additional_charge = $request->additional_charge;
				$TourPackageRate->min_pax_allowed = $request->min_pax_allowed;
				$TourPackageRate->max_pax_allowed = $request->max_pax_allowed;
				if ($tourPackage->is_group_rate_enabled == '1') {
					$TourPackageRate->is_group_rate = 1;
					$TourPackageRate->min_group_size = ($request->has('min_group_size')) ? $request->min_group_size : 0;
					$TourPackageRate->max_group_size = ($request->has('max_group_size')) ? $request->max_group_size : 0;
				}
				$TourPackageRate->orderby = $request->orderby;
				$TourPackageRate->mapping_key = $mappingKey;
				$TourPackageRate->margin = $request->margin;
				$TourPackageRate->description = $request->description;
				$TourPackageRate->scheduling_year = $request->scheduling_year;
				$TourPackageRate->global_rate = $request->global_rate;
				$TourPackageRate->status = $request->status;
				$TourPackageRate->created_by = Auth::id();
				$TourPackageRate->created_by_user_type = userType();
			}

			$TourPackageRate->save();

			if ($request->tour_package_rate_id) { // send blank response for updating record
				return response()->json([]);
			}

			// send record id in response after inserting record
			return response()->json(['tour_package_rate_id' => $TourPackageRate->id]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return sendJsonErrMsg("Unable to save the rate group.");
		}
	}

	/**
	 * Get the list of packages for affiliate
	 *
	 * @param Request $request
	 */
	public function affiliatePkgList(Request $request)
	{
		$year = $request->year ?: null;

		$packageRates = TourPackageRate::where('tour_package_id', $request->selectedPackage)
			->where('affiliate_id', $request->affiliate_id)
			->where('status', '=', 'Active')
			->where('deleted_by', '!=', 1)
			->when($year != null, function ($query) use ($year) {
				$query->where('scheduling_year', $year);
			})
			->when($year == null, function ($query) {
				$query->where('scheduling_year', 0)
					->orWhere('scheduling_year', null);
			})
			->orderByDesc('age_from')
			->orderByDesc('age_to')
			->get([
				'id',
				'tour_package_id',
				'rate_for',
				'age_from',
				'age_to',
				'rate',
				'description',
				'additional_charge',
				'orderby',
				'status',
				'is_group_rate',
				'min_group_size',
				'max_group_size',
				'mapping_key'
			]);

		$tourPackage = TourPackage::find($request->selectedPackage);
		$isGroupRateEnabled = $tourPackage->is_group_rate_enabled;

		return response()->json([
			'packageRates' => $packageRates,
			'isGroupRateEnabled' => $isGroupRateEnabled
		]);
	}

	/**
	 * Update the profile of the affiliate
	 *
	 * @param int $id
	 */
	public function profileedit($id)
	{
		try {
			$affiliate = Customer::where('is_affiliate', 'Yes')
				->findOrFail($id);

			$roles = Role::where('guard_name', 'affiliate')->get();

			$vars = [
				'page_title' => 'Edit Affiliate',
				'affiliate' => $affiliate,
				'id' => $id,
				'roles' => $roles
			];

			return view('users.common.affiliateprofile', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update details of affiliate.');
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Common\AffiliateRequest $request
	 * @param int $id
	 * @return \Illuminate\Http\Response | JsonResponse
	 */
	public function profileupdate(AffiliateprofileRequest $request, $id)
	{
		try {
			$values = $id == auth(session('guard'))->id()
				? $request->except(['status', 'profile_picture'])
				: $request->except('profile_picture');

			if (empty($values['password'])) {
				unset($values['password']);
			} else {
				$values['password'] = Hash::make($values['password']);
			}

			if ($request->hasFile('profile_picture')) {
				/** @var TourOperator $tourOperator */
				$tourOperator = TourOperator::query()->first();
				$photo        = $request->file('profile_picture');
				$fileNmExt    = fileNameExt($photo->getClientOriginalName());
				$ext          = strtolower($fileNmExt->ext);

				if (\in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'], true)) {
					$cleanName   = str_replace(' ', '_', $fileNmExt->name);
					$fileName    = "{$cleanName}_" . time() . ".{$ext}";
					$s3Path      = "images/tour-operator/" . $tourOperator->tenant_id . "/users/" . $fileName;
					$imageStream = Image::make($photo)->stream();
					/** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
					$s3 = Storage::disk('s3');
					$s3->put($s3Path, $imageStream->__toString());
					$values['profile_photo_path'] = $s3->url($s3Path);
				}
			}

			Customer::findOrFail($id)->fill($values)->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update details of affiliate.');
		}
	}

	/**
	 * Update the affiliate information
	 *
	 * @param AffiliateRequest $request
	 * @param int $id
	 */
	public function update(AffiliateRequest $request, $id)
	{
		try {
			DB::beginTransaction();

			// Get the current tour operator (assumed tenant-specific)
			$tourOperator = tourOperator();

			// Fetch the affiliate customer
			$customer = Customer::where('tour_operator_id', $tourOperator->id)
				->where('is_affiliate', 'Yes')
				->findOrFail($id);

			// Prepare update data
			$customerData = [
				'name' => $request->name,
				'email' => $request->email,
				'phone_number' => $request->phone_number,
				'is_subscribed' => $request->has('is_subscribed') ? 'Yes' : 'No',
				'is_price_show' => $request->has('is_price_show') ? 'Yes' : 'No',
				'is_billable' => $request->has('is_billable') ? 1 : 0,
				'is_ota' => $request->has('is_ota') ? 1 : 0,
				'ota_affiliate_percentage' => $request->has('is_ota') ? $customer->ota_affiliate_percentage : 0,
				'next_year_ota_affiliate_percentage' => $request->has('is_ota') ? $customer->next_year_ota_affiliate_percentage : 0,
				'status' => $request->status,
				'duration' => $request->duration,
				'day_hours' => $request->has('day_hours') ? $request->day_hours : null,
				'expire_in_days' => $request->expire_in_days,
				'department_id' => $request->department_id,
				'updated_at' => now(), // Replaced mysqlDT() with Laravel's now()
			];

			// Add password if provided
			if (!empty($request->password)) {
				$customerData['password'] = Hash::make($request->password);
			}

			// Update the customer
			$customer->update($customerData);

			// Update roles and permissions if user has sufficient privileges
			$userRole = userRole();
			$allowedRoles = ['super-admin', 'admin', 'manager'];

			if (in_array($userRole, $allowedRoles)) {
				// Prepare roles to sync
				$rolesToSync = [];
				if ($request->role) {
					$role = Role::where('guard_name', 'affiliate')
						->where('id', $request->role)
						->firstOrFail();

					$rolesToSync[] = $role->name;
				}

				// Sync roles if any are provided
				if (!empty($rolesToSync)) {
					$customer->assignRole($rolesToSync);
				}

				// Handle permissions
				if ($request->has('permission') && is_array($request->permission)) {
					// Fetch permissions from the current tenant's database
					$permissions = Permission::where('guard_name', 'affiliate')
						->whereIn('id', $request->permission)
						->pluck('name')
						->toArray();
					$customer->syncPermissions($permissions);
				} else {
					// Remove all permissions if none are provided
					$customer->syncPermissions([]);
				}
			}

			DB::commit();

			// Clear cached permissions after commit (only if roles/permissions were changed)
			if (in_array($userRole, $allowedRoles)) {
				$this->permissionCache->forgetCachedPermissions();
			}

			return response()->json([]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return sendJsonErrMsg('Unable to update details of affiliate.');
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 * @return \Illuminate\Http\Response | JsonResponse
	 */
	public function destroy($id)
	{
		try {
			DB::table('customers')
				->where('is_affiliate', 'Yes')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => Auth::id(),
					'deleted_by_user_type' => userType(),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete tour promotion.');
		}
	}

	/**
	 * Update the user type from customer to affiliate.
	 *
	 * @param mixed $request
	 * @return \Illuminate\Http\Response | JsonResponse
	 */
	public function userTypeUpdate(Request $request)
	{
		try {
			$affiliate = Customer::where('email', $request->data['email'])->first();

			if ($affiliate) {
				$data = $request->all()['data'];

				$affiliate->tour_operator_id = $data['tour_operator_id'];
				$affiliate->name = $data['name'];
				$affiliate->phone_number = $data['phone_number'];
				$affiliate->password = Hash::make($data['password']);
				$affiliate->is_subscribed = $data['is_subscribed'];
				$affiliate->is_price_show = $data['is_price_show'];
				$affiliate->is_billable = $data['is_billable'];
				$affiliate->is_affiliate = $data['is_affiliate'];
				$affiliate->duration = $data['duration'];
				$affiliate->day_hours = $data['day_hours'];
				$affiliate->affiliate_processing_percentage = $data['affiliate_processing_percentage'];
				$affiliate->stripe_affiliate_percentage = $data['stripe_affiliate_percentage'];
				$affiliate->expire_in_days = $data['expire_in_days'];
				$affiliate->status = $data['status'];
				$affiliate->department_id = $data['department_id'];
				$affiliate->created_by = Auth::id();
				$affiliate->created_by_user_type = userType();
				$affiliate->save();

				// Fetch the role from the current tenant's database
				$role = Role::where('guard_name', 'affiliate')
					->where('id', $data['role'])
					->firstOrFail();

				// Assign the role
				$affiliate->assignRole($role->name);

				// Handle permissions
				if (isset($data['permission']) && !empty($data['permission'])) {
					// Fetch permissions from the current tenant's database
					$permissions = Permission::where('guard_name', 'affiliate')
						->whereIn('id', $data['permission'])
						->pluck('name')
						->toArray();

					$affiliate->givePermissionTo($permissions);
				}

				// Clear cached permissions once after all assignments
				$this->permissionCache->forgetCachedPermissions();
			}

			$url = route(routePrefix() . '.editpackageAffiliates', ['id' => $affiliate->id, 'year' => date('Y')]);

			return response()->json(['url' => $url]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update user type.');
		}
	}

	/**
	 * Get the list of affiliate timeslots tour package wise
	 *
	 * @param Request $request
	 */
	public function affiliateTimeSlotList(Request $request)
	{
		$affiliateId = $request->affiliateId;
		$selectedPackage = $request->selectedPackage;

		$packageAffiliateTimeSlots = DB::table('tour_slot_times_affiliate as tsta')
			->leftjoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
			->where('ast.affiliate_id', $affiliateId)
			->where('tsta.tour_package_id', $selectedPackage)
			->where('tsta.status', 'Active')
			->where('tsta.deleted_by', 0)
			->select(
				'tsta.id as time_slot_id',
				'tsta.tour_package_id',
				'tsta.time_from',
				'tsta.time_to',
				'tsta.status',
				'ast.affiliate_id',
				DB::raw('(CASE WHEN ast.affiliate_id = ' . $affiliateId . ' THEN ast.id ELSE 0 END) AS affiliate_seat_id'),
				DB::raw('(CASE WHEN ast.affiliate_id = ' . $affiliateId . ' THEN ast.seat_limit ELSE 0 END) AS seats')
			);

		$checkAffiliateSeats = DB::table('affiliate_slot_times')
			->where('affiliate_id', $affiliateId)
			->count();

		if ($checkAffiliateSeats > 0) {
			$packageAffiliateTimeSlots->groupBy('tsta.id');
		}

		$packageAffiliateTimeSlots = $packageAffiliateTimeSlots
			->orderBy('tsta.time_from')
			->get();

		return response()->json($packageAffiliateTimeSlots);
	}

	public function affiliateTimeSlotSync(Request $request)
	{
		try {
			$data = PackageAffiliateAssignment::where('package_id', $request->selectedPackage)->where('affiliate_id', $request->affiliateId)->first();

			if (empty($data)) {
				return response()->json(['error' => 'The affiliate has not assigned in the selected package']);
			}
			if (!empty($data) && $data->show_all_package_bookings == 1) {
				return response()->json(['error' => 'The assigned affiliate only has access to view all packages in the affiliate panel']);
			}
			$affiliateSlotTimes = DB::table('affiliate_slot_times')->where('affiliate_id', $request->affiliateId)->where('deleted_by', 0)->get();

			$latestDate = TourSlotTime::where('tour_package_id', $request->selectedPackage)->orderByDesc('date')->first();

			$tourSlotTime = TourSlotTime::where('tour_package_id', $request->selectedPackage)->where('date', $latestDate->date)->get();
			$tourSlotTimesAffiliate = DB::table('tour_slot_times_affiliate')->where('tour_package_id', $request->selectedPackage)->where('deleted_by', 0)->get();
			$idArray = [];
			if (count($tourSlotTimesAffiliate) == 0) {
				foreach ($tourSlotTime as $value) {
					DB::table('tour_slot_times_affiliate')->insert(
						[
							'tour_package_id' => $request->selectedPackage,
							'time_from' => $value->time_from,
							'time_to' => $value->time_to,
							'status' => 'Active',
							'created_at' => Carbon::now(),
							'created_by' => Auth::id(),
							'created_by_user_type' => userType(),

						]
					);
				}

				foreach ($tourSlotTimesAffiliate as $val) {
					DB::table('affiliate_slot_times')->insert(
						[
							'tour_slot_time_id' => $val->id,
							'affiliate_id' => $request->affiliateId,
							'seat_limit' => 0,
							'created_at' => Carbon::now(),
							'created_by' => Auth::id(),
							'created_by_user_type' => userType(),

						]
					);
				}
			}

			if (count($tourSlotTimesAffiliate) > 0) {
				foreach ($tourSlotTimesAffiliate as $slotTimesAffiliate) {
					$fromTimeAffiliate[] = $slotTimesAffiliate->time_from;
					$fromTimeAffiliateId[] = $slotTimesAffiliate->id;
				}

				foreach ($tourSlotTime as $va) {
					$tourSlotTimeArray[] = $va->time_from;
				}

				$uniqueTimeSlot =  array_diff($tourSlotTimeArray, $fromTimeAffiliate);
				if (count($uniqueTimeSlot) > 0) {
					$tourSlotTimeNew = TourSlotTime::where('tour_package_id', $request->selectedPackage)->where('date', $latestDate->date)->whereIn('time_from', $uniqueTimeSlot)->get();
					foreach ($tourSlotTimeNew as $unique) {

						$idArray[] = DB::table('tour_slot_times_affiliate')->insertGetId(
							[
								'tour_package_id' => $request->selectedPackage,
								'time_from' => $unique->time_from,
								'time_to' => $unique->time_to,
								'status' => 'Active',
								'created_at' => Carbon::now(),
								'created_by' => Auth::id(),
								'created_by_user_type' => userType(),

							]
						);
					}

					foreach ($idArray as $id) {
						DB::table('affiliate_slot_times')->insert(
							[
								'tour_slot_time_id' => $id,
								'affiliate_id' => $request->affiliateId,
								'seat_limit' => 0,
								'created_at' => Carbon::now(),
								'created_by' => Auth::id(),
								'created_by_user_type' => userType(),

							]
						);
					}
				}

				if (count($uniqueTimeSlot) == 0 && count($affiliateSlotTimes) == 0) {

					foreach ($tourSlotTimesAffiliate as $val) {
						DB::table('affiliate_slot_times')->insert(
							[
								'tour_slot_time_id' => $val->id,
								'affiliate_id' => $request->affiliateId,
								'seat_limit' => 0,
								'created_at' => Carbon::now(),
								'created_by' => Auth::id(),
								'created_by_user_type' => userType(),

							]
						);
					}
				}
				if (count($uniqueTimeSlot) == 0 && count($affiliateSlotTimes) > 0) {
					foreach ($affiliateSlotTimes as $affiliateIdCheck) {
						$affiliateSlotTimesId[] = $affiliateIdCheck->tour_slot_time_id;
					}
					$uniqueId = array_diff($fromTimeAffiliateId, $affiliateSlotTimesId);
					foreach ($uniqueId as $id) {
						DB::table('affiliate_slot_times')->insert(
							[
								'tour_slot_time_id' => $id,
								'affiliate_id' => $request->affiliateId,
								'seat_limit' => 0,
								'created_at' => Carbon::now(),
								'created_by' => Auth::id(),
								'created_by_user_type' => userType(),

							]
						);
					}
				}
			}

			return response()->json(['success' => 'The sync process has been done.']);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to sync.');
		}
	}

	public function affiliateUserPermission(Request $request)
	{
		try {
			$rolePermissionsArray = DB::table('default_role_permission')
				->where('role_id', $request->id)
				->pluck('permission_id')
				->toArray();

			$permissions = Permission::where('guard_name', 'affiliate')
				->orderBy('display_name')
				->get(['id', 'display_name', 'category', 'guard_name']);

			$permissionsAssigned = DB::table('model_has_permissions')
				->where('model_id', $request->userId)
				->where('model_type', 'App\Models\Customer')
				->pluck('permission_id')
				->toArray();

			$html = '';
			$userRole = userRole();

			// Prepare permissions based on user role
			if (in_array($userRole, ['super-admin', 'admin', 'manager'])) {
				$permissionsAdminOrManager = collect();

				// Fetch relevant permissions based on user role only if needed
				if ($userRole == 'admin') {
					$permissionsAdminOrManager = DB::table('model_has_permissions as mhp')
						->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
						->where('mhp.model_id', Auth::id())
						->where('mhp.model_type', 'App\Models\Admin')
						->pluck('name');
				} elseif ($userRole == 'manager') {
					$permissionsAdminOrManager = DB::table('model_has_permissions as mhp')
						->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
						->where('mhp.model_id', Auth::id())
						->where('mhp.model_type', 'App\Models\Staff')
						->pluck('name');
				}

				// If specific permissions were found for the user role, filter permissions
				if ($permissionsAdminOrManager->isNotEmpty()) {
					$permissions = Permission::where('guard_name', 'affiliate')
						->whereIn('name', $permissionsAdminOrManager)
						->orderBy('display_name')
						->get(['id', 'display_name', 'category', 'guard_name']);
				}

				// Determine the array of permissions to pass to PermissionsService based on assignments
				$permissionsArray = count($permissionsAssigned) > 0 ? $permissionsAssigned : $rolePermissionsArray;

				// Generate HTML for the permissions list
				$html = PermissionsService::getPermissionsList($permissions, $permissionsArray);
			}

			return $html;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public function affiliateUserPermissionEdit(Request $request)
	{
		try {
			$html = '';
			$rolePermissionsArray = DB::table('default_role_permission')
				->where('role_id', $request->roleId)
				->pluck('permission_id')
				->toArray();

			$userRole = userRole();

			if (in_array($userRole, ['super-admin', 'admin', 'manager'])) {
				if ($request->userId) {
					$permissionsQuery = Permission::where('guard_name', 'affiliate')->orderBy('display_name');

					// Retrieve permissions based on user role
					if ($userRole === 'admin') {
						$permissionsAdmin = DB::table('model_has_permissions as mhp')
							->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
							->where('mhp.model_id', Auth::id())
							->where('mhp.model_type', 'App\Models\Admin')
							->pluck('name');

						$permissionsQuery->whereIn('name', $permissionsAdmin);
					} elseif ($userRole === 'manager') {
						$permissionsManager = DB::table('model_has_permissions as mhp')
							->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
							->where('mhp.model_id', Auth::id())
							->where('mhp.model_type', 'App\Models\Staff')
							->pluck('name');

						$permissionsQuery->whereIn('name', $permissionsManager);
					}

					$permissions = $permissionsQuery->get(['id', 'display_name', 'category', 'guard_name']);

					// Get assigned permissions for the specific user (if any)
					$permissionsAssigned = DB::table('model_has_permissions')
						->where('model_id', $request->userId)
						->where('model_type', 'App\Models\Customer')
						->pluck('permission_id')
						->toArray();

					// Choose which set of permissions to display
					if (!empty($permissionsAssigned)) {
						$html = PermissionsService::getPermissionsList($permissions, $permissionsAssigned);
					} elseif ($request->roleId) {
						$html = PermissionsService::getPermissionsList($permissions, $rolePermissionsArray);
					}
				}
			}

			return $html;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public function affiliateUserAccessLevel(Request $request)
	{
		try {
			$data = PermissionsService::accessLevel($request, 'affiliate', 'App\Models\Customer');
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Generate the OTA ID slug from the affiliate display name.
	 * Delegates to BokunUtils::toResellerSlug() — the single source of truth for reseller slug format.
	 * Uniqueness is enforced upstream in AffiliateRequest before this is called.
	 */
	private function generateOtaId(string $name): string
	{
		return BokunUtils::toResellerSlug($name);
	}
}
