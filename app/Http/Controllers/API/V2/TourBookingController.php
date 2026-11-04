<?php

namespace App\Http\Controllers\API\V2;

use App\Constants\Constants;
use App\Http\Controllers\API\V2\BaseController;
use App\Models\TourBookingContact;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\BookingTransact;
use App\Models\TourPackage;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TourBooking\TourBookingAcknowledgement;
use App\Models\Hotel;
use App\Models\SurchargeDate;
use App\Models\TourBooking;
use App\Models\TourPromotion;
use App\Models\TourSlotTime;
use App\Models\Transaction;
use App\Services\Frontend\FrontendCardBookingService;
use App\Services\Frontend\TourBookingAddonService;
use App\Services\TimeSlot\TouristService;
use App\Services\Payments\PaymentModeResolver;
use App\Services\Payments\PaymentModeInference;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Illuminate\Support\Str;
use App\Repositories\TourBooking\TourBookingFormsRepository;
use App\Services\ThreeWayPayment\ThreeWayPaymentService;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use App\Services\TimeSlot\TimeSlotService;
use Illuminate\Support\Facades\Cache;
use App\Services\Booking\HistoryService;
use App\Services\Booking\CheckoutTermsService;
use App\Utils\IpGeolocationUtils;
use App\Utils\StripeUtils;

class TourBookingController extends BaseController
{
    protected $tourBookingFormsRepository;

    /**
     * @param  TourBookingFormsRepository  $tourBookingFormsRepository
     */
    public function __construct(TourBookingFormsRepository $tourBookingFormsRepository)
    {
        $this->tourBookingFormsRepository = $tourBookingFormsRepository;
    }

    /**
     * Check the available seats before booking initialize
     *
     * @param Request $request

     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function availableSeats(Request $request)
    {
        $packageId = (int) $request->package_id;
        $affiliateId = (int) $request->affiliate_id;
        $tourSlotId = (int) $request->tour_slot_id;
        $infants = (int) $request->infants;

        $timeSlot = TimeSlotService::getTimeSlot($tourSlotId);
        $year = Carbon::parse($timeSlot->date)->format('Y');

        $fields = [
            'tst.id',
            'tsd.tour_package_id',
            'tsd.date',
            'tst.time_from',
            'tst.time_to',
            'tst.time_slot_seats as seats',
            'tst.bookable_status',
            DB::raw("COALESCE((SELECT SUM(tbm.tourists) FROM tour_bookings_master AS tbm WHERE tbm.tour_slot_id = tst.id AND tbm.status != 'Cancelled' AND tbm.tour_package_id = $packageId GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
            DB::raw("COALESCE((SELECT DISTINCT tpf.is_parent FROM tour_package_families AS tpf WHERE tpf.tour_package_id = tsd.tour_package_id AND tst.time_from = tpf.time_from AND tpf.relation_year = $year AND tpf.tour_package_id = $packageId), 0) AS is_parent"),
            DB::raw("COALESCE((SELECT DISTINCT tpf.group_index FROM tour_package_families AS tpf WHERE tpf.tour_package_id = tsd.tour_package_id AND tst.time_from = tpf.time_from AND tpf.relation_year = $year AND tpf.tour_package_id = $packageId), 0) AS group_index"),
        ];

        if ($affiliateId) {
            $fields[] = DB::raw("COALESCE((SELECT SUM(tbm.tourists) FROM tour_bookings_master AS tbm WHERE tbm.tour_slot_id = tst.id AND tbm.status != 'Cancelled' AND tbm.tour_package_id = $packageId AND tbm.customer_id = $affiliateId GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS afl_tourists");
        }

        $slotTimes = DB::table('tour_slot_times AS tst')
            ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
            ->where(['tst.id' => $tourSlotId, 'tsd.tour_package_id' => $packageId, 'tsd.status' => 'Active', 'tst.bookable_status' => 'Open', 'tst.deleted_by' => '0'])
            ->first($fields);

        if (!$slotTimes) {
            return $this->seatErrorResponse($packageId);
        }

        $totalTourists = 0;
        if (hasSetting('PACKAGE_RELATION')) {
            $tourPackageFamily = DB::table('tour_package_families')
                ->where('relation_year', $year)
                ->whereIn('parent_package_id', function ($query) use ($packageId, $year) {
                    $query->select('parent_package_id')
                        ->from('tour_package_families')
                        ->where('tour_package_id', $packageId)
                        ->where('relation_year', $year);
                })->get();

            if ($tourPackageFamily->isNotEmpty()) {
                $relationPackage = $tourPackageFamily->filter(fn($obj) => $obj->tour_package_id == $slotTimes->tour_package_id && $obj->group_index == $slotTimes->group_index);
                $totalTourists = TouristService::countTotalTourists($tourPackageFamily, $slotTimes);
            }
        }

        $availableSeats = $this->calculateAvailableSeats($request, $slotTimes, $totalTourists, $relationPackage ?? []);

        $totalRequestedSeats = collect($request->tourists)->sum() - $infants;
        if ($availableSeats < $totalRequestedSeats) {
            return response()->json(["success" => 'false', 'message' => "Available Seat $availableSeats"], 200);
        }

        return response()->json(["success" => 'true', 'availableSeats' => $availableSeats], 200);
    }

    /**
     * Send seat error response
     * 
     * @param mixed $packageId
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    private function seatErrorResponse($packageId)
    {
        $tourPackage = TourPackage::find($packageId);
        $message = $tourPackage->package_has_slots ? 'There was a recent closure of this slot, please choose another.' : 'Available Seat 0.';
        return response()->json(["success" => 'false', 'message' => $message], 200);
    }

    /**
     * Calculate the available seats
     * 
     * @param mixed $request
     * @param mixed $slotTimes
     * @param mixed $totalTourists
     * @param mixed $relationPackage
     * @return int
     */
    private function calculateAvailableSeats($request, $slotTimes, $totalTourists, $relationPackage)
    {
        $seats = $slotTimes->seats;
        $tourists = ($totalTourists > 0 && !empty($relationPackage)) ? $totalTourists : (int) $slotTimes->tourists;

        if ($request->affiliate_id == 0) {
            return max($seats - $tourists, 0);
        }

        $desiredSlot = DB::table('tour_slot_times_affiliate as tsta')
            ->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
            ->where(['ast.affiliate_id' => $request->affiliate_id, 'tsta.tour_package_id' => $request->package_id, 'tsta.time_from' => $slotTimes->time_from, 'tsta.status' => 'Active', 'tsta.deleted_by' => 0])
            ->select('ast.seat_limit')
            ->first();

        $affiliateTourists = (int) $slotTimes->afl_tourists;
        $affiliateSeats = isset($desiredSlot->seat_limit) ? min($slotTimes->seats, $desiredSlot->seat_limit) : 0;

        if ($totalTourists >= $slotTimes->seats || $affiliateTourists >= $affiliateSeats) {
            return 0;
        }

        return min($slotTimes->seats - $totalTourists, $affiliateSeats - $affiliateTourists);
    }

    /**
     * Check the available seats before booking initialize
     *
     * @param Request $request
     */
    public function bulkCheckAvailableSeats(Request $request)
    {
        if ($request->items) {
            $dataArray = [];
            foreach ($request->items as $slotId => $itemData) {
                $year = Carbon::parse($itemData['date'])->format('Y');
                $fields = [
                    'tst.id',
                    'tst.tour_package_id',
                    'tst.date',
                    'tst.time_from',
                    'tst.time_to',
                    'tst.time_slot_seats as seats',
                    'tst.bookable_status',
                    DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                            AND tbm.tour_package_id = " . $itemData['package_id'] . "
                            GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS tourists"),
                    DB::raw("IFNULL((SELECT DISTINCT(tpf.is_parent)
                        FROM tour_package_families AS tpf
                        WHERE tpf.tour_package_id = tst.tour_package_id
                            AND tst.time_from = tpf.time_from
                            AND tpf.relation_year = $year
                            AND tpf.tour_package_id = " . $itemData['package_id'] . "), 0) AS is_parent"),
                    DB::raw("IFNULL((SELECT DISTINCT(tpf.group_index)
                        FROM tour_package_families AS tpf
                        WHERE tpf.tour_package_id = tst.tour_package_id
                            AND tst.time_from = tpf.time_from
                            AND tpf.relation_year = $year
                            AND tpf.tour_package_id = " . $itemData['package_id'] . "), 0) AS group_index"),
                ];

                if ($itemData['affiliate_id']) {
                    $fields[] = DB::raw("IFNULL((SELECT SUM(tbm.tourists)
                        FROM tour_bookings_master AS tbm
                        WHERE tbm.tour_slot_id = tst.id
                            AND tbm.status != 'Cancelled'
                            AND tbm.tour_package_id = " . $itemData['package_id'] . "
                            AND tbm.customer_id = " . $itemData['affiliate_id'] . "
                            GROUP BY tbm.tour_slot_id, tbm.tour_package_id), 0) AS afl_tourists");
                }

                $slotTimes = DB::table('tour_slot_times AS tst')
                    ->where('tst.id', $itemData['tour_slot_id'])
                    ->where('tst.tour_package_id', $itemData['package_id'])
                    ->where('tst.bookable_status', 'Open')
                    ->where('tst.deleted_by', '0')
                    ->first($fields);

