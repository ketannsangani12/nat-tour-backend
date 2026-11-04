<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\TaxRequest;
use Exception;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaxController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$countries = Country::isActive()
			->orderBy('name')
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Add Tax',
			'formSubmitRoute' => route(routePrefix() . '.tax.store'),
			'tax' => new Tax,
			'countries' => $countries
		];

		return view('users.admin.tax-add-edit', $vars);
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\TaxRequest $request
	 * @return mixed
	 */
	public function store(TaxRequest $request)
	{
		try {
			Tax::create($request->all());
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add tax.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Taxes'];

		return view('users.admin.taxes', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$qry = DB::table('taxes AS t')
			->join('states AS s', 's.id', '=', 't.state_id')
			->join('countries AS c', 'c.id', '=', 's.country_id')
			->where('t.deleted_by', 0)
			->select([
				't.id', 't.name AS tax_name', 't.value', 't.value_type', 't.status',
				'c.name AS country', 's.name AS state'
			]);

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('status', function ($tax) {
				if($tax->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tax->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $tax->status . '</span>';
				}
			})
			->editColumn('action', function ($tax) {
				return '<a href="' . route(routePrefix() . '.tax.edit', ['tax' => $tax->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.tax.destroy', ['tax' => $tax->id]) . '" data-method="delete" class="btn btn-danger btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$tax = DB::table('taxes AS t')
			->join('states AS s', 's.id', '=', 't.state_id')
			->where('t.id', $id)
			->first(['t.name', 't.value', 't.value_type', 't.status', 't.state_id', 's.country_id']);

		if (!$tax) {
			abort(404);
		}

		$countries = DB::table('countries')
			->where('id', $tax->country_id)
			->orWhere(function ($qry) {
				$qry->where('deleted_by', 0)
					->where('status', 'Active');
			})
			->orderBy('name')
			->get(['id', 'name']);

		$states = DB::table('states')
			->where(function ($qry) use ($tax) {
				$qry->where('country_id', $tax->country_id)
					->where('deleted_by', 0)
					->where('status', 'Active');
			})
			->orWhere('id', $tax->state_id)
			->orderBy('name')
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Edit Tax',
			'formSubmitRoute' => route(routePrefix() . '.tax.update', ['tax' => $id]),
			'tax' => $tax,
			'countries' => $countries,
			'states' => $states
		];

		return view('users.admin.tax-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\TaxRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(Request $request, int $id)
	{
		try {
			$tax = Tax::find($id);
			$tax->state_id = $request->state;
			$tax->name = $request->name;
			$tax->value = $request->value;
			$tax->value_type = $request->value_type;
			$tax->status = $request->status;
			$tax->updated_by = auth()->id();
			$tax->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update tax details.');
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
			DB::table('taxes')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => auth(session('guard'))->id(),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete tax.');
		}
	}
}
