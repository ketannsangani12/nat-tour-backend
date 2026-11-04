<?php
namespace App\Http\Controllers\Common;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\BookingRequest;
use App\Http\Requests\Common\CartBookingRequest;
use App\Http\Requests\Common\CartRequest;
use App\Http\Requests\Common\ChangePeopleRequest;
use App\Http\Requests\Common\MakeCartPaymentRequest;
use App\Http\Requests\Common\SwitchPackageRequest;
use App\Http\Requests\Common\EditContactRequest;
use App\Models\PackageAffiliateAssignment;
use App\Models\Staff;
use App\Models\TourOperator;
use App\Models\TourPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Admin;
use App\Services\Booking\PaymentLinkService;
use App\Models\TourPromotion;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\API\TourBooking\BookingCheckInStatusRequest;
use App\Http\Requests\API\TourBooking\BookingTipPaymentRequest;
use App\Http\Requests\API\TourBooking\BookingSendEmailRequest;
use App\Http\Requests\API\TourBooking\CalendarRequest;
use App\Http\Requests\API\TourBooking\MakePaymentRequest;
use App\Http\Requests\API\TourBooking\BookingPackageRedeemStatusRequest;
use App\Repositories\TourBooking\TourBookingFormsRepository;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\BookingService;
use App\Services\TimeSlot\TouristService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use Stripe\Stripe;
use App\Utils\CalendarWeekFilterUtils;
use App\Utils\BokunUtils;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Services\Calendar\CalendarService;
use App\Services\Booking\PackageHasContactsService;
use App\Services\ReceiptPrint\ReceiptPrintService;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;
use App\Models\TourSlotTime;

class TourBookingController extends Controller
{
	/**
	 * @var CustomerRepository
	 */
	protected $customerRepository;

	/**
	 * @var TourSlotRepository
	 */
	protected $tourSlotRepository;

	/**
	 * @var TourBookingRepository
	 */
	protected $tourBookingRepository;

	/**
	 * @var TourBookingFormsRepository
	 */
	protected $tourBookingFormsRepository;

	/**
	 * @var TourBookingAddonRepository
	 */
	protected $tourBookingAddonRepository;

