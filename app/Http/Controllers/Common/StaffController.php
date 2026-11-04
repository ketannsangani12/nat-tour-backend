<?php

namespace App\Http\Controllers\Common;

use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Common\StaffRequest;
use App\Models\Staff;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Common\OperatorProfileRequest;
use App\Repositories\TourOperator\TourOperatorRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Department;
use App\Services\Permissions\PermissionsService;
use App\Services\Permissions\PermissionCacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class StaffController extends Controller
{
	protected $tourOperatorRepository;
	protected $permissionCache;

	/**
	 * @param  TourOperatorRepository  $tourOperatorRepository
	 * @param  PermissionCacheService  $permissionCache
	 */
	public function __construct(TourOperatorRepository $tourOperatorRepository, PermissionCacheService $permissionCache)
	{
		parent::__construct();

		$this->tourOperatorRepository = $tourOperatorRepository;
		$this->permissionCache = $permissionCache;
	}

	/**
	 * Show the form for creating a new resource.
	 * 
	 * @return mixed
	 */
	public function create()
	{
		$title = (session('userType') == 'Admin') ? " Tour Operator " : " ";
		$vars['roles'] = Role::where('guard_name', 'tour_operator_staff')->whereNotIn('name', ['admin', 'super-admin', 'affiliate'])->get();
		$vars['subRoles'] = Role::where('guard_name', 'tour_operator_staff')->where('name', 'tour-guide')->get();
		$vars['permissions'] = Permission::where('guard_name', 'tour_operator_staff')->get(['id', 'display_name', 'guard_name']);
		$vars['permissionsArray'] = [];
		$vars['page_title'] = "Add{$title}Staff User";
		$vars['departments'] = Department::where('status', 'Active')->get();

		return view('users.common.tour-operator-staff-add', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param \App\Http\Requests\Common\StaffRequest $request
	 * @return mixed
	 */
	public function store(StaffRequest $request)
	{
		try {
			DB::beginTransaction();

			// Get the current tour operator (assumed tenant-specific)
			$tourOperator = tourOperator();

			// Create the staff user
			$staff = new Staff();
			$staff->tour_operator_id = $tourOperator->id;
			$staff->first_name = $request->first_name;
			$staff->last_name = $request->last_name;
			$staff->email = $request->email;
			$staff->password = Hash::make($request->password);
			$staff->phone_number = $request->phone_number;
			$staff->department_id = $request->department_id;
			$staff->status = $request->status;
			$staff->capacity = $request->capacity;
			$staff->save();

			// Fetch the primary role from the current tenant's database
			$role = Role::where('guard_name', 'tour_operator_staff')->where('id', $request->role)->firstOrFail();

			// Prepare roles to assign
			$rolesToAssign = [$role->name];

			// Handle sub-role if provided
			if ($request->sub_role) {
				$subRole = Role::where('guard_name', 'tour_operator_staff')->where('id', $request->sub_role)->firstOrFail();
				$rolesToAssign[] = $subRole->name;
			}

			// Assign roles to the staff
			$staff->assignRole($rolesToAssign);

			// Handle permissions
			if ($request->has('permission') && is_array($request->permission)) {
				// Fetch permissions from the current tenant's database
				$permissions = Permission::where('guard_name', 'tour_operator_staff')
					->whereIn('id', $request->permission)
					->pluck('name')
					->toArray();

				// Assign permissions to the staff
				$staff->givePermissionTo($permissions);
			}

			DB::commit();

			// Clear cached permissions after commit to ensure consistency
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			$message = session('userType') === 'Admin'
				? 'Unable to add tour operator staff user.'
				: 'Unable to add staff user.';

			return sendJsonErrMsg($message);
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$roles = Role::where('guard_name', 'tour_operator_staff')->whereNotIn('name', ['admin', 'super-admin', 'affiliate'])->get();
		$selectedRoleId = Session::get('role', '');
		$selectedRole = ($selectedRoleId != '') ? Role::find($selectedRoleId) : null;
		$vars = ['page_title' => 'Staff Users', 'roles' => $roles, 'selectedRole' => $selectedRole];	
		return view('users.common.tour-operator-staff-list', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param  \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt, Request $request)
	{
		$userRole = userRole();
		$roleFilter = ($request->has('role_filter') && $request->role_filter != '') ? $request->input('role_filter') : '';
		
		Session::put('role', $roleFilter);
		
		if ($userRole === 'affiliate') {
			// For affiliate role, return an empty datatable response
			return $dt->of(collect())->make(true);
		}

		$departmentPackage = departmentPackage();

		$query = DB::table('tour_operator_staff AS tos')
			->join('model_has_roles AS mhr', 'mhr.model_id', '=', 'tos.id')
			->join('roles AS r', 'r.id', '=', 'mhr.role_id')
			->where('mhr.model_type', 'App\Models\Staff')
			->where('tos.status', '!=', 'Deleted')
			->where('tos.deleted_by', 0)
			->when($departmentPackage, function ($q) {
				$q->where('tos.department_id', Auth::user()->department_id);
			});

		if (in_array($userRole, ['super-admin', 'admin'])) {
			if (!empty($roleFilter)) {
				$query->where('r.id', $roleFilter);
			}
			// No extra filter for these roles
		} elseif ($userRole === 'manager') {
			$query->where(function ($q) {
				$q->where('r.name', '!=', 'manager')
					->orWhere('tos.id', Auth::id());
			});
			if (!empty($roleFilter)) {
				$query->where('r.id', $roleFilter);
			}
		} else {
			$query->where('r.name', '!=', 'manager');
			if (!empty($roleFilter)) {
				$query->where('r.id', $roleFilter);
			}
		}

		// Use group_concat to merge multiple role names
		$query->select(
			'tos.id',
			'tos.first_name',
			'tos.last_name',
			'tos.email',
			'tos.phone_number',
			'tos.status',
			'r.name as role_name',
			DB::raw("GROUP_CONCAT(r.display_name ORDER BY r.display_name ASC SEPARATOR ', ') as name")
		)
			->groupBy('tos.id', 'tos.first_name', 'tos.last_name', 'tos.email', 'tos.phone_number', 'tos.status');

		$data = $query->get();
		$routePrefix = routePrefix();

		return $dt->of($data)
			->addIndexColumn()
			->editColumn('status', function ($staff) {
				if ($staff->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $staff->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $staff->status . '</span>';
				}
			})
			->editColumn('name', function ($staff) {
				$roles = explode(',', $staff->name);

				if (count($roles) === 2) {
					return sprintf(
						'<span class="text-success">%s</span>, <span class="text-primary">%s</span>',
						trim($roles[1]),
						trim($roles[0])
					);
				}

				$class = trim($staff->name) === 'Manager' ? 'text-success' : '';
				$style = $class ? '' : ' style="color:#212529;"';

				return sprintf('<span%s class="%s">%s</span>', $style, $class, e($staff->name));
			})
			->addColumn('action', function ($staff) use ($routePrefix) {
				return $this->getEditButton(route($routePrefix . '.staff.edit', ['staff' => $staff->id]))
					. $this->getDeleteButton(route($routePrefix . '.staff.destroy', ['staff' => $staff->id]));
			})
			->rawColumns(['action', 'status', 'name'])
			->make(true);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function edit(int $id)
	{
		$staff = Staff::departmentPackage()->findOrFail($id);
		$roles = Role::where('guard_name', 'tour_operator_staff')->whereNotIn('name', ['admin', 'super-admin', 'affiliate'])->get();
		$subRoles = Role::where('guard_name', 'tour_operator_staff')->where('name', 'tour-guide')->get();

		if (!$roles) {
			abort(500);
		}

		$vars = [
			'page_title' => 'Edit Staff User',
			'roles' => $roles,
			'staff' => $staff,
			'id' => $id,
			'subRoles' => $subRoles
		];

		$vars['permissions'] = Permission::where('guard_name', 'tour_operator_staff')->get(['id', 'display_name', 'guard_name']);
		$permissions = DB::table('model_has_permissions')->where('model_id', $id)->where('model_type', 'App\Models\Staff')->get(['permission_id']);
		$vars['permissionsArray'] = [];
		foreach ($permissions as $key => $value) {
			$vars['permissionsArray'][] = $value->permission_id;
		}

		$vars['roleCount'] = DB::table('model_has_roles')->where('model_type', 'App\Models\Staff')->where('model_id', $id)->count();

		$vars['tourGuideRole'] = '';
		$vars['modelHasRole'] = DB::table('model_has_roles as mhr')
			->join('roles as r', 'r.id', '=', 'mhr.role_id')
			->where('model_type', 'App\Models\Staff')
			->where('model_id', $id);

		if ($vars['roleCount'] == '2') {
			$vars['tourGuideRole'] = clone $vars['modelHasRole'];
			$vars['tourGuideRole']->where('r.name', 'tour-guide');
			$vars['modelHasRole']->where('r.name', '!=', 'tour-guide');
			$vars['tourGuideRole'] = $vars['tourGuideRole']->first();
		}

		$vars['modelHasRole'] = $vars['modelHasRole']->first();

		if ($vars['modelHasRole']->name != "tour-guide") {
			$vars['roleCount'] = 2;
		}
		$vars['departments'] = Department::where('status', 'Active')->get();
		$vars['roleTypes'] = $vars['modelHasRole']->role_id
			? DB::table('role_types')
			->where('role_id', $vars['modelHasRole']->role_id)
			->whereNull('deleted_at')
			->get()
			: [];

		return view('users.common.tour-operator-staff-edit', $vars);
	}

	/**
	 * Edit profile for the staff user
	 * 
	 * @param int $id
	 */
	public function editProfile($id)
	{
		$staff = Staff::departmentPackage()->findOrFail($id);
		$roleCount = DB::table('model_has_roles')->where('model_type', 'App\Models\Staff')->where('model_id', $id)->count();

		$modelHasRole = DB::table('model_has_roles as mhr')
			->join('roles as r', 'r.id', '=', 'mhr.role_id')
			->where('mhr.model_id', $id)
			->where('mhr.model_type', 'App\Models\Staff');

		$modelHasSubRole = '';
		if ($roleCount == 2) {
			$modelHasSubRole = clone $modelHasRole;
			$modelHasRole = $modelHasRole->where('r.name', '!=', 'tour-guide');
			$modelHasSubRole = $modelHasSubRole->where('r.name', 'tour-guide');
			$modelHasSubRole = $modelHasSubRole->first();
		}
		$modelHasRole = $modelHasRole->first();

		$roles = Role::where('guard_name', 'tour_operator_staff')->whereNotIn('name', ['admin', 'super-admin', 'affiliate'])->get();
		$subRoles = Role::where('guard_name', 'tour_operator_staff')->where('name', 'tour-guide')->get();

		$vars = [
			'page_title' => 'Edit Staff User',
			'staff' => $staff,
			'id' => $id,
			'modelHasRole' => $modelHasRole,
			'modelHasSubRole' => $modelHasSubRole,
			'subRoles' => $subRoles,
			'roles' => $roles
		];

		return view('users.common.operatorprofile', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Common\StaffRequest $request
	 * @param int $id
	 */
	public function updateProfile(OperatorProfileRequest $request, int $id)
	{
		try {
			return $this->tourOperatorRepository->updateStaffProfile($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Common\StaffRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateGeneral(StaffRequest $request, int $id)
	{
		try {
			DB::beginTransaction();

			// Get the current tour operator (assumed tenant-specific)
			$tourOperator = tourOperator();

			// Fetch the staff user
			$staff = Staff::where('tour_operator_id', $tourOperator->id)->findOrFail($id);

			// Determine allowed fields based on user role
			$userRole = userRole();
			$allowedRoles = ['super-admin', 'admin', 'manager'];
			$values = in_array($userRole, $allowedRoles)
				? $request->all()
				: $request->except(['role', 'sub_role', 'permission']);

			// Update staff details
			if (empty($values['password'])) {
				$staff->fill($request->except('password'))->save();
			} else {
				$staff->fill([
					'first_name' => $request->first_name,
					'last_name' => $request->last_name,
					'email' => $request->email,
					'phone_number' => $request->phone_number,
					'department_id' => $request->department_id,
					'status' => $request->status,
					'password' => Hash::make($request->password),
					'role_type_id' => $request->role_type_id,
					'tip_distribution_percentage' => $request->tip_distribution_percentage,
					'capacity' => $request->capacity
				])->save();
			}

			// Update roles and permissions if user has sufficient privileges
			if (in_array($userRole, $allowedRoles)) {
				// Prepare roles to sync
				$rolesToSync = [];
				if ($request->role) {
					$role = Role::where('guard_name', 'tour_operator_staff')->where('id', $request->role)->firstOrFail();
					$rolesToSync[] = $role->name;
				}

				if ($request->sub_role) {
					$subRole = Role::where('guard_name', 'tour_operator_staff')->where('id', $request->sub_role)->firstOrFail();
					$rolesToSync[] = $subRole->name;
				}

				// Sync roles if any are provided
				if (!empty($rolesToSync)) {
					$staff->syncRoles($rolesToSync);
				}

				// Handle permissions
				if ($request->has('permission') && is_array($request->permission)) {
					// Fetch permissions from the current tenant's database
					$permissions = Permission::where('guard_name', 'tour_operator_staff')
						->whereIn('id', $request->permission)
						->pluck('name')
						->toArray();
					$staff->syncPermissions($permissions);
				} else {
					// Remove all permissions if none are provided
					$staff->syncPermissions([]);
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
			return sendJsonErrMsg('Unable to update staff user details');
		}
	}

	/**
	 * Update the role for the staff user
	 * 
	 * @param Request $request
	 * @param int $id
	 */
	public function updateRole(Request $request, $id)
	{
		try {
			DB::beginTransaction();

			// Get the current tour operator (assumed tenant-specific)
			$tourOperator = tourOperator();

			// Fetch the staff user
			$staff = Staff::where('tour_operator_id', $tourOperator->id)->where('id', $id)->firstOrFail();

			// Validate and fetch the role
			$role = Role::where('guard_name', 'tour_operator_staff')->where('id', $request->role)->firstOrFail();

			// Assign the role (use syncRoles to replace existing roles)
			$staff->syncRoles([$role->name]);

			DB::commit();

			// Clear cached permissions after commit
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return sendJsonErrMsg("Unable to update staff user's role");
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function destroy(int $id)
	{
		try {
			DB::table('tour_operator_staff')
				->where('id', $id)
				->update([
					'status' => 'Deleted',
					'updated_at' => mysqlDT(),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => session('userType'),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete staff user");
		}
	}

	/**
	 * Change the status of the staff user
	 * 
	 * @param Request $request
	 * @param int $id
	 */
	public function userPermission(Request $request)
	{
		try {
			$role = Role::where('guard_name', 'tour_operator_staff')
				->where('id', $request->id)
				->first(['name']);

			$permissionsRoleArray = DB::table('default_role_permission')
				->where('role_id', $request->id)
				->pluck('permission_id')
				->toArray();

			$permissionsAssigned = DB::table('model_has_permissions')
				->where('model_id', $request->userId)
				->where('model_type', 'App\Models\Staff')
				->pluck('permission_id') // Directly get the permission IDs
				->toArray();

			$modelHasRoles = DB::table('model_has_roles')
				->where('model_type', 'App\Models\Staff')
				->where('model_id', $request->userId)
				->first();

			$html = '';
			$userRole = userRole();

			if (in_array($userRole, ['super-admin', 'admin', 'manager'])) {
				// Only load specific permissions based on the user role.
				if ($userRole === 'admin') {
					$permissionsAdmin = DB::table('model_has_permissions as mhp')
						->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
						->where('mhp.model_id', Auth::id())
						->where('mhp.model_type', 'App\Models\Admin')
						->pluck('name')
						->toArray();

					$permissions = Permission::where('guard_name', 'tour_operator_staff')
						->whereIn('name', $permissionsAdmin)
						->orderBy('display_name')
						->get(['id', 'display_name', 'category', 'guard_name']);
				} elseif ($userRole === 'manager') {
					$permissionsManager = DB::table('model_has_permissions')
						->where('model_id', Auth::id())
						->where('model_type', 'App\Models\Staff')
						->pluck('permission_id')
						->toArray();

					$permissions = Permission::where('guard_name', 'tour_operator_staff')
						->whereIn('id', $permissionsManager)
						->orderBy('display_name')
						->get(['id', 'display_name', 'category', 'guard_name']);
				} else { // super-admin
					$permissions = Permission::where('guard_name', 'tour_operator_staff')
						->orderBy('display_name')
						->get(['id', 'display_name', 'category', 'guard_name']);
				}

				// Use permissionsAssigned to build the permissions list only if user has permissions or roles assigned.
				if ($permissionsAssigned && $modelHasRoles && $modelHasRoles->role_id == $request->id) {
					$html = PermissionsService::getPermissionsList($permissions, $permissionsAssigned);
				} else {
					$html = PermissionsService::getPermissionsList($permissions, $permissionsRoleArray);
				}
			}

			return [
				'role' => $role,
				'html' => $html
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update the user permissions
	 * 
	 * @param Request $request
	 * @param int $id
	 */
	public function userPermissionEdit(Request $request)
	{
		try {
			$html = '';
			$role = Role::where('guard_name', 'tour_operator_staff')
				->where('id', $request->roleId)
				->first(['name']);

			$permissionsRoleArray = [];
			$userRole = userRole();
			$permissions = collect();

			// Check if user has 'super-admin' role and fetch permissions if needed
			if ($userRole == 'super-admin') {
				$permissionsRoleArray = DB::table('default_role_permission')
					->where('role_id', $request->id)
					->pluck('permission_id')
					->toArray();
			}

			// If user is 'super-admin', 'admin', or 'manager', proceed to fetch specific permissions
			if (in_array($userRole, ['super-admin', 'admin', 'manager'])) {
				if ($request->userId) {
					// Admin-specific permissions
					if ($userRole == 'admin') {
						$permissionsAdmin = DB::table('model_has_permissions as mhp')
							->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
							->where('mhp.model_id', Auth::id())
							->where('mhp.model_type', 'App\Models\Admin')
							->pluck('name');

						$permissions = Permission::where('guard_name', 'tour_operator_staff')
							->whereIn('name', $permissionsAdmin)
							->orderBy('display_name')
							->get(['id', 'display_name', 'category', 'guard_name']);
					}
					// Manager-specific permissions
					elseif ($userRole == 'manager') {
						$permissionsManager = DB::table('model_has_permissions')
							->where('model_id', Auth::id())
							->where('model_type', 'App\Models\Staff')
							->pluck('permission_id')
							->toArray();

						$permissions = Permission::where('guard_name', 'tour_operator_staff')
					        ->where(function ($query) use ($permissionsManager) {
					            $query->where('category', 'Board')
					                  ->orWhereIn('id', $permissionsManager);
					        })
					        ->orderBy('display_name')
					        ->get(['id', 'display_name', 'category', 'guard_name']);
					}
					// Default permissions for other roles
					else {
						$permissions = Permission::where('guard_name', 'tour_operator_staff')
							->orderBy('display_name')
							->get(['id', 'display_name', 'category', 'guard_name']);
					}

					// Fetch assigned permissions for the specific user
					$permissionsAssigned = DB::table('model_has_permissions')
						->where('model_id', $request->userId)
						->where('model_type', 'App\Models\Staff')
						->pluck('permission_id')
						->toArray();

					// Generate HTML based on assigned permissions
					if (count($permissionsAssigned) > 0) {
						$html = PermissionsService::getPermissionsList($permissions, $permissionsAssigned);
					} else {
						$html = PermissionsService::getPermissionsList($permissions, $permissionsRoleArray);
					}
				}
			}

			return ['role' => $role, 'html' => $html];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Staff user access level
	 * 
	 * @param Request $request
	 */

	public function userAccessLevel(Request $request)
	{
		try {
			$data = PermissionsService::accessLevel($request, 'tour_operator_staff', 'App\Models\Staff');
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
}
