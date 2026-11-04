<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTenant;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Partnership;
use App\Models\Staff;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$vars = [
			'page_title' => 'Add Tenant',
		];

		return view('users.common.tenant-add', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  Request $request
	 * @return mixed
	 */
	public function store(Request $request)
	{
		try {
			// Validate input
			$validated = $request->validate([
				'subdomain' => 'required|string|alpha_dash|max:255|unique:tenants,id',
				'name' => 'required|string|max:255',
				'timezone' => 'required|timezone',
			]);

			// Check for existing tenant
			$checkTenantExistance = Tenant::find($request->subdomain);
			if ($checkTenantExistance) {
				throw new Exception("The same tenant already exists in the system.");
			}

			// Dispatch job with validated data
			dispatch(new GenerateTenant($validated));

			return redirect()
				->route(routePrefix() . '.tenant.index')
				->with('message', 'Tenant will be formed in the background shortly; please check back in 5 minutes.');
		} catch (Exception $exception) {
			Log::error($exception);

			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Tenants'];

		return view('users.common.tenants', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dataTable
	 * @return mixed
	 */
	public function indexAHR(DataTables $dataTable)
	{
		$query = DB::table('tenants AS t')
			->join('domains AS d', 'd.tenant_id', '=', 't.id')
			->select([
				't.id', 't.name', 'd.domain', 't.timezone'
			]);

		return Datatables::of($query)
			->addIndexColumn()
			->editColumn('action', function ($tenant) {
				return '<div class="flex items-center"><a href="https://' . $tenant->domain . '" class="btn btn-primary btn-sm btn-primary-small mr-2 !flex items-center" target="__blank" rel="noopener noreferrer"><i class="fa fa-arrow-alt-circle-right mr-2"></i> Login</a>' .
					'<button data-url="' . route('admin.tenant.destroy', ['id' => $tenant->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small mr-2 !flex items-center"><i class="far fa-trash-alt mr-2"></i> Delete</button>' .
					'<button data-tenant-id="' . $tenant->id . '" data-tenant-name="' . e($tenant->name) . '" class="btn btn-sm btn-warning-small !flex items-center btn-ota-keys"><i class="fas fa-key mr-2"></i> OTAs</button>' .
					'</div>';
			})
			->rawColumns(['action'])
			->make(true);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param string $id
	 * @return mixed
	 */
	public function edit(string $id)
	{
		$tenant = DB::table('tenants AS t')
			->join('domains AS d', 'd.tenant_id', '=', 't.id')
			->where('t.id', $id)
			->first([
				't.id', 't.name', 'd.domain', 't.timezone'
			]);

		if (!$tenant) {
			throw new Exception("Unable to edit tenant.");
		}

		$vars = [
			'page_title' => 'Edit Tenant',
			'tenant' => $tenant
		];

		return view('users.common.tenant-edit', $vars);
	}

	/**
	 * Update tour operator's logo.
	 *
	 * @param Request $request
	 * @param string $id
	 * @return mixed
	 */
	public function update(Request $request, string $id)
	{
		try {
			$tenant = DB::table('tenants AS t')
				->join('domains AS d', 'd.tenant_id', '=', 't.id')
				->where('t.id', $id)
				->first([
					't.id', 't.name', 'd.domain', 't.timezone'
				]);

			return redirect()
				->route(routePrefix() . '.tenant.edit', ['id' => $tenant->id])
				->with('message', 'Tenant updated.');
		} catch (Exception $exception) {
			Log::error($exception);

			return back()
				->withErrors(['errors' => 'Unable to update tenant.']);
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param string $id
	 * @return mixed
	 */
	public function destroy(string $id)
	{
		try {
			$tenant = Tenant::find($id);

			// $tenant->softDelete();

			$tenant->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete tenant.');
		}
	}

	/**
	 * Switch the tenant from the calendar section
	 * 
	 * @param string $tenantId
	 */
	public function switchCompany($tenantId)
	{
		$userType = userType();

		$loggedInUserId = Auth::id();

		$partnership = Partnership::where('current_user_id', $loggedInUserId)
			->where('current_user_type', $userType)
			->where('tenant_id', $tenantId)
			->first();

		$domain = $tenantId . config('constants.environment.' . config('app.env'));

		$userId = Crypt::encrypt($partnership->partner_user_id);

		return response()->json([
			'url' => 'https://' . $domain . '/' . 'accept-company' . '/' . $userId . '/' . $partnership->partner_user_type
		]);
	}

	/**
	 * Login to another tenant using partnership module
	 * 
	 * @param string $userId
	 * @param string $userType
	 */
	public function acceptCompany($userId, $userType)
	{
		$userId = Crypt::decrypt($userId);

		$partnership = Partnership::where('current_user_id', $userId)
			->where('partner_user_id', $userId)
			->where('current_user_type', $userType)
			->where('tenant_id', tenant()->id)
			->first();

		if ($partnership) {
			session([
				'guard' => getGuard($userType),
				'userType' => $userType,
			]);

			if ($userType == 'Admin') {
				$user = Admin::find($userId);
				Auth::guard(getGuard($userType))->loginUsingId($user->id);
				return redirect()->route('admin.tour-operator.booking.calendar');
			} elseif ($userType == 'Tour Operator') {
				$user = Staff::find($userId);
				Auth::guard(getGuard($userType))->loginUsingId($user->id);
				return redirect()->route('tour-operator.booking.calendar');
			} else {
				$user = Customer::find($userId);
				Auth::guard(getGuard($userType))->loginUsingId($user->id);
				return redirect()->route('affiliate.booking.calendar');
			}
		}

		return redirect()->back();
	}
}
