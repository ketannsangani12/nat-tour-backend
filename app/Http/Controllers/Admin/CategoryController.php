<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class CategoryController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$vars = [
			'page_title' => 'Add Category',
			'formSubmitRoute' => route(routePrefix() . '.category.store'),
			'category' => new Category
		];

		return view('users.admin.category-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\CategoryRequest $r
	 * @return mixed
	 */
	public function store(CategoryRequest $r)
	{
		try {
			Category::create($r->all());
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add category.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Categories'];

		return view('users.admin.categories', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$qry = DB::table('categories AS c')
                  ->get();

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('status', function ($category) {
				if($category->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $category->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $category->status . '</span>';
				}
			})
			->editColumn('action', function ($category) {
				return '<a href="' . route(routePrefix() . '.category.edit', ['category' => $category->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>';
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
		$category = Category::findOrFail($id);

		$vars = [
			'page_title' => 'Edit Category',
			'formSubmitRoute' => route(routePrefix() . '.category.update', ['category' => $id]),
			'category' => $category
		];

		return view('users.admin.category-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\CategoryRequest $r
	 * @param int $id
	 * @return mixed
	 */
	public function update(CategoryRequest $r, int $id)
	{
		try {
			Category::findOrFail($id)
				->fill($r->all())
				->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update category.');
		}
	}
}
