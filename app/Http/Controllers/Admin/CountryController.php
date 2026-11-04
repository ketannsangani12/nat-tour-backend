<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CountryRequest;
use App\Models\Country;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class CountryController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$vars = [
			'page_title' => 'Add Country',
			'formSubmitRoute' => route(routePrefix() . '.country.store'),
			'country' => new Country
		];

		return view('users.admin.country-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\CountryRequest $request
	 * @return mixed
	 */
	public function store(CountryRequest $request)
	{
		try {
			Country::create($request->all());
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add country.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Countries'];

		$vars['allCountries'] = Country::select('id', 'name', 'code', 'currency', 'currency_symbol', 'phone_code', 'locale')->get();
		$vars['activeCountry'] = Country::isActive()->first();

		return view('users.admin.countries', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param  \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$countries = Country::orderBy('name')
			->select(['id', 'name', 'code', 'currency', 'currency_symbol', 'phone_code', 'locale', 'status']);

		return Datatables::of($countries)
			->addIndexColumn()
			->editColumn('status', function ($country) {
				return '<span class="status-' . $country->status . '">' . $country->status . '</span>';
			})
			->editColumn('action', function ($country) {
				return '<a href="' . route(routePrefix() . '.country.edit', ['country' => $country->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.country.destroy', ['country' => $country->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$country = Country::findOrFail($id);

		$vars = [
			'page_title' => 'Edit Country',
			'formSubmitRoute' => route(routePrefix() . '.country.update', ['country' => $country]),
			'country' => $country
		];

		return view('users.admin.country-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\AdminUserRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(CountryRequest $request, int $id)
	{
		try {
			Country::findOrFail($id)
				->fill($request->all())
				->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update country.');
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
			DB::table('countries')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => auth(session('guard'))->id(),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete country.');
		}
	}

	/**
	 * Set the specified resource as active.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function setActive(Request $request)
	{
		$countryId = $request->id;

		Country::query()->update(['status' => 'Inactive']); // deactivate all
		Country::where('id', $countryId)->update(['status' => 'Active']); // activate selected

		return response()->json(['status' => 'success']);
	}
}
