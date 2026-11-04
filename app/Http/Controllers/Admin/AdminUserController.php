<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Http\Requests\Admin\AdminProfileRequest;
use App\Http\Requests\Admin\AdminPasswordChange;
use App\Models\Admin;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;
use App\Models\Department;
use App\Models\TourOperator;
use App\Services\Permissions\PermissionsService;
use App\Services\Permissions\PermissionCacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class AdminUserController extends Controller
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
		$vars = [
			'page_title' => 'Add Admin User',
			'formSubmitRoute' => route(routePrefix() . '.admin-user.store'),
			'admin' => new Admin
		];
		$userRole = userRole();
		if ($userRole == 'super-admin') {
			$vars['roles'] = Role::where('guard_name', 'admin')->whereIn('name', ['admin', 'super-admin'])->get();
		} else {
			$vars['roles'] = Role::where('guard_name', 'admin')->where('name', 'admin')->get();
		}
		$vars['permissions'] = Permission::where('guard_name', 'admin')->get(['id', 'display_name', 'guard_name']);
		$vars['permissionsArray'] = [];
		$vars['departments'] = Department::where('status', 'Active')->get();
		return view('users.admin.admin-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\AdminUserRequest $r
	 * @return mixed
	 */
	public function store(AdminUserRequest $request)
	{
		try {
			DB::beginTransaction();

			// Prepare admin data
			$adminData = $request->all();
			if (!empty($adminData['password'])) {
				$adminData['password'] = Hash::make($adminData['password']);
			}

			// Create the admin user in the current tenant's database
			$admin = Admin::create($adminData);

			// Fetch the role from the current tenant's database
			$role = Role::where('guard_name', 'admin')
				->where('id', $request->role)
				->firstOrFail();

			// Assign the role to the user
			$admin->assignRole($role->name);

			// Handle permissions
			if ($request->has('permission') && is_array($request->permission)) {
				// Fetch permissions from the current tenant's database
				$permissions = Permission::where('guard_name', 'admin')
					->whereIn('id', $request->permission)
					->pluck('name')
					->toArray();

				// Assign permissions to the user
				$admin->givePermissionTo($permissions);
			}

			DB::commit();

			// Clear cached permissions after commit
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return sendJsonErrMsg('Unable to add admin user.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Admin Users'];

		return view('users.admin.admins', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function indexAHR(Request $request)
	{
		$admins = collect();
		$userRole = userRole();
		if ($userRole == 'super-admin') {
			$admins = Admin::select(['id', 'name', 'email', 'status']);
		} else if ($userRole == 'admin') {
			$admins = Admin::role('admin')->select(['id', 'name', 'email', 'status']);
		}

		return Datatables::of($admins)
			->addIndexColumn()
			->editColumn('status', function ($admin) {
				if($admin->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $admin->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $admin->status . '</span>';
				}
			})
			->editColumn('action', function ($admin) {
				return '<a href="' . route(routePrefix() . '.admin-user.edit', ['admin_user' => $admin]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.admin-user.destroy', ['admin_user' => $admin]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
	public function editProfile($id)
	{
		$admin = Admin::findOrFail($id);
		$roles = Role::where('guard_name', 'admin')
			->whereIn('name', ['admin', 'super-admin'])
			->get();

		$modelHasRole = DB::table('model_has_roles')->where('model_type', 'App\Models\Admin')->where('model_id', $id)->first();

		$vars = [
			'page_title' => 'Edit Admin User',
			'admin' => $admin,
			'id' => $id,
			'roles' => $roles,
			'modelHasRole' => $modelHasRole,
		];

		return view('users.admin.adminprofile', $vars);
	}

	public function updateProfile(AdminProfileRequest $request, int $id)
	{
		try {
			$admin = Admin::findOrFail($id);

			$values = $id == auth(session('guard'))->id()
				? $request->except(['status', 'profile_picture'])
				: $request->except('profile_picture');

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

			$admin->fill($values)->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update Profile.');
		}
	}

	public function edit($id)
	{
		$admin = Admin::findOrFail($id);

		$vars = [
			'page_title' => 'Edit Admin User',
			'formSubmitRoute' => route(routePrefix() . '.admin-user.update', ['admin_user' => $admin]),
			'admin' => $admin,
			'id' => $id,
		];

		$userRole = userRole();
		if ($userRole == 'super-admin') {
			$vars['roles'] = Role::where('guard_name', 'admin')
				->whereIn('name', ['admin', 'super-admin'])
				->get();
		} else {
			$vars['roles'] = Role::where('guard_name', 'admin')
				->where('name', 'admin')
				->get();
		}

		$vars['modelHasRole'] = DB::table('model_has_roles')->where('model_type', 'App\Models\Admin')->where('model_id', $id)->first();
		$vars['departments'] = Department::where('status', 'Active')->get();

		return view('users.admin.admin-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\AdminUserRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(AdminUserRequest $request, int $id)
	{
		try {
			DB::beginTransaction();

			// Fetch the admin user
			$admin = Admin::findOrFail($id);

			// Prevent updating the current user's status
			$values = $id == auth(session('guard'))->id()
				? $request->except('status')
				: $request->all();

			// Update user details
			if (empty($values['password'])) {
				$admin->fill($request->except('password'))->saveOrFail();
			} else {
				$admin->update([
					'name' => $request->name,
					'password' => Hash::make($request->password),
					'email' => $request->email,
					'status' => $request->status,
					'department_id' => $request->department_id,
				]);
			}

			// Fetch the role from the current tenant's database
			$role = Role::where('guard_name', 'admin')
				->where('id', $request->role)
				->firstOrFail();

			// Sync the role for the user
			$admin->syncRoles([$role->name]);

			// Handle permissions
			if ($request->has('permission') && is_array($request->permission)) {
				// Fetch permissions from the current tenant's database
				$permissions = Permission::where('guard_name', 'admin')
					->whereIn('id', $request->permission)
					->pluck('name')
					->toArray();

				// Sync permissions for the user
				$admin->syncPermissions($permissions);
			} else {
				// Remove all permissions if none are provided
				$admin->syncPermissions([]);
			}

			DB::commit();

			// Clear cached permissions after commit
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return sendJsonErrMsg('Unable to update admin user details.');
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
		if ($id == auth(session('guard'))->id()) {
			return sendJsonErrMsg('Your own record can not be deleted by you.');
		}

		try {
			DB::table('admins')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => auth(session('guard'))->id(),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete admin user.');
		}
	}

	public function changeProfilePassword(AdminPasswordChange $request, int $id)
	{
		try {
			Admin::where('id', $id)->update(['password' => Hash::make($request->new_password)]);
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update password.');
		}
	}

	/**
	 * Add admin user permission.
	 *
	 * @param object $request
	 * @return mixed
	 */
	public function adminUserPermission(Request $request)
	{
		try {
			// Fetch the role name directly from the role
			$role = Role::find($request->id, ['name']);

			// Retrieve permissions from the 'default_role_permission' table in one go
			$permissionsRoleArray = DB::table('default_role_permission')
				->where('role_id', $request->id)
				->pluck('permission_id')
				->toArray();

			// Retrieve admin permissions (if applicable)
			$permissionsAdmin = DB::table('model_has_permissions')
				->where('model_id', Auth::id())
				->where('model_type', 'App\Models\Admin')
				->pluck('permission_id')
				->toArray();

			// Check if the user has the 'admin' role and fetch permissions accordingly
			$permissions = Permission::where('guard_name', 'admin')
				->when(Auth::user()->roles[0]->name == 'admin', function ($query) use ($permissionsAdmin) {
					return $query->whereIn('id', $permissionsAdmin);
				})
				->orderBy('display_name')
				->get(['id', 'display_name', 'category', 'guard_name']);

			// Fetch assigned permissions for the user (this will be reused in both cases)
			$permissionsAssigned = DB::table('model_has_permissions')
				->where('model_id', $request->userId)
				->where('model_type', 'App\Models\Admin')
				->pluck('permission_id')
				->toArray();

			// Initialize the HTML variable
			$html = '';

			// If the role is admin, generate permissions list
			if ($role->name == 'admin') {
				$permissionsArray = count($permissionsAssigned) > 0 ? $permissionsAssigned : $permissionsRoleArray;
				$html = PermissionsService::getPermissionsList($permissions, $permissionsArray);
			}

			// Return the HTML (empty if the role is not 'admin')
			return $role->name == 'admin' ? $html : '';
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Edit admin user permission.
	 *
	 * @param object $request
	 * @return mixed
	 */
	public function adminUserPermissionEdit(Request $request)
	{
		try {
			$html = '';
			$role = Role::find($request->roleId, ['name']);
			$roleHasPermissions = DB::table('default_role_permission')
				->where('role_id', $request->roleId)
				->pluck('permission_id')
				->toArray();

			if ($request->userId) {
				$permissionsAdmin = DB::table('model_has_permissions')
					->where('model_id', Auth::id())
					->where('model_type', 'App\Models\Admin')
					->pluck('permission_id')
					->toArray();

				if (Auth::user()->roles->first()->name == 'admin') {
					$permissions = Permission::where('guard_name', 'admin')
						->whereIn('id', $permissionsAdmin)
						->orderBy('display_name')
						->get(['id', 'display_name', 'category', 'guard_name']);
				} else {
					$permissions = Permission::where('guard_name', 'admin')
						->orderBy('display_name')
						->get(['id', 'display_name', 'category', 'guard_name']);
				}

				$permissionsAssigned = DB::table('model_has_permissions')
					->where('model_id', $request->userId)
					->where('model_type', 'App\Models\Admin')
					->pluck('permission_id')
					->toArray();

				if (!empty($permissionsAssigned)) {
					$html = PermissionsService::getPermissionsList($permissions, $permissionsAssigned);
				} elseif ($role?->name == 'admin') {
					$html = PermissionsService::getPermissionsList($permissions, $roleHasPermissions);
				}
			}

			if ($role?->name != 'admin') {
				$html = '';
			}

			return $html;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public function adminUserAccessLevel(Request $request)
	{
		try {
			$data = PermissionsService::accessLevel($request, 'admin', 'App\Models\Admin');
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
}
