<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Repositories\Report\ReportRepository;
use Exception;
use Illuminate\Support\Facades\Log;

class Today_avialabilityController extends Controller
{
	protected $reportRepository;

	public function __construct(ReportRepository $reportRepository)
	{
		parent::__construct();

		$this->reportRepository = $reportRepository;
	}

	/**
	 * Today availability list method
	 * 
	 * @param string $fromDate
	 * @param int $tourPackageId
	 */
	public function indexAHR(string $fromDate = "",  int $tourPackageId = 0)
	{
		if ($fromDate == "")
			$fromDate = date('Y-m-d');
		else
			$fromDate = substr($fromDate, 0, 4) . '-' . substr($fromDate, 4, 2) . '-' . substr($fromDate, 6, 2);

		$qry = DB::table('tour_bookings_master AS tbm')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tbm.tour_slot_id')
			->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tbm.tour_package_id', '=', $tourPackageId)
			->where('tsd.deleted_by', '=', '0')
			->where('tsd.date', [$fromDate])
			->select([
				'c.name as name', 'tsd.date as bookingdate', 'tsd.created_at as createdate', 'tbm.status as status', 'tp.name as bookingid',  't.amount as amount', 't.status as payment', 'to.logo as touroperatorlogo',
			]);

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('createdate', function ($createdate) {
				return substr($createdate->createdate, 0, 10);
			})
			->make(true);
	}

	/**
	 * Availability Report
	 *
	 * @return mixed
	 */
	public function todayavialabilityList(Request $request, string $fromDate = "", string $toDate = "", int $tourOperatorId = 0, int $tourPackageId = 0)
	{
		try {
			return $this->reportRepository->todayavialabilityList($request, $fromDate, $toDate, $tourOperatorId, $tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}
}
