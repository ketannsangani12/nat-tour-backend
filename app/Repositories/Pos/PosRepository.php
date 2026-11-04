<?php

namespace App\Repositories\Pos;

use App\Constants\Constants;
use App\Events\BookingNew;
use App\Events\BookingRequestNotification;
use App\Exceptions\Core\GeneralCoreException;
use App\Mail\TourBooking\PosOrderReceipt;
use App\Models\BookingRequest;
use App\Models\BookingTransact;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\Repository;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Models\TourPromotion;
use App\Models\Transaction;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\BookingService;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\StripeService;
use App\Services\Booking\TransactionService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentModeInference;
use App\Services\Payments\PaymentModeResolver;
use App\Services\CardBookingService;
use App\Services\CashBookingService;
use App\Services\Pos\PosDontRefundService;
use App\Services\Pos\PosFullRefundService;
use App\Services\Pos\PosService;
use App\Services\Pos\PosTicketRefundService;
use App\Services\RefundBooking\DontRefundService;
use App\Services\RefundBooking\TicketRefundService;
use App\Services\RefundBooking\WeatherRefundService;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TimeSlot\TimeSlotService;
use App\Services\TourOperator\TourOperatorService;
use App\Utils\BookingUtils;
use App\Utils\ManagerRequestUtils;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\CardException;

class PosRepository extends Repository
{

    public $customer = Customer::class;

    public $tourPackageRate = TourPackageRate::class;

    public $tourBooking = TourBooking::class;

    public $bookingTransact = BookingTransact::class;

    public $transaction = Transaction::class;

    /**
     * Initialize the POS
     * TODO: Need to optimize this method in future
     * @return array
     */
    public function initPos(): array
    {
        try {

            $categories = Category::where('status', 'Active')->get();
            if ($categories) {
                $defaultCategoryId = $categories[0]->id;
            }
            $minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_FOUR]);
            $cashFees = hasSetting('CASH_FEES');
            $tourOperator = TourOperatorService::getTourOperator();
            $currentTime = Carbon::now($tourOperator->timezone)->format('Y-m-d H:i:s');
            $managers = (Auth::check()) ? BookingService::getManagers() : [];
            $allowedPermissions = (Auth::check()) ? BookingService::requestPermissions(Auth::user()->id) : [];
            $managerRequestEnable = (Auth::check()) ? BookingService::managerRequestEnable(Auth::user()->id) : [];
            $customerInfoMandatory = hasSetting('CUSTOMER_INFO_MANDATORY');

