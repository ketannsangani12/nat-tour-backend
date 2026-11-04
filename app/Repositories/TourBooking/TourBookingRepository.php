<?php

namespace App\Repositories\TourBooking;

use App\Jobs\CartBookingsEmails;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\PackageAffiliateAssignment;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\Transaction;
use App\Models\TourPackageRate;
use App\Models\BookingTransact;
use App\Models\Hotel;
use App\Models\BookingRequest;
use App\Models\InvoiceBooking;
use App\Models\TourPromotion;
use App\Models\DiscountHistory;
use App\Models\TourBookingContact;

use App\Repositories\Repository;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Repositories\Notify\SMSNotifications;
use App\Repositories\Agenda\AgendaRepository;

use App\Notifications\TourBooking\TipForCustomer;
use App\Notifications\TourBooking\TipForGuide;

use App\Services\Booking\EditBookingService;
use App\Services\Booking\EmailService;
use App\Services\Booking\PaymentLinkService;
use App\Services\Booking\TransactionService;
use App\Services\Cancel100RefundService;
use App\Services\CancelDontRefundService;
use App\Services\CancelFullRefundService;
use App\Services\CancelPartialRefundService;

use App\Services\CardBookingService;
use App\Services\Cart\CartService;
use App\Services\CashBookingService;
use App\Services\InvoicedBookingService;

use App\Services\DeletePerson100RefundService;
use App\Services\DeletePersonDontRefundService;
use App\Services\DeletePersonFullRefundService;
use App\Services\DeletePersonPartialRefundService;
use App\Services\PermitFeeRefundBookingService;
use App\Services\RefundBookingService;

use App\Services\Calendar\CalendarService;
use App\Services\Customer\CustomerService;
use App\Services\Staff\StaffService;
use App\Services\Tax\TaxService;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\TimeSlot\TouristService;
use App\Services\TourOperator\TourOperatorService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;

use App\Services\Booking\BookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\PrintService;
use App\Services\Booking\StripeService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\BundlePackageBookingService;
use App\Services\Booking\PackageHasContactsService;

use App\Services\CancelBooking\DontRefundCancelService;
use App\Services\CancelBooking\PartialRefundCancelService;
use App\Services\CancelBooking\TicketRefundCancelService;
use App\Services\CancelBooking\WeatherRefundCancelService;

use App\Services\ExtendBooking\ExtendBookingCardService;
use App\Services\ExtendBooking\ExtendBookingCashService;
use App\Services\ExtendBooking\ExtendBookingInvoicedService;
use App\Services\ExtendBooking\ExtendBookingPayLaterService;

use App\Services\SwitchTourPackage\SwitchTourPackageInvoicedService;
use App\Services\SwitchTourPackage\SwitchTourPackageCashService;
use App\Services\SwitchTourPackage\SwitchTourPackageCardService;
use App\Services\SwitchTourPackage\SwitchTourPackagePayLaterService;
use App\Services\SwitchTourPackage\SwitchTourPackagePaymentLinkService;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\CardException;

use Carbon\Carbon;
use App\Constants\Constants;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\PlatformFeeMetadata;
use App\Events\BookingNew;
use App\Events\BookingRequestNotification;
use Illuminate\Http\Request;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\SurchargeDate;
use App\Models\TourBookingForms;
use App\Services\Booking\DiscountService;
use App\Utils\BookingUtils;
use App\Models\TourSlotTime;
use App\Models\TourDateSlot;
use App\Services\Calendar\CalendarQueryService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Transformers\ApiRequestTransformer;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\RemainingPayment\RemainingPaymentService;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Utils\CalendarWeekFilterUtils;
use App\Utils\StripeUtils;
use App\Utils\StrUtils;
use Illuminate\Support\Facades\Auth;

class TourBookingRepository extends Repository
{
	use DetectsConcurrencyErrors;

	/**
	 * @var string
	 */
	public $customer = Customer::class;

	public $tourOperator = TourOperator::class;

	public $admin = Admin::class;

	public $staff = Staff::class;

	public $tourBooking = TourBooking::class;

	public $transaction = Transaction::class;

	public $tourPackage = TourPackage::class;

	public $tourSlotTime = TourSlotTime::class;

	public $tourDateSlot = TourDateSlot::class;

	public $tourBookingHistory = TourBookingHistory::class;

	public $tourPackageRate = TourPackageRate::class;

	public $bookingTransact = BookingTransact::class;

	public $invoiceBooking = InvoiceBooking::class;

	public $promotion = TourPromotion::class;

	public $hotel = Hotel::class;

	public $customRateDate = SurchargeDate::class;

	public $discountHistory = DiscountHistory::class;

	public $tourBookingContact = TourBookingContact::class;

	protected $tourSlotRepository;

	protected $tourBookingFormsRepository;

	protected $agendaRepository;

	protected $smsNotifications;

	protected ApiRequestTransformer $transformer;

	/**
	 * @param  TourSlotRepository  $tourSlotRepository
	 * @param  TourBookingFormsRepository  $tourBookingFormsRepository
	 * @param  AgendaRepository  $agendaRepository
	 * 
	 * @version 1.0.0
	 */
	public function __construct(TourSlotRepository $tourSlotRepository, TourBookingFormsRepository $tourBookingFormsRepository, AgendaRepository $agendaRepository, SMSNotifications $smsNotifications, ApiRequestTransformer $transformer)
	{
		$this->tourSlotRepository = $tourSlotRepository;
		$this->tourBookingFormsRepository = $tourBookingFormsRepository;
		$this->agendaRepository = $agendaRepository;
		$this->smsNotifications = $smsNotifications;
		$this->transformer = $transformer;

		Stripe::setApiKey(getStripeSecretKey());
	}

	/**
	 * Initialize the booking process
	 * TODO: Need to optimize this method in future
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function initBooking(Request $request): array
	{
		try {
			$userType = userType();
			$cartView = hasSetting('CART_VIEW');
			$cashFees = hasSetting('CASH_FEES');
			$hotelPickup = hasSetting('HOTEL_PICKUP');
			$customCharge = hasSetting('CUSTOM_CHARGE');

			$tourOperator = TourOperatorService::getTourOperator();
			$managers = BookingService::getManagers();
			$requestPermissions = BookingService::requestPermissions(Auth::id());
			$managerRequestEnable = BookingService::managerRequestEnable(Auth::id());
			$paymentMethodsEnabled = ($userType != 'Admin') ? paymentMethodsEnabled() : '';

			$vars = [
				'page_title' => 'Book Tour Package',
				'slotTimes' => [],
				'rateGroups' => [],
				'tourOperatorId' => $tourOperator->id,
				'tourPackageId' => 0,
				'date' => date('Y-m-d'),
				'uType' => $userType,
				'touristsGroups' => [],
				'timeSlotId' => 0,
				'serviceCommissionPercentage' => 0,
				'affiliateProcessingPercentage' => 0,
				'cartView' => $cartView ? "1" : "0",
				'cashFees' => $cashFees ? "1" : "0",
				'hotelPickup' => $hotelPickup,
				'customCharge' => $customCharge ? "1" : "0",
				'managers' => $managers,
				'requestPermissions' => $requestPermissions,
				'managerRequestEnable' => $managerRequestEnable,
				'paymentOptions' => $paymentMethodsEnabled, // blank means all options card|cash|pay later
				'defaultBookingSource' => getDefaultBookingSource(),
			];

			$vars['paymentacknowledgement'] = '';
			if ($request->payment_intent_client_secret && $request->payment_intent) {
				if ($cartView) {
					$paymentStatus = CartService::completeSecureCardPaymentForCart($_GET);
				} else {
					$paymentStatus = CartService::completeSecureCardPayment($_GET);
				}

				$paymentAcknowledgement = $paymentStatus;
				$vars['paymentacknowledgement'] = $paymentAcknowledgement;

				session()->forget('cart');
				session()->save();
			}

			$cart = CartService::getCartData();
			$vars = CartService::calculateCartTotals($cartView, $cart, $vars);

			$vars['cart'] = $cart;

			// get date based on timezone of the tenant
			$vars['currenttime'] = Carbon::now($tourOperator->timezone)->format('Y-m-d H:i:s');
			$convertedDate = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now(), config('app.timezone'))->setTimezone($tourOperator->timezone)->format('Y-m-d');
			if ($convertedDate != '') {
				$vars['date'] = $convertedDate;
			}

			// To change the status of the slot bookable status
			TimeSlotService::updateSlotStatus($vars['date']);

			$timeSlotId = $request->time_slot_id ?? '';
			// booking from agenda page based on slot
			if ($request->time_slot_id) {
				$vars = BookingService::bookFromAgenda($request, $vars);
			}

			// get tout packages
			$vars['tourPackages'] = TourPackageService::getTourPackages();
			// get tout staff
			$vars['staff'] = StaffService::getStaff();
			// get tout taxes
			$vars['taxes'] = TaxService::getTaxes();
			// get affiliates
			$vars['affiliate'] = CustomerService::getCustomers();

			$customerId = $request->customer_id ?? '';
			$bookingId = $request->booking_id ?? '';

			// new booking for contact or rebook
			if ($request->customer_id && $request->booking_id) {
				$vars = BookingService::bookFromRebook($request, $vars);
			}

			// affiliate booking
			if ($userType == 'Affiliate') {
				$vars = TourPackageRateService::getRateGroups($request, $vars, $vars['date'], $customerId, $bookingId, $timeSlotId);
			}

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Add package to Cart
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function addToCart($request)
	{
		try {
			$totalTourists = 0;
			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$totalTourists += $request->tourists[$a];
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one adult.');
			}

			// check the min and max pax allowed feature allowed in backend or not
			// check the min and max pax allowed feature allowed in backend or not
			$isGroupRate = TourPackageService::validateMinPaxAndGetRateType(
				$request->tourists,
				$request->rate_group
			);

			$userType = userType();

			$info = TourPackageService::getTourPackageSlotInfo($request->slot_time);

			//stopNextYearBookings($info->date);

			// BookingUtils::checkAffiliateReservation($request, $info);
			BookingUtils::checkBookingTimeValidity($request, $info);

			$rateGrpIds = [];
			$permitfeeAr = [];
			$touristsAr = [];
			for ($a = 0, $c = count($request->tourists); $a < $c; $a++) {
				if ($request->tourists[$a]) {
					$touristsAr[] = $request->tourists[$a];
					$x = $a;
					$rateGrpIds[] = $request->rate_group[$a];
					// Affiliates cannot set the permit fee: force the rate's value (server source of truth),
					// which blocks the client from injecting a stale/wrong permit fee. Tour Operators/Admins
					// may legitimately waive the fee, so keep their posted value.
					$permitfeeAr[] = (userType() === 'Affiliate')
						? (float) (DB::table('tour_package_rates')->where('id', $request->rate_group[$a])->value('permit_fee') ?? 0)
						: ($request->permit_fee[++$x] ?? 0);
				}
			}

			$rateGroups = BookingUtils::getRateGroups($rateGrpIds);

			$commissionRates = TourPackageService::getCommissionRates($request->affiliatecust, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

			$total = $discountAmt = $serviceCommissionTotal = $subtt = 0;

			$result = BookingService::calculateBooking($request, $rateGroups, $natFeesPercentage, $stripeFeesPercentage, $isGroupRate, $permitfeeAr, $userType, true);
			$subtt = $result['subtt'];
			$total = $result['total'];
			$serviceCommissionTotal = $result['serviceCommissionTotal'];
			$touristGroups = $result['touristGroupsForCart'];

			$discountData = DiscountService::calculateDiscountForCart($request, $total, 0, $info);
			$discountPercentage = $discountData['discount_percentage'];
			$discountAmt = $discountData['discount_amount'];

			$total -= $discountAmt;

			if ($request->has('discount_type')) {
				$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
			} else {
				$serviceCommission = $serviceCommissionTotal;
			}

			$total += $serviceCommission;
			$total = round($total, 2);

			$tourPackageId = $info->tour_package_id;
			$cart = session()->get('cart', []);
			$addedInCart = false;
			$cartIndex = '';

			$cart['customer_details'] = [
				'affiliatecust' => $request->affiliatecust,
				'customer_name' => $request->customer_name,
				'customer_phone_number' => $request->customer_phone_number,
				'customer_email' => $request->customer_email
			];

			if (!empty($cart) && !empty($cart['tour_packages'])) {
				$cart['tour_packages'] = array_values($cart['tour_packages']);
				foreach ($cart['tour_packages'] as $k => $item) {
					if ($item['tour_package_id'] == $tourPackageId) {
						$addedInCart = true;
						$cartIndex = $k;
					}
				}
			}

			$discountPromoCode = '';
			if ($request->has('discount_type') && $request->discount_type == 'Promo Code') {
				$discountPromoCode = $request->discount_promo_code;
				$promotion = $this->promotion::where('code', $discountPromoCode)
					->where('tour_package_id', $tourPackageId)
					->first();
			}

			// Only when custom charge is added
			$customChargeData = $request->custom_charge_data;
			$chargeDetails = CartService::calculateCustomCharge($customChargeData, $natFeesPercentage);

			$customChargeFee = $chargeDetails['customChargeFee'];
			$totalCustomCharge = $chargeDetails['totalCustomCharge'];
			$customChargeAmount = $chargeDetails['customChargeAmount'];

			// Store contacts when package has_contacts enabled
			if ($info->has_contacts == 1) {
				if ($request->contact_data != "") {
					$contactData = $request->contact_data;
				}
			}

			$additionalData = [
				'is_group_rate' => $isGroupRate,
				'permit_fee' => $permitfeeAr,
				'rate_group' => $rateGrpIds,
				'tourists' => $touristsAr,
				'tourist_groups' => $touristGroups,
				'subtt' => $subtt,
				'service_commission' => $serviceCommission,
				'total' => $total,
				'discount_amount' => $discountAmt,
				'discount_percentage' => $discountPercentage,
				'custom_charge' => $customChargeAmount,
				'custom_charge_fees' => $customChargeFee,
				'custom_charge_total' => $totalCustomCharge,
				'discount_promo_code' => $discountPromoCode,
				'tour_promotion_id' => isset($promotion) ? $promotion->id : 0,
				'contact_data' => $contactData ?? ''
			];
			// Prepare tour package data
			$tourPackageData = CartService::preparePackageData($request, $tourPackageId, $info, $additionalData);

			// Update existing package or add a new one
			if (!empty($cart['tour_packages']) && $addedInCart && $cartIndex !== '' && isset($cart['tour_packages'][$cartIndex])) {
				$cart['tour_packages'][$cartIndex] = $tourPackageData;
			} else {
				$cart['tour_packages'][] = $tourPackageData;
			}
			$cart = CartService::calculateAddonPrices($cart);

			session()->put('cart', $cart);
			$cart = session()->get('cart');

			return [
				'cart' => $cart
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Edit Cart
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function editCart($request)
	{
		try {
			$cartPackageId = $request->tourPackageId;
			$cart = session()->get('cart');
			$cartPackageDetails = [];
			if (!empty($cart) && !empty($cart['tour_packages'])) {
				foreach ($cart['tour_packages'] as $k => $item) {
					if ($item['tour_package_id'] == $cartPackageId) {
						$cartPackageDetails = $item;
					}
				}
			}

			return [
				'cartPackageDetails' => $cartPackageDetails,
				'customerDetails' => $cart['customer_details']
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Remove package from Cart
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function removeFromCart($request)
	{
		try {
			$cartPackageId = $request->tourPackageId;
			$cart = session()->get('cart');
			if (!empty($cart) && !empty($cart['tour_packages'])) {
				foreach ($cart['tour_packages'] as $k => $item) {
					if ($item['tour_package_id'] == $cartPackageId) {
						unset($cart['tour_packages'][$k]);
					}
				}
			}
			if (empty($cart['tour_packages'])) {
				unset($cart['customer_details']);
			}
			session()->put('cart', $cart);
			$cartPackages = session()->get('cart');
			array_values($cartPackages);

			return [
				'cartPackages' => $cartPackages
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Create new booking
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function createBooking($request)
	{
		DB::beginTransaction();
		try {
			if (isset($request->api) && $request->api == 1) {
				$this->transformer->transformRequestData($request);
			}

			$isReserveForAffiliate = $request->is_reserved_for_affiliate ?? 0;
			$totalTourists = 0;
			$totalInfants = 0;

			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$rateGrp = $request->rate_group[$a];
				$checkIsInfantRate = TourPackageRateService::checkIsInfantRate($rateGrp);
				$totalTourists += ($checkIsInfantRate != 1) ? $request->tourists[$a] : 0;
				$totalInfants += ($checkIsInfantRate == 1) ? $request->tourists[$a] : 0;
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one adult.');
			}

			// check the min and max pax allowed feature allowed in backend or not
			$isGroupRate = TourPackageService::validateMinPaxAndGetRateType(
				$request->tourists,
				$request->rate_group
			);

			$userType = userType();
			$isCashFees = cashFees();
			$isCustomCharge = customCharge();
			$info = TourPackageService::getTourSlotInfo($request->slot_time, $userType);
			if (!$info) {
				throw new Exception('Unable to book the tour package.');
			}

			//stopNextYearBookings($info->date);
			// BookingUtils::checkAffiliateReservation($request, $info);
			BookingUtils::checkBookingTimeValidity($request, $info);
			BookingUtils::checkAffiliateDurationRestriction($info);

			$rateGrpIds = [];
			$permitfeeAr = [];

			for ($a = 0, $c = count($request->tourists); $a < $c; $a++) {
				if ($request->tourists[$a]) {
					$x = $a;
					$rateGrpIds[] = $request->rate_group[$a];
					// Affiliates cannot set the permit fee: force the rate's value (server source of truth),
					// which blocks the client from injecting a stale/wrong permit fee. Tour Operators/Admins
					// may legitimately waive the fee, so keep their posted value.
					$permitfeeAr[] = (userType() === 'Affiliate')
						? (float) (DB::table('tour_package_rates')->where('id', $request->rate_group[$a])->value('permit_fee') ?? 0)
						: ($request->permit_fee[++$x] ?? 0);
				}
			}

			$rateGroups = BookingUtils::getRateGroups($rateGrpIds);

			$customerData = BookingUtils::getOrCreateCustomer(
				$request->affiliatecust,
				$request->customer_email,
				$request->customer_name,
				$request->customer_phone_number,
				$info->tour_operator_id
			);

			$customer = $customerData['customer'];
			$newCustomer = $customerData['isNewCustomer'];
			$pwd = $customerData['pwd'];

			$commissionRates = TourPackageService::getCommissionRates($request->affiliatecust, $info);
			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

			$discountAmt = 0;

			// Calculate booking total and transact data
			$result = BookingService::calculateBooking($request, $rateGroups, $natFeesPercentage, $stripeFeesPercentage, $isGroupRate, $permitfeeAr, $userType);
			$total = $result['total'];
			$serviceCommissionTotal = $result['serviceCommissionTotal'];
			$tbTransact = $result['tbTransact'];
			$touristGroups = $result['touristGroups'];
			$tbTransaction = $result['tbTransaction'];

			if ($request->custom_charge_fees == null) {
				$request->custom_charge_fees = 0.00;
			}

			//Handle change amount for cash payment
			if ($request->payment_method == "Cash") {
				BookingUtils::handleCashPayment($request, $isCashFees);
			}

			// Apply discount if applicable
			if ($request->has('discount_type')) {
				$discountAmt = BookingService::calculateDiscountForCreateBooking($request, $total, $info);
			}

			$total -= $discountAmt;
			if ($request->has('discount_type')) {
				$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
			} else {
				$serviceCommission = $serviceCommissionTotal;
			}

			/* START: critical code */
			if (($request->custom_fields && trim($request->custom_fields) != "") || ($request->custom_charge_data && trim($request->custom_charge_data) != "")) {
				$addonFees = ($request->addons * $natFeesPercentage) / 100;
				$request->fee = $request->fee - $request->custom_charge_fees - $addonFees;
			}

			$total = $request->subtt;
			$serviceCommission = $request->fee;
			$tt = $request->subtt + $request->fee;
			/* END: critical code */

			$total += $serviceCommission;
			$total = round($total, 2);

			/* START : code for get expire at */
			$customerId = $request->affiliatecust == 0 ? $customer->id : $request->affiliatecust;
			$expiryDate = BookingService::calculateExpiryDate($info->tour_package_id, $customerId);
			/* END : code for get expire at */

			/*Tour promotion detail */
			$promoDetail = '';
			if (isset($request->discount_promo_code)) {
				$promoDetail = $this->promotion::where('code', $request->discount_promo_code)
					->where('tour_package_id', $info->tour_package_id)
					->first();
			}

			$tourBooking = $this->tourBooking::create([
				'tour_package_id' => $info->tour_package_id,
				'tour_slot_id' => $request->slot_time,
				'customer_id' => $request->affiliatecust == 0 ? $customer->id : $request->affiliatecust,
				'tour_promotion_id' => !empty($promoDetail) ? $promoDetail->id : 0,
				'hotel_id' => $request->hotel_id ?? 0,
				'tourists' => $totalTourists,
				'infants' => $totalInfants,
				'is_reserved_for_affiliate' => $isReserveForAffiliate,
				'name' => $request->customer_name,
				'email' => $request->customer_email,
				'phone_number' => $request->customer_phone_number,
				'comments' => $request->customer_comment,
				'note' => ($request->has('tour_operator_note')) ? $request->tour_operator_note : '',
				'note_datetime' => empty($request->tour_operator_note) ? null : mysqlDT(),
				'mask_required' => 'Yes',
				'discount2_value' => $discountAmt,
				'discount2_percentage' => empty($request->discount_percentage) ? 0 : $request->discount_percentage,
				'discount_promo_code' => isset($request->discount_promo_code) ? $request->discount_promo_code : '',
				'service_commission' => ($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $request->fee : 0,
				'total' => ($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $tt : $request->subtt,
				'booking_total' => ($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $tt : $request->subtt,
				'status' => 'Booked',
				'is_group_booking' => ($isGroupRate == '1') ? 1 : 0,
				'visitor' => $request->getClientIp(),
				'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
				'expire_at' => $expiryDate,
				'created_by' => empty($request->booked_by_staff) ? Auth::id() : $request->booked_by_staff,
				'created_by_user_type' => empty($request->booked_by_staff) ? $userType : 'Tour Operator',
				'updated_at' => null,
			]);
			DB::commit();
			if (!empty($request->customer_comment)) {
				HistoryService::storeCommentInHistory($request->customer_comment, $tourBooking->id);
			}

			for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
				$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
			}

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			// Close the time slot on booking
			TimeSlotService::closeSlotOnBooking($tourBooking->tour_package_id, $tourBooking->tour_slot_id);

			/* Execute only when package has contacts feature enabled - START*/
			if ($info->has_contacts == 1) {
				if ($request->contact_data != "") {
					try {
						PackageHasContactsService::storeTourBookingPeople($tourBooking->id, $request->contact_data);
					} catch (Exception $exception) {
						Log::error($exception->getMessage());
					}
				}
			}
			/* Execute only when package has contacts feature enabled - END*/

			$created_by_with_name = BookingService::createdUserInfo();

			/* START - Added this to place the history of person added */
			$addedPeoplesText = array();
			if (!empty($tbTransaction)) {
				foreach ($tbTransaction as $tblTransaction) {
					$addedPeoplesText[] = ($isGroupRate == '1') ? 'Person(s) ' . $tblTransaction['tourists'] : $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
				}
			}

			HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTourists + $totalInfants, $created_by_with_name);
			/* END - Added this to place the history of person added */

			/* Save custom Addon Fields */
			$addonPrice = $addonFee = $addonTotal = 0;
			if ($request->custom_fields && trim($request->custom_fields) != "") {
				try {
					$addons = json_decode(trim($request->custom_fields), true);
					foreach ($addons['fields'] as $key => &$addon) {
						if ($addon['priceInfo']['enabled'] == 'true') {
							$addonPrice += $addon['priceInfo']['price'];
							if ($request->payment_method === 'Card' || ($isCashFees == 1 && $request->payment_method == 'Cash')) {
								$addonFee += $addon['priceInfo']['fee'];
								$addonTotal += $addon['priceInfo']['price'] + $addon['priceInfo']['fee'];
							} else {
								$addon['priceInfo']['fee'] = 0;
								$addonTotal += $addon['priceInfo']['price'];
							}
						}
					}

					$this->tourBookingFormsRepository->insertBookingForm($addons, $tourBooking->id);
				} catch (Exception $e) {
					Log::error($e->getMessage());
				}
			}
			/* End Save custom Addon Fields */

			/* Store Custom Charge Start */
			$customChargeDetails = [];
			$customChargeAmount = 0;
			$customChargeFees = 0;

			if ($request->custom_charge_data != "") {
				try {
					$customData = json_decode($request->custom_charge_data, true);
					$customChargeDetails = $customData['custom_data'];
					$customChargeAmount = ($customChargeDetails['custom_charge_amount'] > 0) ? $customChargeDetails['custom_charge_amount'] : 0;
					$customChargeFees = $request->custom_charge_fees;
					$customChargeDetails['amount'] = $customChargeAmount;
					$customChargeDetails['fees'] = $customChargeFees;
					unset($customChargeDetails['custom_charge_amount']);

					CustomChargeBookingService::tourBookingCustomCharge($tourBooking->id, $customChargeDetails, null, 'Pay Later', 'Open');
					DB::commit();
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}
			/* Store Custom Charge End */

			if ($request->payment_method == 'Cash') {
				// Adjust split value if a custom charge is applied
				if (!empty($request->split_value) && $customChargeAmount > 0) {
					$request->split_value -= $customChargeAmount;
				}
				$cashPaymentDetails = BookingService::calculateCashPaymentForCreateBooking(
					$request,
					$customChargeAmount,
					$customChargeFees,
					$addonPrice,
					$addonFee,
					$tt,
					$request->subtt,
					$isCashFees,
					$stripeFeesPercentage,
					$serviceCommission
				);
				$cashPaidAmount = $cashPaymentDetails['cashPaidAmount'];
				$changeAmount = $cashPaymentDetails['changeAmount'];
				$feeAmountCash = $cashPaymentDetails['feeAmountCash'];
				$cashAmount = $cashPaymentDetails['cashAmount'];

				$transAmount = !empty($request->split_value) ? $request->split_value : $cashAmount;
				$paymentType = !empty($request->split_value) ? 1 : 0;

				$transactionId = TransactionService::storePaymentInTransaction($tourBooking->id, $transAmount, $feeAmountCash, 'Cash', $paymentType, null, null);
				DB::commit();

				$data = [
					'amount' => !empty($request->split_value) ? $request->split_value : $cashAmount,
					'payment_method' => !empty($request->split_value) ? 'Split Payment' : 'Full Payment',
					'discount_promo_code'	=> isset($request->discount_promo_code) ? $request->discount_promo_code : ''
				];

				if (($customChargeAmount > 0 || $addonPrice > 0) && $cashPaidAmount > 0) {
					$data['custom_charge_amount'] = $customChargeAmount;
					$data['addon_price'] = $addonPrice;
					$data['cash_paid_amount'] = $cashPaidAmount;
				}

				$historyId = HistoryService::storeCashPaymentInHistory($tourBooking->id, $tourBooking->tourists + $tourBooking->infants, $data, $changeAmount, $created_by_with_name);

				if (!empty($request->tour_operator_note)) {
					HistoryService::storeNoteInHistory($request->tour_operator_note, $tourBooking->id);
				}

				// Below line is for to update tour booking history table
				$isSplitOrFull = !empty($request->split_value) ? 'Split Payment' : 'Full Payment';
				if ($isCashFees == '1') {
					$bookingData = BookingUtils::prepareBookingDetails(
						$tourBooking,
						$request,
						$info,
						$totalTourists,
						$tt,
						$discountAmt
					);
					CardBookingService::run($bookingData, $transactionId, $historyId, $isSplitOrFull, $request->all());
				} else {
					CashBookingService::run($tourBooking, $transactionId, $historyId, $isSplitOrFull, $request->all());
				}
			}

			DB::commit();
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}

		$errors = [];

		$paymentIntentInfo = [
			'clientSecret' => '',
			'transactionId' => '',
		];
		$bookingNotifInfo = [];

		try {
			$slotInfo = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->where('tst.id', $request->slot_time)
				->when(($userType == 'Tour Operator' || $userType == 'Affiliate'), function ($query) {
					$query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
				})
				->select([
					DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();

			$booking = BookingUtils::prepareBookingData(
				$tourBooking,
				$request,
				$info,
				$slotInfo,
				$touristGroups,
				$totalTourists,
				$isCustomCharge,
				$customChargeAmount,
				$isCashFees,
				$tt
			);

			if ($request->payment_method != 'Card') {
				BookingNew::dispatch($booking);
				$booking['notif_for'] = 'Tour Operator';
				BookingNew::dispatch($booking);
			}

			if ($request->payment_method == 'Card') {
				$bookingNotifInfo = $booking;
			}

			$arEmailData = [
				'tour_d_t' => $booking['tour_d_t'],
				'bookingId' => $booking['id'],
				'bookingGroupId' => $tourBooking->group_id,
				'pwd' => ($newCustomer) ? $pwd : '',
				'custName' => $tourBooking->name,
				'custEmail' => $tourBooking->email,
				'custPhone' => $tourBooking->phone_number
			];

			if ($request->payment_method == 'Cash' || $request->payment_method == 'Pay Later') {
				if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
					EmailService::sendAcknowledgementEmail($info, $arEmailData);
				}
			}

			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);
		} catch (Exception $exception) {
			Log::error($exception);
			$errors[] = 'email';
		}

		$splitCount = 0;
		if ($request->dividedBy != "Please select option") {
			$splitCount = $request->dividedBy;
		}

		if (isset($newCustomer) && $newCustomer) {
			$affiliateCust = "No";
		} else {
			$affiliateCust = $customer->is_affiliate;
		}

		if ($request->payment_method == 'Pay Later') {
			if (!empty($request->tour_operator_note)) {
				HistoryService::storeNoteInHistory($request->tour_operator_note, $tourBooking->id);
			}

			if ($tourBooking->hotel_id) {
				HistoryService::storePickupHotelInHistory($tourBooking, $created_by_with_name);
			}

			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

			return [
				'isAffiliate' => $affiliateCust,
				'bookingId' => $tourBooking->id,
				'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
				'stripeId' => $paymentIntentInfo['clientSecret'],
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors' => $errors,
				'splitCount' => $splitCount,
			];
		}

		$month = substr($request->expiration, 0, 2);
		$yearSet = substr($request->expiration, 3, 2);
		$y = 20;
		$year = $y . '' . $yearSet;
		$cardnumber = $request->cardnumber;
		$cvc = $request->cvv;
		$master = $this->tourBooking::where('id', $tourBooking->id)->first();

		if ($request->payment_method == 'Card') {
			$data = [
				'total' => $tt,
				'discount2_value' => $discountAmt,
				'customer_id' => $master->customer_id,
				'customer_name' => $master->name,
				'customer_email' => $master->email,
				'customer_phone' => $master->phone_number,
				'tourists' => $totalTourists,
				'booking_id' => $tourBooking->id,
				'group_id' => $tourBooking->group_id,
				'tour_package' => $info->package_name,
				'date' => $info->date,
				'time_from' => $info->time_from,
				'subtotal' => $request->subtt,
				'serviceCommission' => $request->fee,
				'month' => $month,
				'year' => $year,
				'cardnumber' => $cardnumber,
				'cvc' => $cvc,
				'destination' => $info->stripe_destination_id,
				'slot_time' => $request->slot_time,
				'tour_operator_id' => $info->tour_operator_id,
				'statement_descriptor' => $info->statement_descriptor,
				'card_reader_prefix' => $info->card_reader_prefix,
				'note' => $request->tour_operator_note,
				'booked_by_staff' => $request->booked_by_staff,
				'split_value' => $request->split_value,
				'api' => $request->api,
				'splitCount' => $request->dividedBy,
				'tour_package_id' => $info->tour_package_id,
				'bookingNotifInfo' => $bookingNotifInfo,
				'addon_price' => $addonPrice,
				'addon_fee' => $addonFee,
				'addon_total' => $addonTotal,
				'custom_charge' => $customChargeDetails,
				'custom_charge_amount' => $customChargeAmount,
				'custom_charge_fees' => $customChargeFees
			];

			if ($request->has('api') && $request->api == '1') {
				$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
				if ($paymentIntentId == '') {
					throw new Exception('You must provide Stripe Payment Intent Id');
				}

				$data['payment_intent_id'] = $paymentIntentId;
				$paymentIntentInfo = $this->createBookingCard($data);
			} else if ($request->has('payment_intent_id') && $request->payment_intent_id != '') {
				if ($request->has('feature') && $request->feature == 'create_booking') {
					$data['payment_intent_id'] = $request->payment_intent_id;
					$data['webhook'] = 0;
					$data['web_manual_card'] = 1;
				} else {
					$data['payment_intent_id'] = $request->payment_intent_id;
					$data['webhook'] = 0;
					//$data['after_capture'] = true;
					$data['web_card_reader'] = 1;
				}
				$paymentIntentInfo = $this->createBookingCard($data);
			} else {
				$paymentIntentInfo = $this->createPaymentIntent($data);
			}

			if (isset($paymentIntentInfo['nextAction'])) {
				$master->stripe_paymentintent_id = $paymentIntentInfo['clientSecret'];
				$master->save();
				return [
					'bookingId' => $tourBooking->id,
					'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
					'stripeId' => $paymentIntentInfo['clientSecret'],
					'require3ds' => 1,
					'nextAction' => $paymentIntentInfo['nextAction'],
					'errors' => $errors,
					'errors_message' => !empty($errors_message) ? $errors_message : '',
				];
			}

			if ($paymentIntentInfo['clientSecret'] == '') {
				$master->booking_total = $master->booking_total - $master->service_commission;
				$master->total = $master->total - $master->service_commission;
				$master->service_commission = 0.00;
				$master->save();
				$errors[] = 'payment';
			}

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
			}
		}

		$splitCount = 0;
		if ($request->dividedBy != "Please select option" && $request->split_value != '') {
			$splitCount = $request->dividedBy;
		}

		return [
			'isAffiliate' => $affiliateCust,
			'bookingId' => $tourBooking->id,
			'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
			'stripeId' => $paymentIntentInfo['clientSecret'],
			'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
			'errors' => $errors,
			'errors_message' => !empty($errors_message) ? $errors_message : '',
			'splitCount' => $splitCount,
		];
	}

	/**
	 * Create Combo booking
	 *
	 * @param object $request
	 *
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function createComboBooking($request)
	{
		DB::beginTransaction();
		$cartPackagesEmailData = array();
		$cardBookingsData = array();

		try {
			if ($request->is('api/*')) {
				$json = $request->json()->all();
				$validator = Validator::make($json, [
					'customer_details' => 'required|array',
					'tour_packages' => 'required|array',
					'customer_details.customer_name' => 'required',
					'customer_details.customer_email' => 'required',
					'tour_packages.*.slot_time' => 'required|integer|min:1',
					'tour_packages.*.tour_package_id' => 'required|integer|min:1',
					'tour_packages.*.permit_fee' => 'required|array',
					'tour_packages.*.rate_group' => 'required|array',
					'tour_packages.*.subtt' => 'required',
					'tour_packages.*.fee' => 'required',
					'tour_packages.*.total' => 'required'
				]);

				if ($validator->fails()) {
					throw new Exception(implode('<br>', $validator->errors()->all()));
				}

				$cart = $json;
			} else {
				$cart = session()->get('cart');
			}


			if (empty($cart) || !isset($cart['tour_packages']) || !isset($cart['tour_packages']) || empty($cart['tour_packages']) || empty($cart['customer_details'])) {
				throw new Exception("Your cart is empty.Please add packages in the cart.");
			}

			$cartPackages = $cart['tour_packages'];
			$cartSlotInfo = CartService::validateCartPackages($cartPackages);
			$tourOperatorId = $cartSlotInfo['tourOperatorId'];

			CartService::validateTouristsAndBookings($cartPackages, $cart, $request);

			$userType = userType();

			$paymentIntentId = $request->has('payment_intent_id') && $request->payment_intent_id != ''
				? $request->payment_intent_id
				: '';

			if (!$cartSlotInfo['validatedCart'] && $paymentIntentId == '') {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}
			$customerDetails = $cart['customer_details'];
			$splitCount = 0;
			if ($request->is('api/*')) {
				$splitValue = !empty($cart['split_value']) ? $cart['split_value'] : '';
			} else {
				$splitValue = !empty($request->split_value) ? $request->split_value : '';
			}

			if (count($cartPackages) == 1) {
				if ($request->dividedBy != "Please select option") {
					$splitCount = $request->dividedBy;
				}
			}

			if (count($cartPackages) > 1 && !empty($splitValue)) {
				throw new Exception("You can do split payment only if single package in the cart.");
			}

			if ($request->is('api/*')) {
				$affiliateCustomer = $customerDetails['affiliatecust'];
				$customerEmail = $customerDetails['customer_email'];
				$customerName = $customerDetails['customer_name'];
				$customerPhoneNumber = $customerDetails['customer_phone_number'];
			} else {
				$affiliateCustomer = $request->affiliatecust;
				$customerEmail = $request->customer_email;
				$customerName = $request->customer_name;
				$customerPhoneNumber = $request->customer_phone_number;
			}

			$customerData = BookingUtils::getOrCreateCustomer(
				$affiliateCustomer,
				$customerEmail,
				$customerName,
				$customerPhoneNumber,
				$tourOperatorId
			);

			$customer = $customerData['customer'];
			$newCustomer = $customerData['isNewCustomer'];
			$pwd = $customerData['pwd'];

			$createdByWithName = BookingService::createdUserInfo();

			if ($request->is('api/*')) {
				$paymentMethod = $cart['payment_method'];
			} else {
				$paymentMethod = $request->payment_method;
			}

			$isCashFees = cashFees();

			if (isset($newCustomer) && $newCustomer) {
				$affiliateCust = "No";
			} else {
				$affiliateCust = $customer->is_affiliate;
			}

			$errors = [];

			$paymentIntentInfo = [
				'clientSecret' => '',
				'transactionId' => '',
			];

			$cartId = Str::random(10) . time();
			$firstBookingId = '';
			$totalCartAmount = 0;
			$totalCartFees = 0;
			$totalCustomCharge = 0;
			$totalCustomChargeFees = 0;
			$totalTipAmount = 0;
			$totalTipFees = 0;
			$i = 0;
			$comboPaymentHistIds = [];

			foreach ($cartPackages as $key => $cartPackage) {
				$slotTime = $cartPackage['slot_time'];
				$bookedByStaff = $cartPackage['booked_by_staff'];
				$tourists = $cartPackage['tourists'];
				$rateGroup = $cartPackage['rate_group'];
				$permitFee = $cartPackage['permit_fee'];
				$hotelId = $cartPackage['hotel_id'] ?? '';
				$isReservedForAffiliate = $cartPackage['is_reserved_for_affiliate'];
				$customerComment = $cartPackage['customer_comment'] ?? '';
				$tourOperatorNote = $cartPackage['tour_operator_note'] ?? '';
				$subtt = $cartPackage['subtt'];
				$fee = $cartPackage['fee'];
				$discountAmt = $cartPackage['discount_amount'];
				$discountType = $cartPackage['discount_type'];
				$discountPercentage = $cartPackage['discount_percentage'];
				$promotionId = $cartPackage['tour_promotion_id'];
				$discountPromoCode = $cartPackage['discount_promo_code'];
				$customChargeAmount = $cartPackage['custom_charge'];
				$customChargeFees = $cartPackage['custom_charge_fees'];
				$tipAmount = $cartPackage['tip_amount'] ?? 0;
				$tipFees = $cartPackage['tip_fees'] ?? 0;
				$isGroupRate = $cartPackage['is_group_rate'] ?? '';
				$isPartnerPackage = $cartPackage['is_partner_package'] ?? '';
				$addonPrice = $cartPackage['addon_price'] ?? 0;
				$addonFee = $cartPackage['addon_fee'] ?? 0;
				$addonTotal = $cartPackage['addon_total'] ?? 0;
				$contactData = $cartPackage['contact_data'] ?? '';

				$info = CartService::getTourSlotInfo($slotTime);

				//stopNextYearBookings($info->date);

				$rateGroupsData = CartService::getRateGroupsForCart($tourists, $rateGroup, $permitFee);
				$rateGroups = $rateGroupsData['rateGroups'];
				$permitFeeArray = $rateGroupsData['permitFeeArray'];

				//Get commission rates for package
				$commissionRates = TourPackageService::getCommissionRates($affiliateCustomer, $info);

				$natFeesPercentage = $commissionRates['nat_fees_percentage'];
				$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];


				$cartPackageData = [
					'cartPackage' => $cartPackage,
					'tourists' => $tourists,
					'rateGroups' => $rateGroups,
					'selectedRateGroups' => $rateGroup,
					'permitfeeAr' => $permitFeeArray,
					'natFeesPercentage' => $natFeesPercentage,
					'stripeFeesPercentage' => $stripeFeesPercentage,
					'paymentMethod' => $paymentMethod,
					'discountAmt' => $discountAmt,
					'discountType' => $discountType
				];

				$cartPackageGroupTotals = CartService::calculateCartPackageGroupTotals($cartPackageData);

				if ($cartPackageGroupTotals['errorMessage'] != '') {
					DB::rollBack();
					throw new Exception('Unable to book the tour package.');
				}

				$tbTransact = $cartPackageGroupTotals['tbTransact'];
				$tbTransaction = $cartPackageGroupTotals['tbTransaction'];
				$total = $cartPackageGroupTotals['total'];
				$partnerFeesTotal = $cartPackageGroupTotals['partnerFeesTotal'];

				$totalTouristsA = $cartPackageGroupTotals['totalTourists'];
				$totalInfants = $cartPackageGroupTotals['totalInfants'];
				$touristGroups = $cartPackageGroupTotals['touristGroups'];

				if ($discountType != '') {
					$serviceCommissionTotal = $fee;
				} else {
					$serviceCommissionTotal = $cartPackageGroupTotals['serviceCommissionTotal'];
				}

				$serviceCommission = $serviceCommissionTotal;
				$total += $serviceCommission;
				$total = round($total, 2);
				$subtt -= $discountAmt;
				$tt = $subtt + $fee;
				$totalCartAmount += $tt;
				$totalCartFees += $serviceCommission;
				$totalCustomCharge += $customChargeAmount;
				$totalCustomChargeFees += $customChargeFees;
				$totalTipAmount += $tipAmount;
				$totalTipFees += $tipFees;

				/* START : code for get expire at */
				$customerId = $affiliateCustomer == 0 ? $customer->id : $affiliateCustomer;
				$expiryDate = BookingService::calculateExpiryDate($info->tour_package_id, $customerId);
				/* END : code for get expire at */

				//Create Booking
				$tourBooking = $this->tourBooking::create([
					'tour_package_id' => $info->tour_package_id,
					'tour_slot_id' => $slotTime,
					'customer_id' => $affiliateCustomer == 0 ? $customer->id : $affiliateCustomer,
					'tour_promotion_id' => $promotionId,
					'hotel_id' => $hotelId,
					'cart_id' => $cartId,
					'tourists' => $totalTouristsA,
					'infants' => $totalInfants,
					'is_reserved_for_affiliate' => $isReservedForAffiliate,
					'name' => $customerName,
					'email' => $customerEmail,
					'phone_number' => $customerPhoneNumber,
					'comments' => $customerComment,
					'note' => $tourOperatorNote,
					'note_datetime' => empty($note) ? null : mysqlDT(),
					'mask_required' => 'Yes',
					'discount2_value' => $discountAmt,
					'discount2_percentage' => ($discountPercentage > 0) ? $discountPercentage : 0,
					'discount_promo_code' => $discountPromoCode,
					'service_commission' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $fee : 0,
					'total' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $tt : $subtt,
					'booking_total' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $tt : $subtt,
					'status' => 'Booked',
					'is_group_booking' => ($isGroupRate == '1') ? 1 : 0,
					'visitor' => $request->getClientIp(),
					'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
					'expire_at' => $expiryDate,
					'created_by' => empty($bookedByStaff) ? Auth::id() : $bookedByStaff,
					'created_by_user_type' => empty($bookedByStaff) ? $userType : 'Tour Operator',
					'updated_at' => null
				]);
				DB::commit();

				if (!empty($customerComment)) {
					HistoryService::storeCommentInHistory($customerComment, $tourBooking->id);
				}

				for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
					$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
				}

				DB::table('tour_bookings_transact')->insert($tbTransact);
				DB::commit();

				// Close the time slot on booking
				TimeSlotService::closeSlotOnBooking($tourBooking->tour_package_id, $tourBooking->tour_slot_id);

				/* Execute only when package has contacts feature enabled - START*/
				if ($contactData != "") {
					try {
						PackageHasContactsService::storeTourBookingPeople($tourBooking->id, $contactData);
					} catch (Exception $exception) {
						Log::error($exception->getMessage());
					}
				}
				/* Execute only when package has contacts feature enabled - END*/

				/*START - Added this to place the history of person added */
				$addedPeoplesText = array();
				if (!empty($tbTransaction)) {
					foreach ($tbTransaction as $tblTransaction) {
						$addedPeoplesText[] = ($isGroupRate == '1') ? 'Person(s) ' . $tblTransaction['tourists'] : $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
					}
				}

				HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTouristsA + $totalInfants, $createdByWithName, $cartPackage);
				if ($tourOperatorNote != '') {
					HistoryService::storeNoteInHistory($tourOperatorNote, $tourBooking->id);
				}
				/* END - Added this to place the history of person added */

				/** Save custom Addon Fields */
				try {
					if (isset($cartPackage) && isset($cartPackage['custom_addons_field'])) {
						if (trim($cartPackage['custom_addons_field']) != "") {
							$addons = json_decode(trim($cartPackage['custom_addons_field']), true);
							$this->tourBookingFormsRepository->insertBookingForm($addons, $tourBooking->id);
						}
					}
				} catch (Exception $e) {
					Log::error($e->getMessage());
				}
				/** End Save custom Addon Fields */

				/* Store Custom Charge Start */
				$customChargeDetails = [];

				if ($cartPackage['custom_charge_data'] != "") {
					try {
						$customData = json_decode($cartPackage['custom_charge_data'], true);
						$customChargeDetails = $customData['custom_data'];
						$customChargeDetails['amount'] = $customChargeAmount;
						$customChargeDetails['fees'] = $customChargeFees;
						unset($customChargeDetails['custom_charge_amount']);

						CustomChargeBookingService::tourBookingCustomCharge($tourBooking->id, $customChargeDetails, null, 'Pay Later', 'Open');
						DB::commit();
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				}
				/* Store Custom Charge End */

				// Cash payment method
				if ($paymentMethod == 'Cash') {
					$changeAmount = 0;
					if ($isCashFees == 1) {
						$cashAmount = $tt;
						$feeAmountCash = $fee;
						if ($request->dividedBy != 'Please select option') {
							$feeAmountCash = $fee / $request->dividedBy;
						}
					} else {
						$cashAmount = $subtt;
						$feeAmountCash = 0;
					}

					$transAmount = !empty($splitValue) ? $splitValue : $cashAmount;
					$paymentType = !empty($splitValue) ? 1 : 0;
					$transactionId = TransactionService::storePaymentInTransaction(
						$tourBooking->id,
						$transAmount,
						$feeAmountCash,
						'Cash',
						$paymentType,
						'',
						''
					);
					DB::commit();

					$data = [
						'amount' => !empty($splitValue) ? $splitValue : $cashAmount,
						'payment_method' => !empty($splitValue) ? 'Split Payment' : 'Full Payment',
						'discount_promo_code' => $discountPromoCode
					];

					$historyId = HistoryService::storeCashPaymentInHistory($tourBooking->id, $totalTouristsA + $totalInfants, $data, $changeAmount, $createdByWithName);
					$comboPaymentHistIds[] = $historyId;

					// Below line is for to update tour booking history table
					$isSplitOrFull = !empty($splitValue) ? 'Split Payment' : 'Full Payment';
					$requestData = [
						'discount2_value' => 0
					];

					if ($isCashFees == '1') {
						$bookingData = CartService::prepareBookingData($tourBooking, $info, $tt, $discountAmt, $totalTouristsA + $totalInfants, $subtt, $fee, $slotTime, $tourOperatorNote);
						$requestData['payment_method'] = 'Cash';
						CardBookingService::run($bookingData, $transactionId, $historyId, $isSplitOrFull, $requestData);
					} else {
						CashBookingService::run($tourBooking, $transactionId, $historyId, $isSplitOrFull, $requestData);
					}
					DB::commit();
				}

				// Pay later Method
				if ($paymentMethod == 'Pay Later') {
					if ($tourBooking->hotel_id) {
						HistoryService::storePickupHotelInHistory($tourBooking, $createdByWithName, $cartPackage);
					}
				}

				$slotInfo = DB::table('tour_slot_times AS tst')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
					->where('tst.id', $slotTime)
					->when(($userType == 'Tour Operator' || $userType == 'Affiliate'), function ($query) {
						$query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
					})
					->select([
						DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
					])
					->first();

				$booking = CartService::prepareBookingDetails(
					$tourBooking,
					$info,
					$tt,
					$subtt,
					$paymentMethod,
					$isCashFees,
					$touristGroups,
					$totalTouristsA,
					$slotInfo,
					$slotTime,
					$affiliateCustomer,
					$splitValue
				);

				if ($paymentMethod != 'Card') {
					BookingNew::dispatch($booking);
					$booking['notif_for'] = 'Tour Operator';
					BookingNew::dispatch($booking);
				}

				if ($paymentMethod == 'Cash' || $paymentMethod == 'Pay Later') {
					$arEmailData = [
						'booking_id' => $tourBooking->id,
						'group_id' => $tourBooking->group_id,
						'slot_time' => $slotTime,
					];

					if ($newCustomer) {
						$arEmailData['password'] = $pwd;
					}

					$cartPackagesEmailData[] = $arEmailData;
				}

				if ($paymentMethod == 'Card') {
					$cardBookingsData[] = CartService::prepareCardBookingData(
						$tourBooking,
						$info,
						$tt,
						$subtt,
						$fee,
						$slotTime,
						$tourOperatorNote,
						$booking,
						$totalTouristsA + $totalInfants,
						$discountAmt,
						$addonPrice,
						$addonFee,
						$addonTotal,
						$customChargeDetails,
						$customChargeAmount,
						$customChargeFees,
						$tipAmount ?? 0,
						$tipFees ?? 0,
						$partnerFeesTotal,
						$isPartnerPackage
					);
				}

				if ($i == 0) {
					$firstBookingId = $tourBooking->id;
				}

				TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

				$i++;
			}
			/*end foreach*/

			if (\count($comboPaymentHistIds) > 1) {
				foreach ($comboPaymentHistIds as $histId) {
					$siblings = array_values(array_filter($comboPaymentHistIds, fn($id) => $id !== $histId));
					DB::table('tour_booking_history')
						->where('id', $histId)
						->update([
							'details' => DB::raw("JSON_SET(details, '$.combo_payment_history_ids', CAST('" . json_encode($siblings) . "' AS JSON))")
						]);
				}
			}

			// Save the card details
			HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $tourBooking->id, "BACKEND");

			if ($paymentMethod == 'Cash' || $paymentMethod == 'Pay Later') {
				session()->forget('cart');
				session()->save();
			}
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}

		if ($paymentMethod == 'Cash' || $paymentMethod == 'Pay Later') {
			if (!empty($cartPackagesEmailData)) {
				$userId = Auth::id();
				$tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? Auth::user()->tour_operator_id : '';
				$userType = userType();
				$loggedInUserEmail = Auth::user()->email;
				dispatch(new CartBookingsEmails($cartPackagesEmailData, $userId, $userType, $tourOperatorId, $loggedInUserEmail));
			}

			return [
				'isAffiliate' => $affiliateCust,
				'bookingId' => $firstBookingId,
				'bookingToken' => Crypt::encryptString($firstBookingId), // for security purpose
				'stripeId' => $paymentIntentInfo['clientSecret'],
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors' => $errors,
				'splitCount' => $splitCount,
			];
		}

		$month = substr($request->expiration, 0, 2);
		$yearSet = substr($request->expiration, 3, 2);
		$y = 20;
		$year = $y . '' . $yearSet;
		$cardnumber = $request->cardnumber;
		$cvc = $request->cvv;
		$master = $this->tourBooking::where('id', $firstBookingId)->first();

		$cardDetails = [
			'month' => $month,
			'year' => $year,
			'cardnumber' => $cardnumber,
			'cvc' => $cvc,
		];

		if ($request->is('api/*')) {
			$paymentMethod = $cart['payment_method'];
		} else {
			$paymentMethod = $request->payment_method;
		}

		if ($paymentMethod == 'Card') {
			$data = CartService::prepareBookingDataForComboCardBookings(
				$master,
				$cardBookingsData,
				$totalCartAmount,
				$totalCartFees,
				$request,
				$splitValue,
				$info,
				$totalCustomCharge,
				$totalCustomChargeFees,
				$totalTipAmount,
				$totalTipFees
			);

			if ($request->has('api') && $request->api == 1) {
				$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';

				if ($paymentIntentId == '') {
					throw new Exception('You must provide Stripe Payment Intent Id');
				}

				$data['payment_intent_id'] = $paymentIntentId;
				$paymentIntentInfo = $this->createComboBookingCard($data);
			} else if (!$request->is('api/*') && $request->has('payment_intent_id') && $request->payment_intent_id != '') {
				$data['payment_intent_id'] = $request->payment_intent_id;
				if ($request->has('feature') && $request->feature == 'create_booking') {
					// For web manual card
					$data['webhook'] = 0;
					$data['web_manual_card'] = 1;
				} else {
					// For web card reader
					$data['webhook'] = 0;
					$data['web_card_reader'] = 1;
				}
				$paymentIntentInfo = $this->createComboBookingCard($data);
			} else {
				$paymentIntentInfo = $this->createPaymentIntentForCart($data);
			}

			if (isset($paymentIntentInfo['nextAction'])) {
				foreach ($cardBookingsData as $booking) {
					$master->stripe_paymentintent_id = $paymentIntentInfo['clientSecret'];
					$master->save();
				}

				return [
					'bookingId' => $firstBookingId,
					'bookingToken' => Crypt::encryptString($firstBookingId), // for security purpose
					'stripeId' => $paymentIntentInfo['clientSecret'],
					'require3ds' => 1,
					'nextAction' => $paymentIntentInfo['nextAction'],
					'errors' => $errors,
					'errors_message' => !empty($errors_message) ? $errors_message : '',
				];
			}

			if ($paymentIntentInfo['clientSecret'] == '') {
				foreach ($cardBookingsData as $booking) {
					$tourBooking = $this->tourBooking::where('id', $booking['booking_id'])->first();
					$tourBooking->booking_total = $tourBooking->booking_total - $tourBooking->service_commission;
					$tourBooking->total = $tourBooking->total - $tourBooking->service_commission;
					$tourBooking->service_commission = 0.00;
					$tourBooking->save();
				}

				$errors[] = 'payment';
			}

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
			}

			return [
				'isAffiliate' => $affiliateCust,
				'bookingId' => $firstBookingId,
				'bookingToken' => Crypt::encryptString($firstBookingId), // for security purpose
				'stripeId' => $paymentIntentInfo['clientSecret'],
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors' => $errors,
				'errors_message' => !empty($errors_message) ? $errors_message : '',
				'splitCount' => $splitCount,
			];
		}
	}

	/**
	 * Create payment intent for the new booking
	 *
	 * @param array $data
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function createPaymentIntent($data)
	{
		DB::beginTransaction();
		try {

			$userType = userType();
			$isCustomCharge = customCharge();
			$paymentIntentId = (isset($data['payment_intent_id']) && $data['payment_intent_id'] != '') ? $data['payment_intent_id'] : '';
			if ($paymentIntentId == '') {
				throw new Exception("Payment intent is missing. Please refresh the page and try again.");
			}
			$intentMetaData = [
				'booking_id' => $data['booking_id'],
				'tour_package' => $data['tour_package'],
				'date' => $data['date'],
				'time_from' => $data['time_from'],
				'tourists' => $data['tourists'],
				'customer_id' =>  $data['customer_id'],
				'customer_name' => $data['customer_name'],
				'customer_phone' => $data['customer_phone'],
				'customer_email' => $data['customer_email'],
				'split_value' => (isset($data['split_value']) && !empty($data['split_value'])) ? 1 : 0,
			];
			if (isset($data['change_people']) && $data['change_people'] == 1) {
				$intentMetaData = array_merge($intentMetaData, [
					'addedpeoplesetexts' => (!empty($data['addedpeoplesetexts'])) ? implode(',', $data['addedpeoplesetexts']) : '',
					'deletedpeoplestexts' => (!empty($data['deletedpeoplestexts'])) ? implode(',', $data['deletedpeoplestexts']) : '',
					'addedtourists' => $data['addedtourists'] ?? 0,
					'deletedtourits' => $data['deletedtourits'] ?? 0,
					'discount_percentage' => $data['discount_percentage'] ?? 0,
					'discount2_value' => $data['discount2_value'] ?? 0,
					'paymenttotal' => $data['paymenttotal'] ?? 0,
					'refundtotal' => $data['refundtotal'] ?? 0,
					'change_people' => $data['change_people'],
					'from' => $data['from'] ?? '',
				]);
			}
			$paymentIntent = BookingService::updateCardReaderIntent($paymentIntentId, $intentMetaData);

			$paymentType = (!empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0)) ? 1 : 0;
			$transData = [
				'booked_by_staff' => (isset($data['booked_by_staff'])) ? $data['booked_by_staff'] : ''
			];
			$transactionId = TransactionService::storePaymentInTransaction(
				$data['booking_id'],
				$data['total'],
				$data['serviceCommission'],
				'Card',
				$paymentType,
				!empty($paymentIntent->id) ? $paymentIntent->id : null,
				!empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
				$transData,
				mode: PaymentModeInference::fromStripeObject($paymentIntent)
			);

			DB::commit();

			$createdByWithName = BookingService::createdUserInfo();

			$cardDetails = StripeService::extractCardDetails($paymentIntent);
			$crd = $cardDetails['last4'];
			$cardType = $cardDetails['brand'];

			$tourBookingDetails = $this->tourBooking::find($data['booking_id']);

			$historyId = HistoryService::storeCardPaymentInHistory($data['booking_id'], $tourBookingDetails->tourists + $tourBookingDetails->infants, $data, $crd, $cardType, $createdByWithName);

			if (isset($data['add_people']) && $data['add_people'] == 1) {
				HistoryService::updateTouristInHistory($data['booking_id'], $data, $tourBookingDetails, $createdByWithName);
			}

			if (isset($data['change_people']) && $data['change_people'] == 1) {
				HistoryService::changeTouristInHistory($data['booking_id'], $data, $createdByWithName);
				$seatsDiff = $data['addedtourists'] - $data['deletedtourits'];
				TimeSlotAvailabilityService::updateSlotSummary($tourBookingDetails->tour_slot_id, $seatsDiff);
				// Finalize any pending remaining_payment link whose amount was folded into the
				// combined card charge (add-ons / prior change-people items added via payment link).
				if (!empty($tourBookingDetails->group_id)) {
					$cpPendingLink = TourBookingPaymentLink::where('order_id', $tourBookingDetails->group_id)
						->where('status', 'Unpaid')
						->where('section', 'remaining_payment')
						->first();
					if ($cpPendingLink) {
						try {
							RemainingPaymentService::finalizeItems($cpPendingLink, $paymentIntent, (int) $data['booking_id']);
						} catch (\Exception $e) {
							Log::error('RemainingPaymentService finalizeItems failed after change-people card payment: ' . $e->getMessage());
						}
					}
				}
			}

			if (!empty($data['note'])) {
				HistoryService::storeNoteInHistory($data['note'], $data['booking_id']);
			}

			// Below line is for to update tour booking history table
			$isSplitOrFull = (!empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0)) ? 'Split Payment' : 'Full Payment';
			CardBookingService::run($data, $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntent ?? null);

			if (isset($data['bookingNotifInfo']) && !empty($data['bookingNotifInfo'])) {
				BookingNew::dispatch($data['bookingNotifInfo']);
				$data['bookingNotifInfo']['notif_for'] = 'Tour Operator';
				BookingNew::dispatch($data['bookingNotifInfo']);
			}

			$info = TourPackageService::getTourSlotInfo($data['slot_time'], $userType);

			$tourDateTime = $info->date . ' ' . $info->time_from;

			$arEmailData = [
				'tour_d_t' => $tourDateTime,
				'bookingId' => $data['booking_id'],
				'bookingGroupId' => $data['group_id'],
				'custName' => $data['customer_name'],
				'custEmail' => $data['customer_email'],
				'custPhone' => $data['customer_phone']
			];

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendAcknowledgementEmail($info, $arEmailData);
			}

			return [
				'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
				'transactionId' => $transactionId,
				'status' => !empty($paymentIntent->status) ? $paymentIntent->status : ''
			];
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();

			HistoryService::storeCardPaymentErrorInHistory($data['booking_id'], $exception);

			if (isset($data['bookingNotifInfo']) && !empty($data['bookingNotifInfo'])) {
				$data['bookingNotifInfo']['paid'] = '';
				$data['bookingNotifInfo']['BookingTotal'] = 0;
				$data['bookingNotifInfo']['PaymentTotal'] = 0;
				$data['bookingNotifInfo']['payment_method'] = 'Pay Later';
				BookingNew::dispatch($data['bookingNotifInfo']);
				$data['bookingNotifInfo']['notif_for'] = 'Tour Operator';
				BookingNew::dispatch($data['bookingNotifInfo']);
			}

			return [
				'clientSecret' => '',
				'transactionId' => '',
				'ex' => $exception,
			];
		}
	}

	/**
	 * Create payment intent for the cart
	 *
	 * @param array $data
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function createPaymentIntentForCart($data)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$cardBookingsData = $data['data'];

			$tour_operator_name = $cardBookingsData[0]['statement_descriptor'];
			$tourOperatorId = $cardBookingsData[0]['tour_operator_id'];
			if ($data['split_value'] != '') {
				$data['total'] = $data['split_value'];
				if ($data['splitCount'] != '') {
					$data['fees'] = $data['fees'] / $data['splitCount'];
				}
			}

			$data['total'] = !empty($data['total']) ? $data['total'] : 0;
			$customer = $this->customer::find($data['customer_id']);
			$stripeFee = 0;
			$stripeTotal = 0;
			foreach ($cardBookingsData as $cardBookingData) {
				$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $cardBookingData['booking_id'])->where('deleted_by', 0)->first();

				if ($data['split_value'] != '') {
					$stripeF = roundout($data['total'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
					$stripeFee += $stripeF + $cardBookingData['addon_fee'] + $cardBookingData['custom_charge_fees'];
					$stripeTotal += $data['total'] + $cardBookingData['addon_total'] + $cardBookingData['custom_charge_amount'] + $cardBookingData['custom_charge_fees'];
				} else {
					$stripeF = roundout($cardBookingData['total'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
					$stripeFee += $stripeF + $cardBookingData['addon_fee'] + $cardBookingData['custom_charge_fees'];
					$stripeTotal += $cardBookingData['total'] + $cardBookingData['addon_total'] + $cardBookingData['custom_charge_amount'] + $cardBookingData['custom_charge_fees'];
				}
			}

			if ($data['total'] != 0) {
				if ($customer->stripe_id == null) {
					$stripeCustomer = StripeCustomer::create([
						'name' => $customer->name,
						'email' => $customer->email,
						'phone' => $customer->phone_number
					]);

					$customer->stripe_id = $stripeCustomer->id;
					$customer->save();
				}

				$cardDetails = $data['cardDetails'];
				$method = PaymentMethod::create([
					'type' => 'card',
					'card' => [
						'number' => $cardDetails['cardnumber'],
						'exp_month' => $cardDetails['month'],
						'exp_year' => $cardDetails['year'],
						'cvc' => $cardDetails['cvc'],
					],
				]);

				$mode = app(PaymentModeResolver::class)->resolve(array_column($cardBookingsData, 'date'));
				$stripeAttributes = [
					'payment_method_types' => ['card'],
					'payment_method' => $method->id,
					'currency' => currencyFormation(code: true),
					'amount' => $stripeTotal * 100, // stripe needs amount in cents
					'statement_descriptor_suffix' => $tour_operator_name,
					'confirm' => true,
					'customer' => $customer->stripe_id,
					'expand' => ['charges.data.transfer'],
					...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($data['destination'], (int) round($stripeFee * 100), mode: $mode)
				];

				$stripeMetadata = CartService::stripeMetadata($data);

				$paymentIntentParams = $stripeAttributes + $stripeMetadata;
				$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

				if (isset($paymentIntent) && ($paymentIntent->status == 'requires_action' || $paymentIntent->status == 'requires_source_action')) {
					$routePrefix = $userType == 'Affiliate' ? '' : 'tour-operator.';
					$confirmParams = [
						'return_url' => route(prefixedRouteName($routePrefix . 'booking.create')),
						'payment_method_options' => [
							'card' => [
								'request_three_d_secure' => 'any'
							]
						],
						'expand' => ['charges.data.transfer']
					];
					$paymentIntentConfirm = app(PaymentGatewayInterface::class)->confirmPayment($paymentIntent->id, $confirmParams);

					return [
						'clientSecret' => !empty($paymentIntentConfirm->client_secret) ? $paymentIntentConfirm->client_secret : 123,
						'intentId' => !empty($paymentIntentConfirm->id) ? $paymentIntentConfirm->id : null,
						'nextAction' => $paymentIntentConfirm->next_action
					];
				}
			}

			if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
				$chargeParams = $stripeMetadata;
				app(PaymentGatewayInterface::class)->updateConnectedPayment(
					$paymentIntent->charges->data[0]->transfer->destination_payment,
					$chargeParams,
					$paymentIntent->charges->data[0]->transfer->destination
				);
			}

			// Overwrite the total again for ongoing booking database flow.
			$data['total'] = $data['total'] - $data['totalCustomCharge'] - $data['totalCustomChargeFees']; // Only when a custom charge is applied to maintain flow as it is

			foreach ($cardBookingsData as $cardBookingData) {
				$transAmount = (!empty($data['split_value']) && $data['split_value'] > 0) ? $data['total'] : $cardBookingData['total'];
				$feesAmount = (!empty($data['split_value']) && $data['split_value'] > 0) ? $data['fees'] : $cardBookingData['serviceCommission'];
				$paymentType = (!empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0)) ? 1 : 0;
				$transData = [
					'booked_by_staff' => $data['booked_by_staff']
				];

				$transactionId = TransactionService::storePaymentInTransaction(
					$cardBookingData['booking_id'],
					$transAmount,
					$feesAmount,
					'Card',
					$paymentType,
					!empty($paymentIntent->id) ? $paymentIntent->id : null,
					!empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
					$transData,
					mode: $mode ?? PaymentModeResolver::DESTINATION
				);
				DB::commit();

				$created_by_with_name = BookingService::createdUserInfo();

				$crd = !empty($paymentIntent->charges->data[0]->payment_method_details->card->last4) ? $paymentIntent->charges->data[0]->payment_method_details->card->last4 : substr($cardDetails['cardnumber'], -4);
				$cardType = !empty($paymentIntent->charges->data[0]->payment_method_details->card->brand) ? $paymentIntent->charges->data[0]->payment_method_details->card->brand : '';

				$tourBookingDetails = $this->tourBooking::find($cardBookingData['booking_id']);

				$historyId = HistoryService::storeCardPaymentInHistory($cardBookingData['booking_id'], $tourBookingDetails->tourists + $tourBookingDetails->infants, $data, $crd, $cardType, $created_by_with_name, $cardBookingData);

				// Below line is for to update tour booking history table
				$isSplitOrFull = (!empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0)) ? 'Split Payment' : 'Full Payment';
				$cardBookingData['customer_id'] = $data['customer_id'];
				CardBookingService::run($cardBookingData, $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntent);

				if ($cardBookingData['bookingNotifInfo'] && !empty($cardBookingData['bookingNotifInfo'])) {
					BookingNew::dispatch($cardBookingData['bookingNotifInfo']);
					$cardBookingData['bookingNotifInfo']['notif_for'] = 'Tour Operator';
					BookingNew::dispatch($cardBookingData['bookingNotifInfo']);
				}
			}

			$userId = Auth::id();
			$tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? Auth::user()->tour_operator_id : '';
			$userType = userType();
			$loggedInUserEmail = Auth::user()->email;

			dispatch(new CartBookingsEmails($cardBookingsData, $userId, $userType, $tourOperatorId, $loggedInUserEmail));

			session()->forget('cart');
			session()->save();

			return [
				'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
				'transactionId' => 123456,
				'status' => !empty($paymentIntent->status) ? $paymentIntent->status : ''
			];
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();
			$cardBookingsData = $data['data'];
			foreach ($cardBookingsData as $cardBookingData) {
				if ($cardBookingData['bookingNotifInfo'] && !empty($cardBookingData['bookingNotifInfo'])) {
					$cardBookingData['bookingNotifInfo']['paid'] = '';
					$cardBookingData['bookingNotifInfo']['BookingTotal'] = 0;
					$cardBookingData['bookingNotifInfo']['PaymentTotal'] = 0;
					$cardBookingData['bookingNotifInfo']['payment_method'] = 'Pay Later';
					BookingNew::dispatch($cardBookingData['bookingNotifInfo']);
					$cardBookingData['bookingNotifInfo']['notif_for'] = 'Tour Operator';
					BookingNew::dispatch($cardBookingData['bookingNotifInfo']);
				}
			}
			return [
				'clientSecret' => '',
				'transactionId' => '',
				'ex' => $exception,
			];
		}
	}

	/**
	 * Create payment using card for the new booking from mobile app
	 *
	 * @param array $data
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function createBookingCard($data)
	{
		DB::beginTransaction();
		try {
			$created_by_with_name = BookingService::createdUserInfo();

			if ($data['split_value']) {
				$data['total'] = $data['split_value'];
			}

			$tourBookingDetails = $this->tourBooking::find($data['booking_id']);

			$transactionCount = $this->transaction::where('transaction_number', $data['payment_intent_id'])
				->where('tour_booking_id', $data['booking_id'])
				->where('status', 'Completed')
				->count();

			$paymentIntentId = $data['payment_intent_id'] ?? null;
			// Save the card details
			HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $data['booking_id'], "BACKEND");

			if ($paymentIntentId && !(request()->is('api/*'))) {
				$intentMetadata = [
					'booking_id' => $data['booking_id'],
					'tour_package' => $data['tour_package'],
					'date' => $data['date'],
					'time_from' => $data['time_from'],
					'tourists' => $data['tourists'],
					'customer_id' => $data['customer_id'],
					'customer_name' => $data['customer_name'],
					'customer_phone' => $data['customer_phone'],
					'customer_email' => $data['customer_email'],
					'split_value' => (isset($data['split_value']) && !empty($data['split_value']) && $data['split_value'] != 0) ? 1 : 0,
					'create_booking_card' => 1
				];

				$paymentIntent = BookingService::updateCardReaderIntent($paymentIntentId, $intentMetadata);
			} else {
				$retrieveOptions = ['expand' => ['charges.data.transfer']];
				$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
			}

			if ($data['webhook'] != 1) {
				// This condition added to prevent the duplicate bookings
				$fromMetadata = match (true) {
					!empty($data['web_card_reader']) && $data['web_card_reader'] == 1 => 'Web Card Reader',
					!empty($data['web_manual_card']) && $data['web_manual_card'] == 1 => 'Web Manual Card',
					default => 'iOS Manual Card',
				};
				if (!isset($data['after_capture'])) {
					$stripeMetaDataCapture = BookingUtils::generateMetadata($data, $fromMetadata);
					$updateParams = $stripeMetaDataCapture;
					app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);
				}
				if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
					$stripeMetaData = BookingUtils::generateMetadata($data, $fromMetadata);

					$chargeParams = $stripeMetaData;
					app(PaymentGatewayInterface::class)->updateConnectedPayment(
						$paymentIntent->charges->data[0]->transfer->destination_payment,
						$chargeParams,
						$paymentIntent->charges->data[0]->transfer->destination
					);
				}

				$cardDetails = StripeService::extractCardDetails($paymentIntent);
				$crd = $cardDetails['last4'];
				$cardType = $cardDetails['brand'];

				if ($transactionCount == 0) {
					$paymentType = (!empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0)) ? 1 : 0;
					$transData = [
						'booked_by_staff' => (isset($data['booked_by_staff'])) ? $data['booked_by_staff'] : ''
					];

					if ($data['split_value']) {
						$data['total'] = $data['split_value'];
						$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $data['booking_id'])->where('deleted_by', 0)->first();
						$data['serviceCommission'] = roundout($data['total'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
					}

					$transactionId = TransactionService::storePaymentInTransaction(
						$data['booking_id'],
						$data['total'],
						$data['serviceCommission'],
						'Card',
						$paymentType,
						!empty($paymentIntent->id) ? $paymentIntent->id : null,
						!empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
						$transData,
						mode: PaymentModeInference::fromStripeObject($paymentIntent)
					);

					$created_by_with_name = BookingService::createdUserInfo();

					$fromMethod = 'Manual Update';
					$historyId = HistoryService::storeCardPaymentInHistory($data['booking_id'], $tourBookingDetails->tourists + $tourBookingDetails->infants, $data, $crd, $cardType, $created_by_with_name, [], $fromMethod);
				}
			}

			if (isset($data['change_people']) && $data['change_people'] == 1) {
				HistoryService::changeTouristInHistory($data['booking_id'], $data, $created_by_with_name);
				$seatsDiff = $data['addedtourists'] - $data['deletedtourits'];
				TimeSlotAvailabilityService::updateSlotSummary($tourBookingDetails->tour_slot_id, $seatsDiff);
				// Finalize any pending remaining_payment link whose amount was folded into the
				// combined card charge (add-ons / prior change-people items added via payment link).
				if (!empty($tourBookingDetails->group_id)) {
					$cpPendingLink = TourBookingPaymentLink::where('order_id', $tourBookingDetails->group_id)
						->where('status', 'Unpaid')
						->where('section', 'remaining_payment')
						->first();
					if ($cpPendingLink) {
						try {
							RemainingPaymentService::finalizeItems($cpPendingLink, $paymentIntent, (int) $data['booking_id']);
						} catch (\Exception $e) {
							Log::error('RemainingPaymentService finalizeItems failed after change-people card payment (createBookingCard): ' . $e->getMessage());
						}
					}
				}
			}

			if (!empty($data['note'])) {
				HistoryService::storeNoteInHistory($data['note'], $data['booking_id']);
			}

			// Below line is for to update tour booking history table
			if ($data['webhook'] != 1) {
				$isSplitOrFull = !empty($data['split_value']) ? 'Split Payment' : 'Full Payment';
				CardBookingService::run($data, $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntent);
			}

			$userType = userType();
			$info = TourPackageService::getTourSlotInfo($data['slot_time'], $userType);
			$tourDateTime = $info->date . ' ' . $info->time_from;

			if (isset($data['bookingNotifInfo']) && !empty($data['bookingNotifInfo'])) {
				BookingNew::dispatch($data['bookingNotifInfo']);
				$data['bookingNotifInfo']['notif_for'] = 'Tour Operator';
				BookingNew::dispatch($data['bookingNotifInfo']);
			}

			$arEmailData = [
				'tour_d_t' => $tourDateTime,
				'bookingId' => $data['booking_id'],
				'bookingGroupId' => $tourBookingDetails->group_id,
				'custName' => $data['customer_name'],
				'custEmail' => $data['customer_email'],
				'custPhone' => $data['customer_phone']
			];

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendAcknowledgementEmail($info, $arEmailData);
			}
			DB::commit();

			return [
				'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
				'transactionId' => isset($transactionId) ? $transactionId : '',
				'status' => !empty($paymentIntent->status) ? $paymentIntent->status : ''
			];
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();

			HistoryService::storeCardPaymentErrorInHistory($data['booking_id'], $exception);

			return [
				'clientSecret' => '',
				'transactionId' => '',
				'ex' => $exception,
			];
		}
	}

	/**
	 * Create payment using card for the new combo booking from mobile app
	 *
	 * @param array $data
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function createComboBookingCard($data)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$oldBookingID = oldBookingId();
			$cardBookingsData = $data['data'];
			$created_by_with_name = BookingService::createdUserInfo();

			if ($data['split_value']) {
				$data['total'] = $data['split_value'];
			}
			$customer = $this->customer::find($data['customer_id']);

			$data['serviceCommission'] = CartService::calculateServiceCommissionForCard($data, $cardBookingsData, $customer, $oldBookingID); //$destination_amount;

			$paymentIntentId = $data['payment_intent_id'] ?? null;
			$retrieveOptions = ['expand' => ['charges.data.transfer']];
			$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);


			if ($data['webhook'] != 1) {
				// This condition added to prevent the duplicate bookings
				$dataForMetadata = [
					'customer_id' => $customer->id,
					'customer_name' => $customer->name,
					'customer_email' => $customer->email,
					'customer_phone' => $customer->phone_number,
					'data' => $cardBookingsData,
					'split_value' => $data['split_value'],
					'section' => config('constants.payment_sections.new_booking')
				];

				$dataForMetadata['From'] = match (true) {
					!empty($data['web_card_reader']) => 'Web Card Reader',
					!empty($data['web_manual_card']) => 'Web Manual Card',
					default => 'iOS Manual Card',
				};

				if ($dataForMetadata['From'] == 'iOS Manual Card') {
					$dataForMetadata['App'] = Constants::STATUS_ONE;
				}

				$stripeMetadata = CartService::stripeMetadata($dataForMetadata);

				// The card has already been charged at this point. Labelling the
				// payment in Stripe is best effort: if it fails we must still
				// record the payment below, otherwise the customer is charged
				// while the bookings stay unpaid.
				try {
					if (!isset($data['after_capture'])) {
						$updateParams = $stripeMetadata;
						app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);
					}

					if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
						$chargeParams = $stripeMetadata;
						app(PaymentGatewayInterface::class)->updateConnectedPayment(
							$paymentIntent->charges->data[0]->transfer->destination_payment,
							$chargeParams,
							$paymentIntent->charges->data[0]->transfer->destination
						);
					}
				} catch (Exception $metadataException) {
					Log::error('createComboBookingCard: Stripe metadata update failed for ' . $paymentIntentId . ', recording payment anyway', [
						'exception' => $metadataException->getMessage(),
					]);
				}

				$cardDetails = StripeService::extractCardDetails($paymentIntent);
				$crd = $cardDetails['last4'];
				$cardType = $cardDetails['brand'];

				$created_by_with_name = BookingService::createdUserInfo();

				$comboPaymentHistIds = [];
				foreach ($cardBookingsData as $cardBookingData) {
					$transactionCount = $this->transaction::where('transaction_number', $data['payment_intent_id'])
						->where('tour_booking_id', $cardBookingData['booking_id'])
						->where('status', 'Completed')
						->count();

					if ($transactionCount == 0) {
						$useSplitValue = !empty($data['split_value']) && $data['split_value'] > 0;

						$transAmount = $useSplitValue ? $data['total'] : $cardBookingData['total'];
						$feesAmount = $useSplitValue ? $data['serviceCommission'] : $cardBookingData['serviceCommission'];
						$paymentType = (int) $useSplitValue;
						$transData = [
							'booked_by_staff' => isset($cardBookingData['booked_by_staff']) ? $cardBookingData['booked_by_staff'] : ''
						];
						$transactionId = TransactionService::storePaymentInTransaction(
							$cardBookingData['booking_id'],
							$transAmount,
							$feesAmount,
							'Card',
							$paymentType,
							!empty($paymentIntent->id) ? $paymentIntent->id : null,
							!empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
							$transData,
							mode: PaymentModeInference::fromStripeObject($paymentIntent)
						);
						DB::commit();

						$historyId = HistoryService::storeCardPaymentInHistory($cardBookingData['booking_id'], $cardBookingData['tourists'] + $cardBookingData['infants'], $data, $crd, $cardType, $created_by_with_name, $cardBookingData, 'Manual Update');
						$comboPaymentHistIds[] = $historyId;

						// Below line is for to update tour booking history table
						$isSplitOrFull = (!empty($data['split_value'])) ? 'Split Payment' : 'Full Payment';
						$cardBookingData['customer_id'] = $customer->id;
						CardBookingService::run($cardBookingData, $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntent);
						DB::commit();
					}
				}

				if (\count($comboPaymentHistIds) > 1) {
					foreach ($comboPaymentHistIds as $histId) {
						$siblings = array_values(array_filter($comboPaymentHistIds, fn($id) => $id !== $histId));
						DB::table('tour_booking_history')
							->where('id', $histId)
							->update([
								'details' => DB::raw("JSON_SET(details, '$.combo_payment_history_ids', CAST('" . json_encode($siblings) . "' AS JSON))")
							]);
					}
				}
			}

			// Below line is for to save note for combo bookings
			foreach ($cardBookingsData as $cardBookingData) {
				if (!empty($cardBookingData['note'])) {
					HistoryService::storeNoteInHistory($cardBookingData['note'], $cardBookingData['booking_id']);
				}
			}

			$userId = Auth::id();
			$tourOperatorId = ($userType == 'Tour Operator' || $userType == 'Affiliate') ? Auth::user()->tour_operator_id : '';
			$userType = userType();
			$loggedInUserEmail = Auth::user()->email;

			dispatch(new CartBookingsEmails($cardBookingsData, $userId, $userType, $tourOperatorId, $loggedInUserEmail));

			session()->forget('cart');
			session()->save();

			return [
				'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
				'transactionId' => isset($transactionId) ? $transactionId : '',
				'status' => !empty($paymentIntent->status) ? $paymentIntent->status : ''
			];
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();

			$cardBookingsData = $data['data'];
			$bookingId = $cardBookingsData[0]['booking_id'];

			HistoryService::storeCardPaymentErrorInHistory($bookingId, $exception);

			return [
				'clientSecret' => '',
				'transactionId' => '',
				'ex' => $exception,
			];
		}
	}

	/**
	 * Get booking information
	 *
	 * @param int $id
	 * @param bool $forPrinting
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function booking(int $id, $forPrinting)
	{
		$oldBookingId = oldBookingId();

		$externalBooking = TourBooking::where('external_booking_id', (string) $id)->first();
		if ($externalBooking) {
			$id = $externalBooking->id;
		}

		DB::beginTransaction();
		try {
			$userType = userType();
			$isCustomCharge = customCharge();
			$paymentAcknowledgement = '';
			if (isset($_GET) && isset($_GET['payment_intent_client_secret']) && $_GET['payment_intent_client_secret'] != '' && isset($_GET['payment_intent']) && $_GET['payment_intent'] != '') {
				$paymentIntentId = (isset($_GET['payment_intent'])) ? $_GET['payment_intent'] : '';
				$paymentIntentDetails = PaymentIntent::retrieve(['id' => $paymentIntentId]);
				$metaDataInfo = (isset($paymentIntentDetails->metadata['by']) && !empty($paymentIntentDetails->metadata['by'])) ? $paymentIntentDetails->metadata['by'] : '';
				if ($metaDataInfo != '' && $metaDataInfo == 'Switch Package - Web') {
					$data = [
						'new_slot_id' => $paymentIntentDetails->metadata['new_slot_id'],
						'new_package_id' => $paymentIntentDetails->metadata['new_package_id'],
						'booking_id' => $id,
						'paymentIntentDetails' => $paymentIntentDetails
					];
					$paymentAcknowledgement = SwitchTourPackageCardService::completeSecureCardPayment($data);
					DB::commit();
				} else {
					if (cartView() == 1) {
						$paymentAcknowledgement = CartService::completeSecureCardPaymentForCart($_GET, true);
						DB::commit();
					} else {
						$paymentAcknowledgement = BookingService::completeSecureCardPayment($_GET);
						DB::commit();
					}
				}
			}

			$bookingM = BookingService::information($userType, $id);


			// Bundle/Non-bundle Packages
			$packageList = BundlePackageBookingService::getBundlePackageBookings($bookingM->bundle_package, $bookingM->id, $bookingM->tour_package_id);
			$bundlePackageCheckInsList = BundlePackageBookingService::bundlePackageListCheckInsAndTickets($bookingM->bundle_package, $bookingM->id, $bookingM->tour_package_id, $bookingM->is_group_booking);

			$bookingT = BookingService::totalInformation($id, $bookingM->is_group_booking);

			// Execute only if booking package has contacts
			if ($bookingM->has_contacts == 1) {
				$bookingContacts = PackageHasContactsService::contactList($bookingM->id);
			}

			$rateGroupWiseTourists = $bookingT->pluck('tourists', 'tour_package_rate_id')->toArray();
			$rateGroupWiseInfants = $bookingT->pluck('infants', 'tour_package_rate_id')->toArray();

			$rateGroupWiseTotal = $bookingT->pluck('total', 'tour_package_rate_id')->toArray();
			$rateGroupWiseRate = $bookingT->pluck('rate', 'tour_package_rate_id')->toArray();
			$rateGroupWiseAdditionalCharge = $bookingT->pluck('additional_charge', 'tour_package_rate_id')->toArray();
			$rateGroupWiseOldServiceCommission = $bookingT->pluck('processing_charge_percentage', 'tour_package_rate_id')->toArray();
			$rateGroupWisePermitFeesRefunded = $bookingT->pluck('permitFeesRefunded', 'tour_package_rate_id')->toArray();
			$rateGroupWiseNoPermitFees = $bookingT->pluck('noPermitFees', 'tour_package_rate_id')->toArray();
			$makePaymentValueForCardFailed = $bookingT->sum('makePaymentValueForCardFailed');
			$redeemedTickets = $bookingT->sum('redeemed_tickets');
			$notRedeemedTickets = $bookingT->sum('not_redeemed_tickets');
			$maxTourists = max($rateGroupWiseTourists);

			$staffUsers = BookingService::staffUsers($bookingM);

			$user = BookingService::bookingUser($id);
			$paymentsCountOfCart = 0;
			$cartBookingIds = [];
			$comboBookingsInfo = [];
			if ($bookingM->cart_id != null) {
				$cartBookingIds = BookingService::cartBookingIds($id);
				if (request()->is('api/*')) {
					$comboBookingsInfo = BookingService::cartBookingsInfo($id);
				}

				$paymentsCountOfCart = DB::table('transactions AS t')
					->join('tour_bookings_master as tbm', 'tbm.id', '=', 't.tour_booking_id')
					->where('tbm.cart_id', $bookingM->cart_id)
					->count();
			}

			$comboBookingsData = BookingService::comboBookingsData($id);

			$bookedFromFrontend = false;
			if ($bookingM->is_affiliate == 'Yes') {
				$bookedFromFrontend = bookedFromFrontEnd($bookingM->id);
			}

			$transactions = $this->transaction::with('bookingHistory')->where('tour_booking_id', $id)
				->whereIn('status', ['Completed', 'Failed', 'Refund'])
				->where(function ($query) {
					$query->whereNull('charge_type')
						->orWhere('charge_type', 'discount');
				})
				->orderBy('id', 'DESC')
				->get();

			$trans = $transactions->where('status', 'Completed')->first();
			$transF = $transactions->where('status', 'Failed')->first();
			$transP = $transactions->where('status', 'Completed')->sortBy('id')->first();
			$isImportedBooking = false;
			if (!empty($transP) && $transP->via == 'Card' && $transP->transaction_number == "") {
				$isImportedBooking = true;
			}

			// Custom charge service
			$customChargeList = CustomChargeBookingService::customChargeList($id, $transactions);
			$customChargePrice = CustomChargeBookingService::customChargePrice($id, $transactions);

			$cartPaymentTotalForCash = 0;
			$cartPaymentTotalForCard = 0;
			$cartPaymentTotalForBillable = 0;
			$cartServiceCommissionTotal = 0;

			if (empty($transP) && cartView() == 1) {
				$cartPaymentTotal = BookingService::calculateTotalCartPayment($id, $bookingM->is_affiliate);
				$cartPaymentTotalForBillable = $cartPaymentTotal['cartPaymentTotalForCash'];
				$cartPaymentTotalForCash = (cashFees() == '1') ? $cartPaymentTotal['cartPaymentTotalForCard'] : $cartPaymentTotal['cartPaymentTotalForCash'];
				$cartPaymentTotalForCard = $cartPaymentTotal['cartPaymentTotalForCard'];
				$cartServiceCommissionTotal = $cartPaymentTotal['cartServiceCommissionTotal'];
			}

			$totalDiscountPayment = BookingService::calculateDiscountpayment($id);

			$historyPaymentTime = $this->tourBookingHistory::select('id', 'operation_at', 'details')->where('tour_booking_id', $id)->where('operation', 'Payment')->first();
			$refund_amount = 0;
			$payment_total = 0;
			$booking_total = 0;
			if (!empty($historyPaymentTime)) {
				$tourBookingHistory = $this->tourBookingHistory::where('tour_booking_id', $id)
					->where(function ($query) {
						return $query->where('operation', '=', 'Refund')
							->orWhere('operation', '=', 'Cancelled');
					})
					->where('ticket_cost', '!=', 0)
					->where('operation_at', '>', $historyPaymentTime->operation_at)
					->whereNotNull('payment_type')
					->where('payment_type', '!=', '')
					->get([
						'id',
						'ticket_cost',
						'processing_fees',
						'platform_fees',
						'details'
					]);

				if (count($tourBookingHistory) > 0) {
					foreach ($tourBookingHistory as $key => $bookingHistory) {
						$refund_amount = $refund_amount + ($bookingHistory->ticket_cost + $bookingHistory->processing_fees + $bookingHistory->platform_fees);
					}
				}
			}

			$booking_total = $booking_total + $bookingM->total + $refund_amount;
			if ($trans != null) {
				$payment_total = $payment_total + $booking_total - $refund_amount;
			}

			$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $id)
				->where('operation', 'Payment')
				->get([
					'id',
					'ticket_cost',
					'processing_fees',
					'platform_fees'
				]);

			$paymentTrans = $paymentTransactions = $transactions->where('status', 'Completed');

			$refundTrans = $refundTransactions = $transactions->where('status', 'Refund');

			$paymentTransCount = count($paymentTrans);
			$paymentOnlyCheckNotChargeType = 0;
			$refundOnlyCheckNotChargeType = 0;

			foreach ($paymentTrans as $key => $value) {
				$paymentOnlyCheckNotChargeType += $value->amount;
				foreach ($refundTrans as $val) {
					if ($value->transaction_number == $val->transaction_number) {
						unset($paymentTrans[$key]);
					}
				}
			}

			$paymentTransactions = $transactions->where('status', 'Completed');

			if (request()->is('api/*')) {
				$paymentTrans = $paymentTrans->values();
				$paymentTransactions = $paymentTransactions->values();
				foreach ($paymentTrans as $value) {
					$value->card_last_4_digit = $value->bookingHistory->card_last_4_digit ?? '';
				}

				foreach ($paymentTransactions as $val) {
					$val->card_last_4_digit = $val->bookingHistory->card_last_4_digit ?? '';
				}

				$bookingM->username = $user['name'];
			}

			if ($refundTransactions->count()) {
				foreach ($paymentTransactions as $key => $payment) {
					$payment->refund_amount = 0;
					foreach ($refundTransactions as $refund) {
						if ($refund->transaction_number == $payment->transaction_number) {
							$payment->refund_amount += $refund->amount;
						}
					}
				}
			} else {
				foreach ($paymentTransactions as $payment) {
					$payment->refund_amount = 0;
				}
			}

			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $k => $value) {
				$paymentOnlyCheck += $value->ticket_cost + $value->processing_fees + $value->platform_fees;
			}

			if ($refundTransactions->count()) {
				foreach ($refundTrans as $val) {
					$refundOnlyCheckNotChargeType += $val->amount;
				}
			}
			$remainingAmount = $paymentOnlyCheckNotChargeType - $refundOnlyCheckNotChargeType;

			$historyData = HistoryService::historyData($bookingM);

			$splitRefundCheck = $historyData['splitRefundCheck'];
			$ticketCostRefundCheckChangePeople = $historyData['ticketCostRefundCheckChangePeople'];
			$historyDet = $historyData['historyData'];

			$createdByWithName = $historyData['createdByWithName'];
			$payments = DB::table('transactions')
				->where('tour_booking_id', $id)
				->where('status', 'Completed')
				->groupBy('created_at')
				->get(['amount', 'created_at', 'via']);

			$invoiceDetails = [];
			if ($bookingM->via == 'Invoiced') {
				$invoiceDetails = $this->invoiceBooking::join('invoices AS iv', 'iv.id', '=', 'invoice_bookings.invoice_id')
					->where('tour_booking_id', $id)
					->select(
						'iv.id',
						'iv.status',
						'invoice_bookings.invoice_id'
					)
					->orderBy('invoice_bookings.id', 'DESC')
					->first();
			}

			if (request()->is('api/*')) {
				if (count($historyDet) > 0) {
					$discountPromoCode = isset($bookingM->discount_promo_code) ? $bookingM->discount_promo_code : "";
					$historyDet = HistoryService::historyDetails($historyDet, $discountPromoCode);
				}

				for ($a = 0, $ac = count($payments), $paid = 0; $a < $ac; $a++)
					$paid = $payments[$a]->amount;

				$amount = 0;
				$paidF = 0;
				if (!empty($trans)) {
					$paidF = $trans->amount;
				}

				if (!empty($tourBookingHistory)) {
					foreach ($tourBookingHistory as $bookingHistory) {
						$amount += $bookingHistory->ticket_cost + $bookingHistory->processing_fees + $bookingHistory->platform_fees;
					}
					$amount = number_format((float)$amount, 2, '.', '');
					$amount = (float)($amount);
					$paidF = $paymentOnlyCheck - $amount;
				}

				$paidAmount = $paidF;

				if ($paid == 0) {
					$unPaidAmount = $bookingM->total - $paid;
				} elseif (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
					$unPaidAmount = $bookingM->total - $paymentOnlyCheck;
				} else {
					$unPaidAmount = $amount;
				}

				$checkAmountJsonDecode = (isset($historyPaymentTime->details)) ? json_decode($historyPaymentTime->details) : '';
				$checkAmount = $checkAmountJsonDecode->amount ?? 0.0;

				if ($trans != null && $trans->status == "Completed") {
					$pay = round($paymentOnlyCheck);
					$bok = round($bookingM->booking_total);
					if ($bookingM->statusm != 'Cancelled' && $refund_amount > 0) {
						$paymentStatus = "Partial Refund";
					} elseif ($bookingM->statusm == 'Cancelled' && $refund_amount > 0) {
						$paymentStatus = (string)$refund_amount !== (string)$paymentOnlyCheck ? "Partial Refund" : "Refunded";
					} elseif (!empty($trans) && $trans->payment_type == 1 && $bok > $pay) {
						$paymentStatus = "Split";
					} else {
						$paymentStatus = "Paid in Full";
					}
				} else {
					$paymentStatus = 'UnPaid';
				}

				$forCardProcChargePercentage = 0;
				$totalTicketCost = BookingService::calculateTicketCost($bookingT);

				if ($totalTicketCost >= 0) {
					$subTotal = $totalTicketCost;
				} else {
					$subTotal = $bookingM->total;
				}

				$discountPercentage = $bookingM->discount2_percentage ?? 0;
				$discountAmount = $bookingM->discount2_value;
				$feesAmount = $bookingM->service_commission;

				$trans = $transactions->where('status', 'Completed')->sortByDesc('id')->first();
				if (!empty($trans)) {
					$payment_type = $trans->payment_type;
				} else {
					$trans = null;
				}

				$totalV = 0;
				if (!empty($trans) && $trans->payment_type == 1) {
					$totalAmount = $bookingM->total - $paymentOnlyCheck;
					$totalV =  number_format((float)$bookingM->total - $paymentOnlyCheck, 2, '.', '');
				} else {
					if (empty($trans) && $bookingM->service_commission > 0) {
						$totalAmount = $bookingM->total - $bookingM->service_commission;
					} else {
						$totalAmount = $bookingM->total;
					}
				}

				$bookingTotal = ($bookingM->booking_total != "0.00") ? $bookingM->booking_total : $bookingM->total;
				$totalRefundAmount = $amount;
				$paymentTotal = $paidF;
				$oldBookingId = oldBookingId();
				if ($bookingM->status != 'Completed') {
					$forCardProcChargePercentage = 0;
					$totalSubtotal = 0;

					foreach ($bookingT as $value) {
						$rate = 0;
						$per = 0;
						$touristSubtotal = 0;
						$cardProccharge = 0;
						if ($bookingM->is_group_booking == '1') {
							$rate = $value->rate + $value->additional_charge;
						} else {
							$rate = $value->rate + $value->additional_charge + $value->permit_fee;
							$per = $rate * ($value->additional_tax_percentage + $value->processing_charge_percentage) / 100;
						}
						if ($value->rate > 0) {
							if ($bookingM->is_group_booking == '1') {
								$touristSubtotal = $rate + $value->additional_charge;
								$totalSubtotal += $touristSubtotal;
							} else {
								$touristSubtotal = ($rate + $per) * $value->tourists;
								$totalSubtotal += (($rate + $per) * $value->tourists);
							}
							$cardProccharge = number_format((float)($touristSubtotal) * $value->original_processing_charge_percentage / 100, 2, '.', '');
							$forCardProcChargePercentage += $cardProccharge;
						}
					}

					if ($bookingM->is_affiliate == 'Yes') {
						if ($bookingM->discount2_value > 0) {
							if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
								$subtotal = $bookingM->total;
							} else {
								$oldDiscountAmt = number_format((($bookingM->total) * $bookingT[0]->nat_fees_percentage) / 100, 2, '.', '');
								$subtotal = $bookingM->total + $oldDiscountAmt;
							}
							$totalV = number_format((float)$subtotal, 2, '.', '');
						} else {
							if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
								if ($bookingM->id < $oldBookingId) {
									$processing_charge = $bookingM->total * 2.9 / 100;
									$subtotal = $bookingM->total + $processing_charge;
									$totalV = number_format((float)$subtotal, 2, '.', '');
								} else {
									$totalV = number_format((float)$bookingM->total, 2, '.', '');
								}
							} else {
								if (isset($bookingT[0]->nat_fees_percentage) && $bookingT[0]->nat_fees_percentage != "0.00") {
									$bookingFees = 0;
									if ($bookingM->is_affiliate == "Yes" && $bookingM->id < $oldBookingId) {
										$processing_charge = $bookingM->total * 2.9 / 100;
										$subtotal = $bookingM->total + $processing_charge;
									} else {
										$subtotalamount = 0;
										foreach ($bookingT as $key => $booking_t) {
											if ($bookingM->is_group_booking == '1') {
												$rate = $booking_t->rate + $booking_t->additional_charge;
												$subtotalamount = $subtotalamount + $rate;
											} else {
												$rate = $booking_t->rate + $booking_t->additional_charge + $booking_t->permit_fee;
												$subtotalamount = $subtotalamount + $booking_t->tourists * $rate;
											}
											$per = roundout(($rate * ($booking_t->nat_fees_percentage) / 100), 2);
											$bookingFees = $bookingFees + $booking_t->tourists * $per;
										}
										$subtotal = $subtotalamount + $bookingFees;
									}
								} else {
									$subtotal = $bookingM->total + $forCardProcChargePercentage;
								}
								$totalV = number_format((float)$subtotal, 2, '.', '');
							}
						}
					} else {
						$forCardProcChargePercentageForOld = 0;
						$serviceCommissionTotal = 0;
						if ($bookingM->id < $oldBookingId) {
							foreach ($bookingT as $bt) {
								if ($bookingM->is_group_booking == '1') {
									$rate = $bt->rate + $bt->additional_charge;
									$subtotalOld = $rate;
									$touristSubtotalOld = $subtotalOld;
								} else {
									$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee;
									$per = $rate * ($bt->additional_tax_percentage) / 100;
									$subtotalOld = $rate + $per;
									$touristSubtotalOld = $subtotalOld * $bt->tourists;
								}
								$cardProcchargeOld = number_format((float) ($touristSubtotalOld) * 2.9 / 100, 2, '.', '');
								$forCardProcChargePercentageForOld += $cardProcchargeOld;
							}
						} else {
							foreach ($bookingT as $bt) {
								if ($bookingM->is_group_booking == '1') {
									$rate = $bt->rate + $bt->additional_charge;
								} else {
									$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee;
								}
								$per = roundout(($rate * ($bt->nat_fees_percentage) / 100), 2);
								if ($bookingM->is_group_booking == '1') {
									$serviceCommissionTotal += $per;
								} else {
									$serviceCommissionTotal += $bt->tourists * $per;
								}
							}
						}
						if ($bookingM->discount2_percentage > 0) {
							if ($bookingM->id < $oldBookingId) {
								$oldDiscountAmt = number_format((($totalSubtotal + $forCardProcChargePercentageForOld) * $bookingM->discount2_percentage) / 100, 2, '.', '');
								if ($bookingM->is_affiliate == "No") {
									$percentage = roundout(((($totalSubtotal + $forCardProcChargePercentageForOld - $oldDiscountAmt) * 6) / 100), 2);
								} else {
									$percentage = 0;
								}

								if ($bookingM->service_commission == null || $bookingM->service_commission == "0.00") {
									$totalV = ($totalSubtotal + $forCardProcChargePercentageForOld - $oldDiscountAmt) + $percentage;
								} else {
									$totalV = $bookingM->total;
								}
							} else {
								$oldDiscountAmt = number_format((($totalSubtotal + $forCardProcChargePercentage) * $bookingM->discount2_percentage) / 100, 2, '.', '');
								$percentage = roundout(((($totalSubtotal + $forCardProcChargePercentage - $oldDiscountAmt) * $bookingT[0]->nat_fees_percentage) / 100), 2);
								if ($bookingM->service_commission == null || $bookingM->service_commission == "0.00") {
									$totalV = ($totalSubtotal + $forCardProcChargePercentage - $oldDiscountAmt) + $percentage;
								} else {
									$totalV = $bookingM->total;
								}
							}
						} else {
							if ($bookingM->id < $oldBookingId) {
								if ($bookingM->is_affiliate == "No") {
									$percentage = roundout(((($bookingM->total + $forCardProcChargePercentageForOld) * 6) / 100), 2);
								} else {
									$percentage = 0;
								}

								if ($bookingM->service_commission == null || $bookingM->service_commission == "0.00") {
									$totalV = $bookingM->total + $forCardProcChargePercentageForOld + $percentage;
								} else {
									$totalV = $bookingM->total;
								}
							} else {
								if ($bookingM->discount2_value > 0 && isset($bookingT[0]->nat_fees_percentage)) {
									$serviceCommissionTotal = roundout(($bookingM->total * ($bookingT[0]->nat_fees_percentage) / 100), 2);
									$totalV = $bookingM->total + $serviceCommissionTotal;
								} else {
									if ($serviceCommissionTotal > 0 && $bookingM->via == null && ($bookingM->service_commission == null || $bookingM->service_commission == "0.00")) {
										$totalV = $bookingM->total + $serviceCommissionTotal;
									} else {
										$totalV = $bookingM->total;
									}
								}
							}
						}

						$totalV = (string)$totalV;
					}
				}
			}

			$tourPackageRate = $this->tourPackageRate::where('affiliate_id', $bookingM->customer_id)
				->where('scheduling_year', date('Y', strtotime($bookingM->date)))
				->first();

			$isAffiliate = $bookingM->is_affiliate;

			if ($tourPackageRate == null) {
				$isAffiliate = 'No';
			}

			if ($isAffiliate == 'Yes') {
				$isBookedFromFrontend = bookedFromFrontEnd($bookingM->id);
				if ($isBookedFromFrontend) {
					$isAffiliate = 'No';
				}
			}

			$affiliateId = ($isAffiliate == "Yes") ? $bookingM->customer_id : null;

			$mixedRateGroups = BookingService::getMixedRateGroups(
				$bookingM,
				$bookingT,
				$isAffiliate,
				$affiliateId,
				$rateGroupWiseTourists,
				$rateGroupWiseInfants,
				$rateGroupWiseTotal,
				$rateGroupWiseRate,
				$rateGroupWiseAdditionalCharge,
				$rateGroupWiseOldServiceCommission,
				$rateGroupWisePermitFeesRefunded,
				$rateGroupWiseNoPermitFees
			);

			$rateGroups = $mixedRateGroups['rateGroups'];
			$paxWiseGroupRateIds = $mixedRateGroups['paxWiseGroupRateIds'];
			$paxWiseGroupRates = $mixedRateGroups['paxWiseGroupRates'];
			$paxWiseGroupAdditionalCharges = $mixedRateGroups['paxWiseGroupAdditionalCharges'];

			$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $bookingM->id)->where('deleted_by', 0)->first();

			$isRestoreForCard = false;
			if (count($historyDet) > 0) {
				foreach ($historyDet as $hist) {
					if ($hist->operation == 'Cancelled' && $bookingM->via == 'Card' && ($hist->payment_method == "Don't Refund" || $hist->payment_method == "don't refund")) {
						$isRestoreForCard = true;
					}
					$hist->transaction_id = $hist->transaction_id == 0 ? null : $hist->transaction_id;
				}
			}

			$checkIns = checkins();
			$checKInsTourOperator = json_decode($bookingM->tour_op_checkins, true);

			$invoiced = DB::table('invoices as i')
				->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
				->where('ib.tour_booking_id', $id)
				->first(['status']);

			// Disable features based on block time duration and days/hours added for affiliates to block access for affiliates
			$affiliateFeature = 1;
			if ($userType == "Affiliate") {
				$bookingDate = $bookingM->date;
				$bookingTime = $bookingM->time_from;
				$bookingSlotTime = Carbon::parse($bookingDate . ' ' . $bookingTime);
				$carbonIns = Carbon::parse($bookingDate . ' ' . $bookingTime);
				$affiliate = $this->customer::findOrFail($bookingM->customer_id);

				// Execute only when duration is greater than 0 days or hours
				if ($affiliate->duration > 0) {
					if ($affiliate->day_hours == 1) {
						$carbonIns->subDays($affiliate->duration);
					} else if ($affiliate->day_hours == 2) {
						$carbonIns->subHours($affiliate->duration);
					}

					if (($carbonIns <= $bookingSlotTime) && (Carbon::now() >= $carbonIns)) {
						$affiliateFeature = 0;
					}
				}
			}

			// Customer Data
			$customerData = $this->customer::where('id', $bookingM->customer_id)->first();

			if (getNextYearCommissionPercentage($bookingM->date)) {
				$affiliateCommission = getAffiliateCommissionPercentage($customerData->id);
				$customerData->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'];
			}

			if (request()->is('api/*') && $customerData) {
				$customerData->day_hours = (string)$customerData->day_hours;
				$customerData->updated_by = (int)$customerData->updated_by;
				$customerData->affiliate_processing_percentage = (string) $customerData->affiliate_processing_percentage;
			}

			// Hotel Section
			$hotels = $this->hotel::where('tour_package_id', $bookingM->tour_package_id)->get();
			$hotel = null;
			if ($bookingM->hotel_id) {
				$hotel = $this->hotel::find($bookingM->hotel_id);
			}
			$setting = hasSetting('INVOICE_ENABLE_DISABLE');
			$switchPackageSetting = hasSetting('SWITCH_PACKAGE');

			if ($switchPackageSetting == '1') {

				if ($userType == 'Admin') {
					$tourPackagesQuery = $this->tourPackage::where('status', 'Active')
						->where('tour_operator_id', $bookingM->tour_operator_id)
						->whereNot('id', $bookingM->tour_package_id);

					if ($bookingM->is_group_booking == '1') {
						$tourPackagesQuery = $tourPackagesQuery->where('is_group_rate_enabled', 1);
					} else {
						$tourPackagesQuery = $tourPackagesQuery->where('is_group_rate_enabled', 0);
					}

					$tourPackages = $tourPackagesQuery->orderBy('Order_by')->get(['id', 'name']);
				} else {
					$tourPackagesQuery = $this->tourPackage::when($userType == 'Affiliate', function ($q) {
						return $q->whereHas('packageAffiliate', function ($q) {
							$q->where('affiliate_id', Auth::id());
						});
					})
						->where('status', 'Active')
						->where('tour_operator_id', $bookingM->tour_operator_id)
						->whereNot('id', $bookingM->tour_package_id);

					if ($bookingM->is_group_booking == '1') {
						$tourPackagesQuery = $tourPackagesQuery->where('is_group_rate_enabled', 1);
					} else {
						$tourPackagesQuery = $tourPackagesQuery->where('is_group_rate_enabled', 0);
					}

					$tourPackages = $tourPackagesQuery->orderBy('Order_by')->get(['id', 'name']);
				}
			}

			$packageId = $bookingM->tour_package_id;
			$packageSwitchedCount = DB::table('tour_booking_package_swipe_history')->where('tour_booking_id', $id)
				->where(function ($query) use ($packageId) {
					return $query->where('old_tour_package_id', $packageId)
						->orWhere('new_tour_package_id', $packageId);
				})->count();

			$pricedAddons = BookingService::fetchAddonsWithPrice($id);
			$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($id, $bookingT[0]->nat_fees_percentage, $trans);

			// Check if booking has old add-ons (from tour_booking_forms table)
			$hasOldAddons = false;
			$tourBookingForm = DB::table('tour_booking_forms')->where('tour_booking_id', $id)->first();
			if ($tourBookingForm && !empty($tourBookingForm->form_submission)) {
				$hasOldAddons = true;
			}

			$managers = BookingService::getManagers();
			$tourGuides = BookingService::getTourGuides();
			$requestPermissions = BookingService::requestPermissions($id);
			$managerRequestEnable = BookingService::managerRequestEnable($id);
			$fieldRequestPermissions = BookingService::fieldRequestPermissions($id);
			$schoolWaiverEntries = $this->getSchoolWaiverEntries($id);
			if (request()->is('api/*')) {
				$isSwitchPackageDisabled = isSwitchPackageDisabled($bookingM, $trans, $paymentTrans, $refundTrans, $packageSwitchedCount, $pricedAddons, $payLaterAddons) ? 'disabled' : '';
				$vars = [
					'page_title' => 'Tour Booking Info',
					'bookingM' => $bookingM,
					'packageList' => $packageList,
					'bundlePackageCheckInsList' => $bundlePackageCheckInsList,
					'customChargeList' => $customChargeList,
					'customChargePrice' => $customChargePrice,
					'isCustomCharge' => $isCustomCharge,
					'bok' => round($bookingM->booking_total),
					'bookingT' => $bookingT,
					'notRedeemedTickets' => $notRedeemedTickets,
					'redeemedTickets' => $redeemedTickets,
					'history' => $historyDet,
					'payments' => $payments,
					'staffUsers' => $staffUsers,
					'createdByWithName' => $createdByWithName,
					'trans' => $trans,
					'transF' => $transF,
					'timezone' => $bookingM->timezone,
					'invoiceDetails' => ($bookingM->via == 'Invoiced') ? $invoiceDetails : '',
					'paymentacknowledgement' => $paymentAcknowledgement,
					'makePaymentValueForCardFailed' => $makePaymentValueForCardFailed,
					'paymentTotalForCard' => (string) $totalV ?? '0',
					'Refund_Amount' => $refund_amount,
					'Booking_Total' => $booking_total,
					'Payment_Total' => $payment_total,
					'paidAmount' => (isset($paidAmount)) ? number_format((float)$paidAmount, 2, '.', '') : '',
					'unPaidAmount' => (isset($unPaidAmount)) ? number_format((float)$unPaidAmount, 2, '.', '') : '',
					'paymentStatus' => $paymentStatus ?? '',
					'subTotal' => (isset($subTotal)) ? number_format((float)$subTotal, 2, '.', '') : '',
					'discountPercentage' => $discountPercentage ?? '',
					'discountAmount' => $discountAmount ?? '',
					'feesAmount' => (isset($feesAmount)) ? number_format((float)$feesAmount, 2, '.', '') : '',
					'totalAmount' => (isset($totalAmount)) ? number_format((float)$totalAmount, 2, '.', '') : '',
					'bookingTotal' => (isset($bookingTotal)) ? number_format((float)$bookingTotal, 2, '.', '') : '',
					'totalRefundAmount' => (isset($totalRefundAmount)) ? number_format((float)$totalRefundAmount, 2, '.', '') : '',
					'paymentTotal' => (isset($paymentTotal)) ? number_format((float)$paymentTotal, 2, '.', '') : '',
					'paymentOnlyCheck' => $paymentOnlyCheck,
					'remainingAmount' => $remainingAmount,
					'pay' => round($paymentOnlyCheck),
					'transP' => $transP,
					'paymentTrans' => $paymentTrans,
					'paymentCheck' => $payment_type ?? 0,
					'rateGroups' => $rateGroups,
					'paymentTransCount' => $paymentTransCount,
					'paymentTransactions' => $paymentTransactions,
					'vacantSeats' => $bookingM->slot_seats - $bookingM->slot_all_tourists_count,
					'nat_fees_percentage' => $tourBookingTransaction->nat_fees_percentage,
					'stripe_fees_percentage' => $tourBookingTransaction->stripe_fees_percentage,
					'isRestoreForCard' => $isRestoreForCard,
					'tourOperatorCheckInStatues' => $checKInsTourOperator,
					'splitRefundCheck' => $splitRefundCheck,
					'ticketCostRefundCheckChangePeople' => $ticketCostRefundCheckChangePeople,
					'invoiced' => $invoiced,
					'affiliateFeature' => $affiliateFeature,
					'checkAmount' => $checkAmount ?? 0.0,
					'hotels' => $hotels,
					'hotel' => $hotel,
					'cartBookingIds' => (cartView() == 1) ? $cartBookingIds : [],
					'comboBookingsData' => $comboBookingsData,
					'customerData' => $customerData,
					'isImportedBooking' => $isImportedBooking,
					'cashFees' => cashFees(),
					'setting' => $setting,
					'cartPaymentTotalForCash' => number_format((float)$cartPaymentTotalForCash, 2, '.', ''),
					'cartPaymentTotalForCard' => number_format((float)$cartPaymentTotalForCard, 2, '.', ''),
					'cartPaymentTotalForBillable' => number_format((float)$cartPaymentTotalForBillable, 2, '.', ''),
					'paymentsCountOfCart' => $paymentsCountOfCart,
					'bookedFromFrontend' => ($bookedFromFrontend == true) ? 1 : 0,
					'comboBookingsInfo' => $comboBookingsInfo,
					'packageSwitchedCount' => $packageSwitchedCount,
					'paxWiseGroupRateIds' => (!empty($paxWiseGroupRateIds)) ? array_values($paxWiseGroupRateIds) : [],
					'paxWiseGroupRates' => (!empty($paxWiseGroupRates)) ? array_values($paxWiseGroupRates) : [],
					'paxWiseGroupAdditionalCharges' => (!empty($paxWiseGroupAdditionalCharges)) ? array_values($paxWiseGroupAdditionalCharges) : [],
					'pricedAddons' => $pricedAddons,
					'payLaterAddons' => $payLaterAddons,
					'hasOldAddons' => $hasOldAddons,
					'totalDiscountPayment' => $totalDiscountPayment,
					'expire_at' => $bookingM->expire_at,
					'bookingContacts' => isset($bookingContacts) ? $bookingContacts : [],
					'switchPackage' => $switchPackageSetting,
					'tourPackages' => ($switchPackageSetting == '1') ? $tourPackages : [],
					'isSwitchPackageDisabled' => $isSwitchPackageDisabled,
					'managers' => $managers,
					'tourGuides' => $tourGuides,
					'requestPermissions' => $requestPermissions,
					'managerRequestEnable' => $managerRequestEnable,
					'fieldRequestPermissions' => $fieldRequestPermissions,
					'schoolWaiverEntries' => $schoolWaiverEntries,
					'hasSchoolWaiverEntries' => count(array_filter($schoolWaiverEntries, fn($e) => isset($e[4]))) > 0
				];
			} else {
				$vars = [
					'page_title' => 'Tour Booking Info',
					'bookingM' => $bookingM,
					'packageList' => $packageList,
					'bundlePackageCheckInsList' => $bundlePackageCheckInsList,
					'customChargeList' => $customChargeList,
					'customChargePrice' => $customChargePrice,
					'isCustomCharge' => $isCustomCharge,
					'bookingT' => $bookingT,
					'notRedeemedTickets' => $notRedeemedTickets,
					'redeemedTickets' => $redeemedTickets,
					'history' => $historyDet,
					'payments' => $payments,
					'staffUsers' => $staffUsers,
					'user' => $user,
					'trans' => $trans,
					'tourBookingHistory' => $tourBookingHistory ?? '',
					'historyPaymentTime' => $historyPaymentTime ?? '',
					'transF' => $transF,
					'timezone' => $bookingM->timezone,
					'invoiceDetails' => ($bookingM->via == 'Invoiced') ? $invoiceDetails : '',
					'paymentacknowledgement' => $paymentAcknowledgement,
					'makePaymentValueForCardFailed' => $makePaymentValueForCardFailed,
					'paymentTotalForCard' => $totalV ?? 0,
					'Refund_Amount' => $refund_amount,
					'Booking_Total' => $booking_total,
					'Payment_Total' => $payment_total,
					'paidAmount' => (isset($paidAmount)) ? number_format((float)$paidAmount, 2, '.', '') : '',
					'unPaidAmount' => (isset($unPaidAmount)) ? number_format((float)$unPaidAmount, 2, '.', '') : '',
					'paymentStatus' => $paymentStatus ?? '',
					'subTotal' => (isset($subTotal)) ? number_format((float)$subTotal, 2, '.', '') : '',
					'discountPercentage' => $discountPercentage ?? '',
					'discountAmount' => $discountAmount ?? '',
					'feesAmount' => (isset($feesAmount)) ? number_format((float)$feesAmount, 2, '.', '') : '',
					'totalAmount' => (isset($totalAmount)) ? number_format((float)$totalAmount, 2, '.', '') : '',
					'bookingTotal' => (isset($bookingTotal)) ? number_format((float)$bookingTotal, 2, '.', '') : '',
					'totalRefundAmount' => (isset($totalRefundAmount)) ? number_format((float)$totalRefundAmount, 2, '.', '') : '',
					'paymentTotal' => (isset($paymentTotal)) ? number_format((float)$paymentTotal, 2, '.', '') : '',
					'paymentOnlyCheck' => $paymentOnlyCheck,
					'remainingAmount' => $remainingAmount,
					'transP' => $transP,
					'paymentTrans' => $paymentTrans,
					'refundTrans' => $refundTrans,
					'paymentCheck' => $payment_type ?? 0,
					'rateGroups' => $rateGroups,
					'paymentTransCount' => $paymentTransCount,
					'paymentTransactions' => $paymentTransactions,
					'nat_fees_percentage' => $tourBookingTransaction->nat_fees_percentage,
					'stripe_fees_percentage' => $tourBookingTransaction->stripe_fees_percentage,
					'checkIns' => $checkIns,
					'checKInsTourOperator' => $checKInsTourOperator,
					'max_tourits' => $maxTourists,
					'isRestoreForCard' => $isRestoreForCard,
					'splitRefundCheck' => $splitRefundCheck,
					'ticketCostRefundCheckChangePeople' => $ticketCostRefundCheckChangePeople,
					'invoiced' => $invoiced,
					'affiliateFeature' => $affiliateFeature,
					'hotels' => $hotels,
					'hotel' => $hotel,
					'cartBookingIds' => (cartView() == 1) ? $cartBookingIds : [],
					'comboBookingsData' => $comboBookingsData,
					'customerData' => $customerData ?? '',
					'isImportedBooking' => $isImportedBooking,
					'cartPaymentTotalForCash' => $cartPaymentTotalForCash,
					'cartPaymentTotalForCard' => $cartPaymentTotalForCard,
					'cartServiceCommissionTotal' => $cartServiceCommissionTotal,
					'paymentsCountOfCart' => $paymentsCountOfCart,
					'bookedFromFrontend' => ($bookedFromFrontend == true) ? 1 : 0,
					'switchPackage' => $switchPackageSetting,
					'tourPackages' => ($switchPackageSetting == '1') ? $tourPackages : [],
					'packageSwitchedCount' => $packageSwitchedCount,
					'paxWiseGroupRateIds' => $paxWiseGroupRateIds,
					'paxWiseGroupRates' => $paxWiseGroupRates,
					'paxWiseGroupAdditionalCharges' => $paxWiseGroupAdditionalCharges,
					'pricedAddons' => $pricedAddons,
					'payLaterAddons' => $payLaterAddons,
					'hasOldAddons' => $hasOldAddons,
					'totalDiscountPayment' => $totalDiscountPayment,
					'expire_at' => $bookingM->expire_at,
					'bookingContacts' => isset($bookingContacts) ? $bookingContacts : [],
					'managers' => $managers,
					'tourGuides' => $tourGuides,
					'requestPermissions' => $requestPermissions,
					'managerRequestEnable' => $managerRequestEnable,
					'fieldRequestPermissions' => $fieldRequestPermissions,
					'schoolWaiverEntries' => $schoolWaiverEntries,
					'hasSchoolWaiverEntries' => count(array_filter($schoolWaiverEntries, fn($e) => isset($e[4]))) > 0,
				];
			}

			return $vars;
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();

			HistoryService::storeCardPaymentErrorInHistory($id, $exception);

			throw new GeneralCoreException($exception);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			$tourBooking = $this->tourBooking::where('id', $id)->count();
			if ($tourBooking > 0) {
				HistoryService::storeNormalErrorInHistory($id, $exception);
			}

			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get booking status
	 *
	 * @param int $id
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function bookingStatus(int $id)
	{
		try {
			$status = $this->tourBooking::where('id', $id)->first(['status']);

			return $status;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Fetch the booking print details
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function bookingPrint($id): mixed
	{
		try {
			$userType = userType();

			$bookingM = BookingService::information($userType, $id);

			$printInfo = PrintService::data($bookingM);

			return $printInfo;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();

			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Fetch the booking history details
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function bookingHistory($id): mixed
	{
		try {
			$userType = userType();

			$bookingM = BookingService::information($userType, $id);

			$historyData = HistoryService::historyData($bookingM);

			$historyDet = HistoryService::historyDetails($historyData['historyData']);

			return $historyDet;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();

			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Resend the booking information via email
	 *
	 * @param object $request
	 * @param int $id
	 * @return array
	 *
	 * @version 1.0.0
	 */
	public function resendEmail($request, int $id)
	{
		try {
			$info = DB::table('tour_slot_times AS tst')
				->join('tour_bookings_master as bm', 'bm.tour_slot_id', '=', 'tst.id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('customers AS c', 'c.id', '=', 'bm.customer_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('bm.id', $id)
				->select([
					'tst.time_from',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'tp.arrival_time_duration',
					'ct.state_id',
					'st.permit_fee',
					'c.phone_number AS cust_phone',
					'bm.name AS cust_name',
					'bm.group_id',
					'bm.external_booking_id',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					't_o.tenant_id',
					'c.is_affiliate AS is_affiliate',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					'tp.name as packageName',
					'tp.attach_waiver_pdf',
					'tp.waiver_pdf',
					DB::raw("(SELECT SUM(tbm.tourists)
						FROM tour_bookings_master AS tbm
						WHERE tbm.tour_slot_id = tst.id
							AND tbm.status != 'Cancelled'
						GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();

			$booking = [
				'tour_d_t' => $info->date . ' ' . $info->time_from,
			];

			$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
				->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', '=', $id)
				->where('tbt.deleted_by', 0)
				->select('tpr.rate_for', 'tbt.tourists')
				->get();

			$totalTourists = 0;
			foreach ($tourListwithSeat as $value) {
				$totalTourists = $totalTourists + $value->tourists;
			}

			HistoryService::storeEmailInHistory($id, 'Resend Booking');

			$tourBooking = $this->tourBooking::where('id', $id)->first();

			if ($request->email) {
				$tourBooking->email = $request->email;
			}

			$cancelRefundedAmount = 0;
			if ($request->mail_type == 2) {
				$historyCancelBooking = DB::table('tour_booking_history as tbh')
					->where('tbh.tour_booking_id', '=', $id)
					->where('tbh.operation', '=', 'Cancelled')
					->select('tbh.details', 'tbh.payment_method')
					->first();

				if ($historyCancelBooking) {
					$details = json_decode($historyCancelBooking->details);
					if ($historyCancelBooking->payment_method != "Don't Refund") {
						$cancelRefundedAmount = (isset($details->amount)) ? $details->amount : 0;
					}
				}
			}

			if ($request->mail_type == 2) {
				$arEmailData = [
					'tour_d_t' => $booking['tour_d_t'],
					'bookingId' => $id,
					'bookingGroupId' => $tourBooking->group_id,
					'custName' => $tourBooking->name,
					'custEmail' => $tourBooking->email,
					'custPhone' => $tourBooking->phone_number,
					'cancelRefundedAmount' => $cancelRefundedAmount
				];
			} else {
				$arEmailData = [
					'tour_d_t' => $booking['tour_d_t'],
					'bookingId' => $id,
					'bookingGroupId' => $tourBooking->group_id,
					'custName' => $tourBooking->name,
					'custEmail' => $tourBooking->email,
					'custPhone' => $tourBooking->phone_number
				];
			}

			/** Send SMS message */
			// $this->sendSMS($id, $info);

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				if ($request->mail_type == 2) {
					EmailService::sendBookingCancelledEmail($info, $arEmailData);
				} else {
					EmailService::sendAcknowledgementEmail($info, $arEmailData);
				}
			}

			return [];
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Reschedule the booking which is already created
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function reschedule(object $request, int $id, string $userType = null, int $userId = null)
	{
		try {
			$userType = $userType ?? userType();
			$userId   = $userId ?? Auth::id();

			$booking = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
				->where('tbm.id', $id)
				->first([
					'tbm.tourists',
					'tbm.tour_package_id',
					'tst.date',
					'tst.time_from',
					'tbm.name',
					'tbm.email',
					'tbm.phone_number',
					'tbm.customer_id',
					'cus.is_affiliate',
					'tbm.tour_slot_id',
					'tbm.expire_at'
				]);

			if (!$booking) {
				throw new Exception("Tour booking not found.");
			}

			$affiliateSql = '';
			$affiliateSeats = DB::raw('0 as afl_seats');
			$packageId = $booking->tour_package_id;
			if ($userType == 'Affiliate') {
				$showAllPackageBookings = PackageAffiliateAssignment::where('package_id', $packageId)
					->where('affiliate_id', $userId)
					->where('show_all_package_bookings', Constants::STATUS_ONE)
					->count();

				if ($showAllPackageBookings == Constants::STATUS_ZERO) {
					$affiliateSql = ' AND tbm2.customer_id = ' . $userId;
				}
			}

			$info = DB::table('tour_slot_times AS tst')
				->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->where('tst.id', $request->tour_slot_time)
				->first([
					'tst.time_slot_seats as seats',
					'tst.time_from',
					'tst.time_to',
					'tst.date',
					'tp.name AS package_name',
					'tp.id as tour_package_id',
					'tp.things_to_bring',
					'tp.important_notes',
					't_o.cancellation_policy',
					't_o.id as tour_operator_id',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.tenant_id',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					DB::raw("(SELECT SUM(tbm.tourists)
						FROM tour_bookings_master AS tbm
						WHERE tbm.tour_slot_id = tst.id
							AND tbm.status != 'Cancelled'
						GROUP BY tbm.tour_slot_id) AS booked_seats"),
					DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
	                    FROM tour_bookings_master AS tbm2
	                    WHERE tbm2.tour_slot_id = tst.id
	                        AND tbm2.status != 'Cancelled'
	                    " . $affiliateSql . "
	                    GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_booked_seats"),
					$affiliateSeats
				]);

			if (!$info) {
				throw new Exception("Tour or date-time slot not found.");
			}

			/* Validation for expired bookings */
			if (isset($booking->expire_at) && !empty($booking->expire_at)) {
				$expireTime = Carbon::parse($booking->expire_at);
				$rescheduleTime = Carbon::parse($info->date . ' ' . $info->time_from);
				if ($rescheduleTime >= $expireTime) {
					throw new Exception("You cannot reschedule a booking after expiration time.");
				}
			}

			/* Validation only for affiliates */
			// Disable features based on block time duration and days/hours added for affiliates to block access for affiliates
			$affiliateFeature = 1;

			if ($userType == 'Affiliate') {
				$bookingDate = $booking->date;
				$bookingTime = $booking->time_from;
				$bookingSlotTime = Carbon::parse($bookingDate . ' ' . $bookingTime);
				$carbonIns = Carbon::parse($bookingDate . ' ' . $bookingTime);
				$affiliate = $this->customer::findOrFail($booking->customer_id);

				// Execute only when duration is greater than 0 days or hours
				if ($affiliate->duration > 0) {
					if ($affiliate->day_hours == 1) {
						$carbonIns->subDays($affiliate->duration);
					} else if ($affiliate->day_hours == 2) {
						$carbonIns->subHours($affiliate->duration);
					}

					if (($carbonIns <= $bookingSlotTime) && (Carbon::now() >= $carbonIns)) {
						$affiliateFeature = 0;
					}
				}

				if ($affiliateFeature == 0) {
					throw new Exception("You cannot reschedule a booking; the booking exceeds block time.");
				}
			}

			/* START: validate the slot times with general and custom rates */
			$standardRateDateEnabled = hasSetting('CDR_PACKAGES');
			$affiliateId = null;
			if ($booking->is_affiliate == 'Yes') {
				$affiliateId = $booking->customer_id;
				$standardRateDateEnabled = hasSetting('CDR_AFFILIATES');
			}

			if ($standardRateDateEnabled) {
				$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $id)->where('deleted_by', 0)->first();
				$tourPackageRate = $this->tourPackageRate::find($tourBookingTransaction->tour_package_rate_id);
				$slotTimeId = 0;
				if ($tourPackageRate->custom_date_id) {
					$standardRateDate = $this->customRateDate::find($tourPackageRate->custom_date_id);
					if ($standardRateDate->apply_on_slots) {
						$slotTimeId = $request->tour_slot_time;
					}
				}

				$customRateDate = isCustomDateRateEnabled($info->date, $packageId, $affiliateId, $slotTimeId);

				$rateGroups = DB::table('tour_package_rates AS tpr')
					->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
					->where([
						'tpr.tour_package_id' => $packageId,
						'tpr.deleted_by' => 0,
						'tpr.scheduling_year' => date('Y', strtotime($info->date))
					])
					->when($affiliateId, function ($query) use ($affiliateId) {
						return $query->where('tpr.affiliate_id', $affiliateId);
					}, function ($query): mixed {
						return $query->whereNull('tpr.affiliate_id');
					})
					->when($customRateDate, function ($query) use ($customRateDate) {
						return $query->where('tpr.custom_date_id', $customRateDate);
					}, function ($query): mixed {
						return $query->whereNull('tpr.custom_date_id');
					})
					->orderBy('tpr.id')
					->select([
						'tpr.id'
					])
					->first();

				// if ($rateGroups->id != $tourPackageRate->id) {
				// 	throw new Exception("The selected time slot is not compatible with the existing booking rates. Please check the custom rates or selected options and try again.");
				// }
			}
			/* END */

			//Integrate code to restrict reschedule in different calendar year due to rategroups
			$oldDateYear = date('Y', strtotime($booking->date));
			$newDateYear = date('Y', strtotime($info->date));
			if ($oldDateYear != $newDateYear) {
				throw new Exception('You can\'t reschedule this booking for different calendar year.');
			}
			//stopNextYearBookings($info->date);

			// Integrate code to check affiliate have any seats remaining for selected slot
			if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
				$affiliateTimeSlots = DB::table('tour_slot_times_affiliate as tsta')
					->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
					->where('ast.affiliate_id', $userId)
					->where('tsta.tour_package_id', $packageId)
					->where('tsta.time_from', $info->time_from)
					->where('tsta.status', 'Active')
					->where('tsta.deleted_by', 0)
					->select('tsta.id as time_slot_id', 'tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
					->get()
					->toArray();

				$affiliateTimeSlots = (isset($affiliateTimeSlots) && !empty($affiliateTimeSlots)) ? $affiliateTimeSlots : [];
				$desiredSlot = collect($affiliateTimeSlots)->first(function ($slot) use ($info) {
					return $slot->time_from === $info->time_from;
				});

				$tourists = (int)$info->afl_booked_seats;
				$seats = isset($desiredSlot->seat_limit) ? (($info->seats > 0) ? (($info->seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($info->seats)) : 0) : 0;
				$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;

				if ($availableSeats <= 0) {
					throw new Exception("The selected slot is already full. No seats are available for selected slot.");
				}

				if ($booking->tourists > $availableSeats) {
					throw new Exception($availableSeats . ' seat(s) are only available for this slot. You can\'t reschedule this booking for selected slot.');
				}
			} else {
				$tourists = (int)$info->booked_seats;
				$seats = $info->seats;
				$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;
			}

			$bookingUpdate = $this->tourBooking::findOrFail($id);
			$bookingUpdate->update([
				'tour_slot_id' => $request->tour_slot_time,
				'updated_at' => mysqlDT(),
				'updated_by' => $userId,
				'updated_by_user_type' => $userType,
			]);

			$created_by_with_name = BookingService::createdUserInfo(false, null, $userId, $userType);

			HistoryService::storeRescheduledDataInHistory($id, $booking, $info, $created_by_with_name, $userId, $userType);
			// Update slot summary for old slot
			$seatsDiff = - ($booking->tourists);
			TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $seatsDiff);
			// Add slot summary to for slot
			$seatsAdd = $booking->tourists;
			TimeSlotAvailabilityService::updateSlotSummary($request->tour_slot_time, $seatsAdd);
			// Reopen old slot (and family siblings) now that the booking has moved away
			TimeSlotService::reopenSlotOnCancellation($booking->tour_package_id, $booking->tour_slot_id, $id);
			// Close new slot (and family siblings) for the arriving booking
			TimeSlotService::closeSlotOnBooking($booking->tour_package_id, $request->tour_slot_time);
			$arEmailData = [
				'tour_d_t' => $booking->date . " " . $booking->time_from,
				'bookingId' => $id,
				'custName' => $booking->name,
				'custEmail' => $booking->email,
				'custPhone' => $booking->phone_number,
				'rescheduledDateTime' => date('l, F j, Y @ g:i a', strtotime($info->date . " " . $info->time_from)),
			];

			$tourBooking = $this->tourBooking::find($id);
			if ($request->has('send_rb_mail')) {
				if ($request->send_rb_mail != null && ($request->send_rb_mail == "Yes" || $request->send_rb_mail == true)) {
					if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
						EmailService::sendBookingReschuduleEmail($info, $arEmailData);
					}

					HistoryService::storeEmailInHistory($id, 'Rescheduled Booking');
				}
			}

			if (isset($request->api) && $request->api == 1) {
				$tourBooking['dateFrom'] = $booking->date;
				$tourBooking['timeFrom'] = $booking->time_from;
				$tourBooking['dateTo'] = $info->date;
				$tourBooking['timeTo'] = $info->time_from;
				$tourBooking['created_by_with_name'] = $created_by_with_name;

				return $tourBooking;
			} else {
				$routeParams = ['booking' => $id];
				if ($request->has('modal')) {
					$routeParams['modal'] = 1;
				}

				return redirect()
					->route(routePrefix() . '.booking.show', $routeParams)
					->with('message', 'Booking has been rescheduled.');
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Cancel the booking which is already created
	 *
	 * @param object $request
	 * @param int $id
	 * @return object
	 * 
	 * @version 1.0.0
	 */
	public function cancel($request, int $id)
	{
		DB::beginTransaction();
		try {
			$userType = userType();

			$qry = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->where('tbm.id', $id);

			if ($userType == 'Tour Operator') {
				$routePrefix = '';
				$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else if ($userType == 'Affiliate') {
				$routePrefix = 'affiliate.';
				$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else {
				$routePrefix = 'admin.';
			}

			$booking = $qry->first([
				'tbm.tour_package_id AS pkg_id',
				'tbm.tourists',
				'tbm.status',
				'tbm.customer_id',
				'tst.id AS tour_slot_id',
				'tp.tour_operator_id',
				'tbm.email',
				'tbm.name',
				'tbm.group_id',
				'tbm.phone_number',
				'cus.is_affiliate',
				'tp.service_commission_percentage',
				DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
				'tst.time_from',
				'tst.time_to',
				'tsd.date',
				'tst.time_slot_seats as seats',
				'tp.name AS package_name',
				'tp.id as tour_package_id',
				'tp.things_to_bring',
				'tp.important_notes',
				't_o.cancellation_policy',
				't_o.id as tour_operator_id',
				't_o.name AS tour_operator',
				't_o.email AS tour_op_email',
				't_o.phone_number AS tour_op_phone',
				't_o.website AS tour_op_website',
				't_o.logo AS tour_op_logo',
				'tp.service_commission_percentage',
				'cus.affiliate_processing_percentage',
				'cus.stripe_affiliate_percentage',
				'tp.stripe_commission_percentage',
				't_o.tenant_id',
				'tp.email as packageEmail',
				'tp.from_name as packageFromName',
				'tp.from_email as packageFromEmail',
				'tp.api_key as packageApiKey',
				'tbm.service_commission',
				'tbm.is_group_booking',
				DB::raw("(SELECT COUNT(tbm.tourists)
				FROM tour_bookings_master AS tbm
				WHERE tbm.tour_slot_id = tst.id
					AND tbm.status != 'Cancelled'
				GROUP BY tbm.tour_slot_id) AS booked_seats")
			]);

			if (!$booking) {
				throw new Exception("Booking not found.");
			}

			$tourBooking = $this->tourBooking::find($id);

			//calculate refund value
			$refundOnly = $this->tourBookingHistory::where('tour_booking_id', $id)
				->where('operation', 'Refund')
				->get([
					'id',
					'details'
				]);

			$refundOnlyCheck = 0;
			foreach ($refundOnly as $value) {
				$details = json_decode($value->details, true);
				$refundOnlyCheck += $details['amount'];
			}

			$transaction = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->first();

			$tolerance = 0.00001;
			// if ($transaction && ((abs($refundOnlyCheck - (float)$tourBooking->booking_total) < $tolerance) || ($refundOnlyCheck == (float)$tourBooking->booking_total))) {
			// 	if (!isset($request->api)) {
			// 		$currentURL = Route::currentRouteName();
			// 		if ($currentURL == "affiliate.booking.cancel") {
			// 			return redirect()
			// 				->route('affiliate.booking.show',  ['booking' => $id])
			// 				->with('message', 'Unable to cancel booking as transaction already refunded.');
			// 		}

			// 		return redirect()
			// 			->route($routePrefix . 'tour-operator.booking.show', ['booking' => $id])
			// 			->withErrors(['errors' => 'Unable to cancel booking as transaction already refunded.'])
			// 			->withInput();
			// 	} else {
			// 		throw new Exception('Unable to cancel booking as transaction already refunded.');
			// 	}
			// }

			$info = $booking;
			$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $id)->where('deleted_by', 0)->first();
			if (!isset($request->api)) {
				$routeParams = ['booking' => $id];
			}

			$urlCheck = url()->previous();
			$modal = substr($urlCheck, -7);
			if ($modal == "modal=1") {
				$routeParams['modal'] = 1;
			}

			$net_amount = 0;
			if (!empty($transaction)) {
				$net_amount = $transaction->amount - $transaction->fee_amount;
			}

			$createdUserInfoService = BookingService::createdUserInfo(true, $booking);
			$user = $createdUserInfoService['user'];
			$created_by_with_name = $createdUserInfoService['created_by_with_name'];

			$tourBooking = $this->tourBooking::where('id', $id)->first();
			if (!empty($transaction) && $transaction->via == "Card") {
				if (!isset($request->is_cash)) {
					$stripeService = StripeService::stripeDetails($id);
					if ($stripeService == false) {
						if (!isset($request->api)) {
							$currentURL = Route::currentRouteName();
							if ($currentURL == "affiliate.booking.cancel") {
								return redirect()
									->route('affiliate.booking.show', $routeParams)
									->with('message', 'This booking is imported. Kindly use here Cash or Check/Wire option.');
							}
							return redirect()
								->route($routePrefix . 'tour-operator.booking.show', $routeParams)
								->withErrors(['errors' => 'This booking is imported. Kindly use here Cash or Check/Wire option.'])
								->withInput();
						} else {
							throw new Exception('This booking is imported. Kindly use here Cash or Check/Wire option.');
						}
					}
				}
			}

			$refundedAmount = 0;
			if ($request->has('cancel_booking') && $booking->status != 'Cancelled') {
				// for count total payment
				$transactionDetails = $this->transaction::where('tour_booking_id', $id)->get();
				$net_amount = 0;
				$refund_amount = 0;
				if (!empty($transactionDetails)) {
					foreach ($transactionDetails as $data) {
						if ($data->status == "Refund") {
							$refund_amount += $data->amount;
						} else {
							$net_amount += $data->amount;
						}
					}
					$total_amount = $net_amount - $refund_amount;
				}
				$total_amount = round($total_amount, 2);
				// Below line is for to check whether the refund is less than total
				$checkValidAmount = PartialRefundCancelService::refund($transaction, $tourBooking, $request, $total_amount);

				if ($checkValidAmount == 0) {
					if (!isset($request->api)) {
						$currentURL = Route::currentRouteName();
						if ($currentURL == "affiliate.booking.cancel") {
							return redirect()
								->route('affiliate.booking.show', $routeParams)
								->with('message', 'Unable to cancel booking. Please enter amount less than total.');
						}
						return redirect()
							->route($routePrefix . 'tour-operator.booking.show', $routeParams)
							->withErrors(['errors' => 'Unable to cancel booking. Please enter amount less than total.'])
							->withInput();
					} else {
						throw new Exception('Unable to cancel booking. Please enter amount less than total.');
					}
				}

				$update = $tourBooking->update([
					'status' => 'Cancelled',
					'updated_by' => $user->id,
					'updated_by_user_type' => $userType,
				]);

				// Reopen the time slot on cancellation
				TimeSlotService::reopenSlotOnCancellation($tourBooking->tour_package_id, $tourBooking->tour_slot_id, $id);

				$booking = (array)$booking;
				unset($booking['status']);
				$booking['notif_for'] = 'admin';
				$booking['env'] = config('app.env');
				$booking['notif_for'] = 'Tour Operator';

				if ($update) {
					if (!empty($transaction)) {
						// Below line is for to perform cancel booking refund options
						$cancelBookingRefund = $this->handleCancelRefundJobs($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType, $tourBookingTransaction);

						$refundedAmount = (isset($cancelBookingRefund['refundedAmount'])) ? $cancelBookingRefund['refundedAmount'] : 0;

						// Below line is for to update tour booking history table
						$this->handleCancelRefundHistoryUpdateJobs($tourBooking, $cancelBookingRefund['historyId'], $request->all(), $cancelBookingRefund['via'], $cancelBookingRefund['transaction_id']);

						// Void any unpaid remaining_payment link — the booking is cancelled so
						// the customer must not be able to pay the outstanding balance link.
						TourBookingPaymentLink::where('order_id', $tourBooking->group_id)
							->where('status', 'Unpaid')
							->where('section', 'remaining_payment')
							->get()
							->each(function ($link) {
								TourBookingPaymentLinkItem::where('payment_link_id', $link->id)
									->where('status', 'Unpaid')
									->update(['status' => 'Expired', 'updated_at' => Carbon::now()]);
								$link->status = 'Expired';
								$link->save();
							});
					} else {
						$data = [
							'amount' => $request->amount ?? 0,
							'comment' => $request->comment ?? ''
						];

						HistoryService::storeCancelledBookingInHistory($id, $tourBooking->tourists, $data, $created_by_with_name);

						// Refund Custom Charge
						CustomChargeBookingService::refundCustomChargeFromCancel($id, $request);

						// Refund any addon charges even when there is no main booking transaction
						$addonRefundType = match ($request->refund_type ?? null) {
							'full_refund'    => Constants::ADDONS_TICKET_REFUND,
							'100_refund'     => Constants::ADDONS_FULL_REFUND,
							'partial_refund' => Constants::ADDONS_PARTIAL_REFUND,
							default          => Constants::ADDONS_DONT_REFUND,
						};
						$addonMaxAmount = ($addonRefundType === Constants::ADDONS_PARTIAL_REFUND)
							? (float) ($request->partial_refund_value ?? 0)
							: null;
						(new TourBookingAddonRepository())->refundAddonsByBookingId($id, $addonRefundType, null, $addonMaxAmount);
					}
				} else {
					DB::rollBack();
					if (!isset($request->api)) {
						$currentURL = Route::currentRouteName();
						if ($currentURL == "affiliate.booking.cancel") {
							return redirect()
								->route('affiliate.booking.show', $routeParams)
								->with('message', 'Unable to cancel booking');
						}
						return redirect()
							->route($routePrefix . 'tour-operator.booking.show', $routeParams)
							->withErrors(['errors' => 'Unable to cancel booking.'])
							->withInput();
					} else {
						throw new Exception('Unable to cancel booking.');
					}
				}
			}

			if ($request->amount != 0) {
				$this->transaction::create([
					'tour_booking_id' => $id,
					'amount' => -$request->amount, // negative amount for refund
					'transaction_number' => $request->transaction_number,
					'via' => '',
					'response_init' => '',
					'response_end' => '',
					'response_end_datetime' => null,
					'status' => 'Completed',
					'visitor' => $request->getClientIp(),
					'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND
				]);
				DB::commit();

				// To pass in email
				$refundedAmount = $request->amount;

				$data = [
					'amount' => $refundedAmount,
					'comment' => $request->comment ?? '',
				];

				HistoryService::storeCancelledBookingInHistory($id, $tourBooking->tourists, $data, $created_by_with_name);
			}
			DB::commit();


			$booking = is_array($booking) ? $booking : (array)$booking;
			$TourBookingHistory = $this->tourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->first();

			if (!empty($transaction)) {
				if (isset($request->is_cash)) {
					$transaction->via = "Cash";
				}
				if ($transaction->via == "Card") {
					$details = json_decode($TourBookingHistory->details);
				}
			}


			$tourBooking = $this->tourBooking::find($id);

			if ($booking['email']) {
				$arEmailData = [
					'tour_d_t' => $booking['tour_d_t'],
					'bookingId' => $id,
					'bookingGroupId' => $booking['group_id'],
					'custName' => $booking['name'] ?? '',
					'custEmail' => $booking['email'] ?? '',
					'custPhone' => $booking['phone_number'] ?? '',
					'cardNumber' => $details->card_number ?? '',
					'cancelRefundedAmount' => $refundedAmount
				];

				if ($request->has('send_cancel_mail') && ((isset(Auth::user()->email) && in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) || !isset(Auth::user()->email))) {
					EmailService::sendBookingCancelledEmail($info, $arEmailData);
				}
			}

			if (isset($request->api) && $request->api == 1) {
				return $tourBooking;
			} else {
				$currentURL = Route::currentRouteName();
				if ($currentURL == "affiliate.booking.cancel") {
					return redirect()
						->route('affiliate.booking.show', $routeParams)
						->with('message', 'Booking has been cancelled and the refund is being issued');
				}
				return redirect()
					->route($routePrefix . 'tour-operator.booking.show', $routeParams)
					->with('message', 'Booking has been cancelled and the refund is being issued.');
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				$currentURL = Route::currentRouteName();
				if ($currentURL == "affiliate.booking.cancel") {
					return redirect()
						->route('affiliate.booking.show', $routeParams)
						->withErrors(['errors' => 'Unable to cancel booking.' . ' ' . $exception->getMessage()]);
				}
				return redirect()
					->route($routePrefix . 'tour-operator.booking.show', $routeParams)
					->withErrors(['errors' => 'Unable to cancel booking.' . ' ' . $exception->getMessage()]);
			}
		}
	}

	/**
	 * Handle the queue jobs to perform the cancel booking with different refund options for Cash/Invoiced/Wire Transfer
	 *
	 * @param  object  $tourBooking
	 * @param  int     $historyId
	 * @param  object   $request
	 * @param  string  $refundVia
	 * @param  int     $transactionId
	 *
	 * @return mixed
	 */
	public static function handleCancelRefundJobs($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType, $tourBookingTransaction)
	{
		// Perform the jobs based on cancel booking refund options
		if ($request['refund_type'] == 'dont_refund') {
			$cancelBookingRefund = DontRefundCancelService::cancel($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType);
		} elseif ($request['refund_type'] == 'full_refund') {
			$cancelBookingRefund = TicketRefundCancelService::cancel($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType);
		} elseif ($request['refund_type'] == 'partial_refund') {
			$cancelBookingRefund = PartialRefundCancelService::cancel($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType);
		} elseif ($request['refund_type'] == '100_refund') {
			$cancelBookingRefund = WeatherRefundCancelService::cancel($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType, $tourBookingTransaction);
		}
		return $cancelBookingRefund;
	}

	/**
	 * Restore the booking which is cancelled
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function restoreBooking(object $request, int $id)
	{
		DB::beginTransaction();
		try {
			// Fetch
			$booking = $this->tourBooking::find($id);
			$history = $this->tourBookingHistory::where(['tour_booking_id' => $id, 'operation' => 'Cancelled'])->first();

			// Remove refunded custom charge when cancelled
			CustomChargeBookingService::restoreCustomCharges($id);

			// Remove add-on refunds this cancellation itself created — a cancelled booking with
			// paid add-ons otherwise stayed permanently refunded even after being restored.
			// Scoped to $history->operation_at so an EARLIER, unrelated addon refund (e.g. from a
			// pax removed before this cancellation) is never resurrected — only refunds this
			// specific cancellation produced.
			(new TourBookingAddonRepository())->restoreAddonsRefundedByCancelBooking($id, $history->operation_at ?? null);

			$transaction = null;
			if (isset($history->transaction_id)) {
				$transaction = $this->transaction::find($history->transaction_id);
			}

			// Actions
			if ($transaction)
				$transaction->delete();
			if ($history)
				$history->delete();
			if ($booking)
				$booking->status = 'Booked';
			$booking->save();

			// Manipulate
			$created_by_with_name = BookingService::createdUserInfo();

			// Add
			HistoryService::storeRestoreBookingInHistory($id, $created_by_with_name);

			$isPaid = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->first();

			if ($isPaid == null) {
				// correct the total and booking total
				$bookingTransaction = $this->bookingTransact::where('tour_booking_id', $id)->where('deleted_by', 0)->get();
				$total = 0;
				foreach ($bookingTransaction as $pax) {
					$total += $pax->total;
				}
				$booking->total = $total + (float)$booking->service_commission;
				$booking->booking_total = $total + (float)$booking->service_commission;
				$booking->save();
			}

			DB::commit();

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				if (Auth::user()->tour_operator_id) {
					$routePrefix = '';
				} else {
					$routePrefix = 'admin.';
				}

				return redirect()
					->route($routePrefix . 'tour-operator.booking.show', ['booking' => $id])
					->withErrors(['errors' => 'Unable to restore booking.' . ' ' . $exception->getMessage()]);
			}
		}
	}
	/**
	 * Change the payment type of booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function calculateCardAmount(object $request, int $id)
	{
		$userType = userType();
		$oldBookingId = oldBookingId();
		$selectFields = [
			'tbm.id',
			'tbm.tour_package_id',
			'tbm.tourists',
			'tbm.discount',
			'tbm.discount2_value',
			'tbm.discount2_percentage',
			'tbm.service_commission',
			'tbm.total',
			'tbm.note',
			'tp.tour_operator_id',
			'tbm.customer_id',
			'c.is_affiliate',
			'tbm.booking_total',
		];

		$qry = DB::table('tour_bookings_master AS tbm')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id');

		if ($userType == 'Tour Operator') {
			$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		}

		$qry->where('tbm.id', $id);
		$bookingM = $qry->first($selectFields);

		if (!$bookingM) {
			throw new Exception("Tour booking not found.");
		}

		$selectFieldsBookingT = [
			'tbt.id',
			'tbt.tour_package_rate_id',
			'tbt.check_in_status',
			'tbt.refund_permitfee_status',
			'tbt.tourists',
			'tbt.processing_charge_percentage',
			'tbt.additional_tax_percentage',
			'tbt.permit_fee',
			'tbt.additional_charge',
			'tbt.state_tax',
			'tbt.rate',
			'tbt.total',
			'tpr.processing_charge_percentage as original_processing_charge_percentage',
			'tpr.rate_for',
			'tbt.nat_fees_percentage',
			'tbt.stripe_fees_percentage'
		];

		$bookingT = DB::table('tour_bookings_transact AS tbt')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
			->join('tour_operators as to', 'to.id', '=', 'tp.tour_operator_id')
			->where('tbt.tour_booking_id', $id)
			->where('tbt.deleted_by', 0)
			->orderBy('tpr.orderby', 'asc')
			->get($selectFieldsBookingT);

		if (!$bookingT) {
			throw new Exception("Transaction details not found.");
		}

		$natFeesPercentage = $bookingT[0]->nat_fees_percentage;

		$trans = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();

		$forCardProcChargePercentage = 0;
		$totalSubtotal = 0;

		foreach ($bookingT as $value) {
			$rate = 0;
			$per = 0;
			$touristSubtotal = 0;
			$cardProccharge = 0;
			$rate = $value->rate + $value->additional_charge + $value->permit_fee;
			$per = $rate * ($value->additional_tax_percentage + $value->processing_charge_percentage) / 100;
			if ($value->rate > 0) {
				$touristSubtotal = ($rate + $per) * $value->tourists;
				$totalSubtotal += (($rate + $per) * $value->tourists);
				$cardProccharge = number_format((float) ($touristSubtotal) * $value->original_processing_charge_percentage / 100, 2, '.', '');
				$forCardProcChargePercentage += $cardProccharge;
			}
		}

		$paymentTotalForCard = 0;
		if ($bookingM->is_affiliate == 'Yes') {
			if ($bookingM->discount2_value > 0) {
				if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
					$subtotal = $bookingM->total;
				} else {
					$oldDiscountAmt = number_format((($bookingM->total) * $natFeesPercentage) / 100, 2, '.', '');
					$subtotal = $bookingM->total + $oldDiscountAmt;
				}
			} else {
				if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
					if ($id < $oldBookingId) {
						$processing_charge = $bookingM->total * 2.9 / 100;
						$subtotal = $bookingM->total + $processing_charge;
					} else {
						$subtotal = $bookingM->total;
					}
				} else {
					$bookingFees = 0;
					if ($id < $oldBookingId) {
						$processing_charge = $bookingM->total * 2.9 / 100;
						$subtotal = $bookingM->total + $processing_charge;
					} else {
						$subtotalamount = 0;
						foreach ($bookingT as $key => $booking_t) {
							$rate = $booking_t->rate + $booking_t->additional_charge + $booking_t->permit_fee;
							$subtotalamount = $subtotalamount + $booking_t->tourists * $rate;
							$per = roundout(($rate * $natFeesPercentage / 100), 2);
							$bookingFees = $bookingFees + $booking_t->tourists * $per;
						}
						$subtotal = $subtotalamount + $bookingFees;
					}
				}
			}

			if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
				$paymentTotalForCard = $subtotal;
			}
		} else {
			$forCardProcChargePercentageForOld = 0;
			$serviceCommissionTotal = 0;
			if ($id < $oldBookingId) {
				foreach ($bookingT as $bt) {
					$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee;
					$per = $rate * ($bt->additional_tax_percentage) / 100;
					$subtotalOld = $rate + $per;
					$touristSubtotalOld = $subtotalOld * $bt->tourists;
					$cardProcchargeOld = number_format((float) ($touristSubtotalOld) * 2.9 / 100, 2, '.', '');
					$forCardProcChargePercentageForOld += $cardProcchargeOld;
				}
			} else {
				foreach ($bookingT as $bt) {
					$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee;
					$per = roundout(($rate * $natFeesPercentage / 100), 2);
					$serviceCommissionTotal += $bt->tourists * $per;
				}
			}

			if ($bookingM->discount2_percentage > 0) {
				if ($id < $oldBookingId) {
					$oldDiscountAmt = number_format((($totalSubtotal + $forCardProcChargePercentageForOld) * $bookingM->discount2_percentage) / 100, 2, '.', '');
					if ($bookingM->is_affiliate == "No") {
						$percentage = roundout(((($totalSubtotal + $forCardProcChargePercentageForOld - $oldDiscountAmt) * 6) / 100), 2);
					} else {
						$percentage = 0;
					}

					if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
						$paymentTotalForCard = ($totalSubtotal + $forCardProcChargePercentageForOld - $oldDiscountAmt) + $percentage;
					}
				} else {
					$oldDiscountAmt = number_format((($totalSubtotal + $forCardProcChargePercentage) * $bookingM->discount2_percentage) / 100, 2, '.', '');
					$percentage = roundout(((($totalSubtotal + $forCardProcChargePercentage - $oldDiscountAmt) * $natFeesPercentage) / 100), 2);

					if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
						$paymentTotalForCard = ($totalSubtotal + $forCardProcChargePercentage - $oldDiscountAmt) + $percentage;
					}
				}
			} else {
				if ($id < $oldBookingId) {
					if ($bookingM->is_affiliate == "No") {
						$percentage = roundout(((($bookingM->total + $forCardProcChargePercentageForOld) * 6) / 100), 2);
					} else {
						$percentage = 0;
					}

					if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
						$paymentTotalForCard = $bookingM->total + $forCardProcChargePercentageForOld + $percentage;
					}
				} else {
					if ($bookingM->discount2_value > 0) {
						$serviceCommissionTotal = roundout(($bookingM->total * ($natFeesPercentage) / 100), 2);
						if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
							$paymentTotalForCard = $bookingM->total + $serviceCommissionTotal;
						}
					} else {
						if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
							$paymentTotalForCard = $bookingM->total + $serviceCommissionTotal;
						}
					}
				}
			}
		}

		$paymentTotalForCard = number_format((float)$paymentTotalForCard, 2, '.', '');

		return [
			'paymentTotalForCard' => $paymentTotalForCard
		];
	}

	/**
	 * Keep add-on, custom charge, and discount charges' recorded payment type in sync
	 * with the booking's base ticket after changeHistoryPaymentType() switches it.
	 *
	 * Each of those settles through its own `transactions` row and `tour_booking_history`
	 * row (charge_type set), entirely separate from the base ticket row this function
	 * updates directly. Without this, they're left showing the booking's *previous*
	 * payment type indefinitely -- e.g. a booking switched Invoiced -> Cash would still
	 * have its custom charge showing via = 'Invoiced', which (among other things) makes
	 * it eligible to still be pulled onto an affiliate invoice via a `via = 'Invoiced'`
	 * filter even though the booking itself is no longer billable.
	 *
	 * Only touches still-active rows (Completed transactions / Payment history rows) --
	 * already-refunded charges keep their original payment type as a historical record.
	 *
	 * @param int $tourBookingId
	 * @param string $paymentType
	 * @return void
	 */
	private function cascadePaymentTypeToRelatedCharges(int $tourBookingId, string $paymentType): void
	{
		$relatedChargeTypes = ['addons', 'custom_charge', 'discount'];

		DB::table('transactions')
			->where('tour_booking_id', $tourBookingId)
			->where('status', 'Completed')
			->whereIn('charge_type', $relatedChargeTypes)
			->update(['via' => $paymentType]);

		DB::table('tour_booking_history')
			->where('tour_booking_id', $tourBookingId)
			->where('operation', 'Payment')
			->whereIn('charge_type', $relatedChargeTypes)
			->update(['payment_type' => $paymentType]);
	}

	/**
	 * Change the payment type of booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 *
	 * @version 1.0.0
	 */
	public function changeHistoryPaymentType(object $request, int $id)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$oldBookingId = oldBookingId();
			$isCashFees = cashFees();
			// Fetch
			$history = $this->tourBookingHistory::find($id);
			$transaction = $this->transaction::find($history->transaction_id);

			// Only a Card refund leaves real gateway state that a payment-type switch could desync.
			// Cash/Invoiced "refunds" (e.g. removing a pax, discounts) are ledger-only adjustments —
			// no money ever moved through Stripe — so they must not block the switch.
			$refundTrans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Refund')->where('via', 'Card')->whereNull('charge_type')->get(['id', 'amount']);
			$totalrefundedamount = 0;
			foreach ($refundTrans as $key => $val) {
				$totalrefundedamount += $val->amount;
			}

			if ($totalrefundedamount > 0) {
				throw new Exception('Unable to change payment type because one or more refunds done for the booking.');
			}

			// Only count real ticket payments here — add-ons and custom charges also log an
			// 'operation' => 'Payment' row (with charge_type set) for their own settlement, and
			// those aren't a second payment on the booking itself. Without whereNull('charge_type')
			// adding any add-on/custom charge permanently blocks payment type changes on the booking.
			$payments = $this->tourBookingHistory::where('operation', 'Payment')->where('tour_booking_id', $history->tour_booking_id)->whereNull('charge_type')->count();
			if ($payments > 1) {
				throw new Exception('Unable to change payment type because one or more payments done for the booking.');
			}

			// Manipulate
			$originalPaymentType = $history->payment_type;
			$newPaymentType = $request->payment_method;
			if (($originalPaymentType == 'Cash' && $newPaymentType == 'Pay Later') || ($originalPaymentType == 'Invoiced' && ($newPaymentType == 'Cash' && $isCashFees != '1'))) {
				$paymentType = ($newPaymentType == 'Pay Later') ? 'Invoiced' : $newPaymentType;
				$jsonData = json_decode($history->details, true);
				$changeAmount = BookingUtils::calculateChangeAmount($newPaymentType, $request, $jsonData);

				$jsonData['payment_type'] = $paymentType;

				$created_by_with_name = BookingService::createdUserInfo();

				// Actions
				$history->change_amount = $changeAmount;
				$history->payment_type = $paymentType;
				$history->details = json_encode($jsonData, JSON_NUMERIC_CHECK);
				$history->save();

				$transaction->via = $paymentType;
				$transaction->save();

				$this->cascadePaymentTypeToRelatedCharges($history->tour_booking_id, $paymentType);

				DB::commit();

				if ($paymentType == 'Invoiced') {
					$paymentType = 'Billable';
				}

				if ($originalPaymentType == 'Invoiced') {
					$originalPaymentType = 'Billable';
				}

				// Add
				HistoryService::storePaymentTypeUpdateInHistory($history->tour_booking_id, $id, $originalPaymentType, $paymentType, $created_by_with_name);
				DB::commit();

				return response()->json([
					'tourBookingId' => $history->tour_booking_id
				]);
			} else if ($newPaymentType == 'Card' || ($isCashFees == '1' && $newPaymentType == 'Cash')) {
				$tourBookingID = $history->tour_booking_id;
				$info = BookingUtils::getBookingDetails($tourBookingID);

				$isGroupBooking = $info->is_group_booking;

				$tourBooking = DB::table('tour_bookings_master AS tbm')
					->join('customers as c', 'c.id', '=', 'tbm.customer_id')
					->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
					->join('tour_packages as pkg', 'pkg.id', '=', 'tbm.tour_package_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
					->join('tour_operators AS t_o', 't_o.id', '=', 'pkg.tour_operator_id')
					->join('tour_slot_times as st', 'st.id', '=', 'tbm.tour_slot_id')
					->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
					->where('tbm.id', '=', $tourBookingID)
					->where('tbt.deleted_by', 0)
					->select(
						'tbm.id',
						'tbm.id as booking_id',
						'tbm.total',
						'tbm.discount2_value',
						'tbm.discount2_percentage',
						'c.name as Name',
						'sd.date as Date',
						'st.time_from as StartTime',
						'st.time_to as EndTime',
						'tp.package_has_slots',
						'c.is_affiliate',
						'c.phone_number as ContactNumber',
						'c.email as Email',
						'tbm.tourists',
						'c.id as customer_id',
						't_o.id as tour_operator_id',
						't_o.name as tour_operator_name',
						'tbm.service_commission',
						'tp.stripe_destination_id',
						'tp.name as package_name',
						'tp.service_commission_percentage',
						'c.affiliate_processing_percentage',
						'c.stripe_affiliate_percentage',
						'tp.stripe_commission_percentage',
						't_o.statement_descriptor',
						'pkg.tour_operator_id',
						'tbm.tour_package_id'
					)
					->get();

				$customer = $this->customer::where('id', $info->customer_id)
					->withTrashed()
					->first();

				$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

				// Assign the returned values to respective variables
				$natFeesPercentage = $commissionRates['nat_fees_percentage'];
				$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

				$bookingT = $this->bookingTransact::with('packageRate')
					->where('tour_booking_id', $tourBookingID)
					->where('deleted_by', 0)
					->get();

				// Calculate the booking amount
				$discountAmt = 0;
				$oldDiscountAmt = 0;
				$fees = 0;
				$result = EditBookingService::processCalculationsChangePaymentType($bookingT, $request, $info, $isGroupBooking, $natFeesPercentage, $stripeFeesPercentage, 0);
				$total = $result['total'];
				$serviceCommissionTotal = $result['serviceCommissionTotal'];
				DB::commit();

				$trans = $this->transaction::where('tour_booking_id', $tourBookingID)->where('status', 'Completed')->whereNull('charge_type')->first(['payment_type']);

				// Discount given on pay later option when booking created
				// Here is the total minus discount amount
				$totalForDiscountWithSplit = 0;

				if (!empty($trans) && ($trans->payment_type == 'Card')) {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception('Tour booking already paid.');
					} else {
						return response()->json([
							'tourBookingId' => $tourBookingID,
							'paid' => 1
						]);
					}
				}

				if ($info->discount2_value > 0) {
					$discountAmt = $info->discount2_value;
					$total -= $discountAmt;
				}

				// Add the service commission in the total amount
				if ($customer->is_affiliate == 'No') {
					if ($tourBookingID >= $oldBookingId) { // Set booking id 95440
						if ($discountAmt > 0 || $oldDiscountAmt > 0) {
							if ($totalForDiscountWithSplit) {
								$serviceCommission = roundout(($totalForDiscountWithSplit * $natFeesPercentage / 100), 2);
							} else {
								$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
							}
						} else {
							$serviceCommission = $serviceCommissionTotal;
						}
					} else {
						$serviceCommission = number_format((float)$total * 6 / 100, 2, '.', '');
					}

					$total = $total + $serviceCommission;
				} elseif ($customer->is_affiliate == 'Yes') {
					if ($tourBookingID >= $oldBookingId) { // Set booking id 95440
						if ($discountAmt > 0 || $oldDiscountAmt > 0) {
							if ($totalForDiscountWithSplit) {
								$serviceCommission = roundout(($totalForDiscountWithSplit * $natFeesPercentage / 100), 2);
							} else {
								$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
							}
						} else {
							$serviceCommission = $serviceCommissionTotal;
						}

						$total = $total + $serviceCommission;
					}
				}


				$createdByWithName = BookingService::createdUserInfo();

				$total = round($total, 2);
				$subTotal = round($total - $serviceCommission, 2);
				$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
				if ($newPaymentType == 'Card') {
					if ($paymentIntentId == '') {
						throw new Exception('You must provide Stripe Payment Intent Id');
					}

					$retrieveOptions = ['expand' => ['charges.data.transfer']];
					$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

					$cardDetails = StripeService::extractCardDetails($paymentIntent, $request->is('api/*'));
					$crd = $cardDetails['last4'];
					$cardType = $cardDetails['brand'];
					$from = $cardDetails['source'];
					$stripeMetadata = StripeUtils::generateStripeMetadataForChangePaymentType($info, $tourBookingID, $from);

					$updateParams = $stripeMetadata;
					app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

					if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
						$chargeParams = $stripeMetadata;
						app(PaymentGatewayInterface::class)->updateConnectedPayment(
							$paymentIntent->charges->data[0]->transfer->destination_payment,
							$chargeParams,
							$paymentIntent->charges->data[0]->transfer->destination
						);
					}
					$fees = PlatformFeeMetadata::feeFor($paymentIntent, (isset($paymentIntent->application_fee_amount) && $paymentIntent->application_fee_amount > 0) ? $paymentIntent->application_fee_amount / 100 : 0);
				}

				$paymentType = $newPaymentType;
				$jsonData = BookingUtils::updatePaymentHistoryData($history, $total, $paymentType, $crd, $cardType, $createdByWithName);

				// Actions
				$history->payment_type = $paymentType;
				$history->details = json_encode($jsonData, JSON_NUMERIC_CHECK);
				$history->save();
				DB::commit();

				$historyId = $history->id;
				$transaction->amount = $total;
				$transaction->fee_amount = $fees;
				$transaction->transaction_number = $paymentIntent->id ?? null;
				$transaction->application_fee_id = $paymentIntent->charges->data[0]->application_fee ?? null;
				$transaction->payment_type = 0;
				$transaction->via = $paymentType;
				$transaction->save();

				$this->cascadePaymentTypeToRelatedCharges($tourBookingID, $paymentType);

				DB::commit();

				$transactionId = $transaction->id;
				DB::commit();

				if ($request->has('discount_type') && $discountAmt > 0) {
					$total -= $discountAmt;
				}

				if ($oldDiscountAmt > 0) {
					$total -= $oldDiscountAmt;
				}

				$amountUpdate = TourBooking::findOrFail($tourBookingID);
				$amountUpdate->total = $total;
				$amountUpdate->booking_total = $total;
				$amountUpdate->service_commission = $serviceCommission;
				$amountUpdate->save();

				HistoryService::storePaymentTypeUpdateInHistory($history->tour_booking_id, $id, $originalPaymentType, $newPaymentType, $createdByWithName);

				DB::commit();

				$tourBooking[0]->is_change_payment_type = 1;

				$this->handlePaymentHistoryUpdateJobs($tourBooking, $transactionId, $historyId, $request, $paymentType, $info, $paymentIntent);

				DB::commit();

				if (isset($request->api) && $request->api == 1) {
					return [
						'tourBookingId' => $tourBookingID,
						'totalBookedSeats' => $info->seats
					];
				} else {
					return response()->json([
						'tourBookingId' => $tourBookingID,
						'totalBookedSeats' => $info->seats
					]);
				}
			}
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();

			HistoryService::storeCardPaymentErrorInHistory($request->bookingID, $exception);

			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				return response()->json([
					'errors' => $exception->getMessage(),
					'operation_at' => mysqlDT(),
				]);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Handle the queue jobs to perform the history data updates when booking cancelled
	 *
	 * @param  object  $tourBooking
	 * @param  int     $historyId
	 * @param  array   $request
	 * @param  string  $refundVia
	 * @param  int     $transactionId
	 *
	 * @return void
	 */
	public static function handleCancelRefundHistoryUpdateJobs($tourBooking, $historyId, $request, $refundVia, $transactionId): void
	{
		// Perform the jobs based on categories
		if ($request['refund_type'] == 'dont_refund') {
			CancelDontRefundService::run((int) $historyId, $refundVia, $transactionId);
		} elseif ($request['refund_type'] == 'partial_refund') {
			CancelPartialRefundService::run($historyId, $refundVia, $transactionId);
		} elseif ($request['refund_type'] == 'full_refund') {
			CancelFullRefundService::run($tourBooking, $historyId, $refundVia, $transactionId);
		} elseif ($request['refund_type'] == '100_refund') {
			Cancel100RefundService::run($tourBooking, $historyId, $refundVia, $transactionId);
		}
	}

	/**
	 * Refund booking which is already created
	 *
	 * @param object $request
	 * @param int $id
	 * @return object
	 * 
	 * @version 1.0.0
	 */
	public function refund(object $request, int $id)
	{
		DB::beginTransaction();
		try {
			$userType = userType();

			$qry = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
				->where('tbm.id', $id);

			if ($userType == 'Tour Operator') {
				$routePrefix = '';
				$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else if ($userType == 'Affiliate') {
				$routePrefix = 'affiliate.';
				$qry = $qry->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			} else {
				$routePrefix = 'admin.';
			}

			$booking = $qry->first([
				'tbm.tour_package_id AS pkg_id',
				'tbm.tourists',
				'tbm.status',
				'tst.id AS tour_slot_id',
				'tp.tour_operator_id',
				'tbm.email',
				'tbm.name',
				'tbm.phone_number',
				'cus.is_affiliate',
				'tbm.total',
				DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
			]);

			if (!$booking) {
				throw new Exception("Booking not found.");
			}

			if (!isset($request->api)) {
				$routeParams = ['booking' => $id];
				if ($request->has('modal'))
					$routeParams['modal'] = 1;
			}
			$totalTicketCostAmount = 0;
			$transaction = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->first();

			$created_by_with_name = BookingService::createdUserInfo();

			if ($transaction->via == "Card") {
				if (!isset($request->is_cash)) {
					$stripeService = StripeService::stripeDetails($id);
					if ($stripeService == false) {
						if (!isset($request->api)) {
							$currentURL = Route::currentRouteName();
							if ($currentURL == "affiliate.booking.cancel") {
								return redirect()
									->route('affiliate.booking.show', $routeParams)
									->with('message', 'This booking is imported. Kindly use here Cash or Check/Wire option.');
							}
							return redirect()
								->route($routePrefix . 'tour-operator.booking.show', $routeParams)
								->withErrors(['errors' => 'This booking is imported. Kindly use here Cash or Check/Wire option.'])
								->withInput();
						} else {
							throw new Exception('This booking is imported. Kindly use here Cash or Check/Wire option.');
						}
					}
				}
			}
			$calculatedTicketCost = BookingService::calculateRemainingTotalTicketCost($id);
			$transactionData = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->get();
			$transactionRefundData = $this->transaction::where('tour_booking_id', $id)->where('status', 'Refund')->whereNull('charge_type')->get();
			$totalAmt = 0;
			$totalRefundAmt = 0;
			if ($transactionData->count() > 1) {
				foreach ($transactionData as $transaData) {
					$totalAmt += $transaData->amount;
				}
			} else {
				if (!empty($transaction)) {
					$totalAmt =  $transaction->amount;
				}
			}

			if ($transactionRefundData->count() > 1) {
				foreach ($transactionRefundData as $transaRefundData) {
					$totalRefundAmt += $transaRefundData->amount;
				}
			} else {
				$transactionRefundData = $this->transaction::where('tour_booking_id', $id)->where('status', 'Refund')->whereNull('charge_type')->first();
				if (!empty($transactionRefundData)) {
					$totalRefundAmt = $transactionRefundData->amount;
				}
			}
			$totalAmt = round($totalAmt - $totalRefundAmt, 2);
			$totalTicketCostAmount = $calculatedTicketCost['netAmount'];

			if ($request->has('refund_booking')) {
				$update = $this->tourBooking::where('id', $id)->update(['updated_by' => Auth::id(), 'updated_by_user_type' => $userType]);

				if ($update) {
					if (!empty($transaction)) {
						if ($request->partial_refund_value > $totalAmt) {
							if (isset($request->api) && $request->api == 1) {
								throw new Exception('Unable to refund booking. Please enter amount less than or equal ticket cost.');
							} else {
								$currentURL = Route::currentRouteName();
								if ($currentURL == "affiliate.booking.refund") {
									return redirect()
										->route('affiliate.booking.show', $routeParams)
										->withErrors(['errors' => 'Unable to refund booking.Please enter amount less than or equal paid amount.'])
										->withInput();
								}

								return redirect()
									->route($routePrefix . 'tour-operator.booking.show', $routeParams)
									->withErrors(['errors' => 'Unable to refund booking.Please enter amount less than or equal paid amount.'])
									->withInput();
							}
						}
						if ($request->refund_booking == 2) {
							$partialRefund = $request->input('partial_refund', []);
							$totalPartialRefundAmount = 0;
							foreach ($partialRefund as $kp => $partial) {
								$totalPartialRefundAmount += (float)$partial;
							}
							if ($totalPartialRefundAmount > $totalTicketCostAmount) {
								if (isset($request->api) && $request->api == 1) {
									throw new Exception('Unable to refund booking. Please enter amount less than or equal paid amount.');
								} else {
									$currentURL = Route::currentRouteName();
									if ($currentURL == "affiliate.booking.refund") {
										return redirect()
											->route('affiliate.booking.show', $routeParams)
											->withErrors(['errors' => 'Unable to refund booking.Please enter amount less than or equal paid amount.'])
											->withInput();
									}

									return redirect()
										->route($routePrefix . 'tour-operator.booking.show', $routeParams)
										->withErrors(['errors' => 'Unable to refund booking.Please enter amount less than or equal paid amount.'])
										->withInput();
								}
							}
						}

						$splitTotal = 0;
						if ($request->refund_booking == 2) {
							$check = $request->input('check', []);
							$partial_refund = $request->input('partial_refund', []);
							$refund_type = "Partial Refund";
							$cardsArray = array();
							$partialAmountArray = array();
							foreach ($check as $key => $value) {
								foreach ($partial_refund as $kp => $partial) {
									if ($key == $kp && !empty($partial) && !empty($value)) {

										$trans = $this->transaction::where('transaction_number', $value)->whereNull('charge_type')->first();
										if (!isset($request->is_cash)) {
											if ($partial > $trans->amount) {
												if (isset($request->api) && $request->api == 1) {
													throw new Exception('Unable to refund booking. Please enter amount less than total.');
												} else {
													$currentURL = Route::currentRouteName();
													if ($currentURL == "affiliate.booking.refund") {
														return redirect()
															->route('affiliate.booking.show', $routeParams)
															->withErrors(['errors' => 'Unable to refund booking.Please enter amount less than total .'])
															->withInput();
													}

													return redirect()
														->route($routePrefix . 'tour-operator.booking.show', $routeParams)
														->withErrors(['errors' => 'Unable to refund booking.Please enter amount less than total .'])
														->withInput();
												}
											}

											$refundParams = [
												'payment_intent' => $trans->transaction_number,
												'amount' => $partial * 100,
												'reverse_transfer' => true,
												'refund_application_fee' => true,
											];
											$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $trans->payment_mode);

											$histDetails = $this->tourBookingHistory::where('transaction_id', $trans->id)->where('operation', 'Payment')->first(['card_last_4_digit']);
											if (!empty($histDetails)) {
												$cardsArray[] = $histDetails->card_last_4_digit;
												$partialAmountArray[] = $partial;
											}
										}
										$refundTransId = TransactionService::storeRefundInTransaction(
											$id,
											$partial,
											0,
											!empty($request->is_cash) ? $request->is_cash : 'Card',
											$trans->transaction_number,
											$trans->application_fee_id,
											mode: $trans->payment_mode
										);

										$splitTotal += $partial;
									}

									if ($key == $kp && empty($partial) && !empty($value)) {
										$trans = $this->transaction::where('transaction_number', $value)->where('status', 'Completed')->whereNull('charge_type')->first();
										$refundTransactions = $this->transaction::where('transaction_number', $value)->where('status', 'Refund')->get();
										$refundedAmount = 0;
										if ($refundTransactions) {
											foreach ($refundTransactions as $refundTransaction) {
												$refundedAmount += $refundTransaction->amount;
											}
										}
										$remainingAmountForTrans = $trans->amount - $refundedAmount;
										if (!isset($request->is_cash)) {
											$refundParams = [
												'payment_intent' => $trans->transaction_number,
												'amount' => $remainingAmountForTrans * 100,
												'reverse_transfer' => true,
												'refund_application_fee' => true
											];
											$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $trans->payment_mode);

											$histDetails = $this->tourBookingHistory::where('transaction_id', $trans->id)->where('tour_booking_id', $id)->where('operation', 'Payment')->first(['card_last_4_digit']);
											if (!empty($histDetails)) {
												$cardsArray[] = $histDetails->card_last_4_digit;
												$partialAmountArray[] = $remainingAmountForTrans;
											}
										}
										$refundTransId = TransactionService::storeRefundInTransaction(
											$id,
											$remainingAmountForTrans,
											0,
											!empty($request->is_cash) ? $request->is_cash : 'Card',
											$trans->transaction_number,
											$trans->application_fee_id,
											mode: $trans->payment_mode
										);

										$splitTotal += $remainingAmountForTrans;
									}
								}
							}

							if (isset($request->is_cash)) {
								if ($request->is_cash == "Cash") {
									$trans->via = 'Cash';
								} else {
									$trans->via = "Cheque/Wire";
								}
							}

							if (empty($trans)) {
								if (isset($request->api) && $request->api == 1) {
									throw new Exception(' Please select checkbox.');
								} else {
									$currentURL = Route::currentRouteName();
									if ($currentURL == "affiliate.booking.refund") {
										return redirect()
											->route('affiliate.booking.show', $routeParams)
											->withErrors(['errors' => 'Please select checkbox .'])
											->withInput();
									}

									return redirect()
										->route($routePrefix . 'tour-operator.booking.show', $routeParams)
										->withErrors(['errors' => 'Please select checkbox .'])
										->withInput();
								}
							}

							$historyId = HistoryService::storeRefundDataInHistory(
								$id,
								$booking->tourists,
								$created_by_with_name,
								$request->comment,
								$refund_type,
								$trans,
								$splitTotal,
								null, /* $cardNumber */
								$cardsArray,
								$partialAmountArray
							);
							DB::commit();
							// Below line is for to update tour booking history table
							RefundBookingService::run($refundTransId ?? null, $historyId, $trans->via);
							DB::commit();
						} else {
							if ($booking->total - $request->partial_refund_value > 0) {
								$this->tourBooking::where('id', $id)->update(['total' => number_format((float)$booking->total - $request->partial_refund_value, 2, '.', '')]);
							}

							$refund_keep_us = $transaction->amount - (float)$request->partial_refund_value;
							$refund_keep_us = number_format((float)$refund_keep_us, 2, '.', '');
							$total = number_format((float)$request->partial_refund_value, 2, '.', '');
							$refund_type = "Partial Refund";

							if (isset($request->is_cash) && $request->is_cash != '') {
								if ($request->is_cash == 'Cash') {
									$transaction->via = 'Cash';
								} elseif ($request->is_cash == 'Wire Transfer') {
									$transaction->via = 'Check/Wire';
								}
							}

							$cardNumber = '';
							if ($transaction->via == 'Card') {
								$refundParams = [
									'payment_intent' => $transaction->transaction_number,
									'amount' => $request->partial_refund_value * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
								$histDetails = $this->tourBookingHistory::where('transaction_id', $transaction->id)->where('tour_booking_id', $id)->where('operation', 'Payment')->first(['card_last_4_digit']);
								$cardNumber = $histDetails->card_last_4_digit;
							}

							$historyId = HistoryService::storeRefundDataInHistory(
								$id,
								$booking->tourists,
								$created_by_with_name,
								$request->comment,
								$refund_type,
								$transaction,
								$total,
								$cardNumber,
								[],
								[],
								null,
								null,
								null,
								0,
								null,
								$transaction->fee_amount
							);

							$TS = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->whereNull('charge_type')->first();

							if (!empty($transaction) && $transaction->via == 'Card') {
								$refundTransId = TransactionService::storeRefundInTransaction(
									$id,
									$total,
									0,
									'Card',
									$TS->transaction_number,
									$TS->application_fee_id,
									mode: $TS->payment_mode
								);
							} elseif (!empty($transaction) && ($transaction->via == 'Cash' || $transaction->via == 'Check/Wire')) {
								$refundTransId = TransactionService::storeRefundInTransaction(
									$id,
									$total,
									0,
									$transaction->via,
									'',
									''
								);
							}
							DB::commit();

							// Below line is for to update tour booking history table
							RefundBookingService::run($refundTransId ?? null, $historyId, $transaction->via);
						}
					} else {
						if ($request->partial_refund_value > $booking->total) {
							if (isset($request->api) && $request->api == 1) {
								throw new Exception('Unable to refund booking. Please enter amount less than total.');
							} else {
								$currentURL = Route::currentRouteName();
								if ($currentURL == "affiliate.booking.refund") {
									return redirect()
										->route('affiliate.booking.show', $routeParams)
										->withErrors(['errors' => 'Unable to refund booking. Please enter amount less than total.'])
										->withInput();
								}
								return redirect()
									->route($routePrefix . 'tour-operator.booking.show', $routeParams)
									->withErrors(['errors' => 'Unable to refund booking. Please enter amount less than total.'])
									->withInput();
							}
						}

						$historyId = HistoryService::storeRefundDataInHistory(
							$id,
							$booking->tourists,
							$created_by_with_name,
							$request->comment
						);
					}
				} else {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception('We are unable to alter the booking, hence we are unable to refund the booking.');
					} else {
						$currentURL = Route::currentRouteName();
						if ($currentURL == "affiliate.booking.cancel") {
							return redirect()
								->route('affiliate.booking.show', $routeParams)
								->with('message', 'We are unable to alter the booking, hence we are unable to refund the booking.');
						}

						return redirect()
							->route($routePrefix . 'tour-operator.booking.show', $routeParams)
							->withErrors(['errors' => 'We are unable to alter the booking, hence we are unable to refund the booking.'])
							->withInput();
					}
				}
			}
			DB::commit();

			$info = DB::table('tour_slot_times AS tst')
				->join('tour_bookings_master as bm', 'bm.tour_slot_id', '=', 'tst.id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->join('customers as cus', 'cus.id', '=', 'bm.customer_id')
				->where('bm.id', $id)
				->select([
					'tst.time_from',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					'cus.affiliate_processing_percentage',
					'cus.stripe_affiliate_percentage',
					'tp.stripe_commission_percentage',
					't_o.tenant_id',
					'bm.name As custname',
					'bm.phone_number As phone_number',
					'bm.email As custemail',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();


			$booking = is_array($booking) ? $booking : (array)$booking;

			if (!empty($transaction)) {
				if (isset($request->is_cash)) {
					$transaction->via = 'Cash';
				}
			}

			if ($booking['email']) {

				$arEmailData = [
					'tour_d_t' => $booking['tour_d_t'],
					'bookingId' => $id,
					'custName' => $info->custname,
					'custEmail' => $info->custemail,
					'custPhone' => $info->phone_number ?? '',
					'refundedAmount' => $request->partial_refund_value
				];

				if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
					EmailService::sendBookingRefundEmail($info, $arEmailData);
				}
			}

			if (isset($request->api) && $request->api == 1) {
				$tourBooking = $this->tourBooking::find($id);
				return $tourBooking;
			} else {
				$currentURL = Route::currentRouteName();
				if ($currentURL == "affiliate.booking.refund") {
					return redirect()
						->route('affiliate.booking.show', $routeParams)
						->with('message', 'Booking refund is being issued.');
				}

				return redirect()
					->route($routePrefix . 'tour-operator.booking.show', $routeParams)
					->with('message', 'Booking refund is being issued.');
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				$currentURL = Route::currentRouteName();
				if ($currentURL == "affiliate.booking.refund") {
					return redirect()
						->route('affiliate.booking.show', $routeParams)
						->withErrors(['errors' => 'Unable to refund booking.' . ' ' . $exception->getMessage()]);
				}
				return redirect()
					->route($routePrefix . 'tour-operator.booking.show', $routeParams)
					->withErrors(['errors' => 'Unable to refund booking.' . ' ' . $exception->getMessage()]);
			}
		}
	}
	/**
	 * Search the booking which is already created
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function search(object $request)
	{
		try {
			$search = $request->search;

			$bookings = DB::table('tour_bookings_master AS tbm')
				->leftJoin('tour_bookings_transact AS tbt', function ($join) {
					$join->on('tbt.tour_booking_id', '=', 'tbm.id')
						->where('tbt.deleted_by', 0);
				})
				->leftJoin('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_op', 't_op.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id');

			$bookings = $bookings->where('tp.tour_operator_id', $request->tour_operator_id);

			$bookings = $bookings->where(function ($qry) use ($search) {
				$search = str_ireplace("'", "'", "$search");
				// Strip the booking prefix (e.g. "D-") so searching "D-13835" finds tbm.id=13835.
				$strippedId = bookingPrefix($search, true);

				$qry->where('tbm.name', 'LIKE', "%{$search}%")
					->orWhere('tbm.email', '=', "$search")
					->orWhere('tbm.phone_number', 'LIKE', "%{$search}")
					->orWhere('tbm.note', 'LIKE', "%{$search}%")
					->orWhere('tbm.comments', 'LIKE', "%{$search}%")
					->orWhere('tbm.viator_bookingRef', $search)
					->orWhere('tbm.get_your_guide_reference_booking', $search)
					->orWhere('tbm.ota_reference_booking', $search)
					->orWhere('tbm.id', $search);

				if (is_numeric($strippedId)) {
					$qry->orWhere('tbm.id', (int) $strippedId);
				}
			});

			$bookings = $bookings->groupBy('tbm.id')
				->orderByDesc('tbm.created_at')
				->take(50);

			$response = $bookings->get([
				'tbm.id',
				'tbm.name AS cust_name',
				'c.is_affiliate',
				'tp.name AS pkg_name',
				'tbm.id',
				'tbm.email AS cust_email',
				'c.is_affiliate',
				'tp.name AS pkg_name',
				'tp.package_has_slots',
				'tp.package_has_waiver',
				'tp.package_has_permit',
				'tbm.total',
				'tbm.created_at AS booking_d_t',
				't_op.timezone',
				'tbm.status',
				'tbm.viator_bookingRef AS tripadvisor_id',
				'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
				'tbm.is_reserved_for_affiliate',
				'tbm.reference_booking',
				DB::raw(
					"(SELECT SUM(t.amount)
					FROM transactions AS t
					WHERE t.tour_booking_id = tbm.id
						AND t.status = 'Completed'
					GROUP BY t.tour_booking_id
					) AS paid"
				),
				DB::raw(
					'IFNULL((SELECT SUM(json_extract(`tbh`.`details`, "$.amount"))
						FROM tour_booking_history AS tbh
						WHERE tbh.tour_booking_id = tbm.id
						AND
						(
							`tbh`.`operation` = "Refund"
							or `tbh`.`operation` = "Cancelled"
						)
						and (
							not JSON_CONTAINS(
								`tbh`.`details`, json_quote("Don\'t Refund"), "$.refund_type"
							)
							and not JSON_CONTAINS(
								`tbh`.`details`, json_quote("don\'t refund"), "$.refund_type"
							)
							and not JSON_CONTAINS(
								`tbh`.`details`, json_quote("fareharbor : don\'t refund"), "$.refund_type"
							)
						)
						GROUP BY tbh.tour_booking_id), 0) AS RefundAmount'
				),
				DB::raw(
					"(SELECT t.via
					FROM transactions AS t
					WHERE t.tour_booking_id = tbm.id
						AND t.status = 'Completed'
					GROUP BY t.tour_booking_id
					) AS payment_method"
				),
				DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
				DB::raw(
					"GROUP_CONCAT(
						CONCAT(LEFT(tpr.rate_for, 1), ':', tbt.tourists)
						ORDER BY age_from DESC, age_to DESC
						SEPARATOR ' | '
					) AS t_groups"
				)
			]);

			foreach ($response as $key => $value) {
				$invoiced = DB::table('invoices as i')
					->join('invoice_bookings as ib', 'ib.invoice_id', '=', 'i.id')
					->where('ib.tour_booking_id', $value->id)->first(['status']);

				if (!empty($invoiced)) {
					$response[$key]->invoiceStatus = $invoiced->status;
				} else {
					$response[$key]->invoiceStatus = '0';
				}

				$response[$key]->prefixedId = bookingPrefix($value->id);
			}

			if (!empty($response) && !empty($response)) {
				$result = array(
					'search'            => $request->search,
					'tour_operator_id'  => $request->tour_operator_id,
					'bookings'          => $response,
				);

				return $result;
			}

			throw new Exception('Search details not available.');
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update checkin status of booking which is created
	 *
	 * @param object $request
	 * @return mixed
	 *
	 * @version 1.0.0
	 */
	public function updateCheckInStatus(object $request, $tourOptId = 0, $silentOnUpdateStatus = false, $deadlockAttempt = 1)
	{
		DB::beginTransaction();
		try {
			$tourOperatorId = ($tourOptId != 0) ? intval($tourOptId) : Auth::user()->tour_operator_id;

			$qry = DB::table('tour_bookings_transact AS tbt')
				->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
				->where('tbt.tour_booking_id', $request->booking_id)
				->where('tbt.deleted_by', 0);

			if ($tourOperatorId) {
				$qry = $qry->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
					->where('tp.tour_operator_id', $tourOperatorId);
			}

			if ($request->has('tpr_id')) // set status for a particular age/rate groups
				$qry = $qry->where('tbt.tour_package_rate_id', $request->tpr_id);

			// Order by id so concurrent check-in requests touching the same set of
			// tour_bookings_transact rows always lock them in the same order below —
			// without this, two overlapping requests can lock rows in reverse order
			// and deadlock (SQLSTATE 40001 / error 1213).
			$bookingT = $qry->orderBy('tbt.id')->get(['tbm.status', 'tbt.id', 'tbt.tourists', 'tbt.check_in_status', 'tbt.tour_package_rate_id']);
			$updated = 0;
			$mysqlDT = mysqlDT();

			if (count($bookingT) && $bookingT[0]->status == 'Cancelled') {
				throw new Exception('The booking has been cancelled, check-in status can not be updated.');
			}

			if ($request->has('all_same_checked_in') && $request->input('all_same_checked_in') == '0') {
				$oldCheckInStatus = $request->old_check_in_status;
				$created_by_with_name = BookingService::createdUserInfo();

				$historyArray = array();
				$updatedCheckInCount = 0;
				for ($a = 0, $ac = count($bookingT); $a < $ac; $a++) {
					if ($bookingT[$a]->check_in_status == '')
						$statusInfo = [];
					else
						$statusInfo = json_decode($bookingT[$a]->check_in_status);

					$rateGrp = DB::table('tour_package_rates AS tpr')
						->join('tour_bookings_transact AS tst', 'tst.tour_package_rate_id', '=', 'tpr.id')
						->where('tst.tour_booking_id', $request->booking_id)
						->where('tpr.id', $bookingT[$a]->tour_package_rate_id)
						->where('tst.deleted_by', 0)
						->first(['tpr.rate_for', 'tst.tourists']);

					if (!$rateGrp) {
						throw new Exception('Unable to set check-in status.');
					}

					for ($b = 0; $b < $bookingT[$a]->tourists; $b++) {
						if (!isset($statusInfo[$b])) {
							$statusInfo[$b] = new \stdClass;
							$statusInfo[$b]->status = 'N';
						}

						if ($request->check_in_status == '1') {
							if ($statusInfo[$b]->status == $oldCheckInStatus || $statusInfo[$b]->status == '' || !isset($statusInfo[$b])) {
								$statusInfo[$b]->status = '1';
								$statusInfo[$b]->dateTime = $mysqlDT;
								$historyArray[$a][$b]['status'] = "1st Check In";
								$historyArray[$a][$b]['who'] = "{$rateGrp->rate_for} #" . ($b + 1);
								$historyArray[$a][$b]['created_by_with_name'] = $created_by_with_name;
								$updatedCheckInCount++;
							}
						} else if ($request->check_in_status == '2') {
							if ($statusInfo[$b]->status == $oldCheckInStatus) {
								$statusInfo[$b]->status = '2';
								$statusInfo[$b]->dateTime = $mysqlDT;
								$historyArray[$a][$b]['status'] = "2nd Check In";
								$historyArray[$a][$b]['who'] = "{$rateGrp->rate_for} #" . ($b + 1);
								$historyArray[$a][$b]['created_by_with_name'] = $created_by_with_name;
								$updatedCheckInCount++;
							}
						} else if ($request->check_in_status == 'N') {
							if ($statusInfo[$b]->status == $oldCheckInStatus) {
								$statusInfo[$b]->status = 'N';
								$statusInfo[$b]->dateTime = $mysqlDT;
								$historyArray[$a][$b]['status'] = "Not Checked In";
								$historyArray[$a][$b]['who'] = "{$rateGrp->rate_for} #" . ($b + 1);
								$historyArray[$a][$b]['created_by_with_name'] = $created_by_with_name;
								$updatedCheckInCount++;
							}
						} else if ($request->check_in_status == 'A') {
							if ($statusInfo[$b]->status == $oldCheckInStatus) {
								$statusInfo[$b]->status = 'A';
								$statusInfo[$b]->dateTime = $mysqlDT;
								$historyArray[$a][$b]['status'] = "Absent";
								$historyArray[$a][$b]['who'] = "{$rateGrp->rate_for} #" . ($b + 1);
								$historyArray[$a][$b]['created_by_with_name'] = $created_by_with_name;
								$updatedCheckInCount++;
							}
						}
					}

					$updated = $updated + DB::table('tour_bookings_transact')
						->where('id', $bookingT[$a]->id)
						->update(['check_in_status' => json_encode($statusInfo)]);
				}
				$dateTime = mysqlDT();
				if ($updated) {
					if (!empty($historyArray)) {
						foreach ($historyArray as $key => $historArr) {
							foreach ($historArr as $k => $v) {
								HistoryService::storeCheckInDataInHistory($request->booking_id, $v, $dateTime);
							}
						}
					}
				}

				$masterCheck = DB::table('tour_bookings_master AS tbm')
					->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
					->where('tbm.id', $request->booking_id)
					->first(['tbm.status', 'c.is_affiliate']);

				$transaction = Transaction::where('tour_booking_id', $request->booking_id)->first();

				DB::commit();

				return [
					'whoCheckedIn' => $updatedCheckInCount,
					'checkInDateTime' => $dateTime,
					'check_in_status' => '',
					'status' => $transaction,
					'is_affiliate' => $masterCheck->is_affiliate,
				];
			} else {

				for ($a = 0, $ac = count($bookingT); $a < $ac; $a++) {
					if ($bookingT[$a]->check_in_status == '')
						$statusInfo = [];
					else
						$statusInfo = json_decode($bookingT[$a]->check_in_status);

					for ($b = 0; $b < $bookingT[$a]->tourists; $b++) {
						if (!isset($statusInfo[$b])) {
							$statusInfo[$b] = new \stdClass;
							$statusInfo[$b]->status = 'N';
						}

						if ($request->has('index')) {
							if ($request->index == $b) {
								$statusInfo[$b]->status = $request->check_in_status;
								$statusInfo[$b]->dateTime = $mysqlDT;
								break;
							}
						} else {
							$statusInfo[$b]->status = $request->check_in_status;
							$statusInfo[$b]->dateTime = $mysqlDT;
						}
					}
					if ($bookingT[$a]->tourists < count($statusInfo)) {
						$updated_arr_count = count($statusInfo) - $bookingT[$a]->tourists;
						$statusInfo = array_slice($statusInfo, 0, -$updated_arr_count);
					}

					$updated = $updated + DB::table('tour_bookings_transact')
						->where('id', $bookingT[$a]->id)
						->update(['check_in_status' => json_encode($statusInfo)]);
				}

				if ($updated) {
					$checkInStatuses = ['N' => 'Not Checked In', '1' => '1st Check In', '2' => '2nd Check In', 'A' => 'Absent'];

					if ($request->has('tpr_id')) {
						$rateGrp = DB::table('tour_package_rates AS tpr')
							->join('tour_bookings_transact AS tst', 'tst.tour_package_rate_id', '=', 'tpr.id')
							->where('tst.tour_booking_id', $request->booking_id)
							->where('tst.deleted_by', 0)
							->where('tpr.id', $request->tpr_id)
							->first(['tpr.rate_for', 'tst.tourists']);

						if (!$rateGrp) {
							throw new Exception('Unable to set check-in status.');
						}

						$whoCheckedIn = "{$rateGrp->rate_for} #" . ($request->index + 1);
					} else
						$whoCheckedIn = 'All';

					$dateTime = mysqlDT();
					$created_by_with_name = BookingService::createdUserInfo();

					if (!$silentOnUpdateStatus) {
						$detail = [
							'status' => $checkInStatuses[$request->check_in_status],
							'who' => $whoCheckedIn,
							'created_by_with_name' => $created_by_with_name
						];

						HistoryService::storeCheckInDataInHistory($request->booking_id, $detail, $dateTime);
					}

					$masterCheck = DB::table('tour_bookings_master AS tbm')
						->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
						->where('tbm.id', $request->booking_id)
						->first(['tbm.status', 'c.is_affiliate']);

					$transaction = Transaction::select('id', 'tour_booking_id', 'amount', 'fee_amount', 'transaction_number', 'via', 'payment_type', 'status')->where('tour_booking_id', $request->booking_id)->first();

					DB::commit();
					return [
						'whoCheckedIn' => $whoCheckedIn,
						'checkInDateTime' => $dateTime,
						'check_in_status' => $checkInStatuses[$request->check_in_status],
						'status' => $transaction,
						'is_affiliate' => $masterCheck->is_affiliate,
					];
				} else {
					if (!$silentOnUpdateStatus) {
						throw new Exception('Check-in status could not be updated. The selected status may already be applied to this booking.');
					} else {
						DB::commit();
						return [
							'whoCheckedIn' => 'All',
							'checkInDateTime' => Carbon::now(),
							'check_in_status' => $request->check_in_status,
							'status' => "",
							'is_affiliate' => ""
						];
					}
				}
			}
		} catch (Exception $exception) {
			// Roll all the way back to level 0 rather than the implicit target level.
			// A deadlock (or similar) can cause MySQL to abort the *entire* underlying
			// transaction server-side, discarding any savepoints -- a plain DB::rollBack()
			// would then try "ROLLBACK TO SAVEPOINT transN" against a savepoint that no
			// longer exists and throw a second, unrelated error that masks this one and
			// skips the retry below. Forcing level 0 goes through the plain-ROLLBACK path
			// instead and leaves the connection's transaction counter in a clean state.
			try {
				DB::rollBack(0);
			} catch (Exception $rollbackException) {
				Log::error($rollbackException);
			}

			// Overlapping check-in requests for the same booking (double-tap, kiosk +
			// front desk, retried submissions) can deadlock on these row locks. Retry
			// a few times with a small backoff before giving up, same as Laravel's
			// own DB::transaction($callback, $attempts) retry behaviour.
			if ($this->causedByConcurrencyError($exception) && $deadlockAttempt < 3) {
				usleep(100000 * $deadlockAttempt); // 100ms, 200ms
				return $this->updateCheckInStatus($request, $tourOptId, $silentOnUpdateStatus, $deadlockAttempt + 1);
			}

			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Tip payment of booking which is created
	 *
	 * @param object $request
	 * @return object
	 * 
	 * @version 1.0.0
	 */
	public function tipPayment(object $request)
	{
		DB::beginTransaction();
		try {
			$userType = userType();

			$tourBooking = $this->tipBookingDetails($request->bookingID);

			if ($request->has('api') && $request->api == '1') {
				$paymentIntentId = ($request->has('paymentintentid') && $request->paymentintentid != '') ? $request->paymentintentid : '';

				if ($request->webhook != 1) {
					$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId);

					$updateParams = [
						'description' => 'Booking Id: ' . $tourBooking->id .
							"\nTour Package: " . $tourBooking->package_name .
							"\nTour Date: " . $tourBooking->Date .
							"\nTour Time: " . $tourBooking->StartTime .
							"\nTourists: " . $tourBooking->tourists .
							"\nEmail: " . $tourBooking->Email,
						'metadata' => [
							'booking_id' => $tourBooking->id,
							'tour_package' => $tourBooking->package_name,
							'tour_date' => $tourBooking->Date,
							'tour_time' => $tourBooking->StartTime,
							'tourists' => $tourBooking->tourists,
							'customer_name' => $tourBooking->Name,
							'customer_phone' => $tourBooking->ContactNumber,
							'email' => $tourBooking->Email,
							'tenant_id' => tenant()->id,
						]
					];
					app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

					$crd = '';
					$cardInfo = isset($paymentIntent->charges->data[0]->payment_method_details) ? (array) $paymentIntent->charges->data[0]->payment_method_details : [];
					if (isset($cardInfo['card_present']['last4'])) {
						$crd = $cardInfo['card_present']['last4'];
					} elseif (isset($cardInfo['card']['last4'])) {
						$crd = $cardInfo['card']['last4'];
					}

					$cardType = !empty($paymentIntent->charges->data[0]->payment_method_details->card_present->brand) ? $paymentIntent->charges->data[0]->payment_method_details->card_present->brand : '';

					if ($crd == '') {
						throw new Exception('Something went wrong with Stripe payment.Please try again.');
					}
				}

				$data = [
					'amount' => $request->amount
				];
			} else {
				$crd = '';
				$cardType = '';
				$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
				$data = [
					'amount' => $request->amount,
					'notes' => $request->notes,
					'booking_id' => $tourBooking->id,
					'tour_operator_staff_id' => $request->staffname,
					'tour_package' => $tourBooking->package_name,
				];

				$intentMetadata = [
					'booking_id' => $data['booking_id'],
					'tour_package' => $data['tour_package'],
					'date' => $tourBooking->Date,
					'time_from' => $tourBooking->StartTime,
					'tourists' => $tourBooking->tourists,
					'customer_id' =>  $tourBooking->customer_id,
					'customer_name' => $tourBooking->Name,
					'customer_phone' => $tourBooking->ContactNumber,
					'customer_email' => $tourBooking->Email,
				];
				$paymentIntent = BookingService::updateCardReaderIntent($paymentIntentId, $intentMetadata);
				$cardInfo = $paymentIntent->charges->data[0]->payment_method_details ?? [];

				if (isset($cardInfo['card_present']['last4'])) {
					$crd = $cardInfo['card_present']['last4'] ?? '';
					$cardType = $cardInfo['card_present']['brand'] ?? '';
				} elseif (isset($cardInfo['card']['last4'])) {
					$crd = $cardInfo['card']['last4'] ?? '';
					$cardType = $cardInfo['card']['brand'] ?? '';
				}
			}

			$date = Carbon::now();
			DB::table('tour_booking_tips')->insert([
				'tour_booking_id' => $tourBooking->id,
				'tour_operator_staff_id' => $request->staffname,
				'amount' => $request->amount,
				'notes' => $request->notes,
				'date' => $date,
				'transaction_number' => $paymentIntentId,
				'status' => 'Completed',
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
			]);

			$created_by_with_name = BookingService::createdUserInfo();
			if ($request->webhook != 1) {
				HistoryService::storeTipPaymentInHistory($tourBooking->id, $data, $crd, $cardType, $created_by_with_name);
			}

			$bookingId = $tourBooking->id;

			$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
				->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', '=', $bookingId)
				->where('tbt.deleted_by', 0)
				->select('tpr.rate_for', 'tbt.tourists')
				->get();

			$touros = DB::table('tour_operator_staff as tos')
				->where('tos.id', '=', $request->staffname)
				->select('tos.id', 'tos.Email', 'tos.phone_number', DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as tosName"))
				->first();

			$arEmailData = [
				'bookingId' => $bookingId,
				'tourOperatorId' => $tourBooking->tour_operator_id,
				'tourPackageId' => $tourBooking->tour_package_id,
				'tourOperator' => $tourBooking->tour_operator_name,
				'tourOpPhone' => $tourBooking->tour_op_phone,
				'tourOpEmail' => $tourBooking->tour_op_email,
				'tourOpWebsite' => $tourBooking->tour_op_website,
				'tourPackage' => $tourBooking->package_name,
				'tourDate' => $tourBooking->Date,
				'tourDateTime' => $tourBooking->Date,
				'tourtime' => $tourBooking->StartTime,
				'urlBookingInfo' => route('customer.tour-booking-info', ['bookingId' => Crypt::encryptString($bookingId)]),
				'custName' => $tourBooking->tbmName,
				'custEmail' => $tourBooking->tbmEmail,
				'custPhone' => $tourBooking->tbmPhoneNumber,
				'urlLiabilityWaiver' => route('customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($bookingId)]),
				'importantNotes' => $tourBooking->important_notes,
				'cancellationPolicy' => $tourBooking->cancellation_policy,
				'numberoftourist' => $tourBooking->tourists,
				'tourListwithSeat' => $tourListwithSeat,/**/
				'guideName' => $touros->tosName,
				'custHead' => "Customer",
				'guideEmail' => $touros->Email,
				'custPayAmount' => $request->amount,
				'date' => Carbon::now()->format('Y-m-d'),
				'packageEmail' => $tourBooking->packageEmail,
				'packageFromName' => $tourBooking->packageFromName,
				'packageFromEmail' => $tourBooking->packageFromEmail,
				'packageApiKey' => $tourBooking->packageApiKey,
				'tenantId' => $tourBooking->tenant_id
			];

			if (!empty($tourBooking->tour_op_logo)) {
				$tourOperatorLogo = tourOperatorLogo($tourBooking->tenant_id, $tourBooking->tour_op_logo);
				$arEmailData['tourOpLogo'] = $tourOperatorLogo;
			}

			$tourPkgPhoto = DB::table('tour_package_photos')
				->where('tour_package_id', $tourBooking->tour_package_id)
				->where('placement', 2)
				->where('status', 'Active')
				->where('deleted_by', 0)
				->latest()->first(['path']);

			if ($tourPkgPhoto) {
				$tourPackageLogo = tourPackageLogo($tourBooking->tenant_id, $tourBooking->tour_package_id, $tourPkgPhoto->path);
				$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
			}

			if ($request->has('send_tip_mail')) {
				if ($request->send_tip_mail != null && $request->send_tip_mail == "Yes") {
					$booking = $this->tourBooking::find($bookingId);
					$booking->notify(new TipForCustomer($arEmailData, 'tip_for_customer'));
					$booking->notify(new TipForGuide($arEmailData, 'tip_for_guide'));
				}
			}

			DB::commit();

			if (isset($request->api) && $request->api == 1) {
				return response()->json([
					'bookingId' => $tourBooking->id,
				]);
			} else {
				return response()->json([
					'bookingId' => $tourBooking->id,
				]);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Tip payment of booking details
	 *
	 * @param int $bookingId
	 * @return object
	 * 
	 * @version 1.0.0
	 */
	public function tipBookingDetails($bookingId)
	{

		$data = DB::table('tour_bookings_master AS tbm')
			->join('customers as c', 'c.id', '=', 'tbm.customer_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('tour_operator_staff as tos', 'tos.tour_operator_id', '=', 't_o.id')
			->join('tour_slot_times as st', 'st.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
			->where('tbm.id', '=', $bookingId)
			->select(
				'tbm.id',
				'tbm.name as tbmName',
				'tbm.email as tbmEmail',
				'tbm.phone_number as tbmPhoneNumber',
				'tp.id as tour_package_id',
				'tp.name as package_name',
				'tp.important_notes',
				'tos.id as staff_id',
				'c.name as Name',
				'sd.date as Date',
				'st.time_from as StartTime',
				'st.time_to as EndTime',
				'tp.package_has_slots',
				'c.is_affiliate',
				'c.phone_number as ContactNumber',
				'c.email as Email',
				'tbm.tourists',
				'tbm.infants',
				't_o.id as tour_operator_id',
				't_o.name as tour_operator_name',
				't_o.phone_number AS tour_op_phone',
				't_o.email AS tour_op_email',
				't_o.logo AS tour_op_logo',
				't_o.cancellation_policy',
				't_o.website AS tour_op_website',
				't_o.tenant_id',
				'tp.stripe_destination_id',
				'tp.email as packageEmail',
				'tp.from_name as packageFromName',
				'tp.from_email as packageFromEmail',
				'tp.api_key as packageApiKey',
				'c.id as customer_id',
			)
			->first();

		return 	$data;
	}

	/**
	 * Save note for booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function saveNote(object $request, int $id)
	{
		try {
			if (empty($request->all())) {
				throw new Exception("Atleast select any staff user or add note for this booking.");
			}

			$userType = userType();

			if ($userType == 'Admin')
				$booking = TourBooking::findOrFail($id);
			else {
				$booking = TourBooking::join('tour_packages AS tp', 'tp.id', '=', 'tour_bookings_master.tour_package_id')
					->where('tp.tour_operator_id', Auth::user()->tour_operator_id)
					->select('tour_bookings_master.*')
					->findOrFail($id);
			}

			$bookerInfo = [];

			if ($request->note !== $booking->note || !empty($bookerInfo) || $request->hasFile('attachment')) {
				DB::beginTransaction();

				if ($request->note !== $booking->note) {
					$booking->note = $request->note;
					$booking->note_datetime = mysqlDT();
					$updatingNote = true;
				} else {
					$updatingNote = $request->hasFile('attachment');
				}

				$booking->save();

				if ($updatingNote) {
					$attachments = [];
					if ($request->hasFile('attachment')) {
						$file       = $request->file('attachment');
						$tourOp     = DB::table('tour_operators')->where('id', Auth::user()->tour_operator_id)->first(['tenant_id']);
						$tenantId   = $tourOp->tenant_id ?? getSubdomainFromHost();
						$ext        = strtolower($file->getClientOriginalExtension());
						$fileName   = uniqid('note_') . '_' . $id . '.' . $ext;
						$s3Path     = "images/tour-operator/{$tenantId}/notes/{$id}/{$fileName}";
						Storage::disk('s3')->putFileAs("images/tour-operator/{$tenantId}/notes/{$id}", $file, $fileName);
						$attachments[] = [
							'name' => $file->getClientOriginalName(),
							'ext'  => $ext,
							'path' => $s3Path,
							'url'  => Storage::disk('s3')->url($s3Path),
						];
					}
					HistoryService::storeNoteInHistory($request->note, $id, $attachments);
				}

				if (!empty($bookerInfo)) {
					if ($bookerInfo['newUserType'] == 'Admin') {
						$admin = DB::table('admins')
							->where('id', $bookerInfo['newId'])
							->first(['name']);

						if ($admin)
							$bookerInfo['newName'] = $admin->name;
					} else if ($bookerInfo['newUserType'] == 'Affiliate') {
						$affiliate = DB::table('customers')
							->where('id', $bookerInfo['newId'])
							->first(['name']);

						if ($affiliate)
							$bookerInfo['newName'] = $affiliate->name;
					} else {
						$staffUser = DB::table('tour_operator_staff')
							->where('id', $bookerInfo['newId'])
							->first(['first_name', 'last_name']);

						if ($staffUser)
							$bookerInfo['newName'] = trim($staffUser->first_name . ' ' . $staffUser->last_name);
					}

					if ($bookerInfo['oldUserType'] == 'Admin') {
						$admin = DB::table('admins')
							->where('id', $bookerInfo['oldId'])
							->first(['name']);

						if ($admin)
							$bookerInfo['oldName'] = $admin->name;
					} else if ($bookerInfo['oldUserType'] == 'Affiliate') {
						$affiliate = DB::table('customers')
							->where('id', $bookerInfo['oldId'])
							->first(['name']);

						if ($affiliate)
							$bookerInfo['oldName'] = $affiliate->name;
					} else {
						$staffUser = DB::table('tour_operator_staff')
							->where('id', $bookerInfo['oldId'])
							->first(['first_name', 'last_name']);

						if ($staffUser)
							$bookerInfo['oldName'] = trim($staffUser->first_name . ' ' . $staffUser->last_name);
					}

					if ($bookerInfo['newUserType'] == 'Tour Operator')
						$bookerInfo['newUserType'] = $bookerInfo['newUserType'] . ' Staff';

					if ($bookerInfo['oldUserType'] == 'Tour Operator')
						$bookerInfo['oldUserType'] = $bookerInfo['oldUserType'] . ' Staff';

					if ($bookerInfo['newUserType'] == 'Affiliate')
						$bookerInfo['newUserType'] = $bookerInfo['newUserType'] . ' Affiliate';

					if ($bookerInfo['oldUserType'] == 'Affiliate')
						$bookerInfo['oldUserType'] = $bookerInfo['oldUserType'] . ' Affiliate';

					if ($userType == "Admin") {
						$user = Admin::find(Auth::id());
						$created_by_with_name = $user->name . ': (' . "Admin" . ')';
					} elseif ($userType == "Tour Operator") {
						$user = Staff::find(Auth::id());
						$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
					} elseif ($userType == "Affiliate") {
						$user = $this->customer::find(Auth::id());
						$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
					}

					HistoryService::storeBookedByDataInHistory($id, $bookerInfo, $created_by_with_name);
				}

				DB::commit();

				if (isset($request->api) && $request->api == 1) {
					$noteArr = [
						'bookingId' => $id,
						'booked_by_staff' => $request->booked_by_staff,
						'notes' => $request->note,
						'operation' => 'Note',
						'operation_by' => Auth::id(),
						'operation_by_user_type' => $userType
					];
					return $noteArr;
				} else {
					return response()->json(['updated' => 1]);
				}
			} else {
				$message = $request->note === $booking->note ? "The similar note is there in this booking before." : "There cannot be a blank note field.";
				throw new Exception($message);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Save comments for booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function saveComments(object $request, int $id)
	{
		try {
			if (empty($request->all())) {
				throw new Exception("Atleast add small comment for this booking.");
			}

			$userType = userType();

			if ($userType == 'Admin')
				$booking = $this->tourBooking::findOrFail($id);
			else {
				$booking = $this->tourBooking::join('tour_packages AS tp', 'tp.id', '=', 'tour_bookings_master.tour_package_id')
					->where('tp.tour_operator_id', Auth::user()->tour_operator_id)
					->select('tour_bookings_master.*')
					->findOrFail($id);
			}

			if ($request->comments != $booking->comments) {
				DB::beginTransaction();

				$booking->comments = $request->comments;
				$booking->save();
				DB::commit();

				HistoryService::storeCommentInHistory($request->comments, $id);

				$name = '';
				if ($userType == 'Tour Operator') {
					$finduser = $this->staff::where('id', Auth::id())->first();
					$name = $finduser->first_name . ' ' . $finduser->last_name;
				} else if ($userType == 'Affiliate') {
					$finduser = $this->customer::where('id', Auth::id())->first();
					$name = $finduser->name;
				} else {
					$finduser = $this->admin::where('id', Auth::id())->first();
					$name = $finduser->name ?? '';
				}
				if (isset($request->api) && $request->api == 1) {
					return [
						'booking_id' => $id,
						'comments' => $request->comments,
						'operation_at' => mysqlDT(),
					];
				} else {
					return response()->json(['operation_at' => mysqlDT(), 'name' => $name, 'user_type' => $userType]);
				}
			} else {
				$message = $request->comments === $booking->comments ? "The similar comment is there in this booking before." : "There cannot be a blank comment field.";
				throw new Exception($message);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Save reference of booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function saveReferenceBooking(object $request, int $id)
	{
		try {
			if (empty($request->all())) {
				throw new Exception("Add reference number for this booking.");
			}

			$userType = userType();

			if ($userType == 'Admin') {
				$booking = $this->tourBooking::findOrFail($id);
			} else {
				$booking = $this->tourBooking::join('tour_packages AS tp', 'tp.id', '=', 'tour_bookings_master.tour_package_id')
					->where('tp.tour_operator_id', Auth::user()->tour_operator_id)
					->select('tour_bookings_master.*')
					->findOrFail($id);
			}

			if ($request->reference_booking != $booking->reference_booking) {
				DB::beginTransaction();

				$booking->reference_booking = $request->reference_booking;
				$booking->save();
				DB::commit();

				HistoryService::storeReferenceBookingInHistory($id, $request->reference_booking);

				$name = '';
				if ($userType == 'Tour Operator') {
					$finduser = $this->staff::where('id', Auth::id())->first();
					$name = $finduser->first_name . ' ' . $finduser->last_name;
				} else {
					$finduser = $this->admin::where('id', Auth::id())->first();
					$name = $finduser->name ?? '';
				}

				if (isset($request->api) && $request->api == 1) {
					return [
						'booking_id' => $id,
						'reference_booking' => $request->reference_booking,
						'operation_at' => mysqlDT(),
					];
				} else {
					return response()->json([
						'operation_at' => mysqlDT(),
						'name' => $name,
						'user_type' => $userType
					]);
				}
			} else {
				$message = $request->reference_booking === $booking->reference_booking ? "The similar reference number is there in this booking before." : "There cannot be a blank reference number field.";
				throw new Exception($message);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Save tour guide in the booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function saveTourGuide(object $request, int $id)
	{
		try {
			if (empty($request->all())) {
				throw new Exception("Select tour guide to add in this booking.");
			}

			$booking = $this->tourBooking::findOrFail($id);

			if ($request->tour_guide_id != $booking->tour_guide_id) {
				DB::beginTransaction();

				$booking->tour_guide_id = $request->tour_guide_id != 0 ? $request->tour_guide_id : NULL;
				$booking->save();
				DB::commit();

				if ($request->tour_guide_id != 0) {
					$guide = $this->staff::findOrFail($booking->tour_guide_id);
					$guideName = "$guide->first_name $guide->last_name";

					$note = "Tour guide $guideName has been successfully assigned to the booking.";
				} else {
					$note = "The assigned tour guide has been removed from the booking.";
				}

				HistoryService::storeNoteInHistory($note, $id);

				return [
					'booking_id' => $id,
					'tour_guide_id' => $request->tour_guide_id,
					'operation_at' => mysqlDT(),
					'note' => $note,
				];
			} else {
				$message = $request->tour_guide_id === $booking->tour_guide_id ? "The similar tour guide is there in this booking before." : "There cannot be a blank reference number field.";
				throw new Exception($message);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Send sms for booking
	 *
	 * @param int|string $bookingId
	 * @param mixed $info
	 *
	 * @return void
	 * 
	 * @version 1.0.0
	 */
	public function sendSMS($bookingId,  $info): void
	{
		// if $sms enabled send sms txt message
		if ($info->cust_phone && $info->is_affiliate == "No") {
			$props = (array)$info;
			$props['package_name'] = $info->packageName;

			$settings = Setting::where('key', 'BOOKING_ID_PREFIX')->first();
			$id = $settings->value . "" . $bookingId;

			$smsNotify = new SMSNotifications();
			$smsNotify->sendSMSLink($id, $props);
		}
	}

	/**
	 * Send email for booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function sendEmail(object $request, int $bookingId)
	{
		try {
			$userType = userType();

			$qry = DB::table('tour_bookings_master AS tbm')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'c.tour_operator_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->join('countries AS cn', 'cn.id', '=', 'st.country_id')
				->where('tbm.id', $bookingId)
				->select([
					't_o.name AS tour_operator',
					't_o.email AS t_o_email',
					't_o.phone_number AS t_o_phone',
					't_o.address AS t_o_address',
					't_o.postal_code AS t_o_postal_code',
					't_o.logo AS t_o_logo',
					't_o.tenant_id',
					'ct.name AS t_o_city',
					'st.name AS t_o_state',
					'cn.name AS t_o_country',
					'tbm.name AS customer',
					'tbm.name AS cust_name',
					'tbm.email AS cust_email',
					'tbm.phone_number AS cust_phone',
					'tbm.tour_package_id',
					'c.is_affiliate AS is_affiliate',
					'tp.name as packageName',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
				]);

			if ($userType == 'Tour Operator' || $userType == 'Affiliate')
				$qry = $qry->where('c.tour_operator_id', Auth::user()->tour_operator_id);

			$info = $qry->first();

			if (!$info) {
				throw new Exception("Unable to get the data of tour booking or customer.");
			}
			$tourOperatorLogo = '';
			if (!empty($info->t_o_logo)) {
				$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->t_o_logo);
			}

			$arEmailData = [
				'bookingId' => $bookingId,
				'emailType' => config('constants.email_types.custom_email'),
				'emailContent' => $request->email_content,
				'tourOperator' => $info->tour_operator,
				'tourOpEmail' => $info->t_o_email,
				'tourOpPhone' => $info->t_o_phone,
				'tourOpAddress' => $info->t_o_address,
				'tourOpPostalCode' => $info->t_o_postal_code,
				'tourOpCity' => $info->t_o_city,
				'tourOpState' => $info->t_o_state,
				'tourOpCountry' => $info->t_o_country,
				'tourOpLogo' => $tourOperatorLogo,
				'tourPackageId' => $info->tour_package_id,
				'tourPackage' => $info->packageName,
				'packageEmail' => $info->packageEmail,
				'packageFromName' => $info->packageFromName,
				'packageFromEmail' => $info->packageFromEmail,
				'packageApiKey' => $info->packageApiKey,
				'operationBy' => Auth::id(),
				'operationByUserType' => $userType,
				'tenantId' => $info->tenant_id
			];

			if ($info->t_o_email) {
				$arEmailData['replyTo'] = [
					'name' => $info->tour_operator,
					'email' => $info->t_o_email,
				];
			}

			/** Send SMS message */
			// $this->sendSMS($bookingId, $info);

			sendEmail(
				$request->email_subject,
				[
					'name' => $info->customer,
					'email' => $info->cust_email,
				],
				'customer-booking-custom',
				$arEmailData
			);

			if (isset($request->api) && $request->api == 1) {
				return [];
			} else {
				return response()->json([]);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Initialize the Calendar
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function calendar(object $request): array
	{
		try {
			$vars = [
				'page_title'      => 'Tour Bookings Calendar',
				'pageId'          => 'calendar',
				'tourOperatorId'  => 0,
				'currentDate'     => date('Y-m-d H:i:s'),
			];

			$userType = userType();

			if ($userType == 'Admin') {
				$vars['tourOperators'] = $this->tourOperator::where('status', 'Active')
					->orderBy('id')
					->get(['id', 'name']);

				$vars['tourOperatorId'] = Cookie::get('tourOpId', $vars['tourOperators']->first()->id);
			} else {
				$vars['tourOperatorId'] = $request->tourOperatorId ?? Auth::user()->tour_operator_id;
			}

			$yearMonth = $request->year . $request->month;

			if (isset($request->api) && $request->api == 1) {
				$date = substr($yearMonth, 0, 4) . '-' . str_pad(substr($yearMonth, 4, 2), 2, '0', STR_PAD_LEFT) . '-01';
			} else {
				$date = session('calendar_date') ?? date('Y-m-01');
			}

			$option    = session('calendar_option', 'month');
			$week      = session('calendar_week');
			$packageId = session()->get('package_id');

			if ($request->is('api/*')) {
				$option    = $request->get('option', 'month');
				$week      = $request->get('week');
				$packageId = $request->get('package_id');
			}

			$arYM = explode('-', $date);

			// Calendar week filter integration
			$currentMonth = Carbon::now()->month;
			$requestMonth = (int)$arYM[1];
			$requestYear = $arYM[0];
			$nowDate = Carbon::now();

			if ($week) {
				$weekNumber = (int)$week;
			} else {
				if ($requestMonth == $currentMonth) {
					$weekNumber = CalendarWeekFilterUtils::getWeeks($nowDate, "sunday", 1);
				} else {
					$weekNumber = CalendarWeekFilterUtils::getWeeks(Carbon::parse($date), "sunday", 0);
				}
			}

			$tourOpId = $vars['tourOperatorId'];

			$affiliateJoinQueries = CalendarQueryService::generateQueries($userType);

			$showAllPackageBookings = $affiliateJoinQueries['showAllPackageBookings'];

			$dateSlotsResult = CalendarQueryService::fetchDateSlots($userType, $option, $date, $tourOpId, $requestMonth, $requestYear, $weekNumber, $packageId);
			$dateSlots = $dateSlotsResult['data'];
			$vars['weekDates'] = $dateSlotsResult['weekDates'];

			$packageRelationSetting = hasSetting('PACKAGE_RELATION');
			$tourPackageFamilies = [];
			if ($packageRelationSetting && count($dateSlots)) {
				$packageIds = implode(',', array_unique(array_column((array) $dateSlots, 'tour_package_id')));

				$tourPackageFamilies = DB::select("SELECT *
					FROM tour_package_families
					WHERE parent_package_id IN (
						SELECT parent_package_id
						FROM tour_package_families
						WHERE tour_package_id IN (" . $packageIds . ")
					)");
			}

			$calData = [];
			foreach ($dateSlots as $key => $dateSlot) {
				$relationPackage = [];
				$targetParent = [];
				$totalTourists = 0;
				/* START - mapping tour package and reducing the relational seats */
				if ($packageRelationSetting && count($tourPackageFamilies)) {
					$relationPackage = array_filter($tourPackageFamilies, function ($obj) use ($dateSlot) {
						return $obj->tour_package_id == $dateSlot->tour_package_id && $obj->group_index == $dateSlot->group_index;
					});

					$targetParent = array_filter($tourPackageFamilies, function ($obj) use ($dateSlot) {
						return $obj->is_parent == 1 && $obj->tour_package_id == $dateSlot->tour_package_id && $obj->group_index == $dateSlot->group_index;
					});

					// $totalTourists = TouristService::countTotalTourists($tourPackageFamilies, $dateSlot);
					$totalTourists = TouristService::getTotalTouristsForSlots($tourPackageFamilies, $dateSlots, $dateSlot);
				}
				/* END */

				if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
					// Actual seats calculation
					$bookedSeatsPerSlot = ($totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $dateSlot->booked_seats;
					$totalSeatsPerSlot = $dateSlot->seats * $dateSlot->available_slots;

					// Affiliate seats calculation
					$tourists = (int) $dateSlot->afl_tourists;
					$seats = ($dateSlot->seats > 0) ? (($dateSlot->seats >= $dateSlot->afl_seats) ? ($dateSlot->afl_seats * $dateSlot->available_slots) : ($dateSlot->seats * $dateSlot->available_slots)) : 0;
					$overBooking = max(0, $tourists - $seats);

					if ($bookedSeatsPerSlot >= $totalSeatsPerSlot || $tourists >= $seats) {
						$remainingSeatsPerSlot = 0;
					} else {
						$globalTotal = $totalSeatsPerSlot - $bookedSeatsPerSlot;
						$affliateTotal = $seats - $tourists;
						$vacant = min($globalTotal, $affliateTotal);
						if ($vacant >= $seats) {
							$remainingSeatsPerSlot = $seats;
						} else {
							$remainingSeatsPerSlot = $vacant;
						}
					}

					if ($seats <= $remainingSeatsPerSlot) {
						$seats = $tourists > $seats ? 0 : $seats - $tourists;
					} elseif ($remainingSeatsPerSlot < $seats) {
						$seats = $remainingSeatsPerSlot;
					}

					if (!$overBooking) {
						$seats = $seats + $tourists;
					} else {
						$seats = ($seats + $tourists) - $overBooking;
					}

					$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;
					$bookedSeats = $tourists;
				} else {
					$tourists = ($totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $dateSlot->tourists;
					$seats = $dateSlot->seats * $dateSlot->available_slots;
					$overBooking = max(0, $tourists - $seats);
					$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;
					$bookedSeats = count($targetParent) ? (($totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $dateSlot->tourists) : (int) $dateSlot->tourists;
				}

				if (!isset($calData[$dateSlot->id])) {
					$calData[$dateSlot->id] = [
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

				$calData[$dateSlot->id]['tourists'] += $tourists;
				$calData[$dateSlot->id]['seats'] += $seats;
				$calData[$dateSlot->id]['over_booking'] += $overBooking;
				$calData[$dateSlot->id]['available_seats'] += $availableSeats;
				$calData[$dateSlot->id]['booked_seats'] += $bookedSeats;
			}

			$vars['tourSlots'] = array_values($calData);

			$vars['year'] = session('calendar_year') ?? $arYM[0];
			$vars['month'] = session('calendar_month') ?? $arYM[1];
			$vars['week'] = session('calendar_week') ?? $weekNumber;

			// Get selected filter option
			$vars['option'] = session('calendar_option') ?? 'month';
			if ($request->is('api/*')) {
				$vars['option'] = $request->option ?? 'month';
			}
			$vars['date'] = $date;

			$vars['affiliates'] = BookingUtils::getActiveAffiliates($vars['tourOperatorId'], $userType);

			$bookings = CalendarQueryService::fetchBookings($userType, $showAllPackageBookings);

			$bookingIds = array_column($bookings, 'id');
			$allHistory = TourBookingHistory::whereIn('tour_booking_id', $bookingIds)
				->get(['id', 'tour_booking_id', 'details', 'operation'])
				->groupBy('tour_booking_id');

			foreach ($bookings as $key => $bookingData) {
				$bookings[$key]->is_tripadvisor_booking = $bookingData->tripadvisor_id ? 1 : 0;
				$bookings[$key]->is_get_your_guide_booking = $bookingData->get_your_guide_booking_id ? 1 : 0;
				$bookings[$key]->invoiceStatus = $bookingData->invoice_status ?? 0;
				$bookings[$key]->prefixedId = bookingPrefix($bookingData->id);

				if (!empty($bookingData->timezone)) {
					$bookings[$key]->booking_d_t = Carbon::createFromFormat('Y-m-d H:i:s', $bookingData->booking_d_t, config('app.timezone'))
						->setTimezone($bookingData->timezone)
						->format('Y-m-d H:i:s');
				}

				$history = $allHistory->get($bookingData->id, collect());
				if ($bookingData->is_group_booking == '1') {
					$tGroupsChanged = ["P:" . $bookingData->tourists];
				} else {
					$tGroupsChanged = CalendarService::touristGroups($bookingData->t_groups);
				}
				$bookings[$key]->t_groups = isset($tGroupsChanged) ? implode(" |", $tGroupsChanged) : '';
				$bookings[$key]->BookingTotal = $bookingData->total;
				$bookings[$key]->RefundAmount = 0;

				foreach ($history as $his => $historyData) {
					$details = json_decode($historyData->details);

					if ($historyData->operation == 'Update') {
						$bookings[$key]->BookingTotal = $bookingData->total;
					}

					if ($historyData->operation == 'Payment') {
						$bookings[$key]->BookingTotal = round((float) $details->amount, 2);
					}

					if ($historyData->operation == 'Refund' && $bookingData->id == $historyData->tour_booking_id) {
						$bookings[$key]->RefundAmount += (float) $details->amount;
					}

					if ($historyData->operation == 'Cancelled' && isset($details->refund_type) && $details->refund_type != "Don't Refund") {
						$bookings[$key]->RefundAmount += $details->amount;
					}
				}

				$bookings[$key]->RefundAmount = round($bookings[$key]->RefundAmount, 2);
				$bookings[$key]->totalF = round($bookings[$key]->total);
				$bookings[$key]->PaymentTotal = max(0, $bookings[$key]->BookingTotal - $bookings[$key]->RefundAmount);

				$bookings[$key]->background_colour_class = BookingService::backgroundColourClass($bookings[$key]);
			}

			$vars['tourBookings'] = $bookings;

			$vars['tourPackages'] = CalendarService::getActivePackages($userType);

			if ($request->is('api/*')) {
				$settings =  DB::table('settings')
					->select(['key', 'value'])->get();

				if (!empty($settings)) {
					foreach ($settings as $setting) {
						if ($setting->key == 'API_VERSION') {
							$vars['apiVersion'] = (string) $setting->value;
						}

						if ($setting->key == 'IS_NO_HIDE') {
							$vars['isNoHide'] = ($setting->value == '1') ? true : false;
						}
					}
				}
			}

			$vars['partnerships'] = CalendarService::getPartnersData($userType);
			$vars['showAllPackageBookings'] = $showAllPackageBookings;

			if (count($vars['tourBookings']) != 0 || count($vars['affiliates']) != 0 || count($vars['tourSlots']) != 0) {
				return $vars;
			}

			throw new Exception("Calendar details not available.");
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public function getTimeSlotsByDate($date, $pkg_id)
	{
		try {
			$overbooked_seats = 0;
			$overbooked_seats_arr = array();
			$timeSlots = DB::select("SELECT `tst`.`id`, `tst`.`time_from`, `tst`.`time_to`, `tst`.`time_slot_seats` AS `seats`, `tst`.`bookable_status`,
				IFNULL((SELECT SUM(`tbm`.`tourists`)
					FROM `tour_bookings_master` AS `tbm`
					WHERE `tbm`.`tour_slot_id` = `tst`.`id`
						AND `tbm`.`status` != 'Cancelled'
						AND `tbm`.`tour_package_id` = ?
					GROUP BY `tbm`.`tour_slot_id`, `tbm`.`tour_package_id`), 0) AS tourists,
				IFNULL((SELECT SUM(`tbm`.`tourists`)
					FROM `tour_bookings_master` AS `tbm`
					JOIN `customers` AS `c` ON `c`.`id` = `tbm`.`customer_id`
					WHERE `tbm`.`tour_slot_id` = `tst`.`id`
						AND `c`.`is_affiliate` = 'Yes'
						AND tbm.`status` != 'Cancelled'
						AND `tbm`.`tour_package_id` = ?
					GROUP BY `tbm`.`tour_slot_id`, `tbm`.`tour_package_id`), 0) AS afl_tourists
				FROM `tour_slot_dates` as `tsd`
				JOIN `tour_slot_times` as `tst` on `tst`.`tour_slot_id` = `tsd`.`id`
					WHERE `tsd`.`date` = ?
						AND `tsd`.`status` = 'Active'
						AND `tst`.`deleted_by` = 0
						AND `tsd`.`tour_package_id` = ?", [$pkg_id, $pkg_id, $date, $pkg_id]);

			foreach ($timeSlots as $key => $timeSlot) {
				if ((int)$timeSlot->tourists > $timeSlot->seats) {
					$overbooked_seats_arr[$key]['date'] = $date;
					$overbooked_seats += ((int)$timeSlot->tourists - $timeSlot->seats);
					$overbooked_seats_arr[$key]['over_booking'] = $overbooked_seats;
				}
			}

			$overbooked_seats_arr = array_values($overbooked_seats_arr);

			return $overbooked_seats_arr;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Edit booking (Delete and Edit)
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function editBooking(object $request)
	{
		try {
			$userType = userType();
			$oldBookingId = oldBookingId();
			$isCashFees = cashFees();

			$check = $request->input('check', []);

			$modalUrl = '';
			if (!$request->is('api/*')) {
				if ($request->has('parent_url') && $request->parent_url != '') {
					if (Str::contains($request->parent_url, 'calendar') || Str::contains($request->parent_url, 'modal=1')) {
						$modalUrl = '?modal=1';
					}
				} else {
					$modalUrl = '?modal=1';
				}
			}
			$transP = $this->transaction::where('tour_booking_id', $request->bookingID)
				->where('status', 'Completed')
				->whereNull('charge_type')
				->orderBy('id', 'ASC')
				->first();

			$tourBooking = $this->tourBooking::find($request->bookingID);
			$packageHasContacts = $this->tourPackage::find($tourBooking->tour_package_id);
			$paymentTransCountForBooking = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->count();
			$isImportedBooking = false;
			if (!empty($transP) && $transP->via == 'Card' && $transP->transaction_number === "" && $request->action_type == "update" && $paymentTransCountForBooking == 1) {
				$isImportedBooking = true;
			}

			if ($request->payment_type == "Card" && $request->is_cash == "" && $request->action_type == "delete") {
				if ($isImportedBooking == true) {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception('This booking is imported. Kindly use here Cash or Check/Wire option');
					} else {
						$message = 'This booking is imported. Kindly use here Cash or Check/Wire option.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
			}

			if (count($check) == 0) {
				if (isset($request->api) && $request->api == 1) {
					throw new Exception('Your are not selected any check box in action option.');
				} else {
					$message = 'Your are not selected any check box in action option.';
					return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
				}
			}

			$created_by_with_name = BookingService::createdUserInfo();

			$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();

			$array = array_count_values($check);

			$checkPermitfee = $request->input('permitF', []);
			if ($request->action_type == "update") {
				$paymentTransCountForBooking = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->count();
				//calculate refund value
				$refundOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
					->where('operation', 'Refund')
					->where('payment_method', '!=', "Don't Refund")
					->get([
						'id',
						'details'
					]);
				$refundOnlyCheck = 0;
				foreach ($refundOnly as $value) {
					$details = json_decode($value->details, true);
					$refundOnlyCheck += $details['amount'];
				}
				if ($refundOnlyCheck == (float)$tourBooking->booking_total) {
					$message = 'Unable to update permit fee as transaction already refunded.';
					if (isset($request->api) && $request->api == 1) {
						throw new Exception($message);
					} else {
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}
				if ($request->payment_type == 'Card' && $paymentTransCountForBooking > 1 && $request->checked_transactions == '') {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception("You must need to select payment transaction to update.");
					} else {
						$message = 'You must need to select payment transaction to update permit fees.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}

				$refundpermitarray = array();
				$oldpermitfeesarray = array();
				$newprocessingfees = array();
				$tourbooking = $this->tourBooking::where('id', $request->bookingID)->first();
				$totalTouristtriedtoincreasepermitfeesarray = array();
				$totalTouristswithoutpermitfeesarray = array();

				foreach ($check as $key => $value) {
					$btset = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $value)->where('deleted_by', 0)->first();
					$customerCheck = $this->tourBooking::where('id', $request->bookingID)->first();
					$customer = $this->customer::where('id', $customerCheck->customer_id)->first();
					$oldpermitfeesarray[$value] = $btset->permit_fee;
					$tourpackagerate = $this->tourPackageRate::withTrashed()->find($value);
					if ($btset->refund_permitfee_status == '') {
						$statusInfo = [];
					} else {
						$statusInfo = json_decode($btset->refund_permitfee_status);
					}

					$oldpermitfees = $btset->permit_fee * $btset->tourists;
					$totalTouristswithoutpermitfees = 0;
					$totalTouristtriedtoincreasepermitfees = 0;
					for ($b = 0; $b < $btset->tourists; $b++) {
						$totalold = $btset->total;

						if (!isset($statusInfo[$b]) || (!isset($statusInfo[$b]) && $checkPermitfee[$tourpackagerate->rate_for . "." . $b] > 0)) {
							$statusInfo[$b] = new \stdClass;
							$statusInfo[$b]->status = 'N';
						}
						if ($oldpermitfees > 0 && $checkPermitfee[$tourpackagerate->rate_for . "." . $b] == 0.00 && (((isset($statusInfo[$b]->status) && $statusInfo[$b]->status == 'N') || $btset->refund_permitfee_status == ''))) {
							$totalTouristswithoutpermitfees++;
							if ($customer->is_affiliate === 'Yes' && $btset->additional_tax_percentage) {
								$oldrt = ($btset->rate + $btset->additional_charge + $btset->permit_fee) * ($btset->additional_tax_percentage) / 100;
								$rt = ($btset->rate + $btset->additional_charge) * ($btset->additional_tax_percentage) / 100;
							} else {
								$oldrt = ($btset->rate + $btset->additional_charge + $btset->permit_fee) * ($btset->additional_tax_percentage + $btset->processing_charge_percentage) / 100;
								$rt = ($btset->rate + $btset->additional_charge) * ($btset->additional_tax_percentage + $btset->processing_charge_percentage) / 100;
							}
							$refundpermitarray[$value] = $totalTouristswithoutpermitfees;
							$newprocessingfees[$value] = number_format((float)($oldrt - $rt), 2);
							$statusInfo[$b]->amount = $btset->permit_fee;
							$statusInfo[$b]->processing_fees = ($request->bookingID < $oldBookingId) ? number_format((float)($oldrt - $rt), 2) : 0; // number_format((float)($oldrt - $rt), 2);
							$statusInfo[$b]->status = 'Y';
							$statusInfo[$b]->dateTime = mysqlDT();
							if (isset($statusInfo[$b]->is_pax_added) && $statusInfo[$b]->is_pax_added == true) {
								unset($statusInfo[$b]->is_pax_added);
							}
						}

						if (($oldpermitfees == 0 && $checkPermitfee[$tourpackagerate->rate_for . "." . $b] > 0) || (isset($statusInfo[$b]) && $statusInfo[$b]->status == 'Y' && $checkPermitfee[$tourpackagerate->rate_for . "." . $b] > 0)) {
							$totalTouristtriedtoincreasepermitfees++;
						}
					}

					$totalTouristtriedtoincreasepermitfeesarray[$value] = $totalTouristtriedtoincreasepermitfees;
					$totalTouristswithoutpermitfeesarray[$value] = $totalTouristswithoutpermitfees;

					$subtotal = $total = $totalold;
					$total = $subtotal;
					$bt = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $value)->where('deleted_by', 0)->first();
					if ($request->payment_type == 'Card' && $request->bookingID > 14000 && $customer->is_affiliate == 'No') {
						$transaction = $this->transaction::where('tour_booking_id', $request->bookingID)->whereNull('charge_type')->first();
						$fee = ($bt->nat_fees_percentage * $subtotal) / 100;
						$total = $fee + $total;
					}


					if ($bt->permit_fee != $checkPermitfee[$key]) {
						$this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $value)->where('deleted_by', 0)
							->update([
								'refund_permitfee_status' => json_encode($statusInfo)
							]);
					}
				}

				if (array_sum($totalTouristtriedtoincreasepermitfeesarray) > 0 && array_sum($totalTouristswithoutpermitfeesarray) == 0) {
					if (isset($request->api) && $request->api == 1) {
						throw new Exception("Your are not allowed to increase permit fees.");
					} else {
						$message = 'Your are not allowed to increase permit fees.';
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}

				if (!empty($refundpermitarray)) {
					$amountTotal = 0;
					$amountTotalrefundpermitfees = 0;
					$amountTotalrefundprocessingfees = 0;
					$textfordeleted = '';
					$i = 0;
					foreach ($refundpermitarray as $key => $value) {
						$btset = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)->first();
						$tourpackagerate = $this->tourPackageRate::withTrashed()->find($key);

						$textfordeleted .= ($i == 0) ? $tourpackagerate->rate_for . " person " . $value : "  , " . $tourpackagerate->rate_for . " person " . $value;
						$totalrefundpermitfeesamount = 0;
						$totalrefundprocessingfeesamount = 0;
						$totalrefundpermitfeesamount = (isset($oldpermitfeesarray[$key])) ? $oldpermitfeesarray[$key] * $value : 0;
						if (($request->bookingID < $oldBookingId)) {
							$totalrefundprocessingfeesamount = (isset($newprocessingfees[$key])) ? $newprocessingfees[$key] * $value : 0;
						}
						$amountTotalrefundprocessingfees += $totalrefundprocessingfeesamount;
						$amountTotalrefundpermitfees += $oldpermitfeesarray[$key] * $value;
						$bookingtotal = $btset->total - $totalrefundpermitfeesamount - $totalrefundprocessingfeesamount;

						$permitfee = ($btset->tourists == $value) ? 0.00 : $btset->permit_fee;
						$this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
							->update(
								[
									'total' => number_format((float)$bookingtotal, 2),
									'permit_fee' => $permitfee,
								]
							);
						$i++;
					}

					$amountTotal = $tourbooking->total - $amountTotalrefundpermitfees - $amountTotalrefundprocessingfees;
					$TS = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->latest('id')->first();

					$permitFee = 0;
					if (!empty($TS) && ($TS->via == "Card" || ($isCashFees == '1' && $TS->via == "Cash"))) {
						$permitFee = $tourbooking->service_commission;
					}

					$tourBooking = $this->tourBooking::where('id', $request->bookingID)->update(['total' => $amountTotal, 'service_commission' => $permitFee]);
					if ($isImportedBooking == true) {

						if ($request->is_cash == '') {
							if (isset($request->api) && $request->api == 1) {
								throw new Exception("Your must need to select Cash/Wire payment method.");
							} else {
								$message = 'Your must need to select Cash/Wire payment method.';
								return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
							}
						}

						if ($request->is_cash == 'Cash') {
							$paymentType = 'Cash';
						} elseif ($request->is_cash == 'Wire Transfer') {
							$paymentType = 'Check/Wire';
						}
					} else {
						$paymentType = $request->payment_type;
					}

					$details = json_encode([], JSON_NUMERIC_CHECK);

					$data = [
						'amount' => $amountTotal,
						'permit_fees' => $amountTotalrefundpermitfees + $amountTotalrefundprocessingfees,
						'payment_type' => $paymentType,
					];
					$tourBooking = $this->tourBooking::find($request->bookingID);
					HistoryService::updateTouristInHistory($request->bookingID, $data, $tourBooking, $created_by_with_name);

					$cardNumber = '';
					if (!empty($TS) && $request->payment_type == 'Card') {
						if ($request->has('checked_transactions') && $request->checked_transactions != '') {
							$cardDetails = BookingService::cardLastFourDigit($request->bookingID, $request->checked_transactions);
						} else {
							$cardDetails = BookingService::cardLastFourDigit($request->bookingID, $TS->transaction_number);
						}
						$cardNumber = $cardDetails['card_last_4_digit'];
					}
					if (!empty($TS)) {
						if ($isImportedBooking == true && $request->payment_type == 'Card') {
							if ($request->is_cash == 'Cash') {
								$paymentType = 'Cash';
							} elseif ($request->is_cash == 'Wire Transfer') {
								$paymentType = 'Check/Wire';
							}
						} else {
							$paymentType = $request->payment_type;
						}

						$data = [
							'amount' => $amountTotalrefundpermitfees + $amountTotalrefundprocessingfees,
							'refund_keep_us' => 0,
							'payment_type' => $paymentType,
							'card_number' => $cardNumber,
							'deleted' => $textfordeleted,
							'refund_type' => 'Permitfees Refund',
							'comment' => null
						];
					} else {
						$data = [
							'amount' => 0,
							'refund_keep_us' => 0,
							'payment_type' => null,
							'card_number' => null,
							'deleted' => $textfordeleted,
							'refund_type' => 'Permitfees Refund',
							'comment' => null
						];
					}

					$bookingInfo = $this->tourBooking::find($request->bookingID);

					$historyId = HistoryService::storeRefundDataInHistory(
						$request->bookingID,
						$bookingInfo->tourists,
						$created_by_with_name,
						$data['comment'],
						$data['refund_type'],
						null, /* $transaction */
						$data['amount'],
						$data['card_number'],
						[], /* $cardsArray */
						[], /* $partialAmountArray */
						$data['payment_type'],
						null, /* $paymentTotal */
						null, /* $refundTotal */
						$data['refund_keep_us'],
						$data['deleted']
					);

					if (!empty($TS) && $request->payment_type == 'Card' && !$isImportedBooking) {
						try {
							if ($request->has('checked_transactions') && $request->checked_transactions != '') {
								$TSDetails = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount', 'application_fee_id', 'payment_mode']);
								$refundParams = [
									'payment_intent' => $request->checked_transactions,
									'amount' => ($amountTotalrefundpermitfees + $amountTotalrefundprocessingfees) * 100,
									'reverse_transfer' => true,
								];
								$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $TSDetails->payment_mode);

								$TS_number = $request->checked_transactions;
								$application_fee_id = $TSDetails->application_fee_id;
								$piMode = $TSDetails->payment_mode;
							} else {
								$refundParams = [
									'payment_intent' => $TS->transaction_number,
									'amount' => ($amountTotalrefundpermitfees + $amountTotalrefundprocessingfees) * 100,
									'reverse_transfer' => true,
								];
								$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $TS->payment_mode);
								$TS_number = $TS->transaction_number;
								$application_fee_id = $TS->application_fee_id;
								$piMode = $TS->payment_mode;
							}
							$refundAmount = $amountTotalrefundpermitfees + $amountTotalrefundprocessingfees;
							$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, 'Card', $TS_number, $application_fee_id, mode: $piMode);
						} catch (\Exception $e) {
							Log::error('Permit fee Stripe refund failed', [
								'booking_id' => $request->bookingID,
								'error'      => $e->getMessage(),
							]);
							throw $e;
						}
					} else if (!empty($TS) && $request->payment_type == 'Card' && $isImportedBooking) {
						if ($request->is_cash == 'Cash') {
							$paymentType = 'Cash';
						} elseif ($request->is_cash == 'Wire Transfer') {
							$paymentType = 'Check/Wire';
						}
						$refundAmount = $amountTotalrefundpermitfees + $amountTotalrefundprocessingfees;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $paymentType, null, null, mode: $TS->payment_mode);
					} elseif (!empty($TS) && $request->payment_type == 'Cash') {
						$refundAmount = $amountTotalrefundpermitfees + $amountTotalrefundprocessingfees;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, 'Cash', null, null);
					}

					// Permit fee refund queue job dispatch
					PermitFeeRefundBookingService::run($tourbooking, $refundTransId ?? null, $historyId, $request->all(), $request->payment_type);
				}

				if (!isset($request->api)) {
					$message = 'Permit fee updated successfully.';
					return BookingService::refundRouteWithSuccess($request->bookingID, $modalUrl, $message);
				} else {
					return [
						'booking_id' => $request->bookingID,
						'history' => isset($TourBookingHistory) ? $TourBookingHistory : null,
						'message' => 'Permit fee updated successfully.'
					];
				}
			}

			$total = 0;
			$checkTotalTourists = count($check);
			$transK = Transaction::where('status', 'Completed')->where('tour_booking_id', $request->bookingID)->first();

			if ($request->total_tourist == $checkTotalTourists) {
				if (isset($request->api) && $request->api == 1) {
					throw new Exception("You can not delete all records. Please use cancel booking option.");
				} else {
					$message = 'You can not delete all records. Please use cancel booking option.';
					return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
				}
			}

			$oldBookingID = $oldBookingId;
			if ($tourBooking->is_group_booking == '1' && $request->action_type == "delete") {
				$res = EditBookingService::deletePaxFromGroupBooking($request, $modalUrl, $paymentTransCountForBooking);
				// deletePaxFromGroupBooking() only ever returns a plain array on success; any other
				// return (a redirect on the classic web submit, or a JSON error response on an
				// AJAX/JSON submit) is already a complete response and must be returned as-is,
				// not fallen through into the single-booking logic below. Not gated on a specific
				// status code (previously ===302 only) so a JSON error response — which is never
				// a 302 — still short-circuits here instead of silently continuing.
				if (getType($res) != "array") {
					return $res;
				}
			} else {
				if ($request->action_type == "delete") {
					$paymentTransCountForBooking = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->count();
					if ($request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && $request->is_cash == '' && $paymentTransCountForBooking > 1 && ($request->has('checked_transactions') && $request->checked_transactions == '' && $request->refund_Type != 'dont_refund')) {
						if (isset($request->api) && $request->api == 1) {
							throw new Exception("You must need to select payment transaction to delete person.");
						} else {
							$message = 'You must need to select payment transaction to delete person.';
							return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
						}
					}
					if ($request->refund_Type == "partial_refund") {
						if ($request->partial_Refund_value == '') {
							if (isset($request->api) && $request->api == 1) {
								throw new Exception("Please enter partial refund amount value.");
							} else {
								$message = 'Please enter partial refund amount value.';
								return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
							}
						}
					}
					$refundedpermitarray = array();

					$checkarrays = array();
					$checkinstatusarray = array();
					$redeemTicketArray = array();
					$refundedpermitfeearray = array();

					foreach ($check as $key => $value) {
						$btset = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $value)->where('deleted_by', 0)->first();

						$explode = explode('.', $key);

						$checkarrays[$value][] = $explode[1];
						$checkinstatusarray[$value] = $btset->check_in_status;
						$redeemTicketArray[$value] = $btset->redeem_ticket;
						$refundedpermitfeearray[$value] = $btset->refund_permitfee_status;
					}
					$updatedCheckIns = array();
					$updatedRedeemTickets = array();
					$updatedRefundedPermitFees = array();

					if (!empty($checkarrays)) {
						foreach ($checkarrays as $k => $checkarray) {
							if (isset($checkinstatusarray[$k]) && $checkinstatusarray[$k] != '')
								$checkinstatusInfo = json_decode($checkinstatusarray[$k]);
							else
								$checkinstatusInfo = [];

							if (isset($redeemTicketArray[$k]) && $redeemTicketArray[$k] != '')
								$redeemTicketInfo = json_decode($redeemTicketArray[$k], TRUE);
							else
								$redeemTicketInfo = [];

							if (isset($refundedpermitfeearray[$k]) && $refundedpermitfeearray[$k] != '')
								$refundpermitfeesstatusInfo = json_decode($refundedpermitfeearray[$k]);
							else
								$refundpermitfeesstatusInfo = [];

							foreach ($checkarray as $l => $m) {
								if (isset($checkinstatusInfo[$m]) && !empty($checkinstatusInfo[$m])) {
									unset($checkinstatusInfo[$m]);
								}
								if (!empty($redeemTicketInfo)) {
									for ($y = 0; $y < count($redeemTicketInfo); $y++) {
										if (isset($redeemTicketInfo[$y]['data'][$m]) && !empty($redeemTicketInfo[$y]['data'][$m])) {
											unset($redeemTicketInfo[$y]['data'][$m]);
										}
										if (isset($redeemTicketInfo[$y]['data'])) {
											$redeemTicketInfo[$y]['data'] = array_values($redeemTicketInfo[$y]['data']);
										}
									}
								}
								if (isset($refundpermitfeesstatusInfo[$m]) && !empty($refundpermitfeesstatusInfo[$m])) {

									if (isset($refundpermitfeesstatusInfo[$m]->status) && $refundpermitfeesstatusInfo[$m]->status == 'Y' && isset($refundpermitfeesstatusInfo[$m]->amount) && $refundpermitfeesstatusInfo[$m]->amount > 0) {
										$refundedpermitarray[$k][] = $m;
									}

									unset($refundpermitfeesstatusInfo[$m]);
								}
							}
							$updatedCheckIns[$k] = $checkinstatusInfo;
							$updatedRedeemTickets[$k] = $redeemTicketInfo;
							$updatedRefundedPermitFees[$k] = $refundpermitfeesstatusInfo;
						}
					}

					//Check validations with calculations for multiple payments using card
					if ($request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && $request->is_cash == '' && $paymentTransCountForBooking > 1 && ($request->has('checked_transactions') && $request->checked_transactions != '')) {
						$refundTrans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);
						$paymentTrans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount']);
						$totalrefundedamount = 0;
						foreach ($refundTrans as $ky => $val) {
							$totalrefundedamount += $val->amount;
						}
						$amountCapturedStripe = 0;
						$amountRefundedStripe = 0;
						$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($request->checked_transactions);
						if (!empty($paymentIntent) && isset($paymentIntent->charges) && isset($paymentIntent->charges->data[0]) && !empty($paymentIntent->charges->data[0])) {
							$amountCapturedStripe = $paymentIntent->charges->data[0]->amount_captured;
							$amountRefundedStripe = ($paymentIntent->charges->data[0]->amount_refunded != '') ? $paymentIntent->charges->data[0]->amount_refunded : 0;
						}

						//Validation check if payment transactions fully refunded or not
						if ($paymentTrans->amount - $totalrefundedamount <= 0) {
							if (isset($request->api) && $request->api == 1) {
								throw new Exception('Unable to refund booking. Selected transaction already fully refunded.Please select different one.');
							} else {
								$message = 'Unable to refund booking. Selected transaction already fully refunded. Please select different one.';
								return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
							}
						}

						$totalamountforvalidation = 0;
						foreach ($array as $key => $value) {
							$tourBooking = $this->tourBooking::find($request->bookingID);
							$customer = $this->customer::find($tourBooking->customer_id);

							$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $key)->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
							if ($customer->is_affiliate == 'Yes') {
								$service_commission_percentage = $rateCheck->nat_fees_percentage;
							} else {
								$service_commission_percentage = $rateCheck->nat_fees_percentage;
							}
							if ($request->action_type == "delete" && $rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
								$touristrefundedpermitfeesalready = count($refundedpermitarray[$key]);
								$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

								if ($request->payment_type === 'Card') {
									$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
								} else {
									$per = $rate * $rateCheck->additional_tax_percentage / 100;
								}
								$subtotal = ($rate + $per);
								if ($request->payment_type === 'Card' && $rateCheck->processing_charge_percentage > 0) {
									$subtotal = number_format((float)(($subtotal * $value) - (($touristrefundedpermitfeesalready * $rateCheck->permit_fee) + ($touristrefundedpermitfeesalready * (($rateCheck->permit_fee * $rateCheck->processing_charge_percentage) / 100)))), 2, '.', '');
								} else {
									$subtotal = number_format((float)(($subtotal * $value) - ($touristrefundedpermitfeesalready * $rateCheck->permit_fee)), 2, '.', '');
								}
							} else {
								$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
								if ($request->payment_type === 'Card') {
									$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
								} else {
									$per = $rate * $rateCheck->additional_tax_percentage / 100;
								}
								$subtotal = ($rate + $per);
								$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
							}
							$totalamountforvalidation += $subtotal;
						}
						if ($request->refund_Type == "partial_refund") {
							//We added fees calculations because we need to refund platoform fees as well in partial refund.
							$feeAmount = 0;
							if ($request->payment_type == 'Card') {
								$feeAmount = ($totalamountforvalidation * $service_commission_percentage) / 100;
							}
							$netAmountForValidationPartialRefund = round($totalamountforvalidation + $feeAmount, 2);;
							if ($request->partial_Refund_value > $netAmountForValidationPartialRefund) {
								if (isset($request->api) && $request->api == 1) {
									throw new Exception('Unable to refund booking. Please enter amount less than total.');
								} else {
									$message = 'Unable to refund booking. Please enter amount less than total.';
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}
						if ($request->refund_Type != "dont_refund" && $amountCapturedStripe > 0) {
							$remainingAmountStripe = $amountCapturedStripe / 100 - $amountRefundedStripe / 100;
							if ($totalamountforvalidation > $remainingAmountStripe) {
								if (isset($request->api) && $request->api == 1) {
									throw new Exception('Unable to refund booking. For the chosen transaction, the refund amount is greater than the unrefunded amount. Please choose another transaction.');
								} else {
									$message = 'Unable to refund booking. For the chosen transaction, the refund amount is greater than the unrefunded amount. Please choose another transaction.';
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}

						//Validation check for 100% refund when deleting persons
						if ($request->refund_Type == "100_refund" && ($totalamountforvalidation > 0)) {
							$set_fee_amount = 0;
							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$set_fee_amount = ($totalamountforvalidation * 6) / 100;
								}
							} else {
								$set_fee_amount = ($totalamountforvalidation * $service_commission_percentage) / 100;
							}
							$Refund100 = $totalamountforvalidation + $set_fee_amount;
							$Refund100 = round($Refund100, 2);

							$rounded = round($paymentTrans->amount - $totalrefundedamount - $Refund100, 2);
							if ($rounded < -0.05) {
								if (isset($request->api) && $request->api == 1) {
									throw new Exception('Unable to refund booking. Please choose a valid transaction or person to refund.');
								} else {
									$message = 'Unable to refund booking. Please choose a valid transaction or person to refund.';
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}
						//Validation check for full refund minus fees when deleting persons
						if ($request->refund_Type == "full_refund" && ($totalamountforvalidation > 0)) {

							$Refund100MinusFees = round($totalamountforvalidation, 2);

							if (($paymentTrans->amount - $totalrefundedamount - $Refund100MinusFees) < -0.05) {
								if (isset($request->api) && $request->api == 1) {
									throw new Exception('Unable to refund booking. Please choose a valid transaction or person to refund.');
								} else {
									$message = 'Unable to refund booking. Please choose a valid transaction or person to refund.';
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}
					}

					//Check validations cash
					if ($request->payment_type == 'Cash' && $request->refund_Type != "dont_refund") {
						if ($request->refund_Type == "partial_refund") {
							if ($request->partial_Refund_value == "NaN") {
								$message = 'Please enter partial refund amount.';
								if (isset($request->api) && $request->api == 1) {
									throw new Exception($message);
								} else {
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}
						$checkTotalTourist = count($check);
						foreach ($array as $key => $value) {
							$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
							$subtotal = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
						}
						//calculate refund value
						$refundOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
							->where('operation', 'Refund')
							->where('payment_method', '!=', "Don't Refund")
							->get([
								'id',
								'details'
							]);
						$refundOnlyCheck = 0;
						foreach ($refundOnly as $value) {
							$details = json_decode($value->details, true);
							$refundOnlyCheck += $details['amount'];
						}
						if ($request->refund_Type == "partial_refund") {
							if ($request->partial_Refund_value > ((float)$tourBooking->booking_total - $refundOnlyCheck)) {
								$message = 'Unable to refund booking. Refund amount is greater than the unrefunded amount.';
								if (isset($request->api) && $request->api == 1) {
									throw new Exception($message);
								} else {
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}
						$calculatedRefund = $subtotal * $checkTotalTourist;
						$availableAmount = (float)$tourBooking->booking_total - $refundOnlyCheck;
						$tolerance = 0.01;
						if (abs($calculatedRefund - $availableAmount) > $tolerance) {
							if ($calculatedRefund > $availableAmount) {
								$message = 'Unable to refund booking. Refund amount is greater than the unrefunded amount.';
								if (isset($request->api) && $request->api == 1) {
									throw new Exception($message);
								} else {
									return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
								}
							}
						}
					}

					// validation cash end
					$tourBooking = $this->tourBooking::find($request->bookingID);
					//Update waiver, checkins and permit fees details for rate groups
					EditBookingService::updateWaiverAndPermitFees($request, $updatedCheckIns, $updatedRedeemTickets, $updatedRefundedPermitFees);
				}

				$total = 0;
				foreach ($array as $keyl => $value) {
					$transaction = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
					$tourBooking = $this->tourBooking::find($request->bookingID);
					$customer = $this->customer::find($tourBooking->customer_id);
					$rateCheckname = $this->tourPackageRate::where('id', $keyl)->withTrashed()->first();
					$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $keyl)->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
					$stripe_fees_percentage = $rateCheck->stripe_fees_percentage;

					if ($customer->is_affiliate == 'Yes') {
						$service_commission_percentage = $rateCheck->nat_fees_percentage;
					} else {
						$service_commission_percentage = $rateCheck->nat_fees_percentage;
					}

					if ($request->action_type == "delete" && $rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$keyl])) {
						$touristrefundedpermitfeesalready = count($refundedpermitarray[$keyl]);
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

						if ($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) {
							$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
						} else {
							$per = $rate * $rateCheck->additional_tax_percentage / 100;
						}

						$subtotal = ($rate + $per);
						if (($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) && $rateCheck->processing_charge_percentage > 0) {
							$subtotal = number_format((float)(($subtotal * $value) - (($touristrefundedpermitfeesalready * $rateCheck->permit_fee) + ($touristrefundedpermitfeesalready * (($rateCheck->permit_fee * $rateCheck->processing_charge_percentage) / 100)))), 2, '.', '');
						} else {
							$subtotal = number_format((float)(($subtotal * $value) - ($touristrefundedpermitfeesalready * $rateCheck->permit_fee)), 2, '.', '');
						}
					} else {
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

						if ($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) {
							$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
						} else {
							$per = $rate * $rateCheck->additional_tax_percentage / 100;
						}

						$subtotal = ($rate + $per);
						$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
					}

					$total += $subtotal;
				}

				$btvtotal = 0;
				$btv = $this->tourBooking::find($request->bookingID);
				$btvtotal = $btv->total;
				$btvtotal = (float)$btvtotal;
				$total = (float)$total;
				$nan = 0;

				$tsm = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();

				if (!empty($tsm) && $tsm->payment_type == 1 && $request->refund_Type == "dont_refund") {
					$paymentOnlyCheck = 0;
					$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();

					foreach ($paymentOnly as $value) {
						$details = json_decode($value->details);
						$paymentOnlyCheck += $details->amount;
					}

					$pendingAmount = $btv->total - $paymentOnlyCheck;
					$pendingAmount = round($pendingAmount);
					$deleteRequestAmount = 0;

					$deleteRequestAmount = $this->splitPaymentCheckBeforeDelete($array, $request->bookingID, $request->payment_type);

					$deleteRequestAmount = round($deleteRequestAmount);

					if ($deleteRequestAmount > $pendingAmount) {
						if (isset($request->api) && $request->api == 1) {
							throw new Exception('Unable to delete booking. Please delete unpaid pax first.');
						} else {
							$message = 'Unable to delete booking. Please delete unpaid pax first.';
							return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
						}
					}
				}

				if ($request->partial_Refund_value == "NaN") {
					$nan = 0;
				} else {
					$nan = $request->partial_Refund_value;
				}

				// validation for payemnt total less than refund total 
				$transactionData = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->get();
				$transactionRefundData = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Refund')->whereNull('charge_type')->get();
				$totalAmt = 0;
				$totalRefundAmt = 0;
				if ($transactionData->count() > 1) {
					foreach ($transactionData as $transaData) {
						$totalAmt += $transaData->amount;
					}
				} else {
					if (!empty($transaction)) {
						$totalAmt =  $transaction->amount;
					}
				}

				if ($transactionRefundData->count() > 1) {
					foreach ($transactionRefundData as $transaRefundData) {
						$totalRefundAmt += $transaRefundData->amount;
					}
				} else {
					$transactionRefundData = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Refund')->whereNull('charge_type')->first();
					if (!empty($transactionRefundData)) {
						$totalRefundAmt = $transactionRefundData->amount;
					}
				}
				$totalAmt = round($totalAmt - $totalRefundAmt, 2);
				if (!empty($transP)) { // if no pay later
					if ($request->refund_Type == "partial_refund") {
						$message = 'Unable to refund booking. Please enter amount less than total.';
						if ($nan > $totalAmt || $totalAmt == 0) {
							if (isset($request->api) && $request->api == 1) {
								throw new Exception($message);
							} else {
								return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
							}
						}
					}

					if ($request->refund_Type == "full_refund") {
						if ($nan > $totalAmt || $totalAmt == 0) {
							$message = 'Unable to refund booking. Refund amount is greater than the unrefunded amount.';
							if (isset($request->api) && $request->api == 1) {
								throw new Exception($message);
							} else {
								return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
							}
						}
					}

					if ($request->refund_Type == "100_refund") {
						if ($nan > $totalAmt || $totalAmt == 0) {
							$message = 'Unable to refund booking. Refund amount is greater than the unrefunded amount.';
							if (isset($request->api) && $request->api == 1) {
								throw new Exception($message);
							} else {
								return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
							}
						}
					}
				}
				// vaidation end 
				//Validation modified because now refunding fees as well in partial refunds
				if ($request->refund_Type == "partial_refund") {
					$feeAmount = 0;
					if ($request->payment_type == 'Card') {
						$feeAmount = ($total * $service_commission_percentage) / 100;
					}
					$netAmountForValidationPartialRefund = round($total + $feeAmount, 2);
					if (($nan > $netAmountForValidationPartialRefund) || ($nan > $btvtotal)) {
						$message = 'Unable to refund booking. Please enter amount less than total.';
						if (isset($request->api) && $request->api == 1) {
							throw new Exception($message);
						} else {
							return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
						}
					}
				}

				if (($total > $btvtotal) && ($request->partial_Refund_value == "NaN") && ($request->refund_Type != "dont_refund")) {
					$message = 'Unable to refund booking. Please enter amount less than total.';
					if (isset($request->api) && $request->api == 1) {
						throw new Exception($message);
					} else {
						return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
					}
				}

				$rateChecknameForPartialRefund = [];
				$tourBookingTotalForPayLater = 0;
				$totalDeletedPax = 0;
				foreach ($array as $key => $value) {
					$transaction = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
					$tourBooking = $this->tourBooking::find($request->bookingID);
					$customer = $this->customer::find($tourBooking->customer_id);
					$rateCheckname = $this->tourPackageRate::where('id', $key)->withTrashed()->first();

					$rateChecknameForPartialRefund[] = $rateCheckname->rate_for . ' person ' . $value;
					$totalDeletedPax += $value;
					$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $key)->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
					if ($customer->is_affiliate == 'Yes') {
						$serviceCommissionPercentage = $rateCheck->nat_fees_percentage;
					} else {
						$serviceCommissionPercentage = $rateCheck->nat_fees_percentage;
					}

					if ($request->action_type == "delete" && $rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
						$touristrefundedpermitfeesalready = count($refundedpermitarray[$key]);
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

						if ($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) {
							$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
						} else {
							$per = $rate * $rateCheck->additional_tax_percentage / 100;
						}

						$subtotal = ($rate + $per);
						if (($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) && $rateCheck->processing_charge_percentage > 0) {
							$subtotal = number_format((float)(($subtotal * $value) - (($touristrefundedpermitfeesalready * $rateCheck->permit_fee) + ($touristrefundedpermitfeesalready * (($rateCheck->permit_fee * $rateCheck->processing_charge_percentage) / 100)))), 2, '.', '');
						} else {
							$subtotal = number_format((float)(($subtotal * $value) - ($touristrefundedpermitfeesalready * $rateCheck->permit_fee)), 2, '.', '');
						}
					} else {
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
						if ($request->payment_type === 'Card' || ($isCashFees == '1' && $request->payment_type === 'Cash')) {
							$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
						} else {
							$per = $rate * $rateCheck->additional_tax_percentage / 100;
						}

						$subtotal = ($rate + $per);
						$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
					}

					$total = $subtotal;

					$bt = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)->first();

					$set_total = $bt->total - $total;
					$set_tourist = $bt->tourists - $value;

					$total_fee_amount = 0;
					if (!empty($transaction) && ($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash'))) {
						if ($request->bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$total_fee_amount = ($set_total * 6) / 100;
							}
						} else {
							$total_fee_amount = ($set_total * $serviceCommissionPercentage) / 100;
						}
					}

					$total_fee_amount = number_format((float)$total_fee_amount, 2, '.', '');

					$set_fee_amount = 0;
					if ($transaction) {
						$set_fee_amount = $tourBooking->service_commission - (float)$total_fee_amount;
						$set_fee_amount = number_format((float)$set_fee_amount, 2, '.', '');
					}

					DB::beginTransaction();
					if ($set_tourist == 0) {
						$this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
							->update([
								'total' => $set_total,
								'tourists' => $set_tourist,
								'check_in_status' => '',
								'redeem_ticket' => null,
								'refund_permitfee_status' => ''
							]);
					} else {
						$this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
							->update([
								'total' => $set_total,
								'tourists' => $set_tourist
							]);
					}

					if (!empty($transaction)) {
						$tourBooking_set_total = $tourBooking->total - $total;
						$tourBookingset_tourist = $tourBooking->tourists - $value;
						if (($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash')) && $request->refund_Type != "fareharbor") {
							$set_fee_amount = 0.00;
							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$set_fee_amount = ($total * 6) / 100;
								}
							} else {
								$set_fee_amount = ($total * $serviceCommissionPercentage) / 100;
							}

							$set_fee_amount = number_format((float)$set_fee_amount, 2, '.', '');
							$set_fee_amount = (float)$set_fee_amount;
							if ($request->refund_Type == "dont_refund") {

								$tourBooking_set_total = $tourBooking->total - ($total + $set_fee_amount);

								$permitFeeUpdate = ($tourBooking_set_total * $stripe_fees_percentage) / 100;
								$permitFeeUpdate = number_format((float)$permitFeeUpdate, 2, '.', '');

								if ($transK->payment_type == 1) {
									$tourBooking->update([
										'booking_total' => $tourBooking_set_total,
										'service_commission' => $permitFeeUpdate,
									]);
									DB::commit();
								}
							}

							if ($request->refund_Type == "100_refund") {
								// Ticket + fees both refunded — reduce total by full amount
								$tourBooking_set_total = $tourBooking->total - ($total + $set_fee_amount);
							}

							if ($request->refund_Type == "full_refund") {
								// Fees kept (not refunded) — reduce total by ticket cost only to match transaction amount
								$tourBooking_set_total = $tourBooking->total - $total;
							}

							$tourBooking_set_total = number_format((float)$tourBooking_set_total, 2, '.', '');
							if ($request->refund_Type != "partial_refund") {
								$masterUpdateDataV1 = [
									'total'    => $tourBooking_set_total,
									'tourists' => $tourBookingset_tourist,
								];
								// For 100% refund the fees go back to the customer, so remove them from service_commission too
								if ($request->refund_Type == "100_refund") {
									$masterUpdateDataV1['service_commission'] = max(0, round($tourBooking->service_commission - $set_fee_amount, 2));
								}
								$tourBooking->update($masterUpdateDataV1);
								DB::commit();
							}
						} else {
							if ($request->refund_Type != "partial_refund") {
								$tourBooking->update([
									'total'    => $tourBooking_set_total,
									'tourists' => $tourBookingset_tourist
								]);
								DB::commit();
							}
						}
					} else {
						// booking_total holds the original booking amount and must not change.
						// For card bookings, total includes the platform fee — subtract ticket+fee.
						if (isset($request->payment_type) && $request->payment_type === 'Card') {
							$noTransFee = round($total * $serviceCommissionPercentage / 100, 2);
							$tourBooking_set_total = $tourBooking->total - ($total + $noTransFee);
						} else {
							$tourBooking_set_total = $tourBooking->total - $total;
						}
						$tourBookingTotalForPayLater += $total;
						$tourBookingset_tourist = $tourBooking->tourists - $value;
						if ($request->refund_Type != "partial_refund") {
							$tourBooking->update([
								'total'    => $tourBooking_set_total,
								'tourists' => $tourBookingset_tourist
							]);
							DB::commit();
						}
					}

					if ($request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && ($request->is_cash == '')) {
						if ($request->refund_Type == "full_refund" && ($total > 0)) {
							if ($request->has('checked_transactions') && $request->checked_transactions != '') {
								// The admin explicitly picked this transaction, which is not necessarily
								// $transaction — look up its own persisted payment_mode. No fallback if
								// missing: a checked_transactions value with no matching row is a
								// genuine data problem that should surface loudly.
								$checkedTransaction = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->firstOrFail(['id', 'payment_mode']);
								$refundParams = [
									'payment_intent' => $request->checked_transactions,
									'amount' => $total * 100,
									'reverse_transfer' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $checkedTransaction->payment_mode);
							} else {
								$refundParams = [
									'payment_intent' => $transaction->transaction_number,
									'amount' => $total * 100,
									'reverse_transfer' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
							}
							DB::commit();
						}

						if ($request->refund_Type == "100_refund" && ($total > 0)) {
							$set_fee_amount = 0.00;

							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$set_fee_amount = ($total * 6) / 100;
								}
							} else {
								$set_fee_amount = ($total * $serviceCommissionPercentage) / 100;
							}

							$Refund100 = $total + $set_fee_amount;
							$Refund100 = round($Refund100, 2);
							$set_fee_amount = round($set_fee_amount, 2);
							if ($request->has('checked_transactions') && $request->checked_transactions != '') {
								$refundTrans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);
								$paymentTrans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount', 'payment_mode']);

								$totalrefundedamount = 0;
								foreach ($refundTrans as $ky => $val) {
									$totalrefundedamount += $val->amount;
								}

								$rounded = round($paymentTrans->amount - $totalrefundedamount - $Refund100, 2);

								if ($rounded <= -0.01) {
									$refundParams = [
										'payment_intent' => $request->checked_transactions,
										'amount' => ($Refund100 + $rounded) * 100,
										'reverse_transfer' => true,
										'refund_application_fee' => true,
									];
									app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
									DB::commit();
								} else {
									$refundParams = [
										'payment_intent' => $request->checked_transactions,
										'amount' => $Refund100 * 100,
										'reverse_transfer' => true,
										'refund_application_fee' => true,
									];
									app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
									DB::commit();
								}
							} else {
								$refundParams = [
									'payment_intent' => $transaction->transaction_number,
									'amount' => $Refund100 * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);

								DB::commit();
							}
						}
					}

					$deletedPeople = $value;
					if ($request->refund_Type == "dont_refund") {
						if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
							$fee_amount = 0;
							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$fee_amount = ($total * 6) / 100;
								}
							} else {
								$fee_amount = ($total * $serviceCommissionPercentage) / 100;
							}

							$fee_amount = number_format((float)$fee_amount, 2, '.', '');
							$total = $total + $fee_amount;
							$refund_keep_us = $total;
						} else {
							$refund_keep_us = $total;
						}

						if (empty($transaction)) {
							$total = 0;
						}

						// $fee_amount = 0;
						$refund_type = "Don't Refund";
					}

					if ($request->refund_Type == "partial_refund") {
						if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
							$fee_amount = 0;
							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$fee_amount = ($total * 6) / 100;
								}
							} else {
								$fee_amount = ($total * $serviceCommissionPercentage) / 100;
							}

							$fee_amount = number_format((float)$fee_amount, 2, '.', '');
							$total = $total + $fee_amount;
							$refund_keep_us = $total - (float)$request->partial_Refund_value;
							$refund_keep_us = number_format((float)$refund_keep_us, 2, '.', '');
							$fee_amount = 0;
						} else {
							$refund_keep_us = $total - (float)$request->partial_Refund_value;
							$refund_keep_us = number_format((float)$refund_keep_us, 2, '.', '');
						}

						if (empty($transaction)) {
							$total = 0;
						}

						$total = number_format((float)$request->partial_Refund_value, 2, '.', '');
						$refund_type = "Partial Refund";
					}

					if ($request->refund_Type == "full_refund") {
						if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
							$fee_amount = 0;
							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$fee_amount = ($total * 6) / 100;
								}
							} else {
								$fee_amount = ($total * $serviceCommissionPercentage) / 100;
							}

							$fee_amount = number_format((float)$fee_amount, 2, '.', '');
							$refund_keep_us = $fee_amount;
							$fee_amount = 0;
						} else {
							$refund_keep_us = 0;
						}

						if (empty($transaction)) {
							$total = 0;
						}

						$refund_type = "Full Refund minus Fees";
					}

					if ($request->refund_Type == "fareharbor") {
						$refund_keep_us = $total;
						$refund_type = "Fareharbor";
					}

					if ($request->refund_Type == "100_refund") {
						if (($request->payment_type == 'Card' || ($isCashFees == '1' && $request->payment_type == 'Cash')) && $request->refund_Type != "fareharbor") {
							$fee_amount = 0;
							if ($request->bookingID < $oldBookingID) {
								if ($customer->is_affiliate === "No") {
									$fee_amount = ($total * 6) / 100;
								}
							} else {
								$fee_amount = ($total * $serviceCommissionPercentage) / 100;
							}

							$fee_amount = number_format((float)$fee_amount, 2, '.', '');
							$total = $total + $fee_amount;
							$refund_keep_us = 0;
						} else {
							$refund_keep_us = 0;
						}

						if (empty($transaction)) {
							$total = 0;
						}

						$refund_type = "100 % Refund";
					}

					if (isset($request->is_cash) && $request->is_cash != '') {
						if ($request->is_cash == 'Cash') {
							$refundVia = 'Cash';
						} elseif ($request->is_cash == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					} else {
						$refundVia = $request->payment_type;
					}

					$data = [
						'amount' => $total,
						'refund_keep_us' => $refund_keep_us,
						'payment_type' => $refundVia,
						'deleted' => $rateCheckname->rate_for . ' person ' . $value,
						'created_by_with_name' => $created_by_with_name,
						'refund_type' => $refund_type,
						'comment' => null
					];

					if ($request->refund_Type != "partial_refund") {
						$historyId = HistoryService::storeRefundDataInHistory(
							$request->bookingID,
							$tourBooking->tourists,
							$created_by_with_name,
							$data['comment'],
							$data['refund_type'],
							null, /* $transaction */
							$data['amount'],
							null, /* $cardNumber */
							[], /* $cardsArray */
							[], /* $partialAmountArray */
							$data['payment_type'],
							null, /* $paymentTotal */
							null, /* $refundTotal */
							$data['refund_keep_us'],
							$data['deleted'],
							isset($fee_amount) ? $fee_amount : 0
						);

						// Remove contacts if booking package has contacts
						if ($packageHasContacts->has_contacts == 1) {
							if (isset($request->contacts) && $request->contacts != null) {
								try {
									PackageHasContactsService::deleteContacts($request->contacts);
								} catch (Exception $ex) {
									Log::error($ex->getMessage());
								}
							}
						}
					}

					$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();
					$paymentOnlyCheck = 0;
					foreach ($paymentOnly as $value) {
						$details = json_decode($value->details);
						$paymentOnlyCheck += $details->amount;
					}

					$bok = round($tourBooking->booking_total);
					$pay = round($paymentOnlyCheck);
					if (!empty($transaction) && $request->payment_type == 'Card' && $request->refund_Type != "fareharbor" && $request->refund_Type != "partial_refund") {
						if (isset($request->is_cash) && $request->is_cash != '') {
							if ($request->is_cash == 'Cash') {
								$refundVia = 'Cash';
							} elseif ($request->is_cash == 'Wire Transfer') {
								$refundVia = 'Check/Wire';
							}
						} else {
							$refundVia = $request->payment_type;
						}

						$TS = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();

						if ($TS->payment_type == 0) {
							if ($request->has('checked_transactions') && $request->checked_transactions != '') {
								$TS1 = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first();
								$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $total;
								$refundFeesAmount = $request->refund_Type == "100_refund" ? round($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2) : 0.00;
								$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, $refundFeesAmount, $refundVia, $TS1->transaction_number, $TS1->application_fee_id, mode: $TS1->payment_mode);
							} else {
								$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $total;
								$refundFeesAmount = $request->refund_Type == "100_refund" ? round($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2) : 0.00;
								$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, $refundFeesAmount, $refundVia, $TS->transaction_number, $TS->application_fee_id, mode: $TS->payment_mode);
							}
						}

						if ($pay >= $bok) {
							$TS->payment_type = 0;
							$TS->save();
						}
					} elseif (!empty($transaction) && $request->payment_type == 'Cash' && $request->refund_Type != "partial_refund") {
						if (isset($request->is_cash) && $request->is_cash != '') {
							if ($request->is_cash == 'Cash') {
								$refundVia = 'Cash';
							} elseif ($request->is_cash == 'Wire Transfer') {
								$refundVia = 'Check/Wire';
							}
						} else {
							$refundVia = $request->payment_type;
						}
						$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $total;
						if ($isCashFees == '1') {
							$refundFeesAmount = $request->refund_Type == "dont_refund" ? 0.00 : round($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
						} else {
							$refundFeesAmount = 0;
						}

						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, $refundFeesAmount, $refundVia, null, null);

						DB::commit();
					} elseif (!empty($transaction) && $request->refund_Type == "fareharbor" && $request->refund_Type != "partial_refund") {
						if (isset($request->is_cash) && $request->is_cash != '') {
							if ($request->is_cash == 'Cash') {
								$refundVia = 'Cash';
							} elseif ($request->is_cash == 'Wire Transfer') {
								$refundVia = 'Check/Wire';
							}
						} else {
							$refundVia = $request->payment_type;
						}
						$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $total;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $refundVia, null, null, mode: $transaction->payment_mode);
						DB::commit();
					} elseif (!empty($transaction) && $request->refund_Type != "partial_refund") {
						// Handle third-party platforms (TripAdvisor, GetYourGuide, Headout, etc.)
						if (isset($request->is_cash) && $request->is_cash != '') {
							if ($request->is_cash == 'Cash') {
								$refundVia = 'Cash';
							} elseif ($request->is_cash == 'Wire Transfer') {
								$refundVia = 'Check/Wire';
							}
						} else {
							$refundVia = $request->payment_type;
						}
						$refundAmount = $request->refund_Type == "dont_refund" ? 0.00 : $total;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $refundVia, null, null, mode: $transaction->payment_mode);
						DB::commit();
					}

					// Below line is for to update tour booking history table
					if ($request->refund_Type != "partial_refund") {
						$this->handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $request->all(), $refundVia, $refundTransId ?? null);
					}
				}


				// Partial Refund edit booking
				if ($request->refund_Type == "partial_refund") {
					$tourBooking = $this->tourBooking::find($request->bookingID);

					if ($request->has('checked_transactions') && $request->checked_transactions != '') {
						$transac = $this->transaction::where('tour_booking_id', $request->bookingID)->where('transaction_number', $request->checked_transactions)->where('status', 'Completed')->whereNull('charge_type')->first();
					} else {
						$transac = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
					}

					if ($request->partial_Refund_value == "NaN") {
						$message = 'Please enter partial refund amount.';
						if (isset($request->api) && $request->api == 1) {
							throw new Exception($message);
						} else {
							return BookingService::refundRouteWithError($request->bookingID, $modalUrl, $message);
						}
					}
					$transactionFirst = $this->transaction::where('tour_booking_id', $request->bookingID)->whereNull('charge_type')->first();
					if ($refundVia == "Card") {
						if ($request->partial_Refund_value > 0) {
							$refundParams = [
								'payment_intent' => $transac->transaction_number,
								'amount' => $request->partial_Refund_value * 100,
								'reverse_transfer' => true,
								'refund_application_fee' => true
							];
							app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transac->payment_mode);
						}
					}

					$btp = $this->bookingTransact::where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->get();

					$tcount = 0;
					foreach ($btp as $value) {
						$tcount += $value->tourists;
					}

					if (!empty($transactionFirst)) {
						$ptotal = $tourBooking->total - $request->partial_Refund_value;
						$tourBooking->update([
							'total' => $ptotal,
							'tourists' => $tcount,
						]);
					} else {
						$tourBooking->update([
							'total' => $tourBooking->total - $tourBookingTotalForPayLater,
							'booking_total' => $tourBooking->booking_total - $tourBookingTotalForPayLater,
							'tourists' => $tcount
						]);
					}
					DB::commit();
					if (!empty($transactionFirst)) {
						$partialValue = $request->partial_Refund_value;
					} else {
						$partialValue = 0;
						$refund_keep_us = 0;
					}

					$data = [
						'amount' => $partialValue,
						'refund_keep_us' => $refund_keep_us,
						'payment_type' => $refundVia,
						'deleted' => $rateChecknameForPartialRefund,
						'created_by_with_name' => $created_by_with_name,
						'refund_type' => $refund_type,
						'comment' => null
					];

					$historyId = HistoryService::storeRefundDataInHistory(
						$request->bookingID,
						$tcount,
						$created_by_with_name,
						$data['comment'],
						$data['refund_type'],
						null, /* $transaction */
						$data['amount'],
						null, /* $cardNumber */
						[], /* $cardsArray */
						[], /* $partialAmountArray */
						$data['payment_type'],
						null, /* $paymentTotal */
						null, /* $refundTotal */
						$data['refund_keep_us'],
						$data['deleted']
					);

					// Remove contacts if booking package has contacts
					if ($packageHasContacts->has_contacts == 1) {
						if (isset($request->contacts) && $request->contacts != null) {
							try {
								PackageHasContactsService::deleteContacts($request->contacts);
							} catch (Exception $ex) {
								Log::error($ex->getMessage());
							}
						}
					}

					if (!empty($transactionFirst)) {
						$refundAmount = $request->partial_Refund_value;
						$refundTransId = TransactionService::storeRefundInTransaction($request->bookingID, $refundAmount, 0, $refundVia, ($refundVia == "Card") ? $transac->transaction_number : null, null, mode: ($refundVia == "Card") ? $transac->payment_mode : PaymentModeResolver::DESTINATION);
						$this->handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $request->all(), $refundVia, $refundTransId ?? null);
					}
					DB::commit();
				}
			}
			// Update slot summary
			$seatsDiff = - ($totalDeletedPax);
			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $seatsDiff);
			DB::commit();
			$info = DB::table('tour_slot_times AS tst')
				->join('tour_bookings_master as bm', 'bm.tour_slot_id', '=', 'tst.id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('bm.id', $request->bookingID)
				->select([
					'tst.time_from',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage',
					't_o.tenant_id',
					'bm.name As custname',
					'bm.email As custemail',
					'bm.phone_number As phone_number',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					DB::raw("(SELECT SUM(tbm.tourists)
                            FROM tour_bookings_master AS tbm
                            WHERE tbm.tour_slot_id = tst.id
                                AND tbm.status != 'Cancelled'
                            GROUP BY tbm.tour_slot_id) AS booked_seats")
				])->first();

			$deletedPeople = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Refund')->get();

			$arEmailData = [
				'tour_d_t' => $info->date . ' ' . $info->time_from,
				'bookingId' => $request->bookingID,
				'custName' => $info->custname,
				'custEmail' => $info->custemail,
				'custPhone' => $info->phone_number ?? '',
				'deletedPeople' => $deletedPeople
			];

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendPeopleRemovedEmail($info, $arEmailData);
			}

			if (isset($request->api) && $request->api == 1) {
				return [
					'booking_id' => $request->bookingID,
					'message' => 'Booking has been updated' . ($request->refund_Type != "dont_refund" ? ' and refund has been initiated.' : '.')
				];
			} else {
				$message = 'Booking has been updated' . ($request->refund_Type != "dont_refund" ? ' and refund has been initiated.' : '.');
				return BookingService::refundRouteWithSuccess($request->bookingID, $modalUrl, $message);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Change people in the existing booking
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function splitPeopleBooking($request)
	{
		try {
			$tourBooking = $this->tourBooking::find($request->bookingID);
			$check = $request->input('check', []);
			$checkTotalTourist = count($check);
			$isCashFees = cashFees();

			if (count($check) == 0) {
				throw new Exception('You have not selected any checkbox in the action option.');
			}

			if ($tourBooking->tourists == $checkTotalTourist) {
				throw new Exception("You cannot split all tourists.");
			}

			if ($tourBooking->tourists < $checkTotalTourist) {
				throw new Exception("Something went wrong.Please try after sometimes.");
			}

			$check = $request->input('check', []);
			$array = array_count_values($check);
			foreach ($array as $key => $value) {
				$rateCheck = BookingTransact::where('tour_booking_id', $request->bookingID)->where('tour_package_rate_id', $key)->first();
				$subtotal = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
			}

			//calculate refund value
			$refundOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Refund')
				->where('payment_method', '!=', "Don't Refund")
				->whereNull('charge_type')
				->where('payment_type', '!=', "")
				->get([
					'id',
					'details'
				]);

			$refundOnlyCheck = 0;
			foreach ($refundOnly as $value) {
				$details = json_decode($value->details, true);
				$refundOnlyCheck += $details['amount'];
			}

			$trans = $this->transaction::where('tour_booking_id', $request->bookingID)
				->where('status', 'Completed')
				->whereNull('charge_type')
				->orderBy('id', 'DESC')
				->first();

			$calculatedRefund = $subtotal * $checkTotalTourist;
			$availableAmount = (float)$tourBooking->booking_total - $refundOnlyCheck;
			$tolerance = 0.01;
			if ($trans && abs($calculatedRefund - $availableAmount) > $tolerance) {
				if ($calculatedRefund > $availableAmount) {
					throw new Exception("The paid amount is too low to split among the selected people.");
				}
			}

			$paymentMethod = isset($trans->via) ? $trans->via : '';
			if ($isCashFees == '1' && $paymentMethod == 'Cash') {
				$paymentMethod = 'Card';
			}

			switch ($paymentMethod) {
				case 'Cash':
					$extendBooking = ExtendBookingCashService::run($request);
					break;
				case 'Invoiced':
					$extendBooking = ExtendBookingInvoicedService::run($request);
					break;
				case 'Card':
					$extendBooking = ExtendBookingCardService::run($request);
					break;
				default:
					$extendBooking = ExtendBookingPayLaterService::run($request);
					break;
			}

			return $extendBooking;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Change people in the existing booking
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function changePeopleBooking($request)
	{
		DB::beginTransaction();
		try {
			$isValidationCheckForBookingChangePeople = $request->get('validation_check') === 'true';
			if (isset($request->api) && $request->api == 1) {
				$this->transformer->transformRequestData($request);
			}

			$oldBookingID = oldBookingId();
			$isCashFees = cashFees();
			$tourBooking = $this->tourBooking::find($request->bookingID);

			$isGroupBooking = $tourBooking->is_group_booking;
			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->bookingID)
				->where('tbt.deleted_by', 0)
				->orderBy('tpr.orderby', 'asc')
				->get(['tpr.rate_for', 'tbt.tour_package_rate_id', 'tbt.tourists', 'tbt.rate', 'tbt.additional_charge', 'tbt.total', 'tbt.stripe_fees_percentage']);

			$rateGroupWiseTourists = array();
			$totalTouristsOfBooking = 0;
			$oldGroupRateId = 0;
			$oldGroupTourists = 0;
			$oldGroupRate = 0;
			foreach ($bookingT as $value) {
				$rateGroupWiseTourists[$value->tour_package_rate_id] = $value->tourists;
				$totalTouristsOfBooking += $value->tourists;
				if ($isGroupBooking == '1') {
					$oldGroupRateId = $value->tour_package_rate_id;
					$oldGroupTourists += $value->tourists;
					$oldGroupRate = $value->total;
				}
			}

			//calculate refund value
			$refundOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Refund')
				->get([
					'id',
					'details'
				]);
			$refundOnlyCheck = 0;
			foreach ($refundOnly as $value) {
				$details = json_decode($value->details, true);
				$refundOnlyCheck += $details['amount'];
			}
			$totalBooking = (float)$tourBooking->booking_total;
			$tolerance = 0.00001;
			if (request()->is('api/*')) {
				if (isset($request->refund_Type) && !empty($request->refund_Type) && $request->refund_Type != "dont_refund") {
					if (((abs($refundOnlyCheck - $totalBooking) < $tolerance) || ($refundOnlyCheck == $totalBooking)) && ((string)$request->tourists[0] < (string)$totalTouristsOfBooking)) {
						throw new Exception('Unable to change people as transaction already refunded.');
					}
				}
			} else {
				if (((abs($refundOnlyCheck - $totalBooking) < $tolerance) || ($refundOnlyCheck == $totalBooking)) && ((string)$request->tourists[0] < (string)$totalTouristsOfBooking)) {
					throw new Exception('Unable to change people as transaction already refunded.');
				}
			}


			if (empty($rateGroupWiseTourists)) {
				throw new Exception('Please select at least one adult.');
			}


			//Validations to restrict user to perform only delete peoples

			$result = BookingUtils::calculateChangedTouristsData($request, $tourBooking, $isGroupBooking, $rateGroupWiseTourists);

			$addedPeopleCount = $result['addedPeopleCount'];
			$deletedPeopleCount = $result['deletedPeopleCount'];
			$rateGroupWiseAddedPeople = $result['rateGroupWiseAddedPeople'];
			$rateGroupWiseDeletedPeople = $result['rateGroupWiseDeletedPeople'];
			$rateGroupWiseAddedPeopleArray = $result['rateGroupWiseAddedPeopleArray'];
			$rateGroupWiseDeletedPeopleArray = $result['rateGroupWiseDeletedPeopleArray'];
			$rateGrpIds = $result['rateGrpIds'];
			$permitfeeAr = $result['permitfeeAr'];

			// Check if there are addon changes even when tourist count hasn't changed
			$hasAddonChanges = ($request->has('addons_payload') && !empty($request->addons_payload)) ||
				($request->has('addons_added') && !empty($request->addons_added)) ||
				($request->has('addons_updated') && !empty($request->addons_updated)) ||
				($request->has('addons_removed') && !empty($request->addons_removed));

			if ($addedPeopleCount == 0 && $deletedPeopleCount == 0 && !$hasAddonChanges) {
				throw new Exception('You must need to select tourists to change.');
			}

			if ($addedPeopleCount == 0 && $deletedPeopleCount > 0 && $deletedPeopleCount == $totalTouristsOfBooking) {
				throw new Exception('You can not delete all pax from the booking.');
			}

			$userType = userType();

			$info = TourPackageService::getTourSlotInfo($request->slot_time, $userType);
			if (!$info) {
				throw new Exception('Unable to change people to tour package');
			}

			/* Validation for booking has contacts or not */
			if ($info->has_contacts == 1) {
				PackageHasContactsService::contactList($request->bookingID);
			}

			$created_by_with_name = BookingService::createdUserInfo();
			$customer = $this->customer::where('id', $tourBooking->customer_id)->withTrashed()->first();

			$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

			$rateGroups = BookingUtils::getRateGroups($rateGrpIds);

			$rateGroupWiseRate = $bookingT->pluck('rate', 'tour_package_rate_id')->toArray();
			$rateGroupWiseAdditionalCharge = $bookingT->pluck('additional_charge', 'tour_package_rate_id')->toArray();

			foreach ($rateGroups as $rateGroup) {
				if (isset($rateGroupWiseRate[$rateGroup->id])) {
					$rateGroup->rate = $rateGroupWiseRate[$rateGroup->id];
				}

				if (isset($rateGroupWiseAdditionalCharge[$rateGroup->id])) {
					$rateGroup->additional_charge = $rateGroupWiseAdditionalCharge[$rateGroup->id];
				}
			}

			$trans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();
			$payAmount = $request->payment_amount;
			$refundAmount = $request->refund_amount;
			$infantsorchilds = $request->infantorchilds;

			// This is for Pay Later bookings and infants and payment and refund amounts are same
			if ($trans == null || ($payAmount == 0 && $refundAmount == 0 && $infantsorchilds > 0) || ($payAmount == $refundAmount || round($refundAmount - $payAmount, 2) == -0.01 || round($payAmount - $refundAmount, 2) == 0.01)) {
				$discountAmt = 0;
				$result = EditBookingService::processCalculationsChangePeople($request, $rateGroups, $rateGroupWiseAddedPeople, $rateGroupWiseDeletedPeople, $rateGroupWiseTourists, $permitfeeAr, $natFeesPercentage, $oldGroupRateId, $oldGroupRate, $isGroupBooking, $stripeFeesPercentage);
				$total = $result['total'];
				$paymentTotal = $result['paymentTotal'];
				$refundTotal = $result['refundTotal'];
				$newlyAddedTotal = $result['newlyAddedTotal'];
				$newGroupRateId = $result['newGroupRateId'];
				$touristGroups	= $result['touristGroups'];
				$tbTransact = $result['tbTransact'];
				$subtt = $total;

				//This is for refund process
				$refundProcess = false;
				if ($total < 0) {
					$refundProcess = true;
				}

				if (!$refundProcess) {
					if ($request->has('discount_type') && $total > 0) {
						$discountAmt = match ($request->discount_type) {
							'Percentage' => round(($total * ($request->discount_percentage ?? 0)) / 100, 2),
							'Fixed Money' => round($request->discount_fixed_money ?? 0, 2),
							default => 0,
						};
					}
				}

				$tt = $total = round($total, 2);
				$totalTourists = $tourBooking->tourists + $addedPeopleCount - $deletedPeopleCount;

				$tourBookingUpdate = false;
				if ($tourBooking) {
					$updates = [
						'tourists' => $totalTourists,
						'discount2_value' => DB::raw('discount2_value+' . $discountAmt),
						'updated_at' => mysqlDT(),
						'updated_by' => Auth::id(),
						'updated_by_user_type' => $userType,
					];

					// Add totals only if it's not a group booking or a new group rate exists
					if (!$isGroupBooking || $newGroupRateId !== '') {
						$updates['total'] = DB::raw('total+' . $subtt);
						$updates['booking_total'] = DB::raw('booking_total+' . $subtt);
					}

					if (
						!empty($request->discount_percentage) ||
						(!empty($tourBooking->discount2_percentage) && $tourBooking->discount2_percentage > 0)
					) {
						$oldDiscountedTotalWithoutServiceCharge = $tourBooking->total;
						$originalTotalOld = $oldDiscountedTotalWithoutServiceCharge + $tourBooking->discount2_value;

						$newDiscountedTotalWithoutServiceCharge = $total;
						$originalTotalNew = $newDiscountedTotalWithoutServiceCharge + $discountAmt;

						$newDiscountPercent = round(
							100 - (
								($oldDiscountedTotalWithoutServiceCharge + $newDiscountedTotalWithoutServiceCharge) * 100 /
								($originalTotalOld + $originalTotalNew)
							),
							2
						);

						$updates['discount2_percentage'] = $newDiscountPercent;
					}

					$tourBookingUpdate = $tourBooking->update($updates);
				}

				if (!$tourBookingUpdate) {
					throw new Exception('Unable to change people to tour package.');
				}

				for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
					$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
				}

				$addedPeoplesTexts = array();
				$deletedPeoplesTexts = array();

				if (!empty($tbTransact)) {
					foreach ($tbTransact as $tbltrans) {

						$tbTransactexist = $this->bookingTransact::where('tour_package_rate_id', $tbltrans['tour_package_rate_id'])->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
						$tourPackageRateGroup = $this->tourPackageRate::find($tbltrans['tour_package_rate_id']);
						if (!empty($tbTransactexist) && isset($tbTransactexist->id)) {

							$updatesTransact = [];
							if ($tbltrans['added'] == true) {
								$addedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
								$updatesTransact = BookingUtils::getTransactionUpdatesChangePeople($tbltrans, $tbTransactexist, $isGroupBooking, $userType);
							} else if ($tbltrans['deleted'] == true) {
								$deletedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
								$updatesTransact = BookingUtils::getTransactionUpdatesChangePeople($tbltrans, $tbTransactexist, $isGroupBooking, $userType);
							}
							unset($tbltrans['rate_for']);
							if ($tbTransactexist->check_in_status != '') {
								$updatesTransact['check_in_status'] = BookingUtils::updateCheckInStatusChangePeople($tbltrans, $tbTransactexist);
							}

							if ($tbTransactexist->redeem_ticket != '') {
								$updatesTransact['redeem_ticket'] = BookingUtils::updateRedeemTicketInfoChangePeople($tbltrans, $tbTransactexist);
							}

							if (isset($updatesTransact) && !empty($updatesTransact)) {
								if ($isGroupBooking != '1') {
									if ($request->payment_method == "Card") {
										$updatesTransact = static::saveCardPermitFeeJson($updatesTransact, $tbTransactexist, $tbltrans, $tourPackageRateGroup);
									} else {
										$updatesTransact = static::saveNonCardPermitFeeJson($updatesTransact, $tbTransactexist, $tbltrans);
									}
								}
							}

							if (isset($updatesTransact) && !empty($updatesTransact)) {
								$this->bookingTransact::where('id', $tbTransactexist->id)->update($updatesTransact);
							}
						} else {
							if ($isGroupBooking == '1' && $newGroupRateId > 0 && $oldGroupRateId != $newGroupRateId) {
								$tbTransactexist = $this->bookingTransact::where('tour_package_rate_id', $oldGroupRateId)->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
								if (!empty($tbTransactexist) && isset($tbTransactexist->id)) {
									if (($tbltrans['added'] == true || $tbltrans['deleted'] == true)) {
										$updatesTransact = [];
										if ($tbltrans['added'] == true) {
											$addedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
										} else if ($tbltrans['deleted'] == true) {
											$deletedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
										}
										$updatesTransact = BookingUtils::getTransactionUpdatesChangePeopleGroupBooking($tbltrans, $newGroupRateId, $userType);

										if ($tbTransactexist->check_in_status != '') {
											$updatesTransact['check_in_status'] = BookingUtils::updateCheckInStatusChangePeople($tbltrans, $tbTransactexist);
										}


										if ($tbTransactexist->redeem_ticket != '') {
											$updatesTransact['redeem_ticket'] = BookingUtils::updateRedeemTicketInfoChangePeople($tbltrans, $tbTransactexist);
										}

										if (isset($updatesTransact) && !empty($updatesTransact)) {
											$this->bookingTransact::where('id', $tbTransactexist->id)->update($updatesTransact);
										}
									}
								}
							} else {
								$addedPeoplesTexts[] = $tbltrans['rate_for'] . " person " . $tbltrans['tourists'];
								unset($tbltrans['rate_for'], $tbltrans['added'], $tbltrans['deleted']);
								DB::table('tour_bookings_transact')->insert($tbltrans);
							}
						}
					}
				}

				/* Execute contacts only if booking package has contacts feature enabled */
				$packageHasContacts = $this->tourPackage::find($tourBooking->tour_package_id);

				if ($packageHasContacts->has_contacts == 1) {
					/* Remove Contacts */
					if ($deletedPeopleCount > 0) {
						if (isset($request->contacts)) {
							try {
								PackageHasContactsService::deleteContacts($request->contacts);
							} catch (Exception $ex) {
								Log::error($ex->getMessage());
							}
						}
					}

					/* Add Contacts */
					if ($addedPeopleCount > 0) {
						if (isset($request->new_contacts) && $request->new_contacts != "") {
							try {
								PackageHasContactsService::storeTourBookingPeople($tourBooking->id, $request->new_contacts);
							} catch (Exception $ex) {
								Log::error($ex->getMessage());
							}
						}
					}
				}

				$data = [
					'tourists' => $totalTourists,
					'sub_operation' => 'Tourists Changed',
					'discount_percentage' => $request->discount_percentage ?: 0,
					'discount2_value' => $discountAmt,
					'addedpeoplesetexts' => $addedPeoplesTexts,
					'deletedpeoplestexts' => $deletedPeoplesTexts,
					'addedtourists' => $addedPeopleCount,
					'deletedtourits' => $deletedPeopleCount,
					'paymenttotal' => $paymentTotal,
					'refundtotal' => $refundTotal
				];

				HistoryService::changeTouristInHistory($tourBooking->id, $data, $created_by_with_name);
				$seatsDiff = $addedPeopleCount - $deletedPeopleCount;
				TimeSlotAvailabilityService::updateSlotSummary($request->slot_time, $seatsDiff);
				$errors = [];
				$paymentIntentInfo = [
					'clientSecret' => '',
					'transactionId' => '',
				];

				try {
					$arEmailData = [
						'tour_d_t' => $info->date . ' ' . $info->time_from,
						'bookingId' => $request->bookingID,
						'bookingGroupId' => $tourBooking->group_id,
						'custName' => $tourBooking->name,
						'custEmail' => $tourBooking->email,
						'custPhone' => $tourBooking->phone_number
					];

					if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
						EmailService::sendAcknowledgementEmail($info, $arEmailData);
					}
				} catch (Exception $exception) {
					Log::error($exception);
					$errors[] = 'email';
				}
				DB::commit();

				return [
					'bookingId' => $tourBooking->id,
					'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
					'stripeId' => $paymentIntentInfo['clientSecret'],
					'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
					'errors' => $errors,
					'errors_message' => !empty($errors_message) ? $errors_message : '',
				];
			}
			if (!$request->is('api/*')) {
				if ($isValidationCheckForBookingChangePeople) {
					DB::commit();
					return response()->json([
						'message' => 'All validation checks were successful!'
					]);
				}
			}
			if ($addedPeopleCount == 0 && $deletedPeopleCount > 0) {
				$formData = $request->all();
				$formData['clientIp'] =  $request->getClientIp();
				$formData['requestFrom'] = $request->is('api/*') ? Constants::API : Constants::BACKEND;
				if ($isGroupBooking == '1') {
					$paymentTransCountForBooking = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->count();
					$res = EditBookingService::deletePaxFromGroupBooking($request, '', $paymentTransCountForBooking, true);
				} else {
					$res = $this->deletePeopleChangePeople($formData);
				}
				if (!empty($res) && isset($res['success']) && $res['success'] == true) {
					DB::commit();
					return [
						'bookingId' => $request->bookingID,
						'bookingToken' => Crypt::encryptString($request->bookingID), // for security purpose
					];
				} else {
					throw new Exception('Something went wrong.Unable to change people to tour package.');
				}
			}

			$transactionFirst = $this->transaction::where('status', 'Completed')->where('tour_booking_id', $request->bookingID)->whereNull('charge_type')->first();
			$paymentMethod = (!empty($transactionFirst)) ? $transactionFirst->via : '';

			$discountAmt = 0;
			$tt = 0;
			$serviceCommission = 0;
			$serviceCommissionTotal = 0;
			$serviceCommissionNewlyAddedTotal = 0;
			$newGroupRateId = 0;

			$result = EditBookingService::processCalculationsChangePeople($request, $rateGroups, $rateGroupWiseAddedPeople, $rateGroupWiseDeletedPeople, $rateGroupWiseTourists, $permitfeeAr, $natFeesPercentage, $oldGroupRateId, $oldGroupRate, $isGroupBooking, $stripeFeesPercentage);
			$total = $result['total'];

			$paymentTotal = $result['paymentTotal'];
			$refundTotal = $result['refundTotal'];
			$newlyAddedTotal = $result['newlyAddedTotal'];
			$newGroupRateId = $result['newGroupRateId'];
			$touristGroups	= $result['touristGroups'];
			$tbTransact = $result['tbTransact'];
			if ($request->payment_method == 'Card' || $paymentMethod == 'Card' || ($isCashFees == '1' && ($request->payment_method == 'Cash' || $paymentMethod == 'Cash'))) {
				$serviceCommissionTotal = $result['serviceCommissionTotal'];
			}

			//This is for refund process
			$refundProcess = false;
			if ($total < 0) {
				$refundProcess = true;
			}

			$totalTourists = $tourBooking->tourists + $addedPeopleCount - $deletedPeopleCount;
			$subtt = $total;
			if (!$refundProcess) {
				if ($request->has('discount_type')) {
					$discountAmt = match (true) {
						$request->discount_type === 'Percentage' && !empty($request->discount_percentage) =>
						round(($total * $request->discount_percentage) / 100, 2),

						$request->discount_type === 'Fixed Money' && !empty($request->discount_fixed_money) =>
						round($request->discount_fixed_money, 2),

						default => 0,
					};
					$total -= $discountAmt;
					$subtt -= $discountAmt;
				}
			}

			$checkaffiliate = null;
			if ($tourBooking->customer_id != 0) {
				$checkaffiliate = $this->customer::where('id', $tourBooking->customer_id)->first();
			}

			if (!$refundProcess) {
				if (($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) && $checkaffiliate->is_affiliate == 'No') {
					//This is for old bookings
					if ($request->bookingID < $oldBookingID) {
						$serviceCommission = $total * 6 / 100;
						$total += $serviceCommission;
						if ($newlyAddedTotal > 0) {
							$newlyAddedTotal += $newlyAddedTotal * 6 / 100;
						}
					} else {
						if ($serviceCommissionTotal > 0 && $deletedPeopleCount == 0) { //This is the calculations if people only added
							$serviceCommission = $serviceCommissionTotal;
						} else {
							$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2); //This is the calculations if people only added and deleted both
						}
						$total += $serviceCommission;
						if ($newlyAddedTotal > 0) {
							if ($serviceCommissionNewlyAddedTotal > 0 && $deletedPeopleCount == 0) { //This is the calculations if people only added
								$newlyAddedTotal += $serviceCommissionNewlyAddedTotal;
							} else {
								$newlyAddedTotal += roundout(($newlyAddedTotal * $natFeesPercentage / 100), 2); //This is the calculations if people only added and deleted both
							}
						}
					}
				}

				if (($request->payment_method == "Card" || ($isCashFees == 1 && $request->payment_method == 'Cash')) && $checkaffiliate->is_affiliate == 'Yes') {
					if ($request->bookingID > $oldBookingID) {
						if ($serviceCommissionTotal > 0 && $deletedPeopleCount == 0) {
							$serviceCommission = $serviceCommissionTotal;
						} else {
							$serviceCommission = roundout(($total * $natFeesPercentage / 100), 2);
						}
						$total += $serviceCommission;
						if ($newlyAddedTotal > 0) {
							if ($serviceCommissionNewlyAddedTotal > 0 && $deletedPeopleCount == 0) {
								$newlyAddedTotal += $serviceCommissionNewlyAddedTotal;
							} else {
								$newlyAddedTotal += roundout(($newlyAddedTotal * $natFeesPercentage / 100), 2);
							}
						}
					}
				}
			}

			$tt = $total = round($total, 2);
			$serviceCommission = round($serviceCommission, 2);

			$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Payment')
				->get([
					'id',
					'ticket_cost',
					'processing_fees',
					'platform_fees'
				]);

			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$paymentOnlyCheck += $value->ticket_cost + $value->processing_fees + $value->platform_fees;
			}

			$splitPendingAmount = 0;
			$splitPendingAmount = round($tourBooking->total - $paymentOnlyCheck, 2);

			$transP = $this->transaction::where('status', 'Completed')->where('tour_booking_id', $request->bookingID)->whereNull('charge_type')->first();
			$doNotRefund = 0;
			if ($refundProcess && !empty($transP) && ($transP->via == 'Card' || ($isCashFees == 1 && $transP->via == 'Cash'))) {
				$trans = $this->transaction::where('tour_booking_id', $request->bookingID)
					->where('status', 'Completed')
					->whereNull('charge_type')
					->orderBy('id', 'DESC')
					->first();

				if (!empty($trans) && ($transP->via == "Card" || ($isCashFees == 1 && $transP->via == 'Cash')) && $trans->payment_type == 1) {
					$doNotRefund = 1;
				}
			}

			$tourBookingUpdate = false;
			if ($tourBooking) {
				$partialRefunds = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Refund')->whereIn('payment_method', ['Partial Refund', '100 % Refund', 'Full Refund minus Fees', 'Permitfees Refund', "Don't Refund"])->get();

				$bookingT = $this->bookingTransact::with('packageRate')
					->where('tour_booking_id', $request->bookingID)
					->where('deleted_by', 0)
					->get();

				// Calculate the booking amount because old bookings data have booking_total in tour_booking_master table is 0
				$calculatedAmounts = BookingService::calculateBookingAmountChangePeople($request, $bookingT, $natFeesPercentage, $oldBookingID, $rateGroupWiseAddedPeopleArray, $rateGroupWiseDeletedPeopleArray, $isCashFees, $paymentMethod, $isGroupBooking);
				$bookingTotal = $calculatedAmounts['bookingTotal'];
				$refundedPermitFeesTotalAmount = $calculatedAmounts['refundedPermitFeesTotalAmount'];
				$refundedProcessingFeesTotal = $calculatedAmounts['refundedProcessingFeesTotal'];
				$serviceCommissionOldTotal = $calculatedAmounts['serviceCommissionOldTotal'];

				if ($tourBooking->discount2_value > 0 && $bookingTotal > 0) {
					$bookingTotal -= $tourBooking->discount2_value;
				}

				$serviceCommissionOld = 0;
				if (($request->payment_method == "Card" || ($isCashFees == 1 && $request->payment_method == 'Cash')) && $checkaffiliate->is_affiliate == 'No') {
					if ($request->bookingID < $oldBookingID) {
						$serviceCommissionOld = ($bookingTotal + $refundedPermitFeesTotalAmount + $refundedProcessingFeesTotal) * 6 / 100;
						$bookingTotal += $serviceCommissionOld;
					} else {
						if ($serviceCommissionOldTotal > 0) {
							$serviceCommissionOld = $serviceCommissionOldTotal;
						} else {
							$serviceCommissionOld = round((($bookingTotal + $refundedPermitFeesTotalAmount + $refundedProcessingFeesTotal) * $natFeesPercentage / 100), 2);
						}
						$bookingTotal += $serviceCommissionOld;
					}
				}

				if (($request->payment_method == "Card" || ($isCashFees == 1 && $request->payment_method == 'Cash')) && $checkaffiliate->is_affiliate == 'Yes' && $natFeesPercentage > 0 && $request->bookingID > $oldBookingID) {
					if ($serviceCommissionOldTotal > 0) {
						$serviceCommissionOld = $serviceCommissionOldTotal;
					} else {
						$serviceCommissionOld = round((($bookingTotal + $refundedPermitFeesTotalAmount + $refundedProcessingFeesTotal) * $natFeesPercentage / 100), 2);
					}
					$bookingTotal += $serviceCommissionOld;
				}

				//This is for adjust calculation of booking total on booking info page if partial refunds done
				if ($refundProcess) {
					$bookingTotal += abs($total);
				}

				$partialRefundAmount = BookingService::calculatePartialRefundAmount($partialRefunds);
				$bookingTotal += $partialRefundAmount;

				$bookingTotal = round($bookingTotal, 2);
				$transactionFirst = $this->transaction::where('status', 'Completed')->where('tour_booking_id', $tourBooking->id)->whereNull('charge_type')->first();
				$paymentMethod = $transactionFirst->via;

				if ($refundProcess && ($paymentMethod == 'Card' || ($isCashFees == 1 && $paymentMethod == 'Cash'))) {
					if ($doNotRefund == 1) {
						$rateCheck = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)->where('deleted_by', 0)->first();
						$stripeFeesPercentage = $rateCheck->stripe_fees_percentage;
						$serviceCommissionPercentage = $rateCheck->nat_fees_percentage;
						if ($request->bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$setFeeAmount = (abs($total) * 6) / 100;
							}
						} else {
							$setFeeAmount = (abs($total) * $serviceCommissionPercentage) / 100;
						}

						$setFeeAmount = number_format((float)$setFeeAmount, 2, '.', '');
						$setFeeAmount = (float)$setFeeAmount;
						$tourBookingSetTotal = $tourBooking->total - (abs($total) + $setFeeAmount);
						$permitFeeUpdate = ($tourBookingSetTotal * $stripeFeesPercentage) / 100;
						$updates = [
							'tourists' => $totalTourists,
							'discount2_value' => DB::raw('discount2_value+' . $discountAmt),
							'service_commission' => $permitFeeUpdate,
							'booking_total' => $tourBookingSetTotal,
							'total' => $tourBookingSetTotal,
							'updated_at' => mysqlDT(),
							'updated_by' => Auth::id(),
							'updated_by_user_type' => $userType,
						];
					} else {
						$updates = [
							'tourists' => $totalTourists,
							'discount2_value' => DB::raw('discount2_value+' . $discountAmt),
							'service_commission' => DB::raw('service_commission+' . (($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $serviceCommission : 0)),
							'total' => DB::raw('total+' . (($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $total : $subtt)),
							'updated_at' => mysqlDT(),
							'updated_by' => Auth::id(),
							'updated_by_user_type' => $userType,
						];
					}
				} else if ($refundProcess && $paymentMethod == 'Cash' && $isCashFees != 1) {
					$updates = [
						'tourists' => $totalTourists,
						'discount2_value' => DB::raw('discount2_value+' . $discountAmt),
						'service_commission' => DB::raw('service_commission+' . ($request->payment_method == "Card" ? $serviceCommission : 0)),
						'total' => DB::raw('total+' . $subtt),
						'updated_at' => mysqlDT(),
						'updated_by' => Auth::id(),
						'updated_by_user_type' => $userType,
					];
				} else {
					$updates = [
						'tourists' => $totalTourists,
						'discount2_value' => DB::raw('discount2_value+' . $discountAmt),
						'service_commission' => DB::raw('service_commission+' . (($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $serviceCommission : 0)),
						'total' => DB::raw('total+' . (($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $total : $subtt)),
						'booking_total' => DB::raw('booking_total+' . (($request->payment_method == "Card" || ($isCashFees == '1' && $request->payment_method == 'Cash')) ? $total : $subtt)),
						'updated_at' => mysqlDT(),
						'updated_by' => Auth::id(),
						'updated_by_user_type' => $userType,
					];
				}

				if (!empty($request->discount_percentage) || (!empty($tourBooking->discount2_percentage) && $tourBooking->discount2_percentage > 0)) {
					$oldDiscountedTotalWithoutServiceCharge = $tourBooking->total - $tourBooking->service_commission;
					$originalTotalOld = $oldDiscountedTotalWithoutServiceCharge + $tourBooking->discount2_value;

					$newDiscountedTotalWithoutServiceCharge = $total - $serviceCommission;
					$originalTotalNew = $newDiscountedTotalWithoutServiceCharge + $discountAmt;

					$newDiscountPercent = round(100 - (
						($oldDiscountedTotalWithoutServiceCharge + $newDiscountedTotalWithoutServiceCharge) * 100 /
						($originalTotalOld + $originalTotalNew)
					), 2);

					$updates['discount2_percentage'] = $newDiscountPercent;
				}

				$tourBookingUpdate = $tourBooking->update($updates);
			}


			for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
				$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
			}

			$addedPeoplesTexts = array();
			$deletedPeoplesTexts = array();
			if (!empty($tbTransact)) {
				foreach ($tbTransact as $tbltrans) {
					$tbTransactexist = $this->bookingTransact::where('tour_package_rate_id', $tbltrans['tour_package_rate_id'])->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
					$tourPackageRateGroup = $this->tourPackageRate::find($tbltrans['tour_package_rate_id']);

					if (!empty($tbTransactexist) && isset($tbTransactexist->id)) {
						if ($tbltrans['added'] == true) {
							$addedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
						} else if ($tbltrans['deleted'] == true) {
							$deletedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
						}
						EditBookingService::updateTblTransactChangePeople($tbltrans, $tbTransactexist, $request->payment_method, $isGroupBooking, $userType);
					} else {
						if ($isGroupBooking == '1' && $newGroupRateId > 0 && $oldGroupRateId != $newGroupRateId) {
							$tbTransactexist = $this->bookingTransact::where('tour_package_rate_id', $oldGroupRateId)->where('tour_booking_id', $request->bookingID)->where('deleted_by', 0)->first();
							if (!empty($tbTransactexist) && isset($tbTransactexist->id)) {
								EditBookingService::updateTblTransactChangeGroupPeople($tbltrans, $tbTransactexist, $newGroupRateId, $userType);
								if ($tbltrans['added'] == true) {
									$addedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
								} else if ($tbltrans['deleted'] == true) {
									$deletedPeoplesTexts[] = BookingUtils::getPeopleText($isGroupBooking, $tbltrans);
								}
							}
						} else {
							$addedPeoplesTexts[] = $tbltrans['rate_for'] . " person " . $tbltrans['tourists'];
							unset($tbltrans['rate_for'], $tbltrans['added'], $tbltrans['deleted']);
							DB::table('tour_bookings_transact')->insert($tbltrans);
						}
					}
				}
			}

			/* Execute only when booking package has contacts feature enabled */
			$packageHasContacts = $this->tourPackage::find($tourBooking->tour_package_id);

			if ($packageHasContacts->has_contacts == 1) {
				/* Remove contacts */
				if ($deletedPeopleCount > 0) {
					if (isset($request->contacts)) {
						try {
							PackageHasContactsService::deleteContacts($request->contacts);
						} catch (Exception $ex) {
							Log::error($ex->getMessage());
						}
					}
				}

				/* Add contacts */
				if ($addedPeopleCount > 0) {
					if (isset($request->new_contacts) && $request->new_contacts != "") {
						try {
							PackageHasContactsService::storeTourBookingPeople($tourBooking->id, $request->new_contacts);
						} catch (Exception $ex) {
							Log::error($ex->getMessage());
						}
					}
				}
			}

			$created_by_with_name = BookingService::createdUserInfo();

			$paymentTotal = round($paymentTotal, 2);
			$refundTotal = round($refundTotal, 2);

			$data = [
				'tourists' => $totalTourists,
				'sub_operation' => 'Tourists Changed',
				'discount_percentage' => $request->discount_percentage ?: 0,
				'discount2_value' => $discountAmt,
				'addedpeoplesetexts' => $addedPeoplesTexts,
				'deletedpeoplestexts' => $deletedPeoplesTexts,
				'addedtourists' => $addedPeopleCount,
				'deletedtourits' => $deletedPeopleCount,
				'paymenttotal' => $paymentTotal,
				'refundtotal' => $refundTotal
			];

			//This is for refund process
			if ($refundProcess) {
				$transactionFirst = $this->transaction::where('status', 'Completed')->where('tour_booking_id', $tourBooking->id)->whereNull('charge_type')->first();
				$paymentMethod = $transactionFirst->via;
				if ($paymentMethod == 'Cash' || $paymentMethod == 'Invoiced') {
					$total = abs($total);
					$transactionId = TransactionService::storeRefundInTransaction($tourBooking->id, $total, '', $paymentMethod, null, null);

					$historyId = HistoryService::storeRefundDataInHistory(
						$tourBooking->id,
						$totalTourists,
						$created_by_with_name,
						null, /* $comment */
						'Partial Refund', /* $refundType */
						null, /* $transaction */
						$total,
						null, /* $cardNumber */
						[], /* $cardsArray */
						[], /* $partialAmountArray */
						$paymentMethod,
						$paymentTotal,
						$refundTotal
					);

					HistoryService::changeTouristInHistory($tourBooking->id, $data, $created_by_with_name);
				} else if ($paymentMethod == 'Card') {
					$total = abs($total);
					if ($total > 0) {
						$piMode = $transactionFirst->payment_mode;
						if ($request->has('checked_transactions') && $request->checked_transactions != '') {
							$transID = $request->checked_transactions;
							// The admin explicitly picked this transaction, which is not necessarily
							// $transactionFirst — look up its own persisted payment_mode rather than
							// assuming it matches whichever transaction happened to be "first". No
							// fallback if it's missing: a checked_transactions value with no matching
							// row is a genuine data problem that should surface loudly, not be papered
							// over with an assumed mode.
							$checkedTransaction = $this->transaction::where('transaction_number', $transID)->firstOrFail();
							$piMode = $checkedTransaction->payment_mode;
							StripeService::processStripeRefund($transID, $total, $checkedTransaction->payment_mode);
						} else {
							if (!empty($transactionFirst->transaction_number) && $doNotRefund == 0) {
								$transID = $transactionFirst->transaction_number;
								StripeService::processStripeRefund($transID, $total, $transactionFirst->payment_mode);
							}
						}

						if ($doNotRefund == 0) {
							$transactionId = TransactionService::storeRefundInTransaction($tourBooking->id, $total, '', $paymentMethod, ($doNotRefund == 1) ? null : $transID, null, mode: $piMode);
							$cardDetails = BookingService::cardLastFourDigit($tourBooking->id, $transID);
							$cardNumber = (!empty($cardDetails)) ? $cardDetails['card_last_4_digit'] : '';
						} else {
							$cardNumber = '';
						}

						$historyId = HistoryService::storeRefundDataInHistory(
							$tourBooking->id,
							$totalTourists,
							$created_by_with_name,
							null, /* $comment */
							($doNotRefund == 1) ? "Don't Refund" : 'Partial Refund', /* $refundType */
							null, /* $transaction */
							$total,
							$cardNumber,
							[], /* $cardsArray */
							[], /* $partialAmountArray */
							$paymentMethod,
							$paymentTotal,
							$refundTotal,
							$total
						);
					}

					HistoryService::changeTouristInHistory($tourBooking->id, $data, $created_by_with_name);
				}
				$seatsDiff = $addedPeopleCount - $deletedPeopleCount;
				TimeSlotAvailabilityService::updateSlotSummary($request->slot_time, $seatsDiff);
				if ($doNotRefund == 0) {
					RefundBookingService::run($transactionId ?? null, $historyId, $paymentMethod);
				}
				DB::commit();

				return [
					'bookingId' => $tourBooking->id,
					'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
				];
			} else {
				if ($request->payment_method == 'Cash' || $request->payment_method == 'Pay Later' || $request->payment_method == 'Invoiced') {
					$paymentMethod = ($request->payment_method == 'Pay Later' || $request->payment_method == 'Invoiced') ? 'Invoiced' : 'Cash';
					$changeAmount = 0;

					if ($request->payment_method === "Cash") {
						$cashPaidAmount = $request->has('cash_paid_amount') && $request->cash_paid_amount !== ''
							? $request->cash_paid_amount
							: '';

						$paymentAmountCash = !empty($request->split_value)
							? $request->split_value
							: ($isCashFees == 1 ? $tt : $subtt);

						// Calculate change amount if cash paid is more than required amount
						if (!empty($cashPaidAmount) && $cashPaidAmount > $paymentAmountCash) {
							$changeAmount = round($cashPaidAmount - $paymentAmountCash, 2);
						}

						// Ensure change is 0 if no cash amount is provided
						if (empty($cashPaidAmount)) {
							$changeAmount = 0;
						}
					}

					if ($isCashFees == 1 && $request->payment_method == "Cash") {
						$totalAmount = !empty($request->split_value) ? $request->split_value : $tt;
						if ($tourBooking->id < $oldBookingID) {
							if ($customer->is_affiliate === 'Yes') {
								$feesAmount = ($totalAmount - ($totalAmount * 2.9 / 100));
							} else {
								$feesAmount = ($totalAmount - ($totalAmount * 8.9 / 100));
							}
						} else {
							$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)->where('deleted_by', 0)->first();
							$feesAmount = ($totalAmount - ($totalAmount * $tourBookingTransaction->stripe_fees_percentage / 100));
						}

						$feesAmount = $totalAmount - $feesAmount;
						$feesAmount = roundout($feesAmount, 2);
					} else {
						$totalAmount = !empty($request->split_value) ? $request->split_value : $subtt;
						$feesAmount = 0;
					}

					$paymentType = (!empty($request->split_value) || $splitPendingAmount > 0) ? 1 : 0;
					$transactionId = TransactionService::storePaymentInTransaction($tourBooking->id, $totalAmount, $feesAmount, $paymentMethod, $paymentType, null, null);

					$data = [
						'tourists' => $totalTourists,
						'amount' => $totalAmount,
						'payment_type' => $paymentMethod,
						'payment_method' => (!empty($request->split_value) || $splitPendingAmount > 0) ? 'Split Payment' : 'Full Payment',
						'paymenttotal' => $paymentTotal,
						'refundtotal' => $refundTotal,
					];

					$historyId = HistoryService::storeCashPaymentInHistory($tourBooking->id, $totalTourists, $data, $changeAmount, $created_by_with_name);

					// Below line is for to update tour booking history table
					$isSplitOrFull = (!empty($request->split_value) || $splitPendingAmount > 0) ? 'Split Payment' : 'Full Payment';
					if ($paymentMethod == 'Cash') {
						if ($isCashFees == 1) {
							$tourBookingData = [
								'booking_id' => $request->bookingID,
								'customer_id' => $tourBooking->customer_id,
								'is_pax_added' => true,
							];
							CardBookingService::run($tourBookingData, $transactionId, $historyId, $isSplitOrFull, $request->all() + ['is_pax_added' => true]);
						} else {
							CashBookingService::run($tourBooking, $transactionId, $historyId, $isSplitOrFull, $request->all() + ['is_pax_added' => true]);
						}
					} elseif ($paymentMethod === 'Invoiced') {
						InvoicedBookingService::run($tourBooking, $transactionId, $historyId, $isSplitOrFull, $request->all() + ['is_pax_added' => true]);
					}

					$changePeopleData = [
						'tourists' => $totalTourists,
						'discount_percentage' => $request->discount_percentage ? $request->discount_percentage : 0,
						'discount2_value' => $discountAmt,
						'addedpeoplesetexts' => $addedPeoplesTexts,
						'deletedpeoplestexts' => $deletedPeoplesTexts,
						'addedtourists' => $addedPeopleCount,
						'deletedtourits' => $deletedPeopleCount,
						'paymenttotal' => $paymentTotal,
						'refundtotal' => $refundTotal
					];

					HistoryService::changeTouristInHistory($tourBooking->id, $changePeopleData, $created_by_with_name);
					$seatsDiff = $addedPeopleCount - $deletedPeopleCount;
					TimeSlotAvailabilityService::updateSlotSummary($request->slot_time, $seatsDiff);
				}
			}
			if ($refundProcess || (!$refundProcess && ($request->payment_method == 'Cash' || $request->payment_method == 'Pay Later' || $request->payment_method == 'Invoiced'))) {
				DB::commit();
			}
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}

		$errors = [];

		$paymentIntentInfo = [
			'clientSecret' => '',
			'transactionId' => '',
		];

		try {
			$arEmailData = [
				'tour_d_t' => $info->date . ' ' . $info->time_from,
				'bookingId' => $request->bookingID,
				'bookingGroupId' => $tourBooking->group_id,
				'custName' => $tourBooking->name,
				'custEmail' => $tourBooking->email,
				'custPhone' => $tourBooking->phone_number
			];

			if ($request->payment_method == 'Cash' || $request->payment_method == 'Pay Later' || $paymentMethod == 'Cash' || $paymentMethod == 'Invoiced') {
				if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
					EmailService::sendAcknowledgementEmail($info, $arEmailData);
				}
			}
		} catch (Exception $exception) {
			Log::error($exception);
			$errors[] = 'email';
		}

		if ($request->payment_method == 'Pay Later') {
			DB::commit();

			return [
				'bookingId' => $tourBooking->id,
				'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
				'stripeId' => $paymentIntentInfo['clientSecret'],
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors' => $errors,
			];
		}
		if ($request->payment_method == 'Card') {
			$data = [
				'total' => $tt,
				'customer_id' => $tourBooking->customer_id,
				'customer_name' => $tourBooking->name,
				'customer_email' => $tourBooking->email,
				'customer_phone' => $tourBooking->phone_number,
				'tourists' => $totalTourists,
				'booking_id' => $tourBooking->id,
				'group_id' => $tourBooking->group_id,
				'tour_package' => $info->package_name,
				'date' => $info->date,
				'time_from' => $info->time_from,
				'subtotal' => $subtt,
				'serviceCommission' => $serviceCommission,
				'destination' => $info->stripe_destination_id,
				'slot_time' => $request->slot_time,
				'tour_operator_id' => $info->tour_operator_id,
				'statement_descriptor' => $info->statement_descriptor,
				'card_reader_prefix' => $info->card_reader_prefix,
				'note' => $request->tour_operator_note,
				'booked_by_staff' => $request->booked_by_staff,
				'split_value' => $request->split_value,
				'split_pending_amount' => $splitPendingAmount,
				'api' => $request->api,
				'change_people' => 1,
				'discount_percentage' => $request->discount_percentage ? $request->discount_percentage : 0,
				'discount2_value' => $discountAmt,
				'addedpeoplesetexts' => $addedPeoplesTexts,
				'deletedpeoplestexts' => $deletedPeoplesTexts,
				'addedtourists' => $addedPeopleCount,
				'deletedtourits' => $deletedPeopleCount,
				'paymenttotal' => $paymentTotal,
				'refundtotal' => $refundTotal,
				'is_pax_added' => true,
				'webhook' => !empty($request->webhook) ? 1 : 0,
				'tour_package_id' => $info->tour_package_id,
				'custom_charge' => [],
				'custom_charge_amount' => 0, // Check if custom charge is applied
				'custom_charge_fees' => 0,
				'addon_price' => 0,
				'addon_fee' => 0,
				'addon_total' => 0,
				'payment_intent_id' => ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : ''
			];
			$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
			// Save the card details
			HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $data['booking_id'], "BACKEND");

			if ($request->has('feature') && $request->feature == 'change_people') {
				$data['from'] = 'Web Manual Card';
			}
			if ($request->has('api') && $request->api == 1) {

				if ($paymentIntentId == '') {
					throw new Exception('You must provide Stripe Payment Intent Id');
				}
				$data['payment_intent_id'] = $paymentIntentId;
				$paymentIntentInfo = $this->createBookingCard($data);
			} else {
				$paymentIntentInfo = $this->createPaymentIntent($data);
			}

			if (isset($paymentIntentInfo['nextAction'])) {
				$tourBooking->stripe_paymentintent_id = $paymentIntentInfo['clientSecret'];
				$tourBooking->save();
				DB::commit();

				return [
					'bookingId' => $tourBooking->id,
					'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
					'stripeId' => $paymentIntentInfo['clientSecret'],
					'require3ds' => 1,
					'nextAction' => $paymentIntentInfo['nextAction'],
					'errors' => $errors,
					'errors_message' => !empty($errors_message) ? $errors_message : ''
				];
			}

			if ($paymentIntentInfo['clientSecret'] == '') {
				$errors[] = 'payment';
			}

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
				DB::rollBack();
			}
		}
		if ($request->payment_method == 'Card' && isset($paymentIntentInfo['clientSecret']) && $paymentIntentInfo['clientSecret'] == '' && isset($paymentIntentInfo['ex']) && !empty($paymentIntentInfo['ex'])) {
			DB::rollBack();
		} else {
			DB::commit();
		}


		return [
			'bookingId' => $tourBooking->id,
			'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
			'stripeId' => $paymentIntentInfo['clientSecret'],
			'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
			'errors' => $errors,
			'errors_message' => !empty($errors_message) ? $errors_message : '',
			'operation_at' => mysqlDT()
		];
	}

	/**
	 * Remove the people from booking
	 *
	 * @param array $data
	 */
	private function deletePeopleChangePeople($data)
	{
		DB::beginTransaction();
		try {
			if (isset($data['api']) && $data['api'] == 1) {
				if (isset($data['permit_fee'][0])) {
					$data['permit_fee'] = explode(",", $data['permit_fee'][0]);
				}

				if (isset($data['rate_group'][0])) {
					$data['rate_group'] = explode(",", $data['rate_group'][0]);
				}

				if (isset($data['tourists'][0])) {
					$data['tourists'] = explode(",", $data['tourists'][0]);
				}
			}

			$bookingID = $data['bookingID'];
			$oldBookingID = oldBookingId();
			$isCashFees = cashFees();

			$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $bookingID)->where('deleted_by', 0)->first();
			$transK = Transaction::where('status', 'Completed')->where('tour_booking_id', $bookingID)->first();
			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $data['bookingID'])
				->where('tbt.deleted_by', 0)
				->orderByDesc('tpr.age_from')
				->orderByDesc('tpr.age_to')
				->get(['tpr.rate_for', 'tbt.tour_package_rate_id', 'tbt.tourists', 'tbt.total']);

			$rateGroupWiseTourists = array();
			foreach ($bookingT as $value) {
				$rateGroupWiseTourists[$value->tour_package_rate_id] = $value->tourists;
			}

			$deletedPeopleCount = 0;
			$rateGroupWisedeletedPeople = array();
			$rateGroupWisedeletedPeopleArray = array();
			for ($a = 0, $ac = count($data['tourists']); $a < $ac; $a++) {
				$rateGroupWisedeletedPeople[$a] = 0;

				if (isset($data['rate_group'][$a]) && isset($rateGroupWiseTourists[$data['rate_group'][$a]]) && $data['tourists'][$a] < $rateGroupWiseTourists[$data['rate_group'][$a]]) {
					$deletedPeopleCount += $rateGroupWiseTourists[$data['rate_group'][$a]] - $data['tourists'][$a];
					$rateGroupWisedeletedPeople[$a] = (int)$rateGroupWiseTourists[$data['rate_group'][$a]] - $data['tourists'][$a];
					$rateGroupWisedeletedPeopleArray[$data['rate_group'][$a]] = $rateGroupWisedeletedPeople[$a];
				}
			}


			//Validations
			if ($data['total_tourist'] == $deletedPeopleCount) {
				throw new Exception("You can not delete all records. Please use cancel booking option.");
			}

			$paymentTransCountForBooking = $this->transaction::where('tour_booking_id', $data['bookingID'])->where('status', 'Completed')->whereNull('charge_type')->count();

			if (isset($data['payment_type']) && $data['payment_type'] == 'Card' && $data['refund_Type'] != "fareharbor" && $data['is_cash'] == '' && $paymentTransCountForBooking > 1 && (isset($data['checked_transactions']) && $data['checked_transactions'] == '') && $data['refund_Type'] != 'dont_refund') {
				throw new Exception("You must need to select payment transaction to delete person.");
			}

			$created_by_with_name = BookingService::createdUserInfo();

			$refundedpermitarray = array();
			$checkarrays = array();
			$checkinstatusarray = array();
			$redeemTicketArray = array();
			$refundedpermitfeearray = array();

			foreach ($rateGroupWisedeletedPeopleArray as $key => $value) {
				$btset = $this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)->first();
				$touristsCount = $btset->tourists - $value;
				//This is for removing indexes from the end
				for ($a = $touristsCount, $ac = $btset->tourists; $a < $ac; $a++) {
					$checkarrays[$key][] = $a;
				}
				$checkinstatusarray[$key] = $btset->check_in_status;
				$redeemTicketArray[$key] = $btset->redeem_ticket;
				$refundedpermitfeearray[$key] = $btset->refund_permitfee_status;
			}

			$updatedCheckIns = array();
			$updatedRedeemTickets = array();
			$updatedRefundedPermitfees = array();

			if (!empty($checkarrays)) {
				foreach ($checkarrays as $k => $checkarray) {
					if (isset($checkinstatusarray[$k]) && $checkinstatusarray[$k] != '')
						$checkinstatusInfo = json_decode($checkinstatusarray[$k]);
					else
						$checkinstatusInfo = [];

					if (isset($redeemTicketArray[$k]) && $redeemTicketArray[$k] != '')
						$redeemTicketInfo = json_decode($redeemTicketArray[$k], TRUE);
					else
						$redeemTicketInfo = [];

					if (isset($refundedpermitfeearray[$k]) && $refundedpermitfeearray[$k] != '')
						$refundpermitfeesstatusInfo = json_decode($refundedpermitfeearray[$k]);
					else
						$refundpermitfeesstatusInfo = [];

					foreach ($checkarray as $m) {
						if (isset($checkinstatusInfo[$m]) && !empty($checkinstatusInfo[$m])) {
							unset($checkinstatusInfo[$m]);
						}

						if (!empty($redeemTicketInfo)) {
							for ($y = 0; $y < count($redeemTicketInfo); $y++) {
								if (isset($redeemTicketInfo[$y]['data'][$m]) && !empty($redeemTicketInfo[$y]['data'][$m])) {
									unset($redeemTicketInfo[$y]['data'][$m]);
								}
								if (isset($redeemTicketInfo[$y]['data'])) {
									$redeemTicketInfo[$y]['data'] = array_values($redeemTicketInfo[$y]['data']);
								}
							}
						}

						if (isset($refundpermitfeesstatusInfo[$m]) && !empty($refundpermitfeesstatusInfo[$m])) {
							if (isset($refundpermitfeesstatusInfo[$m]->status) && $refundpermitfeesstatusInfo[$m]->status == 'Y' && isset($refundpermitfeesstatusInfo[$m]->amount) && $refundpermitfeesstatusInfo[$m]->amount > 0) {
								$refundedpermitarray[$k][] = $m;
							}

							unset($refundpermitfeesstatusInfo[$m]);
						}
					}

					$updatedCheckIns[$k] = $checkinstatusInfo;
					$updatedRedeemTickets[$k] = $redeemTicketInfo;
					$updatedRefundedPermitfees[$k] = $refundpermitfeesstatusInfo;
				}
			}

			//Check validations with calculations for multiple payments using card
			if ($data['payment_type'] == 'Card' && $data['refund_Type'] != "fareharbor" && $data['is_cash'] == '' && $paymentTransCountForBooking > 1 && (isset($data['checked_transactions']) && $data['checked_transactions'] != '')) {
				$refundTrans = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);
				$paymentTrans = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount']);
				$totalrefundedamount = 0;
				foreach ($refundTrans as $val) {
					$totalrefundedamount += $val->amount;
				}

				$amountCapturedStripe = 0;
				$amountRefundedStripe = 0;
				$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($data['checked_transactions']);

				if (!empty($paymentIntent) && isset($paymentIntent->charges) && isset($paymentIntent->charges->data[0]) && !empty($paymentIntent->charges->data[0])) {
					$amountCapturedStripe = $paymentIntent->charges->data[0]->amount_captured;
					$amountRefundedStripe = ($paymentIntent->charges->data[0]->amount_refunded != '') ? $paymentIntent->charges->data[0]->amount_refunded : 0;
				}

				//Validation check if payment transactions fully refunded or not
				if ($paymentTrans->amount - $totalrefundedamount <= 0) {
					throw new Exception('Unable to refund booking. The chosen transaction has already been fully reimbursed. Please make a new selection.');
				}

				$totalamountforvalidation = 0;
				foreach ($rateGroupWisedeletedPeopleArray as $key => $value) {
					$tourBooking = $this->tourBooking::where('id', $bookingID)->where('status', 'Booked')->first();
					$customer = $this->customer::where('id', $tourBooking->customer_id)->first();

					$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $key)->where('tour_booking_id', $bookingID)->where('deleted_by', 0)->first();
					if ($customer->is_affiliate == 'Yes') {
						$service_commission_percentage = $rateCheck->nat_fees_percentage;
					} else {
						$service_commission_percentage = $rateCheck->nat_fees_percentage;
					}

					if ($rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
						$touristrefundedpermitfeesalready = count($refundedpermitarray[$key]);
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

						if ($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) {
							$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
						} else {
							$per = $rate * $rateCheck->additional_tax_percentage / 100;
						}

						$subtotal = ($rate + $per);
						if (($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) && $rateCheck->processing_charge_percentage > 0) {
							$subtotal = number_format((float)(($subtotal * $value) - (($touristrefundedpermitfeesalready * $rateCheck->permit_fee) + ($touristrefundedpermitfeesalready * (($rateCheck->permit_fee * $rateCheck->processing_charge_percentage) / 100)))), 2, '.', '');
						} else {
							$subtotal = number_format((float)(($subtotal * $value) - ($touristrefundedpermitfeesalready * $rateCheck->permit_fee)), 2, '.', '');
						}
					} else {
						$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
						if ($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) {
							$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
						} else {
							$per = $rate * $rateCheck->additional_tax_percentage / 100;
						}

						$subtotal = ($rate + $per);
						$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
					}

					$totalamountforvalidation += $subtotal;
				}

				if ($data['refund_Type'] == "partial_refund") {
					$feeAmount = 0;
					if ($data['payment_type'] === 'Card') {
						$feeAmount = ($totalamountforvalidation * $service_commission_percentage) / 100;
					}
					$netAmountForValidationPartialRefund = round($totalamountforvalidation + $feeAmount, 2);
					if ($data['partial_Refund_value'] > $netAmountForValidationPartialRefund) {
						throw new Exception('Unable to refund booking. Please enter amount less than total.');
					}
				}

				if ($data['refund_Type'] != "dont_refund" && $amountCapturedStripe > 0) {
					$remainingAmountStripe = $amountCapturedStripe / 100 - $amountRefundedStripe / 100;
					if ($totalamountforvalidation > $remainingAmountStripe) {
						throw new Exception('Unable to refund booking. For the chosen transaction, the refund amount is greater than the unrefunded amount. Please choose another transaction.');
					}
				}

				//Validation check for 100% refund when deleting persons
				if ($data['refund_Type']  == "100_refund" && ($totalamountforvalidation > 0)) {
					$set_fee_amount = 0;
					if ($bookingID < $oldBookingID) {
						if ($customer->is_affiliate === "No") {
							$set_fee_amount = ($totalamountforvalidation * 6) / 100;
						}
					} else {
						$set_fee_amount = ($totalamountforvalidation * $service_commission_percentage) / 100;
					}

					$Refund100 = $totalamountforvalidation + $set_fee_amount;
					$Refund100 = round($Refund100, 2);
					$rounded = round($paymentTrans->amount - $totalrefundedamount - $Refund100, 2);
					if ($rounded < -0.05) {
						throw new Exception('Unable to refund booking. Please choose a valid transaction or person to refund.');
					}
				}

				//Validation check for full refund minus fees when deleting persons
				if ($data['refund_Type'] == "full_refund" && ($totalamountforvalidation > 0)) {
					$Refund100MinusFees = round($totalamountforvalidation, 2);
					if (($paymentTrans->amount - $totalrefundedamount - $Refund100MinusFees) < -0.05) {
						throw new Exception('Unable to refund booking. Please choose a valid transaction or person to refund.');
					}
				}
			}

			//Updating checkins json
			if (!empty($updatedCheckIns)) {
				foreach ($updatedCheckIns as $key => $value) {
					if (!empty($value)) {
						$this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
							->update([
								'check_in_status' => (!empty($value)) ? json_encode((array_values($value))) : ''
							]);
					}
				}
			}

			//Updating redeem ticket json
			if (!empty($updatedRedeemTickets)) {
				foreach ($updatedRedeemTickets as $key => $value) {
					if (!empty($value)) {
						$this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
							->update([
								'redeem_ticket' => (!empty($value)) ? json_encode((array_values($value))) : ''
							]);
					}
				}
			}

			//Updating refunded permit fee json
			if (!empty($updatedRefundedPermitfees)) {
				foreach ($updatedRefundedPermitfees as $key => $value) {
					if (!empty($value)) {
						$this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)
							->update([
								'refund_permitfee_status' => (!empty($value)) ? json_encode(array_values($value)) : '',
							]);
					}
				}
			}

			$total = 0;
			foreach ($rateGroupWisedeletedPeopleArray as $keyl => $value) {
				$transaction = $this->transaction::where('tour_booking_id', $bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
				$tourBooking = $this->tourBooking::where('id', $bookingID)->where('status', 'Booked')->first();
				$customer = $this->customer::where('id', $tourBooking->customer_id)->first();
				$rateCheckname = $this->tourPackageRate::where('id', $keyl)->withTrashed()->first();
				$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $keyl)->where('tour_booking_id', $bookingID)->where('deleted_by', 0)->first();

				if ($customer->is_affiliate == 'Yes') {
					$service_commission_percentage = $rateCheck->nat_fees_percentage;
				} else {
					$service_commission_percentage = $rateCheck->nat_fees_percentage;
				}

				if ($rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$keyl])) {
					$touristrefundedpermitfeesalready = count($refundedpermitarray[$keyl]);
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

					if ($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) {
						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					} else {
						$per = $rate * $rateCheck->additional_tax_percentage / 100;
					}

					$subtotal = ($rate + $per);
					if (($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) && $rateCheck->processing_charge_percentage > 0) {
						$subtotal = number_format((float)(($subtotal * $value) - (($touristrefundedpermitfeesalready * $rateCheck->permit_fee) + ($touristrefundedpermitfeesalready * (($rateCheck->permit_fee * $rateCheck->processing_charge_percentage) / 100)))), 2, '.', '');
					} else {
						$subtotal = number_format((float)(($subtotal * $value) - ($touristrefundedpermitfeesalready * $rateCheck->permit_fee)), 2, '.', '');
					}
				} else {
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
					if ($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) {
						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					} else {
						$per = $rate * $rateCheck->additional_tax_percentage / 100;
					}

					$subtotal = ($rate + $per);
					$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
				}

				$total += $subtotal;
			}

			//Validationds for delete people with split payments for unpaid paxes
			$btvtotal = 0;
			$btv = $this->tourBooking::where('id', $bookingID)->first();
			$btvtotal = $btv->total;
			$btvtotal =  (float)$btvtotal;
			$total =  (float)$total;
			$nan = 0;

			$tsm = $this->transaction::where('tour_booking_id', $bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();

			if (!empty($tsm) && $tsm->payment_type == 1 && $data['refund_Type'] == "dont_refund") {
				$paymentOnlyCheck = 0;
				$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $bookingID)->where('operation', 'Payment')->get();

				foreach ($paymentOnly as $value) {
					$details = json_decode($value->details);
					$paymentOnlyCheck += $details->amount;
				}

				$pendingAmount = $btv->total - $paymentOnlyCheck;
				$pendingAmount = round($pendingAmount);
				$deleteRequestAmount = 0;

				$deleteRequestAmount = $this->splitPaymentCheckBeforeDelete($rateGroupWisedeletedPeopleArray, $bookingID, $data['payment_type']);

				$deleteRequestAmount = round($deleteRequestAmount);
				if ($deleteRequestAmount > $pendingAmount) {
					throw new Exception('Unable to delete booking. Please delete unpaid pax first.');
				}
			}

			if ($data['refund_Type'] == "partial_refund") {
				if ($data['partial_Refund_value'] == "NaN") {
					$nan = 0;
				} else {
					$nan = $data['partial_Refund_value'];
				}
				$feeAmount = 0;
				if ($data['payment_type'] === 'Card') {
					$feeAmount = ($total * $service_commission_percentage) / 100;
				}
				$netAmountForValidationPartialRefund = round($total + $feeAmount, 2);
				if (($nan > $netAmountForValidationPartialRefund) || ($nan > $btvtotal)) {
					throw new Exception('Unable to refund booking. Please enter amount less than total.');
				}
			}

			if (($total > $btvtotal)  && ($data['partial_Refund_value'] == "NaN") && ($data['refund_Type'] != "dont_refund")) {
				throw new Exception('Unable to refund booking. Please enter amount less than total.');
			}

			$rateChecknameForPartialRefund = [];
			$tourBookingTotalForPayLater = 0;
			$totalDeletedPax = 0;
			foreach ($rateGroupWisedeletedPeopleArray as $key => $value) {
				$transaction = $this->transaction::where('tour_booking_id', $bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
				$tourBooking = $this->tourBooking::find($bookingID);
				$customer = $this->customer::find($tourBooking->customer_id);

				$rateCheckname = $this->tourPackageRate::where('id', $key)->first();

				$rateChecknameForPartialRefund[] = $rateCheckname->rate_for . ' person ' . $value;
				$totalDeletedPax += $value;
				$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $key)->where('tour_booking_id', $bookingID)->where('deleted_by', 0)->first();
				$stripe_fees_percentage = $rateCheck->stripe_fees_percentage;

				if ($customer->is_affiliate == 'Yes') {
					$service_commission_percentage = $rateCheck->nat_fees_percentage;
				} else {
					$service_commission_percentage = $rateCheck->nat_fees_percentage;
				}

				if ($rateCheck->refund_permitfee_status != '' && isset($refundedpermitarray) && !empty($refundedpermitarray) && isset($refundedpermitarray[$key])) {
					$touristrefundedpermitfeesalready = count($refundedpermitarray[$key]);
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

					if ($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) {
						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					} else {
						$per = $rate * $rateCheck->additional_tax_percentage / 100;
					}

					$subtotal = ($rate + $per);
					if (($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) && $rateCheck->processing_charge_percentage > 0) {
						$subtotal = number_format((float)(($subtotal * $value) - (($touristrefundedpermitfeesalready * $rateCheck->permit_fee) + ($touristrefundedpermitfeesalready * (($rateCheck->permit_fee * $rateCheck->processing_charge_percentage) / 100)))), 2, '.', '');
					} else {
						$subtotal = number_format((float)(($subtotal * $value) - ($touristrefundedpermitfeesalready * $rateCheck->permit_fee)), 2, '.', '');
					}
				} else {
					$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;

					if ($data['payment_type'] === 'Card' || ($isCashFees == '1' && $data['payment_type'] === 'Cash')) {
						$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
					} else {
						$per = $rate * $rateCheck->additional_tax_percentage / 100;
					}

					$subtotal = ($rate + $per);
					$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
				}

				$total = $subtotal;

				$bt = $this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)->first();

				$set_total = $bt->total - $total;
				$set_tourist = $bt->tourists - $value;

				$total_fee_amount = 0;
				if (!empty($transaction) && ($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == "Cash"))) {
					if ($bookingID < $oldBookingID) {
						if ($customer->is_affiliate === "No") {
							$total_fee_amount = ($set_total * 6) / 100;
						}
					} else {
						$total_fee_amount = ($set_total * $service_commission_percentage) / 100;
					}
				}

				$total_fee_amount = number_format((float)$total_fee_amount, 2, '.', '');

				$set_fee_amount = 0;
				if ($transaction) {
					$set_fee_amount = $tourBooking->service_commission - (float)$total_fee_amount;
					$set_fee_amount = number_format((float)$set_fee_amount, 2, '.', '');
				}

				if ($set_tourist == 0) {
					$this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)
						->update([
							'total' => $set_total,
							'tourists' => $set_tourist,
							'check_in_status' => '',
							'redeem_ticket' => null,
							'refund_permitfee_status' => ''
						]);
				} else {
					$this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)
						->update([
							'total' => $set_total,
							'tourists' => $set_tourist
						]);
				}

				if (!empty($transaction)) {
					$tourBooking_set_total = $tourBooking->total - $total;
					$tourBookingset_tourist = $tourBooking->tourists - $value;
					if ($transaction->via == "Card" && $data['refund_Type'] != "fareharbor") {
						if ($bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$set_fee_amount = ($total * 6) / 100;
							}
						} else {
							$set_fee_amount = ($total * $service_commission_percentage) / 100;
						}

						$set_fee_amount = number_format((float)$set_fee_amount, 2, '.', '');
						$set_fee_amount = (float)$set_fee_amount;
						if ($data['refund_Type'] == "dont_refund") {
							$tourBooking_set_total = $tourBooking->total - ($total + $set_fee_amount);
							$permitFeeUpdate = ($tourBooking_set_total * $stripe_fees_percentage) / 100;
							$permitFeeUpdate = number_format((float)$permitFeeUpdate, 2, '.', '');
							if ($transK->payment_type == 1) {
								$tourBooking->update([
									'booking_total' => $tourBooking_set_total,
									'service_commission' => $permitFeeUpdate,
								]);
								DB::commit();
							}
						}

						if ($data['refund_Type'] == "100_refund") {
							$tourBooking_set_total = $tourBooking->total - ($total + $set_fee_amount);
						}

						if ($data['refund_Type'] == "full_refund") {
							$tourBooking_set_total = $tourBooking->total - ($total + $set_fee_amount);
						}

						$tourBooking_set_total = number_format((float)$tourBooking_set_total, 2, '.', '');
						if ($data['refund_Type'] != "partial_refund") {
							$tourBooking->update([
								'total' => $tourBooking_set_total,
								'tourists' => $tourBookingset_tourist
							]);
							DB::commit();
						}
					} else if ($data['refund_Type'] != "partial_refund") {
						$tourBooking->update([
							'total' => $tourBooking_set_total,
							'tourists' => $tourBookingset_tourist
						]);
						DB::commit();
					}
				} else {
					$tourBooking_set_total = $tourBooking->total - $total;
					$tourBookingTotalForPayLater += $total;
					$tourBookingset_tourist = $tourBooking->tourists - $value;
					if ($data['refund_Type'] != "partial_refund") {
						$tourBooking->update([
							'total' => $tourBooking_set_total,
							'booking_total' => $tourBooking_set_total,
							'tourists' => $tourBookingset_tourist
						]);
						DB::commit();
					}
				}

				if ($data['payment_type'] == 'Card' && $data['refund_Type'] != "fareharbor" && ($data['is_cash'] == '')) {
					if ($data['refund_Type'] == "full_refund" && ($total > 0)) {
						if (isset($data['checked_transactions']) && $data['checked_transactions'] != '') {
							// The admin explicitly picked this transaction, which is not necessarily
							// $transaction — look up its own persisted payment_mode. No fallback if
							// missing: a checked_transactions value with no matching row is a genuine
							// data problem that should surface loudly.
							$checkedTransaction = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Completed')->whereNull('charge_type')->firstOrFail(['id', 'payment_mode']);
							$refundParams = [
								'payment_intent' => $data['checked_transactions'],
								'amount' => $total * 100,
								'reverse_transfer' => true,
							];
							$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $checkedTransaction->payment_mode);
						} else {
							$refundParams = [
								'payment_intent' => $transaction->transaction_number,
								'amount' => $total * 100,
								'reverse_transfer' => true,
							];
							$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
						}
						DB::commit();
					}

					if ($data['refund_Type'] == "100_refund" && ($total > 0)) {
						if ($bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$set_fee_amount = ($total * 6) / 100;
							}
						} else {
							$set_fee_amount = ($total * $service_commission_percentage) / 100;
						}
						$Refund100 = $total + $set_fee_amount;
						$Refund100 = round($Refund100, 2);
						if (isset($data['checked_transactions']) && $data['checked_transactions'] != '') {
							$refundTrans = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);
							$paymentTrans = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Completed')->whereNull('charge_type')->first(['id', 'amount', 'payment_mode']);
							$totalrefundedamount = 0;
							foreach ($refundTrans as $ky => $val) {
								$totalrefundedamount += $val->amount;
							}

							$rounded = round($paymentTrans->amount - $totalrefundedamount - $Refund100, 2);

							if ($rounded <= -0.01) {
								$refundParams = [
									'payment_intent' => $data['checked_transactions'],
									'amount' => ($Refund100 + $rounded) * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
							} else {
								$refundParams = [
									'payment_intent' => $data['checked_transactions'],
									'amount' => $Refund100 * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
							}
						} else {
							$refundParams = [
								'payment_intent' => $transaction->transaction_number,
								'amount' => $Refund100 * 100,
								'reverse_transfer' => true,
								'refund_application_fee' => true,
							];
							app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transaction->payment_mode);
						}
					}
				}
				$deletedPeople = $value;
				if ($data['refund_Type'] == "dont_refund") {
					if (($data['payment_type'] == 'Card' || ($isCashFees == '1' && $data['payment_type'] == 'Cash')) && $data['refund_Type'] != "fareharbor") {
						$fee_amount = 0;
						if ($bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$fee_amount = ($total * 6) / 100;
							}
						} else {
							$fee_amount = ($total * $service_commission_percentage) / 100;
						}
						$fee_amount = number_format((float)$fee_amount, 2, '.', '');
						$total = $total + $fee_amount;
						$refund_keep_us = $total;
					} else {
						$refund_keep_us = $total;
					}

					if (empty($transaction)) {
						$total = 0;
					}

					$fee_amount = 0;
					$refund_type = "Don't Refund";
				}

				if ($data['refund_Type'] == "partial_refund") {
					if (($data['payment_type'] == 'Card' || ($isCashFees == '1' && $data['payment_type'] == 'Cash')) && $data['refund_Type'] != "fareharbor") {
						$fee_amount = 0;
						if ($bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$fee_amount = ($total * 6) / 100;
							}
						} else {
							$fee_amount = ($total * $service_commission_percentage) / 100;
						}
						$fee_amount = number_format((float)$fee_amount, 2, '.', '');
						$total = $total + $fee_amount;
						$refund_keep_us = $total - (float)$data['partial_Refund_value'];
						$refund_keep_us = number_format((float)$refund_keep_us, 2, '.', '');
						$fee_amount = 0;
					} else {
						$refund_keep_us = $total - (float)$data['partial_Refund_value'];
						$refund_keep_us = number_format((float)$refund_keep_us, 2, '.', '');
					}

					if (empty($transaction)) {
						$total = 0;
					}

					$total = number_format((float)$data['partial_Refund_value'], 2, '.', '');
					$refund_type = "Partial Refund";
				}

				if ($data['refund_Type'] == "full_refund") {
					if (($data['payment_type'] == 'Card' || ($isCashFees == '1' && $data['payment_type'] == 'Cash')) && $data['refund_Type'] != "fareharbor") {
						$fee_amount = 0;
						if ($bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$fee_amount = ($total * 6) / 100;
							}
						} else {
							$fee_amount = ($total * $service_commission_percentage) / 100;
						}
						$fee_amount = number_format((float)$fee_amount, 2, '.', '');
						$refund_keep_us = $fee_amount;
						$fee_amount = 0;
					} else {
						$refund_keep_us = 0;
					}
					if (empty($transaction)) {
						$total = 0;
					}
					$refund_type = "Full Refund minus Fees";
				}

				if ($data['refund_Type'] == "fareharbor") {
					$refund_keep_us = $total;
					$refund_type = "Fareharbor";
				}

				if ($data['refund_Type'] == "100_refund") {
					if (($data['payment_type'] == 'Card' || ($isCashFees == '1' && $data['payment_type'] == 'Cash')) && $data['refund_Type'] != "fareharbor") {
						$fee_amount = 0;
						if ($bookingID < $oldBookingID) {
							if ($customer->is_affiliate === "No") {
								$fee_amount = ($total * 6) / 100;
							}
						} else {
							$fee_amount = ($total * $service_commission_percentage) / 100;
						}
						$fee_amount = number_format((float)$fee_amount, 2, '.', '');
						$total = $total + $fee_amount;
						$refund_keep_us = 0;
					} else {
						$refund_keep_us = 0;
					}
					if (empty($transaction)) {
						$total = 0;
					}
					$refund_type = "100 % Refund";
				}

				if (isset($data['is_cash']) && $data['is_cash'] != '') {
					if ($data['is_cash'] == 'Cash') {
						$refundVia = 'Cash';
					} elseif ($data['is_cash'] == 'Wire Transfer') {
						$refundVia = 'Check/Wire';
					}
				} else {
					$refundVia = $data['payment_type'];
				}

				$bookingInfo = $this->tourBooking::find($bookingID);
				if ($data['refund_Type'] != "partial_refund") {
					$historyId = HistoryService::storeRefundDataInHistory(
						$bookingID,
						$bookingInfo->tourists,
						$created_by_with_name,
						null, /* $comment */
						$refund_type,
						$transaction,
						$total,
						null, /* $cardNumber */
						[], /* $cardsArray */
						[], /* $partialAmountArray */
						$refundVia,
						null, /* $paymentTotal */
						null, /* $refundTotal */
						$refund_keep_us,
						$rateCheckname->rate_for . ' person ' . $value /* $deleted */
					);
				}

				$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $bookingID)->where('operation', 'Payment')->get();
				$paymentOnlyCheck = 0;
				foreach ($paymentOnly as $value) {
					$details = json_decode($value->details);
					$paymentOnlyCheck += $details->amount;
				}

				$bok = round($bookingInfo->booking_total);
				$pay = round($paymentOnlyCheck);
				if (!empty($transaction) && $data['payment_type'] == 'Card' && $data['refund_Type'] != "fareharbor" && $data['refund_Type'] != "partial_refund") {
					if (isset($data['is_cash']) && $data['is_cash'] != '') {
						if ($data['is_cash'] == 'Cash') {
							$refundVia = 'Cash';
						} else if ($data['is_cash'] == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					} else {
						$refundVia = $data['payment_type'];
					}
					$TS = $this->transaction::where('tour_booking_id', $bookingID)->where('status', 'Completed')->whereNull('charge_type')->orderBy('id', 'DESC')->first();
					if ($TS->payment_type == 0) {
						if (isset($data['checked_transactions']) && $data['checked_transactions'] != '') {
							$TS1 = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Completed')->whereNull('charge_type')->first();
							if ($data['refund_Type'] == "dont_refund") {
								$refundAmount = 0.00;
							} else {
								$refundAmount = $total;
							}
							if ($data['refund_Type'] == "100_refund") {
								$feeAmount = round($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
							} else {
								$feeAmount = 0.00;
							}
							$refundTransId = TransactionService::storeRefundInTransaction($bookingID, $refundAmount, $feeAmount, $refundVia, $TS1->transaction_number, $TS1->application_fee_id, mode: $TS1->payment_mode);
						} else {
							if ($data['refund_Type'] == "dont_refund") {
								$refundAmount = 0.00;
							} else {
								$refundAmount = $total;
							}
							if ($data['refund_Type'] == "100_refund") {
								$feeAmount =  round($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
							} else {
								$feeAmount = 0.00;
							}
							$refundTransId = TransactionService::storeRefundInTransaction($bookingID, $refundAmount, $feeAmount, $refundVia, $TS->transaction_number, $TS->application_fee_id, mode: $TS->payment_mode);
						}
					}

					if ($pay >= $bok) {
						$TS->payment_type = 0;
						$TS->save();
					}
				} elseif (!empty($transaction) && $data['payment_type'] == 'Cash' && $data['refund_Type'] != "partial_refund") {
					if (isset($data['is_cash']) && $data['is_cash'] != '') {
						if ($data['is_cash'] == 'Cash') {
							$refundVia = 'Cash';
						} elseif ($data['is_cash'] == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					} else {
						$refundVia = $data['payment_type'];
					}

					if ($data['refund_Type'] == "dont_refund") {
						$refundAmount = 0.00;
					} else {
						$refundAmount = $total;
					}
					if ($isCashFees == 1) {
						if ($data['refund_Type'] == "100_refund") {
							$feeAmount = round($total * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
						} else {
							$feeAmount = 0.00;
						}
					} else {
						$feeAmount = 0.00;
					}
					$refundTransId = TransactionService::storeRefundInTransaction($bookingID, $refundAmount, $feeAmount, $refundVia, null, null);
					DB::commit();
				} elseif (!empty($transaction) && $data['refund_Type'] == "fareharbor") {
					if (isset($data['is_cash']) && $data['is_cash'] != '') {
						if ($data['is_cash'] == 'Cash') {
							$refundVia = 'Cash';
						} elseif ($data['is_cash'] == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					} else {
						$refundVia = $data['payment_type'];
					}
					if ($data['refund_Type'] == "dont_refund") {
						$refundAmount = 0.00;
					} else {
						$refundAmount = $total;
					}
					$refundTransId = TransactionService::storeRefundInTransaction($bookingID, $refundAmount, 0, $refundVia, null, null, mode: $transaction->payment_mode);
					DB::commit();
				} elseif (!empty($transaction) && $data['refund_Type'] != "partial_refund") {
					// Handle third-party platforms (TripAdvisor, GetYourGuide, Headout, etc.)
					if (isset($data['is_cash']) && $data['is_cash'] != '') {
						if ($data['is_cash'] == 'Cash') {
							$refundVia = 'Cash';
						} elseif ($data['is_cash'] == 'Wire Transfer') {
							$refundVia = 'Check/Wire';
						}
					} else {
						$refundVia = $data['payment_type'];
					}
					if ($data['refund_Type'] == "dont_refund") {
						$refundAmount = 0.00;
					} else {
						$refundAmount = $total;
					}
					$refundTransId = TransactionService::storeRefundInTransaction($bookingID, $refundAmount, 0, $refundVia, null, null, mode: $transaction->payment_mode);
					DB::commit();
				}

				// Below line is for to update tour booking history table
				if ($data['refund_Type'] != "partial_refund") {
					$this->handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $data, $refundVia, $refundTransId ?? null);
				}
			}

			/* Remove contacts if booking package has contacts feature enabled */
			$getBooking = $this->tourBooking::findOrFail($bookingID);
			$packageHasContacts = $this->tourPackage::find($getBooking->tour_package_id);

			if ($packageHasContacts->has_contacts == 1) {
				if (isset($data['contacts'])) {
					try {
						PackageHasContactsService::deleteContacts($data['contacts']);
					} catch (Exception $ex) {
						Log::error($ex->getMessage());
					}
				}
			}

			// Partial Refund edit booking
			if ($data['refund_Type'] == "partial_refund") {
				$totalc = $this->tourBooking::where('id', $bookingID)->first();
				DB::beginTransaction();
				if (isset($data['checked_transactions']) && $data['checked_transactions'] != '') {
					$transac = $this->transaction::where('tour_booking_id', $bookingID)->where('transaction_number', $data['checked_transactions'])->where('status', 'Completed')->whereNull('charge_type')->first();
				} else {
					$transac = $this->transaction::where('tour_booking_id', $bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();
				}

				$transactionFirst = $this->transaction::where('tour_booking_id', $bookingID)->whereNull('charge_type')->first();
				if ($refundVia == "Card") {
					if ($data['partial_Refund_value'] > 0) {
						$refundParams = [
							'payment_intent' => $transac->transaction_number,
							'amount' => $data['partial_Refund_value'] * 100,
							'reverse_transfer' => true,
							'refund_application_fee' => true,
						];
						$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transac->payment_mode);
					}
				}

				$btp = $this->bookingTransact::where('tour_booking_id', $bookingID)->where('deleted_by', 0)->get();
				$tcount = 0;
				foreach ($btp as $value) {
					$tcount += $value->tourists;
				}

				if (!empty($transactionFirst)) {
					$ptotal = $totalc->total - $data['partial_Refund_value'];
					$tourBooking->update([
						'total' => $ptotal,
						'tourists' => $tcount,
					]);
				} else {
					$tourBooking->update([
						'total' => $totalc->total - $tourBookingTotalForPayLater,
						'booking_total' => $totalc->booking_total - $tourBookingTotalForPayLater,
						'tourists' => $tcount
					]);
				}
				DB::commit();

				if (!empty($transactionFirst)) {
					$partialValue = $data['partial_Refund_value'];
				} else {
					$partialValue = 0;
					$refund_keep_us = 0;
				}

				$historyId = HistoryService::storeRefundDataInHistory(
					$bookingID,
					$tcount,
					$created_by_with_name,
					null, /* $comment */
					$refund_type,
					null, /* $transaction */
					$partialValue,
					null, /* $cardNumber */
					[], /* $cardsArray */
					[], /* $partialAmountArray */
					$refundVia,
					null, /* $paymentTotal */
					null, /* $refundTotal */
					$refund_keep_us,
					$rateChecknameForPartialRefund /* $deleted */
				);

				if (!empty($transactionFirst)) {
					$refundTransId = TransactionService::storeRefundInTransaction(
						$bookingID,
						$data['partial_Refund_value'],
						0,
						$refundVia,
						($refundVia == "Card") ? $transac->transaction_number : null,
						'',
						mode: ($refundVia == "Card") ? $transac->payment_mode : PaymentModeResolver::DESTINATION
					);
					$this->handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $data, $refundVia, $refundTransId ?? null);
				}
				DB::commit();
			}
			// Update slot summary
			$seatsDiff = - ($totalDeletedPax);
			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $seatsDiff);
			DB::commit();
			$info = DB::table('tour_slot_times AS tst')
				->join('tour_bookings_master as bm', 'bm.tour_slot_id', '=', 'tst.id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('bm.id', $bookingID)
				->select([
					'tst.time_from',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage',
					't_o.tenant_id',
					'bm.name As custname',
					'bm.email As custemail',
					'bm.phone_number As phone_number',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					DB::raw("(SELECT SUM(tbm.tourists)
                            FROM tour_bookings_master AS tbm
                            WHERE tbm.tour_slot_id = tst.id
                                AND tbm.status != 'Cancelled'
                            GROUP BY tbm.tour_slot_id) AS booked_seats")
				])
				->first();


			$deletedPeople = $this->tourBookingHistory::where('tour_booking_id', $bookingID)->where('operation', 'Refund')->get();

			$arEmailData = [
				'tour_d_t' => $info->date . ' ' . $info->time_from,
				'bookingId' => $bookingID,
				'custName' => $info->custname,
				'custEmail' => $info->custemail,
				'custPhone' => $info->phone_number ?? '',
				'deletedPeople' => $deletedPeople
			];

			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendPeopleRemovedEmail($info, $arEmailData);
			}
			return [
				'booking_id' => $bookingID,
				'success' => true,
				'message' => 'Booking has been updated' . ($data['refund_Type'] != "dont_refund" ? ' and refund has been initiated.' : '.')
			];
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Switch package in the existing booking
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function switchPackageBooking($request)
	{
		DB::beginTransaction();
		try {
			$trans = $this->transaction::where('tour_booking_id', $request->bookingID)
				->where('status', 'Completed')
				->orderBy('id', 'DESC')
				->first();


			$paymentMethod = $trans ? $trans->via : '';
			$paymentIntentId = ($request->is('api/*')) ? $request->paymentIntentId : $request->payment_intent_id;

			// Admin selected "Pay with Link" in the Make Payment modal: send a payment link
			// for the remaining balance instead of collecting payment immediately.
			if ($request->payment_method === 'Payment Link') {
				$switchTourPackage = SwitchTourPackagePaymentLinkService::run($request);
			} else {
				switch ($paymentMethod) {
					case 'Cash':
						$switchTourPackage = SwitchTourPackageCashService::run($request);
						break;
					case 'Invoiced':
						$switchTourPackage = SwitchTourPackageInvoicedService::run($request);
						break;
					case 'Card':
						$switchTourPackage = SwitchTourPackageCardService::run($request);
						break;
					default:
						$switchTourPackage = SwitchTourPackagePayLaterService::run($request);
						break;
				}
			}

			// Save the card details
			HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $request->bookingID, "BACKEND");

			return $switchTourPackage;
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public static function saveCardPermitFeeJson($updatesTransact, $tbTransactexist, $tbltrans, $tourPackageRateGroup)
	{
		if ($tbTransactexist->refund_permitfee_status != '') {
			$statusInfo = json_decode($tbTransactexist->refund_permitfee_status);
			for ($b = $tbTransactexist->tourists; $b < ($tbTransactexist->tourists + $tbltrans['tourists']); $b++) {
				if ($tbltrans['permit_fee']) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
					$statusInfo[$b]->is_pax_added = true;
				} elseif ($tbltrans['permit_fee'] == 0 && $tourPackageRateGroup->permit_fee == 0) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = '0.00';
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				} else {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = 8 * $tbltrans['processing_charge_percentage'] / 100;
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
			}

			$updatesTransact['refund_permitfee_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';

			return $updatesTransact;
		} elseif ($tbTransactexist->permit_fee == 0) {
			for ($b = 0; $b < ($tbltrans['tourists'] + $tbTransactexist->tourists); $b++) {
				$overrideStatusInfo = 0;
				if (!isset($statusInfo[$b])) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
					$statusInfo[$b]->is_pax_added = true;
					$overrideStatusInfo = 1;
				}
				if ($overrideStatusInfo && $tbltrans['permit_fee'] == 0 && $tbTransactexist->tourists > $b) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = $tbTransactexist->tourists > $b ? 0 : (8 * $tbltrans['processing_charge_percentage'] / 100);
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
				if ($overrideStatusInfo && $tbltrans['permit_fee'] != 0 && $tbTransactexist->tourists > $b) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = 8 * $tbltrans['processing_charge_percentage'] / 100;
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
			}
			if ($tbTransactexist->rate == 0) {
				$updatesTransact['refund_permitfee_status'] = '';
			} else {
				$updatesTransact['refund_permitfee_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';
			}


			return $updatesTransact;
		} elseif ($tbTransactexist->permit_fee != 0) {
			for ($b = 0; $b < ($tbltrans['tourists'] + $tbTransactexist->tourists); $b++) {
				$overrideStatusInfo = 0;
				if (!isset($statusInfo[$b])) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
					$statusInfo[$b]->is_pax_added = true;
					$overrideStatusInfo = 1;
				}
				if ($overrideStatusInfo && $tbltrans['permit_fee'] == 0 && $tbTransactexist->tourists <= $b) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = 8 * $tbltrans['processing_charge_percentage'] / 100;
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
			}

			$updatesTransact['refund_permitfee_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';

			return $updatesTransact;
		}
	}

	public static function saveNonCardPermitFeeJson($updatesTransact, $tbTransactexist, $tbltrans)
	{
		if ($tbTransactexist->refund_permitfee_status != '') {
			$statusInfo = json_decode($tbTransactexist->refund_permitfee_status);
			for ($b = 0; $b < ($tbltrans['tourists'] + $tbTransactexist->tourists); $b++) {
				$overrideStatusInfo = 0;
				if (!isset($statusInfo[$b])) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
					$overrideStatusInfo = 1;
				}
				if ($overrideStatusInfo && $tbltrans['permit_fee'] == 0) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = '0.00';
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
			}

			$updatesTransact['refund_permitfee_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';

			return $updatesTransact;
		} elseif ($tbTransactexist->permit_fee == 0) {
			for ($b = 0; $b < ($tbltrans['tourists'] + $tbTransactexist->tourists); $b++) {
				$overrideStatusInfo = 0;
				if (!isset($statusInfo[$b])) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
					$overrideStatusInfo = 1;
				}
				if ($overrideStatusInfo && ($tbltrans['permit_fee'] == 0 || $tbTransactexist->tourists > $b)) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = '0.00';
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
			}
			if ($tbTransactexist->rate == 0) {
				$updatesTransact['refund_permitfee_status'] = '';
			} else {
				$updatesTransact['refund_permitfee_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';
			}

			return $updatesTransact;
		} elseif ($tbTransactexist->permit_fee != 0) {
			for ($b = 0; $b < ($tbltrans['tourists'] + $tbTransactexist->tourists); $b++) {
				$overrideStatusInfo = 0;
				if (!isset($statusInfo[$b])) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
					$overrideStatusInfo = 1;
				}
				if ($overrideStatusInfo && $tbltrans['permit_fee'] == 0 && $tbTransactexist->tourists <= $b) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'Y';
					$statusInfo[$b]->amount = '8.00';
					$statusInfo[$b]->processing_fees = '0.00';
					$statusInfo[$b]->is_pax_added = true;
					$statusInfo[$b]->dateTime = mysqlDT();
				}
			}

			$updatesTransact['refund_permitfee_status'] = (!empty($statusInfo)) ? json_encode((array_values($statusInfo))) : '';

			return $updatesTransact;
		}
	}

	/**
	 * Handle the queue jobs to perform the history data updates when delete person from edit booking section
	 *
	 * @param  object  $tourBooking
	 * @param  int     $historyId
	 * @param  array   $request
	 * @param  string  $refundVia
	 * @param  int     $transactionId
	 *
	 * @return void
	 * 
	 * @version 1.0.0
	 */
	public function handleDeletePersonHistoryUpdateJobs($tourBooking, $historyId, $request, $refundVia, $transactionId): void
	{
		// Perform the jobs based on categories
		if ($request['refund_Type'] == "dont_refund") {
			DeletePersonDontRefundService::run($historyId, $request, $refundVia, $transactionId);
		} elseif ($request['refund_Type'] == "partial_refund" || $request['refund_Type'] == "fareharbor") {
			DeletePersonPartialRefundService::run($historyId, $request, $refundVia, $transactionId);
		} elseif ($request['refund_Type'] == "full_refund") {
			DeletePersonFullRefundService::run($historyId, $request, $refundVia, $transactionId);
		} elseif ($request['refund_Type'] == "100_refund") {
			DeletePerson100RefundService::run($tourBooking, $historyId, $request, $refundVia, $transactionId);
		}
	}

	/**
	 * Get the group of people for new booking
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function getGroupOfPeopleNewBook(object $request)
	{
		try {
			$fields = [
				'tst.id',
				'tst.tour_slot_id',
				'tst.date',
				'tst.time_from',
				'tst.time_to',
				'tst.time_slot_seats as seats',
				'tst.bookable_status',
				DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                GROUP BY tbm.tour_slot_id), 0) AS tourists"),
				DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                FROM tour_bookings_master AS tbm
                JOIN customers AS c ON c.id = tbm.customer_id
                WHERE tbm.tour_slot_id = tst.id
                        AND c.is_affiliate = 'Yes'
                        AND tbm.status != 'Cancelled'
                GROUP BY tbm.tour_slot_id), 0) AS afl_tourists"),
			];

			$slotTimes = DB::table('tour_slot_times AS tst')
				->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
				->where('tst.date', $request->date) // separator is not necessary in date
				->where('tst.tour_package_id', $request->tour_package_id)
				->where('tst.deleted_by', 0)
				->orderBy('tst.time_from');

			$response = $slotTimes->get($fields)->toArray();

			if (!empty($response)) {
				$finalslotTimes = array();
				$touristsInfoData = array();
				foreach ($response as $key => $responseVal) {
					$touristsInfoData = DB::table('tour_package_rates AS tpr')
						->leftJoin('tour_bookings_transact AS tbt', 'tbt.tour_package_rate_id', '=', 'tpr.id')
						->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
						->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
						->where('tbm.tour_slot_id', $responseVal->id)
						->whereIn('tbm.status', ['Booked', 'Completed'])
						->where('tbt.deleted_by', 0)
						->groupBy('tbt.tour_package_rate_id')
						->orderByDesc('tpr.age_from')
						->select(['tpr.rate_for', DB::raw('SUM(tbt.tourists) AS tourists')])
						->get();

					if ($responseVal->tourists > $responseVal->seats) {
						$answer = abs($responseVal->tourists - $responseVal->seats);
					} else {
						$answer = 0;
					}

					$finalslotTimes[$key]['id']              = $responseVal->id;
					$finalslotTimes[$key]['tour_slot_id']    = $responseVal->tour_slot_id;
					$finalslotTimes[$key]['time_from']       = $responseVal->time_from;
					$finalslotTimes[$key]['time_to']         = $responseVal->time_to;
					$finalslotTimes[$key]['seats']           = $responseVal->seats;
					$finalslotTimes[$key]['tourists']        = $responseVal->tourists;
					$finalslotTimes[$key]['overbooked ']     = $answer;
					$finalslotTimes[$key]['bookable_status'] = $responseVal->bookable_status;
					$finalslotTimes[$key]['afl_tourists']    = $responseVal->afl_tourists;
					$finalslotTimes[$key]['touristsInfo']    = $touristsInfoData;
				}

				$isBlackoutDate = isBlackoutDateEnabled($request->date);
				$affiliateId = (!$isBlackoutDate) ? $request->affiliateId : NULL;
				/* START: Custom Date Rate Validation */
				$standardRateDate = $this->customRateDate::where('affiliate_id', $affiliateId)
					->where('tour_package_id', $request->tour_package_id)
					->where('date_range_start', '<=', $responseVal->date)
					->where('date_range_end', '>=', $responseVal->date)
					->orderBy('priority', 'DESC')
					->first();

				if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
					$customRateDate = isCustomDateRateEnabled($responseVal->date, $request->tour_package_id, $affiliateId, $responseVal->id);
				} else {
					$customRateDate = isCustomDateRateEnabled($responseVal->date, $request->tour_package_id, $affiliateId);
				}
				/* END */

				$rateGroups = DB::table('tour_package_rates AS tpr')
					->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
					->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
					->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
					->join('states AS st', 'st.id', '=', 'ct.state_id')
					->where([
						'tpr.tour_package_id' => $request->tour_package_id,
						'tpr.deleted_by' => 0
					])
					->when($affiliateId, function ($query) use ($request) {
						return $query->where('tpr.affiliate_id', $request->affiliateId);
					}, function ($query): mixed {
						return $query->whereNull('tpr.affiliate_id');
					})
					->when($customRateDate, function ($query) use ($customRateDate) {
						return $query->where('tpr.custom_date_id', $customRateDate);
					}, function ($query): mixed {
						return $query->whereNull('tpr.custom_date_id');
					})
					->orderBy('tpr.id')
					->select([
						'tpr.id',
						'tpr.rate_for',
						'tpr.rate',
						'tpr.description',
						'tpr.additional_tax_percentage',
						'tpr.processing_charge_percentage',
						'tpr.additional_charge',
						'tpr.permit_fee',
						'tp.service_commission_percentage'
					])
					->get();

				if (!empty($finalslotTimes)) {
					return ['slotTimes' => $finalslotTimes, 'rateGroups' => $rateGroups];
				} else {
					return ['slotTimes' => $slotTimes->get($fields), 'rateGroups' => $rateGroups];
				}
			}
			throw new Exception('Group of people details not available.');
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Booking agenda with/without day time slots selection
	 *
	 * @param int $date
	 * @param int $tourOperatorId
	 * @param string $tourPackagesId
	 * @param int $PaymentStatus
	 * @param string $slotId
	 * @param int $api
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function dayTimeSlots(Request $request, int $date = 0, int $tourOperatorId = 0, string $tourPackagesId = '', int $PaymentStatus = 0, $slotId = '', int $api = 0, $downloadAgenda = 0)
	{
		$ApibookingId = $request->ApibookingId;
		$bookingId = session()->get('bookingId');

		try {
			if (!empty($bookingId) || !empty($ApibookingId)) {
				$bookingId = session('bookingId', $ApibookingId);
				$PaymentStatus = session('PaymentStatus', $PaymentStatus);
				$tourOperatorId = session('tourOperatorId', $tourOperatorId);
				$tourPackagesId = session('tourPackagesId', $tourPackagesId);
				$slotId = session('slotId', $slotId);
				$date = session('date', $date);
				$api = session('api', $api);

				session()->forget([
					'bookingId',
					'PaymentStatus',
					'tourOperatorId',
					'tourPackagesId',
					'slotId',
					'date'
				]);
			}

			if ($PaymentStatus == 2) {
				$PaymentStatus = "Completed";
			} elseif ($PaymentStatus == 3) {
				$PaymentStatus = "Failed";
			}

			$userType = userType();
			$agendaView = agendaView();
			$vars = [
				'page_title' => 'Tour Booking Schedules',
				'pageId' => 'date bookings',
				'tourOperatorId' => ($userType == 'Admin') ? ($tourOperatorId ?: (Cookie::get('tourOpId') ?: $this->tourOperator::first()->id)) : Auth::user()->tour_operator_id,
				'tourPackagesId' => (int) $tourPackagesId,
				'PaymentStatus' => $PaymentStatus,
				'slotId' => $slotId,
				'date' => ($date == 0) ? date('Y-m-d') : substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2),
				'agendaView' => $agendaView,
				'agendaAmount' => hasSetting('AGENDA_AMOUNT'),
				'packageHasSlots' => 1
			];

			if ($userType == 'Admin') {
				$vars['tourOperators'] = $this->tourOperator::where('status', 'Active')
					->orderBy('name')
					->get(['id', 'name']);
			} else {
				$vars['tourOperatorId'] = (int) $vars['tourOperatorId'];
			}

			$departmentPackage = departmentPackage();

			$vars['tourPackages'] = $this->tourPackage::when($userType == 'Affiliate', function ($q) {
				return $q->whereHas('packageAffiliate', function ($q) {
					$q->where('affiliate_id', Auth::id());
				});
			})
				->when($departmentPackage, function ($q) {
					return $q->whereHas('departments', function ($q) {
						$q->where('department_id', Auth::user()->department_id);
					});
				})
				->where('status', 'Active')
				->where('show_in_agenda', Constants::STATUS_ONE)
				->orderBy('name')
				->get(['id', 'name', 'stripe_destination_id']);

			$payment = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
				->where('tsd.date', $date)
				->when($bookingId, function ($query) use ($bookingId) {
					return $query->where('tbm.id', $bookingId);
				})
				->when($userType == 'Affiliate', function ($query) {
					return $query->where('tbm.customer_id', Auth::id());
				})
				->when($departmentPackage, function ($query) use ($departmentPackage) {
					return $query->whereIn('tp.id', $departmentPackage);
				})
				->whereIn('tbm.status', ['Booked', 'Cancelled'])
				->where('tbh.operation', 'Payment')
				->where('tp.show_in_agenda', Constants::STATUS_ONE)
				->get([
					'tbm.status',
					'tbh.operation_at',
					'tbh.tour_booking_id',
					'tbh.ticket_cost',
					'tbh.processing_fees',
					'tbh.platform_fees',
					DB::raw("(SELECT SUM(case  
						when operation = 'Refund' then (`tbh`.`ticket_cost` + `tbh`.`processing_fees` + `tbh`.`platform_fees`) 
						when operation = 'Cancelled' then (`tbh`.`ticket_cost` + `tbh`.`processing_fees` + `tbh`.`platform_fees`)
						ELSE 0 END)
						FROM tour_booking_history AS tbh
					WHERE tbh.tour_booking_id = tbm.id ) AS `TotalRefund`")
				]);

			$vars['Payment'] = $payment;
			$vars['Refund_Cancel1'] = $payment->where('TotalRefund', '>', 0);

			$cancelledBookingResult = $payment->where('status', 'Cancelled')->where('TotalRefund', '>', 0);
			$vars['cancelledBookingAmount'] = (isset($cancelledBookingResult)) ? (float) $cancelledBookingResult->sum('TotalRefund') : 0;

			$affiliateSeats = '';
			$affiliateTotalTourists = '';
			$redeemTicketFlag = 0;

			$affiliateSeats = DB::raw('0 as afl_seats');
			if ($userType == 'Affiliate') {
				$affiliateSeats = DB::raw('IFNULL(ast.seat_limit,0) as afl_seats');
				$affiliateTotalTourists = 'AND tbm2.customer_id = ' . Auth::id();
			}

			$dayBookings = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->leftJoin('hotels as h', 'h.id', '=', 'tbm.hotel_id')
				->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
				->leftJoin('tour_booking_history AS tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
				->leftJoin('tour_booking_forms as tbf', 'tbm.id', '=', 'tbf.tour_booking_id')
				// ->leftJoin('tour_package_bundle_relations as tpbr', 'tpbr.tour_package_id', '=', 'tp.id')
				->where('tsd.date', $date)
				->where('tbm.status', 'Booked')
				->where('tp.show_in_agenda', Constants::STATUS_ONE)
				->when($bookingId, function ($query) use ($bookingId) {
					return $query->where('tbm.id', $bookingId);
				})
				->when($tourPackagesId && $tourPackagesId !== '0', function ($query) use ($vars) {
					return $query->where('tp.id', $vars['tourPackagesId']);
				})
				->when($departmentPackage, function ($query) use ($departmentPackage) {
					return $query->whereIn('tp.id', $departmentPackage);
				})
				->when($slotId, function ($query) use ($vars) {
					if (agendaView() == 1) {
						return $query->where('tst.id', $vars['slotId']);
					} else {
						if ($vars['slotId'] == 'No') {
							return $query->where('tbm.hotel_id', 0);
						} else {
							return $query->where('tbm.hotel_id', $vars['slotId']);
						}
					}
				})
				->when($PaymentStatus && $PaymentStatus == 'Completed', function ($query) use ($vars) {
					return $query->where('t.status', $vars['PaymentStatus']);
				})
				->when($PaymentStatus && $PaymentStatus == 'Failed', function ($query) use ($date, $bookingId, $userType) {
					return $query->whereNotIn('tbm.id', static function ($q) use ($date, $bookingId, $userType) {
						return $q->select('tour_booking_id')->from('transactions')
							->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'transactions.tour_booking_id')
							->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
							->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
							->where('tsd.date', $date)
							->when($bookingId, function ($query) use ($bookingId) {
								return $query->where('tbm.id', $bookingId);
							})
							->when($userType == 'Affiliate', function ($query) {
								return $query->leftJoin('tour_slot_times_affiliate as tsta', function ($query) {
									return $query->on('tsta.time_from', '=', 'tst.time_from')
										->on('tsta.time_to', '=', 'tst.time_to')
										->on('tsta.tour_package_id', '=', 'tp.id');
								})
									->leftJoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
									->where('tbm.customer_id', Auth::id());
							})
							->groupBy('tour_booking_id')
							->having(DB::raw('count(`transactions`.`id`)'), '>', 0);
					});
				})
				->when($userType == 'Affiliate', function ($query) {
					return $query->leftJoin('tour_slot_times_affiliate as tsta', function ($query) {
						return $query->on('tsta.time_from', '=', 'tst.time_from')
							->on('tsta.time_to', '=', 'tst.time_to')
							->on('tsta.tour_package_id', '=', 'tp.id');
					})
						->leftJoin('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
						->where('tbm.customer_id', Auth::id());
				})
				->groupBy('tbm.id')
				->orderBy(agendaView() == 1 ? 'tst.time_from' : 'tbm.hotel_id')
				->orderBy('tp.name')
				->orderBy('c.name', 'asc')
				->get([
					'tbm.id AS booking_id',
					'tbm.cart_id',
					'tbm.viator_bookingRef AS tripadvisor_id',
					'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
					'tbm.ota_reference_booking AS ota_booking_id',
					'tbm.tour_slot_id',
					'tbm.hotel_id',
					'tbm.tourists',
					'tbm.comments',
					'tbm.note',
					'tbm.total',
					'tbm.liability_waiver_signing',
					'tbm.created_by_user_type',
					'tbm.is_reserved_for_affiliate',
					'tbm.reference_booking',
					'tbm.is_group_booking',
					'tst.date',
					'tst.time_slot_seats as seats',
					'tst.time_from',
					'tst.time_to',
					'tp.id AS tour_package_id',
					'tp.name AS tour_package_name',
					'tp.stripe_destination_id',
					'tp.package_has_slots',
					'tp.package_has_waiver',
					'tp.package_has_permit',
					'tp.bundle_package',
					'tp.checkin as is_check_in',
					'tp.checkin_type',
					'tp.hours',
					'tp.has_contacts',
					'tbm.name AS customer_name',
					'tbm.email',
					'tbm.phone_number',
					'c.is_affiliate',
					'tbm.booking_total',
					'h.name AS hotel_name',
					'h.address AS hotel_address',
					'tbf.form_submission',
					DB::raw(
						"IFNULL((SELECT SUM(amount)
						FROM transactions AS t
						WHERE t.tour_booking_id = tbm.id
						AND t.status = 'Completed'
						GROUP BY t.tour_booking_id), 0) AS paids"
					),
					DB::raw(
						'IFNULL((SELECT SUM(
							CASE 
						   	WHEN (operation = "Refund" 
							AND JSON_UNQUOTE(JSON_EXTRACT(`tbh`.`details`, \'$.payment_type\')) != "")  
							THEN (`tbh`.`ticket_cost` + `tbh`.`processing_fees` + `tbh`.`platform_fees`)
							WHEN operation = "Cancelled" 
							THEN (`tbh`.`ticket_cost` + `tbh`.`processing_fees` + `tbh`.`platform_fees`) 
							END)
						FROM tour_booking_history AS tbh
						WHERE tbh.tour_booking_id = tbm.id
						AND
						(
							`tbh`.`operation` = "Refund"
							or `tbh`.`operation` = "Cancelled"
						)
						AND `tbh`.`ticket_cost` > 0
						GROUP BY tbh.tour_booking_id), 0) AS refund'
					),
					DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id),0) AS booked_seats"),
					DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
                        FROM tour_bookings_master AS tbm2
                        JOIN customers AS c ON c.id = tbm2.customer_id
                        WHERE tbm2.tour_slot_id = tst.id
                            AND c.is_affiliate = 'Yes'
                            AND tbm2.status != 'Cancelled'
                            " . $affiliateTotalTourists . "
                        GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_tourists"),
					DB::raw("(CASE WHEN tbm.viator_bookingRef IS NOT NULL THEN 1 ELSE 0 END) AS viator_bookingRef"),
					DB::raw(
						"IFNULL((SELECT GROUP_CONCAT(
							CONCAT(tpbr.tour_package_id, ':', tpbr.package_name)
							ORDER BY tp.name ASC
							SEPARATOR '|'
						)
						FROM tour_package_bundle_relations AS tpbr
						WHERE tbm.tour_package_id = tpbr.parent_id
						AND tpbr.deleted_by = 0), CONCAT(tp.id, ':', tp.name)) as packages"
					),
					$affiliateSeats
				]);

			$vars['dayBookings'] = $dayBookings;

			$vars['packageHasContacts'] = 0;

			if ($tourPackagesId && $tourPackagesId !== '0') {
				$packageHasContacts = $this->tourPackage::select('has_contacts')->where('id', $tourPackagesId)->firstOrFail();
				$vars['packageHasContacts'] = $packageHasContacts->has_contacts;
			}

			$vars['dayBookingsInfo'] = [];

			for ($a = 0, $ac = count($vars['dayBookings']); $a < $ac; $a++) {
				$counter = 0;
				if ($vars['dayBookings'][$a]->liability_waiver_signing != "") {
					$convertArray = json_decode($vars['dayBookings'][$a]->liability_waiver_signing, true);
					$i = 0;
					foreach ($convertArray as $convertArrayValues) {
						if ($convertArray[$i][0] != "") {
							$counter += 1;
						}
						$i++;
					}
				}

				$vars['dayBookings'][$a]->counter = $counter;
				// Disable features based on block time duration and days/hours added for affiliates to block access for affiliates
				$vars['dayBookings'][$a]->affiliateFeature = 1;
				if ($userType == "Affiliate") {
					$tour_booking = $this->tourBooking::findOrFail($vars['dayBookings'][$a]->booking_id);

					$bookingDate = $date;
					$bookingTime = $vars['dayBookings'][$a]->time_from;
					$bookingSlotTime = Carbon::parse($bookingDate . ' ' . $bookingTime);
					$carbonIns = Carbon::parse($bookingDate . ' ' . $bookingTime);
					$affiliate = $this->customer::findOrFail($tour_booking->customer_id);
					// Execute only when duration is greater than 0 days or hours
					if ($affiliate->duration > 0 && !checkPermission('booking-after-duration')) {
						if ($affiliate->day_hours == 1) {
							$carbonIns->subDays($affiliate->duration);
						} else if ($affiliate->day_hours == 2) {
							$carbonIns->subHours($affiliate->duration);
						}

						if (($carbonIns <= $bookingSlotTime) && (Carbon::now() >= $carbonIns)) {
							$vars['dayBookings'][$a]->affiliateFeature = 0;
						}
					}

					// Integration of available seats for an affiliate
					$tourists = (int)$vars['dayBookings'][$a]->booked_seats;
					$seats = $vars['dayBookings'][$a]->seats;
					$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;
					$remainingSeats = ($tourists > $seats) ? 0 : $availableSeats;

					$vars['dayBookings'][$a]->seats = $vars['dayBookings'][$a]->afl_seats;
					$vars['dayBookings'][$a]->aflSeats = $vars['dayBookings'][$a]->afl_seats;
					$vars['dayBookings'][$a]->aflTourists = $vars['dayBookings'][$a]->tourists;
					$vars['dayBookings'][$a]->aflTotalTourists = $vars['dayBookings'][$a]->afl_tourists;
					$vars['dayBookings'][$a]->aflRemainingSeats = $remainingSeats;
				} else {
					$vars['dayBookings'][$a]->affiliateFeature = 1;
				}

				if (($api != '' && $api == 1) || ($downloadAgenda != '' && $downloadAgenda == 1)) {
					$checkinsSql = 'select `tbt`.`tourists`, `tbt`.`check_in_status`, `tpr`.`rate_for` from `tour_bookings_transact` as `tbt`
						inner join `tour_package_rates` as `tpr` on `tpr`.`id` = `tbt`.`tour_package_rate_id`
						where `tbt`.`deleted_by` = 0 AND `tbt`.`tour_booking_id` = "' . $vars['dayBookings'][$a]->booking_id . '"';

					$checkins = DB::select($checkinsSql);

					if ($checkins) {
						foreach ($checkins as $k => $checkin) {
							$decoded = json_decode($checkin->check_in_status, true);
							$checkins[$k]->check_in_status = ($downloadAgenda != '' && $downloadAgenda == 1) ? $checkin->check_in_status : $decoded;
						}
					}

					$vars['dayBookingsInfo']['b-' . $vars['dayBookings'][$a]->booking_id] = (object) $checkins;
				}

				$vars['dayBookings'][$a]->background_colour_class = BookingService::backgroundColourClass($vars['dayBookings'][$a]);

				$vars['dayBookings'][$a]->addons_price = 0;
				$vars['dayBookings'][$a]->addons_fee = 0;
				$vars['dayBookings'][$a]->addons_total = 0;
				if (isset($vars['dayBookings'][$a]->form_submission) && !empty($vars['dayBookings'][$a]->form_submission)) {
					$decodedForm = json_decode($vars['dayBookings'][$a]->form_submission, true);
					if (isset($decodedForm) && !empty($decodedForm)) {
						foreach ($decodedForm as $key => $addon) {
							if ($addon['priceInfo']['enabled'] == 'true') {
								$vars['dayBookings'][$a]->addons_price += $addon['priceInfo']['price'];
								$vars['dayBookings'][$a]->addons_fee += $addon['priceInfo']['fee'];
								$vars['dayBookings'][$a]->addons_total += $addon['priceInfo']['price'] + $addon['priceInfo']['fee'];
							}
						}
					}
				}

				$vars['packageHasSlots'] = $vars['dayBookings'][$a]->package_has_slots;

				// Bundle/Non-bundle packages
				if (isset($vars['dayBookings'][$a]->packages)) {
					$explodePackage = explode('|', $vars['dayBookings'][$a]->packages);
					if (count($explodePackage) > 0) {
						foreach ($explodePackage as $key => $package) {
							$exPackage = explode(':', $package);
							$vars['dayBookings'][$a]->package_list[$key] = (object) [
								'id' => $exPackage[0],
								'name' => $exPackage[1],
								'redeemed' => 0,
								'not_redeemed' => 0,
								'total' => 0,
								'class' => '',
								'af_class' => ''
							];
						}
					}
				}

				if ($vars['dayBookings'][$a]->is_check_in == 1) {
					if ($vars['dayBookings'][$a]->checkin_type == 1) {
						$redeemTicketFlag++;
					}
				}
			}

			$vars['redeemTicketFlag'] = $redeemTicketFlag;

			if ($vars['tourOperatorId'] && $vars['tourOperatorId'] !== 0) {
				$tourOperatorCheckInStatues = $this->tourOperator::where('id', $vars['tourOperatorId'])->first(['checkins']);
				$vars['tourOperatorCheckInStatues'] = json_decode($tourOperatorCheckInStatues->checkins, true);
				$vars['checkInStatues'] = checkins();
			}

			$dayBookingsInfo = $vars['dayBookings'];

			if ($api != '' && $api == 1) {
				$dayBookings = $vars['dayBookings'];
				$allBookedTotal = 0;
				$allRefundTotal = 0;
				$allPaymentTotal = 0;
				$totalTourists = 0;
				$slotId = 0;
				$seatsInfo = [];
				$sortedData = [];

				if (!empty($dayBookings)) {
					$bookingTotalDetails = [];
					$refundTotalDetails = [];

					foreach ($vars['Payment'] as $payment) {
						$bookingTotalDetails[] = [
							"amount" => $payment->ticket_cost + $payment->processing_fees + $payment->platform_fees,
							"tour_booking_id" => $payment->tour_booking_id
						];
					}

					if (!empty($vars['Refund_Cancel1'])) {
						foreach ($vars['Refund_Cancel1'] as $refund) {
							$refundTotalDetails[] = $refund;
						}
					}
					$agendaView = agendaView();
					$agendaViewId = $agendaView == 1 ? 'tour_slot_id' : 'hotel_id';
					foreach ($dayBookings as $key => $dayBooking) {

						$sortedData[$dayBooking->$agendaViewId]['slotTime'] = ($agendaView == 1) ? $dayBooking->time_from . " - " . $dayBooking->time_to : '';
						if ($agendaView != 1) {
							$sortedData[$dayBooking->$agendaViewId]['hotelName'] = ($dayBooking->hotel_name != '') ? $dayBooking->hotel_name : 'Custom Pickup';
							$sortedData[$dayBooking->$agendaViewId]['hotelId'] = ($dayBooking->hotel_id != '') ? $dayBooking->hotel_id : '';
						}

						if (isset($vars['dayBookingsInfo']['b-' . $dayBooking->booking_id])) {
							$dayBooking->dayBookings = $vars['dayBookingsInfo']['b-' . $dayBooking->booking_id];
						} else {
							$dayBooking->dayBookings = [];
						}

						$dayBooking->bookingtotal = 0;
						$dayBooking->totalrefund = 0;
						$dayBooking->booking_Total = 0;

						foreach ($bookingTotalDetails as $booking) {
							if (isset($booking['tour_booking_id']) && $booking['tour_booking_id'] == $dayBooking->booking_id) {
								if (!empty($booking['amount'])) {
									$dayBooking->bookingtotal += (float)$booking['amount'];
								}
							}
						}

						foreach ($refundTotalDetails as $refund) {
							if (isset($refund->tour_booking_id) && $refund->tour_booking_id == $dayBooking->booking_id) {
								$dayBooking->totalrefund = $refund->TotalRefund;
							}
						}

						$sortedData[$dayBooking->$agendaViewId]['slotArray'][] = $dayBooking;
					}

					foreach ($sortedData as $slotId => $slotData) {
						$seatsInfo[$slotId] = [
							'slotId' => $slotId,
							'seatsCount' => isset($slotData['slotArray'][0]->seats) ? $slotData['slotArray'][0]->seats : 0,
							'bookingsCount' => 0,
							'touristsCount' => 0,
							'amtUnpaid' => 0,
							'amtPaid' => 0,
							'amtTotal' => 0,
							'bookingTotal' => 0,
							'refundTotal' => 0,
							'paymentTotal' => 0,
							'Refund_Total' => 0,
							'booking_total' => 0,
						];

						foreach ($slotData['slotArray'] as $value) {
							if ($value->booking_total != "0.00") {
								$value->total = $value->booking_total;
							}
							$value->paid = (string)($value->bookingtotal - $value->refund);
							$value->refund = (string)$value->refund;
						}
					}

					foreach ($dayBookings as $dayBooking) {
						$slotId = $dayBooking->$agendaViewId;

						$seatsInfo[$slotId]['bookingsCount']++;
						$seatsInfo[$slotId]['touristsCount'] += $dayBooking->tourists;
						$seatsInfo[$slotId]['amtUnpaid'] += $dayBooking->total - $dayBooking->paid;
						$seatsInfo[$slotId]['amtPaid'] += $dayBooking->paid;
						$seatsInfo[$slotId]['amtTotal'] = $dayBooking->total;
						$seatsInfo[$slotId]['booking_total'] += $dayBooking->booking_total;

						if ($dayBooking->booking_total != 0) {
							$seatsInfo[$slotId]['bookingTotal'] += $dayBooking->booking_total;
						} elseif ($dayBooking->bookingtotal == 0) {
							$seatsInfo[$slotId]['bookingTotal'] += $dayBooking->total;
						} else {
							$seatsInfo[$slotId]['bookingTotal'] += $dayBooking->bookingtotal;
						}

						$seatsInfo[$slotId]['refundTotal'] += $dayBooking->refund;
						$seatsInfo[$slotId]['paymentTotal'] += $dayBooking->bookingtotal - $dayBooking->totalrefund;
					}

					foreach ($seatsInfo as $seatInfo) {
						$allBookedTotal += $seatInfo['bookingTotal'];
						$allRefundTotal += $seatInfo['refundTotal'];
						$allPaymentTotal += $seatInfo['paymentTotal'];
						$totalTourists += $seatInfo['touristsCount'];
					}
				}

				// Rest of the code using $allBookedTotal, $allRefundTotal, $allPaymentTotal, $totalTourists
				$vars['allBookedTotal'] = number_format((float)$allBookedTotal, 2, '.', '');
				$vars['allRefundTotal'] = number_format((float)$allRefundTotal, 2, '.', '');
				$vars['allPaymentTotal'] = number_format((float)$allPaymentTotal, 2, '.', '');
				$vars['totalTourits'] = $totalTourists;

				$newSortedData = [];
				if (!empty($sortedData)) {
					$i = 0;
					foreach ($sortedData as $key => $sortData) {
						$availableSeats = isset($seatsInfo[$key]['seatsCount']) && isset($seatsInfo[$key]['touristsCount']) ? $seatsInfo[$key]['seatsCount'] - $seatsInfo[$key]['touristsCount'] : 0;
						$seats = isset($seatsInfo[$key]['seatsCount']) ? $seatsInfo[$key]['seatsCount'] : 0;
						$sortedData[$key]['availableSeats'] = (string)$availableSeats;
						$sortedData[$key]['seats'] = (string)$seats;

						// Generate age group array
						$ageTotalGroup = [];
						foreach ($sortedData[$key]['slotArray'] as $slotBookings) {
							foreach ($slotBookings->dayBookings as $ageGroup) {
								$rateFor = $ageGroup->rate_for;
								if (isset($ageTotalGroup[$rateFor])) {
									$ageTotalGroup[$rateFor] += (int)$ageGroup->tourists;
								} else {
									$ageTotalGroup[$rateFor] = (int)$ageGroup->tourists;
								}
							}
						}

						$sortedData[$key]['ageTotalGroup'] = array_map(function ($value, $key) {
							return $value . ' ' . $key;
						}, $ageTotalGroup, array_keys($ageTotalGroup));

						$newSortedData[$i] = $sortedData[$key];
						$i++;
					}
				}

				unset($vars['dayBookings']);
				unset($vars['dayBookingsInfo']);
				unset($vars['Payment']);
				unset($vars['Refund_Cancel']);
				unset($vars['Refund_Cancel1']);
				$vars['data'] = $newSortedData;
			}

			$this->agendaRepository->getCheckInsAndTickets($dayBookingsInfo);

			if ($vars['packageHasContacts']) {
				$this->agendaRepository->bookingContacts($dayBookingsInfo);
			}

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Generate stripe token for payment
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function stripeGenerateToken()
	{
		try {
			$connectionToken = app(PaymentGatewayInterface::class)->createConnectionToken();

			return ['secret' => $connectionToken->secret];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Cancel stripe card reader action for payment
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function stripeCancelCardReader($request)
	{
		try {
			$response = app(PaymentGatewayInterface::class)->cancelTerminalReaderAction($request->card_reader_id);

			return $response;
		} catch (Exception $exception) {
			Log::error($exception);

			if ($request->has('booking_id') && $request->booking_id != '') {
				HistoryService::storeNormalErrorInHistory($request->booking_id, $exception);
			}

			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Create stripe payment intent for booking payment
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function stripeCreatePaymentIntent($request)
	{
		try {
			$tourOperator = tourOperator();
			$tourBooking = $this->tourBooking::with([
				'tourPackage',
				'tourSlotTime.tourSlotDate',
				'customer'
			])->find($request->booking_id);

			if (!$tourBooking) {
				throw new Exception("Booking not found.");
			}

			$tourPackage = $tourBooking->tourPackage;
			$customer = $tourBooking->customer;
			$convertedAmount = $request->amount / 100;
			$tourOperatorName = $tourOperator->statement_descriptor;

			// If customer doesn't have a stripe_id, create one
			if ($request->booking_id && $request->card_reader && isset($customer) && $customer->stripe_id == null) {
				$stripeCustomer = StripeCustomer::create([
					'name' => $customer->name,
					'email' => $customer->email,
					'phone' => $customer->phone_number
				]);
				$customer->stripe_id = $stripeCustomer->id;
				$customer->save();
			}

			// Determine destinationAmount and metadata
			if ($request->has('cartID') && $request->cartID != '') {
				$cartId = $request->cartID;
				$cartBookingsData = CartService::getCartBookings($cartId);
				$cartPaymentTotal = BookingService::calculateTotalCartPayment($request->booking_id, $customer->is_affiliate);
				$destinationAmount = round($cartPaymentTotal['cartServiceCommissionTotal'], 2);
				// Mixed-cart rule: any 2027+ date among this cart's bookings makes the whole
				// card-reader charge platform-held.
				$mode = app(PaymentModeResolver::class)->resolve(array_column($cartBookingsData, 'date'));

				if ($request->card_reader) {
					$stripeAttributes = StripeUtils::getStripeAttributesForAppCardReader(
						$request,
						$convertedAmount,
						$destinationAmount,
						$tourOperatorName,
						$tourPackage->stripe_destination_id ?: $tourOperator->stripe_destination_id,
						$customer,
						$mode
					);

					$dataForMetadata = StripeUtils::prepareMetadataForAppCardReader($request, $customer, $cartBookingsData);
					$stripeMetadata = CartService::stripeMetadata($dataForMetadata);

					$paymentIntentParams = $stripeAttributes + $stripeMetadata;
					$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);
				} else {
					$paymentIntentParams = [
						'payment_method_types' => ['card_present'],
						'capture_method' => 'automatic',
						'currency' => currencyFormation(code: true),
						'amount' => $convertedAmount * 100,
						'statement_descriptor_suffix' => $tourOperatorName,
						'metadata' => ['tenant_id' => tenant()->id],
						...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($tourOperator->stripe_destination_id, (int) round($destinationAmount * 100), mode: $mode)
					];
					$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);
				}
			} else {
				$bookingTransaction = $this->bookingTransact::where('tour_booking_id', $request->booking_id)
					->where('deleted_by', 0)
					->first();

				if (!$bookingTransaction) {
					throw new Exception("Transaction not found.");
				}

				$destinationAmount = round(
					$convertedAmount * $bookingTransaction->stripe_fees_percentage / 100,
					2
				);

				$discountFixed = $request->discount_fixed ?? '';
				$discountPercentage = $request->discount_percentage ?? '';
				$mode = app(PaymentModeResolver::class)->resolveForSingleDate($tourBooking->tourSlotTime->date);

				if ($request->card_reader) {
					$stripeAttributes = StripeUtils::getStripeAttributesForAppCardReader(
						$request,
						$convertedAmount,
						$destinationAmount,
						$tourOperatorName,
						$tourPackage->stripe_destination_id ?: $tourOperator->stripe_destination_id,
						$customer,
						$mode
					);

					$stripeMetadata = StripeUtils::getStripeMetadataForAppCardReader(
						$request,
						$tourBooking,
						$tourPackage,
						$customer,
						$convertedAmount,
						$destinationAmount,
						$discountFixed,
						$discountPercentage
					);

					$paymentIntentParams = $stripeAttributes + $stripeMetadata;
					$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);
				} else {
					$paymentIntentParams = [
						'payment_method_types' => ['card_present'],
						'capture_method' => 'automatic',
						'currency' => currencyFormation(code: true),
						'amount' => $convertedAmount * 100,
						'statement_descriptor_suffix' => $tourOperatorName,
						'metadata' => ['tenant_id' => tenant()->id],
						...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($tourPackage->stripe_destination_id ?: $tourOperator->stripe_destination_id, (int) round($destinationAmount * 100), mode: $mode)
					];
					$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);
				}
			}

			return [
				'client_secret' => $paymentIntent->client_secret,
				'id' => $paymentIntent->id
			];
		} catch (CardException $exception) {
			Log::error($exception);
			HistoryService::storeCardPaymentErrorInHistory($request->booking_id, $exception);
			throw new GeneralCoreException($exception);
		} catch (Exception $exception) {
			Log::error($exception);
			HistoryService::storeNormalErrorInHistory($request->booking_id, $exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Make payment for pay later bookings
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function paymentNewBooking($request)
	{
		DB::beginTransaction();
		try {
			DB::table('tour_bookings_master')->where('id', $request->bookingID)->lockForUpdate()->first(['id']);
			$oldBookingId = oldBookingId();
			$isCashFees = cashFees();
			$info = BookingUtils::getBookingDetails($request->bookingID);

			$discountPromoCode = '';
			if ($request->has('discount_promo_code')) {
				$discountPromoCode = $request->discount_promo_code;
				$promotion = $this->promotion::where('code', $discountPromoCode)
					->where('tour_package_id', $info->tour_package_id)
					->first();

				DB::table('tour_bookings_master')
					->where('id', $info->id)
					->update([
						'tour_promotion_id' => $promotion->id,
						'discount_promo_code' => $discountPromoCode
					]);
			}
			$paymentIntentId = $request->has('payment_intent_id') && $request->payment_intent_id != ''
				? $request->payment_intent_id
				: '';

			// Save the card details
			HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $request->bookingID, "BACKEND");

			// To unblock the affiliate soft booking when make a payment
			if ($info->is_reserved_for_affiliate) {
				DB::table('tour_bookings_master')
					->where('id', $info->id)
					->update([
						'is_reserved_for_affiliate' => 0
					]);
				$note = "Soft booking has been unblocked when make a payment.";
				HistoryService::storeNoteInHistory($note, $info->id);
			}

			$customerData = BookingUtils::getOrCreateCustomer(
				0,
				$info->customeremail,
				$info->customername,
				$request->customerphone,
				$info->tour_operator_id
			);

			$tourBooking = DB::select("
				SELECT
					`tbm`.`id`,
					`tbm`.`id` as `booking_id`,
					`tbm`.`hotel_id`,
					`tbm`.`total`,
					`tbm`.`discount2_value`,
					`tbm`.`discount2_percentage`,
				    `tbm`.`is_group_booking`,
					`c`.`name` as `Name`,
					`sd`.`date` as `Date`,
					`st`.`time_from` as `StartTime`,
					`st`.`time_to` as `EndTime`,
					`c`.`phone_number` as `ContactNumber`,
					`c`.`email` as `Email`,
					`tbm`.`tourists`,
					`c`.`id` as `customer_id`,
					`c`.`is_affiliate`,
					`t_o`.`id` as `tour_operator_id`,
					`t_o`.`name` as `tour_operator_name`,
					`tbm`.`service_commission`,
					`tp`.`stripe_destination_id`,
					`tp`.`name` as `package_name`,
					`tp`.`service_commission_percentage`,
					`tp`.`package_has_slots`,
					`c`.`affiliate_processing_percentage`,
					`tbm`.`created_at`,
					`tbm`.`booking_total`,
					`tbm`.`is_reserved_for_affiliate`,
					`tbm`.`tour_package_id`
				FROM
					`tour_bookings_master` as `tbm`
					INNER JOIN `customers` as `c` ON `c`.`id` = `tbm`.`customer_id`
					INNER JOIN `tour_bookings_transact` as `tbt` ON `tbt`.`tour_booking_id` = `tbm`.`id`
					INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
					INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
					INNER JOIN `tour_slot_times` as `st` ON `st`.`id` = `tbm`.`tour_slot_id`
					INNER JOIN `tour_slot_dates` as `sd` ON `sd`.`id` = `st`.`tour_slot_id`
				WHERE
				`tbm`.`id` = " . $request->bookingID . "
			");

			$isGroupBooking = $tourBooking[0]->is_group_booking;
			$bookingT = $this->bookingTransact::with('packageRate')
				->where('tour_booking_id', $request->bookingID)
				->where('deleted_by', 0)
				->get();

			$rate = 0;
			$permitFee = 0;
			$procFee = 0;
			$additionalCharge = 0;
			$tourists = 0;
			$subTotal = 0;
			$total = 0;
			$serviceCommission = 0;
			$fees = 0;
			$discountAmt = 0;
			$oldDiscountAmt = 0;
			$serviceCommissionTotal = 0;
			$natFeesPercentage = $bookingT[0]->nat_fees_percentage;
			$stripeFeesPercentage = $bookingT[0]->stripe_fees_percentage;

			// A discount entered in the modal must also reduce the link and be stored on the booking
			// (discount2_*) — see DiscountService::applyPaymentLinkDiscount().
			DiscountService::applyPaymentLinkDiscount($request, $tourBooking, $info);

			// Calculate NAT platform fee for a new Payment Link (V1: straight tourists, no partner fees)
			$linkPlatformFee = 0.0;
			if ($request->payment_method == 'Payment Link' && empty($request->remaining_link_id)) {
				if ($info->discount2_value > 0) {
					$linkPlatformFee = roundout(($tourBooking[0]->total * $natFeesPercentage / 100), 2);
				} else {
					foreach ($bookingT as $tbTransact) {
						$linkPlatformFee += BookingService::calculateServiceCommission(
							$tbTransact->rate,
							$tbTransact->additional_charge,
							$tbTransact->permit_fee,
							$natFeesPercentage,
							$tbTransact->tourists,
							$isGroupBooking
						);
					}
				}
			}
			$linkResponse = PaymentLinkService::handleMakePaymentSection($request, $info, $tourBooking, $paymentIntentId, $request->payment_method, $linkPlatformFee, $natFeesPercentage);
			if ($linkResponse !== null) {
				return $linkResponse;
			}

			// Calculate the booking amount
			foreach ($bookingT as $tbTransaction) {
				$rate = $tbTransaction->rate;
				$permitFee = $tbTransaction->permit_fee;
				$additionalCharge = $tbTransaction->additional_charge;
				$tourists = $tbTransaction->tourists;

				$procFee = ($request->bookingID < $oldBookingId) ? 2.9 : $tbTransaction->packageRate->processing_charge_percentage;
				if ($request->payment_method == 'Card' || ($isCashFees == '1' && $request->payment_method == 'Cash')) {
					if (
						!$request->has('discount_type') || empty($request->discount_type) ||
						(empty($request->discount_fixed_money) && empty($request->discount_percentage)) &&
						$info->discount2_value
					) {
						$serviceCommissionTotal += BookingService::calculateServiceCommission(
							$rate,
							$additionalCharge,
							$permitFee,
							$natFeesPercentage,
							$tourists,
							$isGroupBooking
						);
					}
				} else {
					$procFee = 0;
				}

				$subTotal = BookingService::calculateSubTotal($rate, $permitFee, $additionalCharge, $tourists, $isGroupBooking, 0, $tbTransaction->refund_permitfee_status);
				$total += ($procFee && ($request->payment_method == 'Card' || ($isCashFees == '1' && $request->payment_method == 'Cash'))) ? $subTotal + ($subTotal * ($procFee / 100)) : $subTotal;
				$tbTransaction->processing_charge_percentage = $procFee;
				$tbTransaction->nat_fees_percentage = $natFeesPercentage;
				$tbTransaction->stripe_fees_percentage = $stripeFeesPercentage;
				$tbTransaction->total = $procFee ? $subTotal + ($subTotal * ($procFee / 100)) : $subTotal;
				$tbTransaction->save();
			}
			/*end foreach*/

			$trans = $this->transaction::where('tour_booking_id', $request->bookingID)->where('status', 'Completed')->whereNull('charge_type')->first(['payment_type']);
			//Here Paid amount is less than ticket cost in cash bookings for change amount feature
			if ($request->payment_method == "Cash" && $request->has('cash_paid_amount') && $request->has('cash_paid_amount') != '' && $request->split_value == '') {
				$cashPaidAmount = ($request->has('cash_paid_amount') && $request->has('cash_paid_amount') != '') ? $request->cash_paid_amount : '';
				// Compare against what's actually owed after discounts, not the undiscounted total.
				$paymentAmountCash = empty($trans) ? DiscountService::cashDueAfterDiscounts($request, (float) $total, $tourBooking) : $total;
				if (!empty($trans) && $trans->payment_type == 1) {
					$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();
					$paymentOnlyCheck = 0;
					foreach ($paymentOnly as $value) {
						$details = json_decode($value->details);
						$paymentOnlyCheck += $details->amount;
					}
					$paymentAmountCash =  $total - $paymentOnlyCheck;
				}

				$changeAmount = 0;
				if ($cashPaidAmount != '' && $cashPaidAmount > 0 && $cashPaidAmount < $paymentAmountCash) {
					$request->split_value = $cashPaidAmount;
				}
			}

			// Discount given on pay later option when booking created
			// Here is the total minus discount amount
			$totalForDiscountWithSplit = 0;
			if (empty($trans) && !empty($request->split_value) && $info->discount2_value > 0 && !$request->has('discount_type') && empty($request->discount_percentage) && empty($request->discount_fixed_money)) {
				$totalForDiscountWithSplit = $total - $info->discount2_value;
			}

			//Discount applied in make payment with split payment
			if (empty($trans) && !empty($request->split_value) && $request->has('discount_type') && (!empty($request->discount_percentage) || !empty($request->discount_fixed_money) || !empty($request->discount_promo_code))) {
				$totalForDiscountWithSplit = DiscountService::calculateTotalWithSplitDiscount($request, $total, $tourBooking[0]->tour_package_id, $trans);
			}

			if (!empty($trans) && $trans->payment_type == 1) {
				if ($request->split_value) {
					$total = $request->split_value;
				}
			}

			if (!empty($trans) && ($trans->payment_type == '' || $trans->payment_type == 0)) {
				if (isset($request->api) && $request->api == 1) {
					if ($request->payment_method == 'Card') {
						return [
							'tourBookingId' => $request->bookingID,
							'totalBookedSeats' => $info->seats
						];
					} else {
						throw new Exception('Tour booking already paid.');
					}
				} else {
					return response()->json([
						'tourBookingId' => $request->bookingID,
						'paid' => 1
					]);
				}
			}

			// get discount detail if promo code applied from booking info module
			$discountAmt = DiscountService::calculateDiscount($request, $total, $tourBooking[0]->tour_package_id);
			//Get info of promo code applied for the discount
			$promoDetail = [];
			if ($request->has('discount_type') && $request->discount_type == 'Promo Code' && $request->is_applied == 1 && !empty($request->discount_promo_code)) {
				$promoDetail = $this->promotion::where('code', $request->discount_promo_code)
					->where('tour_package_id', $info->tour_package_id)
					->first();
			}

			if (empty($request->split_value)) {
				$total -= $discountAmt;
			}

			//Check for old discount applied when booking is done using Pay Later
			$discountData = DiscountService::calculateOldDiscount($tourBooking, $total, $request);
			$oldDiscountAmt = $discountData['oldDiscountAmt'];
			$total = $discountData['total'];

			// Add the service commission in the total amount
			if (($request->payment_method == "Card" || ($isCashFees == 1 && $request->payment_method == 'Cash'))) {
				$serviceCommission = StripeService::calculateServiceCommissionMakePayment(
					$total,
					$totalForDiscountWithSplit,
					$natFeesPercentage,
					$serviceCommissionTotal,
					$request->bookingID,
					$oldBookingId,
					$discountAmt,
					$oldDiscountAmt,
					$request
				);
				$total = $total + $serviceCommission;
			}

			// Set user of updated booking
			$createdByWithName = BookingService::createdUserInfo();

			// Set payment type while do payment of booking
			$paymentType = match ($request->payment_method) {
				'Pay Later', 'Invoiced' => 'Invoiced',
				'Cash' => 'Cash',
				default => 'Card',
			};

			$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)->where('operation', 'Payment')->get();
			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$paymentOnlyCheck += $details->amount;
			}

			if ($request->payment_method != 'Card') {
				$total = number_format((float)$total, 2, '.', '');
				if (!empty($trans) && $trans->payment_type == 1) {
					if (empty($request->split_value)) {
						$total -= $paymentOnlyCheck;
					}
				}

				if ($isCashFees == '1' && $request->payment_method == 'Cash') {
					if (!empty($request->split_value)) {
						$fees = roundout($request->split_value * $stripeFeesPercentage / 100, 2);
					} else {
						$fees = roundout(($total * $stripeFeesPercentage) / 100);
					}
				}
			} else {
				$total = round($total, 2);
				$subTotal = round($total - $serviceCommission, 2);

				if ($request->has('api') && $request->api == '1' || (!$request->is('api/*') && $request->has('payment_intent_id') && $request->payment_intent_id != '')) {
					if (!empty($trans) && $trans->payment_type == 1) {
						if (empty($request->split_value)) {
							$total -= $paymentOnlyCheck;
						}
					}

					$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
					if ($paymentIntentId == '') {
						throw new Exception('You must provide Stripe Payment Intent Id');
					}

					$retrieveOptions = ['expand' => ['charges.data.transfer']];
					$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

					$cardDetails = StripeService::extractCardDetails($paymentIntent, $request->is('api/*'));
					$crd = $cardDetails['last4'];
					$cardType = $cardDetails['brand'];
					$from = $cardDetails['source'];

					if ($request->webhook != 1 || !$request->is('api/*')) {
						StripeService::updatePaymentIntentForMakePayment($paymentIntentId, $total, $subTotal, $serviceCommission, $info, $request, $from, 0);
					}

					$fees = PlatformFeeMetadata::feeFor($paymentIntent, (isset($paymentIntent->application_fee_amount) && $paymentIntent->application_fee_amount > 0) ? $paymentIntent->application_fee_amount / 100 : 0);
				}
			}

			$transactionCount = 0;

			if (
				$request->payment_method === 'Card' &&
				!empty($request->payment_intent_id)
			) {
				$transactionCount = Transaction::where([
					['transaction_number', '=', $request->payment_intent_id],
					['tour_booking_id', '=', $request->bookingID],
					['status', '=', 'Completed']
				])->count();
			}

			if ($request->webhook != 1 && $transactionCount == 0) {
				$totalPayment = !empty($request->split_value) ? $request->split_value : $total;
				$metadataFrom = isset($paymentIntent) ? $paymentIntent->metadata['from'] : "";
				if (!empty($metadataFrom)) {
					$totalPayment = (!empty($metadataFrom) && $metadataFrom == "Web Card Reader") ? ($paymentIntent->amount / 100) : $total;
				}
				$splitPayment = !empty($request->split_value) ? 1 : 0;
				$transactionId = TransactionService::storePaymentInTransaction(
					$request->bookingID,
					$totalPayment,
					$fees,
					$paymentType,
					$splitPayment,
					$paymentIntent->id ?? null,
					$paymentIntent->charges->data[0]->application_fee ?? null,
					mode: ($paymentType === 'Card' && isset($paymentIntent)) ? PaymentModeInference::fromStripeObject($paymentIntent) : PaymentModeResolver::DESTINATION
				);
				if ($request->api != 1) {
					BookingService::dispatchBookingNotification($tourBooking[0], $request, $total, $paymentType);
				}
			}

			$changeAmount = 0;
			if ($request->payment_method == "Cash") {
				$cashPaidAmount = $request->has('cash_paid_amount') && !empty($request->cash_paid_amount)
					? $request->cash_paid_amount
					: 0;

				$paymentAmountCash = !empty($request->split_value) ? $request->split_value : $total;

				if ($cashPaidAmount > $paymentAmountCash) {
					$changeAmount = round($cashPaidAmount - $paymentAmountCash, 2);
				}
			}

			$discountPromoCode = !empty($info->discount_promo_code)
				? $info->discount_promo_code
				: ($request->has('discount_type') && $request->discount_type === 'Promo Code'
					? $request->discount_promo_code
					: '');

			if ($request->webhook != 1 && $transactionCount == 0) {
				$totalPayment = !empty($request->split_value) ? $request->split_value : $total;
				$metadataFrom = isset($paymentIntent) ? $paymentIntent->metadata['from'] : "";
				if (!empty($metadataFrom)) {
					$totalPayment = (!empty($metadataFrom) && $metadataFrom == "Web Card Reader") ? ($paymentIntent->amount / 100) : $total;
				}
				$data = [
					'total' => $totalPayment,
					'payment_type' => $paymentType,
					'discount_promo_code' => $discountPromoCode
				];

				$historyId = HistoryService::storeCardPaymentInHistory(
					$tourBooking[0]->booking_id,
					$tourBooking[0]->tourists,
					$data,
					$crd ?? '',
					$cardType ?? '',
					$createdByWithName,
					[], /* $cardBookingData */
					'Manual Update Make Payment', /* $fromMethod */
					$changeAmount,
					!empty($request->split_value) ? 'Split Payment' : 'Full Payment' /* $paymentMethod */
				);
			}

			if (empty($trans)) {
				if ($request->payment_method == 'Cash' || $request->payment_method == 'Pay Later' || $request->payment_method == 'Invoiced' || ($request->payment_method == 'Card' && $request->webhook != 1)) {
					$amountUpdate = TourBooking::findOrFail($request->bookingID);
					$amountUpdate->total = $total;
					$amountUpdate->booking_total = $total;
					$amountUpdate->service_commission = $serviceCommission;
					$amountUpdate->save();
				}
			}

			if (empty($trans)) {
				$shouldUpdateAmount = ($request->payment_method === 'Cash' && $isCashFees !== '1')
					|| $request->payment_method === 'Pay Later'
					|| $request->payment_method === 'Invoiced'
					|| $request->webhook != 1;
				if ($shouldUpdateAmount) {
					$this->updateBookingAmount($request, $total, $discountAmt, $oldDiscountAmt, $promoDetail);
				}
			} else if (!empty($trans)) {
				if ($request->has('discount_type') && $discountAmt > 0 && $request->webhook != 1) {
					$amountUpdate = $this->tourBooking::findOrFail($request->bookingID);
					if ($amountUpdate) {
						$amountUpdate->total -= $discountAmt;
						$amountUpdate->booking_total -= $discountAmt;
						// Apply discount values based on discount type
						if ($request->has('discount_type')) {
							$this->applyDiscountValues($request, $amountUpdate, $discountAmt, $promoDetail);
						}
						$amountUpdate->save();
					}
				}
			}

			// Below line is for to update tour booking history table
			if ($request->webhook != 1 && $transactionCount == 0) {
				$paymentIntent = isset($paymentIntent) ? $paymentIntent : null;
				$this->handlePaymentHistoryUpdateJobs($tourBooking, $transactionId, $historyId, $request, $paymentType, $info, $paymentIntent);
			}

			DB::commit();

			if (isset($request->api) && $request->api == 1) {
				return [
					'tourBookingId' => $request->bookingID,
					'totalBookedSeats' => $info->seats
				];
			} else {
				$splitCount = "Please select option";
				if ($request->dividedBy != "Please select option" && $request->split_value != '') {
					$splitCount = $request->dividedBy;
				}
				return response()->json([
					'tourBookingId' => $request->bookingID,
					'totalBookedSeats' => $info->seats,
					'splitCount' => $splitCount,
				]);
			}
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();
			HistoryService::storeCardPaymentErrorInHistory($request->bookingID, $exception);
			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				return response()->json([
					'errors' => $exception->getMessage(),
					'operation_at' => mysqlDT(),
				]);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			HistoryService::storeNormalErrorInHistory($request->bookingID, $exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Handle the payment-link section of paymentNewBooking():
	 * 1. Pending remaining_payment link — resend email (Pay with Link) or finalize via card (Pay with Card).
	 * 2. New payment link — calculate total, store link, send email.
	 * Returns a JsonResponse when the request is fully handled, or null to fall through to normal payment logic.
	 */
	/**
	 * Updates the booking amount by applying applicable discounts.
	 *
	 * @param Request $request
	 * @param float   $total
	 * @param float   $discountAmt
	 * @param float   $oldDiscountAmt
	 * @param object  $promoDetail
	 */
	private function updateBookingAmount($request, &$total, $discountAmt, $oldDiscountAmt, $promoDetail)
	{
		$isCashFees = cashFees();
		$amountUpdate = $this->tourBooking::findOrFail($request->bookingID);

		if (!$amountUpdate) {
			return;
		}

		// Apply discounts if applicable
		if (!empty($request->split_value)) {
			if ($request->has('discount_type') && $discountAmt > 0) {
				$total -= $discountAmt;
			}
			if ($oldDiscountAmt > 0) {
				$total -= $oldDiscountAmt;
			}
		}

		// Update booking amounts
		$amountUpdate->total = $total;
		$amountUpdate->booking_total = $total;

		// Apply discount values
		$this->applyDiscountValues($request, $amountUpdate, $discountAmt, $promoDetail);
		// Reset service commission (if applicable)
		if (($request->payment_method === 'Cash' && $isCashFees !== '1')
			|| $request->payment_method === 'Pay Later'
			|| $request->payment_method === 'Invoiced'
		) {
			$amountUpdate->service_commission = 0;
		}

		$amountUpdate->save();
	}
	/**
	 * Applies discount values to the booking for make payment.
	 *
	 * @param Request $request
	 * @param mixed $amountUpdate
	 * @param float $discountAmt
	 * @param object $promoDetail
	 */
	private function applyDiscountValues($request, &$amountUpdate, $discountAmt, $promoDetail)
	{
		if (!$request->has('discount_type')) {
			return;
		}
		$discountValue = ($amountUpdate->discount2_value > 0)
			? $amountUpdate->discount2_value + $discountAmt
			: $discountAmt;
		switch ($request->discount_type) {
			case 'Percentage':
				$amountUpdate->discount2_percentage = $request->discount_percentage;
				$amountUpdate->discount2_value = $discountValue;
				break;

			case 'Promo Code':
				if ($promoDetail->discount_value_type === 'Percent') {
					$amountUpdate->discount2_percentage = $request->discount_percentage;
				}
				$amountUpdate->discount2_value = $discountValue;
				// Fall through to apply discount amount

			case 'Fixed Money':
				$amountUpdate->discount2_value = $discountValue;
				break;
		}
	}
	/**
	 * Make whole payment of the cart bookings
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function paymentCartBookings($request)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$oldBookingId = oldBookingId();
			$isCashFees = cashFees();
			$cartId = $request->cartID;
			$paymentMethod = $request->payment_method_cart_payment;
			$cartBookings = DB::table('tour_bookings_master AS tbm')
				->where('tbm.cart_id', $cartId)
				->get();
			$bookingDetails = $this->tourBooking::where('id', $request->bookingID)->first();
			$customer = $this->customer::where('id', $bookingDetails->customer_id)
				->withTrashed()->first();

			// Set user of updated booking
			$routePrefix = '';
			if ($userType == "Admin") {
				$routePrefix = 'admin.';
			}
			$createdByWithName = BookingService::createdUserInfo();
			$totalCartAmount = 0;
			$totalCartFees = 0;
			foreach ($cartBookings as $cartBooking) {
				$bookingId = $cartBooking->id;
				$info = BookingUtils::getBookingDetails($bookingId);
				// To unblock the affiliate soft booking when make a payment
				if ($info->is_reserved_for_affiliate) {
					DB::table('tour_bookings_master')
						->where('id', $bookingId)
						->update([
							'is_reserved_for_affiliate' => 0
						]);

					$note = "Soft booking has been unblocked when make a payment.";
					HistoryService::storeNoteInHistory($note, $bookingId);
				}

				$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

				// Assign the returned values to respective variables
				$natFeesPercentage = $commissionRates['nat_fees_percentage'];
				$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

				$bookingT = $this->bookingTransact::with('packageRate')
					->where('tour_booking_id', $bookingId)
					->where('deleted_by', 0)
					->get();

				// Calculate the booking amount
				$result = CartService::calculateTotalAmount($bookingT, $paymentMethod, $isCashFees, $request, $info, $natFeesPercentage, $stripeFeesPercentage, true);
				$total = $result['total'];
				$serviceCommissionTotal = $result['serviceCommissionTotal'];
				$oldDiscountAmt = 0;
				$fees = 0;
				$transCount = $this->transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->count();
				// Check for old discount applied when booking is done using Pay Later
				if (isset($info->discount2_value) && $info->discount2_value > 0 && $info->discount2_percentage == 0) {
					$oldDiscountAmt = $info->discount2_value;
					$total -= $info->discount2_value;
				} else if ($info->discount2_percentage > 0) {
					$oldDiscountAmt = number_format(($total * $info->discount2_percentage) / 100, 2, '.', '');
					$total -= $oldDiscountAmt;
				}

				// Add the service commission in the total amount
				if (
					($paymentMethod === 'Card' || ($isCashFees === '1' && $paymentMethod === 'Cash'))
					&& $customer->is_affiliate === 'No'
				) {
					if ($bookingId >= $oldBookingId) { // Set booking id 95440
						$serviceCommission = ($oldDiscountAmt > 0)
							? roundout(($total * $natFeesPercentage / 100), 2)
							: $serviceCommissionTotal;
					} else {
						$serviceCommission = number_format((float)$total * 6 / 100, 2, '.', '');
					}

					$total += $serviceCommission;
				} elseif (
					($paymentMethod === 'Card' || ($isCashFees === '1' && $paymentMethod === 'Cash'))
					&& $customer->is_affiliate === 'Yes'
				) {
					if ($request->bookingID >= $oldBookingId) { // Set booking id 95440
						$serviceCommission = ($oldDiscountAmt > 0)
							? roundout(($total * $natFeesPercentage / 100), 2)
							: $serviceCommissionTotal;

						$total += $serviceCommission;
					}
				}
				$totalCartAmount += $total;
				if ($paymentMethod === 'Card' || ($isCashFees === '1' && $paymentMethod === 'Cash')) {
					$totalCartFees += $serviceCommission;
				}

				// Get custom charge
				$customCharge = CustomChargeBookingService::customChargePrice($bookingId);
				$payLaterCustomCharges = CustomChargeBookingService::customChargeList($bookingId);

				if ($paymentMethod != 'Card') {
					$total = number_format((float)$total, 2, '.', '');

					if ($isCashFees == '1' && $paymentMethod == 'Cash') {
						$fees = $serviceCommission;
					}
				} else {
					$total = round($total, 2);
					$subTotal = round($total - $serviceCommission, 2);

					$addonsAmount = BookingService::fetchPayLaterAddonsWithPrice($info->id, $natFeesPercentage);

					$cardBookingsData[] = [
						'total'                => $total,
						'discount2_value'      => $info->discount2_value,
						'tourists'             => $info->tourists,
						'booking_id'           => $bookingId,
						'tour_package'         => $info->package_name,
						'date'                 => $info->date,
						'time_from'            => $info->time_from,
						'subtotal'             => $subTotal,
						'serviceCommission'    => $serviceCommission,
						'destination'          => $info->stripe_destination_id,
						'slot_time'            => $info->tour_slot_id,
						'tour_operator_id'     => $info->tour_operator_id,
						'statement_descriptor' => $info->statement_descriptor,
						'card_reader_prefix'   => $info->card_reader_prefix,
						'tour_package_id'      => $info->tour_package_id,
						'addon_price'          => $addonsAmount['addons_price'],
						'addon_fee'            => $addonsAmount['addons_fee'],
						'addon_total'          => $addonsAmount['addons_total'],
						'custom_charge_amount' => $customCharge['custom_charge_total'],
						'custom_charge_fees'   => $customCharge['pay_later_custom_charge_fees'],
						'custom_charge_total'  => $customCharge['pay_later_custom_charge_total'],
					];
				}

				// Set payment type while do payment of booking
				if (in_array($paymentMethod, ['Pay Later', 'Invoiced'], true)) {
					$paymentType = 'Invoiced';
				} elseif ($paymentMethod === 'Cash') {
					$paymentType = 'Cash';
				} else {
					$paymentType = 'Card';
				}

				$tourBooking = BookingService::bookingInfoData($bookingId);

				if ($paymentType == "Invoiced" || $paymentType == "Cash") {
					if ($transCount == 0) {
						$transactionId = TransactionService::storePaymentInTransaction($bookingId, $total, $fees, $paymentType);

						$data = [
							'amount' => $total,
							'payment_method' => 'Full Payment'
						];

						$historyId = HistoryService::storeCashPaymentInHistory($tourBooking[0]->id, $tourBooking[0]->tourists, $data, 0 /* $changeAmount */, $createdByWithName);

						$amountUpdate = TourBooking::findOrFail($bookingId);
						$amountUpdate->total = $total;
						$amountUpdate->booking_total = $total;
						$amountUpdate->service_commission = ($isCashFees == '1' && $paymentMethod == 'Cash') ? $serviceCommission : 0;
						$amountUpdate->save();

						// Below line is for to update tour booking history table
						if ($request->webhook != 1 && $transCount == 0) {
							$this->handlePaymentHistoryUpdateJobs($tourBooking, $transactionId, $historyId, $request, $paymentType, $info);
						}

						DB::commit();
					}
				}

				if ($request->api != 1) {
					$booking = CartService::prepareBookingDataForMakeCartPayment($tourBooking, $bookingId, $paymentType, $total);
					CartService::dispatchBooking($booking);
				}
			}

			if ($paymentMethod == 'Card') {
				$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
				if ($paymentIntentId == '') {
					throw new Exception('You must provide Stripe Payment Intent Id');
				}

				$retrieveOptions = ['expand' => ['charges.data.transfer']];
				$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
				$cardDetails = StripeService::extractCardDetails($paymentIntent, $request->has('api'));
				$crd = $cardDetails['last4'];
				$cardType = $cardDetails['brand'];
				$from = $cardDetails['source'];

				if ($request->webhook != 1) {

					$dataForMetadata = [
						'customer_id' => $customer->id,
						'customer_name' => $customer->name,
						'customer_email' => $customer->email,
						'customer_phone' => $customer->phone_number,
						'data' => $cardBookingsData,
						'split_value' => ''
					];
					if ($request->has('api')) {
						$dataForMetadata['App'] = Constants::STATUS_ONE;
						$dataForMetadata['Section'] = config('constants.payment_sections.make_payment');
					}
					$dataForMetadata['From'] = $from;
					$stripeMetadata = CartService::stripeMetadata($dataForMetadata);
					$updateParams = $stripeMetadata;
					app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

					if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
						$chargeParams = $stripeMetadata;
						app(PaymentGatewayInterface::class)->updateConnectedPayment(
							$paymentIntent->charges->data[0]->transfer->destination_payment,
							$chargeParams,
							$paymentIntent->charges->data[0]->transfer->destination
						);
					}
				}

				foreach ($cardBookingsData as $cardBooking) {
					$transactionCount = 0;
					$bookingId = $cardBooking['booking_id'];
					$transactionCount = Transaction::where('transaction_number', $paymentIntentId)
						->where('tour_booking_id', $bookingId)
						->where('status', 'Completed')
						->count();

					if ($request->webhook != 1 && $transactionCount == 0) {
						$tourBooking = BookingService::bookingInfoData($bookingId);
						$transactionId = TransactionService::storePaymentInTransaction(
							$bookingId,
							$cardBooking['total'],
							$cardBooking['serviceCommission'],
							'Card',
							0,
							$paymentIntentId ?? '',
							$paymentIntent->charges->data[0]->application_fee ?? null,
							mode: PaymentModeInference::fromStripeObject($paymentIntent)
						);


						$historyId = HistoryService::storeCardPaymentInHistory($tourBooking[0]->booking_id, $tourBooking[0]->tourists, [] /* $data */, $crd, $cardType, $createdByWithName, $cardBooking, 'Manual Update Make Payment' /* $fromMethod */);

						$amountUpdate = TourBooking::findOrFail($bookingId);
						$amountUpdate->total = $cardBooking['total'];
						$amountUpdate->booking_total = $cardBooking['total'];
						$amountUpdate->service_commission = $cardBooking['serviceCommission'];
						$amountUpdate->save();

						// Below line is for to update tour booking history table
						if ($request->webhook != 1) {
							$tourBooking[0]->tour_operator_id = $cardBooking['tour_operator_id'];
							CardBookingService::run((array)$tourBooking[0], $transactionId, $historyId, 'Full Payment', $request->all(), $paymentIntent);
						}

						DB::commit();
					}
				}
			}

			if (isset($request->api) && $request->api == 1) {
				return [
					'tourBookingId' => $request->bookingID,
				];
			} else {
				return response()->json([
					'tourBookingId' => $request->bookingID
				]);
			}
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();
			HistoryService::storeCardPaymentErrorInHistory($request->bookingID, $exception);

			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				return response()->json([
					'errors' => $exception->getMessage(),
					'operation_at' => mysqlDT(),
				]);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			HistoryService::storeNormalErrorInHistory($request->bookingID, $exception);

			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Handle the queue jobs to perform the history data updates
	 *
	 * @param  mixed  $tourBooking
	 * @param  int     $transactionId
	 * @param  int     $historyId
	 * @param  mixed  $request
	 * @param  string  $paymentType
	 *
	 * @return void
	 * 
	 * @version 1.0.0
	 */
	public function handlePaymentHistoryUpdateJobs($tourBooking, $transactionId, $historyId, $request, $paymentType, $info, $paymentIntent = null): void
	{
		// Check the split payment or full payment
		if (is_array($request)) { // This is for 3d secure payments stripe
			$isSplitOrFull = (!empty($request['split_value'])) ? 'Split Payment' : 'Full Payment';
		} else {
			$isSplitOrFull = !empty($request->split_value) ? 'Split Payment' : 'Full Payment';
		}

		$tourBooking[0]->tour_operator_id = $info->tour_operator_id;
		$isCashFees = cashFees();
		// Perform the jobs based on categories
		if ($paymentType === 'Card' || ($isCashFees == '1' && $paymentType === 'Cash')) {
			CardBookingService::run((array) $tourBooking[0], $transactionId, $historyId, $isSplitOrFull, (!is_array($request)) ? $request->all() : $request, $paymentIntent);
		} elseif ($paymentType === 'Cash') {
			CashBookingService::run($tourBooking[0], $transactionId, $historyId, $isSplitOrFull, $request->all());
		} elseif ($paymentType === 'Invoiced') {
			InvoicedBookingService::run($tourBooking[0], $transactionId, $historyId, $isSplitOrFull, $request->all());
		}
	}

	/**
	 * Save affiliate bookings from calendar
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function saveAffiliateBooking($request)
	{
		try {
			DB::beginTransaction();
			$userType = userType();
			$affiliate = DB::table('customers')
				->where('id', $request->affiliate_id)
				->where('status', 'Active')
				->where('is_affiliate', 'Yes')
				->first();

			if (!$affiliate) {
				throw new Exception('Unable to book/reserve seats or affiliate not found.');
			}

			$createdByWithName = BookingService::createdUserInfo();

			$booking = DB::table('tour_bookings_master')
				->where('tour_slot_id', $request->tour_slot_id)
				->where('customer_id', $request->affiliate_id)
				->where('status', '!=', 'Cancelled')
				->orderBy('id', 'DESC')
				->first(['id', 'tourists', 'created_at', 'status', 'total', 'is_group_booking', 'group_id']);

			// When editing existing booking, exclude it from availability calculation
			$excludeBookingId = $booking ? $booking->id : null;
			$info = CalendarService::getReservedSeats($request, $userType, $excludeBookingId);

			if (!$info) {
				if ($userType == 'Affiliate') {
					throw new Exception('No seats are available for this slot.');
				} else {
					throw new Exception('Unable to book/reserve seats.');
				}
			}

			// Validate that booking is not for a past date/time
			if ($info->package_has_slots && ("{$info->date} {$info->time_from}" <= mysqlDT())) {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}

			BookingUtils::checkAffiliateDurationRestriction($info);

			/* START: Logic to send slot info in helper */
			$isBlackoutDate = isBlackoutDateEnabled($info->date);
			$customRateDate = TourPackageRateService::getCustomRateDate($info, $request);
			/* END */
			if ($isBlackoutDate) {
				if (getNextYearCommissionPercentage($request->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$affiliate->affiliate_processing_percentage = $serviceCommission['service_commission_percentage'];
					$affiliate->stripe_affiliate_percentage = $serviceCommission['stripe_commission_percentage'];
				} else {
					$affiliate->affiliate_processing_percentage = $info->service_commission_percentage;
					$affiliate->stripe_affiliate_percentage = $info->stripe_commission_percentage;
				}
			} else {
				if (getNextYearCommissionPercentage($info->date)) {
					$affiliateCommission = getAffiliateCommissionPercentage($request->affiliate_id);
					$affiliate->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'];
					$affiliate->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
				}
			}

			$seatsToBook = $request->seats;

			$numOfEmptySeats = $info->seats - $info->booked_seats;
			$isGroupRateEnabledForPackage = $info->is_group_rate_enabled;
			$trans = null;
			if ($booking) {
				$trans = Transaction::where('tour_booking_id', $booking->id)->orderBy('id', 'DESC')->first();
				$seatsToBook = $seatsToBook - $booking->tourists;
			}

			if ($request->seats == 0 && $trans == null) {

				BookingUtils::cancelBooking($booking->id, $userType);

				$data = [
					'amount' => $booking->total
				];

				HistoryService::storeCancelledBookingInHistory($booking->id, $booking->tourists, $data, $createdByWithName);
				$seatsDiff = - ($booking->tourists);
				TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $seatsDiff);
				DB::commit();
				TimeSlotService::reopenSlotOnCancellation($info->tour_package_id, $request->tour_slot_id, $booking->id);
				$response = [
					'tourBookingId' => $booking->id,
					'totalBookedSeats' => $info->booked_seats - $booking->tourists,
				];

				if (isset($request->api) && $request->api == 1) {
					return $response;
				} else {
					return response()->json($response);
				}
			} elseif ($request->seats == 0 && $trans != null) {
				if ($trans->via == "Cash" || $trans->via == "Invoiced") {

					BookingUtils::cancelBooking($booking->id, $userType);

					$paymentType = ($trans->via === "Cash") ? "Cash" : "Invoiced";

					$refundTransId = TransactionService::storeRefundInTransaction(
						$booking->id,
						$booking->total,
						0,
						$paymentType,
						'',
						''
					);
					$data = [
						'amount' => $booking->total,
						'refund_keep_us' => 0,
						'payment_type' => $paymentType,
						'refund_type' => "100 % Refund",
					];

					$historyId = HistoryService::storeCancelledBookingInHistory($booking->id, $booking->tourists, $data, $createdByWithName);
					Cancel100RefundService::run($booking, $historyId, $paymentType, $refundTransId);
					$seatsDiff = - ($booking->tourists);
					TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $seatsDiff);
					DB::commit();
					TimeSlotService::reopenSlotOnCancellation($info->tour_package_id, $request->tour_slot_id, $booking->id);
					$response = [
						'tourBookingId' => $booking->id,
						'totalBookedSeats' => $info->booked_seats - $booking->tourists,
					];

					if (isset($request->api) && $request->api == 1) {
						return $response;
					} else {
						return response()->json($response);
					}
				} else {
					BookingUtils::cancelBooking($booking->id, $userType);

					$netAmount = $trans->amount;
					StripeService::processStripeRefund($trans->transaction_number, $netAmount, $trans->payment_mode);

					$refundTransId = TransactionService::storeRefundInTransaction(
						$booking->id,
						$netAmount,
						$trans->fee_amount,
						'Card',
						$trans->transaction_number,
						$trans->application_fee_id,
						mode: $trans->payment_mode
					);

					$data = [
						'amount' => $booking->total,
						'refund_keep_us' => 0,
						'transaction_id' => $refundTransId,
						'payment_type' => 'Card',
						'refund_type' => "100 % Refund",
					];

					$historyId = HistoryService::storeCancelledBookingInHistory($booking->id, $booking->tourists, $data, $createdByWithName);
					Cancel100RefundService::run($booking, $historyId, 'Card', $refundTransId);
					$seatsDiff = - ($booking->tourists);
					TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $seatsDiff);
					DB::commit();
					TimeSlotService::reopenSlotOnCancellation($info->tour_package_id, $request->tour_slot_id, $booking->id);
					$response = [
						'tourBookingId' => $booking->id,
						'totalBookedSeats' => $info->booked_seats - $booking->tourists,
					];

					if (isset($request->api) && $request->api == 1) {
						return $response;
					} else {
						return response()->json($response);
					}
				}
			}

			if ($booking != null && $trans == null) { // if already booked then update
				// Integrate code to check affiliate have any seats remaining for selected slot
				if ($userType == 'Affiliate') {
					CalendarService::checkSeatAvailability($info, $request);
				} else {
					$tourists = (int)$info->booked_seats;
					$seats = $info->seats;
					$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;
				}

				$isGroupBooking = $booking->is_group_booking;

				$qryM = DB::table('tour_bookings_master AS tbm')
					->where('tbm.id', $booking->id);

				$qryT = DB::table('tour_bookings_transact AS tbt')
					->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
					->where('tbt.tour_booking_id', $booking->id)
					->where('tbt.deleted_by', 0);

				if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
					$qryM = $qryM->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
						->where('tp.tour_operator_id', Auth::user()->tour_operator_id);

					$qryT = $qryT->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
						->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
				}

				$rateGroup = $qryT->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
					->first(['tpr.id', 'tpr.rate_for', 'tpr.rate', 'tpr.additional_tax_percentage', 'tpr.processing_charge_percentage', 'tpr.additional_charge', 'tpr.min_pax_allowed', 'tpr.max_pax_allowed']);

				if (!$rateGroup) {
					throw new Exception('Unable to update affiliate booking data.');
				}

				if ($customRateDate) {
					$customRateGroup = DB::table('tour_package_rates')
						->where('tour_package_id', $info->tour_package_id)
						->where('status', 'Active')
						->when($customRateDate, function ($query) use ($customRateDate) {
							return $query->where('custom_date_id', $customRateDate);
						}, function ($query): mixed {
							return $query->whereNull('custom_date_id');
						})
						->where('scheduling_year', date('Y', strtotime($info->date)))
						->first(['id', 'rate_for', 'rate', 'additional_tax_percentage', 'processing_charge_percentage', 'additional_charge', 'min_pax_allowed', 'max_pax_allowed']);

					if (!$customRateGroup) {
						throw new Exception('Unable to update affiliate booking data.');
					}

					if ($rateGroup->id != $customRateGroup->id) {
						$rateGroup = $customRateGroup;
					}

					$customDateRateFieldValueT = [
						'tbt.tour_package_rate_id' => $rateGroup->id,
						'tbt.additional_tax_percentage' => ($isGroupRateEnabledForPackage != '1') ? $rateGroup->additional_tax_percentage : 0,
						'tbt.additional_charge' => $rateGroup->additional_charge,
						'tbt.rate' => $rateGroup->rate,
						'tbt.is_group_rate' => ($isGroupRateEnabledForPackage == '1') ? 1 : 0
					];
				}

				$updateTotal = $stateTax = 0;
				$discountAmt = 0;
				$newRateGroupId = 0;
				if ($isGroupBooking == '1') {
					$rateGroupNewData = TourPackageRateService::getUpdatedRateGroupForGroupBookingAffiliate($info, $request, $customRateDate, $rateGroup);
					$newRateGroupId = $rateGroupNewData['rate_group_id'];
					$updateTotal = $rateGroupNewData['updated_total'];
				} else {
					$updateTotal = (
						($rateGroup->rate) + ($rateGroup->additional_charge)
						+ $info->permit_fee
					);

					if ($updateTotal > 0) {
						$updateTotal += (float)($updateTotal) *  ((float)$rateGroup->additional_tax_percentage) / 100;

						$updateTotal = $updateTotal * $request->seats;
						$updateTotal = number_format((float)$updateTotal, 2, '.', '');
					}
				}

				$updateDT = mysqlDT();

				$uFieldValueM = [
					'tbm.updated_at' => $updateDT,
					'tbm.updated_by' => Auth::id(),
					'tbm.updated_by_user_type' => $userType,
				];

				$uFieldValueT = [
					'tbt.updated_at' => $updateDT,
					'tbt.updated_by' => Auth::id(),
					'tbt.updated_by_user_type' => $userType,
				];

				if (isset($customDateRateFieldValueT)) {
					$uFieldValueT = $uFieldValueT + $customDateRateFieldValueT;
				}

				if ($request->seats) {
					$qryM->update([
						'tbm.tourists' => $request->seats,
						'tbm.total' => $updateTotal,
						'tbm.booking_total' => $updateTotal,
						'tbm.status' => DB::raw("IF(tbm.status = 'Cancelled', 'Booked', tbm.status)"),
					] + $uFieldValueM);

					if ($isGroupBooking == '1') {
						$qryT->update(['tbt.tour_package_rate_id' => $newRateGroupId, 'tbt.rate' => $updateTotal, 'tbt.tourists' => $request->seats, 'tbt.total' => $updateTotal] + $uFieldValueT);
					} else {
						$qryT->update(['tbt.tourists' => $request->seats, 'tbt.total' => $updateTotal] + $uFieldValueT);
					}
					$addedPeoplesText[] = ($isGroupBooking == '1') ? 'Person(s) ' . $request->seats : $rateGroup->rate_for . " person " . $request->seats;

					DB::commit();
					$tbh = TourBookingHistory::where('tour_booking_id', $booking->id)->where('operation', 'Added')->first();

					if (!empty($tbh)) {
						$tbh->tourists = $request->seats;
						$tbh->details = json_encode([
							'sub_operation' => 'Tourists Added',
							'discount_percentage' => $request->discount_percentage ?: 0,
							'discount2_value' => 0,
							'added' => $addedPeoplesText,
							'newTourists' => $request->seats,
							'created_by_with_name' => $createdByWithName
						], JSON_NUMERIC_CHECK);
						$tbh->save();
					} else {
						HistoryService::storeTouristInHistory(
							$request,
							$booking,
							0, /* $discountAmt */
							$addedPeoplesText,
							$request->seats,
							$createdByWithName
						);
					}
					DB::commit();

					$seatsDiff = $request->seats - $booking->tourists;
				} else { // zero seats then cancel the booking
					$qryM->update([
						'tbm.status' => 'Cancelled',
					] + $uFieldValueM);

					$qryT->update(['tbt.tourists' => 0,] + $uFieldValueT);
					DB::commit();
					TimeSlotService::reopenSlotOnCancellation($info->tour_package_id, $request->tour_slot_id, $booking->id);
					$seatsDiff = -$booking->tourists;
				}
			} else { // add new booking
				if ($request->seats == 0)
					throw new Exception('No seat was booked for the affiliate so no seat is added/updated.');

				if ($userType == 'Affiliate') {
					CalendarService::checkSeatAvailability($info, $request);
				} else {
					$tourists = (int)$info->booked_seats;
					$seats = $info->seats;
					$availableSeats = $tourists > $seats ? 0 : $seats - $tourists;
				}

				$emptySeats =  $numOfEmptySeats + $info->overbook_seats;

				if ($seatsToBook > $emptySeats) {
					throw new Exception('Unable to add affiliate booking data.Please add less no seat.');
				}

				$rateGroup = TourPackageRateService::getApplicableRateGroupAffiliate($info, $request, $isGroupRateEnabledForPackage, $customRateDate);
				if (!$rateGroup) {
					throw new Exception('No rate groups found. Please add rate groups for the affiliate.');
				}

				$groupTotal = $stateTax =  0;
				$discountAmt = 0;

				// Calculate base group total
				if ($isGroupRateEnabledForPackage) {
					$groupTotal = $rateGroup->rate + $rateGroup->additional_charge;
				} else {
					$groupTotal = ($request->seats * ($rateGroup->rate + $rateGroup->additional_charge + $info->permit_fee)) + $stateTax;
				}

				// Apply discount if available
				if ($request->has('discount_type')) {
					if ($request->discount_type === 'Percentage' && !empty($request->discount_percentage)) {
						$discountAmt = round(($groupTotal * $request->discount_percentage) / 100, 2);
					} elseif ($request->discount_type === 'Fixed Money' && !empty($request->discount_fixed_money)) {
						$discountAmt = round($request->discount_fixed_money, 2);
					}
				}

				// Deduct discount and format the total amount
				$groupTotal = round($groupTotal - $discountAmt, 2);

				$updateTotal = $groupTotal;
				/* START : code for get expire at */
				$expiryDate = BookingService::calculateExpiryDate($info->tour_package_id, $request->affiliate_id);
				/* END : code for get expire at */

				$booking = $this->tourBooking::create([
					'tour_package_id' => $info->tour_package_id,
					'tour_slot_id' => $request->tour_slot_id,
					'customer_id' => $request->affiliate_id,
					'is_reserved_for_affiliate' => Constants::STATUS_ONE,
					'name' => $affiliate->name,
					'email' => $affiliate->email,
					'phone_number' => $affiliate->phone_number,
					'tourists' => $request->seats,
					'discount2_value' => $discountAmt,
					'discount2_percentage' => empty($request->discount_percentage) ? 0 : $request->discount_percentage,
					'total' => $groupTotal,
					'booking_total' => $groupTotal,
					'status' => 'Booked',
					'is_group_booking' => ($isGroupRateEnabledForPackage == '1') ? 1 : 0,
					'visitor' => $request->getClientIp(),
					'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
					'expire_at' => $expiryDate,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'updated_at' => null
				]);

				$tbTransaction = $this->bookingTransact::create([
					'tour_booking_id' => $booking->id,
					'tour_package_rate_id' => $rateGroup->id,
					'tourists' => $request->seats,
					'processing_charge_percentage' => 0,
					'nat_fees_percentage' => $affiliate->affiliate_processing_percentage,
					'stripe_fees_percentage' => $affiliate->stripe_affiliate_percentage,
					'additional_tax_percentage' => ($isGroupRateEnabledForPackage != '1') ? $rateGroup->additional_tax_percentage : 0,
					'state_tax' => $stateTax,
					'total' => $groupTotal,
					'additional_charge' => $rateGroup->additional_charge,
					'permit_fee' => ($isGroupRateEnabledForPackage == '1') ? 0 : $info->permit_fee,
					'rate' => $rateGroup['rate'],
					'is_group_rate' => ($isGroupRateEnabledForPackage == '1') ? 1 : 0,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
				]);

				/* START - Added this to place the history of person added */
				$addedPeoplesText = [];
				if (!empty($tbTransaction)) {
					$addedPeoplesText[] = ($isGroupRateEnabledForPackage == '1') ? 'Person(s) ' . $tbTransaction['tourists'] : $rateGroup->rate_for . " person " . $tbTransaction['tourists'];
				}

				HistoryService::storeTouristInHistory($request, $booking, 0 /* $discountAmt */, $addedPeoplesText, $request->seats, $createdByWithName);
				/* END - Added this to place the history of person added */
				DB::commit();

				// Close the time slot on booking
				TimeSlotService::closeSlotOnBooking($booking->tour_package_id, $booking->tour_slot_id);
				$seatsDiff = $request->seats;
			}

			$minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO]);
			if ($minMaxPaxApply && $rateGroup->min_pax_allowed > $request->seats) {
				throw new Exception("Minimum $rateGroup->min_pax_allowed peoples are required.");
			}

			if ($minMaxPaxApply && $rateGroup->max_pax_allowed && $rateGroup->max_pax_allowed < $request->seats) {
				throw new Exception("Maximum $rateGroup->max_pax_allowed peoples are allowed.");
			}

			$tGroupsChanged = BookingService::tGroupsString($booking->id);

			$arEmailData = [
				'tour_d_t' => $info->tour_d_t,
				'bookingId' => $booking->id,
				'bookingGroupId' => $booking->group_id,
				'custName' => $affiliate->name,
				'custEmail' => $affiliate->email,
				'custPhone' => $affiliate->phone_number,
			];

			$affiliate = $this->customer::find($affiliate->id);
			if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
				EmailService::sendAcknowledgementEmail($info, $arEmailData);
			}

			TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $booking->tourists);

			BookingService::dispatchAffiliateBookingNotification(
				$booking,
				$request,
				$affiliate,
				$info,
				$seatsDiff,
				$updateTotal,
				$groupTotal ?? null,
				$tGroupsChanged ?? null
			);

			DB::commit();

			if (isset($request->api) && $request->api == 1) {
				return [
					'tourBookingId' => $booking->id,
					'totalBookedSeats' => $info->seats - $numOfEmptySeats + $seatsToBook
				];
			} else {
				if ($userType == 'Affiliate') {
					$numOfEmptySeats = $info->afl_seats - $info->afl_booked_seats;
					$returnArr = $info->afl_seats - $numOfEmptySeats + $seatsToBook;
				} else {
					$returnArr = $info->seats - $numOfEmptySeats + $seatsToBook;
				}

				return response()->json([
					'tourBookingId' => $booking->id,
					'totalBookedSeats' => $returnArr
				]);
			}
		} catch (Exception $exception) {
			DB::rollback();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Check the overbooking for the specific slot user trying to book
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function checkOverbooking($request)
	{
		try {
			$userType = userType();

			$affiliateSql = '';
			$affiliateSeats = DB::raw('0 as afl_seats');
			$showAllPackageBookings = 1;
			$packageId = ($request->has('package_id')) ? $request->package_id : '';

			if ($userType == 'Affiliate') {
				$showAllPackageBookings = PackageAffiliateAssignment::where('package_id', $packageId)
					->where('affiliate_id', Auth::id())
					->where('show_all_package_bookings', Constants::STATUS_ONE)
					->count();
				if ($showAllPackageBookings == Constants::STATUS_ZERO) {
					$affiliateSql = ' AND tbm2.customer_id = ' . Auth::id();
				}
			}

			$info = DB::table('tour_slot_times AS tst')
				->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
				->where('tst.id', $request->slot_time)
				->select([
					'tst.date',
					'tst.time_slot_seats as seats',
					'tp.overbook_seats',
					'tst.time_from',
					'tst.time_to',
					'tst.tour_package_id',
					DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id),0) AS booked_seats"),
					DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
	                    FROM tour_bookings_master AS tbm2
	                    WHERE tbm2.tour_slot_id = tst.id
	                        AND tbm2.status != 'Cancelled'
	                    " . $affiliateSql . "
	                    GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_booked_seats"),
					DB::raw("IFNULL((SELECT DISTINCT(tpf.is_parent)
						FROM tour_package_families AS tpf
						WHERE tpf.tour_package_id = tst.tour_package_id
							AND tst.time_from = tpf.time_from), 0) AS is_parent"),
					DB::raw("IFNULL((SELECT DISTINCT(tpf.group_index)
						FROM tour_package_families AS tpf
						WHERE tpf.tour_package_id = tst.tour_package_id
							AND tst.time_from = tpf.time_from), 0) AS group_index")
				])
				->first();

			/* START - mapping tour package and reducing the relational seats */
			$packageRelationSetting = hasSetting('PACKAGE_RELATION');
			$tourPackageFamily = [];
			if ($packageRelationSetting) {
				$tourPackageFamily = DB::select("SELECT *
					FROM tour_package_families
					WHERE parent_package_id IN (
						SELECT parent_package_id
						FROM tour_package_families
						WHERE tour_package_id = $info->tour_package_id
					)");
			}

			$relationPackage = [];
			$totalTourists = 0;
			if ($packageRelationSetting && count($tourPackageFamily)) {
				$relationPackage = array_filter($tourPackageFamily, function ($obj) use ($info) {
					return $obj->tour_package_id == $info->tour_package_id && $obj->group_index == $info->group_index;
				});

				$totalTourists = TouristService::countTotalTourists($tourPackageFamily, $info);
			}
			/* END */

			// Only for affiliate
			if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
				$affiliate = $this->customer::findOrFail(Auth::id());
				$affiliateTimeSlots = DB::table('tour_slot_times_affiliate as tsta')
					->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
					->where('ast.affiliate_id', $affiliate->id)
					->where('tsta.tour_package_id', $packageId)
					->where('tsta.time_from', $info->time_from)
					->where('tsta.status', 'Active')
					->where('tsta.deleted_by', 0)
					->select('tsta.id as time_slot_id', 'tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
					->get()
					->toArray();
			}

			if ($userType == 'Affiliate') {
				$info->overbook_seats = 0;
				// Affiliates sharing the overall pool (show_all_package_bookings == 1) have
				// no dedicated per-slot seat block, so their limit IS the tour's total
				// capacity — not 0. Affiliates with a dedicated block (== 0) get their
				// real allocation below. Leaving this at 0 for the pooled case makes
				// checkOverbooking() always report "no seats available" for them.
				$info->afl_seats = ($showAllPackageBookings == Constants::STATUS_ONE) ? $info->seats : 0;
			}

			if ($totalTourists > 0 && !empty($relationPackage)) {
				$info->booked_seats = $totalTourists;
			}
			if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
				$affiliateTimeSlots = (isset($affiliateTimeSlots) && !empty($affiliateTimeSlots)) ? $affiliateTimeSlots : [];
				$desiredSlot = collect($affiliateTimeSlots)->first(function ($slot) use ($info) {
					return $slot->time_from === $info->time_from;
				});

				$info->afl_seats = isset($desiredSlot->seat_limit) ? (($info->seats > 0) ? (($info->seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($info->seats)) : 0) : 0;
			}

			if ($request->has('affiliate_id')) {
				$info->affiliate_tourists = 0;
				$booking = DB::table('tour_bookings_master')
					->where('tour_slot_id', $request->slot_time)
					->where('customer_id', $request->affiliate_id)
					->where('status', '!=', 'Cancelled')
					->orderBy('id', 'DESC')
					->first(['id', 'tourists', 'created_at', 'status', 'total']);

				if ($booking) {
					$trans = Transaction::where('tour_booking_id', $booking->id)->orderBy('id', 'DESC')->first();
					if ($trans == null) {
						$info->affiliate_tourists = $booking->tourists;
					} else {
						$info->affiliate_tourists = 0;
					}
				}
			}

			return $info;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * View and print the invoice of booking
	 *
	 * @param int $id
	 * 
	 * @version 1.0.0
	 */
	public function bookingInvoicePrint(int $id)
	{
		try {
			$bookingM = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->leftJoin('tour_promotions AS promo', 'promo.id', '=', 'tbm.tour_promotion_id')
				->where('tbm.id', $id)
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
					'tbm.is_group_booking',
					'tp.name AS tour_package',
					'tp.package_has_slots',
					'tp.package_has_waiver',
					'tp.package_has_permit',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.logo',
					't_o.tenant_id',
					'tsd.date',
					'tst.time_from',
					'tst.time_to',
					'tbm.name AS customer',
					'tbm.email AS cust_email',
					'tbm.phone_number AS cust_phone',
					'promo.discount_value',
					'promo.discount_value_type',
					'tbm.booking_total'
				]);

			if (!$bookingM) {
				throw new Exception("Tour booking not found.");
			}

			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $id)
				->where('tbt.deleted_by', 0)
				->orderBy('tpr.orderby', 'asc')
				->get([
					'tbt.id',
					'tbt.tourists',
					'tbt.processing_charge_percentage',
					'tbt.nat_fees_percentage',
					'tbt.additional_tax_percentage',
					'tbt.permit_fee',
					'tbt.additional_charge',
					'tbt.state_tax',
					'tbt.rate',
					'tbt.total',
					'tpr.rate_for'
				]);

			if (!$bookingT) {
				abort(500, "Unable to get booking's information.");
			}

			$history = DB::table('tour_booking_history')
				->orderByDesc('operation_at')
				->where('tour_booking_id', $id)
				->get();

			$transaction = Transaction::where('tour_booking_id', $id)->first();
			$trans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
			$HistoryPaymentTime = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->first();
			$refundAmountSwitchPackage = 0;

			if (!empty($HistoryPaymentTime)) {
				$TourBookingHistory = TourBookingHistory::where('tour_booking_id', $id)->where('operation_at', '>', $HistoryPaymentTime->operation_at)
					->where(function ($query) {
						return $query->where('operation', '=', 'Refund')
							->orWhere('operation', '=', 'Cancelled');
					})
					->whereNotNull('payment_type')
					->where('payment_type', '!=', '')
					->get();
				if (count($TourBookingHistory) > 0) {
					foreach ($TourBookingHistory as $key => $bookingHistory) {
						if ($bookingHistory->package_swipe_history_id != '') {
							$refundAmountSwitchPackage = $refundAmountSwitchPackage + ($bookingHistory->ticket_cost + $bookingHistory->processing_fees + $bookingHistory->platform_fees);
						}
					}
				}
			}

			$paymentOnly = TourBookingHistory::where('tour_booking_id', $id)->where('operation', 'Payment')->get();
			$paymentTrans = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->get(['id', 'tour_booking_id', 'amount', 'fee_amount', 'transaction_number', 'application_fee_id', 'via', 'payment_type', 'status']);

			$paymentTransCount = count($paymentTrans);
			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$paymentOnlyCheck += $details->amount;
			}

			$pricedAddons = BookingService::fetchAddonsWithPrice($id);
			$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($id, $bookingT[0]->nat_fees_percentage, $trans);

			$paymentOnlyCheck = $paymentOnlyCheck - $refundAmountSwitchPackage;
			$transP = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'ASC')->first();

			// Custom charge service to calculate custom charge if applied
			$customChargePrice = CustomChargeBookingService::customChargePrice($id, $paymentTrans);

			$totalDiscountPayment = BookingService::calculateDiscountpayment($id);

			$vars = [
				'page_title' => 'Tour Booking Info',
				'bookingM' => $bookingM,
				'bookingT' => $bookingT,
				'history' => $history,
				'transaction' => $transaction,
				'TourBookingHistory' => $TourBookingHistory ?? '',
				'HistoryPaymentTime' => $HistoryPaymentTime ?? '',
				'trans' => $trans,
				'paymentOnlyCheck' => $paymentOnlyCheck,
				'transP' => $transP,
				'paymentTransCount' => $paymentTransCount,
				'isComboBookings' => false,
				'pricedAddons' => $pricedAddons,
				'payLaterAddons' => $payLaterAddons,
				'customChargePrice' => $customChargePrice,
				'totalDiscountPayment' => $totalDiscountPayment
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * View and print the invoice of combo booking
	 *
	 * @param string $id
	 * 
	 * @version 1.0.0
	 */
	public function comboBookingInvoice(string $id, $request = [])
	{
		try {
			$tourBooking = TourBooking::where('cart_id', $id)->first(['id', 'cart_id']);
			if (!$tourBooking) {
				throw new Exception("Tour booking not found.");
			}
			if ($tourBooking->cart_id != '') {
				$comboBookings = [];
				$comboSubtotal = 0;
				$comboBookingFeesTotal = 0;
				$comboBookingTotal = 0;
				$comboRefundTotal = 0;
				$comboNonRefundableFeesTotal = 0;
				$comboPaymentTotal = 0;
				$logo = $tourOpPhone = $tourOpEmail = $tourOpPhoneEmail = $custPhoneEmail = $custPhone = $custEmail = '';
				$cartBookings = TourBooking::where('cart_id', $tourBooking->cart_id)->get(['id']);
				if ($cartBookings) {
					foreach ($cartBookings as $cartBooking) {
						$bookingM = DB::table('tour_bookings_master AS tbm')
							->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
							->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
							->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
							->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
							->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
							->leftJoin('tour_promotions AS promo', 'promo.id', '=', 'tbm.tour_promotion_id')
							->where('tbm.id', $cartBooking->id)
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
								'tp.package_has_waiver',
								'tp.package_has_permit',
								't_o.name AS tour_operator',
								't_o.email AS tour_op_email',
								't_o.phone_number AS tour_op_phone',
								't_o.logo',
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
								'tbm.is_group_booking'
							]);
						$bookingT = DB::table('tour_bookings_transact AS tbt')
							->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
							->where('tbt.tour_booking_id', $cartBooking->id)
							->where('tbt.deleted_by', 0)
							->orderBy('tpr.orderby', 'asc')
							->get([
								'tbt.id',
								'tbt.tourists',
								'tbt.processing_charge_percentage',
								'tbt.additional_tax_percentage',
								'tbt.nat_fees_percentage',
								'tbt.permit_fee',
								'tbt.additional_charge',
								'tbt.state_tax',
								'tbt.rate',
								'tbt.total',
								'tpr.rate_for'
							]);

						$trans = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
						$HistoryPaymentTime = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation', 'Payment')->first();

						if (!empty($HistoryPaymentTime)) {
							$TourBookingHistory = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation_at', '>', $HistoryPaymentTime->operation_at)
								->where(function ($query) {
									return $query->where('operation', '=', 'Refund')
										->orWhere('operation', '=', 'Cancelled');
								})
								->get();
						}

						$paymentOnly = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation', 'Payment')->get();
						$paymentTrans = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->get(['id', 'tour_booking_id', 'amount', 'fee_amount', 'transaction_number', 'application_fee_id', 'via', 'payment_type', 'status']);

						$paymentTransCount = count($paymentTrans);
						$paymentOnlyCheck = 0;
						foreach ($paymentOnly as $value) {
							$details = json_decode($value->details);
							$paymentOnlyCheck += $details->amount;
						}

						$transP = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->orderBy('id', 'ASC')->first();


						$promoDiscount = 0;

						if ($bookingM->discount2_percentage > 0) {
							$promoDiscount = str_replace('.00', '', $bookingM->discount2_value) . '%';
						} elseif ($bookingM->discount2_value > 0) {
							$promoDiscount = currencyFormation(code: false, symbol: true) . $bookingM->discount2_value;
						}

						$totalBeforeServiceCommission = $bookingM->total - $bookingM->service_commission;
						$serviceCommissionPercentage = 0;
						if ($totalBeforeServiceCommission != 0) {
							$serviceCommissionPercentage = round($bookingM->service_commission * 100 / $totalBeforeServiceCommission, 2);
						}

						$amount = 0;
						$refund_keep_us = 0;
						$deletedAgeGroup = [];

						if (!empty($trans)) {
							$paidF = $paymentOnlyCheck;
						} else {
							$paidF = 0;
						}

						if (!empty($TourBookingHistory)) {
							foreach ($TourBookingHistory as $BookingHistory) {
								$details = json_decode($BookingHistory->details);

								if (isset($details->refund_type) && !in_array(strtolower($details->refund_type), ["don't refund", "partial refund"])) {
									$amount += $details->amount;

									if (isset($details->deleted)) {
										$deletedAgeGroup[] = $details->deleted;
									}
								}

								$details->refund_keep_us = $details->refund_keep_us ?? 0;
								if (isset($details->refund_type) && strtolower($details->refund_type) !== "partial refund") {
									$refund_keep_us += $details->refund_keep_us;
								}


								$paidF = $paymentOnlyCheck - $amount;
								if (!empty($HistoryPaymentTime)) {
									$checkAmount = json_decode($HistoryPaymentTime->details);
									if ($checkAmount->amount == $amount) {
										$paidF = 0;
									}
								}
							}
						}

						$resultArray = [];
						if (count($deletedAgeGroup)) {
							$personCount = [];
							foreach ($deletedAgeGroup as $entry) {
								// Get the prefix text "Adults (Ages 13 & Up) person"
								$prefix = substr($entry, 0, strpos($entry, "person"));

								// Extract the person number from the entry and convert to integer
								$personNumber = intval(substr($entry, strrpos($entry, " ") + 1));

								// Check if the entry with the same prefix exists in the person count array
								if (!isset($personCount[$prefix])) {
									// Create a new entry in the person count array
									$personCount[$prefix] = 0;
								}

								// Increment the sum of person numbers for the existing entry
								$personCount[$prefix] += $personNumber;
							}

							// Convert the person count array to the desired format
							foreach ($personCount as $prefix => $count) {
								$resultArray[] = $prefix . " " . $count;
							}
						}

						if ($bookingM->status == "Cancelled") {
							if (count($resultArray) > 0) {
								foreach ($resultArray as $key => $ar) {
									$rate = substr($ar, 0, strripos($ar, " "));
									$rateNumber = intval(substr($ar, strrpos($ar, " ") + 1));
									$ratespacetrim = rtrim($rate);

									foreach ($bookingT as $bt) {
										if ($ratespacetrim == $bt->rate_for) {
											$totaltourists = $rateNumber + $bt->tourists;
											unset($resultArray[$key]);
										}

										if (!empty($totaltourists)) {
											$resultArray[] = $bt->rate_for . " " . $totaltourists;
										} else {
											$resultArray[] = $bt->rate_for . " " . $bt->tourists;
										}
									}
								}
							} else {
								foreach ($bookingT as $bt) {
									$resultArray[] = $bt->rate_for . " " . $bt->tourists;
								}
							}
						}

						$ageGroup = (!empty($resultArray)) ? implode("<br>", $resultArray) : null;

						$bookingTotal = 0;
						$refundKeepUs = 0;
						$refundAmount = 0;
						$paymentTotal = 0;
						if ((!empty($trans) && $transP->payment_type == 1) || (!empty($trans) && ($transP->payment_type == 0 || $transP->payment_type == '') && $paymentTransCount > 1)) {
							$bookingTotal = $bookingM->booking_total;
							$comboBookingTotal += $bookingM->booking_total;
						} elseif (!empty($checkAmount)) {
							$bookingTotal = $checkAmount->amount;
							$comboBookingTotal += $checkAmount->amount;
						} else {
							$bookingTotal = $bookingM->total;
							$comboBookingTotal += $bookingM->total;
						}

						if ($refund_keep_us) {
							$refundKeepUs = $refund_keep_us;
							$comboNonRefundableFeesTotal += $refund_keep_us;
						}
						if ($amount) {
							$refundAmount = $amount;
							$comboRefundTotal += $amount;
						}
						$paymentTotal = ($paidF > 0) ? $paidF : 0.00;

						$comboPaymentTotal += $paidF;
						$comboSubtotal += ($bookingM->total - $bookingM->service_commission);
						$comboBookingFeesTotal += $bookingM->service_commission;

						// Add-on 
						$pricedAddons = BookingService::fetchAddonsWithPrice($bookingM->id);
						$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($bookingM->id, $bookingT[0]->nat_fees_percentage, $trans);

						// Custom charge service to calculate custom charge if applied
						$customChargePrice = CustomChargeBookingService::customChargePrice($bookingM->id, $paymentTrans);

						$comboBookings[] = [
							'bookingM' => $bookingM,
							'bookingT' => $bookingT,
							'ageGroup' => $ageGroup,
							'customChargePrice' => $customChargePrice,
							'pricedAddons' => $pricedAddons,
							'payLaterAddons' => $payLaterAddons,
							'bookingTotal' => number_format((float)$bookingTotal, 2, '.', ''),
							'refundKeepUs' => number_format((float)$refundKeepUs, 2, '.', ''),
							'refundAmount' => number_format((float)$refundAmount, 2, '.', ''),
							'paymentTotal' => number_format((float)$paymentTotal, 2, '.', '')
						];
					}
				}

				$bookingM = $comboBookings[0]['bookingM'];

				if ($bookingM->logo != '')
					$logo = tourOperatorLogo($bookingM->tenant_id, $bookingM->logo);

				if ($bookingM->tour_op_phone != '' && $bookingM->tour_op_email != '')
					$tourOpPhoneEmail = e($bookingM->tour_op_phone) . '<br>' . e($bookingM->tour_op_email);
				elseif ($bookingM->tour_op_email != '')
					$tourOpPhoneEmail = e($bookingM->tour_op_email);
				elseif ($bookingM->tour_op_phone != '')
					$tourOpPhoneEmail = e($bookingM->tour_op_phone);

				if ($bookingM->cust_phone != '' && $bookingM->cust_email != '')
					$custPhoneEmail = e($bookingM->cust_phone) . '<br>' . e($bookingM->cust_email);
				elseif ($bookingM->cust_email != '')
					$custPhoneEmail = e($bookingM->cust_email);
				elseif ($bookingM->cust_phone != '')
					$custPhoneEmail = e($bookingM->cust_phone);

				$tourOpPhone = $bookingM->tour_op_phone;
				$tourOpEmail = $bookingM->tour_op_email;
				$custPhone = $bookingM->cust_phone;
				$custEmail = $bookingM->cust_email;

				if (isset($request['api']) && $request['api'] == '1') {
					$vars = [
						'comboBookings' => $comboBookings,
						'logo' => $logo,
						'tourOpPhone' => $tourOpPhone,
						'tourOpEmail' => $tourOpEmail,
						'custPhone' => $custPhone,
						'custEmail' => $custEmail,
						'tourOperator' => $bookingM->tour_operator,
						'comboSubtotal' => number_format((float)$comboSubtotal, 2, '.', ''),
						'comboBookingFeesTotal' => number_format((float)$comboBookingFeesTotal, 2, '.', ''),
						'comboBookingTotal' => number_format((float)$comboBookingTotal, 2, '.', ''),
						'comboNonRefundableFeesTotal' => number_format((float)$comboNonRefundableFeesTotal, 2, '.', ''),
						'comboRefundTotal' => number_format((float)$comboRefundTotal, 2, '.', ''),
						'comboPaymentTotal' => number_format((float)$comboPaymentTotal, 2, '.', ''),
					];
				} else {
					$vars = [
						'page_title' => 'Tour Booking Info',
						'comboBookings' => $comboBookings,
						'logo' => $logo,
						'tourOpPhoneEmail' => $tourOpPhoneEmail,
						'custPhoneEmail' => $custPhoneEmail,
						'tourOperator' => $bookingM->tour_operator,
						'comboSubtotal' => $comboSubtotal,
						'comboBookingFeesTotal' => $comboBookingFeesTotal,
						'comboBookingTotal' => $comboBookingTotal,
						'comboNonRefundableFeesTotal' => $comboNonRefundableFeesTotal,
						'comboRefundTotal' => $comboRefundTotal,
						'comboPaymentTotal' => $comboPaymentTotal,
					];
				}
			}
			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Build this method to store the booking before capture payment
	 *
	 * @param object $request
	 * 
	 * @version 1.0.0
	 */
	public function cardReaderBeforeCapture($request)
	{
		DB::beginTransaction();
		try {
			$this->transformer->transformRequestData($request);

			$totalTourists = 0;

			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$totalTourists += $request->tourists[$a];
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one adult.');
			}

			$userType = userType();
			$info = TourPackageService::getTourSlotInfo($request->slot_time, $userType);
			if (!$info) {
				throw new Exception('Unable to book the tour package.');
			}

			if ($info->package_has_slots && ("{$info->date} {$info->time_from}" <= mysqlDT())) {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}

			$isGroupRateEnabledForPackage = $info->is_group_rate_enabled;
			$isGroupRate = 0;
			$minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO]);
			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$result = TourPackageRate::find($request->rate_group[$a]);
				$isGroupRate = $result->is_group_rate;
				if ($minMaxPaxApply && $result->min_pax_allowed > $request->tourists[$a]) {
					throw new Exception("Please select a minimum of $result->min_pax_allowed $result->rate_for people to process your booking.");
				}
			}

			$isReserveForAffiliate = $request->is_reserved_for_affiliate ?? 0;
			if ($request->has('is_reserved_for_affiliate') && $request->is_reserved_for_affiliate == 1) {
				$ReserveBooking = TourBooking::whereDoesntHave('transactions')->where('tour_package_id', $info->tour_package_id)->where('is_reserved_for_affiliate', '1')->where('customer_id', $request->affiliatecust)->where('tour_slot_id', $request->slot_time)->where('status', 'Booked')->count();
				if ($ReserveBooking > 0) {
					throw new Exception('Unable to book the tour package.You have already reserve booking for the slot');
				}
			}
			$rateGrpIds = [];
			$permitfeeAr = [];

			for ($a = 0, $c = count($request->tourists); $a < $c; $a++) {
				if ($request->tourists[$a]) {
					$x = $a;
					$rateGrpIds[] = $request->rate_group[$a];
					// Affiliates cannot set the permit fee: force the rate's value (server source of truth),
					// which blocks the client from injecting a stale/wrong permit fee. Tour Operators/Admins
					// may legitimately waive the fee, so keep their posted value.
					$permitfeeAr[] = (userType() === 'Affiliate')
						? (float) (DB::table('tour_package_rates')->where('id', $request->rate_group[$a])->value('permit_fee') ?? 0)
						: ($request->permit_fee[++$x] ?? 0);
				}
			}
			if ($request->customer_email == '') {
				throw new Exception('Please enter email address.');
			}

			$rateGroups = DB::table('tour_package_rates AS tpr')
				->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
				->whereIn('tpr.id', $rateGrpIds)
				->where('tpr.deleted_by', 0)
				->orderBy('tpr.orderby', 'asc')
				->get([
					'tpr.id',
					'tpr.rate_for',
					'tpr.rate',
					'tpr.additional_tax_percentage',
					'tpr.processing_charge_percentage',
					'tpr.additional_charge'
				]);

			$customer = $this->customer::where('email', $request->customer_email)
				->where('tour_operator_id', $info->tour_operator_id)
				->first();

			if (!$customer && $request->affiliatecust == 0) {
				$pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
				$pwd = '';

				for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
					$pwd .= $pwdChars[rand(0, $cl)];
				}

				$customer = $this->customer::create([
					'tour_operator_id' => $info->tour_operator_id,
					'name' => $request->customer_name,
					'email' => $request->customer_email,
					'phone_number' => str_replace(['(', ')', '+', '-', '.', ' '], '', $request->customer_phone_number),
					'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
				]);
				DB::commit();
			}

			$isBlackoutDate = isBlackoutDateEnabled($info->date);
			$total = $discountAmt = 0;
			$tbTransact = $touristGroups = [];

			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				if (!$request->tourists[$a])
					continue;

				$tourists = $request->tourists[$a];
				$groupTotal = 0;

				for ($b = 0, $bc = count($rateGroups); $b < $bc; $b++) {
					if ($request->rate_group[$a] == $rateGroups[$b]->id) {
						$touristGroups[] = $rateGroups[$b]->rate_for[0] . ':' . $tourists;
						break;
					}
				}

				if ($b >= $bc) { // rate group not found
					DB::rollBack();
					throw new Exception('Unable to book the tour package.');
				}

				// rate may be zero for one or more age/rate group and
				// if rate is non-zero then only add other amounts
				if ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') {
					$groupTotal = $rateGroups[$b]->rate + $rateGroups[$b]->additional_charge;
				} else {
					$groupTotal = ($tourists * ($rateGroups[$b]->rate +
						$rateGroups[$b]->additional_charge +
						$permitfeeAr[$b]
					));
				}

				if ($request->payment_method === 'Card') {
					$groupTotal += ($groupTotal * (float)$rateGroups[$b]->processing_charge_percentage) / 100;
				}

				if ($request->subtt == null) {
					$request->subtt = 0.00;
				}

				if ($request->fee == null) {
					$request->fee = 0.00;
				}

				$tt = $request->subtt + $request->fee;

				$natFeesPercentage = $info->service_commission_percentage;
				$stripeFeesPercentage = $info->stripe_commission_percentage;
				if ($customer->is_affiliate == 'Yes') {
					$natFeesPercentage = ($isBlackoutDate) ? $info->service_commission_percentage : $customer->affiliate_processing_percentage;
					$stripeFeesPercentage = ($isBlackoutDate) ? $info->stripe_commission_percentage : $customer->stripe_affiliate_percentage;

					if (getNextYearCommissionPercentage($info->date)) {
						if ($isBlackoutDate) {
							$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
							$natFeesPercentage = $serviceCommission['service_commission_percentage'];
							$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
						} else {
							$serviceCommission = getAffiliateCommissionPercentage($customer->id);
							$natFeesPercentage = $serviceCommission['affiliate_processing_percentage'];
							$stripeFeesPercentage = $serviceCommission['stripe_affiliate_percentage'];
						}
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$natFeesPercentage = $serviceCommission['service_commission_percentage'];
					$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
				}

				$tbTransact[] = [
					'tour_package_rate_id' => $rateGroups[$b]->id,
					'tourists' => $tourists,
					'processing_charge_percentage' => (($request->payment_method !== 'Card') ? 0 : $rateGroups[$b]->processing_charge_percentage),
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage,
					'additional_tax_percentage' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 0 : ($rateGroups[$b]->additional_tax_percentage),
					'additional_charge' => $rateGroups[$b]->additional_charge,
					'permit_fee' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 0 : $permitfeeAr[$b],
					'rate' => $rateGroups[$b]->rate,
					'total' => $groupTotal,
					'is_group_rate' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 1 : 0,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => Carbon::now(),
				];

				/* START - Added this to place the history of person added */
				$tbTransaction[] = [
					'tour_package_rate_id' => $rateGroups[$b]->id,
					'tourists' => $tourists,
					'rate_for' => $rateGroups[$b]->rate_for,
					'processing_charge_percentage' => (($request->payment_method !== 'Card') ? 0 : $rateGroups[$b]->processing_charge_percentage),
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage,
					'additional_tax_percentage' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 0 : ($rateGroups[$b]->additional_tax_percentage),
					'additional_charge' => $rateGroups[$b]->additional_charge,
					'permit_fee' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 0 : $permitfeeAr[$b],
					'rate' => $rateGroups[$b]->rate,
					'total' => $groupTotal,
					'is_group_rate' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 1 : 0,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => Carbon::now(),
				];
				/* END - Added this to place the history of person added */

				$total += $groupTotal;
			}
			$discoutPromoCode = "";
			if ($request->has('discount_type')) {
				if ($request->discount_type == 'Percentage' && !empty($request->discount_percentage)) {
					$discountAmt = round(($total * $request->discount_percentage) / 100, 2);
				} elseif ($request->discount_type == 'Fixed Money' && !empty($request->discount_fixed_money)) {
					$discountAmt = round($request->discount_fixed_money, 2);
				} elseif ($request->discount_type == 'Promo Code' && $request->is_applied == 1 && !empty($request->discount_promo_code)) {
					$discoutPromoCode = $request->discount_promo_code;
					$promoDetail = $this->promotion::where('code', $discoutPromoCode)
						->where('tour_package_id', $info->tour_package_id)->first();
					if ($promoDetail->discount_value_type == 'Percent') {
						$discountAmt = round(($total * $promoDetail->discount_value) / 100, 2);
					} else {
						$discountAmt = round($promoDetail->discount_value, 2);
					}
				}
			}

			$total -= $discountAmt;
			$serviceCommission = $total * $natFeesPercentage / 100;

			$total += $serviceCommission;
			$total = round($total, 2);

			/* START : code for get expire at */
			$customerId = $request->affiliatecust == 0 ? $customer->id : $request->affiliatecust;
			$expiryDate = BookingService::calculateExpiryDate($info->tour_package_id, $customerId);
			/* END : code for get expire at */

			$tourBooking = $this->tourBooking::create([
				'tour_package_id' => $info->tour_package_id,
				'tour_slot_id' => $request->slot_time,
				'customer_id' => $customerId,
				'hotel_id' => $request->hotel_id ?? 0,
				'tourists' => $totalTourists,
				'name' => $request->customer_name,
				'email' => $request->customer_email,
				'phone_number' => $request->customer_phone_number,
				'is_reserved_for_affiliate' => $isReserveForAffiliate,
				'comments' => $request->customer_comment,
				'note' => ($request->has('tour_operator_note')) ? $request->tour_operator_note : '',
				'note_datetime' => empty($request->tour_operator_note) ? null : mysqlDT(),
				'mask_required' => 'Yes',
				'discount2_value' => $discountAmt,
				'discount2_percentage' => empty($request->discount_percentage) ? 0 : $request->discount_percentage,
				'service_commission' => $serviceCommission,
				'total' => $total,
				'booking_total' => $total,
				'status' => 'Booked',
				'is_group_booking' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 1 : 0,
				'visitor' => $request->getClientIp(),
				'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
				'expire_at' => $expiryDate,
				'created_by' => empty($request->booked_by_staff) ? Auth::id() : $request->booked_by_staff,
				'created_by_user_type' => empty($request->booked_by_staff) ? $userType : 'Tour Operator',
				'updated_at' => null,
				'discount_promo_code' => $discoutPromoCode,
				'tour_promotion_id' => isset($promoDetail) ? $promoDetail->id : 0,
			]);
			DB::commit();

			if (!empty($request->customer_comment)) {
				HistoryService::storeCommentInHistory($request->customer_comment, $tourBooking->id);
			}

			for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
				$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
			}

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			$created_by_with_name = BookingService::createdUserInfo();

			/* START - Added this to place the history of person added */
			$addedPeoplesText = array();
			if (!empty($tbTransaction)) {
				foreach ($tbTransaction as $tblTransaction) {
					$addedPeoplesText[] = ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 'Person(s) ' . $tblTransaction['tourists'] : $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
				}
			}

			HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTourists, $created_by_with_name, [], 'Card Reader');
			/* END - Added this to place the history of person added */

			$master = $this->tourBooking::find($tourBooking->id);
			$fee = 0;
			if ($request->split_value) {
				$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)->where('deleted_by', 0)->first();
				$fee = ($request->split_value - ($request->split_value * $tourBookingTransaction->stripe_fees_percentage / 100));
				$fee = $request->split_value - $fee;
				$fee = roundout($fee, 2);
			}

			$isCashFees = cashFees();
			/* Save custom Addon Fields */
			$addonPrice = $addonFee = $addonTotal = 0;
			if ($request->custom_fields && trim($request->custom_fields) != "") {
				try {
					$addons = json_decode(trim($request->custom_fields), true);
					foreach ($addons['fields'] as $key => &$addon) {
						if ($addon['priceInfo']['enabled'] == 'true') {
							$addonPrice += $addon['priceInfo']['price'];
							if ($request->payment_method === 'Card' || ($isCashFees == 1 && $request->payment_method == 'Cash')) {
								$addonFee += $addon['priceInfo']['fee'];
								$addonTotal += $addon['priceInfo']['price'] + $addon['priceInfo']['fee'];
							} else {
								$addon['priceInfo']['fee'] = 0;
								$addonTotal += $addon['priceInfo']['price'];
							}
						}
					}

					$this->tourBookingFormsRepository->insertBookingForm($addons, $tourBooking->id);
				} catch (Exception $e) {
					Log::error($e->getMessage());
				}
			}
			/* End Save custom Addon Fields */

			/* Store Custom Charge Start */
			$customChargeDetails = [];
			$customChargeAmount = 0;
			$customChargeFees = 0;
			$customChargeIsTaxed = 0;

			if (isset($request->custom_charge_data) && $request->custom_charge_data != "") {
				try {
					$customData = json_decode($request->custom_charge_data, true);
					$customChargeDetails = $customData['custom_data'];
					$customChargeAmount = ($customChargeDetails['custom_charge_amount'] > 0) ? $customChargeDetails['custom_charge_amount'] : 0;
					$customChargeFees = $request->custom_charge_fees;
					$customChargeIsTaxed = $customChargeDetails['taxable'];
					$customChargeDetails['amount'] = $customChargeAmount;
					$customChargeDetails['fees'] = $customChargeFees;
					unset($customChargeDetails['custom_charge_amount']);

					CustomChargeBookingService::tourBookingCustomCharge($tourBooking->id, $customChargeDetails, null, 'Pay Later', 'Open');
					DB::commit();
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}
			/* Store Custom Charge End */

			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

			$vars = [
				'booking_id' => $tourBooking->id,
				'total' => $total,
				'discount2_value' => $discountAmt,
				'customer_id' => $master->customer_id,
				'customer_name' => $master->name,
				'customer_email' => $master->email,
				'customer_phone' => $master->phone_number,
				'tourists' => $totalTourists,
				'tour_package' => $info->package_name,
				'date' => $info->date,
				'time_from' => $info->time_from,
				'subtotal' => $request->subtt,
				'serviceCommission' => $serviceCommission,
				'destination' => $info->stripe_destination_id,
				'slot_time' => $request->slot_time,
				'tour_operator_id' => $info->tour_operator_id,
				'note' => $request->tour_operator_note,
				'booked_by_staff' => $request->booked_by_staff,
				'split_value' => $request->split_value,
				'fee' => $fee,
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Capture stripe payment using payment intent id
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function cardReaderAfterCapture($request)
	{
		try {
			$master = $this->tourBooking::find($request->booking_id);

			if (is_null($master)) {
				throw new Exception('You must provide correct booking Id');
			}

			$data = [
				'booking_id' => $request->booking_id,
				'total' => $request->total,
				'discount2_value' => $request->discount2_value,
				'customer_id' => $request->customer_id,
				'customer_name' => $request->customer_name,
				'customer_email' => $request->customer_email,
				'customer_phone' => $request->customer_phone,
				'tourists' => $request->tourists,
				'tour_package' => $request->tour_package,
				'date' => $request->date,
				'time_from' => $request->time_from,
				'subtotal' => $request->subtotal,
				'serviceCommission' => $request->serviceCommission,
				'destination' => $request->stripe_destination_id,
				'slot_time' => $request->slot_time,
				'tour_operator_id' => $request->tour_operator_id,
				'note' => $request->tour_operator_note,
				'booked_by_staff' => $request->booked_by_staff,
				'split_value' => $request->split_value,
				'api' => $request->api,
				'webhook' => !empty($request->webhook) ? 1 : 0,
				'tour_package_id' => $master->tour_package_id,
			];

			$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';

			if ($paymentIntentId == '') {
				throw new Exception('You must provide Stripe Payment Intent Id');
			}

			$data['payment_intent_id'] = $paymentIntentId;
			$data['after_capture'] = 1;
			$paymentIntentInfo = $this->createBookingCard($data);

			if (isset($paymentIntentInfo['nextAction'])) {
				$master->stripe_paymentintent_id = $paymentIntentInfo['clientSecret'];
				$master->save();

				return [
					'status' => $paymentIntentInfo['status'],
					'payment_intent_id' => $request->payment_intent_id,
					'bookingId' => $request->booking_id,
					'bookingToken' => Crypt::encryptString($request->booking_id), // for security purpose
					'require3ds' => 1,
					'nextAction' => $paymentIntentInfo['nextAction'],
				];
			}

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
			}


			/* Update Custom Charge Start */
			if (isset($request->custom_charge_data) && $request->custom_charge_data != "") {
				$retrieveOptions = ['expand' => ['charges.data.transfer']];
				$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
				$customData = json_decode($request->custom_charge_data, true);
				$customData['custom_charge_fees'] = $request->custom_charge_fees;
				$customData['custom_charge_amount'] = $customData['custom_data']['custom_charge_amount'];
				try {
					CustomChargeBookingService::tourBookingUpdateCustomCharge($request->booking_id, $customData, $paymentIntent);
					DB::commit();
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}
			/* Update Custom Charge End */

			return [
				'status' => $paymentIntentInfo['status'],
				'payment_intent_id' => $request->payment_intent_id,
				'bookingId' => $request->booking_id,
				'bookingToken' => Crypt::encryptString($request->booking_id), // for security purpose
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors_message' => !empty($errors_message) ? $errors_message : '',
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Build this method to store the booking before capture payment for manual card
	 *
	 * @param object $request
	 * 
	 * @version 1.0.0
	 */
	public function manualCardBeforeCapture($request)
	{
		DB::beginTransaction();
		try {
			$this->transformer->transformRequestData($request);

			$totalTourists = 0;

			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$totalTourists += $request->tourists[$a];
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one adult.');
			}

			$userType = userType();
			$isReserveForAffiliate = $request->is_reserved_for_affiliate ?? 0;
			$info = DB::table('tour_slot_times AS tst')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
				->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
				->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
				->join('states AS st', 'st.id', '=', 'ct.state_id')
				->where('tst.id', $request->slot_time)
				->select([
					'tst.time_from',
					'tst.time_to',
					'tsd.date',
					'tst.time_slot_seats as seats',
					'tp.tour_operator_id',
					'tsd.tour_package_id',
					'tp.name AS package_name',
					'tp.important_notes',
					'tp.things_to_bring',
					'ct.state_id',
					'st.permit_fee',
					't_o.name AS tour_operator',
					't_o.email AS tour_op_email',
					't_o.phone_number AS tour_op_phone',
					't_o.website AS tour_op_website',
					't_o.logo AS tour_op_logo',
					't_o.cancellation_policy',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage',
					'tp.is_group_rate_enabled',
					'tp.stripe_destination_id',
					't_o.id as tour_operator_id',
					'tp.email as packageEmail',
					'tp.from_name as packageFromName',
					'tp.from_email as packageFromEmail',
					'tp.api_key as packageApiKey',
					'tp.package_has_slots',
					'tp.package_has_waiver',
					'tp.package_has_permit',
					DB::raw("(SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                        GROUP BY tbm.tour_slot_id) AS booked_seats")
				]);

			if ($userType == 'Tour Operator') {
				$info = $info->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			}

			$info = $info->first();

			if (!$info) {
				throw new Exception('Unable to book the tour package.');
			}

			if ($info->package_has_slots && ("{$info->date} {$info->time_from}" <= mysqlDT())) {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}

			$isGroupRateEnabledForPackage = $info->is_group_rate_enabled;
			$isGroupRate = 0;
			$minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO]);
			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$result = TourPackageRate::find($request->rate_group[$a]);
				$isGroupRate = $result->is_group_rate;
				if ($minMaxPaxApply && $result->min_pax_allowed > $request->tourists[$a]) {
					throw new Exception("Please select a minimum of $result->min_pax_allowed $result->rate_for people to process your booking.");
				}
			}

			if ($request->has('is_reserved_for_affiliate') && $request->is_reserved_for_affiliate == 1) {
				$ReserveBooking = TourBooking::whereDoesntHave('transactions')->where('tour_package_id', $info->tour_package_id)->where('is_reserved_for_affiliate', '1')->where('customer_id', $request->affiliatecust)->where('tour_slot_id', $request->slot_time)->where('status', 'Booked')->count();
				if ($ReserveBooking > 0) {
					throw new Exception('Unable to book the tour package.You have already reserve booking for the slot');
				}
			}
			$rateGrpIds = [];
			$permitfeeAr = [];

			for ($a = 0, $c = count($request->tourists); $a < $c; $a++) {
				if ($request->tourists[$a]) {
					$x = $a;
					$rateGrpIds[] = $request->rate_group[$a];
					// Affiliates cannot set the permit fee: force the rate's value (server source of truth),
					// which blocks the client from injecting a stale/wrong permit fee. Tour Operators/Admins
					// may legitimately waive the fee, so keep their posted value.
					$permitfeeAr[] = (userType() === 'Affiliate')
						? (float) (DB::table('tour_package_rates')->where('id', $request->rate_group[$a])->value('permit_fee') ?? 0)
						: ($request->permit_fee[++$x] ?? 0);
				}
			}

			$rateGroups = DB::table('tour_package_rates AS tpr')
				->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
				->whereIn('tpr.id', $rateGrpIds)
				->where('tpr.deleted_by', 0)
				->orderBy('tpr.orderby', 'asc')
				->get([
					'tpr.id',
					'tpr.rate_for',
					'tpr.rate',
					'tpr.additional_tax_percentage',
					'tpr.processing_charge_percentage',
					'tpr.additional_charge'
				]);

			$customer = $this->customer::where('email', $request->customer_email)
				->where('tour_operator_id', $info->tour_operator_id)
				->first();

			if (!$customer && $request->affiliatecust == 0) {
				$pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
				$pwd = '';

				for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
					$pwd .= $pwdChars[rand(0, $cl)];
				}

				$customer = $this->customer::create([
					'tour_operator_id' => $info->tour_operator_id,
					'name' => $request->customer_name,
					'email' => $request->customer_email,
					'phone_number' => str_replace(['(', ')', '+', '-', '.', ' '], '', $request->customer_phone_number),
					'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
				]);
				DB::commit();
			}
			$isBlackoutDate = isBlackoutDateEnabled($info->date);
			$natFeesPercentage = $info->service_commission_percentage;
			$stripeFeesPercentage = $info->stripe_commission_percentage;

			if ($customer->is_affiliate == 'Yes') {
				$natFeesPercentage = ($isBlackoutDate) ? $info->service_commission_percentage : $customer->affiliate_processing_percentage;
				$stripeFeesPercentage = ($isBlackoutDate) ? $info->stripe_commission_percentage : $customer->stripe_affiliate_percentage;

				if (getNextYearCommissionPercentage($info->date)) {
					if ($isBlackoutDate) {
						$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
						$natFeesPercentage = $serviceCommission['service_commission_percentage'];
						$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
					} else {
						$affiliateCommission = getAffiliateCommissionPercentage($customer->id);
						$natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
						$stripeFeesPercentage = $affiliateCommission['stripe_affiliate_percentage'];
					}
				}
			} elseif (getNextYearCommissionPercentage($info->date)) {
				$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
				$natFeesPercentage = $serviceCommission['service_commission_percentage'];
				$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
			}
			$total = $discountAmt = 0;
			$tbTransact = $touristGroups = [];

			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				if (!$request->tourists[$a])
					continue;

				$tourists = $request->tourists[$a];
				$groupTotal = 0;

				for ($b = 0, $bc = count($rateGroups); $b < $bc; $b++) {
					if ($request->rate_group[$a] == $rateGroups[$b]->id) {
						$touristGroups[] = $rateGroups[$b]->rate_for[0] . ':' . $tourists;
						break;
					}
				}

				if ($b >= $bc) { // rate group not found
					DB::rollBack();
					throw new Exception('Unable to book the tour package.');
				}

				// rate may be zero for one or more age/rate group and
				// if rate is non-zero then only add other amounts
				if ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') {
					$groupTotal = $rateGroups[$b]->rate + $rateGroups[$b]->additional_charge;
				} else {
					$groupTotal = ($tourists * ($rateGroups[$b]->rate +
						$rateGroups[$b]->additional_charge +
						$permitfeeAr[$b]
					));
				}

				if ($request->payment_method === 'Card') {
					$groupTotal += ($groupTotal * (float)$rateGroups[$b]->processing_charge_percentage) / 100;
				}

				if ($request->subtt == null) {
					$request->subtt = 0.00;
				}

				if ($request->fee == null) {
					$request->fee = 0.00;
				}

				// $tt = $request->subtt + $request->fee;

				$tbTransact[] = [
					'tour_package_rate_id' => $rateGroups[$b]->id,
					'tourists' => $tourists,
					'processing_charge_percentage' => (($request->payment_method !== 'Card') ? 0 : $rateGroups[$b]->processing_charge_percentage),
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage,
					'additional_tax_percentage' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 0 : ($rateGroups[$b]->additional_tax_percentage),
					'additional_charge' => $rateGroups[$b]->additional_charge,
					'permit_fee' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 0 : $permitfeeAr[$b],
					'rate' => $rateGroups[$b]->rate,
					'total' => $groupTotal,
					'is_group_rate' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 1 : 0,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => Carbon::now(),
				];

				/* START - Added this to place the history of person added */
				$tbTransaction[] = [
					'tour_package_rate_id' => $rateGroups[$b]->id,
					'tourists' => $tourists,
					'rate_for' => $rateGroups[$b]->rate_for,
					'processing_charge_percentage' => (($request->payment_method !== 'Card') ? 0 : $rateGroups[$b]->processing_charge_percentage),
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage,
					'additional_tax_percentage' => ($rateGroups[$b]->additional_tax_percentage),
					'additional_charge' => ($rateGroups[$b]->additional_charge),
					'permit_fee' => $permitfeeAr[$b],
					'rate' => $rateGroups[$b]->rate,
					'total' => $groupTotal,
					'is_group_rate' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 1 : 0,
					'created_by' => Auth::id(),
					'created_by_user_type' => $userType,
					'created_at' => Carbon::now(),
				];
				/* END - Added this to place the history of person added */

				$total += $groupTotal;
			}
			$discoutPromoCode = "";
			if ($request->has('discount_type')) {
				if ($request->discount_type == 'Percentage' && !empty($request->discount_percentage)) {
					$discountAmt = round(($total * $request->discount_percentage) / 100, 2);
				} elseif ($request->discount_type == 'Fixed Money' && !empty($request->discount_fixed_money)) {
					$discountAmt = round($request->discount_fixed_money, 2);
				} elseif ($request->discount_type == 'Promo Code' && $request->is_applied == 1 && !empty($request->discount_promo_code)) {
					$discoutPromoCode = $request->discount_promo_code;
					$promoDetail = $this->promotion::where('code', $discoutPromoCode)
						->where('tour_package_id', $info->tour_package_id)->first();
					if ($promoDetail->discount_value_type == 'Percent') {
						$discountAmt = round(($total * $promoDetail->discount_value) / 100, 2);
					} else {
						$discountAmt = round($promoDetail->discount_value, 2);
					}
				}
			}

			$isCashFees = cashFees();
			$total -= $discountAmt;
			$serviceCommission = ($request->payment_method == "Card" || ($isCashFees == 1 && $request->payment_method == 'Cash'))
				? round($total * $natFeesPercentage / 100, 2)
				: 0;
			$total = round($total + $serviceCommission, 2);

			/* START : code for get expire at */
			$customerId = $request->affiliatecust == 0 ? $customer->id : $request->affiliatecust;
			$expiryDate = BookingService::calculateExpiryDate($info->tour_package_id, $customerId);
			/* END : code for get expire at */

			$tourBooking = $this->tourBooking::create([
				'tour_package_id' => $info->tour_package_id,
				'tour_slot_id' => $request->slot_time,
				'customer_id' => $customerId,
				'hotel_id' => $request->hotel_id ?? 0,
				'tourists' => $totalTourists,
				'name' => $request->customer_name,
				'email' => $request->customer_email,
				'phone_number' => $request->customer_phone_number,
				'is_reserved_for_affiliate' => $isReserveForAffiliate,
				'comments' => $request->customer_comment,
				'note' => ($request->has('tour_operator_note')) ? $request->tour_operator_note : '',
				'note_datetime' => empty($request->tour_operator_note) ? null : mysqlDT(),
				'mask_required' => 'Yes',
				'discount2_value' => $discountAmt,
				'discount2_percentage' => empty($request->discount_percentage) ? 0 : $request->discount_percentage,
				'service_commission' => $serviceCommission,
				'total' => $total,
				'booking_total' => $total,
				'status' => 'Booked',
				'is_group_booking' => ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 1 : 0,
				'visitor' => $request->getClientIp(),
				'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
				'expire_at' => $expiryDate,
				'created_by' => empty($request->booked_by_staff) ? Auth::id() : $request->booked_by_staff,
				'created_by_user_type' => empty($request->booked_by_staff) ? $userType : 'Tour Operator',
				'updated_at' => null,
				'discount_promo_code' => $discoutPromoCode,
				'tour_promotion_id' => isset($promoDetail) ? $promoDetail->id : 0,
			]);
			DB::commit();

			if (!empty($request->customer_comment)) {
				HistoryService::storeCommentInHistory($request->customer_comment, $tourBooking->id);
			}

			if (!empty($request->tour_operator_note)) {
				HistoryService::storeNoteInHistory($request->tour_operator_note, $tourBooking->id);
			}

			for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
				$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
			}

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			$created_by_with_name = BookingService::createdUserInfo();

			/* START - Added this to place the history of person added */
			$addedPeoplesText = array();
			if (!empty($tbTransaction)) {
				foreach ($tbTransaction as $tblTransaction) {
					$addedPeoplesText[] = ($isGroupRate == '1' || $isGroupRateEnabledForPackage == '1') ? 'Person(s) ' . $tblTransaction['tourists'] : $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
				}
			}

			HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTourists, $created_by_with_name);
			/* END - Added this to place the history of person added */

			$master = $this->tourBooking::find($tourBooking->id);

			$fee = 0;
			if ($request->split_value) {
				$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $tourBooking->id)->where('deleted_by', 0)->first();
				$fee = ($request->split_value - ($request->split_value * $tourBookingTransaction->stripe_fees_percentage / 100));
				$fee = $request->split_value - $fee;
				$fee = roundout($fee, 2);
			}

			/* Save custom Addon Fields */
			$addonPrice = $addonFee = $addonTotal = 0;
			if ($request->custom_fields && trim($request->custom_fields) != "") {
				try {
					$addons = json_decode(trim($request->custom_fields), true);
					foreach ($addons['fields'] as $key => &$addon) {
						if ($addon['priceInfo']['enabled'] == 'true') {
							$addonPrice += $addon['priceInfo']['price'];
							if ($request->payment_method === 'Card' || ($isCashFees == 1 && $request->payment_method == 'Cash')) {
								$addonFee += $addon['priceInfo']['fee'];
								$addonTotal += $addon['priceInfo']['price'] + $addon['priceInfo']['fee'];
							} else {
								$addon['priceInfo']['fee'] = 0;
								$addonTotal += $addon['priceInfo']['price'];
							}
						}
					}

					$this->tourBookingFormsRepository->insertBookingForm($addons, $tourBooking->id);
				} catch (Exception $e) {
					Log::error($e->getMessage());
				}
			}
			/* End Save custom Addon Fields */

			/* Store Custom Charge Start */
			$customChargeDetails = [];
			$customChargeAmount = 0;
			$customChargeFees = 0;
			$customChargeIsTaxed = 0;

			if (isset($request->custom_charge_data) && $request->custom_charge_data != "") {
				try {
					$customData = json_decode($request->custom_charge_data, true);
					$customChargeDetails = $customData['custom_data'];
					$customChargeAmount = ($customChargeDetails['custom_charge_amount'] > 0) ? $customChargeDetails['custom_charge_amount'] : 0;
					$customChargeFees = $request->custom_charge_fees;
					$customChargeIsTaxed = $customChargeDetails['taxable'];
					$customChargeDetails['amount'] = $customChargeAmount;
					$customChargeDetails['fees'] = $customChargeFees;
					unset($customChargeDetails['custom_charge_amount']);

					CustomChargeBookingService::tourBookingCustomCharge($tourBooking->id, $customChargeDetails, null, 'Pay Later', 'Open');
					DB::commit();
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}
			/* Store Custom Charge End */

			TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

			$vars = [
				'booking_id' => $tourBooking->id,
				'total' => $total,
				'discount2_value' => $discountAmt,
				'customer_id' => $master->customer_id,
				'customer_name' => $master->name,
				'customer_email' => $master->email,
				'customer_phone' => $master->phone_number,
				'tourists' => $totalTourists,
				'tour_package' => $info->package_name,
				'date' => $info->date,
				'time_from' => $info->time_from,
				'subtotal' => $request->subtt,
				'serviceCommission' => $serviceCommission,
				'destination' => $info->stripe_destination_id,
				'slot_time' => $request->slot_time,
				'tour_operator_id' => $info->tour_operator_id,
				'note' => $request->tour_operator_note,
				'booked_by_staff' => $request->booked_by_staff,
				'split_value' => $request->split_value,
				'tour_package_id' => $info->tour_package_id,
				'nat_fees_percentage' => $natFeesPercentage,
				'stripe_fees_percentage' => $stripeFeesPercentage,
				'fee' => $fee
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Cancel unpaid booking in manual card payment
	 *
	 * @param object $request
	 * 
	 * @version 1.0.0
	 */
	public function cancelTourManualCard($request)
	{
		DB::beginTransaction();
		try {
			$bookingIDs = [];

			if (isset($request->booking_id)) {
				$bookingIDs = explode(",", $request->booking_id);
			}

			if (empty($bookingIDs)) {
				throw new Exception('Something went wrong. Please try after sometimes.');
			}

			foreach ($bookingIDs as $bookingID) {
				$tourBooking = $this->tourBooking::find($bookingID);

				if (!$tourBooking) {
					throw new Exception('Tour booking not found.');
				}

				$trans = Transaction::where('tour_booking_id', $request->booking_id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();

				if ($trans) {
					throw new Exception('Something went wrong. Please try after sometimes.');
				}

				$tourBooking->status = 'Cancelled';
				$tourBooking->updated_by = Auth::id();
				$tourBooking->updated_at = Carbon::now();
				$tourBooking->updated_by_user_type = userType();
				$tourBooking->save();
				DB::commit();
				// Update slot summary for old slot
				$seatsDiff = - ($tourBooking->tourists);
				TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $seatsDiff);
			}


			return [
				'bookingId' => $bookingIDs[0]
			];
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Build this method to store the combo bookings before capture payment for card reader
	 *
	 * @param object $request
	 * 
	 * @version 1.0.0
	 */
	public function cardReaderBeforeCaptureComboTour($request)
	{
		DB::beginTransaction();
		try {
			$json = $request->json()->all();
			$validator = Validator::make($json, [
				'customer_details' => 'required|array',
				'tour_packages' => 'required|array',
				'customer_details.customer_name' => 'required',
				'customer_details.customer_email' => 'required',
				'tour_packages.*.slot_time' => 'required|integer|min:1',
				'tour_packages.*.tour_package_id' => 'required|integer|min:1',
				'tour_packages.*.permit_fee' => 'required|array',
				'tour_packages.*.rate_group' => 'required|array',
				'tour_packages.*.subtt' => 'required',
				'tour_packages.*.fee' => 'required',
				'tour_packages.*.total' => 'required'
			]);

			if ($validator->fails()) {
				throw new Exception(implode('<br>', $validator->errors()->all()));
			}
			$cart = $json;

			if (empty($cart) || !isset($cart['tour_packages']) || !isset($cart['tour_packages']) || empty($cart['tour_packages']) || empty($cart['customer_details'])) {
				throw new Exception("Your cart is empty.Please add packages in the cart.");
			}
			$cartPackages = $cart['tour_packages'];

			$cartSlotInfo = CartService::validateCartPackages($cartPackages);
			$tourOperatorId = $cartSlotInfo['tourOperatorId'];

			$totalTourists = 0;
			foreach ($cartPackages as $cartPackage) {
				for ($a = 0, $ac = count($cartPackage['tourists']); $a < $ac; $a++) {
					$totalTourists += $cartPackage['tourists'][$a];
				}
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one adult.');
			}


			$userType = userType();

			if (!$cartSlotInfo['validatedCart']) {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}

			if ($request->is('api/*')) {
				$minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO]);
				foreach ($cartPackages as $cartPackage) {
					for ($a = 0, $ac = count($cartPackage['tourists']); $a < $ac; $a++) {
						$result = TourPackageRate::find($cartPackage['rate_group'][$a]);

						if ($minMaxPaxApply && $result->min_pax_allowed > $cartPackage['tourists'][$a]) {
							throw new Exception("Please select a minimum of $result->min_pax_allowed $result->rate_for people to process your booking.");
						}
					}
				}
			}

			$customerDetails = $cart['customer_details'];
			$splitValue = !empty($cart['split_value']) ? $cart['split_value'] : '';

			if (count($cartPackages) > 1 && !empty($splitValue)) {
				throw new Exception("You can do split payment only if single package in the cart.");
			}

			$created_by_with_name = BookingService::createdUserInfo();

			$affiliateCustomer = $customerDetails['affiliatecust'];
			$customerEmail = $customerDetails['customer_email'];
			$customerName = $customerDetails['customer_name'];
			$customerPhoneNumber = $customerDetails['customer_phone_number'];

			if ($affiliateCustomer > 0) {
				$customer = $this->customer::where('id', $affiliateCustomer)
					->where('tour_operator_id', $tourOperatorId)
					->first();
			} else {
				$customer = $this->customer::where('email', $customerEmail)
					->where('tour_operator_id', $tourOperatorId)
					->first();
			}

			if (!$customer && $affiliateCustomer == 0) {
				$pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
				$pwd = '';

				for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
					$pwd .= $pwdChars[rand(0, $cl)];
				}

				$customer = $this->customer::create([
					'tour_operator_id' => $tourOperatorId,
					'name' => $customerName,
					'email' => $customerEmail,
					'phone_number' => str_replace(['(', ')', '+', '-', '.', ' '], '', $customerPhoneNumber),
					'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
				]);
				DB::commit();
			}

			$customerData = [
				'customer_id' => $customer->id,
				'customer_name' => $customerName,
				'customer_phone_number' => $customerPhoneNumber,
				'customer_email' => $customerEmail
			];

			$cartId = Str::random(10) . time();
			$firstBookingId = '';
			$totalCartAmount = 0;
			$totalCartFees = 0;
			$i = 0;
			$bookingsData = [];
			foreach ($cartPackages as $key => $cartPackage) {
				$slotTime = $cartPackage['slot_time'];
				$bookedByStaff = $cartPackage['booked_by_staff'];
				$tourists = $cartPackage['tourists'];
				$rateGroup = $cartPackage['rate_group'];
				$permitFee = $cartPackage['permit_fee'];
				$hotelId = $cartPackage['hotel_id'] ?? '';
				$isReservedForAffiliate = $cartPackage['is_reserved_for_affiliate'];
				$customerComment = $cartPackage['customer_comment'] ?? '';
				$tourOperatorNote = $cartPackage['tour_operator_note'] ?? '';
				$subtt = $cartPackage['subtt'];
				$fee = $cartPackage['fee'];
				$discountAmt = $cartPackage['discount_amount'];
				$discountType = $cartPackage['discount_type'];
				$discountPercentage = $cartPackage['discount_percentage'];

				$info = "SELECT
					`tst`.`time_from`, `tst`.`time_to`, `tst`.`date`, `tst`.`time_slot_seats` AS `seats`, `tp`.`tour_operator_id`, `tst`.`tour_package_id`,
					`tp`.`name` AS `package_name`, `tp`.`important_notes`, `tp`.`things_to_bring`, `t_o`.`name` AS `tour_operator`,
					`t_o`.`email` AS `tour_op_email`, `t_o`.`phone_number` AS `tour_op_phone`, `t_o`.`website` AS `tour_op_website`, `t_o`.`logo` AS `tour_op_logo`, `t_o`.`cancellation_policy`,
					`tp`.`service_commission_percentage`, `tp`.`stripe_commission_percentage`, `t_o`.`tenant_id`,
					`tp`.`stripe_destination_id`, `t_o`.`id` as `tour_operator_id`,`t_o`.`statement_descriptor`,`t_o`.`card_reader_prefix`, `tp`.`email` as `packageEmail`, `tp`.`from_name` as `packageFromName`, `tp`.`from_email` as `packageFromEmail`, `tp`.`api_key` as `packageApiKey`
				FROM
					`tour_slot_times` as `tst`
					INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tst`.`tour_package_id`
					INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
				WHERE
					`tst`.`id` = " . $slotTime . "
				";

				if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
					$info .= ' AND tp.tour_operator_id = ' . Auth::user()->tour_operator_id . '';
				}

				$info = collect(DB::select($info))->first();

				$rateGrpIds = [];
				$permitfeeAr = [];

				for ($a = 0, $c = count($tourists); $a < $c; $a++) {
					if ($tourists[$a]) {
						$x = $a;
						$rateGrpIds[] = $rateGroup[$a];
						$permitfeeAr[] = $permitFee[$x];
					}
				}

				$rateGroups = BookingUtils::getRateGroups($rateGrpIds);
				$isBlackoutDate = isBlackoutDateEnabled($info->date);
				$natFeesPercentage = $info->service_commission_percentage;
				$stripeFeesPercentage = $info->stripe_commission_percentage;
				if ($customer->is_affiliate == 'Yes') {
					$natFeesPercentage = ($isBlackoutDate) ? $info->service_commission_percentage : $customer->affiliate_processing_percentage;
					$stripeFeesPercentage = ($isBlackoutDate) ? $info->stripe_commission_percentage : $customer->stripe_affiliate_percentage;

					if (getNextYearCommissionPercentage($info->date)) {
						if ($isBlackoutDate) {
							$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
							$natFeesPercentage = $serviceCommission['service_commission_percentage'];
							$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
						} else {
							$affiliateCommission = getAffiliateCommissionPercentage($customer->id);
							$natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
							$stripeFeesPercentage = $affiliateCommission['stripe_affiliate_percentage'];
						}
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$natFeesPercentage = $serviceCommission['service_commission_percentage'];
					$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
				}

				$cartPackageData = [
					'cartPackage' => $cartPackage,
					'tourists' => $tourists,
					'rateGroups' => $rateGroups,
					'selectedRateGroups' => $rateGroup,
					'permitfeeAr' => $permitfeeAr,
					'natFeesPercentage' => $natFeesPercentage,
					'stripeFeesPercentage' => $stripeFeesPercentage,
					'paymentMethod' => 'Card',
					'discountAmt' => $discountAmt,
					'discountType' => $discountType
				];

				$cartPackageGroupTotals = CartService::calculateCartPackageGroupTotals($cartPackageData);

				if ($cartPackageGroupTotals['errorMessage'] != '') {
					DB::rollBack();
					throw new Exception('Unable to book the tour package.');
				}

				$tbTransact = $cartPackageGroupTotals['tbTransact'];
				$tbTransaction = $cartPackageGroupTotals['tbTransaction'];
				$total = $cartPackageGroupTotals['total'];

				$totalTouristsA = $cartPackageGroupTotals['totalTourists'];

				if ($discountType != '') {
					$serviceCommissionTotal = $fee;
				} else {
					$serviceCommissionTotal = $cartPackageGroupTotals['serviceCommissionTotal'];
				}

				$serviceCommission = $serviceCommissionTotal;
				$total += $serviceCommission;
				$total = round($total, 2);
				$subtt -= $discountAmt;
				$tt = $subtt + $fee;
				$totalCartAmount += $total;
				$totalCartFees += $serviceCommission;

				//Create Booking
				$tourBooking = $this->tourBooking::create([
					'tour_package_id' => $info->tour_package_id,
					'tour_slot_id' => $slotTime,
					'customer_id' => $affiliateCustomer == 0 ? $customer->id : $affiliateCustomer,
					'hotel_id' => $hotelId,
					'cart_id' => $cartId,
					'tourists' => $totalTouristsA,
					'is_reserved_for_affiliate' => $isReservedForAffiliate,
					'name' => $customerName,
					'email' => $customerEmail,
					'phone_number' => $customerPhoneNumber,
					'comments' => $customerComment,
					'note' => $tourOperatorNote,
					'note_datetime' => empty($note) ? null : mysqlDT(),
					'mask_required' => 'Yes',
					'discount2_value' => $discountAmt,
					'discount2_percentage' => ($discountPercentage > 0) ? $discountPercentage : 0,
					'service_commission' => $serviceCommission,
					'total' => $total,
					'booking_total' => $total,
					'status' => 'Booked',
					'visitor' => $request->getClientIp(),
					'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
					'created_by' => empty($bookedByStaff) ? Auth::id() : $bookedByStaff,
					'created_by_user_type' => empty($bookedByStaff) ? $userType : 'Tour Operator',
					'updated_at' => null,
				]);
				DB::commit();

				if (!empty($customerComment)) {
					HistoryService::storeCommentInHistory($customerComment, $tourBooking->id);
				}
				for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
					$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
				}

				DB::table('tour_bookings_transact')->insert($tbTransact);
				DB::commit();

				/*START - Added this to place the history of person added */
				$addedPeoplesText = array();
				if (!empty($tbTransaction)) {
					foreach ($tbTransaction as $tblTransaction) {
						$addedPeoplesText[] = $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
					}
				}

				HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTouristsA, $created_by_with_name);

				TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

				$bookingsData[] = [
					'booking_id' => $tourBooking->id,
					'total' => $total,
					'discount2_value' => $discountAmt,
					'tourists' => $totalTouristsA,
					'tour_package' => $info->package_name,
					'date' => $info->date,
					'time_from' => $info->time_from,
					'subtotal' => $subtt,
					'serviceCommission' => $serviceCommission,
					'destination' => $info->stripe_destination_id,
					'slot_time' => $slotTime,
					'tour_operator_id' => $info->tour_operator_id,
					'note' => $tourOperatorNote,
					'booked_by_staff' => $bookedByStaff,
					'tour_package_id' => $info->tour_package_id,
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage
				];
			}

			$vars = [
				'customer_details' => $customerData,
				'data' => $bookingsData,
				'cart_id' => $cartId,
				'split_value' => $splitValue,
				'total' => $totalCartAmount
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Capture stripe payment using payment intent id for card reader (Combo Bookings)
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function cardReaderAfterCaptureComboTour($request)
	{
		try {
			$json = $request->json()->all();
			$validator = Validator::make($json, [
				'customer_details' => 'required|array',
				'data' => 'required|array'
			]);

			if ($validator->fails()) {
				throw new Exception(implode('<br>', $validator->errors()->all()));
			}

			if (empty($json) || !isset($json['data']) || !isset($json['customer_details']) || empty($json['data']) || empty($json['customer_details'])) {
				throw new Exception("Unable to book tour packages.");
			}

			$data = [
				'data' => $json['data'],
				'customer_details' => $json['customer_details'],
				'split_value' => $json['split_value'],
				'total' => $json['total'],
				'api' => $json['api'],
				'webhook' => !empty($json['webhook']) ? 1 : 0
			];

			$paymentIntentId = (isset($json['payment_intent_id']) && $json['payment_intent_id'] != '') ? $json['payment_intent_id'] : '';
			if ($paymentIntentId == '') {
				throw new Exception('You must provide Stripe Payment Intent Id');
			}

			$data['payment_intent_id'] = $paymentIntentId;
			$data['after_capture'] = 1;
			$paymentIntentInfo = $this->createComboBookingCard($data);

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
			}
			$bookingId = $json['data'][0]['booking_id'] ?? '';
			return [
				'status' => $paymentIntentInfo['status'],
				'payment_intent_id' => $paymentIntentId,
				'bookingId' => $bookingId,
				'bookingToken' => Crypt::encryptString($bookingId), // for security purpose
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors_message' => !empty($errors_message) ? $errors_message : '',
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}


	/**
	 * Capture stripe payment using payment intent id for manual card
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function manualCardAfterCapture($request)
	{
		try {
			$master = $this->tourBooking::find($request->booking_id);

			if (is_null($master)) {
				throw new Exception('You must provide correct booking Id');
			}

			$data = [
				'booking_id' => $request->booking_id,
				'total' => $request->total,
				'discount2_value' => $request->discount2_value,
				'customer_id' => $request->customer_id,
				'customer_name' => $request->customer_name,
				'customer_email' => $request->customer_email,
				'customer_phone' => $request->customer_phone,
				'tourists' => $request->tourists,
				'tour_package' => $request->tour_package,
				'date' => $request->date,
				'time_from' => $request->time_from,
				'subtotal' => $request->subtotal,
				'serviceCommission' => $request->serviceCommission,
				'destination' => $request->stripe_destination_id,
				'slot_time' => $request->slot_time,
				'tour_operator_id' => $request->tour_operator_id,
				'note' => $request->tour_operator_note,
				'booked_by_staff' => $request->booked_by_staff,
				'split_value' => $request->split_value,
				'api' => $request->api,
				'webhook' => !empty($request->webhook) ? 1 : 0,
				'tour_package_id' => $master->tour_package_id,
			];

			$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';
			if ($paymentIntentId == '') {
				throw new Exception('You must provide Stripe Payment Intent Id');
			}

			$data['payment_intent_id'] = $paymentIntentId;
			$data['after_capture'] = 1;
			$paymentIntentInfo = $this->createBookingCard($data);

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
			}


			/* Update Custom Charge Start */
			if (isset($request->custom_charge_data) && $request->custom_charge_data != "") {
				$retrieveOptions = ['expand' => ['charges.data.transfer']];
				$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
				$customData = json_decode($request->custom_charge_data, true);
				$customData['custom_charge_fees'] = $request->custom_charge_fees;
				$customData['custom_charge_amount'] = $customData['custom_data']['custom_charge_amount'];
				try {
					CustomChargeBookingService::tourBookingUpdateCustomCharge($request->booking_id, $customData, $paymentIntent);
					DB::commit();
				} catch (Exception $ex) {
					Log::error($ex->getMessage());
				}
			}
			/* Update Custom Charge End */

			return [
				'status' => $paymentIntentInfo['status'],
				'payment_intent_id' => $request->payment_intent_id,
				'bookingId' => $request->booking_id,
				'bookingToken' => Crypt::encryptString($request->booking_id), // for security purpose
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors_message' => !empty($errors_message) ? $errors_message : '',
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Build this method to store the combo bookings before capture payment for manual card
	 *
	 * @param object $request
	 * 
	 * @version 1.0.0
	 */
	public function manualCardBeforeCaptureComboTour($request)
	{
		DB::beginTransaction();
		try {
			$json = $request->json()->all();
			$validator = Validator::make($json, [
				'customer_details' => 'required|array',
				'tour_packages' => 'required|array',
				'customer_details.customer_name' => 'required',
				'customer_details.customer_email' => 'required',
				'tour_packages.*.slot_time' => 'required|integer|min:1',
				'tour_packages.*.tour_package_id' => 'required|integer|min:1',
				'tour_packages.*.permit_fee' => 'required|array',
				'tour_packages.*.rate_group' => 'required|array',
				'tour_packages.*.subtt' => 'required',
				'tour_packages.*.fee' => 'required',
				'tour_packages.*.total' => 'required'
			]);

			if ($validator->fails()) {
				throw new Exception(implode('<br>', $validator->errors()->all()));
			}
			$cart = $json;

			if (empty($cart) || !isset($cart['tour_packages']) || !isset($cart['tour_packages']) || empty($cart['tour_packages']) || empty($cart['customer_details'])) {
				throw new Exception("Your cart is empty.Please add packages in the cart.");
			}
			$cartPackages = $cart['tour_packages'];

			$cartSlotInfo = CartService::validateCartPackages($cartPackages);
			$tourOperatorId = $cartSlotInfo['tourOperatorId'];

			$totalTourists = 0;
			foreach ($cartPackages as $cartPackage) {
				for ($a = 0, $ac = count($cartPackage['tourists']); $a < $ac; $a++) {
					$totalTourists += $cartPackage['tourists'][$a];
				}
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one adult.');
			}

			$userType = userType();

			if (!$cartSlotInfo['validatedCart']) {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}

			if ($request->is('api/*')) {
				$minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_TWO]);
				foreach ($cartPackages as $cartPackage) {
					for ($a = 0, $ac = count($cartPackage['tourists']); $a < $ac; $a++) {
						$result = TourPackageRate::find($cartPackage['rate_group'][$a]);

						if ($minMaxPaxApply && $result->min_pax_allowed > $cartPackage['tourists'][$a]) {
							throw new Exception("Please select a minimum of $result->min_pax_allowed $result->rate_for people to process your booking.");
						}
					}
				}
			}

			$customerDetails = $cart['customer_details'];
			$splitValue = !empty($cart['split_value']) ? $cart['split_value'] : '';

			if (count($cartPackages) > 1 && !empty($splitValue)) {
				throw new Exception("You can do split payment only if single package in the cart.");
			}

			$created_by_with_name = BookingService::createdUserInfo();

			$affiliateCustomer = $customerDetails['affiliatecust'];
			$customerEmail = $customerDetails['customer_email'];
			$customerName = $customerDetails['customer_name'];
			$customerPhoneNumber = $customerDetails['customer_phone_number'];

			if ($affiliateCustomer > 0) {
				$customer = $this->customer::where('id', $affiliateCustomer)
					->where('tour_operator_id', $tourOperatorId)
					->first();
			} else {
				$customer = $this->customer::where('email', $customerEmail)
					->where('tour_operator_id', $tourOperatorId)
					->first();
			}

			if (!$customer && $affiliateCustomer == 0) {
				$pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
				$pwd = '';

				for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
					$pwd .= $pwdChars[rand(0, $cl)];
				}

				$customer = $this->customer::create([
					'tour_operator_id' => $tourOperatorId,
					'name' => $customerName,
					'email' => $customerEmail,
					'phone_number' => str_replace(['(', ')', '+', '-', '.', ' '], '', $customerPhoneNumber),
					'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
				]);
				DB::commit();
			}

			$customerData = [
				'customer_id' => $customer->id,
				'customer_name' => $customerName,
				'customer_phone_number' => $customerPhoneNumber,
				'customer_email' => $customerEmail
			];

			$cartId = Str::random(10) . time();
			$totalCartAmount = 0;
			$totalCartFees = 0;
			$i = 0;
			$bookingsData = [];
			$stripeDestinationId = '';
			foreach ($cartPackages as $key => $cartPackage) {
				$slotTime = $cartPackage['slot_time'];
				$bookedByStaff = $cartPackage['booked_by_staff'];
				$tourists = $cartPackage['tourists'];
				$rateGroup = $cartPackage['rate_group'];
				$permitFee = $cartPackage['permit_fee'];
				$hotelId = $cartPackage['hotel_id'] ?? '';
				$isReservedForAffiliate = $cartPackage['is_reserved_for_affiliate'];
				$customerComment = $cartPackage['customer_comment'] ?? '';
				$tourOperatorNote = $cartPackage['tour_operator_note'] ?? '';
				$subtt = $cartPackage['subtt'];
				$fee = $cartPackage['fee'];
				$discountAmt = $cartPackage['discount_amount'];
				$discountType = $cartPackage['discount_type'];
				$discountPercentage = $cartPackage['discount_percentage'];

				$info = "SELECT
					`tst`.`time_from`, `tst`.`time_to`, `tst`.`date`, `tst`.`time_slot_seats` AS `seats`, `tp`.`tour_operator_id`, `tst`.`tour_package_id`,
					`tp`.`name` AS `package_name`, `tp`.`important_notes`, `tp`.`things_to_bring`, `t_o`.`name` AS `tour_operator`,
					`t_o`.`email` AS `tour_op_email`, `t_o`.`phone_number` AS `tour_op_phone`, `t_o`.`website` AS `tour_op_website`, `t_o`.`logo` AS `tour_op_logo`, `t_o`.`cancellation_policy`,
					`tp`.`service_commission_percentage`, `tp`.`stripe_commission_percentage`, `t_o`.`tenant_id`,
					`tp`.`stripe_destination_id`, `t_o`.`id` as `tour_operator_id`,`t_o`.`statement_descriptor`,`t_o`.`card_reader_prefix`, `tp`.`email` as `packageEmail`, `tp`.`from_name` as `packageFromName`, `tp`.`from_email` as `packageFromEmail`, `tp`.`api_key` as `packageApiKey`, `tp`.`is_group_rate_enabled` as `isGroupRateEnabled`
				FROM
					`tour_slot_times` as `tst`
					INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tst`.`tour_package_id`
					INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
				WHERE
					`tst`.`id` = " . $slotTime . "
				";

				if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
					$info .= ' AND tp.tour_operator_id = ' . Auth::user()->tour_operator_id . '';
				}

				$info = collect(DB::select($info))->first();

				$stripeDestinationId = $info->stripe_destination_id;
				$isGroupRateEnabled = $info->isGroupRateEnabled;
				$isBlackoutDate = isBlackoutDateEnabled($info->date);
				$rateGrpIds = [];
				$permitfeeAr = [];

				for ($a = 0, $c = count($tourists); $a < $c; $a++) {
					if ($tourists[$a]) {
						$x = $a;
						$rateGrpIds[] = $rateGroup[$a];
						$permitfeeAr[] = $permitFee[$x];
					}
				}

				$rateGroups = BookingUtils::getRateGroups($rateGrpIds);

				$natFeesPercentage = $info->service_commission_percentage;
				$stripeFeesPercentage = $info->stripe_commission_percentage;
				if ($customer->is_affiliate == 'Yes') {
					$natFeesPercentage = ($isBlackoutDate) ? $info->service_commission_percentage : $customer->affiliate_processing_percentage;
					$stripeFeesPercentage = ($isBlackoutDate) ? $info->stripe_commission_percentage : $customer->stripe_affiliate_percentage;

					if (getNextYearCommissionPercentage($info->date)) {
						if ($isBlackoutDate) {
							$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
							$natFeesPercentage = $serviceCommission['service_commission_percentage'];
							$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
						} else {
							$affiliateCommission = getAffiliateCommissionPercentage($customer->id);
							$natFeesPercentage = $affiliateCommission['affiliate_processing_percentage'];
							$stripeFeesPercentage = $affiliateCommission['stripe_affiliate_percentage'];
						}
					}
				} elseif (getNextYearCommissionPercentage($info->date)) {
					$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
					$natFeesPercentage = $serviceCommission['service_commission_percentage'];
					$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
				}

				$cartPackageData = [
					'cartPackage' => $cartPackage,
					'tourists' => $tourists,
					'rateGroups' => $rateGroups,
					'selectedRateGroups' => $rateGroup,
					'permitfeeAr' => $permitfeeAr,
					'natFeesPercentage' => $natFeesPercentage,
					'stripeFeesPercentage' => $stripeFeesPercentage,
					'paymentMethod' => 'Card',
					'discountAmt' => $discountAmt,
					'discountType' => $discountType
				];

				$cartPackageGroupTotals = CartService::calculateCartPackageGroupTotals($cartPackageData);

				if ($cartPackageGroupTotals['errorMessage'] != '') {
					DB::rollBack();
					throw new Exception('Unable to book the tour package.');
				}

				$tbTransact = $cartPackageGroupTotals['tbTransact'];
				$tbTransaction = $cartPackageGroupTotals['tbTransaction'];
				$total = $cartPackageGroupTotals['total'];

				$totalTouristsA = $cartPackageGroupTotals['totalTourists'];

				if ($discountType != '') {
					$serviceCommissionTotal = $fee;
				} else {
					$serviceCommissionTotal = $cartPackageGroupTotals['serviceCommissionTotal'];
				}

				$serviceCommission = $serviceCommissionTotal;
				$total += $serviceCommission;
				$total = round($total, 2);
				$subtt -= $discountAmt;
				$tt = $subtt + $fee;
				$totalCartAmount += $total;
				$totalCartFees += $serviceCommission;

				//Create Booking
				$tourBooking = $this->tourBooking::create([
					'tour_package_id' => $info->tour_package_id,
					'tour_slot_id' => $slotTime,
					'customer_id' => $affiliateCustomer == 0 ? $customer->id : $affiliateCustomer,
					'hotel_id' => $hotelId,
					'cart_id' => $cartId,
					'tourists' => $totalTouristsA,
					'is_reserved_for_affiliate' => $isReservedForAffiliate,
					'name' => $customerName,
					'email' => $customerEmail,
					'phone_number' => $customerPhoneNumber,
					'comments' => $customerComment,
					'note' => $tourOperatorNote,
					'note_datetime' => empty($note) ? null : mysqlDT(),
					'mask_required' => 'Yes',
					'discount2_value' => $discountAmt,
					'discount2_percentage' => ($discountPercentage > 0) ? $discountPercentage : 0,
					'service_commission' => $serviceCommission,
					'total' => $total,
					'booking_total' => $total,
					'status' => 'Booked',
					'visitor' => $request->getClientIp(),
					'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
					'created_by' => empty($bookedByStaff) ? Auth::id() : $bookedByStaff,
					'created_by_user_type' => empty($bookedByStaff) ? $userType : 'Tour Operator',
					'updated_at' => null,
				]);
				DB::commit();

				if (!empty($customerComment)) {
					HistoryService::storeCommentInHistory($customerComment, $tourBooking->id);
				}
				for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
					$tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
				}

				DB::table('tour_bookings_transact')->insert($tbTransact);
				DB::commit();

				/*START - Added this to place the history of person added */
				$addedPeoplesText = array();
				if (!empty($tbTransaction)) {
					foreach ($tbTransaction as $tblTransaction) {
						$addedPeoplesText[] = $tblTransaction['rate_for'] . " person " . $tblTransaction['tourists'];
					}
				}

				HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTouristsA, $created_by_with_name);

				TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

				$bookingsData[] = [
					'booking_id' => $tourBooking->id,
					'total' => $total,
					'discount2_value' => $discountAmt,
					'tourists' => $totalTouristsA,
					'tour_package' => $info->package_name,
					'date' => $info->date,
					'time_from' => $info->time_from,
					'subtotal' => $subtt,
					'serviceCommission' => $serviceCommission,
					'destination' => $info->stripe_destination_id,
					'slot_time' => $slotTime,
					'tour_operator_id' => $info->tour_operator_id,
					'note' => $tourOperatorNote,
					'booked_by_staff' => $bookedByStaff,
					'tour_package_id' => $info->tour_package_id,
					'nat_fees_percentage' => $natFeesPercentage,
					'stripe_fees_percentage' => $stripeFeesPercentage
				];
			}

			$vars = [
				'customer_details' => $customerData,
				'data' => $bookingsData,
				'split_value' => $splitValue,
				'total' => $totalCartAmount,
				'stripe_destination_id' => $stripeDestinationId
			];

			return $vars;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Capture stripe payment using payment intent id for manual card (Combo Bookings)
	 *
	 * @param object $request
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function manualCardAfterCaptureComboTour($request)
	{
		try {
			$json = $request->json()->all();
			$validator = Validator::make($json, [
				'customer_details' => 'required|array',
				'data' => 'required|array'
			]);

			if ($validator->fails()) {
				throw new Exception(implode('<br>', $validator->errors()->all()));
			}

			if (empty($json) || !isset($json['data']) || !isset($json['customer_details']) || empty($json['data']) || empty($json['customer_details'])) {
				throw new Exception("Unable to book tour packages.");
			}

			$data = [
				'data' => $json['data'],
				'customer_details' => $json['customer_details'],
				'split_value' => $json['split_value'],
				'total' => $json['total'],
				'api' => $json['api'],
				'webhook' => !empty($json['webhook']) ? 1 : 0
			];

			$paymentIntentId = (isset($json['payment_intent_id']) && $json['payment_intent_id'] != '') ? $json['payment_intent_id'] : '';
			if ($paymentIntentId == '') {
				throw new Exception('You must provide Stripe Payment Intent Id');
			}

			$data['payment_intent_id'] = $paymentIntentId;
			$data['after_capture'] = 1;
			$paymentIntentInfo = $this->createComboBookingCard($data);

			if (!empty($paymentIntentInfo['ex'])) {
				$errors_message = $paymentIntentInfo['ex']->getError()->message;
			}
			$bookingId = $json['data'][0]['booking_id'] ?? '';
			return [
				'status' => $paymentIntentInfo['status'],
				'payment_intent_id' => $paymentIntentId,
				'bookingId' => $bookingId,
				'bookingToken' => Crypt::encryptString($bookingId), // for security purpose
				'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
				'errors_message' => !empty($errors_message) ? $errors_message : '',
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}


	public function affiliateBookings(int $tourSlotId)
	{
		try {
			$qry = DB::table('tour_bookings_master AS tbm')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->where('c.is_affiliate', 'Yes')
				->where('tbm.tour_slot_id', $tourSlotId)
				->where('tbm.status', '!=', 'Cancelled')
				->select(DB::raw("SUM(tbm.tourists) as tourists"), 'c.name', 'c.id AS affiliate_id')
				->groupBy('c.name');
			if (session('userType') == 'Affiliate')
				$qry = $qry->where('tbm.customer_id', Auth::id());
			if (session('userType') == 'Tour Operator')
				$qry = $qry->where('c.tour_operator_id', Auth::user()->tour_operator_id);

			return response()->json($qry->get());
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Store session data to reuse those data based on filter
	 *
	 * @param Request $request
	 *
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function dayTimeSlotsbooking(Request $request)
	{
		try {
			session()->put('bookingId', bookingPrefix($request->id, true));
			session()->put('PaymentStatus', $request->payment_status);
			session()->put('tourOperatorId', $request->tour_operator);
			session()->put('tourPackagesId', $request->tour_package);
			session()->put('slotId', '');
			session()->put('date', $request->date);
			session()->put('api', '');
			session()->put('viewId', $request->id);

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * return booking History
	 * 
	 * @version 1.0.0
	 */
	public function bookingHistoryAndTransactions($bookingId)
	{
		$booking = $this->tourBooking::where('id', $bookingId)->get();
		$history = $this->tourBookingHistory::where('tour_booking_id', $bookingId)->get();
		$transactions = $this->transaction::where('tour_booking_id', $bookingId)
			->where(function ($q) {
				$q->whereNull('charge_type')->orWhere('charge_type', 'addons');
			})->get();
		$bookingTrans = $this->bookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->get();
		return ['booking' => $booking, 'history' => $history, 'transactions' => $transactions, 'booking_tansactions' => $bookingTrans, 'id' => $bookingId];
	}


	/**
	 * Validation for Edit booking Split payment Don't refund option for pending payment
	 *
	 * @param string $payment_type
	 * @param string $action_type
	 * @param int $tour_operator_id
	 * @param object $request
	 * @param array $array
	 */

	public function splitPaymentCheckBeforeDelete($array, $bookingID, $payment_type)
	{
		$isCashFees = cashFees();
		foreach ($array as $key => $value) {
			$transaction = $this->transaction::where('tour_booking_id', $bookingID)->where('status', 'Completed')->whereNull('charge_type')->first();

			$tourBooking = $this->tourBooking::where('id', $bookingID)->where('status', 'Booked')->first();
			$customer = $this->customer::where('id', $tourBooking->customer_id)->first();

			$rateCheck = $this->bookingTransact::where('tour_package_rate_id', $key)->where('tour_booking_id', $bookingID)->first();
			if ($customer->is_affiliate == 'Yes') {
				$service_commission_percentage = $rateCheck->nat_fees_percentage;
			} else {
				$service_commission_percentage = $rateCheck->nat_fees_percentage;
			}

			$rate = $rateCheck->rate + $rateCheck->additional_charge + $rateCheck->permit_fee;
			if ($payment_type === 'Card' || ($isCashFees == '1' && $payment_type === 'Cash')) {
				$per = $rate * ($rateCheck->additional_tax_percentage + $rateCheck->processing_charge_percentage) / 100;
			} else {
				$per = $rate * $rateCheck->additional_tax_percentage / 100;
			}

			$subtotal = ($rate + $per);
			$subtotal = number_format((float)$subtotal * $value, 2, '.', '');
			$total = $subtotal;

			$bt = $this->bookingTransact::where('tour_booking_id', $bookingID)->where('tour_package_rate_id', $key)->where('deleted_by', 0)->first();

			$set_total = $bt->total - $total;
			$oldBookingID = oldBookingId();
			$total_fee_amount = 0;
			if (!empty($transaction) && ($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash'))) {
				if ($bookingID < $oldBookingID && $customer->is_affiliate === "No") {
					$total_fee_amount = ($set_total * 6) / 100;
				} else {
					$total_fee_amount = ($set_total * $service_commission_percentage) / 100;
				}
			}

			$total_fee_amount = number_format((float)$total_fee_amount, 2, '.', '');
			$set_fee_amount = 0;
			if ($transaction) {
				$set_fee_amount = $tourBooking->service_commission - (float)$total_fee_amount;
				$set_fee_amount = number_format((float)$set_fee_amount, 2, '.', '');
			}

			if (!empty($transaction)) {
				if ($transaction->via == "Card" || ($isCashFees == '1' && $transaction->via == 'Cash')) {
					$set_fee_amount = 0.00;
					if ($bookingID < $oldBookingID) {
						if ($customer->is_affiliate === "No") {
							$set_fee_amount = ($total * 6) / 100;
						}
					} else {
						$set_fee_amount = ($total * $service_commission_percentage) / 100;
					}

					$set_fee_amount = number_format((float)$set_fee_amount, 2, '.', '');
					$set_fee_amount = (float)$set_fee_amount;
					$setTotal = $set_fee_amount + $total;

					return $setTotal;
				}
			}
		}
	}

	/**
	 * Unblock the affiliate soft booking
	 *
	 * @param int $bookingId
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function affiliateSoftBookingUnblock($bookingId)
	{
		try {
			$booking = $this->tourBooking::find($bookingId);

			$isPaid = $this->transaction::where('tour_booking_id', $bookingId)->whereNull('charge_type')->count();

			if ($isPaid && $booking->is_reserved_for_affiliate == 0) {
				throw new Exception("Due to the fact that the booking has already been paid for, it cannot be blocked.");
			}

			$booking->is_reserved_for_affiliate = $booking->is_reserved_for_affiliate == 0 ? 1 : 0;
			$booking->save();

			if ($booking->is_reserved_for_affiliate == 0) {
				$details = "Soft booking is now unblocked.";
			} else {
				$details = "Soft booking is now blocked.";
			}

			HistoryService::storeNoteInHistory($details, $bookingId);

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Add or update the hotel pickup
	 *
	 * @param array $data
	 * @param int $bookingId
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function hotelPickupUpdate($data, $bookingId)
	{
		try {
			$userType = userType();

			$created_by_with_name = BookingService::createdUserInfo();

			$tourBooking = $this->tourBooking::find($bookingId);
			$tourBooking->hotel_id = $data['hotel_id'];
			$tourBooking->save();

			HistoryService::storePickupHotelInHistory($tourBooking, $created_by_with_name);

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Download agenda page date wise
	 *
	 * @param int $date
	 * @param int $tourOperatorId
	 * @param string $tourPackagesId
	 * @param int $paymentStatus
	 * @param string $slotId
	 * @param int $api
	 * @return array|mixed
	 * 
	 * @version 1.0.0
	 */
	public function downloadAgenda(Request $request, int $date = 0, int $tourOperatorId = 0, string $tourPackagesId = '', int $paymentStatus = 0, $slotId = '', int $api = 0)
	{
		try {
			$vars = $this->dayTimeSlots($request, $date, $tourOperatorId, $tourPackagesId, $paymentStatus, $slotId, $api, 1);

			$tenantId = getSubdomainFromHost();
			$convertedDate = date("m-d-Y", strtotime($vars['date']));
			$fileName = $tenantId . '_agenda_' . $convertedDate . '.csv';
			$headers = csvHeaders($fileName);
			$columnsHeader = csvColumns('agenda_columns');

			$callback = function () use ($vars, $columnsHeader, $convertedDate) {

				$file = fopen('php://output', 'w');  // Create a file
				$blankLine = '';

				fputcsv($file, array('Date: ' . $convertedDate));
				fputcsv($file, array($blankLine));

				$slots = [];
				$tourPackageId = 0;
				$slotId = 0;
				$sltId = 0;
				$dayBookingsId = [];
				$agendaView = agendaView();
				$dayBookings = $vars['dayBookings'];
				$packageHasSlots = 1;

				// Slot wise booking list
				if (!empty($vars['dayBookings'])) {
					for ($i = 0, $j = -1, $ic = count($dayBookings); $i < $ic; $i++) {
						$packageHasSlots = $dayBookings[$i]->package_has_slots;

						$dayBookingsId[] = [
							'id' => $dayBookings[$i]->booking_id,
						];
						$agendaViewId = $agendaView == 1 ? 'tour_slot_id' : 'hotel_id';

						if ($dayBookings[$i]->tour_package_id != $tourPackageId || $dayBookings[$i]->$agendaViewId != $slotId) {
							$tourPackageId = $dayBookings[$i]->tour_package_id;
							$slotId = $dayBookings[$i]->$agendaViewId;

							if ($agendaView == 1) {
								$slots[] = [
									'id' => $slotId,
									'tFrom' => $dayBookings[$i]->time_from,
									'tTo' => $dayBookings[$i]->time_to,
									'packageHasSlots' => $dayBookings[$i]->package_has_slots,
								];
							} else {
								if ($slotId == 0) {
									$sltId = uniqid();
									$slots[] = [
										'id' => $sltId,
										'hotel_name' => $dayBookings[$i]->hotel_name,
									];
								} else {
									$slots[] = [
										'id' => $slotId,
										'hotel_name' => $dayBookings[$i]->hotel_name,
									];
								}
							}

							fputcsv($file, array($dayBookings[$i]->tour_package_name));

							if ($agendaView == 1) {
								if ($packageHasSlots) {
									fputcsv($file, array('@ ' . $dayBookings[$i]->time_from . ' - ' . $dayBookings[$i]->time_to));
								}
							} else {
								fputcsv($file, array($dayBookings[$i]->hotel_name ?? 'Custom Pickup'));
							}

							fputcsv($file, $columnsHeader);
						}

						$bookingInfo = $vars['dayBookingsInfo']['b-' . $dayBookings[$i]->booking_id];
						$touristsAgeInfo = [];
						$bookingCheckIn = '';
						$checkinArray = array();

						$bookingCheckInsCount = ['1' => 0, '2' => 0, 'A' => 0];

						for ($b = 0, $bc = count($bookingInfo); $b < $bc; $b++) {
							$ageGroupTitle = $bookingInfo[$b]->rate_for;
							$touristsAgeInfo[] = $bookingInfo[$b]->tourists . ' ' . $ageGroupTitle;

							if ($bookingInfo[$b]->check_in_status == '')
								$bookingCheckIn = 'Not Checked In';
							else {
								$checkIns = (array) json_decode($bookingInfo[$b]->check_in_status);
								$groupCheckInsCount = ['1' => 0, '2' => 0, 'A' => 0];

								$l = 0;
								$m = 0;
								for ($c = 0, $cc = $bookingInfo[$b]->tourists; $c < $cc; $c++) {
									if (isset($checkIns[$c]->status) && isset($bookingInfo[$b]->tourists) && $checkIns[$c]->status != 'N' && $c <= $bookingInfo[$b]->tourists) {
										$groupCheckInsCount[$checkIns[$c]->status]++;
										$bookingCheckInsCount[$checkIns[$c]->status]++;
									}
								}
							}
						}

						$bookingId = ($dayBookings[$i]->booking_id != null) ? $dayBookings[$i]->booking_id : '';
						$customerName = ($dayBookings[$i]->customer_name != null) ? $dayBookings[$i]->customer_name . "\n" : '';
						$phoneNumber = ($dayBookings[$i]->phone_number != null) ? $dayBookings[$i]->phone_number . "\n" : '';
						$email = ($dayBookings[$i]->email != null) ? $dayBookings[$i]->email . "\n" : '';

						$rateGroupTotal = '';
						$notCheckedInStatus = '';
						$firstCheckInStatus = '';
						$secondCheckInStatus = '';
						$absentCheckInStatus = '';

						$tcount = 0;
						if (isset($bookingCheckInsCount['1'])) {
							$tcount += $bookingCheckInsCount['1'];
						}

						if (isset($bookingCheckInsCount['2'])) {
							$tcount += $bookingCheckInsCount['2'];
						}

						if (isset($bookingCheckInsCount['A'])) {
							$tcount += $bookingCheckInsCount['A'];
						}

						$notCheckInCount = ($dayBookings[$i]->tourists + $dayBookings[$i]->infants) - (
							$tcount
						);

						// Not Checked In
						if ($notCheckInCount) {
							$notCheckInStatus = "N";
							$notCheckedInStatus = $notCheckInCount;
						}

						// 1st Check In
						if (isset($bookingCheckInsCount['1']) && $bookingCheckInsCount['1']) {
							$firstCheckInStatus = $bookingCheckInsCount['1'];
						}

						// 2nd Check In
						if (isset($bookingCheckInsCount['2']) && $bookingCheckInsCount['2']) {
							$secondCheckInStatus = $bookingCheckInsCount['2'];
						}

						// Absent
						if (isset($bookingCheckInsCount['A']) && $bookingCheckInsCount['A']) {
							$absentCheckInStatus = $bookingCheckInsCount['A'];
						}

						$comments = $dayBookings[$i]->comments;

						$inVar = implode(',', $touristsAgeInfo);
						foreach ($touristsAgeInfo as $value) {
							$rateGroupTotal .= $value . "\n";
						}

						if ($dayBookings[$i]->counter > 0) {
							$waiverSigned = $dayBookings[$i]->counter . ' Signed';
						}

						if ($dayBookings[$i]->tourists - $dayBookings[$i]->counter) {
							$waiverSigned = ($dayBookings[$i]->tourists - $dayBookings[$i]->counter) . ' Not Signed';
						}




						$bookedBy = $dayBookings[$i]->created_by_user_type ?? '';
						$notes = $dayBookings[$i]->note ?? '';
						$customer = $customerName . $phoneNumber . $email;

						fputcsv($file, array(
							$bookingId,
							$customer,
							$rateGroupTotal,
							$notCheckedInStatus,
							$firstCheckInStatus,
							$secondCheckInStatus,
							$absentCheckInStatus,
							$comments,
							$waiverSigned,
							//'Booking Total: ' .  . "\n" . 'Unpaid Amount: ' . $unpaidAmount . "\n" . 'Payment Amount: ' . $paymentAmount,
							$bookedBy,
							$notes
						));

						if ($i == $ic - 1 || $dayBookings[$i + 1]->tour_package_id != $tourPackageId || $dayBookings[$i + 1]->$agendaViewId != $slotId) {
							fputcsv($file, array($blankLine));
							fputcsv($file, array($blankLine));
						}
					}
				}

				fclose($file);
			};

			return response()->stream($callback, 200, $headers);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Edit Booking Discount
	 *
	 * @param array $data
	 * @param int $bookingId
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function editDiscount(Request $request)
	{
		try {
			$userType = userType();
			$bookingT = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->bookingID)
				->orderByDesc('tpr.age_from')
				->orderByDesc('tpr.age_to')
				->get([
					'tbt.id',
					'tbt.tourists',
					'tbt.processing_charge_percentage',
					'tbt.additional_tax_percentage',
					'tbt.permit_fee',
					'tbt.additional_charge',
					'tbt.state_tax',
					'tbt.rate',
					'tbt.total',
					'tbt.stripe_fees_percentage',
					'tpr.rate_for'
				]);
			$total = 0;

			if (count($bookingT) > 0) {
				foreach ($bookingT as $value) {
					$total += $value->total;
				}
			}
			$totalTicketCost = $total;


			$booking = $this->tourBooking::where('id', $request->bookingID)
				// tour_promotion_id/tourists/tour_slot_id were missing here -- tour_promotion_id
				// != 0 below always read as false against a null column (null != 0 is false in
				// PHP), so the old-promotion lookup never ran; tourists/tour_slot_id are needed
				// by the 'Promo Code' case's min_allowed_tourists/date-range checks below.
				->select('discount2_value', 'discount2_percentage', 'discount_promo_code', 'total', 'service_commission', 'tour_promotion_id', 'tourists', 'tour_slot_id')
				->first();

			if ($booking->tour_promotion_id != 0) {
				$oldTourPromotion = $this->promotion::where('code', $booking->tour_promotion_id)->first();
			}

			$fixedAmount = $request->has('edit_discount_fixed_money') ? (float)$request->edit_discount_fixed_money : 0.00;
			$discountPercentage = $request->has('edit_discount_percentage') ? (float)$request->edit_discount_percentage : 0;
			$discountPromocode = $request->has('edit_discount_promo_code') ? $request->edit_discount_promo_code : null;

			$newDiscount2Value = 0;
			$newDiscount2Percentage = 0;
			$newPromocode = '';
			$newTourPromotionID = 0;
			switch ($request->edit_discount_type) {
				case 'Fixed Money':
					if ($booking->discount2_value == $request->edit_discount_fixed_money) {
						throw new Exception("The discount value cannot be same as old");
					}
					if ($fixedAmount > $total) {
						throw new Exception("The discount amount cannot be greater than the total booking amount of " . $total);
					}
					$newDiscount2Value = $fixedAmount;
					break;
				case 'Percentage':
					if ($booking->discount2_percentage == $request->edit_discount_percentage) {
						throw new Exception("The discount percentage cannot be same as old");
					}
					if ($discountPercentage < 0 || $discountPercentage > 100) {
						throw new Exception("The discount percentage must be between 0 and 100");
					}
					$discountValue = ($total * $discountPercentage) / 100;
					if ($discountValue > $total) {
						throw new Exception("The discount amount cannot be greater than the total booking amount of " . $total);
					}
					$newDiscount2Value = $discountValue;
					break;
				case 'Promo Code':
					if ($booking->discount_promo_code == $request->edit_discount_promo_code) {
						throw new Exception("The Promocode cannot be same as old");
					}

					// Same status/date-range scoping the checkout-time coupon flow already
					// applies (see API\V2\TourBookingController::applyCoupon() /
					// NetworkService::applyCoupon()) -- an inactive or expired code just falls
					// through to the existing "invalid promocode" error below, same as there.
					// Scoped to the BOOKING'S OWN tour date (not "today"), matching that same
					// convention -- a promotion is valid for tours happening in its window,
					// regardless of when the admin happens to be editing the discount.
					$tourSlotTime = $this->tourSlotTime::find($booking->tour_slot_id);
					$tourDate     = $tourSlotTime->date ?? null;

					$tourPromotion = $this->promotion::where('code', $discountPromocode)
						->where('status', 'Active')
						->when($tourDate, fn ($q) => $q->whereRaw('? between `start_date` and `end_date`', [$tourDate]))
						->first();
					if (!$tourPromotion) {
						throw new Exception("Invalid Promocode. Please enter correct promocode.");
					}

					// tour_bookings_master.tourists already excludes infants by convention --
					// infants live in the separate `infants` column, and `tourists` is only ever
					// incremented/decremented for non-infant-rate headcounts (see the
					// is_infant_rate-gated $setTourist/$setInfant split used throughout this
					// repository, e.g. changePeopleBooking()). So this booking's own `tourists`
					// is already the correct, infant-excluded figure to compare against.
					if ((int) $tourPromotion->min_allowed_tourists > 0 && (int) $booking->tourists < (int) $tourPromotion->min_allowed_tourists) {
						throw new Exception("This promo code requires at least " . $tourPromotion->min_allowed_tourists . " tourist(s) on the booking.");
					}

					// Same "how many bookings already used this exact promotion" check the
					// checkout-time coupon flow already enforces. editDiscount() never reused
					// it, so the same promo code could be applied on unlimited existing bookings
					// even after max_allowed_usages was already reached elsewhere -- confirmed
					// live: a code created with max_allowed_usages=1, used once via Edit
					// Discount, was accepted again on a second, unrelated booking. 0 means
					// unlimited, matching that same convention.
					if ((int) $tourPromotion->max_allowed_usages > 0) {
						$promoUsageCount = $this->tourBooking::where('tour_promotion_id', $tourPromotion->id)->count();
						if ($promoUsageCount >= (int) $tourPromotion->max_allowed_usages) {
							throw new Exception("This promo code has already reached its maximum allowed usage.");
						}
					}

					$newTourPromotionID = $tourPromotion->id;
					$newPromocode = $discountPromocode;
					if ($tourPromotion->discount_value_type == 'Fixed Money') {
						$newDiscount2Value = $tourPromotion->discount_value;
					}

					if ($tourPromotion->discount_value_type == 'Percent') {
						$newDiscount2Percentage = $tourPromotion->discount_value;

						$newDiscount2Value = $total * $newDiscount2Percentage / 100;
					}

					// Same cap the Fixed Money / Percentage branches above already enforce -- a
					// promo code's resulting discount must not exceed the booking's own total
					// either (this branch was the one case here that never checked it).
					if ($newDiscount2Value > $total) {
						throw new Exception("The discount amount cannot be greater than the total booking amount of " . $total);
					}
					break;
				default:
					return response()->json(['errors' => ['common' => 'Invalid discount type.']], 400);
			}

			$calculateAmount = BookingService::calculateAddEditDiscountAmount($request->bookingID, $newDiscount2Value);
			$totals = $this->transaction::where('tour_booking_id', $request->bookingID)
				->select(
					DB::raw('SUM(CASE WHEN status = "Completed" THEN amount ELSE 0 END) as totalPayment'),
					DB::raw('SUM(CASE WHEN status = "Refund" THEN amount ELSE 0 END) as totalRefund'),
					DB::raw('SUM(CASE WHEN status = "Completed" THEN amount ELSE 0 END) + SUM(CASE WHEN status = "Refund" THEN amount ELSE 0 END) as netAmount'),
					DB::raw('SUM(CASE WHEN status = "Completed" THEN fee_amount ELSE 0 END) + SUM(CASE WHEN status = "Refund" THEN fee_amount ELSE 0 END) as netFeeAmount')
				)
				->first();

			$totalPayment = (float)$totals['totalPayment'];
			$totalRefund = (float)$totals['totalRefund'];
			$paymentStatus = 'same';
			$cashAmount = 0;
			if ($request->payment_method == 'Cash') {
				if ($calculateAmount['discountType'] === 'refund') {
					$paymentStatus = 'refund';
					$cashAmount =  (cashFees() == 1) ? ((float)$calculateAmount['newDiscountValue'] + (float)$calculateAmount['fees']) : (float)$calculateAmount['newDiscountValue'];
					$totalRefund += (float)$cashAmount;
				} else {
					$paymentStatus = 'additional';
					$cashAmount = (cashFees() == 1) ? ((float)$calculateAmount['newDiscountValue'] + (float)$calculateAmount['fees']) : (float)$calculateAmount['newDiscountValue'];
					$totalPayment += (float)$cashAmount;
				}
			} else {
				if ($calculateAmount['discountType'] === 'refund') {
					$paymentStatus = 'refund';
					$cashAmount =  (float)$calculateAmount['newDiscountValue'] + (float)$calculateAmount['fees'];
					$totalRefund += (float)$cashAmount;
				} else {
					$paymentStatus = 'additional';
					$cashAmount = (float)$calculateAmount['newDiscountValue'] + (float)$calculateAmount['fees'];
					$totalPayment += (float)$cashAmount;
				}
			}

			// Fail fast, before the confirm dialog is even shown: a discount refund can only
			// legitimately draw on ticket cost that hasn't already been refunded elsewhere
			// (Partial Refund, Remove Pax, etc). See ensureDiscountRefundWithinTicketAvailability()'s
			// docblock for the confirmed live incident (booking D-71132) this closes.
			if ($paymentStatus === 'refund' && $request->payment_method !== 'Pay Later') {
				BookingService::ensureDiscountRefundWithinTicketAvailability((int) $request->bookingID, (float) $cashAmount);
			}

			$paymentOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Payment')
				->get([
					'id',
					'ticket_cost',
					'processing_fees',
					'platform_fees'
				]);

			$trans = $this->transaction::where('tour_booking_id', $request->bookingID)
				->first('payment_type');

			$paymentOnlyCheck = 0;
			foreach ($paymentOnly as $value) {
				$paymentOnlyCheck += $value->ticket_cost + $value->processing_fees + $value->platform_fees;
			}

			$splitPendingAmount = round($booking->total - $paymentOnlyCheck, 2);
			$newTotal = $calculateAmount['newDiscountValue'];
			$totalSumBooking = ($totalPayment - $totalRefund) + $totalRefund;
			$totalSumFees =  $calculateAmount['total_discount_fees'];

			$serviceFees = 0.00;
			if ($request->payment_method === "Card" || ($request->payment_method === "Cash" && cashFees() == 1)) {
				$serviceFees = $calculateAmount['fees'];
			}

			//calculate refund value
			$refundOnly = $this->tourBookingHistory::where('tour_booking_id', $request->bookingID)
				->where('operation', 'Refund')
				->get([
					'id',
					'details'
				]);

			$refundOnlyCheck = 0;
			foreach ($refundOnly as $value) {
				$details = json_decode($value->details, true);
				$refundOnlyCheck += $details['amount'];
			}

			$discountHistoryArray = array(
				'tour_booking_id' => (int)$request->bookingID,
				'old_tour_promotion_id' => (isset($oldTourPromotion)) ? $oldTourPromotion->id : 0,
				'old_discount2_value' => $calculateAmount['oldDiscountValue'],
				'old_discount2_percentage' => (int)$booking->discount2_percentage,
				'old_discount_promo_code' => $booking->discount_promo_code,
				'old_total' => (int)$booking->total,
				'service_commission' => $serviceFees,
				'discount_type' => $request->edit_discount_type,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'updated_by' => Auth::id(),
				'updated_by_user_type' => $userType,
				'new_tour_promotion_id' => $newTourPromotionID,
				'new_discount2_value' => (float)$calculateAmount['discountValue'],
				'rate' => $calculateAmount['sub_total'],
				'new_discount2_percentage' => $discountPercentage,
				'new_discount_promo_code' => $newPromocode,
				'new_total' => (float)$newTotal,
				'payment_method' => $request->payment_method,
				'payment_type' => $calculateAmount['discountType'],
				'total_sum_booking' => $totalSumBooking,
				'total_sum_fees' => $totalSumFees,
				'cash_amount' => $cashAmount,
				'split_pending_amount' => $splitPendingAmount,
				'split_check' => $paymentOnlyCheck - $totalRefund,
				'totalTicketCost' => $totalTicketCost,
				'total_refund' => $refundOnlyCheck,
			);

			if ($request->payment_method == 'Pay Later') {
				$appliedDiscountResponse = $this->applyDiscount('Pay Later', $discountHistoryArray);
				return $appliedDiscountResponse;
			}

			if (!empty($trans) && $trans->payment_type == 1 && $splitPendingAmount > 0) {
				$appliedDiscountSplitData = $this->applyDiscount('Split Payment', $discountHistoryArray);
				return $appliedDiscountSplitData;
			}

			/* api */
			if (isset($request->is_click) && $request->is_click == 1) { //second time call api - apply discount
				$appliedDiscountApiData = $this->applyDiscount($request->payment_method, $discountHistoryArray);
				return $appliedDiscountApiData;
			}

			if ($discountHistoryArray['total_sum_booking'] == $discountHistoryArray['total_refund']) {
				$totalPay = $discountHistoryArray['total_sum_booking'] - $discountHistoryArray['total_refund'];
				throw new Exception("Unable to apply discount. Refund amount (" . $discountHistoryArray['cash_amount'] . ") is greater than unrefundeed amount on charge (" . $totalPay . ")");
			}

			return response()->json(['discountHistory' => $discountHistoryArray, 'isSplit' => ($splitPendingAmount > 0) ? true : false, 'paymentStatus' => $paymentStatus, 'cashAmount' => abs($cashAmount)]);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Apply discount in the booking
	 * 
	 * @param string $paymentMethod
	 * @param array $discountHistoryArray
	 * 
	 * @version 1.0.0
	 */
	public function applyDiscount($paymentMethod, $discountHistoryArray)
	{
		if ($paymentMethod != 'Pay Later' && ($discountHistoryArray['total_sum_booking'] == $discountHistoryArray['total_refund'])) {
			$totalPay = $discountHistoryArray['total_sum_booking'] - $discountHistoryArray['total_refund'];
			throw new Exception("Unable to apply discount. Refund amount (" . $discountHistoryArray['cash_amount'] . ") is greater than unrefundeed amount on charge (" . $totalPay . ")");
		}
		DB::beginTransaction();
		try {

			$cashFees = cashFees();

			if ($paymentMethod == 'Pay Later' || $paymentMethod == 'Split Payment') {
				// add edit discount history
				$newDiscount = BookingService::addEditDiscount($discountHistoryArray);
				// edit tour booking table data
				$bookingDiscountHistoryArray = $discountHistoryArray;
				$bookingDiscountHistoryArray['rate'] = $discountHistoryArray['total_sum_booking'];
				$discountHistory = $this->discountHistory::find($newDiscount->id);
				if ($paymentMethod == 'Pay Later') {
					$bookingDiscountHistoryArray['service_commission'] = 0.00;
					$payLater = 1;
					BookingService::updateBooking($bookingDiscountHistoryArray, $bookingDiscountHistoryArray['payment_type'] !== 'refund', $payLater, 0);
					HistoryService::updateBookingDiscountInHistory($discountHistoryArray['tour_booking_id'], $discountHistoryArray);
				} else {
					$splitPayment = 1;
					$discountHistoryArray['service_commission'] = ($paymentMethod == "Card" || ($cashFees == 1 && $paymentMethod == "Cash")) ? $discountHistoryArray['service_commission'] : 0.00;
					BookingService::updateBooking($bookingDiscountHistoryArray, $bookingDiscountHistoryArray['payment_type'] !== 'refund', 0, $splitPayment);
					HistoryService::updateBookingDiscountInHistory($discountHistoryArray['tour_booking_id'], $discountHistoryArray, $splitPayment);
					$discountHistory['is_split'] = 1;
				}
				DB::commit();
				return response()->json(['discountHistory' => $discountHistory]);
			} else if ($paymentMethod == 'Cash' || $paymentMethod == 'Card' || $paymentMethod == 'Invoiced') {
				// Defense-in-depth backstop (editDiscount()'s preview already checks this, but
				// editDiscountCashPayment()/editDiscountRefund() re-enter applyDiscount() with a
				// caller-supplied $discountHistoryArray, so this is re-verified right before
				// anything is written/refunded) -- see ensureDiscountRefundWithinTicketAvailability()'s
				// docblock.
				if (($discountHistoryArray['payment_type'] ?? null) === 'refund') {
					BookingService::ensureDiscountRefundWithinTicketAvailability(
						(int) $discountHistoryArray['tour_booking_id'],
						(float) ($discountHistoryArray['cash_amount'] ?? 0)
					);
				}

				// Update tour bookings master table data
				$bookingDiscountHistoryArray = $discountHistoryArray;
				$bookingDiscountHistoryArray['rate'] = $discountHistoryArray['total_sum_booking'];

				BookingService::updateBooking($bookingDiscountHistoryArray, $bookingDiscountHistoryArray['payment_type'] !== 'refund');
				$discountHistoryArray['service_commission'] = ($paymentMethod == "Card" || ($cashFees == 1 && $paymentMethod == "Cash")) ? $discountHistoryArray['service_commission'] : 0.00;

				// Add transaction table data
				$amount = $discountHistoryArray['new_total'] + $discountHistoryArray['service_commission'];
				$feesAmount = $discountHistoryArray['service_commission'];
				$transactionNumber = $discountHistoryArray['transaction_number'] ?? null;
				$appFeeId = $discountHistoryArray['application_fee_id'] ?? null;
				if ($discountHistoryArray['payment_type'] === "refund") {
					$chargeType = 'discount';
					// Only a Card discount ever carries a real Stripe PI in $transactionNumber —
					// Cash/Invoiced never touch Stripe, so payment_mode isn't a meaningful concept
					// for them and the column's own DESTINATION default is the correct, inert
					// value. A refund always targets an already-recorded charge, so a non-empty
					// $transactionNumber is guaranteed to have a matching row.
					$discountRefundMode = !empty($transactionNumber)
						? DB::table('transactions')->where('transaction_number', $transactionNumber)->value('payment_mode')
						: PaymentModeResolver::DESTINATION;
					$transactionId = TransactionService::storeRefundInTransaction($discountHistoryArray['tour_booking_id'], $amount, $feesAmount, $paymentMethod, $transactionNumber, $appFeeId, $chargeType, mode: $discountRefundMode);
					$discountHistoryArray['transaction_id'] = $transactionId;
				} else {
					$paymentType = ($discountHistoryArray['split_pending_amount'] > 0) ? 1 : 0;
					if (!(isset($discountHistoryArray['transaction_id']) && $discountHistoryArray['transaction_id'])) {
						$discountHistoryArray['from_edit_discount'] = 1;
						$transactionId = TransactionService::storePaymentInTransaction($discountHistoryArray['tour_booking_id'], $amount, $feesAmount, $paymentMethod, $paymentType, $transactionNumber, $appFeeId, $discountHistoryArray);
						$discountHistoryArray['transaction_id'] = $transactionId;
					}
				}

				// Add tour booking history table data
				HistoryService::updateBookingDiscountInHistory($discountHistoryArray['tour_booking_id'], $discountHistoryArray);
				$newDiscount = BookingService::addEditDiscount($discountHistoryArray);

				$discountHistory = $this->discountHistory::find($newDiscount->id);
				DB::commit();
				return response()->json(['discountHistory' => $discountHistory]);
			} else {
				DB::rollBack();
				throw new Exception("Invalid Payment Type");
			}
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Edit discount cash payment for the booking
	 * 
	 * @version 1.0.0
	 */
	public function editDiscountCashPayment()
	{
		try {
			$discountHistoryArray = request()->has('discountHistory') ? json_decode(request()->discountHistory, true) : "";
			$appliedDiscountResponse = $this->applyDiscount($discountHistoryArray['payment_method'], $discountHistoryArray);
			return $appliedDiscountResponse;
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Create payment intent for the new booking
	 *
	 * @param array $data
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function createEditDiscountPaymentIntent($data)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$tour_operator_name = $data['statement_descriptor'];

			$data['total'] = !empty($data['total']) ? $data['total'] : 0;
			$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $data['booking_id'])->where('deleted_by', 0)->first();
			$destination_amount = roundout($data['total'] * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
			$data['service_commission'] = $destination_amount;
			if ($data['total'] != 0) {
				$paymentIntentId = ($data['payment_intent_id'] && $data['payment_intent_id'] != '') ? $data['payment_intent_id'] : '';
				if ($paymentIntentId) {
					// code for card reader web

					$intentMetadata = [
						'booking_id' => $data['booking_id'],
						'tour_package' => $data['tour_package'],
						'date' => $data['date'],
						'time_from' => $data['time_from'],
						'tourists' => $data['tourists'],
						'customer_id' =>  $data['customer_id'],
						'customer_name' => $data['customer_name'],
						'customer_phone' => $data['customer_phone'],
						'customer_email' => $data['customer_email'],
						'split_value' => (isset($data['split_value']) && !empty($data['split_value'])) ? 1 : 0,
						'currency' => currencyFormation(code: true),
						'amount' => $data['total'] * 100, // stripe needs amount in cents
						'statement_descriptor' => $tour_operator_name,
						'confirm' => true,
						'application_fee_amount' => $destination_amount * 100,
						'edit_discount' => 1
					];
					$paymentIntent = BookingService::updateCardReaderIntent($paymentIntentId, $intentMetadata);
				}
				if (isset($paymentIntent) && ($paymentIntent->status == 'requires_action' || $paymentIntent->status == 'requires_source_action')) {
					$routePrefix = $userType == 'Affiliate' ? '' : 'tour-operator.';
					$returnUrl = route(prefixedRouteName($routePrefix . 'booking.create'));

					$confirmParams = [
						'return_url' => $returnUrl,
						'payment_method_options' => [
							'card' => [
								'request_three_d_secure' => 'any'
							]
						],
						'expand' => ['charges.data.transfer']
					];
					$paymentIntentConfirm = app(PaymentGatewayInterface::class)->confirmPayment($paymentIntent->id, $confirmParams);

					return [
						'clientSecret' => !empty($paymentIntentConfirm->client_secret) ? $paymentIntentConfirm->client_secret : 123,
						'intentId' => !empty($paymentIntentConfirm->id) ? $paymentIntentConfirm->id : null,
						'nextAction' => $paymentIntentConfirm->next_action
					];
				}
			}
			if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
				$chargeParams = $intentMetadata;
				app(PaymentGatewayInterface::class)->updateConnectedPayment(
					$paymentIntent->charges->data[0]->transfer->destination_payment,
					$chargeParams,
					$paymentIntent->charges->data[0]->transfer->destination
				);
			}

			$paymentType = (!empty($data['split_value']) || (isset($data['split_pending_amount']) && $data['split_pending_amount'] > 0)) ? 1 : 0;
			$paymentIntentId = !empty($paymentIntent->id) ? $paymentIntent->id : null;
			$applicationFeeId = !empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null;
			$cardInfo = isset($paymentIntent->charges->data[0]->payment_method_details) ? $paymentIntent->charges->data[0]->payment_method_details : [];
			$crd = '';
			if (isset($cardInfo['card_present']['last4'])) {
				$crd = (isset($cardInfo['card_present']['last4'])) ? $cardInfo['card_present']['last4'] : '';
			} else if (isset($cardInfo['card']['last4'])) {
				$crd = (isset($cardInfo['card']['last4'])) ? $cardInfo['card']['last4'] : '';
			}
			$payload['from_edit_discount'] = 1;
			$transactionId = TransactionService::storePaymentInTransaction($data['booking_id'], $data['total'], $data['service_commission'], 'Card', $paymentType, $paymentIntentId, $applicationFeeId, $payload, mode: isset($paymentIntent) ? PaymentModeInference::fromStripeObject($paymentIntent) : PaymentModeResolver::DESTINATION);
			DB::commit();
			return [
				'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
				'transactionId' => $transactionId,
				'status' => !empty($paymentIntent->status) ? $paymentIntent->status : '',
				'cardLast4' => $crd
			];
		} catch (CardException $exception) {
			Log::error($exception);
			HistoryService::updateBookingDiscountInHistory($data['booking_id'], $data);

			return [
				'clientSecret' => '',
				'transactionId' => '',
				'ex' => $exception,
				'cardLast4' => ''
			];
		}
	}

	/**
	 * Make a payment when discount applied and user need to pay
	 * @param request $request
	 * 
	 * @version 1.0.0
	 */
	public function editDiscountCardPayment($request)
	{
		$discountHistoryArray = $this->getDiscountHistoryArray($request);

		// Fetch necessary data
		$master = $this->tourBooking::find($discountHistoryArray['tour_booking_id']);
		$tourPackageInfo = $this->getTourPackageInfo($master->tour_slot_id);

		// Webhook handling
		$discountHistoryArray['webhook'] = $this->getWebhookStatus($request);

		if ($request->is('api/*') && $request->has('payment_intent_id')) {
			return $this->handleApiPayment($request, $discountHistoryArray, $tourPackageInfo, $master);
		} else {
			return $this->handleWebPayment($request, $discountHistoryArray, $tourPackageInfo, $master);
		}
	}

	/**
	 * Discount history for card edit discount payment.
	 *
	 * @param request $request
	 */
	private function getDiscountHistoryArray($request)
	{
		if ($request->is('api/*')) {
			return $request->has('discountHistory') ? $request->discountHistory : "";
		} else {
			return $request->has('discountHistory') ? json_decode($request->discountHistory, true) : "";
		}
	}

	/**
	 * Webhook status for card edit discount payment.
	 *
	 * @param request $request
	 */
	private function getWebhookStatus($request)
	{
		return (!empty($request->webhook) && $request->webhook == 1) ? 1 : 0;
	}

	/**
	 * Handle API payment for card edit discount payment.
	 *
	 * @param request $request
	 * @param array $discountHistoryArray
	 * @param $tourPackageInfo
	 * @param $master
	 */
	private function handleApiPayment($request, $discountHistoryArray, $tourPackageInfo, $master)
	{
		$paymentIntentId = $request->payment_intent_id;
		$paymentIntentInfo = PaymentIntent::retrieve(['id' => $paymentIntentId]);

		$last4 = $this->getCardLast4($paymentIntentInfo);

		// Update discount history with transaction info
		$discountHistoryArray['transaction_id'] = $paymentIntentId;
		$discountHistoryArray['cardLast4'] = $last4;

		if ($request->webhook != 1) {
			return $this->applyDiscount($discountHistoryArray['payment_method'], $discountHistoryArray);
		} else {
			return $discountHistoryArray['tour_booking_id'];
		}
	}

	/**
	 * Payment Intent Info for card edit discount payment.
	 *
	 * @param $paymentIntentInfo
	 */
	private function getCardLast4($paymentIntentInfo)
	{
		$paymentMethodDetails = $paymentIntentInfo->charges->data[0]->payment_method_details;
		return $paymentMethodDetails->card_present->last4 ?? $paymentMethodDetails->card->last4 ?? '';
	}

	/**
	 * Handle Web payment for card edit discount payment.
	 *
	 * @param request $request
	 * @param array $discountHistoryArray
	 * @param $tourPackageInfo
	 * @param $master
	 */
	private function handleWebPayment($request, $discountHistoryArray, $tourPackageInfo, $master)
	{
		$paymentIntentId     = $request->has('payment_intent_id') ? $request->payment_intent_id : "";
		$combinedChargeTotal = (float) ($request->combined_charge_total ?? 0);

		$data = [
			'discount_detail'       => $discountHistoryArray,
			'tour_PackageInfo'      => $tourPackageInfo,
			'tour_booking'          => $master,
			'combined_charge_total' => $combinedChargeTotal,
		];

		$paymentIntentData = $this->prepareDiscountPaymentIntentData($data);
		$paymentIntentData['payment_intent_id'] = $paymentIntentId ?: "";

		$paymentIntentInfo = $this->createEditDiscountPaymentIntent($paymentIntentData);

		$discountHistoryArray['transaction_id'] = $paymentIntentInfo['transactionId'];
		$discountHistoryArray['cardLast4']      = $paymentIntentInfo['cardLast4'];

		// Resolve any pending remaining_payment link BEFORE calling applyDiscount so we can
		// clear split_pending_amount when the combined PI covers everything in one charge.
		$pendingLink = null;
		if ($paymentIntentId && !empty($master->group_id)) {
			$pendingLink = TourBookingPaymentLink::where('order_id', $master->group_id)
				->where('status', 'Unpaid')
				->where('section', 'remaining_payment')
				->first();
		}

		// The combined PI charges the discount amount plus any pending link items in a single
		// Stripe charge — it is not a split payment. Zeroing split_pending_amount makes
		// applyDiscount() record "Full Payment" instead of "Split Payment" in history.
		if ($pendingLink) {
			$discountHistoryArray['split_pending_amount'] = 0;
		}

		$result = $this->applyDiscount($discountHistoryArray['payment_method'], $discountHistoryArray);

		// Finalize pending link items (addon, change_people, discount, etc.) against this same PI.
		if ($pendingLink) {
			try {
				$paymentIntentObj = app(PaymentGatewayInterface::class)->retrievePayment(
					$paymentIntentId, ['expand' => ['charges.data.transfer']]
				);
				RemainingPaymentService::finalizeItems($pendingLink, $paymentIntentObj, $master->id, 'Card');
			} catch (\Exception $e) {
				Log::error('RemainingPaymentService finalizeItems failed after card edit_discount (V1): ' . $e->getMessage());
			}
		}

		return $result;
	}


	/**
	 * Provide refund if the discount add after booking payment
	 * @param $request
	 * 
	 * @version 1.0.0
	 */
	public function editDiscountRefund($request)
	{
		try {
			$discountHistoryArray = $request->has('discountHistory') ? json_decode($request->discountHistory, true) : "";

			// Refund Amount Start
			if ($discountHistoryArray['payment_method'] === 'Card') {
				if ($discountHistoryArray['total_sum_booking'] == $discountHistoryArray['total_refund']) {
					$totalPay = $discountHistoryArray['total_sum_booking'] - $discountHistoryArray['total_refund'];
					throw new Exception('Unable to apply discount. Refund amount (' . $discountHistoryArray['cash_amount'] . ') is greater than charge amount (' . $totalPay . ')');
				}

				// This is the actual Stripe refund call below -- the critical checkpoint. The
				// equality check just above only catches "this booking has been refunded in
				// full" (every charge_type combined); it can't tell "tickets are already fully
				// refunded but add-ons/Don't-Refund settlements are still outstanding" apart from
				// that, which is exactly what let a discount refund draw down non-ticket money on
				// booking D-71132. See ensureDiscountRefundWithinTicketAvailability()'s docblock.
				if (($discountHistoryArray['payment_type'] ?? null) === 'refund') {
					BookingService::ensureDiscountRefundWithinTicketAvailability(
						(int) $discountHistoryArray['tour_booking_id'],
						(float) ($discountHistoryArray['cash_amount'] ?? 0)
					);
				}

				if ($request->has('check_multiple_transactions_refund') && $request->check_multiple_transactions_refund != '') {
					$amount = round(($discountHistoryArray['new_total'] + $discountHistoryArray['service_commission']) * 100);

					$TSDetails = $this->transaction::where('tour_booking_id', $discountHistoryArray['tour_booking_id'])
						->where('transaction_number', $request->check_multiple_transactions_refund)
						->where('status', 'Completed')
						->first(['id', 'amount', 'application_fee_id', 'payment_mode']);

					$transactionAmount = round($TSDetails->amount * 100);

					if ($transactionAmount < $amount) {
						// throw new Exception('Refund amount (' . $amount . ') is greater than charge amount (' . $TSDetails->amount . ')');
						throw new Exception('Refund amount (' . number_format($amount / 100, 2) . ') exceeds charge amount (' . number_format($transactionAmount / 100, 2) . ')');
					}

					$refundParams = [
						'payment_intent' => $request->check_multiple_transactions_refund,
						'amount' => $amount,
						'reverse_transfer' => true,
						'refund_application_fee' => true
					];
					$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $TSDetails->payment_mode);

					$discountHistoryArray['transaction_number'] = $request->check_multiple_transactions_refund;
					$discountHistoryArray['application_fee_id'] = $TSDetails->application_fee_id;
				} else {
					$amount = round(($discountHistoryArray['new_total'] + $discountHistoryArray['service_commission']) * 100);
					$transactions = DB::table('transactions')->where('tour_booking_id', $discountHistoryArray['tour_booking_id'])->first();

					$refundParams = [
						'payment_intent' => $transactions->transaction_number,
						'amount' => $amount,
						'reverse_transfer' => true,
						'refund_application_fee' => true
					];
					$refund = app(PaymentGatewayInterface::class)->refundPayment($refundParams, $transactions->payment_mode);
					$discountHistoryArray['transaction_number'] = $transactions->transaction_number;
					$discountHistoryArray['application_fee_id'] = $transactions->application_fee_id;
				}
			}

			// Refund Amount End
			// Apply Discount
			$appliedDiscountResponse = $this->applyDiscount($discountHistoryArray['payment_method'], $discountHistoryArray);
			return $appliedDiscountResponse;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get tour package information
	 * 
	 * @param int $tourSlotId
	 * 
	 * @version 1.0.0
	 */
	public function getTourPackageInfo($tourSlotId)
	{
		$info = "SELECT
			`tst`.`time_from`, `tst`.`date`, `tp`.`tour_operator_id`, `tst`.`tour_package_id`,
			`tp`.`name` AS `package_name`, `tp`.`stripe_destination_id`,`t_o`.`statement_descriptor`,`t_o`.`card_reader_prefix`
		FROM
			`tour_slot_times` as `tst`
			INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tst`.`tour_package_id`
			INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
		WHERE
			`tst`.`id` = " . $tourSlotId . "
		";

		$info = collect(DB::select($info))->first();

		return $info;
	}

	/**
	 * Prepate the payment intent for the edit discount feature to make a payment
	 * 
	 * @param array $data
	 * 
	 * @version 1.0.0
	 */
	public function prepareDiscountPaymentIntentData($data)
	{
		$discountHistoryArray = $data['discount_detail'];
		$tourPackageInfo = $data['tour_PackageInfo'];
		$cardDetails = isset($data['discount_detail']['card_detail']) ? $data['discount_detail']['card_detail'] : [];
		$tourBooking = $data['tour_booking'];
		$response = [
			'total' => $discountHistoryArray['new_total'] + $discountHistoryArray['service_commission'],
			'discount2_value' => $discountHistoryArray['new_discount2_value'],
			'customer_id' => $tourBooking->customer_id,
			'customer_name' => $tourBooking->name,
			'customer_email' => $tourBooking->email,
			'customer_phone' => $tourBooking->phone_number,
			'tourists' => $tourBooking->tourists,
			'booking_id' => $discountHistoryArray['tour_booking_id'],
			'tour_package' => $tourPackageInfo->package_name,
			'date' => $tourPackageInfo->date,
			'destination' => $tourPackageInfo->stripe_destination_id,
			'time_from' => $tourPackageInfo->time_from,
			'subtotal' => $discountHistoryArray['new_total'],
			'service_commission' => $discountHistoryArray['service_commission'],
			'month' => $cardDetails ? $cardDetails['month'] : "",
			'year' => $cardDetails ? $cardDetails['year'] : "",
			'cardnumber' => $cardDetails ? $cardDetails['cardNumber'] : "",
			'cvc' => $cardDetails ? $cardDetails['cvc'] : "",
			'tour_operator_id' => $tourPackageInfo->tour_operator_id,
			'statement_descriptor' => $tourPackageInfo->statement_descriptor,
			'card_reader_prefix' => $tourPackageInfo->card_reader_prefix,
			'tour_package_id' => $tourPackageInfo->tour_package_id,
			'split_value' => '',
			'slot_time' => $tourBooking->tour_slot_id,
		];
		return $response;
	}

	/**
	 * Extract the month and year to filter the data for the calendar
	 * 
	 * @param string $date
	 * 
	 * @version 1.0.0
	 */
	public function extractMonthAndYear($date)
	{
		// Define a regular expression to match the date format
		$pattern = '/(\d{2})\/(\d{2})/';

		// Use preg_match to extract month and year
		if (preg_match($pattern, $date, $matches)) {
			$month = (int) $matches[1];
			$year = (int) $matches[2];

			return [
				'month' => $month,
				'year' => $year,
			];
		} else {
			// Handle invalid date format
			throw new Exception('Invalid date format');
		}
	}

	/**
	 * Add custom charge to the booking which is already created
	 *
	 * @param object $request
	 * @param int $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function addCustomCharge(object $request, int $id)
	{
		DB::beginTransaction();
		try {
			$isCashFees = cashFees();
			$routePrefix = BookingService::routePrefixInfo();
			$isCustomCharge = customCharge();
			$customChargeDetails = [];
			// Get the booking details
			$booking = $this->getBookingDetails($id);

			if (!$booking) {
				throw new Exception("Booking not found.");
			}

			if (!isset($request->api)) {
				$routeParams = ['booking' => $id];
				if ($request->has('modal'))
					$routeParams['modal'] = 1;
			}

			$paymentIntentId = $request->has('payment_intent_id') && $request->payment_intent_id != ''
				? $request->payment_intent_id
				: '';

			// Save the card details
			HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $id, "BACKEND");

			$transaction = $this->transaction::where('tour_booking_id', $id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
			$rateCheck = $this->bookingTransact::where('tour_booking_id', $id)->where('deleted_by', 0)->first();
			$serviceCommissionPercentage = $rateCheck->nat_fees_percentage;

			if ($isCustomCharge == 1) {
				$customChargeAmount = number_format((float)$request->custom_charge_amount, 2, '.', '');
				$customChargeIsTaxed = (isset($request->taxable) && $request->taxable == 1) ? 1 : 0;
				$customChargeDescription = $request->comment;
				$customChargeFees = $request->custom_charge_fees;
				$finalCustomChargeAmount = $request->custom_charge_total;

				$customChargeDetails = [
					'amount' => $customChargeAmount,
					'taxable' => $customChargeIsTaxed,
					'comment' => $customChargeDescription,
					'fees' => $customChargeFees
				];

				if ($finalCustomChargeAmount <= 0) {
					return [
						'bookingId' => $id,
						'message' => 'Please at least add a custom charge greater than zero.'
					];
				} else {
					// For Pay Later Bookings
					if ($transaction == null) {
						$paymentType = "Pay Later";
						$paymentStatus = "Open";
						if ($customChargeIsTaxed == 1) {
							$customChargeFees = ($customChargeAmount * $serviceCommissionPercentage) / 100;
							$customChargeDetails['fees'] = $customChargeFees;
						}
					}

					// Booking status
					if (!empty($transaction) && in_array($transaction->via, ["Invoiced", "Cash", "Card"])) {
						$paymentType = $transaction->via;
						$paymentStatus = "Paid";
					}


					if (!empty($transaction) && ($transaction->via == "Cash" || $transaction->via == "Invoiced")) {
						$finalCustomChargeAmount = $finalCustomChargeAmount - $customChargeFees;
						$customChargeFees = ($isCashFees == 1) ? $customChargeFees : 0;
						$customChargeIsTaxed = ($isCashFees == 1) ? $customChargeIsTaxed : 0;
						$customChargeDetails['taxable'] = $customChargeIsTaxed;
						$customChargeDetails['fees'] = $customChargeFees;
					}

					if ($request->is('api/*') && isset($request->paymentIntentId) && !empty($request->paymentIntentId)) {
						return CustomChargeBookingService::cardPaymentCustomChargeFromApp($id, $request->paymentIntentId, $customChargeDetails);
					}

					// For Card Bookings
					if (!empty($transaction) && $transaction->via == "Card" && $request->is('api/*') === false) {
						$paymentIntentId = ($request->has('payment_intent_id') && $request->payment_intent_id != '') ? $request->payment_intent_id : '';

						// Payment Intent Integration
						$errors = [];
						$paymentIntentInfo = [
							'clientSecret' => '',
							'transactionId' => '',
						];

						$tourBooking = $this->tourBooking::find($id);

						$data = [
							'amount' => $finalCustomChargeAmount,
							'customer_id' => $tourBooking->customer_id,
							'customer_name' => $tourBooking->name,
							'customer_email' => $tourBooking->email,
							'customer_phone' => $tourBooking->phone_number,
							'booking_id' => $tourBooking->id,
							'tour_package' => $booking->package_name,
							'date' => $booking->date,
							'time_from' => $booking->time_from,
							'month' => isset($month) ? $month : "",
							'year' =>  isset($year) ? $year : "",
							'cardnumber' => isset($cardnumber) ? $cardnumber : "",
							'cvc' => isset($cvc) ? $cvc : "",
							'destination' => $booking->stripe_destination_id,
							'tour_operator_id' => $booking->tour_operator_id,
							'statement_descriptor' => $booking->statement_descriptor,
							'card_reader_prefix' => $booking->card_reader_prefix,
							'webhook' => !empty($request->webhook) ? 1 : 0,
							'tour_package_id' => $booking->tour_package_id,
							'custom_charge' => $customChargeDetails,
							'custom_charge_amount' => $customChargeAmount,
							'custom_charge_fees' => $customChargeFees,
							'payment_intent_id' => $paymentIntentId
						];

						$paymentIntentInfo = $this->customChargeCreatePaymentIntent($data);

						if (isset($paymentIntentInfo['nextAction'])) {
							$tourBooking->stripe_paymentintent_id = $paymentIntentInfo['clientSecret'];
							$tourBooking->save();
							DB::commit();

							return [
								'bookingId' => $tourBooking->id,
								'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
								'stripeId' => $paymentIntentInfo['clientSecret'],
								'require3ds' => 1,
								'nextAction' => $paymentIntentInfo['nextAction'],
								'errors' => $errors,
								'errors_message' => !empty($errors_message) ? $errors_message : ''
							];
						}

						if ($paymentIntentInfo['clientSecret'] == '') {
							$errors[] = 'payment';
						}

						if (!empty($paymentIntentInfo['ex'])) {
							$errors_message = $paymentIntentInfo['ex']->getError()->message;
						}

						return [
							'bookingId' => $tourBooking->id,
							'bookingToken' => Crypt::encryptString($tourBooking->id), // for security purpose
							'stripeId' => $paymentIntentInfo['clientSecret'],
							'transactionId' => Crypt::encryptString($paymentIntentInfo['transactionId']),
							'errors' => $errors,
							'errors_message' => !empty($errors_message) ? $errors_message : '',
							'message' => 'The custom charge added successfully to the booking.'
						];
					}

					if (!empty($transaction) && ($transaction->via == "Cash" || $transaction->via == "Invoiced")) {
						// TransactionService to store custom charges in the transactions table for Cash
						$customChargeTransactionId = TransactionService::storeCustomChargeInTransaction($id, $finalCustomChargeAmount, $customChargeFees, $paymentType, '');
						DB::commit();

						// Save custom charges in history
						HistoryService::storeCustomChargePaymentInHistory($id, $paymentType, $customChargeAmount, $customChargeFees, $customChargeDetails, '', $customChargeTransactionId);
						DB::commit();
					}

					// CustomChargeBookingService to store custom charges with details in the tour_booking_custom_charge table
					CustomChargeBookingService::tourBookingCustomCharge(
						$id,
						$customChargeDetails,
						'',
						$paymentType,
						$paymentStatus
					);
					DB::commit();

					$message = 'The custom charge added successfully to the booking.';
					if ($paymentType != "Pay Later") {
						return [
							'bookingId' => $id,
							'message' => $message
						];
					}

					$currentURL = Route::currentRouteName();
					$route = ($currentURL == "affiliate.booking.add-custom-charge")
						? 'affiliate.booking.show'
						: $routePrefix . 'tour-operator.booking.show';

					return redirect()
						->route($route, $routeParams)
						->with('message', $message);
				}
			} else {
				return [
					'bookingId' => $id,
					'message' => 'The custom charge feature is disabled; please enable it from the settings module.'
				];
			}
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get booking details for custom charge
	 * 
	 * @param int $id
	 */
	public static function getBookingDetails($id)
	{
		$booking = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
			->where('tbm.id', $id)
			->first([
				'tbm.tourists',
				'tbm.status',
				'tst.id AS tour_slot_id',
				'tp.tour_operator_id',
				'tbm.email',
				'tbm.name',
				'tbm.phone_number',
				'cus.is_affiliate',
				'tbm.total',
				'tst.time_from',
				'tsd.date',
				'tp.id as tour_package_id',
				'tp.name AS package_name',
				'tp.stripe_destination_id',
				't_o.id as tour_operator_id',
				't_o.statement_descriptor',
				't_o.card_reader_prefix'
			]);

		return	$booking;
	}


	/**
	 * Create payment intent for the new custom charge added to the booking
	 *
	 * @param array $data
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function customChargeCreatePaymentIntent($data)
	{
		DB::beginTransaction();
		try {
			$userType = userType();

			$tour_operator_name = $data['statement_descriptor'];
			$data['amount'] = !empty($data['amount']) ? $data['amount'] : 0;
			$customChargeBaseAmount = $data['custom_charge_amount'] ?? $data['amount'];
			$customChargeFees = isset($data['custom_charge_fees']) ? floatval($data['custom_charge_fees']) : 0;
			$customer = $this->customer::find($data['customer_id']);

			$tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $data['booking_id'])->where('deleted_by', 0)->first();

			// Determine if taxable: check taxable flag OR if fees > 0 (fees are only sent when taxable)
			$isTaxable = (isset($data['custom_charge']['taxable']) && $data['custom_charge']['taxable'] == 1) || $customChargeFees > 0 ? 1 : 0;

			if ($isTaxable == 1) {
				// Use fees from data if available and > 0, otherwise calculate from base amount
				$stripeFees = $customChargeFees > 0
					? roundout($customChargeFees, 2)
					: roundout($customChargeBaseAmount * $tourBookingTransaction->stripe_fees_percentage / 100, 2);
			} else {
				$stripeFees = 0;
			}

			if ($data['amount'] != 0) {
				if ($customer->stripe_id == null) {
					$stripeCustomer = StripeCustomer::create([
						'name' => $customer->name,
						'email' => $customer->email,
						'phone' => $customer->phone_number
					]);

					$customer->stripe_id = $stripeCustomer->id;
					$customer->save();
				}
				$paymentIntentId = ($data['payment_intent_id'] &&  $data['payment_intent_id'] != '') ? $data['payment_intent_id'] : '';
				if (!$paymentIntentId) {
					$method = PaymentMethod::create([
						'type' => 'card',
						'card' => [
							'number' => $data['cardnumber'],
							'exp_month' => $data['month'],
							'exp_year' => $data['year'],
							'cvc' => $data['cvc'],
						],
					]);
					DB::commit();

					$mode = app(PaymentModeResolver::class)->resolveForSingleDate($data['date'] ?? null);
					$stripeAttributes = [
						'payment_method_types' => ['card'],
						'payment_method' => $method->id,
						'currency' => currencyFormation(code: true),
						'amount' => $data['amount'] * 100, // stripe needs amount in cents
						'statement_descriptor_suffix' => $tour_operator_name,
						'confirm' => true,
						'customer' => $customer->stripe_id,
						'expand' => ['charges.data.transfer'],
						...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($data['destination'], (int) round($stripeFees * 100), mode: $mode)
					];

					$stripeMetadata = [
						'description' => 'Booking Id: ' . $data['booking_id'] .
							"\nTour Package: " . $data['tour_package'] .
							"\nTour Date: " . $data['date'] .
							"\nTour Time: " . $data['time_from'] .
							"\nEmail: " . $data['customer_email'],
						'metadata' => [
							'booking_id' => $data['booking_id'],
							'tour_package' => $data['tour_package'],
							'tour_date' => $data['date'],
							'tour_time' => $data['time_from'],
							'customer_name' => $data['customer_name'],
							'customer_phone' => $data['customer_phone'],
							'email' => $data['customer_email'],
							'tenant_id' => tenant()->id,
						],
					];

					$paymentIntentParams = $stripeAttributes + $stripeMetadata;
					$paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);
				} else if ($paymentIntentId && !(request()->is('api/*'))) {
					$intentMetadata = [
						'booking_id' => $data['booking_id'],
						'tour_package' => $data['tour_package'],
						'date' => $data['date'],
						'time_from' => $data['time_from'],
						'tourists' => isset($data['tourists']) ? $data['tourists'] : "",
						'customer_id' =>  $data['customer_id'],
						'customer_name' => $data['customer_name'],
						'customer_phone' => $data['customer_phone'],
						'customer_email' => $data['customer_email'],
						'split_value' => (isset($data['split_value']) && !empty($data['split_value'])) ? 1 : 0,
						'currency' => currencyFormation(code: true),
						'amount' => $data['amount'] * 100, // stripe needs amount in cents
						'statement_descriptor' => $tour_operator_name,
						'confirm' => true,
						'application_fee_amount' => $stripeFees * 100,
						'custom_charge' => 1
					];
					$paymentIntent = BookingService::updateCardReaderIntent($paymentIntentId, $intentMetadata);
				}

				if (isset($paymentIntent) && ($paymentIntent->status == 'requires_action' || $paymentIntent->status == 'requires_source_action')) {
					if ($userType == "Affiliate") {
						$returnUrl = route('affiliate.booking.show', ['booking' => $data['booking_id']]);
					} else {
						if ($userType == "Admin") {
							$routePrefix = 'admin.';
						} elseif ($userType == "Tour Operator") {
							$routePrefix = '';
						}

						$returnUrl = route($routePrefix . 'tour-operator.booking.show', ['booking' => $data['booking_id']]);
					}

					$confirmParams = [
						'return_url' => $returnUrl,
						'payment_method_options' => [
							'card' => [
								'request_three_d_secure' => 'any'
							]
						],
						'expand' => ['charges.data.transfer']
					];
					$paymentIntentConfirm = app(PaymentGatewayInterface::class)->confirmPayment($paymentIntent->id, $confirmParams);

					return [
						'clientSecret' => !empty($paymentIntentConfirm->client_secret) ? $paymentIntentConfirm->client_secret : 123,
						'intentId' => !empty($paymentIntentConfirm->id) ? $paymentIntentConfirm->id : null,
						'nextAction' => $paymentIntentConfirm->next_action
					];
				}
			}

			if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
				$chargeParams = $stripeMetadata;
				app(PaymentGatewayInterface::class)->updateConnectedPayment(
					$paymentIntent->charges->data[0]->transfer->destination_payment,
					$chargeParams,
					$paymentIntent->charges->data[0]->transfer->destination
				);
			}

			// TransactionService to store custom charges in the transactions table for Card
			$customChargeTransactionId = TransactionService::storeCustomChargeInTransaction(
				$data['booking_id'],
				$data['custom_charge_amount'],
				$data['custom_charge_fees'],
				'Card',
				$paymentIntent,
				mode: PaymentModeInference::fromStripeObject($paymentIntent)
			);
			DB::commit();

			// CustomChargeBookingService to store custom charges with details in the tour_booking_custom_charge table
			CustomChargeBookingService::tourBookingCustomCharge(
				$data['booking_id'],
				$data['custom_charge'],
				!empty($paymentIntent->id) ? $paymentIntent->id : null,
				'Card',
				'Paid'
			);
			DB::commit();


			if (!empty($data['custom_charge'])) {
				$data['custom_charge']['status'] = 'Paid';
			}

			// Save custom charges in history
			HistoryService::storeCustomChargePaymentInHistory($data['booking_id'], 'Card', $data['custom_charge_amount'], $data['custom_charge_fees'], $data['custom_charge'], $paymentIntent, $customChargeTransactionId);
			DB::commit();

			return [
				'clientSecret' => !empty($paymentIntent->client_secret) ? $paymentIntent->client_secret : 123,
				'transactionId' => $customChargeTransactionId,
				'status' => !empty($paymentIntent->status) ? $paymentIntent->status : ''
			];
		} catch (CardException $exception) {
			Log::error($exception);
			DB::rollBack();

			HistoryService::storeCardPaymentErrorInHistory($data['booking_id'], $exception);

			return [
				'clientSecret' => '',
				'transactionId' => '',
				'ex' => $exception,
			];
		}
	}

	/**
	 * Refund Custom Charge from the booking
	 *
	 * @param object $request
	 * @param int $id
	 * @return object
	 * 
	 * @version 1.0.0
	 */
	public function refundCustomCharge(object $request, int $id)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$isCashFees = cashFees();
			$routePrefix = BookingService::routePrefixInfo();

			if (isset($request->custom_charge_ids) && $request->custom_charge_ids != "") {
				$request->custom_charge_ids = is_string($request->custom_charge_ids) ? json_decode($request->custom_charge_ids, true) : $request->custom_charge_ids;
			} else {
				throw new Exception("Please select at least one custom charge you want to refund.");
			}

			$transactions = $this->transaction::where('tour_booking_id', $id)->where('charge_type', 'custom_charge')->where('status', 'Completed')->get();
			$customerCheck = $this->tourBooking::where('id', $id)->first();
			$customer = $this->customer::where('id', $customerCheck->customer_id)->first();
			$rateCheck = $this->bookingTransact::where('tour_booking_id', $id)->where('deleted_by', 0)->first();
			$serviceCommissionPercentage = $rateCheck->nat_fees_percentage;

			$customChargeFields = [
				'tbm.id as tour_booking_id',
				'tbcc.id as custom_charge_id',
				'tbcc.parent_id',
				'tbcc.amount',
				'tbcc.fees',
				'tbcc.taxable',
				'tbcc.transaction_id',
				'tbcc.description',
				'tbcc.payment_method',
				'tbcc.status'
			];

			$customChargeParent = DB::table('tour_booking_custom_charges AS tbcc')
				->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbcc.tour_booking_id')
				->where('tbm.id', $id)
				->whereNull('tbcc.parent_id')
				->whereIn('tbcc.id', $request->custom_charge_ids);

			if (count($transactions) == 0) {
				$customChargeParent = $customChargeParent->get($customChargeFields);
			} else {
				$customChargeParent = $customChargeParent
					->join('transactions AS t', 't.tour_booking_id', '=', 'tbm.id')
					->where('t.charge_type', 'custom_charge')
					->where('t.status', 'Completed')
					->distinct()
					->get($customChargeFields);
			}

			if (count($customChargeParent) == 0) {
				throw new Exception("Tour booking custom charges not found.");
			}

			$customChargeFees = 0;
			$customChargeAmount = 0;
			$customChargeTaxable = 0;
			$amountCapturedStripe = 0;
			$amountRefundedStripe = 0;
			$totalrefundedamount = 0;
			$setFeeAmount = 0;
			$paymentIntentId = '';
			$applicationFeeId = null;
			$customChargeTransactionId = null;
			$cardLast4 = null;
			$refundCustomCharge = [];
			$history = [];
			$getTransactions = [];

			foreach ($customChargeParent as $key => $customCharge) {
				$historyPaymentMethod = '';

				if ($request->refund_type == "full_refund") {
					if (($isCashFees != 1 && $request->payment_method == "Cash") || $request->payment_method == "Invoiced") {
						$customChargeAmount = $customCharge->amount;
					} else {
						if (($isCashFees == 1 && $request->payment_method == "Cash") || $request->payment_method == "Card") {
							$customChargeAmount = $customCharge->amount;
						}
					}
					$refundType = "Full Refund minus Fees";
				}

				if ($request->refund_type == "100_refund") {
					if (($isCashFees != 1 && $request->payment_method == "Cash") || $request->payment_method == "Invoiced") {
						$customChargeAmount = $customCharge->amount;
					} else {
						if (($isCashFees == 1 && $request->payment_method == "Cash") || $request->payment_method == "Card") {
							$customChargeAmount = $customCharge->amount;
							$customChargeFees = $customCharge->fees;
							$customChargeTaxable = $customCharge->taxable;
						}
					}
					$refundType = "100 % Refund";
				}

				if ($request->refund_type == "dont_refund") {
					$refundType = "Don't Refund";
					if (($isCashFees != 1 && $request->payment_method == "Cash") || $request->payment_method == "Invoiced") {
						$customChargeAmount = $customCharge->amount;
					} else {
						if (($isCashFees == 1 && $request->payment_method == "Cash") || $request->payment_method == "Card") {
							$customChargeAmount = $customCharge->amount;
							$customChargeFees = $customCharge->fees;
							$customChargeTaxable = $customCharge->taxable;
						}
					}
				}

				if ($request->payment_method == "Card") {
					if ($request->refund_type != "dont_refund") {
						$refundTrans = $this->transaction::where('tour_booking_id', $id)->where('transaction_number', $customCharge->transaction_id)->where('charge_type', 'custom_charge')->where('status', 'Refund')->get(['id', 'amount']);
						$paymentTrans = $this->transaction::where('tour_booking_id', $id)->where('transaction_number', $customCharge->transaction_id)->where('charge_type', 'custom_charge')->where('status', 'Completed')->first(['id', 'amount', 'transaction_number', 'application_fee_id', 'payment_mode']);

						foreach ($refundTrans as $ky => $val) {
							$totalrefundedamount += $val->amount;
						}

						$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($customCharge->transaction_id);
						if (!empty($paymentIntent) && isset($paymentIntent->charges) && isset($paymentIntent->charges->data[0]) && !empty($paymentIntent->charges->data[0])) {
							$amountCapturedStripe = $paymentIntent->charges->data[0]->amount_captured;
							$amountRefundedStripe = ($paymentIntent->charges->data[0]->amount_refunded != '') ? $paymentIntent->charges->data[0]->amount_refunded : 0;
						}

						//Validation check if payment transactions fully refunded or not
						if ($customCharge->amount - $totalrefundedamount <= 0) {
							if ($request->refund_type == "full_refund") {
								$ccAmount = $customChargeAmount;
							}
							if ($request->refund_type == "100_refund") {
								$ccAmount = $customChargeAmount + $customChargeFees;
							}
							$message = 'Unable to refund booking. The transaction for custom charge of $' . $ccAmount . ' already fully refunded.';
							return response()->json($message, 400);
						}

						if ($request->refund_type == "full_refund") {
							$refundParams = [
								'payment_intent' => $customCharge->transaction_id,
								'amount' => $customChargeAmount * 100,
								'reverse_transfer' => true,
							];
							app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
							DB::commit();
						}

						if ($request->refund_type == "100_refund") {
							$setFeeAmount = (abs($customChargeAmount) * $serviceCommissionPercentage) / 100;
							$refund100 = $customChargeAmount + $setFeeAmount;
							$refund100 = roundout($refund100, 2);
							$rounded = round($paymentTrans->amount - $totalrefundedamount - $refund100, 2);

							if ($rounded <= -0.01) {
								$refundParams = [
									'payment_intent' => $customCharge->transaction_id,
									'amount' => ($refund100 + $rounded) * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
								DB::commit();
							} else {
								$refundParams = [
									'payment_intent' => $customCharge->transaction_id,
									'amount' => $refund100 * 100,
									'reverse_transfer' => true,
									'refund_application_fee' => true,
								];
								app(PaymentGatewayInterface::class)->refundPayment($refundParams, $paymentTrans->payment_mode);
								DB::commit();
							}
						}
						$paymentIntentId = $customCharge->transaction_id;
						$applicationFeeId = $paymentTrans->application_fee_id;
						$transactionDetails = Transaction::where('tour_booking_id', $id)->where('transaction_number', $paymentIntentId)->where('status', 'Completed')->where('charge_type', 'custom_charge')->first(['id']);
						$histDetails = TourBookingHistory::where('transaction_id', $transactionDetails->id)->where('tour_booking_id', $id)->where('operation', 'Payment')->first(['card_last_4_digit']);
						$cardLast4 = $histDetails->card_last_4_digit ?? 0;
					}
				}

				$history = [
					'amount' => $customChargeAmount,
					'taxable' => $customChargeTaxable,
					'fees' => $customChargeFees,
					'status' => $refundType
				];

				// Store refunds in transactions only for Cash,Card and Invoiced bookings
				if ($request->payment_method != "Pay Later") {
					$historyPaymentMethod = $request->payment_method;

					if ($request->refund_type == "dont_refund") {
						$customChargeAmt = $customChargeFee = 0;
					} else {
						$customChargeAmt = $customChargeAmount;
						$customChargeFee = $customChargeFees;
					}

					// Cash/Invoiced custom charges never carry a Stripe settlement, so payment_mode
					// isn't meaningful for them — DESTINATION (the column's own default) is the
					// correct, inert value there; Card uses the transaction's real persisted mode.
					$customChargeMode = ($request->payment_method == "Card") ? $paymentTrans->payment_mode : PaymentModeResolver::DESTINATION;
					$customChargeTransactionId = TransactionService::storeCustomChargeRefundInTransaction($id, $customChargeAmt, $customChargeFee, $request->payment_method, $paymentIntentId, $applicationFeeId, mode: $customChargeMode);
					DB::commit();
				}

				CustomChargeBookingService::refundTourBookingCustomCharge($id, $customCharge->custom_charge_id, $history, $customCharge->transaction_id, $request->payment_method, $refundType, 'Refund');
				DB::commit();

				HistoryService::storeCustomChargeRefundInHistory($id, $historyPaymentMethod, $customChargeAmount, $customChargeFees, $history, $refundType, $cardLast4, $customChargeTransactionId, 'removed');
			}

			$message = 'Booking has been updated' . ($request->refund_type != "dont_refund" ? ' and refund has been initiated.' : '.');
			return response()->json([
				'message' => $message
			], 200);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			if (isset($request->api) && $request->api == 1) {
				throw new GeneralCoreException($exception);
			} else {
				$message = 'Unable to refund a custom charge to the booking.' . ' ' . $exception->getMessage();
				return response()->json($message, 400);
			}
		}
	}

	/**
	 * Update status of redeem tickets for package of booking created
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function updatePackageRedeemTicketStatus(object $request)
	{
		DB::beginTransaction();
		try {
			$updated = 0;
			$totalTourists = 0;
			$updatedRedeemTicketCount = 0;
			$ticketCount = 1;
			$notRedeemedTickets = 0;
			$historyArr = [];
			$redeemTicketStatusInfo = [];
			$status = null;
			$mysqlDT = mysqlDT();
			$created_by_with_name = BookingService::createdUserInfo();

			$transactQuery = DB::table('tour_bookings_transact AS tbt')
				->join('tour_bookings_master AS tbm', 'tbm.id', '=', 'tbt.tour_booking_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->where('tbt.tour_booking_id', $request->booking_id)
				->where('tbt.deleted_by', 0);

			$bookingTransact = $transactQuery->get([
				'tbm.status',
				'tbt.id',
				'tbt.tourists',
				'tbt.redeem_ticket',
				'tbt.tour_package_rate_id',
				'tp.name as package_name'
			]);

			if (count($bookingTransact) && $bookingTransact[0]->status == 'Cancelled') {
				throw new Exception('The booking has been cancelled, ticket can not be redeemed');
			}

			$parentPackage = $this->tourPackage::findOrFail($request->parent_package_id);
			$package = $this->tourPackage::findOrFail($request->package_id);

			// Get total tourists of the booking
			$totalTourists = $this->tourBooking::findOrFail($request->booking_id);
			$totalTourists = $totalTourists['tourists'];

			if (!$parentPackage || !$package) {
				throw new Exception('Tour package details not found.');
			}

			// Get packages when package is bundle
			$packageList = BundlePackageBookingService::getBundlePackageBookings($parentPackage->bundle_package, $request->booking_id, $parentPackage->id);

			for ($a = 0; $a < count($bookingTransact); $a++) {
				if ($bookingTransact[$a]->redeem_ticket == null) {
					$redeemStatusInfo = [];
				} else {
					$redeemStatusInfo = json_decode($bookingTransact[$a]->redeem_ticket, TRUE);
				}

				// If redeem_ticket field is empty
				if (empty($redeemStatusInfo)) {
					for ($i = 0; $i < count($packageList); $i++) {
						$redeemStatusInfo[$i] = [
							'id' => $packageList[$i]->tour_package_id,
							'name' => $packageList[$i]->package_name,
							'checkin_type' => $packageList[$i]->checkin_type,
							'data' => []
						];
						for ($b = 0; $b < $bookingTransact[$a]->tourists; $b++) {
							$redeemStatusInfo[$i]['data'][$b] = [
								// 'status' => 'Active',
								'status' => ($packageList[$i]->checkin_type == 0) ? 'N' : 'Active',
								'dateTime' => $mysqlDT
							];
						}
					}
				}

				// If redeem_ticket field is not empty
				for ($x = 0; $x < $bookingTransact[$a]->tourists; $x++) {
					for ($y = 0; $y < count($redeemStatusInfo); $y++) {
						$checkInType = TourPackage::select('checkin_type')->where('id', $redeemStatusInfo[$y]['id'])->firstOrFail();

						if (!isset($redeemStatusInfo[$y]['data'][$x])) {
							$redeemStatusInfo[$y]['data'][$x] = [
								'status' => ($request->checkin_type == 0) ? $request->status : 'Active',
								'dateTime' => $mysqlDT
							];
						}
					}
				}

				for ($x = 0; $x < $bookingTransact[$a]->tourists; $x++) {
					for ($y = 0; $y < count($redeemStatusInfo); $y++) {
						if ($request->package_id == $redeemStatusInfo[$y]['id']) {
							if ($redeemStatusInfo[$y]['data'][$x]['status'] == 'Active') {
								$notRedeemedTickets++;
							}
						}
					}
				}

				$redeemTicketStatusInfo[$a] = [
					'id' => $bookingTransact[$a]->id,
					'tour_package_rate_id' => $bookingTransact[$a]->tour_package_rate_id,
					'tourists' => $bookingTransact[$a]->tourists,
					'ticketData' => $redeemStatusInfo
				];
			}

			for ($a = 0; $a < count($redeemTicketStatusInfo); $a++) {
				if ($request->has('all_redeem_tickets') && $request->all_redeem_tickets == 'all') {

					// For All => When number of tickets are same[not redeemed] as total tourists
					if ($totalTourists == $notRedeemedTickets) {
						for ($x = 0; $x < $redeemTicketStatusInfo[$a]['tourists']; $x++) {
							for ($y = 0; $y < count($redeemTicketStatusInfo[$a]['ticketData']); $y++) {
								if ($request->package_id == $redeemTicketStatusInfo[$a]['ticketData'][$y]['id']) {
									$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x] = [
										'status' => 'Inactive',
										'dateTime' => $mysqlDT
									];
									$status = $redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'];
								}

								if ($redeemTicketStatusInfo[$a]['tourists'] < count($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'])) {
									$arrCount = count($redeemTicketStatusInfo[$a]['ticketData'][$y]['data']) - $redeemTicketStatusInfo[$a]['tourists'];
									$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'] = array_slice($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'], 0, -$arrCount);
								}
							}
						}
					} else {
						$rateGrp = DB::table('tour_package_rates AS tpr')
							->join('tour_bookings_transact AS tst', 'tst.tour_package_rate_id', '=', 'tpr.id')
							->where('tst.tour_booking_id', $request->booking_id)
							->where('tpr.id', $redeemTicketStatusInfo[$a]['tour_package_rate_id'])
							->where('tst.deleted_by', 0)
							->first(['tpr.rate_for', 'tst.tourists']);

						if (!$rateGrp) {
							throw new Exception('Unable to redeem tickets.');
						}

						for ($x = 0; $x < $redeemTicketStatusInfo[$a]['tourists']; $x++) {
							for ($y = 0; $y < count($redeemTicketStatusInfo[$a]['ticketData']); $y++) {
								if ($request->package_id == $redeemTicketStatusInfo[$a]['ticketData'][$y]['id']) {
									if ($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'] == 'Inactive') {
										$ticketCount--;
									}

									if ($ticketCount <= $notRedeemedTickets) {
										if ($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'] == 'Active') {
											$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x] = [
												'status' => 'Inactive',
												'dateTime' => $mysqlDT
											];
											$status = $redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'];

											// History note updates
											if (!isset($historyArr[$a][$x])) {
												$historyArr[$a][$x] = [
													'status' => $redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'],
													'who' => "{$rateGrp->rate_for} #" . ($x + 1),
													'checkin_type' => $package->checkin_type,
													'package' => $package->name,
													'created_by_with_name' => $created_by_with_name
												];
											}

											$updatedRedeemTicketCount++;
										}
									}
								}
								if ($redeemTicketStatusInfo[$a]['tourists'] < count($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'])) {
									$arrCount = count($redeemTicketStatusInfo[$a]['ticketData'][$y]['data']) - $redeemTicketStatusInfo[$a]['tourists'];
									$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'] = array_slice($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'], 0, -$arrCount);
								}
							}
							$ticketCount++;
						}
					}
				} else {
					for ($x = 0; $x < $redeemTicketStatusInfo[$a]['tourists']; $x++) {
						for ($y = 0; $y < count($redeemTicketStatusInfo[$a]['ticketData']); $y++) {
							if ($request->has('index')) {
								if ($request->tpr_id == $redeemTicketStatusInfo[$a]['tour_package_rate_id']) {
									if (($request->index == $x) && ($request->package_id == $redeemTicketStatusInfo[$a]['ticketData'][$y]['id'])) {
										if (isset($redeemTicketStatusInfo[$a]['ticketData'][$y]['checkin_type'])) {
											if ($redeemTicketStatusInfo[$a]['ticketData'][$y]['checkin_type'] == 0) {
												// Check-in
												$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'] = $request->status;
											} else {
												// Redeem Tickets
												$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'] = ($request->status == "Active") ? 'Inactive' : 'Active';
											}
										} else {
											$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'] = ($request->status == "Active") ? 'Inactive' : 'Active';
										}
										$status = $redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'];
										$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['dateTime'] = $mysqlDT;
										break;
									}
								}
							} else {
								if ($request->package_id == $redeemTicketStatusInfo[$a]['ticketData'][$y]['id']) {
									$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'] = $request->status;
									$status = $redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['status'];
									$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'][$x]['dateTime'] = $mysqlDT;
								}
							}

							if ($redeemTicketStatusInfo[$a]['tourists'] < count($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'])) {
								$arrCount = count($redeemTicketStatusInfo[$a]['ticketData'][$y]['data']) - $redeemTicketStatusInfo[$a]['tourists'];
								$redeemTicketStatusInfo[$a]['ticketData'][$y]['data'] = array_slice($redeemTicketStatusInfo[$a]['ticketData'][$y]['data'], 0, -$arrCount);
							}
						}
					}
				}

				$updated = $updated + DB::table('tour_bookings_transact')
					->where('id', $redeemTicketStatusInfo[$a]['id'])
					->update([
						'redeem_ticket' => json_encode($redeemTicketStatusInfo[$a]['ticketData'])
					]);
			}

			$checkInStatuses = ['N' => 'Not Checked In', '1' => '1st Check In', '2' => '2nd Check In', 'A' => 'Absent'];

			if ($updated) {
				if (!isset($request->not_redeem_tickets)) {
					if ($request->has('tpr_id')) {

						$rateGrp = DB::table('tour_package_rates AS tpr')
							->join('tour_bookings_transact AS tst', 'tst.tour_package_rate_id', '=', 'tpr.id')
							->where('tst.tour_booking_id', $request->booking_id)
							->where('tst.deleted_by', 0)
							->where('tpr.id', $request->tpr_id)
							->first(['tpr.rate_for', 'tst.tourists']);

						if (!$rateGrp) {
							throw new Exception('Unable to redeem ticket.');
						}

						$whoRedeemedTickets = "{$rateGrp->rate_for} #" . ($request->index + 1);
					} else
						$whoRedeemedTickets = 'All';

					$history = [
						'package' => $package->name,
						'status' => ($request->checkin_type == 0) ? $checkInStatuses[$status] : $status,
						'checkin_type' => $request->checkin_type,
						'who' => $whoRedeemedTickets,
						'created_by_with_name' => $created_by_with_name
					];

					HistoryService::storeCheckInDataInHistory($request->booking_id, $history, $mysqlDT);
				} else {

					if ($totalTourists == $notRedeemedTickets) {
						$whoRedeemedTickets = 'All';

						$history = [
							'package' => $package->name,
							'status' => $status,
							'checkin_type' => $package->checkin_type,
							'who' => $whoRedeemedTickets,
							'created_by_with_name' => $created_by_with_name
						];

						HistoryService::storeCheckInDataInHistory($request->booking_id, $history, $mysqlDT);
					} else {
						$whoRedeemedTickets = $updatedRedeemTicketCount;

						if (!empty($historyArr)) {
							foreach ($historyArr as $key => $history) {
								foreach ($history as $k => $v) {
									HistoryService::storeCheckInDataInHistory($request->booking_id, $v, $mysqlDT);
								}
							}
						}
					}
				}
			} else {
				$message = 'Ticket could not be redeemed. It may already be redeemed for ' . $package->name . '.';
				throw new Exception($message);
			}

			$masterCheck = DB::table('tour_bookings_master AS tbm')
				->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
				->where('tbm.id', $request->booking_id)
				->first(['tbm.status', 'c.is_affiliate']);

			DB::commit();

			return response()->json([
				'updated' => $updated,
				'whoCheckedIn' => $whoRedeemedTickets,
				'status' => $status,
				'tourists' => $totalTourists,
				'package_name' => $package->name,
				'checkInDateTime' => $mysqlDT,
				'checkin_type' => $request->checkin_type,
				'is_affiliate' => $masterCheck->is_affiliate,
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollBack();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get custom charge visibility
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function getCustomChargeVisibility()
	{
		try {
			return [
				'is_custom_charge' => hasSetting('CUSTOM_CHARGE') ? 1 : 0
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Get custom charge form data
	 * @param int|string $id
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function getCustomChargeData($id)
	{
		$transactions = Transaction::with('bookingHistory')
			->where('tour_booking_id', $id)
			->where('status', 'Completed')
			->where('charge_type', 'custom_charge')
			->orderBy('id', 'DESC')
			->get();

		$customChargeParentList = CustomChargeBookingService::tourBookingCustomCharges($id, $transactions);
		if (empty($customChargeParentList)) {
			return [];
		}

		$customChargeRefunds = CustomChargeBookingService::customChargeRefunds($id);
		$refundIds = collect($customChargeRefunds)->pluck('parent_id')->toArray();

		$remainingIds = array_filter(
			array_column($customChargeParentList, 'custom_charge_id'),
			fn($chargeId) => !in_array($chargeId, $refundIds)
		);

		if (empty($remainingIds)) {
			return [];
		}

		$customChargeList = DB::table('tour_booking_custom_charges as tbcc')
			->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbcc.tour_booking_id')
			->where('tbm.id', $id)
			->whereIn('tbcc.id', $remainingIds)
			->select('tbm.id as tour_booking_id', 'tbcc.id as custom_charge_id', 'tbcc.parent_id', 'tbcc.amount', 'tbcc.fees', 'tbcc.taxable', 'tbcc.transaction_id', 'tbcc.description', 'tbcc.payment_method', 'tbcc.status')
			->orderByDesc('tbcc.id')
			->get();

		return $customChargeList;
	}

	/**
	 * Get promo code details
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function getPromoDetail(object $request)
	{
		try {
			$now = Carbon::now()->format('Y-m-d');

			$detail = TourPromotion::where('tour_package_id', $request->tourPackageId)
				->where('code', $request->promoCode)
				->whereRaw('"' . $now . '" between `start_date` and `end_date`')
				->where('status', 'Active')
				->first(['id', 'max_allowed_usages', 'discount_value', 'discount_value_type']);

			if (!$detail) {
				throw new Exception('Please enter valid promo code.');
			}

			return $detail;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Request to the manager
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function requestToManager(object $request)
	{
		try {

			$actionStore = session()->get('actionStore');
			$qryBookings = 'FIND_IN_SET("' . $request->bookingId . '" ,bookings)'; //This is to check request from POS/Backend exist
			$requestCheck = BookingRequest::where('order_id', $request->orderId)
				->where('manager_id', $request->manager_id)
				->whereDate('created_at', Carbon::today())
				->where('request_type', $actionStore['request_type'])
				->where('staff_id', Auth::user()->id)
				->where('staff_type', userType())
				->whereIn('status', ['Approved', 'Pending'])
				->whereJsonContains('details->field_type',  $actionStore['field_type'])
				->whereRaw($qryBookings)
				->first();

			$oldRequestCheck = BookingRequest::where('order_id', $request->orderId)
				->where('manager_id', $request->manager_id)
				->whereDate('created_at', Carbon::today())
				->where('request_type', $actionStore['request_type'])
				->where('staff_id', Auth::user()->id)
				->where('staff_type', userType())
				->where('status', 'Pending')
				->first();

			if (!empty($requestCheck) && $requestCheck->status != 'Approved') {
				return [
					'message' => "Request already has been sent to this manager",
					'status' => 'error',
					'request_status' => $requestCheck->status
				];
			}

			if (!empty($requestCheck) && $requestCheck->status == 'Approved') {
				return [
					'message' => "Request has been approved. Please reload the page",
					'status' => 'error',
					'request_status' => $requestCheck->status
				];
			}

			$bookingRequest = new BookingRequest;
			$bookingRequest->tour_booking_id = $request->bookingId;
			$bookingRequest->order_id = $request->orderId;
			$bookingRequest->bookings = $request->bookingId;
			$bookingRequest->staff_id = Auth::user()->id;
			$bookingRequest->manager_id = $request->manager_id;
			$bookingRequest->status = 'Pending';
			$bookingRequest->request_type = $actionStore['request_type'];
			$bookingRequest->request_from = 'BACKEND';
			$bookingRequest->staff_type = userType();
			$bookingRequest->details = json_encode($actionStore);
			$bookingRequest->cancelled_by = '';
			$bookingRequest->save();
			if (!empty($oldRequestCheck)) {
				$oldRequestCheck->status = "Expired";
				$oldRequestCheck->save();
			}

			$tenant = tourOperator();
			broadcast(new BookingRequestNotification($bookingRequest, $tenant));

			return [
				'message' => 'Manager request has been sent',
				'status' => 'success'
			];
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * The manual id check
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function manualIdCheck($request)
	{
		try {
			$staff = staff::where('manager_id', $request->manager_id)->where('id', $request->user_id)->where('status', '!=', 'Deleted')->where('manager_id', '!=', 0)->first();
			$correct = 'Incorrect';
			$status = 'error';
			if (!empty($staff)) {
				$correct = 'Correct';
				$status = 'success';
				session()->put('manager_id', $request->manager_id);
				session()->put('user_id', $request->user_id);
			}

			$data = ['message' => $correct, 'status' => $status];
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * The manual pin check
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function manualPinCheck($request)
	{
		try {
			$manager_id = session()->get('manager_id');
			$user_id = session()->get('user_id');
			$actionStore = session()->get('actionStore');
			$staff = staff::where('manager_pin', $request->manager_pin)
				->where('manager_id', $manager_id)
				->where('id', $user_id)
				->where('status', '!=', 'Deleted')
				->where('manager_pin', '!=', 0)
				->first();

			if (!empty($staff)) {
				$bookingRequest = BookingRequest::where('tour_booking_id', $request->bookingId)
					->where('manager_id', $user_id)
					->whereDate('created_at', Carbon::today())
					->where('request_type', $actionStore['request_type'])
					->where('staff_id', Auth::user()->id)
					->where('staff_type', userType())
					->where('status', 'Pending')
					->whereJsonContains('details->field_type',  $actionStore['field_type'])
					->first();

				if (!empty($bookingRequest)) {
					$bookingRequest->status = "Approved";
					$bookingRequest->save();
				} else {
					$bookingRequest = new BookingRequest;
					$bookingRequest->tour_booking_id = $request->bookingId;
					$bookingRequest->order_id = $request->orderId;
					$bookingRequest->bookings = $request->bookingId;
					$bookingRequest->staff_id = Auth::user()->id;
					$bookingRequest->manager_id = $user_id;
					$bookingRequest->status = 'Approved';
					$bookingRequest->request_type = $actionStore['request_type'];
					$bookingRequest->request_from = 'BACKEND';
					$bookingRequest->staff_type = userType();
					$bookingRequest->details = json_encode($actionStore);
					$bookingRequest->cancelled_by = '';
					$bookingRequest->save();
				}

				$data = [
					'message' => 'Correct',
					'status' => 'success'
				];
			} else {
				$data = [
					'message' => 'Manager pin is incorrect',
					'status' => 'error'
				];
			}

			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * The manual pin check for new booking
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function manualPinCheckBooking($request)
	{
		try {
			$manager_id = session()->get('manager_id');
			$user_id = session()->get('user_id');
			$staff = staff::where('manager_pin', $request->manager_pin)
				->where('manager_id', $manager_id)
				->where('id', $user_id)
				->where('status', '!=', 'Deleted')
				->first();

			if (!empty($staff)) {
				$data = [
					'message' => 'Correct',
					'status' => 'success'
				];
			} else {
				$data = [
					'message' => 'Manager pin is incorrect',
					'status' => 'error'
				];
			}

			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Execute to get contact details for given contact ID
	 *
	 * @param integer $contactId
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function getContactDetails($contactId)
	{
		try {
			$contact = PackageHasContactsService::getContact($contactId);

			return $contact;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Execute to edit contact details for given contact ID
	 *
	 * @param Request $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function editContact($request)
	{
		try {
			if ($request->transact_id != null) {
				$contactId = DB::table('tour_booking_contacts')
					->insertGetId([
						'tour_booking_id' => $request->booking_id,
						'tour_booking_transact_id' => $request->transact_id,
						'name' => $request->name,
						'age' => $request->age,
						'weight' => $request->weight,
						'note' => ($request->note != "") ? $request->note : null
					]);
			} else {
				$contact = PackageHasContactsService::getContact($request->contact_id);

				$contactUpdate = DB::table('tour_booking_contacts')
					->where('id', $request->contact_id)
					->update([
						'name' => $request->name,
						'age' => $request->age,
						'weight' => $request->weight,
						'note' => ($request->note != "") ? $request->note : null
					]);

				if (!$contactUpdate) {
					return false;
				}

				$contactId = $request->contact_id;
			}

			$contact = PackageHasContactsService::getContact($contactId);

			return $contact;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Download agenda page date wise in CSV
	 *
	 * @param int $date
	 * @param int $tourOperatorId
	 * @param string $tourPackagesId
	 * @param int $PaymentStatus
	 * @param string $slotId
	 * @param int $api
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function rosterReportCSV(Request $request, int $date = 0, int $tourOperatorId = 0, string $tourPackagesId = '', int $PaymentStatus = 0, $slotId = '', int $api = 0)
	{
		try {
			$agendaInfo = $this->dayTimeSlots($request, $date, $tourOperatorId, $tourPackagesId, $PaymentStatus, $slotId, $api);

			if ($tourPackagesId && $agendaInfo['packageHasContacts']) {
				return $this->agendaRepository->rosterReportCSV($agendaInfo);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Download agenda page date wise in PDF
	 *
	 * @param int $date
	 * @param int $tourOperatorId
	 * @param string $tourPackagesId
	 * @param int $PaymentStatus
	 * @param string $slotId
	 * @param int $api
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function rosterReportPDF(Request $request, int $date = 0, int $tourOperatorId = 0, string $tourPackagesId = '', int $PaymentStatus = 0, $slotId = '', int $api = 0)
	{
		try {
			$agendaInfo = $this->dayTimeSlots($request, $date, $tourOperatorId, $tourPackagesId, $PaymentStatus, $slotId, $api);

			if ($tourPackagesId && $agendaInfo['packageHasContacts']) {
				return $this->agendaRepository->rosterReportPDF($agendaInfo);
			}
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Post method to send plain text sms message
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function smsSend($request)
	{
		try {
			$message = "Message From: " . strtoupper(tenancy()->tenant->id) . "\n";
			$message .= $request->message;
			$message .= "\n";

			$phone = StrUtils::parseIntPhoneNumber($request->phone_number);
			$response = $this->smsNotifications->sendSMSAsync($message, $phone);

			return response()->json([
				"results" => $response,
				"params" => [
					"message" => $message,
					"phone" => $phone
				],
				"error" => null
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Post method to send sms qr code link
	 * @param int|string $bookingId
	 * @param array $props
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function smsSendLink($bookingId, $props)
	{
		try {
			$settings = Setting::where('key', 'BOOKING_ID_PREFIX')->first();
			$id = $settings->value . "" . $bookingId;

			$res = $this->smsNotifications->sendSMSLink($id, $props);

			return response()->json([
				"results" => $res,
				"params" => [
					"booking_id" => $bookingId
				],
				"error" => null
			]);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}


	/**
	 * The action details store on session
	 *
	 * @param object $request
	 * @return mixed
	 * 
	 * @version 1.0.0
	 */
	public function actionStore($request)
	{
		try {
			session()->put('actionStore', $request->except(['_token']));
			$data = [
				'message' => 'Store',
				'status' => 'success'
			];
			return $data;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * Execute to get contact details for given contact ID
	 *
	 * @param integer|string $bookingId
	 * 
	 * @return array
	 * 
	 * @version 1.0.0
	 */
	public function rateGroupWiseBookingContacts($bookingId)
	{
		try {
			$totalContacts = [];
			$tourBookingTransact = $this->bookingTransact::select('id', 'tour_package_rate_id')->where('tour_booking_id', $bookingId)->get();

			foreach ($tourBookingTransact as $key => $bookingTransact) {
				$contacts = $this->tourBookingContact::where('tour_booking_id', $bookingId)->where('tour_booking_transact_id', $bookingTransact->id)->count();
				$totalContacts[] = [
					'rate_id' => $bookingTransact->tour_package_rate_id,
					'contacts' => $contacts
				];
			}

			return $totalContacts;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Update Time Slot Bookable Status [Open|Only by Phone|Closed]
	 *
	 * @param object $request
	 * @return array
	 * @throws GeneralCoreException
	 * 
	 * @version 1.0.0
	 */
	public function updateSlotBookableStatus($request)
	{
		DB::beginTransaction();
		try {
			// Fetch time slot with required details
			$timeSlot = $this->tourSlotTime::findOrFail($request->slot_id);

			$updateDT = mysqlDT();
			$oldValues = [];
			$newValues = ['bookable_status' => $request->status];

			// Assign class based on status
			$statusClasses = [
				'Open' => 'bookable',
				'Only by Phone' => 'callback',
				'Closed' => ''
			];
			$class = $statusClasses[$request->status] ?? '';

			// Track changes for history
			diffRecord(['bookable_status'], 'u', (array) $timeSlot, $newValues, $oldValues, $newValues);

			// Insert change record into history table
			DB::table('records_history')->insert([
				'record_id_1' => $timeSlot->id,
				'record_id_2' => $timeSlot->tour_package_id,
				'operation_type' => 'u',
				'table_name' => 'tour_slot_times',
				'entity_info' => "{$timeSlot->date} {$timeSlot->time_from}",
				'old_values' => json_encode($oldValues),
				'new_values' => json_encode($newValues),
				'operation_at' => $updateDT,
				'operation_by' => Auth::id(),
				'operation_by_user_type' => session('userType'),
			]);

			// Update the record
			DB::table('tour_slot_times')
				->where('id', $request->slot_id)
				->update(array_merge($newValues, [
					'updated_at' => $updateDT,
					'updated_by' => Auth::id(),
					'updated_by_user_type' => session('userType')
				]));

			DB::commit();

			return [
				'status' => $request->status,
				'class' => $class
			];
		} catch (Exception $exception) {
			DB::rollback();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public function updateSlotHideStatus($request)
	{
		DB::beginTransaction();
		try {
			$timeSlot = $this->tourSlotTime::findOrFail($request->slot_id);

			$updateDT = mysqlDT();
			$newHideSlot = $timeSlot->hide_slot ? 0 : 1;
			$oldValues = [];
			$newValues = ['hide_slot' => $newHideSlot];

			diffRecord(['hide_slot'], 'u', (array) $timeSlot, $newValues, $oldValues, $newValues);

			DB::table('records_history')->insert([
				'record_id_1'          => $timeSlot->id,
				'record_id_2'          => $timeSlot->tour_package_id,
				'operation_type'       => 'u',
				'table_name'           => 'tour_slot_times',
				'entity_info'          => "{$timeSlot->date} {$timeSlot->time_from}",
				'old_values'           => json_encode($oldValues),
				'new_values'           => json_encode($newValues),
				'operation_at'         => $updateDT,
				'operation_by'         => Auth::id(),
				'operation_by_user_type' => session('userType'),
			]);

			DB::table('tour_slot_times')
				->where('id', $request->slot_id)
				->update(array_merge($newValues, [
					'updated_at'           => $updateDT,
					'updated_by'           => Auth::id(),
					'updated_by_user_type' => session('userType'),
				]));

			DB::commit();

			return ['hide_slot' => $newHideSlot];
		} catch (Exception $exception) {
			DB::rollback();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	public function bulkUpdateSlotHideStatus($request)
	{
		DB::beginTransaction();
		try {
			$updateDT = mysqlDT();
			$hideSlot = (int) $request->hide_slot;

			foreach ($request->slotIds as $slotId) {
				$timeSlot = $this->tourSlotTime::findOrFail($slotId);
				$oldValues = [];
				$newValues = ['hide_slot' => $hideSlot];
				diffRecord(['hide_slot'], 'u', (array) $timeSlot, $newValues, $oldValues, $newValues);
				DB::table('records_history')->insert([
					'record_id_1'          => $timeSlot->id,
					'record_id_2'          => $timeSlot->tour_package_id,
					'operation_type'       => 'u',
					'table_name'           => 'tour_slot_times',
					'entity_info'          => "{$timeSlot->date} {$timeSlot->time_from}",
					'old_values'           => json_encode($oldValues),
					'new_values'           => json_encode($newValues),
					'operation_at'         => $updateDT,
					'operation_by'         => Auth::id(),
					'operation_by_user_type' => session('userType'),
				]);
			}

			DB::table('tour_slot_times')
				->whereIn('id', $request->slotIds)
				->update([
					'hide_slot'            => $hideSlot,
					'updated_at'           => $updateDT,
					'updated_by'           => Auth::id(),
					'updated_by_user_type' => session('userType'),
				]);

			DB::commit();
			return ['hide_slot' => $hideSlot];
		} catch (Exception $exception) {
			DB::rollback();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Add Time Slot From Calendar Page
	 *
	 * @param object $request
	 * @return array|mixed
	 * @throws GeneralCoreException
	 *
	 * @version 1.0.0
	 */
	public function addTimeSlot(Request $request)
	{
		DB::beginTransaction();
		try {
			$userType = userType();
			$formattedDate = Carbon::parse($request->date)->toDateString();

			// Check if the time slot already exists
			$timeSlotExists = $this->tourSlotTime::where([
				'tour_package_id' => $request->tour_package_id,
				'date' => $formattedDate,
				'time_from' => $request->from_time,
				'time_to' => $request->to_time
			])->exists();

			if ($timeSlotExists) {
				throw new Exception('Time slot already exists!!');
			}

			// Fetch the tour slot date
			$dateSlot = $this->tourDateSlot::where([
				'tour_package_id' => $request->tour_package_id,
				'date' => $formattedDate
			])->select('id')->firstOrFail();

			// Save time slot using Eloquent
			$timeSlot = $this->tourSlotTime::create([
				'tour_slot_id' => $dateSlot->id,
				'tour_package_id' => $request->tour_package_id,
				'time_slot_seats' => $request->seats,
				'date' => $formattedDate,
				'time_from' => $request->from_time,
				'time_to' => $request->to_time,
				'bookable_status' => $request->status,
				'hide_slot' => $request->hide_time_slot,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType
			]);

			DB::commit();

			return response()->json([
				'time_slot_id' => $timeSlot->id,
				'seats' => $request->seats,
				'from_time' => $request->from_time,
				'to_time' => $request->to_time,
				'status' => $request->status
			]);
		} catch (Exception $e) {
			DB::rollback();
			Log::error($e);
			throw new GeneralCoreException($e);
		}
	}

	/**
	 * Return all waiver entries from liability_waiver_signing, newest first.
	 * Supports both formats:
	 *   - Direct waiver (3 elements): [name, age, datetime]
	 *   - School waiver (5 elements): [name, age, datetime, parent_email, reference_id]
	 */
	private function getSchoolWaiverEntries(int $id): array
	{
		$raw = DB::table('tour_bookings_master')->where('id', $id)->value('liability_waiver_signing');
		$all = (!empty($raw)) ? json_decode($raw, true) : [];
		return array_reverse(array_values($all));
	}
}
