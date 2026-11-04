<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Http\Requests\Common\TourPromotionRequest;
use App\Http\Requests\Common\PromoAssignToAffiliate;
use App\Models\TourPromotion;
use App\Models\Customer;
use App\Models\PromocodeAffiliate;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class TourPromotionController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$vars = [
			'page_title' => 'Add Tour Promotion',
			'tourPromotion' => new TourPromotion([
				'start_date' => date('Y-m-d'),
				'end_date' => date('Y-m-d'),
				'max_allowed_usages' => 0
			]),
		];

	
		$vars += [
			'formSubmitRoute' => route(routePrefix() . '.promotion.store'),
		];

		$departmentPackage = departmentPackage();

		$tourPackages = DB::table('tour_packages')
			->where('deleted_by', 0)
			->where('status', 'Active')
			->when($departmentPackage, function ($q) use ($departmentPackage) {
				return $q->whereIn('id', $departmentPackage);
			})
			->orderBy('name')
			->get(['id', 'name']);

		$vars += [
			'tourPackages' => $tourPackages,
		];

		return view('users.common.tour-promotion-add-edit', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  TourPromotionRequest $request
	 * @return mixed
	 */
	public function store(TourPromotionRequest $request)
	{
		try {
			if ($request->end_date && $request->start_date > $request->end_date) {
				return sendJsonErrMsg('Start date can not be greater than end date.');
			}

			$promos = TourPromotion::where('code', $request->code)
				->where('tour_package_id', $request->tour_package)
				->get(['start_date', 'end_date']);

			if (count($promos) && $this->areDatesOverlapping($promos, $request->start_date, $request->end_date)) {
				return sendJsonErrMsg('The entered start and/or end date is overlapping with an existing promotions having the same code.');
			}

			$tourOperator = tourOperator();

			$dateRange = explode('-', $request->date_range);

			$data = [
				'tour_package_id' => $request->tour_package,
				'tour_operator_id' => $tourOperator->id,
				'code' => $request->code,
				'max_allowed_usages' => $request->max_allowed_usages,
				'min_allowed_tourists' => $request->min_allowed_tourists,
				'start_date' => date("Y-m-d", strtotime($dateRange[0])),
				'end_date' => date("Y-m-d", strtotime($dateRange[1])),
				'is_extra_charge' => $request->is_extra_charge,
				'discount_value' => $request->discount_value,
				'discount_value_type' => $request->discount_value_type,
				'status' => $request->status,
			];

			TourPromotion::create($data);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add tour promotion.');
		}
	}

	/**
	 * Are date overlapping or not check
	 * 
	 * @param array $promos
	 * @param string $startDate
	 * @param string $endDate
	 */
	private function areDatesOverlapping($promos, $startDate, $endDate)
	{
		$endDate = $endDate ?: '9999-99-99';

		for ($a = 0, $ac = count($promos); $a < $ac; $a++) {
			if ($promos[$a]->end_date == '0000-00-00') {
				$promos[$a]->end_date = '9999-99-99';
			}

			if (
				($startDate >= $promos[$a]->start_date && $startDate <= $promos[$a]->end_date) ||
				($endDate >= $promos[$a]->start_date && $endDate <= $promos[$a]->end_date) ||
				($startDate <= $promos[$a]->start_date && $endDate >= $promos[$a]->end_date)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = [
			'page_title' => 'Tour Promotions'
		];

		return view('users.common.tour-promotions', $vars);
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

		$selectFields = ['tp.id', 'tp.code', 'tp.start_date', 'tp.end_date', 'tp.status','t_p.name AS tour_package'];

		$status = request('status');

		$query = DB::table('tour_promotions AS tp')
		    ->join('tour_packages AS t_p', 't_p.id', '=', 'tp.tour_package_id')
			->when($departmentPackage, function ($q) use ($departmentPackage) {
				return $q->whereIn('t_p.id', $departmentPackage);
			})
			->when($status, function ($q) use ($status) {
				return $q->where('tp.status', $status);
			})
			->where('tp.deleted_by', 0);

		$routePrefix = routePrefix();
		$query = $query->select($selectFields);

		return $dt->of($query)
			->addIndexColumn()
			->editColumn('status', function ($tourPromotion) {
				if($tourPromotion->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tourPromotion->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">Expired</span>';
				}
			})
			->editColumn('action', function ($tourPromotion) use ($routePrefix) {
				return '<a href="' . route($routePrefix . '.promotion.edit', ['promotion' => $tourPromotion->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>' .
					// '<a href="' . route($routePrefix . '.promotion-affiliate', ['promotionId' => $tourPromotion->id]) . '" class="btn btn-sm btn-email-templates"><i class="far fa-fw fa-handshake"></i> Assign To Affiliate</a>' .
					'<button data-url="' . route($routePrefix . '.promotion.destroy', ['promotion' => $tourPromotion->id]) . '" data-method="delete" class="btn btn-sm btn-delete btn-danger-small"><i class="far fa-trash-alt mr-2"></i> Delete</button>';
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
		$vars = [
			'page_title' => 'Edit Tour Promotion'
		];

		$departmentPackage = departmentPackage();
		$routePrefix = routePrefix();

		$tourPackages = DB::table('tour_packages')
			->where('deleted_by', 0)
			->where('status', 'Active')
			->when($departmentPackage, function ($q) use ($departmentPackage) {
				return $q->whereIn('id', $departmentPackage);
			})
			->orderBy('name')
			->get(['id', 'name']);

		$vars['tourPackages'] = $tourPackages;

		$tourPromotion = TourPromotion::findOrFail($id);

		$vars += [
			'tourPromotion' => $tourPromotion,
			'formSubmitRoute' => route($routePrefix . '.promotion.update', ['promotion' => $id]),
		];

		return view('users.common.tour-promotion-add-edit', $vars);
	}

	/**
	 * Update tour promotion details.
	 *
	 * @param  TourPromotionRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function update(TourPromotionRequest $request, int $id)
	{
		try {
			$tourPromotion = TourPromotion::findOrFail($id);

			if ($request->end_date && $request->start_date > $request->end_date) {
				return sendJsonErrMsg('Start date can not be greater than end date.');
			}

			$promos = TourPromotion::where('code', $request->code)
				->where('id', '!=', $id)
				->where('tour_package_id', $request->tour_package)
				->get(['start_date', 'end_date']);

			if (count($promos)) {
				if ($this->areDatesOverlapping($promos, $request->start_date, $request->end_date)) {
					return sendJsonErrMsg('The entered start and/or end date is overlapping with an existing promotions having the same code.');
				}
			}

			$tourOperator = tourOperator();

			$dateRange = explode('-', $request->date_range);

			$data = [
				'tour_package_id' => $request->tour_package,
				'tour_operator_id' => $tourOperator->id,
				'code' => $request->code,
				'max_allowed_usages' => $request->max_allowed_usages,
				'min_allowed_tourists' => $request->min_allowed_tourists,
				'start_date' => date("Y-m-d", strtotime($dateRange[0])),
				'end_date' => date("Y-m-d", strtotime($dateRange[1])),
				'is_extra_charge' => $request->is_extra_charge,
				'discount_value' => $request->discount_value,
				'discount_value_type' => $request->discount_value_type,
				'status' => $request->status,
			];

			$tourPromotion->fill($data)->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update tour promotion.');
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
			DB::table('tour_promotions')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => auth()->id(),
					'deleted_by_user_type' => session('userType'),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete tour promotion.');
		}
	}

	/**
	 * Promotion Affiliate list and add more affiliates.
	 *
	 * @param int $promotionId
	 * @return mixed
	 */
	public function promotionAffiliate($promotionId)
	{
		try {
			$vars = [
				'page_title' => 'Promotion Assign To Affiliates'
			];

			//get affiliates to assign the package
			$affiliates = Customer::isAffiliate()->get();
			$vars += [
				'affiliates' => $affiliates
			];

			$assignedAffiliates = PromocodeAffiliate::with('affiliate')
				->where('promotion_id', $promotionId)
				->orderBy('affiliate_id')
				->get();

			
			$vars += [
				'formSubmitRoute' => route(routePrefix() . '.promotion-assign-to-affiliate'),
			];
			

			$vars += [
				'assignedAffiliates' => $assignedAffiliates,
				'promotionId' => $promotionId
			];

			return view('users.common.tour-promotions-affiliate', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Promotion Assign To Affiliate.
	 *
	 * @param  PromoAssignToAffiliate $request
	 * @return mixed
	 */
	public function promotionAssignToAffiliate(PromoAssignToAffiliate $request)
	{
		try {
			$data = PromocodeAffiliate::where('promotion_id', $request->promotion_id)
				->where('affiliate_id', $request->affiliate_id)
				->first();

			if (empty($data)) {
				$promocodeAffiliate = new PromocodeAffiliate;
				$promocodeAffiliate->promotion_id =  $request->promotion_id;
				$promocodeAffiliate->affiliate_id =  $request->affiliate_id;
				$promocodeAffiliate->save();
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Remove the assigned affiliate from the Promocode
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function unassignAffiliate(int $id)
	{
		try {
			$assignment = PromocodeAffiliate::find($id);
			$assignment->forceDelete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the assigned affiliate.");
		}
	}
}