            $vars = [
                'page_title' => 'POS',
                'pageId' => 'pos',
                'categories' => $categories,
                'defaultCategoryId' => $defaultCategoryId ?? '',
                'minMaxPaxApply' => $minMaxPaxApply,
                'cashFees' => $cashFees,
                'currentTime' => $currentTime,
                'managers' => $managers,
                'managerRequestEnable' => $managerRequestEnable,
                'allowedPermissions' => $allowedPermissions,
                'customerInfoMandatory' => $customerInfoMandatory
            ];
            return $vars;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get packages category wise
     * @param int $categoryId
     * @param object $request
     *  @return mixed
     */
    public function getPackages($categoryId, $request): mixed
    {
        try {
            $departmentPackage = departmentPackage();
            $json = $request->json()->all();
            $cartPackages = $json['tourPackages'] ?? [];

            $todayDate = Carbon::now()->format('Y-m-d');
            $dateNow = Carbon::now()->format('Ymd');
            $search = $json['search'] ?? '';

            $tourPackageData = PosService::getAvailableTourPackages($categoryId, $departmentPackage, $search, $todayDate);

            $tourPackages = [];
            if (!$tourPackageData->isEmpty()) {
                foreach ($tourPackageData as $data) {
                    $tourPackageId = $data->tourPackageId;
                    if (getNextYearCommissionPercentage($todayDate)) {
                        $serviceCommission = getNormalCommissionPercentage($tourPackageId);
                        $data->service_commission_percentage = $serviceCommission['service_commission_percentage'];
                    }

                    // Default values
                    $applyOnDate = false;
                    $applyOnSlots = false;
                    $date = $dateNow;
                    $time = '';

                    // Check if the package exists in cartPackages
                    if (!empty($cartPackages) && in_array($tourPackageId, array_column($cartPackages, 'tourPackageId'))) {
                        $cartPackageIndex = array_search($tourPackageId, array_column($cartPackages, 'tourPackageId'));

                        $applyOnDate = $cartPackages[$cartPackageIndex]['applyOnDate'];
                        $applyOnSlots = $cartPackages[$cartPackageIndex]['applyOnSlots'];
                        $date = !empty($cartPackages[$cartPackageIndex]['slotDate'])
                            ? date('Ymd', strtotime($cartPackages[$cartPackageIndex]['slotDate']))
                            : $dateNow;

                        $time = $cartPackages[$cartPackageIndex]['slotTime'];
                    }

                    // Fetch dynamic rate groups
                    $rateGroups = PosService::getDynamiceRates($tourPackageId, $applyOnDate, $applyOnSlots, $date, $time);

                    // Build tour package data array
                    $tourPackages[$tourPackageId] = [
                        'tourPackageName'          => $data->tourPackageName,
                        'tourPackageId'            => $tourPackageId,
                        'packageHasSlots'          => $data->packageHasSlots,
                        'packageImage'             => !empty($data->tourPackageImage)
                            ? tourPackageLogo($data->tenant_id, $tourPackageId, $data->tourPackageImage)
                            : asset('images/no-photo.png'),
                        'isGroupRateEnabled'       => $data->isGroupRateEnabled,
                        'serviceCommissionPercentage' => $data->service_commission_percentage,
                        'rategroups'               => $rateGroups,
                    ];

                    // Clean up unused data
                    unset($data->tourPackageImage, $data->tenant_id, $data->slotCount, $data->tourPackageId);
                }
            }

            $tourPackages = array_values($tourPackages);

            foreach ($tourPackages as $k => $tourPackage) {
                if ($tourPackage['isGroupRateEnabled'] == 1) {
                    $rateGroups = $tourPackage['rategroups'];
                    $paxWiseGroupRateIds = [];
                    $paxWiseGroupRates = [];
                    $paxWiseGroupAdditionalCharges = [];
                    foreach ($rateGroups as $rateGroup) {
                        if ($rateGroup->is_group_rate == 1) {
                            if ($rateGroup->min_group_size > 0 && $rateGroup->max_group_size > 0 && $rateGroup->min_group_size <= $rateGroup->max_group_size) {
                                for ($i = $rateGroup->min_group_size; $i <= $rateGroup->max_group_size; $i++) {
                                    $paxWiseGroupRateIds[$i] = $rateGroup->id;
                                    $paxWiseGroupRates[$i] = (float)$rateGroup->rate;
                                    $paxWiseGroupAdditionalCharges[$i] = ($rateGroup->additional_charge > 0) ? (float)$rateGroup->additional_charge : 0.00;
                                }
                            }
                        }
                    }
                    $tourPackages[$k] = array_merge($tourPackages[$k], [
                        'paxWiseGroupRateIds' => $paxWiseGroupRateIds,
                        'paxWiseGroupRates' => $paxWiseGroupRates,
                        'paxWiseGroupAdditionalCharges' => $paxWiseGroupAdditionalCharges,
                    ]);
                }
            }

            return response()->json([
                'tourPackages' => $tourPackages
            ]);
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
     */
    public function manualPinCheck($request)
    {
        try {
            $staff = Staff::where('manager_pin', $request->manager_pin)
                ->where('manager_id', $request->manager_id)
                ->where('id', $request->manager)
                ->where('status', '!=', 'Deleted')
                ->first();

            if (!empty($staff)) {
                $data = [
                    'message' => 'Correct',
                    'status' => 'success'
                ];
            } else {
                $data = [
                    'message' => 'Manager PIN is incorrect',
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
     * Apply Promo Code for Tour Package
     * @param object $request
     *
     * @return mixed
     */
    public function applyPromoCode(object $request): mixed
    {
        try {
            $tourPackageId = $request->tourPackageId;
            $promoCode = $request->promoCode;
            $tourPromotion = TourPromotion::where('tour_package_id', $tourPackageId)
                ->where('code', $promoCode)
                ->first();

            if (!$tourPromotion) {
                return sendJsonErrMsg("Please enter valid promo code.");
            }

            $date = Carbon::now()->format('Y-m-d');
            $endDate = $tourPromotion->end_date;
            if ($date > $endDate) {
                return sendJsonErrMsg("Promo code is expired.");
            }

            $discount = $tourPromotion->discount_value;

            return [
                'discountType' => $tourPromotion->discount_value_type,
                'discount' => $discount,
                'tourPackageId' => $tourPackageId,
                'promoCode' => $promoCode
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Validate cart in POS
     * @param object $request
     *
     * @return mixed
     */
    public function validateCart(object $request): mixed
    {
        try {
            $json = $request->json()->all();
            $cartPackages = $json['tourPackages'] ?? [];
            $customerName = $json['customerName'] ?? '';
            $customerEmail = $json['customerEmail'] ?? '';
            if (empty($cartPackages)) {
                return sendJsonErrMsg("Your cart is empty.Please add packages in the cart.");
            }
            $customerInfoMandatory = hasSetting('CUSTOMER_INFO_MANDATORY');
            if ($customerInfoMandatory && empty($customerName) && empty($customerEmail)) {
                return sendJsonErrMsg("Please provide customer name and email.");
            }
            $dataArray = [];
            $closedSlotsOfPackages = [];
            foreach ($cartPackages as $key => $item) {
                if ($item['slotId'] != '') {
                    $slotTimes = PosService::getSlotTimes($item);

                    $relationPackage = [];
                    $totalTourists = 0;
                    if ($slotTimes) {
                        /* START - mapping tour package and reducing the relational seats */
                        if (hasSetting('PACKAGE_RELATION')) {
                            $tourPackageFamily = PosService::getRelatedTourPackageFamily($item['tourPackageId'], $item['slotDate']);
                            if (count($tourPackageFamily)) {
                                $totalTourists = PosService::calculateTotalTourists($tourPackageFamily, $slotTimes);
                            }
                        }
                        /* END */
                        $seats = $slotTimes->seats;
                        $tourists = ((int) $totalTourists > 0 && !empty($relationPackage)) ? $totalTourists : $slotTimes->tourists;
                        $availableSeats = $seats - $tourists;
                        $totalTourist = $item['tourists'];
                        if ($availableSeats < 0 || $availableSeats < $totalTourist) {
                            $dataArray[] = $item['tourPackageName'];
                        }
                    } else {
                        $closedSlotsOfPackages[] = $item['tourPackageName'];
                    }
                }
            }

            if (!empty($dataArray)) {
                return response()->json(['tourPackages' => array_unique($dataArray), 'closedSlotsForPackages' => array_unique($closedSlotsOfPackages)]);
            }

            if (!empty($closedSlotsOfPackages)) {
                return response()->json(['closedSlotsOfPackages' => array_unique($closedSlotsOfPackages)]);
            }

            if (empty($dataArray)) {
                $minMaxPaxApply = in_array(minMaxPaxApply(), [Constants::STATUS_ONE, Constants::STATUS_FOUR]);
                $bookingAllowed = true;
                $tourPackagesMinMaxPax = [];

                foreach ($cartPackages as $key => $cartPackage) {
                    if ($item['slotId'] != '') {
                        $date = date('Ymd', strtotime($cartPackage['slotDate']));
                        $time = $cartPackage['slotTime'];
                    } else {
                        $date =  date('Ymd');
                        $time = '';
                    }

                    $tourPackageId = $cartPackage['tourPackageId'];
                    $applyOnDate = $cartPackage['applyOnDate'];
                    $applyOnSlots = $cartPackage['applyOnSlots'];

                    $packageRateGroups = PosService::getDynamiceRates($tourPackageId, $applyOnDate, $applyOnSlots, $date, $time, $minMaxPaxApply);
                    $rateGroups = $cartPackage['rateGroups'];

                    // Check package rate groups
                    foreach ($packageRateGroups as $packageRateGroup) {
                        if ($minMaxPaxApply && array_search($packageRateGroup->id, array_column($rateGroups, 'id')) === false) {
                            $bookingAllowed = BookingUtils::setMinMaxPaxDetails($tourPackagesMinMaxPax, $tourPackageId, $cartPackage, $packageRateGroup);
                        }
                    }
                    foreach ($rateGroups as $rateGroup) {
                        $result = TourPackageRate::find($rateGroup['id']);

                        if ($minMaxPaxApply) {
                            if (
                                $result->min_pax_allowed > $rateGroup['tourists'] ||
                                ($result->max_pax_allowed > 0 && $result->max_pax_allowed < $rateGroup['tourists'])
                            ) {
                                $bookingAllowed = BookingUtils::setMinMaxPaxDetails($tourPackagesMinMaxPax, $tourPackageId, $cartPackage, $result);
                            }
                        }
                    }
                }

                if (!$bookingAllowed) {
                    $tourPackagesMinMaxPax = array_values($tourPackagesMinMaxPax);
                    return response()->json(['tourPackagesMinMaxPax' => $tourPackagesMinMaxPax]);
                } else {
                    return response()->json([]);
                }
            }

            return response()->json([]);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg('Unable to process booking.');
        }
    }

    /**
     * Create bookings in POS
     * @param object $request
     *
     * @return mixed
     */
    public function createBookings(object $request): mixed
    {
        DB::beginTransaction();
        try {
            $json = $request->json()->all();
            $cartPackages = $json['tourPackages'] ?? [];
            $paymentMethod = $json['paymentMethod'];
            $note = $json['note'] ?? '';
            $customerName = $json['customerName'];
            $customerPhone = $json['customerPhone'];
            $customerEmail = $json['customerEmail'];
            $isCashFees = cashFees();
            $paymentIntentId = $json["cardReaderPaymentIntentId"] ?? null;

            if (empty($cartPackages)) {
                return sendJsonErrMsg("Your cart is empty.Please add packages in the cart.");
            }

            if ($paymentMethod == '') {
                return sendJsonErrMsg("Something went wrong.Please choose payment method properly.");
            }

            $createdByWithName = BookingService::createdUserInfo();
            $tourOperatorInfo = TourPackage::where('id', $cartPackages[0]['tourPackageId'])->first();
            $tourOperatorId = $tourOperatorInfo->tour_operator_id;
            $customer = PosService::getOrCreateCustomer($tourOperatorId, $customerEmail, $customerName, $customerPhone);
            $cartId = Str::random(10) . time();
            $firstBookingId = '';
            $totalCartAmount = 0;
            $totalCartFees = 0;
            $i = 0;

            foreach ($cartPackages as $key => $cartPackage) {
                $info = PosService::getSlotInfo($cartPackage['tourPackageId'], $cartPackage['slotId']);
                if (!$info) {
                    DB::rollBack();
                    return sendJsonErrMsg('Tourslot not found.');
                }

                $rateGroups = $cartPackage['rategroups'];

                if ($info) {
                    $natFeesPercentage = $info->service_commission_percentage;
                    $stripeFeesPercentage = $info->stripe_commission_percentage;
                    if (getNextYearCommissionPercentage($info->date)) {
                        $serviceCommission = getNormalCommissionPercentage($info->tour_package_id);
                        $natFeesPercentage = $serviceCommission['service_commission_percentage'];
                        $stripeFeesPercentage = $serviceCommission['stripe_commission_percentage'];
                    }
                    $isGroupRateEnabled = $info->is_group_rate_enabled;
                    if (!empty($rateGroups)) {
                        $cartPackageData = [
                            'rateGroups' => $rateGroups,
                            'natFeesPercentage' => $natFeesPercentage,
                            'stripeFeesPercentage' => $stripeFeesPercentage,
                            'discountType' => $cartPackage['discountType'],
                            'discount' => $cartPackage['discount'],
                            'promoCode' => $cartPackage['promoCode'],
                            'isGroupRateEnabled' => $isGroupRateEnabled
                        ];
                        $cartPackageGroupTotals = PosService::calculateCartPackageGroupTotals($cartPackageData);
                        if ($cartPackageGroupTotals['errorMessage'] != '') {
                            DB::rollBack();
                            return sendJsonErrMsg($cartPackageGroupTotals['errorMessage']);
                        }

                        $tbTransact = $cartPackageGroupTotals['tbTransact'];
                        $subtt = $total = $cartPackageGroupTotals['total'];
                        $discountAmt = $cartPackageGroupTotals['discountAmt'];
                        $discountPercentage = $cartPackageGroupTotals['discountPercentage'];
                        $discountPromoCode = $cartPackageGroupTotals['discountPromoCode'];

                        $totalTouristsA = $cartPackageGroupTotals['totalTourists'];
                        $touristGroups = $cartPackageGroupTotals['touristGroups'];

                        $serviceCommissionTotal = $cartPackageGroupTotals['serviceCommissionTotal'];

                        $serviceCommission = $serviceCommissionTotal;
                        $total += $serviceCommission;
                        $tt = $total = round($total, 2);
                        $totalCartAmount += $total;
                        $totalCartFees += $serviceCommission;
                        $expiryDate = BookingService::calculateExpiryDate($info->tour_package_id, $customer->id);
                        //Create Booking
                        $tourBooking = $this->tourBooking::create([
                            'tour_package_id' => $info->tour_package_id,
                            'tour_slot_id' => $info->slotId,
                            'customer_id' => $customer->id,
                            'cart_id' => $cartId,
                            'tourists' => $totalTouristsA,
                            'name' => $customer->name,
                            'email' => $customer->email,
                            'phone_number' => $customer->phone_number ?? '',
                            'note' => $note ?? '',
                            'note_datetime' => mysqlDT(),
                            'mask_required' => 'Yes',
                            'discount2_value' => $discountAmt,
                            'discount2_percentage' => $discountPercentage,
                            'discount_promo_code' => $discountPromoCode,
                            'service_commission' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $serviceCommission : 0,
                            'total' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $tt : $subtt,
                            'booking_total' => ($paymentMethod == "Card" || ($isCashFees == '1' && $paymentMethod == 'Cash')) ? $tt : $subtt,
                            'status' => 'Booked',
                            'is_group_booking' => ($isGroupRateEnabled == '1') ? 1 : 0,
                            'visitor' => $request->getClientIp(),
                            'expire_at' => $expiryDate,
                            'request_from' => $request->is('api/*') ? Constants::API : Constants::BACKEND,
                            'created_by' => empty($bookedByStaff) ? Auth::id() : $bookedByStaff,
                            'created_by_user_type' => userType(),
                            'updated_at' => null,
                        ]);

                        for ($a = 0, $ac = count($tbTransact); $a < $ac; $a++) {
                            $tbTransact[$a]['tour_booking_id'] = $tourBooking->id;
                        }

                        DB::table('tour_bookings_transact')->insert($tbTransact);

                        // Close the time slot on booking
                        TimeSlotService::closeSlotOnBooking($tourBooking->tour_package_id, $tourBooking->tour_slot_id);

                        $addedPeoplesText = $cartPackageGroupTotals['addedPeoplesText'];

                        HistoryService::storeTouristInHistory($request, $tourBooking, $discountAmt, $addedPeoplesText, $totalTouristsA, $createdByWithName);

                        if ($note) {
                            HistoryService::storeNoteInHistory($note, $tourBooking->id);
                        }
                        // Cash payment method
                        if ($paymentMethod == 'Cash') {
                            $changeAmount = 0;

                            $cashAmount = $isCashFees == 1 ? $tt : $subtt;
                            $feeAmountCash = $isCashFees == 1 ? $serviceCommission : 0;

                            $transAmount = $cashAmount;
                            $paymentType = 0;
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

                            $cashPaymentData = [
                                'amount' => $cashAmount,
                                'payment_method' => 'Full Payment'
                            ];

                            $historyId = HistoryService::storeCashPaymentInHistory($tourBooking->id, $totalTouristsA, $cashPaymentData, $changeAmount, $createdByWithName);

                            // Below line is for to update tour booking history table
                            $isSplitOrFull = 'Full Payment';
                            if ($isCashFees == '1') {
                                $bookingData = [
                                    'total'               => $tt,
                                    'discount2_value'     => $discountAmt,
                                    'tourists'            => $totalTouristsA,
                                    'booking_id'          => $tourBooking->id,
                                    'customer_id'         => $tourBooking->customer_id,
                                    'customer_name'       => $tourBooking->name,
                                    'customer_email'      => $tourBooking->email,
                                    'customer_phone'      => $tourBooking->phone_number,
                                    'tour_package'        => $info->package_name,
                                    'date'                => $info->date,
                                    'time_from'           => $info->time_from,
                                    'subtotal'            => $subtt,
                                    'service_commission'  => $serviceCommission,
                                    'destination'         => $info->stripe_destination_id,
                                    'slot_time'           => $info->slotId,
                                    'tour_operator_id'    => $info->tour_operator_id,
                                    'statement_descriptor' => $info->statement_descriptor,
                                    'card_reader_prefix'  => $info->card_reader_prefix,
                                    'tour_package_id'     => $info->tour_package_id,
                                ];

                                $requestData['payment_method'] = 'Cash';
                                CardBookingService::run($bookingData, $transactionId, $historyId, $isSplitOrFull, $requestData);
                            } else {
                                CashBookingService::run($tourBooking, $transactionId, $historyId, $isSplitOrFull, []);
                            }
                            DB::commit();
                        }

                        $bookingNotifInfo =  PosService::notificationOfNewBooking($tourBooking, $info, $paymentMethod, $subtt, $tt, $touristGroups, $totalTouristsA, $isCashFees);
                        if ($paymentMethod == 'Card') {
                            $cardBookingsData[] = [
                                'total' => $tt,
                                'discount2_value' => $discountAmt,
                                'tourists' => $totalTouristsA,
                                'booking_id' => $tourBooking->id,
                                'tour_package' => $info->package_name,
                                'date' => $info->date,
                                'time_from' => $info->time_from,
                                'subtotal' => $subtt,
                                'serviceCommission' => $serviceCommission,
                                'destination' => $info->stripe_destination_id,
                                'slot_time' => $info->slotId,
                                'tour_operator_id' => $info->tour_operator_id,
                                'statement_descriptor' => $info->statement_descriptor,
                                'card_reader_prefix' => $info->card_reader_prefix,
                                'tour_package_id' => $info->tour_package_id,
                                'bookingNotifInfo' => $bookingNotifInfo
                            ];
                        }

                        if ($i == 0) {
                            $firstBookingId = $tourBooking->id;
                        }

                        TimeSlotAvailabilityService::updateSlotSummary($tourBooking->tour_slot_id, $tourBooking->tourists);

                        $i++;
                    }
                }
            }

            if ($paymentMethod == 'Cash') {
                $orderId = $tourBooking->group_id;
                if ($customerEmail != '' && $orderId != '') {
                    $arEmailData = PosService::posOrderDetails($tourBooking->group_id, $customerEmail);

                    if ((Auth::check() && in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) || !Auth::check()) {
                        Mail::send(new PosOrderReceipt($arEmailData, 'pos_order_receipt'));
                    }
                }
                $tourBooking = $this->tourBooking::where('id', $firstBookingId)->first();

                return [
                    'orderId' => $tourBooking->group_id,
                    'date' => Carbon::parse($tourBooking->created_at)->format('l, M j, Y'),
                    'time' => Carbon::parse($tourBooking->created_at)->format('H:i A'),
                    'orderToken' => Crypt::encryptString($tourBooking->group_id), // for security purpose
                    'customerName' => $customer->name,
                    'customerEmail' => $customer->email,
                    'customerPhone' => $customer->phone_number ?? ''
                ];
            }
            $master = $this->tourBooking::where('id', $firstBookingId)->first();

            if ($paymentMethod == 'Card') {

                $data = [
                    'customer_id' => $master->customer_id,
                    'customer_name' => $master->name,
                    'customer_email' => $master->email,
                    'customer_phone' => $master->phone_number,
                    'data' => $cardBookingsData,
                    'total' => $totalCartAmount,
                    'fees' => $totalCartFees,
                    'destination' => $info->stripe_destination_id
                ];

                $paymentIntentInfo = $this->cardPaymentForPos($data, $paymentIntentId);
                if (isset($paymentIntentInfo['ex']) && !empty($paymentIntentInfo['ex'])) {
                    DB::rollBack();
                    return sendJsonErrMsg($paymentIntentInfo['ex']);
                }

                DB::commit();
            }

            return [
                'orderId' => $master->group_id,
                'date' => Carbon::parse($master->created_at)->format('l, M j, Y'),
                'time' => Carbon::parse($master->created_at)->format('H:i A'),
                'orderToken' => Crypt::encryptString($master->group_id), // for security purpose
                'customerName' => $customer->name,
                'customerEmail' => $customer->email,
                'customerPhone' => $customer->phone_number ?? ''

            ];
        } catch (CardException $exception) {
            Log::error($exception);
            DB::rollBack();
            return sendJsonErrMsg($exception->getMessage());
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }

    /**
     * Card payment for POS
     *
     * @param array $data
     * @param string|null $exsitingPaymentIntentId Payment intent id if already created
     * @return array
     */
    public function cardPaymentForPos($data, $paymentIntentId = null)
    {
        DB::beginTransaction();
        try {
            $cardBookingsData = $data['data'];
            $data['total'] = !empty($data['total']) ? $data['total'] : 0;
            $stripeFee = 0;
            $stripeTotal = 0;
            foreach ($cardBookingsData as $cardBookingData) {
                $stripeFee += $cardBookingData['serviceCommission'];
                $stripeTotal += $cardBookingData['total'];
            }

            if ($data['total'] != 0) {
                $stripeMetadata = PosService::stripeMetadata($data);

                // if we have an existing payment intent
                if ($paymentIntentId != null) {
                    $retrieveOptions = ['expand' => ['charges.data.transfer']];
                    $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

                    $updateParams = $stripeMetadata;
                    $paymentIntent = app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

                    if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
                        $chargeParams = $stripeMetadata;
                        app(PaymentGatewayInterface::class)->updateConnectedPayment(
                            $paymentIntent->charges->data[0]->transfer->destination_payment,
                            $chargeParams,
                            $paymentIntent->charges->data[0]->transfer->destination
                        );
                    }
                }
            }

            $firstBookingId = '';
            foreach ($cardBookingsData as $cardBookingData) {
                $transAmount = $cardBookingData['total'];
                $feesAmount = $cardBookingData['serviceCommission'];
                $paymentType =  0;
                $firstBookingId = $cardBookingData['booking_id'];

                $transactionId = TransactionService::storePaymentInTransaction(
                    $cardBookingData['booking_id'],
                    $transAmount,
                    $feesAmount,
                    'Card',
                    $paymentType,
                    !empty($paymentIntent->id) ? $paymentIntent->id : null,
                    !empty($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
                    mode: !empty($paymentIntent) ? PaymentModeInference::fromStripeObject($paymentIntent) : PaymentModeResolver::DESTINATION
                );
                DB::commit();

                $createdByWithName = BookingService::createdUserInfo();
                $cardDetails = StripeService::extractCardDetails($paymentIntent, false);
                $crd = $cardDetails['last4'];
                $cardType = $cardDetails['brand'];
                $tourBookingDetails = $this->tourBooking::find($cardBookingData['booking_id']);

                $historyId = HistoryService::storeCardPaymentInHistory($cardBookingData['booking_id'], $tourBookingDetails->tourists, $data, $crd, $cardType, $createdByWithName, $cardBookingData);

                // Below line is for to update tour booking history table
                $isSplitOrFull = 'Full Payment';
                $cardBookingData['customer_id'] = $data['customer_id'];
                CardBookingService::run($cardBookingData, $transactionId, $historyId, $isSplitOrFull, request()->all(), $paymentIntent);

                if ($cardBookingData['bookingNotifInfo'] && !empty($cardBookingData['bookingNotifInfo'])) {
                    BookingNew::dispatch($cardBookingData['bookingNotifInfo']);
                    $cardBookingData['bookingNotifInfo']['notif_for'] = 'Tour Operator';
                    BookingNew::dispatch($cardBookingData['bookingNotifInfo']);
                }
            }
            $tourBooking = $this->tourBooking::where('id', $firstBookingId)->first();
            $customerEmail = $tourBooking->email;
            $orderId = $tourBooking->group_id;
            if ($customerEmail != '' && $orderId != '') {
                $arEmailData = PosService::posOrderDetails($orderId, $customerEmail);
                if ((Auth::check() && in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) || !Auth::check()) {
                    Mail::send(new PosOrderReceipt($arEmailData, 'pos_order_receipt'));
                }
            }
            return [
                'orderId' => $orderId,
                'date' => Carbon::parse($tourBooking->created_at)->format('l, M j, Y'),
                'time' => Carbon::parse($tourBooking->created_at)->format('H:i A'),
                'orderToken' => Crypt::encryptString($tourBooking->group_id), // for security purpose
            ];
            //}
        } catch (CardException $exception) {
            Log::error($exception);
            DB::rollBack();
            $cardBookingsData = $data['data'];

            return [
                'orderId' => '',
                'date' => '',
                'time' => '',
                'ex' => $exception->getMessage(),
            ];
        }
    }
    /**
     * POS Orders List
     *
     *
     * @return array
     */
    public function orders(): mixed
    {
        try {
            $search = (request()->has('search') && request()->search != '') ? request()->search : '';

            $searchQuery = '';
            if ($search != '') {
                $searchQuery = " AND `tbm`.`group_id` LIKE '%$search%'";
            }

            $departmentPackageQuery = '';
            $departmentPackage = departmentPackage();
            if ($departmentPackage) {
                $departmentPackageIdsString = implode("','", $departmentPackage);
                $departmentPackageQuery = " AND `tbm`.`tour_package_id` IN ('$departmentPackageIdsString')";
            }

            $orders = DB::select("
                    SELECT 
                        tbm.group_id AS orderId,
                        tbm.created_at AS createdAt,
                        SUM(
                            CASE 
                                WHEN tbh.operation = 'Payment' 
                                THEN tbh.ticket_cost + tbh.platform_fees
                                ELSE 0
                            END
                        ) AS paymentCost,
                        SUM(
                            CASE 
                                WHEN tbh.operation IN ('Refund', 'Cancelled') 
                                THEN tbh.ticket_cost + tbh.platform_fees
                                ELSE 0
                            END
                        ) AS refundCost
                    FROM tour_bookings_master AS tbm
                    INNER JOIN tour_booking_history AS tbh 
                        ON tbh.tour_booking_id = tbm.id
                    WHERE tbm.group_id IS NOT NULL
                        $searchQuery
                        $departmentPackageQuery
                    GROUP BY tbm.group_id
                    ORDER BY tbm.group_id DESC
                    LIMIT 1000
                ");

            if ($orders) {
                foreach ($orders as $key => $order) {
                    $orders[$key]->totalSale = 0;
                    $paymentCost = round($order->paymentCost, 2);
                    $refundCost = round($order->refundCost, 2);
                    $orders[$key]->totalSale = $paymentCost - $refundCost;
                }
            }
            return [
                'orders' => $orders
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * POS Order Info
     *
     * @param array $data
     * @return mixed
     */
    public function order($orderId): mixed
    {
        try {

            $tourBookings = DB::select("SELECT
                `tbm`.`id`,
                `tbm`.`group_id` as `orderId`,
                `tbm`.`created_at` as `createdAt`,
                `tbm`.`tourists`,
                `tbm`.`status`,
                `tbm`.`service_commission` as `platformFees`,
                `tbm`.`discount2_value`,
                `tsd`.`date`,
				`tst`.`time_from`,
                `tp`.`name` as `tourPackageName`,
                `tp`.`package_has_slots` as `packageHasSlots`,
                `c`.`name` as `customerName`,
                `c`.`email` as `customerEmail`,
                `c`.`phone_number` as `customerPhone`,
                (SELECT SUM(`tbt`.`total`) FROM tour_bookings_transact as tbt WHERE tbt.deleted_by = 0 and tbt.tour_booking_id = tbm.id  GROUP BY tbt.tour_booking_id) as bookingTotal,
                (SELECT SUM(CASE
                        WHEN `tbh`.`operation` = 'Payment' THEN `tbh`.`ticket_cost` + ifnull(`tbh`.`platform_fees`, 0)
                        ELSE 0
                        END)
                            FROM tour_booking_history AS tbh
                            WHERE tbh.tour_booking_id = tbm.id) as paymentCost,
                (SELECT SUM(CASE
                        WHEN `tbh`.`operation` = 'Refund' THEN `tbh`.`ticket_cost` + ifnull(`tbh`.`platform_fees`, 0)
                        WHEN `tbh`.`operation` = 'Cancelled' THEN `tbh`.`ticket_cost` + ifnull(`tbh`.`platform_fees`, 0)
                        ELSE 0
                        END)
                            FROM tour_booking_history AS tbh
                            WHERE tbh.tour_booking_id = tbm.id) as refundCost,
                (SELECT t.via FROM transactions AS t WHERE t.tour_booking_id = tbm.id AND t.status = 'Completed' GROUP BY t.tour_booking_id) AS paymentMethod
            FROM
                `tour_bookings_master` AS `tbm`
                INNER JOIN `tour_slot_times` AS `tst` ON `tst`.`id` = `tbm`.`tour_slot_id`
                INNER JOIN `tour_slot_dates` AS `tsd` ON `tsd`.`id` = `tst`.`tour_slot_id`
                INNER JOIN `tour_packages` AS `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
                INNER JOIN `customers` AS `c` ON `c`.`id` = `tbm`.`customer_id`
            WHERE
                `tbm`.`group_id` = $orderId
             ");



            if (empty($tourBookings)) {
                return sendJsonErrMsg("Order not found.");
            }
            $customChargeTotal = 0;
            $customChargeFeesTotal = 0;
            $addOnsTotal = 0;
            $addOnsFeesTotal = 0;
            $customerName = $tourBookings[0]->customerName;
            $customerEmail = $tourBookings[0]->customerEmail;
            $customerPhone = $tourBookings[0]->customerPhone;
            foreach ($tourBookings as $tourBooking) {
                $transactions =  DB::table('transactions as t')->where('t.tour_booking_id', $tourBooking->id)
                    ->whereIn('status', ['Completed', 'Failed', 'Refund'])
                    ->whereNull('charge_type')
                    ->orderBy('id', 'DESC')
                    ->get();

                $customChargePrice = CustomChargeBookingService::customChargePrice($tourBooking->id, $transactions);
                $pricedAddons = BookingService::fetchAddonsWithPrice($tourBooking->id);
                $customChargeTotal += $customChargePrice['custom_charge_total'];
                $customChargeFeesTotal += $customChargePrice['custom_charge_fees'];
                $addOnsTotal += $pricedAddons['addons_final_price'];
                $addOnsFeesTotal += $pricedAddons['addons_final_fee'];
            }
            $payments = DB::table('tour_booking_history as tbh')
                ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'tbh.tour_booking_id')
                ->where('tbm.group_id', $orderId)
                ->where('tbh.operation', 'Payment')
                ->groupBy('tbh.card_last_4_digit')
                ->orderBy('tbh.id', 'ASC')
                ->select([
                    'tbh.card_last_4_digit as cardLastDigits',
                    'tbh.payment_type as paymentMethod',
                    DB::raw("SUM(JSON_UNQUOTE(JSON_EXTRACT(tbh.details, '$.amount'))) AS amount"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(tbh.details, '$.card_type')) AS cardType")
                ])
                ->get();
            $bookingsInfo = $tourBookings;
            $requestPermissions = (Auth::check()) ? PosService::requestPermissions($orderId) : [];
            return [
                'bookingsInfo' => $bookingsInfo,
                'date' => Carbon::parse($bookingsInfo[0]->createdAt)->format('l, M j, Y'),
                'time' => Carbon::parse($bookingsInfo[0]->createdAt)->format('H:i A'),
                'orderId' => $orderId,
                'paymentMethod' => $bookingsInfo[0]->paymentMethod,
                'payments' => $payments,
                'customChargeTotal' => $customChargeTotal,
                'customChargeFeesTotal' => $customChargeFeesTotal,
                'addOnsTotal' => $addOnsTotal,
                'addOnsFeesTotal' => $addOnsFeesTotal,
                'customerName' => $customerName,
                'customerEmail' => $customerEmail,
                'customerPhone' => $customerPhone,
                'requestPermissions' => $requestPermissions
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * View and print the invoice of POS booking
     *
     * @param string $id
     * @param object $request
     *
     * @return mixed
     */
    public function sendReceipt(string $id, $request = [])
    {
        try {
            $customerEmail = ($request->has('email') && $request->email != '') ? $request->email : '';
            if ($customerEmail == '') {
                throw new Exception("Email is required.");
            }
            $tourBooking = TourBooking::where('group_id', $id)->first(['id', 'group_id']);
            if (!$tourBooking) {
                throw new Exception("Tour booking not found.");
            }
            if ($tourBooking->group_id != '') {
                $arEmailData = PosService::posOrderDetails($id, $customerEmail);
            }
            if ((Auth::check() && in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) || !Auth::check()) {
                Mail::send(new PosOrderReceipt($arEmailData, 'pos_order_receipt'));
            }

            return [
                'orderId' => $id
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * View and print the invoice of POS booking
     *
     * @param string $id
     * @return mixed
     */
    public function printReceipt(string $id, $request = [])
    {
        try {
            $tourBooking = TourBooking::where('group_id', $id)->first(['id', 'group_id']);
            if (!$tourBooking) {
                throw new Exception("Tour booking not found.");
            }
            if ($tourBooking->group_id != '') {
                $vars = PosService::posOrderDetails($id);
            }

            return $vars;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * POS Settings
     *
     * @return mixed
     */
    public function settings()
    {
        try {
            $todayDate = Carbon::today()->toDateString();

            $tourPackages = DB::table('tour_packages as tp')
                ->join('categories as c', 'c.id', '=', 'tp.category_id')
                ->leftJoin(DB::raw("(
                    SELECT tour_package_id, COUNT(id) as slotCount
                    FROM tour_slot_times
                    WHERE `date` = '{$todayDate}'
                    GROUP BY tour_package_id
                ) as slots"), 'slots.tour_package_id', '=', 'tp.id')
                ->select(
                    'tp.id as tourPackageId',
                    'tp.name as tourPackageName',
                    'tp.pos_order as posOrder',
                    'c.id as categoryId',
                    'c.name as categoryName',
                    DB::raw('COALESCE(slots.slotCount, 0) as slotCount')
                )
                ->where('tp.status', 'Active')
                ->where('tp.show_in_pos', 1)
                ->having('slotCount', '>', 0)
                ->orderBy('c.id')
                ->orderBy('tp.pos_order')
                ->get();

            $categories = $tourPackages->groupBy('categoryId')->map(function ($items, $categoryId) {
                return [
                    'categoryId'   => $categoryId,
                    'categoryName' => $items->first()->categoryName,
                    'tourPackages' => $items->map(fn($item) => collect($item)->except(['categoryName']))->values()
                ];
            })->values();

            return response()->json(['categories' => $categories]);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update POS Setting
     *
     * @param object $request
     * @return mixed
     */
    public function updateSettings(object $request)
    {
        DB::beginTransaction();
        try {
            $json = $request->json()->all();
            $tourPackages = $json['tourPackages'] ?? [];
            if ($tourPackages) {
                foreach ($tourPackages as $tourPackage) {
                    TourPackage::where('id', $tourPackage['tourPackageId'])
                        ->update(['pos_order' => $tourPackage['posOrder']]);
                }
                DB::commit();
            }
            return response()->json([]);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Cancel Order in POS
     *
     * @param string $id
     * @param object $request
     *
     * @return mixed
     */
    public function cancelOrder(string $id, $request = [])
    {
        DB::beginTransaction();
        try {
            $json = $request->json()->all();
            $bookings = $json['bookings'] ?? [];
            $userType = userType();
            if ($bookings) {
                $tourBookingFirst = $this->tourBooking::where('group_id', $id)->where('id', $bookings[0])->first();
                $createdUserInfoService = BookingService::createdUserInfo(true, $tourBookingFirst);
                $user = $createdUserInfoService['user'];
                $createdByWithName = $createdUserInfoService['created_by_with_name'];
                $updatedRequest = new Request([
                    'refund_type' => $json['refund_type'],
                    'is_cash' => $json['is_cash'],
                    'comment' => $json['comment'],
                    'fareharbor' => ""
                ]);
                foreach ($bookings as $bookingId) {
                    $tourBooking = $this->tourBooking::where('group_id', $id)->where('id', $bookingId)->first();
                    if ($tourBooking && $tourBooking->status != 'Cancelled') {
                        $transaction = $this->transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->first();
                        $tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->first();
                        $update = $tourBooking->update([
                            'status' => 'Cancelled',
                            'updated_by' => $user->id,
                            'updated_by_user_type' => $userType,
                        ]);

                        if ($update) {
                            if (!empty($transaction)) {
                                // Below line is for to perform cancel booking refund options
                                $cancelBookingRefund = TourBookingRepository::handleCancelRefundJobs($transaction, $bookingId, $tourBooking, $updatedRequest, $createdByWithName, $userType, $tourBookingTransaction);

                                $refundedAmount = (isset($cancelBookingRefund['refundedAmount'])) ? $cancelBookingRefund['refundedAmount'] : 0;

                                // Below line is for to update tour booking history table
                                TourBookingRepository::handleCancelRefundHistoryUpdateJobs($tourBooking, $cancelBookingRefund['historyId'], $updatedRequest, $cancelBookingRefund['via'], $cancelBookingRefund['transaction_id']);
                            } else {
                                $data = [
                                    'amount' => 0,
                                    'comment' => $json['comment'] ?? ''
                                ];

                                HistoryService::storeCancelledBookingInHistory($bookingId, $tourBooking->tourists, $data, $createdByWithName);

                                // Refund Custom Charge
                                CustomChargeBookingService::refundCustomChargeFromCancel($bookingId, $request);
                            }
                            DB::commit();
                        }
                    }
                }
                return response()->json(['bookings' => $bookings]);
            }
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Refund Order in POS
     *
     * @param string $id
     * @param object $request
     *
     * @return mixed
     */
    public function refundOrder(string $id, $request = [])
    {
        DB::beginTransaction();
        try {
            $json = $request->json()->all();
            $bookings = $json['bookings'] ?? [];
            $userType = userType();
            if ($bookings) {
                $tourBookingFirst = $this->tourBooking::where('group_id', $id)->where('id', $bookings[0])->first();
                $createdUserInfoService = BookingService::createdUserInfo(true, $tourBookingFirst);
                $user = $createdUserInfoService['user'];
                $createdByWithName = $createdUserInfoService['created_by_with_name'];
                $updatedRequest = new Request([
                    'refund_type' => $json['refund_type'],
                    'is_cash' => $json['is_cash'],
                    'comment' => $json['comment'],
                    'fareharbor' => ""
                ]);
                foreach ($bookings as $bookingId) {
                    $tourBooking = $this->tourBooking::where('group_id', $id)->where('id', $bookingId)->first();

                    $transaction = $this->transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->first();
                    $tourBookingTransaction = $this->bookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->first();

                    if (!empty($transaction)) {
                        // Below line is for to perform refund booking refund options

                        $bookingRefund = $this->handleRefundJobs($transaction, $bookingId, $tourBooking, $updatedRequest, $createdByWithName, $userType, $tourBookingTransaction);

                        $refundedAmount = (isset($bookingRefund['refundedAmount'])) ? $bookingRefund['refundedAmount'] : 0;

                        // Below line is for to update tour booking history table
                        $this->handleRefundHistoryUpdateJobs($tourBooking, $bookingRefund['historyId'], $updatedRequest, $bookingRefund['via'], $bookingRefund['transaction_id']);
                    } else {
                        $data = [
                            'amount' => $request->amount ?? 0,
                            'comment' => $request->comment ?? ''
                        ];

                        HistoryService::storeCancelledBookingInHistory($id, $tourBooking->tourists, $data, $createdByWithName);

                        // Refund Custom Charge
                        CustomChargeBookingService::refundCustomChargeFromCancel($id, $request);

                        DB::commit();
                    }
                }
                return response()->json(['bookings' => $bookings]);
            }
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Check manager request for order
     *
     * @param object $request
     * @return mixed
     */
    public function checkRequest($orderId, object $request)
    {
        DB::beginTransaction();
        try {
            $bookingIds = $request->bookings;
            $requestType = $request->request_type;

            $qryBookings = '';
            $i = 1; // to append AND in query

            foreach ($bookingIds as $key => $value) {
                $qryBookings .= 'FIND_IN_SET("' . $value . '" ,bookings)';
                if ($i < count($bookingIds)) {
                    $qryBookings .= ' OR '; // use OR as per use
                }
                $i++;
            }
            $qryBookings = '(' . $qryBookings . ')';
            $fieldType = $requestType . '_' . $request->refund_type;
            $requestExist = BookingRequest::where('order_id', $orderId)
                ->where('request_type', $requestType)
                ->where('staff_id', Auth::user()->id)
                ->where('staff_type', userType())
                ->whereJsonContains('details->field_type',  $fieldType)
                ->whereRaw($qryBookings)
                ->whereIn('status', ['Pending', 'Approved'])
                ->first();
            if (!empty($requestExist)) {
                return [
                    'status' => 'success',
                    'requestStatus' => $requestExist->status
                ];
            } else {
                return [
                    'status' => 'success',
                    'requestStatus' => ''
                ];
            }
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Request to the manager
     *
     * @param object $request
     * @return mixed
     */
    public function requestToManager(object $request)
    {
        DB::beginTransaction();
        try {
            $orderId = $request->order_id;
            $bookingIds = $request->bookings;
            $requestType = $request->request_type;

            $qryBookings = '';
            $i = 1; // to append AND in query

            foreach ($bookingIds as $key => $value) {
                $qryBookings .= 'FIND_IN_SET("' . $value . '" ,bookings)';
                if ($i < count($bookingIds)) {
                    $qryBookings .= ' OR '; // use OR as per use
                }
                $i++;
            }
            $qryBookings = '(' . $qryBookings . ')';
            $fieldType = $requestType . '_' . $request->refund_type;
            $requestExist = BookingRequest::where('order_id', $orderId)
                ->where('request_type', $requestType)
                ->where('staff_id', Auth::user()->id)
                ->where('staff_type', userType())
                ->where('manager_id', $request->manager_id)
                ->whereDate('created_at', Carbon::today())
                ->whereIn('status', ['Pending', 'Approved'])
                ->whereJsonContains('details->field_type',  $fieldType)
                ->whereRaw($qryBookings)
                ->first();
            if (!empty($requestExist) && $requestExist->status != 'Approved') {
                return sendJsonErrMsg("Request already has been sent to this manager.");
            }

            if (!empty($requestExist) && $requestExist->status == 'Approved') {
                return sendJsonErrMsg("Request has been approved. Please reload the page.");
            }
            $oldRequestCheck = BookingRequest::where('order_id', $orderId)
                ->where('request_type', $requestType)
                ->whereDate('created_at', Carbon::today())
                ->where('staff_id', Auth::user()->id)
                ->where('staff_type', userType())
                ->where('manager_id', $request->manager_id)
                ->where('status', 'Pending')
                ->first();


            $options = ManagerRequestUtils::getManagerRequestOptions($requestType, $request->all());
            $bookingRequest = new BookingRequest;
            $bookingRequest->order_id = $orderId;
            $bookingRequest->staff_id = Auth::user()->id;
            $bookingRequest->manager_id = $request->manager_id;
            $bookingRequest->bookings = implode(",", $bookingIds);
            $bookingRequest->details = json_encode($options, JSON_NUMERIC_CHECK);
            $bookingRequest->status = 'Pending';
            $bookingRequest->request_type = $requestType;
            $bookingRequest->request_from = Constants::POS;
            $bookingRequest->created_at = Carbon::now();
            $bookingRequest->staff_type = userType();
            $bookingRequest->cancelled_by = '';
            $bookingRequest->save();
            DB::commit();
            if (!empty($oldRequestCheck)) {
                $oldRequestCheck->status = "Expired";
                $oldRequestCheck->save();
            }
            DB::commit();

            $tenant = tourOperator();
            broadcast(new BookingRequestNotification($bookingRequest, $tenant));

            return [
                'message' => 'Manager request has been sent',
                'status' => 'success'
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Handle the queue jobs to perform the refund with different refund options for Cash/Invoiced/Wire Transfer
     *
     * @param  object  $tourBooking
     * @param  int     $historyId
     * @param  object   $request
     * @param  string  $refundVia
     * @param  int     $transactionId
     *
     * @return mixed
     */
    public function handleRefundJobs($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType, $tourBookingTransaction)
    {
        // Perform the jobs based on refund booking refund options
        if ($request['refund_type'] == 'dont_refund') {
            $cancelBookingRefund = DontRefundService::refund($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType);
        } elseif ($request['refund_type'] == 'full_refund') {
            $cancelBookingRefund = TicketRefundService::refund($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType);
        } elseif ($request['refund_type'] == '100_refund') {
            $cancelBookingRefund = WeatherRefundService::refund($transaction, $id, $tourBooking, $request, $created_by_with_name, $userType, $tourBookingTransaction);
        }
        return $cancelBookingRefund;
    }
    /**
     * Handle the queue jobs to perform the history data updates when refund in POS
     *
     * @param  object  $tourBooking
     * @param  int     $historyId
     * @param  object   $request
     * @param  string  $refundVia
     * @param  int     $transactionId
     *
     * @return void
     */
    public static function handleRefundHistoryUpdateJobs($tourBooking, $historyId, $request, $refundVia, $transactionId): void
    {
        // Perform the jobs based on categories
        switch ($request->refund_type) {
            case 'dont_refund':
                PosDontRefundService::run((int) $historyId, $refundVia, $transactionId);
                break;
            case 'full_refund':
                PosTicketRefundService::run($tourBooking, $historyId, $refundVia, $transactionId);
                break;
            case '100_refund':
                PosFullRefundService::run($tourBooking, $historyId, $refundVia, $transactionId);
                break;
        }
    }
}
