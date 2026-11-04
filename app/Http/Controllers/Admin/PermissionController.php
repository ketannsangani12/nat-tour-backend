<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Models\Permission;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;
use App\Constants\Constants;
use App\Services\Permissions\PermissionCacheService;

class PermissionController extends Controller
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
			'page_title' => 'Add Permission',
			'formSubmitRoute' => route(routePrefix() . '.permission.store'),
			'permission' => new Permission
		];

		return view('users.admin.permission-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\PermissionRequest $request
	 * @return mixed
	 */
	public function store(PermissionRequest $request)
	{
		try {
			$permission = new Permission;
			$permission->name = Str::slug($request->display_name);
			$permission->display_name = $request->display_name;
			$permission->guard_name = 'tour_operator_staff';
			$permission->description = $request->description;
			$permission->created_from = Constants::BACKEND;
			$permission->save();

			// Clear cached permissions once after permission creation
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add permission.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Permissions'];

		return view('users.admin.permission', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$permission = Permission::/*orderBy('display_order')*/orderBy('name')
			->select(['id', 'display_name as name', /*'display_order', 'status'*/ 'description']);

		return Datatables::of($permission)
			->addIndexColumn()

			->editColumn('action', function ($permission) {
				return '<a href="' . route(routePrefix() . '.permission.edit', ['permission' => $permission->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.permission.destroy', ['permission' => $permission->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$permission = Permission::findOrFail($id);

		$vars = [
			'page_title' => 'Edit Permission',
			'formSubmitRoute' => route(routePrefix() . '.permission.update', ['permission' => $permission]),
			'permission' => $permission
		];

		return view('users.admin.permission-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\PermissionRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(PermissionRequest $request, $id)
	{
		try {
			$permission = Permission::find($id);

			if (!$permission) {
				return sendJsonErrMsg('Permission not found.', 404);
			}

			$permission->name = Str::slug($request->display_name);
			$permission->display_name = $request->display_name;
			$permission->guard_name = 'tour_operator_staff';
			$permission->description = $request->description;
			$permission->created_from = Constants::BACKEND;
			$permission->save();

			// Clear cached permissions once after permission update
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update permission.');
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
			DB::table('permissions')
				->where('id', $id)
				->delete();

			// Clear cached permissions once after permission deletion
			$this->permissionCache->forgetCachedPermissions();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete permission.');
		}
	}
}
