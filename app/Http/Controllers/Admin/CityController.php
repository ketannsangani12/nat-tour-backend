<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CityRequest;
use App\Models\City;
use App\Models\Country;
use Exception;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CityController extends Controller
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
			'page_title' => 'Add City',
			'formSubmitRoute' => route(routePrefix() . '.city.store'),
			'city' => new City,
			'countries' => $countries
		];

		return view('users.admin.city-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\CityRequest $request
	 * @return mixed
	 */
	public function store(CityRequest $request)
	{
		try {
			City::create($request->all());
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add city.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Cities'];

		return view('users.admin.cities', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param  DataTables $dataTables
	 * @return mixed
	 */
	public function indexAHR(DataTables $dataTables)
	{
		$query = DB::table('cities AS ct')
			->join('states AS s', 's.id', '=', 'ct.state_id')
			->join('countries AS c', 'c.id', '=', 's.country_id')
			->where('ct.deleted_by', 0)
			->where('s.deleted_by', 0)
			->where('c.deleted_by', 0)
			->where('c.status', 'Active')
			->select(['ct.id', 'ct.name AS city', 'ct.timezone', 'ct.status', 's.name AS state']);

		return $dataTables->of($query)
			->addIndexColumn()
			->editColumn('status', function ($city) {
				return '<span class="status-' . $city->status . '">' . $city->status . '</span>';
			})
			->editColumn('action', function ($city) {
				return '<a href="' . route(routePrefix() . '.city.edit', ['city' => $city->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.city.destroy', ['city' => $city->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$city = DB::table('cities AS ct')
			->join('states AS s', 's.id', '=', 'ct.state_id')
			->where('ct.id', $id)
			->first(['ct.id', 'ct.name', 'ct.status', 'ct.state_id', 's.country_id', 'ct.timezone']);

		if (!$city) {
			abort(404);
		}

		$countries = DB::table('countries')
			->where('id', $city->country_id)
			->orWhere(function ($qry) {
				$qry->where('deleted_by', 0)
					->where('status', 'Active');
			})
			->get(['id', 'name']);

		$states = DB::table('states')
			->where('country_id', $city->country_id)
			->where(function ($qry) use ($city) {
				$qry->where(function ($qry2) {
					$qry2->where('deleted_by', 0)
						->where('status', 'Active');
				})
					->orWhere('id', $city->state_id);
			})
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Edit City',
			'formSubmitRoute' => route(routePrefix() . '.city.update', ['city' => $id]),
			'city' => $city,
			'countries' => $countries,
			'states' => $states
		];

		return view('users.admin.city-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\CityRequest $r
	 * @param int $id
	 * @return mixed
	 */
	public function update(CityRequest $request, int $id)
	{
		try {
			City::findOrFail($id)
				->fill([
					'country_id' => $request->country,
					'state_id' => $request->state,
					'name' => $request->name,
					'status' => $request->status,
					'timezone' => $request->timezone,
					'updated_by' => Auth::id()
				])
				->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update city.');
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
			DB::table('cities')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => Auth::id(),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete city.');
		}
	}

	/**
	 * Returns array of cities of the supplied state id.
	 *
	 * @param int $stateId
	 * @return mixed
	 */
	public function getCities(int $stateId)
	{
		try {
			$cities = City::where('status', 'Active')
				->where('deleted_by', 0)
				->where('state_id', $stateId)
				->orderBy('name')
				->get(['id', 'name', 'timezone']);

			return response()->json($cities);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to get cities.');
		}
	}

	/**
	 * Find city by city id or name; create if not found; and returns its id.
	 *
	 * @param int|string $city
	 * @return int
	 */
	public function findOrCreate($city, $stateId, $countryId)
	{
		try {
			$city2 = DB::table('cities AS ct')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->join('countries AS cn', 'cn.id', '=', 'st.country_id')
				->where([
					'ct.' . (ctype_digit($city) ? 'id' : 'name') => $city,
					'st.id' => $stateId,
					'cn.id' => $countryId,
					'ct.deleted_by' => 0,
					'st.deleted_by' => 0,
					'cn.deleted_by' => 0,
				])
				->where('cn.status', 'Active')
				->first(['ct.id', 'ct.name', 'ct.timezone']);

			if (!$city2) {
				if (ctype_digit($city))
					return 0;
				else {
					$city2 = City::create(['name' => $city]);
					return $city2->id;
				}
			} else {
				return $city2->id;
			}
		} catch (Exception $exception) {
			Log::error($exception);
			return -1;
		}
	}
}
