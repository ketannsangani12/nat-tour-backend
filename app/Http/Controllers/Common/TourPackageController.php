<?php

namespace App\Http\Controllers\Common;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\TourPackageRequest;
use App\Http\Requests\Common\UpdateTourPackageSettingsRequest;
use App\Models\CancellationPolicy;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\TourPackageForm;
use App\Models\TourBookingForms;
use App\Models\PackageAffiliateAssignment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\TourPackageFamily;
use App\Models\TourPackageRate;
use App\Models\TourPackageImage;
use App\Models\TourPackageBundleRelation;
use App\Models\BookingTransact;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBooking;
use Exception;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\Booking\BookingService;
use App\Utils\BookingUtils;
use App\Services\Booking\PaymentLinkService;
use App\Repositories\Octo\OctoRepository;
use Intervention\Image\ImageManagerStatic as Image;

class TourPackageController extends Controller
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create()
	{
		$vars = ['page_title' => 'Add Tour Package'];

		if (session('userType') == 'Admin') {
			$tourOperators = TourOperator::where('status', 'Active')
				->orderBy('name')
				->get(['id', 'name']);

			if (!$tourOperators) {
				abort(500);
			}

			$vars['tourOperators'] = $tourOperators;
		}

		$vars['categories'] = Category::where('status', 'Active')->get();

		return view('users.common.tour-package-add', $vars);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \App\Http\Requests\Common\TourPackageRequest $request
	 * @return mixed
	 */
	public function store(TourPackageRequest $request)
	{
		try {
			DB::beginTransaction();

			$tourOperator = TourOperator::select(['service_commission_percentage', 'stripe_commission_percentage'])->first();
			$tourPackageData = $request->only(TourPackage::fillableFields());
			$tourPackageData['service_commission_percentage'] = $tourOperator->service_commission_percentage;
			$tourPackageData['stripe_commission_percentage'] = $tourOperator->stripe_commission_percentage;
			TourPackage::create($tourPackageData);

			DB::commit();
			$routePrefix =  routePrefix();
			$return = redirect()
				->route($routePrefix . '.package.index')
				->with('message', 'Tour Package added.');


			return $return;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();

			return back()
				->withErrors(['errors' => 'Unable to add tour package.'])
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
		$vars = ['page_title' => 'Tour Packages'];

		return view('users.common.tour-packages', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function indexAHR(DataTables $dt)
	{
		$routePrefix =  routePrefix();
		$departmentPackage = departmentPackage();

		$selectFields = [
			'tp.id',
			'tp.name',
			'tp.hours',
			'tp.minutes',
			'tp.status'
		];

		$query = DB::table('tour_packages AS tp')
			->where('tp.deleted_by', 0)
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tp.id', $departmentPackage);
			})
			->orderBy('Order_by')
			->select($selectFields);

		return $dt->of($query)
			->addIndexColumn()
			->editColumn('status', function ($tourPackage) {
				if ($tourPackage->status == 'Active') {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">' . $tourPackage->status . '</span>';
				} else {
					return '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">' . $tourPackage->status . '</span>';
				}
			})
			->editColumn('action', function ($tourPackage) use ($routePrefix) {
				$emailTemplates = '';
				$emailTemplates .= $this->getCustomButton('Email Templates', route($routePrefix . '.email-template.index', ['tour_package' => $tourPackage->id]), 'btn btn-email-templates btn-sm mr-2 btn-info-small', 'far fa-envelope mr-1');
				$emailTemplates .= $this->getEditButton(route($routePrefix . '.editpackage', ['id' => $tourPackage->id, 'year' => date('Y')])) .
					$this->getDeleteButton(route($routePrefix . '.package.destroy', ['package' => $tourPackage->id]));

				return $emailTemplates;
			})
			->rawColumns(['action', 'status'])
			->make(true);
	}

	/**
	 * Summary of editpackage
	 * @param int $id
	 * @param mixed $year
	 * @return mixed
	 */
	public function editpackage(int $id, $year)
	{
		try {
			$tourPackageFamily = [];
			$suggestedBokunCodes = [];

			if (session('userType') == 'Admin') {
				$tourOperators = tourOperator();
				$vars['tourOperators'] = $tourOperators;

				if (!$tourOperators) {
					abort(500);
				}
			}

			$tourPackage = TourPackage::with(['families', 'tourOperator'])->findOrFail($id);

			$allTourPackages = TourPackage::departmentPackage()->get();

			$nonBundlePackages = TourPackage::departmentPackage()
				->where('id', '!=', $id)
				->where('bundle_package', 0)
				->get();

			$bundlePackages = TourPackageBundleRelation::where('parent_id', $id)->get();

			$tourPackageFamilies = collect();
			if ($tourPackage->families->count()) {
				$getParentPackage = TourPackageFamily::where('tour_package_id', $tourPackage->id)
					->where('relation_year', $year)
					->first();

				if ($getParentPackage) {
					$tourPackageFamilies = TourPackageFamily::with('tourPackage')
						->where('tour_operator_id', $tourPackage->tour_operator_id)
						->where('parent_package_id', $getParentPackage->parent_package_id)
						->where('relation_year', $year)
						->orderBy('time_from')
						->orderBy('is_parent', 'desc')
						->get();

					foreach ($tourPackageFamilies as $tourPackageData) {
						if (!isset($tourPackageFamily[$tourPackageData->tour_package_id]['tour_package_id'])) {
							$tourPackageFamily[$tourPackageData->tour_package_id]['tour_package'] = $tourPackageData;
						}

						$tourPackageFamily[$tourPackageData->tour_package_id]['id'] = $tourPackageData->id;
						$tourPackageFamily[$tourPackageData->tour_package_id]['tour_package_id'] = $tourPackageData->tour_package_id;
						$tourPackageFamily[$tourPackageData->tour_package_id]['tour_package_name'] = $tourPackageData->tourPackage->name;
						$tourPackageFamily[$tourPackageData->tour_package_id]['time_from'][] = $tourPackageData->time_from;
						$tourPackageFamily[$tourPackageData->tour_package_id]['is_parent'][] = $tourPackageData->is_parent;
					}
				}
			}

			$packageRates = TourPackageRate::where('tour_package_id', $tourPackage->id)
				->where(function ($query) {
					$query->whereNull('affiliate_id');
				})
				->when($year != null, function ($query) use ($year) {
					$query->where('scheduling_year', $year);
				})
				->when($year == null, function ($query) {
					$query->where(function ($query) {
						$query->where('scheduling_year', 0)
							->orWhere('scheduling_year', null);
					});
				})
				->orderByDesc('age_from')
				->orderByDesc('age_to')
				->get([
					'id',
					'affiliate_id',
					'rate_for',
					'age_from',
					'age_to',
					'rate',
					'description',
					'additional_charge',
					'orderby',
					'status',
					'permit_fee',
					'min_pax_allowed',
					'max_pax_allowed',
					'is_group_rate',
					'min_group_size',
					'max_group_size',
					'mapping_key',
					'global_rate'
				]);

			$packagePhotos = DB::table('tour_package_photos')
				->where('tour_package_id', $tourPackage->id)
				->where('deleted_by', 0)
				->get(['id', 'placement', 'path', 'status']);

			if (!$packageRates)
				abort(500);

			$hotels = Hotel::where('tour_package_id', $tourPackage->id)->get();

			//get tourPackageImage
			$tourPackageImage = TourPackageImage::where('tour_package_id', $tourPackage->id)->get();

			//get affiliates to assign the package
			$affiliates = Customer::isAffiliate()->departmentPackage()->orderBy('name', 'asc')->get();

			//get assigned affiliate list for this package
			$assignedAffiliates = PackageAffiliateAssignment::where('package_id', $tourPackage->id)
				->orderBy('affiliate_id')
				->get();

			//get cancellation policies list for this package
			$cancellationPolicies = CancellationPolicy::active()
				->where('package_id', $tourPackage->id)
				->get();

			//get categories
			$categories = Category::where('status', 'Active')->get();

			if ($tourPackage->is_combo_package == Constants::STATUS_ONE) {
				$comboTourPackages = $allTourPackages->where('id', '!=', $id)->where('is_combo_package', Constants::STATUS_ONE);
			}

			if (!empty($tourPackage->id) && !empty($tourPackage->name)) {
				// Short code: TENANTID + PackageID
				$shortCode = strtoupper(($tourPackage->tourOperator->tenant_id ?? '') . $tourPackage->id);

				// Full code: PACKAGENAME + PackageID (alphanumeric only)
				$fullCode = str_replace(' ', '', ucwords(strtolower(preg_replace('/[^A-Za-z0-9 ]/', '', $tourPackage->name)))) . $tourPackage->id;

				$suggestedBokunCodes = [$shortCode, $fullCode];
			}

			$vars = [
				'page_title' => 'Edit Tour Package',
				'tourPackage' => $tourPackage,
				'packageRates' => $packageRates,
				'packagePhotos' => $packagePhotos,
				'allTourPackages' => $allTourPackages,
				'nonBundlePackages' => $nonBundlePackages,
				'bundlePackages' => $bundlePackages,
				'tourPackageFamilies' => $tourPackageFamilies,
				'tourPackageFamily' => $tourPackageFamily,
				'hotels' => $hotels,
				'tourPackageImage' => $tourPackageImage,
				'affiliates' => $affiliates,
				'assignedAffiliates' => $assignedAffiliates,
				'cancellationPolicies' => $cancellationPolicies,
				'categories' => $categories,
				'comboTourPackages' => $comboTourPackages ?? [],
				'year' => $year,
				'suggestedBokunCodes' => $suggestedBokunCodes
			];

			return view('users.common.tour-package-edit', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', $exception->getMessage());
		}
	}

	/**
	 * Update general details of the tour package.
	 *
	 * @param  \App\Http\Requests\Common\TourPackageRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateGeneral(TourPackageRequest $tourPackageRequest, int $id)
	{
		try {
			$tourPackage = TourPackage::findOrFail($id);
			$tourOperator = tourOperator();

			DB::transaction(function () use ($tourPackage, $tourOperator, $tourPackageRequest) {
				// Set defaults if not present
				$newValues = $tourPackageRequest->all();
				$newValues['duration'] ??= 0;
				$newValues['ota_duration'] ??= 0;
				$newValues['block_ctb_duration'] ??= 0;

				// Transform things_to_bring
				if (!empty($newValues['things_to_bring'])) {
					$arrayThing = json_decode($newValues['things_to_bring']);
					if (is_array($arrayThing)) {
						$resultstr = array_filter(array_map(fn($v) => !empty($v->value) ? "\"{$v->value}\"" : null, $arrayThing));
						$newValues['things_to_bring'] = '[' . implode(', ', $resultstr) . ']';
					}
				}

				$newValues['tour_operator_id'] ??= $tourOperator->id;

				$oldValues1 = $tourPackage->toArray();
				$oldValues2 = $newValues2 = [];

				// Diff old vs new
				diffRecord([
					'tour_operator_id',
					'name',
					'stripe_destination_id',
					'short_description',
					'long_description',
					'hours',
					'minutes',
					'day_hours',
					'duration',
					'ota_duration',
					'block_ctb_duration',
					'ctb_description',
					'things_to_bring',
					'important_notes',
					'status',
					'overbook_seats',
					'Order_by',
					'service_commission_percentage',
					'stripe_commission_percentage',
					'from_name',
					'from_email',
					'from_email_hotmail',
					'frontend_enabled',
					'is_combo_package',
					'latitude',
					'longitude',
					'productCode',
					'get_your_guide_product_code',
					'bokun_product_code',
					'type',
					'category_id',
					'travel_duration',
					'is_group_rate_enabled',
					'package_has_slots',
					'package_has_waiver',
					'package_has_permit',
					'show_in_pos',
					'show_in_agenda',
					'bundle_package',
					'checkin',
					'checkin_type',
					'expire_in_days'
				], 'u', $oldValues1, $newValues, $oldValues2, $newValues2);

				if (!empty($newValues2)) {
					if (isset($newValues2['name'])) {
						$newValues2['package_name'] = $newValues2['name'];
						unset($newValues2['name']);
					}

					unset($newValues2['short_description'], $newValues2['full_description']);

					if (isset($newValues2['hours']) || isset($newValues2['minutes'])) {
						$hours = $newValues['hours'] ?? 0;
						$minutes = $newValues['minutes'] ?? 0;
						$newValues2['duration'] = "{$hours} hour(s) {$minutes} minute(s)";
						unset($newValues2['hours'], $newValues2['minutes']);
					}

					$updateDT = mysqlDT();

					DB::table('records_history')->insert([
						'record_id_1' => $tourPackage->id,
						'record_id_2' => $tourOperator->id,
						'operation_type' => 'u',
						'table_name' => 'tour_packages',
						'entity_info' => '',
						'old_values' => json_encode($oldValues2),
						'new_values' => json_encode($newValues2),
						'operation_at' => $updateDT,
						'operation_by' => Auth::id(),
						'operation_by_user_type' => session('userType'),
					]);

					$newValues['updated_at'] = $updateDT;
					$newValues['updated_by'] = Auth::id();
					$newValues['updated_by_user_type'] = session('userType');

					$tourPackage->fill($newValues)->save();
				}
			});

			app(OctoRepository::class)->dispatchProductNotifications($tourPackage->id);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour package's general information.");
		}
	}

	/**
	 * Update SEO details of the tour package.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateSEO(Request $request, int $id)
	{
		try {
			if (session('userType') == 'Admin') {
				$tourPackage = TourPackage::findOrFail($id);
			} else {
				$tourPackage = TourPackage::where('tour_operator_id', Auth::user()->tour_operator_id)
					->findOrFail($id);
			}

			$tourPackage->fill($request->all())
				->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour package's SEO details.");
		}
	}

	/**
	 * Update Waiver details of the tour package.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateWaiver(Request $request, int $id)
	{
		try {
			if (session('userType') == 'Admin') {
				$tourPackage = TourPackage::findOrFail($id);
			} else {
				$tourPackage = TourPackage::where('tour_operator_id', Auth::user()->tour_operator_id)
					->findOrFail($id);
			}
			$tourPackage->waiver_content        = $request->waiver_content;
			$tourPackage->school_waiver_content = $request->school_waiver_content;

			if ($request->hasFile('waiver_pdf')) {
				$tenantId = tourOperator()->tenant_id;
				$file     = $request->file('waiver_pdf');
				$path     = $file->store("pdf/{$tenantId}", 's3');
				$tourPackage->waiver_pdf = $path;
			}

			$tourPackage->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour package's waiver details.");
		}
	}
	/**
	 * Delete Waiver PDF of the tour package.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function deleteWaiverPdf(int $id)
	{
		try {
			if (session('userType') == 'Admin') {
				$tourPackage = TourPackage::findOrFail($id);
			} else {
				$tourPackage = TourPackage::where('tour_operator_id', Auth::user()->tour_operator_id)
					->findOrFail($id);
			}
			$waiverPdfPath = $tourPackage->waiver_pdf;
			
			if ($waiverPdfPath && Storage::disk('s3')->exists($waiverPdfPath)) {
				Storage::disk('s3')->delete($waiverPdfPath);
			}
			$tourPackage->waiver_pdf = null;
			$tourPackage->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour package's waiver details.");
		}
	}
	/**
	 * Update Settings of the tour package.
	 *
	 * @param  \App\Http\Requests\Common\UpdateTourPackageSettingsRequest $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateSettings(UpdateTourPackageSettingsRequest $request, int $id)
	{
		try {
			if (session('userType') == 'Admin') {
				$tourPackage = TourPackage::findOrFail($id);
			} else {
				$tourPackage = TourPackage::where('tour_operator_id', Auth::user()->tour_operator_id)
					->findOrFail($id);
			}

			$oldValues2 = $newValues2 = [];
			$oldValues1 = $tourPackage->toArray();
			$newValues1 = $request->all();
			$tourOperator =  tourOperator();
			$newValues1['tour_operator_id'] = $tourOperator->id;

			diffRecord(
				[
					'stripe_destination_id',
					'overbook_seats',
					'Order_by',
					'service_commission_percentage',
					'stripe_commission_percentage',
					'next_year_service_commission_percentage',
					'next_year_stripe_commission_percentage',
					'from_name',
					'from_email',
					'from_email_hotmail',
					'package_colour',
					'latitude',
					'longitude',
					'productCode',
					'get_your_guide_product_code',
					'bokun_product_code',
					'travel_duration',
					'expire_in_days',
					'arrival_time_duration'
				],
				'u',
				$oldValues1,
				$newValues1,
				$oldValues2,
				$newValues2
			);

			if (count($newValues2)) {

				$updateDT = mysqlDT();

				DB::table('records_history')->insert([
					'record_id_1' => $tourPackage->id,
					'record_id_2' => $tourOperator->id,
					'operation_type' => 'u',
					'table_name' => 'tour_packages',
					'entity_info' => '', // $oldValues1['name'],
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

				$tourPackage->fill($newValues1)->save();
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour package's settings information.");
		}
	}
	/**
	 * Update Individual Setting of the tour package.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function updateSetting(Request $request, int $id)
	{
		try {
			$tourPackage = TourPackage::findOrFail($id);

			$oldValues2 = $newValues2 = [];
			$oldValues1 = $tourPackage->toArray();
			$newValues1 = $request->all();

			$tourOperator =  tourOperator();
			$newValues1['tour_operator_id'] = $tourOperator->id;

			diffRecord(
				[
					'category_id',
					'type',
					'checkin_type',
					'frontend_enabled',
					'is_combo_package',
					'bundle_package',
					'show_in_agenda',
					'show_in_pos',
					'is_group_rate_enabled',
					'checkin',
					'package_has_slots',
					'package_has_permit',
					'package_has_waiver',
					'tax_applicable',
					'show_seat_availability',
					'status',
					'has_contacts',
					'is_partner_package',
					'disable_slot_overlap_blocking',
					'slot_close_on_booking',
					'include_in_operation_board',
					'include_in_addons',
					'attach_waiver_pdf'
				],
				'u',
				$oldValues1,
				$newValues1,
				$oldValues2,
				$newValues2
			);

			if (count($newValues2)) {
				$updateDT = mysqlDT();

				DB::table('records_history')->insert([
					'record_id_1' => $tourPackage->id,
					'record_id_2' => $tourOperator->id,
					'operation_type' => 'u',
					'table_name' => 'tour_packages',
					'entity_info' => '', // $oldValues1['name'],
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

				$tourPackage->fill($newValues1)->save();
				app(OctoRepository::class)->dispatchProductNotifications($tourPackage->id);

				if (isset($newValues1['is_partner_package']) && $newValues1['is_partner_package'] != '1') {
					// If the package is not a partner package, then remove the partner fees from the rates
					TourPackageRate::where('tour_package_id', $tourPackage->id)
						->update([
							'partner_fee_amount' => 0,
							'partner_fee_percentage' => 0,
							'partner_fee_type' => NULL,
							'updated_at' => mysqlDT(),
							'updated_by' => Auth::id()
						]);
				}
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update the tour package's settings information.");
		}
	}

	/**
	 * Add photo of tour package.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param int $id
	 * @return mixed
	 */
	public function addPhotos(Request $request, int $id)
	{
		$routePrefix = routePrefix();

		$tourPackage = TourPackage::findOrFail($id);

		$placements = [
			'photo_header' => 1,
			'photos_other' => 2,
			'header_one' => 3,
			'header_two' => 4,
			'date_time_section_image' => 5,
			'frontend_package_image' => 6,
			'email_template_image' => 7,
		];

		$errors = [];

		foreach ($placements as $field => $placement) {
			if ($request->has($field)) {
				$result = $this->addNewPhotos($tourPackage, $request->file($field), $placement);

				if ($result === false) {
					$errors[] = $field;
				} else {
					// Mark old photo as deleted if any already uploaded
					DB::table('tour_package_photos')
						->where('tour_package_id', $id)
						->where('id', '<', $result)
						->where('placement', $placement)
						->update([
							'deleted_at' => mysqlDT(),
							'deleted_by' => auth(session('guard'))->id(),
							'deleted_by_user_type' => session('userType'),
						]);
				}
			}
		}

		$return = redirect()->route($routePrefix . '.editpackage', ['id' => $id, 'year' => date('Y')]);

		if (!empty($errors)) {
			return $return->withErrors('Unable to upload ' . implode(' and ', $errors));
		} else {
			return $return->with('message', 'Photo(s) uploaded.');
		}
	}

	/**
	 * Add new photo for the package
	 *
	 * @param TourPackage $tourPackage
	 * @param mixed $photos
	 * @param mixed $placement
	 */
	private function addNewPhotos(TourPackage $tourPackage, $photos, $placement)
	{
		if (!is_array($photos)) {
			$photos = [$photos];
		}

		DB::beginTransaction();
		try {
			$tourOperator = TourOperator::find($tourPackage->tour_operator_id);

			for ($i = 0; $i < count($photos); $i++) {
				$fileNmExt = fileNameExt($photos[$i]->getClientOriginalName());
				$ext = strtolower($fileNmExt->ext);

				if (in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'])) {
					$pckgPhotoId = DB::table('tour_package_photos')->insertGetId([
						'tour_package_id' => $tourPackage->id,
						'placement' => $placement,
						'created_by' => Auth::id(),
						'created_by_user_type' => session('userType'),
					]);

					$fileName = $fileNmExt->name . "-$pckgPhotoId.$ext";
					$photo = $photos[$i];
					if ($placement == 6) {
						$imageNormal = Image::make($photo->path())->fit(500, 300)->stream();
					} else {
						$imageNormal = Image::make($photo)->stream();
					}

					$path = "images/tour-operator/" . $tourOperator->tenant_id . "/package/$tourPackage->id/";
					Storage::disk('s3')->put($path . $fileName, $imageNormal->__toString());

					DB::table('tour_package_photos')
						->where('id', $pckgPhotoId)
						->update(['path' => $fileName]);
				}
			}

			DB::commit();
			return $pckgPhotoId;
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	/**
	 * Update the photo status in the package
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function updatePhotoStatus(Request $request, int $id)
	{
		try {
			DB::table('tour_package_photos')
				->where('id', $id)
				->update([
					'status' => $request->status ? 'Active' : 'Inactive',
					'updated_at' => mysqlDT(),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => session('userType'),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to update photo's status.");
		}
	}

	/**
	 * Delete the photo from the package
	 *
	 * @param int $id
	 */
	public function deletePhoto(int $id)
	{
		try {
			DB::table('tour_package_photos')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => Auth::id(),
					'deleted_by_user_type' => session('userType'),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete photo.");
		}
	}

	/**
	 * Add or update rate for the package
	 *
	 * @param Request $request
	 * @param int $tourPackageId
	 */
	public function addUpdateRate(Request $request, int $tourPackageId)
	{
		$tourPackage = TourPackage::find($tourPackageId);

		if ($tourPackage->is_group_rate_enabled == '1') {
			$request->validate([
				'rate_for' => 'required|max:50',
				'age_from' => 'max:255',
				'age_to' => 'max:255',
				'rate' => 'required|min:0|max:99999.99',
				'additional_charge' => 'max:99999.99',
				'mapping_key' => 'required|numeric|min:0|max:99999',
				'min_group_size' => 'required|numeric|min:1|max:99999',
				'max_group_size' => 'required|numeric|min:1|max:99999',
			]);
		} else {
			$request->validate([
				'rate_for' => 'required|max:50',
				'age_from' => 'max:255',
				'age_to' => 'max:255',
				'rate' => 'required|min:0|max:99999.99',
				'additional_charge' => 'max:99999.99',
				'orderby' => 'max:100',
				'mapping_key' => 'required|numeric|min:0|max:99999',
			]);
		}

		try {
			$oldValues2 = $newValues2 = [];
			$newValues1 = $request->all();
			if ($tourPackage->is_group_rate_enabled == '1') {
				$newValues1['is_group_rate'] = '1';
			}

			if ($request->tour_package_rate_id) { // record already exist, find it
				$rateGroup = TourPackageRate::findOrFail($request->tour_package_rate_id);
				$mappingKey = $request->mapping_key;
				$mappingKeyRateGroupExist = TourPackageRate::where('tour_package_id', $tourPackageId)
					->where('scheduling_year', $request->scheduling_year)
					->where('id', '!=', $request->tour_package_rate_id)
					->whereNull('affiliate_id')
					->where('mapping_key', $mappingKey)
					->count();
				if ($mappingKey != 0 && $mappingKeyRateGroupExist > 0) {
					return sendJsonErrMsg("Mapping key has been already taken.");
				}

				$operation = 'u';
				$oldValues1 = $rateGroup->toArray();

				if ($rateGroup->age_from && $rateGroup->age_to) {
					$entityInfo = "{$rateGroup->age_from} to {$rateGroup->age_to}";
				} elseif ($rateGroup->age_to) {
					$entityInfo = "{$rateGroup->age_to} and below";
				} else {
					$entityInfo = "{$rateGroup->age_from} and above";
				}

				$entityInfo = "{$rateGroup->rate_for} (Age $entityInfo)";
			} else { // record not added, create new record
				$rateGroup = new TourPackageRate;
				$rateGroup->tour_package_id = $tourPackageId;
				$mappingKey = $request->mapping_key;
				$mappingKeyRateGroupExist = TourPackageRate::where('tour_package_id', $tourPackageId)
					->where('scheduling_year', $request->scheduling_year)
					->whereNull('affiliate_id')
					->where('mapping_key', $mappingKey)
					->count();
				if ($mappingKey != 0 && $mappingKeyRateGroupExist > 0) {
					return sendJsonErrMsg("Mapping key has been already taken.");
				}

				$operation = 'i';
				$oldValues1 = [];

				if ($newValues1['age_from'] && $newValues1['age_to']) {
					$entityInfo = $newValues1['age_from'] . ' to ' . $newValues1['age_to'];
				} elseif ($newValues1['age_to']) {
					$entityInfo = $newValues1['age_to'] . ' and below';
				} else {
					$entityInfo = $newValues1['age_from'] . ' and above';
				}

				$entityInfo = $newValues1['rate_for'] . " (Age $entityInfo)";
			}

			if ($tourPackage->is_group_rate_enabled == '1') {
				diffRecord(
					['scheduling_year', 'rate_for', 'age_from', 'age_to', 'rate', 'description', 'additional_charge', 'orderby', 'status', 'permit_fee', 'min_pax_allowed', 'max_pax_allowed', 'min_group_size', 'max_group_size', 'is_group_rate', 'mapping_key', 'global_rate'],
					$operation,
					$oldValues1,
					$newValues1,
					$oldValues2,
					$newValues2
				);
			} else {
				diffRecord(
					['scheduling_year', 'rate_for', 'age_from', 'age_to', 'rate', 'description', 'additional_charge', 'orderby', 'status', 'permit_fee', 'min_pax_allowed', 'max_pax_allowed', 'mapping_key', 'global_rate'],
					$operation,
					$oldValues1,
					$newValues1,
					$oldValues2,
					$newValues2
				);
			}

			if (count($newValues2)) {
				$inUpDT = mysqlDT();
				DB::beginTransaction();
				$newValues1['updated_at'] = null;

				$rateGroup->fill($newValues1)
					->save();

				if ($operation == 'i') {
					unset($newValues2['rate_for'], $newValues2['age_from'], $newValues2['age_to']);
				}

				DB::table('records_history')->insert([
					'record_id_1' => $rateGroup->id,
					'record_id_2' => $rateGroup->tour_package_id,
					'operation_type' => $operation,
					'table_name' => 'tour_package_rates',
					'entity_info' => $entityInfo,
					'old_values' => json_encode($oldValues2),
					'new_values' => json_encode($newValues2),
					'operation_at' => $inUpDT,
					'operation_by' => Auth::id(),
					'operation_by_user_type' => session('userType'),
				]);

				DB::commit();
				app(OctoRepository::class)->dispatchProductNotifications($rateGroup->tour_package_id);
			}

			if ($request->tour_package_rate_id) { // send blank response for updating record
				return response()->json([]);
			}

			// send record id in response after inserting record
			return response()->json(['tour_package_rate_id' => $rateGroup->id]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			return sendJsonErrMsg("Unable to save the rate group.");
		}
	}

	/**
	 * Delete the rate group from the package
	 *
	 * @param int $id
	 */
	public function deleteRate(int $id)
	{
		try {
			$deleteDT = mysqlDT();

			$rateGroup = DB::table('tour_package_rates AS tpr')
				->where('tpr.id', $id);

			if (session('userType') == 'Tour Operator') {
				$rateGroup = $rateGroup->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
					->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			}

			$rateGroup = $rateGroup->first(['tpr.tour_package_id', 'tpr.rate_for', 'tpr.age_from', 'tpr.age_to']);

			if ($rateGroup->age_from && $rateGroup->age_to) {
				$entityInfo = "{$rateGroup->age_from} to {$rateGroup->age_to}";
			} elseif ($rateGroup->age_to) {
				$entityInfo = "{$rateGroup->age_to} and below";
			} else {
				$entityInfo = "{$rateGroup->age_from} and above";
			}

			$entityInfo = "{$rateGroup->rate_for} (Age $entityInfo)";

			DB::table('tour_package_rates')
				->where('id', $id)
				->update([
					'deleted_at' => $deleteDT,
					'deleted_by' => Auth::id(),
					'deleted_by_user_type' => session('userType'),
				]);

			DB::table('records_history')->insert([
				'record_id_1' => $id,
				'record_id_2' => $rateGroup->tour_package_id,
				'operation_type' => 'd',
				'table_name' => 'tour_package_rates',
				'entity_info' => $entityInfo,
				'old_values' => '',
				'new_values' => '',
				'operation_at' => $deleteDT,
				'operation_by' => Auth::id(),
				'operation_by_user_type' => session('userType'),
			]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the rate group.");
		}
	}

	/**
	 * Add the parent or child relation from the tour package
	 *
	 * @param Request $request
	 * @param int $tourPackageId
	 *
	 * @return mixed
	 */
	public function addRelation(Request $request, int $tourPackageId)
	{
		$request->validate([
			'tour_operator_id' => 'required',
			'parent_package_id' => 'required',
			'tour_package_id' => 'required',
			'time_from' => 'required',
			'is_parent' => 'required',
			'group_index' => 'required',
			'relation_year' => 'required'
		]);

		try {
			$checkParentPackage = TourPackageFamily::where('tour_package_id', $request->tour_package_id)
				->where('relation_year', $request->relation_year)
				->first();

			// If the selected package is already a child of some other parent, then get that parent_package_id
			if ($checkParentPackage) {
				$tourPackageId = $checkParentPackage->parent_package_id;
				$request->merge([
					'parent_package_id' => $tourPackageId,
				]);
			}

			if ($request->is_parent == 1 && $tourPackageId != $request->tour_package_id) {
				throw new Exception("Please pick/edit the appropriate package to establish relations.");
			}


			$packageExist = TourPackageFamily::where('tour_package_id', $request->tour_package_id)
				->where('parent_package_id', $tourPackageId)
				->where('time_from', $request->time_from)
				->where('group_index', $request->group_index)
				->where('relation_year', $request->relation_year)
				->first();
			if ($packageExist) {
				throw new Exception("The chosen package has already been added.");
			}

			$relationWithSameIndexExist = TourPackageFamily::where('tour_operator_id', $request->tour_operator_id)
				->where('tour_package_id', $request->tour_package_id)
				->where('parent_package_id', $tourPackageId)
				->where('time_from', '!=', $request->time_from)
				->where('group_index', $request->group_index)
				->where('relation_year', $request->relation_year)
				->first();
			if ($relationWithSameIndexExist) {
				throw new Exception("The group index has already been taken for this package. Please choose a different group index.");
			}
			if ($request->is_parent == 0) {
				$childExist = TourPackageFamily::where('tour_operator_id', $request->tour_operator_id)
					->where('tour_package_id', $request->tour_package_id)
					->where('parent_package_id', $tourPackageId)
					->where('time_from', $request->time_from)
					->where('group_index', $request->group_index)
					->where('is_parent', $request->is_parent)
					->where('relation_year', $request->relation_year)
					->count();

				if ($childExist) {
					throw new Exception("The combo package has already been added.");
				}
			}

			$tourPackageFamily = TourPackageFamily::create($request->all());

			// send record id in response after inserting record
			return response()->json($tourPackageFamily);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Remove the parent or child relation from the tour package
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function deleteRelation(int $id)
	{
		try {
			$tourPackageFamily = TourPackageFamily::find($id);
			if (!$tourPackageFamily) {
				return sendJsonErrMsg("Relation group not found.");
			}
			$tourPackageFamily->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the relation group.");
		}
	}

	/**
	 * Add the hotel for the tour package
	 *
	 * @param Request $request
	 * @param int $tourPackageId
	 *
	 * @return mixed
	 */
	public function addHotel(Request $request, int $tourPackageId)
	{
		$request->validate([
			'tour_operator_id' => 'required',
			'tour_package_id' => 'required',
			'name' => 'required',
			'status' => 'required',
			'address' => 'required',
		]);

		try {
			$hotel = Hotel::create($request->all());

			if ($request->hasFile('hotel_image')) {
				$tourOperator = TourOperator::find($request->tour_operator_id);

				$file = $request->file('hotel_image');
				$name = time() . $file->getClientOriginalName();

				$imageThumb = Image::make($file->path())->resize(100, 100, function ($constraint) {
					$constraint->aspectRatio();
				})->stream();

				$filePath = "images/tour-operator/" . $tourOperator->tenant_id . "/package/" . $request->tour_package_id . "/hotels/" . $hotel->id . "/" . $name;
				Storage::disk('s3')->put($filePath, $imageThumb);

				$hotel->image = $name;
				$hotel->save();
			}

			if (session('userType') == 'Admin') {
				$routePrefix = 'admin.';
			} else {
				$routePrefix = '';
			}

			return redirect()
				->route($routePrefix . 'tour-operator.editpackage', [$request->tour_operator_id, last(explode('/', url()->previous()))])
				->with('message', 'Hotel added.');
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => 'Unable to add hotel.'])
				->withInput();
		}
	}

	/**
	 * Update the hotel from the tour package
	 *
	 * @param Request $request
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function updateHotel(Request $request, int $id)
	{
		$request->validate([
			'name' => 'required',
			'status' => 'required',
			'address' => 'required',
			'location_url' => 'required'
		]);

		try {
			$hotel = Hotel::find($id);
			$hotel->name = $request->name;
			$hotel->email = $request->email;
			$hotel->phone_number = $request->phone_number;
			$hotel->status = $request->status;
			$hotel->address = $request->address;
			$hotel->location_url = $request->location_url;

			if ($request->hasFile('hotel_image')) {
				$tourOperator = TourOperator::find($hotel->tour_operator_id);

				$file = $request->file('hotel_image');
				$name = time() . $file->getClientOriginalName();

				$imageThumb = Image::make($file->path())->resize(100, 100, function ($constraint) {
					$constraint->aspectRatio();
				})->stream();

				$filePath = "images/tour-operator/{$tourOperator->tenant_id}/package/{$hotel->tour_package_id}/hotels/{$hotel->id}/$name";
				Storage::disk('s3')->put($filePath, $imageThumb);

				$hotel->image = $name;
			}

			$hotel->save();

			$routePrefix = (session('userType') == 'Admin') ? 'admin.' : '';

			return redirect()
				->route("{$routePrefix}tour-operator.editpackage", [$hotel->tour_operator_id, last(explode('/', url()->previous()))])
				->with('message', 'Hotel updated.');
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => 'Unable to update hotel.'])
				->withInput();
		}
	}

	/**
	 * Remove the hotel from the tour package
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function deleteHotel(int $id)
	{
		try {
			$hotel = Hotel::find($id);
			if (!$hotel) {
				return sendJsonErrMsg("Hotel not found.");
			}
			$hotel->deleted_by = Auth::id();
			$hotel->deleted_by_user_type = userType();
			$hotel->save();

			// Hotel delete
			$hotel->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the relation group.");
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
			$query = DB::table('tour_packages')
				->where('id', $id);

			if (session('userType') == 'Tour Operator') {
				$query = $query->where('tour_operator_id', Auth::user()->tour_operator_id);
			}

			$query = $query->update([
				'deleted_at' => mysqlDT(),
				'deleted_by' => Auth::id(),
				'deleted_by_user_type' => session('userType'),
			]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete tour package.");
		}
	}

	/**
	 * Get the packages list
	 *
	 * @param int $tourOperatorId
	 */
	public function getPackages($tourOperatorId)
	{
		try {
			$tourPackages = TourPackage::where('status', 'Active')
				->where('tour_operator_id', $tourOperatorId)
				->orderBy('name')
				->get(['id', 'name']);

			return response()->json($tourPackages);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to get tour packages.");
		}
	}

	/**
	 * Remove the Category from the tour package
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function deleteCategory($id)
	{
		try {
			$tourPackageImage = TourPackageImage::find($id);
			if (!$tourPackageImage) {
				return sendJsonErrMsg("Category not found.");
			}
			$tourPackageImage->deleted_by = Auth::id();
			$tourPackageImage->deleted_by_user_type = userType();
			$tourPackageImage->save();

			// Category delete
			$tourPackageImage->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the category.");
		}
	}

	/**
	 * Add the category for the tour package
	 *
	 * @param Request $request
	 *
	 * @return mixed
	 */
	public function addCategory(Request $request)
	{
		$request->validate([
			'tour_package_id' => 'required',
			'caption' => 'required',
			'category' => 'required',
			'category_image' => 'required',
		]);

		$getCategory = function ($cat) {
			$cat = str_replace(' ', '', $cat);
			if ($cat == 'CoverImages') {
				$cat = 'mainImage';
			}

			if ($cat == 'Map') {
				$cat = 'Directions';
			}

			return $cat;
		};

		DB::beginTransaction();
		try {
			$tourPackageImage = new TourPackageImage;
			$tourPackageImage->caption = $request->caption;
			$tourPackageImage->tour_package_id = $request->tour_package_id;
			$tourPackageImage->image = $request->category;
			$tourPackageImage->category = $getCategory($request->category);
			$tourPackageImage->created_by = Auth::id();
			$tourPackageImage->created_by_user_type = userType();
			$tourPackageImage->is_cover = ($request->category == 'Cover Images') ? 1 : 0;

			if ($request->hasFile('category_image')) {
				$tourOperator = TourOperator::find($request->tour_operator_id);

				$file = $request->file('category_image');
				$name = time() . $file->getClientOriginalName();
				$imageThumb = Image::make($file->path())->stream();
				// Removing 100/100  Resize To Give Mobile App Dev control over the aspect ratio of the images
				// if (in_array($tourPackageImage->category, ['mainImage', 'Directions', 'Splash'])) {
				// 	$imageThumb = Image::make($file->path())->stream();
				// } else {
				// 	$imageThumb = Image::make($file->path())->resize(100, 100, function ($constraint) {
				// 		$constraint->aspectRatio();
				// 	})->stream();
				// }

				$filePath = "images/tour-operator/" . $tourOperator->tenant_id . "/package/" . $request->tour_package_id . "/category" . $tourPackageImage->id . "/" . $name;
				Storage::disk('s3')->put($filePath, $imageThumb);

				$tourPackageImage->url = Storage::disk('s3')->url($filePath);
			}

			$tourPackageImage->save();
			DB::commit();

			return back()->with('message', 'Category added.');
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);

			return back()
				->withErrors(['errors' => 'Unable to add category.'])
				->withInput();
		}
	}

	/**
	 * Update the category of the package booking
	 *
	 * @param Request $request
	 */
	public function updateCategory(Request $request)
	{
		$request->validate([
			'tour_package_id' => 'required',
			'caption' => 'required',
			'category' => 'required',
			'tour_package_image_id' => 'required',
		]);

		DB::beginTransaction();
		try {
			$tourPackageImage = TourPackageImage::find($request->tour_package_image_id);
			$tourPackageImage->caption = $request->caption;
			$tourPackageImage->tour_package_id = $request->tour_package_id;
			$tourPackageImage->image = $request->category;
			$tourPackageImage->updated_by = Auth::id();
			$tourPackageImage->updated_by_user_type = userType();

			if ($request->hasFile('category_image')) {
				$tourOperator = TourOperator::find($request->tour_operator_id);

				$file = $request->file('category_image');
				$name = time() . $file->getClientOriginalName();
				$imageThumb = Image::make($file->path())->stream();
				// Removing 100/100  Resize To Give Mobile App Dev control over the aspect ratio of the images
				// if (in_array($request->category, ['Cover Images', 'Map', 'Splash'])) {
				// 	$imageThumb = Image::make($file->path())->stream();
				// } else {
				// 	$imageThumb = Image::make($file->path())->resize(100, 100, function ($constraint) {
				// 		$constraint->aspectRatio();
				// 	})->stream();
				// }

				$filePath = "images/tour-operator/" . $tourOperator->tenant_id . "/package/" . $request->tour_package_id . "/category" . $tourPackageImage->id . "/" . $name;
				Storage::disk('s3')->put($filePath, $imageThumb);

				$tourPackageImage->url = Storage::disk('s3')->url($filePath);
			}

			$tourPackageImage->save();
			DB::commit();

			return back()->with('message', 'Category updated.');
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);

			return back()
				->withErrors(['errors' => 'Unable to update category.'])
				->withInput();
		}
	}

	/**
	 * Dynamic iframe URL for the frontend packages
	 *
	 * @param Request $request
	 */
	public function iFrameUrl(Request $request)
	{
		$request->validate([
			'iframe_url' => 'required',
			'tour_package_id' => 'required',
			'tour_operator_id' => 'required',
		]);

		DB::beginTransaction();
		try {
			$tourOperator = TourPackage::find($request->tour_package_id);

			if (app()->environment('staging')) {
				$count = strlen(config('constants.environment_iframe.staging'));
				$substr = substr($request->iframe_url, $count);
				$tourOperator->iframe_url = $substr;
			} elseif (app()->environment('production')) {
				$count = strlen(config('constants.environment_iframe.production'));
				$substr = substr($request->iframe_url, $count);
				$tourOperator->iframe_url = $substr;
			} elseif (app()->environment('development')) {
				$count = strlen(config('constants.environment_iframe.development'));
				$substr = substr($request->iframe_url, $count);
				$tourOperator->iframe_url = $substr;
			} else {
				$count = strlen(config('constants.environment_iframe.local'));
				$substr = substr($request->iframe_url, $count);
				$tourOperator->iframe_url = $substr;
			}

			if (!empty($tourOperator->things_to_bring)) {
				$arrayThing = json_decode($tourOperator->things_to_bring);
				foreach ($arrayThing as $value) {
					if (!empty($value->value)) {
						$resultstr[] = '"' . $value->value . '"';
					}
				}

				$rm = implode(", ", $resultstr);
				$tourOperator->things_to_bring = "[" . $rm . "]";
			}

			$tourOperator->save();
			DB::commit();

			return back()->with('message', 'iFrame url updated.');
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);

			return back()
				->withErrors(['errors' => 'Unable to update iframe url.'])
				->withInput();
		}
	}

	/**
	 * Assign affiliate to the package
	 *
	 * @param int $id
	 * @param Request $request
	 */
	public function assignAffiliate($id, Request $request)
	{
		DB::beginTransaction();
		$affiliateId = $request->affiliateId;
		$isAssigned = ($request->has('isAssigned')) ? $request->isAssigned : '';
		$showAllPackageBookings = ($request->has('showAllPackageBookings')) ? $request->showAllPackageBookings : '';
		$checkExistence = PackageAffiliateAssignment::where('package_id', $id)
			->where('affiliate_id', $affiliateId)
			->first();

		if (!$checkExistence && $showAllPackageBookings != '') {
			return sendJsonErrMsg("Please assign the affiliate first.");
		}

		if ($checkExistence && ($isAssigned != '' && $isAssigned == 0)) {
			$checkExistence->forceDelete();
			DB::commit();
		} else if ($isAssigned == 1) {
			$assignment = new PackageAffiliateAssignment();
			$assignment->affiliate_id = $affiliateId;
			$assignment->package_id = $id;
			$assignment->show_all_package_bookings = 0;
			$assignment->created_by = Auth::id();
			$assignment->created_by_user_type = userType();
			$assignment->save();
			DB::commit();
		} else if ($checkExistence && $showAllPackageBookings != '') {
			$checkExistence->show_all_package_bookings = $showAllPackageBookings;
			$checkExistence->save();
			DB::commit();
		}

		return response()->json([]);
	}

	/**
	 * Update the Assigned Affiliate
	 *
	 * @param Request $request
	 */
	public function updateAssignedAffiliate(Request $request)
	{
		try {
			DB::beginTransaction();
			$affiliateAssignment = PackageAffiliateAssignment::find($request->id);
			$affiliateAssignment->show_all_package_bookings = $request->show_all_package_bookings;
			$affiliateAssignment->save();
			DB::commit();

			return back()->with('message', 'Affiliate updated.');
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return back()
				->withErrors(['errors' => 'Unable to update affiliate.'])
				->withInput();
		}
	}

	/**
	 * Remove the assigned affiliate from the tour package
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function unassignAffiliate(int $id)
	{
		try {
			$assignment = PackageAffiliateAssignment::find($id);
			$assignment->forceDelete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the assigned affiliate.");
		}
	}

	/**
	 * SavePackageFrom && UpdatePackgeForm
	 * Upsert Custom Form / update or insert new custom form for package id
	 * Note: one form per package id
	 * Note: this is only updated from the package edit page.
	 * @param Request $request body: [ id=>int?, name=>str, package_id=>int, fields=>obj ]
	 */
	public function upsertPackageForm(Request $request)
	{
		$id = null;
		$action = null;
		DB::beginTransaction();
		try {
			if (isset($request->id) && !empty($request->id)) {
				$action = "update";
				$id = $request->id;
				$data = ['form_name' => $request->name, 'description' => $request->description, 'status' => $request->status, 'form_package_id' => $request->package_id, 'form_fields' => json_encode($request->fields)];
				TourPackageForm::where('id', $id)->update($data);
			} else {
				$action = "insert";
				$tourPackageForm = new TourPackageForm;
				$tourPackageForm->form_name = $request->name;
				$tourPackageForm->description = $request->description;
				$tourPackageForm->form_package_id = intval($request->package_id);
				$tourPackageForm->form_fields = json_encode($request->fields);
				$tourPackageForm->status = $request->status;
				$tourPackageForm->save();
				$id = $tourPackageForm->id;
			}
			DB::commit();
			return  response()->json(["success" => true, "action" => $action,  "id" => $id, "data" => $request->all(), "error" => null]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return  response()->json(["success" => false, "action" => $action, "id" => $id, "data" => $request->all(), "error" => $exception]);
		}
	}

	/**
	 * Get the custom form from the db by tour package id
	 * @method get
	 * @param $tourPackageId
	 */
	public function getPackageForm($tourPackageId)
	{
		try {
			$form = TourPackageForm::where('form_package_id', intval($tourPackageId))->limit(1)->select(["id", "form_name", "description", "status", "form_package_id", "form_fields"])->get()->first();
			return response()->json([
				"success" => true,
				"tour_package_id" => $tourPackageId,
				"form" => $form,
				"error" => null,
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return  response()->json(["success" => false, "tour_package_id" => $tourPackageId, "form" => null, "error" => $exception]);
		}
	}

	/**
	 * Hard Delete The Custom Form
	 * Has no cascade effect
	 * @param $id primary row id
	 * @param $tourPackageId package id
	 */
	public function deletePackageFrom($id, $tourPackageId)
	{
		DB::beginTransaction();
		try {
			DB::delete("DELETE FROM `tour_package_form` WHERE `id` = ?  AND `form_package_id` = ? ", [intval($id), intval($tourPackageId)]);
			DB::commit();
			return response()->json(["success" => true,  "id" => $id, "error" => null]);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return  response()->json(["success" => false, "id" => $id, "error" => $exception]);
		}
	}

	public function getSubmittedForm($bookingId)
	{
		$res =  ['form_id' => null, 'booking_id' => null, 'fields' => null, 'error' => null];
		try {
			$form = TourBookingForms::where('tour_booking_id', intval($bookingId))
				->limit(1)
				->select(["tour_package_form_id", "tour_booking_id", "form_submission"])
				->get()
				->first();

			if ($form) {
				$res['form_id'] =  $form->tour_package_form_id;
				$res['booking_id'] = $form->tour_booking_id;

				$fields = json_decode($form->form_submission);
				if (is_iterable($fields)) {
					foreach ($fields as $field) {
						$field->booking_addon_id = 0;
					}
				}

				$res['fields'] = $fields;
			}
		} catch (Exception $e) {
			Log::error($e);
			$res['error'] = $e->getMessage();
		}

		return response()->json($res);
	}

	/**
	 *
	 */
	public function updateSubmittedForm(Request $request)
	{
		$res =  ['update' => null, 'form_id' => null, 'booking_id' => null, 'fields' => null, 'error' => null];
		try {
			DB::beginTransaction();
			$trans = Transaction::where('tour_booking_id', $request->tour_booking_id)->first();
			$bookingT = BookingTransact::where('tour_booking_id', $request->tour_booking_id)->where('deleted_by', 0)->first();
			$payLaterAddonsOld = BookingService::fetchPayLaterAddonsWithPrice($request->tour_booking_id, $bookingT->nat_fees_percentage, $trans);

			$bookingFormAddons = TourBookingForms::firstOrCreate(['tour_booking_id' => $request->tour_booking_id]);

			$bookingFormAddons->tour_package_form_id = intval($request->tour_package_form_id);
			$bookingFormAddons->tour_booking_id = intval($request->tour_booking_id);
			$bookingFormAddons->form_submission = json_encode($request->form_submission);

			$res["update"] = $bookingFormAddons->save();
			$res['form_id'] =  $request->tour_package_form_id;
			$res['booking_id'] = $request->tour_booking_id;
			$res['fields'] = $request->form_submission;

			if (empty($trans)) {
				$tourBooking = TourBooking::find($request->tour_booking_id);
				$tourBookingPaymentLink = TourBookingPaymentLink::where('order_id', $tourBooking->group_id)->where('status', 'Unpaid')->first();

				if ($tourBookingPaymentLink) {

					$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($request->tour_booking_id, $bookingT->nat_fees_percentage, $trans);

					$infoDetails = BookingUtils::getBookingDetails($tourBooking->id);
					$data = [
						'oldAddonsTotal' => $payLaterAddonsOld['addons_total'],
						'newAddonsTotal' => $payLaterAddons['addons_total']
					];
					PaymentLinkService::paymentLinkUpdate($data, $infoDetails, $tourBooking, $tourBookingPaymentLink, 'Add-ons');

					/*if ($payLaterAddonsOld['addons_total'] > $payLaterAddons['addons_total']) {

						$tourBookingPaymentLink->amount = $tourBookingPaymentLink->amount - ($payLaterAddonsOld['addons_total'] - $payLaterAddons['addons_total']);

					} else {
						$tourBookingPaymentLink->amount = $tourBookingPaymentLink->amount + ($payLaterAddons['addons_total'] - $payLaterAddonsOld['addons_total']);

					}
					$tourBookingPaymentLink->stripe_session_id = null;
					$tourBookingPaymentLink->stripe_checkout_url = null;
					$tourBookingPaymentLink->save();*/
				}
			}
			DB::commit();
		} catch (Exception $e) {
			Log::error($e);
			$res["error"] = $e->getMessage();
		}

		return response()->json($res);
	}

	/**
	 * Store cancellation policies for the package
	 *
	 * @param Request $request
	 */
	public function storeCancellationPolicy(Request $request)
	{
		try {
			$request->validate([
				'package_id' => 'required',
				'title' => 'required',
				'description' => 'required',
				'days_hours_before' => 'required',
				'type' => 'required',
				'refund_basis' => 'required',
				'refund_percent' => 'required'
			]);

			$checkExistence = CancellationPolicy::where('package_id', $request->package_id)
				->where('days_hours_before', $request->days_hours_before)
				->where('type', $request->type)
				->count();

			if ($checkExistence) {
				throw new Exception("This package has already have this cancellation policy.");
			}

			$policy = new CancellationPolicy();
			$policy->package_id = $request->package_id;
			$policy->title = $request->title;
			$policy->description = $request->description;
			$policy->days_hours_before = $request->days_hours_before;
			$policy->type = $request->type;
			$policy->refund_basis = $request->refund_basis;
			$policy->refund_percent = ($request->refund_basis == 'no_refund') ? 0 : $request->refund_percent;
			$policy->save();

			return back()->with('message', 'Cancellation policy added.');
		} catch (Exception $exception) {
			Log::error($exception);

			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Remove the cancellation policy from the tour package
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function deleteCancellationPolicy(int $id)
	{
		try {
			$policy = CancellationPolicy::find($id);
			$policy->forceDelete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the cancellation policy.");
		}
	}

	/**
	 * Add the child bundle packages for the selected tour package
	 *
	 * @param Request $request
	 * @param int $tourPackageId
	 *
	 * @return mixed
	 */
	public function addBundleRelation(Request $request, int $tourPackageId)
	{
		$request->validate([
			'tour_operator_id' => 'required',
			'parent_package_id' => 'required',
			'tour_package_id' => 'required'
		]);

		try {
			$packageExist = TourPackage::findOrFail($tourPackageId);
			if (!$packageExist) {
				throw new Exception("The tour package not found!!");
			}

			if ($packageExist->bundle_package == 0) {
				throw new Exception("Please enable the bundle package option from the general package details.");
			}

			$tourPackage = TourPackageBundleRelation::where('parent_id', $tourPackageId)
				->where('tour_package_id', $request->tour_package_id)
				->first();

			if ($tourPackage) {
				throw new Exception("The chosen package has already been added.");
			}

			$packageName = TourPackage::select('name')->where('id', $request->tour_package_id)->first();

			if (!$packageName) {
				throw new Exception("The chosen package not found.");
			}

			$bundlePackage = TourPackageBundleRelation::create([
				'parent_id' => $tourPackageId,
				'tour_package_id' => $request->tour_package_id,
				'package_name' => $packageName->name
			]);

			// send record id in response after inserting record
			return response()->json($bundlePackage);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Remove the child bundle relation for the selected tour package
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public function deleteBundleRelation(int $id)
	{
		try {
			$bundlePackage = TourPackageBundleRelation::find($id);
			if (!$bundlePackage) {
				return sendJsonErrMsg("Bundle Package not found.");
			}
			$bundlePackage->deleted_by = Auth::id();
			$bundlePackage->deleted_by_user_type = session('userType');
			$bundlePackage->save();
			$bundlePackage->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg("Unable to delete the bundle package.");
		}
	}
}