                $relationPackage = [];
                $totalTourists = 0;
                if ($slotTimes) {
                    /* START - mapping tour package and reducing the relational seats */
                    if (hasSetting('PACKAGE_RELATION')) {
                        $tourPackageFamily = DB::select("SELECT *
                            FROM tour_package_families
                            WHERE relation_year = $year
                            AND parent_package_id IN (
                                SELECT parent_package_id
                                FROM tour_package_families
                                WHERE tour_package_id = " . $itemData['package_id'] . "
                                    AND relation_year = $year)");

                        if (count($tourPackageFamily)) {
                            $relationPackage = array_filter($tourPackageFamily, fn($obj) => $obj->tour_package_id == $slotTimes->tour_package_id && $obj->group_index == $slotTimes->group_index);

                            $totalTourists = TouristService::countTotalTourists($tourPackageFamily, $slotTimes);
                        }
                    }
                    /* END */

                    if ($itemData['affiliate_id'] == 0) {
                        $seats = $slotTimes->seats;
                        $tourists = ((int) $totalTourists > 0 && !empty($relationPackage)) ? $totalTourists : $slotTimes->tourists;
                        $availableSeats = $seats - $tourists;
                    } else {
                        $desiredSlot = DB::table('tour_slot_times_affiliate as tsta')
                            ->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
                            ->where('ast.affiliate_id', $itemData['affiliate_id'])
                            ->where('tsta.tour_package_id', $itemData['package_id'])
                            ->where('tsta.time_from', $slotTimes->time_from)
                            ->where('tsta.status', 'Active')
                            ->where('tsta.deleted_by', 0)
                            ->select('ast.seat_limit')
                            ->first();

                        // Actual seats calculation
                        $bookedSeatsPerSlot = ((int) $totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $slotTimes->tourists;
                        $totalSeatsPerSlot = $slotTimes->seats;

                        // Affiliate seats calculation
                        $tourists = (int) $slotTimes->afl_tourists;
                        $seats = isset($desiredSlot->seat_limit) ? (($slotTimes->seats > 0) ? (($slotTimes->seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($slotTimes->seats)) : 0) : 0;

                        if ($bookedSeatsPerSlot >= $totalSeatsPerSlot || $tourists >= $seats) {
                            $availableSeats = 0;
                        } else {
                            $globalTotal = $totalSeatsPerSlot - $bookedSeatsPerSlot;
                            $affliateTotal = $seats - $tourists;
                            $vacant = min($globalTotal, $affliateTotal);
                            $availableSeats = ($vacant >= $seats) ? $seats : $vacant;
                        }
                    }

                    $totalTourist = 0;
                    $infants = $itemData['infant_count'] ?? 0;
                    if (isset($itemData['people_group']) && array_sum($itemData['people_group']) > 0) {
                        foreach ($itemData['people_group'] as $value) {
                            $totalTourist += $value;
                        }
                        $totalTourist -= $infants;
                    }

                    if ($availableSeats <= 0) {
                        $tourPackage = TourPackage::find($itemData['package_id']);
                        $message = $tourPackage->package_has_slots ? 'There was a recent closure of this slot, please choose another.' : 'Available Seat 0.';

                        $data = [
                            "success" => 'false',
                            "message" => $message,
                        ];

                        $dataArray[$slotId] = $data;
                    }

                    if ($availableSeats < $totalTourist) {
                        $data = [
                            "success" => 'false',
                            "message" => "Available Seat $availableSeats",
                        ];

                        $dataArray[$slotId] = $data;
                    }
                } else {
                    $tourPackage = TourPackage::find($itemData['package_id']);
                    $message = $tourPackage->package_has_slots ? 'There was a recent closure of this slot, please choose another.' : 'Available Seat 0.';

                    $data = [
                        "success" => 'false',
                        "message" => $message,
                    ];

                    $dataArray[$slotId] = $data;
                }
            }

            return response()->json($dataArray, 200);
        }
    }

    /**
     * Booking API to create the new booking
     *
     * @param Request $request
     */
    public function packageBookingTour(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'name' => 'required|string|min:1|max:80',
            'phone_number' => 'required',
            'email' => 'required|email',
            'payment_intent_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors()->toArray());
        } else {
            try {
                $data = [];
                $i = 0;
                $description = null;
                $tourTotal = 0;
                $lineBreak = chr(0x0D) . chr(0x0A) . ' ' . chr(0x0D) . chr(0x0A);
                $bookingIds = [];
                $gratuity = [];
                $affiliateId = null;
                $cartId = Str::random(10) . time();

                /* START: Booking already created using backend process due to network issues */
                $existingTransactions = Transaction::where('transaction_number', $request->payment_intent_id)
                    ->where('status', 'Completed')
                    ->pluck('tour_booking_id');

                if ($existingTransactions->isNotEmpty()) {
                    $bookingIds = $existingTransactions->toArray();

                    return response()->json([
                        'bookingIds' => $bookingIds,
                        'success' => true,
                        'message' => 'Your tour bookings are successfully confirmed.'
                    ], 200);
                }

                $existingBookings = TourBooking::where('stripe_paymentintent_id', $request->payment_intent_id)
                    ->whereIn('status', ['Booked', 'Cancelled'])
                    ->pluck('id');

                if ($existingBookings->isNotEmpty()) {
                    $bookingIds = $existingBookings->toArray();

                    return response()->json([
                        'bookingIds' => $bookingIds,
                        'success' => true,
                        'message' => 'Your tour bookings are successfully confirmed.'
                    ], 200);
                }
                /* END: Booking already created using backend process due to network issues */

                // NOTE: the advance-booking cutoff (package minimum-notice window) is
                // intentionally NOT re-checked here. By this point payment_intent_id is
                // already confirmed and the card has been charged, so rejecting booking
                // creation because the slot has since crossed its cutoff would strand a paid
                // customer with no booking. That check belongs only in createPaymentIntent(),
                // before any money moves.
                //
                // $request->items arrives keyed by tour_slot_id, and PHP/JS both enumerate
                // integer-like keys in ascending numeric order regardless of insertion order
                // - so without this, the item with the lowest tour_slot_id always gets
                // inserted first (lowest tour_bookings_master.id), which is what
                // BookingService::comboBookingsData() treats as the combo's parent booking.
                // Sorting by the cids order the customer actually saw preserves keys (still
                // tour_slot_id => item) while making insertion order match cids order, so the
                // intended package ends up parent.
                $items = $request->items;
                if (!empty($request->combo_ids)) {
                    $comboOrder = array_flip(array_map('intval', explode(',', $request->combo_ids)));
                    $items = collect($items)
                        ->sortBy(fn($item) => $comboOrder[$item['package_id']] ?? PHP_INT_MAX)
                        ->all();
                }

                foreach ($items as $slotId => $item) {
                    $package = TourPackage::where('id', $item['package_id'])->first();

                    if ($item['affiliate_id']) {
                        $affiliateId = $item['affiliate_id'];
                        $customer = Customer::find($item['affiliate_id']);
                        if (empty($customer)) {
                            $customer = Customer::where('email', $request->email)->first();
                        }
                    } else {
                        $customer = Customer::where('email', $request->email)->first();
                    }

                    // Update IP and location for existing customers if not already set
                    if ($customer && (!$customer->ip_address || !$customer->country)) {
                        $customer->update(['ip_address' => $request->getClientIp()]);
                        IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, $request->getClientIp());
                    }

                    $promoId = null;
                    $promoCode = '';
                    if ($item['tour_promotion_id'] > 0) {
                        $promoId = $item['tour_promotion_id'];
                        $promoDetail = TourPromotion::where('id', $promoId)->first();
                        $promoCode = $promoDetail ? $promoDetail->code : '';
                    }

                    if (empty($customer)) {
                        $customer = new Customer;
                        $customer->tour_operator_id = $package->tour_operator_id;
                        $customer->name = $request->name;
                        $customer->phone_number = $request->phone_number;
                        $customer->email = $request->email;
                        $customer->is_subscribed = $request->getemailupdates ? 'Yes' : 'No';
                        $customer->ip_address = $request->getClientIp(); // Capture IP address for guest customers
                        $customer->save();

                        // Get and store location data for the new guest customer
                        if ($customer->id && $customer->ip_address) {
                            IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, $customer->ip_address);
                        }
                    }

                    if ($customer) {
                        $infants = $item['infant_count'] ?? 0;
                        $bookingMaster = new TourBooking;
                        $bookingMaster->tour_package_id = $item['package_id'];
                        $bookingMaster->tour_promotion_id = $promoId ?? 0;
                        $bookingMaster->tour_slot_id = $slotId;
                        $bookingMaster->customer_id = $customer->id;
                        $bookingMaster->hotel_id = $item['hotel_id'];
                        $bookingMaster->cart_id = count($request->items) > 1 ? $cartId : null;
                        $bookingMaster->name = $request->name;
                        $bookingMaster->phone_number = $request->phone_number;
                        $bookingMaster->email = $request->email;
                        $bookingMaster->tourists = array_sum($item['people_group']) - $infants;
                        $bookingMaster->infants = $infants;
                        $bookingMaster->comments = $request->comment ?? "";
                        $bookingMaster->discount2_value = $item['discount2_value'];
                        $bookingMaster->discount2_percentage = $item['discount2_percentage'];
                        $bookingMaster->discount_promo_code = $promoCode;
                        $bookingMaster->service_commission = $item['fees'];
                        $bookingMaster->total = $item['total'];
                        $bookingMaster->booking_total = $item['total'];
                        $bookingMaster->stripe_paymentintent_id = $request->payment_intent_id;
                        $bookingMaster->created_by = $customer->id;
                        $bookingMaster->created_by_user_type = 'Customer';
                        $bookingMaster->status = 'Cancelled';
                        $bookingMaster->is_group_booking = $package->is_group_rate_enabled;
                        $bookingMaster->is_partner_booking = $package->is_partner_package;
                        $bookingMaster->visitor = $request->getClientIp();
                        $bookingMaster->request_from = Constants::FRONTEND;
                        $bookingMaster->booking_source = 'online';

                        if ($bookingMaster->save()) {
                            $bookingData = $this->bookingData($bookingMaster->id);
                            $isBlackoutDate = isBlackoutDateEnabled($bookingData->Date);
                            if (getNextYearCommissionPercentage($bookingData->Date)) {
                                if ($affiliateId) {
                                    if ($isBlackoutDate) {
                                        $normalCommission = getNormalCommissionPercentage($package->id);
                                        $customer->affiliate_processing_percentage = $normalCommission['service_commission_percentage'];
                                        $customer->stripe_affiliate_percentage = $normalCommission['stripe_commission_percentage'];
                                    } else {
                                        $affiliateCommission = getAffiliateCommissionPercentage($customer->id);
                                        $customer->affiliate_processing_percentage = $affiliateCommission['affiliate_processing_percentage'];
                                        $customer->stripe_affiliate_percentage = $affiliateCommission['stripe_affiliate_percentage'];
                                    }
                                } else {
                                    $normalCommission = getNormalCommissionPercentage($package->id);
                                    $package->service_commission_percentage = $normalCommission['service_commission_percentage'];
                                    $package->stripe_commission_percentage = $normalCommission['stripe_commission_percentage'];
                                }
                            } else {
                                if ($affiliateId && $isBlackoutDate) {
                                    $customer->affiliate_processing_percentage = $package->service_commission_percentage;
                                    $customer->stripe_affiliate_percentage = $package->stripe_commission_percentage;
                                }
                            }

                            /* START: Custom Date Rate Validation */
                            $affiliateIdForRate = (!$isBlackoutDate) ? $affiliateId : NULL;
                            $standardRateDate = SurchargeDate::where('affiliate_id', $affiliateIdForRate)
                                ->where('tour_package_id', $bookingData->tour_package_id)
                                ->where('date_range_start', '<=', $bookingData->Date)
                                ->where('date_range_end', '>=', $bookingData->Date)
                                ->orderBy('priority', 'DESC')
                                ->first();

                            if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
                                $customRateDate = isCustomDateRateEnabled($bookingData->Date, $bookingData->tour_package_id, $affiliateIdForRate, $slotId);
                            } else {
                                $customRateDate = isCustomDateRateEnabled($bookingData->Date, $bookingData->tour_package_id, $affiliateIdForRate);
                            }
                            /* END */

                            $rates = DB::table('tour_package_rates as pkg_rate')
                                ->where([
                                    'pkg_rate.tour_package_id' => $item['package_id'],
                                    'pkg_rate.deleted_by' => 0,
                                    'pkg_rate.status' => 'Active'
                                ])
                                ->when($item['affiliate_id'] != 0 && !$isBlackoutDate, fn($query) => $query->where('pkg_rate.affiliate_id', $item['affiliate_id']), fn($query) => $query->whereNull('pkg_rate.affiliate_id'))
                                ->when($customRateDate, fn($query) => $query->where('pkg_rate.custom_date_id', $customRateDate), fn($query) => $query->whereNull('pkg_rate.custom_date_id'))
                                ->when($item['selectedRateId'], fn($query) => $query->where('pkg_rate.id', $item['selectedRateId']))
                                ->where('pkg_rate.scheduling_year', '=', date('Y', strtotime($bookingData->Date)))
                                ->orderBy('pkg_rate.orderby')
                                ->get();

                            $subTotal = 0;
                            $applicationFeeAmount = 0;
                            foreach ($rates as $key => $rateItem) {
                                if (isset($item['people_group'][$key]) && $item['people_group'][$key] > 0) {
                                    $isInfantRate = $rateItem->is_infant_rate;
                                    $transact = new BookingTransact;
                                    $transact->tour_booking_id = $bookingMaster->id;
                                    $transact->tour_package_rate_id = $rateItem->id;
                                    if ($isInfantRate == 1) {
                                        $transact->infants = $item['people_group'][$key] ?? 0;
                                    } else {
                                        $transact->tourists = $item['people_group'][$key] ?? 0;
                                    }
                                    $transact->rate = $rateItem->rate;
                                    $transact->additional_charge = $rateItem->additional_charge;
                                    $transact->permit_fee = $rateItem->permit_fee;
                                    $transact->partner_fees_type = $package->is_partner_package ? $rateItem->partner_fee_type : null;
                                    $transact->partner_fees_percentage = $package->is_partner_package ? $rateItem->partner_fee_percentage : 0;
                                    $transact->partner_fees_amount = $package->is_partner_package ? $rateItem->partner_fee_amount : 0;
                                    $transact->total = $item['calculation'][$key] ?? 0;
                                    $transact->nat_fees_percentage =  $item['affiliate_id'] ? $customer->affiliate_processing_percentage : $package->service_commission_percentage;
                                    $transact->stripe_fees_percentage = $item['affiliate_id'] ? $customer->stripe_affiliate_percentage : $package->stripe_commission_percentage;
                                    $transact->is_group_rate = $rateItem->is_group_rate;
                                    $transact->is_infant_rate = $isInfantRate;
                                    $transact->created_by_user_type = 'Customer';
                                    $transact->created_by = $customer->id;
                                    $transact->save();

                                    $paxRate = $transact->rate + $transact->additional_charge + $transact->permit_fee + $transact->partner_fees_amount;
                                    $serviceCommission = roundout($paxRate * $transact->nat_fees_percentage / 100, 2);
                                    if ($item['discount2_value'] == 0) {
                                        $applicationFeeAmount += $transact->tourists * $serviceCommission;
                                    } else {
                                        $subTotal += $transact->tourists * $paxRate;
                                    }

                                    if ($package->has_contacts && isset($item['paxDetails'][$rateItem->id]) && count($item['paxDetails'][$rateItem->id]) > 0) {
                                        foreach ($item['paxDetails'][$rateItem->id] as $contact) {
                                            $bookingContact = new TourBookingContact();
                                            $bookingContact->tour_booking_id = $bookingMaster->id;
                                            $bookingContact->tour_booking_transact_id = $transact->id;
                                            $bookingContact->name = $contact['name'];
                                            $bookingContact->age = $contact['age'];
                                            $bookingContact->weight = $contact['weight'];
                                            $bookingContact->note = $contact['note'] ?? null;
                                            $bookingContact->save();
                                        }
                                    }
                                }
                            }

                            if ($item['discount2_value'] != 0) {
                                $applicationFeeAmount = roundout(($subTotal - $item['discount2_value']) * $transact->nat_fees_percentage / 100, 2);
                            }

                            $tourTotal += $bookingData->total;

                            //save gratuity if any from prop: $item["gratuity"]
                            if (isset($item['gratuity_id']) && $item['gratuity_id'] > 0 && isset($item['gratuity_price']) && $item['gratuity_price'] > 0) {
                                $gratuity[$bookingMaster->id] = $this->prepareGratuityData($item, $bookingMaster, $customer, $request);
                            }

                            //save custom form fields if any from prop: $item["custom_fields"]
                            if (isset($item['custom_fields']) && !empty($item['custom_fields'])) {
                                // Build map of tour_package_rate_id => tour_bookings_transact.id
                                $rateToTransactMap = BookingTransact::where('tour_booking_id', $bookingMaster->id)
                                    ->pluck('id', 'tour_package_rate_id')
                                    ->toArray();

                                TourBookingAddonService::saveFormAddons($item['custom_fields'], $bookingMaster->id, $rateToTransactMap);
                            }
                            // $this->tourBookingFormsRepository->saveBookingForm($item, $bookingMaster->id);
                        }
                    }

