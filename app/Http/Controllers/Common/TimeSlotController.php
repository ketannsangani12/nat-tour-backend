<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Common\TimeSlotRequest;
use App\Models\TourPackage;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class TimeSlotController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$tourPackages = TourPackage::departmentPackage()
			->orderBy('name')
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Add Time Slot',
			'tourPackages' => $tourPackages,
			'formSubmitRoute' => route(routePrefix() . '.time-slot.store'),
		];

		return view('users.common.time-slot-add', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Common\TimeSlotRequest $request
	 * @return mixed
	 */
	public function store(TimeSlotRequest $request)
	{
		DB::beginTransaction();
		try {
			if ($request->has('time_from')) {
				for ($i = 0, $timeSlots = [], $userId = auth()->id(); $i < count($request->time_from); $i++) {
					$timeSlots[] = [
						'tour_package_id' => $request->tour_package,
						'time_from' => $request->time_from[$i],
						'time_to' => empty($request->time_to[$i]) ? null : $request->time_to[$i],
						'status' => $request->status[$i],
						'created_at' => mysqlDT(),
						'created_by' => $userId,
						'created_by_user_type' => session('userType')
					];
				}

				DB::table('tour_slot_times_affiliate')->insert($timeSlots);
			}

			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return sendJsonErrMsg('An error occurred. Unable to add tour slot.');
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = [
			'page_title' => 'Time Slots',
		];

		$departmentPackage = departmentPackage();

		$vars['tourPackages'] = DB::table('tour_packages')
			->where('deleted_by', 0)
			->where('status', 'Active')
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('id', $departmentPackage);
			})
			->orderBy('name')
			->get(['id', 'name', 'tour_operator_id']);

		return view('users.common.time-slots', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$departmentPackage = departmentPackage();

		$selectFields = [
			'tsta.id',
			'tsta.time_from',
			'tsta.time_to',
			'tsta.status',
			'tp.name AS tour_package'
		];

		$query = DB::table('tour_slot_times_affiliate as tsta')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsta.tour_package_id')
			->where('tp.deleted_by', 0)
			->where('tsta.deleted_by', 0)
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tp.id', $departmentPackage);
			})
			->select($selectFields);

		$routePrefix = routePrefix();

		return $dt->of($query)
			->addIndexColumn()
			->editColumn('status', function ($tourSlot) {
				if($tourSlot->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tourSlot->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $tourSlot->status . '</span>';
				}
			})
			->editColumn('time_from', function ($tourSlot) {
				return date("h:i A", strtotime($tourSlot->time_from));
			})
			->editColumn('time_to', function ($tourSlot) {
				return date("h:i A", strtotime($tourSlot->time_to));
			})
			->editColumn('action', function ($tourSlot) use ($routePrefix) {
				return $this->getEditButton(route($routePrefix . '.time-slot.edit', ['time_slot' => $tourSlot->id]))
					. $this->getDeleteButton(route($routePrefix . '.time-slot.destroy', ['time_slot' => $tourSlot->id]));
			})
			->rawColumns(['action', 'status'])
			->make(true);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexPackageAHR(DataTables $dt, String $tourPkId)
	{
		$selectFields = [
			'tsta.id',
			'tsta.time_from',
			'tsta.time_to',
			'tsta.status',
			'tp.name AS tour_package'
		];

		$query = DB::table('tour_slot_times_affiliate as tsta')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsta.tour_package_id')
			->where('tp.deleted_by', 0)
			->where('tsta.deleted_by', 0)
			->where('tp.id', '=', $tourPkId)
			->select($selectFields);

		$routePrefix = routePrefix();

		return $dt->of($query)
			->addIndexColumn()
			->editColumn('status', function ($tourSlot) {
				if($tourSlot->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tourSlot->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $tourSlot->status . '</span>';
				}
			})
			->editColumn('time_from', function ($tourSlot) {
				return date("h:i A", strtotime($tourSlot->time_from));
			})
			->editColumn('time_to', function ($tourSlot) {
				return date("h:i A", strtotime($tourSlot->time_to));
			})
			->editColumn('action', function ($tourSlot) use ($routePrefix) {
				return '<a href="' . route($routePrefix . '.time-slot.edit', ['time_slot' => $tourSlot->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route($routePrefix . '.time-slot.destroy', ['time_slot' => $tourSlot->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$selectFields = ['tsta.id', 'tsta.time_from', 'tsta.time_to', 'tsta.status', 'tsta.tour_package_id'];

		$tourSlot = DB::table('tour_slot_times_affiliate as tsta')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsta.tour_package_id')
			->where('tsta.id', $id)
			->where('tsta.deleted_by', 0)
			->orderBy('tsta.time_from')
			->orderBy('tsta.time_to')
			->first($selectFields);

		if (!$tourSlot) {
			abort(404);
		}

		$departmentPackage = departmentPackage();

		$tourPackages = DB::table('tour_packages')
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('id', $departmentPackage);
			})
			->where('deleted_by', 0)
			->where('status', 'Active')
			->orderBy('name')
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Edit Time Slot',
			'tourSlot' => $tourSlot,
			'tourPackages' => $tourPackages
		];

		return view('users.common.time-slot-edit', $vars);
	}

	/**
	 * Update tour promotion details.
	 *
	 * @param  \App\Http\Requests\Common\TimeSlotRequest $request
	 * @param int $id
	 * 
	 * @return mixed
	 */
	public function update(TimeSlotRequest $request, int $id)
	{
		DB::beginTransaction();
		try {
			$slotExists = DB::table('tour_slot_times_affiliate')
				->where('id', '!=', $id)
				->where('time_from', $request->time_from)
				->where('time_to', $request->time_to)
				->where('deleted_by', 0)
				->first();

			if ($slotExists) {
				return sendJsonErrMsg('The selected slot is already added.');
			}

			$timeSlot = DB::table('tour_slot_times_affiliate')
				->select('time_from', 'time_to', 'status', 'tour_package_id')
				->where('id', $id)
				->first();

			$oldValues1 = (array)$timeSlot;
			$newValues1 = $request->all();
			$updateDT = mysqlDT();
			$newValues1['tour_package_id'] = $request->tour_package;
			unset($newValues1['_method']);
			unset($newValues1['tour_package']);
			unset($newValues1['tour_operator']);

			DB::table('records_history')->insert([
				'record_id_1' => $id,
				'record_id_2' => request('tour_package'),
				'operation_type' => 'u',
				'table_name' => 'tour_slot_times_affiliate',
				'entity_info' => $timeSlot->tour_package_id,
				'old_values' => json_encode($oldValues1),
				'new_values' => json_encode($newValues1),
				'operation_at' => $updateDT,
				'operation_by' => auth()->id(),
				'operation_by_user_type' => session('userType'),
			]);

			$newValues1 += [
				'updated_at' => $updateDT,
				'updated_by' => auth()->id(),
				'updated_by_user_type' => session('userType'),
			];

			DB::table('tour_slot_times_affiliate')->where('id', $id)->update($newValues1);
			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return sendJsonErrMsg('Unable to update the tour slot.');
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
			$query = DB::table('tour_slot_times_affiliate as tsta')->where('tsta.id', $id);

			$tourSlot = $query->first();

			DB::beginTransaction();

			DB::table('records_history')->insert([
				'record_id_1' => $id,
				'record_id_2' => $tourSlot->tour_package_id,
				'operation_type' => 'd',
				'table_name' => 'tour_slot_dates',
				'entity_info' => $tourSlot->tour_package_id,
				'old_values' => '',
				'new_values' => '',
				'operation_at' => mysqlDT(),
				'operation_by' => auth()->id(),
				'operation_by_user_type' => session('userType'),
			]);

			$query->update([
				'tsta.deleted_at' => mysqlDT(),
				'tsta.deleted_by' => auth()->id(),
				'tsta.deleted_by_user_type' => session('userType'),
			]);

			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();
			return sendJsonErrMsg('Unable to delete tour slot.');
		}
	}

	/**
	 * Add update time slot
	 * 
	 * @param Request $request
	 */
	public function addUpdateTimeSlot(Request $request)
	{
		DB::beginTransaction();
		try {
			if ($request->affiliate_seat_id > 0) {
				DB::table('affiliate_slot_times')->where('id', $request->affiliate_seat_id)->update([
					'seat_limit' => $request->seat_limit,
					'updated_at' => mysqlDT()
				]);

				$timeSlotId = $request->affiliate_seat_id;
			} else {
				$timeSlotId = DB::table('affiliate_slot_times')->insertGetId([
					'tour_slot_time_id' => $request->time_slot_id,
					'affiliate_id'      => $request->affiliate_id,
					'seat_limit'        => $request->seat_limit,
					'created_at'        => mysqlDT()
				]);
			}
			DB::commit();

			return response()->json([
				'timeSlotId' => $timeSlotId
			]);
		} catch (Exception $exception) {
			DB::rollback();

			return sendJsonErrMsg('Unable to save seats for selected time slot.');
		}
	}

	/**
	 * Add update the time slot
	 * 
	 * @param Request $request
	 */
	public function addUpdateTimeSlots(Request $request)
	{
		$request->validate([
			'time_slots'         => 'required|array',
			'time_slots.*.seats' => 'required|min:0',
		], [
			'time_slots.*.seats.required' => '"Seats" is/are required.'
		]);

		$timeSlotIds = [];

		try {
			for ($a = 0, $ac = count($request->time_slots); $a < $ac; $a++) {
				$result = $this->addUpdateTimeSlotValues([
					'tour_operator_id' => $request->time_slots[$a]['tour_operator_id'],
					'affiliate_id'     => $request->time_slots[$a]['affiliate_id'],
					'time_slot_id'     => $request->time_slots[$a]['time_slot_id'],
					'seats'            => $request->time_slots[$a]['seats'],
				], $request->time_slots[$a]['affiliate_seat_id']);

				if ($result === false) {
					return sendJsonErrMsg('Unable to save the seats ' . $request->time_slots[$a]['seats']);
				} else
					$timeSlotIds[] = $result['timeSlotId'];
			}

			return response()->json([
				'timeSlotIds' => $timeSlotIds
			], 200, [], JSON_NUMERIC_CHECK);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to save the time slot.');
		}
	}

	/**
	 * Add update the time slot for date
	 * 
	 * @param array $newValues1
	 * @param int $timeSlotId
	 */
	private function addUpdateTimeSlotValues(array $newValues1, $timeSlotId)
	{
		try {
			if ($timeSlotId) { // id is not zero/blank, time slot in tour_slot_times_affiliate already exist
				$updateDT = mysqlDT();

				$timeSlot = DB::table('tour_slot_times_affiliate as tsta')
					->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
					->where('ast.id', $timeSlotId)
					->first();

				if (!$timeSlot) {
					return false;
				}

				$oldValues1 = (array) $timeSlot;

				DB::beginTransaction();

				DB::table('records_history')->insert([
					'record_id_1' => $timeSlot->id,
					'record_id_2' => $timeSlot->tour_package_id,
					'operation_type' => 'u',
					'table_name' => 'tour_slot_times_affiliate',
					'entity_info' => $timeSlot->time_from . ' ' . $timeSlot->time_to,
					'old_values' => json_encode($oldValues1),
					'new_values' => json_encode($newValues1),
					'operation_at' => $updateDT,
					'operation_by' => auth()->id(),
					'operation_by_user_type' => session('userType'),
				]);

				$newValues1 += [
					'updated_at' => $updateDT,
					'updated_by' => auth()->id(),
					'updated_by_user_type' => session('userType'),
				];

				unset($newValues1['tour_operator_id']);

				$check = DB::table('affiliate_slot_times')->where('id', $timeSlotId)->first();

				if ($check) {
					$timeSlotId = $check->id;
					DB::table('affiliate_slot_times')->where('id', $check->id)->update([
						'seat_limit' => $newValues1['seats'],
					]);
				} else {
					$timeSlotId = DB::table('affiliate_slot_times')->insertGetId([
						'tour_slot_time_id' => $timeSlotId,
						'affiliate_id'      => $newValues1['affiliate_id'],
						'seat_limit'        => $newValues1['seats'],
						'created_at'        => mysqlDT()
					]);
				}
			} else {
				$timeSlotId	= DB::table('affiliate_slot_times')->insertGetId([
					'affiliate_id'      => $newValues1['affiliate_id'],
					'tour_slot_time_id' => $newValues1['time_slot_id'],
					'seat_limit'        => $newValues1['seats'],
					'created_at'        => mysqlDT()
				]);
			}

			DB::commit();

			return [
				'timeSlotId' => $timeSlotId
			];
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return false;
		}
	}

	/**
	 * Delete the time slot
	 * 
	 * @param int $timeSlotId
	 */
	public function deleteTimeSlot($timeSlotId)
	{
		try {
			$query = DB::table('tour_slot_times_affiliate as tsta')
				->where('tsta.id', $timeSlotId);

			$timeSlot = $query->first();

			if (!$timeSlot) {
				return sendJsonErrMsg(['Unable to delete the time slot.', 'Time slot not found.'], 404);
			}

			$deleteDT = mysqlDT();
			DB::beginTransaction();

			DB::table('records_history')->insert([
				'record_id_1' => $timeSlotId,
				'record_id_2' => $timeSlot->tour_package_id,
				'operation_type' => 'd',
				'table_name' => 'tour_slot_times_affiliate',
				'entity_info' => $timeSlot->time_from . ' ' . $timeSlot->time_to,
				'old_values' => '',
				'new_values' => '',
				'operation_at' => $deleteDT,
				'operation_by' => auth()->id(),
				'operation_by_user_type' => session('userType'),
			]);

			$query->update([
				'tsta.deleted_at' => $deleteDT,
				'tsta.deleted_by' => auth()->id(),
				'tsta.deleted_by_user_type' => session('userType')
			]);

			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return sendJsonErrMsg('Unable to delete the time slot.');
		}
	}
}
