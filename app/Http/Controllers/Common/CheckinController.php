<?php

namespace App\Http\Controllers\Common;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Repositories\CheckIn\CheckInRepository;
use App\Constants\Constants;
use Illuminate\Support\Facades\Log;

/**
 * QR Code Checkin Controller was created to display 
 * A customer centric check-in flow using a QR code.
 */
class CheckinController extends Controller
{
	private $checkInRepository;

	/**
	 * Init CheckIn Class
	 * @return mixed
	 */
	public $tourPackage = TourPackage::class;
	
	public function __construct(CheckInRepository $checkInRepo)
	{
		parent::__construct();
		$this->checkInRepository = $checkInRepo;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * param integer $tourPackageId
	 * param integer $tourPackageId
	 * 
	 * @return mixed
	 */
	public function index($tourPackageId = 0, $isBundlePackage = 0)
	{
		$vars = [
			'page_title' => 'QR Check-In Tools'
		];

		if(getSubdomainFromHost() != Constants::TENANT_GCW) {
			return view('users.common.checkin.index', $vars);
		}

		if($tourPackageId == 0) {
			return $this->tourPackages();
		} else {
			$package = $this->tourPackage::select('bundle_package')->where('id', $tourPackageId)->first();
			
			if(!$package) {
				return redirect()->back()->withErrors(['message' => 'The tour package not found!!']);
			}

			if($package->bundle_package == 1) {
				return redirect()->route(request()->route()->getName())->withErrors(['message' => 'Please select valid package!!']);
			}
			
			$isBundlePackage = ($package->bundle_package == 1) ? 1 : 0;
			$vars['tourPackageId'] = $tourPackageId;
			$vars['isBundlePackage'] = $isBundlePackage;
			return view('users.common.checkin.index', $vars);
		}
	}

	/**
	 * Validate the customer booking
	 * 
	 * @param Request $request
	 * @param int $bookingId
	 * 
	 * @return JsonResponse
	 */
	public function validateBooking(Request $request, $bookingId): JsonResponse
	{
		try {
			$booking = $this->checkInRepository->getBooking($bookingId);
			return response()->json($booking, 200);
		} catch (Exception $err) {
			return response()->json(["results" => [], "expired" => "", "error" => $err->getMessage()]);
		}
	}

	/**
	 * Get the waiver info for the customer
	 * 
	 * @param Request $request
	 */
	public function waiverInfo(Request $request)
	{
		try {
			$waiver = $this->checkInRepository->waiver($request);
			return response()->json($waiver, 200);
		} catch (Exception $err) {
			return response()->json();
		}
	}

	/**
	 * Sign the waiver form for the customer
	 * 
	 * @param Request $request
	 * @param int $bookingId
	 */
	public function signWaiver(Request $request, $bookingId)
	{
		try {
			$waiver = $this->checkInRepository->signwaiver($request->signatures, $bookingId);
			$checkin = $this->checkInRepository->bulkCheckIn($request->checkins);
			return response()->json(['waiver' => $waiver, 'checkin' => $checkin, 'error' => null], 200);
		} catch (Exception $err) {
			print($err->getMessage());
			return response()->json(['waiver' => null, 'checkin' => null, 'error' => $err]);
		}
	}

	/**
	 * Bulk check in for the customers
	 * 
	 * @param Request $request
	 */
	public function bulkCheckIn(Request $request)
	{
		try {
			$reponse = $this->checkInRepository->bulkCheckIn($request->checkins);
			return response()->json(['results' => $reponse, 'error' => null], 200);
		} catch (Exception $err) {
			return response()->json(['results' => null, 'error' => $err]);
		}
	}

	/**
	 * Display list of packages that are non-bundle
	 *
	 * @return mixed
	 */
	public function tourPackages()
	{
		$vars = [
			'page_title' => 'Tour Packages | Locations'
		];

		$vars['tourPackages'] = $this->tourPackage::where('bundle_package', 0)
			->where('checkin', 1)
			->where('checkin_type', 1)
			->get();
		
		return view('users.common.checkin.packages', $vars);
	}

	/**
	 * Search booking ID from booking Group ID in Web
	 * 
	 * @param Request $request
	 */
	public function searchBookingByGroupId(Request $request)
	{
		try {
			return $this->checkInRepository->getBookingInfo($request->booking_group_id, $request->package_id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
}