                    if ($i > 0) {
                        $description .= $lineBreak;
                    }

                    $description .= 'Booking Id: ' . $bookingData->TourBookingID .
                        "\nTour Package: " . $bookingData->TourPkgName .
                        "\nTour Date: " . $bookingData->Date .
                        "\nTour Time: " . $bookingData->StartTime .
                        "\nTourists: " . $bookingData->tourists + $bookingData->infants .
                        "\nEmail: " . $bookingData->Email;

                    $data[] = [
                        'BookingId' => $bookingData->TourBookingID,
                    ];

                    $bookingIds[] = $bookingData->TourBookingID;

                    $i++;
                }

                try {
                    Stripe::setApiKey(getStripeSecretKey());

                    if ($customer->stripe_id == null) {
                        $customer->stripe_id = $request->stripe_customer_id;
                        $customer->save();
                    }

                    $stripeMetadata = [
                        'description' => StripeUtils::limitDescription($description),
                        'metadata' => [
                            'customer_name' => $bookingData->Name,
                            'customer_phone' => $bookingData->ContactNumber,
                            'email' => $bookingData->Email,
                            'bookings' => json_encode($data),
                            'from' => Constants::ONLINE,
                            'tenant_id' => tenant()->id,
                        ]
                    ];

                    // Use the existing paymentIntentId from the request
                    $paymentIntentId = $request->payment_intent_id;

                    // Update PaymentIntent attributes
                    $updateParams = $stripeMetadata;
                    app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

                    $retrieveOptions = ['expand' => ['charges.data.transfer']];
                    $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

                    if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
                        $chargeParams = $stripeMetadata;
                        app(PaymentGatewayInterface::class)->updateConnectedPayment(
                            $paymentIntent->charges->data[0]->transfer->destination_payment,
                            $chargeParams,
                            $paymentIntent->charges->data[0]->transfer->destination
                        );
                    }

                    // Confirm PaymentIntent if it requires action
                    if ($paymentIntent->status == 'requires_action' || $paymentIntent->status == 'requires_source_action') {
                        $confirmParams = [
                            'payment_method_options' => [
                                'card' => [
                                    'request_three_d_secure' => 'any'
                                ]
                            ]
                        ];
                        $paymentIntent = app(PaymentGatewayInterface::class)->confirmPayment($paymentIntentId, $confirmParams);

                        return [
                            'bookingIds' => $bookingIds,
                            'affiliateId' => $affiliateId,
                            'clientSecret' => $paymentIntent->client_secret ?? '',
                            'intentId' => $paymentIntent->id ?? null,
                            'nextAction' => $paymentIntent->next_action ?? null,
                        ];
                    }
                } catch (CardException $exception) {
                    Log::error($exception);

                    foreach ($bookingIds as $key => $bookingId) {
                        DB::table('tour_booking_history')
                            ->insert([
                                'tour_booking_id' => $bookingId,
                                'operation' => 'Card Error',
                                'details' => $exception->getMessage(),
                                'operation_at' => Carbon::now(),
                                'operation_by' => $bookingMaster->customer_id,
                                'operation_by_user_type' => 'Customer',
                                'updated_at' => Carbon::now(),
                                'created_at' => Carbon::now(),
                            ]);

                        DB::table('transactions')
                            ->insert([
                                'tour_booking_id' => $bookingId,
                                'amount' => $tourTotal,
                                'transaction_number' => null,
                                'via' => 'Card',
                                'response_init' => '',
                                'response_end' => '',
                                'response_end_datetime' => Carbon::now(),
                                'status' => 'Failed',
                                'visitor' => $request->getClientIp(),
                                'request_from' => Constants::FRONTEND,
                                'created_by' => '',
                                'created_by_user_type' => 'Customer',
                                'created_at' => Carbon::now(),
                            ]);

                        TourBooking::where('id', $bookingId)->update(['status' => 'Cancelled']);
                    }

                    return $this->sendError($exception->getError()->message);
                } catch (Exception $exception) {
                    Log::error($exception);

                    foreach ($bookingIds as $key => $bookingId) {
                        DB::table('tour_booking_history')
                            ->insert([
                                'tour_booking_id' => $bookingId,
                                'operation' => 'Error',
                                'details' => $exception->getMessage(),
                                'operation_at' => Carbon::now(),
                                'operation_by' => $bookingMaster->customer_id,
                                'operation_by_user_type' => 'Customer',
                                'updated_at' => Carbon::now(),
                                'created_at' => Carbon::now(),
                            ]);

                        DB::table('transactions')
                            ->insert([
                                'tour_booking_id' => $bookingId,
                                'amount' => $tourTotal,
                                'transaction_number' => null,
                                'via' => 'Card',
                                'response_init' => '',
                                'response_end' => '',
                                'response_end_datetime' => Carbon::now(),
                                'status' => 'Failed',
                                'visitor' => $request->getClientIp(),
                                'request_from' => Constants::FRONTEND,
                                'created_by' => '',
                                'created_by_user_type' => 'Customer',
                                'created_at' => Carbon::now(),
                            ]);

                        TourBooking::where('id', $bookingId)->update(['status' => 'Cancelled']);
                    }

                    return $this->sendError($exception->getMessage());
                }

                $cardLast4Digit = $cardType = '';
                if (!empty($paymentIntent->charges->data)) {
                    $charge = $paymentIntent->charges->data[0]; // Get the first charge

                    // Check if payment was made using LINK (Saved Payment Method)
                    if ($charge->payment_method_details->type === 'card') {
                        $cardLast4Digit = $charge->payment_method_details->card->last4 ?? '';
                        $cardType = $charge->payment_method_details->card->brand ?? '';
                    } elseif ($charge->payment_method_details->type === 'link') {
                        // If using Link, try to fetch the details differently
                        $cardLast4Digit = $charge->payment_method_details->link->last4 ?? 'Link';
                        $cardType = 'Link (Saved Payment)';
                    }
                }

                $comboPaymentHistIds = [];
                foreach ($bookingIds as $id) {
                    $bookingData = $this->bookingData($id);

                    TourBooking::where('id', $id)->update(['status' => 'Booked']);
                    TimeSlotService::closeSlotOnBooking($bookingData->tour_package_id, $bookingData->tour_slot_time_id);

                    // Save checkout term acknowledgments
                    if ($request->has('custom_term_acknowledgments')) {
                        CheckoutTermsService::saveCheckoutTerms($id, $request->custom_term_acknowledgments);
                    }

                    $tbTransaction = BookingTransact::with('packageRate')->where('tour_booking_id', $id)->get();
                    $addedPeoplesText = [];
                    $partnerFeesAmount = 0;
                    if ($tbTransaction->count()) {
                        foreach ($tbTransaction as $tblTransaction) {
                            $addedPeoplesText[] = $bookingData->is_group_rate_enabled ? "Person(s) {$tblTransaction->tourists}" : $tblTransaction->packageRate->rate_for . " person " . ($tblTransaction->infants > 0 ? $tblTransaction->infants : $tblTransaction->tourists);
                            $partnerFeesAmount += $tblTransaction->tourists * $tblTransaction->partner_fees_amount;
                        }
                    }

                    DB::table('tour_booking_history')
                        ->insert([
                            'tour_booking_id' => $id,
                            'tourists' => $bookingData->tourists + $bookingData->infants,
                            'operation' => 'Added',
                            'details' => json_encode([
                                'sub_operation' => 'Tourists Added',
                                'discount_percentage' => 0,
                                'discount2_value' => 0,
                                'added' => $addedPeoplesText,
                                'newTourists' => $bookingData->tourists + $bookingData->infants,
                                'created_by_with_name' => $bookingData->customer_name
                            ], JSON_NUMERIC_CHECK),
                            'operation_at' => Carbon::now(), // laravel doesn't seems to honouring timezone given in config > app.php!
                            'operation_by' => $bookingData->customer_id,
                            'operation_by_user_type' => 'Customer',
                            'updated_at' => Carbon::now(),
                            'created_at' => Carbon::now()
                        ]);

                    $transactionId = DB::table('transactions')
                        ->insertGetId([
                            'tour_booking_id' => $id,
                            'amount' => $bookingData->total,
                            'fee_amount' => $bookingData->service_commission,
                            'transaction_number' => $paymentIntent->id,
                            'application_fee_id' => $paymentIntent->charges->data[0]->application_fee,
                            'via' => 'Card',
                            'payment_mode' => PaymentModeInference::fromStripeObject($paymentIntent),
                            'response_init' => '',
                            'response_end' => '',
                            'response_end_datetime' => Carbon::now(),
                            'status' => 'Completed',
                            'visitor' => $request->getClientIp(),
                            'request_from' => Constants::FRONTEND,
                            'created_by' => $bookingData->customer_id,
                            'created_by_user_type' => 'Customer',
                            'created_at' => Carbon::now(),
                        ]);

                    $historyId = DB::table('tour_booking_history')
                        ->insertGetId([
                            'tour_booking_id' => $id,
                            'tourists' => $bookingData->tourists + $bookingData->infants,
                            'operation' => 'Payment',
                            'details' => json_encode(['amount' => $bookingData->total, 'payment_type' => 'Card', 'card_number' => $cardLast4Digit, 'card_type' => $cardType], JSON_NUMERIC_CHECK),
                            'operation_at' => Carbon::now(),
                            'operation_by' => $bookingData->customer_id,
                            'operation_by_user_type' => 'Customer',
                            'updated_at' => Carbon::now(),
                            'created_at' => Carbon::now(),
                        ]);

                    $comboPaymentHistIds[] = $historyId;

                    if ($bookingData->is_partner_booking == 1) {
                        $payoutData = [
                            'amount' => $bookingData->total,
                            'platform_fees' => $bookingData->service_commission,
                            'partner_fees' => $partnerFeesAmount,
                            'booking_id' => $id,
                            'transaction_id' => $transactionId,
                            'package_id' => $bookingData->tour_package_id,
                            'partner_destination_id' => $bookingData->stripe_destination_id,
                            'payment_method' => 'Card',
                            'transfer_group' => $paymentIntent->id,
                        ];

                        ThreeWayPaymentService::transferToPartner($payoutData);
                    }


                    // Mark addons as paid
                    TourBookingAddonService::markAddonsAsPaid($id, $paymentIntent);

                    // Save the card details
                    HistoryService::getSavedCardFromPaymentIntent($paymentIntentId, $id, "FRONTEND");

                    if (count($gratuity) && array_key_exists($id, $gratuity)) {
                        $gratuityData = $gratuity[$id];
                        $gratuityData['gratuities']['transaction_number'] = $paymentIntent->id;
                        $gratuityData['gratuities']['application_fee_id'] = $paymentIntent->charges->data[0]->application_fee;
                        DB::table('gratuities')->insert($gratuityData['gratuities']);
                        $gratuityData['transactions']['transaction_number'] = $paymentIntent->id;
                        $gratuityData['transactions']['application_fee_id'] = $paymentIntent->charges->data[0]->application_fee;
                        $gratuityTransactionId = DB::table('transactions')->insertGetId($gratuityData['transactions']);
                        $gratuityData['tour_booking_history']['transaction_id'] = $gratuityTransactionId;
                        $gratuityData['tour_booking_history']['card_last_4_digit'] = $cardLast4Digit;
                        $jsonData = json_decode($gratuityData['tour_booking_history']['details'], true);
                        $jsonData['card_number'] = $cardLast4Digit;
                        $jsonData['card_type'] = $cardType;
                        $gratuityData['tour_booking_history']['details'] = json_encode($jsonData);
                        DB::table('tour_booking_history')->insert($gratuityData['tour_booking_history']);
                    }

                    // Below 3 line is for to update tour booking history table
                    $bookingData->booking_id = $bookingData->TourBookingID;
                    $bookingData->affiliate_id = $affiliateId;
                    FrontendCardBookingService::run((array) $bookingData, $transactionId, $historyId, 'Full Payment', $paymentIntent);
                    TimeSlotAvailabilityService::updateSlotSummary($bookingData->tour_slot_time_id, $bookingData->tourists);
                    $tourListwithSeat = DB::table('tour_bookings_transact as tbm')
                        ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbm.tour_package_rate_id')
                        ->where('tbm.tour_booking_id', '=', $id)
                        ->select('tpr.rate_for', 'tbm.tourists', 'tbm.infants')
                        ->get();

                    $bookingTime = "{$bookingData->Date} {$bookingData->StartTime}";
                    $bookingData->tourDate = date('l, F j, Y', strtotime($bookingTime));
                    $bookingData->tourDateTime = date('l, F j, Y @ g:i a', strtotime($bookingTime));

                    $bookId = Crypt::encryptString($id);

                    $bookingData->urlBookingInfo = route('customer.tour-booking-info', ['bookingId' => $bookId]);
                    $bookingData->urlLiabilityWaiver = route('customer.liability-waiver-form', ['bookingId' => $bookId]);
                    $bookingData->cancellationUrl = route('customer.confirm-cancel-booking', ['bookingId' => $bookId]);

                    if (!empty($bookingData->tour_op_logo)) {
                        $tourOperatorLogo = tourOperatorLogo($bookingData->tenant_id, $bookingData->tour_op_logo);
                        $bookingData->logo = $tourOperatorLogo;
                    }

                    $tourPkgPhoto = DB::table('tour_package_photos')
                        ->where('tour_package_id', $bookingData->tour_package_id)
                        ->where('placement', 2)
                        ->where('status', 'Active')
                        ->latest()
                        ->first(['path']);

                    if (isset($tourPkgPhoto) && !empty($tourPkgPhoto)) {
                        $tourPackageLogo = tourPackageLogo($bookingData->tenant_id, $bookingData->tour_package_id, $tourPkgPhoto->path);
                        $bookingData->tourPkgPhoto = $tourPackageLogo;
                    }

                    $tourPackageImage = tourPackageImage($bookingData->tenant_id, $bookingData->tour_package_id);
                    $bookingData->tourPkgImage = $tourPackageImage;

                    $bookingData->thingsToBring = $bookingData->things_to_bring ? json_decode($bookingData->things_to_bring) : [];

                    $arEmailData = [
                        'bookingId' => $bookingData->TourBookingID,
                        'bookingGroupId' => $bookingData->group_id,
                        'tourOperatorId' => $bookingData->tour_operator_id,
                        'tourPackageId' => $bookingData->tour_package_id,
                        'tourOperator' => $bookingData->tour_operator_name,
                        'tourOpPhone' => $bookingData->tourOpPhone,
                        'tourOpEmail' => $bookingData->tourOpEmail,
                        'tourOpWebsite' => $bookingData->tourOpWebsite,
                        'tourPackage' => $bookingData->TourPkgName,
                        'tourDate' => $bookingData->tourDate,
                        'tourDateTime' => $bookingData->tourDateTime,
                        'urlBookingInfo' => $bookingData->urlBookingInfo,
                        'custName' => $bookingData->Name,
                        'custEmail' => $bookingData->Email,
                        'custPhone' => $bookingData->ContactNumber,
                        'urlLiabilityWaiver' => $bookingData->urlLiabilityWaiver,
                        'importantNotes' => $bookingData->important_notes,
                        'thingsToBring' => $bookingData->thingsToBring,
                        'cancellationPolicy' => $bookingData->cancellationPolicy,
                        'cancellationUrl' => $bookingData->cancellationUrl,
                        'numberoftourist' => $bookingData->tourists + $bookingData->infants,
                        'tourListwithSeat' => $tourListwithSeat,
                        'packageEmail' => $bookingData->packageEmail,
                        'packageFromName' => $bookingData->packageFromName,
                        'packageFromEmail' => $bookingData->packageFromEmail,
                        'packageApiKey' => $bookingData->packageApiKey,
                        'waiverPdf' => $bookingData->waiverPdf ?? null,
                        'attachWaiverPdf' => $bookingData->attachWaiverPdf ?? null,
                        'tourOpLogo' => $bookingData->logo ?? null,
                        'tourPkgPhoto' => $bookingData->tourPkgPhoto ?? null,
                        'tourPkgImage' => $bookingData->tourPkgImage ?? null,
                        'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
                        'operationBy' => $customer->id,
                        'operationByUserType' => 'Customer',
                        'tenantId' => tourOperator()->tenant_id
                    ];

                    Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));

                    if ($customer->is_affiliate == 'Yes') {
                        // replace the customer data when book using affiliate ID
                        $arEmailData['custName'] = $customer->name;
                        $arEmailData['custEmail'] = $customer->email;
                        $arEmailData['custPhone'] = $customer->phone_number;

                        Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));
                    }

                    if ($bookingData->hotel_id) {
                        $hotel = Hotel::find($bookingData->hotel_id);

                        DB::table('tour_booking_history')->insert([
                            'tour_booking_id' => $id,
                            'operation' => 'Pickup Hotel',
                            'details' => json_encode([
                                'id' => $hotel->id,
                                'name' => $hotel->name,
                                'address' => $hotel->address,
                                'email' => $hotel->email,
                                'phone_number' => $hotel->phone_number,
                                'status' => $hotel->status,
                                'created_by_with_name' => $bookingData->Name . ': (' . "Customer" . ')'
                            ], JSON_NUMERIC_CHECK),
                            'operation_at' => mysqlDT(),
                            'operation_by' => $bookingData->customer_id,
                            'operation_by_user_type' => 'Customer',
                            'updated_at' => Carbon::now(),
                            'created_at' => Carbon::now(),
                        ]);
                    }
                }

                if (count($comboPaymentHistIds) > 1) {
                    foreach ($comboPaymentHistIds as $histId) {
                        $siblings = array_values(array_filter($comboPaymentHistIds, fn($id) => $id !== $histId));
                        DB::table('tour_booking_history')
                            ->where('id', $histId)
                            ->update([
                                'details' => DB::raw("JSON_SET(details, '$.combo_payment_history_ids', CAST('" . json_encode($siblings) . "' AS JSON))")
                            ]);
                    }
                }

                $data = [
                    "bookingIds" => $bookingIds,
                    "success" => 'true',
                    'message' => 'Your Tour Bookings Is Successfully Booked.'
                ];

                return response()->json($data, 200);
            } catch (Exception $exception) {
                Log::error($exception);
                return $this->sendError($exception->getMessage());
            }
        }
    }

    /**
     * Booking API to handle the 3DS payment for new booking
     *
     * @param Request $request
     */
    public function packageBooking3dsPayment(Request $request)
    {
        if ($request->payment_intent && $request->payment_intent_client_secret) {
            $bookingCount = TourBooking::whereIn('id', $request->booking_ids)->count();

            if ($bookingCount) {
                Stripe::setApiKey(getStripeSecretKey());

                $paymentIntentDetails = PaymentIntent::retrieve([
                    'id' => $request->payment_intent,
                    'expand' => ['charges.data.transfer']
                ]);

                try {
                    if (!empty($paymentIntentDetails) && isset($paymentIntentDetails->status) && $paymentIntentDetails->status == 'succeeded') {
                        $data = [];
                        $bookingIds = [];
                        $description = null;
                        $lineBreak = chr(0x0D) . chr(0x0A) . ' ' . chr(0x0D) . chr(0x0A);
                        foreach ($request->booking_ids as $index => $id) {
                            $transactionsExist = Transaction::where('tour_booking_id', $id)->where('status', 'Completed')->first();
                            if (!$transactionsExist) {
                                TourBooking::where('id', $id)->update(['status' => 'Booked']);
                                $bookedItem = TourBooking::find($id);
                                TimeSlotService::closeSlotOnBooking($bookedItem->tour_package_id, $bookedItem->tour_slot_id);

                                // Save checkout term acknowledgments
                                if ($request->has('custom_term_acknowledgments')) {
                                    CheckoutTermsService::saveCheckoutTerms($id, $request->custom_term_acknowledgments);
                                }

                                $bookingData = DB::table('tour_bookings_master as bm')
                                    ->join('customers as cus', 'cus.id', '=', 'bm.customer_id')
                                    ->join('tour_packages AS tp', 'tp.id', '=', 'bm.tour_package_id')
                                    ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                                    ->join('tour_slot_times as st', 'st.id', '=', 'bm.tour_slot_id')
                                    ->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
                                    ->where('bm.id', '=', $id)
                                    ->select(
                                        'bm.id as TourBookingID',
                                        'bm.group_id',
                                        'bm.tour_promotion_id',
                                        'bm.hotel_id',
                                        'bm.total',
                                        'bm.service_commission',
                                        'bm.name as Name',
                                        'bm.email as Email',
                                        'bm.phone_number as ContactNumber',
                                        'bm.discount2_value',
                                        'bm.discount2_percentage',
                                        'bm.tourists',
                                        'bm.infants',
                                        'bm.tour_promotion_id',
                                        'bm.is_partner_booking',
                                        'sd.date as Date',
                                        'st.time_from as StartTime',
                                        'st.time_to as EndTime',
                                        'tp.name as TourPkgName',
                                        't_o.logo AS tour_op_logo',
                                        'tp.id AS tour_package_id',
                                        'cus.id as customer_id',
                                        'cus.name as customer_name',
                                        'cus.email as customer_email',
                                        'cus.phone_number as customer_phone_number',
                                        'cus.is_affiliate',
                                        't_o.name as tour_operator_name',
                                        'st.id as tour_slot_time_id',
                                        'tp.stripe_destination_id',
                                        't_o.id As tour_operator_id',
                                        'tp.name as packageName',
                                        'tp.email as packageEmail',
                                        'tp.from_name as packageFromName',
                                        'tp.from_email as packageFromEmail',
                                        'tp.api_key as packageApiKey',
                                        't_o.phone_number As tourOpPhone',
                                        't_o.email As tourOpEmail',
                                        't_o.website As tourOpWebsite',
                                        't_o.statement_descriptor',
                                        't_o.card_reader_prefix',
                                        'tp.important_notes',
                                        'tp.things_to_bring',
                                        'tp.is_group_rate_enabled',
                                        't_o.cancellation_policy As cancellationPolicy',
                                        't_o.tenant_id'
                                    )
                                    ->first();

                                $tbTransaction = BookingTransact::with('packageRate')->where('tour_booking_id', $id)->get();
                                $addedPeoplesText = [];
                                $partnerFeesAmount = 0;
                                if ($tbTransaction->count()) {
                                    foreach ($tbTransaction as $tblTransaction) {
                                        $addedPeoplesText[] = $tblTransaction->packageRate->rate_for . " person " . $tblTransaction->tourists;
                                        $partnerFeesAmount += $tblTransaction->tourists * $tblTransaction->partner_fees_amount;
                                    }
                                }

                                DB::table('tour_booking_history')
                                    ->insert([
                                        'tour_booking_id' => $bookingData->TourBookingID,
                                        'tourists' => $bookingData->tourists,
                                        'operation' => 'Added',
                                        'details' => json_encode([
                                            'sub_operation' => 'Tourists Added',
                                            'discount_percentage' => 0,
                                            'discount2_value' => 0,
                                            'added' => $addedPeoplesText,
                                            'newTourists' => $bookingData->tourists,
                                            'created_by_with_name' => $bookingData->customer_name
                                        ], JSON_NUMERIC_CHECK),
                                        'operation_at' => Carbon::now(), // laravel doesn't seems to honouring timezone given in config > app.php!
                                        'operation_by' => $bookingData->customer_id,
                                        'operation_by_user_type' => 'Customer',
                                        'updated_at' => Carbon::now(),
                                        'created_at' => Carbon::now()
                                    ]);

                                $transactionId = DB::table('transactions')
                                    ->insertGetId([
                                        'tour_booking_id' => $id,
                                        'amount' => $bookingData->total,
                                        'fee_amount' => $bookingData->service_commission,
                                        'transaction_number' => $paymentIntentDetails->id,
                                        'application_fee_id' => $paymentIntentDetails->charges->data[0]->application_fee,
                                        'via' => 'Card',
                                        'payment_mode' => PaymentModeInference::fromStripeObject($paymentIntentDetails),
                                        'response_init' => '',
                                        'response_end' => '',
                                        'response_end_datetime' => Carbon::now(),
                                        'status' => 'Completed',
                                        'visitor' => $request->getClientIp(),
                                        'request_from' => Constants::FRONTEND,
                                        'created_by' => $bookingData->customer_id,
                                        'created_by_user_type' => 'Customer',
                                        'created_at' => Carbon::now(),
                                    ]);

                                $cardLast4Digit = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
                                $cardType = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

                                $historyId = DB::table('tour_booking_history')
                                    ->insertGetId([
                                        'tour_booking_id' => $id,
                                        'tourists' => $bookingData->tourists,
                                        'operation' => 'Payment',
                                        'details' => json_encode(['amount' => $bookingData->total, 'payment_type' => 'Card', 'card_number' => $cardLast4Digit, 'card_type' => $cardType], JSON_NUMERIC_CHECK),
                                        'operation_at' => Carbon::now(),
                                        'operation_by' => $bookingData->customer_id,
                                        'operation_by_user_type' => 'Customer',
                                        'updated_at' => Carbon::now(),
                                        'created_at' => Carbon::now(),
                                    ]);

                                if ($bookingData->is_partner_booking == 1) {
                                    $payoutData = [
                                        'amount' => $bookingData->total,
                                        'platform_fees' => $bookingData->service_commission,
                                        'partner_fees' => $partnerFeesAmount,
                                        'booking_id' => $id,
                                        'transaction_id' => $transactionId,
                                        'package_id' => $bookingData->tour_package_id,
                                        'partner_destination_id' => $bookingData->stripe_destination_id,
                                        'payment_method' => 'Card',
                                        'transfer_group' => $paymentIntentDetails->id,
                                    ];

                                    ThreeWayPaymentService::transferToPartner($payoutData);
                                }

                                $bookingData->booking_id = $bookingData->TourBookingID;
                                $bookingData->affiliate_id = $request->affiliate_id;
                                FrontendCardBookingService::run((array) $bookingData, $transactionId, $historyId, 'Full Payment', $paymentIntentDetails);
                                TimeSlotAvailabilityService::updateSlotSummary($bookingData->tour_slot_time_id, $bookingData->tourists);

                                // Save the card details
                                HistoryService::getSavedCardFromPaymentIntent($paymentIntentDetails->id, $id, "FRONTEND");

                                $tourListwithSeat = DB::table('tour_bookings_transact as tbm')
                                    ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbm.tour_package_rate_id')
                                    ->where('tbm.tour_booking_id', '=', $id)
                                    ->select('tpr.rate_for', 'tbm.tourists', 'tbm.infants')
                                    ->get();

                                $bookingTime = "{$bookingData->Date} {$bookingData->StartTime}";
                                $bookingData->tourDate = date('l, F j, Y', strtotime($bookingTime));
                                $bookingData->tourDateTime = date('l, F j, Y @ g:i a', strtotime($bookingTime));

                                $encId = Crypt::encryptString($bookingData->TourBookingID);
                                $bookingData->urlBookingInfo = route('customer.tour-booking-info', ['bookingId' => $encId]);
                                $bookingData->urlLiabilityWaiver = route('customer.liability-waiver-form', ['bookingId' => $encId]);
                                $bookingData->cancellationUrl = route('customer.confirm-cancel-booking', ['bookingId' => $encId]);

                                if (!empty($bookingData->tour_op_logo)) {
                                    $bookingData->logo = tourOperatorLogo($bookingData->tenant_id, $bookingData->tour_op_logo);
                                }

                                $tourPkgPhoto = DB::table('tour_package_photos')
                                    ->where('tour_package_id', $bookingData->tour_package_id)
                                    ->where('placement', 2)
                                    ->where('status', 'Active')
                                    ->latest()
                                    ->first(['path']);

                                if (isset($tourPkgPhoto) && !empty($tourPkgPhoto)) {
                                    $bookingData->tourPkgPhoto = tourPackageLogo($bookingData->tenant_id, $bookingData->tour_package_id, $tourPkgPhoto->path);
                                }

                                $tourPackageImage = tourPackageImage($bookingData->tenant_id, $bookingData->tour_package_id);
                                $bookingData->tourPkgImage = $tourPackageImage;

                                $bookingData->thingsToBring = $bookingData->things_to_bring ? json_decode($bookingData->things_to_bring) : [];

                                $arEmailData = [
                                    'bookingId' => $bookingData->TourBookingID,
                                    'bookingGroupId' => $bookingData->group_id,
                                    'tourOperatorId' => $bookingData->tour_operator_id,
                                    'tourPackageId' => $bookingData->tour_package_id,
                                    'tourOperator' => $bookingData->tour_operator_name,
                                    'tourOpPhone' => $bookingData->tourOpPhone,
                                    'tourOpEmail' => $bookingData->tourOpEmail,
                                    'tourOpWebsite' => $bookingData->tourOpWebsite,
                                    'tourPackage' => $bookingData->TourPkgName,
                                    'tourDate' => $bookingData->tourDate,
                                    'tourDateTime' => $bookingData->tourDateTime,
                                    'urlBookingInfo' => $bookingData->urlBookingInfo,
                                    'custName' => $bookingData->Name,
                                    'custEmail' => $bookingData->Email,
                                    'custPhone' => $bookingData->ContactNumber,
                                    'urlLiabilityWaiver' => $bookingData->urlLiabilityWaiver,
                                    'importantNotes' => $bookingData->important_notes,
                                    'thingsToBring' => $bookingData->thingsToBring,
                                    'cancellationPolicy' => $bookingData->cancellationPolicy,
                                    'cancellationUrl' => $bookingData->cancellationUrl,
                                    'numberoftourist' => $bookingData->tourists + $bookingData->infants,
                                    'tourListwithSeat' => $tourListwithSeat,
                                    'packageEmail' => $bookingData->packageEmail,
                                    'packageFromName' => $bookingData->packageFromName,
                                    'packageFromEmail' => $bookingData->packageFromEmail,
                                    'packageApiKey' => $bookingData->packageApiKey,
                                    'waiverPdf' => $bookingData->waiverPdf ?? null,
                                    'attachWaiverPdf' => $bookingData->attachWaiverPdf ?? null,
                                    'tourOpLogo' => $bookingData->logo ?? null,
                                    'tourPkgPhoto' => $bookingData->tourPkgPhoto ?? null,
                                    'tourPkgImage' => $bookingData->tourPkgImage ?? null,
                                    'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
                                    'operationBy' => $bookingData->customer_id,
                                    'operationByUserType' => 'Customer',
                                    'tenantId' => tourOperator()->tenant_id
                                ];

                                Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));

                                if ($bookingData->is_affiliate == 'Yes') {
                                    // replace the customer data when book using affiliate ID
                                    $arEmailData['custName'] = $bookingData->customer_name;
                                    $arEmailData['custEmail'] = $bookingData->customer_email;
                                    $arEmailData['custPhone'] = $bookingData->customer_phone_number;

                                    Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));
                                }

                                if ($index > 0) {
                                    $description .= $lineBreak;
                                }

                                $description .= 'Booking Id: ' . $bookingData->TourBookingID .
                                    "\nTour Package: " . $bookingData->TourPkgName .
                                    "\nTour Date: " . $bookingData->Date .
                                    "\nTour Time: " . $bookingData->StartTime .
                                    "\nTourists: " . $bookingData->tourists .
                                    "\nEmail: " . $bookingData->Email;

                                $data[] = [
                                    'BookingId' => $bookingData->TourBookingID,
                                    // 'tour_date' => $bookingData->Date,
                                    // 'tour_package' => $bookingData->TourPkgName,
                                    // 'tour_time' => $bookingData->StartTime,
                                    // 'tourists' => $bookingData->tourists,
                                    // 'amount' => $bookingData->total,
                                    // 'fee' => $bookingData->service_commission
                                ];

                                $bookingIds[] = $bookingData->TourBookingID;
                            } else {
                                $data = [
                                    "success" => 'true',
                                    'message' => 'Transaction Details Exists, Please Try Again.'
                                ];

                                return response()->json($data, 406);
                            }
                        }

                        if (isset($paymentIntentDetails->charges->data[0]->transfer->destination_payment)) {
                            $stripeMetadata = [
                                'description' => StripeUtils::limitDescription($description),
                                'metadata' => [
                                    'customer_name' => $bookingData->Name,
                                    'customer_phone' => $bookingData->ContactNumber,
                                    'email' => $bookingData->Email,
                                    'bookings' => json_encode($data),
                                    'from' => Constants::ONLINE,
                                    'tenant_id' => tenant()->id,
                                ]
                            ];

                            // Update the destination payment in the platform
                            $chargeParams = $stripeMetadata;
                            app(PaymentGatewayInterface::class)->updateConnectedPayment(
                                $paymentIntentDetails->charges->data[0]->transfer->destination_payment,
                                $chargeParams,
                                $paymentIntentDetails->charges->data[0]->transfer->destination
                            );
                        }

                        $data = [
                            "bookingIds" => $bookingIds,
                            "success" => 'true',
                            'message' => 'Your Tour Booking Is Successfully Booked.'
                        ];

                        return response()->json($data, 200);
                    } else {
                        foreach ($request->booking_ids as $index => $id) {
                            $booking = TourBooking::find($id);

                            DB::table('tour_booking_history')
                                ->insert([
                                    'tour_booking_id' => $id,
                                    'operation' => 'Card Error',
                                    'details' => $paymentIntentDetails->last_payment_error->message ?? 'Unable to capture the payment.',
                                    'operation_at' => Carbon::now(),
                                    'operation_by' => $booking->customer_id,
                                    'operation_by_user_type' => 'Customer',
                                    'updated_at' => Carbon::now(),
                                    'created_at' => Carbon::now(),
                                ]);

                            DB::table('transactions')
                                ->insert([
                                    'tour_booking_id' => $id,
                                    'amount' => $booking->total,
                                    'transaction_number' => null,
                                    'via' => 'Card',
                                    'response_init' => '',
                                    'response_end' => '',
                                    'response_end_datetime' => Carbon::now(),
                                    'status' => 'Failed',
                                    'visitor' => $request->getClientIp(),
                                    'request_from' => Constants::FRONTEND,
                                    'created_by' => '',
                                    'created_by_user_type' => 'Customer',
                                    'created_at' => Carbon::now(),
                                ]);

                            TourBooking::where('id', $id)->update(['status' => 'Cancelled']);
                        }

                        $data = [
                            "bookingIds" => $request->booking_ids,
                            "success" => 'true',
                            'message' => $paymentIntentDetails->last_payment_error->message ?? 'Please Try Again!!! Something Went Wrong.'
                        ];

                        return response()->json($data, 406);
                    }
                } catch (CardException $exception) {
                    Log::error($exception);

                    foreach ($request->booking_ids as $index => $id) {
                        $booking = TourBooking::find($id);

                        DB::table('tour_booking_history')
                            ->insert([
                                'tour_booking_id' => $booking->id,
                                'operation' => 'Card Error',
                                'details' => $exception->getMessage(),
                                'operation_at' => Carbon::now(),
                                'operation_by' => $booking->customer_id,
                                'operation_by_user_type' => 'Customer',
                                'updated_at' => Carbon::now(),
                                'created_at' => Carbon::now(),
                            ]);

                        DB::table('transactions')
                            ->insert([
                                'tour_booking_id' => $booking->id,
                                'amount' => $booking->total,
                                'transaction_number' => null,
                                'via' => 'Card',
                                'response_init' => '',
                                'response_end' => '',
                                'response_end_datetime' => Carbon::now(),
                                'status' => 'Failed',
                                'visitor' => $request->getClientIp(),
                                'request_from' => Constants::FRONTEND,
                                'created_by' => '',
                                'created_by_user_type' => 'Customer',
                                'created_at' => Carbon::now(),
                            ]);
                    }

                    $data = [
                        "bookingIds" => $request->booking_ids,
                        "success" => 'true',
                        'message' => 'Please Try Again!!! Something Went Wrong.'
                    ];

                    return response()->json($data, 406);
                }
            } else {
                $data = [
                    "success" => 'true',
                    'message' => 'Booking Details Does Not Exists, Please Try Again.'
                ];

                return response()->json($data, 406);
            }
        } else {
            $data = [
                "success" => 'true',
                'message' => 'Request Parameters Does Not Exists, Please Try Again.'
            ];

            return response()->json($data, 406);
        }
    }

    /**
     * Get the bookings info which is created
     *
     * @param Request $request
     */
    public function packageBookingConfirmed(Request $request)
    {
        try {
            // Main query with all necessary joins and selections
            $bookingData = DB::table('tour_bookings_master as bm')
                ->join('customers as cus', 'cus.id', '=', 'bm.customer_id')
                ->join('tour_packages as pkg', 'pkg.id', '=', 'bm.tour_package_id')
                ->join('tour_operators as to', 'to.id', '=', 'pkg.tour_operator_id')
                ->join('tour_slot_times as st', 'st.id', '=', 'bm.tour_slot_id')
                ->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
                ->leftJoin('hotels as h', 'h.id', '=', 'bm.hotel_id')
                ->leftJoin('tour_promotions as tp', 'tp.id', '=', 'bm.tour_promotion_id')
                ->leftJoin('gratuities as g', function ($join) {
                    $join->on('g.booking_id', '=', 'bm.id');
                })
                ->whereIn('bm.id', $request->ids)
                ->select(
                    'bm.id',
                    'bm.customer_id',
                    'bm.hotel_id',
                    'bm.tour_promotion_id',
                    'bm.discount2_value',
                    'bm.discount2_percentage',
                    'bm.service_commission',
                    'bm.total',
                    DB::raw('(bm.tourists + bm.infants) as tourists'),
                    'bm.is_partner_booking',
                    'cus.name',
                    'cus.phone_number',
                    'cus.email',
                    'cus.is_affiliate',
                    'sd.date',
                    'st.time_from',
                    'st.time_to',
                    'pkg.id as package_id',
                    'pkg.tour_operator_id',
                    'pkg.name as package_name',
                    'pkg.is_group_rate_enabled',
                    'pkg.package_has_slots',
                    'pkg.package_has_waiver',
                    'pkg.package_has_permit',
                    'to.tenant_id',
                    'to.phone_number as company_number',
                    'h.name as hotel_name',
                    'h.address as hotel_address',
                    'tp.code as promocode',
                    'g.amount as gratuity_amount',
                    'g.fees as gratuity_fees'
                )
                ->get();

            if ($bookingData->isEmpty()) {
                return $this->sendError('Tour Booking Not Found.');
            }

            $bookingPrefix = bookingPrefixSetting();

            // Format dates and times once
            $bookingData->each(function ($data) use ($bookingPrefix) {
                // Format date and time
                $data->date = date("l, F d - Y", strtotime($data->date));
                $data->time = sprintf(
                    "%s To %s",
                    date("h:ia", strtotime($data->time_from)),
                    date("h:ia", strtotime($data->time_to))
                );

                // Fetch tour package rates
                $data->tourPackageRates = DB::table('tour_bookings_transact as tr')
                    ->join('tour_package_rates as pr', 'pr.id', '=', 'tr.tour_package_rate_id')
                    ->where('tr.tour_booking_id', $data->id)
                    ->select(
                        DB::raw('CASE WHEN tr.infants > 0 THEN tr.infants ELSE tr.tourists END AS tourists'),
                        'pr.rate_for',
                        'tr.rate',
                        'tr.permit_fee',
                        'tr.additional_charge',
                        'tr.partner_fees_amount',
                        'tr.total',
                        'tr.nat_fees_percentage',
                        'pr.description'
                    )
                    ->get();

                // Calculate totals
                $subtotal = 0;
                $fee = 0;
                $ageCount = 0;

                $data->tourPackageRates->each(function ($rateData) use ($data, &$subtotal, &$fee, &$ageCount) {
                    $tourists = $rateData->tourists;
                    $natPercentage = $rateData->nat_fees_percentage;

                    // Format rate display
                    $rateData->tourists = "{$rateData->rate_for} : {$tourists}";
                    $rateData->fees = $rateData->description ?: ($data->is_group_rate_enabled
                        ? taxLabel() . ": " . moneyFormat($rateData->additional_charge)
                        : "Permit Fee " . moneyFormat($rateData->permit_fee) . " & " . taxLabel() . " " . moneyFormat($rateData->additional_charge));

                    if ($data->is_partner_booking && !$rateData->description && $rateData->partner_fees_amount > 0) {
                        $rateData->fees .= " & Partner Fee " . moneyFormat($rateData->partner_fees_amount);
                    }

                    // Calculate totals
                    if ($data->is_group_rate_enabled) {
                        $rate = $rateData->rate + $rateData->additional_charge + $rateData->partner_fees_amount;
                        $subtotal += $rate;
                        $fee += ($rate * $natPercentage) / 100;
                    } else {
                        $rate = $rateData->rate + $rateData->additional_charge + $rateData->permit_fee + $rateData->partner_fees_amount;
                        $subtotal += $rate * $tourists;
                        $fee += (($rate * $natPercentage) / 100) * $tourists;
                    }

                    $ageCount += $tourists;
                });

                // Format totals
                $data->subtotal = number_format($subtotal, 2, '.', '');
                $data->fees = number_format($fee, 2, '.', '');
                $data->total = number_format($subtotal + $fee, 2, '.', '');
                $data->tourists = $ageCount;

                // === NEW ADD-ONS SYSTEM (Replaces old tour_booking_addons) ===
                $addons = DB::table('tour_booking_addon_fields as af')
                    ->join('tour_booking_addon_transactions as at', 'at.tour_booking_addon_id', '=', 'af.id')
                    ->where('af.tour_booking_id', $data->id)
                    ->whereNull('af.deleted_at')
                    ->whereNull('at.deleted_at')
                    ->where('at.status', 'paid')
                    ->select(
                        'af.field_label',
                        'af.option_label',
                        'af.unit_type',
                        'af.unit',
                        'af.value as field_value', // for price_per_unit (number input)
                        'at.field_price',
                        'at.field_fee',
                        'at.option_price',
                        'at.option_fee',
                        'at.field_additional_fee',
                        'at.option_additional_fee'
                    )
                    ->get();

                $addonItems = [];
                $addonsTotalPrice = 0;
                $addonsTotalFee = 0;

                foreach ($addons as $addon) {
                    // Determine quantity
                    $quantity = 1;
                    if ($addon->unit_type === 'price_per_unit') {
                        $quantity = (int) $addon->field_value;
                    } elseif ($addon->unit_type === 'price_per_pax') {
                        $quantity = 1; // already one row per person
                    } else {
                        $quantity = (int) $addon->unit;
                    }

                    // field_price/field_fee are already quantity-scaled totals (see createPricedRecord())
                    $totalPrice = $addon->field_price + $addon->option_price;
                    $totalFee   = $addon->field_fee + $addon->option_fee;
                    $unitPrice  = $quantity > 0 ? $totalPrice / $quantity : $totalPrice;
                    $unitFee    = $quantity > 0 ? $totalFee / $quantity : $totalFee;

                    $addonsTotalPrice += $totalPrice;
                    $addonsTotalFee += $totalFee;

                    $label = $addon->field_label;
                    if ($addon->option_label) {
                        $label .= " ({$addon->option_label})";
                    }
                    if ($quantity > 1) {
                        $label .= " × {$quantity}";
                    }

                    $addonItems[] = [
                        'name'       => $label,
                        'price'      => number_format($totalPrice, 2),
                        'fee'        => number_format($totalFee, 2),
                        'quantity'   => $quantity,
                        'unit_price' => number_format($unitPrice, 2),
                        'unit_fee'   => number_format($unitFee, 2),
                    ];
                }

                $data->tourBookingAddons = $addonItems;
                $data->addons_total = number_format($addonsTotalPrice, 2, '.', '');
                $data->addons_fee = number_format($addonsTotalFee, 2, '.', '');

                // Format booking ID
                $data->id = $bookingPrefix ? $bookingPrefix . $data->id : $data->id;
            });

            return $this->sendResponse($bookingData, 'Tour Booking Details.', 200);
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Apply coupon code for the booking to get discount
     *
     * @param Request $request
     * @param int $packageId
     * @param int $timeSlotId
     * @param mixed $code
     */
    public function applyCoupon(Request $request, $packageId, $timeSlotId, $code)
    {
        try {
            $timeSlot = TourSlotTime::find($timeSlotId);

            $promotion = TourPromotion::where('tour_package_id', $packageId)
                ->where('code', $code)
                ->whereRaw("\"{$timeSlot->date}\" between `start_date` and `end_date`")
                ->where('status', 'Active')
                ->first(['id', 'max_allowed_usages', 'min_allowed_tourists', 'discount_value', 'discount_value_type']);

            if ($promotion) {
                $bookingsCount = TourBooking::where('tour_promotion_id', $promotion->id)->count();

                if ($bookingsCount >= $promotion->max_allowed_usages && $promotion->max_allowed_usages != 0) {
                    return $this->sendError('Your coupon code is no longer valid.');
                }

                // Optional query param ('tourists', infants excluded) -- the customer-facing
                // widget doesn't send this today, so this check only activates once a caller
                // actually supplies it. Fail-open when absent rather than defaulting to 0 and
                // silently rejecting every promo with a configured minimum for every existing
                // caller that hasn't been updated to send it yet.
                if ($request->filled('tourists') && (int) $promotion->min_allowed_tourists > 0
                    && (int) $request->input('tourists') < (int) $promotion->min_allowed_tourists) {
                    return $this->sendError('This coupon requires at least ' . $promotion->min_allowed_tourists . ' tourist(s).');
                }

                $data = [
                    'success' => true,
                    'message' => 'Coupon code applied.',
                    'data' => $promotion
                ];

                return response()->json($data, 200);
            } else {
                return $this->sendError('Your coupon code is not valid.');
            }
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError('Your coupon code is not valid.');
        }
    }

    /**
     * Get the booking data for the tour booking
     *
     * @param int $id
     */
    public function bookingData($id)
    {
        $bookingData = DB::table('tour_bookings_master as bm')
            ->join('customers as cus', 'cus.id', '=', 'bm.customer_id')
            ->join('tour_packages AS tp', 'tp.id', '=', 'bm.tour_package_id')
            ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
            ->join('tour_slot_times as st', 'st.id', '=', 'bm.tour_slot_id')
            ->where('bm.id', '=', $id)
            ->select(
                'bm.id as TourBookingID',
                'bm.group_id',
                'bm.tour_promotion_id',
                'bm.hotel_id',
                'bm.total',
                'bm.service_commission',
                'bm.name as Name',
                'bm.email as Email',
                'bm.phone_number as ContactNumber',
                'bm.discount2_value',
                'bm.discount2_percentage',
                'bm.tourists',
                'bm.infants',
                'bm.tour_promotion_id',
                'bm.is_partner_booking',
                'st.date as Date',
                'st.time_from as StartTime',
                'st.time_to as EndTime',
                'tp.name as TourPkgName',
                't_o.logo AS tour_op_logo',
                'tp.id AS tour_package_id',
                'cus.id as customer_id',
                'cus.name as customer_name',
                'cus.email as customer_email',
                'cus.phone_number as customer_phone_number',
                'cus.is_affiliate',
                't_o.name as tour_operator_name',
                'st.id as tour_slot_time_id',
                'tp.stripe_destination_id',
                't_o.id As tour_operator_id',
                'tp.name as packageName',
                'tp.email as packageEmail',
                'tp.from_name as packageFromName',
                'tp.from_email as packageFromEmail',
                'tp.api_key as packageApiKey',
                'tp.attach_waiver_pdf as attachWaiverPdf',
                'tp.waiver_pdf as waiverPdf',
                't_o.phone_number As tourOpPhone',
                't_o.email As tourOpEmail',
                't_o.website As tourOpWebsite',
                't_o.statement_descriptor',
                't_o.card_reader_prefix',
                'tp.important_notes',
                'tp.things_to_bring',
                'tp.is_group_rate_enabled',
                't_o.cancellation_policy As cancellationPolicy',
                't_o.tenant_id'
            )
            ->first();

        return $bookingData;
    }

    /**
     * Create payment intent to display card elements in frontend
     *
     * @param Request $request
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            $i = 0;
            $tourTotal = 0;
            $tourApplicationFeeAmount = 0;
            $destination = '';
            $data = [];
            $description = null;
            $tourOperator = tourOperator();

            foreach ($request->items as $slotId => $item) {
                $timeSlotData = DB::table('tour_slot_times')->where('id', $slotId)->first();

                if (!$timeSlotData) {
                    return response()->json(['message' => 'The selected tour time slot could not be found. Please refresh and try again.'], 422);
                }

                stopNextYearBookings($timeSlotData->date);

                $package = TourPackage::where('id', $item['package_id'])->first();

                if ($package && $package->duration > 0 && !$item['affiliate_id']) {
                    $durationHours = (int) $package->duration;
                    if ($package->day_hours == Constants::STATUS_ONE) {
                        $durationHours *= 24;
                    }

                    if ($timeSlotData) {
                        $blockDateTime = Carbon::now()->addHours($durationHours);
                        $slotDateTime = Carbon::parse($timeSlotData->date . ' ' . $timeSlotData->time_from);
                        if ($slotDateTime->lt($blockDateTime)) {
                            $unit = $package->day_hours == Constants::STATUS_ONE ? 'day(s)' : 'hour(s)';
                            return response()->json(['message' => 'This tour is not available for online booking. Please book at least ' . $package->duration . ' ' . $unit . ' in advance.'], 422);
                        }
                    }
                }

                $infants = $item['infant_count'] ?? 0;
                $isBlackoutDate = isBlackoutDateEnabled($timeSlotData->date);
                $serviceCommissionPercentage = $package->service_commission_percentage;
                if ($item['affiliate_id'] != 0) {
                    $customer = Customer::where('id', $item['affiliate_id'])->first();
                    $serviceCommissionPercentage = ($isBlackoutDate) ? $package->service_commission_percentage : $customer->affiliate_processing_percentage; // Default to affiliate processing percentage
                    if ($customer && getNextYearCommissionPercentage($timeSlotData->date)) {
                        $affiliateCommission = getAffiliateCommissionPercentage($customer->id);
                        $serviceCommissionPercentage = ($isBlackoutDate) ? $package->next_year_service_commission_percentage : $affiliateCommission['affiliate_processing_percentage']; // Use next year affiliate processing percentage
                    }
                } else if (getNextYearCommissionPercentage($timeSlotData->date)) {
                    $serviceCommission = getNormalCommissionPercentage($package->id);
                    $serviceCommissionPercentage = $serviceCommission['service_commission_percentage']; // Use next year service commission percentage
                }

                /* START: Custom Date Rate Validation */
                $affiliateIdForRate = (!$isBlackoutDate) ? $item['affiliate_id'] : 0;
                $standardRateDate = SurchargeDate::where('affiliate_id', $affiliateIdForRate)
                    ->where('tour_package_id', $package->id)
                    ->where('date_range_start', '<=', $timeSlotData->date)
                    ->where('date_range_end', '>=', $timeSlotData->date)
                    ->orderBy('priority', 'DESC')
                    ->first();

                $slotTimeId = 0;
                if (isset($standardRateDate) && $standardRateDate->apply_on_slots) {
                    $slotTimeId = $timeSlotData->id;
                }

                $customRateDate = isCustomDateRateEnabled($timeSlotData->date, $package->id, $affiliateIdForRate, $slotTimeId);
                /* END */

                $rates = DB::table('tour_package_rates as pkg_rate')
                    ->where([
                        'pkg_rate.tour_package_id' => $package->id,
                        'pkg_rate.deleted_by' => 0,
                        'pkg_rate.status' => 'Active'
                    ])
                    ->when($item['affiliate_id'] != 0 && !$isBlackoutDate, fn($query) => $query->where('pkg_rate.affiliate_id', $item['affiliate_id']), fn($query) => $query->whereNull('pkg_rate.affiliate_id'))
                    ->when($customRateDate, fn($query) => $query->where('pkg_rate.custom_date_id', $customRateDate), fn($query) => $query->whereNull('pkg_rate.custom_date_id'))
                    ->when($item['selectedRateId'], fn($query) => $query->where('pkg_rate.id', $item['selectedRateId']))
                    ->where('pkg_rate.scheduling_year', '=', date('Y', strtotime($timeSlotData->date)))
                    ->orderBy('pkg_rate.orderby')
                    ->get();

                $subTotal = 0;
                $applicationFeeAmount = 0;
                foreach ($rates as $key => $rateItem) {
                    if (isset($item['people_group'][$key]) && $item['people_group'][$key] > 0) {
                        $tourists = $item['people_group'][$key] ?? 0;
                        $partnerFeeAmount = $package->is_partner_package ? $rateItem->partner_fee_amount : 0;
                        if ($package->is_group_rate_enabled) {
                            // No permit fee calculated in group rate discount feature
                            $paxRate = $rateItem->rate + $rateItem->additional_charge + $partnerFeeAmount;
                            $subTotal += $paxRate;
                        } else {
                            $paxRate = $rateItem->rate + $rateItem->additional_charge + $rateItem->permit_fee + $partnerFeeAmount;
                            $subTotal += $tourists * $paxRate;
                        }

                        $serviceCommission = roundout($paxRate * $serviceCommissionPercentage / 100, 2);

                        if ($item['discount2_value'] == 0) {
                            if ($package->is_group_rate_enabled) {
                                $applicationFeeAmount += $serviceCommission;
                            } else {
                                $applicationFeeAmount += $tourists * $serviceCommission;
                            }
                        }
                    }
                }

                if ($item['discount2_value'] != 0) {
                    $applicationFeeAmount = roundout(($subTotal - $item['discount2_value']) * $serviceCommissionPercentage / 100, 2);
                }

                $tourTotal += ($subTotal - $item['discount2_value']) + $applicationFeeAmount + ($item['addons_total'] ?? 0) + ($item['addons_fee'] ?? 0);
                $tourApplicationFeeAmount += $applicationFeeAmount + ($item['addons_fee'] ?? 0);

                if ($i > 0) {
                    $description .= ', ';
                }
                $totalTourists = array_sum($item['people_group']) - $infants;

                $description .= "Tour Package: " . $package->name .
                    "\nTour Date: " . $timeSlotData->date .
                    "\nTour Time: " . $timeSlotData->time_from .
                    "\nTourists: " . $totalTourists;

                $data[] = [
                    'tour_date' => $timeSlotData->date,
                    'tour_package' => $package->name,
                    'tour_time' => $timeSlotData->time_from,
                    'tourists' => $totalTourists,
                    'amount' => $tourTotal,
                    'fee' => $applicationFeeAmount
                ];

                $i++;
            }

            $destination = $tourOperator->stripe_destination_id;

            $statementDescriptor = $tourOperator->statement_descriptor ?: substr(strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $tourOperator->name . ' ' . $package->name)), 0, 22);

            // Mixed-cart rule: any 2027+ item in this cart makes the whole charge platform-held.
            $mode = app(PaymentModeResolver::class)->resolve(array_column($data, 'tour_date'));

            $paymentIntentParams = [
                'payment_method_types'        => ['card'],
                'statement_descriptor_suffix' => $statementDescriptor,
                'currency'                    => currencyFormation(code: true),
                'amount'                      => $tourTotal * 100,
                'description'                 => StripeUtils::limitDescription($description),
                'metadata'                    => [
                    'from' => Constants::ONLINE,
                    'tenant_id' => tenant()->id,
                    // 'bookings' => json_encode($data),
                ], ...app(PaymentGatewayInterface::class)->buildConnectedPaymentData($destination, (int) round($tourApplicationFeeAmount * 100), mode: $mode)
            ];
            $paymentIntent = app(PaymentGatewayInterface::class)->createPayment($paymentIntentParams);

            $data = [
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id
            ];

            return response()->json($data, 200);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        } catch (Exception $exception) {
            Log::error($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Summary of verifyPayment
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function verifyPayment(Request $request)
    {
        Stripe::setApiKey(getStripeSecretKey());

        try {
            $paymentIntent = PaymentIntent::retrieve($request->paymentIntentId);

            if ($paymentIntent->status === 'succeeded') {
                // Update booking/payment status in database
                return response()->json([
                    'status' => 'succeeded',
                    'message' => 'Payment successful'
                ]);
            } elseif (isset($paymentIntent->review)) {
                return response()->json([
                    'status' => 'review_opened',
                    'message' => 'Payment is under manual review'
                ]);
            }

            return response()->json([
                'status' => $paymentIntent->status,
                'message' => 'Payment still processing'
            ]);
        } catch (Exception $e) {
            Log::error("Payment verification failed: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed'
            ], 500);
        }
    }

    /**
     * Prepare gratuity data for insertion
     *
     * @param array $item
     * @param object $bookingMaster
     * @param object $customer
     * @param Request $request
     */
    public function prepareGratuityData($item, $bookingMaster, $customer, $request)
    {
        $now = Carbon::now()->addSecond();

        $gratuity['gratuities'] = [
            'booking_id' => $bookingMaster->id,
            'gratuity_setting_id' => $item['gratuity_id'],
            'customer_id' => $customer->id,
            'gratuity_type' => 'pre',
            'amount' => $item['gratuity_price'],
            'fees' => $item['gratuity_fees'],
            'message' => $item['gratuity_message'],
            'payment_method' => 'Card',
            'transaction_number' => null,
            'application_fee_id' => null,
            'status' => 'paid',
            'created_by' => $customer->id,
            'created_by_user_type' => 'Customer',
            'created_at' => $now,
            'updated_at' => $now
        ];

        $gratuity['transactions'] = [
            'tour_booking_id' => $bookingMaster->id,
            'amount' => $item['gratuity_total'],
            'fee_amount' => $item['gratuity_fees'],
            'transaction_number' => null,
            'application_fee_id' => null,
            'via' => 'Card',
            'payment_type' => 0,
            'response_init' => '',
            'response_end' => '',
            'response_end_datetime' => $now,
            'charge_type' => 'tip',
            'status' => 'Completed',
            'visitor' => $request->getClientIp(),
            'request_from' => Constants::FRONTEND,
            'created_by' => $customer->id,
            'created_by_user_type' => 'Customer',
            'created_at' => $now
        ];

        $gratuity['tour_booking_history'] = [
            'tour_booking_id' => $bookingMaster->id,
            'transaction_id' => null,
            'ticket_cost' => $item['gratuity_price'],
            'platform_fees' => $item['gratuity_fees'],
            'card_last_4_digit' => null,
            'payment_type' => 'Card',
            'payment_method' => 'Full Payment',
            'operation' => 'Payment',
            'details' => json_encode([
                'booking_id' => $bookingMaster->id,
                'operation' => "added",
                'tip' => [
                    'amount' => $item['gratuity_price'],
                    'fees' => $item['gratuity_fees'],
                    'message' => $item['gratuity_message'] ?? '',
                    'status' => 'paid'
                ],
                'payment_type' => 'Card',
                'payment_method' => 'Full Payment',
                'card_number' => null,
                'card_type' => null,
                'amount' => $item['gratuity_total'],
                'fees' => $item['gratuity_fees'],
                'created_by_with_name' => "{$bookingMaster->name}: (Customer)"
            ], JSON_NUMERIC_CHECK),
            'is_processed' => 1,
            'charge_type' => 'tip',
            'operation_at' => mysqlDT(),
            'operation_by' => $customer->id,
            'operation_by_user_type' => 'Customer',
            'updated_at' => $now,
            'created_at' => $now,
        ];

        return $gratuity;
    }
}
