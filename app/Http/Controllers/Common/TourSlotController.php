<?php

namespace App\Http\Controllers\Common;

use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Common\TourSlotRequest;
use App\Jobs\GenerateBulkSlots;
use App\Models\TourDateSlot;
use App\Models\TourPackage;
use App\Repositories\TourPackageRate\TourPackageRateRepository;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Repositories\TourSlot\TourSlotRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class TourSlotController extends Controller
{
	protected $tourSlotRepository;

	protected $tourPackageRateRepository;

	/**
	 * @param  TourSlotRepository  $tourSlotRepository
	 * @param  TourPackageRateRepository  $tourPackageRateRepository
	 */
	public function __construct(TourSlotRepository $tourSlotRepository, TourPackageRateRepository  $tourPackageRateRepository)
	{
		parent::__construct();

		$this->tourSlotRepository = $tourSlotRepository;
		$this->tourPackageRateRepository = $tourPackageRateRepository;
	}

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
			'page_title' => 'Add Tour Slot',
			'formSubmitRoute' => route(routePrefix() . '.slot.store'),
			'tourPackages' => $tourPackages
		];

		return view('users.common.tour-slot-add', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Common\TourSlotRequest $request
	 * @return mixed
	 */
	public function store(TourSlotRequest $request)
	{
		DB::beginTransaction();
		try {
			$data = $request->all();
			$dateSplit = explode(" - ", $data['date_range']);
			$startDate = strtotime($dateSplit[0]);

			if (Str::contains(Route::currentRouteName(), ['tour-operator.slot.store', 'tour-operator.slot.repeat'])) {
				[$from, $to] = array_map(function ($date) {
					return date('Y-m-d', strtotime($date));
				}, $dateSplit);

				$daysSelected = $data['days'] ?? [];
				$tourDateSlotCountQuery = TourDateSlot::where('tour_package_id', $data['tour_package'])
					->whereBetween('date', [$from, $to]);

				// Modified day number to match with MySQL DAYOFWEEK function day number
				if (!empty($daysSelected)) {
					$daysSelected = array_map(function ($day) {
						return ($day == '7') ? 1 : ($day + 1);
					}, $daysSelected);

					$tourDateSlotCountQuery->whereIn(DB::raw("DAYOFWEEK(date)"), $daysSelected);
				}

				$tourDateSlotCount = $tourDateSlotCountQuery->count();

				if ($tourDateSlotCount) {
					throw new Exception('For the precise day you specified in the range, the slot has already been added.');
				}
			}

			$days = $data['days'] ?? [];

			while ($startDate <= strtotime($dateSplit[1])) {
				$date = date("Y-m-d", $startDate);
				$data['date'] = $date;
				$data['created_by'] = Auth::id();
				$data['created_by_user_type'] = userType();

				if (empty($days) || in_array(date('N', $startDate), $days)) {
					dispatch(new GenerateBulkSlots($data));
				}

				$startDate = strtotime('+1 day', $startDate);
			}

			DB::commit();
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return sendJsonErrMsg($exception->getMessage());
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
			'page_title' => 'Tour Slots'
		];

		return view('users.common.tour-slots', $vars);
	}

	/**
	 * Display the tour slots bulk manager page.
	 *
	 * @return mixed
	 */
	public function bulkManager()
	{
		$tourPackages = TourPackage::departmentPackage()
			->active() // exclude inactive packages from the dropdown
			->where('package_has_slots', 1) // only packages that use time slots
			->orderBy('name')
			->get(['id', 'name']);

		$vars = [
			'page_title' => 'Tour Slots Bulk Manager',
			'tourPackages' => $tourPackages
		];

		return view('users.common.tour-slots-bulk-manager', $vars);
	}

	/**
	 * Distinct slot "from times" for a package across a date range.
	 * Used by the Bulk Manager "Select Existing" grid.
	 *
	 * @param Request $request
	 */
	public function distinctSlotTimes(Request $request)
	{
		$request->validate([
			'tourPackageId' => 'required|integer',
			'fromDate'      => 'required|date',
			'toDate'        => 'required|date|after_or_equal:fromDate',
		]);

		try {
			return $this->tourSlotRepository->getDistinctSlotTimes(
				(int) $request->tourPackageId,
				$request->fromDate,
				$request->toDate
			);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Persist a queued Bulk Manager operation (e.g. Update Status) to the database.
	 *
	 * @param Request $request
	 */
	public function storeBulkOperation(Request $request)
	{
		$request->validate([
			'tour_package_id' => 'required|integer',
			'action'          => 'required|in:status,seats,convert,visibility,repeat,delete,create',
			'from_date'       => 'required|date',
			'to_date'         => 'required|date|after_or_equal:from_date',
			'slots'           => 'nullable|array',
			'payload'         => 'nullable|array',
			'total_slots'     => 'nullable|integer',
		]);

		try {
			return $this->tourSlotRepository->storeBulkOperation($request);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * List queued Bulk Manager operations (to repopulate the queue on page reload).
	 */
	public function bulkOperations()
	{
		try {
			return $this->tourSlotRepository->getQueuedBulkOperations();
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * List applied Bulk Manager operations (shown in the Change History section).
	 */
	public function appliedBulkOperations()
	{
		try {
			return $this->tourSlotRepository->getAppliedBulkOperations();
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Clear Change History — soft-delete the applied Bulk Manager operations.
	 */
	public function clearBulkOperationsHistory()
	{
		try {
			return $this->tourSlotRepository->clearAppliedBulkOperations();
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update an existing queued Bulk Manager operation (when a queued op is edited & re-added).
	 *
	 * @param Request $request
	 * @param int $operation
	 */
	public function updateBulkOperation(Request $request, $operation)
	{
		$request->validate([
			'tour_package_id' => 'required|integer',
			'action'          => 'required|in:status,seats,convert,visibility,repeat,delete,create',
			'from_date'       => 'required|date',
			'to_date'         => 'required|date|after_or_equal:from_date',
			'slots'           => 'nullable|array',
			'payload'         => 'nullable|array',
			'total_slots'     => 'nullable|integer',
		]);

		try {
			return $this->tourSlotRepository->updateBulkOperation((int) $operation, $request);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Soft-delete a queued Bulk Manager operation.
	 *
	 * @param int $operation
	 */
	public function destroyBulkOperation($operation)
	{
		try {
			return $this->tourSlotRepository->deleteBulkOperation((int) $operation);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Apply all queued Bulk Manager operations — dispatches them as a chained queue so they run
	 * one-by-one in the background.
	 */
	public function applyBulkOperations()
	{
		try {
			return $this->tourSlotRepository->applyQueuedBulkOperations();
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Existing slot rows (with slot_id, seats, status) for a package across a date range.
	 * Used by the Bulk Manager preview to show the real slot per date.
	 *
	 * @param Request $request
	 */
	public function slotsInRange(Request $request)
	{
		$request->validate([
			'tourPackageId' => 'required|integer',
			'fromDate'      => 'required|date',
			'toDate'        => 'required|date|after_or_equal:fromDate',
		]);

		try {
			return $this->tourSlotRepository->getSlotsInRange(
				(int) $request->tourPackageId,
				$request->fromDate,
				$request->toDate
			);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Search the data based on date
	 * 
	 * @param string $fromDate
	 * @param string $toDate
	 */
	public function searchdate($fromDate, $toDate)
	{
		$routePrefix = routePrefix();

		$selectFields = ['tsd.id', 'tsd.date', 'tsd.status', 'tp.name AS tour_package'];

		$query = DB::table('tour_slot_dates AS tsd')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tsd.deleted_by', 0)
			->where('tp.deleted_by', 0)
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->select($selectFields);

		return Datatables::of($query)
			->addIndexColumn()
			->editColumn('status', function ($tourSlot) {
				return '<span class="status-' . $tourSlot->status . '">' . $tourSlot->status . '</span>';
			})
			->editColumn('action', function ($tourSlot) use ($routePrefix) {
				return  $this->getCustomButton('Repeat', route($routePrefix . '.slot.repeat-slot-form', ['dateSlotId' => $tourSlot->id]), 'btn btn-repeat btn-sm mr-2 btn-info-small', 'fas fa-redo')
					. $this->getEditButton(route($routePrefix . '.slot.edit', ['slot' => $tourSlot->id]))
					. $this->getDeleteButton(route($routePrefix . '.slot.destroy', ['slot' => $tourSlot->id]));
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
	public function indexAHR(DataTables $dt)
	{
		$selectFields = ['tsd.id', 'tsd.date', 'tsd.status', 'tp.name AS tour_package'];
		$fromDate = date('Y-m-d');
		$toDate = Carbon::now();
		$toDate->addDays(7);
		$toDate->format('M d Y');
		$routePrefix =  routePrefix();
		$departmentPackage = departmentPackage();

		$query = DB::table('tour_slot_dates AS tsd')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tsd.deleted_by', 0)
			->where('tp.deleted_by', 0)
			->whereBetween('tsd.date', [$fromDate, $toDate])
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tp.id', $departmentPackage);
			})
			->select($selectFields);

		return $dt->of($query)
			->addIndexColumn()
			->editColumn('status', function ($tourSlot) {
				if($tourSlot->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tourSlot->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $tourSlot->status . '</span>';
				}
			})
			->editColumn('action', function ($tourSlot) use ($routePrefix) {
				return '<a href="' . route($routePrefix . '.slot.repeat-slot-form', ['dateSlotId' => $tourSlot->id]) . '" class="btn btn-repeat btn-sm mr-2 btn-info-small"><i class="fas fa-redo mr-2"></i> Repeat</a>' .
					'<a href="' . route($routePrefix . '.slot.edit', ['slot' => $tourSlot->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					'<button data-url="' . route($routePrefix . '.slot.destroy', ['slot' => $tourSlot->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</a>';
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
		$selectFields = [
			'tp.tour_operator_id',
			'tsd.id',
			'tsd.date',
			'tst.time_slot_seats as seats',
			'tsd.status',
			'tsd.tour_package_id'
		];

		$tourSlot = DB::table('tour_slot_dates AS tsd')
			->join('tour_slot_times as tst', 'tst.tour_slot_id', '=', 'tsd.id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tsd.id', $id)
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

		$timeSlots = DB::table('tour_slot_times')
			->where('tour_slot_id', $id)
			->where('deleted_by', 0)
			->orderBy('time_from')
			->orderBy('time_to')
			->get(['id', 'time_from', 'time_to', 'bookable_status', 'time_slot_seats', 'hide_slot']);

		$vars = [
			'page_title' => 'Edit Tour Slot',
			'tourSlot' => $tourSlot,
			'tourPackages' => $tourPackages,
			'timeSlots' => $timeSlots
		];

		return view('users.common.tour-slot-edit', $vars);
	}

	/**
	 * Update tour promotion details.
	 *
	 * @param  \App\Http\Requests\Common\TourSlotRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(TourSlotRequest $request, int $id)
	{
		try {
			$tourSlot = TourDateSlot::findOrFail($id);

			$oldValues2 = $newValues2 = [];
			$oldValues1 = $tourSlot->toArray();
			$newValues1 = $request->all();
			$newValues1['tour_package_id'] = request('tour_package');

			diffRecord(
				['tour_package_id', 'date', 'seats', 'status'],
				'u',
				$oldValues1,
				$newValues1,
				$oldValues2,
				$newValues2
			);

			if (count($newValues2)) {
				DB::beginTransaction();
				$updateDT = mysqlDT();

				DB::table('records_history')->insert([
					'record_id_1' => $tourSlot->id,
					'record_id_2' => request('tour_package'),
					'operation_type' => 'u',
					'table_name' => 'tour_slot_dates',
					'entity_info' => $tourSlot->date,
					'old_values' => json_encode($oldValues2),
					'new_values' => json_encode($newValues2),
					'operation_at' => $updateDT,
					'operation_by' => Auth::id(),
					'operation_by_user_type' => session('userType'),
				]);

				$newValues1 += [
					'updated_at' => $updateDT,
					'updated_by' => Auth::id(),
					'updated_by_user_type' => session('userType'),
				];

				$tourSlot->fill($newValues1)->saveOrFail();
				DB::commit();
			}

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
			$query = DB::table('tour_slot_dates AS tsd')
				->where('tsd.id', $id);

			$tourSlot = $query->first(['tsd.tour_package_id', 'tsd.date']);

			DB::beginTransaction();
			DB::table('records_history')->insert([
				'record_id_1' => $id,
				'record_id_2' => $tourSlot->tour_package_id,
				'operation_type' => 'd',
				'table_name' => 'tour_slot_dates',
				'entity_info' => $tourSlot->date,
				'old_values' => '',
				'new_values' => '',
				'operation_at' => mysqlDT(),
				'operation_by' => Auth::id(),
				'operation_by_user_type' => session('userType'),
			]);

			$query->update([
				'tsd.deleted_at' => mysqlDT(),
				'tsd.deleted_by' => Auth::id(),
				'tsd.deleted_by_user_type' => session('userType'),
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
	 * Add update the time slots
	 * 
	 * @param Request $request
	 */
	public function addUpdateTimeSlot(Request $request)
	{
		$request->validate([
			'tour_slot_id' => 'required',
			'time_from' => 'required',
			'time_slot_seats' => 'required',
		], [
			'tour_slot_id.required' => 'Invalid request.',
			'time_from.required' => '"From Time" is required.'
		]);

		try {
			$result = $this->addUpdateTimeSlotValues([
				'tour_slot_id' => $request->tour_slot_id,
				'time_from' => $request->time_from,
				'time_to' => empty($request->time_to) ? null : $request->time_to,
				'bookable_status' => $request->bookable_status,
				'time_slot_seats' => $request->time_slot_seats,
				'hide_slot' => $request->hide_slot,
			], $request->time_slot_id);

			if ($result === false) {
				return sendJsonErrMsg('Unable to save the time slot.');
			}

			return response()->json($result);
		} catch (Exception $ex) {
			return sendJsonErrMsg('Unable to save the time slot.');
		}
	}

	/**
	 * Add update the multiple time slots
	 * 
	 * @param Request $request
	 */
	public function addUpdateTimeSlots(Request $request)
	{
		$request->validate([
			'tour_slot_id' => 'required',
			'time_slots' => 'required|array',
			'time_slots.*.time_from' => 'required',
			'time_slots.*.time_slot_seats' => 'required',
		], [
			'tour_slot_id.required' => 'Invalid request.',
			'time_slots.required' => 'No time slots to add/update!',
			'time_slots.*.time_from.required' => '"From Time" is/are required.'
		]);

		$timeSlotIds = [];
		try {
			for ($a = 0, $ac = count($request->time_slots); $a < $ac; $a++) {
				$result = $this->addUpdateTimeSlotValues([
					'tour_slot_id' => $request->tour_slot_id,
					'time_from' => $request->time_slots[$a]['time_from'],
					'time_to' => empty($request->time_slots[$a]['time_to']) ? null : $request->time_slots[$a]['time_to'],
					'bookable_status' => $request->time_slots[$a]['bookable_status'],
					'time_slot_seats' => $request->time_slots[$a]['time_slot_seats'],
					'hide_slot' => $request->time_slots[$a]['hide_slot'],
				], $request->time_slots[$a]['time_slot_id']);

				if ($result === false) {
					$timeTo = empty($request->time_slots[$a]['time_to']) ? '' : ' to ' . $request->time_slots[$a]['time_to'];
					return sendJsonErrMsg('Unable to save the time slot ' . $request->time_slots[$a]['time_from'] . $timeTo . '.');
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
	 * Remove all time slot
	 * 
	 * @param Request $request
	 */
	public function deleteallTimeSlot(Request $request)
	{
		$request->validate([
			'tour_slot_id' => 'required',
			'time_slots' => 'required|array',
			'time_slots.*.time_from' => 'required',
		], [
			'tour_slot_id.required' => 'Invalid request.',
			'time_slots.required' => 'No time slots to add/update!',
			'time_slots.*.time_from.required' => '"From Time" is/are required.'
		]);

		$timeSlotIds = [];
		try {
			for ($a = 0, $ac = count($request->time_slots); $a < $ac; $a++) {
				$query = DB::table('tour_slot_times AS tst')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->where('tst.id', $request->time_slots[$a]['time_slot_id']);

				$timeSlot = $query->first([
					'tst.tour_slot_id',
					'tst.time_from',
					'tsd.date',
					'tsd.tour_package_id',
					'tst.id as tourSlotId'
				]);

				if (!$timeSlot) {
					return sendJsonErrMsg(['Unable to delete the time slot.', 'Time slot not found.'], 404);
				}

				$deleteDT = mysqlDT();
				DB::beginTransaction();

				DB::table('records_history')->insert([
					'record_id_1' => $request->time_slots[$a]['time_slot_id'],
					'record_id_2' => $timeSlot->tour_package_id,
					'operation_type' => 'd',
					'table_name' => 'tour_slot_times',
					'entity_info' => "{$timeSlot->date} {$timeSlot->time_from}",
					'old_values' => '',
					'new_values' => '',
					'operation_at' => $deleteDT,
					'operation_by' => Auth::id(),
					'operation_by_user_type' => session('userType'),
				]);

				$query->update([
					'tst.deleted_at' => $deleteDT,
					'tst.deleted_by' => Auth::id(),
					'tst.deleted_by_user_type' => session('userType')
				]);

				$timeSlotIds[] = $request->time_slots[$a]['time_slot_id'];
				DB::commit();
			}

			return response()->json([
				'timeSlotIds' => $timeSlotIds
			], 200, [], JSON_NUMERIC_CHECK);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return sendJsonErrMsg('Unable to delete the time slot.');
		}
	}

	/**
	 * Add update the time slot for the date
	 * 
	 * @param array $newValues1
	 * @param int $timeSlotId
	 */
	private function addUpdateTimeSlotValues(array $newValues1, $timeSlotId)
	{
		try {
			$affiliateTimeSlots = [];
			$tourPackageId = null;

			if ($timeSlotId) { // id is not zero/blank, time slot in tour_slot_times already exist
				$updateDT = mysqlDT();

				$query = DB::table('tour_slot_times AS tst')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->where('tst.id', $timeSlotId);

				$timeSlot = $query->first(['tst.*', 'tsd.date', 'tsd.tour_package_id']);

				if (!$timeSlot) {
					return false;
				}

				$oldValues2 = $newValues2 = [];
				$oldValues1 = (array) $timeSlot;
				$tourPackageId = $timeSlot->tour_package_id;

				diffRecord(
					['time_from', 'time_to', 'hide_slot', 'bookable_status', 'time_slot_seats', 'tour_package_id'],
					'u',
					$oldValues1,
					$newValues1,
					$oldValues2,
					$newValues2
				);

				if (count($newValues2)) {
					if (isset($newValues2['time_from'])) {
						$newValues2['from_time'] = $newValues2['time_from'];
						unset($newValues2['time_from']);
					}

					if (isset($newValues2['time_to'])) {
						$newValues2['to_time'] = $newValues2['time_to'];
						unset($newValues2['time_to']);
					}

					if (isset($newValues2['time_slot_seats'])) {
						unset($newValues2['time_slot_seats']);
					}

					DB::beginTransaction();
					DB::table('records_history')->insert([
						'record_id_1' => $timeSlot->id,
						'record_id_2' => $timeSlot->tour_package_id,
						'operation_type' => 'u',
						'table_name' => 'tour_slot_times',
						'entity_info' => $timeSlot->date . ' ' . $timeSlot->time_from,
						'old_values' => json_encode($oldValues2),
						'new_values' => json_encode($newValues2),
						'operation_at' => $updateDT,
						'operation_by' => Auth::id(),
						'operation_by_user_type' => session('userType'),
					]);

					$newValues1 += [
						'tst.updated_at' => $updateDT,
						'tst.updated_by' => Auth::id(),
						'tst.updated_by_user_type' => session('userType'),
					];

					$query->update($newValues1);
					DB::commit();
				}
			} else {
				$newValues2 = [];
				$newValues2 = $newValues1;

				$tourSlot = DB::table('tour_slot_dates AS tsd')
					->where('tsd.id', $newValues1['tour_slot_id'])
					->first(['tsd.date', 'tsd.tour_package_id']);

				$newValues1 += [
					'created_by' => Auth::id(),
					'created_by_user_type' => session('userType'),
					'tour_package_id' => $tourSlot->tour_package_id,
					'date' => $tourSlot->date,
				];

				$timeSlotId = DB::table('tour_slot_times')->insertGetId($newValues1);

				DB::table('records_history')->insert([
					'record_id_1' => $timeSlotId,
					'record_id_2' => $tourSlot->tour_package_id,
					'operation_type' => 'i',
					'table_name' => 'tour_slot_times',
					'entity_info' => $tourSlot->date . ' ' . $newValues1['time_from'],
					'old_values' => '[]',
					'new_values' => json_encode($newValues2),
					'operation_at' => mysqlDT(),
					'operation_by' => Auth::id(),
					'operation_by_user_type' => session('userType'),
				]);
				DB::commit();

				$tourPackageId = $tourSlot->tour_package_id;
			}
			
			// Check timeslot added for affiliates in tour_slot_times_affiliate table
			if($tourPackageId != null) {
				$timeFrom = $newValues1['time_from'];
				$timeTo = $newValues1['time_to'];
				$currentDateTime = mysqlDT();
				$userId = Auth::id();
				$userType = session('userType');

				$affiliateTimeSlots = affiliateTimeSlots($tourPackageId, $timeFrom, $timeTo, $currentDateTime, $userId, $userType, $affiliateTimeSlots);
				// Store affiliate timeslots in tour_slot_times_affiliate table
				DB::table('tour_slot_times_affiliate')->insert($affiliateTimeSlots);
				DB::commit();
			}

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
	 * Remove the time slot from the specific date
	 * 
	 * @param int $timeSlotId
	 */
	public function deleteTimeSlot($timeSlotId)
	{
		try {
			$query = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tst.id', $timeSlotId);

			$timeSlot = $query->first(['tst.tour_slot_id', 'tst.time_from', 'tsd.date', 'tsd.tour_package_id']);

			if (!$timeSlot) {
				return sendJsonErrMsg(['Unable to delete the time slot.', 'Time slot not found.'], 404);
			}

			$deleteDT = mysqlDT();

			DB::beginTransaction();
			DB::table('records_history')->insert([
				'record_id_1' => $timeSlotId,
				'record_id_2' => $timeSlot->tour_package_id,
				'operation_type' => 'd',
				'table_name' => 'tour_slot_times',
				'entity_info' => $timeSlot->date . ' ' . $timeSlot->time_from,
				'old_values' => '',
				'new_values' => '',
				'operation_at' => $deleteDT,
				'operation_by' => Auth::id(),
				'operation_by_user_type' => session('userType'),
			]);

			$query->update([
				'tst.deleted_at' => $deleteDT,
				'tst.deleted_by' => Auth::id(),
				'tst.deleted_by_user_type' => session('userType')
			]);

			DB::commit();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return sendJsonErrMsg('Unable to delete the time slot.');
		}
	}

	/**
	 * Bulk edit the time slot
	 */
	public function bulkEditTimeSlot()
	{
		try {
			$tourPackages = TourPackage::departmentPackage()
				->orderBy('name')
				->get(['id', 'name']);

			$vars = [
				'page_title' => 'Bulk Edit Tour Slot',
				'formSubmitRoute' => route(prefixedRouteName('tour-operator.slot.bulk-update-time-slot')),
				'tourPackages' => $tourPackages
			];

			return view('users.common.tour-slot-bulk-edit', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Bulk update the time slot
	 *
	 * @param Request $request
	 */
	public function bulkUpdateTimeSlot(Request $request)
	{
		try {
			return $this->tourSlotRepository->bulkUpdateTimeSlotByDateRange($request);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get tour slot time by date
	 * also called from Controllers\Common\TourBookingController::create()
	 * 
	 * @param Request $request
	 */
	public function getTourSlotTimesByDate(Request $request)
	{
		try {
			return $this->tourSlotRepository->getTourSlotTimesByDate($request);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get tour rates by time slot
	 * @param Request $request
	 */
	public function getTourRatesBySlot(Request $request)
	{
		try {
			$data = $request->all();

			return $this->tourPackageRateRepository->getTourRatesBySlot($data);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Repeat time slot form to create a time slots
	 * 
	 * @param int $dateSlotId
	 */
	public function repeatSlotForm($dateSlotId)
	{
		$vars = [
			'page_title' => 'Repeat Tour Slots'
		];

		$fields = [
			'tsd.id',
			'tsd.date',
			'tp.id AS tour_package_id',
			'tp.name AS tour_package',
			'tp.tour_operator_id'
		];

		$vars['slotInfo'] = DB::table('tour_slot_dates AS tsd')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->where('tsd.id', $dateSlotId)
			->first($fields);

		if (!$vars['slotInfo']) {
			abort(500);
		}

		$vars['tourPackages'] = TourPackage::departmentPackage()
			->where(function ($qry) use ($vars) {
				$qry->where('status', 'Active')
					->orWhere('id', $vars['slotInfo']->tour_package_id);
			})
			->orderBy('name')
			->get(['id', 'name']);

		$vars['timeSlots'] = DB::table('tour_slot_times')
			->where('tour_slot_id', $dateSlotId)
			->orderBy('time_from')
			->get(['time_from', 'time_to', 'bookable_status', 'time_slot_seats', 'hide_slot']);

		return view('users.common.tour-slot-repeat', $vars);
	}

	/**
	 * Repeat time slot addition
	 * 
	 * @param TourSlotRequest $request
	 */
	public function repeatSlot(TourSlotRequest $request)
	{
		if ($request->date_from > $request->date_to) {
			return sendJsonErrMsg('"From Date" can not be greater than "To Date".');
		}

		try {
			$query = DB::table('tour_slot_dates AS tsd')
				->join('tour_slot_times AS tst', 'tst.tour_slot_id', '=', 'tsd.id')
				->select('tsd.date', 'tst.time_from', 'tsd.id')
				->where('tsd.tour_package_id', $request->tour_package)
				->where('tsd.deleted_by', 0)
				->where(function ($query) use ($request) {
					for ($a = 0, $ac = count($request->time_from); $a < $ac; $a++) {
						$query->orWhere(function ($query) use ($request, $a) {
							if (empty($request->date_to) || $request->date_from == $request->date_to)
								$query = $query->where('tsd.date', $request->date_from);
							else
								$query = $query->whereBetween('tsd.date', [$request->date_from, $request->date_to]);

							$query->where('tst.time_from', $request->time_from[$a]);
						});
					}
				});

			if (session('userType') == 'Tour Operator') {
				$query = $query->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
					->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			}

			if (count($slots = $query->get())) {
				$errors = ['Following date-time slots combinations already exist:'];
				$existingSlotsArray = array();
				$existingDatesArray = array();
				$existingDatesSlotsIds = array();
				for ($a = 0, $ac = count($slots); $a < $ac; $a++) {
					$existingDatesArray[] = $slots[$a]->date;
					$existingDatesSlotsIds[$slots[$a]->date] = $slots[$a]->id;
					$existingSlotsArray[] = $slots[$a]->date . ' ' . $slots[$a]->time_from;
				}

				$slotsArray = array();
				$dates = new \DatePeriod(
					new \DateTime($request->date_from),
					new \DateInterval('P1D'),
					(new \DateTime($request->date_to ?: $request->date_from))
						->add(new \DateInterval('P1D')) // end date isn't included in period, so adding it
				);

				foreach ($dates as $date) {
					for ($b = 0, $userId = Auth::id(); $b < count($request->time_from); $b++) {
						if (!in_array($date->format('Y-m-d') . ' ' . $request->time_from[$b], $existingSlotsArray)) {
							$slotArray = array();
							$slotArray['date'] = $date->format('Y-m-d');
							$slotArray['from_time'] = $request->time_from[$b];
							$slotArray['to_time'] = $request->time_to[$b];
							$slotArray['bookable_status'] = $request->bookable_status[$b];
							$slotArray['hide_slot'] = $request->hide_slot[$b];
							// Seats belong to the posted row, so carry them with the slot. The insert
							// loops below iterate dates, not row indexes, and cannot recover $b.
							$slotArray['time_slot_seats'] = $request->time_slot_seats[$b] ?? 0;
							$slotsArray[$date->format('Y-m-d')]['slots'][] = $slotArray;

							if (!in_array($date->format('Y-m-d'), $existingDatesArray)) {
								$slotsArray[$date->format('Y-m-d')]['date_exists'] = false;
							} else {
								$slotsArray[$date->format('Y-m-d')]['date_exists'] = true;
							}
						} else {
							$errors[] = formatDateTime($date->format('Y-m-d') . ' ' . $request->time_from[$b]);
						}
					}
				}

				if (empty($slotsArray)) {
					$errors[] = 'Duplicate combinations of date-time slots can not be created.';
					return sendJsonErrMsg($errors, 500);
				}

				DB::beginTransaction();
				foreach ($slotsArray as $key => $slotArray) {
					$timeSlots = [];
					$affiliateTimeSlots = [];
					
					if ($slotArray['date_exists']) {
						foreach ($slotArray['slots'] as $slot) {
							if (isset($existingDatesSlotsIds[$key])) {
								$timeSlots[] = [
									'tour_slot_id' => $existingDatesSlotsIds[$key],
									'tour_package_id' => $request->tour_package,
									'time_slot_seats' => $slot['time_slot_seats'],
									'date' => $key,
									'time_from' => $slot['from_time'],
									'time_to' => empty($slot['to_time']) ? null : $slot['to_time'],
									'bookable_status' => $slot['bookable_status'],
									'hide_slot' => $slot['hide_slot'],
									'created_by' => $userId,
									'created_by_user_type' => session('userType')
								];

								// Check timeslot added for affiliates in tour_slot_times_affiliate table
								$tourPackageId = $request->input('tour_package');
								$fromTime = $slot['from_time'];
								$toTime = empty($slot['to_time']) ? null : $slot['to_time'];
								$currentDateTime = Carbon::now(); // Assuming you're using Carbon for date/time operations
								$userType = session('userType');
								
								$affiliateTimeSlots = affiliateTimeSlots($tourPackageId, $fromTime, $toTime, $currentDateTime, $userId, $userType, $affiliateTimeSlots);
							}
						}
					} else {
						// $key is this iteration's date. $date still holds the last date of the
						// DatePeriod above, so it must not be used here.
						$dateSlot = TourDateSlot::create([
							'tour_package_id' => $request->tour_package,
							'date' => $key,
							'seats' => 0,
							'status' => $request->status,
						]);

						foreach ($slotArray['slots'] as $slot) {
							$timeSlots[] = [
								'tour_slot_id' => $dateSlot->id,
								'tour_package_id' => $request->tour_package,
								'time_slot_seats' => $slot['time_slot_seats'],
								'date' => $key,
								'time_from' => $slot['from_time'],
								'time_to' => empty($slot['to_time']) ? null : $slot['to_time'],
								'bookable_status' => $slot['bookable_status'],
								'hide_slot' => $slot['hide_slot'],
								'created_by' => $userId,
								'created_by_user_type' => session('userType')
							];

							// Check timeslot added for affiliates in tour_slot_times_affiliate table
							$tourPackageId = $request->input('tour_package');
							$fromTime = $slot['from_time'];
							$toTime = empty($slot['to_time']) ? null : $slot['to_time'];
							$currentDateTime = Carbon::now(); // Assuming you're using Carbon for date/time operations
							$userType = session('userType');

							$affiliateTimeSlots = affiliateTimeSlots($tourPackageId, $fromTime, $toTime, $currentDateTime, $userId, $userType, $affiliateTimeSlots);
						}
					}

					DB::table('tour_slot_times')->insert($timeSlots);

					// Store affiliate timeslots in tour_slot_times_affiliate table
					DB::table('tour_slot_times_affiliate')->insert($affiliateTimeSlots);
				}
				DB::commit();
			} else {
				DB::beginTransaction();

				$dates = new \DatePeriod(
					new \DateTime($request->date_from),
					new \DateInterval('P1D'),
					(new \DateTime($request->date_to ?: $request->date_from))
						->add(new \DateInterval('P1D')) // end date isn't included in period, so adding it
				);

				foreach ($dates as $date) {
					$dateSlot = TourDateSlot::create([
						'tour_package_id' => $request->tour_package,
						'date' => $date->format('Y-m-d'),
						'seats' => 0,
						'status' => $request->status,
					]);

					for ($a = 0, $timeSlots = $affiliateTimeSlots = [], $userId = Auth::id(); $a < count($request->time_from); $a++) {
						$timeSlots[] = [
							'tour_slot_id' => $dateSlot->id,
							'tour_package_id' => $request->tour_package,
							'time_slot_seats' => $request->time_slot_seats[$a],
							'date' => $date->format('Y-m-d'),
							'time_from' => $request->time_from[$a],
							'time_to' => empty($request->time_to[$a]) ? null : $request->time_to[$a],
							'bookable_status' => $request->bookable_status[$a],
							'hide_slot' => $request->hide_slot[$a],
							'created_by' => $userId,
							'created_by_user_type' => session('userType')
						];

						// Check timeslot added for affiliates in tour_slot_times_affiliate table
						$tourPackageId = $request->input('tour_package');
						$timeFrom = $request->time_from[$a];
						$timeTo = (empty($request->time_to[$a]) ? null : $request->time_to[$a]);
						$currentDateTime = Carbon::now(); // Assuming you're using Carbon for date/time operations
						$userType = session('userType');

						$affiliateTimeSlots = affiliateTimeSlots($tourPackageId, $timeFrom, $timeTo, $currentDateTime, $userId, $userType, $affiliateTimeSlots);
					}

					DB::table('tour_slot_times')->insert($timeSlots);

					// Store affiliate timeslots in tour_slot_times_affiliate table
					DB::table('tour_slot_times_affiliate')->insert($affiliateTimeSlots);
				}

				DB::commit();
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add tour slots.');
		}
	}

	/**
	 * Logic to send the emails to the users when tour closed because of some reasons
	 *
	 * @param Request $request
	 */
	public function lowerAntelopeCanyonClosureEmail(Request $request)
	{
		try {
			return $this->tourSlotRepository->lowerAntelopeCanyonClosureEmailSendProcess($request);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get Color Date for booking calendar
	 * 
	 * @param Request $request
	 */
	public function getColorDate(Request $request)
	{
		try {
			return $this->tourSlotRepository->getColorDate($request);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
}