	/**
	 * @param  CustomerRepository  $customerRepository
	 * @param  TourSlotRepository  $tourSlotRepository
	 * @param  TourBookingRepository  $tourBookingRepository
	 * @param  TourBookingFormsRepository  $tourBookingFormsRepository
	 * @param  TourBookingAddonRepository  $tourBookingAddonRepository
	 */
	public function __construct(
		CustomerRepository $customerRepository,
		TourSlotRepository $tourSlotRepository,
		TourBookingRepository $tourBookingRepository,
		TourBookingFormsRepository $tourBookingFormsRepository,
		TourBookingAddonRepository $tourBookingAddonRepository
	) {
		parent::__construct();

		$this->customerRepository = $customerRepository;
		$this->tourSlotRepository = $tourSlotRepository;
		$this->tourBookingRepository = $tourBookingRepository;
		$this->tourBookingFormsRepository = $tourBookingFormsRepository;
		$this->tourBookingAddonRepository = $tourBookingAddonRepository;

		Stripe::setApiKey(getStripeSecretKey());
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function create(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->initBooking($request);

			return view('users.common.tour-booking-add', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Get tour operator affiliates
	 *
	 * @param int $id
	 */
	public function getTourOperatorAffiliates($id)
	{
		if (!$id) {
			$customer1 = array();
		} else {
			$fields1 = ['c.id', 'c.name', 'c.email',];
			$customer1 = DB::table('customers AS c')
				->where('is_affiliate', 'Yes')->where('tour_operator_id', $id)->get($fields1);
		}
		return response()->json(array('data' => $customer1));
	}

	/**
	 * Help section
	 */
	public function help()
	{
		$vars = [
			'page_title' => 'Tour Help',
		];

		return view('users.common.help', $vars);
	}

	/**
	 * Add to cart
	 *
	 * @param CartRequest $request
	 * @return mixed
	 */
	public function addToCart(CartRequest $request)
	{
		try {
			return $this->tourBookingRepository->addToCart($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Edit cart
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function editCart(Request $request)
	{
		try {
			return $this->tourBookingRepository->editCart($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Remove from cart
	 *
	 * @param $request
	 * @return mixed
	 */
	public function removeFromCart(Request $request)
	{
		try {
			return $this->tourBookingRepository->removeFromCart($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function setDefaultBookingSource(Request $request)
	{
		try {
			$request->validate([
				'booking_source' => 'required|in:phone_call,walk_in,email,other',
			]);

			$user = auth()->user();
			$user->default_booking_source = $request->booking_source;
			$user->save();

			return response()->json(['success' => true]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to save default booking source.');
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param BookingRequest $request
	 * @return mixed
	 */
	public function store(BookingRequest $request)
	{
		try {
			return $this->tourBookingRepository->createBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Book multiple packages
	 *
	 * @param CartBookingRequest $request
	 * @return mixed
	 */
	public function createComboBooking(CartBookingRequest $request)
	{
		try {
			return $this->tourBookingRepository->createComboBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Save affiliate booking
	 *
	 * @param Request $request
	 */
	public function saveAffiliateBooking(Request $request)
	{
		try {
			return $this->tourBookingRepository->saveAffiliateBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	// Add Tip code start
	/**
	 * @return mixed
	 *
	 * @param BookingTipPaymentRequest $request
	 * @throws Exception
	 */
	public function tipPayment(BookingTipPaymentRequest $request)
	{
		try {
			return $this->tourBookingRepository->tipPayment($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}


	/**
	 * @return mixed
	 *
	 * @param MakePaymentRequest $request
	 * @throws Exception
	 */
	public function paymentnewbooking(MakePaymentRequest $request)
	{
		try {
			return $this->tourBookingRepository->paymentNewBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 *
	 * @param Request $request
	 * @throws Exception
	 */
	public function editdiscount(Request $request)
	{
		try {
			return $this->tourBookingRepository->editDiscount($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 *
	 * @param MakeCartPaymentRequest $request
	 * @throws Exception
	 */
	public function paymentCartBookings(MakeCartPaymentRequest $request)
	{
		try {
			return $this->tourBookingRepository->paymentCartBookings($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Reschedule the booking which is already created
	 *
	 * @param Request $request
	 * @param int $bookingId
	 */
	public function reschedule(Request $request, int $bookingId)
	{
		try {
			return $this->tourBookingRepository->reschedule($request, $bookingId);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Calendar to show date and month wise bookings
	 *
	 * @param CalendarRequest $request
	 * @throws Exception
	 */
	public function calendar(CalendarRequest $request)
	{
		try {
			$vars = $this->tourBookingRepository->calendar($request);
			$packages = $vars['tourPackages'];
			$packageId = Session::get('package_id') ?? [];
			$packageId = implode(',', $packageId);

			return view('users.common.tour-calendar', $vars, compact('packages', 'packageId'));
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Feature to get the month wise data usnig ajax
	 *
	 * @param Request $request
	 */
	public function calendarAHR(Request $request)
	{
		$request->validate([
			'yearMonth' => 'required|integer',
			'tourOperatorId' => 'integer',
			'option' => 'required|in:month,week,day'
		]);

		$tenant = Tenant::where('id', $request->tenantId)->first();
		if (!$tenant) {
			return sendJsonErrMsg('Invalid tenant selected.');
		}

		config(['database.connections.master.database' => $tenant->tenancy_db_name]);
		DB::purge('master');
		DB::reconnect('master');

		if ($request->has('tourOperatorId')) {
			$tourOperatorId = $request->tourOperatorId;
		} else {
			$tourOperatorId = 0;
		}

		$calData = $this->calendarDateSlots($request->yearMonth, $tourOperatorId, $request->packageId, $request->option, $request->week, $request->tenantId);

		if ($calData === false) {
			return sendJsonErrMsg('Unable to get tour slots of the month.');
		}

		if (session('userType') == 'Admin') {
			$calData['tourBookings'] = null; // $this->latestBookings($tourOperatorId);

			if ($calData['tourBookings'] === false) {
				return sendJsonErrMsg('Unable to get tour bookings.');
			}
		}

		if (!empty($calData['tourBookings'])) {
			$calBookingData = (array) $calData['tourBookings'];
			foreach ($calBookingData as $key => $tourbooking) {
				if (empty($tourbooking->invoiceStatus)) {
					$calBookingData[$key]->invoiceStatus = '0';
				}

				if (isset($tourbooking->timezone) && $tourbooking->timezone != '') {
					$booking_d_t = $tourbooking->booking_d_t;
					$calBookingData[$key]->booking_d_t = Carbon::createFromFormat('Y-m-d H:i:s', $booking_d_t, config('app.timezone'))
						->setTimezone($tourbooking->timezone)
						->format('Y-m-d H:i:s');
				}
			}
		}

		session([
			'calendar_month' => $calData['month'],
			'calendar_year' => $calData['year'],
			'calendar_date' => $calData['date'],
			'calendar_week' => $calData['week'],
			'calendar_option' => $calData['option']
		]);

		return response()->json($calData);
	}

	/**
	 * Get the calendar date slots
	 *
	 * @param int $yearMonth
	 * @param int $tourOperatorId
	 * @param array $packageId
	 * @param string $option
	 * @param int $week
	 * @param int $tenantId
	 * @return mixed
	 */
	private function calendarDateSlots(int $yearMonth = 0, int $tourOperatorId = 0, $packageId = array(), $option, $week, $tenantId)
	{
		try {
			$userType = userType();
			Session::put('package_id', $packageId);

			// Skip switchCalendar logic if tenantId matches current subdomain
			$calendarAssignedPackages = [];
			if ($tenantId !== getSubdomainFromHost()) {
				$switchCalendar = DB::connection('mysql')->table('configurations')
					->where('tenant_id', $tenantId)
					->where('calendar_switch', 1)
					->first();

				if ($switchCalendar && !empty($switchCalendar->package_assignments)) {
					$calendarAssignedPackages = json_decode($switchCalendar->package_assignments, true);
				}
			}

			$monthStartDate = $yearMonth == 0 ? date('Y-m-01') : substr($yearMonth, 0, 4) . '-' . substr($yearMonth, 4, 2) . '-01';
			$arYM = explode('-', $monthStartDate);
			$weekDatesArray = [];
			$year = $arYM[0];
			$requestMonth = $arYM[1];
			$weekNumber = $week;

			// Compute date range early so affiliate subqueries can be scoped to the visible month/week
			if ($option === 'week') {
				$weekWithDatesOfMonth = CalendarWeekFilterUtils::weekOfMonth($requestMonth, $year);
				$weekDatesArray = CalendarWeekFilterUtils::getFirstToLastDateOfWeek($weekWithDatesOfMonth, $weekNumber);
				$dateRangeSql = "tsd2.date BETWEEN CAST('{$weekDatesArray['weekStartDate']}' AS DATE) AND CAST('{$weekDatesArray['weekEndDate']}' AS DATE)";
			} else {
				$dateRangeSql = "tsd2.date BETWEEN CAST('{$monthStartDate}' AS DATE) AND LAST_DAY(CAST('{$monthStartDate}' AS DATE))";
			}

			if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
				$tourOperatorId = Auth::user()->tour_operator_id;
			}

			// Affiliate join and filters
			$affiliateJoinQuery = '';
			$affiliateTouristsJoin = '';
			$staffJoinQuery = '';
			$affiliateTourists = '';
			$affiliateSeats = '';
			$showAllPackageBookings = 1;

			if ($userType == 'Affiliate') {
				$showAllPackageBookings = PackageAffiliateAssignment::on('master')
					->where('affiliate_id', Auth::id())
					->where('show_all_package_bookings', Constants::STATUS_ONE)
					->count();

				if ($showAllPackageBookings == Constants::STATUS_ZERO) {
					$affiliateJoinQuery = 'INNER JOIN package_affiliate_assignments paa ON paa.package_id = tp.id
	                    AND paa.affiliate_id = ' . Auth::id() . '
	                    LEFT JOIN tour_slot_times_affiliate tsta ON tsta.time_from = tst.time_from
	                        AND tsta.time_to = tst.time_to
	                        AND tsta.tour_package_id = tp.id
	                        AND tsta.deleted_by = 0
	                        AND tsta.status = "Active"
	                    LEFT JOIN affiliate_slot_times ast ON ast.tour_slot_time_id = tsta.id
	                        AND ast.affiliate_id = ' . Auth::id() . '
	                        AND ast.deleted_by = 0';

					$affiliateId = Auth::id();
					$affiliateTourists = 'IFNULL(afl_agg.afl_tourists, 0) AS afl_tourists,';
					$affiliateTouristsJoin = "LEFT JOIN (
	                    SELECT tst2.id AS tour_slot_id, tbm2.tour_package_id, SUM(tbm2.tourists) AS afl_tourists
	                    FROM tour_slot_dates AS tsd2
	                    INNER JOIN tour_slot_times AS tst2 ON tst2.tour_slot_id = tsd2.id
	                    INNER JOIN tour_bookings_master AS tbm2 ON tbm2.tour_slot_id = tst2.id
	                        AND tbm2.status != 'Cancelled'
	                        AND tbm2.customer_id = {$affiliateId}
	                    WHERE {$dateRangeSql}
	                    GROUP BY tst2.id, tbm2.tour_package_id
	                ) AS afl_agg ON afl_agg.tour_slot_id = tst.id AND afl_agg.tour_package_id = tp.id";

					$affiliateSeats = 'IFNULL(ast.seat_limit,0) as afl_seats,';
				} else {
					$affiliateJoinQuery = 'INNER JOIN package_affiliate_assignments paa ON paa.package_id = tp.id
	                    AND paa.affiliate_id = ' . Auth::id();
				}
			} elseif (departmentPackage()) {
				$staffJoinQuery = 'INNER JOIN department_package dp ON dp.package_id = tp.id
	                AND dp.department_id = ' . Auth::user()->department_id;
			}

			// Intersect with tenant-assigned packages
			$shouldFilterByPackage = !empty($packageId) && !in_array(0, $packageId);

			if (!empty($calendarAssignedPackages)) {
				$calendarAssignedPackages = array_map('intval', $calendarAssignedPackages);

				$packageId = $shouldFilterByPackage
					? array_intersect($packageId, $calendarAssignedPackages)
					: $calendarAssignedPackages;
			}

			$packageRelationSetting = hasSetting('PACKAGE_RELATION');

			// $packageId also scopes the SQL fetch of date-slots below. When the filtered
			// package belongs to a PACKAGE_RELATION family, the child/parent availability math
			// further down looks for a sibling row (is_parent == 1) inside $dateSlots to know
			// the group's shared capacity. If the fetch stays scoped to only the filtered
			// package, that sibling row never exists and availability always computes as 0.
			// Widen the fetch to the full family group here, then filter the displayed rows
			// back down to $requestedPackageId once $calData is built below.
			$requestedPackageId = $packageId;

			if ($packageRelationSetting && $shouldFilterByPackage && !empty($packageId)) {
				$filterIdsStr = implode(',', array_map('intval', $packageId));
				$familyPackageIds = DB::connection('master')->select("SELECT DISTINCT tour_package_id
	                    FROM tour_package_families
	                    WHERE relation_year = {$year}
	                    AND parent_package_id IN (
	                            SELECT parent_package_id
	                            FROM tour_package_families
	                            WHERE tour_package_id IN ($filterIdsStr)
	                                    AND relation_year = {$year}
	                    )");

				if (!empty($familyPackageIds)) {
					$packageId = array_values(array_unique(array_merge(
						$packageId,
						array_column($familyPackageIds, 'tour_package_id')
					)));
				}
			}

			// LEFT JOIN for group_index/is_parent — one join vs one correlated subquery per result row
			$packageFamiliesJoin = "LEFT JOIN tour_package_families AS tpf ON tpf.tour_package_id = tsd.tour_package_id
	                AND tpf.time_from = tst.time_from
	                AND tpf.relation_year = {$year}";

			$dateSlotsQuery = "
	            SELECT
	                tsd.id,
	                tsd.date,
	                tst.time_from,
	                tp.id AS tour_package_id,
	                tp.name AS pkg_name,
	                IFNULL(SUM(tbm.tourists), 0) AS tourists,
	                IFNULL(tst.time_slot_seats, 0) as seats,
	                " . $affiliateTourists . "
	                " . $affiliateSeats . "
	                tst.id AS time_slot_id,
	                COUNT(DISTINCT tst.id) AS available_slots,
	                IFNULL(tpf.group_index, 0) AS group_index,
	                IFNULL(tpf.is_parent, 0) AS is_parent
	            FROM
	                tour_slot_dates AS tsd
	                INNER JOIN tour_packages AS tp ON tp.id = tsd.tour_package_id
	                INNER JOIN tour_slot_times AS tst ON tst.tour_slot_id = tsd.id
	                LEFT JOIN tour_bookings_master AS tbm ON tbm.tour_slot_id = tst.id
	                    AND tbm.tour_package_id = tsd.tour_package_id
	                    AND tbm.status != 'Cancelled'
	                " . $affiliateJoinQuery . "
	                " . $affiliateTouristsJoin . "
	                " . $packageFamiliesJoin . "
	                " . $staffJoinQuery . "
	            WHERE ";

			if ($option != '') {
				if ($option == "month") {
					$dateSlotsQuery .= "tsd.date BETWEEN CAST('$monthStartDate' AS DATE) AND LAST_DAY(CAST('$monthStartDate' AS DATE)) ";
				} elseif ($option == "week") {
					$dateSlotsQuery .= "tsd.date BETWEEN CAST('" . $weekDatesArray['weekStartDate'] . "' AS DATE) AND CAST('" . $weekDatesArray['weekEndDate'] . "' AS DATE) ";
				}
			}

			$dateSlotsQuery .= " AND tp.tour_operator_id = " . $tourOperatorId . "
	            AND tsd.status = 'Active'
	            AND tp.status = 'Active'
	            AND tp.deleted_by = 0
	            AND tsd.deleted_by = 0
	            AND tst.deleted_by = 0";

			if (!empty($packageId)) {
				$i = implode(',', $packageId);
				$dateSlotsQuery .= " AND tp.id IN ($i) ";
			} elseif (!empty($calendarAssignedPackages)) {
				$i = implode(',', $calendarAssignedPackages);
				$dateSlotsQuery .= " AND tp.id IN ($i) ";
			}

			$dateSlotsQuery .= "
	            GROUP BY tsd.id, tsd.date, tp.id, tp.name, tst.time_slot_seats, tst.id, tpf.group_index, tpf.is_parent
	            ORDER BY tsd.date ASC, tp.Order_by ASC, tst.id ASC
	        ";

			$dateSlots = DB::connection('master')->select($dateSlotsQuery);

			// --- rest of code remains unchanged ---
			$tourPackageFamilies = [];

			if ($packageRelationSetting && count($dateSlots)) {
				$packageIds = implode(',', array_unique(array_column((array)$dateSlots, 'tour_package_id')));
				$tourPackageFamilies = DB::connection('master')->select("
	                SELECT * FROM tour_package_families
					WHERE relation_year = $year
	                AND parent_package_id IN (
	                    SELECT parent_package_id
	                    FROM tour_package_families
	                    WHERE tour_package_id IN ($packageIds)
							AND relation_year = $year
	                )
	            ");
			}

			// AFFILIATE PERFORMANCE FIX: Pre-calculate child package tourists AND parent package data
			// This lookup is keyed by: date|time_from|parent_package_id => child bookings
			$childBookingsLookup = [];
			$parentPackageDataLookup = []; // For unassigned parent packages: date|time|parent_package_id => {seats, bookings}

			if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO && count($tourPackageFamilies) > 0) {
				// Get assigned package IDs
				$assignedPackageIds = array_unique(array_column($dateSlots, 'tour_package_id'));

				// Build optimized lookups in single pass
				$parentChildMap = [];
				$packageToParentMap = [];
				foreach ($tourPackageFamilies as $family) {
					// Build parent-child map
					if (!isset($parentChildMap[$family->parent_package_id])) {
						$parentChildMap[$family->parent_package_id] = [];
					}
					$parentChildMap[$family->parent_package_id][] = $family->tour_package_id;

					// Build package-to-parent map for quick lookup
					if ($family->is_parent == 1) {
						$packageToParentMap[$family->tour_package_id] = $family->parent_package_id;
					}
				}

				// Collect all unassigned child packages and their date/time combinations in one pass
				$unassignedChildrenByParent = [];
				$dateTimesByParent = [];

				foreach ($assignedPackageIds as $assignedPackageId) {
					// Quick lookup using pre-built map
					$parentPackageId = $packageToParentMap[$assignedPackageId] ?? null;

					if ($parentPackageId && isset($parentChildMap[$parentPackageId])) {
						// Find unassigned child packages
						$unassignedChildIds = array_diff($parentChildMap[$parentPackageId], $assignedPackageIds);

						if (!empty($unassignedChildIds)) {
							$unassignedChildrenByParent[$assignedPackageId] = array_values(array_filter(array_map('intval', $unassignedChildIds)));

							// Collect date/time combinations for this parent
							if (!isset($dateTimesByParent[$assignedPackageId])) {
								$dateTimesByParent[$assignedPackageId] = [];
							}

							foreach ($dateSlots as $slot) {
								if ($slot->tour_package_id == $assignedPackageId) {
									$dateTimesByParent[$assignedPackageId][$slot->date . '|' . $slot->time_from] = true;
								}
							}
						}
					}
				}

				// Single optimized query for ALL unassigned child bookings
				if (!empty($unassignedChildrenByParent)) {
					$allUnassignedChildIds = [];
					$allDateTimeCombinations = [];

					foreach ($unassignedChildrenByParent as $parentId => $childIds) {
						$allUnassignedChildIds = array_merge($allUnassignedChildIds, $childIds);
						$allDateTimeCombinations = array_merge($allDateTimeCombinations, array_keys($dateTimesByParent[$parentId]));
					}

					$allUnassignedChildIds = array_unique($allUnassignedChildIds);
					$allDateTimeCombinations = array_unique($allDateTimeCombinations);

					if (!empty($allUnassignedChildIds) && !empty($allDateTimeCombinations)) {
						$dtDates = [];
						$dtTimes = [];
						foreach ($allDateTimeCombinations as $dateTimeKey) {
							[$dtDate, $dtTime] = explode('|', $dateTimeKey);
							$dtDates[] = $dtDate;
							$dtTimes[] = $dtTime;
						}
						$dtMinDate = min($dtDates);
						$dtMaxDate = max($dtDates);
						$dtUniqueTimes = array_values(array_unique($dtTimes));
						$dtTimePlaceholders = implode(',', array_fill(0, count($dtUniqueTimes), '?'));
						$queryParams = array_merge([$dtMinDate, $dtMaxDate], $dtUniqueTimes);

						$childBookingsResults = DB::connection('master')->select("
							SELECT 
								DATE(tst.date) as slot_date,
								tst.time_from,
								tbm.tour_package_id,
								IFNULL(SUM(tbm.tourists), 0) as total_bookings
							FROM tour_bookings_master tbm
							JOIN tour_slot_times tst ON tst.id = tbm.tour_slot_id
							WHERE tst.date BETWEEN ? AND ?
								AND tst.time_from IN ({$dtTimePlaceholders})
								AND tbm.status != 'Cancelled'
								AND tbm.tour_package_id IN (" . implode(',', $allUnassignedChildIds) . ")
							GROUP BY DATE(tst.date), tst.time_from, tbm.tour_package_id
						", $queryParams);

						// Build lookup array: date|time|parent_package_id => child bookings
						// Map child bookings back to their parent packages
						foreach ($childBookingsResults as $result) {
							$childPackageId = $result->tour_package_id;

							// Find which parent(s) this child belongs to
							foreach ($unassignedChildrenByParent as $parentId => $childIds) {
								if (in_array($childPackageId, $childIds)) {
									$lookupKey = $result->slot_date . '|' . $result->time_from . '|' . $parentId;
									if (!isset($childBookingsLookup[$lookupKey])) {
										$childBookingsLookup[$lookupKey] = 0;
									}
									$childBookingsLookup[$lookupKey] += (int)$result->total_bookings;
								}
							}
						}
					}
				}

				// PERFORMANCE FIX: Pre-load parent package data for child packages assigned to affiliate
				// Find child packages assigned to affiliate that need parent data
				$childPackagesNeedingParentData = [];
				foreach ($assignedPackageIds as $assignedPackageId) {
					$isChildPackage = false;
					$parentPackageId = null;

					foreach ($tourPackageFamilies as $family) {
						if ($family->tour_package_id == $assignedPackageId && $family->is_parent == 0) {
							$isChildPackage = true;
							$parentPackageId = $family->parent_package_id;
							break;
						}
					}

					if ($isChildPackage && $parentPackageId && !in_array($parentPackageId, $assignedPackageIds)) {
						// This is a child package assigned to affiliate, but parent is not assigned
						$childPackagesNeedingParentData[$assignedPackageId] = $parentPackageId;
					}
				}

				// Batch query for all unassigned parent package data
				if (!empty($childPackagesNeedingParentData)) {
					$uniqueParentIds = array_unique(array_values($childPackagesNeedingParentData));
					$allDateTimeCombinations = [];

					// Collect all date/time combinations from assigned child packages
					foreach ($dateSlots as $slot) {
						if (array_key_exists($slot->tour_package_id, $childPackagesNeedingParentData)) {
							$allDateTimeCombinations[$slot->date . '|' . $slot->time_from] = true;
						}
					}
					$allDateTimeCombinations = array_keys($allDateTimeCombinations);

					if (!empty($uniqueParentIds) && !empty($allDateTimeCombinations)) {
						$ptDates = [];
						$ptTimes = [];
						foreach ($allDateTimeCombinations as $dateTimeKey) {
							[$ptDate, $ptTime] = explode('|', $dateTimeKey);
							$ptDates[] = $ptDate;
							$ptTimes[] = $ptTime;
						}
						$ptMinDate = min($ptDates);
						$ptMaxDate = max($ptDates);
						$ptUniqueTimes = array_values(array_unique($ptTimes));
						$ptTimePlaceholders = implode(',', array_fill(0, count($ptUniqueTimes), '?'));
						$queryParams = array_merge([$ptMinDate, $ptMaxDate], $ptUniqueTimes, $uniqueParentIds);

						$parentPackageResults = DB::connection('master')->select("
							SELECT 
								tsd.tour_package_id,
								tsd.date,
								tst.time_from,
								IFNULL(tst.time_slot_seats, 0) as seats,
								COUNT(DISTINCT tst.id) AS available_slots,
								IFNULL(SUM(tbm.tourists), 0) AS parent_bookings
							FROM tour_slot_dates AS tsd
							INNER JOIN tour_slot_times AS tst ON tst.tour_slot_id = tsd.id
							LEFT JOIN tour_bookings_master AS tbm ON tbm.tour_slot_id = tst.id
								AND tbm.tour_package_id = tsd.tour_package_id
								AND tbm.status != 'Cancelled'
							WHERE tsd.date BETWEEN ? AND ?
								AND tst.time_from IN ({$ptTimePlaceholders})
								AND tsd.tour_package_id IN (" . implode(',', array_fill(0, count($uniqueParentIds), '?')) . ")
								AND tsd.status = 'Active'
								AND tsd.deleted_by = 0
								AND tst.deleted_by = 0
							GROUP BY tsd.tour_package_id, tsd.date, tst.time_from
						", $queryParams);

						// Build lookup: date|time|parent_package_id => {seats, bookings}
						foreach ($parentPackageResults as $result) {
							$lookupKey = $result->date . '|' . $result->time_from . '|' . $result->tour_package_id;
							$parentPackageDataLookup[$lookupKey] = [
								'total_seats' => $result->seats * $result->available_slots,
								'total_bookings' => (int)$result->parent_bookings
							];
						}
					}
				}
			}

			// Pre-load all package assignments for this affiliate to avoid N+1 inside the foreach
			$affiliateAssignedPackageIds = [];
			if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
				$affiliateAssignedPackageIds = array_flip(
					PackageAffiliateAssignment::on('master')
						->where('affiliate_id', Auth::id())
						->pluck('package_id')
						->toArray()
				);
			}

			$calData = [];
			foreach ($dateSlots as $dateSlot) {
				$relationPackage = [];
				$targetParent = [];
				$totalTourists = 0;

				if ($packageRelationSetting && count($tourPackageFamilies)) {
					$relationPackage = array_filter(
						$tourPackageFamilies,
						fn($obj) =>
						$obj->tour_package_id == $dateSlot->tour_package_id && $obj->group_index == $dateSlot->group_index
					);

					$targetParent = array_filter(
						$tourPackageFamilies,
						fn($obj) =>
						$obj->is_parent == 1 && $obj->tour_package_id == $dateSlot->tour_package_id && $obj->group_index == $dateSlot->group_index
					);

					// Calculate total tourists from actual bookings (optimized - same as V3 repository)
					$totalTourists = 0;
					if (!empty($relationPackage)) {
						$groupKey = $dateSlot->group_index;

						// Scope to the same parent_package_id so independent family groups
						// that share the same group_index ordinal don't cross-contaminate.
						$firstRelation = reset($relationPackage);
						$parentId = $firstRelation->parent_package_id;

						$groupPackageIds = array_column(
							array_filter($tourPackageFamilies, fn($f) => $f->group_index == $groupKey && $f->parent_package_id == $parentId),
							'tour_package_id'
						);

						$totalTourists = array_sum(
							array_column(
								array_filter(
									$dateSlots,
									fn($s) =>
									$s->date == $dateSlot->date &&
										$s->time_from == $dateSlot->time_from &&
										$s->group_index == $groupKey &&
										in_array($s->tour_package_id, $groupPackageIds)
								),
								'tourists'
							)
						);
					}

					// Fallback to original service method if no bookings found
					if ($totalTourists === 0) {
						$totalTourists = TouristService::getTotalTouristsForSlots($tourPackageFamilies, $dateSlots, $dateSlot);
					}
				}

				if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
					$tourists = (int)$dateSlot->afl_tourists;
					$affiliateSeats = $dateSlot->afl_seats;

					// For affiliate calendar: calculate proper availability accounting for parent-child relationships
					if ($affiliateSeats > 0) {
						// Check if this is a parent package (not a child)
						$isParentPackage = !empty($relationPackage) && count($targetParent) > 0;
						$isChildPackage = !empty($relationPackage) && count($targetParent) == 0;

						$globalBookings = (int)$dateSlot->tourists;

						if ($packageRelationSetting && count($tourPackageFamilies)) {
							if ($isParentPackage) {
								// PARENT package assigned to affiliate: include child bookings to prevent overbooking
								$childBookings = 0;

								// First, add child bookings from assigned packages (in $dateSlots)
								foreach ($dateSlots as $relatedSlot) {
									if (
										$relatedSlot->date == $dateSlot->date &&
										$relatedSlot->time_from == $dateSlot->time_from &&
										$relatedSlot->group_index == $dateSlot->group_index &&
										$relatedSlot->tour_package_id != $dateSlot->tour_package_id
									) {
										$childBookings += (int)$relatedSlot->tourists;
									}
								}

								// Then, add child bookings from unassigned child packages (from lookup)
								$lookupKey = $dateSlot->date . '|' . $dateSlot->time_from . '|' . $dateSlot->tour_package_id;
								$unassignedChildBookings = $childBookingsLookup[$lookupKey] ?? 0;
								$childBookings += $unassignedChildBookings;

								$globalBookings += $childBookings;
							} elseif ($isChildPackage) {
								// CHILD package assigned to affiliate: need to check parent capacity and sibling bookings
								// Find parent package capacity and total bookings (parent + all siblings)
								$parentTotalSeats = 0;
								$parentTotalBookings = 0;

								// Get all packages in this group (parent + all children), scoped to
								// the same parent_package_id to avoid cross-family contamination.
								$firstRelationForChild = reset($relationPackage);
								$parentIdForChild = $firstRelationForChild->parent_package_id;
								$groupPackageIds = array_column(
									array_filter($tourPackageFamilies, fn($f) => $f->group_index == $dateSlot->group_index && $f->parent_package_id == $parentIdForChild),
									'tour_package_id'
								);

								// Calculate total bookings from ALL packages in the group (parent + all children)
								foreach ($dateSlots as $relatedSlot) {
									if (
										$relatedSlot->date == $dateSlot->date &&
										$relatedSlot->time_from == $dateSlot->time_from &&
										$relatedSlot->group_index == $dateSlot->group_index &&
										in_array($relatedSlot->tour_package_id, $groupPackageIds)
									) {

										$parentTotalBookings += (int)$relatedSlot->tourists;

										// Find parent package seats (assuming parent has the main capacity)
										if ($relatedSlot->is_parent == 1) {
											$parentTotalSeats = $relatedSlot->seats * $relatedSlot->available_slots;
										}
									}
								}

								// If no parent found in dateSlots, use pre-loaded parent data
								if ($parentTotalSeats == 0) {
									// Find parent package ID for this child
									$parentPackageId = null;
									foreach ($tourPackageFamilies as $family) {
										if ($family->tour_package_id == $dateSlot->tour_package_id) {
											$parentPackageId = $family->parent_package_id;
											break;
										}
									}

									if ($parentPackageId) {
										// Use pre-loaded parent package data (NO QUERY IN LOOP!)
										$parentLookupKey = $dateSlot->date . '|' . $dateSlot->time_from . '|' . $parentPackageId;
										$parentData = $parentPackageDataLookup[$parentLookupKey] ?? null;

										if ($parentData) {
											$parentTotalSeats = $parentData['total_seats'];
											$parentTotalBookings += $parentData['total_bookings'];
										}
									}
								}

								// For child packages: availability is constrained by parent's remaining capacity
								if ($parentTotalSeats > 0) {
									$parentRemainingCapacity = max(0, $parentTotalSeats - $parentTotalBookings);
									$childOwnAvailability = max(0, $affiliateSeats - $tourists);
									$availableSeats = min($childOwnAvailability, $parentRemainingCapacity);

									// Don't modify globalBookings for child packages - use child's own capacity
									$globalRemaining = max(0, $dateSlot->seats - $globalBookings);
									$availableSeats = min($availableSeats, $globalRemaining);
								} else {
									// No parent capacity found - use child's own capacity only
									$globalRemaining = max(0, $dateSlot->seats - $globalBookings);
									$affiliateRemaining = max(0, $affiliateSeats - $tourists);
									$availableSeats = min($globalRemaining, $affiliateRemaining);
								}

								$overBooking = max(0, $tourists - $affiliateSeats);
								$bookedSeats = $tourists;
								$seats = $affiliateSeats;
							} else {
								// No relation package - use standard logic
								$globalRemaining = max(0, $dateSlot->seats - $globalBookings);
								$affiliateRemaining = max(0, $affiliateSeats - $tourists);
								$availableSeats = min($globalRemaining, $affiliateRemaining);

								$overBooking = max(0, $tourists - $affiliateSeats);
								$bookedSeats = $tourists;
								$seats = $affiliateSeats;
							}
						} else {
							// No package relations - use standard logic
							$globalRemaining = max(0, $dateSlot->seats - $globalBookings);
							$affiliateRemaining = max(0, $affiliateSeats - $tourists);
							$availableSeats = min($globalRemaining, $affiliateRemaining);

							$overBooking = max(0, $tourists - $affiliateSeats);
							$bookedSeats = $tourists;
							$seats = $affiliateSeats;
						}

						// For parent packages, we already calculated availableSeats above
						if ($isParentPackage) {
							$globalRemaining = max(0, $dateSlot->seats - $globalBookings);
							$affiliateRemaining = max(0, $affiliateSeats - $tourists);
							$availableSeats = min($globalRemaining, $affiliateRemaining);

							$overBooking = max(0, $tourists - $affiliateSeats);
							$bookedSeats = $tourists;
							$seats = $affiliateSeats;
						}
					} else {
						// No affiliate seat allocation - cannot book
						$tourists = 0;
						$overBooking = 0;
						$availableSeats = 0;
						$bookedSeats = 0;
						$seats = 0;
					}
				} else {
					// Apply parent-child logic: parent shows total, child shows only child bookings (same as V3 repository)
					if (!empty($relationPackage)) {
						$isParentPackage = count($targetParent) > 0;

						if ($isParentPackage) {
							// Parent package: show total tourists (parent + child)
							$tourists = ($totalTourists > 0) ? (int) $totalTourists : (int) $dateSlot->tourists;
							$bookedSeats = $tourists;
							$seats = $dateSlot->seats * $dateSlot->available_slots;
							$overBooking = max(0, $tourists - $seats);
							$availableSeats = max(0, $seats - $tourists);
						} else {
							// Child package: show only child bookings but availability constrained by parent
							$tourists = (int) $dateSlot->tourists;
							$bookedSeats = $tourists;
							$childSeats = $dateSlot->seats * $dateSlot->available_slots;

							// For child packages, available seats should be constrained by parent's remaining capacity
							// Find parent package seats and calculate remaining capacity
							$parentTotalSeats = 0;
							foreach ($dateSlots as $relatedSlot) {
								if (
									$relatedSlot->date == $dateSlot->date &&
									$relatedSlot->time_from == $dateSlot->time_from &&
									$relatedSlot->group_index == $dateSlot->group_index &&
									$relatedSlot->is_parent == 1
								) {
									$parentTotalSeats += $relatedSlot->seats * $relatedSlot->available_slots;
								}
							}

							// Child availability is constrained by both child capacity and parent's remaining capacity
							$childOwnAvailability = max(0, $childSeats - $tourists);
							$parentRemainingCapacity = max(0, $parentTotalSeats - ($totalTourists > 0 ? $totalTourists : 0));
							$availableSeats = min($childOwnAvailability, $parentRemainingCapacity);
							$seats = $childSeats;

							// Child overbooking is based on child's own capacity
							$overBooking = max(0, $tourists - $childSeats);
						}
					} else {
						// Non-related packages: use original logic
						$tourists = (int) $dateSlot->tourists;
						$bookedSeats = $tourists;
						$seats = $dateSlot->seats * $dateSlot->available_slots;
						$overBooking = max(0, $tourists - $seats);
						$availableSeats = max(0, $seats - $tourists);
					}
				}

				// Group by date and package, with special handling for affiliate parent-child relationships
				$calKey = $dateSlot->date . '_' . $dateSlot->tour_package_id;

				// For affiliates: aggregate child bookings to parent ONLY if affiliate is NOT assigned to child package directly
				if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO && !empty($relationPackage) && count($targetParent) == 0) {
					// Use pre-loaded lookup (avoids N+1 — one query before the loop)
					$childPackageAssignment = isset($affiliateAssignedPackageIds[$dateSlot->tour_package_id]);

					// Only aggregate to parent if affiliate is NOT assigned to child package
					if (!$childPackageAssignment) {
						// Find parent package ID for this child package
						foreach ($dateSlots as $relatedSlot) {
							if (
								$relatedSlot->date == $dateSlot->date &&
								$relatedSlot->time_from == $dateSlot->time_from &&
								$relatedSlot->group_index == $dateSlot->group_index &&
								$relatedSlot->is_parent == 1
							) {
								$calKey = $dateSlot->date . '_' . $relatedSlot->tour_package_id;
								break;
							}
						}
					}
					// If affiliate IS assigned to child package, keep original calKey (show child package separately)
				}

				if (!isset($calData[$calKey])) {
					$calData[$calKey] = [
						'id' => $dateSlot->id,
						'date' => $dateSlot->date,
						'pkg_id' => $dateSlot->tour_package_id,
						'pkg_name' => $dateSlot->pkg_name,
						'tourists' => 0,
						'seats' => 0,
						'over_booking' => 0,
						'available_seats' => 0,
						'booked_seats' => 0,
					];
				}

				$calData[$calKey]['tourists'] += $tourists;
				$calData[$calKey]['seats'] += $seats;
				$calData[$calKey]['over_booking'] += $overBooking;
				$calData[$calKey]['available_seats'] += $availableSeats;
				$calData[$calKey]['booked_seats'] += $bookedSeats ?? 0;
			}

			// For affiliate calendar, recalculate available seats properly after aggregation
			// if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
			// 	foreach ($calData as $key => &$dateData) {
			// 		$dateOnly = $dateData['date'];
			// 		$packageId = $dateData['pkg_id'];

			// 		// For affiliates, calculate availability based on affiliate seat limits per slot
			// 		$affiliateAvailableSeats = 0;

			// 		// Get slots for this specific date and package
			// 		$slotsForDate = array_filter($dateSlots, function($slot) use ($dateOnly, $packageId) {
			// 			return $slot->date == $dateOnly && $slot->tour_package_id == $packageId;
			// 		});

			// 		// For affiliates assigned only to parent package, calculate availability per slot then sum
			// 		foreach ($slotsForDate as $slot) {
			// 			if (isset($slot->afl_seats) && $slot->afl_seats > 0) {
			// 				// Use the total tourists (parent + child) vs slot capacity
			// 				$globalRemaining = max(0, $slot->seats - $slot->tourists);
			// 				$affiliateRemaining = max(0, $slot->afl_seats - $slot->afl_tourists);
			// 				$slotAvailable = min($globalRemaining, $affiliateRemaining);
			// 				$affiliateAvailableSeats += $slotAvailable;
			// 			}
			// 		}

			// 		$dateData['available_seats'] = $affiliateAvailableSeats;
			// 	}
			// }

			$tourSlots = array_values($calData);

			// Drop the sibling/parent rows that were only fetched above for capacity math —
			// the caller filtered the calendar to $requestedPackageId and shouldn't see extras.
			if ($shouldFilterByPackage) {
				$requestedPackageIdInts = array_map('intval', $requestedPackageId);
				$tourSlots = array_values(array_filter(
					$tourSlots,
					fn($slot) => in_array((int) $slot['pkg_id'], $requestedPackageIdInts)
				));
			}

			return [
				'tourSlots' => $tourSlots,
				'year' => $arYM[0],
				'month' => $arYM[1],
				'date' => $monthStartDate,
				'week' => $weekNumber,
				'option' => $option,
				'weekDates' => ($option == 'week') ? $weekDatesArray['weekDates'] : $weekDatesArray
			];
		} catch (Exception $e) {
			Log::error($e);
			return false;
		}
	}

	/**
	 * This function is also called from search() function in this controller
	 * Provide the latest 10 bookings based on search or reload
	 *
	 * @param mixed $tourOperatorId
	 * @param mixed $search
	 */
	private function latestBookings($tourOperatorId, $search = '')
	{
		try {
			$search = trim($search !== '' ? str_ireplace("\u{2019}", "'", $search) : '');

			// Step 1: fetch 10 IDs cheaply via index-friendly query.
			// tp join is excluded — it prevents MySQL from using tbm.customer_id / tbm.created_at
			// indexes, causing a full 300k+ row scan even when the affiliate customer_id filter
			// is highly selective. tp.status and tour_operator_id are enforced in Step 2.
			$step1 = DB::table('tour_bookings_master AS tbm');

			if (session('userType') == 'Affiliate') {
				$assignedPackageIds = PackageAffiliateAssignment::where('affiliate_id', Auth::id())
					->where('show_all_package_bookings', Constants::STATUS_ONE)
					->pluck('package_id')
					->toArray();

				if (count($assignedPackageIds)) {
					$step1->whereIn('tbm.tour_package_id', $assignedPackageIds);
				} else {
					$step1->where('tbm.customer_id', Auth::id());
				}
			}

			if ($search !== '') {
				$identifyText = identifyText($search);
				// LIKE '%x%' cannot use B-tree indexes; a 1-2 char general-text search
				// scans 500k+ rows for minimal benefit. Email/booking/phone use equality
				// or prefix LIKE so they are exempt from this minimum-length gate.
				if (!in_array($identifyText, ['email', 'booking', 'phone']) && mb_strlen($search) < 3) {
					return collect();
				}

				$step1->where(function ($q) use ($search, $identifyText) {
					if ($identifyText == 'email') {
						$q->where('tbm.email', $search);
					} elseif ($identifyText == 'booking') {
						$q->where('tbm.id', 'LIKE', bookingPrefix("{$search}%", true));
						// Only search OTA references when the input contains non-digit characters.
						// Pure-number inputs (e.g. "13835") are booking IDs; OTA references
						// always carry a letter prefix (TRIP-xxx, GYG-xxx). A fulltext search
						// for "13835*" matches TRIP-1383XXXXXX tokens and crowds out the real booking.
						if (!ctype_digit($search)) {
							$ftOtaSearch = trim(preg_replace('/[+\-><()~*"@]+/', '', $search));
							if (mb_strlen($ftOtaSearch) >= 3) {
								$q->orWhereRaw('MATCH(tbm.ota_reference_booking) AGAINST (? IN BOOLEAN MODE)', [$ftOtaSearch . '*']);
							} else {
								$q->orWhere('tbm.ota_reference_booking', 'LIKE', "%{$search}%");
							}
						}
					} elseif ($identifyText == 'phone') {
						$normalizedSearchTerm = normalizedSearchTerm($search);
						if ($normalizedSearchTerm !== '') {
							$q->whereRaw("REGEXP_REPLACE(tbm.phone_number, '[^0-9]', '') LIKE ?", ["%{$normalizedSearchTerm}%"])
							 	->orWhereRaw("tbm.phone_number LIKE ?", ["%{$search}%"]);
						}
						// Also match OTA references containing this number (e.g. "1383591429" inside "TRIP-1383591429").
						$q->orWhere('tbm.ota_reference_booking', 'LIKE', "%{$search}%");
					} else {
						$ftSearch = trim(preg_replace('/[+\-><()~*"@]+/', '', $search));
						if ($ftSearch !== '') {
							$isMultiWord = str_contains($ftSearch, ' ');

							if ($isMultiWord) {
								// Multi-word (e.g. "natalie gold"): require ALL words as prefixes using AND (+word*).
								// "+natalie* +gold*" finds "Natalie Goldberg" (gold* matches Goldberg)
								// but NOT "Natalie Guillory" (Guillory doesn't start with "gold").
								$words = preg_split('/\s+/', $ftSearch, -1, PREG_SPLIT_NO_EMPTY);
								$andPrefixSearch = implode(' ', array_map(fn($w) => '+' . $w . '*', $words));
								if ($andPrefixSearch !== '') {
									$q->whereRaw('MATCH(tbm.name, tbm.email, tbm.note, tbm.comments) AGAINST (? IN BOOLEAN MODE)', [$andPrefixSearch]);
								}
								$q->orWhereRaw('LOWER(tbm.name) = LOWER(?)', [$search]);
							} else {
								// Single word: keep the original prefix search behaviour.
								$q->whereRaw('MATCH(tbm.name, tbm.email, tbm.note, tbm.comments) AGAINST (? IN BOOLEAN MODE)', [$ftSearch . '*'])
									->orWhereRaw('LOWER(tbm.name) = LOWER(?)', [$search])
									->orWhere('tbm.viator_bookingRef', $search)
									->orWhere('tbm.get_your_guide_reference_booking', $search)
									->orWhere('tbm.ota_reference_booking', $search);
								if (mb_strlen($ftSearch) >= 3) {
									$q->orWhereRaw('MATCH(tbm.ota_reference_booking) AGAINST (? IN BOOLEAN MODE)', [$ftSearch . '*']);
								} else {
									$q->orWhere('tbm.ota_reference_booking', 'LIKE', "%{$search}%");
								}
							}
						} else {
							$q->where('tbm.viator_bookingRef', $search)
								->orWhere('tbm.get_your_guide_reference_booking', $search);
						}
					}
				});
			}

			$idRows = $step1->orderByDesc('tbm.created_at')
				->take(10)
				->get(['tbm.id', 'tbm.cart_id']);

			if ($idRows->isEmpty()) {
				return collect();
			}

			$ids    = $idRows->pluck('id')->toArray();
			$idsStr = implode(',', $ids);

			$cartIdValues = $idRows->pluck('cart_id')->filter()->unique()->values()->toArray();
			$cartIdFilter = !empty($cartIdValues)
				? "WHERE cart_id IN ('" . implode("','", array_map('addslashes', $cartIdValues)) . "')"
				: 'WHERE 1=0';

			// Step 2: full detail query scoped entirely to $ids
			$sql = "
				SELECT
					tbm.id,
					tbm.cart_id,
					tbm.viator_bookingRef AS tripadvisor_id,
					tbm.get_your_guide_reference_booking AS get_your_guide_booking_id,
					tbm.ota_reference_booking AS ota_booking_id,
					tst.date,
					tbm.name AS cust_name,
					c.is_affiliate,
					tp.name AS pkg_name,
					tbm.email AS cust_email,
					tbm.total,
					tbm.created_at AS booking_d_t,
					tbm.is_group_booking,
					tbm.tourists,
					tbm.infants,
					t_op.timezone,
					tbm.status,
					tbm.is_reserved_for_affiliate,
					tbm.reference_booking,
					grouped.cart_booking_count AS cartBookingsCount,
					tp.package_has_slots,
					tp.package_has_waiver,
					tp.package_has_permit,
					IF(ib.invoice_id IS NOT NULL, i.status, 0) AS invoiceStatus,
					trans_paid.paid,
					trans_method.payment_method,
					d.id AS dispute_id,
					d.stripe_dispute_id,
					d.status AS dispute_status,
					d.reason AS dispute_reason,
					d.amount AS dispute_amount,
					d.currency AS dispute_currency,
					d.evidence_due_by AS dispute_evidence_due_by,
					active_disputes.dispute_count,
					CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t,
					GROUP_CONCAT(
						CONCAT(
							LEFT(tpr.rate_for, 1), ':',
							CASE WHEN tbt.infants > 0 THEN tbt.infants ELSE tbt.tourists END
						)
						ORDER BY tpr.age_from DESC, tpr.age_to DESC
						SEPARATOR ' | '
					) AS t_groups,
					CASE WHEN tbm.viator_bookingRef IS NOT NULL THEN 'Yes' ELSE 'No' END AS viator_bookingRef,
					CASE WHEN tbm.get_your_guide_reference_booking IS NOT NULL THEN 'Yes' ELSE 'No' END AS get_your_guide_reference_booking,
					CASE WHEN tbm.ota_reference_booking IS NOT NULL THEN 'Yes' ELSE 'No' END AS ota_reference_booking
				FROM tour_bookings_master AS tbm
				INNER JOIN tour_bookings_transact AS tbt ON tbt.tour_booking_id = tbm.id AND tbt.deleted_by = 0
				INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id
				INNER JOIN customers AS c ON c.id = tbm.customer_id
				INNER JOIN tour_packages AS tp ON tp.id = tbm.tour_package_id
				INNER JOIN tour_operators AS t_op ON t_op.id = tp.tour_operator_id
				INNER JOIN tour_slot_times AS tst ON tst.id = tbm.tour_slot_id
				INNER JOIN tour_slot_dates AS tsd ON tsd.id = tst.tour_slot_id
				LEFT JOIN (
					SELECT cart_id, COUNT(*) AS cart_booking_count
					FROM tour_bookings_master
					{$cartIdFilter}
					GROUP BY cart_id
				) AS grouped ON grouped.cart_id = tbm.cart_id
				LEFT JOIN (
					SELECT tour_booking_id, SUM(amount) AS paid
					FROM transactions
					WHERE tour_booking_id IN ({$idsStr}) AND status = 'Completed'
					GROUP BY tour_booking_id
				) AS trans_paid ON trans_paid.tour_booking_id = tbm.id
				LEFT JOIN (
					SELECT tour_booking_id, MAX(via) AS payment_method
					FROM transactions
					WHERE tour_booking_id IN ({$idsStr}) AND status = 'Completed'
					GROUP BY tour_booking_id
				) AS trans_method ON trans_method.tour_booking_id = tbm.id
				LEFT JOIN invoice_bookings AS ib ON ib.tour_booking_id = tbm.id
				LEFT JOIN invoices AS i ON i.id = ib.invoice_id
				LEFT JOIN (
					SELECT booking_id,
					       MAX(id) AS latest_dispute_id,
					       COUNT(*) AS dispute_count
					FROM disputes
					WHERE status IN ('warning_needs_response', 'under_review')
					GROUP BY booking_id
				) AS active_disputes ON active_disputes.booking_id = tbm.id
				LEFT JOIN disputes AS d ON d.id = active_disputes.latest_dispute_id
				WHERE tbm.id IN ({$idsStr})
				GROUP BY tbm.id
				ORDER BY tbm.created_at DESC
			";

			$bookings = collect(DB::select($sql));

			if ($bookings->isEmpty()) {
				return collect();
			}

			// Batch-load all history for these bookings (eliminates N+1)
			$allHistory = TourBookingHistory::whereIn('tour_booking_id', $ids)
				->get(['id', 'tour_booking_id', 'details', 'operation'])
				->groupBy('tour_booking_id');

			$bookingPrefix = bookingPrefixSetting();
			foreach ($bookings as $key => $booking) {
				$booking->background_colour_class = BookingService::backgroundColourClass($booking);
				$booking->prefixedId = ($bookingPrefix != '') ? $bookingPrefix . $booking->id : $booking->id;

				$booking->has_active_dispute = !is_null($booking->dispute_id);
				$booking->dispute_info = null;

				if ($booking->has_active_dispute) {
					$booking->dispute_info = [
						'id' => $booking->stripe_dispute_id,
						'status' => $booking->dispute_status,
						'reason' => $booking->dispute_reason,
						'amount' => $booking->dispute_amount,
						'currency' => $booking->dispute_currency,
						'evidence_due_by' => $booking->dispute_evidence_due_by,
						'dispute_count' => $booking->dispute_count ?? 1,
						'status_label' => $this->getDisputeStatusLabel($booking->dispute_status),
						'reason_label' => $this->getDisputeReasonLabel($booking->dispute_reason),
						'css_class' => $this->getDisputeStatusCssClass($booking->dispute_status),
						'requires_attention' => $this->disputeRequiresAttention($booking->dispute_status, $booking->dispute_evidence_due_by),
						'days_until_deadline' => $this->getDaysUntilDeadline($booking->dispute_evidence_due_by),
					];
				}

				$history = $allHistory->get($booking->id, collect());

				if ($booking->is_group_booking == '1') {
					$tGroupsChanged = ['P:' . $booking->tourists];
				} else {
					$tGroupsChanged = CalendarService::touristGroups($booking->t_groups);
				}
				$bookings[$key]->t_groups    = isset($tGroupsChanged) ? implode(' |', $tGroupsChanged) : '';
				$bookings[$key]->BookingTotal = $booking->total;
				$bookings[$key]->RefundAmount = 0;

				foreach ($history as $historyData) {
					$details = json_decode($historyData->details);

					if ($historyData->operation == 'Update') {
						$bookings[$key]->BookingTotal = $booking->total;
					}

					if ($historyData->operation == 'Payment') {
						$bookings[$key]->BookingTotal = round((float) $details->amount, 2);
					}

					if ($historyData->operation == 'Refund' && $booking->id == $historyData->tour_booking_id) {
						$bookings[$key]->RefundAmount += (float) $details->amount;
					}

					if ($historyData->operation == 'Cancelled' && isset($details->refund_type) && $details->refund_type != "Don't Refund") {
						$bookings[$key]->RefundAmount += $details->amount;
					}
				}

				$bookings[$key]->RefundAmount = round($bookings[$key]->RefundAmount, 2);
				$bookings[$key]->totalF       = round($bookings[$key]->total);
				$bookings[$key]->PaymentTotal = max(0, $bookings[$key]->BookingTotal - $bookings[$key]->RefundAmount);
				$bookings[$key]->ota_title    = strtolower(BokunUtils::getOTAChannelNameByBookingId($bookings[$key]->id));
			}

			return $bookings;
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	/**
	 * Get human-readable dispute status label.
	 */
	private function getDisputeStatusLabel(?string $status): string
	{
		if (!$status) return '';
		
		return match($status) {
			'warning_needs_response' => 'Needs Response',
			'under_review' => 'Under Review',
			'charge_refunded' => 'Charge Refunded',
			'lost' => 'Lost',
			'won' => 'Won',
			default => ucfirst(str_replace('_', ' ', $status))
		};
	}

	/**
	 * Get human-readable dispute reason label.
	 */
	private function getDisputeReasonLabel(?string $reason): string
	{
		if (!$reason) return '';
		
		return match($reason) {
			'fraudulent' => 'Fraudulent',
			'subscription_canceled' => 'Subscription Canceled',
			'product_unacceptable' => 'Product Unacceptable',
			'credit_not_processed' => 'Credit Not Processed',
			'duplicate' => 'Duplicate',
			'product_not_received' => 'Product Not Received',
			'unrecognized' => 'Unrecognized',
			'general' => 'General',
			default => ucfirst(str_replace('_', ' ', $reason))
		};
	}

	/**
	 * Get CSS class for dispute status styling.
	 */
	private function getDisputeStatusCssClass(?string $status): string
	{
		if (!$status) return '';
		
		return match($status) {
			'warning_needs_response' => 'bg-red-100 text-red-800 border-red-200',
			'under_review' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
			'charge_refunded' => 'bg-blue-100 text-blue-800 border-blue-200',
			'lost' => 'bg-red-100 text-red-800 border-red-200',
			'won' => 'bg-green-100 text-green-800 border-green-200',
			default => 'bg-gray-100 text-gray-800 border-gray-200'
		};
	}

	/**
	 * Check if dispute requires immediate attention.
	 */
	private function disputeRequiresAttention(?string $status, ?string $evidenceDueBy): bool
	{
		if ($status !== 'warning_needs_response' || !$evidenceDueBy) {
			return false;
		}

		$daysUntilDeadline = $this->getDaysUntilDeadline($evidenceDueBy);
		return $daysUntilDeadline !== null && $daysUntilDeadline <= 3;
	}

	/**
	 * Get days until evidence deadline.
	 */
	private function getDaysUntilDeadline(?string $evidenceDueBy): ?int
	{
		if (!$evidenceDueBy) {
			return null;
		}
		
		try {
			$deadline = Carbon::parse($evidenceDueBy);
			return now()->diffInDays($deadline, false);
		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * Search affiliate for the booking
	 *
	 * @param int $affiliateId
	 */
	public function searchAffiliate(int $affiliateId)
	{
		try {
			session()->put('affiliateId', $affiliateId);

			$customers = DB::table('customers')
				->where('is_affiliate', 'Yes')
				->where('status', 'Active')
				->where('deleted_by', 0)
				->where('id', $affiliateId)
				->distinct()
				->select(['name', 'email', 'phone_number']);

			if (session('userType') == 'Tour Operator') {
				$customers = $customers->where('tour_operator_id', Auth::user()->tour_operator_id);
			}

			return response()->json($customers->get());
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to search for customer.');
		}
	}

	/**
	 * @return mixed

	 * @param int $date
	 * @param string $tourPackagesId
	 * @param int $PaymentStatus
	 * @param string $slotId
	 * @throws Exception
	 */
	public function dayTimeSlots(Request $request, int $date = 0, string $tourPackagesId = '', int $PaymentStatus = 0, $slotId = '')
	{
		try {
			$vars = $this->tourBookingRepository->dayTimeSlots($request, $date, $tourPackagesId, $PaymentStatus, $slotId);

			if (isset($request->selectedCalendar)) {
				return $vars;
			}
			return view('users.common.tour-bookings-day-time-slots', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Time slot bookings info
	 *
	 * @param int $timeSlotId
	 */
	public function timeSlotBookingsInfo($timeSlotId)
	{
		try {
			return $this->tourSlotRepository->timeSlotBookingsInfo($timeSlotId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Time slot tourist information
	 *
	 * @param int $timeSlotId
	 */
	public function timeSlotTouristsInfo($timeSlotId)
	{
		try {
			return $this->tourSlotRepository->timeSlotTouristsInfo($timeSlotId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Display the specified resource. also called from printInfo function
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function show(int $id, $forPrinting = false)
	{
		try {
			$vars = $this->tourBookingRepository->booking($id, $forPrinting);

			return view('users.common.tour-booking-info', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Print the specified resource
	 *
	 * @param int $bookingId
	 * @return mixed
	 */
	public function printInfo($bookingId)
	{
		try {
			$vars = $this->tourBookingRepository->booking($bookingId, true);

			try {
				$tp = TourPackage::find($vars['bookingM']->tour_package_id);
				$vars['tpkg'] = $tp;
				$vars['totalDiscountPayment'] = BookingService::calculateDiscountpayment($bookingId);
			} catch (Exception $exception) {
				Log::error($exception);
			}

			$booking = TourBooking::find($bookingId);
			$vars['hasActiveDispute'] = $booking?->hasActiveDispute() ?? false;
			$vars['isDisputed']       = $booking?->isDisputed() ?? false;

			return view('users.common.tour-booking-info-print', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Print receipt using Epson ePOS SDK or fallback to browser print
	 *
	 * @param int $bookingId
	 * @return mixed
	 */
	public function receiptPrint($bookingId)
	{
		try {
			$vars = $this->tourBookingRepository->booking($bookingId, true);

			try {
				$tp = TourPackage::find($vars['bookingM']->tour_package_id);
				$vars['tpkg'] = $tp;
				$vars['totalDiscountPayment'] = BookingService::calculateDiscountpayment($bookingId);
			} catch (Exception $exception) {
				Log::error($exception);
			}

			$vars['fallbackPrintRoute'] = route(prefixedRouteName('tour-operator.booking.print-info'), $bookingId);

			return view('users.common.tour-booking-receipt-print', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()])
				->withInput();
		}
	}

	/**
	 * Get receipt print data as JSON for thermal printing
	 *
	 * @param Request $request
	 * @param int $bookingId
	 * @return JsonResponse
	 */
	public function receiptPrintData(Request $request, $bookingId): JsonResponse
	{
		try {
			$vars = $this->tourBookingRepository->booking($bookingId, true);

			if (!isset($vars['bookingM'])) {
				return response()->json([
					'success' => false,
					'message' => 'Booking not found',
				], 404);
			}

			$includeLogo = $request->query('logoBase64') !== 'false';

			$paymentTransactions = $vars['paymentTransactions'] ?? collect();
			$additionalTransactions = Transaction::where('tour_booking_id', $bookingId)
				->where('status', 'Completed')
				->whereIn('charge_type', ['custom_charge', 'tip'])
				->orderBy('id', 'DESC')
				->get();

			if ($additionalTransactions->isNotEmpty()) {
				$paymentTransactions = $paymentTransactions->merge($additionalTransactions)->sortBy('created_at')->values();
			}

			$receiptPrintService = app(ReceiptPrintService::class);
			$receiptData = $receiptPrintService->getReceiptData(
				$vars['bookingM'],
				$vars['bookingT'],
				$vars['pricedAddons'] ?? [],
				$vars['payLaterAddons'] ?? [],
				$vars['tipAmounts'] ?? [],
				$vars['customChargePrice'] ?? [],
				$vars['paymentOnlyCheck'] ?? 0,
				$vars['Refund_Amount'] ?? 0,
				$paymentTransactions,
				$includeLogo
			);

			return response()->json([
				'success' => true,
				'data' => $receiptData,
				'message' => 'Receipt data fetched successfully',
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage(),
			], 500);
		}
	}

	/**
	 * Save note for booking
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function saveNote(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->saveNote($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function savePriorityNote(Request $request, int $id)
	{
		try {
			return response()->json($this->tourBookingRepository->savePriorityNote($request, $id));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Cancel the booking which is already created
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function cancel(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->cancel($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Restore the booking which is cancelled
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function restore(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->restoreBooking($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Change the payment type of booking
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function changePaymentType(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->changeHistoryPaymentType($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Refund booking which is already created
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function refund(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->refund($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
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
			DB::table('tour_bookings_master')
				->where('id', $id)
				->update([
					'deleted_at' => mysqlDT(),
					'deleted_by' => auth(session('guard'))->id(),
					'deleted_by_user_type' => session('userType'),
				]);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to delete tour booking.');
		}
	}

	/**
	 * Save comments for booking
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function saveComments(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->saveComments($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Save reference of booking
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function saveReferenceBooking(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->saveReferenceBooking($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Save tour guide in the booking
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function saveTourGuide(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->saveTourGuide($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Get tour operator data
	 *
	 * @param int $tourOperatorId
	 */
	public function getTourOperatorData(int $tourOperatorId)
	{
		$data = $this->get_tour_operator_data($tourOperatorId);

		if ($data === false)
			return sendJsonErrMsg('Unable to get tour packages and other details.');

		return response()->json($data);
	}

	/**
	 * Get tour operator reports
	 *
	 * @param int $tourOperatorId
	 */
	public function getTourOperatorReports(int $tourOperatorId)
	{
		if ($tourOperatorId == 0) {
			$reports = reports();
			$reports = array_values($reports);
		} else {
			$data = TourOperator::select('reports')->where('id', $tourOperatorId)->first();

			// Decode safely
			$reports = json_decode($data->reports ?? '[]', true);

			// Ensure $reports is an array
			if (!is_array($reports)) {
				$reports = [];
			}

			// Reports to remove
			$remove = ["TripAdvisor Booking Report", "GetYourGuide Booking Report"];

			// Check if any of the reports exist in current array
			$intersection = array_intersect($reports, $remove);

			if (!empty($intersection)) {
				// Remove and update
				$updatedReports = array_values(array_diff($reports, $remove));

				TourOperator::where('id', $tourOperatorId)->update([
					'reports' => json_encode($updatedReports),
				]);

				$reports = $updatedReports;
			}
		}

		// Early validation check
		if ($reports === false) {
			return sendJsonErrMsg('Unable to get tour packages and other details.');
		}

		if (is_array($reports)) {
			sort($reports);
		}

		// Load user with permissions
		$user = Admin::with(['roles', 'permissions'])->find(Auth::id());

		// Restrict reports if not super admin
		if ($user && $user->roles->isNotEmpty() && $user->roles[0]->name !== 'super-admin') {
			$permissionsArray = $user->permissions->pluck('display_name')->toArray();
			$reports = array_values(array_intersect($permissionsArray, $reports));
		}

		// Add Payment Breakdown Report if user has permission
		if ($user && $user->can('view-payment-breakdown-report')) {
			if (!in_array('Payment Breakdown Report', $reports)) {
				$reports[] = 'Payment Breakdown Report';
				sort($reports);
			}
		}

		return response()->json([
			'data' => ['reports' => $reports],
		]);
	}

	/**
	 * Get tour operator affiliate data
	 *
	 * @param int $tourOperatorId
	 */
	public function getTourOperatorAffiliateData(int $tourOperatorId)
	{
		$tourOperator_data = TourOperator::select('is_affiliate')->where('id', $tourOperatorId)->first();

		if (!$tourOperator_data)
			return sendJsonErrMsg('Unable to get tour packages and other details.');

		$data = [
			'is_affiliate' => $tourOperator_data
		];

		return response()->json(['data' => $data]);
	}

	/**
	 * Get tour operator data
	 *
	 * @param int $tourOperatorId
	 */
	private function get_tour_operator_data(int $tourOperatorId)
	{
		try {
			$tourPackages = TourPackage::where('status', 'Active')
				->where('tour_operator_id', $tourOperatorId)
				->orderBy('order_by')
				->get(['id', 'name']);

			$staff = Staff::where('status', 'Active')
				->where('tour_operator_id', $tourOperatorId)
				->orderBy('first_name')
				->orderBy('last_name')
				->whereNull('deleted_at')
				->get(['id', 'first_name', 'last_name']);

			$taxes = DB::table('taxes AS t')
				->join('states AS s', 's.id', '=', 't.state_id')
				->join('cities AS ct', 'ct.state_id', '=', 's.id')
				->join('tour_operators AS t_o', 't_o.city_id', '=', 'ct.id')
				->where('t.status', 'Active')
				->where('t_o.id', $tourOperatorId)
				->orderBy('t.name')
				->get(['t.name', 't.value', 't.value_type']);

			return [
				'tourPackages' => $tourPackages,
				'staff' => $staff,
				'taxes' => $taxes,
			];
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	/**
	 * Search the booking by using term
	 *
	 * @param Request $request
	 */
	public function search(Request $request)
	{
		try {
			$tourOperatorId = session('userType') == 'Tour Operator' || session('userType') == 'Affiliate' || session('guard') == "customer" ? Auth::user()->tour_operator_id : $request->tourOperatorId;

			$bookings = $this->latestBookings($tourOperatorId, $request->search);

			if ($bookings === false) {
				return sendJsonErrMsg('Unable to search for booking.');
			}

			foreach ($bookings as $key => $value) {
				if (empty($value->invoiceStatus)) {
					$bookings[$key]->invoiceStatus = '0';
				}

				$bookings[$key]->is_tripadvisor_booking = $value->tripadvisor_id ? 1 : 0;
				$bookings[$key]->is_get_your_guide_booking = $value->get_your_guide_booking_id ? 1 : 0;
				$bookings[$key]->is_ota_booking = $value->ota_booking_id ? 1 : 0;

				$otaTitle = BokunUtils::getOTAChannelNameByBookingId($value->id);
				$bookings[$key]->ota_title = strtolower($otaTitle);
				$bookings[$key]->ota_logo  = BokunUtils::getOTALogoByTitle($otaTitle);

				if (!empty($value->timezone)) {
					$bookings[$key]->booking_d_t = Carbon::createFromFormat('Y-m-d H:i:s', $value->booking_d_t, config('app.timezone'))
						->setTimezone($value->timezone)
						->format('Y-m-d H:i:s');
				}
			}

			return response()->json([
				'search' => $request->search,
				'bookings' => $bookings
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return false;
		}
	}

	/**
	 * Update checkin status of booking which is created
	 *
	 * @param BookingCheckInStatusRequest $request
	 */
	public function updateCheckInStatus(BookingCheckInStatusRequest $request)
	{
		try {
			return $this->tourBookingRepository->updateCheckInStatus($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Affiliate bookings
	 *
	 * @param int $tourSlotId
	 */
	public function affiliateBookings(int $tourSlotId)
	{
		try {
			return $this->tourBookingRepository->affiliateBookings($tourSlotId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Send email form
	 *
	 * @param int $bookingId
	 */
	public function sendEmailForm(int $bookingId)
	{
		try {
			$vars = ['page_title' => 'Send email to customer'];

			$fields = [
				'tbm.*',
				'tst.time_from',
				'tsd.date',
				'tp.name AS tour_package',
				'tbm.name AS customer',
				'tbm.email AS cust_email',
				'tbm.phone_number AS cust_phone',
			];

			$qry = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $bookingId);

			switch (session('userType')) {
				case 'Admin':
					$fields[] = 't_o.name AS tour_operator';
					$qry = $qry->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id');
					break;
				default:
					$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
					break;
			}

			if (!($vars['booking'] = $qry->first($fields)))
				abort(404, 'Unable to get data of the tour booking.');

			return view('users.common.tour-booking-send-email', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to get data of the tour booking.');
		}
	}

	/**
	 * Send email for booking
	 *
	 * @param BookingSendEmailRequest $request
	 * @param int $bookingId
	 */
	public function sendEmail(BookingSendEmailRequest $request, int $bookingId)
	{
		try {
			return $this->tourBookingRepository->sendEmail($request, $bookingId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Liability waiver form
	 *
	 * @param int $bookingId
	 * @param int $touristNum
	 * @param int $isAdult
	 */
	public function liabilityWaiverForm(int $bookingId, int $touristNum, int $isAdult)
	{
		try {
			$vars = $this->customerRepository->getLiabilityWaiver($bookingId, $touristNum, $isAdult);
			return view('users.common.liability-waiver', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Update the waiver by the customer
	 *
	 * @param Request $request
	 * @param int $bookingId
	 */
	public function liabilityWaiverSubmission(Request $request, int $bookingId)
	{
		try {
			return $this->customerRepository->updateLiabilityWaiver($request, $bookingId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Send email form by tour date
	 *
	 * @param string $date
	 * @param string $time
	 */
	public function sendEmailFormByTourDate($date, $time)
	{
		try {
			$vars = ['page_title' => 'Send email to all customer'];

			$fields = [
				'tbm.*',
				'tst.time_from',
				'tsd.date',
				'tp.name AS tour_package',
				'tbm.name AS customer',
				'tbm.email AS cust_email',
				'tbm.phone_number AS cust_phone',
			];

			$qry = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->where('tst.time_from', $time)
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tsd.date', $date);

			switch (session('userType')) {
				case 'Admin':
					$fields[] = 't_o.name AS tour_operator';
					$qry = $qry->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id');
					break;
				default:
					$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
					break;
			}

			if (!($vars['booking'] = $qry->first($fields)))
				abort(404, 'Unable to get data of the tour booking.');

			return view('users.common.tour-booking-send-email-by-date-time', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to get data of the tour booking.');
		}
	}

	/**
	 * Send email by tour date to the customers
	 *
	 * @param Request $request
	 * @param string $date
	 * @param string $time
	 */
	public function sendEmailByTourDate(Request $request, $date, $time)
	{
		set_time_limit(0);
		$request->validate([
			'email_subject' => 'required|min:1',
			'email_content' => 'required|min:1'
		]);

		try {
			$qry = DB::table('tour_bookings_master AS tbm')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'c.tour_operator_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->join('countries AS cn', 'cn.id', '=', 'st.country_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->where('tst.time_from', $time)
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tsd.date', $date)
				->get([
					't_o.name AS tour_operator',
					't_o.email AS t_o_email',
					't_o.phone_number AS t_o_phone',
					't_o.tenant_id',
					't_o.address AS t_o_address',
					't_o.postal_code AS t_o_postal_code',
					't_o.logo AS t_o_logo',
					'ct.name AS t_o_city',
					'st.name AS t_o_state',
					'cn.name AS t_o_country',
					'tbm.name AS customer',
					'tbm.email AS cust_email',
					'tp.name as packageName',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
				]);

			foreach ($qry as $value) {
				$arEmailData = [
					'emailContent' => $request->email_content,
					'tourOperator' => $value->tour_operator,
					'tourOpEmail' => $value->t_o_email,
					'tourOpPhone' => $value->t_o_phone,
					'tourOpAddress' => $value->t_o_address,
					'tourOpPostalCode' => $value->t_o_postal_code,
					'tourOpCity' => $value->t_o_city,
					'tourOpState' => $value->t_o_state,
					'tourOpCountry' => $value->t_o_country,
					'tourOpLogo' => $value->t_o_logo,
					'tourPackage' => $value->packageName,
					'packageEmail' => $value->packageEmail,
					'packageFromName' => $value->packageFromName,
					'packageFromEmail' => $value->packageFromEmail,
					'packageApiKey' => $value->packageApiKey,
					'tenantId' => $value->tenant_id
				];

				sendEmail(
					$request->email_subject,
					[
						'name' => $value->customer,
						'email' => $value->cust_email,
					],
					'customer-booking-custom-date-time',
					$arEmailData
				);
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to get data of the tour booking.');
		}
	}

	/**
	 * Edit booking (Edit and Delete)
	 *
	 * @param Request $request
	 * @throws Exception
	 */
	public function refund_and_booking_edit(Request $request)
	{
		try {
			return $this->tourBookingRepository->editBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Change people in booking.
	 *
	 * @param ChangePeopleRequest $request
	 * @return mixed
	 */
	public function changePeopleBooking(ChangePeopleRequest $request)
	{
		try {
			return $this->tourBookingRepository->changePeopleBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Split people in different booking.
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function splitPeopleBooking(Request $request)
	{
		try {
			return $this->tourBookingRepository->splitPeopleBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Switch package in booking.
	 *
	 * @param SwitchPackageRequest $request
	 * @return mixed
	 */
	public function switchPackageBooking(SwitchPackageRequest $request)
	{
		try {
			return $this->tourBookingRepository->switchPackageBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Resend email for the booking
	 *
	 * @param Request $request
	 * @param int $bookingId
	 */
	public function ResendEmail(Request $request, $bookingId)
	{
		try {
			return response()->json($this->tourBookingRepository->resendEmail($request, $bookingId));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Check the overbooking for the specific slot user trying to book
	 *
	 * @param Request $request
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function checkOverbooking(Request $request)
	{
		try {
			return response()->json($this->tourBookingRepository->checkOverbooking($request));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * View and print the invoice of booking
	 *
	 * @param int $id
	 */
	public function bookingInvoice(int $id)
	{
		try {
			$vars = $this->tourBookingRepository->bookingInvoicePrint($id);
			return view('customer.tour-booking-info', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}
	/**
	 * View and print the invoice of booking
	 *
	 * @param int $id
	 */
	public function invoiceWithoutPrice(int $id)
	{
		try {
			$vars = $this->tourBookingRepository->invoiceWithoutPrice($id);
			return view('customer.tour-booking-info-without-price', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}
	/**
	 * View and print the invoice of combo bookings
	 *
	 * @param string $id
	 */
	public function comboBookingInvoice(string $id)
	{
		try {
			$vars = $this->tourBookingRepository->comboBookingInvoice($id);
			return view('users.common.combo-tour-booking-info', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * @return mixed
	 *
	 * @param Request $request
	 * @throws Exception
	 */
	public function dayTimeSlotsbooking(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->dayTimeSlotsbooking($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return JsonResponse
	 * @param  Request $request
	 */
	public function getBookingHistoryAndTransactions(Request $request, int $bookingId): JsonResponse
	{
		try {
			$response = $this->tourBookingRepository->bookingHistoryAndTransactions($bookingId);
			return response()->json($response, 200);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return string
	 * @param int $bookingId
	 */
	public function splitBookingModalContent($bookingId): string
	{
		try {
			$vars = $this->tourBookingRepository->splitBookingModalContent($bookingId);
			return view('users.common.partials.booking._history-log-body', $vars)->render();
		} catch (Exception $exception) {
			Log::error($exception);
			return '<div class="split-modal-loading" style="color:#c43030;">Failed to load history.</div>';
		}
	}

	/**
	 * Unblock affiliate soft booking
	 *
	 * @param int $bookingId
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function unblockAffiliateSoftBooking($bookingId)
	{
		try {
			return response()->json($this->tourBookingRepository->affiliateSoftBookingUnblock($bookingId));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Unblock affiliate soft booking
	 *
	 * @param Request $request
	 * @param int $bookingId
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function addOrUpdateHotel(Request $request, $bookingId)
	{
		try {
			$data = $request->all();

			if ($data['hotel_id'] == 0) {
				return sendJsonErrMsg("Please choose your hotel for pickup.");
			}

			return response()->json($this->tourBookingRepository->hotelPickupUpdate($data, $bookingId));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	// this function use for get promocode detail using promo code name
	public function getPromoDetail(Request $request)
	{
		try {
			$tourSlotTime = TourSlotTime::find($request->tourSlotId);

			if ($tourSlotTime) {
				$detail = TourPromotion::where('tour_package_id', $request->tourPackageId)
					->where('code', $request->promoCode)
					->whereRaw("\"{$tourSlotTime->date}\" between `start_date` and `end_date`")
					->where('status', 'Active')
					->first(['id', 'max_allowed_usages', 'min_allowed_tourists', 'discount_value', 'discount_value_type']);

				if (!$detail) {
					return sendJsonErrMsg("Please enter valid promo code.");
				}

				// This lookup fetched max_allowed_usages but never actually checked it against
				// real usage -- so a code could be previewed/applied here (New Booking, and Edit
				// Discount's own preview call) on unlimited bookings even after its allowed uses
				// were already exhausted. Same "how many bookings already used this exact
				// promotion" check editDiscount() and the checkout-time applyCoupon() flow now
				// both enforce. 0 means unlimited.
				if ((int) $detail->max_allowed_usages > 0) {
					$promoUsageCount = TourBooking::where('tour_promotion_id', $detail->id)->count();
					if ($promoUsageCount >= (int) $detail->max_allowed_usages) {
						return sendJsonErrMsg("This promo code has already reached its maximum allowed usage.");
					}
				}

				// Tourist count -- from the in-progress New Booking form (a 'tourists' param the
				// frontend sends once people are selected), or, when previewing on an EXISTING
				// booking's Edit Discount page, that booking's own already infant-excluded
				// `tourists` column (tour_bookings_master.tourists never includes infants --
				// same convention editDiscount()'s own min_allowed_tourists check relies on).
				$tourists = $request->filled('bookingId')
					? (int) (TourBooking::find($request->bookingId)->tourists ?? 0)
					: (int) $request->input('tourists', 0);

				if ((int) $detail->min_allowed_tourists > 0 && $tourists < (int) $detail->min_allowed_tourists) {
					return sendJsonErrMsg("This promo code requires at least " . $detail->min_allowed_tourists . " tourist(s).");
				}
			} else {
				return sendJsonErrMsg("Please enter valid promo code.");
			}

			return response()->json($detail);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	// this function is use to make additional payment on edit discount
	public function editDiscountPayment(Request $request)
	{
		try {
			if ($request->has('discountHistory')) {
				$discountHistoryArray = json_decode($request->discountHistory, true);
				if ($discountHistoryArray['payment_method'] == 'Cash' || $discountHistoryArray['payment_method'] == 'Invoiced') {
					return $this->tourBookingRepository->editDiscountCashPayment();
				} else {
					return $this->tourBookingRepository->editDiscountCardPayment($request);
				}
			}
			return response()->json(['message' => 'Invalid details. please reload this page and try again'], 400);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function editDiscountPaymentLink(Request $request)
	{
		try {
			return $this->tourBookingRepository->editDiscountPaymentLink($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	// this function is use to make refund payment on edit discount
	public function editDiscountRefund(Request $request)
	{
		try {
			return $this->tourBookingRepository->editDiscountRefund($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed
	 * @param int $date
	 * @param string $tourPackagesId
	 * @param int $paymentStatus
	 * @param string $slotId
	 * @throws Exception
	 */
	public function downloadAgenda(Request $request, int $date = 0, string $tourPackagesId = '', int $paymentStatus = 0, $slotId = '')
	{
		try {
			$vars = $this->tourBookingRepository->downloadAgenda($request, $date, $tourPackagesId, $paymentStatus, $slotId);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Add custom charge to the booking which is already created
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function addCustomCharge(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->addCustomCharge($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Refund Custom Charge
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function refundCustomCharge(Request $request, int $id)
	{
		try {
			return $this->tourBookingRepository->refundCustomCharge($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function getPaymentLinkItems(int $bookingId): JsonResponse
	{
		try {
			$pending = PaymentLinkService::getPendingForBooking($bookingId);

			if (!$pending) {
				return response()->json(['items' => [], 'total' => moneyFormat(0)]);
			}

			$formattedItems = array_map(fn($item) => [
				'type'       => $item['label'],
				'amount'     => moneyFormat($item['amount']),
				'created_at' => $item['created_at'],
				'created_by' => $item['created_by'] ?? null,
			], $pending['items']->toArray());

			return response()->json([
				'items' => $formattedItems,
				'total' => moneyFormat($pending['total']),
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json(['error' => $exception->getMessage()], 500);
		}
	}

	/**
	 * Make a refund for add-ons in the booking.
	 *
	 * @param Request $request
	 * @return mixed
	 * @throws Exception
	 */
	public function upseartAddons(Request $request)
	{
		try {
			return response()->json($this->tourBookingAddonRepository->chargeAddonFields($request));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Update checkin status of bundle package booking
	 *
	 * @param BookingPackageRedeemStatusRequest $request
	 */
	public function updatePackageRedeemTicketStatus(BookingPackageRedeemStatusRequest $request)
	{
		try {
			return $this->tourBookingRepository->updatePackageRedeemTicketStatus($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Request to the manager
	 *
	 * @param Request $request
	 */
	public function requestToManager(Request $request)
	{
		try {
			return $this->tourBookingRepository->requestToManager($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * The manual id check
	 *
	 * @param Request $request
	 */
	public function manualIdCheck(Request $request)
	{
		try {
			return $this->tourBookingRepository->manualIdCheck($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * The manual pin check
	 *
	 * @param Request $request
	 */
	public function manualPinCheck(Request $request)
	{
		try {
			return $this->tourBookingRepository->manualPinCheck($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * The manual pin check for new booking
	 *
	 * @param Request $request
	 */
	public function manualPinCheckBooking(Request $request)
	{
		try {
			return $this->tourBookingRepository->manualPinCheckBooking($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * The action details store on session
	 *
	 * @param Request $request
	 */
	public function actionStore(Request $request)
	{
		try {
			return $this->tourBookingRepository->actionStore($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Execute to get contact details for given contact ID
	 *
	 * @param integer $contactId
	 */
	public function getContactDetails($contactId)
	{
		try {
			return $this->tourBookingRepository->getContactDetails($contactId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Execute to edit contact details for given contact ID
	 *
	 * @param EditContactRequest $request
	 */
	public function editContact(EditContactRequest $request)
	{
		try {
			return $this->tourBookingRepository->editContact($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param int $date
	 * @param string $tourPackagesId
	 * @param int $PaymentStatus
	 * @param string $slotId
	 * @throws Exception
	 */
	public function rosterReportCSV(Request $request, int $date = 0, string $tourPackagesId = '', int $PaymentStatus = 0, $slotId = '')
	{
		try {
			$vars = $this->tourBookingRepository->rosterReportCSV($request, $date, $tourPackagesId, $PaymentStatus, $slotId);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param int $date
	 * @param string $tourPackagesId
	 * @param int $PaymentStatus
	 * @param string $slotId
	 * @throws Exception
	 */
	public function rosterReportPDF(Request $request, int $date = 0, string $tourPackagesId = '', int $PaymentStatus = 0, $slotId = '')
	{
		try {
			$vars = $this->tourBookingRepository->rosterReportPDF($request, $date, $tourPackagesId, $PaymentStatus, $slotId);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param int|string $bookingId

	 * @throws Exception
	 */
	public function bookingContacts($bookingId)
	{
		try {
			$contacts = PackageHasContactsService::contactList($bookingId);
			return $contacts;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param int|string $bookingId

	 * @throws Exception
	 */
	public function rateGroupWiseBookingContacts($bookingId)
	{
		try {
			$vars = $this->tourBookingRepository->rateGroupWiseBookingContacts($bookingId);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function updateSlotBookableStatus(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->updateSlotBookableStatus($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function addTimeSlot(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->addTimeSlot($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function bulkUpdateSlotBookableStatus(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->bulkUpdateSlotBookableStatus($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function updateSlotHideStatus(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->updateSlotHideStatus($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function bulkUpdateSlotHideStatus(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->bulkUpdateSlotHideStatus($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function createPaymentLink(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->createPaymentLink($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function getPaymentLinkReason(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->getPaymentLinkReason($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function expiryDateOverride(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->expiryDateOverride($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function activePaymentLink(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->activePaymentLink($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function inActivePaymentLink(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->inActivePaymentLink($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function paymentLinkStatusUpdate(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->paymentLinkStatusUpdate($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param Request $request

	 * @throws Exception
	 */
	public function paymentLinkStatusCheck(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->paymentLinkStatusCheck($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Update nested add-ons for the booking
	 *
	 * @param Request $request
	 * @return mixed
	 * @throws Exception
	 */
	public function updateBookingAddons(Request $request)
	{
		try {
			$vars = $this->tourBookingAddonRepository->updateBookingAddons($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	public function addonPaymentLink(Request $request)
	{
		try {
			$vars = $this->tourBookingAddonRepository->createAddonPaymentLink($request);
			return response()->json($vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
		}
	}

	/**
	 * @return mixed

	 * @param $request

	 * @throws Exception
	 */
	public function getBookingPaxAddons(Request $request)
	{
		try {
			$vars = $this->tourBookingAddonRepository->getBookingPaxAddons($request);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Save pax addons for a specific passenger
	 *
	 * @param Request $request
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function savePaxAddons(Request $request)
	{
		try {
			$result = $this->tourBookingAddonRepository->savePaxAddons($request);
			return response()->json($result);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage()
			], 500);
		}
	}

	public function updatePaxAddons(Request $request)
	{
		try {
			$result = $this->tourBookingAddonRepository->updatePaxAddons($request);
			return response()->json($result);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage()
			], 500);
		}
	}

	/**
	 * @return mixed
	 * @param int $tourBookingId
	 */
	public function getTourBookingAddonsSubmission($tourBookingId)
	{
		try {
			return $this->tourBookingAddonRepository->getTourBookingAddonsSubmission($tourBookingId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param int|string $bookingId

	 * @throws Exception
	 */
	public function getAssignedGuides($bookingId)
	{
		try {
			$vars = $this->tourBookingRepository->getAssignedGuides($bookingId);
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * @return mixed

	 * @param $request
	 * 
	 * @throws Exception
	 */
	public function agendaCheckinOverview(Request $request)
	{
		try {
			$vars = $this->tourBookingRepository->agendaCheckinOverview($request);
			$routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
			$vars['routePrefix'] = $routePrefix;

			if (isset($request->selectedCalendar)) {
				return $vars;
			}
			return view('users.common.agenda-checkin-overview', $vars)->render();
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Delete only pax addons for a booking
	 *
	 * @param Request $request
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function deletePaxAddons(Request $request)
	{
		try {
			$bookingId          = $request->input('booking_id');
			$paxAddonIds        = json_encode($request->input('rate_groups'));
			$refundType         = $request->input('refund_type', Constants::ADDONS_DONT_REFUND);
			$description        = $request->input('description');
			$pendingLinkItemId  = $request->input('pending_link_item_id') ? (int) $request->input('pending_link_item_id') : null;

			$result = $this->tourBookingAddonRepository->deletePaxAddons($bookingId, $paxAddonIds, $refundType, $description, null, null, null, null, $pendingLinkItemId);
			return response()->json($result);
		} catch (Exception $exception) {
			Log::error($exception);
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage()
			], 500);
		}
	}
}
