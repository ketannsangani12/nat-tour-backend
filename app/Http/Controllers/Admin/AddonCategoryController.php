<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddonCategoryRequest;
use App\Models\AddonCategory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class AddonCategoryController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$vars = [
			'page_title' => 'Add Addon Category',
			'formSubmitRoute' => route(routePrefix() . '.addon-category.store'),
			'addonCategory' => new AddonCategory
		];

		return view('users.admin.addon-category-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\AddonCategoryRequest $r
	 * @return mixed
	 */
	public function store(AddonCategoryRequest $r)
	{
		try {
			AddonCategory::create($r->all());
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add addon category.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Addon Categories'];

		return view('users.admin.addon-categories', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$qry = DB::table('addon_categories')
                  ->whereNull('deleted_at')
                  ->get();

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('status', function ($addonCategory) {
				if($addonCategory->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $addonCategory->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $addonCategory->status . '</span>';
				}
			})
			->editColumn('action', function ($addonCategory) {
				return '<a href="' . route(routePrefix() . '.addon-category.edit', ['addon_category' => $addonCategory->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.addon-category.destroy', ['addon_category' => $addonCategory->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</button>';
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
	public function edit(int $id)
	{
		$addonCategory = AddonCategory::findOrFail($id);

		$vars = [
			'page_title' => 'Edit Addon Category',
			'formSubmitRoute' => route(routePrefix() . '.addon-category.update', ['addon_category' => $id]),
			'addonCategory' => $addonCategory
		];

		return view('users.admin.addon-category-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\AddonCategoryRequest $r
	 * @param int $id
	 * @return mixed
	 */
	public function update(AddonCategoryRequest $r, int $id)
	{
		try {
			AddonCategory::findOrFail($id)
				->fill($r->all())
				->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update addon category.');
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
			AddonCategory::findOrFail($id)->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete addon category.');
		}
	}
}
