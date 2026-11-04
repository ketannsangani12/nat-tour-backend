<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Constants\Constants;
use App\Models\Permission;
use App\Services\Permissions\PermissionsService;
use App\Services\Permissions\PermissionCacheService;

class RoleController extends Controller
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
			'page_title' => 'Add Role',
			'formSubmitRoute' => route(routePrefix() . '.role.store'),
			'role' => new Role
		];

		return view('users.admin.role-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\RoleRequest $r
	 * @return mixed
	 */
	public function store(RoleRequest $request)
	{
		try {
			$role = new Role;
			$role->name = Str::slug($request->display_name);
			$role->display_name = $request->display_name;
			$role->guard_name = $request->guard_name;
			$role->description = $request->description;
			$role->created_from = Constants::BACKEND;
			$role->save();
			if (isset($request->permission)) {
				foreach ($request->permission as $permission) {
					DB::table('default_role_permission')->updateOrInsert([
						'permission_id' => $permission,
						'role_id' => $role->id,
					], [
						'permission_id' => $permission,
					]);
				}
			} else {
				$request->permission = [];
			}

			// Clear cached permissions once after role creation
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add role.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Roles'];

		return view('users.admin.roles', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$roles = Role::/*orderBy('display_order')*/orderBy('name')
			->select(['id', 'display_name as name', /*'display_order', 'status'*/ 'description']);

		return Datatables::of($roles)
			->addIndexColumn()
			// ->editColumn('status', function ($role) {
			// 	return '<span class="status-' . $role->status . '">' . $role->status . '</span>';
			// })
			->editColumn('action', function ($role) {
				return '<a href="' . route(routePrefix() . '.role.edit', ['role' => $role->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.role.destroy', ['role' => $role->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
			})
			// ->rawColumns(['action', 'status'])
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
		$role = Role::findOrFail($id);

		$vars = [
			'page_title' => 'Edit Role',
			'formSubmitRoute' => route(routePrefix() . '.role.update', ['role' => $role]),
			'role' => $role,
			'id' => $id,
		];

		return view('users.admin.role-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\RoleRequest $r
	 * @param int $id
	 * @return mixed
	 */
	public function update(RoleRequest $request, $id)
	{
		try {
			$role = Role::find($id);

			if (!$role) {
				return sendJsonErrMsg('Role not found.', 404);
			}
			$role->name = Str::slug($request->display_name);
			$role->display_name = $request->display_name;
			$role->guard_name = $request->guard_name;
			$role->description = $request->description;
			$role->created_from = Constants::BACKEND;
			$role->save();

			if (isset($request->permission)) {
				foreach ($request->permission as $permission) {
					DB::table('default_role_permission')->updateOrInsert([
						'permission_id' => $permission,
						'role_id' => $id,
					], [
						'permission_id' => $permission,
					]);
				}
			} else {
				$request->permission = [];
			}

			$permissions = DB::table('default_role_permission')->where('role_id', $id)->get(['permission_id']);
			$vars['permissionsArray'] = [];
			foreach ($permissions as $key => $value) {
				$vars['permissionsArray'][] = $value->permission_id;
			}

			$arrayDiff = array_diff($vars['permissionsArray'], $request->permission);

			if (count($arrayDiff) > 0) {
				foreach ($arrayDiff as $permission_id) {
					DB::table('default_role_permission')->where('permission_id', $permission_id)
						->where('role_id', $id)
						->delete();
				}
			}

			// Clear cached permissions once after role update
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update role details.');
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
			DB::table('default_role_permission')->where('role_id', $id)->delete();
			DB::table('roles')->where('id', $id)->delete();

			// Clear cached permissions once after role deletion
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete the role.');
		}
	}

	public function rolePermissionAdd(Request $request)
	{
		try {
			$html = '';
			$permissions = Permission::where('guard_name', $request->guard_name)->orderBy('display_name')->get(['id', 'display_name', 'category']);
			$permissionsAssigned = DB::table('default_role_permission')->where('role_id', $request->role_id)->get(['permission_id']);
			$permissionsArray = [];
			if (count($permissionsAssigned) > 0) {
				foreach ($permissionsAssigned as $key => $value) {
					$permissionsArray[] = $value->permission_id;
				}
			}
			$html = PermissionsService::getPermissionsList($permissions, $permissionsArray);
			return $html;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable get permission.');
		}
	}

	public function rolePermissionEdit(Request $request)
	{
		try {
			$html = '';
			$permissions = Permission::where('guard_name', $request->guard_name)->orderBy('display_name')->get(['id', 'display_name', 'category']);
			$permissionsAssigned = DB::table('default_role_permission')->where('role_id', $request->role_id)->get(['permission_id']);
			$permissionsArray = [];
			if (count($permissionsAssigned) > 0) {
				foreach ($permissionsAssigned as $key => $value) {
					$permissionsArray[] = $value->permission_id;
				}
			}
			$html = PermissionsService::getPermissionsList($permissions, $permissionsArray);
			return $html;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable get permission.');
		}
	}
}
