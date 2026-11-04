<?php

namespace App\Repositories\TourBooking;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\Transaction;
use App\Models\TourPackageRate;
use App\Models\BookingTransact;
use App\Models\Hotel;
use App\Models\InvoiceBooking;
use App\Models\TourPromotion;
use App\Models\DiscountHistory;
use App\Models\TourBookingContact;

use App\Repositories\Repository;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Repositories\Notify\SMSNotifications;
use App\Repositories\Agenda\AgendaRepository;

use App\Services\Cart\CartService;
use App\Services\TourPackage\TourPackageService;
use App\Services\Booking\BookingService;
use App\Services\Booking\StripeService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Stripe\Stripe;
use Stripe\Exception\CardException;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\SurchargeDate;
use App\Services\Pos\PosService;
use App\Utils\BookingUtils;
use App\Utils\StripeUtils;
use App\Utils\IpGeolocationUtils;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class StripeRepository extends Repository
{
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

	/**
	 * @param  TourSlotRepository  $tourSlotRepository
	 * @param  TourBookingFormsRepository  $tourBookingFormsRepository
	 * @param  AgendaRepository  $agendaRepository
	 */
	public function __construct(TourSlotRepository $tourSlotRepository, TourBookingFormsRepository $tourBookingFormsRepository, AgendaRepository $agendaRepository, SMSNotifications $smsNotifications)
	{
		$this->tourSlotRepository = $tourSlotRepository;
		$this->tourBookingFormsRepository = $tourBookingFormsRepository;
		$this->agendaRepository = $agendaRepository;
		$this->smsNotifications = $smsNotifications;

		Stripe::setApiKey(getStripeSecretKey());
	}

	/**
	 * Create stripe payment intent for booking payment
	 *
	 * @param object $request
	 * @return array
	 */
	public function stripeCreatePaymentIntent($request)
	{
		try {
			if (cartView() == 1) {
				$cart = session()->get('cart');
				if (empty($cart) || !isset($cart['tour_packages'])  || empty($cart['tour_packages'])) {
					if ($request->has('slot_time') && $request->slot_time != '') {
						$info = TourPackageService::getTourPackageSlotInfo($request->slot_time);
						// If NOT combo → just continue normal flow
						if ($info->is_combo_package != 1) {
							// Do nothing here → execution continues to $totalTourists
						}
					} else {
						throw new Exception("Your cart is empty.Please add packages in the cart.");
					}
				} else {
					return $this->stripeCreatePaymentIntentForCart($request);
				}
			}

			$totalTourists = 0;
			for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
				$totalTourists += $request->tourists[$a];
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one tourist.');
			}

			// check the min and max pax allowed feature allowed in backend or not
			$isGroupRate = TourPackageService::validateMinPaxAndGetRateType(
				$request->tourists,
				$request->rate_group
			);

			$info = TourPackageService::getTourPackageSlotInfo($request->slot_time);

			// BookingUtils::checkAffiliateReservation($request, $info);
			BookingUtils::checkBookingTimeValidity($request, $info);
			BookingUtils::checkAffiliateDurationRestriction($info);

			$commissionRates = TourPackageService::getCommissionRates($request->affiliatecust, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

			$pricingDetails = BookingService::calculateTotalForCreateBooking($request, $stripeFeesPercentage, $natFeesPercentage, $info, $isGroupRate);
			$stripeTotal = $pricingDetails['stripeTotal'];
			$stripeFee = $pricingDetails['stripeFee'];
			$partnerFee = $pricingDetails['partnerFee'];
			$payoutAmount = $pricingDetails['payoutAmount'];

			$description = StripeService::generateDescription($info, $totalTourists, $request);
			$metaData = StripeService::prepareBookingData($info, $totalTourists, $request, $stripeTotal, $stripeFee, $partnerFee, $payoutAmount);
			$data = StripeUtils::createStripePaymentIntentNewBooking($info, $stripeTotal, $stripeFee, $description, $metaData);

			return $data;
		} catch (CardException $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Create stripe payment intent for booking payment
	 *
	 * @param object $request
	 * @return array
	 */
	public function stripeCreatePaymentIntentForCart($request)
	{
		DB::beginTransaction();
		try {

			$cardBookingsData = [];
			$cart = session()->get('cart');

			if (empty($cart) || !isset($cart['tour_packages']) || !isset($cart['tour_packages']) || empty($cart['tour_packages']) || empty($cart['customer_details'])) {
				throw new Exception("Your cart is empty.Please add packages in the cart.");
			}

			$cartPackages = $cart['tour_packages'];
			$cartSlotInfo = CartService::validateCartPackages($cartPackages);
			$totalTourists = 0;

			foreach ($cartPackages as $cartPackage) {
				for ($a = 0, $ac = count($cartPackage['tourists']); $a < $ac; $a++) {
					$totalTourists += $cartPackage['tourists'][$a];
				}
			}

			if (!$totalTourists) {
				throw new Exception('Please select at least one tourist.');
			}

			CartService::validateTouristsAndBookings($cartPackages, $cart, $request);

			if (!$cartSlotInfo['validatedCart']) {
				throw new Exception("The tour's date-time has passed; booking isn't possible in the past.");
			}

			$splitValue = !empty($request->split_value) ? $request->split_value : '';
			if (count($cartPackages) > 1 && !empty($splitValue)) {
				throw new Exception("You can do split payment only if single package in the cart.");
			}

			$affiliateCustomer = $request->affiliatecust;
			$paymentMethod = $request->payment_method;

			$totalCartAmount = 0;
			$totalCartFees = 0;
			$totalCustomCharge = 0;
			$totalCustomChargeFees = 0;
			$totalTipAmount = 0;
			$totalTipFees = 0;
			$i = 0;

			foreach ($cartPackages as $key => $cartPackage) {
				$slotTime = $cartPackage['slot_time'];
				$tourists = $cartPackage['tourists'];
				$rateGroup = $cartPackage['rate_group'];
				$permitFee = $cartPackage['permit_fee'];
				$tourOperatorNote = $cartPackage['tour_operator_note'] ?? '';
				$subtt = $cartPackage['subtt'];
				$fee = $cartPackage['fee'];
				$discountAmt = $cartPackage['discount_amount'];
				$discountType = $cartPackage['discount_type'];
				$customChargeAmount = $cartPackage['custom_charge'];
				$customChargeFees = $cartPackage['custom_charge_fees'];
				$tipAmount = $cartPackage['tip_amount'] ?? 0;
				$tipFees = $cartPackage['tip_fees'] ?? 0;
				$addonPrice = $cartPackage['addon_price'] ?? 0;
				$addonFee = $cartPackage['addon_fee'] ?? 0;
				$addonTotal = $cartPackage['addon_total'] ?? 0;

				$info = CartService::getTourSlotInfo($slotTime);

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

				$total = $cartPackageGroupTotals['total'];
				$totalTouristsA = $cartPackageGroupTotals['totalTourists'];
				$totalInfants = $cartPackageGroupTotals['totalInfants'] ?? 0;
				$serviceCommissionTotal = ($discountType != '') ? $fee : $cartPackageGroupTotals['serviceCommissionTotal'];
				$serviceCommission = $serviceCommissionTotal;
				$partnerFees = $cartPackageGroupTotals['partnerFeesTotal'];
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

				$cardBookingsData[] = CartService::prepareCartBookingData($info, [
					'total'                => $tt,
					'discount_amount'      => $discountAmt,
					'total_tourists'       => $totalTouristsA + $totalInfants,
					'subtotal'             => $subtt,
					'fee'                  => $fee,
					'partner_fee'     	   => $partnerFees,
					'slot_time'            => $slotTime,
					'tour_operator_note'   => $tourOperatorNote,
					'addon_price'          => $addonPrice,
					'addon_fee'            => $addonFee,
					'addon_total'          => $addonTotal,
					'custom_charge_amount' => $customChargeAmount,
					'custom_charge_fees'   => $customChargeFees,
					'tip_amount'           => $tipAmount,
					'tip_fees'             => $tipFees
				]);
				$i++;
			}
			/*end foreach*/

			$bookingsData = [
				'customer_name' => $request->customer_name,
				'customer_email' => $request->customer_email,
				'customer_phone' => $request->customer_phone_number,
				'data' => $cardBookingsData,
				'total' => $totalCartAmount,
				'fees' => $totalCartFees,
				'split_value' => $splitValue,
				'splitCount' => $request->dividedBy,
				'destination' => $info->stripe_destination_id,
				'totalCustomCharge' => $totalCustomCharge,
				'totalCustomChargeFees' => $totalCustomChargeFees,
				'totalTipAmount' => $totalTipAmount,
				'totalTipFees' => $totalTipFees
			];

			if ($bookingsData['split_value'] != '') {
				$bookingsData['total'] = $bookingsData['split_value'];
				if ($bookingsData['splitCount'] != 'Please select option') {
					$bookingsData['fees'] /= $bookingsData['splitCount'];
				}
			}

			$bookingsData['total'] = !empty($bookingsData['total']) ? $bookingsData['total'] : 0;

			$amounts = StripeService::calculateStripeAmountsCart($cardBookingsData, $bookingsData, $stripeFeesPercentage);

			// Step 2: Create Payment Intent
			$data = StripeUtils::createStripePaymentIntentCart($amounts, $bookingsData, $info);

			return $data;
		} catch (CardException $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}


	/**
	 * Create stripe payment intent for make payment in bookin info page
	 *
	 * @param object $request
	 * @return array
	 */
	public function stripeCreatePaymentIntentForMakePayment($request)
	{
		try {
			$feature = ($request->has('feature') && $request->feature != '') ? $request->feature : '';
			switch ($feature) {
				case 'make_payment':
					$data = StripeService::stripeCreatePaymentIntentForMakePayment($request);
					break;
				case 'change_people':
					$data = StripeService::stripeCreatePaymentIntentForChangePeople($request);
					break;
				case 'edit_discount':
					$data = StripeService::stripeCreatePaymentIntentForEditDiscount($request);
					break;
				case 'custom_charge':
					$data = StripeService::stripeCreatePaymentIntentForCustomCharge($request);
					break;
				case 'tip':
					$data = StripeService::stripeCreatePaymentIntentForTip($request);
					break;
				case 'create_tip':
					$data = StripeService::stripeCreatePaymentIntentForCreateTipTourGuide($request);
					break;		
				case 'switch_package':
					$data = StripeService::stripeCreatePaymentIntentForSwitchPackage($request);
					break;
				case 'add_ons':
					$data = StripeService::stripeCreatePaymentIntentForAddOns($request);
					break;
				case 'addon_edit_charge':
				case 'bulk_pax_addon_charge':
					$data = StripeService::stripeCreatePaymentIntentForAddonEditCharge($request);
					break;
				case 'change_payment_type':
					$data = StripeService::stripeCreatePaymentIntentForChangePaymentType($request);
					break;
				case 'make_combo_payment':
					$data = StripeService::stripeCreatePaymentIntentForMakeComboPayment($request);
					break;
				default:
					$data = StripeService::stripeCreatePaymentIntentForMakeComboPayment($request);
					break;
			}

			return $data;
		} catch (CardException $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Create stripe paymentintent in POS
	 *
	 * @param object $request
	 *
	 * @return array|JsonResponse
	 */
	public function stripeCreateStripePaymentIntentForPos(object $request): array|JsonResponse
	{
		try {
			$json = $request->json()->all();
			$customerName = $json['customerName'];
			$customerPhone = $json['customerPhone'];
			$customerEmail = $json['customerEmail'];
			$cartPackages = $json['tourPackages'] ?? [];

			if (empty($cartPackages)) {
				throw new Exception("Please add packages in the cart.");
			}

			$tourOperator = tourOperator();
			$tourOperatorId = $tourOperator->id;
			$destination = $tourOperator->stripe_destination_id;

			if ($customerEmail != '') {
				$customer = $this->customer::where('email', $customerEmail)
					->where('tour_operator_id', $tourOperatorId)
					->first();

				if (!$customer) {
					$pwdChars = '23456789~!@#$%^*()-=+_[]{};:?.,>abcdefghijkpqstuxyzABCDEFGHJKLMNPQRSTUXYZ';
					$pwd = '';

					for ($i = 0, $cl = strlen($pwdChars) - 1; $i < 10; $i++) {
						$pwd .= $pwdChars[rand(0, $cl)];
					}

					if ($customerName == '') {
						$defaultCustomer = PosService::getDefaultCustomerInfo($tourOperatorId);
						$customerName = $defaultCustomer->name;
					}

					$customer = $this->customer::create([
						'tour_operator_id' => $tourOperatorId,
						'name' => $customerName,
						'email' => $customerEmail,
						'phone_number' => str_replace(['(', ')', '+', '-', '.', ' '], '', $customerPhone),
						'password' => Hash::make(config('app.debug') ? '123456' : $pwd),
						'ip_address' => request()->ip(), // Capture IP address for POS customers
					]);

					// Get and store location data for the new POS customer
					if ($customer && $customer->id && request()->ip()) {
						IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, request()->ip());
					}
				}
			} else {
				$customer = PosService::getDefaultCustomerInfo($tourOperatorId);
				if ($customerName != '') {
					$customer->name = $customerName;
				}

				if ($customerPhone != '') {
					$customer->phone_number = $customerPhone;
				}
			}

			$totalCartAmount = 0;
			$totalCartFees = 0;
			$i = 0;
			$description = null;
			$statementDescriptor = '';
			$tourDates = [];

			foreach ($cartPackages as $cartPackage) {
				$info = PosService::getSlotInfo($cartPackage['tourPackageId']);
				$package = $this->tourPackage::where('id', $cartPackage['tourPackageId'])->first();
				$rateGroups = $cartPackage['rategroups'];

				if ($info) {
					$tourDates[] = $info->date;
					$natFeesPercentage = $info->service_commission_percentage;
					$stripeFeesPercentage = $info->stripe_commission_percentage;
					if (getNextYearCommissionPercentage($info->date)) {
						$serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
						$natFeesPercentage = $serviceCommission['service_commission_percentage'];
						$stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
					}
					$isGrouRateEnabled = $info->is_group_rate_enabled;

					if (!empty($rateGroups)) {
						$cartPackageData = [
							'rateGroups' => $rateGroups,
							'natFeesPercentage' => $natFeesPercentage,
							'stripeFeesPercentage' => $stripeFeesPercentage,
							'discountType' => $cartPackage['discountType'],
							'discount' => $cartPackage['discount'],
							'promoCode' => $cartPackage['promoCode'],
							'isGroupRateEnabled' => $isGrouRateEnabled
						];

						$cartPackageGroupTotals = PosService::calculateCartPackageGroupTotals($cartPackageData);
						if ($cartPackageGroupTotals['errorMessage'] != '') {
							throw new Exception($cartPackageGroupTotals['errorMessage']);
						}

						$total = $cartPackageGroupTotals['total'];
						$totalTourists = $cartPackageGroupTotals['totalTourists'];
						$serviceCommission = $cartPackageGroupTotals['serviceCommissionTotal'];

						$total += $serviceCommission;
						$total = round($total, 2);
						$totalCartAmount += $total;
						$totalCartFees += $serviceCommission;

						$description .= "Tour Package: " . $package->name .
							"\nTour Date: " . $info->date .
							"\nTour Time: " . $info->time_from .
							"\nTourists: " . $totalTourists;

						$metadataRateGroups = [];
						foreach ($rateGroups as $rateGroup) {
							$metadataRateGroup = [
								'id' => $rateGroup['id'],
								'tourists' => $rateGroup['tourists']
							];

							$metadataRateGroups[] = $metadataRateGroup;
						}
					}
				}

				$statementDescriptor = $info->statement_descriptor;
				$i++;
			}

			$stripeTotal = $totalCartAmount;
			$stripeFee = $totalCartFees;

			$stripeMetadata = [
				'customer_name' => $customer->name,
				'customer_phone' => $customer->phone_number,
				'email' => $customer->email,
				'from' => 'POS'
			];

			$data = StripeUtils::createStripePaymentIntentPos($stripeTotal, $stripeFee, $description, $stripeMetadata, $destination, $statementDescriptor, $tourDates);

			return $data;
		} catch (CardException $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * Delete a payment method from Stripe
	 *
	 * @param object $request
	 * @return JsonResponse
	 */

	public function stripeDeletePaymentMethod($request)
	{
	    try {

	        $request->validate([
	            'payment_method_id' => 'required|string|starts_with:pm_',
	        ]);

	        $gateway = app(PaymentGatewayInterface::class);

	        // Retrieve payment method from Stripe
	        $paymentMethod = $gateway->retrievePaymentMethod($request->payment_method_id);

	        if (!$paymentMethod || empty($paymentMethod->customer)) {
	            return response()->json([
	                'success' => false,
	                'message' => 'Payment method not attached to any customer.',
	            ], 400);
	        }

	        $customerId = $paymentMethod->customer;

	        // Detach card from Stripe
	        $gateway->detachPaymentMethod($request->payment_method_id);

	        // Soft delete from save_credit_card table
	        DB::table('save_credit_card')
	            ->where('customer_id', $customerId)
	            ->where('last4', $paymentMethod->card->last4)
	            ->where('exp_month', $paymentMethod->card->exp_month)
	            ->where('exp_year', $paymentMethod->card->exp_year)
	            ->whereNull('deleted_at')
	            ->update([
	                'deleted_by' => Auth::id(),
	                'deleted_by_user_type' => userType(),
	                'deleted_at' => now(),
	                'updated_at' => now(),
	            ]);

	        return response()->json([
	            'success' => true,
	            'message' => 'Payment method deleted successfully',
	        ]);

	    } catch (Exception $exception) {
			Log::error("Error deleting PaymentMethod: {$exception->getMessage()}");
			throw new GeneralCoreException($exception);
		}
	}

	public function saveCardList($request)
	{
		$tourBooking = TourBooking::find($request->booking_id);
		$customer = Customer::find($tourBooking->customer_id);
		$data = StripeUtils::saveCards($customer);
		return $data;
	}
}
