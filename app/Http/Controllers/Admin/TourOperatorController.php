<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\TourOperatorRequest;
use App\Models\TourOperator;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\TourPackage;
use App\Models\AffiliateBlackoutDate;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManagerStatic as Image;
use Yajra\DataTables\DataTables;
use App\Contracts\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TourOperatorController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		if (isPermissionGranted()) {
			$countries = Country::isActive()
				->orderBy('name')
				->get(['id', 'name']);

			$vars = [
				'page_title' => 'Add Tour Operator',
				'countries' => $countries
			];

			return view('users.admin.tour-operator-add', $vars);
		} else {
			return back()
				->withErrors(['errors' => 'Tour operator creation is not allowed.']);
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Admin\TourOperatorRequest $request
	 * @return mixed
	 */
	public function store(TourOperatorRequest $request)
	{
		$socialMedia = [];

		if ($request->has('sm_name')) {
			for ($i = 0; $i < count($request->sm_name); $i++) {
				$sm_name = trim($request->sm_name[$i]);
				$sm_url = trim($request->sm_url[$i]);

				if ($sm_name === '' && $sm_url === '') {
					continue;
				}

				if ($sm_name === '') {
					$sm_name = "($i)";
				} elseif (isset($socialMedia[$sm_name])) {
					$sm_name = "$sm_name ($i)";
				}

				$socialMedia[$sm_name] = $sm_url;
			}
		}

		$fieldsValues = $request->all();
		$fieldsValues['social_media_urls'] = json_encode($socialMedia);

		try {
			$ctId = app('App\Http\Controllers\Admin\CityController')
				->findOrCreate($request->city, $request->state, $request->country);

			if ($ctId == 0) {
				return back()
					->withErrors(['errors' => 'City not found or numeric city name is not allowed.'])
					->withInput();
			} elseif ($ctId == -1) {
				return back()
					->withErrors(['errors' => 'Unable to add tour operator. There is a problem with finding/adding city.'])
					->withInput();
			}

			DB::beginTransaction();

			$fieldsValues['city_id'] = $ctId;
			$tourOperator = TourOperator::create($fieldsValues);

			if ($logo = $request->file('logo')) {
				$fileNmExt = fileNameExt($logo->getClientOriginalName());
				$ext = strtolower($fileNmExt->ext);

				if (in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'])) {
					$fileName = $fileNmExt->name . "-{$tourOperator->id}.$ext";

					$imageNormal = Image::make($logo->path())->stream();

					$imageThumb = Image::make($logo->path())->resize(300, NULL, function ($constraint) {
						$constraint->aspectRatio();
					})->stream();

					$path = "images/tour-operator/{$tourOperator->tenant_id}/logo/";
					Storage::disk('s3')->put("$path$fileName", $imageNormal->__toString());

					$tourOperator->update(['logo' => $fileName]);

					$path = "images/tour-operator/resized-logo/";
					Storage::disk('s3')->put("$path$fileName", $imageThumb->__toString());
				}
			}

			DB::commit();

			return redirect()
				->route(routePrefix() . '.tour-operator.index')
				->with('message', 'Tour operator added.');
		} catch (Exception $exception) {
			DB::rollback();
			Log::error($exception);

			return back()
				->withErrors(['errors' => 'Unable to add tour operator.'])
				->withInput();
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return mixed
	 */
	public function index()
	{
		$vars = ['page_title' => 'Tour Operators'];

		return view('users.admin.tour-operators', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$qry = DB::table('tour_operators AS t_o')
			->leftJoin('cities AS ct', 'ct.id', '=', 't_o.city_id')
			->leftJoin('states AS s', 's.id', '=', 'ct.state_id')
			->leftJoin('countries AS c', 'c.id', '=', 's.country_id')
			->where('t_o.deleted_by', 0)
			->select([
				't_o.id',
				't_o.name',
				't_o.phone_number',
				't_o.status',
				't_o.created_at',
				'ct.name AS city',
				's.name AS state',
				'c.name AS country'
			]);

		return Datatables::of($qry)
			->addIndexColumn()
			->editColumn('status', function ($tourOperator) {
				if($tourOperator->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tourOperator->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $tourOperator->status . '</span>';
				}
			})
			->editColumn('action', function ($tourOperator) {
				return '<a href="' . route(routePrefix() . '.tour-operator.edit', ['tour_operator' => $tourOperator->id]) . '" class="btn btn-edit btn-sm mr-2 btn-primary-small"><i class="far fa-edit mr-2"></i> Edit</a>';
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
		$tourOperator = DB::table('tour_operators AS t_o')
			->leftJoin('cities AS ct', 'ct.id', '=', 't_o.city_id')
			->leftJoin('states AS s', 's.id', '=', 'ct.state_id')
			->where('t_o.id', $id)
			->first(['t_o.*', 'ct.state_id', 's.country_id']);

		if (!$tourOperator)
			abort(404);

		$countries = Country::isActive()
			->orWhere('id', $tourOperator->country_id)
			->orderBy('name')
			->get(['id', 'name']);

		$states = State::where(function ($qry) use ($tourOperator) {
			$qry->where('status', 'Active')
				->orWhere('id', $tourOperator->state_id);
		})
			->where('country_id', $tourOperator->country_id)
			->orderBy('name')
			->get(['id', 'name']);

		$cities = City::where(function ($qry) use ($tourOperator) {
			$qry->where('status', 'Active')
				->orWhere('id', $tourOperator->city_id);
		})
			->where('state_id', $tourOperator->state_id)
			->orderBy('name')
			->get(['id', 'name']);

		$packages = DB::table('tour_packages')
			->where('tour_operator_id', $id)
			->where('is_partner_package', 1)
			->where('deleted_by', 0)
			->get();
		// Report Setup Array
		$reports = reports();

		//Checkins Setup Array
		$checkins = checkins();

		// Decode checkout terms if stored as JSON string
		$rawTerms = $tourOperator->custom_checkout_terms ?? null;
		$checkoutTerms = is_array($rawTerms) ? $rawTerms : (is_string($rawTerms) ? json_decode($rawTerms, true) : []);
		$checkoutTerms = $checkoutTerms ?: [];

		$vars = [
			'page_title' => 'Edit Tour Operator',
			'tourOperator' => $tourOperator,
			'countries' => $countries,
			'states' => $states,
			'cities' => $cities,
			'reports' => $reports,
			'checkins' => $checkins,
			'packages' => $packages,
			'checkoutTerms' => $checkoutTerms,
		];

		return view('users.admin.tour-operator-edit', $vars);
	}

	/**
	 * Update tour operator's logo.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateLogo(Request $request, int $id)
	{
		if ($request->file('logo') != null) {
			$request->validate(['logo' => 'image']);
		} elseif ($request->file('logo_navigation') != null) {
			$request->validate(['logo_navigation' => 'image']);
		} else if ($request->file('logo_print') != null) {
			$request->validate(['logo_print' => 'image']);
		}

		if ($request->file('logo_navigation') != null || $request->file('logo_print') != null) {
			$updateLogos =  $this->updateOtherLogos($request->all(), $id);
			$tourOperator = TourOperator::findOrFail($id);
			if ($updateLogos) {
				return redirect()
					->route(routePrefix() . '.tour-operator.edit', ['tour_operator' => $tourOperator])
					->with('message', 'Logo updated.');
			} else {
				return back()
					->withErrors(['errors' => 'Unable to update logo.']);
			}
		} else {
			$tourOperator = TourOperator::findOrFail($id);

			$logo = $request->file('logo');
			$fileNmExt = fileNameExt($logo->getClientOriginalName());
			$ext = strtolower($fileNmExt->ext);

			if (!in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'])) {
				return back()
					->withErrors(['errors' => 'Please select a JPEG, PNG, WebP or GIF image file.']);
			}

			try {
				$fileName = $fileNmExt->name . "-{$tourOperator->id}.$ext";

				$imageNormal = Image::make($logo->path())->stream();

				$imageThumb = Image::make($logo->path())->resize(300, NULL, function ($constraint) {
					$constraint->aspectRatio();
				})->stream();

				$path = "images/tour-operator/" . $tourOperator->tenant_id . "/logo/";
				Storage::disk('s3')->put($path . $fileName, $imageNormal->__toString());

				$tourOperator->update(['logo' => $fileName]);

				$path = "images/tour-operator/" . $tourOperator->tenant_id . "/resized-logo/";
				Storage::disk('s3')->put($path . $fileName, $imageThumb->__toString());

				return redirect()
					->route(routePrefix() . '.tour-operator.edit', ['tour_operator' => $tourOperator])
					->with('message', 'Logo updated.');
			} catch (Exception $exception) {
				Log::error($exception);

				return back()
					->withErrors(['errors' => 'Unable to update logo.']);
			}
		}
	}

	/**
	 * Update menu navigations logo and print logo.
	 *
	 * @param $request
	 * @param int $id
	 * @return mixed
	 */
	private function updateOtherLogos($request, int $id)
	{
		$tourOperator = TourOperator::findOrFail($id);
		if (isset($request['logo_print'])) {
			$logo = $request['logo_print'];
		} else if (isset($request['logo_navigation'])) {
			$logo = $request['logo_navigation'];
		}

		$fileNmExt = fileNameExt($logo->getClientOriginalName());
		$ext = strtolower($fileNmExt->ext);

		if (!in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'])) {
			return false;
		}

		try {
			if (isset($request['logo_print'])) {
				$fileName = $fileNmExt->name . "-print-{$tourOperator->id}.$ext";
			} else if (isset($request['logo_navigation'])) {
				$fileName = $fileNmExt->name . "-navigation-{$tourOperator->id}.$ext";
			}

			$imageNormal = Image::make($logo->path())->stream();

			$path = "images/tour-operator/" . $tourOperator->tenant_id . "/logo/";
			Storage::disk('s3')->put($path . $fileName, $imageNormal->__toString());
			if (isset($request['logo_print'])) {
				$tourOperator->update(['logo_print' => $fileName]);
			} else if (isset($request['logo_navigation'])) {
				$tourOperator->update(['logo_navigation' => $fileName]);
			}

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	/**
	 * Update status of the tour operator.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateStatus(Request $request, int $id)
	{
		$request->validate([
			'status' => [
				'required',
				\Illuminate\Validation\Rule::in(['Active', 'Inactive'])
			]
		]);

		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		$tourOperator->status = $request->status;

		try {
			$tourOperator->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's status.");
		}
	}

	/**
	 * Update general details of the tour operator.
	 *
	 * @param  \App\Http\Requests\Admin\TourOperatorRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateGeneral(TourOperatorRequest $request, int $id)
	{
		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		$ctId = app('App\Http\Controllers\Admin\CityController')
			->findOrCreate($request->city, $request->state, $request->country);

		if ($ctId == 0) {
			return back()
				->withErrors(['errors' => 'City not found or numeric city name is not allowed.'])
				->withInput();
		} elseif ($ctId == -1) {
			return back()
				->withErrors(['errors' => 'Unable to add tour operator. There is problem with finding/adding city.'])
				->withInput();
		}

		$fieldsValues = $request->all();
		$fieldsValues['city_id'] = $ctId;
		$tourOperator->fill($fieldsValues);

		try {
			$tourOperator->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's general details.");
		}
	}

	/**
	 * Update SEO details of the tour operator.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateSEO(Request $request, int $id)
	{
		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		$tourOperator->fill($request->all());

		try {
			$tourOperator->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's SEO details.");
		}
	}

	/**
	 * Update connected stripe accounts for tour packages.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function updateConnectedAccounts(Request $request)
	{
		$request->validate(
			[
				'stripe_destination_id_package' => 'required',
			],
			[
				'stripe_destination_id_package.required' => '"Stripe Connected Account ID" is required.'
			]
		);
		try {
			$tourPackage = TourPackage::find($request->package_id);
			$tourPackage->update(
				[
					'stripe_destination_id' => $request->stripe_destination_id_package,
					'updated_at' => mysqlDT(),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => userType(),
				]
			);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the Partner Package Connected Account Id.");
		}
	}
	/**
	 * Get connected stripe account information.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function getConnectedAccountInfo(Request $request)
	{
		$request->validate(
			[
				'stripe_destination_id' => 'required',
			],
			[
				'stripe_destination_id.required' => '"Stripe Connected Account ID" is required.'
			]
		);
		try {
			$account = app(PaymentGatewayInterface::class)->retrieveAccount($request->stripe_destination_id);
			return response()->json([
				'account_name' => $account->settings->payments->statement_descriptor ?? '',
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to get information of Connected Account. Please enter valid Account Id.");
		}
	}

	/**
	 * Update the report setup
	 * 
	 * @param Request $request
	 * @param int $id
	 */
	public function updateReportSetup(Request $request, int $id)
	{
		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		try {
			$tourOperator->reports = json_encode($request->reports, TRUE);
			$tourOperator->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's SEO details.");
		}
	}

	/**
	 * Update the affiliate setup
	 * 
	 * @param Request $request
	 * @param int $id
	 */
	public function updateAffiliateSetup(Request $request, int $id)
	{
		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		try {
			$tourOperator->is_affiliate = $request->is_affiliate;
			$tourOperator->save();
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's SEO details.");
		}
	}

	/**
	 * Update the checkin setup
	 * 
	 * @param Request $request
	 * @param int $id
	 */
	public function updateCheckinSetup(Request $request, int $id)
	{
		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		try {
			$tourOperator->checkins = json_encode($request->checkins, TRUE);
			$tourOperator->save();
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's SEO details.");
		}
	}

	/**
	 * Update custom checkout terms for the tour operator.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateCheckoutTerms(Request $request, int $id)
	{
		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}

		try {
			$terms = [];
			$usedKeys = [];
			if ($request->has('checkout_terms') && is_array($request->checkout_terms)) {
				foreach ($request->checkout_terms as $term) {
					if (!empty($term['key']) && !empty($term['label'])) {
						$sanitizedKey = preg_replace('/[^a-z0-9_]/', '', strtolower($term['key']));
						
						// Check for duplicate keys
						if (in_array($sanitizedKey, $usedKeys)) {
							return sendJsonErrMsg("Duplicate key found: '{$sanitizedKey}'. Each term must have a unique key.", 422);
						}
						$usedKeys[] = $sanitizedKey;

						$terms[] = [
							'key' => $sanitizedKey,
							'label' => trim($term['label']),
							'required' => (bool) ($term['required'] ?? false),
							'status' => $term['status'] ?? 'active',
						];
					}
				}
			}

			$tourOperator->custom_checkout_terms = $terms;
			$tourOperator->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update checkout terms.");
		}
	}

	/**
	 * Delete a single checkout term by key.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function deleteCheckoutTerm(Request $request)
	{
		$request->validate([
			'key' => 'required|string',
		]);

		try {
			$tourOperator = TourOperator::first();

			if (!$tourOperator) {
				return sendJsonErrMsg("Tour operator not found.", 404);
			}

			$terms = $tourOperator->custom_checkout_terms ?? [];
			$keyToDelete = $request->key;

			// Filter out the term with matching key
			$terms = array_values(array_filter($terms, function($term) use ($keyToDelete) {
				return $term['key'] !== $keyToDelete;
			}));

			$tourOperator->custom_checkout_terms = $terms;
			$tourOperator->save();

			return response()->json(['message' => 'Checkout term deleted successfully.']);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete checkout term.");
		}
	}

	/**
	 * Update social media details of the tour operator.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateSocialMedia(Request $request, int $id)
	{
		$request->validate([
			'sm_name' => 'array',
			'sm_url' => 'array',
		]);

		$tourOperator = TourOperator::find($id);

		if (!$tourOperator) {
			return sendJsonErrMsg("Tour operator not found.", 404);
		}
		$socialMedia = [];

		if ($request->has('sm_name')) {
			for ($i = 0; $i < count($request->sm_name); $i++) {
				$sm_name = trim($request->sm_name[$i]);
				$sm_url = trim($request->sm_url[$i]);

				if ($sm_name === '' && $sm_url === '')
					continue;

				if ($sm_name === '')
					$sm_name = "($i)";
				elseif (isset($socialMedia[$sm_name]))
					$sm_name = "$sm_name ($i)";

				$socialMedia[$sm_name] = $sm_url;
			}
		}

		$tourOperator->social_media_urls = json_encode($socialMedia);

		try {
			$tourOperator->save();
			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour operator's social media details.");
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
			if (isPermissionGranted()) {
				DB::table('tour_operators')
					->where('id', $id)
					->update([
						'deleted_at' => mysqlDT(),
						'deleted_by' => auth(session('guard'))->id(),
						'deleted_by_user_type' => session('userType'),
					]);

				return response()->json([]);
			} else {
				return sendJsonErrMsg('Tour operator deletion is not allowed.');
			}
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete tour operator.');
		}
	}

	/**
	 * blackout date
	 */
	public function blackoutDate()
	{
		try {

			$page_title = "Blackout Dates";
			$blackoutDates = AffiliateBlackoutDate::get();

			return view('users.common.blackout-dates', compact('blackoutDates', 'page_title'));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to fetch blackout date');
		}
	}

	/**
	 * Add the blackout date
	 *
	 * @param Request $request
	 *
	 * @return mixed
	 */
	public function addBlackoutDate(Request $request)
	{
		try {

			$blackout = new AffiliateBlackoutDate();
			$blackout->description = $request->description;
			$blackout->from_date = $request->from_date;
			$blackout->to_date = $request->to_date;
			$blackout->save();

			return back()->with('message', 'Blackout date added.');
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to add blackout date');
		}
	}

	/**
	 * Update the blackout date
	 *
	 * @param Request $request
	 *
	 * @return mixed
	 */
	public function updateBlackoutDate(Request $request)
	{
		try {

			$blackout = AffiliateBlackoutDate::find($request->id);
			$blackout->description = $request->description;
			$blackout->from_date = $request->from_date;
			$blackout->to_date = $request->to_date;
			$blackout->save();

			return back()->with('message', 'Blackout date updated.');
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to update blackout date');
		}
	}

	/**
	 * Delete the blackout date
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function deleteBlackoutDate($id)
	{
		try {
			$blackout = AffiliateBlackoutDate::find($id);
			if (!$blackout) {
				return sendJsonErrMsg("Blackout not found.");
			}

			// Blackout delete
			$blackout->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the blackout.");
		}
	}
}
