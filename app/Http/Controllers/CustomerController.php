<?php

namespace App\Http\Controllers;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\BookingTransact;
use App\Models\Tenant;
use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Transaction;
use App\Models\TourBookingHistory;
use App\Models\TourBooking;
use App\Models\TourOperator;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use App\Utils\AuthUtils;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\PaymentLinkService;
use App\Services\Booking\TipService;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Models\Customer;
use App\Contracts\Payments\PaymentGatewayInterface;

class CustomerController extends Controller
{
	protected $customerRepository;

	protected $tourBookingRepository;

	/**
	 * @param CustomerRepository $customerRepository
	 * @param TourBookingRepository $tourBookingRepository
	 */
	public function __construct(CustomerRepository $customerRepository, TourBookingRepository $tourBookingRepository)
	{
		$this->customerRepository = $customerRepository;
		$this->tourBookingRepository = $tourBookingRepository;
	}

	/**
	 * Get tour bookings
	 */
	public function tourBookings()
	{
		$vars = [
			'page_title' => 'Tour Bookings'
		];

		return view('customer.tour-bookings', $vars);
	}

	/**
	 * Array response for DataTable.
	 *
	 * @param \Yajra\DataTables\DataTables $dt
	 * @return mixed
	 */
	public function tourBookingsAHR(DataTables $dt)
	{
		$query = DB::table('tour_bookings_master AS tbm')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->where('tbm.deleted_by', 0)
			->where('tbm.customer_id', Auth::id())
			->select([
				'tbm.id',
				'tbm.status',
				'tp.name AS tour_package',
				'tsd.date',
				'tst.time_from',
				'c.name AS customer',
				't_o.name AS tour_operator',
				DB::raw("IF(tbm.liability_waiver_signing = '', 'Sign', 'View') AS liability_waiver_signing")
			]);

		return DataTables::of($query)
			->editColumn('status', function ($booking) {
				return '<span id="sts-' . $booking->id . '" class="status-' . $booking->status . '">' . $booking->status . '</span>';
			})
			->editColumn('action', function ($booking) {
				return '<a href="' . route('customer.tour-booking-info', ['bookingId' => Crypt::encryptString($booking->id)]) . '" class="btn btn-info btn-sm" title="View"><i class="fa fa-eye"></i></a>';
			})
			->editColumn('liability_waiver_link', function ($booking) {
				return '<a href="' . route('customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($booking->id)]) . '">' . $booking->liability_waiver_signing . '</a>'; //<i class="fa fa-pencil-square-o"></i>
			})
			->rawColumns(['action', 'status', 'liability_waiver_link'])
			->make(true);
	}

	/**
	 * Get the tour booking info for the customer
	 * @param string $encBookingId
	 * @throws \Exception
	 * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
	 */
	public function tourBookingInfo(string $encBookingId)
	{
		if (isCentralDomain()) {
			return response()->view('errors.404', [], 404);
		}

		try {
			$isEncrypted = self::isEncrypted($encBookingId);
			$bookingId = $encBookingId;

			if ($isEncrypted == true) {
				$bookingId = Crypt::decryptString($encBookingId);
			}

			// Use query builder with select instead of array for better readability
			$bookingM = DB::table('tour_bookings_master as tbm')
				->select(
					'tbm.id',
					'tbm.tourists',
					'tbm.infants',
					'tbm.status',
					'tbm.total',
					'tbm.note',
					'tbm.discount',
					'tbm.discount2_value',
					'tbm.tour_package_id',
					'tbm.discount2_percentage',
					'tbm.service_commission',
					'tbm.created_at',
					'tp.name as tour_package',
					'tp.package_has_slots',
					'tp.package_has_waiver',
					'tp.package_has_permit',
					't_o.name as tour_operator',
					't_o.cancellation_within_days',
					't_o.email as tour_op_email',
					't_o.phone_number as tour_op_phone',
					't_o.logo',
					't_o.timezone',
					't_o.tenant_id',
					'tsd.date',
					'tst.time_from',
					'tst.time_to',
					'tbm.name as customer',
					'tbm.email as cust_email',
					'tbm.phone_number as cust_phone',
					'promo.discount_value',
					'promo.discount_value_type',
					'tbm.booking_total',
					'tbm.is_group_booking',
					'tbm.is_partner_booking'
				)
				->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators as t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates as tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('customers as c', 'c.id', '=', 'tbm.customer_id')
				->leftJoin('tour_promotions as promo', 'promo.id', '=', 'tbm.tour_promotion_id')
				->where('tbm.id', $bookingId)
				->first() ?? abort(404, "No booking found with the supplied id.");

			$bookingT = DB::table('tour_bookings_transact as tbt')
				->select(
					'tbt.id',
					'tbt.tourists',
					'tbt.infants',
					'tbt.processing_charge_percentage',
					'tbt.nat_fees_percentage',
					'tbt.additional_tax_percentage',
					'tbt.permit_fee',
					'tbt.partner_fees_amount',
					'tbt.additional_charge',
					'tbt.state_tax',
					'tbt.rate',
					'tbt.total',
					'tbt.stripe_fees_percentage',
					'tpr.rate_for'
				)
				->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $bookingId)
				->where('tbt.deleted_at', null)
				->orderByDesc('tpr.age_from')
				->orderByDesc('tpr.age_to')
				->get() ?? abort(500, "Unable to get booking's information.");

			// Combine similar queries and use eager loading where possible
			$history = DB::table('tour_booking_history')
				->where('tour_booking_id', $bookingId)
				->orderByDesc('operation_at')
				->get();

			// One query for every transaction-derived value below, instead of a separate
			// Transaction::where('tour_booking_id', ...) round-trip per value — Eloquent's
			// where()/orderBy()/first() mutate the underlying builder in place rather than
			// cloning it, so reusing a single query-builder *instance* across calls previously
			// corrupted later calls with earlier ones' conditions; fetching once and deriving the
			// rest via Collection methods (which never mutate anything) avoids that class of bug
			// without paying for four round-trips to do it. Matches bookingInvoicePrint()'s (the
			// admin invoice page rendering this same shared blade) already-correct result shape.
			$allTransactions = Transaction::where('tour_booking_id', $bookingId)->get();
			$transaction = $allTransactions->first();
			$completedTransactions = $allTransactions->where('status', 'Completed');
			$trans = $completedTransactions->sortByDesc('id')->first();
			$transP = $completedTransactions->sortBy('id')->first();

			$totalDiscountPayment = BookingService::calculateDiscountpayment($bookingId);

			// Extract payment history logic to a separate method
			$paymentData = $this->getPaymentData($bookingId);

			// ->values() re-indexes from 0, matching what a fresh ->get() would have returned —
			// $completedTransactions otherwise keeps $allTransactions' original keys, which a
			// consumer indexing this list positionally (e.g. $paymentTrans[0]) would not expect.
			$paymentTrans = $completedTransactions->values();

			$customChargePrice = CustomChargeBookingService::customChargePrice($bookingId, $paymentTrans);

			// Extract cancellation logic to a separate method
			$cancellationData = $this->handleCancellation($bookingM, $bookingId);

			$pricedAddons = BookingService::fetchAddonsWithPrice($bookingId);
			$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice(
				$bookingId, 
				$bookingT[0]->nat_fees_percentage, 
				$trans
			);

			// getTipAmounts()'s 2nd param is unused (immediately overwritten internally by its
			// own tipTransactions() query) — nothing meaningful to pass here.
			$tipAmounts = TipService::getTipAmounts($bookingId);

			return view('customer.tour-booking-info', [
				'page_title' => 'Tour Booking Info',
				'bookingId' => $encBookingId,
				'bookingM' => $bookingM,
				'bookingT' => $bookingT,
				'history' => $history,
				// Customer-facing "Payment & Refund History" table on the invoice — see
				// HistoryService::customerFacingPaymentLog() for why this exists (a customer has
				// no way to see an old refund, e.g. a permit fee refund from months back, from the
				// aggregate totals alone). Matches bookingInvoicePrint()'s equivalent, which this
				// page had fallen out of parity with.
				'paymentHistoryLog' => HistoryService::customerFacingPaymentLog($history),
				'transaction' => $transaction,
				'TourBookingHistory' => $paymentData['tourBookingHistory'] ?? '',
				'HistoryPaymentTime' => $paymentData['historyPaymentTime'] ?? '',
				'trans' => $trans,
				'paymentOnlyCheck' => $paymentData['paymentOnlyCheck'],
				'transP' => $transP,
				'paymentTransCount' => $paymentTrans->count(),
				'cancelBooking' => $cancellationData['cancelBooking'],
				'cancelPreventNotification' => $cancellationData['cancelPreventNotification'],
				'refundType' => $cancellationData['refundType'],
				'cancellationPolicyApply' => $cancellationData['cancellationPolicyApply'],
				'pricedAddons' => $pricedAddons,
				'payLaterAddons' => $payLaterAddons,
				'customChargePrice' => $customChargePrice,
				'tipAmounts' => $tipAmounts,
				'totalDiscountPayment' => $totalDiscountPayment
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, "Unable to get booking's information.");
		}
	}

	/**
	 * Encrypt checking
	 * @param string $value
	 */
	function isEncrypted($value)
	{
	    if (!is_string($value)) return false;

	    $decoded = base64_decode($value, true);
	    if (!$decoded) return false;

	    $json = json_decode($decoded, true);
	    return is_array($json)
	        && isset($json['iv'], $json['value'], $json['mac']);
	}

	/**
	 * Summary of getPaymentData
	 * @param mixed $bookingId
	 * @return array{historyPaymentTime: TourBookingHistory|null, paymentOnlyCheck: mixed, tourBookingHistory: \Illuminate\Database\Eloquent\Collection<int, TourBookingHistory>|null}
	 */
	private function getPaymentData($bookingId): array
	{
		// orderBy('id', 'asc') is required — without it, ->first() can resolve to ANY of the
		// booking's Payment rows (e.g. a later "Guest Added" charge instead of the true first
		// payment), and the operation_at > ... filter below then silently drops every Refund/
		// Cancelled row that happened before that later payment — understating both Refund
		// Amount and (since Payment Total = paymentOnlyCheck - Refund Amount) Payment Total.
		// The payment_type filter is required too — a "Payment"-operation row can ALSO be a $0
		// addon-tracking placeholder (payment_type left null, no real payment happened). Without
		// excluding it, $historyPaymentTime can resolve to that placeholder instead of the real
		// first payment, so its $0 "amount" gets compared against the refund total below and
		// coincidentally zeroes out Payment Total even when nothing was ever refunded.
		// Matches TourBookingRepository::booking()'s / bookingInvoicePrint()'s equivalent query.
		$historyPaymentTime = TourBookingHistory::where('tour_booking_id', $bookingId)
			->where('operation', 'Payment')
			->whereNotNull('payment_type')
			->where('payment_type', '!=', '')
			->orderBy('id', 'asc')
			->first();

		$tourBookingHistory = null;
		if ($historyPaymentTime) {
			$tourBookingHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
				->where('operation_at', '>', $historyPaymentTime->operation_at)
				->whereIn('operation', ['Refund', 'Cancelled'])
				// A Refund/Cancelled row can ALSO be a $0 "Don't Refund" addon-removal
				// placeholder (payment_type left blank — no real refund happened), not a genuine
				// refund against an actual payment. Matches bookingInvoicePrint()'s (the admin
				// invoice page rendering this same shared blade) already-correct equivalent query.
				->whereNotNull('payment_type')
				->where('payment_type', '!=', '')
				->get();
		}

		$paymentOnly = TourBookingHistory::where('tour_booking_id', $bookingId)
			->where('operation', 'Payment')
			->get();

		$paymentOnlyCheck = $paymentOnly->sum(function ($value) {
			return json_decode($value->details)->amount;
		});

		return [
			'historyPaymentTime' => $historyPaymentTime,
			'tourBookingHistory' => $tourBookingHistory,
			'paymentOnlyCheck' => $paymentOnlyCheck
		];
	}

	/**
	 * Summary of handleCancellation
	 * @param mixed $bookingM
	 * @param mixed $bookingId
	 * @return array<array|string>|array{cancelBooking: int, cancelPreventNotification: null, cancellationPolicyApply: array, refundType: null}
	 */
	private function handleCancellation($bookingM, $bookingId): array
	{
		$data = [
			'cancelBooking' => request()->segment(1) === "confirm-cancel-booking" ? 1 : 0,
			'cancelPreventNotification' => null,
			'refundType' => null,
			'cancellationPolicyApply' => []
		];

		if (!$data['cancelBooking']) {
			return $data;
		}

		if ($bookingM->status === "Cancelled") {
			$data['cancelPreventNotification'] = "The reservation has already been cancelled; you are unable to do so again.";
			return $data;
		}

		$checkInCount = BookingTransact::where('tour_booking_id', $bookingId)
			->where('check_in_status', '!=', '')
			->count();

		if ($checkInCount > 0) {
			$data['cancelPreventNotification'] = "Check-in status updated. You are unable to cancel the booking; please contact the tour operator.";
			return $data;
		}

		$providedDatetime = Carbon::parse("{$bookingM->date} {$bookingM->time_from}");
		$now = Carbon::now(config('app.timezone'))->setTimezone($bookingM->timezone);
		$end = $providedDatetime->setTimezone($bookingM->timezone);

		if ($now->gte($end)) {
			$data['cancelPreventNotification'] = "The tour date has past; you cannot cancel the reservation.";
			return $data;
		}

		$closureEmailCount = TourBookingHistory::where('tour_booking_id', $bookingId)
			->where('details', 'like', '%closure email sent%')
			->count();

		if ($closureEmailCount > 0) {
			$data['cancellationPolicyApply'] = ['refund_basis' => 'full_amount', 'refund_percent' => 100];
			return $data;
		}

		$diffInHours = $now->diffInHours($end);
		$cancellationPolicies = DB::table('cancellation_policies')
			->where('package_id', $bookingM->tour_package_id)
			->where('type', '=', 'hours')
			->where('days_hours_before', '>=', $diffInHours)
			->orderBy('days_hours_before', 'asc')
			->get();

		foreach ($cancellationPolicies as $policy) {
			$start = $providedDatetime->copy()->subHours($policy->days_hours_before)
				->setTimezone($bookingM->timezone);
			if ($now->between($start, $end) && empty($data['cancellationPolicyApply'])) {
				$data['cancellationPolicyApply'] = [
					'refund_basis' => $policy->refund_basis,
					'refund_percent' => $policy->refund_percent
				];
				break;
			}
		}

		if (empty($data['cancellationPolicyApply'])) {
			$diffInDays = $now->diffInDays($end);
			$cancellationPolicies = DB::table('cancellation_policies')
				->where('package_id', $bookingM->tour_package_id)
				->where('type', 'days')
				->where('days_hours_before', '>=', $diffInDays)
				->orderBy('days_hours_before', 'asc')
				->get();

			foreach ($cancellationPolicies as $policy) {
				$start = $providedDatetime->copy()->subDays($policy->days_hours_before)
					->setTimezone($bookingM->timezone);
				if ($now->between($start, $end) && empty($data['cancellationPolicyApply'])) {
					$data['cancellationPolicyApply'] = [
						'refund_basis' => $policy->refund_basis,
						'refund_percent' => $policy->refund_percent
					];
					break;
				}
			}
		}

		return $data;
	}

	/**
	 * Tour booking cancel by customer from the email by customer
	 *
	 * @param Request $request
	 * @param string $envBookingId
	 */
	public function tourBookingCancel(Request $request, string $encBookingId)
	{
		try {
			$bookingId = Crypt::decryptString($encBookingId);

			$bookingM = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $bookingId)
				->first([
					'tbm.tour_package_id',
					't_o.cancellation_within_days',
					't_o.timezone',
					'tbm.service_commission',
					DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t")
				]);

			if (!$bookingM) {
				throw new Exception("No booking found.");
			}

			$search = 'closure email sent';

			$closureEmailCount = TourBookingHistory::where('tour_booking_id', $bookingId)
				->where('details', 'like', "%{$search}%")
				->count();

			$refundType = $request->refund_type;
			$refundPercent = $request->refund_percent;
			if ($closureEmailCount > 0) {
				$request->merge([
					'refund_type' => '100_refund'
				]);
			} else {
				if ($refundPercent == 100) {
					if ($refundType == 'ticket_cost') {
						$request->merge([
							'refund_type' => 'full_refund'
						]);
					} else if ($refundType == 'no_refund') {
						$request->merge([
							'refund_type' => 'dont_refund'
						]);
					}
				} else {
					if ($refundType == '') {
						$request->merge([
							'refund_type' => 'full_refund'
						]);
					} else if ($refundType == 'no_refund') {
						$request->merge([
							'refund_type' => 'dont_refund'
						]);
					} else {
						$refundAmount = BookingService::calculateRefundAmountOnCancellation($bookingId, $bookingM, $refundType, $refundPercent);
						$partialAmount = $refundAmount['partialAmount'];
						$request->merge([
							'refund_type' => 'partial_refund',
							'partial_refund_value' => $partialAmount,
							'refund_basis' => $refundType,
							'refund_percent' => $refundPercent
						]);
					}
				}
			}

			// Customer-initiated cancellations always send the email
			$request->merge(['send_cancel_mail' => '1']);

			$data = $this->tourBookingRepository->cancel($request, $bookingId);

			$refund = Transaction::where('tour_booking_id', $bookingId)
				->where('amount', '>', 0)
				->where('status', 'Refund')
				->count();

			$response = [
				'code' => 200,
				'message' => $refund ? "Expect 7-10 business days for refunds" : __('tour-operator.api_response.booking_cancellation_success'),
				'data' => $data
			];

			return response()->json($response);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update the customers by admin or tour operator
	 *
	 * @param Request $request
	 * @param int $id
	 */
	public function updateByAdminOrTourOp(Request $request, $id)
	{
		$request->validate([
			'name' => 'max:80',
			'email' => 'required|email|max:50',
			'phone_number' => 'max:30',
		]);

		try {
			return $this->customerRepository->updateByAdminOrTourOp($request, $id);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Search the customers from the data
	 *
	 * @param Request $request
	 */
	public function search(Request $request)
	{
		$strSearch = request('search', '');

		try {
			$customers = DB::table('customers')
				->where('is_affiliate', 'No')
				->where('status', 'Active')
				->where('deleted_by', 0)
				->where(function ($qry) use ($strSearch) {
					$qry->where('id', $strSearch)
						->orWhere('name', 'LIKE', "%{$strSearch}%")
						->orWhere('email', 'LIKE', "%{$strSearch}%")
						->orWhere('phone_number', 'LIKE', "%{$strSearch}%");
				})
				->take(50)
				->distinct()
				->select(['name', 'email', 'phone_number']);

			return response()->json($customers->get());
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to search for customer.');
		}
	}

	/**
	 * Liability waiver form view
	 *
	 * @param string $encBookingId
	 */
	public function liabilityWaiverForm($encBookingId)
	{
		try {
			if (isCentralDomain()) {
				throw new Exception("GET:liability-waiver route impact from the central domain.");
			}

			$bookingId = Crypt::decryptString($encBookingId);

			$vars = [
				'page_title' => 'Liability Waiver Form',
				'encBookingId' => $encBookingId,
			];

			$vars['bookingInfo'] = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->join('countries AS cn', 'cn.id', '=', 'st.country_id')
				->where('tbm.id', $bookingId);

			if (session('userType') == "Customer") {
				$vars['bookingInfo'] = $vars['bookingInfo']->where('tbm.customer_id', Auth::id());
			}

			$vars['bookingInfo'] = $vars['bookingInfo']->first([
				't_o.name AS tour_operator',
				't_o.name',
				't_o.email',
				't_o.phone_number',
				't_o.address',
				't_o.website',
				'tp.waiver_content',
				't_o.postal_code',
				'ct.name AS city',
				'st.name AS state',
				'cn.name AS country',
				'tp.name AS tour_package',
				'tbm.liability_waiver_signing',
				'tbm.id AS booking_id',
			]);

			if (!$vars['bookingInfo']) {
				return view('errors.404', ['error' => 'No booking found having the supplied booking id.']);
			}

			$vars['rateGroups'] = DB::table('tour_bookings_transact AS tst')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tst.tour_package_rate_id')
				->where('tst.tour_booking_id', $bookingId)
				->where('tst.deleted_at', null)
				->orderBy('tpr.orderby', 'asc')
				->get([
					'tst.id',
					'tst.tour_package_rate_id',
					'tst.tourists',
					'tst.infants',
					'tpr.rate_for',
					'tpr.age_from',
					'tpr.age_to',
					'tst.processing_charge_percentage',
					'tst.additional_tax_percentage',
					'tst.permit_fee',
					'tst.additional_charge',
					'tst.state_tax',
					'tst.rate',
					'tst.total'
				]);

			return view('liability-waiver', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to get tour booking information.');
		}
	}

	/**
	 * Liability waiver submission
	 *
	 * @param Request $request
	 * @param string $encBookingId
	 */
	public function liabilityWaiverSubmission(Request $request, string $encBookingId)
	{
		$request->validate([
			'name' => 'required|array',
			'age' => 'required|array',
			'age.*' => 'integer',
			'agree' => 'required',
		]);

		if (count($request->name) != count($request->age)) {
			return sendJsonErrMsg("Count of Names and Ages aren't matching.");
		}

		try {
			$bookingId = Crypt::decryptString($encBookingId);
			$signingInfo = [];
			$dateTime = mysqlDT();

			for ($a = 0, $ac = count($request->name); $a < $ac; $a++) {
				$signingInfo[] = [$request->name[$a], $request->age[$a], $dateTime];
			}

			$cd = DB::table('tour_bookings_master')
				->where('id', $bookingId)
				->update(['liability_waiver_signing' => json_encode($signingInfo, JSON_NUMERIC_CHECK)]);

			if ($cd) {
				if (($bookingId)) {
					for ($a = 0, $ac = count($request->name); $a < $ac; $a++) {
						$signingInfo[] = [$request->name[$a], $request->age[$a], $dateTime];
					}

					$whoSignIn = [$request->age[0], $request->name[0]];
				}

				$TourBookingHistory = TourBooking::where('id', $bookingId)->first();
				$created_by_with_name = $TourBookingHistory->name . ' ' . "Customer";

				DB::table('tour_booking_history')
					->insert([
						'tour_booking_id' => $bookingId,
						'operation' => 'Waiver',
						'details' => json_encode([
							'status' => $signingInfo,
							'whoSignIn' => $whoSignIn,
							'created_by_with_name' => $created_by_with_name,

						]),
						'operation_at' => $dateTime, // laravel doesn't seems to honouring timezone given in config > app.php!
						'operation_by' => $TourBookingHistory->customer_id,
						'operation_by_user_type' => 'Customer',
						'updated_at' => Carbon::now(),
						'created_at' => Carbon::now(),
					]);

				return response()->json([
					'status' => $signingInfo,
					'whoSignIn' => $whoSignIn,
					'checkInDateTime' => $dateTime,
				]);
			}

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to save the tourists and signing information.');
		}
	}

	/**
	 * School group waiver form — one student at a time, shareable link for all tenants.
	 *
	 * @param string $encBookingId
	 */
	public function schoolWaiverForm(string $encBookingId)
	{
		try {
			if (isCentralDomain()) {
				throw new Exception("GET:school-waiver route impact from the central domain.");
			}

			$bookingId = Crypt::decryptString($encBookingId);

			$bookingInfo = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->join('countries AS cn', 'cn.id', '=', 'st.country_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $bookingId)
				->first([
					't_o.name AS tour_operator',
					't_o.email',
					't_o.phone_number',
					't_o.address',
					't_o.website',
					't_o.postal_code',
					'tp.waiver_content',
					'tp.school_waiver_content',
					'ct.name AS city',
					'st.name AS state',
					'cn.name AS country',
					'tp.name AS tour_package',
					'tbm.id AS booking_id',
					'tbm.status',
					'tbm.customer_id',
					'tsd.date AS tour_date',
				]);

			if (!$bookingInfo) {
				return view('errors.404', ['error' => 'No booking found having the supplied booking id.']);
			}

			$totalStudents = DB::table('tour_bookings_transact')
				->where('tour_booking_id', $bookingId)
				->whereNull('deleted_at')
				->sum('tourists');

			$ageRange = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $bookingId)
				->whereNull('tbt.deleted_at')
				->selectRaw('MIN(tpr.age_from) AS age_min, MAX(tpr.age_to) AS age_max')
				->first();

			$ageMin = $ageRange->age_min ?? 1;
			$ageMax = (empty($ageRange->age_max) || $ageRange->age_max == 0) ? 120 : $ageRange->age_max;

			// Fetch all waiver entries (both direct and school waiver formats)
			$existingRaw     = DB::table('tour_bookings_master')->where('id', $bookingId)->value('liability_waiver_signing');
			$existingEntries = (!empty($existingRaw)) ? json_decode($existingRaw, true) : [];
			$submittedCount  = count($existingEntries);
			$isFull          = (int) $totalStudents > 0 && $submittedCount >= (int) $totalStudents;

			/** @var object $bookingInfo */
			return view('school-waiver', [
				'page_title'     => 'School Group Waiver',
				'encBookingId'   => $encBookingId,
				'bookingInfo'    => (object) $bookingInfo,
				'totalStudents'  => (int) $totalStudents,
				'submittedCount' => $submittedCount,
				'schoolEntries'  => array_reverse($existingEntries), // newest first
				'isFull'         => $isFull,
				'ageMin'         => (int) $ageMin,
				'ageMax'         => (int) $ageMax,
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to get tour booking information.');
		}
	}

	/**
	 * School group waiver submission — appends one student entry to liability_waiver_signing.
	 *
	 * Stored entry format: [full_name, age, datetime, parent_email, reference_id]
	 *
	 * @param Request $request
	 * @param string  $encBookingId
	 */
	public function schoolWaiverSubmission(Request $request, string $encBookingId)
	{
		$request->validate([
			'first_name'   => 'required|string|max:100',
			'last_name'    => 'required|string|max:100',
			'age'          => 'required|integer|min:1|max:120',
			'parent_email' => 'nullable|email|max:200',
			'agree1'       => 'required',
			'agree2'       => 'required',
			'agree3'       => 'required',
		]);

		try {
			$bookingId = Crypt::decryptString($encBookingId);

			$booking = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $bookingId)
				->first([
					'tbm.id AS booking_id',
					'tbm.customer_id',
					'tbm.name AS booker_name',
					'tp.name AS tour_package',
					't_o.name AS tour_operator',
					'tsd.date AS tour_date',
				]);

			if (!$booking) {
				abort(404, 'No booking found.');
			}

			$fullName    = trim($request->first_name . ' ' . $request->last_name);
			$dateTime    = mysqlDT();
			$referenceId = strtoupper(bin2hex(random_bytes(4)));  // 8-char hex e.g. B608497F
			$parentEmail = $request->parent_email ?? '';

			// Append to existing signingInfo (do not overwrite previous entries)
			$existing = DB::table('tour_bookings_master')
				->where('id', $bookingId)
				->value('liability_waiver_signing');

			$signingInfo   = (!empty($existing)) ? json_decode($existing, true) : [];
			$totalStudents = DB::table('tour_bookings_transact')
				->where('tour_booking_id', $bookingId)
				->whereNull('deleted_at')
				->sum('tourists');

			// Capacity guard — block if all student slots are already filled (count all entry types)
			if ((int) $totalStudents > 0 && count($signingInfo) >= (int) $totalStudents) {
				return redirect()->route('customer.school-waiver-form', ['bookingId' => $encBookingId])
					->withErrors(['limit' => 'All student waivers for this booking have already been submitted.']);
			}

			$newEntry      = [$fullName, (int) $request->age, $dateTime, $parentEmail, $referenceId];
			$signingInfo[] = $newEntry;

			DB::table('tour_bookings_master')
				->where('id', $bookingId)
				->update(['liability_waiver_signing' => json_encode($signingInfo, JSON_NUMERIC_CHECK)]);

			DB::table('tour_booking_history')->insert([
				'tour_booking_id'        => $bookingId,
				'operation'              => 'Waiver',
				'details'                => json_encode([
					'type'                 => 'school_waiver',
					'student'              => $fullName,
					'ref'                  => $referenceId,
					'whoSignIn'            => [(int) $request->age, $fullName],
					'created_by_with_name' => $fullName . ' Parent/Guardian',
				]),
				'operation_at'           => $dateTime,
				'operation_by'           => $booking->customer_id,
				'operation_by_user_type' => 'Customer',
				'updated_at'             => Carbon::now(),
				'created_at'             => Carbon::now(),
			]);

			// PRG pattern — flash success data and redirect to GET success route
			return redirect()->route('customer.school-waiver-success', ['bookingId' => $encBookingId])
				->with('waiver_success', [
					'firstName'   => $request->first_name,
					'lastName'    => $request->last_name,
					'parentEmail' => $parentEmail,
					'referenceId' => $referenceId,
					'submittedAt' => $dateTime,
				]);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to save the waiver information.');
		}
	}

	/**
	 * School group waiver success page (GET).
	 * Requires session flash from schoolWaiverSubmission — redirects to form if accessed directly or on refresh.
	 *
	 * @param string $encBookingId
	 */
	public function schoolWaiverSuccess(string $encBookingId)
	{
		$successData = session('waiver_success');

		if (!$successData) {
			// No flash data = direct access or page refresh — send back to the form
			return redirect()->route('customer.school-waiver-form', ['bookingId' => $encBookingId]);
		}

		try {
			$bookingId = Crypt::decryptString($encBookingId);

			$booking = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $bookingId)
				->first([
					'tbm.id AS booking_id',
					'tbm.customer_id',
					'tp.name AS tour_package',
					't_o.name AS tour_operator',
					'tsd.date AS tour_date',
				]);

			if (!$booking) {
				abort(404, 'No booking found.');
			}

			return view('school-waiver-success', [
				'page_title'   => 'Waiver Signed Successfully',
				'encBookingId' => $encBookingId,
				'firstName'    => $successData['firstName'],
				'lastName'     => $successData['lastName'],
				'parentEmail'  => $successData['parentEmail'],
				'referenceId'  => $successData['referenceId'],
				'submittedAt'  => $successData['submittedAt'],
				'booking'      => $booking,
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			abort(500, 'Unable to load the waiver confirmation page.');
		}
	}

	/**
	 * Check if tenant is exist or not.
	 *
	 * @param string $tenantId
	 */
	public function checkTenantExist($tenantId, $tenantInfo = "N")
	{
		try {
			$tenant = Tenant::find($tenantId);

			if ($tenant) {
				$info = null;
				if ($tenantInfo != "N") {
					$info = AuthUtils::tenantPublicInfo($tenant);
				}

				return response()->json([
					'code' => 200,
					'message' => "Workspace found.",
					'id' => $tenant->id,
					'info' => $info
				]);
			} else {
				throw new Exception("No workspace found.");
			}
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Trip Advisor booking PDF
	 *
	 * @param string $encBookingId
	 */
	public static function tripAdvisorBookingPdf(string $encBookingId)
	{
		try {
			$bookingId = Crypt::decryptString($encBookingId);

			$bookingM = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->leftJoin('tour_promotions AS promo', 'promo.id', '=', 'tbm.tour_promotion_id')
				->where('tbm.id', $bookingId)
				->first([
					'tbm.id',
					'tbm.tourists',
					'tbm.status',
					'tbm.total',
					'tbm.note',
					'tbm.discount',
					'tbm.discount2_value',
					'tbm.discount2_percentage',
					'tbm.service_commission',
					'tbm.created_at',
					'tp.name AS tour_package',
					'tp.package_has_slots',
					'tp.package_has_permit',
					't_o.name AS tour_operator',
					't_o.cancellation_within_days',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.logo',
					't_o.timezone',
					't_o.tenant_id',
					'tsd.date',
					'tst.time_from',
					'tst.time_to',
					'tbm.name AS customer',
					'tbm.email AS cust_email',
					'tbm.phone_number AS cust_phone',
					'promo.discount_value',
					'promo.discount_value_type',
					'tbm.booking_total',
					'tbm.is_group_booking',
					'tbm.is_partner_booking'
				]);

			if (!$bookingM) {
				abort(404, "No booking found with the supplied id.");
			}

			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $bookingId)
				->orderByDesc('tpr.age_from')
				->orderByDesc('tpr.age_to')
				->get([
					'tbt.id',
					'tbt.tourists',
					'tbt.processing_charge_percentage',
					'tbt.additional_tax_percentage',
					'tbt.permit_fee',
					'tbt.partner_fees_amount',
					'tbt.additional_charge',
					'tbt.state_tax',
					'tbt.rate',
					'tbt.total',
					'tbt.stripe_fees_percentage',
					'tpr.rate_for'
				]);

			if (!$bookingT) {
				abort(500, "Unable to get booking's information.");
			}

			$trans = Transaction::where('tour_booking_id', $bookingId)
				->where('status', 'Completed')
				->orderBy('id', 'DESC')
				->first();

			$HistoryPaymentTime = TourBookingHistory::where('tour_booking_id', $bookingId)
				->where('operation', 'Payment')
				->first();

			if (!empty($HistoryPaymentTime)) {
				$TourBookingHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
					->where('operation_at', '>', $HistoryPaymentTime->operation_at)
					->where(function ($query) {
						return $query->where('operation', '=', 'Refund')
							->orWhere('operation', '=', 'Cancelled');
					})->get();
			}

			$paymentTrans = Transaction::where('tour_booking_id', $bookingId)
				->where('status', 'Completed')
				->get([
					'id',
					'tour_booking_id',
					'amount',
					'fee_amount',
					'transaction_number',
					'application_fee_id',
					'via',
					'payment_type',
					'status'
				]);

			$paymentTransCount = count($paymentTrans);

			$paymentOnly = TourBookingHistory::where('tour_booking_id', $bookingId)
				->where('operation', 'Payment')
				->get();

			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$paymentOnlyCheck += $details->amount;
			}

			$transP = Transaction::where('tour_booking_id', $bookingId)
				->where('status', 'Completed')
				->orderBy('id', 'ASC')
				->first();

			$refundType = null;

			$vars = [
				'page_title' => 'TripAdvisor Booking Info',
				'bookingM' => $bookingM,
				'bookingT' => $bookingT,
				'TourBookingHistory' => $TourBookingHistory ?? '',
				'HistoryPaymentTime' => $HistoryPaymentTime ?? '',
				'trans' => $trans,
				'paymentOnlyCheck' => $paymentOnlyCheck,
				'transP' => $transP,
				'paymentTransCount' => $paymentTransCount,
				'refundType' => $refundType
			];

			if (config('app.env') == "production") {
				$path = 'pdf/' . tenancy()->tenant->id . '/production/tripadvisor/' . $bookingId . '-viator-booking.pdf';
			} else if (config('app.env') == "staging") {
				$path = 'pdf/' . tenancy()->tenant->id . '/staging/tripadvisor/' . $bookingId . '-viator-booking.pdf';
			} else if (config('app.env') == "development") {
				$path = 'pdf/' . tenancy()->tenant->id . '/development/tripadvisor/' . $bookingId . '-viator-booking.pdf';
			}

			if (config('app.env') == "local") {
				return url('/') . '/tour-booking-info/' . Crypt::encryptString($bookingId);
			} else {
				if (!Storage::disk('s3')->exists($path)) {
					$pdf = PDF::loadView('customer.viator-booking-info', $vars);
					$content = $pdf->download()->getOriginalContent();
					Storage::disk('s3')->put($path, $content);
				}

				$pdfInvoiceFile = Storage::disk('s3')->url($path);
				return $pdfInvoiceFile;
			}
		} catch (Exception $exception) {
			Log::error($exception);
			return $exception->getMessage();
		}
	}

	/**
	 * Generate|Create Payment Links For Customer
	 *
	 * @param string $token
	 */
	public static function generatePaymentLink($token)
	{
		try {
			return PaymentLinkService::createPaymentLink($token);
		} catch (Exception $exception) {
			Log::error($exception);
			return $exception->getMessage();
		}
	}

	/**
	 * Save Payment Paid By Customer Via Payment Link
	 *
	 * @param Request $request
	 */
	public static function paymentLinkSavePayment(Request $request)
	{
		try {
			return PaymentLinkService::savePayment($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return $exception->getMessage();
		}
	}

	/**
	 * Success Page After Payment Made By Customer Via Payment Link
	 *
	 * @param Request $request
	 * 
	 */
	public static function paymentLinkSuccess(Request $request)
	{
        DB::beginTransaction();
		try {
			$vars = [
				'page_title' => 'Payment Link'
			];
			
			$data = PaymentLinkService::savePaymentUpdateBooking($request);
			$vars['tour_booking_id'] = Crypt::encryptString($data['bookingID']);

			$vars['cart_id'] = null;
			if ($data['cartId']) {
				$vars['cart_id'] = Crypt::encryptString($data['cartId']);
			}
			
			return view('customer.payment-link', $vars);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return $exception->getMessage();
		}
	}

	/**
	 * Get the tour booking info for combo booking payment link
	 * @param string $encCartId
	 * @throws \Exception
	 * @return \Illuminate\Contracts\View\View
	 */
	public function paymentLinkBourBookingInfo(string $encCartId)
	{
		try {
			
			$cartId = Crypt::decryptString($encCartId);
			$vars = BookingService::comboBookingInvoiceDetails($cartId);

			return view('users.common.combo-tour-booking-info', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Tip payment page when customer scan QR code
	 *
	 * @param String $guideId
	 * 
	 */
	public static function payTip($guideId)
	{
		try {
			$tourOperator = TourOperator::select('id', 'stripe_commission_percentage', 'tenant_id')->first();
			$guideId = Crypt::decrypt($guideId);
			$guideInfo = DB::table('tour_operator_staff as tos')
				->join('model_has_roles as mhr', 'mhr.model_id', '=', 'tos.id')
				->join('roles as r', 'r.id', '=', 'mhr.role_id')
				->where('mhr.model_type', 'App\Models\Staff')
				->where('r.name', 'tour-guide')
				->where('tos.id', $guideId)
				->first(['tos.id', 'tos.first_name', 'tos.last_name', 'profile_photo_path']);
			$guide = $guideInfo ?? abort(404, 'Tour guide information not found.');

			$vars = [
				'page_title' => 'Tip Payment',
				'tourOperator' => $tourOperator,
				'guideId' => $guideId,
				'guide' => $guide
			];
			return view('customer.tip-payment', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return $exception->getMessage();
		}
	}
	/**
	 * Tip payment page when customer clicks link from tip reminder email
	 * 	 
	 * @param String $bookingId
	 * 
	 */
	public static function giveTip($bookingId)
	{
		try {
			$tourOperator = TourOperator::select('id', 'stripe_commission_percentage', 'tenant_id')->first();
			$bookingId = Crypt::decrypt($bookingId);
			$tourBooking = TourBooking::find($bookingId);
			if (!$tourBooking) {
				abort(404, 'Tour booking not found.');
			}

			$guidesInfo = DB::table('booking_tour_guides as btg')
				->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
				->join('model_has_roles as mhr', 'mhr.model_id', '=', 'tos.id')
				->join('roles as r', 'r.id', '=', 'mhr.role_id')
				->where('mhr.model_type', 'App\Models\Staff')
				->where('r.name', 'tour-guide')
				->where('btg.booking_id', $bookingId)
				->first(['tos.id', 'tos.first_name', 'tos.last_name', 'profile_photo_path']);
			$guides = $guidesInfo ?? abort(404, 'Tour guide information not found.');

			$vars = [
				'page_title' => 'Tip Payment',
				'tourOperator' => $tourOperator,
				'guides' => $guides,
				'bookingId' => $bookingId,
				'customerId' => $tourBooking->customer_id,
			];
			return view('customer.post-tip', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return $exception->getMessage();
		}
	}
	/**
	 * Create a payment link for tipping a tour guide
	 *
	 * @param Request $request
	 * 
	 */
	public function createTipPaymentLink(Request $request)
	{
		$amount = $request->tip_amount + $request->tip_fees; // Total amount including fees
		$tourOperator = TourOperator::first();
		$logo = tourOperatorLogo(tenant()->id, $tourOperator->logo);
		$currencyCode = currencyFormation(true, false);
		$guideId = ($request->has('guide_id')) ? $request->guide_id : '';
		$bookingId = ($request->has('booking_id')) ? $request->booking_id : '';
		$customerId = ($request->has('customer_id')) ? $request->customer_id : '';
		$isAffiliate = Customer::where('id', $customerId)->where('is_affiliate', 'Yes')->first();


		$productData = [
			'name' => 'Tip for Tour Guide',
			'description' => 'Thank you for your service!',
			'images' => [$logo],
		];

		// Tips are explicitly excluded from Payout Simplification — always DESTINATION.
		$paymentIntentData = app(PaymentGatewayInterface::class)->buildConnectedPaymentData(
		    $tourOperator->stripe_destination_id,
		    (int) round($request->tip_fees * 100),
		    mode: PaymentModeResolver::DESTINATION
		);

		// Apply card save logic
		if (!hasSetting('DISABLE_CARD_SAVE')) {

		    $allowAffiliateCardSave = !$isAffiliate ||
		        ($isAffiliate && !hasSetting('DISABLE_AFFILIATE_CARD_SAVE'));

		    if ($allowAffiliateCardSave) {
		        $paymentIntentData['setup_future_usage'] = 'off_session';
		    }
		}

		// Create session
		$sessionParams = [
		    'payment_method_types' => ['card'],
		    'line_items' => [[
		        'price_data' => [
		            'currency' => ($currencyCode != '') ? $currencyCode : 'usd',
		            'product_data' => $productData,
		            'unit_amount' => $amount * 100,
		        ],
		        'quantity' => 1,
		    ]],
		    'mode' => 'payment',
		    'success_url' => route('customer.success-tip-payment') . '?session_id={CHECKOUT_SESSION_ID}',
		    // Tips are explicitly excluded from Payout Simplification — always DESTINATION.
		    'payment_intent_data' => stripOnBehalfOfForCountry($paymentIntentData, PaymentModeResolver::DESTINATION),
		    'metadata' => [
		        'tenant' => tenancy()->tenant->name,
		        'tenant_id' => tenancy()->tenant->id,
		        'description' => 'Tip for Tour Guide',
		        'guide_id' => $guideId,
		        'booking_id' => $bookingId,
		        'customer_id' => $customerId,
		        'tip_amount' => $request->tip_amount,
		        'tip_fees' => $request->tip_fees,
		        'message' => $request->message ?? '',
		    ],
		    'expires_at' => time() + 1800,
		];
		$session = app(PaymentGatewayInterface::class)->createCheckoutSession($sessionParams);

		return redirect($session->url);
	}

	/**
	 * Success Page After Tip Payment Made By Customer Via Payment Link
	 *
	 * @param Request $request
	 * 
	 */
	public static function tipPaymentSuccess(Request $request)
	{
		DB::beginTransaction();
		try {
			$vars = [
				'page_title' => 'Payment Link'
			];
			PaymentLinkService::saveTipPayment($request);

			return view('customer.tip-payment-success', $vars);
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			return $exception->getMessage();
		}
	}

}
