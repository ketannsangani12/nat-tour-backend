<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StateRequest;
use App\Models\State;
use App\Models\Country;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class StateController extends Controller
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
			'page_title' => 'Add State',
			'formSubmitRoute' => route(routePrefix() . '.state.store'),
			'state' => new State,
			'countries' => $countries
		];

		return view('users.admin.state-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\StateRequest $r
	 * @return mixed
	 */
	public function store(StateRequest $r)
	{
		try {
			State::create($r->all());
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add state.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'States'];

		return view('users.admin.states', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dataTables
	 * @return mixed
	 */
	public function indexAHR(DataTables $dataTables)
	{
		$query = DB::table('states AS s')
			->join('countries AS c', 's.country_id', '=', 'c.id')
			->where('s.deleted_by', 0)
			->where('c.deleted_by', 0)
			->where('c.status', 'Active')
			->select(['s.id', 's.name AS state', 's.permit_fee', 's.status']);

		return $dataTables->of($query)
			->addIndexColumn()
			->editColumn('status', function ($state) {
				if($state->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $state->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $state->status . '</span>';
				}
			})
			->editColumn('action', function ($state) {
				return '<a href="' . route(routePrefix() . '.state.edit', ['state' => $state->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route(routePrefix() . '.state.destroy', ['state' => $state->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$state = State::findOrFail($id);

		$countries = Country::isActive()
			->orWhere('id', $state->country_id)
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Edit State',
			'formSubmitRoute' => route(routePrefix() . '.state.update', ['state' => $id]),
			'state' => $state,
			'countries' => $countries
		];

		return view('users.admin.state-add-edit', $vars);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param \App\Http\Requests\Admin\StateRequest $r
	 * @param int $id
	 * @return mixed
	 */
	public function update(StateRequest $r, int $id)
	{
		try {
			State::findOrFail($id)
				->fill($r->all())
				->saveOrFail();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update state.');
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
			DB::table('states')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => auth(session('guard'))->id(),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete state.');
		}
	}

	/**
	 * Returns array of states of the supplied country id.
	 *
	 * @param int $countryId
	 * @return mixed
	 */
	public function getStates(int $countryId)
	{
		try {
			$states = State::where('status', 'Active')
				->where('deleted_by', 0)
				->where('country_id', $countryId)
				->orderBy('name')
				->get(['id', 'name']);

			return response()->json($states);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to get states.');
		}
	}
}
