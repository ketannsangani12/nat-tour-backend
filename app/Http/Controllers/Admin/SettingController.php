<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

use App\Models\Tenant;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\SurchargeDate;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Repositories\Pos\CardReaderRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
	/**
	 * Parent data to be fetched
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Show the form for updating settings of the platform.
	 *
	 * @return mixed
	 */
	public function edit()
	{
		$isCentral = isCentralDomain();

		$packages     = $isCentral ? collect() : TourPackage::active()->orderBy('Order_by')->get();
		$surchargeDate = $isCentral ? collect() : SurchargeDate::with('tourPackage')->get();

		if (! $isCentral) {
			foreach ($surchargeDate as $surcharge) {
				$surcharge->date = Carbon::parse($surcharge->date)->format('m/d/Y');
			}
		}

		$settings = $isCentral
			? Setting::whereIn('key', ['MAINTENANCE_MODE', 'STRIPE_WEBHOOK_SECRET'])->get()
			: Setting::orderBy('order_number')->get();

		$routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';

		foreach ($settings as $setting) {
			$setting->display_name = match ($setting->key) {
				'CUSTOM_FIELDS'                  => 'ADD-ONS',
				'CDR_PACKAGES'                   => 'Packages',
				'CDR_AFFILIATES'                 => 'Affiliates',
				'DEFAULT_BOOKING_SOURCE'         => 'Default Booking Source',
				'PLATFORM_HELD_PAYOUT_ENABLED'   => 'Payout Simplification (2027+ Bookings)',
				'ALLOW_NEXT_YEAR_BOOKINGS'       => 'Allow ' . (date('Y') + 1) . ' Bookings',
				default => $setting->key,
			};
		}

		$vars = [
			'page_title' => 'Settings',
			'packages' => $packages,
			'settings' => $settings,
			'surchargeDate' => $surchargeDate,
			'formSubmitRoute' => route(prefixedRouteName("{$routePrefix}setting.update")),
			'formSubmitRouteSurchargeDate' => route(prefixedRouteName("{$routePrefix}setting.add-surcharge-date")),
		];

		return view('users.admin.setting-add-edit', $vars);
	}

	/**
	 * Store settings of the platform.
	 *
	 * @param  Request $request
	 * @return mixed
	 */
	public function update(Request $request)
	{
		try {
			$params = $request->all();
			foreach ($params as $key => $param) {
				if (!is_numeric($key)) continue;
				$setting = Setting::findOrFail($key);

				// Deliberately not editable from this generic settings form (see the matching
				// comment in setting-add-edit.blade.php) — flipping which Stripe settlement mode
				// live payments use should never happen via an accidental toggle click, only a
				// deliberate direct database update.
				if ($setting->key === 'PLATFORM_HELD_PAYOUT_ENABLED') {
					continue;
				}

				if ($setting->value != $param) {
					$setting->value = $param;
					$setting->updated_at = Carbon::now();
					$setting->updated_by = Auth::id();
					$setting->save();
				}

				// if we are changing the prefix then update the prefix in tenant central table as well
				if ( $setting->key == "BOOKING_ID_PREFIX" ) {
					Tenant::updatePrefixFromTenant($param);
				}

				if ( $setting->key === 'MAINTENANCE_MODE' ) {
					Cache::store('file')->forget('central_maintenance_mode');
				}
			}
			//end forloop

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


	public function updateStripeSetting( Request $request )
	{
		try {

			// Add more commands as needed
			$avaliable_commands = [
				"debit_negative_balances_on" =>     [
					'settings' => [
						'payouts' => [
							'debit_negative_balances' => true,
						],
					],
				],
				"debit_negative_balances_off" =>     [
					'settings' => [
						'payouts' => [
							'debit_negative_balances' => false,
						],
					],
				]
			];

			$cmd = $avaliable_commands[$request->command];
			if ( empty($cmd) ) {
				return sendJsonErrMsg("Invalid command");
			}

			app(PaymentGatewayInterface::class)->updateAccount($request->connected_account_id, $cmd);
			return response()->json(["success" => true, "message" => "Stripe setting updated successfully", "cmd" => $cmd ] );
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function getStripeSettings( Request $request )
	{
		try {
			$account = app(PaymentGatewayInterface::class)->retrieveAccount($request->id);
			$settings = $account->settings;
			return response()->json(["success" => true, "message" => "Stripe settings fetched successfully", "settings" => $settings ]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


	/**
	 * Add surcharge date.
	 *
	 * @param  Request $request
	 * @return mixed
	 */
	public function addSurchargeDate(Request $request)
	{
		try {
			// Validate the incoming request data
			$request->validate([
				'date_range_start' => 'required|date',
				'date_range_end' => 'required|date|after_or_equal:date_range_start',
			]);

			// Check for any overlapping date ranges
			$isOverlapping = SurchargeDate::where(function ($query) use ($request) {
					$query->whereBetween('date_range_start', [$request->date_range_start, $request->date_range_end])
						->orWhereBetween('date_range_end', [$request->date_range_start, $request->date_range_end])
						->orWhere(function ($query) use ($request) {
							$query->where('date_range_start', '<=', $request->date_range_start)
									->where('date_range_end', '>=', $request->date_range_end);
						});
				})
				->where('tour_package_id', $request->tour_package_id)
				->exists();

			if ($isOverlapping) {
				throw ValidationException::withMessages([
					'date_range_start' => 'The specified date range overlaps with an existing range.',
					'date_range_end' => 'The specified date range overlaps with an existing range.',
				]);
			}

			$surchargeDate = new SurchargeDate;
			$surchargeDate->custom_date_name = $request->custom_date_name;
			$surchargeDate->tour_package_id = $request->tour_package_id;
			$surchargeDate->date = $request->date_range_start;
			$surchargeDate->date_range_start = $request->date_range_start;
			$surchargeDate->date_range_end = $request->date_range_end;
			$surchargeDate->apply_on_slots = $request->apply_on_slots;
			$surchargeDate->save();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Update surcharge date.
	 *
	 * @param  Request $request
	 * @return mixed
	 */
	public function updateSurchargeDate(Request $request)
	{
		try {
			// Validate the incoming request data
			$request->validate([
				'id' => 'required|exists:surcharge_dates,id',
				'date_range_start' => 'required|date',
				'date_range_end' => 'required|date|after_or_equal:date_range_start',
			]);

			// Check for any overlapping date ranges
			$isOverlapping = SurchargeDate::where(function ($query) use ($request) {
					$query->whereBetween('date_range_start', [$request->date_range_start, $request->date_range_end])
						->orWhereBetween('date_range_end', [$request->date_range_start, $request->date_range_end])
						->orWhere(function ($query) use ($request) {
							$query->where('date_range_start', '<=', $request->date_range_start)
									->where('date_range_end', '>=', $request->date_range_end);
						});
				})
				->where('id', '!=', $request->id)
				->exists();

			if ($isOverlapping) {
				throw ValidationException::withMessages([
					'date_range_start' => 'The specified date range overlaps with an existing range.',
					'date_range_end' => 'The specified date range overlaps with an existing range.',
				]);
			}

			$surchargeDate = SurchargeDate::find($request->id);
			$surchargeDate->custom_date_name = $request->custom_date_name;
			$surchargeDate->date_range_start = $request->date_range_start;
			$surchargeDate->date_range_end = $request->date_range_end;
			$surchargeDate->apply_on_slots = $request->apply_on_slots;
			$surchargeDate->save();

			// Return success response
			return redirect()->back()->with('success', 'Standard Rate Date updated successfully.');
		} catch (Exception $exception) {
			Log::error($exception);
			return redirect()->back()->with('error', 'Failed to update Standard Rate Date.');
		}
	}

	/**
	 * Delete surcharge date.
	 *
	 * @param  $id
	 * @return mixed
	 */
	public function deleteSurchargeDate($id)
	{
		try {
			$surchargeDate =  SurchargeDate::find($id);
			$surchargeDate->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


	public function cardReadersSettings(){
		$vars = [
			'page_title' => 'Card Reader Settings',
		];
		return view('users.admin.card-reader-settings', $vars);
	}

	public function printerSettings(){
		$vars = [
			'page_title' => 'Printer Settings',
		];
		return view('users.admin.printer-settings', $vars);
	}

	/**
	 * Return Card Reader Actions
	 * @param Request $request (action str)
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function cardReaderActions(Request $request){
		$action = "";
		$result = [];
		try{
			$cardReader = new CardReaderRepository();
			$action = $request->action;

			switch ($action) {
				case 'connection_token':
					$result = $cardReader->createConnectionToken();
					break;
				case 'pair_reader':
					$result = $cardReader->pairReader($request->all());
					break;
				case 'unpair_reader':
					$result = $cardReader->unpairReader($request->reader_id);
					break;
				case 'get_account_settings':
					$result = $cardReader->getAccountSettings();
					break;
				case 'get_location':
					$result = $cardReader->getLocation($request->location_id);
					break;
				case 'get_payment_intent':
					$result = $cardReader->getPaymentIntent($request->all());
					break;
				default:
					$action = "no action taken";
					break;
			}

			// CardReaderRepository's ownership checks (get_location/pair_reader/
			// unpair_reader) fail "quietly" — they push to $cardReader->errors
			// and return null rather than throwing. Without checking that here,
			// this would still respond 200/success:true and the frontend's
			// postAction() (which only throws on a non-OK HTTP status) would
			// treat a rejected request as if it had succeeded.
			if (!empty($cardReader->errors)) {
				return sendJsonErrMsg($cardReader->errors, 422);
			}

			return response()->json(["success" => true,
									 "message" => "Request ".$action." successful",
									 "result" => $result,
									 "errors" => $cardReader->errors
								]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


}
