<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TourPackage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vars = ['page_title' => 'Departments'];

        return view('users.common.department', $vars);
    }

    /**
     * Array response for DataTable.
     *
     * @param \Yajra\DataTables\DataTables $dt
     * @return mixed
     */
    public function indexAHR(DataTables $dt)
    {
        $qry = Department::get();
        return Datatables::of($qry)
            ->addIndexColumn()
            ->editColumn('status', function ($department) {
                if($department->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $department->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $department->status . '</span>';
				}
            })
            ->editColumn('action', function ($department) {
                return '<a href="' . route(routePrefix() . '.department.edit', ['department' => $department->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' . '<button data-url="' . route(routePrefix() . '.department.destroy', ['department' => $department->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
            })
            ->rawColumns(['action', 'status'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vars = [
            'page_title' => 'Add Department',
            'formSubmitRoute' => route(routePrefix() . '.department.store'),
            'department' => new Department,
            'packages' => TourPackage::active()->get()
        ];

        return view('users.department-add-edit', $vars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $department = Department::create($request->all());

            if ($department && $request->is_package_assigned && isset($request->package_ids) && !empty($request->package_ids)) {
                // Attach packages to the department
                $department->packages()->sync($request->package_ids);
            }
            DB::commit();

            return response()->json([]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception);
            return sendJsonErrMsg('Unable to add department.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id)
    {
        $department = Department::with('packages')->findOrFail($id);

        $packages = TourPackage::active()->get();

        $vars = [
            'page_title' => 'Edit Department',
            'formSubmitRoute' => route(routePrefix() . '.department.update', ['department' => $id]),
            'department' => $department,
            'packages' => $packages
        ];

        return view('users.department-add-edit', $vars);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param $request
     * @param int $id
     * @return mixed
     */
    public function update(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $department = Department::findOrFail($id);
            $department->fill($request->all())->saveOrFail();

            if ($department && $request->is_package_assigned && isset($request->package_ids) && !empty($request->package_ids)) {
                // Attach packages to the department
                $department->packages()->sync($request->package_ids);
            } else {
                $department->packages()->sync([]);
            }
            DB::commit();

            return response()->json([]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error($exception);
            return sendJsonErrMsg('Unable to update department.');
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
            Department::find($id)->delete();
            return response()->json([]);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg('Unable to delete the department.');
        }
    }
}
