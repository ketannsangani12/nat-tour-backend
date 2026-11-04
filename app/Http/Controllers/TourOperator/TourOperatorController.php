<?php

namespace App\Http\Controllers\TourOperator;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\TourPackageRate;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TourOperatorController extends Controller
{
	/**
	 * Dashboard
	 */
	public function dashboard()
	{
		$guard = session('guard');
		$id = auth($guard)->user()->id;

		$roleNames = "";

		if ($guard == 'tour_operator_staff') {
			$roleNames = DB::table('tour_operator_staff_roles as tosd')
				->select('r.id', 'r.name as name')
				->join('roles as r', 'r.id', '=', 'tosd.role_id')
				->where('tosd.deleted_by', '=', '0')
				->where('tosd.tour_operator_staff_id', '=', $id)
				->get();
		} else {
			$roleNames = DB::table('customers as cs')
				->where('cs.deleted_by', '=', '0')
				->where('cs.id', '=', $id)
				->get();
		}

		session()->put('roleNames', $roleNames);

		$vars = [
			'page_title' => 'Dashboard',
			'roleNames' => $roleNames
		];

		return view('users.tour-operator.dashboard', $vars);
	}

	/**
	 * Check the rates for the min max pax allowed cases
	 * 
	 * @param Request $request
	 */
	public function checkRates(Request $request)
	{
		try {
			if (in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO])) {
				$ex = explode('-', $request->rateGroupID);

				$result = TourPackageRate::where('id', $ex[1])
					->where('tour_package_id', $request->package)
					->first();

				if ($result->min_pax_allowed > $request->peoples) {
					return sendJsonErrMsg("Minimum $result->min_pax_allowed peoples are required.");
				}

				if ($result->max_pax_allowed && $result->max_pax_allowed < $request->peoples) {
					return sendJsonErrMsg("Maximum $result->max_pax_allowed peoples are allowed.");
				}
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Something wrong.');
		}
	}
}
